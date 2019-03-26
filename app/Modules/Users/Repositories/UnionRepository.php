<?php
/**
 * Created by PhpStorm.
 * User: EDZ
 * Date: 2019/1/5
 * Time: 15:46
 */

namespace App\Modules\Users\Repositories;


use App\Models\User\Union;

class UnionRepository
{
    public static function firstOrCreate($option)
    {
        return Union::firstOrCreate($option);
    }

    public static function create(array $array)
    {
        return Union::create($array);
    }

    public static function update($option, $extra_option)
    {
        Union::where($option)->update($extra_option);
    }
}