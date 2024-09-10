<?php

namespace App\Models\Ihost;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    use HasFactory;

    protected $table = 'domain';

    protected $fillable = [
        'user_id',
        'invoice_id',
        'price_id',
        'product_name',
        'created_at',
        'expiring_at',
        'price',
        'status',
        'auto_renew',
        'connect_reseller_id',
        'domain_name',
        'website_id',
        'dns_zone_id',
        'privacy_protection'
    ];

    public $timestamps = false;
}
