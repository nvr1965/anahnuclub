<?php

namespace App\Providers;

use Anthropic\Client;
use App\Models\Comment;
use App\Models\Event;
use App\Models\Group;
use App\Models\Message;
use App\Models\Photo;
use App\Models\Post;
use App\Models\User;
use App\Models\Video;
use App\Translation\ClaudeTranslator;
use App\Translation\DeepLTranslator;
use App\Translation\FallbackTranslator;
use App\Translation\NullTranslator;
use App\Translation\Translator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $claude = fn () => new ClaudeTranslator(
            new Client(apiKey: config('services.anthropic.key')),
            config('services.anthropic.translation_model'),
        );

        $this->app->singleton(Translator::class, fn () => match (config('services.translation.driver')) {
            'claude' => $claude(),
            // Languages DeepL does not know (Uzbek, Georgian, ...) go to Claude when its key is set.
            'deepl' => new FallbackTranslator(
                new DeepLTranslator(config('services.deepl.key')),
                config('services.anthropic.key') ? $claude() : null,
            ),
            default => new NullTranslator,
        });
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
