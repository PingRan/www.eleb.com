<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    //
    protected $fillable=['name','tel','province','city','county','address','member_id','is_default'];
}
