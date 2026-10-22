<?php

namespace App\Support;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** The black list of email addresses: deleted spammers cannot sign up again with the same address. */
class BannedEmails
{
    public static function add(User $user): void
    {
        DB::table('banned_emails')->updateOrInsert(
            ['email' => Str::lower(trim($user->email))],
            ['name' => $user->name, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public static function has(string $email): bool
    {
        return DB::table('banned_emails')->where('email', Str::lower(trim($email)))->exists();
    }

    /** Validation rule for the email field of signing up and of the profile. */
    public static function rule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (is_string($value) && self::has($value)) {
                $fail(__('This email address cannot be used on the site.'));
            }
        };
    }
}
