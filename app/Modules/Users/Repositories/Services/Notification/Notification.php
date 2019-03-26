<?php
/**
 * Created by PhpStorm.
 * User: wmj
 * Date: 2018/5/8
 * Time: 15:37
 */

namespace App\Modules\Users\Repositories\Services\Notification;
use App\Modules\Orders\Repositories\Services\OrderInfo;
use App\Modules\Orders\Repositories\Services\Push;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class Notification
{
    public static function orderNotification($order_id, $receiver, $type,$push=null){
        try{
            $notypes = Config::get("patpat.notification_type");
            $order_number = OrderInfo::generateOrderNumber($order_id);
            $title = "";
            if($type == $notypes['order']['order_placed']){
                $title = "Your order #$order_number has been placed.";
            }
            elseif($type == $notypes['order']['order_settled']){
                $title = "Your order #$order_number is now in processing.";
            }
            elseif($type == $notypes['order']['order_cancelled']){
                $title = "Your order #$order_number has been cancelled. Your money has been refunded.";
            }
            elseif($type == $notypes['order']['order_shipped']){
                $title = "Your order #$order_number has been shipped.";
            }
            elseif($type == $notypes['order']['order_delivered']){
                $title = "Your order #$order_number has been delivered.";
            }
            elseif($type == $notypes['order']['order_pushreivew']){
                $title = "Your PatPat freebie item has been delivered! Want another freebie? Please write a review of your current freebie in the PatPat APP!";
            }
            elseif($type == $notypes['order']['order_pushreivew_secondday']){
                $title = "Your PatPat freebie item was delivered yesterday, want another? Write a review of your current one in the PatPat App to receive more free stuff!";
            }
            elseif($type == $notypes['order']['order_pushreivew_thirdday']){
                $title = "You received your PatPat freebie item a while ago, but it's not too late to receive another! Write a review of your last freebie in the PatPat APP to receive another one!";
            }

            self::createNotification(
                $title,
                $type,
                $receiver,
                'oms_orders',
                $order_id,
                $push
            );
        }catch (Exception $e) {
            Log::error("orderNotification:".$e->getMessage());
        }
    }

    public static function createNotification($title, $type, $receiver, $reference_table=null,$reference_id=null,$pushData=null){
        $notification = array(
            "title"=>$title,
            "type"=>$type,
            "user_id"=>$receiver,
            "reference_table"=>$reference_table,
            "reference_id"=>$reference_id,
            "created_at"=>Carbon::now()->toDateTimeString(),
            "isread" =>0
        );
        try{
            DB::table('sys_notification')->insert($notification);
        }catch (Exception $e){
            var_dump($e->getMessage());
        }
        if($pushData){
            Push::pushToUser($receiver,$type,$pushData);
        }

    }

    public static function genNotificationContent(&$notification){
        $type = $notification->type;
        $notification_types = Config::get("patpat.notification_type");
        $icon = "http://patpatdev.s3.amazonaws.com/logo/notification/notification_account.jpg";
        $action = "patpat://?action=default_action";
        $message = "";

        if(in_array($type,$notification_types['wallet'])){
            $icon = "http://patpatdev.s3.amazonaws.com/logo/notification/notification_wallet.jpg";
            $action = "patpat://?action=wallet";
        }
        elseif(in_array($type,$notification_types['referral'])){
            $item_id =  $notification->item_id;
            $user = DB::table("sys_customers")->find($item_id);
            if($user){
                if($user->customers_avatar){
                    $icon = $user->customers_avatar;
                }
                $icon = "http://patpatdev.s3.amazonaws.com/logo/head.png";
            }
            else{
                $icon = "http://patpatdev.s3.amazonaws.com/logo/head.png";
            }
            $action = "patpat://?action=referral";
        }
        elseif(in_array($type,$notification_types['event'])){
            //$action = "patpat://?action=event_detail";
            $icon = cdn_url("/assets/img/events.png");
        }elseif(in_array($type,$notification_types['order'])){
            $order_id =  $notification->item_id;
            $action = "patpat://?action=order_detail&order_id=".$order_id;
            $icon = OrderInfo::getOrderIcon($order_id);
        }elseif(in_array($type,$notification_types['coupon'])){
            if($type==$notification_types['coupon']['invite']){
                $walletRecordId =  $notification->item_id;
                //$action = "patpat://?action=wallet";
                $action = "";//修复app端进入wallet app闪退bug，api端暂时简单处理,action先返回空发
                $record = DB::table('mb_wallet_credit_record')->find($walletRecordId);
                $icon = OrderInfo::getOrderIcon($record->order_id);
            }elseif($type==$notification_types['coupon']['invitefrom']){
                $uid =  $notification->item_id;
                $user = DB::table('sys_customers')->find($uid);
                if($user->customers_avatar){
                    $icon = $user->customers_avatar;
                }
            }
            elseif($type==$notification_types['coupon']['inviteto']){
                $uid =  $notification->item_id;
                $user = DB::table('sys_customers')->find($uid);
                if($user->customers_avatar){
                    $icon = $user->customers_avatar;
                }
            }
        }elseif(in_array($type,Config::get('patpat.notification_type.cart_notpay'))){
            $product_img=null;
            $product_id =  $notification->item_id;
            $action = "patpat://?action=shopping_cart";
            if(!empty($product_id)) {
                $product_img=DB::table('oms_products')->find($product_id);
            }
            if(!empty($product_img)) {
                $icon=$product_img->icon;
            }
        }elseif($type == 'on line') {
            $product_img=null;
            $info = explode("_", $notification->item_id);
            $event_id = $info[0];
            $product_id = $info[1];
            $action = "patpat://?action=product_detail&event_id=".$event_id."&product_id=".$product_id;
            if(!empty($product_id)) {
                $product_img=DB::table('oms_products')->find($product_id);
            }
            if(!empty($product_img)) {
                $icon=$product_img->icon;
            }
        }
        elseif($type == 'cancel_invite_order') {
            $icon = "http://patpatdev.s3.amazonaws.com/logo/notification/notification_wallet.jpg";
            $action = "patpat://?action=wallet";
        }
        elseif (in_array($type,$notification_types['easter_wallet'])) {
            $icon = cdn_url("/assets/img/icon/Promotions1_Promotions1@2x.png");
            $action = "patpat://?action=wallet";
        }
        else if(in_array($type, $notification_types['PromotionsRepository'])){
            $icon = cdn_url("/assets/img/icon/Promotions1_Promotions1@2x.png");
        }
        else if (in_array($type, $notification_types['system'])){
            $icon = cdn_url("/assets/img/system.png");
        }

        $notification->icon = cdn_url($icon);
        $notification->action = $action;
        $notification->message = $message;
    }
}