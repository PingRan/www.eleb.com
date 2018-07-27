<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        //
        'api/loginCheck','api/reg','api/addAddress','api/editAddress','api/addCart','api/addOrder','api/changePassword','api/forgetPassword'
    ];
}
