<?php


namespace App\Models\Promotion;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $table = 'promotions_activity';

    public function promotionGoods()
    {
        return $this->hasMany(ActivityGood::class, 'activity_id', 'id');
    }
}