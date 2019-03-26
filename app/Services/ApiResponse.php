<?php
/**
 * Created by PhpStorm.
 * User: longyuan
 * Date: 2018/9/4
 * Time: 下午2:48
 */
namespace App\Services;
use Illuminate\Support\Facades\Response;

class ApiResponse extends Response
{
    /**
     * Response data
     *
     * @var array
     */
    public $data = [];

    function __construct($status = g_STATUSCODE_OK, $msg = '',$content = null)
    {
        $this->data = [
            g_API_STATUS => $status,
            g_API_MSG => $msg,
            g_API_CONTENT => $content,
        ];
    }

    /**
     * Return json string
     *
     * @param string $status
     * @param string $msg
     * @param null $content
     * @return mixed
     */
    public static function init($status=g_STATUSCODE_OK, $msg='',$content = null){
        $apiResponse = new ApiResponse($status,$msg,$content);
        return $apiResponse->data;
    }

    /**
     * Success response json string
     *
     * @param null $content
     * @return mixed
     */
    public static function success($content = null,$message = 'success'){
        return self::init(g_STATUSCODE_OK,$message,$content);
    }

    /**
     * Failure response json string
     *
     * @param $status
     * @param $msg
     * @return mixed
     */
    public static function failure($status,$msg,$content = null){
        return self::init($status,$msg, $content);
    }

    /**
     * Export data
     *
     * @param $data
     * @param $export_format
     * @return string
     */
    public static function export($data,$export_format=g_EXPORT_FORMAT_JSON){
        if($export_format == g_EXPORT_FORMAT_JSON){
            return Response::json($data);
        }else{
            return '';
        }
    }

}