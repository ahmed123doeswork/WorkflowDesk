<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::patch('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    Route::get('/enquiries', [EnquiryController::class, 'index']);
    Route::post('/enquiries', [EnquiryController::class, 'store']);
    Route::get('/enquiries/{enquiry}', [EnquiryController::class, 'show']);
    Route::patch('/enquiries/{enquiry}', [EnquiryController::class, 'update']);
    Route::patch('/enquiries/{enquiry}/assign', [EnquiryController::class, 'assign']);
    Route::patch('/enquiries/{enquiry}/transition', [EnquiryController::class, 'transition']);
});
