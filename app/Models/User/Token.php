<?php


namespace App\Models\User;


use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    protected $table = 'user_tokens';
    protected $guarded = ['id'];
}