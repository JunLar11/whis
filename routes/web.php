<?php
use App\Controllers\Home;
use App\Models\User;
use Whis\Auth\Auth;
use Whis\Http\Response;
use Whis\Routing\Route;
use Whis\Storage\Storage;

Storage::Routes();
Auth::Routes();
Route::get('', [Home::class,'create']);
Route::get('/form', function () {
    return view('form');
});
Route::get('/{id:\d+}', function (int $id) {
    return json(['id' => $id]);
});