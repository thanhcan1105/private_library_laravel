<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PublicBookSubmissionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $event,
        public string $bookTitle,
        public string $audience,
        public int $bookId,
        public ?string $reviewNote = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, int|string|null> */
    public function toArray(object $notifiable): array
    {
        $isNewSubmission = $this->event === 'submitted';
        $isApproved = $this->event === 'approved';

        return [
            'title' => $isNewSubmission ? 'Có đề xuất sách mới' : ($isApproved ? 'Sách đã được duyệt' : 'Đề xuất sách chưa được duyệt'),
            'message' => $isNewSubmission
                ? 'Sách “'.$this->bookTitle.'” đang chờ quản lý xem xét.'
                : ($isApproved
                    ? 'Sách “'.$this->bookTitle.'” đã được đưa vào kho công khai.'
                    : 'Sách “'.$this->bookTitle.'” chưa được duyệt.'),
            'url' => $this->audience === 'admin' ? route('admin.public-submissions.index') : route('library'),
            'event' => $this->event,
            'audience' => $this->audience,
            'book_id' => $this->bookId,
            'review_note' => $this->reviewNote,
        ];
    }
}
