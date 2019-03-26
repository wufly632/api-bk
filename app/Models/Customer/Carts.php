<?php

namespace App\Models\Customer;

use App\Models\Product\Products;
use Illuminate\Database\Eloquent\Model;

class Carts extends Model
{
    protected $table = 'customer_carts';

    protected $guarded = ['id'];

    public function goodDetailInfo()
    {
        return $this->hasOne(Products::class, 'id', 'good_id');
    }
}