<?php
/**
 * Created by patpat.
 * User: Bruce.He
 * Date: 16/4/20
 * Time: 下午9:34
 */

namespace App\Modules\Users\Repositories\Services\Auth;

use App\Models\User\Token;
use App\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class AuthUtil
{
    public static function make_password($length = 8)
    {
        $chars = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h',
            'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's',
            't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D',
            'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
            'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
            '0', '1', '2', '3', '4', '5', '6', '7', '8', '9');

        $keys = array_rand($chars, $length);
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[$keys[$i]];
        }
        return $password;
    }

    /**
     * generate user token
     * @param $user_id
     * @return string
     */
    public static function generateUserToken($user_id)
    {
        $platform = request()->get('platform', 'pc');
        $origin_token = Token::where('user_id', $user_id)->where('platform', $platform)->first();
        if ($origin_token) {
            return $origin_token->token;
        }
        $token = self::make_password(32);
        $ip_address = getIP();
        $ip_address = trim($ip_address);
        $insert = array(
            "user_id" => $user_id,
            "token" => $token . $user_id,
            "ip_address" => $ip_address,
            "created_at" => Carbon::now(),
            "updated_at" => Carbon::now(),
            'platform' => $platform
        );
        Token::create($insert);
        // return user_token
        return $token . $user_id;
    }

    public static function cacheTokenInfo($user_id, $token, $expire_time = g_CACHE_REGISTERED_TOKEN_EXPIRE_TIME, $is_register = 1)
    {
        $platform = request()->input('platform', 'pc');
        if (in_array(strtolower($platform), ['ios', 'android'])) {
            $expire_time = g_CACHE_UNREGISTERED_TOKEN_EXPIRE_TIME;
        } elseif (in_array(strtolower($platform), ['pc', 'h5'])) {
            $expire_time = 1440;
        }
        $user_id_key = $token;
        $user_info = self::getMyAccountLoginInfo($user_id);
        $data = json_encode($user_info);
        Cache::put($user_id_key, $data, $expire_time);
    }

    public static function hasUserInfoCache($user_id)
    {
        return Cache::has(self::userInfoCacheKey($user_id));
    }

    public static function userInfoCacheKey($user_id)
    {
        return g_CACHE_USERINFO . $user_id;
    }

    public static function getUserInfoCache($user_id)
    {
        //get user info with json format, then decode json string to array
        $userInfo = json_decode(Cache::get(self::userInfoCacheKey($user_id)));
        if (!$userInfo) {
            self::clearUserInfoCache($user_id);
        }
        return $userInfo;
    }

    public static function clearUserInfoCache($user_id)
    {
        Cache::forget(self::userInfoCacheKey($user_id));
    }

    public static function getUserInfo($user_id)
    {
        $user_info = self::getMyAccountLoginInfo($user_id);
        if ($user_info) {
            unset($user_info->user_token);
            $login_type = self::getLoginType($user_id);
            $user_info->login_type = $login_type;
        }
        // $notice_info = self::getRealtimeInfo($user_id);
        $notice_info = [];
        $returnResult = array(
            "user_info" => $user_info,
            "notice_info" => $notice_info
        );
        return $returnResult;
    }

    public static function cacheUserInfo($userInfo, $user_id = null)
    {
        $cache_key = self::userInfoCacheKey($user_id);
        //first encode userinfo to json string, then save to cache
        Cache::put($cache_key, json_encode($userInfo), g_CACHE_EXPIRETIME);
    }

    /**
     * 获取指定用户登录信息
     *
     * @param $user_id
     * @return mixed
     */
    public static function getMyAccountLoginInfo($user_id)
    {
        $data = User::useWritePdo()
            ->select(
                'id',
                'firstname AS first_name',
                'password AS user_token',
                'lastname AS last_name',
                'fullname AS name',
                'email AS email',
                'last_login_datetime AS last_login',
                'registered',
                'cucoe_id as invite_code'
            )
            ->addSelect(DB::raw("unix_timestamp(created_at) as member_since"))
            ->find($user_id);
        return $data;
    }

    public static function getLoginType($user_id)
    {
        $user = User::useWritePdo()->find($user_id);
        if (!$user) {
            return "invalid user";
        }
        if ($user->email) {
            return "email";
        }
        return "guest";
    }

    public static function getRealtimeInfo($userId)
    {
        $returnResult = array();
        $returnResult["wallet_credit"] = self::getCredits($userId);
        //todo 需要从cart模块调用
//        $returnResult["shopping_cart_record"] = self::getCartItems($userId);
        $returnResult["shopping_cart_record"] = 0;
//        $returnResult["notification"] = self::getNotificationCount($userId);
        //todo 需要从notification模块调用
        $returnResult["notification"] = 0;
        $returnResult['newlikedata'] = self::getNewLikeCount($userId);
        $returnResult["saves_count"] = self::getSavedProductsCount($userId);
        return $returnResult;
    }

    public static function getCredits($user_id)
    {
        $wallet_credit = DB::table('mb_wallet_credit')->useWritePdo()
            ->where('user_id', '=', $user_id)->first();
        if (!$wallet_credit) {
            $wallet_credit = array(
                'user_id' => $user_id,
                'credit' => '0.00',
                'currency' => "USD",
            );
        }
        return $wallet_credit;
    }

    public static function getNewLikeCount($user_id)
    {
        $newLike = DB::table('mb_user_comments_likes')->useWritePdo()->select()->where('user_id', '=', $user_id)->where('is_read', '=', '0')->get();
        $icon = DB::table("sys_customers")->useWritePdo()->select("customers_avatar")->where("id", $user_id)->first();
        $count = 0;
        if (count($newLike) > 0) {
            $count = count($newLike);
        }
        if (!$icon) {
            $icon = "http://patpatdev.s3.amazonaws.com/logo/reviews/signinpic.jpg";
        }
        return array("newlike_count" => $count, "newlike_icon" => isset($icon->customers_avatar) ? $icon->customers_avatar : '');
    }

    public static function getSavedProductsCount($user_id)
    {
        $records = DB::table("sys_customer_saved")->useWritePdo()
            ->where("user_id", $user_id)
            ->where("type", "product")
            ->orderBy("created_at", "desc")
            ->get();
        $return_array = [];
        $product_ids = [];
        foreach ($records as $record) {
            if ($record) {
                $save_id = $record->save_id;
                $info = explode("_", $save_id);
                if (count($info) > 1) {
                    $event_id = $info[0];
                    $product_id = $info[1];
                    $event = DB::table("oms_events")->find($event_id);
                    if (!$event) {
                        continue;
                    }
                    $product_ids[] = $product_id;
                }
            }
        }
        $return_array = ProductsService::getBatchProductsByIds($product_ids);
        return count($return_array);
    }

    /**
     * Check email and password is matched
     *
     * @param $email
     * @param $md5_password
     * @return bool
     */
    public static function checkUserInfo($email, $password)
    {
        $user = User::selectRaw('id, password')
            ->where('email', $email)
            ->where('status', 1)
            ->first();
        if ($user && Hash::check($password, $user->password)) {
            return $user;
        }
        return false;
    }

    public static function getAccountByEmail($email)
    {
        return DB::table('sys_customers')->useWritePdo()->where('customers_email_address', $email)->where('customers_email_address', '<>', '')->first();
    }
}