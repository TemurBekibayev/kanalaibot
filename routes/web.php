<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/mini-app/{any?}', function () {
    return view('miniapp');
})->where('any', '.*');
