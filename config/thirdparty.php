<?php
return [
    'trackingMoreApi' => env('TRACKING_MORE_API'),
    'inviteStatus' => env('ACTIVITY_INVITE_STATUS', false),
    'inviteMoney' => env('INVITE_MONEY', 10),
    'marqueeRequestFrequent' => env('MARQUEE_REQUEST_FREQUENT', 60),//请求间隔，以秒数计算;
    'marqueeFaker' => env('MARQUEE_FAKER', false),
    'cashBackGoodList' => env('CASH_BACK_GOOD_LIST'),
    'smsName' => env('SMS_NAME'),
    'smsPassword' => env('SMS_PASSWORD'),
    'smsWhiteList' => env('SMS_WHITE_LIST'),
    'yunPianApiKey' => env('YUNPIAN_APIKEY'),
    'serverPosition' => env('SERVER_POSITION'),
];