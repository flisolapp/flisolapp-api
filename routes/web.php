<?php

use Illuminate\Support\Facades\Route;

//Route::get('/', function () {
//    return view('welcome');
//});

Route::get('/', fn() => response()->json(['api' => 'flisolapp-api', 'status' => 'ok']));
