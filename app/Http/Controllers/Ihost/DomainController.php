<?php

namespace App\Http\Controllers\Ihost;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use File;

use App\Models\Ihost\Domain;
use App\Models\Ihost\Product;
use App\Models\Price;

class DomainController extends Controller
{
	protected function connect_reseller()
	{
		if (auth()->user()->region == 'in') {
			return env('CONNECT_RESELLER_INDIA');
		} else {
			return env('CONNECT_RESELLER');
		}
	}

	public function search(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'domain_name' => ['required', 'string'],
			'extension' => ['required', 'string'],
			'locale' =>  ['required', 'string', 'max:2']
		], [], [
			'domain_name' => 'Domain name',
			'extension' => 'Extension',
			'locale' => 'Locale'
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => false,
				'message' => 'Validation failed.',
				'data' => [
					'statusCode' => 412,
					'error' => $validator->errors()
				]
			]);
		}

		# check if we sell that domain extension by checking the database
		$product_detail = Product::where('product_name', $request->extension)->first();

		if (!$product_detail) { # product is not found. we can send the negative response
			return response()->json([
				'status' => false,
				'message' => 'This domain can not be registered with us.',
				'data' => [
					'statusCode' => 404,
					'error' => [
						'domain_name' => ['This domain can not be registered with us.']
					]
				]
			]);
		}

		# get the price id of the domain extension
		$price_info = Price::where('product_id', $product_detail->product_id)->where('region', $request->locale)->where('duration', '12')->first();

		if (!$price_info) {
			return response()->json([
				'status' => false,
				'message' => 'Can\'t register this domain. Please contact the support team.',
				'data' => [
					'statusCode' => 404,
					'error' => [
						'domain_name' => ['Can\'t register this domain. Please contact the support team.']
					]
				]
			]);
		}

		if ($request->locale == 'in') {
			$api_key = env('CONNECT_RESELLER_INDIA');
		} else {
			$api_key = env('CONNECT_RESELLER');
		}

		$curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/checkdomainavailable?APIKey='. $api_key .'&websiteName='. $request->domain_name);

		curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true]);

		$response = curl_exec($curl);

		curl_close($curl);

		$response = json_decode($response);

		if (property_exists($response, 'statusCode') && $response->statusCode == 401) {
			return response()->json([
				'status' => false,
				'message' => $response->responseText,
				'data' => ['error' => ['unauthenticated' => ['Error Code - 401! Please contact the support team']]]
			]);
		}

		$stripe = new \Stripe\StripeClient(env("STRIPE"));
		$domain_price_info = $stripe->prices->retrieve($price_info->price_id, []);

		if ($price_info->discount_id) {
			$discount_info = $stripe->coupons->retrieve($price_info->discount_id, []);
		} else {
			$discount_info = null;
		}

		if ($response->responseMsg->statusCode == 404) { // domain name not valid
			return response()->json([
				'status' => false,
				'message' => 'Domain name not valid.',
			]);
		}

		if ($response->responseMsg->statusCode != 200) { // the domain is not available for registration
			return response()->json([
				'status' => true,
				'message' => $response->responseMsg->message,
				'data' => [
					'status_code' => $response->responseMsg->statusCode,
					'name' => $request->domain_name,
					'currency' => $domain_price_info->currency,
					'message' => $response->responseMsg->message
				]
			]);
		}

		$ci_duration = match($price_info->duration) {
			12 => 1,
			24 => 2,
			36 => 3,
			60 => 5,
			'default' => 1
		};

		$domain_data = [
			'name' => $request->domain_name,
			'extension' => $request->extension,
			'product_id' => $price_info->product_id,
			'price_id' => $price_info->price_id,
			'currency' => $domain_price_info->currency,
			'unit_amount' => $domain_price_info->unit_amount,
			'discount_info' => $discount_info,
			'duration' => $price_info->duration,
			'duration_text' => $price_info->duration_text,
			'ci_duration' => $ci_duration,
			'renewal' => date('M j, Y', strtotime($price_info->duration_text)),
			'status_code' => 200
		];

		return response()->json([
			'status' => true,
			'message' => 'Domain is available for registration.',
			'data' => $domain_data
		]);
	}

	public function similar_domains(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'domain' => ['required', 'string'],
			'currency' => ['required', 'string'],
			'suggestions_count' => ['required', 'integer']
		], [], [
			'domain' => 'Domain name',
			'currency' => 'Currency'
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => false,
				'message' => 'Validation failed.',
				'data' => $validator->errors()
			]);
		}

		if ($request->currency == 'inr') {
			$api_key = env('CONNECT_RESELLER_INDIA');
		} else {
			$api_key = env('CONNECT_RESELLER');
		}

		$curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/domainSuggestion?APIKey='. $api_key .'&keyword='. $request->domain .'&maxResult='. $request->suggestions_count);

		curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true]);

		$suggestions = curl_exec($curl);

		curl_close($curl);

		return response()->json([
			'status' => true,
			'message' => 'Domain suggestions.',
			'data' => json_decode($suggestions)->registryDomainSuggestionList
		]);
	}

	private function activate_domain($website_id)
	{
		if ($website_id == null) {
			return false;
		}

		$curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/ManageDNSRecords?APIKey='. $this->connect_reseller() .'&WebsiteId='. $website_id);

		curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true]);

		$response = curl_exec($curl);

		curl_close($curl);

		$response = json_decode($response);

		if ($response->responseMsg->statusCode !== 200) {
			return false;
		}

		return true;
	}

	public function domain_status(Request $request)
	{
		$validation = Validator::make($request->all(), [
			'domain_name' => ['required']
		]);

		if ($validation->fails()) {
			return response()->json([
				'status' => false,
				'message' => 'Validation failed.',
				'data' => $validation->errors()
			]);
		}

		$authorization = Domain::where('user_id', auth()->user()->id)->where('domain_name', $request->domain_name)->first();

		if (!$authorization) {
			return response()->json([
				'status' => false,
				'message' => 'Authorization failed.',
				'data' => []
			]);
		}

		if ($authorization->status == 'Setup') {
			// 1. Get the website id for the domain
			$curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/ViewDomain?APIKey='. $this->connect_reseller() .'&websiteName='. $request->domain_name);

			curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true]);

			$domain_details = curl_exec($curl);

			curl_close($curl);

			$domain_details = json_decode($domain_details);

			if ($domain_details->responseMsg->statusCode !== 200) {
				return response()->json(false);
			}

			// 2. Activate the domain in connect reseller account
			$activate_cr = $this->activate_domain($domain_details->responseData->websiteId);

			if (!$activate_cr) {
				return response()->json(false);
			}

			// 3. Update the website id into the database
			$update_website_id = Domain::where('domain_name', $request->domain_name)->update([
				'website_id' => $domain_details->responseData->websiteId,
				'status' => 'Active'
			]);

			// 4. Send the website id of the domain
			return response()->json([
				'website_id' => $domain_details->responseData->websiteId
			]);
		}

		return response()->json([
			'website_id' => $authorization->website_id
		]);
	}

	public function details(Request $request)
	{
		$validation = Validator::make($request->all(), [
		    'domain_name' => ['required']
		]);

		if ($validation->fails()) {
			return response()->json([
				'status' => false,
				'messasge' => 'Query parameters missing.',
				'data' => $validation->errors()
			]);
		}

		$curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/ViewDomain?APIKey='. $this->connect_reseller() .'&websiteName='. $request->domain_name);

		curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true]);

		$domain_details = curl_exec($curl);

		curl_close($curl);

		$domain_details = json_decode($domain_details);

		return response()->json([
			'status' => true,
			'message' => 'Domain details',
			'data' => $domain_details
		]);
	}

	public function fetch_dns(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'website_id' => ['required', 'string']
		], [], [
			'website_id' => 'Website ID'
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => false,
				'message' => $validator->errors(),
				'data' => []
			]);
		}

		$curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/ViewDNSRecord?APIKey='. $this->connect_reseller() .'&WebsiteId=' . $request->website_id);

		curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true]);

		$response = curl_exec($curl);

		curl_close($curl);

		$response = json_decode($response);

		if (property_exists($response, 'statusCode')) {
			return response()->json([
				'status' => false,
				'message' => $response->responseText,
				'data' => $response
			]);
		}

		return response()->json([
			'status' => true,
			'message' => 'DNS Information for the domain',
			'data' => $response->responseData
		]);
	}

	public function add_dns(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'dns_zone_id' => ['required', 'string'],
			'record_type' => ['required', 'string'],
			'record_name' => ['required', 'string'],
			'record_value' => ['required', 'string'],
			'record_ttl' => ['required', 'integer'],
		], [], [
			'dns_zone_id' => 'DNS Zone ID',
			'record_type' => 'Record Type',
			'record_name' => 'Record Name',
			'record_value' => 'Record Value',
			'record_ttl' => 'Record TTL'
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => false,
				'message' => 'Query Parameters Missing.',
				'data' => [
					'statusCode' => 412,
					'errors' => $validator->errors()
				]
			]);
		}

		$curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/AddDNSRecord?APIKey='. $this->connect_reseller() .'&DNSZoneID='. $request->dns_zone_id .'&RecordName='. $request->record_name .'&RecordType='. $request->record_type .'&RecordValue='. $request->record_value .'&RecordPriority='. $request->record_priority .'&RecordTTL='. $request->record_ttl);

		curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true]);

		$response = curl_exec($curl);

		curl_close($curl);

		$response = json_decode($response);

		if (property_exists($response, 'statusCode')) {
			return response()->json([
				'status' => false,
				'message' => $response->responseText,
				'data' => $response
			]);
		}

		if ($response->responseData->statusCode !== 200) {
			return response()->json([
				'status' => false,
				'message' => $response->responseData->message,
				'data' => []
			]);
		}

		return response()->json([
			'status' => true,
			'message' => 'DNS Record Added.',
			'data' => $response
		]);
	}

	public function edit_dns(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'dns_zone_id' => ['required', 'string'],
			'dns_zone_record_id' => ['required', 'string'],
			'record_type' => ['required', 'string'],
			'record_name' => ['required', 'string'],
			'record_value' => ['required', 'string'],
			'record_ttl' => ['required', 'string']
		], [], [
			'dns_zone_id' => 'DNS Zone ID',
			'dns_zone_record_id' => 'DNS Zone Record ID',
			'record_type' => 'Record Type',
			'record_name' => 'Record Name',
			'record_value' => 'Record Value',
			'record_ttl' => 'Record TTL'
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => false,
				'message' => 'Validation failed.',
				'data' => $validator->errors()
			]);
		}

		$curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/ModifyDNSRecord?APIKey='. $this->connect_reseller() .'&DNSZoneID='. $request->dns_zone_id .'&DNSZoneRecordID='. $request->dns_zone_record_id .'&RecordName='. $request->record_name .'&RecordType='. $request->record_type .'&RecordValue='. $request->record_value .'&RecordTTL='. $request->record_ttl);

		curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true]);

		$response = curl_exec($curl);

		curl_close($curl);

		$response = json_decode($response);

		if (property_exists($response, 'statusCode')) {
			return response()->json([
				'status' => false,
				'message' => $response->responseText,
				'data' => $response
			]);
		}

		if ($response->responseData->statusCode !== 200) {
			return response()->json([
				'status' => false,
				'message' => $response->responseData->message,
				'data' => []
			]);
		}

		return response()->json([
			'status' => true,
			'message' => 'DNS Record Updated.',
			'data' => $response
		]);
	}

	public function delete_dns(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'dns_zone_id' => ['required', 'string'],
			'dns_zone_record_id' => ['required', 'string']
		], [], [
			'dns_zone_id' => 'DNS Zone ID',
			'dns_zone_record_id' => 'DNS Zone Record ID'
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => false,
				'message' => 'Validation failed.',
				'data' => $validator->errors()
			]);
		}

		$curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/DeleteDNSRecord?APIKey='. env('CONNECT_RESELLER') .'&DNSZoneID='. $request->dns_zone_id .'&DNSZoneRecordID='. $request->dns_zone_record_id);

		curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true]);

		$response = curl_exec($curl);

		curl_close($curl);

		$response = json_decode($response);

		if (property_exists($response, 'statusCode')) {
			return response()->json([
				'status' => false,
				'message' => $response->responseText,
				'data' => $response
			]);
		}

		return response()->json([
			'status' => true,
			'message' => 'DNS Record Deleted.',
			'data' => $response->responseData
		]);
	}

	public function modify_nameservers(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'domain_name_id' => ['required', 'integer'],
			'website_name' => ['required', 'string']
		], [], [
			'domain_name_id' => 'Domain ID',
			'website_name' => 'Domain name'
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => false,
				'message' => 'Validation failed.',
				'data' => $validator->errors()
			]);
		}

		$nameservers = json_decode($request->nameservers);

		while (count($nameservers) < 8) {
			array_push($nameservers, '');
		}

		$curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/UpdateNameServer?APIKey='. $this->connect_reseller() .'&domainNameId='. $request->domain_name_id .'&websiteName='. $request->website_name .'&nameServer1='. $nameservers[0] .'&nameServer2='. $nameservers[1] .'&nameServer3='. $nameservers[2] .'&nameServer4='. $nameservers[3] .'&nameServer5='. $nameservers[4] .'&nameServer6='. $nameservers[5] .'&nameServer7='. $nameservers[6] .'&nameServer8='. $nameservers[7]);

		curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true]);

		$response = curl_exec($curl);

		curl_close($curl);

		$response = json_decode($response);

		if ($response->responseMsg->statusCode !== 200) {
			return response()->json([
				'status' => false,
				'message' => $response->responseMsg->message
			]);
		}

		return response()->json([
			'status' => true,
			'message' => 'Nameservers updated.',
			'data' => $response->responseData
			// 'data' => $nameservers
		]);
	}

	public function change_privacy(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'domain_name_id' => ['required', 'integer'],
			'value' => ['required', 'string']
		], [], [
			'domain_name_id' => 'Domain ID',
			'value' => 'Privacy Protection Value'
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => false,
				'message' =>  'Validation failed.',
				'data' => $validator->errors()
			]);
		}

		if ($request->value == 'true') {
			$curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/ManageDomainPrivacyProtection?APIKey='. $this->connect_reseller() .'&domainNameId='. $request->domain_name_id .'&iswhoisprotected=true');
			
			$status = 'Enabled';
		} else {
			$curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/ManageDomainPrivacyProtection?APIKey='. $this->connect_reseller() .'&domainNameId='. $request->domain_name_id .'&iswhoisprotected=false');

			$status = 'Disabled';
		}

		curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true]);

		$response = curl_exec($curl);

		curl_close($curl);

		$response = json_decode($response);

		if ($response->responseMsg->statusCode == 200) {
			$curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/ViewDomainById?APIKey='. $this->connect_reseller() .'&id='. $request->domain_name_id);
			
			curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true]);

			$domain_details = curl_exec($curl);

			curl_close($curl);

			$domain_details = json_decode($domain_details);

			if ($domain_details->responseMsg->statusCode == 200) {
				Domain::where('domain_name', $domain_details->responseData->websiteName)->update(['privacy_protection' => filter_var($request->value, FILTER_VALIDATE_BOOLEAN)]);
			}
		}
		
		return response()->json([
			'status' => true,
			'message' => $response->responseMsg->message,
			'data' => [
				'privacy_status' => $status
			]
		]);
	}

	public function domain_lock(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'domain_name_id' => ['required', 'integer'],
			'website_name' => ['required', 'string'],
			'value' => ['required', 'string']
		], [], [
			'domain_name_id' => 'Domain Name ID',
			'website_name' => 'Website Name',
			'value' => 'Value'
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => false,
				'message' => 'Validation failed.',
				'data' => $validator->errors()
			]);
		}

		if ($request->value == 'true') {
			$curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/ManageDomainLock?APIKey='. $this->connect_reseller() .'&domainNameId='. $request->domain_name_id .'&websiteName='. $request->website_name .'&isDomainLocked=true');
		} else {
			$curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/ManageDomainLock?APIKey='. $this->connect_reseller() .'&domainNameId='. $request->domain_name_id .'&websiteName='. $request->website_name .'&isDomainLocked=false');
		}

		curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true]);

		$response = curl_exec($curl);

		curl_close($curl);

		$response = json_decode($response);

		if ($response->responseMsg->statusCode != 200) {
			return response()->json([
				'status' => false,
				'message' => 'Can\'t update the domain lock. Talk to support team.',
				'data' => []
			]);
		}

		return response()->json([
			'status' => true,
			'message' => 'Domain lock updated.',
			'data' => []
		]);
	}

	public function theft_protection(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'domain_name_id' => ['required', 'integer'],
			'website_name' => ['required', 'string'],
			'value' => ['required', 'string']
		], [], [
			'domain_name_id' => 'Domain Name ID',
			'website_name' => 'Website Name',
			'value' => 'Value'
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => false,
				'message' => 'Validation failed.',
				'data' => $validator->errors()
			]);
		}

		if ($request->value == 'true') {
			$curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/ManageTheftProtection?APIKey='. $this->connect_reseller() .'&domainNameId='. $request->domain_name_id .'&websiteName='. $request->website_name .'&isTheftProtection=true');
		} else {
			$curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/ManageTheftProtection?APIKey='. $this->connect_reseller() .'&domainNameId='. $request->domain_name_id .'&websiteName='. $request->website_name .'&isTheftProtection=false');
		}

		curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true]);

		$response = curl_exec($curl);

		curl_close($curl);

		$response = json_decode($response);

		if ($response->responseMsg->statusCode != 200) {
			return response()->json([
				'status' => false,
				'message' => 'Can\'t update theft protection on this domain. Talk to support team.',
				'data' => []
			]);
		}

		return response()->json([
			'status' => true,
			'message' => 'Theft protection for the domain is updated.',
			'data' => []
		]);
	}

	// public function multi_year_price(Request $request)
	// {
	// 	$validator = Validator::make($request->all(), [
	// 		'website_name' => ['required', 'string'],
	// 		'region' => ['required', 'string']
	// 	], [], [
	// 		'website_name' => 'Website name',
	// 		'region' => 'Region'
	// 	]);

	// 	if ($validator->fails()) {
	// 		return response()->json([
	// 			'status' => false,
	// 			'message' => 'Validation failed',
	// 			'data' => $validator->errors()
	// 		]);
	// 	}

	// 	if ($request->region == 'in') {
	// 		$api_key = env('CONNECT_RESELLER_INDIA');
	// 		$currency = '₹';
	// 	} else {
	// 		$api_key = env('CONNECT_RESELLER');
	// 		$currency = '$';
	// 	}

	// 	$curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/checkDomainPrice?APIKey='. $api_key .'&websiteName='. $request->website_name);

	// 	curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true]);

	// 	$response = curl_exec($curl);

	// 	curl_close($curl);

	// 	$response = json_decode($response);

	// 	if ($response->responseMsg->statusCode !== 200) {
	// 		return response()->json([
	// 			'status' => false,
	// 			'message' => 'Can\'t get the multi year pricing for this domain.',
	// 			'data' => []
	// 		]);
	// 	}

	// 	$prices = json_decode(json_encode($response), true);

	// 	# break the domain into different parts
	// 	$domainParts = explode('.', $request->website_name);

	// 	# remove the domain name and keep the extension from the array
	// 	$extension_array = array_slice($domainParts, 1);

	// 	# join the extensions to form the tld
	// 	$extension = '.' . implode('.', $extension_array);

	// 	$product_id = Product::where('product_name', $extension)->first()->product_id;

	// 	$price_array = [];

	// 	for ($i = 0; $i < 3; $i++) {
	// 		if ($i == '0') {
	// 			$unit_amount = str_replace('Registration Price for 1 year is ', '', $prices['responseData'][0][$i]['description']);
	// 			$duration = '1 Year';
	// 			$price_id = Price::where('product_id', $product_id)->where('duration', 12)->first()->price_id;
	// 			$renewal_date = date('M j, Y', strtotime('1 year'));
	// 		}

	// 		if ($i == '1') {
	// 			$unit_amount = str_replace('Registration Price for 2 year is ', '', $prices['responseData'][0][$i]['description']);
	// 			$duration = '2 Years';
	// 			$price_id = null;
	// 			$renewal_date = date('M j, Y', strtotime('2 years'));
	// 		}

	// 		if ($i == '2') {
	// 			$unit_amount = str_replace('Registration Price for 3 year is ', '', $prices['responseData'][0][$i]['description']);
	// 			$duration = '3 Years';
	// 			$price_id = null;
	// 			$renewal_date = date('M j, Y', strtotime('3 years'));
	// 		}
	// 		array_push($price_array, [
	// 			'description' => $prices['responseData'][0][$i]['description'],
	// 			'unit_amount' => $unit_amount,
	// 			'duration' => $duration,
	// 			'renewal_date' => $renewal_date,
	// 			'price_id' => $price_id
	// 		]);
	// 	}

	// 	return response()->json([
	// 		'status' => true,
	// 		'message' => 'Multi year pricing for '. $request->website_name,
	// 		'data' => [
	// 			'domain_name' => $request->website_name,
	// 			'currency' => $currency,
	// 			'prices' => $price_array,
	// 		]
	// 	]);
	// }

	public function multi_year_price(Request $request)
	{
		$duration = [12, 24, 36, 60];

		foreach ($duration as $value) {

			$duration_text = match($value) {
				12 => '1 Year',
				24 => '2 Years',
				36 => '3 Years',
				60 => '5 Years'
			};

			$price_info = Price::where('product_id', $request->product_id)->where('region', $request->region)->where('duration', $value)->first();

			if (empty($price_info)) {
				$pricing[$duration_text] = null;
			}

			$pricing[$duration_text] = $price_info;
		}

		return response()->json($pricing);
	}

	public function create_multi_year_price(Request $request)
	{
		$price_info = $this->fetch_multi_year_pricing($request->all());

		if (!$price_info) {
			return response()->json([
				'status' => false,
				'message' => 'Can\'t get the price for '. $request->duration_text .'. Try again later.'
			]);
		}

		$duration_text = match($request->duration_text) {
			'1 Year' => '1 year',
			'2 Years' => '2 year',
			'3 Years' => '3 year',
			'5 Years' => '5 year'
		};

		$duration = match($request->duration_text) {
			'1 Year' => 1,
			'2 Years' => 2,
			'3 Years' => 3,
			'5 Years' => 5
		};

		$db_duration = match($request->duration_text) {
			'1 Year' => 12,
			'2 Years' => 24,
			'3 Years' => 36,
			'5 Years' => 60
		};

		# get the unit amount for 1 year price
		$stripe = new \Stripe\StripeClient(env("STRIPE"));
		$one_year_price_info = $stripe->prices->retrieve(Price::where('product_id', $request->product_id)->where('region', $request->region)->where('duration_text', '1 Year')->first()->price_id, []);

		foreach ($price_info as $value) {				
			if (strpos($value['description'], $duration_text) > 0) {
				$stripe_data = [
					'product_id' => $request->product_id,
					'currency' => $this->currency_from_region($request->region),
					'nickname' => $duration_text .' plan - '. $this->currency_from_region($request->region),
					'unit_amount' => $one_year_price_info->unit_amount * $duration,
					'region' => $request->region,
					'selling_price' => str_replace('Registration Price for '. $duration_text .' is ', '', $value['description']) * 100,
					'duration_text' => $request->duration_text,
					'duration' => $db_duration
				];
			}
		}

		if ($stripe_data['unit_amount'] != $stripe_data['selling_price']) {
			$stripe_data['coupon_name'] = Product::where('product_id', $request->product_id)->first()->product_name . ' - ' . $duration_text;
		}

		# Create new domain price in Stripe & Database
		$price_info = $this->create_domain_price($stripe_data);
		
		if ($price_info) {
			return response()->json([
				'status' => true,
				'message' => 'New price created',
				'data' => ['price_id' => $price_info]
			]);
		}
	}

	protected function fetch_multi_year_pricing($input)
	{
		/**
		 * 1. Get the pricing for the mentioned year from connect reseller
		 * 2. Edit the pricing
		 * 3. Create a new price on Stripe
		 * 4. Update the data into the database
		 * 5. Return the price
		*/

		if ($input['region'] == 'in') {
			$api_key = env('CONNECT_RESELLER_INDIA');
		} else {
			$api_key = env('CONNECT_RESELLER');
		}

		$curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/checkDomainPrice?APIKey='. $api_key .'&websiteName='. $input['domain']);

		curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true]);

		$response = curl_exec($curl);

		curl_close($curl);

		$response = json_decode($response, true);

		if ($response['responseMsg']['statusCode'] !== 200) {
			return false;
		}

		return $response['responseData']['0'];
	}

	protected function create_domain_price($data)
	{
		$stripe = new \Stripe\StripeClient(env("STRIPE"));

		$stripe_price = $stripe->prices->create([
			'currency' => $data['currency'],
			'unit_amount' => $data['unit_amount'],
			'product' => $data['product_id'],
			'nickname' => $data['nickname']
		]);

		$price_id = $stripe_price->id;

		if ($data['unit_amount'] != $data['selling_price']) {
			# code for creating a new coupon
			$coupon = $stripe->coupons->create([
				'amount_off' => $data['unit_amount'] - $data['selling_price'],
				'currency' => $data['currency'],
				'duration' => 'once',
				'name' => $data['coupon_name']
			]);

			$discount_type = 'amount';
			$discount_id = $coupon->id;
		} else {
			$discount_type = null;
			$discount_id = null;
		}

		$add_price_db = Price::insert([
			'product_id' => $data['product_id'],
			'price_id' => $price_id,
			'region' => $data['region'],
			'duration_text' => $data['duration_text'],
			'duration' => $data['duration'],
			'discount_type' => $discount_type,
			'discount_id' => $discount_id
		]);
		
		return $price_id;
	}

	public function mutli_year_price_info(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'price_id' => ['required', 'string']
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => false,
				'message' => 'Price ID missing.'
			]);
		}

		$price_info = Price::where('price_id', $request->price_id)->first();

		if (empty($price_info)) {
			return response()->json([
				'status' => false,
				'message' => 'Cannot find the pricing of this domain.'
			]);
		}

		$stripe = new \Stripe\StripeClient(env("STRIPE"));
		$stripe_price_info = $stripe->prices->retrieve($price_info->price_id, []);

		if ($price_info->discount_id) {
			$discount_info = $stripe->coupons->retrieve($price_info->discount_id, []);
		} else {
			$discount_info = null;
		}

		$ci_duration = match($price_info->duration) {
			12 => 1,
			24 => 2,
			36 => 3,
			60 => 5,
			'default' => 1
		};

		$domain_data = [
			'product_id' => $price_info->product_id,
			'price_id' => $price_info->price_id,
			'currency' => $stripe_price_info->currency,
			'unit_amount' => $stripe_price_info->unit_amount,
			'discount_info' => $discount_info,
			'duration' => $price_info->duration,
			'duration_text' => $price_info->duration_text,
			'ci_duration' => $ci_duration,
			'renewal' => date('M j, Y', strtotime($price_info->duration_text))
		];

		return response()->json([
			'status' => true,
			'data' => $domain_data
		]);
	}

	public function popular_domain_prices(Request $request)
	{
		$popular_extensions = [
			[
				'tld' => '.info',
				'product_id' => 'prod_QnOkVIBPsoreSM'
			],
			[
				'tld' => '.agency',
				'product_id' => 'prod_QnOY2Q4YXUwpSf'
			],
			[
				'tld' => '.online',
				'product_id' => 'prod_QnOoEiHAJSpf6X'
			],
			[
				'tld' => '.cloud',
				'product_id' => 'prod_QnOcEVO2Njj8pw'
			],
			[
				'tld' => '.shop',
				'product_id' => 'prod_QnOXUFk6TUaNfL'
			],
			[
				'tld' => '.io',
				'product_id' => 'prod_QnOlJ7TdRqGeP0'
			],
			[
				'tld' => '.icu',
				'product_id' => 'prod_QnOkKrI8A2R7Dp'
			],
			[
				'tld' => '.ai',
				'product_id' => 'prod_QnOYYPw4MTLwjj'
			],
		];

		$prices = [];
		$stripe = new \Stripe\StripeClient(env("STRIPE"));

		foreach ($popular_extensions as $extension) {
			$domain_info = Price::where('product_id', $extension['product_id'])
			->where('region', $request->region)
			->where('duration', 12)
			->first();

			$price_info = $stripe->prices->retrieve($domain_info->price_id, []);

			if ($domain_info->discount_id == null) { // this tld doesn't have any discount
				array_push($prices, [
					'tld' => $extension['tld'],
					'registration_fee' => ($price_info->unit_amount) / 100,
					'renewal_fee' => ($price_info->unit_amount) / 100,
					'currency' => $this->currency_symbol($request->region)
				]);
			} else { // this tld has a discount
				$discount_info = $stripe->coupons->retrieve($domain_info->discount_id, []);

				if ($discount_info->percent_off == null) { // this discount is in amount
					array_push($prices, [
						'tld' => $extension['tld'],
						'registration_fee' => ($price_info->unit_amount - $discount_info->amount_off) / 100,
						'renewal_fee' => ($price_info->unit_amount) / 100,
						'currency' => $this->currency_symbol($request->region)
					]);
				} else { // this discount is in percent
					// will do the calculations later
				}
			}
		}

		return response()->json($prices);
	}
}
