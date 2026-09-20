<?php

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/TestConfig.php');

require_once(TEST_PATH . '/TestEnvironment.php');

$path_to_root = SRC_PATH;
require_once(SRC_PATH . '/includes/types.inc');

/**
 * Credit notes (trans_type 11) posted through POST /sales/.
 *
 * A credit note is the one sales document the API writes straight to
 * debtor_trans: an invoice is written by way of a sales order, which FA reads
 * back before the invoice row is built, and that read is what fills in the
 * cart's tax_included and price factor. Nothing fills them in for a credit
 * note, so whatever sales_add() puts on the cart is what gets written.
 *
 * Dates are inside 2013 because the fixture's fiscal years are 2010-2013.
 *
 * The file is named to sort after Customer_Test and Inventory_Test: the suite
 * shares one database and runs in filename order, and those two assert that
 * adding one record makes the list one longer - which only holds while the
 * list is shorter than RESULTS_PER_PAGE (2). The customers and items created
 * here would push them over it.
 */
class SalesCreditNoteTest extends TestCase
{
    const ORDER_DATE = '01/02/2013';

    const DELIVERY_DATE = '03/04/2013';

    /** Tax-inclusive sales type 'Retail' in the fixture. */
    const SALES_TYPE_INCLUSIVE = 1;

    /** Tax-exclusive sales type 'Wholesale' in the fixture. */
    const SALES_TYPE_EXCLUSIVE = 2;

    /** 'Accounts Receivables', the branch's receivables account. */
    const RECEIVABLES = '1200';

    /** 'Sales Tax', where the 5% tax type posts. */
    const SALES_TAX = '2150';

    private $client;

    private $customerId;

    private $branchId;

    private $stockId;

    public function setUp(): void
    {
        $this->client = TestEnvironment::client();
    }

    /**
     * A reference nothing else will have used.
     *
     * TestEnvironment::createId() is date('YmdHis') and these tests run well
     * inside one second, so it collides on debtor_ref and on the document
     * reference - which FrontAccounting rejects silently, as a 200.
     */
    private function uniqueRef()
    {
        return substr(uniqid(), -10);
    }

    /**
     * Create the customer, branch and item one credit note needs.
     *
     * Ids are read back rather than assumed: the fixture ships with no
     * customers at all, so they depend on what ran before.
     */
    private function createCustomerAndItem($suffix)
    {
        $response = $this->client->post('/modules/api/customers/', array(
            'headers' => TestEnvironment::headers(),
            'form_params' => array(
                'name' => 'Credit Note Customer ' . $suffix,
                'debtor_ref' => 'CN' . $suffix,
                'address' => 'address',
                'tax_id' => 'tax_id',
                'curr_code' => 'USD',
                'credit_status' => '1',
                'payment_terms' => '1',
                'discount' => '0',
                'pymt_discount' => '0',
                'credit_limit' => '1000',
                'sales_type' => self::SALES_TYPE_INCLUSIVE,
                'notes' => 'notes'
            )
        ));
        $this->assertEquals('201', $response->getStatusCode(), 'Could not create the customer');
        $this->customerId = json_decode($response->getBody())->debtor_no;

        $response = $this->client->get('/modules/api/customers/' . $this->customerId . '/branches/', array(
            'headers' => TestEnvironment::headers()
        ));
        $this->assertEquals('200', $response->getStatusCode());
        $branches = json_decode($response->getBody());
        $this->assertNotEmpty($branches, 'The customer was created without a branch');
        $this->branchId = $branches[0]->branch_code;

        // stock_id is varchar(20), so the suffix has to stay short.
        $this->stockId = 'CN' . $suffix;
        TestEnvironment::createItem($this->client, $this->stockId, 'Credit Note Item');
    }

    /**
     * Post one credit note for one line of $price, and return the response.
     *
     * A $salesType of null posts none at all.
     *
     * 'http_errors' is off per request: TestEnvironment::client() means to
     * switch Guzzle's exceptions off for every one, but does it with Guzzle 3's
     * 'request.options' => array('exceptions' => false), which Guzzle 7
     * ignores - so a 4xx throws instead of returning a response to assert on.
     */
    private function postCreditNote($ref, $salesType, $price, $options = array())
    {
        $params = array_merge(array(
            'trans_type' => ST_CUSTCREDIT,
            'ref' => $ref,
            'comments' => 'credit note test',
            'order_date' => self::ORDER_DATE,
            'delivery_date' => self::DELIVERY_DATE,
            'cust_ref' => 'cust_ref',
            'deliver_to' => 'deliver_to',
            'delivery_address' => 'delivery_address',
            'phone' => 'phone',
            'ship_via' => '0',
            'location' => 'DEF',
            'freight_cost' => '0',
            'customer_id' => $this->customerId,
            'branch_id' => $this->branchId,
            'sales_type' => $salesType,
            'dimension_id' => '0',
            'dimension2_id' => '0',
            'items' => array(
                0 => array(
                    'stock_id' => $this->stockId,
                    'qty' => '1',
                    'price' => $price,
                    'discount' => '0',
                    'description' => 'description'
                )
            ),
        ), $options);

        if ($salesType === null) {
            unset($params['sales_type']);
        }

        return $this->client->post('/modules/api/sales/', array(
            'headers' => TestEnvironment::headers(),
            'http_errors' => false,
            'form_params' => $params
        ));
    }

    /**
     * Edit one credit note, and return the response.
     *
     * A $salesType of null sends none at all.
     */
    private function putCreditNote($transNo, $ref, $salesType, $comments)
    {
        $params = array(
            'trans_type' => ST_CUSTCREDIT,
            'ref' => $ref,
            'comments' => $comments,
            'order_date' => self::ORDER_DATE,
            'delivery_date' => self::DELIVERY_DATE,
            'cust_ref' => 'cust_ref',
            'deliver_to' => 'deliver_to',
            'delivery_address' => 'delivery_address',
            'phone' => 'phone',
            'ship_via' => '0',
            'location' => 'DEF',
            'freight_cost' => '0',
            'customer_id' => $this->customerId,
            'branch_id' => $this->branchId,
            'sales_type' => $salesType,
            'dimension_id' => '0',
            'dimension2_id' => '0',
        );

        if ($salesType === null) {
            unset($params['sales_type']);
        }

        return $this->client->put('/modules/api/sales/' . $transNo . '/' . ST_CUSTCREDIT, array(
            'headers' => TestEnvironment::headers(),
            'http_errors' => false,
            'form_params' => $params
        ));
    }

    /**
     * The transaction number FrontAccounting gave the credit note it just
     * wrote, taken from the message the API answers with.
     *
     * That message is the only handle on it: POST /sales/ answers with FA's
     * own wording rather than the document, and the list endpoint is capped at
     * RESULTS_PER_PAGE (2), so a document cannot be found there by reference.
     * Asserting the wording is the point as much as the number - a write FA
     * refuses comes back as a 200 carrying a fragment of its error page, so a
     * status check alone passes while nothing has been written.
     */
    private function assertPosted($response)
    {
        $this->assertEquals('200', $response->getStatusCode());
        $body = trim((string)$response->getBody());
        $this->assertMatchesRegularExpression('/^Credit Note # \d+ has been processed\.$/', $body);

        preg_match('/\d+/', $body, $matches);
        return $matches[0];
    }

    /**
     * The trial balance for the fixture's 2013 fiscal year.
     */
    private function trialBalance()
    {
        $response = $this->client->get('/modules/api/glquery/trialbalance/2013-01-01/2013-12-31/', array(
            'headers' => TestEnvironment::headers()
        ));
        $this->assertEquals('200', $response->getStatusCode());
        return json_decode($response->getBody())->accounts;
    }

    /**
     * What one account was credited between two trial balances - negative for
     * a debit. The suite shares one database, so only the movement one posting
     * made says anything.
     */
    private function credited($before, $after, $account)
    {
        $movement = function ($balance) use ($account) {
            if (!isset($balance->{$account})) {
                return 0;
            }
            return $balance->{$account}->end_credit - $balance->{$account}->end_debit;
        };
        return $movement($after) - $movement($before);
    }

    /**
     * Read one credit note back.
     */
    private function creditNote($transNo)
    {
        $response = $this->client->get('/modules/api/sales/' . $transNo . '/' . ST_CUSTCREDIT, array(
            'headers' => TestEnvironment::headers()
        ));
        $this->assertEquals('200', $response->getStatusCode(), "Credit note $transNo could not be read back");
        return json_decode($response->getBody());
    }

    /**
     * Posting a credit note writes it, and it reads back as it was posted.
     */
    public function testCreditNotePostedOk()
    {
        $ref = $this->uniqueRef();
        $this->createCustomerAndItem($ref);

        $transNo = $this->assertPosted($this->postCreditNote($ref, self::SALES_TYPE_INCLUSIVE, '2'));

        $note = $this->creditNote($transNo);
        $this->assertEquals($ref, $note->ref);
        $this->assertEquals($this->customerId, $note->customer_id);
        $this->assertEquals($this->branchId, $note->branch_id);
        $this->assertEquals(self::SALES_TYPE_INCLUSIVE, $note->sales_type);
        $this->assertCount(1, $note->line_items);
        $this->assertEquals($this->stockId, $note->line_items[0]->stock_id);
        $this->assertEquals(2, (float)$note->line_items[0]->price);
    }

    /**
     * A tax inclusive sales type credits the customer the price as posted.
     *
     * 'Retail' is tax inclusive, so the 2.00 already contains the 5% tax: the
     * customer is credited 2.00 and 0.10 of it is tax.
     *
     * The assertion is on the general ledger rather than on the document,
     * because reading a document back takes the tax treatment from the sales
     * type again (get_customer_trans joins sales_types), so it agrees with
     * itself whatever was written. The ledger is what the cart actually wrote.
     */
    public function testTaxInclusiveSalesTypeCreditsThePriceAsPosted()
    {
        $suffix = $this->uniqueRef();
        $this->createCustomerAndItem($suffix);

        $before = $this->trialBalance();
        $transNo = $this->assertPosted(
            $this->postCreditNote('I' . $suffix, self::SALES_TYPE_INCLUSIVE, '2')
        );
        $after = $this->trialBalance();

        $this->assertEqualsWithDelta(2, $this->credited($before, $after, self::RECEIVABLES), 0.001);
        $this->assertEqualsWithDelta(0.1, -$this->credited($before, $after, self::SALES_TAX), 0.001);
        $this->assertEquals(2, $this->creditNote($transNo)->display_total);
    }

    /**
     * A tax exclusive sales type adds the tax on top of the price.
     *
     * 'Wholesale' is not tax inclusive, so the same 2.00 line credits the
     * customer 2.10, of which 0.10 is tax. Taking tax_included from the sales
     * type is the only thing that tells this apart from the Retail case: a
     * cart left without it cannot be written at all, and one given a fixed
     * value gets one of the two wrong.
     */
    public function testTaxExclusiveSalesTypeAddsTaxToThePrice()
    {
        $suffix = $this->uniqueRef();
        $this->createCustomerAndItem($suffix);

        $before = $this->trialBalance();
        $transNo = $this->assertPosted(
            $this->postCreditNote('E' . $suffix, self::SALES_TYPE_EXCLUSIVE, '2')
        );
        $after = $this->trialBalance();

        $this->assertEqualsWithDelta(2.1, $this->credited($before, $after, self::RECEIVABLES), 0.001);
        $this->assertEqualsWithDelta(0.1, -$this->credited($before, $after, self::SALES_TAX), 0.001);
        $this->assertEquals(2.1, $this->creditNote($transNo)->display_total);
    }

    /**
     * Changing the sales type of a credit note changes its tax treatment.
     *
     * Editing rewrites the document, so a note first posted under tax
     * inclusive 'Retail' and then moved to tax exclusive 'Wholesale' has to
     * credit the customer 0.10 more than it did - the 2.00 line now carries
     * the tax on top. sales_edit() assigning ->sales_type by itself leaves the
     * cart with the tax treatment it was read from the database with.
     */
    public function testEditedCreditNoteFollowsTheNewSalesType()
    {
        $suffix = $this->uniqueRef();
        $this->createCustomerAndItem($suffix);
        $transNo = $this->assertPosted(
            $this->postCreditNote($suffix, self::SALES_TYPE_INCLUSIVE, '2')
        );

        $before = $this->trialBalance();
        $response = $this->putCreditNote($transNo, $suffix, self::SALES_TYPE_EXCLUSIVE, 'now wholesale');
        $after = $this->trialBalance();

        $this->assertEquals('200', $response->getStatusCode());
        $this->assertMatchesRegularExpression(
            '/^Credit # \d+ has been updated\.$/',
            trim((string)$response->getBody())
        );

        // 2.10 credited in place of the 2.00 the note was written with.
        $this->assertEqualsWithDelta(0.1, $this->credited($before, $after, self::RECEIVABLES), 0.001);
        // The tax itself does not move: 0.10 either way, inside the price or on top of it.
        $this->assertEqualsWithDelta(0, $this->credited($before, $after, self::SALES_TAX), 0.001);

        $note = $this->creditNote($transNo);
        $this->assertEquals(self::SALES_TYPE_EXCLUSIVE, $note->sales_type);
        $this->assertEquals(2.1, $note->display_total);
    }

    /**
     * A sales type that does not exist is answered when editing too, and the
     * note is left as it was.
     *
     * sales_edit() takes the sales type through the same lookup as sales_add(),
     * so an unknown one is a 400 and not a rewrite with whatever tax treatment
     * the cart happened to hold.
     */
    public function testEditWithUnknownSalesTypeRejected()
    {
        $suffix = $this->uniqueRef();
        $this->createCustomerAndItem($suffix);
        $transNo = $this->assertPosted(
            $this->postCreditNote($suffix, self::SALES_TYPE_INCLUSIVE, '2')
        );

        $before = $this->trialBalance();
        $response = $this->putCreditNote($transNo, $suffix, '99999', 'unknown sales type');
        $after = $this->trialBalance();

        $this->assertEquals('400', $response->getStatusCode());
        $body = json_decode($response->getBody());
        $this->assertNotNull($body, 'The rejection was not JSON');
        $this->assertNotEmpty($body->msg, 'The rejection carried no message');
        $this->assertEquals(0, $this->credited($before, $after, self::RECEIVABLES), 'Nothing should have been rewritten');

        $note = $this->creditNote($transNo);
        $this->assertEquals(self::SALES_TYPE_INCLUSIVE, $note->sales_type);
        $this->assertEquals(2, $note->display_total);
    }

    /**
     * An edit that does not mention the sales type keeps the one the note has.
     *
     * The sales type is only put on the cart when the request carries one, so
     * a note posted under tax inclusive 'Retail' and edited without it is
     * still credited 2.00, not left without a tax treatment.
     */
    public function testEditWithoutSalesTypeKeepsTheOneTheNoteHas()
    {
        $suffix = $this->uniqueRef();
        $this->createCustomerAndItem($suffix);
        $transNo = $this->assertPosted(
            $this->postCreditNote($suffix, self::SALES_TYPE_INCLUSIVE, '2')
        );

        $before = $this->trialBalance();
        $response = $this->putCreditNote($transNo, $suffix, null, 'comment only');
        $after = $this->trialBalance();

        $this->assertEquals('200', $response->getStatusCode());
        $this->assertMatchesRegularExpression(
            '/^Credit # \d+ has been updated\.$/',
            trim((string)$response->getBody())
        );
        $this->assertEqualsWithDelta(0, $this->credited($before, $after, self::RECEIVABLES), 0.001);

        $note = $this->creditNote($transNo);
        $this->assertEquals(self::SALES_TYPE_INCLUSIVE, $note->sales_type);
        $this->assertEquals(2, $note->display_total);
    }
    /**
     * A sales type that does not exist is answered, not written.
     *
     * Without the sales type there is nothing to take the tax treatment from,
     * and the write fails inside FrontAccounting - which answers a 200 carrying
     * a page fragment. The caller is told instead, and nothing is posted.
     */
    public function testUnknownSalesTypeRejected()
    {
        $suffix = $this->uniqueRef();
        $this->createCustomerAndItem($suffix);

        $before = $this->trialBalance();
        $response = $this->postCreditNote($suffix, '99999', '2');
        $after = $this->trialBalance();

        $this->assertEquals('400', $response->getStatusCode());
        $body = json_decode($response->getBody());
        $this->assertNotNull($body, 'The rejection was not JSON');
        $this->assertNotEmpty($body->msg, 'The rejection carried no message');
        $this->assertEquals(0, $this->credited($before, $after, self::RECEIVABLES), 'Nothing should have been posted');
    }

    /**
     * A credit note with no sales type at all is a missing required property.
     */
    public function testMissingSalesTypeRejected()
    {
        $suffix = $this->uniqueRef();
        $this->createCustomerAndItem($suffix);

        $before = $this->trialBalance();
        $response = $this->postCreditNote($suffix, null, '2');
        $after = $this->trialBalance();

        $this->assertEquals('412', $response->getStatusCode());
        $body = json_decode($response->getBody());
        $this->assertNotNull($body, 'The rejection was not JSON');
        $this->assertNotEmpty($body->msg, 'The rejection carried no message');
        $this->assertEquals(0, $this->credited($before, $after, self::RECEIVABLES), 'Nothing should have been posted');
    }
}
