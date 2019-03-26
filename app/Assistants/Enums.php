<?php
/**
 * Created by ZhongYue.
 * User: Administrator
 * Date: 2018/4/28
 * Time: 17:42
 */
class WebDiyStatus
{
    const ACTIVE = 1;
    const INACTIVE = 0;
}

class WebDiyPage
{
    const NAVIGATION = 'navigation_v2.0';
    const HOME = 'home_v2.0';
    const CHANNEL = 'channel';
}

class StoreCategoryVersion
{
    const VERSION = 5;
}

class EventStatus
{
    const ADOPTED = "adopted";
    const DRAFT = "draft";
}

class EventType
{
    const FLASH_SALE = "FlashSale";
    const DAILY_SPECIALS = "DailySpecials";
}

/**
 * product status
 * Class ProductStatus
 */
class ProductStatus
{
// 审核状态:
    const DRAFT = 1; // 默认创建后状态
    const SUBMITTED = 2; // 提交审核
    const ADOPTED = 4; // 通过可用
    const REJECTED = 8; // 拒绝通过

    // 上线状态
    const ONLINE = 11;
    const OFFLINE = self::ADOPTED; // 通过后状态默认即为"下线"
    const BLACK_LIST = 12; // 黑名单
}

class Promotion
{
    /**
     * 满m元减n元优惠(moneyoff)
     * @var string
     */
    const TAKE_M_OFF_N = 'moneyoff';

    /**
     * 满m件打n折(discountoff)
     * @var string
     */
    const BUY_M_GET_N_OFF = 'discountoff';

    /**
     * 买m件免n件(buy-m-free-n)
     * @var string
     */
    const BUY_M_GET_N_FREE = 'buy-m-free-n';

    /**
     * 买m件售n元(buy-m-saleprice-n)
     * @var string
     */
    const ANY_M_FOR_N = 'buy-m-saleprice-n';

    /**
     * 随机返现
     * @var string
     */
    const CASHBACK = 'cashback';

}

/**
 * 商品分类的版本号
 */
class CategoryVersion
{
    const version = 5;
}
class PageCategoryType
{
    const PAGE_CATEGORY_TYPE_CLEARANCE = 'clearance';
    const PAGE_CATEGORY_TYPE_NEW_ARRIVALS = 'new_arrivals';
}

/**
 * Class CommonConfigType
 */
class CommonConfigType
{
    const FAQ_TOP_QUESTIONS = 'faq_top_questions';//常见FAQ

}

/**
 * 商品曝光前缀
 *
 * Class ExposureType
 */
class ExposureType
{
    const EXPOSURE_TYPE_PREFIX_NEW_ARRIVALS = 'new_arrivals-0-';
    const EXPOSURE_TYPE_PREFIX_CLEARANCE = 'clearance-all-';
}

class ProductSortTypes
{
    const SORT_PRICE_L2H = 1;
    const SORT_PRICE_H2L = 2;
    const SORT_BEST_SELLING = 3;
    const SORT_BEST_MATCH = 4;
    const SORT_HIGHEST_REVIEW = 5;
    const SORT_DISCOUNT = 6;
    const SORT_NEWEST = 7;
    const SORT_RECOMMENDED = 8;
}

class ProductCategories
{
    const CATEGORY_BABY_N_TODDLERS = '27';
    const CATEGORY_KIDS = '28';
    const CATEGORY_WOMEN = '29';
    const CATEGORY_MATCHING_OUTFITS = '30';
    const CATEGORY_HOME_N_STORAGE = '26';
    public static function all(){
        return [self::CATEGORY_BABY_N_TODDLERS,self::CATEGORY_KIDS,self::CATEGORY_WOMEN,
            self::CATEGORY_MATCHING_OUTFITS,self::CATEGORY_HOME_N_STORAGE];
    }
}

class OrderType
{
    //订单类型:0用户正常下单，1赠品单 2.补发货订单 3.网红赠送单
    const NORMAL = 0;
    const PRESENT = 1;
    const RESEND = 2;
    const CEWEBRITY = 3;
    const PRESELL = 4;
}

class OrderStatus
{
    const NOTPAY = 'notpay';
    const PROCESSING = 'processing';
    const SHIPPED = 'shipped';
    const PARTLYSHIPPED = 'partlyshipped';
    const DELIVERED = 'delivered';
    const CANCELED = 'cancelled';
    const RETURNING = 'returning';
    const RETURNED = 'returned';
    const SETTLED = 'settled';
    const PLACED = 'placed';
    const ASSORTING = "assorting"; //正在配货,这个状态涵盖了仓库那边拣货,分拣,质检
    const PRESHIP = "pre_ship"; //等待发货,发货单创建时的默认状态
    const SENDFORSETTLE = 'sendforsettle'; //已发送了settle请求，但尚为知道settle结果

    /**
     * 用户有以下状态的订单，则说明不是首单，不再享有首单优惠（首单打折，首单减钱）
     * @var array
     */
    public static $invalid_first_order_statuses = array(
        self::PLACED,
        self::SETTLED,
        //self::SETTLE_FAILURE,
//        self::PRE_SHIP,
        self::ASSORTING,
//        self::PARTLY_SHIPPED,
        self::SHIPPED,
        self::DELIVERED,
        //self::CANCELLED,
        self::RETURNING,
        self::RETURNED
    );

    public static $statuses = array(
//        self::NOT_PAY => "Not Pay",
        self::PLACED => 'Placed',//用户下单后，后台捡货单生成之前，应该有一个Cancel按钮取消订单
        self::SETTLED => "Settled",
//        self::SETTLE_FAILURE => "Settle Failure",
//        self::PRE_SHIP => "Pre Ship",
        self::ASSORTING => "Assorting",
//        self::PARTLY_SHIPPED => "Partly Shipped",
        self::SHIPPED => "Shipped",
        self::DELIVERED => "Delivered",
//        self::CANCELLED => "Cancelled",
        self::RETURNING => "Returning",
        self::RETURNED => "Returned",
    );

    public static $cannotRefundStatus = [ //不能退款的订单状态
//        self::NOT_PAY,
        self::PLACED,
//        self::SETTLE_FAILURE
    ];

    public static $wmsCannotCancel = [
//        self::NOT_PAY,
        self::PLACED,
        self::SHIPPED,
        self::DELIVERED,
//        self::CANCELLED,
        self::RETURNING,
        self::RETURNED
    ];

    //customer中只显示以下状态订单
    public static $efficient_statuses = array(
        self::SHIPPED,
        self::DELIVERED,
        self::SETTLED,
//        self::PARTLY_SHIPPED,
    );

    //customer只显示以下几种状态的 orders
    public static $valid_order_statuses = array(
        self::SETTLED,
//        self::PARTLY_SHIPPED,
        self::SHIPPED,
        self::DELIVERED
    );
}

class CartStatus
{
    const NOTPAY = 'Notpay';
    const AUTHORIZED = 'authorized';
}

class CartRecordStatus
{
    const NOTPAY = 'Notpay';
    const DELETED = 'deleted';
}

class OrderSkuCurrentStatus
{
    const CANCELLED = 'cancelled';//订单sku已被取消
    const NOTPAY = 'notpay';
    const DELIVERED = 'delivered';
    const PICKED = 'picked';
    const PLACED = 'placed';
}

/**
 * URL Action的所有类型,参考地址 http://112.74.17.106/projects/server_api/wiki/4_PatPat_Action
 * Class PPURLAction
 * @package App
 */
class PPURLAction
{
    const ProductDetail = 'product_detail';    //进产品详情
    const EventDetail = 'event_detail';        //进event详情
    const Wallet = 'wallet';                   //进wallet
    const ShoppingCart = 'shopping_cart';      //进购物车
    const Orders = 'orders';                   //进订单列表
    const OrderDetail = 'order_detail';        //进订单详情
    const WebPage = 'webpage';                 //打开web网页
    const Tel = 'tel';                         //打电话
    const Email = 'email';                     //发邮件
    const SMS = 'sms';                         //发sms
    const Category = 'category';               //跳转到分类
    const Share = 'share';                     //分享
    const Freebie = 'freebie';                 //0元活动
    const Clearance = 'clearance';             //清仓
    const AppStore = 'appstore';               //appstore
    const DailyDeal = 'dailydeal';             //dailydeal
}

/**
 * featured接口返回的部分类型
 * Class PPFeaturedType
 * @package App
 */
class PPFeaturedType
{
    const Slide = "slide";           //首页滚动栏
    const Category = "category";     //首页分类
    const Dailydeal = "dailydeal";   //首页dailydeal
    const Ambassador = "Ambassador"; //首页大使
    const Flashsale = "flashsale";   //首页闪购
}

class PaymentCreatorMap
{
    const PAYPAL_UNIQUE_KEY_PREFIX = 'paypal_token_';//paypal应的支付方式前缀

}

class DeliveryMethod
{
    /**
     * 标准快递
     */
    const STANDARD = 'Standard';

    /**
     * 特快专递
     */
    const EXPRESS = 'Express';
}

Class SourceType{
    /**
     * 来源
     */
    const SOURCE_IOS = "ios";
    const SOURCE_ANDROID = "android";
    const SOURCE_WEB = "web";
    const SOURCE_WAP = "wap";
    const SOURCE_PWA = "pwa";

}


class ArraySort
{
    /**
     * 获取商品库存时是否处理key
     */
    const KEY_ORIGIN = 0;//按照原来的索引下标
    const KEY_SKU_ID = 1;//以sku_id为数组key返回
}

class TransactionStatus
{
    const AUTHORIZED = 'authorized';
    const CANCELLED = 'cancelled';
    //针对wallet支付状态
    const SUCCESS = 'success';
}

class WalletCreditType
{
    const CASHBACK = 'cashback';//系统返现
    const SPEND = 'spend';//消費
    //针对wallet支付状态
    const INVITE = 'invite';//邀請
    const ACTIVITYCASHBACK = 'activity_cashback';//活動反現
    const EASTERCASHBACK = 'easter_cashback';//復活節返現
}

/**
 * 优惠券使用类型
 *
 * Class VoucherUseType
 */
class VoucherUseType
{
    const CASH = 1;  // 现金券
    const DISCOUNT = 2;  // 折扣券
    const GIFT = 3;  // 免赠券
}

/**
 * 支付方式
 *
 * Class PaymentType
 */
class PaymentType
{
    const PAYMENT_TYPE_PAYPAL = 'paypal';//paypal
    const PAYMENT_TYPE_STRIPE = 'stripe';//stripe
    const ADYEN = 'adyen';
    const APPLEPAY = 'applepay';
    const BRAINTREE = 'braintree';
    const PAYPAL = 'paypal';
    const STRIPE = 'stripe';
    const SAFE_CHARGE = 'safe_charge';
    const COD = 'COD';
    static $cod_countrys = array('Hong Kong','Taiwan','Singapore','Malaysia','Thailand');  //COD允许的国家地区
}

class CouponType
{
    const INVITE = 'invite';//邀请
    const REDUCE_GOLD = 'reduce_gold';//满减
    const FIRST_ORDER_OFF = 'first_order_off';//首单
}



class NotificationType
{
    const RECEIVED = 'received';
    const ACCEPTED = 'accepted';
    const NEW_CASH_BACK = 'new_cash_back';
    const CASH_BACK_ACTIVATED = 'cash_back_activated';
    const INVITE = 'invite';
    const INVITE_ACCEPT = 'inviteaccept';
    const INVITE_FROM = 'invitefrom';
    const INVITE_TO = 'inviteto';
    const INVITE_CANCEL = 'cancel_invite_order';
    const ORDER_PLACED = 'order_placed';
    const ORDER_SETTLED = 'order_settled';
    const ORDER_CANCELLED = 'order_cancelled';
    const ORDER_SHIPPED = 'order_shipped';
    const ORDER_PUSH_REVIEW = 'order_pushreivew';
    const ORDER_DELIVERED = 'order_delivered';
    const ORDER_PUSH_REVIEW_THIRDSECOND = 'order_pushreivew_secondday';
    const ORDER_PUSH_REVIEW_THIRDDAY = 'order_pushreivew_thirdday';
    const BETWEEN_FIRST_TO_TWELFTH = 'between_first_to_twelfth';
    const AFTER_TWELFTH = 'after_twelfth';
    const BEFORE_EVENT_END = 'before_event_end';
    const EVENT = 'event';
    const EASTER_CASH_BACK = 'easter_cash_back';
    const CANCEL_EASTER_CASH_BACK = 'cancel_easter_cash_back';
    const CASH_BACK = 'cash_back';
    const ACTIVITY_CASH_BACK = 'activity_cashback';
    const SYSTEM = 'system';

    protected $order_type = [
        self::ORDER_PLACED,
        self::ORDER_CANCELLED,
        self::ORDER_DELIVERED,
        self::ORDER_PUSH_REVIEW,
        self::ORDER_SETTLED,
        self::ORDER_SHIPPED,
        self::ORDER_PUSH_REVIEW_THIRDDAY,
        self::ORDER_PUSH_REVIEW_THIRDSECOND
    ];
}
