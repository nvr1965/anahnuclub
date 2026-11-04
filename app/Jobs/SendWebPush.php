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
        try {
            WebPush::toUsers($this->userIds, $this->payload, $this->ttl);
        } catch (\Throwable $e) {
            // Never breaks the message or call it belongs to; the members' "Check notifications" shows it.
            report($e);
            foreach ($this->userIds as $id) {
                WebPush::journal($id, json_encode($this->payload), 'error: '.mb_substr($e->getMessage(), 0, 120));
            }
        }
    }
}
