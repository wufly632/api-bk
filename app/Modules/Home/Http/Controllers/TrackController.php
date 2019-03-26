<?php

namespace App\Modules\Home\Http\Controllers;

use App\Assistants\CLogger;
use App\Services\ApiResponse;
use Illuminate\Routing\Controller;


class TrackController extends Controller
{

    public function index()
    {
        if($this->verifier()){
            return $this->verifier();
        }
        $data = [];
        $data[] = request()->input('system');
        $data[] = request()->input('local');
        $data[] = request()->input('otype');
        $data[] = request()->input('user');
        $data[] = request()->input('rtype');
        $data[] = request()->getClientIp();
        $data[] = request()->input('token', '');
        CLogger::getLogger('system-info', 'track')->info(implode('||', $data));
        return ApiResponse::success('ok');
    }

    protected function verifier()
    {
        if(! request()->input('system', '')){
            return ApiResponse::failure(g_API_ERROR, 'sys can not be null');
        }else if(! request()->input('local', '')){
            return ApiResponse::failure(g_API_ERROR, 'loc can not be bull');
        }else if(! request()->input('otype', '')){
            return ApiResponse::failure(g_API_ERROR, 'o can not be null');
        }else if(! request()->input('user', '')){
            return ApiResponse::failure(g_API_ERROR, 'u can not be null');
        }else if(! request()->input('rtype', '')){
            return ApiResponse::failure(g_API_ERROR, 'r can not be null');
        }
        return false;
    }
}
