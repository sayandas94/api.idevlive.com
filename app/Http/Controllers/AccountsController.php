<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Http\Controllers\Controller;

use App\Models\User;
use App\Models\Account;
use App\Models\Ihost\Domain;
use App\Models\Ihost\Hosting;
// use App\Models\Ihost\Email;

class AccountsController extends Controller
{
	public function register(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'email' => ['required', 'email', 'unique:users,email'],
			'password' => ['required', 'string', 'min:8'],
			'customer_name' => ['required', 'string'],
			'region' => ['required', 'string']
		], [], [
			'email' => 'Email',
			'password' => 'Password'
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => false,
				'message' => 'Validation failed.',
				'data' => $validator->errors()
			]);
		}

		$connect_reseller = $this->create_connect_reseller_account($request->all());
		$stripe = $this->create_stripe_account($request->all());
		

		$inputData = [
			'email' => $request->email,
			'password' => password_hash($request->password, PASSWORD_DEFAULT),
			'name' => $request->customer_name,
			'stripe' => $stripe,
			'connect_reseller' => $connect_reseller->responseData->clientId,
			'region' => $request->region
		];

		$user = User::create($inputData);

		$token = $user->createToken('login')->plainTextToken;

		return response()->json([
			'status' => true,
			'message' => 'Registration successfull.',
			'data' => [
				'url' => 'sign-in',
				'token' => $token
			]
		]);
	}

	protected function create_stripe_account($userData)
	{
		$stripe = new \Stripe\StripeClient(env('STRIPE'));

		$customer = $stripe->customers->create([
			'name' => $userData['customer_name'],
			'email' => $userData['email']
		]);

		return $customer->id;
	}

	protected function create_connect_reseller_account($userData)
	{

		if ($userData['region'] == 'in') {
			$api_key = env('CONNECT_RESELLER_INDIA');
		} else {
			$api_key = env('CONNECT_RESELLER');
		}

		$curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/AddClient?APIKey='. $api_key .'&FirstName='. urlencode($userData['customer_name']) .'&UserName='. urlencode($userData['email']) .'&Password='. urlencode($userData['password']) .'&CompanyName=&Address1=&City=&StateName=&CountryName='. urlencode($this->get_cr_country_name($userData['region'])) .'&Zip=&PhoneNo_cc=&PhoneNo=');

		// $curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/DeleteCustomer?APIKey='. $api_key .'&customerId=209927');


		curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true]);

		$response = curl_exec($curl);

		curl_close($curl);

		$response = json_decode($response);

		return $response;
	}

	public function login(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'email' => ['required', 'email'],
			'password' => ['required', 'string', 'min:8']
		], [], [
			'email' => 'Email address',
			'password' => 'Password'
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => false,
				'message' => 'Validation failed.',
				'data' => $validator->errors()
			]);
		}
		
		$user = User::where('email', $request->email)->first();

		if (!$user) {
			return response()->json([
				'status' => false,
				'message' => 'Invalid email address.',
				'data' => [
					'email' => ['There is no account with this email address.']
				]
			]);
		}

		if (!password_verify($request->password, $user->password)) {
			return response()->json([
				'status' => false,
				'message' => 'Invalid password.',
				'data' => [
					'password' => ['Incorrect password. Please try again or try <a href="reset-password" class="red-text medium" style="text-decoration: underline">resetting your password.</a>']
				]
			]);
		}

		$token = $user->createToken('login')->plainTextToken;
		
		if ($request->previous_url) {
			$redirect_url = $request->previous_url;
		} else {
			$redirect_url = 'user/dashboard';
		}

		return response()->json([
			'status' => true,
			'message' => 'Login successfull.',
			'data' => [
				'token' => $token,
				'url' => $redirect_url
			]
		]);
	}

	public function logout()
	{
		auth()->user()->tokens()->delete();

		return response()->json([
			'status' => true,
			'message' => 'Logged out.',
			'data' => []
		]);
	}

	public function profile()
	{
		$userData = auth()->user();

		if (!$userData) {
			return response()->json([
				'status' => false,
				'message' => 'Unauthenticated.',
				'data' => [],
			], 401);
		}

		return response()->json([
			'status' => true,
			'message' => 'Profile information.',
			'data' => $userData,
		]);
	}

	public function active_subscriptions(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'id' => ['required', 'integer'],
			// 'product' => ['required', 'string']
		], [], [
			'id' => 'User ID',
			// 'product' => 'Product type'
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => false,
				'message' => 'Validation failed.',
				'data' => $validator->errors()
			]);
		}

		switch ($request->product) {
			case 'hosting':
				$products = Hosting::where('user_id', $request->id)->get();
				break;

			case 'domain':
				$products = Domain::where('user_id', $request->id)->get();
				break;

			case 'email':
				$products = [];
				break;
			
			default:
				$domains = Domain::where('user_id', $request->id)->get()->toArray();
				foreach ($domains as &$domain) {
					$domain['category'] = 'domain';
				}

				$hostings = Hosting::where('user_id', $request->id)->get()->toArray();
				foreach ($hostings as &$hosting) {
					$hosting['category'] = 'hosting';
				}

				$products = array_merge($domains, $hostings);
		}

		foreach ($products as &$value) {
			$value['created_at'] = date('M d, Y', strtotime($value['created_at']));
			$value['expiring_at'] = date('M d, Y', strtotime($value['expiring_at']));
			$value['str_created'] = strtotime($value['created_at']);
			$value['str_expiring'] = strtotime($value['expiring_at']);
			$value['str_today'] = strtotime('today');
		}

		return response()->json([
			'status' => true,
			'message' => '',
			'data' => $products
		]);
	}

	public function fetch_address(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'user_id' => ['required', 'integer']
		], [], [
			'user_id' => 'User ID'
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => false,
				'message' => 'Validation failed.',
				'data' => $validator->errors()
			]);
		}

		$address = Account::where(['user_id' => $request->user_id])->get();

		if (count($address) == 0) {
			return response()->json([
				'status' => true,
				'message' => 'No address found.',
				'data' => $address->first()
			]);
		}

		return response()->json([
			'status' => true,
			'message' => 'Address fetched.',
			'data' => $address->first()
		]);
	}

	public function update_address(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'customer_name' => ['required', 'string'],
			'email' => ['required', 'email'],
			'phone_number' => ['required', 'string', 'min:10'],
			'street' => ['required', 'string'],
			'city' => ['required', 'string'],
			'state' => ['required', 'string'],
			'postal_code' => ['required', 'string'],
			'country' => ['required', 'string']
		], [], [
			'customer_name' => 'Customer name',
			'email' => 'Email address',
			'phone_number' => 'Phone number',
			'street' => 'Street address',
			'city' => 'City',
			'state' => 'State',
			'postal_code' => 'Postal code',
			'country' => 'Country'
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => false,
				'message' => 'Validation failed.',
				'data' => []
			]);
		}

		$inputData = [
			'customer_name' => $request->customer_name,
			'email' => $request->email,
			'phone_number' => $request->phone_number,
			'street' => $request->street,
			'city' => $request->city,
			'state' => $request->state,
			'postal_code' => $request->postal_code,
			'country' => $request->country
		];

		# update customer address in stripe
		$stripe = new \Stripe\StripeClient(env('STRIPE'));

		$stripe->customers->update(auth()->user()->stripe, [
			'phone' => $inputData['phone_number'],
			'address' => [
				'line1' => $inputData['street'],
				'city' => $inputData['city'],
				'state' => $inputData['state'],
				'postal_code' => $inputData['postal_code'],
				'country' => $this->two_letter_country_code_from_full_name($inputData['country'])
			]
		]);

		# update customer address in connect reseller
		if (auth()->user()->region == 'in') {
			$api_key = env('CONNECT_RESELLER_INDIA');
		} else {
			$api_key = env('CONNECT_RESELLER');
		}

		$curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/ModifyClient?APIKey='. $api_key .'&FirstName='. urlencode($inputData['customer_name']) .'&UserName=&Password=&CompanyName=&Address='. urlencode($inputData['street']) .'&City='. urlencode($inputData['city']) .'&StateName='. urlencode($inputData['state']) .'&CountryName='. urlencode($inputData['country']) .'&Zip='. urlencode($inputData['postal_code']) .'&PhoneNo_cc='. $this->phone_number_cc($inputData['country']) .'&PhoneNo='. $inputData['phone_number'] .'&Id='. auth()->user()->connect_reseller);

		curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true]);

		$response = curl_exec($curl);

		curl_close($curl);

		$address = Account::updateOrInsert([
			'user_id' => auth()->user()->id
		], $inputData);

		return response()->json([
			'status' => true,
			'data' => $address
		]);
	}

	public function update_password(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'password' => ['required', 'confirmed', 'min:8'],
			'password_confirmation' => ['required', 'string']
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => true,
				'message' => 'Validation failed.',
				'data' => $validator->errors()
			]);
		}

		$change_password = User::where([
			'id' => auth()->user()->id
		])->update([
			'password' => password_hash($request->password, PASSWORD_DEFAULT)
		]);

		if (!$change_password) {
			return response()->json([
				'status' => false,
				'message' => 'Can\'t update the password. Contact support team.',
				'data' => []
			]);
		}

		return response()->json([
			'status' => true,
			'message' => 'Password updated.',
			'data' => []
		]);
	}

	public function update_pin(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'support_pin' => ['required', 'integer', 'between:0000,9999']
		], [], [
			'support_pin' => 'Support Pin'
		]);

		if ($validator->fails())
		{
			return response()->json([
				'status' => false,
				'message' => 'Validation failed.',
				'data' => $validator->errors()
			]);
		}

		return response()->json([
			'status' => true,
			'message' => 'Support pin updated.',
			'data' => []
		]);
	}

	public function list_cards(Request $request)
	{
		$userData = auth()->user();
		$stripe = auth()->user()->stripe;

		return response()->json($stripe);
	}

	public function invoices()
	{
		$customer_id = auth()->user()->stripe;

		$stripe = new \Stripe\StripeClient(env('STRIPE'));

		$invoices = $stripe->invoices->all(['customer' => $customer_id]);

		foreach ($invoices->data as &$value) {
			$value['created'] = date('M d, Y', $value['created']);
			$value['total'] = number_format($value['total'], 2);
		}

		return response()->json($invoices);
	}

	public function update_autorenew(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'category' => ['required', 'string'],
			'id' => ['required', 'integer'],
			'value' => ['required', 'string']
		], [], [
			'category' => 'Product Category',
			'id' => 'Product ID',
			'value' => 'Value'
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => false,
				'message' => 'Validation failed.',
				'data' => $validator->errors()
			]);
		}

		($request->value == 'true') ? $value = true : $value = false;

		if ($request->category == 'domain') {
			$update_autorenew = Domain::where('id', $request->id)->update(['auto_renew' => $value]);
		}

		if ($request->category == 'hosting') {
			$update_autorenew = Hosting::where('id', $request->id)->update(['auto_renew' => $value]);
		}

		// if ($request->category == 'email') {
		// 	$update_autorenew = Email::where('id', $request->id)->update(['auto_renew' => $request->value]);
		// }

		if (!$update_autorenew) {
			return response()->json([
				'status' => false,
				'message' => 'Auto renew update failed.',
				'data' => []
			]);
		}

		return response()->json([
			'status' => true,
			'message' => 'Auto renew updated.',
			'data' => []
		]);
	}

	public function payment_methods()
	{
		$stripe = new \Stripe\StripeClient(env('STRIPE'));

		$payment_methods = $stripe->customers->allPaymentMethods(auth()->user()->stripe);

		return response()->json($payment_methods);
	}

	public function get_taxes(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'country' => ['required', 'string']
		], [], [
			'country' => 'Billing Country'
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => false,
				'message' => 'Query parameters missing.',
				'data' => $validator->errors()
			]);
		}

		$tax = match ($request->country) {
			'United States' => ['rate' => 0, 'currency' => '$'],
			'India' => ['rate' => 0.18, 'currency' => '₹', 'tax_id' => 'txr_1MOTY0L5iC8E88xqkEOpoSWd'],
			'default' => ['rate' => 0, 'currency' => '$']
		};

		return response()->json([
			'status' => true,
			'data' => $tax
		]);
	}
}
