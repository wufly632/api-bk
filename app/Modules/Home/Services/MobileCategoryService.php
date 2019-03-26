<?php

namespace App\Modules\Home\Services;

use App\Modules\Home\Repositories\MobileCategoryRepository;

class MobileCategoryService
{
    public static function getAll()
    {
        return MobileCategoryRepository::get([['parent_id' => 0]], [], ['id', 'name', 'icon', 'parent_id', 'front']);
    }

    public static function getChildrenByParentId($parentIds)
    {
        return MobileCategoryRepository::get([], $parentIds, ['id', 'name', 'icon', 'image', 'parent_id', 'category_id', 'front']);
    }
}