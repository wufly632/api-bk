<?php

Route::group(['middleware' => 'api', 'prefix' => 'promotions', 'namespace' => 'App\\Modules\Promotions\Http\Controllers'], function () {
    Route::get('/list', 'PromotionsController@index');
    Route::post('/list', 'PromotionsController@index');
});
