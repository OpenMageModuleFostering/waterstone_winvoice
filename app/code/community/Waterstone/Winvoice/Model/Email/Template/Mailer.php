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

class Waterstone_Winvoice_Model_Email_Template_Mailer extends Mage_Core_Model_Email_Template_Mailer
{

    /**
     * List of email infos
     * @see Mage_Core_Model_Email_Info
     *
     * @since 1.0.0
     *
     * @var array
     */
    protected $_emailInfos = array();

    /**
     * Email template
     *
     * @since 1.0.0
     *
     * @var Waterstone_Winvoice_Model_Email_Template
     */
    protected $emailTemplate;

    /**
     * Add email info method.
     * - Add new email info to corresponding list
     *
     * @since 1.0.0
     *
     * @param Mage_Core_Model_Email_Info $emailInfo
     * @return Waterstone_Winvoice_Model_Email_Template_Mailer
     */
    public function addEmailInfo(Mage_Core_Model_Email_Info $emailInfo)
    {
        array_push($this->_emailInfos, $emailInfo);

        return $this;
    }

    /**
     * Send method.
     * - Send all emails from email list
     * @see self::$_emailInfos
     *
     * @since 1.0.0
     *
     * @return Waterstone_Winvoice_Model_Email_Template_Mailer
     */
    public function send()
    {
        if ($this->emailTemplate) {
            $emailTemplate = $this->emailTemplate;
        } else {
            $emailTemplate = Mage::getModel('core/email_template');
        }

        // Send all emails from corresponding list
        while (!empty($this->_emailInfos)) {

            $emailInfo = array_pop($this->_emailInfos);

            // Handle "Bcc" recepients of the current email
            $emailTemplate->addBcc($emailInfo->getBccEmails());

            // Set required design parameters and delegate email sending to Mage_Core_Model_Email_Template
            $emailTemplate->setDesignConfig(array('area' => 'frontend', 'store' => $this->getStoreId()))->sendTransactional(
                $this->getTemplateId(),
                $this->getSender(),
                $emailInfo->getToEmails(),
                $emailInfo->getToNames(),
                $this->getTemplateParams(),
                $this->getStoreId()
            );
        }

        return $this;
    }

    /**
     * Set sender method.
     * - Set email sender.
     *
     * @since 1.0.0
     *
     * @param string|array $sender
     * @return Waterstone_Winvoice_Model_Email_Template_Mailer
     */
    public function setSender($sender)
    {
        return $this->setData('sender', $sender);
    }

    /**
     * Get sender method.
     * - Get email sender.
     *
     * @since 1.0.0
     *
     * @return string|array|null
     */
    public function getSender()
    {
        return $this->_getData('sender');
    }

    /**
     * Set store id method.
     *
     * @since 1.0.0
     *
     * @param int $storeId
     * @return Waterstone_Winvoice_Model_Email_Template_Mailer
     */
    public function setStoreId($storeId)
    {
        return $this->setData('store_id', $storeId);
    }

    /**
     * Get store id method.
     *
     * @since 1.0.0
     *
     * @return int|null
     */
    public function getStoreId()
    {
        return $this->_getData('store_id');
    }

    /**
     * Set template id method.
     *
     * @since 1.0.0
     *
     * @param int $templateId
     * @return Waterstone_Winvoice_Model_Email_Template_Mailer
     */
    public function setTemplateId($templateId)
    {
        return $this->setData('template_id', $templateId);
    }

    /**
     * Get template id method.
     *
     * @since 1.0.0
     *
     * @return int|null
     */
    public function getTemplateId()
    {
        return $this->_getData('template_id');
    }

    /**
     * Set template parameters method.
     *
     * @since 1.0.0
     *
     * @param array $templateParams
     * @return Waterstone_Winvoice_Model_Email_Template_Mailer
     */
    public function setTemplateParams(array $templateParams)
    {
        return $this->setData('template_params', $templateParams);
    }

    /**
     * Get template parameters method.
     *
     * @since 1.0.0
     *
     * @return array|null
     */
    public function getTemplateParams()
    {
        return $this->_getData('template_params');
    }

    /**
     * Add attachment method.
     * - Add attachment to email template.
     *
     * @since 1.0.0
     *
     * @param string $filename
     * @param string $url
     * @return void
     */
    public function addAttachment($filename, $url)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, false);
        curl_setopt($ch, CURLOPT_REFERER, "http://www.weoinvoice.com");
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $file_content = curl_exec($ch);

        curl_close($ch);

        $this->emailTemplate = Mage::getModel('core/email_template');
        $attachment = $this->emailTemplate->getMail()->createAttachment($file_content);
        $attachment->type = 'application/pdf';
        $attachment->filename = $filename;
    }

}

