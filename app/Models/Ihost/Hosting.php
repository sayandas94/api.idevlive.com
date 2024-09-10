<?php

namespace App\Models\Ihost;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hosting extends Model
{
    use HasFactory;

    protected $table = 'hosting';

    protected $fillable = [
        "user_id",
        "invoice_id",
        "price_id",
        "product_name",
        "price",
        "created_at",
        "expiring_at",
        "status",
        "auto_renew",
    ];

    public $timestamps = false;
}
