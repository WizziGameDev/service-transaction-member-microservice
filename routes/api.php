<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GraphQLTestController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


use App\Http\Controllers\TransactionController;

Route::apiResource('transactions', TransactionController::class);
Route::get('/test-graphql', [GraphQLTestController::class, 'test']);