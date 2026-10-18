<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FeedController::class, 'welcome'])->name('home');

Route::post('locale', [LocaleController::class, 'update'])->name('locale.update');
Route::post('translations', TranslationController::class)->middleware('throttle:60,1')->name('translations');

// Open to anyone with the link, so posts can be shared outside the club.
Route::get('posts/{post}', [PostController::class, 'show'])->name('posts.show');

Route::middleware(['auth'])->group(function () {
    Route::get('feed', FeedController::class)->name('dashboard');
    Route::get('search', SearchController::class)->name('search');
    Route::get('groups', [GroupController::class, 'index'])->name('groups.index');
    Route::get('groups/{group}', [GroupController::class, 'show'])->name('groups.show');
    Route::post('groups/{group}/join', [GroupController::class, 'join'])->name('groups.join');

    Route::post('posts', [PostController::class, 'store'])->name('posts.store');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('posts/{post}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::patch('posts/{post}', [PostController::class, 'update'])->name('posts.update');

    Route::get('people/{user}', [PeopleController::class, 'show'])->name('people.show');
    Route::patch('profile/about', [PeopleController::class, 'update'])->name('people.update');
    Route::post('profile/avatar', [PeopleController::class, 'avatar'])->name('people.avatar');
    Route::post('profile/cover', [PeopleController::class, 'cover'])->name('people.cover');

    Route::get('friends', [FriendController::class, 'index'])->name('friends.index');
    Route::post('people/{user}/friend', [FriendController::class, 'store'])->name('friends.store');
    Route::delete('people/{user}/friend', [FriendController::class, 'destroy'])->name('friends.destroy');

    Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('messages/{conversation}', [MessageController::class, 'store'])->middleware('throttle:30,1')->name('messages.store');
    Route::post('people/{user}/message', [MessageController::class, 'start'])->name('messages.start');

    Route::get('albums/{album}', [AlbumController::class, 'show'])->name('albums.show');
    Route::post('albums', [AlbumController::class, 'store'])->name('albums.store');
    Route::patch('albums/{album}', [AlbumController::class, 'update'])->name('albums.update');
    Route::delete('albums/{album}', [AlbumController::class, 'destroy'])->name('albums.destroy');
    Route::post('albums/{album}/photos', [AlbumController::class, 'upload'])->name('albums.upload');
    Route::patch('photos/{photo}', [AlbumController::class, 'updatePhoto'])->name('photos.update');
    Route::delete('photos/{photo}', [AlbumController::class, 'destroyPhoto'])->name('photos.destroy');
    Route::post('photos/{photo}/like', [LikeController::class, 'photo'])->name('photos.like');

    Route::get('videos', [VideoController::class, 'index'])->name('videos.index');
    Route::post('videos', [VideoController::class, 'store'])->name('videos.store');
    Route::get('videos/{video}', [VideoController::class, 'show'])->name('videos.show');
    Route::patch('videos/{video}', [VideoController::class, 'update'])->name('videos.update');
    Route::delete('videos/{video}', [VideoController::class, 'destroy'])->name('videos.destroy');
    Route::post('videos/{video}/like', [LikeController::class, 'video'])->name('videos.like');

    Route::get('events', [EventController::class, 'index'])->name('events.index');
    Route::post('events', [EventController::class, 'store'])->name('events.store');
    Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::post('events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
    Route::post('events/{event}/respond', [EventController::class, 'respond'])->name('events.respond');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/latest', [NotificationController::class, 'latest'])->name('notifications.latest');
    Route::post('notifications/read', [NotificationController::class, 'readAll'])->name('notifications.read');
    Route::get('notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');

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
