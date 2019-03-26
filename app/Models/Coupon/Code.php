<?php

namespace App\Models\Coupon;

use Illuminate\Database\Eloquent\Model;

class Code extends Model
{
    protected $table = 'coupon_code';
    protected $hidden = ['created_at', 'update_at'];
    protected $fillable = ['user_id','coupon_id','code_code','code_receive_status','code_use_status','code_received_at','code_used_start_date','code_used_end_date','code_used_at'];
    protected $guarded = ['id'];

    public function coupon()
    {
        return $this->hasOne(Coupon::class, 'id', 'coupon_id');
    }
}