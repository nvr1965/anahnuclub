<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A Google, Facebook (or other) account a member signs in with. */
class SocialAccount extends Model
{
    protected $fillable = ['provider', 'provider_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
