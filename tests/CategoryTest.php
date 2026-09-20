<?php

require_once(__DIR__ . '/TestConfig.php');

require_once(TEST_PATH . '/TestEnvironment.php');
require_once(TEST_PATH . '/Crud_Base.php');
require_once(TEST_PATH . '/CategoryData.php');

class CategoryTest extends Crud_Base
{
    private $postData = CATEGORY_DATA;

    private $putData;

    public function __construct()
    {
        $this->putData = $this->postData;
        $this->putData['description'] = 'other description';

        parent::__construct(
            '/modules/api/category/',
            'category_id',
            $this->postData,
            $this->putData
        );
    }

    // 	public function testCRUD_Ok();
}
