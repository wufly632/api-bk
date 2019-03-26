<?php
// +----------------------------------------------------------------------
// | CurrencyService.php
// +----------------------------------------------------------------------
// | Description:
// +----------------------------------------------------------------------
// | Time: 2018/12/21 下午4:24
// +----------------------------------------------------------------------
// | Author: wufly <wfxykzd@163.com>
// +----------------------------------------------------------------------

namespace App\Modules\Home\Services;

use App\Assistants\CLogger;
use App\Modules\Users\Repositories\CurrencyRepository;
use App\Services\ApiResponse;

class CurrencyService
{
    const CLIENT_IP_CACHE_KEY = 'CLIENT_IP_CACHE_KEY';

    protected $country = [];

    public function __construct()
    {
        $this->country = config('country.country_currency');
    }

    /**
     * @function 获取ip地址信息
     * @param $ip
     * @return array
     * @throws \Exception
     */
    public static function getIpInfo($ip)
    {
        $cacheKey = self::CLIENT_IP_CACHE_KEY . "_{$ip}";
        if ($ipInfo = \Cache::get($cacheKey)) {
            $ipInfo = json_decode($ipInfo);
            return ApiResponse::success($ipInfo);
        } else {
            // $ip = '39.109.5.44';
            if (is_production()) {
                $result = self::getIpInfoIp($ip);
                if ($result['status'] != 200) {
                    $result = self::getIpIp($ip);
                }
            } else {
                $result = self::getAliIp($ip);
            }
            if ($result['status'] == 200) {
                \Cache::set($cacheKey, json_encode($result['content']), 24 * 60);
            }
            return $result;
        }
    }

    public function getDefaultCurrency()
    {
        if (request()->input('ci', '')) {
            CLogger::getLogger('ip', 'ci')->info('服务端渲染IP' . request()->input('ci'));
        }
        // 获取用户ip国家
        $ipResult = self::getIpInfo(getIP());
        CLogger::getLogger('ip', 'debug')->info(getIP() . json_encode($ipResult));
        $country = 'US';
        if ($ipResult['status'] == 200) {
            $country = $ipResult['content']->country_id ?? $country;
        }
        $country_code = $this->country[$country] ?? 'USD';
        return $country_code;
    }

    public static function getCurrencySymbol($currency_code)
    {
        $currency = CurrencyRepository::getByCurrencyCode($currency_code);
        return $currency->symbol ?? '';
    }

    public static function getAliIp($ip)
    {
        try {
            // 阿里IP查询，国外查询太慢，替换
            $host = "https://api01.aliyun.venuscn.com";
            $path = "/ip";
            $method = "GET";
            $appcode = env('IP_APP_CODE');
            $headers = [
                "Authorization" => "APPCODE " . $appcode
            ];
            // $ip = '61.244.148.166';
            $querys = "ip={$ip}";
            $bodys = "";
            $url = $host . $path . "?" . $querys;
            $client = new \GuzzleHttp\Client();
            $res = $client->request($method, $url, ['headers' => $headers, 'timeout' => 1.5]);
            $ipInfo = \GuzzleHttp\json_decode(trim($res->getBody()->getContents()));
            if ($ipInfo->ret === 200) {
                return ApiResponse::success($ipInfo->data);
            }
            return ApiResponse::failure(g_API_STATUS, 'request error');
        } catch (\Exception $exception) {
            return ApiResponse::failure(g_API_STATUS, $exception->getMessage() . 'request error');
        }
    }

    public static function getIpIp($ip)
    {
        try {
            // IPIP查询
            $host = "http://ipapi.ipip.net";
            $path = "/find";
            $method = "GET";
            $token = env('IPIP_NET_TOKEN', '');
            $headers = [
                "Token" => $token
            ];
            $querys = "addr={$ip}";
            $url = $host . $path . "?" . $querys;
            $client = new \GuzzleHttp\Client();
            $res = $client->request($method, $url, ['headers' => $headers, 'timeout' => 1.5]);
            $ipInfo = \GuzzleHttp\json_decode(trim($res->getBody()->getContents()));
            if ($ipInfo->ret === 'ok') {
                $result = [
                    'country_id' => $ipInfo->data[11]
                ];
                return ApiResponse::success($result);
            }
            return ApiResponse::failure(g_API_STATUS, 'request error');
        } catch (\Exception $exception) {
            return ApiResponse::failure(g_API_STATUS, $exception->getMessage() . 'request error');
        }
    }

    public static function getIpInfoIp($ip)
    {
        try {
            // ipinfo查询
            $host = "https://ipinfo.io";
            $path = "/{$ip}";
            $method = "GET";
            $token = env('IP_INFO_TOKEN', '');
            $headers = [
                "Authorization" => "Bearer " . $token
            ];
            $url = $host . $path;
            $client = new \GuzzleHttp\Client();
            $res = $client->request($method, $url, ['headers' => $headers, 'timeout' => 1.5]);
            $ipInfo = \GuzzleHttp\json_decode(trim($res->getBody()->getContents()));
            // dd($ipInfo);
            if (!isset($ipInfo->bogon) || !$ipInfo->bogon) {
                $result = [
                    'country_id' => $ipInfo->country
                ];
                return ApiResponse::success($result);
            }
            return ApiResponse::failure(g_API_STATUS, 'request error');
        } catch (\Exception $exception) {
            return ApiResponse::failure(g_API_STATUS, $exception->getMessage() . 'request error');
        }
    }
}
