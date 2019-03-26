<?php

namespace App\Modules\Users\Services;

use App\Assistants\CLogger;
use App\Exceptions\ParamErrorException;
use App\Modules\Carts\Services\CartsService;
use App\Modules\Coupon\Repositories\CodeRepository;
use App\Modules\Coupon\Repositories\CouponRepository;
use App\Modules\Orders\Repositories\CustomerFinanceLogRepository;
use App\Modules\Orders\Repositories\CustomerInviteRankRepository;
use App\Modules\Orders\Repositories\OrdersRepository;
use App\Modules\Users\Repositories\AddressRepository;
use App\Modules\Users\Repositories\AuthRepository;
use App\Modules\Users\Repositories\CurrencyRepository;
use App\Modules\Users\Repositories\CustomerIncomeRepository;
use App\Modules\Users\Repositories\CustomerRelationshipRepository;
use App\Modules\Users\Repositories\Services\Auth\AuthSignUp;
use App\Modules\Users\Repositories\Services\Auth\AuthUtil;
use App\Modules\Users\Repositories\Services\Points\Points;
use App\Modules\Users\Repositories\UserCardsRepository;
use App\Modules\Users\Repositories\UserRepository;
use App\Services\ApiResponse;
use App\Traits\ActivityInviteTrait;
use App\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UsersService
{
    use ActivityInviteTrait;

    protected static $userRepository;

    public static function pwresetValidator($request)
    {
        $token      = $request->input('token', '');
        $password   = $request->input('password', '');
        $repassword = $request->input('repassword', '');
        if ( !$token) return ApiResponse::failure(g_API_ERROR, 'Token can not be null');
        if ( !$password) return ApiResponse::failure(g_API_ERROR, 'Please provide password');
        if ( !$repassword) return ApiResponse::failure(g_API_ERROR, 'Please provide confirm password ');
        if ($password != $repassword) return ApiResponse::failure(g_API_ERROR, 'Password is different from confirmation password
');
        if (strlen($password) > AuthSignUp::PASSWORD_MAX_LENGTH || strlen($password) < AuthSignUp::PASSWORD_MIN_LENGTH) {
            return ApiResponse::failure(g_API_ERROR, 'Password length at 6 - 18!');
        }
        $tokenInfo = UserRepository::getPwresetTokenByToken($token);
        if ( !$tokenInfo) return ApiResponse::failure(g_API_ERROR, 'token dose not exists');
        if ($tokenInfo->created_at <= date('Y-m-d H:i:s', strtotime('-30 minute'))) {
            return ApiResponse::failure(g_API_ERROR, 'please log in');
        }
        return false;
    }

    public static function getUserId($token = '')
    {
        $user_id = get_user_id();
        $token   = $token ? $token : request()->input('token');
        return $user_id ? $user_id : (AuthRepository::getUserId($token) ? AuthRepository::getUserId($token)->user_id : 0);
    }

    /**
     * 游客注册
     * @return array
     */
    public static function generateGuestUser()
    {
        $id    = User::create(
            ['registered' => 0]
        )->id;
        $token = AuthUtil::generateUserToken($id);
        return array("user_id" => $id, "token" => $token);
    }

    public static function signUpWithEmail($email, $password)
    {
        $email       = trim($email);
        $checkResult = self::checkEmailSignUpInputs($email, $password);
        if ( !isset($checkResult)) {
            try {
                DB::beginTransaction();
                $user_id = AuthSignUp::insert($email, $password);
                if ($user_id < 0) {
                    return ApiResponse::failure(g_STATUSCODE_AUTH_REGISTERERROR, 'Register failed');
                } else {
//                $user = AuthUtil::checkUserInfo($email, md5($password));
                    //触发注册获取积分事件
                    // self::addUserPoints($user_id);
                    //触发新人礼包优惠券
                    self::addNewUserCoupons($user_id);
                    // 用户关系生成
                    if (request()->input('invite_code', '')) {
                        self::userRelationCreate(request()->input('invite_code'), $user_id);
                    }
                    if (ActivityInviteTrait::checkActivityStatus()) {
                        // 活动期间新用户注册返200塔卡余额
                        self::addActivityAward($user_id);
                    }
                    $guestToken = request()->input('token', '');
                    if ($guestToken) {
                        $guestUserId = UsersService::getUserId();
                        if ($guestUserId && $user_id != $guestUserId) {
                            CartsService::transferCarts($guestUserId, $user_id);
                        }
                    }
                    $token = AuthUtil::generateUserToken($user_id);
                    AuthUtil::cacheTokenInfo($user_id, $token);
                    $userInfo            = UsersService::getUserInfo($user_id);
                    $login_info['token'] = $token;
                    DB::commit();
                    return ApiResponse::success(array('user_id' => $user_id, 'token' => $token, 'email' => $email, 'last_login' => date('Y-m-d H:i:s'), 'invite_code' => $userInfo->cucoe_id, 'name' => $userInfo->fullname));
                }
            } catch (\Exception $exception) {
                DB::rollBack();
                CLogger::getLogger('register', 'users')->info($exception->getMessage());
                return ApiResponse::failure(g_API_ERROR, 'Register failed');
            }
        } else {
            return $checkResult;
        }
    }

    public static function signInWithEmail($email, $password)
    {
        $old_user_id = request()->get("old_user_id", get_user_id());
        $checkResult = self::checkEmailSignInInputs($email, $password);
        if ( !isset($checkResult)) {
            $user = AuthUtil::checkUserInfo($email, $password);
            if ( !$user) {
                return ApiResponse::failure(g_STATUSCODE_AUTH_MATCHINVALID, 'The username and password do not match');
            } else {
                $guestToken = request()->input('token', '');
                $user_id    = $user->id;
                if ($guestToken) {
                    $guestUserId = UsersService::getUserId();
                    if ($guestUserId && $user_id != $guestUserId) {
                        CartsService::transferCarts($guestUserId, $user_id);
                    }
                }
                //if guest login,transfer cart and faves to her account
                $token = AuthUtil::generateUserToken($user_id);
                AuthUtil::cacheTokenInfo($user_id, $token);
                $login_info = AuthUtil::getMyAccountLoginInfo($user_id)->toArray();
                unset($login_info['user_token']);
                $login_info['token'] = $token;
                if ( !$login_info['invite_code']) {
                    $inviteCode = AuthSignUp::getInviteCode();
                    UserRepository::update($user_id, ['cucoe_id' => $inviteCode]);
                    $login_info['invite_code'] = $inviteCode;
                }
                return ApiResponse::success($login_info);
            }
        } else {
            return $checkResult;
        }
    }

    public static function logout($token)
    {
        $prefix         = Cache::getPrefix();
        $raw_expire_key = $prefix . $token;
        Cache::delete($raw_expire_key);
        return ApiResponse::success('');
    }

    public static function makePassword($length)
    {
        return AuthUtil::make_password($length);
    }

    public static function getUserInfo($user_id)
    {
        if (empty(self::$userRepository)) {
            self::$userRepository = new UserRepository();
        }

        return self::$userRepository->getUserInfo($user_id);
    }

    public static function subIntegral($userId, $integral)
    {
        return UserRepository::subIntegral($userId, $integral);
    }

    public static function getDefaultAddress($userId)
    {
        return AddressRepository::getList($userId);
    }

    public static function getFansCount($userId)
    {
        return UserRepository::getFansCount($userId);
    }

    public static function getUserInfoByIds($userIds)
    {
        return UserRepository::getUserInfoByIds($userIds);
    }

    public static function getFansList($userId)
    {
        return UserRepository::getFansList($userId);
    }

    public static function getUsersFansCount($userIds)
    {
        return UserRepository::getUsersFansCount($userIds);
    }

    public static function getUsersIncomeSum($userIds)
    {
        return UserRepository::getUsersIncomeSum($userIds);
    }

    public static function userEditValidator($request)
    {
        $name     = $request->input('name', '');
        $birthday = $request->input('birth', '');
        // $email = $request->input('email', '');
        $gender = $request->input('gender', '');
        // $phone = $request->input('phone', '');
        if ( !$name) return ApiResponse::failure(g_API_ERROR, 'User name can not be null');
        if ( !$birthday) return ApiResponse::failure(g_API_ERROR, 'Birth can not be null');
        // if(! $email) return ApiResponse::failure(g_API_ERROR, 'Email can not be null');
        // if(! $phone) return ApiResponse::failure(g_API_ERROR, 'Telephone can not be null');
        if ( !in_array(strtolower($gender), ['woman', 'man', 'secret'])) return ApiResponse::failure(g_API_ERROR, 'Gender name can not be null');
        return false;
    }


    /**
     * @param $userId
     * @param $request
     * @throws ParamErrorException
     * @throws \Exception
     */
    public static function update($userId, $request)
    {
        $userInfo = [
            'fullname' => $request->input('name'),
            'email' => $request->input('email'),
            'birth' => $request->input('birth'),
            'gender' => $request->input('gender'),
//            'phone'    => $request->input('phone')
        ];
        try {
            DB::beginTransaction();
            if ($request->filled('invite_code')) {
                if (!UsersService::findByInvIiteCode($request->input('invite_code'))) {
                    throw new ParamErrorException('invitation code error');
                }
                self::dealRelation($request->invite_code, false);
            }
            UserRepository::update($userId, $userInfo);
            DB::commit();
        } catch (ParamErrorException $e) {
            throw new ParamErrorException($e->getMessage());
        } catch (\Exception $exception) {
            DB::rollBack();
            ding("用户信息修改失败:" . $exception->getMessage());
            throw new \Exception('修改失败');
        }
    }

    public static function setPassword($tokenInfo, $password)
    {
        try {
            DB::beginTransaction();
            UserRepository::resetPassword($tokenInfo->user_id, ['password' => bcrypt($password)]);
            UserRepository::updatePwresetToken($tokenInfo->id, ['status' => 2]);
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            CLogger::getLogger('pwreset', 'users')->info($exception->getMessage());
            return false;
        }
        return true;
    }

    public static function getUserInfoByEmail($email)
    {
        return UserRepository::getUserINfoByEmail($email);
    }

    public static function getPwresetToken($userId)
    {
        $tokenInfo  = UserRepository::getPwresetToken($userId);
        $isNewToken = true;
        if ($tokenInfo) {
            if ($tokenInfo->created_at <= date('Y-m-d H:i:s', strtotime('-30 minute'))) {
                UserRepository::updatePwresetToken($tokenInfo->id, ['status' => 3]);
                $token = rand_string(32) . $userId . rand(1000, 9999);
            } else {
                $token      = $tokenInfo->token;
                $isNewToken = false;
            }
        } else {
            $token = rand_string(32) . $userId . rand(1000, 9999);
        }
        if ($isNewToken) {
            $data = [
                'user_id' => $userId,
                'token'   => $token,
            ];
            UserRepository::pwresetTokenAdd($data);
        }
        return $token;
    }

    public static function getPwresetTokenByToken($token)
    {
        return UserRepository::getPwresetTokenByToken($token);
    }

    public static function getToken($userId)
    {
        return UserRepository::getToken($userId)->token;
    }

    public static function getCards($userId)
    {
        return UserRepository::getCards($userId);
    }

    /**
     * @param $cardId
     * @return mixed
     */
    public static function getUserCardById($cardId)
    {
        return UserCardsRepository::getCardById($cardId);
    }

    private static function checkEmailSignUpInputs($email, $password)
    {
        if ( !isset($email) || empty($email)) {
            return ApiResponse::failure(g_STATUSCODE_AUTH_VALIDPARAMETERS, 'Please provide email');
        } else if ( !isset($password) || empty($password)) {
            return ApiResponse::failure(g_STATUSCODE_AUTH_VALIDPARAMETERS, 'Please provide password');
        } else if ( !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ApiResponse::failure(g_STATUSCODE_TEXT_UPPER_LIMIT_ERROR, 'E-mail format is incorrect');
        } else if (strlen($email) > AuthSignUp::EMAIL_MAX_LENGTH) {
            return ApiResponse::failure(g_STATUSCODE_TEXT_UPPER_LIMIT_ERROR, 'Email longer than upper limit');
        } else if (AuthSignUp::checkEmailRegistered($email)) {
            return ApiResponse::failure(g_STATUSCODE_AUTH_REGISTERED, 'This email has already been registered. Please sign in or register with another email address.');
        } else if (strlen($password) > AuthSignUp::PASSWORD_MAX_LENGTH || strlen($password) < AuthSignUp::PASSWORD_MIN_LENGTH) {
            return ApiResponse::failure(g_STATUSCODE_TEXT_UPPER_LIMIT_ERROR, 'Password length at 6 - 18!');
        } else {
            return null;
        }
    }

    /**
     * @function 新人礼包优惠券
     * @param $user_id
     */
    private static function addNewUserCoupons($user_id)
    {
        $now          = Carbon::now()->toDateTimeString();
        $coupon_codes = [];
        $result       = CouponRepository::getNewbeeCoupon();
        foreach ($result as $key => $item) {
            $coupon_codes[$key]['user_id']              = $user_id;
            $coupon_codes[$key]['coupon_id']            = $item->id;
            $coupon_codes[$key]['code_receive_status']  = 2;//已领取
            $coupon_codes[$key]['code_use_status']      = 1;//未使用
            $coupon_codes[$key]['code_received_at']     = $now;
            $coupon_codes[$key]['code_used_start_date'] = $now;
            if ($item->use_type == 1) { // 固定时长
                $coupon_codes[$key]['code_used_end_date'] = Carbon::now()->addDays($item->use_days);
            } elseif ($item->use_type == 2) { //固定截止时间
                $coupon_codes[$key]['code_used_end_date'] = $item->coupon_use_enddate;
            }
            $coupon_codes[$key]['created_at'] = $now;
        }
        if ($coupon_codes) {
            CodeRepository::batchCreate($coupon_codes);
        }
    }

    private static function userRelationCreate($inviteCode, $userId)
    {
        $inviteUserInfo = User::where('cucoe_id', $inviteCode)->first();
        if ($inviteUserInfo) {
            //增加邀请金
            if (self::checkCondition()) {
                CustomerIncomeRepository::createInviteRecord($userId, $inviteUserInfo->id);
                self::addMarquee($inviteUserInfo, self::$incFansStr);
                CustomerInviteRankRepository::firstOrCreate($inviteUserInfo->id);
            }
            self::confirmRelation($inviteUserInfo, $userId);
            return CustomerRelationshipRepository::create(['user_id' => $userId, 'parent_id' => $inviteUserInfo->id])->id;
        }
        return true;
    }

    private static function addActivityAward($userId)
    {
        $currency = CurrencyRepository::getByCurrencyCode('BDT');
        $balance  = bcdiv(200, $currency->rate, 2);
        // 添加余额
        UserRepository::addAmountMoney($userId, $balance);
        // 添加收入收入流水记录
        $balanceLog = [
            'turnover_id'  => client_finance_sn($userId, 5),
            'amount'       => $balance,
            'user_id'      => $userId,
            'operate_type' => 5,
            'remark'       => '12月活动奖励'
        ];
        CustomerFinanceLogRepository::addLog($balanceLog);
    }

    /**
     * Check email sign in input parameters is valid
     *
     * @param $email
     * @param $password
     * @return mixed|null
     */
    private static function checkEmailSignInInputs($email, $password)
    {
        if ( !isset($email) || empty($email)) {
            return ApiResponse::failure(g_STATUSCODE_AUTH_VALIDPARAMETERS, 'Please provide email');
        } else if ( !isset($password) || empty($password)) {
            return ApiResponse::failure(g_STATUSCODE_AUTH_VALIDPARAMETERS, 'Please provide password');
        } else if ( !AuthSignUp::checkEmailRegistered($email)) {
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

    public static function firstOrcreate($option, $extraOption = [])
    {
        return UserRepository::firstOrcreate($option, $extraOption);
    }


    public static function updateOrCreate($option, $extraOption)
    {
        return UserRepository::updateOrCreate($option, $extraOption);
    }

    /**
     * 创建响应数据
     * @param $user_id
     * @return mixed
     */
    public static function buildResponseData($user_id)
    {
        $token = AuthUtil::generateUserToken($user_id);
        AuthUtil::cacheTokenInfo($user_id, $token);
        $login_info = AuthUtil::getMyAccountLoginInfo($user_id)->toArray();
        unset($login_info['user_token']);
        $login_info['token'] = $token;
        if ( !$login_info['invite_code']) {
            $inviteCode = AuthSignUp::getInviteCode();
            UserRepository::update($user_id, ['cucoe_id' => $inviteCode]);
            $login_info['invite_code'] = $inviteCode;
        }
        $login_info['registered'] = 'yes';
        $login_info['fullname'] = trim($login_info['name']);
        $login_info['name'] = trim($login_info['name']);
        return $login_info;
    }

    /**
     * 注册完成操作
     * @param $user_id
     */
    public static function eventSignUp($user_id)
    {
        self::addNewUserCoupons($user_id);
        // 用户关系生成
//        if (request()->input('invite_code', '')) {
//            self::userRelationCreate(request()->input('invite_code'), $user_id);
//        }
        if (ActivityInviteTrait::checkActivityStatus()) {
            // 活动期间新用户注册返200塔卡余额
            self::addActivityAward($user_id);
        }
        User::where('id', $user_id)->update(["registered" => 1]);
        $guestToken = request()->input('token', '');
        if ($guestToken) {
            $guestUserId = UsersService::getUserId();
            if ($guestUserId && $user_id != $guestUserId) {
                CartsService::transferCarts($guestUserId, $user_id);
            }
        }
    }

    public static function cartsSync($user_id)
    {
        $guestToken = request()->input('token', '');
        if ($guestToken) {
            $guestUserId = UsersService::getUserId();
            if ($guestUserId && $user_id != $guestUserId) {
                CartsService::transferCarts($guestUserId, $user_id);
            }
        }
    }


    public static function findByPhone($calling_code, $mobile)
    {

        return User::where([['calling_code', '=', $calling_code], ['phone', '=', $mobile]])->first();
    }

    /**
     *
     * @param $calling_code
     * @param $mobile
     * @throws ParamErrorException
     */
    public static function checkRegisterStatus($calling_code, $mobile)
    {
        if (self::findByPhone($calling_code, $mobile)->registered == 1) {
            throw new ParamErrorException('phone already exists');
        };
    }

    public static function findByInvIiteCode($inviteCode)
    {
        return User::where('cucoe_id', $inviteCode)->first();
    }


    /**
     * @param $inviteCode
     * @param bool $alone
     * @throws ParamErrorException
     */
    public static function dealRelation($inviteCode, $alone = true)
    {
        $userId = self::getUserId();

        if (CustomerRelationshipRepository::getParentByUserId($userId)) {
            if ($alone) {
                throw new ParamErrorException('不能重复填写');
            } else {
                return;
            }

        }
        self::userRelationCreate($inviteCode, $userId);
    }

    /**
     * 确认关系成立
     * @param $parentUserInfo
     * @param $userId
     */
    public static function confirmRelation($parentUserInfo, $userId)
    {
        if (self::checkActivityStatus() && OrdersRepository::getOrderPayCount($userId)) {
            $updateItems = CustomerIncomeRepository::updateInviteRecord($userId, $parentUserInfo->id);//修正狀態
            if ($updateItems) {
                UserRepository::addIncome($parentUserInfo->id, config('thirdparty.inviteMoney', 10));//到账
                self::addMarquee($parentUserInfo, self::$gainStr);
                $financeLog = [
                    'turnover_id'  => client_finance_sn($parentUserInfo->id, 6),
                    'amount'       => config('thirdparty.inviteMoney', 10),
                    'user_id'      => $parentUserInfo->id,
                    'from_user_id' => $userId,
                    'operate_type' => 6,
                    'remark'       => '12月活动，粉丝支付'
                ];
                CustomerFinanceLogRepository::addLog($financeLog);
            }
        }
    }

    public static function getParentInviteCode($userId)
    {
        $parentUserInfo = CustomerRelationshipRepository::getParentByUserId($userId);
        if ( !$parentUserInfo) {
            return '';
        } else {
            return self::getUserInfo($parentUserInfo->parent_id)->cucoe_id;
        }
    }
}