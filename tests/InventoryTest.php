<?php

use GuzzleHttp\Client;

require_once(__DIR__ . '/TestConfig.php');

require_once(TEST_PATH . '/TestEnvironment.php');
require_once(TEST_PATH . '/Crud_Base.php');

const INVENTORY_POST_DATA = array(
    'description' => 'description',
    'long_description' => 'long description',
    'category_id' => '1',
    'tax_type_id' => '1',
    'units' => 'ea',
    'mb_flag' => '0',
    'sales_account' => '1',
    'inventory_account' => '1',
    'cogs_account' => '1',
    'adjustment_account' => '1',
    'wip_account' => '1'
);

const INVENTORY_GET_DATA = array(
    'description' => 'description',
    'long_description' => 'long description',
    'category_id' => '1',
    'tax_type_id' => '1',
    'units' => 'ea',
    'mb_flag' => '0',
    'sales_account' => '1',
    'inventory_account' => '1',
    'cogs_account' => '1',
    'adjustment_account' => '1',
    'wip_account' => '1',
    'dimension_id' => '0',
    'dimension2_id' => '0',
    'purchase_cost' => '0',
    'last_cost' => '0',
    'material_cost' => '0',
    'labour_cost' => '0',
    'overhead_cost' => '0',
    'inactive' => '0',
    'no_sale' => '0',
    'no_purchase' => '0',
    'editable' => '1',
    'depreciation_method' => 'D',
    'depreciation_rate' => '100',
    'depreciation_factor' => '1',
    'depreciation_start' => '0000-00-00',
    'depreciation_date' => '0000-00-00',
    'fa_class_id' => '',
    'tax_type_name' => 'Regular',
);

class InventoryTest extends Crud_Base
{
    private $postData = INVENTORY_POST_DATA;
    
    private $putData;
    
    public function __construct()
    {
        // Note: The primary key needs to be provided by the client.
        // It is not an auto-increment property.
        $this->postData['stock_id'] = TestEnvironment::createId();
        $this->putData = $this->postData;
        $this->putData['description'] = 'new description';
        $this->putData['long_description'] = 'new long description';

        parent::__construct(
            '/modules/api/inventory/',
            'stock_id',
            $this->postData,
            $this->putData,
            INVENTORY_GET_DATA
        );
    }

    protected function checkGetAfterPost($result)
    {
        $expected = INVENTORY_GET_DATA;
        $expected['stock_id'] = $this->postData['stock_id'];
        $expected = $this->fixExpectedType($expected, $result);
        // $result = $this->removeKeyProperty($result);
        $this->assertEquals($expected, $result, 'Failed GET after POST');
    }

    protected function checkGetAfterPut($result)
    {
        $expected = INVENTORY_GET_DATA;
        $expected['stock_id'] = $this->putData['stock_id'];
        // Update the expected with the modifications that we PUT
        foreach ($this->putData as $key => $value) {
            if (isset($expected[$key])) {
                $expected[$key] = $value;
            }
        }
        $expected = $this->fixExpectedType($expected, $result);
        // $result = $this->removeKeyProperty($result);
        $this->assertEquals($expected, $result, 'Failed GET after PUT');
    }

    // 	public function testCRUD_Ok();
}
