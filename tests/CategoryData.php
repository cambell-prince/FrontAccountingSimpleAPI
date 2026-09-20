<?php

// Shared by CategoryTest and CategoryJsonTest, which post the same category
// through the form-data and JSON paths respectively. A file of its own because
// each test class now lives in the file PHPUnit names it after, and a constant
// can only be declared once.

const CATEGORY_DATA = array(
    'description' => 'description',
    'dflt_tax_type' => '1',
    'dflt_units' => 'each',
    'dflt_mb_flag' => 'D',
    'dflt_sales_act' => '4010',
    'dflt_cogs_act' => '5010',
    'dflt_inventory_act' => '1510',
    'dflt_adjustment_act' => '5040',
    'dflt_wip_act' => '1530',
    'dflt_dim1' => '0',
    'dflt_dim2' => '0',
    'inactive' => '0',
    'dflt_no_sale' => '0',
    'dflt_no_purchase' => '0',
);
