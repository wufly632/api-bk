<?php
/**
 * Created by sms.
 * User: Bruce.He
 * Date: 15/11/17
 * Time: 上午1:48
 */

use Illuminate\Support\Facades\Cache;
use Torann\GeoIP\Facades\GeoIP;

/**
 * 判断是否是字符串，空的也返回false
 * @param $str
 * @return bool
 */
function isvalid_string($str)
{
    if (!isset($str) || empty($str)) {
        return false;
    }
    return true;
}

/**
 *当原字符串没设置值时，返回替换字符串
 *
 * @param $origin
 * @param $replace
 * @return mixed
 */
function str_replace_empty($origin, $replace)
{
    if (!isset($origin) || (empty($origin)) && !empty($replace)) {
        return $replace;
    }
    return $origin;
}

/**
 * 检查字字符串是否存在，不在存在就返回空字符串
 * @param $str
 * @return mixed
 */
function str_checkreplace($str)
{
    return str_replace_empty($str, '');
}

/**
 * 解析url的所有参数放到数组里
 * @param $url
 * @return array
 */
function parametersWithURl($url)
{
    if (isvalid_string($url)) {
        $queryString = '';
        $flagPos = strpos($url, '?');
        if ($flagPos) {
            //获取?后面的部分，例如k1=v1&k2=v2部分
            $queryString = substr($url, $flagPos + 1);
        }
        if (isvalid_string($queryString)) {
            $queryParts = explode('&', $queryString); //采取&分割开
            $parameters = [];
            foreach ($queryParts as $p) {
                $items = explode('=', $p);
                if (is_array($items) && count($items) == 2) {
                    $parameters[$items[0]] = $items[1];
                }
            }
            return $parameters;
        }
    }
    return [];
}

/**
 * 从url中获取key的值，例如http://demo.com?key=value&k1=v1，获取k1的值
 * @param $url
 * @param $key
 * @return string
 */
function parameterWithURl($url, $key)
{
    if (isvalid_string($key)) {
        $parameters = parametersWithURl($url);
        if (array_has($parameters, $key)) {
            return $parameters[$key];
        }
    }
    return '';
}

/**
 * round up value, e.g: 12.44->13 12.55->13
 * @param $value
 * @param $places
 * @return float
 */
function round_up($value, $places)
{
    $mult = pow(10, abs($places));
    return $places < 0 ?
        ceil($value / $mult) * $mult :
        ceil($value * $mult) / $mult;
}

/**
 * Check email is valid
 *
 * @param $email
 * @return bool
 */
function is_email($email)
{
    $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
    if (preg_match($pattern, $email)) {
        return true;
    }
    return false;
}

/**
 * 将stdClass Object转array，如果要转的数据量比较大采用array_object_l方法
 *
 * @param $array
 * @return array
 */
function array_object($array)
{
    if (is_object($array)) {
        $array = (array)$array;
    }
    if (is_array($array)) {
        foreach ($array as $key => $value) {
            $array[$key] = array_object($value);
        }
    }
    return $array;
}

/**
 * 将stdClass Object转array， 对json的特性，只能是针对utf8的，否则得先转码下
 *
 * @param $array
 * @return mixed
 */
function array_object_l($array)
{
    $array = json_decode(json_encode($array), true);
    return $array;
}

function array_checkreplace($arr)
{
    if (!isset($arr)) {
        return [];
    }
    return $arr;
}

/**
 * 获取本地化域名信息
 * @return string
 */
function get_en_locale()
{
    return getenv('APP_EN_LOCALE') ?: 'www';
}

/**
 * 金额格式化
 * @param $var
 * @return mixed
 */
function format_money($var)
{
    $varExplode = explode('.', $var);
    if (end($varExplode) == '00') {
        return $varExplode[0];
    } else {
        return $var;
    }
}

/**
 * format price
 * @param $price
 * @return string
 */
function format_price($price, $display_symbol = null, $exchange_rate = null)
{
    if (!isset($display_symbol)) {
        $display_symbol = "$";//currencyDisplaySymbol();
    }
    if (!isset($exchange_rate)) {
        $exchange_rate = 1.0;//currencyExchangeRate();
    }
    $price = fetch_number($price);
    $price = sprintf('%.2f', round(floatval($price) * floatval($exchange_rate), 2));
    return $display_symbol . $price;
}

/** detect mobile */
function is_mobile()
{
    $detect = new Mobile_Detect;
    //isMobile会把ipad也算进去,isTablet判断是否是ipad
    if ($detect->isMobile() && !$detect->isTablet()) {
        return true;
    }
    return false;
}

/**
 * Fetch number from string, e.g: $4.3443 => 4.3443
 *
 * @param $str
 * @return mixed
 */
function fetch_number($str)
{
    return preg_replace('/[^\.0123456789]/s', '', $str);
}

/**
 * 将url进行cdn转换
 * @param $url
 * @param bool $is_https
 * @return \Illuminate\Contracts\Routing\UrlGenerator|mixed|string
 */
function cdn_url($url, $is_https = true)
{
    if (!$url || empty($url)) {
        return $url;
    }
    if (!starts_with($url, 'http') && !starts_with($url, 'https')) {
        $url = ($is_https || is_https()) ? secure_url($url) : url($url);
    }
    if ($is_https && !starts_with($url, 'https')) {
        $url = http_to_https($url);
    }
    $cnd_url = env('SOURCE_CND_URL', '');
    $asset_cnd_url = env('ASSET_CND_URL', '');
    $cdn_infos = [
        "www.patpat.com"                       => $asset_cnd_url,
        "patpatasset.s3.amazonaws.com"         => $asset_cnd_url,
        "patpatdev.img.patpat.com"             => $cnd_url,
        "patpatdev.s3.amazonaws.com"           => $cnd_url,
        "patpatdev.s3-us-west-1.amazonaws.com" => $cnd_url,
        "patpatdev.s3.us-west-1.amazonaws.com" => $cnd_url,
        "s3-us-west-1.amazonaws.com"           => $cnd_url,
    ];
    foreach (array_keys($cdn_infos) as $index => $origin_host) {
        if (str_contains($url, $origin_host) && !empty($cdn_infos[$origin_host])) {
            $url = str_replace($origin_host, $cdn_infos[$origin_host], $url);
            break;
        }
    }
    return $url;
}

/**
 * 判断当前是否https请求
 * @return bool
 */
function is_https()
{
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        return true;
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        return true;
    } elseif (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
        return true;
    }
    return false;
}

/**
 * http转换成https
 * @param $url
 * @return mixed
 */
function http_to_https($url)
{
    return preg_replace("/^http:/i", "https:", $url);
}

/**
 * 字符串中的特殊字符处理
 * @param $str
 * @return mixed
 */
function trim_all($str)
{
    $front = array(" ", "　", "\t", "\n", "\r");
    $behind = array("", "", "", "", "");
    return str_replace($front, $behind, $str);
}

function check_if_from_app()
{
    $bool = false;
    $host = request()->url();
    $sub_domain = \Illuminate\Support\Facades\Config::get('app.new_api_domain');
    if (str_contains($host, $sub_domain)) {
        $bool = true;
    }
    return $bool;
}

function get_abb()
{
    return request()->get('abb', 'en');
}

/**
 * 转变商品尺码颜色属性
 */
function tran_product_option($option, $language_id)
{
    if ($language_id != 1) {
        $option_txt = preg_replace('/(^:|: |-| |:)/', "_", strtolower($option));
        $option_txt = trans("sizeColor.$option_txt", [], 'messages', get_abb());
        if (strpos($option_txt, 'sizeColor.') !== false) {
            $option_txt = $option;
        }
    } else {
        $option_txt = $option;
    }
    return $option_txt;
}

function tran_checkout_option($option, $params = [])
{
    $option_txt = trans("checkout.$option", $params, 'messages', get_abb());
    if (strpos($option_txt, 'checkout.') !== false) {
        $option_txt = trans("checkout.$option", $params, 'messages', 'en');
    }
    return $option_txt;
}

/**
 * 转变分类名称属性
 */
function tran_category_name($option, $language_id)
{
    if ($language_id != 1) {
        $option_txt = trans("category.$option", [], 'message', get_abb());
        if (strpos($option_txt, 'category.') !== false) {
            $option_txt = $option;
        }
    } else {
        $option_txt = $option;
    }
    return $option_txt;
}


/**
 * 转化活动优惠
 */
function tran_event_promotion($option, $params = [])
{
    return trans("event.$option", $params, 'messages', get_abb());
}

function get_sort_types()
{
    $abb = get_abb();
    return [
        ProductSortTypes::SORT_PRICE_L2H    => trans('product.' . 'Price (Low to High)', [], 'messages', $abb),
        //按照价格排序（从低到高）
        ProductSortTypes::SORT_PRICE_H2L    => trans('product.' . 'Price (High to Low)', [], 'messages', $abb),
        //按照价格排序（从高到低）
        ProductSortTypes::SORT_BEST_SELLING => trans('product.' . 'Best Selling', [], 'messages', $abb),
        //按照销量排序
        ProductSortTypes::SORT_NEWEST       => trans('product.' . 'Newest', [], 'messages', $abb),
        //新品排序
        ProductSortTypes::SORT_RECOMMENDED  => trans('product.' . 'Recommended', [], 'messages', $abb),
        //按照推荐排序
    ];
}

function get_sort_types_data()
{
    $sortTypes = get_sort_types();
    array_walk($sortTypes, function (&$item, $key) {
        $item = ['key' => $key, "value" => $item];
    });
    return array_merge($sortTypes);
}

function get_search_sort_types_data()
{
    $sortTypes = get_sort_options(false, true);
    array_walk($sortTypes, function (&$item, $key) {
        $item = ['key' => $key, "value" => $item];
    });
    return array_merge($sortTypes);
}

function get_sort_options($is_category = false, $isSearch = false)
{
    $abb = get_abb();
    if ($isSearch) {
        return [
            ProductSortTypes::SORT_BEST_MATCH   => trans('product.' . 'Best Match', [], 'messages', $abb),
            ProductSortTypes::SORT_BEST_SELLING => trans('product.' . 'Best Selling', [], 'messages', $abb),
            ProductSortTypes::SORT_RECOMMENDED  => trans('product.' . 'Recommended', [], 'messages', $abb),
            ProductSortTypes::SORT_NEWEST       => trans('product.' . 'Newest', [], 'messages', $abb),
            ProductSortTypes::SORT_PRICE_L2H    => trans('product.' . 'Price (Low to High)', [], 'messages', $abb),
            ProductSortTypes::SORT_PRICE_H2L    => trans('product.' . 'Price (High to Low)', [], 'messages', $abb),
        ];
    }
    if (!$is_category) {
        return [
            ProductSortTypes::SORT_PRICE_L2H    => trans('product.' . 'Price (Low to High)', [], 'messages', $abb),
            ProductSortTypes::SORT_PRICE_H2L    => trans('product.' . 'Price (High to Low)', [], 'messages', $abb),
            ProductSortTypes::SORT_BEST_SELLING => trans('product.' . 'Best Selling', [], 'messages', $abb),
            ProductSortTypes::SORT_NEWEST       => trans('product.' . 'Newest', [], 'messages', $abb),
            ProductSortTypes::SORT_RECOMMENDED  => trans('product.' . 'Recommended', [], 'messages', $abb),
        ];
    } else {
        return [
            ProductSortTypes::SORT_PRICE_L2H    => trans('product.' . 'Price (Low to High)', [], 'messages', $abb),
            ProductSortTypes::SORT_PRICE_H2L    => trans('product.' . 'Price (High to Low)', [], 'messages', $abb),
            ProductSortTypes::SORT_BEST_SELLING => trans('product.' . 'Best Selling', [], 'messages', $abb),
            ProductSortTypes::SORT_NEWEST       => trans('product.' . 'Newest', [], 'messages', $abb),
            ProductSortTypes::SORT_RECOMMENDED  => trans('product.' . 'Recommended', [], 'messages', $abb),
        ];
    }
}

function get_user_id()
{
    $token = request()->get('token');
    $user_id_key = $token;
    $user_info = Cache::get($user_id_key);
    $user_info = json_decode($user_info);
    $user_id = 0;
    if ($user_info) {
        $user_id = $user_info->id;
    }
    return $user_id;
}

/**API对应的是token****/
function get_unqiue_sessionid()
{
    $token = request()->get('token');
    if (!Cache::has('laravel_unique_session') && !empty($token)) {
        $laravel_unique_token = $token;
        Cache::put(Cache::forever('laravel_unique_session', $laravel_unique_token));
    } else {
        $token = Cache::get('laravel_unique_session', '');
    }
    return $token;
}

function generate_track_data($params)
{
    if ($params) {
        $params = json_decode($params, true);
    } else {
        $params = [];
    }
    $data = [];
    $adlink_Id = Cache::get(g_ADLINK_ID, '');
    //如果adlink_id不存在就不要记录
    if (!isset($adlink_Id) || strlen($adlink_Id) < 1) {
        $adlink_Id = '';
    }
    if ($adlink_Id) {
        $data['adlink_id'] = (string)$adlink_Id;
    }
    $data = array_merge($params, $data);
    if ($data) {
        $data = json_encode($data);
    } else {
        $data = '';
    }
    return $data;
}

/*
 * 删除url的参数，和scheme，只保留例如wwww.patpat.com，www.patpat.com/home
 * */
function parse_url_delete_parameters($url)
{
    try {
        $url = urldecode($url);
        $urlItems = parse_url($url);
        if ($urlItems) {
            $host = $urlItems['host'];
            $path = isset($urlItems['path']) ? $urlItems['path'] : "";
            $lastChars = substr($path, strlen($path) - 1, strlen($path));
            if ($lastChars == '/') {
                $path = substr($path, 0, strlen($path) - 1);
            }
            return $host . $path;
        } else { //对严重不合格的 URL，parse_url() 可能会返回 FALSE。
            return '';
        }
    } catch (\Exception $exception) {
        return '';
    }
}

/** 替换域名
 * @param $href
 * @return null|string|string[]
 */
function href_replace_domain($href)
{
    return preg_replace('/(http|https):\/\/([^\/]+)/i', '', $href);
}


function is_production()
{
    if (env('APP_ENV') == 'production') {
        return true;
    }
    return false;
}

/**
 * 将action转url
 *
 * @param $records
 * @return mixed
 */
function convert_action_to_urls($records)
{
    $records = array_map(function ($record) {
        $record->url = action_to_http_url($record->action);;
        $record->icon = cdn_url($record->icon);
        return $record;
    }, $records);
    return $records;
}

/**
 * 将action转换为url，规则见: http://112.74.17.106/projects/server_api/wiki/4_PatPat_Action
 * @param $action
 * @return string
 */
function action_to_http_url($actionUrl)
{
    $httpUrl = '';
    //只处理来自patpat://action开头的字符串，其他的直接过滤
    if (!starts_with($actionUrl, 'patpat://?action')) {
        return $httpUrl;
    }
    $action = parameterWithURl($actionUrl, 'action');
    switch ($action) {
        case PPURLAction::Wallet:
            $httpUrl = route('wallets');
            break;
        case PPURLAction::OrderDetail:
            $order_id = parameterWithURl($actionUrl, "order_id");
            if (isset($order_id)) {
                $httpUrl = route('orders.details', ['order_id' => $order_id]);
            }
            break;
        case PPURLAction::EventDetail:
            $event_id = parameterWithURl($actionUrl, "event_id");
            $httpUrl = route('showEventDetailById', ['type' => PPFeaturedType::Flashsale, 'id' => $event_id]);
            break;
        case PPURLAction::Freebie:
            $event_id = parameterWithURl($actionUrl, "event_id");
            $product_id = parameterWithURl($actionUrl, "product_id");
            $httpUrl = route('showProductDetailsById', ['eventId' => $event_id, 'productId' => $product_id]);
            break;
        case PPURLAction::DailyDeal:
            $event_id = parameterWithURl($actionUrl, "event_id");
            $product_id = parameterWithURl($actionUrl, "product_id");
            $httpUrl = route('showProductDetailsById', ['eventId' => $event_id, 'productId' => $product_id]);
            break;
        case PPURLAction::ProductDetail:
            $event_id = parameterWithURl($actionUrl, "event_id");
            $product_id = parameterWithURl($actionUrl, "product_id");
            $httpUrl = route('showProductDetailsById', ['eventId' => $event_id, 'productId' => $product_id]);
            break;
        case PPURLAction::WebPage:
            $httpUrl = parameterWithURl($actionUrl, "url");
            break;
    }
    return $httpUrl;
}

// 过滤掉emoji表情
function filterEmoji($str)
{
    $str = preg_replace_callback(
        '/./u',
        function (array $match) {
            return strlen($match[0]) >= 4 ? '' : $match[0];
        },
        $str);
    return $str;
}

function get_credit_card_logo($card_type)
{
    switch ($card_type) {
        case "Visa":
            $logo = asset(cdn_url(asset("/assets/img/credit_card_logo/visa.png")));
            break;
        case "American Express":
            $logo = asset(cdn_url(asset("/assets/img/credit_card_logo/amex.png")));
            break;
        case "Diners Club":
            $logo = asset(cdn_url(asset("/assets/img/credit_card_logo/diners_club.png")));
            break;
        case "Discover":
            $logo = asset(cdn_url(asset("/assets/img/credit_card_logo/discover.png")));
            break;
        case "JCB":
            $logo = asset(cdn_url(asset("/assets/img/credit_card_logo/jcb.png")));
            break;
        case "MasterCard":
            $logo = asset(cdn_url(asset("/assets/img/credit_card_logo/mastercard.png")));
            break;
        case "PayPal":
            $logo = asset(cdn_url(asset("/assets/img/credit_card_logo/paypal.png")));
            break;
        case "ApplePay":
            $logo = asset(cdn_url(asset("/assets/img/credit_card_logo/apple_pay.png")));
            break;
        default:
            $logo = asset(cdn_url(asset("/assets/img/credit_card_logo/visa.png")));
            break;
    }
    return $logo;
}

function get_font_color($color)
{
    $color_value = '68,68,68';
    $font_color = [
        'black' => '68,68,68',
        'green' => '70,186,151',
        'red'   => '241,67,90',
        'white' => '255,255,255'
    ];
    if (isset($font_color[$color])) {
        $color_value = $font_color[$color];
    }
    return $color_value;
}

function get_credit_card_type()
{
    return [
        "American Express",
        "Diners Club",
        "Discover",
        "MasterCard",
        "Visa",
        "JCB",
        "Maestro",
        "UnionPay",
        "Unknown"
    ];
}

function std_class_object_to_array($stdclassobject)
{
    $_array = is_object($stdclassobject) ? get_object_vars($stdclassobject) : $stdclassobject;

    foreach ($_array as $key => $value) {
        $value = (is_array($value) || is_object($value)) ? std_class_object_to_array($value) : $value;
        $array[$key] = $value;
    }

    return $array;
}

function get_time_difference($startDate, $endDate)
{
    $data = array();
    $count_down = '';
    if ($startDate && $endDate) {
        $time = strtotime($endDate) - strtotime($startDate);
        $data['d'] = floor($time / 86400);
        $time -= $data['d'] * 60 * 60 * 24;
        $data['h'] = floor($time / 60 / 60);
        $time -= $data['h'] * 60 * 60;
        $data['m'] = floor($time / 60);
        $time -= $data['m'] * 60;
        $data['s'] = $time;
        $count_down = // ($data['d']?:'').":".
            str_pad((($data['d'] ? $data['d'] * 24 : '') + $data['h'] ?: ''), 2, '0', STR_PAD_LEFT) . ":" .
            str_pad(($data['m'] ?: ''), 2, '0', STR_PAD_LEFT) . ":" .
            str_pad(($data['s'] ?: ''), 2, '0', STR_PAD_LEFT);
    }
    return $count_down;
}

function get_card_type_by_number($number)
{
    $number = preg_replace('/[^\d]/', '', $number);
    if (preg_match('/^3[47][0-9]{13}$/', $number)) {
        return 'American Express';
    } elseif (preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/', $number)) {
        return 'Diners Club';
    } elseif (preg_match('/^6(?:011|5[0-9][0-9])[0-9]{12}$/', $number)) {
        return 'Discover';
    } elseif (preg_match('/^(?:2131|1800|35\d{3})\d{11}$/', $number)) {
        return 'JCB';
    } elseif (preg_match('/^5[1-5][0-9]{14}$/', $number)) {
        return 'MasterCard';
    } elseif (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $number)) {
        return 'Visa';
    } else {
        return 'Unknown';
    }
}

function get_call_user_counrty()
{
    $ip_address = getIP();
    $ip_address = trim($ip_address);
    $ip_location = GeoIP::getLocation($ip_address);
    $country = 'United States';
    if (isset($ip_location['country'])) {
        $country = $ip_location['country'];
    }
    return $country;
}

function secure_route($route_name, $parameters = [])
{
    if (!env('APP_DEBUG')) {
        return http_to_https(route($route_name, $parameters));
    } else {
        return route($route_name, $parameters);
    }
}

/**
 * str to url str
 *
 * @param $str
 * @return mixed|string
 */
function replace_special_char($str)
{
    $str = trim($str);
    $str = str_replace(' ', '-', $str);
    $str = str_replace('/', '-', $str);
    $str = str_replace("'", '', $str);
    $str = str_replace("(", '', $str);
    $str = str_replace(")", '', $str);
    $str = str_replace("?", '', $str);
    $str = str_replace("#", '-', $str);
    $str = str_replace("%", '', $str);
    $str = str_replace(".", '', $str);
    $str = str_replace("&", '-', $str);
    $str = preg_replace('/-+/', '-', $str);
    return $str;
}

/**
 * 通过价格计算满减的金额
 */
function getReduce($price, $promotions)
{
    //满减规则
    $rule = isset($promotions->rule) ? $promotions->rule : '';
    if (empty($rule)) {
        return false;
    }
    $rule = json_decode($rule);
    //是否满足活动要求
    $isSatisfy = false;
    //满减金额
    $reduce = '0.00';
    //满减说明
    $reduceMsg = '';
    //满减内容
    $reduceContent = '';
    foreach ($rule as $val) {
        //如果购买的价格>=满减金额
        if ($price >= $val->money) {
            $tmp = floor($price / $val->money) * $val->reduce;
            if ($tmp > $reduce) {
                $reduce = $tmp;
            }
            $reduceContent = 'BUY $' . round($val->money * floor($price / $val->money), 2) . ' GET ' . $reduce . ' OFF';
            $isSatisfy = true;
        } else {
            //$reduceMsg = '再购¥'.bcsub($val->money, $price, 2).',可享受【满'.$val->money.'元减'.$val->reduce.'元】';
            $reduceMsg = 'More $' . round($val->money - $price, 2) . ' to get $' . $val->reduce . 'off';
            break;
        }
    }
    if (!$reduceMsg && $reduceContent) {
        //$reduceMsg = '已享受【'.$reduceContent.'】';
        $reduceMsg = "You've saved $" . $reduce;
    }

    return array('reduce' => $reduce, 'reduceMsg' => $reduceMsg, 'isSatisfy' => $isSatisfy);
}

/**
 * 通过价格计算满返的金额
 */
function getReturn($price, $promotions)
{
    //消费金额
    $consumeAmount = isset($promotions->consume) ? $promotions->consume : 0;
    //满返规则
    $rule = isset($promotions->rule) ? $promotions->rule : '';
    if (empty($rule)) {
        return false;
    }
    $rule = json_decode($rule);
    //是否满足活动要求
    $isSatisfy = false;
    //满返说明
    $returnMsg = '';
    //如果购买的价格<满返金额
    if ($price < $consumeAmount) {
        //$returnMsg = '再购¥'.bcsub($consumeAmount, $price, 2).',可享受【满'.$consumeAmount.'元返'.$rule->value.'元现金券】';
        $returnMsg = 'More $' . round($consumeAmount - $price, 2) . ' to receive $' . $rule->value . ' back';
    } else {
        //$returnMsg = '已享受【满'.$consumeAmount.'元返'.$rule->value.'元现金券】';
        $returnMsg = "You've received $" . $rule->value . " back.";
        $isSatisfy = true;
    }

    return array('returnMsg' => $returnMsg, 'isSatisfy' => $isSatisfy);
}

/**
 * 通过促销商品数量、价格计算多件多折优惠金额
 */
function getDiscount($goodTotal, $price, $promotions)
{
    //多件多折规则
    $rule = isset($promotions->rule) ? $promotions->rule : '';
    if (empty($rule)) {
        return false;
    }
    $rule = json_decode($rule);
    //是否满足活动要求
    $isSatisfy = false;
    $discountNum = 0;
    //折扣金额
    $discount = '0.00';
    //多件多折说明
    $discountMsg = '';
    //多件多折内容
    $discountContent = '';
    foreach ($rule as $val) {
        //如果购买的数量>=多件数量
        if ($goodTotal >= $val->num) {
            $discount = round($price - round($price * $val->discount / 10, 2), 2);
            //$discountContent = '满'.$val->num.'件'.$val->discount.'折';
            $discountNum = round(round(10 - $val->discount, 2) * 10, 0);
            $discountContent = "BUY " . $val->num . ' GET ' . $discountNum . '% OFF';
            $isSatisfy = true;
        } else {
            //$discountMsg = '再购'.bcsub($val->num, $goodTotal, 0).'件,可享受【满'.$val->num.'件'.$val->discount.'折】';
            $discountMsg = "Buy " . round($val->num - $goodTotal, 0) . " peice get " . round(round(10 - $val->discount,
                        2) * 10, 0) . "% off";
            break;
        }
    }
    if (!$discountMsg && $discountContent) {
        //$discountMsg = '已享受【'.$discountContent.'】';
        $discountMsg = "You've earned $discountNum% OFF";
    }

    return array('discount' => $discount, 'discountMsg' => $discountMsg, 'isSatisfy' => $isSatisfy);
}

/**
 * 通过促销商品数量、促销商品价格和购买数量计算X元n件优惠金额
 */
function getWholesale($goodTotal, $prices, $promotionsGood, $promotions)
{
    $price = array();
    //参加X元n件促销活动的商品价格和购买数量
    if (!is_array($promotionsGood)) {
        return false;
    }
    foreach ($promotionsGood as $good) {
        for ($i = 0; $i < $good['num']; $i++) {
            $price[] = $good['price'];
        }
    }

    //X元n件规则
    $rule = isset($promotions->rule) ? $promotions->rule : '';
    if (empty($rule)) {
        return false;
    }
    $rule = json_decode($rule);
    //是否满足活动要求
    $isSatisfy = false;
    //优惠金额
    $wholesale = '0.00';
    //X元n件说明
    $wholesaleMsg = '';
    //获取计算出来的最大的优惠金额
    $wholesaleMax = array();
    foreach ($rule as $key => $val) {
        //规则价格,每循环一次重获取价格一次
        $rulePrice = $price;
        //如果购买的数量<n件
        if ($goodTotal < $val->wholesale) {
            //$wholesaleMsg = '再购'.bcsub($val->wholesale, $goodTotal, 0).'件,可享受【'.$val->money.'元任选'.$val->wholesale.'件】';
            $wholesaleMsg = "Add " . round($val->wholesale - $goodTotal,
                    0) . "more for “ANY " . $val->wholesale . ' FOR $' . $val->money . '”';
            break;
        } else {
            //如果购买的数量=n件
            if ($goodTotal == $val->wholesale) {
                $wholesaleMax[] = round($prices - $val->money, 2);
                //$wholesaleMsg = '已享受【'.$val->money.'元任选'.$val->wholesale.'件】';
                $wholesaleMsg = 'Enjoyed “ANY ' . $val->wholesale . ' FOR $' . $val->money . '”';
                $isSatisfy = true;
            }
            //如果购买的数量>n件
            if ($goodTotal > $val->wholesale) {
                //如果能被整除
                if ($goodTotal % $val->wholesale == 0) {
                    $wholesaleMax[] = round($prices - round($val->money * floor($goodTotal / $val->wholesale), 2), 2);
                    //$wholesaleMsg = '已享受【'.$val->money.'元任选'.$val->wholesale.'件】';
                    $wholesaleMsg = 'Enjoyed “ANY ' . $val->wholesale . ' FOR $' . $val->money . '”';
                    $isSatisfy = true;
                } else {//如果不能整除
                    //获取余数
                    $remainder = $goodTotal % $val->wholesale;
                    //获取要减掉的最小价格的商品的价格(如果余数为1,就减掉1个最小价格的商品;如果余数为2,就减掉2个最小价格的商品)
                    $outPrice = 0;
                    //根据余数获取最小价格商品
                    for ($j = 0; $j < $remainder; $j++) {
                        //获取最小值的键名(array_search函数在数组中搜索某个键值，并返回对应的键名)
                        $pos = array_search(min($rulePrice), $rulePrice);
                        $outPrice = round($outPrice + $rulePrice[$pos], 2);
                        unset($rulePrice[$pos]);
                    }
                    //减掉余数个最小价格的商品，用剩余的价格计算优惠金额
                    $remainderPrice = round($prices - $outPrice, 2);
                    $wholesaleMax[] = round($remainderPrice,
                        round($val->money * floor(count($rulePrice) / $val->wholesale), 2), 2);
                    //$wholesaleMsg = '已享受【'.$val->money.'元任选'.$val->wholesale.'件】';
                    $wholesaleMsg = 'Enjoyed “ANY ' . $val->wholesale . ' FOR $' . $val->money . '”';
                    $isSatisfy = true;
                }
            }
        }
    }
    //最终获取优惠金额
    if ($wholesaleMax) {
        $wholesale = max($wholesaleMax);
    }

    return array('wholesale' => $wholesale, 'wholesaleMsg' => $wholesaleMsg, 'isSatisfy' => $isSatisfy);
}

/**
 * 通过购买数量计算买n免一优惠金额
 */
function getGive($goodTotal, $promotionsGood, $promotions)
{
    $price = array();
    //参加买n免一促销活动的商品价格和购买数量
    if (!is_array($promotionsGood)) {
        return false;
    }
    foreach ($promotionsGood as $good) {
        for ($i = 0; $i < $good['num']; $i++) {
            $price[] = $good['price'];
        }
    }
    //按照键值对关联数组进行降序排序
    arsort($price);
    //如果您仅向array_merge()函数输入一个数组,且键名是整数,则该函数将返回带有整数键名的新数组,其键名以0开始进行重新索引
    $price = array_merge($price);

    //买n免一规则
    $rule = isset($promotions->rule) ? $promotions->rule : '';
    if (empty($rule)) {
        return false;
    }
    //是否满足活动要求
    $isSatisfy = false;
    //优惠金额
    $give = '0.00';
    //X元n件说明
    $giveMsg = '';
    //获取余数为n-1的商品价格
    $giveMin = array();
    //如果购买的数量<n件
    if ($goodTotal < $rule) {
        //$giveMsg = '再购'.bcsub($rule, $goodTotal, 0).'件,可享受【买'.$rule.'免1】';
        $giveMsg = "More " . round($rule - $goodTotal, 0) . " get " . $rule . ' free';
    } else {
        //如果购买的数量=n件
        if ($goodTotal == $rule) {
            //$giveMsg = '已享受【买'.$rule.'免1】';

            $giveMsg = "Already got {$rule} free";
            $give = min($price);
            $isSatisfy = true;
        }
        //如果购买的数量>n件
        if ($goodTotal > $rule) {
            foreach ($price as $key => $value) {
                //如果键值余数等于n-1,就获取价格
                if (bcmod($key, $rule) == round($rule - 1, 0)) {
                    $giveMin[] = $value;
                }
            }
            $give = array_sum($giveMin);
            //$giveMsg = '已享受【买'.bcmul($rule, bcdiv($goodTotal, $rule, 0), 0).'免'.bcmul(1, bcdiv($goodTotal, $rule, 0), 0).'】';
            $giveMsg = "Already got " . round(1 * round($goodTotal / $rule, 0), 0) . " free";
            $isSatisfy = true;
        }
    }
    return array('give' => $give, 'giveMsg' => $giveMsg, 'isSatisfy' => $isSatisfy);
}

/**
 * 生成券码
 */
function makeCouponCode($coupon_id)
{
    $length = 11; //总长11位数
    $couponIdLen = strlen($coupon_id);//券ID位数
    $couponLen = bcsub($length, bcadd($couponIdLen, 1, 0), 0); //随机数长度=总长-(券ID位数+1).1表示券ID位数(目前只有个位数)

    $str = '';
    $couponStr = '';
    $pool = '0123456789';
    for ($i = 0; $i < $couponLen; $i++) {
        $str .= substr($pool, mt_rand(0, strlen($pool) - 1), 1);
    }
    $couponStr = $couponIdLen . $coupon_id . $str;

    return $couponStr;
}

/**
 * 生成唯一性字符串，长度7
 * hostname后4位
 * 进程号后3位（不足3位前面补0）
 * @return string
 */
function uniq_string()
{
    $hostname = gethostname();
    $pid = getmypid();
    $uniq = substr($hostname, -4, 4) . sprintf("%03d", $pid % 1000);

    return $uniq;
}

/**
 * 毫秒时间戳，长度13
 * @return string
 */
function timestamp_ms()
{
    $ts = date('ymdHis') . sprintf("%03d", floor(microtime(true) * 1000) % 1000);

    return $ts;
}

/**
 * 商家财务流水号
 * @param number $uid
 * @param string $subtype
 * @return string
 */
function business_finance_sn($uid, $subtype)
{
    $ts = timestamp_ms();
    $uniq = uniq_string();
    $uniq = str_replace('-', '0', $uniq);
    $code = rand_string(6, 5);

    $sn = implode('', array(BUSINESS_SN_TYPE_FINANCE, $uid, $subtype, $ts, $code));
    return $sn;
}

/**
 * 用户财务流水号
 * @param number $uid
 * @param string $subtype
 * @return string
 */
function client_finance_sn($uid, $subtype)
{
    $ts = timestamp_ms();
    $uniq = uniq_string();
    $uniq = str_replace('-', '0', $uniq);
    $code = rand_string(6, 5);

    $sn = implode('', array(CLIENT_SN_TYPE_FINANCE, $uid, $subtype, $ts, $code));
    return $sn;
}

/**
 * 积分流水号
 * @param number $uid
 * @param string $subtype
 * @return string
 */
function account_sn($uid)
{
    $ts = timestamp_ms();
    $uniq = uniq_string();
    $uniq = str_replace('-', '0', $uniq);
    $code = rand_string(6, 5);

    $sn = implode('', array(SN_TYPE_INTEGRAL, $uid, $ts, $code));
    return $sn;
}

/**
 * 交易流水号
 * @param number $uid
 * @param string $subtype
 * @return string
 */
function tran_sn($uid, $subtype)
{
    $ts = timestamp_ms();
    $uniq = uniq_string();
    $uniq = str_replace('-', '0', $uniq);
    $code = rand_string(6, 5);

    $sn = implode('', array(CLIENT_SN_TYPE_TRAN, $uid, $subtype, $ts, $code));
    return $sn;
}

/**
 * 生成订单号
 * @param int $uid
 * @param int $type 单订来源，1：PC端，2：H5，3：小程序，4：APP，5：门店
 *
 * @return string
 */
function order_sn($uid, $type)
{
    static $year_code = array('1', '2', '3', '4', '5', '6', '7', '8', '9');
    $now = time();

    $day_diff = date('z', $now) + 1;
    $day_diff = str_pad($day_diff, 3, '0', STR_PAD_LEFT);

    $uid_4 = substr($uid, -4);
    $uid_4 = str_pad($uid_4, 4, '0', STR_PAD_LEFT);

    $second_diff = $now - strtotime(date('Y-m-d 0:0:0', $now));
    $second_diff = str_pad($second_diff, 5, '0', STR_PAD_LEFT);

    $order_sn = $type . $uid_4 . $year_code[intval(date('Y')) - 2017] . $day_diff . $second_diff . substr(microtime(),
            2, 5);
    return $order_sn;
}

/**
 * @param $uid int 用户id
 * @param $pid int 拼团id
 * @param $type int 订单来源，1：PC端，2：H5，3：小程序，4：APP，5：门店
 *
 * @return string 拼团订单号
 */
function pin_order($uid, $pid, $type)
{
    $now = time();

    $uid_4 = substr($uid, -4);
    $uid_4 = str_pad($uid_4, 4, '0', STR_PAD_LEFT);

    $pid_4 = substr($pid, -4);
    $pid_4 = str_pad($pid_4, 4, '0', STR_PAD_LEFT);

    $pin_order_sn = $type . $now . substr(microtime(), 2, 5) . $pid_4 . $uid_4;
    return $pin_order_sn;
}


/**
 * +----------------------------------------------------------
 * 产生随机字串，可用来自动生成密码
 * 默认长度6位 字母和数字混合 支持中文
 * +----------------------------------------------------------
 * @param string $len 长度
 * @param string $type 字串类型
 * 0 字母 1 数字 其它 混合
 * @param string $addChars 额外字符
 * +----------------------------------------------------------
 * @return string
 * +----------------------------------------------------------
 */
function rand_string($len = 6, $type = '', $addChars = '')
{
    $str = '';
    switch ($type) {
        case 0 :
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789' . $addChars;
            break;
        case 1 :
            $chars = str_repeat('0123456789', 3);
            break;
        case 2 :
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' . $addChars;
            break;
        case 3 :
            $chars = 'abcdefghijklmnopqrstuvwxyz' . $addChars;
            break;
        case 5 :
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' . $addChars;
            break;
        default :
            // 默认去掉了容易混淆的字符oOLl和数字01，要添加请使用addChars参数
            $chars = 'ABCDEFGHIJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz0123456789' . $addChars;
            break;
    }
    if ($len > 10) { //位数过长重复字符串一定次数
        $chars = $type == 1 ? str_repeat($chars, $len) : str_repeat($chars, 5);
    }
    if ($type != 4) {
        $chars = str_shuffle($chars);
        $str = substr($chars, 0, $len);
    } else {
        // 中文随机字
        for ($i = 0; $i < $len; $i++) {
            $str .= mb_substr($chars, floor(mt_rand(0, mb_strlen($chars, 'utf-8') - 1)), 1);
        }
    }
    return $str;
}

/**
 * 获取邀请码
 */
function getInviteCode($count = 12)
{
    $str = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $word = '';
    for ($i = 0; $i < $count; $i++) {
        $word .= $str[mt_rand(0, strlen($str) - 1)];
    }
    return $word;
}

/**
 * http转换成https
 * @param $url
 * @return mixed
 */
function httpToHttps($url)
{
    return preg_replace("/^http:/i", "https:", $url);
}

/**
 * @function cdn加速
 * @param $url
 * @param bool $is_https
 * @return \Illuminate\Contracts\Routing\UrlGenerator|mixed|string
 */
function cdnUrl($url, $is_https = true)
{
    if (!$url || empty($url) || env('APP_ENV', 'local') == 'local') {
        return $url;
    }

    $cdn_image_url = env('CDN_IMAGE_URL', '');
    $cdn_skins_url = env('CDN_SKINS_URL', '');
    $cdn_infos = [
        "weiweimao-image.oss-ap-south-1.aliyuncs.com" => $cdn_image_url,
        "cucoe.oss-us-west-1.aliyuncs.com"            => $cdn_image_url,
        "admin.waiwaimall.com"                        => $cdn_skins_url,
        "seller.waiwaimall.com"                       => $cdn_skins_url,
        "images.waiwaimall.com"                       => $cdn_image_url,
    ];

    foreach (array_keys($cdn_infos) as $index => $origin_host) {
        if (str_contains($url, $origin_host) && !empty($cdn_infos[$origin_host])) {
            if (!starts_with($url, 'http://') && !starts_with($url, 'https://')) {
                $url = $is_https ? secure_url($url) : url($url);
            } elseif (starts_with($url, 'http://') && $is_https) {
                $url = 'https://' . ltrim($url, 'http://');
            }
            $url = httpToHttps(str_replace($origin_host, $cdn_infos[$origin_host], $url));
            break;
        }
    }
    return $url;
}

/**
 * @function 记录日志
 * @param $log
 * @param bool $error
 */
function wwlog($log, $error = false)
{
    $month = \Carbon\Carbon::today()->format('Ym');
    $method = $error ? 'error' : 'info';
    $logname = env('LOG_NAME', $error ? 'error' : 'info');
    \App\Assistants\CLogger::getLogger($logname, $month)->$method($log);
}

/**
 * @function 记录错误日志
 * @param $log
 */
function wwerror($log)
{
    wwlog($log, true);
}


