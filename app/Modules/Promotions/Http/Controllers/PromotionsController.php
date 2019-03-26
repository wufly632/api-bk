<?php

namespace App\Modules\Promotions\Http\Controllers;

use App\Modules\Promotions\Services\PromotionsService;
use App\Modules\Users\Repositories\CurrencyRepository;
use App\Services\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class PromotionsController extends Controller
{
    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        $promotionId = $request->input('promotion_id', 0);
        $promotionInfo = PromotionsService::getPromotionInfo($promotionId);
        if (!$promotionInfo) return ApiResponse::failure(g_API_URL_NOTFOUND, 'the activity does not exist');
        $currency_code = isset($request->currency_code) ? $request->currency_code : 'USD';
        $date = date('Y-m-d H:i:s', strtotime("{$promotionInfo->pre_time} day"));
        $currency = CurrencyRepository::getByCurrencyCode($currency_code);
        if (!$currency) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'Currency dose not exists');
        }
        if ($promotionInfo->start_at > $date) return ApiResponse::failure(g_API_URL_NOTFOUND, 'the activity has not started yet');
        $now = Carbon::now()->toDateTimeString();
        if ($promotionInfo->end_at < $now) return ApiResponse::failure(g_API_URL_NOTFOUND, 'the activity is over');
        $result = PromotionsService::promotionShow($promotionInfo, $currency);
        // 判断用户选择的货币与活动货币是否一致
        $result['currency_same'] = true;
        if ($promotionInfo->currency_code != $currency_code) {
            $result['currency_same'] = false;
        }
        return ApiResponse::success($result);
    }
}
