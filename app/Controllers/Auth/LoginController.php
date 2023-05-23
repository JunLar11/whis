<?php

namespace App\Controllers\Auth;

use App\Models\User;
use Whis\Cryptic\Hasher;
use Whis\Http\Controller;
use Whis\Http\Request;
use PHLAK\StrGen\Generator as StrGenerator;

class LoginController extends Controller
{
    public function create(){
        if(!isGuest()){
            return redirect('/');
        }
        $generator=new StrGenerator();
        $token=$generator->alphaNumeric(32);
        session()->set('_token',$token);
        return view('auth/login',['token'=>$token]);
    }
    public function store(Request $request, Hasher $hasher){
        $data=$request->validate(['email' => 'required|email', 'password' => 'required']);
        $user=User::firstWhere('email',$data['email']);
        if(is_null($user) || !$hasher->verify($data['password'],$user->password)) {
            return back()->withErrors(['email' => ["email"=>"Credentials don't match"]]);
        }
        $user->login();
        return redirect('/');
    }
    public function destroy(){
        if(isGuest()){
            return redirect('/');
        }
        auth()->logout();
        return redirect('/');
    }
}
