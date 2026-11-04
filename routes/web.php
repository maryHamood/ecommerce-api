<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->file(public_path('test-ui/index.html'));
});

Route::get('/test-ui', function () {
    return response()->file(public_path('test-ui/index.html'));
});

Route::get('/test-ui/{path}', function (string $path) {
    $file = public_path('test-ui/' . $path);

    if (!file_exists($file) || is_dir($file)) {
        abort(404);
    }

    return response()->file($file);
})->where('path', '.*');

