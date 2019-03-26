<?php

Route::group([
    'middleware' => ['api', 'api.token'],
    'prefix'     => 'coupons',
    'namespace'  => 'App\\Modules\Coupon\Http\Controllers'
], function () {
    Route::post('/receive', 'CouponController@receive');
    Route::post('/apply', 'CouponController@apply');
});
