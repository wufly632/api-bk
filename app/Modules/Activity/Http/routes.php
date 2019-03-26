<?php

Route::group(['middleware' => 'api', 'prefix' => 'invite', 'namespace' => 'App\\Modules\Activity\Http\Controllers'], function()
{
    Route::post('/marquee', 'ActivityController@getMarquee')->name('invite.marquee');
    Route::post('/topten', 'ActivityController@getTopTen')->name('invite.topten');
});
