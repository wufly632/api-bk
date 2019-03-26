<?php
/**
 * Created by patpat.
 * User: Bruce.He
 * Date: 16/10/12
 * Time: 下午11:32
 * Description: API中间键,对API请求做完整性校验,安全性检查,所有对外API都必须经过次Filter
 */

namespace App\Http\Middleware;

use App\Assistants\ApiResponse;
use App\Assistants\CLogger;
use App\Modules\Home\Services\CurrencyService;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;

class ApiAuth
{
    /**
     * 不需要验证token的
     *
     * @var array
     */
    protected $except = [
   
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        //设置语言环境
        App::setLocale('en');
//        header("Access-Control-Allow-Origin: *"); // 允许任意域名发起的跨域请求
        header('Access-Control-Allow-Methods:OPTIONS, GET, POST');
        header('Access-Control-Allow-Headers:x-requested-with');
        header('Access-Control-Max-Age:86400');
        header('Access-Control-Allow-Credentials:true');
        header('Access-Control-Allow-Methods:GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers:x-requested-with,content-type');
        header('Access-Control-Allow-Headers:Origin, No-Cache, X-Requested-With, If-Modified-Since, Pragma, Last-Modified, Cache-Control, Expires, Content-Type, X-E4M-With');

        $version =  $request->header('protocol');
        if(! $request->input('currency_code', '')){
            $request->offsetSet('currency_code', $currency_code = (new CurrencyService)->getDefaultCurrency());
        }
        return $next($request);
    }
}
