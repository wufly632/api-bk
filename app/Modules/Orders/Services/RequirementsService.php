<?php
/**
 * Created by PhpStorm.
 * User: longyuan
 * Date: 2018/9/19
 * Time: 下午9:21
 */

namespace App\Modules\Orders\Services;


use App\Modules\Orders\Repositories\RequirementsRepository;

class RequirementsService
{
    public static function addRequirement($require)
    {
        return RequirementsRepository::addRequirement($require);
    }
}