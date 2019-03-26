<?php

/**
 * Created by patpat.
 * User: Bruce.He
 * Date: 16/4/14
 * Time: 上午1:06
 */

namespace App\Modules\Users\Repositories\Services\Auth;

use App\Composers\WebService\Services\UserService;
use App\User;
use Illuminate\Support\Facades\DB;
use Mockery\Exception;

class AuthSignUp
{
    const EMAIL_MAX_LENGTH = 320;
    const FIRST_NAME_MAX_LENGTH = 250;
    const LAST_NAME_MAX_LENGTH = 250;
    const PASSWORD_MAX_LENGTH = 18;
    const PASSWORD_MIN_LENGTH = 6;

    /**
     * Check email has been registerd
     *
     * @param $email
     * @return bool
     */
    public static function checkEmailRegistered($email)
    {
        $count = User::useWritePdo()
            ->where('email', $email)
            ->where('status', 1)
            ->count('id');
        if ($count > 0) return true;
        return false;
    }


    /**
     * Register user info insert to customers table
     * @param $email
     * @param $password
     * @param $first_name
     * @param $last_name
     * @param string $gender
     * @param string $avatar
     * @param null $regtype
     * @param int $thirdpartid
     * @return int
     */
    public static function insert($email, $password, $gender = 'N', $avatar = '', $regtype = null, $thirdpartid = 0)
    {
        if (!isset($email)) $email = '';
        if (!isset($password)) $password = '';
        $password = bcrypt($password);
        $register_time = date('y-m-d h:i:s', time());
        $name = request()->input('name', '');
        $info = array(
            "cucoe_id" => self::getInviteCode(),
            "email" => $email,
            'fullname' => $name,
            "password" => $password,
            "created_at" => $register_time,
            "last_login_datetime" => $register_time,
            "registered" => 1
        );

        //查询表中是否有该邮箱的记录
        $email_user = User::useWritePdo()
            ->select(
                'id as user_id', 'registered'
            )
            ->where('email', $email)
            ->orderBy('id', 'desc')
            ->first();

        $is_new_email = true;
        if (isset($email_user->registered)) {
            //存在邮箱用户记录
            if ($email_user->registered == 1) {
                //邮箱已经注册，直接返回
                self::getClientAuthUserInfo($email_user->user_id);
                return $email_user->user_id;
            } else {
                $is_new_email = false;
                $info['registered'] = 1;
            }
        }
        try {
            DB::beginTransaction();
            if ($is_new_email) {
                $user_id = User::create($info)->id;
            } else {
                $user_id = $email_user->user_id;
                User::where('id', $user_id)->update($info);
            }
            $created_user = User::find($user_id);
            $created_user->password = $password;
            $created_user->save();
            //用户注册时，更新
            // self::getClientAuthUserInfo($user_id);
            DB::commit();
            return $user_id;
        } catch (Exception $e) {
            DB::rollBack();
            return -1;
        }
    }

    public static function getClientAuthUserInfo($user_id)
    {
        if (AuthUtil::hasUserInfoCache($user_id)) {
            return $userInfo = AuthUtil::getUserInfoCache($user_id);
        }
        $response = AuthUtil::getUserInfo($user_id);
        $user = $response['user_info'];
        $userInfo = (object)[];
        $user && $userInfo = (object)[
            'user_id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'registered' => $user->registered
        ];
        AuthUtil::cacheUserInfo($userInfo, $user_id);
    }

    public static function getInviteCode()
    {
        $code = getInviteCode(6);
        $codeInfo = User::where('cucoe_id', $code)->first();
        if ($codeInfo) return self::getInviteCode();
        return $code;
    }
}