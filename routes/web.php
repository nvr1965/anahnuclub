<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TranslationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FeedController::class, 'welcome'])->name('home');

Route::post('locale', [LocaleController::class, 'update'])->name('locale.update');
Route::post('translations', TranslationController::class)->middleware('throttle:60,1')->name('translations');

Route::middleware(['auth'])->group(function () {
    Route::get('feed', FeedController::class)->name('dashboard');
    Route::get('search', SearchController::class)->name('search');
    Route::get('groups', [GroupController::class, 'index'])->name('groups.index');
    Route::get('groups/{group}', [GroupController::class, 'show'])->name('groups.show');
    Route::post('groups/{group}/join', [GroupController::class, 'join'])->name('groups.join');

    Route::post('posts', [PostController::class, 'store'])->name('posts.store');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('posts/{post}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::post('posts/{post}/like', [LikeController::class, 'toggle'])->name('posts.like');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('index');
    Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
    Route::post('users/{user}/purge', [Admin\UserController::class, 'purge'])->name('users.purge');
    Route::get('posts', [Admin\PostController::class, 'index'])->name('posts.index');
    Route::delete('posts', [Admin\PostController::class, 'destroy'])->name('posts.destroy');
    Route::get('groups', [Admin\GroupController::class, 'index'])->name('groups.index');
    Route::patch('groups/{group:id}', [Admin\GroupController::class, 'update'])->name('groups.update');
    Route::delete('groups/{group:id}', [Admin\GroupController::class, 'destroy'])->name('groups.destroy');
    Route::get('languages', [Admin\LanguageController::class, 'index'])->name('languages.index');
    Route::patch('languages/{language}', [Admin\LanguageController::class, 'update'])->name('languages.update');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
