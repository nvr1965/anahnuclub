<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Album extends Model
{
    protected $fillable = ['user_id', 'group_id', 'name', 'description', 'created_at', 'updated_at'];

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }
}
