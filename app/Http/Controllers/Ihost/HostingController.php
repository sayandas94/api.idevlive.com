<?php

namespace App\Http\Controllers\Ihost;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use App\Models\Ihost\Product;
use App\Models\Price;
use App\Models\Ihost\Hosting;
use App\Models\Ihost\Features;

class HostingController extends Controller
{
	public function choose_plan(Request $request)
	{
		// if ($request->price_id) {
		// 	$inputData = [
		// 		['product_id', $request->product_id],
		// 		['price_id', $request->price_id],
		// 		['region', $request->region]
		// 	];
		// } else {
		// 	$plans = Price::where([
		// 		['product_id', $request->product_id],
		// 		['region', $request->region]
		// 	])->get();

		// 	foreach ($plans as &$plan) {
		// 		$stripe = new \Stripe\StripeClient(env("STRIPE"));
				
		// 		$price = $stripe->prices->retrieve($plan->price_id, []);
		// 		$plan->unit_amount = $price->unit_amount;
		// 		$plan->renewal_date = date('M j, Y', strtotime($plan->duration_text));
		// 		$plan->currency = $price->currency;
	
		// 		if ($plan->discount_id != null) {
		// 			$plan->discount_info = $stripe->coupons->retrieve($plan->discount_id, []);
		// 		}
		// 	}
	
		// 	return response()->json([
		// 		'status' => true,
		// 		'data' => $plans
		// 	]);
		// }

		$product_prices = Price::where([
			['product_id', $request->product_id],
			['region', $request->region]
		])->get();

		foreach ($product_prices as &$price_info) {
			$stripe = new \Stripe\StripeClient(env("STRIPE"));
			
			// $price = $stripe->prices->retrieve($plan->price_id, []);
			// $plan->unit_amount = $price->unit_amount;
			// $plan->renewal_date = date('M j, Y', strtotime($plan->duration_text));
			// $plan->currency = $price->currency;

			// if ($plan->discount_id != null) {
			// 	$plan->discount_info = $stripe->coupons->retrieve($plan->discount_id, []);
			// }
			$stripe_price = $stripe->prices->retrieve($price_info->price_id, []);
			$price_info->unit_amount = $stripe_price->unit_amount;
			$price_info->renewal_date = date('M j, Y', strtotime($price_info->duration_text));
			$price_info->currency = $stripe_price->currency;

			if ($price_info->discount_id != null) {
				$price_info->discount_info = $stripe->coupons->retrieve($price_info->discount_id, []);
			}
		}

		return response()->json([
			'status' => true,
			'data' => [
				'product_name' => Product::where('product_id', $request->product_id)->first()->product_name,
				'features' => Features::where('product_id', $request->product_id)->first()->features,
				'price_info' => $product_prices
			]
		]);
	}

	public function get_price_info(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'price_id' => ['required', 'string']
		], [], [
			'price_id' => 'Price ID'
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => false,
				'message' => 'Validation failed.',
				'data' => $validator->errors()
			]);
		}

		$price_info = Price::where('price_id', $request->price_id)->first();

		if (!$price_info) {
			return response()->json([
				'status' => false,
				'message' => 'Selected pricing doesn\'t exists.',
				'data' => []
			]);
		}

		$stripe = new \Stripe\StripeClient(env("STRIPE"));
			
		$stripe_price = $stripe->prices->retrieve($price_info->price_id, []);
		$price_info->unit_amount = $stripe_price->unit_amount;
		$price_info->renewal_date = date('M j, Y', strtotime($price_info->duration_text));
		$price_info->currency = $stripe_price->currency;

		if ($price_info->discount_id) {
			$price_info->discount_info = $stripe->coupons->retrieve($price_info->discount_id, []);
		}

		return response()->json([
			'status' => true,
			'data' => $price_info
		]);
	}

	public function show(Request $request)
	{
		if ($request->id AND $request->locale) {
			$price = Product::where('stripe', $request->id)->where('locale', $request->locale)->first();
		}

		if ($request->name AND $request->locale) {
			$price = Product::where('name', $request->name)->where('locale', $request->locale)->get();
		}
		return response()->json($price);
	}

	public function multi_year_pricing(Request $request)
	{
		$validation = Validator::make($request->all(), [
			'product_id' => ['required', 'string'],
			'region' => ['required', 'string']
		], [], [
			'product_id' => 'Product ID',
			'region' => 'Region'
		]);

		if ($validation->fails()) {
			return response()->json([
				'status' => false,
				'message' => 'Validation failed.',
				'data' => $validation->errors()
			], 422);
		}

		$prices = Price::where('product_id', $request->product_id)-> where('region', $request->region)->get();

		return response()->json([
			'status' => true,
			'message' => 'Multi year pricing.',
			'data' => $prices
		]);
	}

	public function setup(Request $request)
	{
		$validator = Validator::make($request->all(), [
			// 'domain_name' => ['required', 'string'],
			'domain_select' => ['required', 'string'],
			'datacenter' => ['required', 'string'],
			'wordpress' => ['required', 'string'],
			'hosting_id' => ['required', 'integer']
		], [], [
			'domain_select' => 'Domain select',
			'datacenter' => 'Data center',
			'wordpress' => 'Wordpress',
			'hosting_id' => 'Hosting ID'
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => false,
				'message' => 'Validation failed.',
				'data' => $validator->errors()
			]);
		}

		if ($request->domain_select == 'ihost' && $request->ihost_domain_name == null) {
			return response()->json([
				'status' => false,
				'message' => 'Validation failed.',
				'data' => [
					'domain_name' => ['Domain name can not be empty']
				]
			]);
		}

		if ($request->domain_select == 'third-party' && $request->outside_domain_name == null) {
			return response()->json([
				'status' => false,
				'message' => 'Validation failed.',
				'data' => [
					'domain_name' => ['Domain name can not be empty']
				]
			]);
		}

		// switch ($request->datacenter) {
		//     case 'us':
		//         $datacenter = 'USA';
		//         break;

		//     case 'ca':
		//         $datacenter = 'CA';
		//         break;

		//     case 'in':
		//         $datacenter = 'IN';
		//         break;
			
		//     default:
		//         $datacenter = null;
		//         break;
		// }

		$hosting_info = Hosting::where('id', $request->hosting_id)->first();

		/**
		 * Perform some checks about the hosting
		 * 
		 * 1. Check if the hosting belongs to the logged in user
		 * 2. Check if the invoice has been paid or not (Need to write code for it)
		 * 3. Check if the hosting is not already setup
		 */

		//  1. Check if the hosting belongs to the logged in user
		if ($hosting_info->user_id != auth()->user()->id) {
			return response()->json([
				'status' => false,
				'message' => 'Hosting doesn\'t belong to the logged in user.',
				'data' => []
			]);
		}

		// 2. Check if the invoice has been paid or not (Need to write code for it)

		// 3. Check if the hosting is not already setup
		if ($hosting_info->status != 'Setup') {
			return response()->json([
				'status' => false,
				'message' => 'This hosting has already been setup once.',
				'data' => []
			]);
		}

		/**
		 * I DON'T KNOW WHY I'VE ADDED THIS CODE. I HAVE NO IDEA WHAT DATACENTER VARIABLE IS
		 */
		// if (!$datacenter) {
		// 	return response()->json([
		// 		'status' => false,
		// 		'message' => 'Datacenter not valid.',
		// 		'data' => [
		// 			'datacenter' => 'Please select a valid datacenter.'
		// 		]
		// 	]);
		// }

		$postData = [
			'email' => auth()->user()->email,
			'hosting-password' => Str::random(8),
			'product-name' => $hosting_info->product_name,
			'subscription-id' => $hosting_info->invoice_id,
			'hosting-id' => $hosting_info->id,
			'domain-name' => $request->domain_select == 'ihost' ? $request->ihost_domain_name : $request->outside_domain_name,
			'ip-address' => $this->select_ip($request->datacenter)
		];

		if (!$this->server_name($this->select_ip($request->datacenter))) {
			return response()->json([
				'status' => false,
				'message' => 'Invalid datacenter.',
				'data' => []
			]);
		}

		$curl = curl_init('https://' . $this->server_name($this->select_ip($request->datacenter)) . '/ipanel/users/setupUserAccount');

		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $postData,
			CURLOPT_HTTPHEADER => ['HTTP_X_API_KEY: ipanel_9958328450@Sayan']
		]);

		$response = curl_exec($curl);

		curl_close($curl);

		$response = json_decode($response);

		if (!$response->status) {
			return reponse()->json([
				'status' => false,
				'message' => $response->responseMsg,
				'data' => []
			]);
		}

		$this->update_hosting($postData);

		return response()->json([
			'status' => true,
			'message' => 'Hosting setup done',
			'data' => [
				'hostingPassword' => $postData['hosting-password']
			]
		]);
	}

	/** Function for getting the ip address on the basis of datacenter */
	private function select_ip($datacenter)
	{
		switch ($datacenter) {
			case 'in':
				$ip = '139.59.88.31';
				break;
			
			default:
				$ip = '139.59.88.31';
				break;
		}

		return $ip;
	}

	/** Function for getting the server name on the basis of ip address */
	private function server_name($ip_address)
	{
		switch ($ip_address) {
			case '139.59.88.31':
				$server_name = 'server002-blr001.idevlive.com';
				break;
			
			default:
				$server_name = false;
				break;
		}

		return $server_name;
	}

	/** Update the hosting entry with the details */
	private function update_hosting($postData)
	{
		$update = Hosting::where('id', $postData['hosting-id'])->update([
			'status' => 'Active',
			'primary_domain' => $postData['domain-name'],
			'server_ip' => $postData['ip-address']
		]);
	}
}
