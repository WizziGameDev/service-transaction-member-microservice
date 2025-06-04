<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


use App\Http\Controllers\TransactionMemberController;

Route::get('/transactions-member', [TransactionMemberController::class, 'index']);
Route::get('/transactions-member/{id}', [TransactionMemberController::class, 'show']);
Route::post('/transactions-member', [TransactionMemberController::class, 'store']);
Route::put('/transactions-member/{id}', [TransactionMemberController::class, 'update']);
Route::patch('/transactions-member/{id}/{status}', [TransactionMemberController::class, 'updateStatus']);
Route::delete('/transactions-member/{id}', [TransactionMemberController::class, 'destroy']);