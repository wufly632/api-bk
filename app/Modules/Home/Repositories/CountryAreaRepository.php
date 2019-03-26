<?php

namespace App\Modules\Home\Repositories;

use App\Models\CountryArea;

class CountryAreaRepository
{
    public static function get($options, $field = ['*'])
    {
        $query = CountryArea::whereNotNull('id');
        if ($options) {
            foreach ($options as $option) {
                $query = $query->where($option);
            }
        }
        return $query->orderBy('name')->get($field);
    }
}