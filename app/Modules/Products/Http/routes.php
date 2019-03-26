<?php

Route::group(['middleware' => 'api', 'prefix' => 'products', 'namespace' => 'App\\Modules\Products\Http\Controllers'], function () {
    Route::post('/list', 'ProductsController@index');
    Route::get('/detail', 'ProductsController@show');
    Route::post('/detail', 'ProductsController@show');
});
