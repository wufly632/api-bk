<?php

namespace App\Modules\Users\Repositories;

use App\Models\User\Token;

class AuthRepository
{

    private static function checkEmailSignInInputs($email, $password)
    {
        if (!isset($email) || empty($email)) {
            return ApiResponse::failure(g_STATUSCODE_AUTH_VALIDPARAMETERS, 'Please provide email');
        } else if (!isset($password) || empty($password)) {
            return ApiResponse::failure(g_STATUSCODE_AUTH_VALIDPARAMETERS, 'Please provide password');
        } else if (!AuthSignUp::checkEmailRegistered($email)) {
            return ApiResponse::failure(g_STATUSCODE_AUTH_REGISTERED, 'The email has not been registered');
        } else {
            return null;
        }
    }

    /**触发获取积分事件
     * @param $user_id
     * @param string $type
     */
    public static function addUserPoints($user_id, $type = Points::REGISTER)
    {
        //触发注册获取积分事件
        Points::userRegister($user_id, $type);
    }

    /**
     * 游客注册
     * @return array
     */
    public static function generateGuestUser()
    {
        $now = Carbon::now();
        $now = $now->toDateTimeString();
        $id = DB::table('users')->insertGetId(
            ['registered' => 0, 'created_at' => $now]
        );
        $token = AuthUtil::generateUserToken($id);
        return array("user_id" => $id, "token" => $token);
    }


    public static function resetPassword($verify_code, $password, $email)
    {
        if (!AuthForgotPassword::checkVerifyCode($email, $verify_code)) {
            return ['status' => false, 'code' => g_STATUSCODE_AUTH_FORGOTPASSWORDERROE, 'message' => trans('The verification code is not valid.')];
        }
        if (strlen($password) > g_PASSWORD_MAX_LENGTH || strlen($password) < g_PASSWORD_MIN_LENGTH) {
            return ['status' => false, 'code' => g_STATUSCODE_TEXT_UPPER_LIMIT_ERROR, 'message' => 'Password should be 6-18 length!'];
        }
        if (AuthForgotPassword::resetPassword($password, $email)) {
            return ['status' => true, 'message' => 'Reset password successfully'];
        } else {
            return ['status' => false, 'message' => 'Reset password failure'];
        }
    }

    /**
     * 根据token获取用户ID
     * @param $token
     * @return mixed
     */
    public static function getUserId($token)
    {
        return Token::where('token', $token)->first();
    }

    private static function userRelationCreate($inviteCode, $userId)
    {
        $inviteUserInfo = DB::table('users')->where('cucoe_id', $inviteCode)->first();
        if ($inviteUserInfo) {
            //增加邀请金
            if (self::checkCondition()) {
                CustomerIncomeRepository::createInviteRecord($userId, $inviteUserInfo->id);
                self::addMarquee($inviteUserInfo, self::$incFansStr);
                CustomerInviteRankRepository::firstOrCreate($inviteUserInfo->id);
            }
            return DB::table('customer_relationship')->insertGetId(['user_id' => $userId, 'parent_id' => $inviteUserInfo->id, 'created_at' => date('Y-m-d H:i:s')]);
        }
        return true;
    }

    /**
     * @function 新人礼包优惠券
     * @param $user_id
     */
    private static function addNewUserCoupons($user_id)
    {
        $now = Carbon::now()->toDateTimeString();
        $coupon_codes = [];
        $result = DB::table('coupon')->where('coupon_purpose', 3)
            ->where(function ($query) use ($now) {
                $query->where('use_type', 1)
                    ->orWhere([['coupon_grant_startdate', '<=', $now], ['coupon_grant_enddate', '>=', $now]]);
            })->get();
        foreach ($result as $key => $item) {
            $coupon_codes[$key]['user_id'] = $user_id;
            $coupon_codes[$key]['coupon_id'] = $item->id;
            $coupon_codes[$key]['code_receive_status'] = 2;//已领取
            $coupon_codes[$key]['code_use_status'] = 1;//未使用
            $coupon_codes[$key]['code_received_at'] = $now;
            $coupon_codes[$key]['code_used_start_date'] = $now;
            if ($item->use_type == 1) { // 固定时长
                $coupon_codes[$key]['code_used_end_date'] = Carbon::now()->addDays($item->use_days);
            } elseif ($item->use_type == 2) { //固定截止时间
                $coupon_codes[$key]['code_used_end_date'] = $item->coupon_use_enddate;
            }
            $coupon_codes[$key]['created_at'] = $now;
        }
        if ($coupon_codes) {
            DB::table('coupon_code')->insert($coupon_codes);
        }
    }

    private static function addActivityAward($userId)
    {
        $currency = DB::table('currency')->where('currency_code', 'BDT')->first();
        $balance = round(200 / $currency->rate, 2);
        // 添加余额
        UserRepository::addAmountMoney($userId, $balance);
        // 添加收入收入流水记录
        $balanceLog = [
            'turnover_id' => client_finance_sn($userId, 5),
            'amount' => $balance,
            'user_id' => $userId,
            'created_at' => Carbon::now(),
            'operate_type' => 5,
            'remark' => '12月活动奖励'
        ];
        CustomerFinanceLogRepository::addLog($balanceLog);
    }
}