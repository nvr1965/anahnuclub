<?php

namespace App\Providers;

use App\Models\Comment;
use App\Models\Event;
use App\Models\Group;
use App\Models\Message;
use App\Models\Photo;
use App\Models\Post;
use App\Models\User;
use App\Models\Video;
use App\Translation\NullTranslator;
use App\Translation\Translator;
use App\Translation\TranslatorChain;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Group roles already looked up in this request (Group::roleOf).
        $this->app->scoped('group.roles', fn () => new \ArrayObject);
        // The translation block; with no service set up and on, translation is simply off.
        $this->app->singleton(TranslatorChain::class, fn () => TranslatorChain::fromSettings());
        $this->app->singleton(Translator::class, fn ($app) => $app->make(TranslatorChain::class)->available()
            ? $app->make(TranslatorChain::class)
            : new NullTranslator);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'post' => Post::class,
            'comment' => Comment::class,
            'group' => Group::class,
            'user' => User::class,
            'message' => Message::class,
            'video' => Video::class,
            'event' => Event::class,
            'photo' => Photo::class,
        ]);
    }
}
