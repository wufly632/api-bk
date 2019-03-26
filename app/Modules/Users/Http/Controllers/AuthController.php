<?php

namespace App\Modules\Users\Http\Controllers;

use App\Assistants\CLogger;
use App\Exceptions\ForbiddenException;
use App\Exceptions\OauthException;
use App\Exceptions\ParamErrorException;
use App\Modules\Carts\Services\CartsService;
use App\Modules\Users\Services\AuthService;
use App\Modules\Users\Services\SmsServices;
use App\Modules\Users\Services\UnionService;
use App\Modules\Users\Services\UsersService;
use App\Services\ApiResponse;
use App\User;
use GuzzleHttp\Client;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    protected $smsServices;

    public function __construct(SmsServices $smsServices)
    {
        $this->smsServices = $smsServices;
    }


    /**
     * @throws OauthException
     */
    public function checkAccessToken($userToken)
    {

        try {
            if (!App::environment('dev')) {
                $client = new Client();
                $res = $client->get('https://graph.facebook.com/me?access_token=' . $userToken);
                $resBody = json_decode($res->getBody(), true);
            }
        } catch (\Exception $e) {
            CLogger::getLogger('facebook-auth', 'auth')->info($e->getMessage());
            throw new OauthException('auth failed');
        }
    }

    /**
     * 发送验证码
     * @return mixed
     */
    public function sendSms()
    {
        $calling_code = request()->get('calling_code');
        $mobile = request()->get('mobile');
        if (!trim($calling_code)) {
            return ApiResponse::failure(g_API_ERROR, 'mobile format error');
        }
        if (!trim($mobile)) {
            return ApiResponse::failure(g_API_ERROR, 'mobile can not be null');
        }
        try {
            $type = request()->get('type', 'login');
            $this->smsServices->send($calling_code, $mobile, $type);
            return ApiResponse::success();
        } catch (ForbiddenException $e) {
            return ApiResponse::failure(g_API_ERROR, $e->getMessage());
        } catch (ParamErrorException $e) {
            return ApiResponse::success($e->getMessage());
        } catch (\Exception $e) {
            return ApiResponse::failure(g_API_ERROR, 'send sms failed');
        }
    }

    /**
     * @return mixed
     */
    public function getRandomKey()
    {
        try {
            $requestParams = $this->verifyParams();
            $calling_code = $requestParams['calling_code'];
            $mobile = $requestParams['mobile'];
            return ApiResponse::success([
                'registered' => 'no',
                'random_key' => AuthService::cacheKey($calling_code, $mobile)
            ]);
        } catch (ParamErrorException $paramErrorException) {
            return ApiResponse::failure(g_API_ERROR, $paramErrorException->getMessage());
        } catch (\Exception $e) {
            return ApiResponse::failure(g_API_ERROR, 'Auth failed');
        }

    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function login()
    {
        try {
            $requestParams = $this->verifyParams();
            $calling_code = $requestParams['calling_code'];
            $mobile = $requestParams['mobile'];
            $user = UsersService::firstOrcreate(['calling_code' => $calling_code, 'phone' => $mobile]);
            if ($user->registered != 1) {
                return ApiResponse::success([
                    'registered' => 'no',
                    'random_key' => AuthService::cacheKey($calling_code, $mobile)
                ]);
            } else {
                UsersService::cartsSync($user->id);
                $user->last_login_datetime = Carbon::now()->toDateTimeString();
                $user->save();
                return ApiResponse::success(UsersService::buildResponseData($user->id));
            }
        } catch (ParamErrorException $paramErrorException) {
            return ApiResponse::failure(g_API_ERROR, $paramErrorException->getMessage());
        } catch (\Exception $e) {
            return ApiResponse::failure(g_API_ERROR, 'Auth failed');
        }
    }


    /**
     * @return mixed
     * @throws \Exception
     */
    public function loginConfirm()
    {
        try {
            $requestParams = $this->verifyParams(false);
            $calling_code = $requestParams['calling_code'];
            $mobile = $requestParams['mobile'];
            $random_key = request()->get('random_key');
            AuthService::checkKey($calling_code, $mobile, $random_key);
            UsersService::checkRegisterStatus($calling_code, $mobile);
            DB::beginTransaction();
            $user = UsersService::updateOrCreate(['calling_code' => $calling_code, 'phone' => $mobile],
                ['registered' => 1, "last_login_datetime" => Carbon::now()->toDateTimeString()]);
            UsersService::eventSignUp($user->id);
            DB::commit();
            return ApiResponse::success(UsersService::buildResponseData($user->id));
        } catch (ParamErrorException $paramErrorException) {
            DB::rollBack();
            return ApiResponse::failure(g_API_ERROR, $paramErrorException->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::failure(g_API_ERROR, 'Auth failed');
        }

    }

    /**
     *
     * @param $first
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
     * @return array
     * @throws ParamErrorException
     */
    public function verifyUnionParams()
    {
        $uuid = trim(request()->get('union_id'));
        $union_type = trim(request()->get('union_type'));
        if (!$uuid) {
            throw new ParamErrorException('Auth failed');
        }

        if (!$union_type) {
            throw new ParamErrorException('Auth failed');
        }
        return [
            'union_id'   => $uuid,
            'union_type' => $union_type
        ];
    }

    /**
     *
     * @throws \Exception
     */
    public function unionLogin()
    {
        try {
            $accessToken = request()->get('accessToken');
            $this->checkAccessToken($accessToken);
            $unionParams = $this->verifyUnionParams();
            $user = UnionService::firstOrCreate($unionParams['union_type'], $unionParams['union_id']);
            if (!$user->user_id) {
                return ApiResponse::success(['registered' => 'no']);
            } else {
                return ApiResponse::success(UsersService::buildResponseData($user->user_id));
            }
        } catch (OAuthException $authException) {
            return ApiResponse::failure(g_API_ERROR, 'Auth failed');
        } catch (ParamErrorException $paramErrorException) {
            return ApiResponse::failure(g_API_ERROR, $paramErrorException->getMessage());
        } catch (\Exception $e) {
            return ApiResponse::failure(g_API_ERROR, 'Auth failed');
        }
    }

    /**
     *
     * @throws \Exception
     */
    public function unionLoginConfirm()
    {
        try {
            $requestParams = $this->verifyParams(false);
            $calling_code = $requestParams['calling_code'];
            $mobile = $requestParams['mobile'];
            $random_key = request()->get('random_key');
            AuthService::checkKey($calling_code, $mobile, $random_key);
            $unionParams = $this->verifyUnionParams();
            DB::beginTransaction();
            $user = $this->normalCreate($mobile, $calling_code);
            UnionService::update(['uuid' => $unionParams['union_id'], 'type' => $unionParams['union_type']],
                ['user_id' => $user->id]);
            DB::commit();
            return ApiResponse::success(UsersService::buildResponseData($user->id));
        } catch (ParamErrorException $paramErrorException) {
            DB::rollBack();
            return ApiResponse::failure(g_API_ERROR, $paramErrorException->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::failure(g_API_ERROR, 'Auth failed');
        }
    }


    /**
     * 正常创建用户
     * @param $mobile
     * @param $calling_code
     * @return mixed
     */
    public function normalCreate($mobile, $calling_code)
    {
        $firstname = request()->get('first_name', '');
        $lastname = request()->get('last_name', '');
        $email = request()->get('email', '');
        $user = UsersService::firstOrcreate(['calling_code' => $calling_code, 'phone' => $mobile], [
            "last_login_datetime" => Carbon::now()->toDateTimeString(),
            'firstname'           => $firstname,
            'lastname'            => $lastname,
            'email'               => $email,
            'fullname'            => $firstname . ' ' . $lastname
        ]);
        if ($user->registered == 0) {
            UsersService::eventSignUp($user->id);
        }else{
            UsersService::cartsSync($user->id);
        }
        return $user;
    }

    /**
     * 最新版登陆
     * @return mixed
     */
    public function latestLogin()
    {
        try {
            $requestParams = $this->verifyParams();
            $calling_code = $requestParams['calling_code'];
            $mobile = $requestParams['mobile'];
            DB::beginTransaction();
            $user = $this->normalCreate($mobile, $calling_code);
            DB::commit();
            return ApiResponse::success(UsersService::buildResponseData($user->id));
        } catch (ParamErrorException $paramErrorException) {
            DB::rollBack();
            return ApiResponse::failure(g_API_ERROR, $paramErrorException->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::failure(g_API_ERROR, 'Auth failed');
        }
    }

    /**
     * 最新版联合登陆
     * @return mixed
     */
    public function latestUnionLogin()
    {
        try {
            $requestParams = $this->verifyParams();
            $calling_code = $requestParams['calling_code'];
            $mobile = $requestParams['mobile'];
            $unionParams = $this->verifyUnionParams();
            DB::beginTransaction();
            $user = $this->normalCreate($mobile, $calling_code);
            UnionService::update(['uuid' => $unionParams['union_id'], 'type' => $unionParams['union_type']],
                ['user_id' => $user->id]);
            DB::commit();
            return ApiResponse::success(UsersService::buildResponseData($user->id));
        } catch (ParamErrorException $paramErrorException) {
            DB::rollBack();
            return ApiResponse::failure(g_API_ERROR, $paramErrorException->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::failure(g_API_ERROR, 'Auth failed');
        }
    }
}