<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function(){
    Route::get('gateway/list', 'ApiController@getListGateway');
    Route::post('auth/generate-token', 'ApiController@generateToken');
    Route::post('payment/confirm-payment', 'ApiController@getConfirmPage');
});