<?php

namespace App\Modules\Users\Services;

use App\Assistants\CLogger;
use App\Exceptions\ForbiddenException;
use App\Exceptions\JuTongDaException;
use App\Exceptions\ParamErrorException;
use App\Exceptions\YunPianException;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use \Yunpian\Sdk\YunpianClient;


class SmsServices
{
    public static function whiteList()
    {
        return explode(',', config('thirdparty.smsWhiteList'));
    }

    protected function name()
    {
        return config('thirdparty.smsName');
    }

    protected function password()
    {
        return config('thirdparty.smsPassword');
    }

    protected function yunPianApiKey()
    {
        return config('thirdparty.yunPianApiKey');
    }

    /**
     * 允许类型
     * @return array
     */
    public function typeArray()
    {
        return [
            'login',
            'change_password',
            'cat_order',
        ];
    }


    /**
     * @param $mobile
     * @param $stateNo
     * @param string $type
     * @throws ForbiddenException
     * @throws ParamErrorException
     * @throws YunPianException
     * @throws \Exception
     */
    public function send($stateNo, $mobile, $type = 'login')//
    {
        $code = $this->cacheCode($stateNo . $mobile, $type);
        if (!is_production()) {
            throw new ParamErrorException($code);
        }
        if (!in_array($type, $this->typeArray())) {
            throw new \Exception('type error');
        }
        if ($this->getForbiddenCache($stateNo, $mobile, $type)) {
            throw new ForbiddenException('SMS too frequently, Please try again 30 minutes later');
        }
        try {
            if ($stateNo == 86) {
                $this->yunPian($stateNo, $mobile, $code);
            } else {
                $this->juTongDa($stateNo, $mobile, $code);
            }
            $this->numberCache($stateNo, $mobile, $type);
        } catch (JuTongDaException $exception) {
            try {
                $this->yunPian($stateNo, $mobile, $code);
                $this->numberCache($stateNo, $mobile, $type);
            } catch (YunPianException $yunPianException) {
                throw  new YunPianException('云片发送失败');
            } catch (\Exception $e) {
                throw  new \Exception('云片发送失败');
            }
        } catch (\Exception $e) {
            throw  new \Exception('短信发送失败');
        }
    }

    /**
     * 生成缓存key
     * @param $mobile
     * @param $stateNo
     * @param $type
     * @return string
     */
    public function generateNumberCacheKey($stateNo, $mobile, $type)
    {
        return $stateNo . $mobile . $type . 'num';
    }

    /**
     * 次数缓存
     * @param $mobile
     * @param $stateNo
     * @param $type
     */
    public function numberCache($stateNo, $mobile, $type)
    {
        $key = $this->generateNumberCacheKey($stateNo, $mobile, $type);
        $value = $this->getNumberCache($stateNo, $mobile, $type);
        if ($value) {
            Cache::increment($key);
        } else {
            Cache::put($key, 1, 10);
        }
        if ($value >= 4) {
            $this->forbiddenCache($stateNo, $mobile, $type);
        }
    }

    /**
     * 获取次数缓存
     * @param $mobile
     * @param $stateNo
     * @param $type
     * @return mixed
     */
    public function getNumberCache($stateNo, $mobile, $type)
    {
        $key = $this->generateNumberCacheKey($stateNo, $mobile, $type);
        return Cache::get($key);
    }

    /**
     * 生成缓存key
     * @param $mobile
     * @param $stateNo
     * @param $type
     * @return string
     */
    public function generateForbiddenCacheKey($stateNo, $mobile, $type)
    {
        return $stateNo . $mobile . $type . 'forbidden';
    }

    /**
     * 禁用缓存
     * @param $mobile
     * @param $stateNo
     * @param $type
     */
    public function forbiddenCache($stateNo, $mobile, $type)
    {
        $key = $this->generateForbiddenCacheKey($stateNo, $mobile, $type);
        Cache::put($key, '1', 30);
    }

    /**
     * 获取禁用缓存
     * @param $mobile
     * @param $stateNo
     * @param $type
     * @return mixed
     */
    public function getForbiddenCache($stateNo, $mobile, $type)
    {
        $key = $this->generateForbiddenCacheKey($stateNo, $mobile, $type);
        return Cache::get($key);
    }


    /**
     * @param $stateNo
     * @param $mobile
     * @param $code
     * @throws YunPianException
     */
    public function yunPian($stateNo, $mobile, $code)
    {

        //初始化client,apikey作为所有请求的默认值
        $conf = [];
        //国际版美国服务器
        if ($stateNo == 86) {
            $mobileWithCode = $mobile;
        } else {
            if (config('thirdparty.serverPosition') == 'us') {
                $conf = [
                    YunpianClient::YP_SMS_HOST => 'https://us.yunpian.com',
                    YunpianClient::YP_USER_HOST => 'https://us.yunpian.com',
                    YunpianClient::YP_SIGN_HOST => 'https://us.yunpian.com',
                    YunpianClient::YP_TPL_HOST => 'https://us.yunpian.com',

                ];
            }
            $mobileWithCode = '+' . $stateNo . $mobile;
        }
        $start = microtime(true);
        $clnt = YunpianClient::create($this->yunPianApiKey(), $conf);
        $param = [
            YunpianClient::MOBILE => $mobileWithCode,
            YunpianClient::TEXT   => '【WAIWAIMALL】Verification Code: ' . $code . ', please log in within 5 mins. '
        ];
        $r = $clnt->sms()->single_send($param);
        $end = microtime(true);
        CLogger::getLogger('send', 'yunpian')->info('云片请求时间:' . ($end - $start) . 's');
        if (!$r->isSucc()) {

            ding('云片发送短信错误' . $r->detail());
            if ($r->exception()) {
                throw new YunPianException($r->exception()->getMessage());
            } else {
                throw new YunPianException($r->detail());
            }

        }
    }


    /**
     * @param $stateNo
     * @param $mobile
     * @param $code
     * @throws JuTongDaException
     * @throws \Exception
     */
    public function juTongDa($stateNo, $mobile, $code)
    {
        $url = 'http://114.55.94.101:8081/JTD_International_SMS_Interface/SendSms.do';
        $client = new Client();
        $res = $client->post($url, [
            'form_params' => [
                'username' => $this->name(),
                'sign'     => $this->getSign(),
                'mobile'   => $stateNo . $mobile,
                'stateNo'  => $stateNo,
                'content'  => '【WAIWAIMALL】Verification Code: ' . $code . ', please log in within 5 mins. '
            ]
        ]);
        $resBody = json_decode($res->getBody());
        if ($resBody->code != 0) {
            if (in_array($resBody->code, [9, 51, 999])) {
                throw new JuTongDaException('发送错误');
            } else {
                ding('聚达通短信发送失败,错误代码' . $resBody->code);
                throw new \Exception('短信发送失败');
            }
        }
    }

    /**
     * 生成签名;
     * @return string
     */
    public function getSign()
    {
        return md5($this->password() . $this->name() . $this->password());
    }

    /**
     * 缓存验证码
     * @param $mobileWithCode
     * @param $type
     * @return int
     */
    public function cacheCode($mobileWithCode, $type)
    {
        $key = $this->generateCacheKey($mobileWithCode, $type);

        if (in_array($mobileWithCode, self::whiteList())) {
            $code = '123456';
        } else {
            $code = $this->generateCode();
        }
        Cache::put($key, $code, 5);
        return $code;
    }

    /**
     * @function 生成缓存key
     * @param $mobileWithCode
     * @param $type
     * @return string
     */
    public function generateCacheKey($mobileWithCode, $type)
    {
        return 'mobile-' . $mobileWithCode . '-' . $type;
    }

    /**
     * 生成验证码
     * @return int
     */
    public function generateCode()
    {
        return mt_rand(100000, 999999);
    }

    /**
     * 验证验证码
     * @param $mobileWithCode
     * @param $value
     * @param $type
     * @return bool
     */
    public function checkCode($mobileWithCode, $value, $type = 'login')
    {
        $key = $this->generateCacheKey($mobileWithCode, $type);
        $cachedValue = Cache::get($key);
        if ($cachedValue == $value) {
            Cache::forget($key);
            return true;
        } else {
            return false;
        }
    }
}