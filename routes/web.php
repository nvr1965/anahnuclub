<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\BlockController;
use App\Http\Controllers\CallController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EmbedController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupInviteController;
use App\Http\Controllers\GroupRoleController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FeedController::class, 'welcome'])->name('home');

// Old posts point at pictures of the Joomla site (/images/...); the import copied them into storage.
Route::get('images/{path}', function (string $path) {
    abort_unless(! str_contains($path, '..') && is_file(storage_path("app/public/joomla/images/$path")), 404);

    return redirect('/storage/joomla/images/'.$path, 301);
})->where('path', '.*');

// For search engines.
Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('sitemap.xml', [SeoController::class, 'index'])->name('sitemap');
Route::get('sitemap-pages.xml', [SeoController::class, 'pages'])->name('sitemap.pages');
Route::get('sitemap-posts-{part}.xml', [SeoController::class, 'posts'])->whereNumber('part')->name('sitemap.posts');
Route::get('{key}.txt', [SeoController::class, 'indexNowKey'])->where('key', '[A-Za-z0-9-]{8,128}')->name('indexnow.key');

Route::post('locale', [LocaleController::class, 'update'])->name('locale.update');
Route::post('translations', TranslationController::class)->middleware('throttle:60,1')->name('translations');

// Open to anyone with the link, so posts can be shared outside the club; guests can page through them all.
Route::get('posts', [FeedController::class, 'archive'])->name('posts.archive');
Route::get('posts/page/{page}', [FeedController::class, 'archive'])->whereNumber('page')->name('posts.archive.page');
Route::get('posts/{post}', [PostController::class, 'show'])->name('posts.show');
// A public post for other sites to show in an <iframe>.
Route::get('embed/posts/{post}', [EmbedController::class, 'post'])->whereNumber('post')->name('embed.post');

Route::get('p/{page}', [PageController::class, 'show'])->name('pages.show');
Route::get('contact', [ContactController::class, 'create'])->name('contact');
Route::post('contact', [ContactController::class, 'store'])->middleware('throttle:10,1')->name('contact.store');

// For members; a guest opening a shared link gets the sign-in page with the link's card (PreviewForGuests).
Route::middleware(['preview.guest', 'verified', 'rules'])->group(function () {
    Route::get('groups/{group}', [GroupController::class, 'show'])->name('groups.show');
    Route::get('videos/{video}', [VideoController::class, 'show'])->name('videos.show');
    Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('welcome', [WelcomeController::class, 'show'])->name('welcome');
    Route::post('welcome', [WelcomeController::class, 'accept'])->name('welcome.accept');
});

Route::middleware(['auth', 'verified', 'rules'])->group(function () {
    Route::get('feed', FeedController::class)->name('dashboard');
    Route::get('search', SearchController::class)->name('search');
    Route::get('groups', [GroupController::class, 'index'])->name('groups.index');
    Route::post('groups', [GroupController::class, 'store'])->middleware('throttle:5,60')->name('groups.store');
    Route::post('groups/{group}/join', [GroupController::class, 'join'])->name('groups.join');
    Route::put('groups/{group}', [GroupController::class, 'update'])->name('groups.update');
    Route::delete('groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');
    Route::post('groups/{group}/members/{user}/approve', [GroupController::class, 'approve'])->name('groups.members.approve');
    Route::delete('groups/{group}/members/{user}', [GroupController::class, 'removeMember'])->name('groups.members.destroy');
    Route::get('groups/{group}/invite', [GroupInviteController::class, 'candidates'])->name('groups.invite.candidates');
    Route::post('groups/{group}/invite', [GroupInviteController::class, 'store'])->middleware('throttle:30,60')->name('groups.invite.store');
    Route::post('groups/{group}/invitations/{invitation}/accept', [GroupInviteController::class, 'accept'])->name('groups.invitations.accept');
    Route::delete('groups/{group}/invitations/{invitation}', [GroupInviteController::class, 'decline'])->name('groups.invitations.destroy');
    Route::post('groups/{group}/offers', [GroupRoleController::class, 'offer'])->name('groups.offers.store');
    Route::post('groups/{group}/offers/{offer}/accept', [GroupRoleController::class, 'accept'])->name('groups.offers.accept');
    Route::delete('groups/{group}/offers/{offer}', [GroupRoleController::class, 'cancel'])->name('groups.offers.destroy');
    Route::delete('groups/{group}/roles/{user}', [GroupRoleController::class, 'demote'])->name('groups.roles.destroy');

    Route::post('posts', [PostController::class, 'store'])->name('posts.store');
    Route::get('share', ShareController::class)->name('share');
    Route::get('link-preview', [PostController::class, 'linkPreview'])->middleware('throttle:30,1')->name('links.preview');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('posts/{post}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::patch('posts/{post}', [PostController::class, 'update'])->name('posts.update');

    Route::get('people', [PeopleController::class, 'index'])->name('people.index');
    Route::get('people/{user}', [PeopleController::class, 'show'])->name('people.show');
    Route::patch('profile/about', [PeopleController::class, 'update'])->name('people.update');
    Route::post('profile/avatar', [PeopleController::class, 'avatar'])->name('people.avatar');
    Route::post('profile/cover', [PeopleController::class, 'cover'])->name('people.cover');

    Route::get('friends', [FriendController::class, 'index'])->name('friends.index');
    Route::get('friends/list', [FriendController::class, 'list'])->name('friends.list');
    Route::get('friends/requests', [FriendController::class, 'requests'])->name('friends.requests');
    Route::get('friends/suggestions', [FriendController::class, 'suggestions'])->name('friends.suggestions');
    Route::get('friends/all', [FriendController::class, 'all'])->name('friends.all');
    Route::get('friends/birthdays', [FriendController::class, 'birthdays'])->name('friends.birthdays');
    Route::get('friends/lists', [FriendController::class, 'lists'])->name('friends.lists');
    Route::post('friends/lists', [FriendController::class, 'storeList'])->name('friends.lists.store');
    Route::patch('friends/lists/{friendList}', [FriendController::class, 'updateList'])->name('friends.lists.update');
    Route::delete('friends/lists/{friendList}', [FriendController::class, 'destroyList'])->name('friends.lists.destroy');
    Route::post('friends/lists/{friendList}/people/{user}', [FriendController::class, 'addToList'])->name('friends.lists.add');
    Route::delete('friends/lists/{friendList}/people/{user}', [FriendController::class, 'removeFromList'])->name('friends.lists.remove');
    Route::post('people/{user}/dismiss', [FriendController::class, 'dismiss'])->name('friends.dismiss');
    Route::post('people/{user}/friend', [FriendController::class, 'store'])->name('friends.store');
    Route::delete('people/{user}/friend', [FriendController::class, 'destroy'])->name('friends.destroy');
    Route::post('people/{user}/block', [BlockController::class, 'blockUser'])->name('blocks.user');
    Route::delete('people/{user}/block', [BlockController::class, 'unblockUser'])->name('blocks.user.destroy');
    Route::post('groups/{group}/block', [BlockController::class, 'blockGroup'])->name('blocks.group');
    Route::delete('groups/{group}/block', [BlockController::class, 'unblockGroup'])->name('blocks.group.destroy');

    Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('messages/{conversation}', [MessageController::class, 'store'])->middleware('throttle:30,1')->name('messages.store');
    Route::get('messages/files/{attachment}', [MessageController::class, 'file'])->name('messages.files');
    Route::post('people/{user}/message', [MessageController::class, 'start'])->name('messages.start');
    Route::post('messages/{conversation}/accept', [MessageController::class, 'accept'])->name('messages.accept');
    Route::post('messages/{conversation}/call', [CallController::class, 'start'])->middleware('throttle:20,1')->name('calls.start');
    Route::get('calls/ringing', [CallController::class, 'ringing'])->name('calls.ringing');
    Route::get('calls/{call}', [CallController::class, 'show'])->middleware('throttle:300,1')->name('calls.show');
    Route::post('calls/{call}/signal', [CallController::class, 'signal'])->middleware('throttle:300,1')->name('calls.signal');
    Route::post('calls/{call}/accept', [CallController::class, 'accept'])->name('calls.accept');
    Route::post('calls/{call}/decline', [CallController::class, 'decline'])->name('calls.decline');
    Route::post('calls/{call}/end', [CallController::class, 'end'])->name('calls.end');
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
    Route::patch('videos/{video}', [VideoController::class, 'update'])->name('videos.update');
    Route::delete('videos/{video}', [VideoController::class, 'destroy'])->name('videos.destroy');
    Route::post('videos/{video}/like', [LikeController::class, 'video'])->name('videos.like');

    Route::get('events', [EventController::class, 'index'])->name('events.index');
    Route::post('events', [EventController::class, 'store'])->name('events.store');
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
    Route::post('mail/test', [Admin\MailController::class, 'test'])->name('mail.test');
    Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::post('users/verify', [Admin\UserController::class, 'verify'])->name('users.verify');
    Route::delete('users', [Admin\UserController::class, 'destroy'])->name('users.destroy');
    Route::delete('banned-emails/{id}', [Admin\UserController::class, 'unban'])->whereNumber('id')->name('users.unban');
    Route::patch('users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
    Route::post('users/{user}/purge', [Admin\UserController::class, 'purge'])->name('users.purge');
    Route::get('logins', [Admin\LoginController::class, 'index'])->name('logins.index');
    Route::get('posts', [Admin\PostController::class, 'index'])->name('posts.index');
    Route::delete('posts', [Admin\PostController::class, 'destroy'])->name('posts.destroy');
    Route::get('moderation', [Admin\ModerationController::class, 'index'])->name('moderation.index');
    Route::delete('moderation', [Admin\ModerationController::class, 'destroy'])->name('moderation.destroy');
    Route::post('moderation/keep', [Admin\ModerationController::class, 'keep'])->name('moderation.keep');
    Route::put('moderation/words', [Admin\ModerationController::class, 'words'])->name('moderation.words');
    Route::get('groups', [Admin\GroupController::class, 'index'])->name('groups.index');
    Route::patch('groups/{group:id}', [Admin\GroupController::class, 'update'])->name('groups.update');
    Route::delete('groups/{group:id}', [Admin\GroupController::class, 'destroy'])->name('groups.destroy');
    Route::get('translators', [Admin\TranslatorController::class, 'index'])->name('translators.index');
    Route::put('translators', [Admin\TranslatorController::class, 'update'])->name('translators.update');
    Route::put('translators/{translator}/keys', [Admin\TranslatorController::class, 'keys'])->name('translators.keys');
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

// Any other address: the old Joomla site's ones lead to the same content here, the rest is "not found".
Route::fallback([SeoController::class, 'legacy']);
