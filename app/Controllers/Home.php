<?php

namespace App\Controllers;

use Whis\Http\Controller;
use Whis\Http\Request;

class Home extends Controller
{
    public function create(){
        if(isGuest()){
            return view('home', ['user' => "Guest"]);
            
        }
        return view('home', ['user' => auth()->name]);
    }

    public function store(Request $request){
        $request->validate(['files' => 'required']);
        $files=$request->file('files',["type"=>"filetype:png/jpeg/jpg/pdf","size"=>"filesize:1000000"]);
        foreach ($files as $file) {
            $file->store('uploads','test');
        }
        return redirect('/');
    }
}
