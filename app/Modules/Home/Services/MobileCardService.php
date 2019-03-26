<?php


namespace App\Modules\Home\Services;


use App\Modules\Home\Repositories\MobileCardRepository;

class MobileCardService
{
    public static function getActivities()
    {
        $activities = MobileCardRepository::get([], ['type', 'content']);
        return $activities;
    }
}