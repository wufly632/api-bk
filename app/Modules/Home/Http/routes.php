<?php

Route::group(['middleware' => 'api', 'prefix' => 'home', 'namespace' => 'App\\Modules\Home\Http\Controllers'], function () {
    Route::post('/', 'HomeController@index');
});

Route::group(['middleware' => 'api', 'namespace' => 'App\\Modules\Home\Http\Controllers'], function () {
    Route::post('/country', 'CountryController@index');
    Route::get('/national-code', 'CountryAreaController@nationalCode');
    Route::post('/national-code', 'CountryAreaController@nationalCode');
    //地址列表的地区列表
    Route::post('/arealist', 'CountryAreaController@areaList');

    Route::post('/cates', 'HomeController@category');
    Route::post('pc/cates', 'HomeController@pcCategory');
    Route::post('/search/hotword', 'HomeController@getHotWords');
    Route::post('/pc/home', 'HomeController@pcIndex');
    Route::post('/currency', 'HomeController@currency');
});

Route::group(['middleware' => 'api', 'namespace' => 'App\\Modules\Home\Http\Controllers'], function () {
    Route::post('/shortUrl', 'CommonController@getShortUrl');
    Route::post('/track', 'TrackController@index');
});

