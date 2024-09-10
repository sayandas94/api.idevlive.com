<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('domain', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('invoice_id');
            $table->string('price_id');
            $table->string('product_name');
            $table->date('created_at');
            $table->date('expiring_at');
            $table->decimal('price', total: 8, places: 2);
            $table->string('status');
            $table->boolean('auto_renew');
            $table->bigInteger('connect_reseller_id');
            $table->string('domain_name');
            $table->bigInteger('website_id')->nullable();
            $table->bigInteger('dns_zone_id')->nullable();
            $table->boolean('privacy_protection');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain');
    }
};
