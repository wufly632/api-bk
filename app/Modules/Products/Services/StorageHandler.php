<?php
/**
 * Created by PhpStorm.
 * User: longyuan
 * Date: 2018/9/10
 * Time: 下午6:02
 */

namespace App\Modules\Products\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Monolog\Logger;
use OSS\Core\OssException;
use OSS\OssClient;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class StorageHandler
{

    /**
     * Upload to S3 storage
     * @param $file_field
     * @param $folder
     * @param $keep_original_name
     * @param $use_timestamp
     * @param null $filename
     * @param bool $local_when_failure 失败时上传到本地
     * @param bool $encode_key
     * @return bool|mixed|null|string
     */
    public static function uploadToAws($file_field, $folder, $keep_original_name, $use_timestamp, $filename = null, $local_when_failure = true, $encode_key = true)
    {
        $file = self::getFile($file_field);

        if (!($file instanceof UploadedFile)) {
            return null;
        }
        $extension = $file->getClientOriginalExtension();
        $s3 = self::getS3Client();
        if (!$filename) {
            if (!$keep_original_name) {
                $filename = uniqid('', false);
            } else {
                /*
                 * @see s3->encodeKey
                 */
                if ($encode_key) {
                    $filename = $s3->encodeKey($file->getClientOriginalName());
                } else {
                    $filename = $file->getClientOriginalName();
                }
            }
        } else {
            $filename = $encode_key ? $s3->encodeKey($filename) : $filename;
        }
        // use timestamp: to update cache in patpat-app
        if ($use_timestamp) {
            $filename = $filename . '/' . Carbon::now()->timestamp;
        }
        // extension is need by Intervention/Image
        if (!ends_with($filename, ".$extension")) {
            $filename .= ".$extension";
        }

        $save_as = rtrim($folder, '/') . '/' . $filename;// remove slashes

        try {
            $result = self::putS3Object($save_as, $file->getRealPath());
            if ($result) {// 上传成功
                self::deleteTmpFile($file);
                return self::getObjectUrl($save_as);
            } else {
                throw new \Exception("Failed to upload file({$file->getClientOriginalName()}) as $save_as to S3");
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            if ($local_when_failure) {
                return self::uploadToLocal($file, $folder, $keep_original_name);
            }
        }
    }

    public static function uploadToAliOss($filePath, $folder, $bucket)
    {
        $ossClint = self::getOssClint();
        try{
            $result = $ossClint->uploadFile($bucket, $folder, $filePath);
        }catch (OssException $exception){
            $logger = new Logger('aliyun-upload');
            $logger->info($exception->getMessage());
            return false;
        }
        return $result;
    }

    private static function getOssClint()
    {
        return new OssClient(env('OSS_ACCESS_ID', ''), env('OSS_ACCESS_SECRET', ''), env('OSS_ENDPOINT', ''));
    }

    /**
     * 获取对象地址
     * @param $object
     * @param null $bucket
     * @return mixed
     */
    private static function getObjectUrl($object, $bucket = null)
    {
        $s3 = self::getS3Client();
        $bucket = $bucket ?: self::getBucket();
        $url = $s3->getObjectUrl($bucket, $object);
        $url = str_replace("https", "http", $url);
        return $url;
    }

    /**
     * Upload to Local Storage
     * @param $file_field
     * @param $folder
     * @param $keep_original_name
     * @param null $filename
     * @return string|void
     * @throws \Exception
     */
    public static function uploadToLocal($file_field, $folder, $keep_original_name, $filename = null)
    {
        $file = self::getFile($file_field);
        if (!($file instanceof UploadedFile)) {
            return null;
        };
        $extension = $file->getClientOriginalExtension();
        if (!$filename) {
            if ($keep_original_name) {
                $filename = $file->getClientOriginalName();
            } else {
                $filename = uniqid('', false) . "." . $extension;
            }
        } else {
            $filename .= $extension ? ".$extension" : '';
        }

        if ($folder) {
            if (strpos($folder, 'uploads') === false) {
                $folder = "uploads/$folder";
            }
        } else {
            $folder = 'uploads';
        }
        $folder_abs_path = public_path($folder);
        if (!file_exists($folder_abs_path)) {
            $old = umask(0);
            if (!@mkdir($folder_abs_path, 0777, true) && !is_dir($folder_abs_path)) {
                throw new \Exception("Failed to make directory:$folder_abs_path");
            }
            umask($old);
        }
        $save_as = $folder . '/' . $filename;
        self::putLocalObject($save_as, $file->getRealPath());
        self::deleteTmpFile($file);
//        return "/$save_as"; // 斜杠/非常重要
        return URL::to($save_as);// 多个系统需要查看上传到Local的文件
    }

    /**
     * 上传到本地
     * @param $save_as
     * @param $original_path
     */
    private static function putLocalObject($save_as, $original_path)
    {
        if (self::storageAvailable()) {
            Storage::disk('local')->put($save_as, file_get_contents($original_path));
        } else {
            file_put_contents(public_path($save_as), file_get_contents($original_path));
        }
    }

    /**
     * 本地文件是否存在
     * @param $path
     * @return mixed
     */
    public static function doesLocalObjectExist($path)
    {
        $path = self::urlPath($path);
        if (self::storageAvailable()) {
            return Storage::disk('local')->has($path);
        } else {
            return file_exists(public_path($path));
        }
    }

    /**
     * URL To Path
     * @param $url
     * @return mixed
     */
    public static function urlPath($url)
    {
        if (self::isUrl($url)) {
            return parse_url($url, PHP_URL_PATH);
        }
        return $url;
    }

    private static function isUrl($url)
    {
        if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
            return true;
        }
    }

    /**
     * get the s3 client
     * @return mixed
     */
    public static function getS3Client()
    {
        if (self::storageAvailable()) {
            return self::getS3Adapter()->getClient();
        } else {
            // For Laravel4
            return AWS::get("s3");
        }
    }

    private static function getS3Adapter()
    {
        return Storage::disk("s3")->getDriver()->getAdapter();
    }

    private static function getBucket()
    {
        if (self::storageAvailable()) {
            return self::getS3Adapter()->getBucket();
        } else {
            // TODO:configurable bucket
            return 'patpatdev';
        }
    }


    /**
     * 根据url获取bucket和object
     * @param $url
     * @return array
     */
    public static function getBucketAndObject($url)
    {
        $url = str_replace(["https://", "http://"], "", $url);
        $parts = explode('amazonaws.com/', $url);
        if (count($parts) < 2) {
            return;
        }
        $bucket = self::getBucket();
        $key_name = $parts[1];
        if (strpos($key_name, $bucket) !== false) {
            $key_name = substr($key_name, strlen($bucket) + 1);
        }
        return array(
            'bucket' => $bucket,
            'object' => $key_name
        );
    }


    /**
     * 兼容版本的检查S3文件是否存在
     * @param $key
     * @param $bucket
     */
    private static function doesS3ObjectExist($key, $bucket = null)
    {
        if (self::storageAvailable()) {
            return Storage::disk('s3')->has($key);
        } else {
            $bucket = $bucket ?: self::getBucket();
            return self::getS3Client()->doesObjectExist($bucket, $key);
        }
    }


    /**
     * 兼容版本的上传到S3
     * @param $key
     * @param $file_path
     * @return mixed
     */
    private static function putS3Object($key, $file_path)
    {
        if (self::storageAvailable()) {
            return Storage::disk("s3")->put($key, file_get_contents($file_path), 'public');// public is important here!
        } else {
            // Laravel 4
            $s3 = self::getS3Client();
            $result = $s3->putObject(array(
                'Bucket' => self::getBucket(),
                'Key' => $key,
                'SourceFile' => $file_path,
                'ContentType' => 'image/jpeg',
                'ACL' => 'public-read',
                'StorageClass' => 'STANDARD',
            ));
            // $result is instanceof Guzzle\Service\Resource\Model
            $url = $result['ObjectURL'];
            if ($url) {
                return $url;
            } else {
                Log::waring("Failed to upload as $key", [$result]);
            }
        }
    }

    /**
     * Storage是否可用
     * @return mixed
     */
    private static function storageAvailable()
    {
        return class_exists(Storage::class);
    }


    /**
     * 生成缩略图
     * @param $original_image
     * @param array $sizes
     * @param null $save_to_url 主要用于活动banner2需要生成缩略图到banner1下
     * @param bool|false $force
     */
    public static function generateThumbnails($original_image, array $sizes, $save_to_url = null, $force = false)
    {
        Log::info("Generating thumbs for $original_image");
        // 必须是S3的图片才生成
        $object_info = self::getBucketAndObject($original_image);
        if (!$object_info) {
            return;
        }

        $object = array_get($object_info, 'object');
        if (!self::doesS3ObjectExist($object)) {
            Log::warning("S3 Object($object) not exists");
            return;
        }
        // 保存为
        $save_to_object = null;
        if ($save_to_url) {
            $save_to_info = self::getBucketAndObject($save_to_url);
            if (!$save_to_info) {
                Log::warning("Cannot save $original_image as $save_to_url(Invalid)");
                return;
            }
            $save_to_object = $save_to_info['object'];
        }

        $tmp_path = public_path() . "/uploads/";

        foreach ($sizes as $thumb_setting) {
            list($width, $height) = $thumb_setting;
            $size = $width . "x" . $height;

            $thumb_object = ($save_to_object ?: $object) . "/" . $size;
            if (!$force) {
                // exist?
                if (self::isThumbGenerated($original_image, $size)) {
                    Log::warning("Image($original_image)'s thumbnail with size($size) already generated!");
                    continue;
                }
                if (self::doesS3ObjectExist($thumb_object)) {
                    Log::warning("$thumb_object exist!");
                    self::markFinished($original_image, $size);
                    continue;
                }
            } else {
                Log::info("Force generating $size for $original_image");
            }

            $thumb_file_path = $tmp_path . uniqid('', false);
            try {
                self::resize($original_image, $width, $height, $thumb_file_path);
                Log::info("Generating $thumb_object");
                $uploaded = self::putS3Object($thumb_object, $thumb_file_path);
                if ($uploaded) {
                    self::markFinished($original_image, $size);
                } else {
                    Log::warning("Fail to generate Image($original_image)'s thumbnail with size($size)!");
                }
                // remove temp file
                @unlink($thumb_file_path);
            } catch (Exception $e) {
                Log::error("Cannot generate thumbnail with size:$size", [$e->getMessage(), $e->getFile(), $e->getLine()]);
            }
        }
    }

    private static function markFinished($original_url, $size)
    {
        if ($original_url && $size) {
            $exists = ImageMission::where("addr", $original_url)->where("size", $size)->first();
            if (!$exists) {
                Log::info("Marking finished for $original_url/$size");
                $im = new ImageMission();
                $im->addr = $original_url;
                $im->size = $size;
                $im->save();
            }
        }
    }

    private static function isThumbGenerated($original_url, $size)
    {
        $exists = ImageMission::where("addr", $original_url)->where("size", $size)->first();
        return $exists;
    }


    /**
     * 生成指定尺寸大小的图片
     * @param $original_url
     * @param $width
     * @param $height
     * @param $save_as
     */
    private static function resize($original_url, $width, $height, $save_as)
    {
        $img = Image::make($original_url);
        $img->resize($width, $height,
            function ($constraint) {
                $constraint->aspectRatio();
            })
            ->resizeCanvas($width, $height, "center", false, "#fff");
        $img->save($save_as, 100);

        // 无损压缩优化
        $factory = new \ImageOptimizer\OptimizerFactory();
        $optimizer = $factory->get();// SmartOptimizer
        $optimizer->optimize($save_as);
    }

    /**
     * 获取要上传的文件：可以是File/字段名/URL
     * @param $var
     * @return array|null|UploadedFile
     * @throws Exception
     */
    private static function getFile($var)
    {
        if ($var instanceof UploadedFile) {
            return $var;
        }
        if (is_string($var)) {
            $request = app('request');
            if ($request->hasFile($var)) {
                return $request->file($var);
            }
            // it's a local file
            if (self::doesLocalObjectExist($var)) {
                // TODO:get path from the Storage Facade
                $var = self::urlPath($var);
                $full_path = public_path($var);
            } else {
                if (!self::isUrl($var)) {
                    return null;
                }
                $tmp_folder = 'uploads/tmp';
                $tmp_path = public_path($tmp_folder);
                if (!file_exists($tmp_path)) {
                    $old = umask(0);
                    if (!@mkdir($tmp_path, 0777, true) && !is_dir($tmp_path)) {
                        throw new \Exception("Failed to make directory:$tmp_path");
                    }
                    umask($old);
                }

                set_time_limit(0);
                $full_path = public_path($tmp_folder . '/' . uniqid('', false) . basename($var));
                $content = @file_get_contents($var);
                if ($content !== false) {
                    file_put_contents($full_path, $content);
                } else {
                    throw new Exception("Failed to get content from $var");
                }
            }
            //File::mimeType($full_path)
            return new UploadedFile($full_path, basename($var), finfo_file(finfo_open(FILEINFO_MIME_TYPE), $full_path));
        }
    }

    public function uploadFile($file_field = "file", $directory = "uploads", $keep_original_name = false,
                               $aws = true, $bucket = 'patpatdev', $use_timestamp = false, $contentType = 'image/jpeg')
    {
        if (!$aws) {
            return $this->uploadToLocal($file_field, $directory, $keep_original_name);
        }
        return self::uploadToAws($file_field, $directory, $keep_original_name, $use_timestamp);
    }

    /**
     * 删除临时文件
     * @param $file
     */
    private static function deleteTmpFile(UploadedFile $file)
    {
        $path = $file->getRealPath();
        if (strpos($path, 'tmp') !== false) {
            Log::warning('Deleting temp file:', [$path]);
            @unlink($path);
        }
    }

    /**
     * Excel导出时远程图片必须下载到本地
     * @param $remote_url
     * @param $folder
     * @param $filename
     * @return string|void
     */
    public static function saveToLocalForExporting($remote_url, $folder, $filename)
    {
        if (StorageHandler::doesLocalObjectExist($remote_url)) {
            return substr(StorageHandler::urlPath($remote_url), 1);// remove slash
        }
        $save_as = $folder . $filename;
        if (StorageHandler::doesLocalObjectExist($save_as)) {
            return $save_as;// remove slash
        }
        try {
            $local_url = StorageHandler::uploadToLocal($remote_url, $folder, false, $filename);
        } catch (\Exception $e) {
            Log::warning($e->getMessage(), [$e->getTraceAsString()]);
            $local_url = '';
        }
        if ($local_url) {
            $local_url = substr(StorageHandler::urlPath($local_url), 1);// remove slash
        }
        return $local_url;
    }
}
