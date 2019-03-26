<?php

Route::group(['middleware' => 'api', 'prefix' => 'users', 'namespace' => 'App\\Modules\Users\Http\Controllers'],
    function () {
        Route::post('/login', ['as' => 'users.login', 'uses' => 'UsersController@login']);
        Route::post('/register', ['as' => 'users.register', 'uses' => 'UsersController@register']);
        Route::post('/pwreset', ['as' => 'users.pwreset', 'uses' => 'UsersController@pwreset']);
        Route::post('/pwsave', ['as' => 'users.pwsave', 'uses' => 'UsersController@pwsave']);
        //新版登陆
        Route::post('send-sms', 'AuthController@sendSms');
        Route::post('phone-login', 'AuthController@login');
        Route::post('phone-login-latest', 'AuthController@latestLogin');
        Route::post('phone-login-confirm', 'AuthController@loginConfirm');
        Route::post('union-login', 'AuthController@unionLogin');
        Route::post('union-login-confirm', 'AuthController@unionLoginConfirm');
        Route::post('union-login-confirm-latest', 'AuthController@latestUnionLogin');
        Route::post('random-key', 'AuthController@getRandomKey');
    });

Route::group([
    'middleware' => ['api', 'api.token_validate'],
    'prefix'     => 'users',
    'namespace'  => 'App\\Modules\Users\Http\Controllers'
], function () {
    Route::post('/logout', ['as' => 'users.login', 'uses' => 'UsersController@logout']);
});
Route::group([
    'middleware' => ['api', 'api.token_validate'],
    'prefix'     => 'address',
    'namespace'  => 'App\\Modules\Users\Http\Controllers'
], function () {
    Route::post('/edit', ['as' => 'address.edit', 'uses' => 'AddressController@edit']);
    Route::post('/delete', ['as' => 'address.delete', 'uses' => 'AddressController@delete']);
    Route::post('/default', ['as' => 'address.def', 'uses' => 'AddressController@setDefault']);
    Route::post('/info', ['as' => 'address.info', 'uses' => 'AddressController@getInfo']);
});
Route::group([
    'middleware' => ['api', 'api.token_validate'],
    'prefix'     => 'personal',
    'namespace'  => 'App\\Modules\Users\Http\Controllers'
], function () {
    Route::post('/index', ['as' => 'personal.index', 'uses' => 'PersonalController@index']);
    Route::post('/coupons', ['as' => 'personal.coupons', 'uses' => 'PersonalController@coupons']);
    Route::post('/integral', ['as' => 'personal.integral', 'uses' => 'PersonalController@integral']);
    Route::post('/finance', ['as' => 'personal.finance', 'uses' => 'PersonalController@finance']);
    Route::post('/income', ['as' => 'personal.income', 'uses' => 'PersonalController@income']);
    Route::post('/fans', ['as' => 'personal.fans', 'uses' => 'PersonalController@fans']);
    Route::post('/account', ['as' => 'personal.account', 'uses' => 'PersonalController@account']);
    Route::post('/info', ['as' => 'personal.info', 'uses' => 'PersonalController@info']);
    Route::post('/edit', ['as' => 'personal.edit', 'uses' => 'PersonalController@edit']);
    Route::post('/logo', ['as' => 'personal.logo', 'uses' => 'PersonalController@logo']);
    Route::post('/logo/base64', ['as' => 'personal.logo.base64', 'uses' => 'PersonalController@baseLogo']);
    Route::post('/cardDel', ['as' => 'personal.logo', 'uses' => 'PersonalController@cardDel']);
    Route::post('/invite-code', 'PersonalController@addInviteCode');
    Route::post('/change-phone', 'PersonalController@changePhone');
    Route::post('/check-old-phone', 'PersonalController@checkOldPhone');

});
Route::group([
    'middleware' => ['api', 'api.token_validate'],
    'prefix'     => 'cards',
    'namespace'  => 'App\\Modules\Users\Http\Controllers'
], function () {
    Route::post('/info', ['as' => 'cards.info', 'uses' => 'CardsController@info']);
    Route::post('/add', ['as' => 'cards.add', 'uses' => 'CardsController@add']);
    Route::post('/edit', ['as' => 'cards.edit', 'uses' => 'CardsController@edit']);
    Route::post('/delete', ['as' => 'cards.delete', 'uses' => 'CardsController@delete']);
    Route::post('/default', ['as' => 'cards.default', 'uses' => 'CardsController@setDefault']);
});
Route::group([
    'middleware' => ['api', 'api.token'],
    'prefix'     => 'cards',
    'namespace'  => 'App\\Modules\Users\Http\Controllers'
], function () {
    Route::post('/list', ['as' => 'cards.list', 'uses' => 'CardsController@index']);
});

Route::group([
    'middleware' => ['api', 'api.token'],
    'prefix'     => 'address',
    'namespace'  => 'App\\Modules\Users\Http\Controllers'
], function () {
    Route::post('/add', ['as' => 'address.add', 'uses' => 'AddressController@add']);
    Route::post('/list', ['as' => 'address.list', 'uses' => 'AddressController@index']);
});

Route::group([
    'middleware' => ['api', 'api.token_validate'],
    'prefix'     => 'pc/personal',
    'namespace'  => 'App\\Modules\Users\Http\Controllers'
], function () {
    Route::post('/index', ['as' => 'personal.index', 'uses' => 'PersonalController@pcIndex']);
});