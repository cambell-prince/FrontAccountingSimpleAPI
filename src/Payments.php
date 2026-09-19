<?php
namespace FAAPI;

$path_to_root = "../..";

include_once($path_to_root . "/sales/includes/db/payment_db.inc");
include_once($path_to_root . "/sales/includes/db/custalloc_db.inc");
include_once($path_to_root . "/gl/includes/db/gl_db_bank_accounts.inc");
include_once($path_to_root . "/includes/banking.inc");

/**
 * Customer payments. Wraps FrontAccounting's own write_customer_payment(),
 * the function behind the Customer Payments screen, so the payment lands in
 * the bank account, the GL and the customer ledger exactly as a manually
 * entered one does. Optionally allocates it against one document.
 */
class Payments
{
    /**
     * @SWG\Post(
     *   path="/payments",
     *   summary="Add a customer payment",
     *   tags={"payments"},
     *   operationId="addPayment",
     *   produces={"application/json"},
     *   @SWG\Response(response=201, description="successful operation"),
     *   deprecated=false
     * )
     */
    public function post($rest)
    {
        global $Refs;
        $model = $rest->request()->post();

        foreach (array('customer_id', 'branch_id', 'bank_account', 'amount') as $required) {
            \api_validate($required, $model);
        }
        \api_check('trans_date', $model, date2sql(new_doc_date()));
        \api_check('discount', $model, 0);
        \api_check('memo', $model, '');
        \api_check('ref', $model, '');

        $date = sql2date($model['trans_date']);
        $ref = $model['ref'] !== '' ? $model['ref'] : $Refs->get_next(ST_CUSTPAYMENT, null, $date);

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

        if (isset($model['allocate_to']) && is_array($model['allocate_to'])) {
            $type = (int) $model['allocate_to']['type'];
            $transNo = (int) $model['allocate_to']['trans_no'];
            add_cust_allocation($model['amount'], ST_CUSTPAYMENT, $paymentNo, $type, $transNo, $model['customer_id'], $date);
            update_debtor_trans_allocation(ST_CUSTPAYMENT, $paymentNo, $model['customer_id']);
            update_debtor_trans_allocation($type, $transNo, $model['customer_id']);
        }

        \api_create_response(array('id' => $paymentNo, 'reference' => $ref));
    }
}
