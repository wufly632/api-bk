<?php

namespace App\Modules\Users\Http\Controllers;

use App\Assistants\CLogger;
use App\Mail\PasswordReset;
use App\Modules\Users\Services\UsersService;
use App\Services\ApiResponse;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use function PHPSTORM_META\elementType;

class UsersController extends Controller
{
    public function login()
    {
        $email = request()->input('email');
        $password = request()->input('password');
        return UsersService::signInWithEmail($email, $password);
    }

    public function generateGuest()
    {
        return UsersService::generateGuestUser();
    }

    public function register()
    {
        \Log::info(request()->input());
        $email = request()->input('email');;
        $password = request()->input('password');
        $results = UsersService::signUpWithEmail($email, $password);
        return $results;
    }

    public function logout()
    {
        $token = request()->get('token');
        return UsersService::logout($token);
    }

    public function pwreset()
    {
        $email = request()->input('email', '');
        if( ! $email) return ApiResponse::failure(g_API_ERROR, 'Email can not be null');
        $userInfo = UsersService::getUserInfoByEmail($email);
        if(!$userInfo) return ApiResponse::failure(g_API_ERROR, 'Email dose not exists');
        $token = UsersService::getPwresetToken($userInfo->id);
        $url = env('MAIL_PASSWORD_RESET', '')."?pwset_token={$token}";
        try{
            if(isset($userInfo->firstname)) {
                $name = "{$userInfo->firstname} {$userInfo->lastname}";
            }else{
                $name = "Customer";
            }
            $emailResult = Mail::to($userInfo->email)->send(new PasswordReset($url, $name));
            return ApiResponse::success('');
        }catch (\Exception $exception){
            // dd($exception->getMessage());
            CLogger::getLogger('mail')->info($exception->getMessage());
            return ApiResponse::failure(g_API_ERROR, 'email send failed, try again later');
        }
    }

    public function pwsave(Request $request)
    {
        if($result = UsersService::pwresetValidator($request))
        {
            return $result;
        }
        $token = $request->input('token');
        $password = $request->input('password');
        $tokenInfo = UsersService::getPwresetTokenByToken($token);
        if(UsersService::setPassword($tokenInfo, $password))
        {
            $userToken = UsersService::getToken($tokenInfo->user_id);
            UsersService::logout($userToken);
            return ApiResponse::success('success');
        }
        return ApiResponse::failure(g_API_ERROR, 'Password set failed');
    }
}
