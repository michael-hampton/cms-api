<?php

namespace App\Models;

class ProductVoucher extends Model
{

    protected $table = 'product_voucher';
    protected $fillable = ['product_id', 'voucher_id'];
}