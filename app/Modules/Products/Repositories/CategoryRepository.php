<?php
/**
 * Created by PhpStorm.
 * User: longyuan
 * Date: 2018/9/9
 * Time: 上午11:11
 */

namespace App\Modules\Products\Repositories;


use App\Models\Category\Category;
use App\Models\Category\CategoryAttribute;

class CategoryRepository
{
    const CATE_BASE_ATTR_TYPE = 1;
    const CATE_KEY_ATTR_TYPE = 2;
    const CATE_SKU_ATTR_TYPE = 3;
    const CATE_NOT_KEY_ATTR_TYPE = 4;
    const CATE_ATTR_SHOW_DETAIL = 1;

    public static function getAll()
    {
        return Category::orderByDesc('sort')
            ->get();
    }

    public static function getCateInfo($categoryId)
    {
        return Category::find($categoryId);
    }

    public static function getCateInfoByIds($categoryIds)
    {
        return Category::whereIn('id', $categoryIds)
            ->orderBy('id')
            ->get();
    }

    public static function getCateDetailAttr($cateId)
    {
        return CategoryAttribute::where('category_id', $cateId)
            ->where('attr_type', self::CATE_NOT_KEY_ATTR_TYPE)
            ->where('is_detail', self::CATE_ATTR_SHOW_DETAIL)
            ->get();
    }

    /**
     * @function 获取类目路径
     * @param $cateId
     * @return array
     */
    public static function getCategoryPath($cateId)
    {
        $category = Category::find($cateId);
        if (! $category) {
            return [];
        }
        $category_path = Category::whereIn('id', explode(',', $category->category_ids))
            ->where('status', 1)
            ->orderBy('level', 'asc')
            ->get(['id', 'en_name as name'])
            ->toArray();
        $category_path[] = ['id' => $category->id, 'name' => $category->en_name];
        return $category_path;
    }
}