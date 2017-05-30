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
 
class WTC_AlliedWallet_Model_Observer
{
   public function captureOrder(Varien_Event_Observer $observer)
 	{
     	$payment = $observer->getEvent()->getPayment();
        $pay = $payment->capture(null); // created invoice automatically
 	}
	
	public function autocaptureOrder(Varien_Event_Observer $observer)
	{
		$order = $observer->getEvent()->getData('order');
		$payment = $order->getPayment();
		$amount = $order->getGrandTotal();
		$pay = $this->capture($payment,$order,$amount);
		$this->createInvoice($order);
	}
    	
	public function createInvoice($order)
	{
		try
		{
			if(!$order->canInvoice())
			{
			Mage::throwException(Mage::helper('core')->__('Cannot create an invoice.'));
			}
			$invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
			if (!$invoice->getTotalQty()) {
			Mage::throwException(Mage::helper('core')->__('Cannot create an invoice without products.'));
			}
			$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
			$invoice->register();
			$transactionSave = Mage::getModel('core/resource_transaction')
			->addObject($invoice)
			->addObject($invoice->getOrder());
			$transactionSave->save();
			}
			catch (Mage_Core_Exception $e) {
			}
	}
	
    public function sendInvoiceEmail(Varien_Event_Observer $event)
    {
        $invoice = $event->getInvoice();
 		$invoice->sendEmail(true,'');
    }
	
	public function sendCreditRefundEmail(Varien_Event_Observer $event)
    {
		
	    $creditmemo = $event->getCreditmemo();
		$this->refund($creditmemo);
 		$creditmemo->sendEmail(true,'');
    }
	
	 public function refund ($creditmemo)
    {
		$amount = $creditmemo->getGrandTotal();
		
		$order_id = $creditmemo->getOrderId();
		$order = Mage::getModel("sales/order")->load($order_id); //load order by order id
		$transactionId = $order->getPayment()->getLastTransId();
		
		$merchant_id = Mage::getStoreConfig('payment/alliedwallet/merchant_id');
		//$transactionId = '5dcdb63c-35c5-412c-9a97-672b34beb43c';
		$params = array(
			'MerchantID' => $merchant_id,
			'TransactionID' => $transactionId,
			'RefundAmount'	=> $amount
		);
		
		
		//print_r($params);
		
		// Create the SoapClient instance 
		$client = new SoapClient('https://service.381808.com/Merchant.asmx?WSDL', 
                        array('features'=>SOAP_SINGLE_ELEMENT_ARRAYS));
		
		$result = $client->PartialRefund($params); 
		return $result;
    }
	
	public function capture (Varien_Object $payment,$order, $amount)
    {
		
       // $orderId = $payment->getOrder()->getIncrementId();	
		//$checkout = Mage::getSingleton('checkout/session');
		//$billingAddress = Mage::getSingleton('checkout/session')->getQuote()->getBillingAddress()->getData();
		$merchant_id = Mage::getStoreConfig('payment/alliedwallet/merchant_id');
		$site_id = Mage::getStoreConfig('payment/alliedwallet/site_id');
		$billingAddress = $order->getBillingAddress();
		$currency       = $order->getOrderCurrency(); //$order object
		if (is_object($currency)) {
			$currencyCode = $currency->getCurrencyCode();
		}
		$params = array(
			'MerchantID' => $merchant_id,
			'SiteID' => $site_id,
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
	
	public function getReply($params)
	{
		// Create the SoapClient instance 
		$client = new SoapClient('https://service.381808.com/Merchant.asmx?WSDL', 
                        array('features'=>SOAP_SINGLE_ELEMENT_ARRAYS));
		
		$result = $client->ExecuteCreditCard($params); 
		return $result;
	}
   
}
