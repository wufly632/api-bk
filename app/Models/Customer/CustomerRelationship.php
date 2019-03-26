<?php


namespace App\Models\Customer;

use App\User;
use Illuminate\Database\Eloquent\Model;

class CustomerRelationship extends Model
{
    protected $table = 'customer_relationship';

    protected $fillable = ['user_id', 'parent_id'];

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
}