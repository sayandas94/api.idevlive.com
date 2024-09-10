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
        Schema::create('hosting', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('invoice_id');
            $table->string('price_id');
            $table->string('product_name');
            $table->date('purchase_date');
            $table->date('renewal_date');
            $table->decimal('price', total: 8, places: 2);
            $table->string('status');
            $table->string('primary_domain')->nullable();
            $table->string('server_ip')->nullable();
            $table->string('auto_renew');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hosting');
    }
};
