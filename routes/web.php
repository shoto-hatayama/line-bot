<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SearchRestaurantController;
use App\Services\CallFoodApi;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
// トップ画面
Route::get('/', [SearchRestaurantController::class, 'index']);
// 店舗情報詳細画面
Route::get('/detail/{id}', [SearchRestaurantController::class, 'detail']);
// 近くの飲食店を検索するAPI
Route::post('/nearByRestaurant', [CallFoodApi::class, 'getNearByRestaurant']);
