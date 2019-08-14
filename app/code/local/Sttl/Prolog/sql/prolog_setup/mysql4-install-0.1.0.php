<?php 
$installer = $this;
/* @var $installer Mage_Catalog_Model_Resource_Eav_Mysql4_Setup */

$installer->startSetup();
$conn = $installer->getConnection();

// add prolog product id to main product

$installer->addAttribute('catalog_product', 'prolog_product_id', array(
        'group'             		=> 'General',
        'type'              		=> 'varchar',
        'backend'           		=> '',
        'frontend'          		=> '',
        'label'             		=> 'Prolog Business Unit Product Id',
        'input'             		=> 'text',
        'class'             		=> '',
        'source'            		=> '',
        'is_global'         		=> Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible'           		=> true,
        'required'          		=> false,
        'user_defined'      		=> true,
        'searchable'        		=> false,
        'filterable'        		=> false,
        'comparable'        		=> false,
        'visible_on_front'  		=> false,
        'unique'            		=> false,
        'apply_to'          		=> 'simple,configurable,virtual,bundle,downloadable',
        'is_configurable'   		=> false,
        'position'   				=> 10,
		'used_in_product_listing'   => false
));

// add prolog line number to main product

$installer->addAttribute('catalog_product', 'prolog_line_number', array(
        'group'             		=> 'General',
        'type'              		=> 'varchar',
        'backend'           		=> '',
        'frontend'          		=> '',
        'label'             		=> 'Prolog Product#',
        'input'             		=> 'text',
        'class'             		=> '',
        'source'            		=> '',
        'is_global'         		=> Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible'           		=> true,
        'required'          		=> false,
        'user_defined'      		=> true,
        'searchable'        		=> false,
        'filterable'        		=> false,
        'comparable'        		=> false,
        'visible_on_front'  		=> false,
        'unique'            		=> false,
        'apply_to'          		=> 'simple,configurable,virtual,bundle,downloadable',
        'is_configurable'   		=> false,
        'position'   				=> 10,
		'used_in_product_listing'   => false
));


$installer->run("ALTER TABLE {$this->getTable('sales/order')}
                ADD COLUMN `prolog_order_id` varchar(50) default NULL;");

$installer->run("ALTER TABLE {$this->getTable('sales/quote')}
                ADD COLUMN `prolog_order_id` varchar(50) default NULL;");


$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

$setup->addAttribute('order', 'prolog_order_id', array(
        'label'    => 'prolog_order_id',
        'visible'  => true,
        'required' => false,
        'position'     => 1,
));
/*$setup->addAttribute('quote', 'prolog_order_id', array(
        'label'    => 'prolog_order_id',
        'visible'  => true,
        'required' => false,
        'position'     => 1,
));*/
$installer->setConfigData('prolog/general/prolog_url', 'clientws.prolog3pl.com');
$installer->endSetup();