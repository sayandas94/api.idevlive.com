<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mail;
use App\Mail\RecoverPassword;

class MailController extends Controller
{
    public function index()
    {
        Mail::to('sayan.das94@gmail.com')->send(new RecoverPassword([
            'title' => 'Test Mail',
            'body' => 'The body'
        ]));
    }
}
