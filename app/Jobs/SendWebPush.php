<?php

namespace App\Jobs;

use App\Support\WebPush;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** A notification to the members' phones and computers, sent after the page has answered. */
class SendWebPush implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $userIds
     * @param  array<string, mixed>  $payload  title, body, url, tag, icon, kind
     */
    public function __construct(public array $userIds, public array $payload, public int $ttl = 86400) {}

    public function handle(): void
    {
        WebPush::toUsers($this->userIds, $this->payload, $this->ttl);
    }
}
