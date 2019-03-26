<?php

namespace App\Models\Customer;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class IntegralLog extends Model
{
    protected $table = 'customer_integral_log';

    protected $guarded = ['id', 'created_at'];

    public function getCreatedAtAttribute($date)
    {
        return Carbon::parse($date)->toDateTimeString();
    }
}