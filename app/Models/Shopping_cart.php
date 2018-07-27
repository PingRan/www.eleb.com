<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shopping_cart extends Model
{
    //
    protected $fillable=['member_id','goods_id','amount'];

    //获取商品的方法
    public function goods()
    {
        return $this->hasOne(Menu::class,'id','goods_id');
    }
}
