<?php
/**
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the extension to newer
 * versions in the future. If you need personal customization please  contact us
 * on http://www.waterstone.pt for more information.
 *
 * @category    Waterstone
 * @package     Waterstone_Winvoice
 * @copyright   Copyright (c) 2014 Waterstone Consulting, Lda. (http://www.waterstone.pt)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

$installer = $this;

// Set the setup class
$setup = new Mage_Customer_Model_Entity_Setup('core_setup');
$setup->startSetup();

// Get customer entity data
$vCustomerEntityType = $setup->getEntityTypeId('customer');
$vCustAttributeSetId = $setup->getDefaultAttributeSetId($vCustomerEntityType);
$vCustAttributeGroupId = $setup->getDefaultAttributeGroupId($vCustomerEntityType, $vCustAttributeSetId);

// Set new attribute for the customer entity
$setup->removeAttribute('customer', 'wsclient');
$setup->addAttribute('customer', 'wsclient', array(
        'label'			=> 'Cliente Weo',
        'input' 		=> 'text',
        'type'  		=> 'varchar',
        'forms' 		=> array('customer_account_edit','customer_account_create','adminhtml_customer','checkout_register'),
        'required'		=> 0,
        'user_defined' 	=> 1,
));

// Add the attribute to the attribute group
$setup->addAttributeToGroup($vCustomerEntityType, $vCustAttributeSetId, $vCustAttributeGroupId, 'wsclient', 0);

// Define the forms where the atribute should be used
$oAttribute = Mage::getSingleton('eav/config')->getAttribute('customer', 'wsclient');
$oAttribute->setData('used_in_forms', array('customer_account_edit','customer_account_create','adminhtml_customer','checkout_register'));
$oAttribute->save();

// Add a new attribute to the order entity
$setup->addAttribute('order', 'wsinvoiceurl', array(
    'type'          	=> 'varchar',
    'label'         	=> 'Factura certificada',
    'visible'       	=> true,
    'required'      	=> false,
    'visible_on_front' 	=> true,
    'user_defined'  	=>  true
));

$setup->endSetup();
