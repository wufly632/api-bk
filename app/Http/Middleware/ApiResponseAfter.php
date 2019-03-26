<?php
/**
 * Created by patpat.
 * User: Bruce.He
 * Date: 16/10/12
 * Time: 下午11:32
 * Description: API中间键,对API请求做完整性校验,安全性检查,所有对外API都必须经过次Filter
 */
namespace App\Http\Middleware;

use App\Jobs\CatchApiResponseLog;
use Closure;
use Illuminate\Foundation\Bus\DispatchesJobs;

class ApiResponseAfter
{
    use DispatchesJobs;
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $domain = request()->header('origin');
        if(env('APP_ENV') != 'production' || in_array($domain, explode(',', env('APP_DOMAIN'))) ){
            header("Access-Control-Allow-Origin: {$domain}");
        }
        $response = $next($request);

        //只有非正式环境才启用，正式环境为了性能不能启动
        if(!is_production()){
            //记录请求日志
            $this->addLogs($response->status(),$request->getMethod(),$request->fullUrl(),json_encode($request->all()),str_limit($response->getContent(),200));
        }
     return $response;
    }


    public function addLogs($status,$method,$url,$p,$response)
    {
        $job = new CatchApiResponseLog($status,$method,$url,str_limit($p,200),$response);
        $this->dispatch($job);
    }

}
