<?php


namespace App\Models\Product;


use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
    protected $table = 'admin_attribute';

    public function attrbuteValue()
    {
        return $this->hasMany(AdminAttributeValue::class, 'attribute_id', 'id');
    }
}