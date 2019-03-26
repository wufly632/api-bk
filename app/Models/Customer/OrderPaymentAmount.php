<?php
/**
 * Created by PhpStorm.
 * User: EDZ
 * Date: 2018/12/8
 * Time: 11:24
 */

namespace App\Models\Customer;


use Illuminate\Database\Eloquent\Model;

class OrderPaymentAmount extends Model
{
    protected $table = 'customer_order_payment_amount';

    protected $guarded = ['id'];
}