<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'user_alias', 'firstname', 'lastname', 'fullname', 'birth', 'gender', 'phone', 'last_login_datetime', 'registered', 'cucoe_id', 'accumulated_income',
        'integral', 'amount_money', 'logo', 'calling_code'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function getLogoAttribute($item)
    {
        return cdnUrl($item);
    }
}
