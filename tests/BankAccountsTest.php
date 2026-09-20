<?php
use GuzzleHttp\Client;

require_once(__DIR__ . '/TestConfig.php');

require_once(TEST_PATH . '/TestEnvironment.php');
require_once(TEST_PATH . '/Crud_Base.php');

const BANKACCOUNT_POST_DATA = array(
    'account_code' => '123456',
    'account_type' => '0',
    'bank_account_name' => 'Bank Test Account',
    'bank_account_number' => '12-3456-789123-00',
    'bank_curr_code' => 'USD',
    'bank_name' => 'Bank Test Name',
    'bank_address' => 'Bank Test Address',
    'bank_charge_act' => '5690',
    'dflt_curr_act' => '0',
    'inactive' => '0'
);

class BankAccountsTest extends Crud_Base
{
    private $postData = BANKACCOUNT_POST_DATA;
    
    private $putData;
    
    public function __construct()
    {
        $this->putData = $this->postData;
        $this->putData['bank_account_name'] = 'Bank Test Account Edited';

        parent::__construct(
            '/modules/api/bankaccounts/',
            'id',
            $this->postData,
            $this->putData
        );
    }

    protected function checkGetAfterPost($result)
    {
        $expected = $this->fixExpectedType($this->postData, $result);
        $expected->last_reconciled_date = '0000-00-00 00:00:00';
        $expected->ending_reconcile_balance = '0';
        $result = $this->removeKeyProperty($result);
        $this->assertEquals($expected, $result, 'Failed GET after POST');
    }

    protected function checkGetAfterPut($result)
    {
        $expected = $this->fixExpectedType($this->putData, $result);
        $result = $this->removeKeyProperty($result);
        $expected->last_reconciled_date = '0000-00-00 00:00:00';
        $expected->ending_reconcile_balance = '0';
        $this->assertEquals($expected, $result, 'Failed GET after PUT');
    }

    // 	public function testCRUD_Ok();
}
