<?php

namespace App\Http\Controllers;

use App\Events\WebSocketDemo;
use Illuminate\Http\Request;

class testController extends Controller
{
    public function test(){
        event(new WebSocketDemo('hello'));
    }
}
