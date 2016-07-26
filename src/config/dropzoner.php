<?php

return [
//    'upload-path' => env('DROPZONER_UPLOAD_PATH').date('Y-m-d').'/',
    "upload-path" => "uploads/carousel/{yyyy}{mm}{dd}/{time}{rand:6}", /* 上传保存路径,可以自定义保存路径和文件名格式 */
    'validator'   => [
        'file'    => 'required|mimes:png,gif,jpeg,jpg,bmp'
    ],
    'validator-messages' => [
        'file.mimes'     => 'Uploaded file is not in image format',
        'file.required'  => 'Image is required'
    ],
    'encode'      => 'jpg'
];
