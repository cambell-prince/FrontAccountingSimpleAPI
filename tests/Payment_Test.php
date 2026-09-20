<?php

use GuzzleHttp\Client;

require_once(__DIR__ . '/TestConfig.php');

require_once(TEST_PATH . '/TestEnvironment.php');

$path_to_root = SRC_PATH;
require_once(SRC_PATH . '/includes/types.inc');

/**
 * Customer payments.
 *
 * The payment itself has no read endpoint, so the proof that it landed is the
 * invoice it was allocated to: FrontAccounting keeps the allocated amount on
 * the invoice, so alloc going 0 -> 2 -> 0 across a payment and its void is the
 * money arriving and being taken back again.
 *
 * Dates are inside 2013 because the fixture's fiscal years are 2010-2013.
 */
class PaymentTest extends PHPUnit_Framework_TestCase
{
    /*
     * The requests that expect a rejection pass 'http_errors' => false.
     * TestEnvironment::client() means to switch exceptions off for every
     * request, but does it with Guzzle 3's 'request.options' => array(
     * 'exceptions' => false), which Guzzle 6 ignores - so a 4xx throws a
     * ClientException instead of returning a response to assert on.
     */

    const PAYMENT_DATE = '2013-02-05';

    private $client;

    private $customerId;

    private $branchId;

    public function setUp()
    {
        $this->client = TestEnvironment::client();
    }

    /**
     * A reference nothing else will have used.
     *
     * TestEnvironment::createId() is date('YmdHis'), and these tests run well
     * inside one second, so it collides on debtor_ref and on the invoice
     * reference - which FrontAccounting rejects, silently, as a 200.
     */
    private function uniqueRef()
    {
        return substr(uniqid(), -10);
    }

    /**
     * Create a customer and remember the ids FrontAccounting gave it.
     *
     * Deliberately not hard-coded: the fixture ships with no customers at all,
     * so ids depend on what ran first. Sales_Test assumes customer 2 and passes
     * only because other tests created one before it.
     */
    private function createCustomer($suffix)
    {
        $response = $this->client->post('/modules/api/customers/', array(
            'headers' => TestEnvironment::headers(),
            'form_params' => array(
                'name' => 'Payment Customer ' . $suffix,
                'debtor_ref' => 'PAY' . $suffix,
                'address' => 'address',
                'tax_id' => 'tax_id',
                'curr_code' => 'USD',
                'credit_status' => '1',
                'payment_terms' => '1',
                'discount' => '0',
                'pymt_discount' => '0',
                'credit_limit' => '1000',
                'sales_type' => '1',
                'notes' => 'notes'
            )
        ));
        $this->assertEquals('201', $response->getStatusCode(), 'Could not create the customer');
        $customer = json_decode($response->getBody());
        $this->customerId = $customer->debtor_no;

        $response = $this->client->get('/modules/api/customers/' . $this->customerId . '/branches/', array(
            'headers' => TestEnvironment::headers()
        ));
        $this->assertEquals('200', $response->getStatusCode());
        $branches = json_decode($response->getBody());
        $this->assertNotEmpty($branches, 'The customer was created without a branch');
        $this->branchId = $branches[0]->branch_code;
    }

    /**
     * Create a customer, an item and an invoice for 2.00, and return the
     * invoice's transaction number.
     */
    private function createInvoice($ref)
    {
        $this->createCustomer($ref);
        // stock_id is varchar(20), so the reference has to stay short.
        $stockId = 'PAY' . $ref;
        TestEnvironment::createItem($this->client, $stockId, 'Payment Item');

        $response = $this->client->post('/modules/api/sales/', array(
            'headers' => TestEnvironment::headers(),
            'form_params' => array(
                'trans_type' => ST_SALESINVOICE,
                'ref' => $ref,
                'comments' => 'payment test',
                'order_date' => '01/02/2013',
                'delivery_date' => '03/04/2013',
                'cust_ref' => 'cust_ref',
                'deliver_to' => 'deliver_to',
                'delivery_address' => 'delivery_address',
                'phone' => 'phone',
                'ship_via' => '0',
                'location' => 'DEF',
                'freight_cost' => '0',
                'customer_id' => $this->customerId,
                'branch_id' => $this->branchId,
                'sales_type' => '1',
                'dimension_id' => '0',
                'dimension2_id' => '0',
                'items' => array(
                    0 => array(
                        'stock_id' => $stockId,
                        'qty' => '1',
                        'price' => '2',
                        'discount' => '0',
                        'description' => 'description'
                    )
                ),
            )
        ));
        $this->assertEquals('200', $response->getStatusCode(), 'Could not create the invoice to pay');

        $response = $this->client->get('/modules/api/sales/' . ST_SALESINVOICE . '/', array(
            'headers' => TestEnvironment::headers()
        ));
        $invoices = json_decode($response->getBody());
        $this->assertNotEmpty($invoices, 'The invoice to pay was not created');

        return $invoices[0]->trans_no;
    }

    private function invoiceAllocated($transNo)
    {
        $response = $this->client->get('/modules/api/sales/' . ST_SALESINVOICE . '/', array(
            'headers' => TestEnvironment::headers()
        ));
        $this->assertEquals('200', $response->getStatusCode());
        foreach (json_decode($response->getBody()) as $invoice) {
            if ($invoice->trans_no == $transNo) {
                return $invoice->alloc;
            }
        }
        $this->fail("Invoice $transNo disappeared from the list");
    }

    /**
     * Post a payment allocated to an invoice, then void it. The invoice's
     * allocated amount is what proves each step actually happened.
     */
    public function testPaymentAllocateAndVoid_Ok()
    {
        $ref = $this->uniqueRef();
        $transNo = $this->createInvoice($ref);
        $this->assertEquals(0, $this->invoiceAllocated($transNo), 'The new invoice should be unallocated');

        // Add
        $response = $this->client->post('/modules/api/payments/', array(
            'headers' => TestEnvironment::headers(),
            'form_params' => array(
                'customer_id' => $this->customerId,
                'branch_id' => $this->branchId,
                'bank_account' => '1',
                'amount' => '2',
                'trans_date' => self::PAYMENT_DATE,
                'memo' => 'paid in full',
                'allocate_to' => array(
                    'type' => ST_SALESINVOICE,
                    'trans_no' => $transNo
                )
            )
        ));
        $this->assertEquals('201', $response->getStatusCode(), 'The payment was not created');

        $payment = json_decode($response->getBody());
        $this->assertNotNull($payment, 'The payment response was not JSON');
        $this->assertGreaterThan(0, $payment->id);
        $this->assertNotEmpty($payment->reference, 'A reference should be assigned when none is posted');

        // The money arrived and was applied to the invoice.
        $this->assertEquals(2, $this->invoiceAllocated($transNo), 'The payment was not allocated to the invoice');

        // Void
        $response = $this->client->delete('/modules/api/payments/' . $payment->id, array(
            'headers' => TestEnvironment::headers()
        ));
        $this->assertEquals('200', $response->getStatusCode(), 'The payment was not voided');
        $this->assertEquals($payment->id, json_decode($response->getBody())->id);

        // Voiding released the invoice again.
        $this->assertEquals(0, $this->invoiceAllocated($transNo), 'Voiding did not release the invoice');

        // Voiding twice is the caller's mistake, not a server error.
        $response = $this->client->delete('/modules/api/payments/' . $payment->id, array(
            'headers' => TestEnvironment::headers(),
            'http_errors' => false
        ));
        $this->assertEquals('400', $response->getStatusCode());
    }

    /**
     * A payment with no allocation still posts, and still gets a reference.
     */
    public function testPaymentWithoutAllocation_Ok()
    {
        $this->createCustomer($this->uniqueRef());

        $response = $this->client->post('/modules/api/payments/', array(
            'headers' => TestEnvironment::headers(),
            'form_params' => array(
                'customer_id' => $this->customerId,
                'branch_id' => $this->branchId,
                'bank_account' => '1',
                'amount' => '5.50',
                'trans_date' => self::PAYMENT_DATE,
                'ref' => 'PAY' . TestEnvironment::createId()
            )
        ));
        $this->assertEquals('201', $response->getStatusCode());

        $payment = json_decode($response->getBody());
        $this->assertGreaterThan(0, $payment->id);

        $response = $this->client->delete('/modules/api/payments/' . $payment->id, array(
            'headers' => TestEnvironment::headers()
        ));
        $this->assertEquals('200', $response->getStatusCode());
    }

    /**
     * Bad input is answered, not swallowed. Each of these used to come back as
     * a 200 carrying a fragment of FrontAccounting page HTML, which is what the
     * review of the earlier payments pull request objected to.
     */
    public function testPaymentValidation_Rejected()
    {
        $this->createCustomer($this->uniqueRef());
        $customer = $this->customerId;
        $branch = $this->branchId;

        $bad = array(
            'missing amount' => array(
                'params' => array('customer_id' => $customer, 'branch_id' => $branch, 'bank_account' => '1'),
                'status' => '412'
            ),
            'amount of zero' => array(
                'params' => array('customer_id' => $customer, 'branch_id' => $branch, 'bank_account' => '1', 'amount' => '0'),
                'status' => '400'
            ),
            'amount not a number' => array(
                'params' => array('customer_id' => $customer, 'branch_id' => $branch, 'bank_account' => '1', 'amount' => 'lots'),
                'status' => '400'
            ),
            'unknown customer' => array(
                'params' => array('customer_id' => '99999', 'branch_id' => $branch, 'bank_account' => '1', 'amount' => '1'),
                'status' => '400'
            ),
            'unknown bank account' => array(
                'params' => array('customer_id' => $customer, 'branch_id' => $branch, 'bank_account' => '99999', 'amount' => '1'),
                'status' => '400'
            ),
            'date outside any fiscal year' => array(
                'params' => array(
                    'customer_id' => $customer, 'branch_id' => $branch, 'bank_account' => '1',
                    'amount' => '1', 'trans_date' => '1999-01-01'
                ),
                'status' => '400'
            ),
            'date that is not ISO8601' => array(
                'params' => array(
                    'customer_id' => $customer, 'branch_id' => $branch, 'bank_account' => '1',
                    'amount' => '1', 'trans_date' => '05/02/2013'
                ),
                'status' => '400'
            ),
            'allocation to a document that does not exist' => array(
                'params' => array(
                    'customer_id' => $customer, 'branch_id' => $branch, 'bank_account' => '1',
                    'amount' => '1', 'trans_date' => self::PAYMENT_DATE,
                    'allocate_to' => array('type' => ST_SALESINVOICE, 'trans_no' => '99999')
                ),
                'status' => '400'
            ),
        );

        foreach ($bad as $why => $case) {
            $response = $this->client->post('/modules/api/payments/', array(
                'headers' => TestEnvironment::headers(),
                'http_errors' => false,
                'form_params' => $case['params']
            ));
            $this->assertEquals($case['status'], $response->getStatusCode(), "Expected a rejection for: $why");

            $body = json_decode($response->getBody());
            $this->assertNotNull($body, "The rejection for '$why' was not JSON");
            $this->assertNotEmpty($body->msg, "The rejection for '$why' carried no message");
        }
    }

    /**
     * Voiding something that was never there is a 404, not a 500 and not a
     * silent success.
     */
    public function testVoidUnknownPayment_NotFound()
    {
        $response = $this->client->delete('/modules/api/payments/99999', array(
            'headers' => TestEnvironment::headers(),
            'http_errors' => false
        ));
        $this->assertEquals('404', $response->getStatusCode());
    }
}
