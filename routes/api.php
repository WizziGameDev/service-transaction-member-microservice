<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Jobs\TestRabbitMQJob;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


use App\Http\Controllers\TransactionController;

Route::get('/transactions-member', [TransactionController::class, 'index']);
Route::get('/transactions-member/{id}', [TransactionController::class, 'show']);
Route::post('/transactions-member', [TransactionController::class, 'store']);
Route::put('/transactions-member/{id}', [TransactionController::class, 'update']);
Route::patch('/transactions-member/{id}/{status}', [TransactionController::class, 'updateStatus']);
Route::delete('/transactions-member/{id}', [TransactionController::class, 'destroy']);

Route::get('/send-message', function () {
    $message = 'Halo bro! Ini pesan dari Laravel ke RabbitMQ 🚀';
    TestRabbitMQJob::dispatch($message);

    return response()->json([
        'status' => 'success',
        'message' => 'Pesan dikirim ke RabbitMQ!'
    ]);
});