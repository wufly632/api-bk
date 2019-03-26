<?php
/**
 * Created by PhpStorm.
 * User: EDZ
 * Date: 2018/12/8
 * Time: 11:18
 */

namespace App\Models\Customer;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class FinanceLog extends Model
{
    protected $table = 'customer_finance_log';

    protected $guarded = ['id', 'created_at'];

    public function getCreatedAtAttribute($date)
    {
        return Carbon::parse($date)->toDateTimeString();
    }
}