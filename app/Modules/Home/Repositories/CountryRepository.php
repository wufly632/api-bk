<?php
/**
 * Created by PhpStorm.
 * User: longyuan
 * Date: 2018/10/17
 * Time: 下午9:09
 */
namespace App\Modules\Home\Repositories;

use App\Models\Home\Country;
use Illuminate\Support\Facades\DB;

class CountryRepository
{
    public static function getAll()
    {
        return Country::all();
    }
}