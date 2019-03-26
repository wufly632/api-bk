<?php

namespace App\Modules\Activity\Http\Controllers;

use App\Modules\Activity\Services\ActivityService;
use App\Services\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class ActivityController extends Controller
{
    public function getMarquee()
    {
        return ApiResponse::success(ActivityService::getMarquee());
    }

    public function getTopTen()
    {
        return ApiResponse::success(ActivityService::getTopTen());
    }
}
