<?php

namespace App\Http\Controllers;

use App\Models\evaluate;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Shop;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    //商家列表接口
    public function shopList()
    {
        $shoplist = Shop::all();
        foreach ($shoplist as &$list) {
            unset($list['shop_category_id'], $list['status'], $list['created_at'], $list['updated_at']);

            $list['brand'] = $list['brand'] ? true : false;
            $list['on_time'] = $list['on_time'] ? true : false;
            $list['fengniao'] = $list['fengniao'] ? true : false;
            $list['bao'] = $list['bao'] ? true : false;
            $list['piao'] = $list['piao'] ? true : false;
            $list['zhun'] = $list['zhun'] ? true : false;
            $list['distance'] = mt_rand(10, 2000);
            $list['estimate_time'] = mt_rand(10, 120);

        }
        return json_encode($shoplist);
    }

    //指定商家接口
    public function shopPitch(Request $request)
    {

        $shop_id = $request->id;

        $shop = Shop::find($shop_id);

        unset($shop['shop_category_id'], $shop['status'], $shop['created_at'], $shop['updated_at']);
        $shop['distance'] = mt_rand(10, 2000);
        $shop['estimate_time'] = mt_rand(10, 120);
        $shop['service_code'] = mt_rand(1, 4) . '.' . mt_rand(0, 9);
        $shop['foods_code'] = mt_rand(1, 4) . '.' . mt_rand(0, 9);
        $shop['high_or_low'] = true;
        $shop['h_l_percent'] = mt_rand(10, 100);
        $shop['brand'] = $shop['brand'] ? true : false;
        $shop['on_time'] = $shop['on_time'] ? true : false;
        $shop['fengniao'] = $shop['fengniao'] ? true : false;
        $shop['bao'] = $shop['bao'] ? true : false;
        $shop['piao'] = $shop['piao'] ? true : false;
        $shop['zhun'] = $shop['zhun'] ? true : false;

        //获取该商店对应的评论.
        $evaluate = evaluate::where('shop_id', $shop_id)->get();

        foreach ($evaluate as &$e) {
            unset($e['shop_id']);
        }

        $shop['evaluate'] = $evaluate;
        //查询店铺的菜品分类
        $menucategories = MenuCategory::where('shop_id', $shop_id)->get();
        foreach ($menucategories as &$menucategory) {
            $menus = Menu::where('category_id', $menucategory->id)->get();

            foreach ($menus as &$menu) {
                $goods_id = $menu['id'];
                $menu['goods_id'] = $goods_id;
                unset($menu['created_at'], $menu['updated_at'], $menu['id']);
            }
            $menucategory['is_selected'] = $menucategory['is_selected'] ? true : false;

            $menucategory['goods_list'] = $menus;
            unset($menucategory['shop_id'], $menucategory['id'], $menucategory['created_at'], $menucategory['updated_at']);
        }
        $shop['commodity'] = $menucategories;
        return json_encode($shop);

    }

    //用户注册接口
    public function reg(Request $request)
    {
        return '{
      "status": "true",
      "message": "注册成功"
      
      }';
    }

    public function loginCheck()
    {
//       return '{
//            "status":"true",
//            "message":"登录成功",
//            "user_id":"1",
//            "username":"张三"
//    }';
        return 1;

    }
}
