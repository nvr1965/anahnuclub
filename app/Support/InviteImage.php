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
        'cta' => 'Join the club',
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

    /**
     * The picture for this language, the English one if it has none; a new drawing gets a new address, so networks fetch it again.
     * A drawing from older texts gives way to the site's own picture when that one is up to date.
     */
    public static function url(string $code): string
    {
        if (self::drawn($code) && (self::current($code) || ! self::bundledCurrent($code))) {
            return Storage::disk('public')->url(self::path($code)).'?v='.Storage::disk('public')->lastModified(self::path($code));
        }

        $file = 'images/invite/'.(is_file(public_path("images/invite/$code.jpg")) ? $code : 'en').'.jpg';

        // The address changes with the picture, so networks that keep previews by address fetch the new one.
        return asset($file).'?v='.substr(md5_file(public_path($file)), 0, 8);
    }

    /** A fingerprint of the picture's texts in this language, so a picture drawn from older texts is known to be out of date. */
    public static function signature(string $code): string
    {
        return md5(json_encode(self::texts($code)['texts'], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Whether the language's picture shows its texts as they are now: a drawn one remembers the texts
     * it was drawn from, the ones the site came with are listed in public/images/invite/texts.json.
     * When a text changes (or a picture predates this), the admin Languages page draws it again.
     */
    public static function current(string $code): bool
    {
        if (self::drawn($code)) {
            return Storage::disk('public')->get(self::signaturePath($code)) === self::signature($code);
        }

        return self::bundledCurrent($code);
    }

    private static function bundledCurrent(string $code): bool
    {
        $bundled = json_decode((string) @file_get_contents(public_path('images/invite/texts.json')), true) ?: [];

        return is_file(public_path("images/invite/$code.jpg")) && ($bundled[$code] ?? null) === self::signature($code);
    }

    public static function store(string $code, UploadedFile $file): void
    {
        Storage::disk('public')->putFileAs('invite', $file, "$code.jpg");
        Storage::disk('public')->put(self::signaturePath($code), self::signature($code));
    }

    public static function forget(string $code): void
    {
        Storage::disk('public')->delete([self::path($code), self::signaturePath($code)]);
    }

    private static function signaturePath(string $code): string
    {
        return "invite/$code.sig";
    }
}
