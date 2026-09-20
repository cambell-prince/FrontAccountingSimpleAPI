<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/TestConfig.php');

require_once(TEST_PATH . '/TestEnvironment.php');

class GLOtherTest extends TestCase
{
    public function testAccountTypes_Ok()
    {
        $client = TestEnvironment::client();
        $response = $client->get('/modules/api/glaccounttypes', array(
            'headers' => TestEnvironment::headers()
        ));

        $this->assertEquals('200', $response->getStatusCode());
        $result = $response->getBody();
        $result = json_decode($result);

        $count = count($result);
        $this->assertTrue($count > 0, 'Count > 0');
        $expected = new stdClass();
        $expected->id = '1';
        $expected->name = 'Current Assets';
        $expected->class_id = '1';
        $expected->parent = '';
        $this->assertEquals($expected, $result[0]);
    }
}
