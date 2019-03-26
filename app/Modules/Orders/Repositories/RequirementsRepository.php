<?php


namespace App\Modules\Orders\Repositories;

use App\Models\Requirement\Requirement;


class RequirementsRepository
{
    public static function addRequirement($require)
    {
        return Requirement::insert($require);
    }

}