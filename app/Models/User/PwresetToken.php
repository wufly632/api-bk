<?php
/**
 * Created by PhpStorm.
 * User: EDZ
 * Date: 2018/12/6
 * Time: 15:19
 */

namespace App\Models\User;


use Illuminate\Database\Eloquent\Model;

class PwresetToken extends Model
{
    protected $table = 'user_pwreset_tokens';
    protected $fillable = ['user_id', 'token', 'status'];
}