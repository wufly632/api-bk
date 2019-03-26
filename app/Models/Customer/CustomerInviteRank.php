<?php


namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;

class CustomerInviteRank extends Model
{
    protected $table = 'customer_invite_rank';

    protected $fillable = ['user_id', 'count'];
}