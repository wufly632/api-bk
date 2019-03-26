<?php
/**
 * Created by PhpStorm.
 * User: longyuan
 * Date: 2018/10/17
 * Time: 下午9:08
 */
namespace App\Modules\Home\Services;

use App\Modules\Home\Repositories\CountryRepository;

class CountryService
{
    public static function getAll()
    {
        return CountryRepository::getAll();
    }
}