<?php

namespace Ambang\Dropzoner\Repositories;

use Ambang\Dropzoner\Events\ImageWasDeleted;
use Ambang\Dropzoner\Events\ImageWasUploaded;
use Intervention\Image\ImageManager;

class UploadRepository
{
    protected $file;     //文件上传对象
    protected $code;     //错误码
    protected $state;    //上传状态信息
    protected $config;   //配置信息
    protected $message;  //上传回调信息
    protected $oriName;  //原始文件名
    protected $fileName; //新文件名
    protected $fullName; //完整文件名,即从当前配置目录开始的URL
    protected $filePath; //完整路径,即从当前配置目录开始的URL
    protected $fileSize; //文件大小
    protected $fileType; //文件类型


    public function __construct()
    {
        $this->config = config('dropzoner');
    }


    /**
     * Upload Single Image
     *
     * @param $input
     * @return mixed
     */
    public function upload($input)
    {
        $validator = \Validator::make($input, $this->config['validator'], $this->config['validator-messages']);

        if ($validator->fails()) {

            return response()->json([
                'error' => true,
                'message' => $validator->messages()->first(),
                'code' => 400
            ], 400);
        }
        //获取文件对象
        $this->file = $input['file'];
        //获取原文件名称
        $this->oriName = $this->file ->getClientOriginalName();

        $this->fileSize = $this->formatFileSize($this->file->getSize());

        $this->fileType = $this->getFileExt();

        $this->fullName = $this->getFullName();

        $this->filePath = $this->getFilePath();

        $this->fileName = basename($this->filePath);

        //$manager = new ImageManager();
        //$image = $manager->make( $this->file )->move(dirname($this->filePath) .DIRECTORY_SEPARATOR. $this->fileName );
        $image = $this->file->move(dirname($this->filePath), $this->fileName);
        if( !$image ) {
            return response()->json($this->getFileInfo(false,500,'服务器错误请重新尝试'), 500);

        }

        //Fire ImageWasUploaded Event
        event(new ImageWasUploaded($this->oriName, $this->fileType));

        return response()->json($this->getFileInfo(true,200,$image), 200);
    }

    /**
     * Delete Single Image
     *
     * @param $url
     * @return mixed
     */
    public function delete($url)
    {
        if (\File::exists($url)) {
            $isSuc = \File::delete($url);
        }
        if($isSuc){
            event(new ImageWasDeleted($url));
            return response()->json($this->getFileInfo(true,200,'删除成功'), 200);
        }else{
            return response()->json($this->getFileInfo(true,404,'删除失败'), 404);
        }
    }

    /**
     * Check upload directory and see it there a file with same filename
     * If filename is same, add random 5 char string to the end
     *
     * @param $filename
     * @return string
     */
    private function createUniqueFilename( $filename )
    {
        $full_size_dir = config('dropzoner.upload-path');
        $full_image_path = $full_size_dir . $filename . '.jpg';

        if (\File::exists($full_image_path)) {
            // Generate token for image
            $image_token = substr(sha1(mt_rand()), 0, 5);
            return $filename . '-' . $image_token;
        }

        return $filename;
    }

    /**
     * Create safe file names for server side
     *
     * @param $string
     * @param bool $force_lowercase
     * @return mixed|string
     */
    private function sanitize($string, $force_lowercase = true)
    {
        $strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]",
            "}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
            "â€”", "â€“", ",", "<", ".", ">", "/", "?");
        $clean = trim(str_replace($strip, "", strip_tags($string)));
        $clean = preg_replace('/\s+/', "-", $clean);

        return ($force_lowercase) ?
            (function_exists('mb_strtolower')) ?
                mb_strtolower($clean, 'UTF-8') :
                strtolower($clean) :
            $clean;
    }

    /**
     * 重命名文件
     * @return string
     */
    protected function getFullName()
    {
        //替换日期事件
        $t = time();
        $d = explode('-', date("Y-y-m-d-H-i-s"));
        $format = $this->config["upload-path"];
        $format = str_replace("{yyyy}", $d[0], $format);
        $format = str_replace("{yy}", $d[1], $format);
        $format = str_replace("{mm}", $d[2], $format);
        $format = str_replace("{dd}", $d[3], $format);
        $format = str_replace("{hh}", $d[4], $format);
        $format = str_replace("{ii}", $d[5], $format);
        $format = str_replace("{ss}", $d[6], $format);
        $format = str_replace("{time}", $t, $format);

        //过滤文件名的非法字符,并替换文件名
        $oriName = substr($this->oriName, 0, strrpos($this->oriName, '.'));
        $oriName = preg_replace("/[\|\?\"\<\>\/\*\\\\]+/", '', $oriName);
        $format = str_replace("{filename}", $oriName, $format);

        //替换随机字符串
        $randNum = rand(1, 10000000000) . rand(1, 10000000000);
        if (preg_match("/\{rand\:([\d]*)\}/i", $format, $matches)) {
            $format = preg_replace("/\{rand\:[\d]*\}/i", substr($randNum, 0, $matches[1]), $format);
        }

        $ext = $this->getFileExt();
        return $format . $ext;
    }

    /**
     * 获取文件扩展名
     * @return string
     */
    protected function getFileExt()
    {
        return '.' . $this->file->getClientOriginalExtension();
    }

    /**
     * 获取文件完整路径
     * @return string
     */
    protected function getFilePath()
    {
        $fullName = $this->fullName;

        $rootPath = public_path();

        $fullName = ltrim($fullName, '/');


        return $rootPath . '/' . $fullName;
    }

    function formatFileSize($bytes, $decimals = 2)
    {
        $size = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) .@$size[$factor];
    }

    /**
     * 获取当前上传成功文件的各项信息
     * @return array
     */
    public function getFileInfo($state, $code ,$message = '')
    {
        return array(
            "state" => $this->state = $state,
            "url" => $this->fullName,
            "filename" => $this->fileName,
            "original" => $this->oriName,
            "type" => $this->fileType,
            "size" => $this->fileSize,
            "code" => $this->code = $code,
            "message" => $this->message = $message
        );
    }
}