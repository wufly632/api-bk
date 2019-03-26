<?php


namespace App\Models\User;


use Illuminate\Database\Eloquent\Model;

class Union extends Model
{
    protected $table = 'user_unions';
    protected $fillable = ['user_id', 'uuid', 'type'];
}