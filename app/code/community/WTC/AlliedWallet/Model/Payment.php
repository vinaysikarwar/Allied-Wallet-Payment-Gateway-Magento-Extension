<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category   Web Technology Codes
 * @package    WTC_AlliedWallet
 * @copyright  Copyright (c) 2014 Allied Wallet by Vinay Sikarwar (http://www.webtechnologycodes.com)
 */

class WTC_AlliedWallet_Model_Payment extends Mage_Payment_Model_Method_Cc
{
    protected $_code  = 'alliedwallet';
    protected $_formBlockType = 'alliedwallet/cc';

    /**
     * Availability options
     */
	protected $_isGateway = true;  
  	protected $_canAuthorize = true;  
  	protected $_canUseCheckout = true;  
	protected $_canUseInternal = true;
	protected $_canRefundInvoicePartial = true;
	protected $_canUseForMultishipping  = true;
	protected $_canCapture = true;
	protected $_canRefund = true;

	private $_merchantId;
	private $_password;
	private $_siteId;
	private $_testUrl;
	private $_liveUrl;
	private $_testMode;

	public function __construct() {
		// init this class
		parent::__construct();
		
		// get config data
		$this->merchantId =	$this->getConfigData('merchant_id');
		//$this->password = $this->getConfigData('password');
		$this->siteId = $this->getConfigData('site_id');
		//$this->testUrl = $this->getConfigData('test_url');
		$this->liveUrl = $this->getConfigData('live_url');
		//$this->testMode = $this->getConfigData('test_mode');
		$this->orderDesc = $this->getConfigData('order_desc');
		$this->currency = $this->getConfigData('currency');
	}
	
    /**
     * validate payment form
     *
     * @return  bool
     */
    public function validate()
    {
        return true;
    }

	/**
     
     */   
     public function authorize (Varien_Object $payment, $amount)
    {
			return $this;
    }

	/**
	 * format payment amount correctly. worldpay expects everything in cents
	 * 
	 * @param decimal $amt
	 * @return int
	 */
	public function formatAmount($amt) {
		return $amt * 100;
	}
	

	/**
     
     *
     * @param string $type
     * @return string
     */ 
	public function CcTypeTranslate($type) {
		switch($type) {
			case "AE":
				return "AMEX-SSL";
			case "JCB":	
				return "JCB-SSL";
			case "Solo":
				return "SOLO_GB-SSL";
			case "DI":
				return "DISCOVER-SSL";
			default:
				return "VISA-SSL";
		}
	}	
	
	/**
     
     *
     * @param string $url
     * @return SimpleXMLElement
     */ 
	public function getReply($params)
	{
		// Create the SoapClient instance 
		$client = new SoapClient('https://service.381808.com/Merchant.asmx?WSDL', 
                        array('features'=>SOAP_SINGLE_ELEMENT_ARRAYS));
		
		$result = $client->ExecuteCreditCard($params); 
		return $result;
	}
	
	/**
     * create xml headers.
     *
     * @return DOMImplementation
     */ 	
	public function xmlDom() {
		$dom = new DOMImplementation();
		$xml = $dom->createDocument('', '');
		$xml->encoding = 'UTF-8';
		$xml->version = '1.0';
		return $xml;
	}
	
	/**
     * Get merchantId
     *
     * @param  Varien_Object $payment
     * @return string
     */ 
	public function getMerchantId($payment) {
		// if user pays by amex, use a different merchant ID
		/*
		if ($payment->getCcType() == 'AE') {
			$this->merchantId = $this->getConfigData('loginAmex');
		}
		else {
			$this->merchantId = $this->getConfigData('loginVisa');
		}
		return $this->merchantId;
		*/
	}
	
	/**
     
     *
     * @param  Varien_Object $payment
     * @return string
     */ 
	public function getURL($payment) 
	{
		
			$url = 'http://service.381808.com/ExecuteAVS';		
		return $url;
	}
	
	/**
     * Capture the payment.
     *
     * @param  Varien_Object $payment
     * @param  decimal $amount
     * @return Bpeh_Worldpaydirect_Model_Payment
	 * @throws Mage_Core_Exception
     */ 	
 	public function capture (Varien_Object $payment, $amount)
    {
		if($_POST)
		{
			$capture_case = $_POST['invoice']['capture_case'];
			if($capture_case)
			{
				return true;
			}
		}
        $orderId = $payment->getOrder()->getIncrementId();	
		$order = $payment->getOrder();
		//$order = Mage::getModel("sales/order")->load($orderId); //load order by order id
		$billingAddress = $order->getBillingAddress();
		//$checkout = Mage::getSingleton('checkout/session');
		//$billingAddress = Mage::getSingleton('checkout/session')->getQuote()->getBillingAddress()->getData();
		$currency       = $order->getOrderCurrency(); //$order object
		if (is_object($currency)) {
			$currencyCode = $currency->getCurrencyCode();
		}
		
		$params = array(
			'MerchantID' => $this->merchantId,
			'SiteID' => $this->SiteId,
			'IPAddress' => $_SERVER['REMOTE_ADDR'],
			'Amount' => $amount,
			'CurrencyID' => $currencyCode,
			'FirstName' => $billingAddress['firstname'],
			'LastName' => $billingAddress['lastname'],
			'Phone'=>$billingAddress['telephone'],
			'Address' => $billingAddress['street'],
			'City' => $billingAddress['city'],
			'State' => $billingAddress['region'],
			'Country' => $billingAddress['country_id'],
			'ZipCode' => $billingAddress['postcode'],
			'Email' => $billingAddress['email'],
			'CardNumber' => $payment->getCcNumber(),
			'CardName' => $payment->getCcOwner(),
			'ExpiryMonth' => $payment->getCcExpMonth(),
			'ExpiryYear' => $payment->getCcExpYear(),
			'CardCVV' => $payment->getCcCid()
		);
		$reply = $this->getReply($params);	
		
		//$array = get_object_vars($reply);
		foreach($reply as $data)
		{
			$State = $data->State;
			$status = $data->Status;
			$Message = $data->Message;
			$Technical = $data->Technical;
			$TransactionID = $data->TransactionID;
		}
		
		if($status == 0)
		{
			$payment->setTransactionId($TransactionID);
			$payment->setMessage($Message);
			$payment->setIsTransactionClosed(0);
		}
		if($status == 1)
		{
			$payment->setTransactionId($TransactionID);
			$payment->setMessage($Message);
			$payment->setIsTransactionClosed(0);
		}
		if($status == 2)
		{
			Mage::throwException($Message);
		
		}
		return $this;	
	}  
}
