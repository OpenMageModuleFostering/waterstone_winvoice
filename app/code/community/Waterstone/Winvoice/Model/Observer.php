<?php

class Waterstone_Winvoice_Model_Observer
{
	public function adminhtmlWidgetContainerHtmlBefore(Varien_Event_Observer $observer)
    {

        //Create creditmemo instance and check if creditmemo exists, if so create print the button
        
        // Check if module is enabled on Admin->Configuration->Advanced->Advanced page.
        if (Mage::helper('core')->isModuleOutputEnabled('Waterstone_Winvoice')) {
            $block = $observer->getBlock();
            if ($block instanceof Mage_Adminhtml_Block_Sales_Order_Creditmemo_View) {
                if ($block->getCreditmemo()->getIncrementId()) {
	                $block->addButton('creditPrint', array(
	                'label'     => Mage::helper('sales')->__('Imprimir Nota de Crédito'),
	                'class'     => 'save',
                    'style'     => '',
	                'onclick'   => 'setLocation(\''.$block->getCreditmemo()->getWscreditmemourl().'\')'
                    )
                );
                }
            }
        }
    }


}