<?php

/**
 * Description of Merchant_Billing_Braintree
 *
 * @package Aktive Merchant
 * @author  Ibrahim Tunali <ibrahimtunali@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php
 */
$default_bt_path = dirname(__FILE__).'/braintree/lib/Braintree.php';
if (!class_exists('Braintree') && !file_exists($default_bt_path)) {
	throw new Exception('Braintree API not found');
} else {
	require_once($default_bt_path);
}

class Merchant_Billing_Braintree extends Merchant_Billing_Gateway implements Merchant_Billing_Gateway_Store
{
	public static $default_currency = 'USD';
	public static $supported_countries = array('US');
	public static $supported_cardtypes = array('visa', 'master', 'american_express', 'discover', 'jcb', 'dinners_club');
	public static $homepage_url = 'http://www.braintreepaymentsolutions.com';
	public static $display_name = 'Braintree';

	private $options = array();
	
	public function __construct($options)
	{
		parent::__construct($options);

		$this->required_options('merchant_id, public_key, private_key', $options);
		$this->options = $options;
		Braintree_Configuration::environment($this->is_test()?'sandbox':'production');
		Braintree_Configuration::merchantId($options['merchant_id']);
		Braintree_Configuration::publicKey($options['public_key']);
		Braintree_Configuration::privateKey($options['private_key']);

	}

	private function commit($action, $options)
	{
		try {

			switch ($action) {
				case 'store':
					$result = Braintree_Customer::create($options);
					break;
				case 'unstore':
					$result = Braintree_Customer::delete($options);
					break;
				case 'add_address':
					$result = Braintree_Address::create($options);
					break;
				case 'update_address':
					$merchant_customer_id = $options['_merchant_customer_id'];
					$address_id = $options['_address_id'];
					unset($options['_merchant_customer_id'],$options['_address_id']);
					$result = Braintree_Address::update($merchant_customer_id, $address_id, $options);
					break;
				case 'update':
					$merchant_customer_id = $options['_merchant_customer_id'];
					unset($options['_merchant_customer_id']);
					$result = Braintree_Customer::update($merchant_customer_id, $options);
					break;
				case 'find':
					$result = Braintree_Customer::find($options);
					/*return new Merchant_Billing_Response(
						true,
						'',
						array(
							'merchant_customer_id' => $result->id,
							'first_name' => $result->firstName,
							'last_name' => $result->lastName,
							'company' => $result->company,
							'email' => $result->email,
							'phone' => $result->phone,
							'fax' => $result->fax,
							'website' => $result->website,
							'creditcards' => $result->creditCards,
							'addresses' => $result->addresses,
							'custom_fields' => $result->customFields,
						),
						array('test'=>$this->is_test())
					);*/
					break;
			}
		} catch (Braintree_Exception_NotFound $e) {
				throw new Merchant_Billing_Exception($e->getMessage()?$e->getMessage():'Item Not Found');
		}
		//print_r($result);
		$result = $this->_parse_result($result);
		
		$response_options = array();
		$response_options['test'] = $this->is_test();
		isset($result['fraud_review']) && $response_options['fraud_review'] = $result['fraud_review'];
		isset($result['avs_result']) && $response_options['avs_result'] = $result['avs_result'];
		isset($result['cvv_result']) && $response_options['cvv_result'] = $result['cvv_result'];
		
		return new Merchant_Billing_Response(
			isset($result['success'])?$result['success']:true,
			isset($result['message'])?$result['message']:'',
			$result,
			$response_options
		);
	}

	public function store_address($options)
	{
		$this->required_options('merchant_customer_id,first_name,last_name,city,address,zip,country,state', $options);
		$data['customerId'] = $options['merchant_customer_id'];
		!empty($options['first_name']) && $data['firstName'] = $options['first_name'];
		!empty($options['last_name']) && $data['lastName'] = $options['last_name'];
		!empty($options['company']) && $data['company'] = $options['company'];
		!empty($options['city']) && $data['locality'] = $options['city'];
		!empty($options['address']) && $data['streetAddress'] = $options['address'];
		!empty($options['address_ext']) && $data['extendedAddress'] = $options['address_ext'];
		!empty($options['state']) && $data['region'] = $options['state'];
		!empty($options['zip']) && $data['postalCode'] = $options['zip'];
		!empty($options['country']) && $data['countryCodeAlpha2'] = Merchant_Country::find($options['country'])->code('alpha2');
		return $this->commit('add_address',$data);
	}

	public function update_address($options)
	{
		$this->required_options('merchant_customer_id,id,first_name,last_name,city,address,zip,country,state', $options);
		$data['_merchant_customer_id'] = $options['merchant_customer_id'];
		$data['_address_id'] = $options['id'];
		!empty($options['first_name']) && $data['firstName'] = $options['first_name'];
		!empty($options['last_name']) && $data['lastName'] = $options['last_name'];
		!empty($options['company']) && $data['company'] = $options['company'];
		!empty($options['city']) && $data['locality'] = $options['city'];
		!empty($options['address']) && $data['streetAddress'] = $options['address'];
		!empty($options['address_ext']) && $data['extendedAddress'] = $options['address_ext'];
		!empty($options['state']) && $data['region'] = $options['state'];
		!empty($options['zip']) && $data['postalCode'] = $options['zip'];
		!empty($options['country']) && $data['countryCodeAlpha2'] = Merchant_Country::find($options['country'])->code('alpha2');
		return $this->commit('update_address',$data);
	}

	private function _parse_result($result)
	{
		$return = array();
		switch (get_class($result)) {
			case 'Braintree_Result_Error':
				$return['sended_params'] = $result->params;
				$return['errors'] = $result->errors->deepAll();
				$return['message'] = $result->message;
				if(isset($result->errors->verification)){
					$return['fraud_review'] = $result->errors->verification->processorResponseCode==2000?true:false;
					$return['avs_result'] = array(
						'code'=>$result->errors->verification->avsErrorResponseCode,
						'street_match'=>$result->errors->verification->avsStreetAddressResponseCode,
						'postal_match'=>$result->errors->verification->avsPostalCodeResponseCode
					);
					$return['cvv_result'] = $result->errors->verification->cvvResponseCode;
				}

			case 'Braintree_Result_Successful':
				//'authorization' => $response['transaction_id'],
				$return['success'] = $result->success;
				if(isset($result->customer)){
					$return = array_merge($return,$this->_parse_result($result->customer));	
				}
				if(isset($result->address)){
					$return = array_merge($return,$this->_parse_result($result->address));	
				}
				break;
			case 'Braintree_Customer':
				$return['merchant_customer_id'] = $result->id;
				$return['first_name'] = $result->firstName;
				$return['last_name'] = $result->lastName;
				$return['company'] = $result->company;
				$return['email'] = $result->email;
				$return['phone'] = $result->phone;
				$return['fax'] = $result->fax;
				$return['website'] = $result->website;
				$return['created'] = $result->createdAt;
				$return['updated'] = $result->updatedAt;
				$return['customfields'] = $result->customFields;
				foreach ($result->creditCards as $key => $cc) {
					$return['creditcards'][] = $this->_parse_result($cc);
				}
				foreach ($result->addresses as $key => $addr) {
					$return['addresses'][] = $this->_parse_result($addr);
				}
				break;
			case 'Braintree_CreditCard':
				$return['bin'] = $result->bin;
				$return['month'] = $result->expirationMonth;
				$return['year'] = $result->expirationYear;
				$return['last4'] = $result->last4;
				$return['holdername'] = $result->cardholderName;
				$return['created'] = $result->createdAt;
				$return['updated'] = $result->updatedAt;
				$return['default'] = $result->default;
				$return['expired'] = $result->expired;
				$return['customer_location'] = $result->customerLocation;
				$return['subscriptions'] = $result->subscriptions;
				$return['token'] = $result->token;
				$return['expire_date'] = $result->expirationDate;
				$return['masked'] = $result->maskedNumber;
				$return['merchant_customer_id'] = $result->customerId;
				$return['type'] = $result->cardType;
				$return['billing_address'] = $this->_parse_result($result->billingAddress);
				break;
			case 'Braintree_Address':
				$return['id'] = $result->id;
				$return['merchant_customer_id'] = $result->customerId;
				$return['first_name'] = $result->firstName;
				$return['last_name'] = $result->lastName;
				$return['company'] = $result->company;
				$return['address'] = $result->streetAddress;
				$return['address_ext'] = $result->extendedAddress;
				$return['city'] = $result->locality;
				$return['state'] = $result->region;
				$return['zip'] = $result->postalCode;
				$return['country'] = Merchant_Country::find($result->countryCodeAlpha3)->code('alpha3');
				$return['create'] = $result->createdAt;
				$return['update'] = $result->updatedAt;
				break;
		}
		return $return;
	}

	private function _data_build($options)
	{
		$data = array();
		!empty($options['first_name']) && $data['firstName'] = $options['first_name'];
		!empty($options['last_name']) && $data['lastName'] = $options['last_name'];
		!empty($options['company']) && $data['company'] = $options['company'];
		!empty($options['merchant_customer_id']) && $data['id'] = $options['merchant_customer_id'];
		!empty($options['email']) && $data['email'] = $options['email'];
		!empty($options['phone']) && $data['phone'] = $options['phone'];
		!empty($options['fax']) && $data['fax'] = $options['fax'];
		!empty($options['website']) && $data['website'] = $options['website'];

		if($options['_creditcard'] instanceof Merchant_Billing_CreditCard) {
			$creditcard = $options['_creditcard'];
			$data['creditCard'] = array(
				'number' => $creditcard->number,
				'expirationDate' => date('m/y',$creditcard->expire_date()->expiration()),
				'cvv' => $creditcard->verification_value,
				'cardholderName' => $creditcard->name(),
			);
			if(isset($options['billing_address_id'])) {
				$data['creditCard']['billingAddressId'] = $options['billing_address_id'];
			}
			/*if(isset($options['billing_first_name'])) {
				$data['creditCard']['billingAddress']['firstName'] = $options['billing_first_name'];
				!empty($options['billing_last_name']) && $data['creditCard']['billingAddress']['lastName'] = $options['billing_last_name'];
				!empty($options['billing_company']) && $data['creditCard']['billingAddress']['company'] = $options['billing_company'];
				!empty($options['billing_city']) && $data['creditCard']['billingAddress']['locality'] = $options['billing_city'];
				!empty($options['billing_address']) && $data['creditCard']['billingAddress']['streetAddress'] = $options['billing_address'];
				!empty($options['billing_address_ext']) && $data['creditCard']['billingAddress']['extendedAddress'] = $options['billing_address_ext'];
				!empty($options['billing_state']) && $data['creditCard']['billingAddress']['region'] = $options['billing_state'];
				!empty($options['billing_zip']) && $data['creditCard']['billingAddress']['postalCode'] = $options['billing_zip'];
				!empty($options['billing_country']) && $data['creditCard']['billingAddress']['countryCodeAlpha2'] = Merchant_Country::find($options['billing_country'])->code('alpha2');
				$data['creditCard']['billingAddress']['options'] = array('updateExisting'=> true);
			}*/
			if(isset($options['options'])) {
				isset($options['options']['verify_card']) && $data['creditCard']['options']['verifyCard'] = $options['options']['verify_card'];
				!empty($options['options']['update_token']) && $data['creditCard']['options']['updateExistingToken'] = $options['options']['update_token'];
			}
		}
		return $data;
	}

	public function update($customer_vault_id, Merchant_Billing_CreditCard $creditcard = NULL, $options)
	{
		$data = $this->_data_build(array_merge(array('_creditcard'=>$creditcard),$options));
		return $this->commit('update',array_merge(array('_merchant_customer_id'=>$customer_vault_id),$data));
	}

	public function store(Merchant_Billing_CreditCard $creditcard = NULL, $options)
	{
		$data = $this->_data_build(array_merge(array('_creditcard'=>$creditcard),$options));
		return $this->commit('store',$data);
	}

	public function unstore(Merchant_Billing_CreditCard $creditcard = NULL, $customer_vault_id = NULL)
	{
		if(is_null($customer_vault_id)) {
			throw new Merchant_Billing_Exception("Customer vault id is required!");
		}
		return $this->commit('unstore',$customer_vault_id);
	}

	public function get_customer_profile($options)
	{
		$this->required_options('customer_profile_id', $options);
		return $this->commit('find',$options['customer_profile_id']);
	}
}