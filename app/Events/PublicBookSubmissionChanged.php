<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PublicBookSubmissionChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $bookId,
        public string $event,
        public string $title,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.public-submissions')];
    }

    public function broadcastAs(): string
    {
        return 'public.book.submission.changed';
    }

    /** @return array<string, int|string> */
    public function broadcastWith(): array
    {
        return [
            'book_id' => $this->bookId,
            'event' => $this->event,
            'title' => $this->title,
        ];
    }
}
