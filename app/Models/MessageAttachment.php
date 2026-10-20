<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A file sent in a chat, stored on the private disk. */
class MessageAttachment extends Model
{
    /** Pictures shown in the chat itself; every other file is offered for download. */
    public const IMAGES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    protected $fillable = ['message_id', 'path', 'name', 'mime', 'size'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function isImage(): bool
    {
        return in_array($this->mime, self::IMAGES, true);
    }

    /** @return array{id: int, name: string, size: int, image: bool, url: string} */
    public function card(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'size' => $this->size, 'image' => $this->isImage(), 'url' => route('messages.files', $this)];
    }
}
