<?php
/**
 * Created by PhpStorm.
 * User: EDZ
 * Date: 2018/12/8
 * Time: 13:51
 */

namespace App\Models\Customer;


use App\Models\Order\CustomerOrderAddress;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'customer_order';
    protected $guarded = ['id'];

    public function getCreatedAtAttribute($date)
    {
        return Carbon::parse($date)->toDateTimeString();
    }

    public function address()
    {
        return $this->hasOne(CustomerOrderAddress::class);
    }
}