<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/TestConfig.php');

require_once(TEST_PATH . '/TestEnvironment.php');

class InventoryOtherTest extends TestCase
{
    public function testLocations_Ok()
    {
        $client = TestEnvironment::client();

        // List
        $response = $client->get('/modules/api/locations/', array(
            'headers' => TestEnvironment::headers()
        ));
        $this->assertEquals('200', $response->getStatusCode());
        $result = $response->getBody();
        $result = json_decode($result);

        $count0 = count($result);

        // Add
        $id = 'LOC';
        $response = $client->post('/modules/api/locations/', array(
            'headers' => TestEnvironment::headers(),
            'form_params' => array(
                'loc_code' => $id,
                'location_name' => 'Location Name'
            )
        ));
        $this->assertEquals('201', $response->getStatusCode());
        $result = $response->getBody();
        $result = json_decode($result);

        $this->assertEquals($id, $result->loc_code);
        $this->assertEquals('Location Name', $result->location_name);

        // List again
        $response = $client->get('/modules/api/locations/', array(
            'headers' => TestEnvironment::headers()
        ));
        $this->assertEquals('200', $response->getStatusCode());
        $result = $response->getBody();
        $result = json_decode($result);

        $count1 = count($result);
        $this->assertEquals($count0 + 1, $count1);
    }

    public function testItemCosts_Ok()
    {
        $client = TestEnvironment::client();

        // Add
        $id = TestEnvironment::createId();
        $response = $client->post('/modules/api/inventory/', array(
            'headers' => TestEnvironment::headers(),
            'form_params' => array(
                'stock_id' => $id,
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
            )
        ));
        $this->assertEquals('201', $response->getStatusCode());

        // Read Item Cost
        $response = $client->get('/modules/api/itemcosts/' . $id, array(
            'headers' => TestEnvironment::headers()
        ));
        $this->assertEquals('200', $response->getStatusCode());
        $result = $response->getBody();
        $result = json_decode($result);

        $expected = new stdClass();
        $expected->stock_id = $id;
        $expected->unit_cost = '0';

        $this->assertEquals($expected, $result);

        // Write Item Cost
        $response = $client->put('/modules/api/itemcosts/' . $id, array(
            'headers' => TestEnvironment::headers(),
            'form_params' => array(
                'material_cost' => '1',
                'labour_cost' => '2',
                'overhead_cost' => '3'
            )
        ));
        $this->assertEquals('200', $response->getStatusCode());
        $result = $response->getBody();
        $result = json_decode($result);

        $expected = new stdClass();
        $expected->stock_id = $id;

        $this->assertEquals($expected, $result);

        // Read Item Cost again
        $response = $client->get('/modules/api/itemcosts/' . $id, array(
            'headers' => TestEnvironment::headers()
        ));
        $this->assertEquals('200', $response->getStatusCode());
        $result = $response->getBody();
        $result = json_decode($result);

        $expected = new stdClass();
        $expected->stock_id = $id;
        $expected->unit_cost = '1';

        $this->assertEquals($expected, $result);
    }
}
