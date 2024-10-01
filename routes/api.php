<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AccountsController;
use App\Http\Controllers\Ihost\CheckoutController;
use App\Http\Controllers\Ihost\CartController;
use App\Http\Controllers\Ihost\DomainController;
use App\Http\Controllers\Ihost\HostingController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::group(['prefix' => 'accounts'], function () {
	Route::get('get-taxes', [AccountsController::class, 'get_taxes']);

	Route::post('register', [AccountsController::class, 'register']);
	Route::post('login', [AccountsController::class, 'login']);
});

Route::group(['prefix' => 'ihost'], function () {
	Route::group(['prefix' => 'cart'], function () {
		Route::get('validate', [CartController::class, 'validate']);
		Route::get('product-info', [CartController::class, 'product_info']);
	});

	Route::group(['prefix' => 'checkout'], function () {
		Route::group(['middleware' => ['auth:sanctum']], function () {
			Route::post('create-invoice', [CheckoutController::class, 'create_invoice']);
			Route::post('deliver-products', [CheckoutController::class, 'deliver_products']);
			Route::post('renew-hosting', [CheckoutController::class, 'renew_hosting']);
		});
	});

	Route::group(['prefix' => 'hosting'], function () {
		Route::get('show', [HostingController::class, 'show']);
		Route::get('choose-plan', [HostingController::class, 'choose_plan']);
		Route::get('get-price-info', [HostingController::class, 'get_price_info']);
		Route::get('multi-year-pricing', [HostingController::class, 'multi_year_pricing']);

		Route::group(['middleware' => ['auth:sanctum']], function () {
			Route::get('details', [HostingController::class, 'details']);
			Route::post('setup', [HostingController::class, 'setup']);
		});
	});

	Route::group(['prefix' => 'domain'], function () {
		Route::post('search', [DomainController::class, 'search']);
		Route::get('popular-domain-prices', [DomainController::class, 'popular_domain_prices']);
		Route::get('similar-domains', [DomainController::class, 'similar_domains']);
		Route::get('multi-year-pricing', [DomainController::class, 'multi_year_price']);
		Route::get('create-multi-year-pricing', [DomainController::class, 'create_multi_year_price']);
		Route::get('mutli-year-price-info', [DomainController::class, 'mutli_year_price_info']);
		
		Route::get('get-json', [DomainController::class, 'get_domain']);
		Route::post('add-json', [DomainController::class, 'add_json']);
		
		Route::get('add-domain', [DomainController::class, 'add']);
		Route::group(['middleware' => ['auth:sanctum']], function () {
			// Route::get('activate-domain', [DomainController::class, 'activate_domain']);
			Route::get('domain-status', [DomainController::class, 'domain_status']);
			Route::get('details', [DomainController::class, 'details']);
			Route::get('fetch-dns', [DomainController::class, 'fetch_dns']);
			Route::post('add-dns', [DomainController::class, 'add_dns']);
			Route::post('edit-dns', [DomainController::class, 'edit_dns']);
			Route::get('delete-dns', [DomainController::class, 'delete_dns']);

			Route::post('modify-nameservers', [DomainController::class, 'modify_nameservers']);

			Route::get('change-privacy', [DomainController::class, 'change_privacy']);
			Route::get('domain-lock', [DomainController::class, 'domain_lock']);
			Route::get('theft-protection', [DomainController::class, 'theft_protection']);
		});
	});
});

Route::group(['middleware' => ['auth:sanctum']], function () {
	Route::group(['prefix' => 'accounts'], function () {
		Route::get('logout', [AccountsController::class, 'logout']);
		Route::get('profile', [AccountsController::class, 'profile']);
		Route::get('active-subscriptions', [AccountsController::class, 'active_subscriptions']);
		Route::get('fetch-address', [AccountsController::class, 'fetch_address']);
		Route::get('list-cards', [AccountsController::class, 'list_cards']);
		Route::get('invoices', [AccountsController::class, 'invoices']);
		Route::get('payment-methods', [AccountsController::class, 'payment_methods']);
		
		Route::post('update-address', [AccountsController::class, 'update_address']);
		Route::post('update-password', [AccountsController::class, 'update_password']);
		Route::post('update-pin', [AccountsController::class, 'update_pin']);
		Route::post('update-autorenew', [AccountsController::class, 'update_autorenew']);
	});
});