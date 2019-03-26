<?php

namespace App\Modules\Home\Repositories;

use App\Models\Website\MobileCategory;

class MobileCategoryRepository
{
    public static function get($options, $inoption = [], $field = [])
    {

        $query = MobileCategory::orderByDesc('sort');
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
}