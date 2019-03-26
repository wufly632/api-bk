<?php

/**
 * Created by patpat.
 * User: Bruce.He
 * Date: 16/4/14
 * Time: 上午1:05
 */

namespace App\Modules\Users\Repositories\Services\Auth;

use App\Modules\Users\Services\PatPointService;
use App\Modules\Wallets\Services\WalletService;
use CartStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\URL;
use OrderStatus;

class AuthSignIn
{
    /**
     * 判断该用户是否已经注册
     * @param int $user_id
     * @return mixed
     */
    public static function checkIfUserSignedUp($user_id)
    {
        $user = DB::table('sys_customers')
            ->select(
                'id', 'customers_email_address'
            )
            ->where('id', $user_id)
            ->where('deleted_at', null)
            ->where('registered', '1')
            ->first();
        return $user;
    }

    /**
     * 获取该邮箱的所有未注册用户，用于登录时同步邮箱信息用
     * @param $email
     * @return mixed
     */
    public static function getAllEmailUsers($email)
    {
        $users = DB::table('sys_customers')
            ->select(
                'id', 'customers_email_address'
            )
            ->where('customers_email_address', $email)
            ->where('deleted_at', null)
            ->where('registered', '0')
            ->where('thirdpartid', '0')
            ->get();

        return $users;
    }

    /**
     * 更新用户信息，以最新的为准
     * @param $user_id
     * @param $params
     */
    public static function updateUserEmail($user_id, $params)
    {
        DB::table('sys_customers')->where('id', $user_id)->update($params);
    }

    /**
     * @param $user_id
     * @param $guest
     */
    public static function logRegisterSource($user_id, $guest = false)
    {
        $landing_page = "";
        $registration_source = is_mobile() ? "wap" : "web";
        if ($guest) {
            $from_url = URL::previous() ? URL::previous() : route('home.page');
        } else {
            $from_url = Cookie::get(request('refer'), route('home.page'));
        }
        DB::table("sys_customers")->where("id", $user_id)->update(array("registration_source" => $registration_source,
            "from_url" => $from_url, "landing_page" => $landing_page));
    }

    /**
     * if guest login,transfer guest cart and faves to her account
     * @param $old_user_id
     * @param $user_id
     */
    public static function transferCartAndFaves($old_user_id, $user_id)
    {
        //justify old_user_id is guest?
        $is_guest = DB::table('sys_customers')->where('id', $old_user_id)->first();
        if ($is_guest->registered) {
            return;
        }
        //cart
        $guest_cart = DB::table('mb_cart')->where("user_id", $old_user_id)->where('current_status', CartStatus::NOTPAY)->orderBy('id', 'desc')->first();
        if ($guest_cart) {
            $user_cart = DB::table('mb_cart')->where("user_id", $user_id)->where('current_status', CartStatus::NOTPAY)->orderBy('id', 'desc')->first();
            if ($user_cart) {
                $user_cart_records = DB::table('mb_cart_record')->where("user_id", $user_id)
                    ->where("record_status", CartStatus::NOTPAY)->where('cart_id', $user_cart->id)->get();
                //删除游客用户和已登录用户购物车中完全相同的sku
                foreach ($user_cart_records as $user_cart_record) {
                    $sku_id = $user_cart_record->sku_id;
                    $product_id = $user_cart_record->product_id;
                    $event_id = $user_cart_record->event_id;
                    DB::table('mb_cart_record')->where("user_id", $old_user_id)->where("record_status", CartStatus::NOTPAY)
                        ->where('cart_id', $guest_cart->id)
                        ->where('sku_id', $sku_id)->where('product_id', $product_id)->where('event_id', $event_id)
                        ->delete();
                }
            }
            if ($user_cart) {
                //将游客用户的购物车记录的user id更新为已登录的用户的user id,并将游客用户购物车记录的cart id更新为登录用户的购物车id
                DB::table('mb_cart_record')->where("user_id", $old_user_id)->where("record_status", CartStatus::NOTPAY)
                    ->update(['user_id' => $user_id, 'cart_id' => $user_cart->id]);
            } else {
                //将游客用户的购物车记录的user id更新为已登录的用户的user id
                DB::table('mb_cart_record')->where("user_id", $old_user_id)->where("record_status", CartStatus::NOTPAY)
                    ->where('cart_id', $guest_cart->id)->update(['user_id' => $user_id]);
                //将游客用户的购物车的user id更新为已登录的用户的user id
                DB::table('mb_cart')->where("user_id", $old_user_id)->where("current_status", CartStatus::NOTPAY)->update(['user_id' => $user_id]);
            }
        }

        //更新数据前线判断$old_user_id是否已经注册，未注册才更新
        self::synchronizeUserInformationDetail($old_user_id, $user_id);
        $all_user_ids = [$old_user_id, $user_id];

        WalletService::createUserWallet($user_id);
        //汇总钱包
        $all_wallet_amount = DB::table('mb_wallet_credit')->whereIn('user_id', $all_user_ids)->sum('credit');
        WalletService::updateUserWallet($old_user_id, 0);//将原来未注册用户的钱包更新为0
        WalletService::updateUserWallet($user_id, $all_wallet_amount);//将已注册用户的钱包金额汇总

        //汇总patpoint
        $all_patpoint_account = DB::table('oms_user_points')->whereIn('user_id', $all_user_ids)->sum('point');
        PatPointService::updateUserPoints($old_user_id, 0);//将原来未注册用户的patpoint数量更新为0
        PatPointService::updateUserPoints($user_id, $all_patpoint_account);//将已注册用户的patpoint汇总

        //定制商品更新为新的用户id
        DB::table('oms_product_sku_customization')
            ->where('customer_id', $old_user_id)
            ->update(["customer_id"=>$user_id]);
    }

    /**
     * 同步【相同邮箱，不同user_id的未注册账号】的信息到【已用该邮箱注册的user_id账号】
     * @param $user_id
     */
    public static function synchronizeUsersInformation($user_id)
    {
        $new_user_info = AuthSignIn::checkIfUserSignedUp($user_id);
        if (isset($new_user_info) && isset($new_user_info->customers_email_address) && $new_user_info->customers_email_address != '') {
            $all_email_users = AuthSignIn::getAllEmailUsers($new_user_info->customers_email_address);
            if (isset($all_email_users) && is_array($all_email_users)) {
                $unsigned_user_ids = [];
                foreach ($all_email_users as $sigle_email_user) {
                    if (isset($sigle_email_user->id) && $sigle_email_user->id != '') {
                        self::synchronizeUserInformationDetail($sigle_email_user->id, $user_id);
                        if(isset($sigle_email_user->user_id)){
                            $unsigned_user_ids[] = $sigle_email_user->user_id;
                        }
                    }
                }

                if (!empty($unsigned_user_ids)) {
                    $all_user_ids = array_merge($unsigned_user_ids, [$user_id]);
                    //汇总钱包
                    $all_wallet_amount = DB::table('mb_wallet_credit')->whereIn('user_id', $all_user_ids)->sum('credit');
                    WalletService::updateUserWallet($unsigned_user_ids, 0);//将原来未注册用户的钱包更新为0
                    WalletService::updateUserWallet($user_id, $all_wallet_amount);//将已注册用户的钱包金额汇总

                    //汇总patpoint
                    $all_patpoint_account = DB::table('oms_user_points')->whereIn('user_id', $all_user_ids)->sum('point');
                    PatPointService::updateUserPoints($unsigned_user_ids, 0);//将原来未注册用户的patpoint数量更新为0
                    PatPointService::updateUserPoints($user_id, $all_patpoint_account);//将已注册用户的patpoint汇总
                }
            }
        }
    }

    /**
     * 同步$old_user_id的信息到$user_id账号
     * @param $old_user_id
     * @param $user_id
     * @param array $other 其他需要传递的信息
     * @return bool
     */
    public static function synchronizeUserInformationDetail($old_user_id, $user_id, $other = [])
    {
        if ($old_user_id == $user_id) {
            return;
        }

        $type = ['product', 'sku'];//用户收藏,去除重复
        $user_fave_save_ids = DB::table('sys_customer_saved')->where('user_id', $user_id)
            ->whereIn('type', $type)->where('status', 'saved')->pluck('save_id');//找到用户已经收藏的商品
        DB::table('sys_customer_saved')->where('user_id', $old_user_id)
            ->whereIn('type', $type)->where('status', 'saved')->whereNotIn("save_id", $user_fave_save_ids)
            ->update(['user_id' => $user_id]);

        DB::table('mb_user_delivery')->where("user_id", $old_user_id)->update(['is_default' => 0]);//取消默认地址
        DB::table('mb_user_delivery')->where("user_id", $old_user_id)->update(['user_id' => $user_id]);//用户地址
        DB::table('sys_customers_addressbook')->where("user_id", $old_user_id)->update(['is_default' => 0]);//取消默认地址
        DB::table('sys_customers_addressbook')->where("user_id", $old_user_id)->update(['user_id' => $user_id]);//用户地址

        DB::table('mb_user_billing')->where("user_id", $old_user_id)->update(['user_id' => $user_id]);
        DB::table('oms_orders')->where("user_id", $old_user_id)->where('current_status', '!=', OrderStatus::NOTPAY)->update(['user_id' => $user_id]);//订单
        DB::table('oms_order_sku')->where("user_id", $old_user_id)->where('current_status', '!=', OrderStatus::NOTPAY)->update(['user_id' => $user_id]);//订单sku
        DB::table('oms_ship_orders')->where("user_id", $old_user_id)->update(['user_id' => $user_id]);//发货单
        //DB::table('mb_wallet_credit')->where('user_id',$old_user_id)->update(['user_id' => $user_id]);//用户钱包【不能放在这里更新】
        DB::table('mb_wallet_credit_record')->where('user_id', $old_user_id)->update(['user_id' => $user_id]);//返现
        DB::table('sys_notification')->where('user_id', $old_user_id)->update(['user_id' => $user_id]);//通知
        //DB::table('oms_user_points')->where('user_id',$old_user_id)->update(['user_id' => $user_id]);//用户patpoint总数【不能放在这里更新】
        DB::table('oms_point_history')->where('user_id', $old_user_id)->update(['user_id' => $user_id]);//用户patpoint历史记录
    }

    /**
     * 判断下单邮箱是否已经被注册
     * @param $email
     * @return mixed
     */
    public static function checkIfEmailSignedup($email)
    {
        $user = DB::table('sys_customers')
            ->select(
                'id', 'customers_email_address'
            )
            ->where('customers_email_address', $email)
            ->where('deleted_at', null)
            ->where('registered', '1')
            ->where('thirdpartid', '0')
            ->first();

        return $user;
    }
}