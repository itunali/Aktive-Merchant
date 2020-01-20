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

class Merchant_Billing_Braintree extends Merchant_Billing_Gateway implements Merchant_Billing_Gateway_Store, Merchant_Billing_Gateway_Credit, Merchant_Billing_Gateway_Charge
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
				case 'sale':
					$result = Braintree_Transaction::sale($options);
					break;
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
				case 'transaction_find':
					$result = Braintree_Transaction::find($options);
					break;
				case 'settle':
					$result = Braintree_Transaction::submitForSettlement($options['transaction_id'],$options['amount']);
					break;
				case 'refund':
					$result = Braintree_Transaction::refund($options['transaction_id'],$options['amount']);
					break;
				case 'customer_find':
					$result = Braintree_Customer::find($options);
					break;
				case 'void':
					$result = Braintree_Transaction::void($options);
					break;
				case 'credit':
					if(isset($options['token'])){
						$result = Braintree_Transaction::credit($options['token'],array('amount'=>$options['amount']));
					} else {
						$result = Braintree_Transaction::credit($options);
					}
					break;
			}
		} catch (Braintree_Exception_NotFound $e) {
				throw new Merchant_Billing_Exception($e->getMessage()?$e->getMessage():'Item Not Found');
		}
		$result = $this->_parse_result($result);

		$response_options = array();
		$response_options['test'] = $this->is_test();
		isset($result['transaction_id']) && $response_options['authorization'] = $result['transaction_id'];
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
		!empty($options['country']) && $data['countryCodeAlpha3'] = Merchant_Country::find($options['country'])->code('alpha3')->__toString();
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
		!empty($options['country']) && $data['countryCodeAlpha3'] = Merchant_Country::find($options['country'])->code('alpha3')->__toString();
		return $this->commit('update_address',$data);
	}

	private function _parse_result($result)
	{
		$return = array();
		$class_name = str_replace('\\','_',get_class($result));
		switch ($class_name) {
			case 'Braintree_Result_Error':
				$return['sended_params'] = $result->params;
				$return['errors'] = $result->errors->deepAll();
				$return['message'] = $result->message;
				if(isset($result->verification)){
					$return['fraud_review'] = $result->verification->processorResponseCode==2000?true:false;
					$return['avs_result'] = array(
						'code'=>$result->verification->avsErrorResponseCode,
						'street_match'=>$result->verification->avsStreetAddressResponseCode,
						'postal_match'=>$result->verification->avsPostalCodeResponseCode
					);
					$return['cvv_result'] = $result->verification->cvvResponseCode;
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
				if(isset($result->transaction)){
					$return = array_merge($return,$this->_parse_result($result->transaction));
				}
				break;
			case 'Braintree_Customer':
				$return['created'] = $result->createdAt;
				$return['updated'] = $result->updatedAt;
				$return['customfields'] = $result->customFields;
				foreach ($result->creditCards as $cc) {
					$return['creditcards'][] = $this->_parse_result($cc);
				}
				foreach ($result->addresses as $addr) {
					$return['addresses'][] = $this->_parse_result($addr);
				}
				foreach ($result->paypalAccounts as $paypal_account) {
					$return['paypalaccounts'][] = $this->_parse_result($paypal_account);
				}
			case 'Braintree_Transaction_CustomerDetails':
				$return['merchant_customer_id'] = $result->id;
				$return['first_name'] = $result->firstName;
				$return['last_name'] = $result->lastName;
				$return['company'] = $result->company;
				$return['email'] = $result->email;
				$return['phone'] = $result->phone;
				$return['fax'] = $result->fax;
				$return['website'] = $result->website;
				break;
			case 'Braintree_CreditCard':
				$return['created'] = $result->createdAt;
				$return['updated'] = $result->updatedAt;
				$return['default'] = $result->default;
				$return['expired'] = $result->expired;
				$return['subscriptions'] = $result->subscriptions;
				$return['merchant_customer_id'] = $result->customerId;
				$return['billing_address'] = $this->_parse_result($result->billingAddress);
			case 'Braintree_Transaction_CreditCardDetails':
				$return['bin'] = $result->bin;
				$return['month'] = $result->expirationMonth;
				$return['year'] = $result->expirationYear;
				$return['last4'] = $result->last4;
				$return['holdername'] = $result->cardholderName;
				$return['customer_location'] = $result->customerLocation;
				$return['token'] = $result->token;
				$return['expire_date'] = $result->expirationDate;
				$return['masked'] = $result->maskedNumber;
				$return['type'] = $result->cardType;
				break;
			case 'Braintree_Address':
				$return['merchant_customer_id'] = $result->customerId;
				$return['create'] = $result->createdAt;
				$return['update'] = $result->updatedAt;
			case 'Braintree_Transaction_AddressDetails':
				$return['id'] = $result->id;
				$return['first_name'] = $result->firstName;
				$return['last_name'] = $result->lastName;
				$return['company'] = $result->company;
				$return['address'] = $result->streetAddress;
				$return['address_ext'] = $result->extendedAddress;
				$return['city'] = $result->locality;
				$return['state'] = $result->region;
				$return['zip'] = $result->postalCode;
				$return['country'] = !empty($result->countryCodeAlpha3)?Merchant_Country::find($result->countryCodeAlpha3)->code('alpha3')->__toString():NULL;
				break;
			case 'Braintree_Transaction':
				$return['transaction_id'] = $result->id;
				$return['status'] = $result->status;
				$return['type'] = $result->type;
				$return['currency_code'] = $result->currencyIsoCode;
				$return['amount'] = $result->amount;
				$return['merchant_account_id'] = $result->merchantAccountId;
				$return['order_id'] = $result->orderId;
				$return['created'] = $result->createdAt;
				$return['updated'] = $result->updatedAt;
				$return['customer'] = array(
					'id' => $result->customer['firstName'],
					'first_name' => $result->customer['firstName'],
					'last_name' => $result->customer['lastName'],
					'company' => $result->customer['company'],
					'email' => $result->customer['email'],
					'phone' => $result->customer['phone'],
					'fax' => $result->customer['fax'],
					'website' => $result->customer['website'],
				);
				$return['billing'] = array(
					'id' => $result->billing['id'],
					'first_name' => $result->billing['firstName'],
					'last_name' => $result->billing['lastName'],
					'company' => $result->billing['company'],
					'address' => $result->billing['streetAddress'],
					'address_ext' => $result->billing['extendedAddress'],
					'city' => $result->billing['locality'],
					'state' => $result->billing['region'],
					'zip' => $result->billing['postalCode'],
					'country' => $result->billing['countryCodeAlpha3'],
				);
				$return['shipping'] = array(
					'id' => $result->billing['id'],
					'first_name' => $result->billing['firstName'],
					'last_name' => $result->billing['lastName'],
					'company' => $result->billing['company'],
					'address' => $result->billing['streetAddress'],
					'address_ext' => $result->billing['extendedAddress'],
					'city' => $result->billing['locality'],
					'state' => $result->billing['region'],
					'zip' => $result->billing['postalCode'],
					'country' => $result->billing['countryCodeAlpha3'],
				);
				$return['refund_id'] = $result->refundId;
				$return['refund_ids'] = $result->refundIds;
				$return['refunded_transaction_id'] = $result->refundedTransactionId;
				$return['fraud_review'] = $result->processorResponseCode==2000?true:false;
				$return['avs_result'] = array(
					'code'=>$result->avsErrorResponseCode,
					'street_match'=>$result->avsStreetAddressResponseCode,
					'postal_match'=>$result->avsPostalCodeResponseCode
				);
				$return['cvv_result'] = $result->cvvResponseCode;

				$return['settlement_batch_id'] = $result->settlementBatchId;
				$return['custom_fields'] = $result->customFields;
				$return['processor_authorization_code'] = $result->processorAuthorizationCode;
				$return['processor_response_code'] = $result->processorResponseCode;
				$return['processor_response_text'] = $result->processorResponseText;
				$return['purchase_order_number'] = $result->purchaseOrderNumber;
				$return['tax_amount'] = $result->taxAmount;
				$return['tax'] = $result->taxExempt;
				$return['creditcard'] = array(
					'token' => $result->creditCard['token'],
					'bin' => $result->creditCard['bin'],
					'last4' => $result->creditCard['last4'],
					'type' => $result->creditCard['cardType'],
					'month' => $result->creditCard['expirationMonth'],
					'year' => $result->creditCard['expirationYear'],
					'location' => $result->creditCard['customerLocation'],
					'holdername' => $result->creditCard['cardholderName'],
				);
				foreach ($result->statusHistory as $status) {
					$return['status_history'][] = $this->_parse_result($status);
				}

				$return['plan_id'] = $result->planId;
				$return['subscription_id'] = $result->subscriptionId;
				$return['subscription'] = array(
					'period_end' => $result->subscription['billingPeriodEndDate'],
					'period_start' => $result->subscription['billingPeriodStartDate'],
				);
				$return['descriptor'] = $this->_parse_result($result->descriptor);
				$return['creditcard_details'] = $this->_parse_result($result->creditCardDetails);
				$return['billing_details'] = $this->_parse_result($result->billingDetails);
				$return['shipping_details'] = $this->_parse_result($result->shippingDetails);
				$return['subscription_details'] = $this->_parse_result($result->subscriptionDetails);
				$return['addons'] = $result->addOns;
				$return['discounts'] = $result->discounts;
				isset($result->paypalDetails) && $return['paypal_details'] = $this->_parse_result($result->paypalDetails);
				break;
			case 'Braintree_Transaction_StatusDetails':
				$return['timestamp'] = $result->timestamp;
				$return['status'] = $result->status;
				$return['amount'] = $result->amount;
				$return['user'] = $result->user;
				$return['transaction_source'] = $result->transactionSource;
				break;
			case 'Braintree_Descriptor':
				$return['name'] = $result->name;
				$return['phone'] = $result->phone;
				break;
			case 'Braintree_Transaction_PayPalDetails':
				$return['token'] = $result->token;
				$return['payer_email'] = $result->payerEmail;
				$return['payment_id'] = $result->paymentId;
				$return['authorization_id'] = $result->authorizationId;
				$return['image_url'] = $result->imageUrl;
				$return['debug_id'] = $result->debugId;
				$return['payee_email'] = $result->payeeEmail;
				break;
			case 'Braintree_PayPalAccount':
				$return['token'] = $result->token;
				$return['email'] = $result->email;
				$return['default'] = $result->default;
				$return['image_url'] = $result->imageUrl;
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
		isset($options['device_data']) && $data['deviceData'] = $options['device_data'];

		if($options['_creditcard'] instanceof Merchant_Billing_CreditCard) {
			$creditcard = $options['_creditcard'];
			$data['creditCard'] = array(
				'expirationDate' => date('m/y',$creditcard->expire_date()->expiration()),
				'cardholderName' => $creditcard->name(),
				'cvv' => $creditcard->verification_value,
			);
			// invalid card number means do not update
			!empty($creditcard->number) && $data['creditCard']['number'] = $creditcard->number;
			if(isset($options['billing_address_id'])) {
				$data['creditCard']['billingAddressId'] = $options['billing_address_id'];
			}
			if(isset($options['options'])) {
				isset($options['options']['verify_card']) && $data['creditCard']['options']['verifyCard'] = $options['options']['verify_card'];
				!empty($options['options']['update_token']) && $data['creditCard']['options']['updateExistingToken'] = $options['options']['update_token'];
			}
		}
		if(!empty($options['payment_nonce'])){
			$data['creditCard'] = array(
				'paymentMethodNonce' => $options['payment_nonce'],
			);
			if(isset($options['billing_address_id'])) {
				$data['creditCard']['billingAddressId'] = $options['billing_address_id'];
			}
			if(isset($options['options'])) {
				$data['creditCard']['options'] = array();
				isset($options['options']['verify_card']) && $data['creditCard']['options']['verifyCard'] = $options['options']['verify_card'];
				!empty($options['options']['update_token']) && $data['creditCard']['options']['updateExistingToken'] = $options['options']['update_token'];
			}
		}
		return $data;
	}
	// To void a transaction, the status must be authorized or submitted for settlement.
	public function void($authorization, $options = array())
	{
		return $this->commit('void',$authorization);
	}

	public function credit($money=NULL, $identification, $options = array())
	{
		if(is_numeric($money))
		{
			$money = $this->amount($money);
		}
		return $this->commit('refund',array('transaction_id'=>$identification,'amount'=>$money));
	}

	public function capture($money=NULL, $authorization, $options = array())
	{
		if(is_numeric($money))
		{
			$money = $this->amount($money);
		}
		return $this->commit('settle',array('transaction_id'=>$authorization,'amount'=>$money));

	}
	private function _transaction($money, Merchant_Billing_CreditCard $creditcard = NULL, $options = array())
	{
		$amount = $this->amount($money);
		$data = $this->_transaction_data_build(array_merge(array('_creditcard'=>$creditcard,'amount'=>$amount),$options));
		return $this->commit('sale',$data);
	}
	public function purchase($money, Merchant_Billing_CreditCard $creditcard = NULL, $options = array())
	{
		$options['settle'] = true;
		return $this->_transaction($money, $creditcard, $options);
	}

	public function authorize($money, Merchant_Billing_CreditCard $creditcard = NULL, $options = array())
	{
		$options['settle'] = false;
		return $this->_transaction($money, $creditcard, $options);
	}

	private function _transaction_data_build($options)
	{
		$this->required_options('amount', $options);
		$data = array(
			'amount'=>$options['amount'],
		);
		!empty($options['order_id']) && $data['orderId'] = $options['order_id'];
		!empty($options['merchant_account_id']) && $data['merchantAccountId'] = $options['merchant_account_id'];
		!empty($options['recurring']) && $data['recurring'] = $options['recurring'];

		$data['creditCard'] = array();
		if($options['_creditcard'] instanceof Merchant_Billing_CreditCard) {
			$data['creditCard']['number'] = $options['_creditcard']->number;
			$data['creditCard']['expirationDate'] = date('m/y',$options['_creditcard']->expire_date()->expiration());
			$options['_creditcard']->name()!='' && $data['creditCard']['cardholderName'] = $options['_creditcard']->name();
			!empty($options['_creditcard']->verification_value) && $data['creditCard']['cvv'] = $options['_creditcard']->verification_value;
			// store this card on vault
			!empty($options['token']) && $data['creditCard']['token'] = $options['token'];
		}elseif(!empty($options['payment_nonce'])){
			$data['paymentMethodNonce'] = $options['payment_nonce'];
		}else{
			// use recorded vault record for card details
			$this->required_options('token', $options);
			$data['paymentMethodToken'] = $options['token'];
		}

		// use vault saved customer record
		if(!empty($options['merchant_customer_id'])) {
			$data['customerId'] = $options['merchant_customer_id'];
		}else{
			$data['customer'] = array();
			// for vault store
			!empty($options['customer_id']) && $data['customer']['id'] = $options['customer_id'];
			!empty($options['first_name']) && $data['customer']['firstName'] = $options['first_name'];
			!empty($options['last_name']) && $data['customer']['lastName'] = $options['last_name'];
			!empty($options['company']) && $data['customer']['company'] = $options['company'];
			!empty($options['email']) && $data['customer']['email'] = $options['email'];
			!empty($options['phone']) && $data['customer']['phone'] = $options['phone'];
			!empty($options['fax']) && $data['customer']['fax'] = $options['fax'];
			!empty($options['website']) && $data['customer']['website'] = $options['website'];
		}

		$data['billing'] = array();
		if(!empty($options['billing_address'])) {
			$billing_address = explode('|',wordwrap($options['billing_address'],255,'|'));
			$data['billing']['streetAddress'] = array_shift($billing_address);
			$data['billing']['extendedAddress'] = array_shift($billing_address);
		}
		!empty($options['billing_first_name']) && $data['billing']['firstName'] = $options['billing_first_name'];
		!empty($options['billing_last_name']) && $data['billing']['lastName'] = $options['billing_last_name'];
		!empty($options['billing_company']) && $data['billing']['company'] = $options['billing_company'];
		!empty($options['billing_city']) && $data['billing']['locality'] = $options['billing_city'];
		!empty($options['billing_state']) && $data['billing']['region'] = $options['billing_state'];
		!empty($options['billing_zip']) && $data['billing']['postalCode'] = $options['billing_zip'];
		!empty($options['billing_country']) && $data['billing']['countryCodeAlpha3'] = Merchant_Country::find($options['billing_country'])->code('alpha3')->__toString();

		$data['shipping'] = array();
		if(!empty($options['shipping_address'])) {
			$shipping_address = explode('|',wordwrap($options['shipping_address'],255,'|'));
			$data['shipping']['streetAddress'] = array_shift($shipping_address);
			$data['shipping']['extendedAddress'] = array_shift($shipping_address);
		}
		!empty($options['shipping_first_name']) && $data['shipping']['firstName'] = $options['shipping_first_name'];
		!empty($options['shipping_last_name']) && $data['shipping']['lastName'] = $options['shipping_last_name'];
		!empty($options['shipping_company']) && $data['shipping']['company'] = $options['shipping_company'];
		!empty($options['shipping_city']) && $data['shipping']['locality'] = $options['shipping_city'];
		!empty($options['shipping_state']) && $data['shipping']['region'] = $options['shipping_state'];
		!empty($options['shipping_zip']) && $data['shipping']['postalCode'] = $options['shipping_zip'];
		!empty($options['shipping_country']) && $data['shipping']['countryCodeAlpha3'] = Merchant_Country::find($options['shipping_country'])->code('alpha3')->__toString();

		!empty($options['invoice_number']) && $data['purchaseOrderNumber'] = $options['invoice_number'];
		// only This amount is not automatically added to the total amount of this transaction
		// you have to manually add the tax amount to the transaction’s amount
		!empty($options['tax_amount']) && $data['taxAmount'] = $options['tax_amount'];
		$data['options'] = array();
		isset($options['settle']) && $data['options']['submitForSettlement'] = $options['settle'];
		isset($options['store_in_vault_on_success']) && $data['options']['storeInVaultOnSuccess'] = $options['store_in_vault_on_success'];
		isset($options['add_billing_address_to_payment_method']) && $data['options']['addBillingAddressToPaymentMethod'] = $options['add_billing_address_to_payment_method'];
		isset($options['tax']) && $data['taxExempt'] = $options['tax'];
		isset($options['device_data']) && $data['deviceData'] = $options['device_data'];
		if(isset($options['custom_fields']) && is_array($options['custom_fields'])) {
			$data['customFields'] = $options['custom_fields'];
		}

		$descriptor = array();
		!empty($options['descriptor_name']) && $descriptor['name'] = $options['descriptor_name'];
		!empty($options['descriptor_phone']) && $descriptor['phone'] = $options['descriptor_phone'];
		if(count($descriptor)>0) {
			$data['descriptor'] = $descriptor;
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

	// disabled credit to creditcard transactions by default
	public function credit_to_creditcard($money, $identification = NULL, $options = array())
	{
		if(is_null($identification)) {
			return $this->commit('credit',array('token'=>$identification,'amount'=>$money));
		}else{
			$this->required_options('card_number,card_month,card_year', $options);
			return $this->commit('credit',array(
				'amount' => $money,
				'creditCard' => array(
					'number' => $options['card_number'],
					'expirationDate' => $options['card_month'].'/'.$options['card_year'],
				)
			));
		}
	}

	public function get_customer_profile($options)
	{
		$this->required_options('customer_profile_id', $options);
		return $this->commit('customer_find',$options['customer_profile_id']);
	}

	public function get_transaction($transaction_id)
	{
		return $this->commit('transaction_find',$transaction_id);
	}

	public function amount($money)
	{
		if (!is_numeric($money) || $money < 0) {
			throw new Merchant_Billing_Exception('money amount must be a positive Integer in cents.');
		}

		return number_format($money, 2, '.', '');
	}

	public function generate_unique_id($options = NULL)
	{
		$opts = array();
		isset($options['merchant_account_id']) && $opts['merchantAccountId'] = $options['merchant_account_id'];
		isset($options['customer_id']) && $opts['customerId'] = $options['customer_id'];
		return Braintree_ClientToken::generate($opts);
	}
}
