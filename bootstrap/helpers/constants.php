<?php
/**
 * Created by PatPat.
 * User: Bruce.he
 * Date: 2017/7/18
 * Time: 17:08
 * Description: Define constants
 */

const g_POST = 'POST';
const g_GET = 'GET';
const g_PUT = 'PUT';
const g_DELETE = 'DELETE';

/*-------- API response key ---------*/
const g_API_STATUS  = 'status';
const g_API_MSG     = 'msg';
const g_API_CONTENT = 'content';

/*-------- API response export format ---------*/
const g_EXPORT_FORMAT_JSON  = 'json';
const g_EXPORT_FORMAT_XML   = 'xml';

const g_AUTH_REFER = 'api_refer';

const g_CSOURCE = 'api_pp_csource';

const g_SOURCE = 'api_gd_source';

//记录utm的id
const g_UTM_TAG = "api_utm_tag";

//记录adlin的id
const g_ADLINK_ID = "api_adlink_id";

/**-------- API system code ---------**/
//Normal
const g_STATUSCODE_OK = 200;

//incorrect parameter
const g_API_ERROR = -1;
//incorrect version
const g_API_VERSIONINVALID = -2;
//system error
const g_API_SYSTEMERROR = -3;
//incorrect signcode
const g_API_SIGNERROR = -4;
//token expired
const g_API_TOKENEMISSED = 402;
const g_API_TOKENEXPIRED = 403;
const g_API_URL_NOTFOUND = 404;
//服务器错误
const g_API_SERVER_ERROR = 1002;

//评论开始行数
const g_REVIEWS_START_COUNT = 5;
/**
 * page size
 */
const g_PAGECOUNT = 20;

/**
 * 当前分类版本，获取分类用
 */
const CURRENT_CATEGORY_VERSION = 5;

/**
 * 下个分类版本，预览新分类功能用
 */
const NEXT_CATEGORY_VERSION = 6;

/**
 * Get last page url
 * */
define('REFERER_URL', isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : '');	//上一页地址


const g_COUNTRY_ISOCODE = 'api_co_iso';
const g_COUNTRY_ABBREVIATION = '_co_abb';


/*----------------------- start 缓存常量 start --------------------*/
//cache expire time one day 24*60
const g_CACHE_EXPIRETIME = 1440;

//cache expire time temperory
const g_CACHE_TEMPORARY = 5;

//cache registered user expire time 2 hours 2x60
const g_CACHE_REGISTERED_TOKEN_EXPIRE_TIME = 120;

//cache unregistered or app user expire time 24x30 hours
const g_CACHE_UNREGISTERED_TOKEN_EXPIRE_TIME = 43200;

/**
 * 分类数据的缓存
 */
const g_CACHE_CATEGORYS = 'categorys';

/**
 * 用户信息缓存
 */
const g_CACHE_USERINFO = 'userinfo';

/**
 * 轮询信息缓存
 */
const g_CACHE_POLLING = 'pollinginfo';

/**
 * flash sale best sell缓存
 */
const g_BEST_SELLING_FLASH_SALE = 'best_selling_flashsale';

/**
 * 货币信息缓存
 */
const g_CACHE_CURRENCYS= 'country_currencys';

/**
 * 活动信息缓存
 */
const g_CACHE_ACTIVITY= 'activity_';

/**
 * Store Categorys
 */
const g_CACHE_STORE_CATEGORYS= 'store_categorys';

/**
 * New Store Category
 */
const g_CACHE_NEW_STORE_CATEGORYS= 'new_store_categories';

/**
 * Event Categorys
 */
const g_CACHE_EVENT_CATEGORYS= 'event_categorys';


/**
 * 货币国家缓存
 */
const g_CACHE_CURRENCY_COUNTRYS= 'currency_countrys';

/**
 * FlashSale页面的banner
 */
const g_CACHE_FLASHSALE_PAGE_BANNER = 'flashsale_page_banner';

/**
 * Daily Special页面的banner
 */
const g_CACHE_DAILYSPECIALS_PAGE_BANNER = 'daily_special_page_banner';

/**
 * 首页的slides
 */
const g_CACHE_HOME_SLIDES  = 'home_page_slides';

/**
 * 国家h和洲 delivery缓存
 */
const g_CACHE_COUNTRY_AND_STATES = 'country_and_states';


/**
 * 首页闪购活动热销产品列表缓存
 */
const g_HOME_FLASH_SALE_PRODUCT_LIST = 'home_flash_sale_product_list';

/**
 * 首页闪购活动热销产品列表缓存
 */
const g_FLASH_SALE_PAGE_LIST = 'flash_sale_page_list';


const g_ADLINK= 'adlink';

//索引名后缀
const INDEX_NAME_WEBSITE_ORIGIN_CATEGORY_PRODUCTS = 'es_category_products';
//
const CATEGORY_PRODUCTS_INDEX_SUFFIX = 'category_product';
/**
 * 缓存token对应的user id的key
 */
const g_TOKEN_USER_ID = '_user_id';

/**
 * 缓存token对应的过期时间的key
 */
const g_TOKEN_EXPIRE = '_expire';

/*------------- end 缓存常量 end ---------------------*/

/*-------- 用户验证状态吗, 1开头，四位数的状态吗 ---------*/
//Email is registered
const g_STATUSCODE_AUTH_REGISTERED = '1000';

//Email is not registered
const g_STATUSCODE_AUTH_NOREGISTERED = '1004';

//Token is expired
const g_STATUSCODE_AUTH_TOKENEXPIRED = '1001';

//Email and Passowrd is not matched
const g_STATUSCODE_AUTH_MATCHINVALID = '1002';

//Auth parameters error
const g_STATUSCODE_AUTH_VALIDPARAMETERS = '1003';

//Regirster error
const g_STATUSCODE_AUTH_REGISTERERROR = '1005';

//Forgot password error
const g_STATUSCODE_AUTH_FORGOTPASSWORDERROE = '1006';

//Unknown error
const g_STATUSCODE_UNKOWN_ERROR = '1444';

//Subscript email error
const g_STATUSCODE_SUBSCRIPTEMAIL_ERROR = '1007';

//Get currency information error
const g_STATUSCODE_GETCURRENCYINFORMATION_ERROR = '1009';

//Get currency country information error
const g_STATUSCODE_GETCURRENCY_COUNTRY_INFORMATION_ERROR = '1011';

const g_STATUSCODE_PARAMETERS_MUST_BE_NULL = '1010';

//Upper limit on the textBox fields
const g_STATUSCODE_TEXT_UPPER_LIMIT_ERROR = '1010';
/*-------- 用户验证状态吗, 1开头，四位数的状态吗 ---------*/

/**
 * About user info, cookie key
 */
const g_USERTOKEN = 'pp_user_token';
const g_USERID = 'pp_user_id';

const g_STATUSCODE_PAYMENT_SET_DEFAULT_FAIL = '6003';
const g_STATUSCODE_PAYMENT_CANT_DELETE = '6004';
const g_STATUSCODE_PAYMENT_DELETE_FAIL = '6005';

/*-------- Account ---------*/
const g_STATUSCODE_USER_NOT_FOUND = '7001';
const g_STATUSCODE_EMAIL_EXIST = '7002';
const g_STATUSCODE_REVIEW_NOT_FOUND = '7003';
const g_STATUSCODE_COUPON_CODE_NOT_FOUND = '7004';
const g_STATUSCODE_COUPON_CODE_INVALID = '7005';

/*-------- cart ---------*/

const g_STATUSCODE_CART_RECORD_STOCK = '2000';
const g_STATUSCODE_CART_RECORD_LIMITED_FS = '2001';
const g_STATUSCODE_CART_RECORD_LIMITED = '2002';
const g_STATUSCODE_CART_RECORD_INVALID = '2003';
const g_STATUSCODE_CART_RECORD_UNKNOWN = '2004';
const g_STATUSCODE_CART_MOVE_UNSUCCESS = '2005';

/*-------- cart ---------*/

/*-------- cart ---------*/
const g_STATUSCODE_CHECKOUT_ADDRESS = '4000';
/*-------- cart ---------*/

/*-------- risks ---------*/
const g_STATUSCODE_BLASK_USER = '5000';
/*-------- risks ---------*/

/*-------- Submit Order---------*/
const g_STATUSCODE_ARGUE_ERROR = '20000';
const g_STATUSCODE_ORDER_ERROR = '20001';
const g_STATUSCODE_PAY_ERROR = '20002';
const g_STATUSCODE_UNKNOWN_ERROR = '20003';
const g_STATUSCODE_ORDER__NOT_FOUND = '2004';
/*-------- risks ---------*/

/**
 * Upper limit on the textbox fields
 */
const g_EMAIL_MAX_LENGTH = 320;
const g_FIRST_NAME_MAX_LENGTH = 250;
const g_LAST_NAME_MAX_LENGTH = 250;
const g_PASSWORD_MAX_LENGTH = 18;
const g_PASSWORD_MIN_LENGTH = 6;

const g_ORDER_TOEKN_EXPIRE = 50000;


// 序列号类型
const BUSINESS_SN_TYPE_FINANCE = 'BF';
const CLIENT_SN_TYPE_FINANCE = 'CF'; // 客户财务流水
const SN_TYPE_INTEGRAL = 'I'; // 积分流水
const CLIENT_SN_TYPE_TRAN = 'CT'; // 客户交易流水

//积分消费比例
const SYS_INTEGRAL_RATIO = 0.01;

const CYBS_PAY_SESSION_ID = 'CYBS_PAY_SESSION_ID';