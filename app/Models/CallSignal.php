<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Connection details one browser in a call passes to the other (offer, answer, network candidates). */
class CallSignal extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'type', 'payload'];
}
