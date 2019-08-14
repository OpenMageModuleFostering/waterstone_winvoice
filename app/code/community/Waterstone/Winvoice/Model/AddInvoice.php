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

class Waterstone_Winvoice_Model_AddInvoice extends Mage_Core_Model_Abstract
{
    /* Private variables */
    private $client;
    private $password;

    /**
     * weoInvoice error codes list.
     *
     * @var array
     */
    private $errorMessages = array(
            '300' => 'Falha na autenticação no weoInvoice.',
            '301' => 'Parâmetros insuficientes.',
            '302' => 'Código de cliente já existente ou inválido.',
            '303' => 'Número de contribuinte inválido.',
            '304' => 'Email inválido.',
            '305' => 'Sem permissões para alterar o cliente.',
            '306' => 'Sem permissões para usar o cliente.',
            '307' => 'Empresa sem as informações mínimas necessárias.',
            '308' => 'Empresa sem a tabela de IVA correctamente preenchida.',
            '309' => 'Data inválida.',
            '310' => 'Data de pagamento inválida.',
            '311' => 'Data do documento não sequencial.',
            '312' => 'Tipo de documento inválido.',
            '320' => 'Não é possível criar um documento com um existente em aberto.',
            '321' => 'Documento não disponível.',
            '322' => 'Documento já fechado.',
            '323' => 'Informação de produtos/serviços no documento insuficiente.',
            '324' => 'Taxa de IVA do produto/serviço incorrecta.',
            '325' => 'Sem permissões para usar o documento.',
            '326' => 'Documento ainda está aberto.',
            '327' => 'Motivo da anulação do documento inexistente.',
            '328' => 'Motivo de isenção de IVA incorrecto ou inexistente.',
            '340' => 'Código de cliente inexistente.',
            '341' => 'Erro ao obter dados do cliente.',
            '350' => 'Pagamento de factura com valor inferior a zero.',
            '351' => 'Factura já paga.',
            '352' => 'Recibo com valor inferior a zero.'
            );

    /**
     * Constructor.
     * - Connect to weoInvoice api using nusoap.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function __construct()
    {
        date_default_timezone_set('Europe/Berlin');
        require_once(Mage::getModuleDir('', 'Waterstone_Winvoice').'/Model/nusoap/lib/nusoap.php');

        $this->client = new \nusoap_client(Mage::getStoreConfig('winvoice/wconfig/wapi'));
        $this->password = Mage::getStoreConfig('winvoice/wconfig/wpassword');


        Mage::log("Winvoice | Foi criado um cliente SOAP.", null, 'winvoice.log', true);
        Mage::log("Winvoice | Password do cliente SOAP: {$this->password}", null, 'winvoice.log', true);

    }

    /**
     * Add invoice method.
     * - Gets the client products, creates a winvoice and sends to email.
     *
     * @since 1.0.0
     *
     * @param Mage_Sales_Model_Observer $observer
     * @return void
     */
    public function addInvoice($observer)
    {
        $invoice = $observer->getEvent()->getInvoice();
        $orderNumber = $invoice->getOrder()->getIncrementId();

        Mage::log("Winvoice | A invoice estah associada ah encomenda numero {$orderNumber}", null, 'winvoice.log', true);
        $useCURL = isset($_POST['usecurl']) ? $_POST['usecurl'] : '0';
        $err = $this->client->getError();

        if ($err) {
            Mage::log("Winvoice | Erro ao criar factura.", null, 'winvoice.log', true);
            Mage::throwException(Mage::helper('adminhtml')->__('Erro ao criar factura.'));
        }

        // Get client items
        $this->client->setUseCurl($useCURL);
        $this->client->soap_defencoding = 'UTF-8';
        $items = $invoice->getAllItems();

        foreach ($items as $item) {
            if ($item->getBase_price() == 0) {
                continue;
            }

            $Product = array (
                'item' => $item->getName(),                                                         //string (*) Nome do produto/serviço
                'type' => 'P',                                                                      //char (*) Tipo (P ou S) consoante seja produto ou serviço
                'quantity' => number_format((float)$item->getQty(), 0, '.', ''),                    //double (*) Quantidade (ex: 2)
                'price' => round($item->getPriceInclTax()/( 1 + $item->getOrderItem()->getTaxPercent()/100), 6),                 //double (*) Preço (ex: 99.99)
                'discount' => $item->getDiscountAmount(),                                                               //double Desconto (ex: 19.99)
                'tax' => number_format((float)$item->getOrderItem()->getTaxPercent(), 0, '.', ''),  //int (*)Taxa de IVA (ex: 23)
                'taxreason' => '',                                                                  // string Motivo de isenção de Taxa, caso aplicável (ver tabela Motivos de isenção de IVA)
            );

            $Products[] = $Product;

            Mage::log("Winvoice | A adicionar o item \"{$item->getName()}\" ah invoice, com os dados:", null, 'winvoice.log', true);
            Mage::log("Winvoice | Quantidade: ".number_format((float)$item->getQty(), 0, '.', ''), null, 'winvoice.log', true);
            Mage::log("Winvoice | Preco base: ".number_format((float)$item->getBase_price(), 2, '.', ''), null, 'winvoice.log', true);
            Mage::log("Winvoice | Imposto: ".number_format((float)$item->getOrderItem()->getTaxPercent(), 2, '.', ''), null, 'winvoice.log', true);
            Mage::log("Winvoice | Discontos: ".$item->getDiscountAmount(), null, 'winvoice.log', true);
        }

        // Shipping tax value in percentage
        $vat = Mage::getStoreConfig('winvoice/wconfig/wshiptax');

        $Product = array (
            'item' => $invoice->getOrder()->getShippingDescription(),
            'type' => 'S',
            'quantity' => '1',
            'price' => round($invoice->getOrder()->getBaseShippingInclTax()/( 1 + $vat/100), 6),
            'discount' => number_format((float)$invoice->getOrder()->getBaseShippingDiscountAmount(), 2, '.', ''),
            'tax' => $vat,
            'taxreason' => '',
        );

        $Products[] = $Product;
		

        Mage::log("Winvoice | Id do client a enviar para factura weo: ".$this->getClientNumber($invoice), null, 'winvoice.log', true);

        $params = array(
            'client' => $this->getClientNumber($invoice),
            'type' => '1',                     //Tipo de documento (ver tabela Tipos de Documento)
            'date' => date("Y-m-d"),            //Data do documento (ex: 2011-01-01)
            'payment_date' => date("Y-m-d"),    //date (*) Data de vencimento do documento (ex: 2011-01-01)
            'description' => '',
            'footer' => 'Encomenda: '.$invoice->getOrder()->getIncrementId(),
            'products' => $Products,
            'password' => $this->password,
        );

        $result = $this->client->call("AddDocument", $params);

        if ($this->client->fault) {
            Mage::log("Winvoice | Erro ao criar factura.", null, 'winvoice.log', true);
            Mage::throwException(Mage::helper('adminhtml')->__('Erro ao criar factura.'));
        } else {
            $err = $this->client->getError();

            if ($err) {
                Mage::log("Winvoice | Erro ao criar factura.", null, 'winvoice.log', true);
                Mage::throwException(Mage::helper('adminhtml')->__('Erro ao criar factura.'));
            } else {
                Mage::log("Winvoice | Mensagem retornada pelo weoInvoice: ".$this->errorMessages[$result['answer']], null, 'winvoice.log', true);
                Mage::log("Winvoice | ID retornado pelo weoInvoice: ".$result['description1'], null, 'winvoice.log', true);
                Mage::log("Winvoice | URL retornado pelo weoInvoice: ".$result['description2'], null, 'winvoice.log', true);

                if ($result['answer'] <> 0 && $result['answer'] <> 1) {
                    if (array_key_exists($result['answer'], $this->errorMessages)) {
                        Mage::log("Winvoice | Mensagem retornada pelo weoInvoice: ".$this->errorMessages[$result['answer']], null, 'winvoice.log', true);
                        Mage::throwException(Mage::helper('adminhtml')->__($this->errorMessages[$result['answer']]));
                    } else {
                        Mage::log("Winvoice | Erro ao criar factura.", null, 'winvoice.log', true);
                        Mage::throwException(Mage::helper('adminhtml')->__('Erro ao criar factura.'));
                    }
                }
				
                $prefix = Mage::getConfig()->getTablePrefix();
                $resource = Mage::getSingleton('core/resource')->getConnection('core_read');
                $resource->addColumn($prefix.'sales_flat_invoice', 'wsinvoiceurl', 'Varchar(300) NULL');
                $resource->addColumn($prefix.'sales_flat_invoice', 'wsinvoiceid', 'INT NULL');

                $invoiceId = $result['description1'];
				$invoiceUrl = $result['description2'];
                $invoice->setWsinvoiceurl($invoiceUrl);
                $invoice->setWsinvoiceid($invoiceId);
            }
        }

      
    }

    /**
     * Get invoice url method.
     * - Returns the invoice url from weoInvoice service.
     *
     * @since 1.0.0
     *
     * @param Mage_Sales_Model_Observer $observer
     * @return string
     */
    public function getInvoiceURL($observer)
    {
        $invoice = $observer->getEvent()->getInvoice();

        return $invoice->getWsinvoiceurl();
    }

    /**
     * Get client number method.
     * - Returns the invoice url from weoInvoice service.
     *
     * @since 1.0.0
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @return string
     */
    private function getClientNumber($invoice)
    {
        $address = $invoice->getBillingAddress();
        $vat = $address->getVatId();

        if (!$address->getCountryId() == 'PT') {
            Mage::log("Winvoice | O NIF do cliente ".$vat." eh invalido", null, 'winvoice.log', true);
            Mage::throwException('Numero de contribuinte invalido: '.Mage::helper('adminhtml')->__($vat));
        }
        if (!$this->isValidNif($vat)) {
            Mage::log("Winvoice | O NIF do cliente ".$vat." eh invalido", null, 'winvoice.log', true);
            Mage::throwException('Numero de contribuinte invalido: '.Mage::helper('adminhtml')->__($vat));
        }

        $customer = Mage::getModel('customer/customer')->load($invoice->getOrder()->getCustomerId());

        Mage::log("Winvoice | Os dados do cliente na invoice são:", null, 'winvoice.log', true);
        Mage::log("Winvoice | Nome:".$invoice->getOrder()->getCustomerName(), null, 'winvoice.log', true);
        Mage::log("Winvoice | ID:".$invoice->getOrder()->getCustomerId(), null, 'winvoice.log', true);
        Mage::log("Winvoice | NIF:".$address->getVatId()/*$invoice->getOrder()->getCustomerTaxvat()*/, null, 'winvoice.log', true);
        Mage::log("Winvoice | WSClient do cliente:".$customer->getWsclient(), null, 'winvoice.log', true);
        Mage::log("Winvoice | WSClient da encomenda:".$invoice->getOrder()->getCustomerWsclient(), null, 'winvoice.log', true);
        Mage::log("Winvoice | Morada: ".$address->getStreetFull(), null, 'winvoice.log', true);
        Mage::log("Winvoice | Codigo Postal: ".$address->getPostcode(), null, 'winvoice.log', true);
        Mage::log("Winvoice | Cidade: ".$address->getCity(), null, 'winvoice.log', true);
        Mage::log("Winvoice | Pais: ".$address->getCountryId(), null, 'winvoice.log', true);

        if (is_numeric($customer->getWsclient())) {
            return $customer->getWsclient();
        }

        $useCURL = isset($_POST['usecurl']) ? $_POST['usecurl'] : '0';
        $err = $this->client->getError();

        if ($err) {
            Mage::log("Winvoice | Erro ao criar factura.", null, 'winvoice.log', true);
            Mage::throwException(Mage::helper('adminhtml')->__('Erro ao criar factura.'));
            exit();
        }

        $this->client->setUseCurl($useCURL);
        $this->client->soap_defencoding = 'UTF-8';

        if (is_numeric($vat)) {
            $vatnumber = $vat;
        } else {
            $vatnumber = '999999900';
        }

        $AddClientIn = array (
            'code' => "MAG".$invoice->getOrder()->getCustomerId().rand(1, 999999),
            'name' => $invoice->getOrder()->getCustomerName(),
            'nif'  => $vatnumber,
        //  email string Endereço de email
        //  url string Página web
            'address' => $address->getStreetFull(),
            'postcode' => $address->getPostcode(),
            'city' => $address->getCity(),
            'country' => $address->getCountryId(),
        //  telephone string Telefone do cliente (ex: 212109902)
        //  mobile string Telemóvel do cliente
        //  fax string Fax do cliente
        //  description string Descrição
        );

        $params = array(
            'data'    => $AddClientIn,
            'password'=> $this->password,
        );

        $result = $this->client->call("AddClient", $params);

        if ($this->client->fault) {
            Mage::log("Winvoice | Erro ao criar factura.", null, 'winvoice.log', true);
            Mage::throwException(Mage::helper('adminhtml')->__('Erro ao criar factura.'));
        } else {
            $err = $this->client->getError();

            if ($err) {
                Mage::log("Winvoice | Erro ao criar factura.", null, 'winvoice.log', true);
                Mage::throwException(Mage::helper('adminhtml')->__('Erro ao criar factura.'));
            } else {
                if ($invoice->getOrder()->getCustomerId()) {
                    $customer = Mage::getModel('customer/customer')->load($invoice->getOrder()->getCustomerId());
                    $customer->setWsclient($result['description1']);
                    $customer->save();
                } else {
                    Mage::log("Winvoice | Erro ao criar factura.", null, 'winvoice.log', true);
                    Mage::log("Winvoice | One time costumer processed.", null, 'winvoice.log', true);
                }

                Mage::log("Winvoice | Foi criado um novo WSClient com o numero ".$result['description1'], null, 'winvoice.log', true);
                Mage::log("Winvoice | Codigo API do novo WSClient: ".$result['answer'], null, 'winvoice.log', true);

                return $result['description1'];
            }
        }
    }

    /**
     * Auto invoice method.
     * - Prepares order to create and send invoice to client.
     *
     * @since 1.0.0
     *
     * @param Mage_Sales_Model_Observer $observer
     * @return void
     */
    public function autoInvoice($observer)
    {
        return;

        Mage::log("Winvoice | AutoInvoice trigger for order".$observer->getEvent()->getOrder(), null, 'winvoice.log', true);


        $order = $observer->getEvent()->getShipment()->getOrder();

        $invoice = $order->prepareInvoice();
        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
        $invoice->register();
        $invoice->getOrder()->setCustomerNoteNotify(true);
        $invoice->getOrder()->setIsInProcess(true);

        $order->addStatusHistoryComment('AutoInvoice by Winvoice.', false);

        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder());

        $transactionSave->save();

        $invoice->sendEmail(true, "Factura enviada ao cliente");
    }

    /**
     * Add credit memo method.
     * - Gets information for the credit memo and sends it outwards.
     *
     * @since 1.0.4
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function addCreditMemo($observer)
    {
        $creditmemo = $observer->getEvent()->getCreditmemo();

        $vat = Mage::getStoreConfig('winvoice/wconfig/wshiptax');

        $prefix = Mage::getConfig()->getTablePrefix();
        $resource = Mage::getSingleton('core/resource')->getConnection('core_read');
        $resource->addColumn($prefix.'sales_flat_creditmemo', 'wscreditmemourl', 'Varchar(300) NULL');

        $orderId = $creditmemo->getOrder()->getId();
        $table = $prefix.'sales_flat_invoice';

        $query = "SELECT wsinvoiceid FROM $table WHERE order_id = $orderId";

        $invoice_id = $resource->fetchOne($query);
        $items = $creditmemo->getAllItems();

        foreach ($items as $item) {
            if ($item->getBase_price() == 0) {
                continue;
            }

            $Product = array (
                'item' => $item->getName(),                                                         //string (*) Nome do produto/serviço
                'type' => 'P',                                                                      //char (*) Tipo (P ou S) consoante seja produto ou serviço
                'quantity' => number_format((float)$item->getQty(), 0, '.', ''),                    //double (*) Quantidade (ex: 2)
                'price' => round($item->getPriceInclTax()/( 1 + $item->getOrderItem()->getTaxPercent()/100), 6),                 //double (*) Preço (ex: 99.99)
                'discount' => $item->getDiscountAmount(),                                                               //double Desconto (ex: 19.99)
                'tax' => number_format((float)$item->getOrderItem()->getTaxPercent(), 0, '.', ''),  //int (*)Taxa de IVA (ex: 23)
                'taxreason' => '',                                                                  // string Motivo de isenção de Taxa, caso aplicável (ver tabela Motivos de isenção de IVA)
            );

            $Products[] = $Product;
        }


        $Product = array (
            'item' => $creditmemo->getOrder()->getShippingDescription(),
            'type' => 'S',
            'quantity' => '1',
            'price' => round($creditmemo->getOrder()->getBaseShippingInclTax()/( 1 + $vat/100), 6),
            'discount' => number_format((float)$creditmemo->getOrder()->getBaseShippingDiscountAmount(), 2, '.', ''),
            'tax' => $vat,
            'taxreason' => '',
        );

        $Products[] = $Product;


        $params = array(
            'client' => $this->getClientNumber($creditmemo),
            'type' => '6',                     //Tipo de documento (ver tabela Tipos de Documento)
            'date' => date("Y-m-d"),            //Data do documento (ex: 2011-01-01)
            'payment_date' => date("Y-m-d"),    //date (*) Data de vencimento do documento (ex: 2011-01-01)
            'description' => '',
            'footer' => utf8_decode('Nota de crédito: '.$creditmemo->getIncrementId()),
            'products' => $Products,
            'password' => $this->password,
            'custom' => $invoice_id,
        );

        $result = $this->client->call("AddDocument", $params);
        $url = $result['description2'];
        $creditmemo->setWscreditmemourl($url);

    }





    /**
     * Is valid nif method.
     * - Checks if nif number is valid.
     *
     * @since 1.0.0
     *
     * @param string $nif
     * @return bool
     */
    public function isValidNif($nif)
    {
        if (!is_numeric($nif) || strlen($nif) != 9) {
            return false;
        }

        $narray = str_split($nif);

        if ($narray[0] != 1 && $narray[0] != 2 && $narray[0] != 5 && $narray[0] != 6 && $narray[0] != 8 && $narray[0] != 9) {
            return false;
        }

        $checkbit = $narray[0] * 9;

        for ($i=2; $i<=8; $i++) {
            $checkbit += $nif[$i-1] * (10 - $i);
        }

        $checkbit = 11 - ($checkbit % 11);

        if ($checkbit >= 10) {
            $checkbit = 0;
        }

        if ($nif[8] == $checkbit) {
            return true;
        }

        return false;
    }
}
