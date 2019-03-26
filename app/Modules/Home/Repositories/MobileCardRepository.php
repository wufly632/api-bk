<?php


namespace App\Modules\Home\Repositories;


use App\Models\Home\MobileCard;

class MobileCardRepository
{
    public static function get($options)
    {
        $query = MobileCard::orderBy('id');
        if ($options) {
            foreach ($options as $option) {
                $query = $query->where($option);
            }
        }
        return $query->get();
    }
}