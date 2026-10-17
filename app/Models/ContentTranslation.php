<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentTranslation extends Model
{
    protected $fillable = ['field', 'locale', 'text', 'provider', 'source_hash'];

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }
}
