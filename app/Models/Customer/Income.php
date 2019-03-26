<?php


namespace App\Models\Customer;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Income extends Model
{
    protected $table = 'customer_incomes';

    protected $fillable = ['from_user_id', 'income_user_id', 'type', 'status', 'order_id', 'amount', 'order_amount'];

    public function getCreatedAtAttribute($date)
    {
        return Carbon::parse($date);
    }
}