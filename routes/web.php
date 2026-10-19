<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\BlockController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
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

Route::get('p/{page}', [PageController::class, 'show'])->name('pages.show');
Route::get('contact', [ContactController::class, 'create'])->name('contact');
Route::post('contact', [ContactController::class, 'store'])->middleware('throttle:10,1')->name('contact.store');

Route::middleware(['auth', 'verified'])->group(function () {
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
    Route::get('friends/list', [FriendController::class, 'list'])->name('friends.list');
    Route::post('people/{user}/friend', [FriendController::class, 'store'])->name('friends.store');
    Route::delete('people/{user}/friend', [FriendController::class, 'destroy'])->name('friends.destroy');
    Route::post('people/{user}/block', [BlockController::class, 'blockUser'])->name('blocks.user');
    Route::delete('people/{user}/block', [BlockController::class, 'unblockUser'])->name('blocks.user.destroy');
    Route::post('groups/{group}/block', [BlockController::class, 'blockGroup'])->name('blocks.group');
    Route::delete('groups/{group}/block', [BlockController::class, 'unblockGroup'])->name('blocks.group.destroy');

    Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('messages/{conversation}', [MessageController::class, 'store'])->middleware('throttle:30,1')->name('messages.store');
    Route::post('people/{user}/message', [MessageController::class, 'start'])->name('messages.start');
    Route::post('messages/{conversation}/accept', [MessageController::class, 'accept'])->name('messages.accept');
    Route::post('messages/{conversation}/decline', [MessageController::class, 'decline'])->name('messages.decline');
    Route::post('people/{user}/message-block', [BlockController::class, 'blockMessages'])->name('blocks.messages.store');
    Route::delete('people/{user}/message-block', [BlockController::class, 'unblockMessages'])->name('blocks.messages.destroy');
    Route::put('settings/messages', [BlockController::class, 'messagesFrom'])->name('settings.messages');

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

    Route::patch('posts/{post}/audience', [PostController::class, 'audience'])->name('posts.audience');
    Route::post('posts/{post}/like', [LikeController::class, 'toggle'])->name('posts.like');
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('index');
    Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
    Route::post('users/{user}/purge', [Admin\UserController::class, 'purge'])->name('users.purge');
    Route::get('posts', [Admin\PostController::class, 'index'])->name('posts.index');
    Route::delete('posts', [Admin\PostController::class, 'destroy'])->name('posts.destroy');
    Route::get('groups', [Admin\GroupController::class, 'index'])->name('groups.index');
    Route::patch('groups/{group:id}', [Admin\GroupController::class, 'update'])->name('groups.update');
    Route::delete('groups/{group:id}', [Admin\GroupController::class, 'destroy'])->name('groups.destroy');
    Route::get('translators', [Admin\TranslatorController::class, 'index'])->name('translators.index');
    Route::put('translators', [Admin\TranslatorController::class, 'update'])->name('translators.update');
    Route::post('translators/{translator}/check', [Admin\TranslatorController::class, 'check'])->name('translators.check');
    Route::get('languages', [Admin\LanguageController::class, 'index'])->name('languages.index');
    Route::post('languages', [Admin\LanguageController::class, 'store'])->name('languages.store');
    Route::patch('languages/{language}', [Admin\LanguageController::class, 'update'])->name('languages.update');
    Route::get('languages/{language}/texts', [Admin\LanguageController::class, 'texts'])->name('languages.texts');
    Route::put('languages/{language}/texts', [Admin\LanguageController::class, 'correct'])->name('languages.correct');
    Route::post('languages/{language}/retranslate', [Admin\LanguageController::class, 'retranslate'])->name('languages.retranslate');
    Route::post('languages/{language}/refill', [Admin\LanguageController::class, 'refill'])->name('languages.refill');
    Route::delete('languages/{language}', [Admin\LanguageController::class, 'destroy'])->name('languages.destroy');
    Route::get('pages', [Admin\PageController::class, 'index'])->name('pages.index');
    Route::post('pages', [Admin\PageController::class, 'store'])->name('pages.store');
    Route::put('pages/{page}', [Admin\PageController::class, 'update'])->name('pages.update');
    Route::delete('pages/{page}', [Admin\PageController::class, 'destroy'])->name('pages.destroy');
    Route::post('pages/{page}/translate', [Admin\PageController::class, 'translate'])->name('pages.translate');
    Route::patch('site-settings', [Admin\PageController::class, 'settings'])->name('settings.update');
    Route::get('contact', [Admin\ContactController::class, 'index'])->name('contact.index');
    Route::patch('contact/{message}', [Admin\ContactController::class, 'update'])->name('contact.update');
    Route::delete('contact/{message}', [Admin\ContactController::class, 'destroy'])->name('contact.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
