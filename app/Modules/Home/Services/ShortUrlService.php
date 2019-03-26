<?php

namespace App\Modules\Home\Services;


class ShortUrlService
{
    public static function vgdShorten($url, $shorturl = null)
    {
        $url = urlencode($url);
        $basepath = "https://v.gd/create.php?format=simple";
        //if you want to use is.gd instead, just swap the above line for the commented out one below
        //$basepath = "https://is.gd/create.php?format=simple";
        $result = array();
        $result["errorCode"] = -1;
        $result["shortURL"] = null;
        $result["errorMessage"] = null;

        $opts = array("http" => array("ignore_errors" => true));
        $context = stream_context_create($opts);

        if ($shorturl)
            $path = $basepath . "&shorturl=$shorturl&url=$url";
        else
            $path = $basepath . "&url=$url";

        $response = @file_get_contents($path, false, $context);

        if (!isset($http_response_header)) {
            $result["errorMessage"] = "Local error: Failed to fetch API page";
            return ($result);
        }

        if (!preg_match("{[0-9]{3}}", $http_response_header[0], $httpStatus)) {
            $result["errorMessage"] = "Local error: Failed to extract HTTP status from result request";
            return ($result);
        }

        $errorCode = -1;
        switch ($httpStatus[0]) {
            case 200:
                $errorCode = 0;
                break;
            case 400:
                $errorCode = 1;
                break;
            case 406:
                $errorCode = 2;
                break;
            case 502:
                $errorCode = 3;
                break;
            case 503:
                $errorCode = 4;
                break;
        }
        if ($errorCode == -1) {
            $result["errorMessage"] = "Local error: Unexpected response code received from server";
            return ($result);
        }

        $result["errorCode"] = $errorCode;
        if ($errorCode == 0)
            $result["shortURL"] = $response;
        else
            $result["errorMessage"] = $response;

        return ($result);
    }

    public static function dwzShorten($url)
    {
        /*$url = urlencode($url);
        $url = str_replace('%3A', ':', $url);
        $url = str_replace('%2F', '/', $url);*/
        $basepath = "https://dwz.cn/admin/create";
        $result = array();
        $result["errorCode"] = -1;
        $result["shortURL"] = null;
        $result["errorMessage"] = null;
        $dwzResult = json_decode(self::postJson($basepath, json_encode(['url' => $url])), true);
        if ($dwzResult['Code'] == 0) {
            $result['errorCode'] = 0;
            $result['shortURL'] = $dwzResult['ShortUrl'];
        } else {
            $result['errorCode'] = $dwzResult['Code'];
            $result['errorMessage'] = $dwzResult['ErrMsg'];
        }
        return $result;
    }

    private static function postJson($url, $keysArr, $flag = 0)
    {
        $ch = curl_init();
        if (!$flag) curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $keysArr);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            [
                'Content-Type: application/json; charset=utf-8',
                'Content-Length:' . strlen($keysArr)
            ]
        );
        $ret = curl_exec($ch);
        curl_close($ch);
        if ($ret === FALSE || empty($ret)) {
            throw new \Exception('request error', "50001");
        }
        return $ret;
    }
}