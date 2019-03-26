<?php

namespace App\Http\Middleware;

use App\Assistants\CLogger;
use App\Services\ApiResponse;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Redirect;
use Torann\GeoIP\GeoIP;

class VisitIntercepter
{
    use DispatchesJobs;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        //addPayment接口禁止中国地区访问这个接口,有黑客调用此接口
        //方便测试用加一个模拟ip
        if ($request->path() == 'addPayment') {
            $ip_address = getIP();
            if ($request->get('simulate_ip_address')) {
                $ip_address = $request->get('simulate_ip_address');
            }
            $location = GeoIP::getLocation($ip_address);
            if ($location && $location['isoCode'] == 'CN') {
                CLogger::getLogger('error', 'forbit_ip_headers')->warning('ip ' . $ip_address . ':', $request->headers);
                CLogger::getLogger('error', 'forbit_ip')->warning('forbit ip ' . $ip_address . ' visit ' . $request->fullUrl(), $request->all());
                $seed = mt_rand(0, 100);
                if ($seed < 4) {
                    return Redirect::to('/payment')->with("success", "add payment success!");
                } else {
                    //返回一个假的失败免得被发现已经拒绝访问了
                    return ApiResponse::failure(g_API_ERROR, "Declined fail");
                }
            }
        }
        return $next($request);
    }

}
