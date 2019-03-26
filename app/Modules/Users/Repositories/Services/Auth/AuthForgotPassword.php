<?php
/**
 * Created by patpat.
 * User: Bruce.He
 * Date: 16/4/22
 * Time: 下午10:49
 */

namespace App\Modules\Users\Repositories\Services\Auth;

use Carbon\Carbon;
use DB;
use AccountRepository;
use Exception;

class AuthForgotPassword
{
    public static function checkReminderWithTime($email){
        $avaliable_to    = Carbon::now();
        $avaliable_from  = Carbon::now()->subMinutes(15);
        $count = DB::table('oms_password_reminders')
            ->where('email', $email)
            ->where('created_at', '<', $avaliable_to)
            ->where('created_at', '>', $avaliable_from)
            ->count();
        if($count>0)return true;
        return false;
    }

    public static function checkReminder($email){
        $user = DB::table('oms_password_reminders')
            ->where('email', $email)
            ->first();
        return $user;
    }

    public static function insertReminder($email,$verify_code)
    {
        $insertData = array(
            'email' => $email,
            'token' => $verify_code,
            'created_at' => Carbon::now()
        );
        DB::table('oms_password_reminders')->insert( $insertData );
    }

    public static function updateReminder($reminder,$verify_code){
        $updateData = array(
            'email' => $reminder->email,
            'token' => $verify_code,
            'created_at' => Carbon::now()
        );
        DB::table('oms_password_reminders')->where('email', $reminder->email)->update($updateData);
    }

    public static function checkVerifyCode($email, $verify_code){
        $avaliable_to       = Carbon::now();
        $avaliable_from     = Carbon::now()->subMinutes(15);
        $count = DB::table('oms_password_reminders')
            ->where('email', $email)
            ->where('token', $verify_code)
            ->where('created_at', '<', $avaliable_to)
            ->where('created_at', '>', $avaliable_from)
            ->count();
        
        if($count>0)return true;
        return false;
    }

    public static function resetPassword($password,$email)
    {
        try{
            $user = DB::table('sys_customers')
                ->where('customers_email_address', $email)
                ->where('registered', '1')
                ->where('thirdpartid', '0')
                ->first();
            if(empty($user))return flase;
            DB::table('sys_customers')->where('id', $user->id)->where('thirdpartid', '0')->update(['customers_password'=>md5($password)]);
//If reset need relogin
//          DB::table("sys_user_token")->where('user_id', $user_id)->update(['user_token'=>'','updated_at'=>Carbon::now()->toDateTimeString()]);
            DB::commit();
            return true;
        }
        catch (Exception $e) {
            DB::rollback();
            return flase;
        }
    }

}