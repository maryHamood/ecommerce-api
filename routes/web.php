<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-ui', function () {
    return response()->file(resource_path('test-ui/index.html'), ['Content-Type' => 'text/html; charset=UTF-8']);
});

Route::get('/test-ui/app.js', function () {
    return response()->file(resource_path('test-ui/app.js'), ['Content-Type' => 'text/javascript; charset=UTF-8']);
});

Route::get('/test-ui/app.css', function () {
    return response()->file(resource_path('test-ui/app.css'), ['Content-Type' => 'text/css; charset=UTF-8']);
});
