<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

class ProductSku extends Model
{
    protected $table = 'good_skus';

    protected function getIconAttribute($item)
    {
        return cdnUrl($item);
    }
}
