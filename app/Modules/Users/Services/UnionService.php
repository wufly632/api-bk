<?php


namespace App\Modules\Users\Services;


use App\Modules\Users\Repositories\UnionRepository;

class UnionService
{
    public static function firstOrCreate($type, $uuid)
    {
        return UnionRepository::firstOrCreate(['type' => $type, 'uuid' => $uuid]);
    }

    public static function create(array $array)
    {
        return UnionRepository::create($array);
    }

    public static function update($option, $extra_option)
    {
        UnionRepository::update($option, $extra_option);
    }
}