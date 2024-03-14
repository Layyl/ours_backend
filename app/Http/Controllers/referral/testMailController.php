<?php

namespace App\Http\Controllers\referral;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\testMail;

class testMailController extends Controller
{
    public function sendEmail(){
        $subject = "Test Subject";
        $body = "Test Message";

        Mail::to('tlmb1297@gmail.com')->send(new testMail($subject, $body));
    }
}
