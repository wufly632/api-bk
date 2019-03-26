<?php


namespace App\Modules\Users\Services;


use App\Exceptions\ParamErrorException;
use Illuminate\Support\Facades\Cache;

class AuthService
{
    public static $calling_code = '';
    public static $mobile = '';

    /**
     * 生成登陆随机数key
     * @return string
     */
    public static function generateLoginRandomKey()
    {
        return self::$calling_code . self::$mobile;
    }

    /**
     * 生成登陆随机数value
     * @return string
     */
    public static function generateLoginRandomValue()
    {
        return md5(self::$calling_code . self::$mobile . time() . str_random(32));
    }

    /**
     * 生成修改手机号随机数key
     * @return string
     */
    public static function generateChangePhoneRandomKey()
    {
        return self::$calling_code . self::$mobile . 'changePhone';
    }

    /**
     * 生成修改手机号随机数value
     * @return string
     */
    public static function generateChangePhoneRandomValue()
    {
        return md5(self::$calling_code . self::$mobile . 'changePhone' . time() . str_random(32));
    }

    /**
     * 缓存登陆随机数
     * @param $calling_code
     * @param $mobile
     * @return string
     */
    public static function cacheKey($calling_code, $mobile)
    {
        self::setKey($calling_code, $mobile);
        $key = self::generateLoginRandomKey();
        $value = self::generateLoginRandomValue();
        Cache::put($key, $value, 5);
        return $value;
    }

    /**
     * 设置对象属性
     * @param $calling_code
     * @param $mobile
     */
    public static function setKey($calling_code, $mobile)
    {
        self::$calling_code = $calling_code;
        self::$mobile = $mobile;
    }

    /**
     *
     * 缓存登陆随机数
     * @param $calling_code
     * @param $mobile
     * @param $randomKey
     * @throws ParamErrorException
     */
    public static function checkKey($calling_code, $mobile, $randomKey)
    {
        self::setKey($calling_code, $mobile);
        $cachedValue = Cache::get(self::generateLoginRandomKey());
        if ($cachedValue != $randomKey) {
            throw  new ParamErrorException('auth failed');
        }
        Cache::forget(self::generateLoginRandomKey());
    }

    /**
     * 缓存修改手机号随机数
     * @param $calling_code
     * @param $mobile
     * @return string
     */
    public static function cacheChangePhoneKey($calling_code, $mobile)
    {
        self::setKey($calling_code, $mobile);
        $key = self::generateChangePhoneRandomKey();
        $value = self::generateChangePhoneRandomValue();
        Cache::put($key, $value, 30);
        return $value;
    }

    /**
     * 验证修改手机号随机数
     * @param $calling_code
     * @param $mobile
     * @param $randomKey
     * @throws ParamErrorException
     */
    public static function checkChangePhoneKey($calling_code, $mobile, $randomKey)
    {
        self::setKey($calling_code, $mobile);
        $cachedValue = Cache::get(self::generateChangePhoneRandomKey());
        if (!$cachedValue || $cachedValue != $randomKey) {
            throw  new ParamErrorException('auth failed');
        }
        Cache::forget(self::generateChangePhoneRandomKey());
    }

    /**
     * 获取修改手机号随机数key
     * @param $calling_code
     * @param $mobile
     * @return string
     */
    public static function getChangePhoneKey($calling_code, $mobile)
    {
        self::setKey($calling_code, $mobile);
        return Cache::get(self::generateChangePhoneRandomKey()) ?: '';
    }
}