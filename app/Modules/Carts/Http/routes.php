<?php

Route::group([
    'middleware' => ['api', 'api.token'],
    'prefix'     => 'carts',
    'namespace'  => 'App\\Modules\Carts\Http\Controllers'
], function () {
    Route::post('/', 'CartsController@index');
    Route::post('/sync', 'CartsController@sync');
    Route::post('/delete', 'CartsController@delete');
    Route::post('/num', 'CartsController@counts');
    Route::post('/update', 'CartsController@changeNum');
    Route::post('/checkout', 'CartsController@checkout');
    /*Route::group(['middleware' => ['api.token_validate']], function () {
        Route::post('/checkout', 'CartsController@checkout');
    });*/
});
Route::group(['middleware' => ['api'], 'prefix' => 'carts', 'namespace' => 'App\\Modules\Carts\Http\Controllers'],
    function () {
        Route::post('/show', 'CartsController@cartInfo');
        Route::post('/add', 'CartsController@add');
    });