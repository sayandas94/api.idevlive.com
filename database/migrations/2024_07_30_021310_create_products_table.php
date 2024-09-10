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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('stripe');
            $table->string('name');
            $table->string('category');
            $table->string('duration');
            $table->string('locale')->nullable();
            $table->string('currency_symbol');
            $table->string('currency_letter');
            $table->decimal('before_discount', total: 8, places: 2);
            $table->decimal('after_discount', total: 8, places: 2);
            $table->decimal('per_month', total: 8, places: 2);
            $table->integer('discount')->nullable();
            $table->string('discount_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
