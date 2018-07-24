<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
//接口api

// 获得商家列表接口
//businessList: '/businessList.php',
// 获得指定商家接口
// business: '/business.php',
Route::prefix('api')->group(function(){

    //获得商家列表接口

    Route::get('shopList','ApiController@shopList')->name('shopList');
    // 获得指定商家接口
    Route::get('shopPitch','ApiController@shopPitch')->name('shopPitch');
    //注册接口
    Route::post('reg','ApiController@reg')->name('reg');
    //登录接口
    Route::post('loginCheck','ApiController@loginCheck')->name('loginCheck');
});
