<?php

namespace App\Http\Controllers\Ihost;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Ihost\Product;
use App\Models\Price;

class CartController extends Controller
{
	public function validate(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'id' => ['required', 'string']
		], [], [
			'id' => 'Product ID'
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => false,
				'message' => $validator->errors(),
				'data' => []
			]);
		}

		$product_validate = Product::where('stripe', $request->id)->first();

		return response()->json([
			'status' => true,
			'message' => 'Product validated.',
			'data' => $product_validate
		]);
	}

	public function product_info(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'id' => ['required', 'string'],
		], [], [
			'id' => 'Price ID',
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => false,
				'message' => 'Validation failed.',
				'data' => $validator->errors()
			]);
		}
		
		$price_info = Price::where('price_id', $request->id)->first();
		$product_info = Product::where('product_id', $price_info->product_id)->first();

		$stripe = new \Stripe\StripeClient(env("STRIPE"));
		$stripe_price = $stripe->prices->retrieve($price_info->price_id, []);
		
		$price_info->unit_amount = $stripe_price->unit_amount;
		$price_info->currency = $stripe_price->currency;

		if ($price_info->discount_id) {
			$price_info->discount_info = $stripe->coupons->retrieve($price_info->discount_id, []);
		}

		return response()->json([
			'status' => true,
			'message' => 'Product validated.',
			'data' => [
				'price_info' => $price_info,
				'product_info' => $product_info
			]
		]);
	}
}
