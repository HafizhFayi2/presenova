<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UtilityPageController extends Controller
{
    public function call(Request $request): Response
    {
        return response()->view('utility.call');
    }

    public function forgotPassword(Request $request): Response
    {
        return response()->view('utility.forgot-password');
    }

    public function register(Request $request): Response
    {
        return response()->view('utility.register');
    }

    public function resetPassword(Request $request): Response
    {
        return response()->view('utility.reset-password');
    }
}
