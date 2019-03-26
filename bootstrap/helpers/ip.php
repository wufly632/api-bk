<?php
/**
 * Created by waiwaimall
 * User: long.hao
 * Date: 16/3/5
 * Time: 下午5:54
 */

function ip2long_v6($ip) {
    if(!$ip || empty($ip) || strlen($ip) < 1){
        return $ip;
    }
    try{
        $ip_n = inet_pton($ip);
    }catch (Exception $e){
        return '0';
    }
    $bin = '';
    for ($bit = strlen($ip_n) - 1; $bit >= 0; $bit--) {
        $bin = sprintf('%08b', ord($ip_n[$bit])) . $bin;
    }

    if (function_exists('gmp_init')) {
        return gmp_strval(gmp_init($bin, 2), 10);
    } elseif (function_exists('bcadd')) {
        $dec = '0';
        for ($i = 0; $i < strlen($bin); $i++) {
            $dec = bcmul($dec, '2', 0);
            $dec = bcadd($dec, $bin[$i], 0);
        }
        return $dec;
    } else {
        trigger_error('GMP or BCMATH extension not installed!', E_USER_ERROR);
    }
}

function long2ip_v6($dec) {
    if (function_exists('gmp_init')) {
        $bin = gmp_strval(gmp_init($dec, 10), 2);
    } elseif (function_exists('bcadd')) {
        $bin = '';
        do {
            $bin = bcmod($dec, '2') . $bin;
            $dec = bcdiv($dec, '2', 0);
        } while (bccomp($dec, '0'));
    } else {
        trigger_error('GMP or BCMATH extension not installed!', E_USER_ERROR);
    }

    $bin = str_pad($bin, 128, '0', STR_PAD_LEFT);
    $ip = array();
    for ($bit = 0; $bit <= 7; $bit++) {
        $bin_part = substr($bin, $bit * 16, 16);
        $ip[] = dechex(bindec($bin_part));
    }
    $ip = implode(':', $ip);
    return inet_ntop(inet_pton($ip));
}

function getLocalIP()
{
    try{
        //利用socket获取本地ip,如果没网到不了8.8.8.8就获取不了
        $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_connect($sock, "8.8.8.8", 53);
        socket_getsockname($sock, $name);
        $localIP = $name;
    }catch (Exception $e){
        $localIP = '127.0.0.1';
    }
    return $localIP;
}

/**
 * get user remote ip
 * @return string
 */
function getIP() {
    if(request()->input('ci', '')){
        $ip = request()->input('ci');
    }elseif (getenv('HTTP_CLIENT_IP')) {
        $ip = getenv('HTTP_CLIENT_IP');
    }
    elseif (getenv('HTTP_X_FORWARDED_FOR')) {
        $ip = getenv('HTTP_X_FORWARDED_FOR');
    }
    elseif (getenv('HTTP_X_FORWARDED')) {
        $ip = getenv('HTTP_X_FORWARDED');
    }
    elseif (getenv('HTTP_FORWARDED_FOR')) {
        $ip = getenv('HTTP_FORWARDED_FOR');

    }
    elseif (getenv('HTTP_FORWARDED')) {
        $ip = getenv('HTTP_FORWARDED');
    }
    else if(isset($_SERVER['REMOTE_ADDR'])){
        $ip = $_SERVER['REMOTE_ADDR'];
    }else{
        $ip = '0.0.0.0';
    }
    if($ip){
        $ip_arr = explode(",",$ip);
        if(count($ip_arr)>1){
            foreach($ip_arr as $ip_t){
                if(!checkIPLocalOrIPV6($ip_t)){
                    $ip = $ip_t;
                    return $ip;
                }
            }
        }
    }
    $ip =str_replace(" ","",$ip);
    return str_replace(' ','',$ip);
}

/**
 * check ip is local ip or ipv6
 * @param $ip
 * @return bool
 */
function checkIPLocalOrIPV6($ip){
    if(($ip>="10.0.0.0" && $ip<="10.255.255.255") || ($ip>="172.16.0.0" && $ip<="172.31.255.255") || ($ip>="192.168.0.0" && $ip<="192.168.255.255")){
        return true;
    }
    if(strlen($ip)>20){
        return true;
    }
    return false;
}