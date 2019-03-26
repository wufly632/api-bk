<?php

namespace App\Modules\Users\Http\Controllers;

use App\Exceptions\CustomException;
use App\Exceptions\ParamErrorException;
use App\Models\Currency;
use App\Modules\Coupon\Services\CouponService;
use App\Modules\Orders\Services\CustomerFinanceLogService;
use App\Modules\Orders\Services\CustomerIntegralLogService;
use App\Modules\Orders\Services\OrdersService;
use App\Modules\Products\Services\StorageHandler;
use App\Modules\Users\Services\AuthService;
use App\Modules\Users\Services\CustomerIncomeService;
use App\Modules\Users\Services\SmsServices;
use App\Modules\Users\Services\UsersService;
use App\Services\ApiResponse;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class PersonalController extends Controller
{
    protected $smsServices;

    public function __construct(SmsServices $smsServices)
    {
        $this->smsServices = $smsServices;
    }

    public function index(Request $request)
    {
        $currency_code = isset($request['currency_code']) ? $request['currency_code'] : 'USD';
        $currency = Currency::where('currency_code', $currency_code)->first();
        if (!$currency) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'Currency dose not exists');
        }
        $result = [];
        $userId = UsersService::getUserId();
        if (!$userId) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'User not found');
        }
        $userInfo = UsersService::getUserInfo($userId);
        $result['img'] = cdnUrl($userInfo->logo);
        $result['username'] = $userInfo->fullname ?? "";
        $result['invite_code'] = $userInfo->cucoe_id ?: "";
        $result['name'] = $userInfo->fullname ?? "";
        $result['integral'] = 0;
        $result['currency_symbol'] = $currency->symbol;
        $result['card'] = CouponService::getCouponCount($userId);
        $result['money'] = round($userInfo->amount_money * $currency->rate, $currency->digit);
        $result['income'] = round($userInfo->accumulated_income * $currency->rate, $currency->digit);
        $result['wait_account'] = round(CustomerIncomeService::getWaitAccount($userId) * $currency->rate,
            $currency->digit);
        $result['funs'] = UsersService::getFansCount($userId);
        $result['orders'] = OrdersService::orderList($userId, $request)['orderData'];
        return ApiResponse::success($result);
    }

    public function coupons()
    {
        $userId = UsersService::getUserId();
        $result = CouponService::couponCodeList($userId);
        $couponData['total_page'] = $result->lastPage();
        $couponData['coupons'] = [];
        foreach ($result->items() as $item) {
            $couponTmp = [];
            $couponTmp['currency_symbol'] = Currency::getSymbolByCode($item->currency_code);
            $couponTmp['type'] = $item->coupon->rebate_type;
            $couponTmp['price'] = $item->coupon->coupon_price;
            $couponTmp['coupon'] = $item->coupon->rebate_type;
            $couponTmp['use_price'] = $item->coupon->coupon_use_price;
            $couponTmp['startdate'] = $item->code_used_start_date;
            $couponTmp['enddate'] = $item->code_used_end_date;
            if (date('Y-m-d H:i:s') < $couponTmp['startdate']) {
                $couponTmp['datestatus'] = 2;
            } else {
                if ($item->code_use_status == 2) {
                    $couponTmp['datestatus'] = 4;
                } else {
                    if ($couponTmp['enddate'] < date('Y-m-d H:i:s')) {
                        $couponTmp['datestatus'] = 3;
                    } else {
                        $couponTmp['datestatus'] = 1;
                    }
                }
            }
            $couponData['coupons'][] = $couponTmp;
        }
        return ApiResponse::success($couponData);
    }

    public function integral()
    {
        $userId = UsersService::getUserId();
        // $integrals = CustomerIntegralLogService::getList($userId);
        // $integralData['total_page'] = $integrals->lastPage();
        $integralData['total_page'] = 1;
        $integralData['personal_integral'] = 0;
        $integralData['integrals'] = [];
        // foreach ($integrals->items() as $item) {
        //     $integralTmp = [];
        //     switch ($item->operate_type) {
        //         case 1:
        //             $integralTmp['integral'] = '+' . $item->integral;
        //             $integralTmp['source_type'] = 'Shopping Reward';
        //             break;
        //         case 2:
        //             $integralTmp['integral'] = '-' . $item->integral;
        //             $integralTmp['source_type'] = 'Shopping Consumption';
        //             break;
        //         default:
        //             $integralTmp['integral'] = '';
        //             $integralTmp['source_type'] = '';
        //     }
        //     $integralTmp['created_at'] = $item->created_at;
        //     $integralData['integrals'][] = $integralTmp;
        // }
        return ApiResponse::success($integralData);
    }

    public function finance(Request $request)
    {
        $currency_code = $request->input('currency_code', 'USD');
        $currency = Currency::where('currency_code', $currency_code)->first();
        $userId = UsersService::getUserId();
        $userInfo = UsersService::getUserInfo($userId);
        $finances = CustomerFinanceLogService::getList($userId);
        $financeData['total_page'] = $finances->lastPage();
        $financeData['currency_symbol'] = $currency->symbol;
        $financeData['money'] = round($userInfo->amount_money * $currency->rate, $currency->digit);
        $financeData['finances'] = [];
        foreach ($finances->items() as $item) {
            $financeTmp = [];
            $financeTmp['currency_symbol'] = '$';
            $financeTmp['amount'] = $item->amount;
            switch ($item->operate_type) {
                case 1: // 消费
                    $financeTmp['symbol'] = '-';
                    $financeTmp['operate_type'] = 'Shopping Consumption';
                    $financeTmp['operate_description'] = "You spent $ {$item->amount} on shopping.";
                    break;
                case 2: // 消费返利
                    $financeTmp['symbol'] = '+';
                    $financeTmp['operate_type'] = 'Shopping Reward';
                    $financeTmp['operate_description'] = "You have placed an order for {$item->order_amount}.";
                    break;
                case 3: // 粉丝消费
                    $financeTmp['symbol'] = '+';
                    $financeTmp['operate_type'] = 'Reward by shopping from your followers';
                    $userName = UsersService::getUserInfo($item->from_user_id)->fullname;
                    $financeTmp['operate_description'] = "Your follower {$userName} placed an order for {$item->order_amount}.";
                    break;
                case 4: // 粉丝返利（粉丝的粉丝消费）
                    $financeTmp['symbol'] = '+';
                    $financeTmp['operate_type'] = 'Reward by shopping from your followers';
                    $userName = UsersService::getUserInfo($item->from_user_id)->fullname;
                    $financeTmp['operate_description'] = " Your follower {$userName} have got her followers‘ rewards for {$item->order_amount}.";
                    break;
                case 5:
                    $financeTmp['symbol'] = '+';
                    $financeTmp['operate_type'] = 'Registation Reward';
                    $financeTmp['operate_description'] = 'Welcome to join us';
                    break;
                case 6:
                    $financeTmp['symbol'] = '+';
                    $financeTmp['operate_type'] = 'Reward by inviting a new follower';
                    $userName = UsersService::getUserInfo($item->from_user_id)->fullname;
                    $financeTmp['operate_description'] = "{$userName} has been your follower";
                    break;
                default:
                    $financeTmp['symbol'] = '';
                    $financeTmp['operate_type'] = '';
                    $financeTmp['operate_description'] = '';
            }
            $financeTmp['created_at'] = $item->created_at;
            $financeData['finances'][] = $financeTmp;
        }
        return ApiResponse::success($financeData);
    }

    public function income(Request $request)
    {
        $currency_code = $request->input('currency_code', 'USD');
        $currency = Currency::where('currency_code', $currency_code)->first();
        $userId = UsersService::getUserId();
        $incomes = CustomerIncomeService::getList($userId, 2);
        $incomeData['total_page'] = $incomes->lastPage();
        $incomeData['incomes'] = [];
        $formUserIds = array_pluck($incomes->items(), 'from_user_id');
        $userInfos = UsersService::getUserInfoByIds($formUserIds);
        $userImages = array_pluck($userInfos, 'logo', 'id');
        $userNames = array_pluck($userInfos, 'fullname', 'id');
        foreach ($incomes->items() as $item) {
            $incomeTmp = [];
            $incomeTmp['currency_symbol'] = $currency->symbol;
            $incomeTmp['image'] = $userImages[$item->from_user_id];
            $incomeTmp['amount'] = round($item->amount * $currency->rate, $currency->digit);
            $incomeTmp['date'] = $item->account_at;
            switch ($item->type) {
                case 1: // 购物返利
                    $incomeTmp['type'] = 'Shopping Reward';
                    $incomeTmp['description'] = "You have placed an order for {$item->order_amount}";
                    break;
                case 2: // 粉丝购物
                    $incomeTmp['type'] = 'Reward by your followers‘ shopping';
                    $incomeTmp['description'] = "Your follower {$userNames[$item->from_user_id]} placed an order for {$item->order_amount}";
                    break;
                case 3: // 粉丝返利
                    $incomeTmp['type'] = 'Reward by your followers‘ rewards';
                    $incomeTmp['description'] = "Your follower {$userNames[$item->from_user_id]} have got her followers‘ rewards
for {$item->order_amount}";
                    break;
                case 4:
                    $incomeTmp['type'] = 'Reward by inviting a new follower';
                    $incomeTmp['description'] = "{$userNames[$item->from_user_id]} has been your follower";
                    break;
            }
            $incomeData['incomes'][] = $incomeTmp;
        }
        return ApiResponse::success($incomeData);
    }

    public function fans(Request $request)
    {
        $currency_code = $request->input('currency_code', 'USD');
        $currency = Currency::where('currency_code', $currency_code)->first();
        $userId = UsersService::getUserId();
        $users = UsersService::getFansList($userId);
        $userIds = array_pluck($users->items(), 'user_id');
        $userFans = UsersService::getUsersFansCount($userIds);
        $userIncomes = UsersService::getUsersIncomeSum($userIds);
        $userFans = array_pluck($userFans, 'fansm', 'parent_id');
        $userIncomes = array_pluck($userIncomes, 'income', 'from_user_id');
        // dd($userIncomes);
        $fansData['total_page'] = $users->lastPage();
        $fansData['fans'] = [];
        foreach ($users->items() as $item) {
            $fansTmp = [];
            $fansTmp['currency_symbol'] = $currency->symbol;
            $fansTmp['image'] = cdnUrl($item->user->logo);
            $fansTmp['name'] = $item->user->fullname ?: $item->user->email;
            $fansTmp['sub_fans'] = isset($userFans[$item->user->id]) ? $userFans[$item->user->id] : 0;
            $fansTmp['benefit'] = isset($userIncomes[$item->user->id]) ? round($userIncomes[$item->user->id] * $currency->rate,
                $currency->digit) : 0;
            $fansTmp['date'] = $item->created_at->toDateTimeString();
            $fansData['fans'][] = $fansTmp;
        }
        return ApiResponse::success($fansData);
    }

    public function account(Request $request)
    {
        $currency_code = $request->input('currency_code', 'USD');
        $currency = Currency::where('currency_code', $currency_code)->first();
        $userId = UsersService::getUserId();
        $accounts = CustomerIncomeService::getList($userId, 1);
        $accountData['total_page'] = $accounts->lastPage();
        $accountData['accounts'] = [];
        $formUserIds = array_pluck($accounts->items(), 'from_user_id');
        $userInfos = UsersService::getUserInfoByIds($formUserIds);
        $userImages = array_pluck($userInfos, 'logo', 'id');
        $userNames = array_pluck($userInfos, 'fullname', 'id');
        $userEmail = array_pluck($userInfos, 'email', 'id');
        foreach ($accounts->items() as $item) {
            $accountTmp = [];
            $accountTmp['currency_symbol'] = $currency->symbol;
            $accountTmp['image'] = $userImages[$item->from_user_id] ?? '';
            $accountTmp['amount'] = round($item->amount * $currency->rate, $currency->digit);
            $accountTmp['date'] = $item->account_at;
            switch ($item->type) {
                case 1: // 购物返利
                    $accountTmp['type'] = 'Shopping Reward';
                    $accountTmp['description'] = "You have placed an order for {$item->order_amount}";
                    break;
                case 2: // 粉丝购物
                    $accountTmp['type'] = 'Reward by your followers‘ shopping';
                    $accountTmp['description'] = "Your follower " . ($userNames[$item->from_user_id] ?? $userEmail[$item->from_user_id]) . " placed an order for {$item->order_amount}";
                    break;
                case 3: // 粉丝返利
                    $accountTmp['type'] = 'Reward by your followers‘ rewards';
                    $accountTmp['description'] = "Your follower " . ($userNames[$item->from_user_id] ?? $userEmail[$item->from_user_id]) . " have got her followers‘ rewards
for {$item->order_amount}";
                    break;
                case 4:
                    $accountTmp['type'] = 'Reward by inviting a new follower';
                    $accountTmp['description'] = ($userNames[$item->from_user_id] ?? $userEmail[$item->from_user_id]) . " has been your follower";
                    break;
            }
            $accountData['accounts'][] = $accountTmp;
        }
        return ApiResponse::success($accountData);
    }

    public function info()
    {
        $userId = UsersService::getUserId();
        $userInfo = UsersService::getUserInfo($userId);
        $random_key = AuthService::getChangePhoneKey($userInfo['calling_code'], $userInfo['phone']);
        $userData = [
            'image'       => $userInfo->logo,
            'name'        => $userInfo->fullname,
            'gender'      => $userInfo->gender,
            'birth'       => $userInfo->birth,
            'email'       => $userInfo->email,
            'nation_code' => $userInfo->calling_code,
            'phone'       => $userInfo->phone,
            'invite_code' => UsersService::getParentInviteCode($userId),
            'random_key'  => $random_key
        ];
        return ApiResponse::success($userData);
    }

    public function edit(Request $request)
    {
        if ($result = UsersService::userEditValidator($request)) {
            return $result;
        }
        $userId = UsersService::getUserId();
        try {
            UsersService::update($userId, $request);
            return ApiResponse::success();
        } catch (ParamErrorException $e) {
            return ApiResponse::failure(g_API_ERROR, $e->getMessage());
        } catch (\Exception $e) {
            return ApiResponse::failure(g_API_ERROR, 'User Info save failed try again later');
        }
    }

    public function logo(Request $request)
    {
        $userId = UsersService::getUserId();
        if ($request->hasFile('img_name')) {
            $file = $request->file('img_name');
            $ext = $file->getClientOriginalExtension();
            if (!in_array(strtolower($ext), ['jpg', 'png', 'bmp', 'wbmp', 'jpeg'])) {
                return ApiResponse::failure(g_API_ERROR, 'The image format is incorrect !');
            }
            $size = $file->getSize();
            if ($size > 2 * 1024 * 1024) {
                return ApiResponse::failure(g_API_ERROR, 'The image format is too large !');
            }
            /*list($width, $height) = getimagesize($file->getRealPath());
            if ($width > 800 || $height > 800) {
                return ApiResponse::failure(g_API_ERROR, 'Picture width can not be greater than 800 !');
            }*/
        } else {
            return ApiResponse::failure(g_API_ERROR, 'Image can not be null');
        }
        $product_dir = "logo/{$userId}";
        $directory = "product";
        $directory .= '/' . $product_dir;
        $urlInfo = $this->uploadFile('img_name', $bucket = 'cucoe', $directory, $ali = true, false, false);
        if ($urlInfo) {
            if (is_production()) {
                $logo = $urlInfo['oss-request-url'];
            } else {
                $logo = $urlInfo;
            }
            if (User::where('id', $userId)->update(['logo' => $logo])) {
                return ApiResponse::success(cdnUrl($logo));
            }
            return ApiResponse::failure(g_API_ERROR, 'Image upload failed !');
        } else {
            return ApiResponse::failure(g_API_ERROR, 'Image upload failed !');
        }
    }

    /**
     * @function base64上传用户图像
     * @param Request $request
     */
    public function baseLogo(Request $request)
    {
        $userId = UsersService::getUserId();
        $product_dir = "logo/{$userId}";
        $directory = "product";
        $directory .= '/' . $product_dir;
        $urlInfo = $this->base64_image_content($request->input('img_name'), $directory);
        if ($urlInfo) {
            if (User::where('id', $userId)->update(['logo' => $urlInfo])) {
                return ApiResponse::success(cdnUrl($urlInfo));
            }
            return ApiResponse::failure(g_API_ERROR, 'Image upload failed !');
        } else {
            return ApiResponse::failure(g_API_ERROR, 'Image upload failed !');
        }
    }

    /**
     * [将Base64图片转换为本地图片并保存]
     * @param $base64_image_content [要保存的Base64]
     * @param $path [要保存的路径]
     * @return bool|string
     */
    public function base64_image_content($base64_image_content, $path)
    {
        //匹配出图片的格式
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
            $type = $result[2];
            if (!in_array($type, ['jpg', 'png', 'bmp', 'wbmp', 'jpeg'])) {
                throw new CustomException('The image format is incorrect !');
            }
            $new_file = $path . "/" . date('Ymd', time()) . "/";
            $basePutUrl = $new_file;
            if (!file_exists($basePutUrl)) {
                //检查是否有该文件夹，如果没有就创建，并给予最高权限
                mkdir($basePutUrl, 0700, true);
            }
            $ping_url = mt_rand(100, 999) . time() . ".{$type}";
            $local_file_url = $basePutUrl . $ping_url;
            if (file_put_contents($local_file_url, base64_decode(str_replace($result[1], '', $base64_image_content)))) {
                // 获取图片宽高
                $size = strlen(file_get_contents($local_file_url));
                if ($size > 2 * 1024 * 1024) {
                    throw new CustomException('The image format is too large !');
                }
                /*list($width, $height) = getimagesize($local_file_url);
                if ($width > 800 || $height > 800) {
                    throw new CustomException('Picture width can not be greater than 800 !');
                }*/
                if (env('APP_ENV') == 'production') { // 上传到阿里云存储
                    $bucket = env('OSS_BUCKET', 'cucoe');
                    $img = StorageHandler::uploadToAliOss($local_file_url, $basePutUrl . '/' . $ping_url, $bucket);
                    if ($img) {
                        // 删除本地图片
                        unlink($local_file_url);
                        return $img['oss-request-url'];
                    }
                }
                return env('APP_URL', 'https://api.waiwaimall.com') . '/' . $local_file_url;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function cardDel(Request $request)
    {
        $cardId = $request->input('card_id', 0);
        if (!$cardId) {
            return ApiResponse::failure(g_API_ERROR, 'Card Id can not be null');
        }
        $cardInfo = UsersService::getUserCardById($cardId);
        $userId = UsersService::getUserId();
        if (!$cardInfo || $cardInfo->user_id != $userId) {
            return ApiResponse::failure(g_API_ERROR, 'Cards dose not exits');
        }
        if (UsersService::cardDelete($cardId)) {
            return ApiResponse::success('success');
        }
        return ApiResponse::failure(g_API_ERROR, 'card delete failed');
    }

    /**
     * @function pc个人中心
     * @param Request $request
     * @return mixed
     */
    public static function pcIndex(Request $request)
    {
        $result = [];
        $currency_code = isset($request['currency_code']) ? $request['currency_code'] : 'USD';
        $currency = Currency::where('currency_code', $currency_code)->first();
        if (!$currency) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'Currency dose not exists');
        }
        $userId = UsersService::getUserId();
        if (!$userId) {
            return ApiResponse::failure(g_API_URL_NOTFOUND, 'User not found');
        }
        $userInfo = UsersService::getUserInfo($userId);
        $result['img'] = cdnUrl($userInfo->logo);
        $result['username'] = $userInfo->fullname ?? "";
        $result['name'] = $userInfo->fullname ?? "";
        $result['integral'] = 0;
        $result['currency_symbol'] = $currency->symbol;
        $result['card'] = CouponService::getCouponCount($userId);
        $result['money'] = round($userInfo->amount_money * $currency->rate, $currency->digit);
        $result['income'] = $userInfo->accumulated_income ?? 0;
        $result['income'] = round($result['income'] * $currency->rate, $currency->digit);
        $result['wait_account'] = round(CustomerIncomeService::getWaitAccount($userId) * $currency->rate,
            $currency->digit);
        $result['fans'] = UsersService::getFansCount($userId);
        $orderData = OrdersService::getUserOrdersCounts($userId);
        $orderData[1] = $orderData[1] ?? 0;
        $orderData[3] = $orderData[3] ?? 0;
        $orderData[4] = $orderData[4] ?? 0;
        $result['order_unpay'] = $orderData[1];
        $result['order_ship'] = round($orderData[3] + $orderData[4], 0);
        $result['order_all'] = array_sum($orderData);
        return ApiResponse::success($result);
    }

    /**
     * 上传图片到
     * @param string $file 上传文件类型
     * @param string $bucket 上传到亚马逊的空间别名
     * @param string $directory 本地临时目录
     * @param bool $ali
     * @param bool $keep_original_name 是否保持原始名称
     * @param bool $use_timestamp
     * @param string $contentType
     * @return mixed
     * @throws \Exception
     */
    private function uploadFile(
        $file = "file",
        $bucket = 'cucoe',
        $directory = "uploads",
        $ali = true,
        $keep_original_name = false,
        $use_timestamp = false,
        $contentType = 'image/jpeg'
    ) {
        $ali = is_production();
        if ($ali) {
            $file = \request()->file($file);
            $extension = $file->getClientOriginalExtension();
            $fileName = uniqid() . '.' . $extension;
            $bucket = env('OSS_BUCKET', 'cucoe');
            return StorageHandler::uploadToAliOss($file->getRealPath(), $directory . '/' . $fileName, $bucket);
        } else {
            return StorageHandler::uploadToLocal($file, $directory, $keep_original_name, null);
        }
    }

    /**
     * 添加邀请码
     * @return mixed
     */
    public function addInviteCode()
    {
        if (!\request()->filled('invite_code')) {
            return ApiResponse::failure(g_API_ERROR, 'please provide invitation code');
        }
        $inviteCode = \request()->get('invite_code');

        try {
            if (!UsersService::findByInvIiteCode($inviteCode)) {
                return ApiResponse::failure(g_API_ERROR, 'invitation code error');
            }
            DB::beginTransaction();
            UsersService::dealRelation($inviteCode);
            DB::commit();
            return ApiResponse::success();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::failure(g_API_ERROR, '添加失败');
        }
    }

    /**
     * 修改手机号
     * @return mixed
     */
    public function changePhone()
    {
        try {
            $requestParams = $this->verifyParams();
            $calling_code = $requestParams['calling_code'];
            $mobile = $requestParams['mobile'];
            $random_key = request()->get('random_key');
            if (!$random_key) {
                throw  new ParamErrorException('auth failed');
            }
            $this->verifyMobile($calling_code, $mobile);
            $userId = UsersService::getUserId();
            $userInfo = UsersService::getUserInfo($userId);
            //验证random_key
            AuthService::checkChangePhoneKey($userInfo['calling_code'], $userInfo['phone'], $random_key);
            //验证新手机号是否已经注册

            //更新用户信息
            $userInfo->calling_code = $calling_code;
            $userInfo->phone = $mobile;
            $userInfo->save();

            return ApiResponse::success();
        } catch (ParamErrorException $e) {
            return ApiResponse::failure(g_API_ERROR, $e->getMessage());
        } catch (\Exception $e) {
            return ApiResponse::failure(g_API_ERROR, 'send sms failed');
        }
    }

    /**
     * 验证手机号参数
     * @param bool $first
     * @return array
     * @throws ParamErrorException
     */
    public function verifyParams($first = true)
    {
        $calling_code = trim(request()->get('calling_code'));
        $mobile = trim(request()->get('mobile'));
        $verify_code = trim(request()->get('verify_code'));
        $mobileWithCode = $calling_code . $mobile;
        if (!$calling_code) {
            throw new ParamErrorException('Please provide Telephone Number');
        }

        if (!$mobile) {
            throw new ParamErrorException('Please provide Telephone Number');
        }
        if ($first) {
            if (!$verify_code) {
                throw new ParamErrorException('Please provide Verification Code');
            }
            if (!$this->smsServices->checkCode($mobileWithCode, $verify_code)) {
                throw new ParamErrorException('Verification Code error');
            }
        }

        $data = [
            'calling_code'   => $calling_code,
            'mobile'         => $mobile,
            'mobileWithCode' => $mobileWithCode
        ];
        return $data;
    }

    /**
     * 验证手机是否已经注册
     * @param $calling_code
     * @param $mobile
     * @throws ParamErrorException
     */
    public function verifyMobile($calling_code, $mobile)
    {
        if (UsersService::findByPhone($calling_code, $mobile)) {
            throw new ParamErrorException('Telephone already bind by another User');
        }
    }

    /**
     * 验证旧手机号
     * @return mixed
     */
    public function checkOldPhone()
    {
        $userId = UsersService::getUserId();
        $userInfo = UsersService::getUserInfo($userId);
        $calling_code = $userInfo['calling_code'];
        $phone = $userInfo['phone'];
        $mobileWithCode = $calling_code . $phone;
        $verify_code = trim(request()->get('verify_code'));
        try {
            if (!$verify_code) {
                throw new ParamErrorException('Please provide Verification Code');
            }
            //验证手机号
            if (!$this->smsServices->checkCode($mobileWithCode, $verify_code)) {
                throw new ParamErrorException('Verification code error');
            };
            //生成随机数
            $randomKey = AuthService::cacheChangePhoneKey($calling_code, $phone);
            return ApiResponse::success(['random_key' => $randomKey]);
        } catch (ParamErrorException $e) {
            return ApiResponse::failure(g_API_ERROR, $e->getMessage());
        } catch (\Exception $e) {
            return ApiResponse::failure(g_API_ERROR, 'Verification code error');
        }
    }
}
