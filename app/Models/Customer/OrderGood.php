<?php
/**
 * Created by PhpStorm.
 * User: EDZ
 * Date: 2018/12/8
 * Time: 14:02
 */

namespace App\Models\Customer;


use App\Models\Product\Products;
use Illuminate\Database\Eloquent\Model;

class OrderGood extends Model
{
    protected $table = 'customer_order_goods';
    protected $guarded = ['id'];

    public function order()
    {
        return $this->hasOne(Order::class, 'order_id', 'order_id');
    }

    public function good()
    {
        return $this->hasOne(Products::class, 'id', 'good_id');
    }
}