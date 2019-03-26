<?php
/**
 * Created by PhpStorm.
 * User: EDZ
 * Date: 2018/12/6
 * Time: 15:45
 */

namespace App\Models\Customer;


use Illuminate\Database\Eloquent\Model;

class CardsAddress extends Model
{
    protected $table = 'customer_cards_address';

    protected $guarded = ['id'];
}