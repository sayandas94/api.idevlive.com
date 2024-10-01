<?php

namespace App\Http\Controllers\Ihost;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Ihost\Hosting;
use App\Models\Ihost\Domain;
use App\Models\Price;

class CheckoutController extends Controller
{
    public function create_invoice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'string'],
            'cart_items' => ['required', 'json']
        ], [], [
            'id' => 'User ID',
            'cart_items' => 'Cart Items'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'data' => $validator->errors()
            ]);
        }

        $stripe_id = $request->id;
        $cart_items = json_decode($request->cart_items);

        $stripe = new \Stripe\StripeClient(env('STRIPE'));

        $customer = $stripe->customers->retrieve($stripe_id, []);

        $createInvoice = $stripe->invoices->create([
            "customer" => $stripe_id,
            "auto_advance" => true,
            "description" => "Web Hosting & Domain Registration Services",
        ]);

        foreach ($cart_items as $key => $item) {
            $inputArray = [
                'customer' => $stripe_id,
                'price' => $item->price_id,
                'invoice' => $createInvoice->id,
                'quantity' => 1,
                'description' => $item->product_name
            ];

            if ($customer->address->country == 'IN') {
                $inputArray['tax_rates'] = ['txr_1MOTY0L5iC8E88xqkEOpoSWd'];
            }

            if ($item->discount_id) {
                $inputArray['discountable'] = true;
                $inputArray['discounts'] = ['coupon' => $item->discount_id];
            }

            try {
                $stripe->invoiceItems->create($inputArray);
            } catch (\Throwable $th) {
                return response()->json([
                    'status' => false,
                    'message' => ['error' => ['Error occured while creating the invoice items.']]
                ]);
            }
        }

        $finalizedInvoice = $stripe->invoices->finalizeInvoice(
            $createInvoice->id,
            []
        );

        $paymentIntent = $stripe->paymentIntents->retrieve(
            $finalizedInvoice->payment_intent,
            []
        );

        return response()->json([
            "status" => true,
            "message" => "Invoice created.",
            "data" => [
                "invoiceId" => $createInvoice->id,
                "paymentIntent" => $finalizedInvoice->payment_intent,
                "clientSecret" => $paymentIntent->client_secret,
                "customer" => [
                    "name" => $createInvoice->customer_name,
                    "email" => $createInvoice->customer_email,
                    "phone" => $createInvoice->customer_phone
                ],
                "billingAddress" => $createInvoice->customer_address
            ],
        ]);
    }

    public function deliver_products(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invoice_id' => ['required'],
            'cart_items' => ['required', 'json']
        ], [], [
            'invoice_id' => 'Invoice ID',
            'cart_items' => 'Cart Items'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'data' => $validator->errors()
            ]);
        }

        # CHECK THE VALIDITY OF INVOICE ID
        $invoice_id = $request->invoice_id;

        $stripe = new \Stripe\StripeClient(env("STRIPE"));

        $invoice = $stripe->invoices->retrieve($invoice_id, []);

        if (!$invoice->paid) {
            return response()->json([
                "status" => false,
                "message" => "Invoice not paid yet.",
                "data" => [],
            ]);
        }

        $cart_items = json_decode($request->cart_items);

        foreach ($cart_items as $key => $item) {
            if ($item->category == 'Web Hosting') {
                $inputData = [
                    'user_id' => auth()->user()->id,
                    'invoice_id' => $invoice_id,
                    'price_id' => $item->price_id,
                    'product_name' => $item->product_name,
                    'price' => number_format($item->unit_amount / 100, 2, '.', ''),
                    'created_at' => date('Y-m-d G:i:s', strtotime('now')),
                    'expiring_at' => date('Y-m-d G:i:s', strtotime('+ ' . $item->duration_text)),
                    'status' => 'Setup',
                    'auto_renew' => ($item->auto_renew == 'on') ? 1 : 0
                ];

                try {
                    Hosting::create($inputData);
                } catch (\Illuminate\Database\QueryException $exception) {
                    return response()->json([
                        'status' => false,
                        'message' => $exception->errorInfo,
                        'data' => [date('Y-m-d G:i:s', strtotime('now'))]
                    ]);
                }
            }

            if ($item->category == 'Domain') {
                $inputData = [
                    'user_id' => auth()->user()->id,
                    'invoice_id' => $invoice_id,
                    'price_id' => $item->price_id,
                    'product_name' => $item->product_name,
                    'created_at' => date('Y-m-d G:i:s', strtotime('now')),
                    'expiring_at' => date('Y-m-d G:i:s', strtotime('+ ' . $item->duration_text)),
                    'price' => number_format($item->unit_amount / 100, 2, '.', ''),
                    'status' => 'Setup',
                    'auto_renew' => ($item->auto_renew == 'on') ? 1 : 0,
                    'connect_reseller_id' => auth()->user()->connect_reseller,
                    'domain_name' => $item->domain_name,
                    'privacy_protection' => 1
                ];

                $registration_status = true;

                if (env('APP_ENV') != '') {
                    
                    if (auth()->user()->region == 'in') {
                        $api_key = env('CONNECT_RESELLER_INDIA');
                    } else {
                        $api_key = env('CONNECT_RESELLER');
                    }
                    // Code for Domain Registration
                    // return response()->json([
                    //     'domain' => $item->domain_name,
                    //     'api' => $api_key,
                    //     'reseller_id' => auth()->user()->connect_reseller
                    // ]);
                    // $curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/domainorder?APIKey='. $api_key .'&ProductType=1&Websitename='. $item->domain_name .'&Duration=1&IsWhoisProtection=true&ns1=nameserver1&ns2=nameserver2&ns3=nameserver3&ns4=nameserver4&Id='. auth()->user()->connect_reseller .'&isEnablePremium=0');

                    $curl = curl_init('https://api.connectreseller.com/ConnectReseller/ESHOP/domainorder?APIKey='. $api_key .'&ProductType=1&Websitename='. $item->domain_name .'&Duration=1&IsWhoisProtection=true&ns1=3978.dns1.managedns.org&ns2=3978.dns2.managedns.org&ns3=3978.dns3.managedns.org&ns4=3978.dns4.managedns.org&Id='. auth()->user()->connect_reseller .'&isEnablePremium=0');

                    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true]);

                    $response = curl_exec($curl);

                    curl_close($curl);

                    $response = json_decode($response);

                    if ($response->responseMsg->statusCode != 200) {
                        $registration_status = false;
                    }
                    // return response()->json($response);
                }

                if ($registration_status) {
                    try {
                        Domain::create($inputData);
                    } catch (\Illuminate\Database\QueryException $exception) {
                        return response()->json([
                            'status' => false,
                            'message' => $exception->errorInfo,
                            'data' => [date('Y-m-d G:i:s', strtotime('now'))]
                        ]);
                    }
                }
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Purchase successfull.',
            'data' => [
                'redirect_url' => 'user/dashboard',
                'cart_items' => $cart_items
            ]
        ]);
    }

    public function renew_hosting(Request $request)
    {
        
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'price' => ['required', 'string'],
            'payment_method' => ['required', 'string']
        ], [], [
            'id' => 'Hosting ID',
            'price' => 'Price ID',
            'payment_method' => 'Payment Method'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'data' => $validator->errors()
            ]);
        }

        // $invoiceData = [
        //     'customer' => auth()->user()->stripe,
        //     'price' => $request->price,
        //     'quantity' => 1
        // ];

        // if ($request->taxId != null) {
        //     $invoiceData['tax_rates'] = [$request->taxId];
        // }

        $invoiceData = [
            'customer' => auth()->user()->stripe,
            'default_payment_method' => $request->payment_method,
            'invoice_item' => [
                'customer' => auth()->user()->stripe,
                'price' => $request->price,
                'quantity' => 1
            ]
        ];

        if ($request->taxId != null) {
            $invoiceData['invoice_item']['tax_rates'] = [$request->taxId];
        }

        $invoice = $this->generate_invoice($invoiceData);

        // $invoice = $this->generate_invoice(auth()->user()->stripe);

        // $invoiceData = [
        //     'id' => $invoice->id,
        //     'invoiceItem' => $request->price,
        //     'taxId' => $request->taxId,
        // ];

        // $addInvoiceItems = $this->add_invoice_items

        if ($invoice == false) {
            return response()->json('Can\'t create invoice');
        }

        // return response()->json($invoice->id);

        // $invoicePayment = $this->invoice_payment($invoice->id);

        // if ($invoicePayment == false) {
        //     return response()->json('Can\'t pay the invoice');
        // }
        // return response()->json($invoicePayment);

        $duration = Price::where('price_id', $request->price)->first()->duration_text;

        Hosting::where('id', $request->id)->update([
            'expiring_at' => date('Y-m-d G:i:s', strtotime($duration)),
            'price_id' => $request->price
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Renewal done.',
            'data' => $invoice
        ]);
    }

    protected function generate_invoice($invoiceData)
    {
        $stripe = new \Stripe\StripeClient(env('STRIPE'));

        try {
            $invoice = $stripe->invoices->create([
                'customer' => $invoiceData['customer'],
                'auto_advance' => true,
                'description' => 'Renewal for Web Hosting & Domain Registration',
                'default_payment_method' => $invoiceData['default_payment_method']
            ]);
        } catch (\Throwable $th) {
            return false;
        }

        $invoiceData['invoice_item']['invoice'] = $invoice->id;
        # create invoice item
        try {
            $stripe->invoiceItems->create($invoiceData['invoice_item']);
        } catch (\Throwable $th) {
            return false;
        }

        # finalize the invoice
        try {
            $stripe->invoices->finalizeInvoice($invoice->id, []);
        } catch (\Throwable $th) {
            return $th;
        }

        try {
            $stripe->invoices->pay($invoice->id, []);
        } catch (\Throwable $th) {
            return $th;
        }

        return $invoice;
    }

    // protected function invoice_payment($id)
    // {
        
    //     return true;
    // }
}
