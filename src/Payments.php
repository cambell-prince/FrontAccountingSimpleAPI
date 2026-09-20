<?php
namespace FAAPI;

$path_to_root = "../..";

include_once($path_to_root . "/sales/includes/db/payment_db.inc");
include_once($path_to_root . "/sales/includes/db/custalloc_db.inc");
include_once($path_to_root . "/sales/includes/db/cust_trans_db.inc");
include_once($path_to_root . "/sales/includes/db/customers_db.inc");
include_once($path_to_root . "/sales/includes/db/branches_db.inc");
include_once($path_to_root . "/gl/includes/db/gl_db_bank_accounts.inc");
include_once($path_to_root . "/admin/db/voiding_db.inc");
include_once($path_to_root . "/includes/banking.inc");

/**
 * Customer payments.
 *
 * Wraps FrontAccounting's own write_customer_payment() — the function behind
 * the Customer Payments screen — so a payment lands in the bank account, the
 * general ledger and the customer ledger exactly as a manually entered one
 * does, and void_transaction() to reverse it.
 *
 * @SWG\Definition(
 *   definition="Payment",
 *   type="object",
 *   description="A customer payment",
 *   @SWG\Property(property="id", type="integer", example="1",
 *     description="Transaction number of the payment"
 *   ),
 *   @SWG\Property(property="reference", type="string", example="1",
 *     description="Human readable reference, assigned automatically when not supplied"
 *   )
 * )
 */
class Payments
{
    /**
     * @SWG\Post(
     *     path="/payments",
     *     summary="Record a customer payment",
     *     description="Records a payment against a customer branch and bank account, optionally allocating it to one existing document.",
     *     tags={"payments"},
     *     operationId="addPayment",
     *     produces={"application/json"},
     *     @SWG\Parameter(name="customer_id", in="formData", type="integer", required=true, description="Customer to credit"),
     *     @SWG\Parameter(name="branch_id", in="formData", type="integer", required=true, description="Branch of that customer"),
     *     @SWG\Parameter(name="bank_account", in="formData", type="integer", required=true, description="Bank account receiving the money"),
     *     @SWG\Parameter(name="amount", in="formData", type="number", required=true, description="Amount received, greater than zero"),
     *     @SWG\Parameter(name="trans_date", in="formData", type="string", required=false, description="ISO8601 date (e.g. 2013-12-31). Defaults to today, and must fall inside an open fiscal year."),
     *     @SWG\Parameter(name="discount", in="formData", type="number", required=false, description="Discount given, defaults to 0"),
     *     @SWG\Parameter(name="memo", in="formData", type="string", required=false),
     *     @SWG\Parameter(name="ref", in="formData", type="string", required=false, description="Defaults to the next customer payment reference"),
     *     @SWG\Response(response=201, description="Payment recorded", @SWG\Schema(ref="#/definitions/Payment")),
     *     @SWG\Response(response=400, description="A value in the request cannot be used"),
     *     @SWG\Response(response=412, description="A required value is missing"),
     *     deprecated=false
     * )
     */
    public function post($rest)
    {
        global $Refs;

        $model = $rest->request()->post();

        foreach (array('customer_id', 'branch_id', 'bank_account', 'amount') as $required) {
            \api_validate($required, $model);
        }
        \api_check('trans_date', $model, date('Y-m-d'));
        \api_check('discount', $model, 0);
        \api_check('memo', $model, '');
        \api_check('ref', $model, '');

        \api_validate('trans_date', $model, 400, 'api_validate_date');

        if (!is_numeric($model['amount']) || $model['amount'] <= 0) {
            \api_error(400, 'The amount must be a number greater than zero');
        }
        if (!is_numeric($model['discount']) || $model['discount'] < 0) {
            \api_error(400, 'The discount must be a number of zero or more');
        }

        // Checked here so the caller is told what is wrong. FrontAccounting
        // reports a bad reference by raising E_USER_ERROR, which output_html()
        // then strips out of the response — leaving a 200 with a fragment of
        // page HTML and no explanation at all.
        if (!get_customer($model['customer_id'])) {
            \api_error(400, "No customer with id '{$model['customer_id']}'");
        }
        if (!$this->branchBelongsToCustomer($model['branch_id'], $model['customer_id'])) {
            \api_error(400, "No branch with id '{$model['branch_id']}' for customer '{$model['customer_id']}'");
        }
        if (!get_bank_account($model['bank_account'])) {
            \api_error(400, "No bank account with id '{$model['bank_account']}'");
        }

        // date_ is in the user's display format from here on; the API speaks
        // ISO8601, and sql2date() is the conversion because the two agree on
        // Y-m-d.
        $date = sql2date($model['trans_date']);

        if (!is_date_in_fiscalyears($date, false)) {
            \api_error(400, "The date '{$model['trans_date']}' is not inside an open fiscal year");
        }

        $ref = $model['ref'] !== '' ? $model['ref'] : $Refs->get_next(ST_CUSTPAYMENT, null, $date);

        $allocateTo = $this->validateAllocation($model);

        $paymentNo = write_customer_payment(
            0,
            $model['customer_id'],
            $model['branch_id'],
            $model['bank_account'],
            $date,
            $ref,
            $model['amount'],
            $model['discount'],
            $model['memo']
        );

        if ($allocateTo) {
            add_cust_allocation(
                $model['amount'],
                ST_CUSTPAYMENT,
                $paymentNo,
                $allocateTo['type'],
                $allocateTo['trans_no'],
                $model['customer_id'],
                $date
            );
            update_debtor_trans_allocation(ST_CUSTPAYMENT, $paymentNo, $model['customer_id']);
            update_debtor_trans_allocation($allocateTo['type'], $allocateTo['trans_no'], $model['customer_id']);
        }

        \api_create_response(array('id' => $paymentNo, 'reference' => $ref));
    }

    /**
     * @SWG\Delete(
     *     path="/payments/{id}",
     *     summary="Void a customer payment",
     *     description="Reverses the payment through FrontAccounting's void_transaction(), which removes its ledger and bank entries and releases anything it was allocated to. The transaction number is kept, as voiding does in FrontAccounting.",
     *     tags={"payments"},
     *     operationId="voidPayment",
     *     produces={"application/json"},
     *     @SWG\Parameter(name="id", in="path", type="integer", required=true, description="Transaction number of the payment"),
     *     @SWG\Parameter(name="date", in="formData", type="string", required=false, description="ISO8601 date to void on. Defaults to the payment's own date, which is always inside a fiscal year."),
     *     @SWG\Parameter(name="memo", in="formData", type="string", required=false),
     *     @SWG\Response(response=200, description="Payment voided"),
     *     @SWG\Response(response=400, description="The payment cannot be voided"),
     *     @SWG\Response(response=404, description="No such payment"),
     *     deprecated=false
     * )
     */
    public function delete($rest, $id)
    {
        $model = $rest->request()->post();
        \api_check('memo', $model, '');

        $payment = $this->findTransaction($id, ST_CUSTPAYMENT);
        if (!$payment) {
            \api_error(404, "No customer payment with id '$id'");
        }
        if (get_voided_entry(ST_CUSTPAYMENT, $id) != null) {
            \api_error(400, "Customer payment '$id' has already been voided");
        }

        // The payment's own date by default: void_transaction() writes an audit
        // trail row on the date it is given, and that row's fiscal year cannot
        // be null. Today is not necessarily inside a fiscal year, but the date
        // the payment posted on always is.
        if (isset($model['date']) && $model['date'] !== '') {
            \api_validate('date', $model, 400, 'api_validate_date');
            $date = sql2date($model['date']);
            if (!is_date_in_fiscalyears($date, false)) {
                \api_error(400, "The date '{$model['date']}' is not inside an open fiscal year");
            }
        } else {
            $date = sql2date($payment['tran_date']);
        }

        // A string back means FrontAccounting refused — already voided, or
        // closed off by something downstream. That is the caller's problem to
        // fix, so it is a 400 rather than a server error.
        $message = void_transaction(ST_CUSTPAYMENT, $id, $date, $model['memo']);
        if ($message != null) {
            \api_error(400, $message);
        }

        \api_success_response(array('id' => (int)$id));
    }

    /**
     * A transaction, or null when there is none.
     *
     * Not get_customer_trans(): that calls display_db_error() and exit() when
     * the row is missing, which in an API means a 200 carrying a fragment of
     * FrontAccounting page HTML instead of an answer. A plain lookup can say
     * "no".
     *
     * @return array|null
     */
    private function findTransaction($transNo, $type)
    {
        $sql = "SELECT trans_no, type, debtor_no, tran_date, ov_amount, alloc FROM "
            . TB_PREF . "debtor_trans WHERE trans_no = " . db_escape($transNo)
            . " AND type = " . db_escape($type);
        $row = db_fetch(db_query($sql, 'could not look up the transaction'));

        return $row ? $row : null;
    }

    /**
     * Not get_branch(): it inner joins salesman, so a branch whose salesman is
     * 0 — which is what the customers endpoint creates — is invisible to it.
     * Checking the branch against the customer is the more useful test anyway.
     */
    private function branchBelongsToCustomer($branchId, $customerId)
    {
        $sql = "SELECT branch_code FROM " . TB_PREF . "cust_branch WHERE branch_code = "
            . db_escape($branchId) . " AND debtor_no = " . db_escape($customerId);

        return db_fetch(db_query($sql, 'could not look up the branch')) !== false;
    }

    /**
     * The optional allocate_to {type, trans_no}, checked before anything is
     * written: allocating to a document that does not exist would otherwise
     * leave a payment posted and silently unallocated.
     *
     * @return array|null
     */
    private function validateAllocation($model)
    {
        if (!isset($model['allocate_to'])) {
            return null;
        }
        if (!is_array($model['allocate_to'])
            || !isset($model['allocate_to']['type'])
            || !isset($model['allocate_to']['trans_no'])) {
            \api_error(400, "allocate_to needs a 'type' and a 'trans_no'");
        }

        $type = (int)$model['allocate_to']['type'];
        $transNo = (int)$model['allocate_to']['trans_no'];

        $target = $this->findTransaction($transNo, $type);
        if (!$target) {
            \api_error(400, "No transaction of type '$type' with trans_no '$transNo' to allocate to");
        }
        if ($target['debtor_no'] != $model['customer_id']) {
            \api_error(400, "Transaction '$transNo' belongs to another customer");
        }

        return array('type' => $type, 'trans_no' => $transNo);
    }
}
