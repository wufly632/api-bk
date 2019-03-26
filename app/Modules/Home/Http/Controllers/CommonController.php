<?php

namespace App\Modules\Home\Http\Controllers;

use App\Models\Currency;
use App\Modules\Home\Services\HomeService;
use App\Modules\Home\Services\ShortUrlService;
use App\Modules\Products\Repositories\ProductsRepository;
use App\Modules\Products\Services\ProductsService;
use App\Services\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class CommonController extends Controller
{
    public function getShortUrl(Request $request)
    {
        return ApiResponse::success(['shortUrl' => $request->input('url', '')]);
        if (!$request->input('url', '')) return ApiResponse::failure(g_API_ERROR, 'Url can not be null');
        $result = ShortUrlService::dwzShorten($request->input('url'));
        if ($result['shortURL']) return ApiResponse::success(['shortUrl' => $result['shortURL']]);
        return ApiResponse::failure(g_API_ERROR, 'create Short url failure');
    }
}
