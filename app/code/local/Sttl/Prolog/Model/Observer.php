<?php
/**
 * SilverTouch Technologies Limited.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.silvertouch.com/MagentoExtensions/LICENSE.txt
 *
 * @category   Sttl
 * @package    Sttl_Prolog
 * @copyright  Copyright (c) 2011 SilverTouch Technologies Limited. (http://www.silvertouch.com/MagentoExtensions)
 * @license    http://www.silvertouch.com/MagentoExtensions/LICENSE.txt
 */ 
 
class Sttl_Prolog_Model_Observer
{
	public $_prologUrl = null;
	public $_prologUsername = null;
	public $_prologPassword = null;
	
	//public $prologShipCode = array('ups_GND' => 'UPSG', 'ups_1DA' => 'UPS1', 'ups_2DA' => 'UPS2');	
	
	public function getLoginDetail()
    {
        $this->_prologUrl = Mage::helper('prolog')->getPrologUrl();
        $this->_prologUsername = Mage::helper('prolog')->getPrologUsername();
		$this->_prologPassword = Mage::helper('prolog')->getPrologPassword();
    }
    /**
     * Update Inventory
     */
    public function scheduledUpdateInventory ()
    {
        $productCollection = Mage::getModel('catalog/product')->getCollection()
                                //->addAttributeToSelect('prolog_product_id')
								->addFieldToFilter(array(
                                                        array('attribute'=>'prolog_product_id', 'neq'=>''))
                                                       );        
		$this->getLoginDetail();
		
		// call product model and create product object
		$product = Mage::getModel('catalog/product');
		
		$soapAction = "SOAPAction: http://prolog3pl.com/PLGetInventory\r\n";	
		
		foreach ($productCollection as $productId) {

			// Load product using product id
		 	$product->load($productId->getId());
		 	$plProductId = $productId->getPrologProductId();
		 
			if ($plProductId != "") {				
				//Get Prolog Available Inventory
				$xmlDocument = '<?xml version="1.0" encoding="utf-8"?>
					<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
					<soap:Body><PLGetInventory xmlns="http://prolog3pl.com/"><args>
					<SystemId>' . $this->_prologUsername . '</SystemId><Password>' . $this->_prologPassword . '</Password><AllProducts>false</AllProducts><Products><string>'.$plProductId.'</string></Products>
					</args></PLGetInventory></soap:Body></soap:Envelope>';
		
				$result = $this->postXMLtoURL($this->_prologUrl, "/ProWaresService.asmx", $xmlDocument, $soapAction);

				$current = "";
				$count = 0;
				$data = $this->getBody($result);
				$data=eregi_replace(">"."[[:space:]]+"."<","><",$data);
				$plarray =Mage::helper('prolog')->xml2arrayNew($data); 
				//print_r($plarray);
				
				$plQuantityAvailable = $plarray['soap:Envelope']['soap:Body']['PLGetInventoryResponse']['PLGetInventoryResult']
										['Inventory']['PLInventory']['QuantityAvailable'];				
				
				$stockData = $product->getStockData();
				
				// update stock data using new data
				$stockData['qty'] = $plQuantityAvailable;
				if($plQuantityAvailable>0) {
					$stockData['is_in_stock'] = 1;
				} else {
					$stockData['is_in_stock'] = 0;
				}
				
				// then set product's stock data to update
				$product->setStockData($stockData);
				
				// call save() method to save your product with updated data
				$product->save();
			}			
		}
    }	
	
	
	/**
	 * Send Order value in Prolog
	 */
	public function placeOrderPL ($observer)
	{
		$this->getLoginDetail();
        
		$order = $observer->getOrder();				
		$OrderNumber = $order->getIncrementId();
		$CustomerNumber = null;
		$customerObject = Mage::getSingleton('customer/session'); 
		/*if ($customerObject->isLoggedIn()) {
			$CustomerNumber = $customerObject->getCustomerId();
			$OrderNumber = $CustomerNumber.'_'.$OrderNumber;
		}*/
		$CustomerNumber = Mage::helper('prolog')->getPrologCustomerId();
		$OrderNumber = $CustomerNumber.'_'.$OrderNumber;
		
		$id = $order->getId(); 
		$orderModel = Mage::getModel('sales/order')->load($id);
		$orderModel->setPrologOrderId($OrderNumber);
		$orderModel->setId($id);		
		$orderModel->save();
		
		$CustomerOrderNumber = '';
		$CustomerPO = '';
		$OrderDate = Mage::getModel('core/date')->date('Y-m-d H:i:s');
		$OrderDate = str_replace(' ', 'T', $OrderDate);
		$Delay = '0';
		$AutoAllocate = 'true';
		$PartialShip = 'false';
		$SplitShip = 'false';
				
		$orderShippingMethod = $order->getshipping_method();
			
		if ($orderShippingMethod == 'ups_GND') {
			$ShippingService = 'UPSG';
		} else if ($orderShippingMethod == 'ups_1DA') {
			$ShippingService = 'UPS1';
		} else if ($orderShippingMethod == 'ups_2DA') {
			$ShippingService = 'UPS2';
		} else if ($orderShippingMethod == 'usps_Priority Mail') {
			$ShippingService = 'PM';
		} else if ($orderShippingMethod == 'usps_Priority Mail International') {
			$ShippingService = 'PMI';
		} else if ($orderShippingMethod == 'usps_First-Class') {
			$ShippingService = 'USPSFC';
		} else {
			$ShippingService = null;
		}
		/*print_r($ShippingService);
		exit;*/
		$BillThridParty = 'false';
		$AccountNumber = '';
		$SaturdayDelivery = 'false';
		$Residential = 'false';
		$InsurePackages = 'false';
		$InsureThreshold = '0';
		$EmailConfirmationAddress = $order->getBillingAddress()->getEmail();
		$PackingListComment = 'Thank you for your order.';
		
		$Subtotal = $order->getSubtotal();
		$Shipping = $order->getShippingAmount();
		$Handling = '0.00';
		$Discount = $order->getDiscountAmount();
		$Tax = $order->getTaxAmount();
		$Total = $order->getGrandTotal();
		
		


		//Get Shipping Details
		$shippingAddressFirstName = $order->getShippingAddress()->getFirstname();
		$shippingAddressLastName = $order->getShippingAddress()->getLastname();
		$shippingAddressCompanyName = $order->getShippingAddress()->getCompany();
		$shippingAddressAddress1 = $order->getShippingAddress()->getStreet1();
		$shippingAddressAddress2 = $order->getShippingAddress()->getStreet2();
		$shippingAddressAddress3 = '';
		$shippingAddressCity = $order->getShippingAddress()->getCity();
		$shippingAddressState = $order->getShippingAddress()->getRegion();
		$shippingAddressPostalCode = $order->getShippingAddress()->getPostcode();
		$shippingAddressCountry = $order->getShippingAddress()->getCountryId();
		$shippingAddressPhoneNumber = $order->getShippingAddress()->getTelephone();
		$shippingAddressEmailAddress = $order->getShippingAddress()->getEmail();
	
		//Get Billing Details
		$billingAddressFirstName = $order->getShippingAddress()->getFirstname();
		$billingAddressLastName = $order->getBillingAddress()->getLastname();
		$billingAddressCompanyName = $order->getBillingAddress()->getCompany();
		$billingAddressAddress1 = $order->getBillingAddress()->getStreet1();
		$billingAddressAddress2 = $order->getBillingAddress()->getStreet2();
		$billingAddressAddress3 = '';
		$billingAddressCity = $order->getBillingAddress()->getCity();
		$billingAddressState = $order->getBillingAddress()->getRegion();
		$billingAddressPostalCode = $order->getBillingAddress()->getPostcode();
		$billingAddressCountry = $order->getBillingAddress()->getCountryId();
		$billingAddressPhoneNumber = $order->getBillingAddress()->getTelephone();
		$billingAddressEmailAddress = $order->getBillingAddress()->getEmail();
	
		$xmlOrderInfo = ''.
		'<SystemId>'.$this->_prologUsername.'</SystemId>'.
		'<Password>'.$this->_prologPassword.'</Password>'.
		'<Orders>'.
		'	<PLOrder>'.
		'		<OrderNumber>'.$OrderNumber.'</OrderNumber>'.
		'		<CustomerNumber>'.$CustomerNumber.'</CustomerNumber>'.
		'		<CustomerOrderNumber>'.$CustomerOrderNumber.'</CustomerOrderNumber>'.
		'		<CustomerPO>'.$CustomerPO.'</CustomerPO>'.
		'		<OrderDate>'.$OrderDate.'</OrderDate>'.
		'		<Delay>'.$Delay.'</Delay>'.
		'		<AutoAllocate>'.$AutoAllocate.'</AutoAllocate>'.
		'		<PartialShip>'.$PartialShip.'</PartialShip>'.
		'		<SplitShip>'.$SplitShip.'</SplitShip>'.
		'		<ShippingService>'.$ShippingService.'</ShippingService>'.
		'		<BillThridParty>'.$BillThridParty.'</BillThridParty>'.
		'		<AccountNumber>'.$AccountNumber.'</AccountNumber>'.
		'		<SaturdayDelivery>'.$SaturdayDelivery.'</SaturdayDelivery>'.
		'		<Residential>'.$Residential.'</Residential>'.
		'		<InsurePackages>'.$InsurePackages.'</InsurePackages>'.
		'		<InsureThreshold>'.$InsureThreshold.'</InsureThreshold>'.
		'		<EmailConfirmationAddress>'.$EmailConfirmationAddress.'</EmailConfirmationAddress>'.
		'		<PackingListComment>'.$PackingListComment.'</PackingListComment>'.
		'		<Subtotal>'.$Subtotal.'</Subtotal>'.
		'		<Shipping>'.$Shipping.'</Shipping>'.
		'		<Handling>'.$Handling.'</Handling>'.
		'		<Discount>'.$Discount.'</Discount>'.
		'		<Tax>'.$Tax.'</Tax>'.
		'		<Total>'.$Total.'</Total>'.
		'		<OrderLines>';
				
		//Get Product Details
		$orderItems = $order->getItemsCollection();  
		foreach ($orderItems as $item){
			if($item->getRowTotal() > 0) {
				$productSku = $item->getSku();
				$product = Mage::getModel('catalog/product')->loadByAttribute('sku', $productSku);
				$productDescription = '';									  
				$xmlOrderInfo = $xmlOrderInfo.
				'			<PLOrderLine>'.
				'				<LineNumber>'.$product->getPrologLineNumber().'</LineNumber>'.
				'				<Product>'.$product->getPrologProductId().'</Product>'.
				'				<Description>'.$productDescription.'</Description>'.
				'				<Quantity>'.$item->getQtyOrdered().'</Quantity>'.
				'				<Price>'.$item->getPrice().'</Price>'.
				'			</PLOrderLine>';
			}
		}

		$xmlOrderInfo = $xmlOrderInfo.
		'		</OrderLines>'.
		'		<ShippingAddress>'.
		'			<FirstName>'.$shippingAddressFirstName.'</FirstName>'.
		'			<LastName>'.$shippingAddressLastName.'</LastName>'.
		'			<CompanyName>'.$shippingAddressCompanyName.'</CompanyName>'.
		'			<Address1>'.$shippingAddressAddress1.'</Address1>'.
		'			<Address2>'.$shippingAddressAddress2.'</Address2>'.
		'			<Address3>'.$shippingAddressAddress3.'</Address3>'.
		'			<City>'.$shippingAddressCity.'</City>'.
		'			<State>'.$shippingAddressState.'</State>'.
		'			<PostalCode>'.$shippingAddressPostalCode.'</PostalCode>'.
		'			<Country>'.$shippingAddressCountry.'</Country>'.
		'			<PhoneNumber>'.$shippingAddressPhoneNumber.'</PhoneNumber>'.
		'			<EmailAddress>'.$shippingAddressEmailAddress.'</EmailAddress>'.
		'		</ShippingAddress>'.
		'		<BillingAddress>'.
		'			<FirstName>'.$billingAddressFirstName.'</FirstName>'.
		'			<LastName>'.$billingAddressLastName.'</LastName>'.
		'			<CompanyName>'.$billingAddressCompanyName.'</CompanyName>'.
		'			<Address1>'.$billingAddressAddress1.'</Address1>'.
		'			<Address2>'.$billingAddressAddress2.'</Address2>'.
		'			<Address3>'.$billingAddressAddress3.'</Address3>'.
		'			<City>'.$billingAddressCity.'</City>'.
		'			<State>'.$billingAddressState.'</State>'.
		'			<PostalCode>'.$billingAddressPostalCode.'</PostalCode>'.
		'			<Country>'.$billingAddressCountry.'</Country>'.
		'			<PhoneNumber>'.$billingAddressPhoneNumber.'</PhoneNumber>'.
		'			<EmailAddress>'.$billingAddressEmailAddress.'</EmailAddress>'.
		'		</BillingAddress>'.
		'	</PLOrder>'.
		'</Orders>';
		/*print_r($xmlOrderInfo);
		exit;*/
		
		$xmlDocument = '<?xml version="1.0" encoding="utf-8"?>
			<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
			<soap:Body><PLSubmitOrder xmlns="http://prolog3pl.com/">
			<args>'.$xmlOrderInfo.'</args></PLSubmitOrder></soap:Body></soap:Envelope>';
		
		$soapAction = "SOAPAction: http://prolog3pl.com/PLSubmitOrder\r\n";
		$result = $this->postXMLtoURL($this->_prologUrl, "/ProWaresService.asmx", $xmlDocument, $soapAction);
		
	}
	
	/**
	 * get Order status from Prolog
	 */
	public function scheduledUpdateOrder()
	{
		$shippingDetails = null;
		$carrier = null;
		$title = null;
		$plOrderStatus = null;
		$trackingnumber = null;
		
		$this->getLoginDetail();

		$soapAction = "SOAPAction: http://prolog3pl.com/PLGetOrderStatus\r\n";


		
		$orderCollection = Mage::getModel('sales/order')->getCollection()
                                ->addFieldToFilter('status', array('neq' => 'complete'))
                                ->addFieldToFilter('status', array('neq' => 'canceled'))
                                ->addFieldToFilter('prolog_order_id',array('neq'=>''));
        //$orderCollection->getSelect()->where('status != "complete" and status != "canceled" or prolog_order_id != null');
		
		foreach ($orderCollection as $order) {			
			$orderObject = Mage::getModel('sales/order')->load($order->getEntityId());
			$prologOrderId = $orderObject->getPrologOrderId();
	   
			if ($prologOrderId) {
			
				$xmlDocument = '<?xml version="1.0" encoding="utf-8"?>
					<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
					<soap:Body><PLGetOrderStatus xmlns="http://prolog3pl.com/"><args>
					<SystemId>' . $this->_prologUsername . '</SystemId><Password>' . $this->_prologPassword . '</Password><OrderNumbers><string>'.$prologOrderId.'</string></OrderNumbers>
					</args></PLGetOrderStatus></soap:Body></soap:Envelope>';
				
				$result = $this->postXMLtoURL($this->_prologUrl, "/ProWaresService.asmx", $xmlDocument, $soapAction);
		
				$current = "";
				$count = 0;
				$data = $this->getBody($result);
				$data=eregi_replace(">"."[[:space:]]+"."<","><",$data);
				
				$plOrderArray = Mage::helper('prolog')->xml2arrayNew($data);
				$plOrderStatus =  $plOrderArray['soap:Envelope']['soap:Body']['PLGetOrderStatusResponse']
				['PLGetOrderStatusResult']['Orders']['PLOrderStatusHeader']['Status'];
				$trackingnumber =  $plOrderArray['soap:Envelope']['soap:Body']['PLGetOrderStatusResponse']
				['PLGetOrderStatusResult']['Orders']['PLOrderStatusHeader']['Shipments']['PLOrderStatusShipment']
				['Packages']['PLOrderStatusPackage']['TrackingNumber'];

				//echo $plOrderStatus;
				//Change Order Status in magento using prolog order status
				if ($plOrderStatus == 'OPEN') {
					if($orderObject->getStatus() == 'holded') {
						$orderObject->unhold()->save();
					}
				} else if ($plOrderStatus == 'HOLD') {
					$orderObject->hold()->save();
				} else if ($plOrderStatus == 'COMPLETED') {			
					if($orderObject->getStatus() == 'holded') {
						$orderObject->unhold()->save();
					}
					//Send Shipping Mail to Customer
                    if ($orderObject->canShip()) {
                        $this->SendShippingMail($orderObject);
                    }
				} else if ($plOrderStatus == 'CANCELED') {
					if($orderObject->getStatus() == 'holded') {
						$orderObject->unhold()->save();
					}
					$orderObject->cancel()->save();
				}				
			}
			//echo $order->getEntityId()."  ".$orderObject->getPrologOrderId()."<br/>";
		}
	}
	
	/*
	 * Send Shipping Mail
	 */	
	private function SendShippingMail ($orderObject)
	{
		$shippingDetails = split("_",$orderObject->getShippingMethod());
		if($shippingDetails[0] == 'ups') {
			$carrier = $shippingDetails[0];
			$title = 'United Parcel Service';
		} else if($shippingDetails[0] == 'usps') {
			$carrier = $shippingDetails[0];
			$title = 'United States Postal Service';
		}
		$convertor = Mage::getModel('sales/convert_order');
		$shipment = $convertor->toShipment($orderObject);

		foreach ($orderObject->getAllItems() as $orderItem) {
			if (!$orderItem->getQtyToShip()) {
				continue;
			}
			if ($orderItem->getIsVirtual()) {
				continue;
			}
			$item = $convertor->itemToShipmentItem($orderItem);
			$qty = $orderItem->getQtyToShip();
			$item->setQty($qty);
			$shipment->addItem($item);
		}
		
		$data = array();
		$data['carrier_code'] = $carrier;
		$data['title'] = $title;
		$data['number'] = $trackingnumber;
		
		$track = Mage::getModel('sales/order_shipment_track')->addData($data);
		$shipment->addTrack($track);

		$shipment->register();
		$shipment->addComment('', false);

		$shipment->setEmailSent(true);

		$shipment->getOrder()->setIsInProcess(true);

		$transactionSave = Mage::getModel('core/resource_transaction')
			->addObject($shipment)
			->addObject($shipment->getOrder())
			->save();

		$shipment->sendEmail(true, '');
	}

    	/**
	 * Get result from prolog
	 *
	 * @param string $server
     * @param string $path
	 * @param string $xmlDocument
	 * @param string $sopAction
	 * @return xml value
	 */
	public function postXMLToURL ($server, $path, $xmlDocument, $soapAction)
	{
		$xmlSource = $xmlDocument;
		$contentLength = strlen($xmlSource);
		$fp = fsockopen($server, 80);
		fputs($fp, "POST $path HTTP/1.0\r\n");
		fputs($fp, "Host: $server\r\n");
		fputs($fp, "Content-Type: text/xml\r\n");
		fputs($fp, "Content-Length: $contentLength\r\n");
		fputs($fp, $soapAction);
		fputs($fp, "Connection: close\r\n");
		fputs($fp, "\r\n"); // all headers sent
		fputs($fp, $xmlSource);
		$result = '';
		while (!feof($fp)) {
			$result .= fgets($fp, 128);
		}
		return $result;
	}

	/**
	 * Remove regular expression
	 *
	 * @param xml $httpResponse
	 * @return xml
	 */
	public function getBody ($httpResponse)
	{
		$lines = preg_split('/(\r\n|\r|\n)/', $httpResponse);
		$responseBody = '';
		$lineCount = count($lines);
		for ($i = 0; $i < $lineCount; $i++) {
			if ($lines[$i] == '') {
				break;
			}
		}
		for ($j = $i + 1; $j < $lineCount; $j++) {
			$responseBody .= $lines[$j] . "\n";
		}
		return $responseBody;
	}
}