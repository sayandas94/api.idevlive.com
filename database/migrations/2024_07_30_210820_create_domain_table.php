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
            $table->string('connect_reseller_id');
            $table->string('plan_id');
            $table->string('domain_name');
            $table->date('created_at');
            $table->date('expiring_at');
            $table->boolean('status')->default(TRUE);
            $table->boolean('auto_renew');
            $table->bigInteger('website_id');
            $table->bigInteger('dns_zone_id');
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
