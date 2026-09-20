<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/TestConfig.php');

require_once(TEST_PATH . '/TestEnvironment.php');

class BankAccountsOtherTest extends TestCase
{
    public function testBankAccount_ReadAll_Ok()
    {
        $client = TestEnvironment::client();
        $response = $client->get('/modules/api/bankaccounts/', array(
            'headers' => TestEnvironment::headers()
        ));
        $this->assertEquals('200', $response->getStatusCode());
        $result = $response->getBody();
        $result = json_decode($result);

        $this->assertEquals(2, count($result));

        $expected = array();
        $expected[] = new stdClass();
        $expected[0]->account_code = '1060';
        $expected[0]->account_type = '0';
        $expected[0]->id = '1';
        $expected[0]->bank_account_name = 'Current account';
        $expected[0]->bank_name = 'N/A';
        $expected[0]->bank_account_number = 'N/A';
        $expected[0]->bank_curr_code = 'USD';
        $expected[0]->bank_address = '';
        $expected[0]->dflt_curr_act = '1';
        $expected[] = new stdClass();
        $expected[1]->account_code = '1065';
        $expected[1]->account_type = '3';
        $expected[1]->id = '2';
        $expected[1]->bank_account_name = 'Petty Cash account';
        $expected[1]->bank_name = 'N/A';
        $expected[1]->bank_account_number = 'N/A';
        $expected[1]->bank_curr_code = 'USD';
        $expected[1]->bank_address = '';
        $expected[1]->dflt_curr_act = '0';

        $this->assertEquals($expected, $result);
    }
}
