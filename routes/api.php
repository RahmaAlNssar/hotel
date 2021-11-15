<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth Routes


    Route::post('/logout', 'AuthController@logout');
    Route::post('/login', 'AuthController@login');
    Route::post('/register', 'AuthController@register');
    Route::post('forgot-password', 'AuthController@sendPasswordResetEmail');
    Route::post('reset-password','AuthController@passwordResetProcess');
   


