<?php

/**
 * Description of Merchant_Billing_AuthorizeNet
 *
 * @package Aktive Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Merchant_Billing_AuthorizeNetCim extends Merchant_Billing_Gateway
{
    const LIVE_URL = "https://api.authorize.net/xml/v1/request.api";
    const TEST_URL = "https://apitest.authorize.net/xml/v1/request.api";
    

    private $options;
	private $AUTHORIZE_NET_CIM_NAMESPACE = 'AnetApi/xml/v1/schema/AnetApiSchema.xsd';
     
    private $CIM_ACTIONS = array(
		'create_customer_profile' => 'createCustomerProfile',
		'create_customer_payment_profile' => 'createCustomerPaymentProfile',
		'create_customer_shipping_address' => 'createCustomerShippingAddress',
		'get_customer_profile' => 'getCustomerProfile',
		'get_customer_payment_profile' => 'getCustomerPaymentProfile',
		'get_customer_shipping_address' => 'getCustomerShippingAddress',
		'delete_customer_profile' => 'deleteCustomerProfile',
		'delete_customer_payment_profile' => 'deleteCustomerPaymentProfile',
		'delete_customer_shipping_address' => 'deleteCustomerShippingAddress',
		'update_customer_profile' => 'updateCustomerProfile',
		'update_customer_payment_profile' => 'updateCustomerPaymentProfile',
		'update_customer_shipping_address' => 'updateCustomerShippingAddress',
		'create_customer_profile_transaction' => 'createCustomerProfileTransaction',
		'validate_customer_payment_profile' => 'validateCustomerPaymentProfile'
    );
	
	private $CIM_VALIDATION_MODES = array(
		'none' => 'none',
		'test' => 'testMode',
		'live' => 'liveMode',
		'old' => 'oldLiveMode'
    );
	private $CIM_TRANSACTION_TYPES = array(
        'auth_capture' => 'profileTransAuthCapture',
        'auth_only' => 'profileTransAuthOnly',
        'capture_only' => 'profileTransCaptureOnly',
        'prior_auth_capture' => 'profileTransPriorAuthCapture',
        'refund' => 'profileTransRefund',
        'void' => 'profileTransVoid'
	);
      
	private $BANK_ACCOUNT_TYPES = array(
        'checking' => 'checking',
        'savings' => 'savings',
        'business_checking' => 'businessChecking'
	);
      
    private $ECHECK_TYPES = array(
        'ccd' => 'CCD',
        'ppd' => 'PPD'
	);
      
    public static  $homepage_url = 'http://www.authorize.net/';
    public static  $display_name = 'Authorize.Net CIM';
    public static  $supported_countries = array('US');
    public static  $supported_cardtypes = array('visa', 'master', 'american_express', 'discover');
    
    public function __construct($options)
    {
        $this->required_options('login, password', $options);

        $this->options = $options;
    }
	
	public function create_customer_profile($options = array())
	{
        # TODO Add requires
        $request = $this->build_request('create_customer_profile', $options);
        return $this->commit('create_customer_profile', $request);
    }
	
	public function update_customer_profile($options = array())
	{
		$this->required_options('profile', $options);
		$this->required_options('customer_profile_id', $options['profile']);
        $request = $this->build_request('update_customer_profile', $options);
        return $this->commit('update_customer_profile', $request);
    }

	public function create_customer_payment_profile($options = array())
	{
		$this->required_options('customer_profile_id, payment_profile', $options);
        $request = $this->build_request('create_customer_payment_profile', $options);
        return $this->commit('create_customer_profile', $request);
    }
	
	public function update_customer_payment_profile($options = array())
	{
		$this->required_options('customer_profile_id, payment_profile', $options);
		$this->required_options('customer_payment_profile_id', $options['payment_profile']);
        $request = $this->build_request('update_customer_payment_profile', $options);
        return $this->commit('update_customer_payment_profile', $request);
    }
	
	public function validate_customer_payment_profile($options = array())
	{
		$this->required_options('customer_profile_id, customer_payment_profile_id, validation_mode', $options);
        $request = $this->build_request('validate_customer_payment_profile', $options);
        return $this->commit('validate_customer_payment_profile', $request);
    }
	
	public function create_customer_profile_transaction($options)
	{
		$this->required_options('transaction', $options);
		$this->required_options('type', $options['transaction']);
		switch($options['transaction']['type']) {
			case 'void':
				$this->required_options('trans_id', $options['transaction']);
				break;
			case 'refund':
				$this->required_options('trans_id', $options['transaction']);
				if(!((isset($options['transaction']['customer_profile_id']) && 
						isset($options['transaction']['customer_payment_profile_id'])) ||
						isset($options['transaction']['credit_card_number_masked']) ||
						(isset($options['transaction']['bank_routing_number_masked']) && 
						isset($options['transaction']['bank_account_number_masked'])))){
					return false;
				}
				break;
			case 'prior_auth_capture':
				$this->required_options('trans_id, amount', $options['transaction']);
			default:
				$this->required_options('customer_profile_id, amount, customer_payment_profile_id', $options['transaction']);
				break;
		}
        $request = $this->build_request('create_customer_profile_transaction', $options);
        return $this->commit('create_customer_profile_transaction', $request);
    }
	
	public function create_customer_profile_transaction_for_refund($options)
	{
		$this->required_options('transaction', $options);
		$options['transaction']['type'] = 'refund';
		$this->required_options('trans_id, amount', $options['transaction']);
        $request = $this->build_request('create_customer_profile_transaction', $options);
        return $this->commit('create_customer_profile_transaction', $request);
	}
	
	public function create_customer_profile_transaction_for_void($options)
	{
		$this->required_options('transaction', $options);
		$options['transaction']['type'] = 'void';
		$this->required_options('trans_id', $options['transaction']);
        $request = $this->build_request('create_customer_profile_transaction', $options);
        return $this->commit('create_customer_profile_transaction', $request);
	}
	
	public function create_customer_shipping_address($options)
	{
		$this->required_options('customer_profile_id, address', $options);
        $request = $this->build_request('create_customer_shipping_address', $options);
        return $this->commit('create_customer_shipping_address', $request);
	}
	
	public function delete_customer_payment_profile($options)
	{
		$this->required_options('customer_profile_id, customer_payment_profile_id', $options);
        $request = $this->build_request('delete_customer_payment_profile', $options);
        return $this->commit('delete_customer_payment_profile', $request);
	}
	
	public function delete_customer_profile($options)
	{
		$this->required_options('customer_profile_id', $options);
        $request = $this->build_request('delete_customer_profile', $options);
        return $this->commit('delete_customer_profile', $request);
	}
	
	public function delete_customer_shipping_address($options)
	{
		$this->required_options('customer_profile_id, customer_address_id', $options);
        $request = $this->build_request('delete_customer_shipping_address', $options);
        return $this->commit('delete_customer_shipping_address', $request);
	}
	
	public function update_customer_shipping_address($options)
	{
		$this->required_options('customer_profile_id, address', $options);
		$this->required_options('customer_address_id', $options['address']);
        $request = $this->build_request('update_customer_shipping_address', $options);
        return $this->commit('update_customer_shipping_address', $request);
	}
	
	public function get_customer_shipping_address($options)
	{
		$this->required_options('customer_profile_id, customer_address_id', $options);
        $request = $this->build_request('get_customer_shipping_address', $options);
        return $this->commit('get_customer_shipping_address', $request);
	}
	
	public function get_customer_payment_profile($options)
	{
		$this->required_options('customer_profile_id, customer_payment_profile_id', $options);
        $request = $this->build_request('get_customer_payment_profile', $options);
        return $this->commit('get_customer_payment_profile', $request);
	}
	
	public function get_customer_profile($options)
	{
		$this->required_options('customer_profile_id', $options);
        $request = $this->build_request('get_customer_profile', $options);
        return $this->commit('get_customer_profile', $request);
	}
    
    private function build_request($action,$options)
    {
		if(in_array($action,$this->CIM_ACTIONS)) {
			throw new Exception('Invalid Customer Information Manager Action: '.$action);
		}
		
		//$options = $this->options + $options;
		
		$string = '<?xml version="1.0" encoding="utf-8"?><'.$this->CIM_ACTIONS[$action].'Request xmlns="'.$this->AUTHORIZE_NET_CIM_NAMESPACE.'"></'.$this->CIM_ACTIONS[$action].'Request>';
		
        $xml = new SimpleXMLElement($string,LIBXML_NOWARNING);//
        $this->add_merchant_authentication($xml);
        $build_method = 'build_'.$action.'_request';
        $this->$build_method($xml,$options);
        //unset($options['login'],$options['password']);
        //$this->_addObject($xml,$options);
        
        $xml_text = $xml->asXML();
        Kohana::$log->add(Log::DEBUG,'XML Request :request',array(':request'=>$xml_text));
		return $xml_text;
	}
    
    private function build_create_customer_profile_request($xml, $options)
    {
		$this->add_profile($xml,$options['profile']);
	}
	
	private function build_create_customer_payment_profile_request($xml, $options)
	{
        $xml->addChild('customerProfileId', $options['customer_profile_id']);
        $paymentprofilexml = $xml->addChild('paymentProfile');
        $this->add_payment_profile($paymentprofilexml, $options['payment_profile']);
		isset($options['validation_mode']) && $xml->addChild('validationMode', $this->CIM_VALIDATION_MODES[$options['validation_mode']]);
	}
	
	private function build_create_customer_profile_transaction_request($xml, $options)
	{
		$this->add_transaction($xml, $options['transaction']);
		isset($options['test']) && $options==TRUE && $xml->addChild('extraOptions','x_test_request=TRUE');
	}
	
	private function build_create_customer_shipping_address_request($xml, $options)
	{
		$xml->addChild('customerProfileId', $options['customer_profile_id']);
        $addressxml = $xml->addChild('address');
        $this->add_address($addressxml, $options['address']);
	}
	
	private function build_delete_customer_shipping_address_request($xml, $options)
	{
		$xml->addChild('customerProfileId', $options['customer_profile_id']);
        $xml->addChild('customerAddressId', $options['customer_address_id']);
	}
	
	private function build_delete_customer_payment_profile_request($xml, $options)
	{
		$xml->addChild('customerProfileId', $options['customer_profile_id']);
        $xml->addChild('customerPaymentProfileId', $options['customer_payment_profile_id']);
	}
	
	private function build_delete_customer_profile_request($xml, $options)
	{
		$xml->addChild('customerProfileId', $options['customer_profile_id']);
	}
	
	private function build_get_customer_profile_request($xml, $options)
	{
		$xml->addChild('customerProfileId', $options['customer_profile_id']);
	}
	
	private function build_get_customer_payment_profile_request($xml, $options)
	{
		$xml->addChild('customerProfileId', $options['customer_profile_id']);
        $xml->addChild('customerPaymentProfileId', $options['customer_payment_profile_id']);
	}
	
	private function build_get_customer_shipping_address_request($xml, $options)
	{
		$xml->addChild('customerProfileId', $options['customer_profile_id']);
        $xml->addChild('customerAddressId', $options['customer_address_id']);
	}
	
	private function build_update_customer_profile_request($xml, $options)
	{
		$this->add_profile($xml, $options['profile'], true);
	}
	
	private function build_update_customer_payment_profile_request($xml, $options)
	{
		$xml->addChild('customerProfileId', $options['customer_profile_id']);
        $paymentprofilexml = $xml->addChild('paymentProfile');
        $this->add_payment_profile($paymentprofilexml, $options['payment_profile']);
		isset($options['validation_mode']) && $xml->addChild('validationMode', $this->CIM_VALIDATION_MODES[$options['validation_mode']]);
	}
	
	private function build_update_customer_shipping_address_request($xml, $options)
	{
		$xml->addChild('customerProfileId', $options['customer_profile_id']);
        $addressxml = $xml->addChild('address');
        $this->add_address($addressxml, $options['address']);
	}
	
	private function build_validate_customer_payment_profile_request($xml, $options)
	{
        $xml->addChild('customerProfileId', $options['customer_profile_id']);
        $xml->addChild('customerPaymentProfileId', $options['customer_payment_profile_id']);
        isset($options['customer_address_id']) && $xml->addChild('customerShippingAddressId', $options['customer_address_id']);
		isset($options['validation_mode']) && $xml->addChild('validationMode', $this->CIM_VALIDATION_MODES[$options['validation_mode']]);
	}
	
	private function add_profile($xml, $options, $update = false)
	{
		$profile = $xml->addChild('profile');
		$profile->addChild('merchantCustomerId',$options['merchant_customer_id']);
		$profile->addChild('description',$options['description']);
		$profile->addChild('email',$options['email']);
		if($update)
			$profile->addChild('customerProfileId',$options['customer_profile_id']);
		else {
			isset($options['payment_profiles']) && $this->add_payment_profiles($xml,$options['payment_profiles']);
			isset($options['ship_to_list']) && $this->add_ship_to_list($xml,$options['ship_to_list']);
		}
		return $this;
	}
	
	private function add_merchant_authentication($xml)
	{
        $auth = $xml->addChild('merchantAuthentication');
        $auth->addChild('name',$this->options['login']);
        $auth->addChild('transactionKey',$this->options['password']);
		return $this;
	}
	
	private function add_payment_profiles($xml, $payment_profiles)
	{
		$profiles = $xml->addChild('paymentProfiles');
		foreach($payment_profiles as $profile)
			$this->add_payment_profile($profiles, $profile);
		return $this;
	}

	private function add_payment_profile($xml, $options)
	{
		# 'individual' or 'business' (optional)
		isset($options['customer_type']) && $xml->addChild('customerType',$options['customer_type']);
		
		if(is_array($options['bill_to'])) {
			$bill_to = $xml->addChild('billTo');
			$this->add_address($bill_to,$options['bill_to']);
		}
		if(is_array($options['payment'])) {
			$payment = $xml->addChild('payment');
            $this->add_credit_card($payment, $options['payment']['credit_card']);
            $this->add_bank_account($payment, $options['payment']['bank_account']);
		}
		isset($options['customer_payment_profile_id']) && $xml->addChild('customerPaymentProfileId',$options['customer_payment_profile_id']);
		return $this;
	}
	
	private function add_ship_to_list($xml, $options)
	{
		$ship_to_list = $xml->addChild('shipToList');
		$this->add_address($ship_to_list,$options);
		return $this;
	}
    /*
     * Adds customer’s bank account information
     * Note: This element should only be included
     * when the payment method is bank account.
     */
    private function add_bank_account($xml, $bank_account)
    {
		if(!in_array($bank_account['account_type'],$this->BANK_ACCOUNT_TYPES)) {
			throw new Exception('Invalid Bank Account Type: '.$bank_account['account_type']);
		}
		if(!in_array($bank_account['echeck_type'],$this->ECHECK_TYPES)) {
			throw new Exception('Invalid eCheck Type: '.$bank_account['echeck_type']);
		}
		$bankaccount = $xml->addChild('bankAccount');
		$bankaccount->addChild('accountType',$this->BANK_ACCOUNT_TYPES[$bank_account['account_type']]);
		$bankaccount->addChild('routingNumber',$bank_account['routing_number']);
		$bankaccount->addChild('accountNumber',$bank_account['account_number']);
		$bankaccount->addChild('nameOnAccount',$bank_account['name_on_account']);
		$bankaccount->addChild('echecktype',$this->ECHECK_TYPES[$bank_account['echeck_type']]);
		isset($bank_account['bank_name']) && $bankaccount->addChild('bankName',$bank_account['bank_name']);
		return $this;
	}
	/*
	 * Adds customer’s credit card information
	 * Note: This element should only be included
	 * when the payment method is credit card.
	 */
	private function add_credit_card($xml, Merchant_Billing_CreditCard $credit_card)
	{
		$creditcard = $xml->addChild('creditCard');
		$creditcard->addChild('cardNumber',$credit_card->number);
		$creditcard->addChild('expirationDate',$this->expdate($credit_card));
		isset($credit_card->verification_value) && $creditcard->addChild('cardCode',$credit_card->verification_value);
		return $this;
	}
	/*
	 * Adds customer’s driver's license information
	 * Note: This element is only required for
	 * Wells Fargo SecureSource eCheck.Net merchants
	 */
	private function add_drivers_license($xml, $drivers_license)
	{
		$driverlicense = $xml->addChild('driversLicense');
		$driverlicense->addChild('state',$driver_license['state']);
		$driverlicense->addChild('number',$driver_license['number']);
        // The date of birth listed on the customer's driver's license
        // YYYY-MM-DD
		$driverlicense->addChild('dateOfBirth',$driver_license['date_of_birth']);
		return $this;
	}
	
    private function add_order($xml, $order)
    {
		$orderxml = $xml->addChild('order');
		isset($order['invoice_number']) && $orderxml->addChild('invoiceNumber',$order['invoice_number']);
		isset($order['description']) && $orderxml->addChild('description',$order['description']);
		isset($order['purchase_order_number']) && $orderxml->addChild('purchaseOrderNumber',$order['purchase_order_number']);
	}
	
	private function add_address($xml, $address)
    {
		$xml->addChild('firstName', $address['first_name']);
        $xml->addChild('lastName', $address['last_name']);
        $xml->addChild('company', $address['company']);
        isset($address['address1']) && $xml->addChild('address', $address['address1']);
        isset($address['address']) && $xml->addChild('address', $address['address']);
        $xml->addChild('city', $address['city']);
        $xml->addChild('state', $address['state']);
        $xml->addChild('zip', $address['zip']);
        $xml->addChild('country', $address['country']);
        isset($address['phone_number']) && $xml->addChild('phoneNumber', $address['phone_number']);
        isset($address['fax_number']) && $xml->addChild('faxNumber', $address['fax_number']);
        isset($address['customer_address_id']) && $xml->addChild('customerAddressId', $address['customer_address_id']);
		return $this;
    }
    
	private function add_transaction($xml, $transaction)
	{
		if(!in_array($transaction['type'],$this->CIM_TRANSACTION_TYPES)) {
			throw new Exception('Invalid Customer Information Manager Transaction Type: '.$transaction['type']);
		}
		$transactionxml = $xml->addChild('transaction');
		switch($transaction['type']){
			case 'void':
				isset($transaction['customer_profile_id']) && $transactionxml->addChild('customerProfileId',$transaction['customer_profile_id']);
				isset($transaction['customer_payment_profile_id']) && $transactionxml->addChild('customerPaymentProfileId',$transaction['customer_payment_profile_id']);
				isset($transaction['customer_shipping_address_id']) && $transactionxml->addChild('customerShippingAddressId',$transaction['customer_shipping_address_id']);
				$transactionxml->addChild('transId',$transaction['trans_id']);
				break;
			case 'refund':
				$transactionxml->addChild('amount',$transaction['amount']);
				isset($transaction['customer_profile_id']) && $transactionxml->addChild('customerProfileId',$transaction['customer_profile_id']);
				isset($transaction['customer_payment_profile_id']) && $transactionxml->addChild('customerPaymentProfileId',$transaction['customer_payment_profile_id']);
				isset($transaction['customer_shipping_address_id']) && $transactionxml->addChild('customerShippingAddressId',$transaction['customer_shipping_address_id']);
				isset($transaction['credit_card_number_masked']) && $transactionxml->addChild('creditCardNumberMasked',$transaction['credit_card_number_masked']);
				isset($transaction['bank_routing_number_masked']) && $transactionxml->addChild('bankRoutingNumberMasked',$transaction['bank_routing_number_masked']);
				isset($transaction['bank_account_number_masked']) && $transactionxml->addChild('bankAccountNumberMasked',$transaction['bank_account_number_masked']);
				$transactionxml->addChild('transId',$transaction['trans_id']);
				break;
			case 'prior_auth_capture':
				$transactionxml->addChild('amount',$transaction['amount']);
				$transactionxml->addChild('transId',$transaction['trans_id']);
				break;
			default:
				$transactionxml->addChild('amount',$transaction['amount']);
				$transactionxml->addChild('customerProfileId',$transaction['customer_profile_id']);
				$transactionxml->addChild('customerPaymentProfileId',$transaction['customer_payment_profile_id']);
				($transaction['type']=='capture_only') && $transactionxml->addChild('approvalCode',$transaction['approval_code']);
				break;
		}
		isset($transaction['order']) && $this->add_order($transactionxml, $transaction['order']);
	}

    private function expdate(Merchant_Billing_CreditCard $creditcard)
    {
        $year = $this->cc_format($creditcard->year, 'two_digits');
        $month = $this->cc_format($creditcard->month, 'two_digits');
        return $month . $year;
    }
	
    private function cim_parse($body)
    {
		$xml = new SimpleXMLElement($body,LIBXML_NOWARNING);//
        $response = array();
        $response['ref_id'] = $xml->refId;
        $response['result_code'] = $xml->messages->resultCode;
        $response['code'] = $xml->messages->message->code;
        $response['text'] = $xml->messages->message->text;
        $response['customer_profile_id'] = $xml->customerProfileId;
        return $response;
    }
    
    private function cim_success_from($response)
    {
        return $response['result_code'] == 'Ok';
    }
    
    private function cim_message_from($response)
    {
        return $response['text'];
    }
    
    private function commit($action, $request)
    {
        $url = $this->is_test() ? self::TEST_URL : self::LIVE_URL;
        
		$headers = array("Content-type: text/xml");
		
        $data = $this->ssl_post($url, $request, array('headers'=>$headers));
		Kohana::$log->add(Log::DEBUG,'Merchant Response :response',array(':response'=>$data));

        $response = $this->cim_parse($data);

        return new Merchant_Billing_Response(
            $this->cim_success_from($response),
            $this->cim_message_from($response),
            $response,
            array(
                'test' => $this->is_test(),
                'authorization' => $response['customer_profile_id'],
            )
        );
    }
}
