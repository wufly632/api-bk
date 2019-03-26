<?php
/**
 * Created by PhpStorm.
 * User: ZhongYue
 * Date: 2018/5/11
 * Time: 11:52
 */

namespace App\Http\Middleware;


use App\Services\ApiResponse;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class ApiToken
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
        $token = $request->input('token');
        if (!$token) {
            return Response::json(ApiResponse::failure(g_API_TOKENEMISSED, 'token missed!'));
        }
        return $next($request);
    }
}