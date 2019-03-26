<?php

Route::group([
    'middleware' => ['api', 'api.token'],
    'prefix'     => 'orders',
    'namespace'  => 'App\\Modules\Orders\Http\Controllers'
], function () {
    Route::post('/checkout', 'OrdersController@checkout');
    Route::post('/payment', 'OrdersController@payment');
    Route::post('/cartPay', 'OrdersController@cartPay');//购物车去支付
    Route::post('/cartPayPal', 'OrdersController@cartPayPal');//购物车paypal支付
    Route::post('/pay', 'OrdersController@pay'); // 订单去支付
    Route::post('/paypal', 'OrdersController@paypal'); //订单paypal支付
});
Route::group([
    'middleware' => ['api', 'api.token_validate'],
    'prefix'     => 'orders',
    'namespace'  => 'App\\Modules\Orders\Http\Controllers'
], function () {
    Route::post('/list', 'OrdersController@index');
    Route::post('/detail', 'OrdersController@detail');
    Route::post('/delete', 'OrdersController@delete');
    Route::post('/sign', 'OrdersController@sign');
    Route::post('/cancel', 'OrdersController@cancel');
    Route::post('/logDetail', 'StreamController@getStream')->name('orders.getstream');
});
Route::group([
    'middleware' => ['api', 'api.token'],
    'prefix'     => 'payment',
    'namespace'  => 'App\\Modules\Orders\Http\Controllers'
], function () {
    Route::post('/palExec', 'PaymentController@paypalSuccess');
    Route::post('/paypalExec', 'PaymentController@paypalExec');
    Route::post('/failure', 'PaymentController@paypalFailure');
    Route::post('/status', 'PaymentController@getPaymentStatus');
});

Route::group([
    'middleware' => ['api'],
    'prefix'     => 'orders',
    'namespace'  => 'App\\Modules\Orders\Http\Controllers'
], function () {
    Route::post('/codPay', 'OrdersController@codPay');
    Route::post('/sms-detail', 'OrdersController@smdDetail');
});

