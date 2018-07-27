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

    Route::get('sms','ApiController@sms');
    //添加地址
    Route::post('addAddress','ApiController@addAddress');
    //地址列表
    Route::get('addressList','ApiController@addressList');
    //回显地址
    Route::get('address','ApiController@address');
    //修改保存
    Route::post('editAddress','ApiController@editAddress');
    //保存购物车
    Route::post('addCart','ApiController@addCart');
    //获取购物车数据
    Route::get('cart','ApiController@cart');
    //生成订单接口
    Route::post('addOrder','ApiController@addOrder');
    //获取指定的订单接口
    Route::get('order','ApiController@order');
    //获取我的订单列表接口
    Route::get('orderList','ApiController@orderList');
    //修改密码
    Route::post('changePassword','ApiController@changePassword');
    //忘记密码
    Route::post('forgetPassword','ApiController@forgetPassword');
});

Route::get('test','MemberController@test');

