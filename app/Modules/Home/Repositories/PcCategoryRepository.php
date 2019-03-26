<?php

namespace App\Modules\Home\Repositories;

use App\Models\Website\PcCategory;

class PcCategoryRepository
{
    public static function get($options, $inoption = [], $field = ['*'])
    {

        $query = PcCategory::orderByDesc('sort');
        if ($field) {
            $query = $query->select($field);
        }
        if ($options) {
            foreach ($options as $option) {
                $query = $query->where($option);
            }
        }
        if ($inoption) {
            $query = $query->whereIn('parent_id', $inoption);
        }
        return $query->get();
    }

    public static function find($option)
    {
        return PcCategory::where($option)->first();
    }
}