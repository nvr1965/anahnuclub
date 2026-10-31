<?php

namespace App\Support;

use App\Translation\InterfaceStrings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The invitation picture a shared link shows (1200×630), one per language.
 * The site came with pictures for its first languages (public/images/invite); for a language an
 * administrator adds, the admin Languages page draws one from the language's interface texts
 * (resources/js/lib/invite-image.ts) and uploads it here, onto the public disk.
 */
class InviteImage
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    /** The interface texts the picture is drawn from; they are translated with the rest of the interface. */
    public const KEYS = [
        'slogan' => 'The best social network without censorship',
        'b1' => 'Write in your language, everything is translated',
        'b2' => 'Messages, files and video calls',
        'b3' => 'Groups, photos, videos and events',
        'cta' => 'Join for free',
        'reply' => 'Hi! How are you?',
        'from_ru' => 'Translated from Russian',
        'from_he' => 'Translated from Hebrew',
        'answer' => 'Great! Good to see you',
        'online' => 'online',
        'post' => 'Picnic on the promenade on Saturday, who is in?',
        'like' => 'Like',
        'comment' => 'Comment',
    ];

    /**
     * The picture's texts in this language, English where there is none yet,
     * and whether all of them are translated (only then is the picture worth drawing).
     *
     * @return array{texts: array<string, string>, ready: bool}
     */
    public static function texts(string $code): array
    {
        $strings = InterfaceStrings::read($code);
        $texts = [];
        $ready = true;
        foreach (self::KEYS as $name => $key) {
            $ready = $ready && (isset($strings[$key]) || $code === 'en');
            $texts[$name] = $strings[$key] ?? $key;
        }

        return ['texts' => $texts, 'ready' => $ready];
    }

    /** Where a drawn picture lives on the public disk. */
    public static function path(string $code): string
    {
        return "invite/$code.jpg";
    }

    /** A picture drawn on the site for this language, which wins over the one the site came with. */
    public static function drawn(string $code): bool
    {
        return Storage::disk('public')->exists(self::path($code));
    }

    /** Whether the language has a picture of its own, drawn or bundled. */
    public static function exists(string $code): bool
    {
        return self::drawn($code) || is_file(public_path("images/invite/$code.jpg"));
    }

    /** The picture for this language, the English one if it has none; a new drawing gets a new address, so networks fetch it again. */
    public static function url(string $code): string
    {
        if (self::drawn($code)) {
            return Storage::disk('public')->url(self::path($code)).'?v='.Storage::disk('public')->lastModified(self::path($code));
        }

        return asset('images/invite/'.(is_file(public_path("images/invite/$code.jpg")) ? $code : 'en').'.jpg');
    }

    public static function store(string $code, UploadedFile $file): void
    {
        Storage::disk('public')->putFileAs('invite', $file, "$code.jpg");
    }

    public static function forget(string $code): void
    {
        Storage::disk('public')->delete(self::path($code));
    }
}
