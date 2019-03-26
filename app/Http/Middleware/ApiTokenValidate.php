<?php
/**
 * Created by PhpStorm.
 * User: ZhongYue
 * Date: 2018/5/11
 * Time: 11:52
 */

namespace App\Http\Middleware;


use App\Modules\Home\Services\CurrencyService;
use App\Services\ApiResponse;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class ApiTokenValidate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        return $this->checkToken($request, $next);
    }

    private function checkToken($request, $next)
    {
        $token = $request->get('token');
        $currency_code = $request->input('currency_code');
        $data = '';
        if (!$token) {
            $data = [
                'currency_symbol' => CurrencyService::getCurrencySymbol($currency_code)
            ];
            return Response::json(ApiResponse::failure(g_API_TOKENEMISSED, 'please log in!', $data));
        }

        $is_expire = $this->checkExpireTime($token, $request);
        if ($is_expire) {
            $data = [
                'currency_symbol' => CurrencyService::getCurrencySymbol($currency_code)
            ];
            return Response::json(ApiResponse::failure(g_API_TOKENEXPIRED, 'please log in!', $data));
        }
        return $next($request);
    }

    private function checkExpireTime($token, $request)
    {
        $token_info = Cache::get($token);
        if ($token_info) {
            $prefix = Cache::getPrefix();
            $raw_expire_key = $prefix . $token;

            $is_expire = false;
            Cache::put($raw_expire_key, $token_info, g_CACHE_REGISTERED_TOKEN_EXPIRE_TIME);
            $user_info = json_decode($token_info);
            $request->offsetSet('user_id', $user_info->id);
            $request->offsetSet('is_register', $user_info->registered);
        } else {
            $is_expire = true;
        }
        return $is_expire;
    }
}