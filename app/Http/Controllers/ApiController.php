<?php

namespace App\Http\Controllers;

use App\Http\SphinxClient;
use App\Models\Address;
use App\Models\evaluate;
use App\Models\Member;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\OrderGood;
use App\Models\Shop;
use App\Models\Shopping_cart;
use App\Models\ShopUser;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use App\Jobs\SendSms;
use App\Jobs\sendNotice;
class ApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth', [
            'only' => ['addAddress', 'addressList', 'address', 'editAddress', 'addCart', 'cart'],
        ]);
    }


    public function search($keyword)
    {
        $cl = new SphinxClient ();
        $cl->SetServer ( '127.0.0.1', 9312);
        $cl->SetConnectTimeout ( 10 );
        $cl->SetArrayResult ( true );

        $cl->SetMatchMode ( SPH_MATCH_EXTENDED2);
        $cl->SetLimits(0, 1000);
        $info = $keyword;
        $res = $cl->Query($info, 'shop');//shopstore_search
        $result=[];
        if(isset($res['matches'])){
            array_map(function($value)use(&$result){
                $result[]=$value['id'];
            },$res['matches']);
        }

        return $result;
    }
    //商家列表接口
    public function shopList(Request $request)
    {

        $keyword=$request->keyword;

        if($keyword){

            $where_id=$this->search($keyword);

            $shopList=Shop::where('status',1)->find($where_id);

        }else{

            //先验证redis中是否有缓存的店铺列表信息;
            $shopList=Redis::get('shopList');

            if($shopList!=null){

                return $shopList;
            }

        }
        if(!$keyword||!$shopList){
            $shopList = Shop::where('status',1)->get();
        }

        foreach ($shopList as &$list) {
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
        //将数据存入redis中
        $listData=json_encode($shopList);//转成json字符串存入

        if($request->keyword){

            return $listData;
        }
        Redis::set('shopList',$listData,24*3600);
        return $listData;
    }

    //指定商家接口
    public function shopPitch(Request $request)
    {

        $shop_id = $request->id;

        //先判断redis中的商家缓存信息是否存在 存在返回 不存在查询  用hash将用户访问过的所有商家信息存入redis中
        $shopPitch=Redis::hget('shopPitch',$shop_id);

        if($shopPitch!=null){
            return $shopPitch;
        }


        $shop = Shop::find($shop_id);
        //如果用户提交的id的商铺不存在
        if($shop==null){
            return ["status"=>"false","message"=>"该商铺不存在"];
        }

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
            $menus = Menu::where('category_id', $menucategory->id)->where('status',1)->get();

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
        //存入redis中
        $shopPitch=json_encode($shop);
        //存入hash集合中;
        Redis::hset('shopPitch',$shop_id,$shopPitch);

        return $shopPitch;

    }

    //用户注册接口
    public function reg(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'username' => 'required|between:6,12|unique:members',
            'password' => 'required|between:6,18',
            'tel' => ['regex:/^1[356789]\d{9}$/', 'unique:members'],
            'sms' => 'required',

        ], [
            'username.between' => "用户名在6-12位",
            'username.unique' => "用户名已存在",
            'username.required' => "用户名必须填",
            'password.required' => "密码必须填",
            'password.between' => "密码在6-18位",
            'tel.unique' => "注册失败,电话号码已注册",
            'tel.regex' => "注册失败,电话号码不合法",
            'sms.required' => '注册失败，验证码不能为空',
        ]);

        if ($validator->fails()) {

            return [
                "status" => "false",
                "message" => $validator->errors()->first()
            ];

        }

        if (empty(Redis::get('code' . $request->tel))) {
            return json_encode(["status" => "false", "message" => "验证码已过期"]);
        }

        if (Redis::get('code' . $request->tel) != $request->sms) {
            return json_encode(["status" => "false", "message" => "验证码错误"]);
        };


        $request['password'] = bcrypt($request->password);
        Member::create($request->input());
        $rpone = json_encode(["status" => "true", "message" => "注册成功"]);
        return $rpone;
    }

    //发送短信接口
    public function sms(Request $request)
    {

        $params = array();

        // *** 需用户填写部分 ***

        // fixme 必填: 请参阅 https://ak-console.aliyun.com/ 取得您的AK信息
        $accessKeyId = "LTAIUcSZ77jpzuu1";
        $accessKeySecret = "Jq7t2I13dnQ9zh3NBh0jMbK7L6Qqle";

        // fixme 必填: 短信接收号码
        $params["PhoneNumbers"] = $request->tel;

        // fixme 必填: 短信签名，应严格按"签名名称"填写，请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/sign
        $params["SignName"] = "冉喜平";

        // fixme 必填: 短信模板Code，应严格按"模板CODE"填写, 请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/template
        $params["TemplateCode"] = "SMS_140500016";

        // fixme 可选: 设置模板参数, 假如模板中存在变量需要替换则为必填项
        $params['TemplateParam'] = Array(
            "code" => mt_rand(1000, 9999),
            //"product" => "阿里通信"
        );

        // fixme 可选: 设置发送短信流水号
        //$params['OutId'] = "12345";

        // fixme 可选: 上行短信扩展码, 扩展码字段控制在7位或以下，无特殊需求用户请忽略此字段
        //$params['SmsUpExtendCode'] = "1234567";


        // *** 需用户填写部分结束, 以下代码若无必要无需更改 ***
        if (!empty($params["TemplateParam"]) && is_array($params["TemplateParam"])) {
            $params["TemplateParam"] = json_encode($params["TemplateParam"], JSON_UNESCAPED_UNICODE);
        }

        // 初始化SignatureHelper实例用于设置参数，签名以及发送请求
        $helper = new \App\SignatureHelper();

        // 此处可能会抛出异常，注意catch
        $content = $helper->request(
            $accessKeyId,
            $accessKeySecret,
            "dysmsapi.aliyuncs.com",
            array_merge($params, array(
                "RegionId" => "cn-hangzhou",
                "Action" => "SendSms",
                "Version" => "2017-05-25",
            ))
        // fixme 选填: 启用https
        // ,true
        );

        if (isset($content->Code) && $content->Code == 'OK') {

            //将生成的验证码，存入redis的

            \Illuminate\Support\Facades\Redis::set('code' . $request->tel, json_decode($params['TemplateParam'])->code);
            \Illuminate\Support\Facades\Redis::expire('code' . $request->tel, 300);

            return json_encode(["status" => "1", "message" => "获取短信验证码成功"]);
        }
        return json_encode(["status" => "false", "message" => "获取短信验证码失败"]);


    }

//    public function sms(Request $request)
//    {
//       $Jobs=new SendSms($request->tel);
//       $this->dispatch($Jobs);
//        return json_encode(["status" => "1", "message" => "获取短信验证码成功"]);
//    }
    //登录接口
    public function loginCheck(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'password' => 'required',
        ], [
            'name.required' => '请填写账号',
            'password.required' => '请填写密码',
        ]);


        if ($validator->fails()) {

            return [
                "status" => "false",
                "message" => $validator->errors()->first()
            ];

        }


        if (Auth::attempt(['username' => $request->name, 'password' => $request->password])) {

            $memberinfo=Auth::user();
            if($memberinfo->status!=1){
                Auth::logout();
                return json_encode(["status" => "false", "message" => "该账号被禁用"]);
            }

            $rpone = ['status' => "true", 'message' => '登录成功', 'user_id' => Auth()->id(), 'username' => Auth::user()->username];
            return json_encode($rpone);

        } else {

            return json_encode(["status" => "false", "message" => "密码错误或者用户名不对"]);
        }

    }

    //添加收货地址
    public function addAddress(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => 'between:2,6|required',
            'provence' => 'required',
            'tel' => ['regex:/^1[356789]\d{9}$/', 'required'],
            'city' => 'required',
            'area' => 'required',
            'detail_address' => 'required',
        ], [
            'name.between' => "添加失败,姓名在2-6位间",
            'name.required' => "添加失败,姓名必须填",
            'tel.required' => "添加失败,电话号码必须填",
            'tel.regex' => "添加失败,请填写合法电话号码",
            'provence.required' => "添加失败,收货省必须填",
            'city.required' => "添加失败,收货市必须填",
            'area.required' => "添加失败,收货县必须填",
            'detail_address.required' => "添加失败,收货详细地址必须填",
        ]);

        if ($validator->fails()) {

            return [
                "status" => "false",
                "message" => $validator->errors()->first()
            ];
        }

        $member_id = Auth::id();
        $data = ['name' => $request->name, 'tel' => $request->tel, 'province' => $request->provence, 'city' => $request->city, 'county' => $request->area, 'address' => $request->detail_address, 'member_id' => $member_id, 'is_default' => 0];

        Address::create($data);

        return ["status" => "true", "message" => "添加成功"];

    }

    //收货地址列表接口
    public function addressList()
    {
        $addresses = Address::where('member_id', Auth::id())->get();
        foreach ($addresses as &$address) {

            $address->area = $address->county;
            $address->detail_address = $address->address;
            $address->provence = $address->province;
            unset($address->member_id, $address->created_at, $address->updated_at, $address->is_default, $address->county, $address->address, $address->province);

        }
        return json_encode($addresses);
    }

    //修改地址列表接口 回显
    public function address(Request $request)
    {
        //接收地址id
        $id = $request->id;

        $address = Address::find($id, ['id', 'county', 'province', 'address', 'name', 'tel', 'city']);
        //验证地址是否存在
        if($address==null){
            return ["status"=>"false","message"=>"该地址不存在"];
        }

        $address->area = $address->county;
        $address->detail_address = $address->address;
        $address->provence = $address->province;

        unset($address->address, $address->county, $address->province);

        return json_encode($address);


    }

    //修改地址保存
    public function editAddress(Request $request)
    {


        $validator = Validator::make($request->all(), [
            'name' => 'between:2,6|required',
            'provence' => 'required',
            'tel' => ['regex:/^1[356789]\d{9}$/', 'required'],
            'city' => 'required',
            'area' => 'required',
            'detail_address' => 'required',
        ], [
            'name.between' => "添加失败,姓名在2-6位间",
            'name.required' => "添加失败,姓名必须填",
            'tel.required' => "添加失败,电话号码必须填",
            'tel.regex' => "添加失败,请填写合法电话号码",
            'provence.required' => "添加失败,收货省必须填",
            'city.required' => "添加失败,收货市必须填",
            'area.required' => "添加失败,收货县必须填",
            'detail_address.required' => "添加失败,收货详细地址必须填",
        ]);

        if ($validator->fails()) {

            return [
                "status" => "false",
                "message" => $validator->errors()->first()
            ];
        }

        $addre = Address::find($request->id);

        $addre->update(['name' => $request->name, 'tel' => $request->tel, 'province' => $request->provence, 'city' => $request->city, 'county' => $request->area, 'address' => $request->detail_address]);

        return json_encode(["status" => "true", "message" => "修改成功"]);
    }

    //保存购物车数据接口
    public function addCart(Request $request)
    {
        Shopping_cart::where('member_id', Auth::id())->delete();

        $validator = Validator::make($request->all(), [
            'goodsList' => 'required',
            'goodsCount' => 'required',
        ], [
            'goodsList,required' => "加入失败,请选择商品",
            'goodsCount.required' => "加入失败,请选择商品数量",
        ]);

        if ($validator->fails()) {

            return [
                "status" => "false",
                "message" => $validator->errors()->first()
            ];
        }

        $member_id = Auth::id();

        $cart = [];
        $time = time();
        foreach ($request->goodsList as $k => $v) {
            $cart[$v]['goods_id'] = $v;
            $cart[$v]['amount'] = $request->goodsCount[$k];
            $cart[$v]['member_id'] = $member_id;
            $cart[$v]['time'] = $time;

        }
        Shopping_cart::insert($cart);
        return ["status" => "true", "message" => "添加成功"];

    }

    //获取购物车数据
    public function cart()
    {
        $cartdata = Shopping_cart::where("member_id", Auth::id())->get();
        $goods = [];
        $res = [];
        $totalCost = 0;
        foreach ($cartdata as $cart) {
            $goods['goods_name'] = $cart->goods->goods_name;
            $goods['goods_img'] = $cart->goods->goods_img;
            $goods['goods_id'] = $cart->goods->id;
            $goods['goods_price'] = $cart->goods->goods_price;
            $goods['amount'] = $cart->amount;
            $totalCost += $cart->goods->goods_price * $cart->amount;
            $res[] = $goods;
        }
        $result['goods_list'] = $res;
        $result['totalCost'] = $totalCost;
        return $result;

    }

    //生成订单接口
    public function addOrder(Request $request)
    {

        $member_id = Auth::id();
        $shoppingcarts = Shopping_cart::where('member_id', $member_id)->get();//得到用户购买的goods_id 和数量

        $total = 0;//价格
        $shop_id=0;

        //根据传入的地址id获取地址信息

        $orderinfo = Address::where('id', $request->address_id)->first(['province', 'city', 'county', 'address', 'tel', 'name']);
        if($orderinfo==null){
            return ["status"=>"false","message"=>"该地址不存在"];
        }

        $orderinfo['member_id'] = $member_id;
        $orderinfo['shop_id'] = $shop_id;
        $orderinfo['sn'] =date('Ymd').mt_rand(100000,999999);
        $orderinfo['status'] = 0;
        $orderinfo['out_trade_no'] = uniqid();
        $orderinfo['total'] = $total;

        $data = $orderinfo->toArray();

        DB::beginTransaction();

        try {
            $orderOne = Order::create($data);

            $order_id = $orderOne->id;

            $order_goods = [];
            foreach ($shoppingcarts as $cartOne) {

                $order_goods[$cartOne->goods->id]['goods_name'] = $cartOne->goods->goods_name;
                $order_goods[$cartOne->goods->id]['goods_img'] = $cartOne->goods->goods_img;
                $order_goods[$cartOne->goods->id]['goods_price'] = $cartOne->goods->goods_price;
                $order_goods[$cartOne->goods->id]['amount'] = $cartOne->amount;
                $order_goods[$cartOne->goods->id]['goods_id'] = $cartOne->goods_id;
                $order_goods[$cartOne->goods->id]['order_id'] = $order_id;
                $shop_id = $cartOne->goods->shop_id;
                $total += $cartOne->goods->goods_price * $cartOne->amount;

            }

            OrderGood::insert($order_goods);

            $orderOne->update(['shop_id'=>$shop_id,'total'=>$total]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            return ["status" => "false", "message" => "失败"];
        }
       $shop_name=Shop::find($shop_id)->shop_name;
       $user_id=ShopUser::where('shop_id',$shop_id)->first()->user_id;
       $userEmail=User::find($user_id)->email;
       $tel=Auth::user()->tel;
       $SmsJob=new SendSms($shop_name,$tel);
       $this->dispatch($SmsJob);
       //$this->SmsNotice($shop_name);

        $title='订单通知';
        $Njobs=new sendNotice($title,$userEmail);
        $this->dispatch($Njobs);

//        $this->sendEmail($title,$userEmail);

        return ["status" => "true", "message" => "添加成功", "order_id" => $order_id];

    }

    //获取指定的订单接口
    public function order(Request $request)
    {
        $order = Order::find($request->id, ['sn', 'created_at', 'status', 'shop_id', 'id', 'address', 'total']);

        if($order==null){
            return ["status"=>"false","message"=>"该订单不存在"];
        }

        $order->order_code = $order->sn;
        $order->order_birth_time = date('Y-m-d H:i', strtotime($order->created_at));
        $order->order_status = $order->status == 0 ? '待支付' : '已支付';
        $order->shop_name = $order->shop->shop_name;
        $order->shop_img = $order->shop->shop_img;
        $order->order_address = $order->address;
        $order->order_price = $order->total;
        unset($order->created_at, $order->status, $order->sn);

        $ordergoods = OrderGood::where('order_id',$request->id)->get(['goods_id', 'goods_name', 'amount', 'goods_img', 'goods_price']);
        $order->goods_list = $ordergoods->toArray();

        return $order;
    }

    //获取我的订单接口
    public function orderList()
    {

        $orders = Order::where('member_id',Auth::id())->get(['sn', 'created_at', 'status', 'shop_id', 'id', 'address', 'total']);

        foreach ($orders as &$order){

            $order->order_code = $order->sn;
            $order->order_birth_time = date('Y-m-d H:i', strtotime($order->created_at));
            $order->order_status = $order->status == 0 ? '待支付' : '已支付';
            $order->shop_name = $order->shop->shop_name;
            $order->shop_img = $order->shop->shop_img;
            $order->order_address = $order->address;
            $order->order_price = $order->total;
            unset($order->created_at, $order->status, $order->sn,$order->shop);

            $ordergoods = OrderGood::where('order_id',$order->id)->get(['goods_id', 'goods_name', 'amount', 'goods_img', 'goods_price']);
            $order->goods_list = $ordergoods->toArray();

        }

        return $orders;

    }

    //修改密码接口
    public function changePassword(Request $request)
    {
        $validator=Validator::make($request->all(),
            [
                'oldPassword'=>'required:6,12',
                'newPassword'=>'required',
            ],
            [
                'oldPassword.required'=>'请填写旧密码',
                'newPassword.required'=>'请填写新密码',
            ]
        );
        if($validator->fails()){
            return [
                "status"=>"false",
                "message"=>$validator->errors()->first(),
            ];
        }

        //获取数据库中的旧密码
        $memberInfo=Member::find(3);
        $Dbpassword=$memberInfo->password;
        if(!Hash::check($request->oldPassword,$Dbpassword)){
            return ["status"=>"false","message"=>"旧密码不正确"];
        };
        if(Hash::check($request->newPassword,$Dbpassword)){
            return ["status"=>"false","message"=>"新旧密码一致"];
        }

        $memberInfo->update(['password'=>bcrypt($request->newPassword)]);
        Auth::logout();
        return ["status"=>"true","message"=>"修改成功"];


    } 
    
    //忘记密码
    public function forgetPassword(Request $request)
    {
       $validator=Validator::make($request->all(),[
           'tel'=>['regex:/^1[356789]\d{9}$/','required'],
           'sms'=>'required',
           'password'=>'required|between:6,18',
       ],
           [
               'tel.required'=>'请填写电话号码',
               'tel.regex'=>'请填写合法电话号码',
               'sms.required'=>'请填写验证码',
               'password.required'=>'请填写密码',
               'password.between'=>'密码在6-18位',
           ]
       );

       if($validator->fails()){
           return [
               "status"=>"false",
               "message"=>$validator->errors()->first(),
           ];
       }
       //验证验证码
        if($request->sms!=Redis::get('code'.$request->tel)){
           return [
               "status"=>"false",
               "message"=>"验证码不正确",
           ];
        };
       //根据手机号查询用户账号信息
       $memberinfo=Member::where('tel',$request->tel)->first();
       //验证没有查到的情况
       if(empty($memberinfo)){
           return [
               "status"=>"false",
               "message"=>"该用户还未注册",
           ];
       }
       //修改
       $memberinfo->update(['password'=>bcrypt($request->password)]);
        return [
            "status"=>"true",
            "message"=>"重置成功,请登录",
        ];
    }
    //短信通知接口
    public function SmsNotice($shop_name)
    {
        $memberTel=Auth::user()->tel;

        $params = array();

        // *** 需用户填写部分 ***

        // fixme 必填: 请参阅 https://ak-console.aliyun.com/ 取得您的AK信息
        $accessKeyId = "LTAIUcSZ77jpzuu1";
        $accessKeySecret = "Jq7t2I13dnQ9zh3NBh0jMbK7L6Qqle";

        // fixme 必填: 短信接收号码
        $params["PhoneNumbers"] = $memberTel;

        // fixme 必填: 短信签名，应严格按"签名名称"填写，请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/sign
        $params["SignName"] = "冉喜平";

        // fixme 必填: 短信模板Code，应严格按"模板CODE"填写, 请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/template
        $params["TemplateCode"] = "SMS_141110007";

        // fixme 可选: 设置模板参数, 假如模板中存在变量需要替换则为必填项
        $params['TemplateParam'] = Array(
            "name" =>$shop_name,
            //"product" => "阿里通信"
        );

        // fixme 可选: 设置发送短信流水号
        //$params['OutId'] = "12345";

        // fixme 可选: 上行短信扩展码, 扩展码字段控制在7位或以下，无特殊需求用户请忽略此字段
        //$params['SmsUpExtendCode'] = "1234567";


        // *** 需用户填写部分结束, 以下代码若无必要无需更改 ***
        if (!empty($params["TemplateParam"]) && is_array($params["TemplateParam"])) {
            $params["TemplateParam"] = json_encode($params["TemplateParam"], JSON_UNESCAPED_UNICODE);
        }

        // 初始化SignatureHelper实例用于设置参数，签名以及发送请求
        $helper = new \App\SignatureHelper();

        // 此处可能会抛出异常，注意catch
        $content = $helper->request(
            $accessKeyId,
            $accessKeySecret,
            "dysmsapi.aliyuncs.com",
            array_merge($params, array(
                "RegionId" => "cn-hangzhou",
                "Action" => "SendSms",
                "Version" => "2017-05-25",
            ))
        // fixme 选填: 启用https
        // ,true
        );

        if (isset($content->Code) && $content->Code == 'OK') {

            return json_encode(["status" => "1", "message" => "短信通知已下达,请注意查收"]);
        }
        return json_encode(["status" => "false", "message" => "短信通知失败"]);

    }
    //订单产生时发送email给商家

    public function sendEmail($title,$userEmail)
    {
        $content=date('Y-m-d H:i:s').'您的店铺有新的订单,请尽快处理';
        $r =\Illuminate\Support\Facades\Mail::send('Email', ['content'=>$content], function ($message)use($title,$userEmail) {
            $message->from('pingran1993@163.com', 'eleb平台');
            $message->to([$userEmail])->subject($title);
        });
    }

}
