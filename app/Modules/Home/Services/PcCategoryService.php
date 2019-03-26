<?php

namespace App\Modules\Home\Services;

use App\Modules\Home\Repositories\PcCategoryRepository;

class PcCategoryService
{
    public static function getAll()
    {
        return PcCategoryRepository::get([['parent_id' => 0]], []);
    }

    public static function getChildrenByParentId($parentIds)
    {
        return PcCategoryRepository::get([], $parentIds, ['id', 'name', 'parent_id', 'category_id', 'front']);
    }

    public static function getPath($category_id)
    {
        $category = PcCategoryRepository::find([
            'category_id' => $category_id
        ]);
        if (!$category || isset($category->icon)) {
            return [];
        }
        $nav = [
            [
                'name' => $category->name,
                'id'   => $category->front == 1 ? self::generateFrontCategory($category_id) : $category_id,
            ]
        ];
        if ($category->parent_id) {
            $categoryParent = PcCategoryRepository::find(['id' => $category->parent_id]);
            array_unshift($nav, [
                'name' => $categoryParent->name,
                'id'   => $categoryParent->front == 1 ? self::generateFrontCategory(explode(',',
                    $categoryParent->category_id)) : $categoryParent->category_id,
            ]);
        }
        return $nav;
    }

    public static function generateFrontCategory($ids)
    {
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }
        array_walk($ids, function (&$item) {
            $item = $item . '-front';
        });
        return join(',', $ids);
    }
}