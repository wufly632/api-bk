<?php
/**
 * Created by PhpStorm.
 * User: longyuan
 * Date: 2019/1/7
 * Time: 8:01 PM
 */

namespace App\Models\Category;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FrontCategory extends Model
{
    protected $table = 'category_relate';

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope('status', function (Builder $builder) {
            $builder->where('status',  1);
        });
    }
}