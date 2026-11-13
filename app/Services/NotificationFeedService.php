<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use InvalidArgumentException;

class NotificationFeedService
{
    public function query(User $user, string $audience): MorphMany
    {
        $legacyEvents = match ($audience) {
            'admin' => ['submitted'],
            'user' => ['approved', 'rejected'],
            default => throw new InvalidArgumentException('Unknown notification audience.'),
        };

        return $user->notifications()->where(function (Builder $query) use ($audience, $legacyEvents): void {
            $query->where('data->audience', $audience)
                ->orWhere(function (Builder $legacyQuery) use ($legacyEvents): void {
                    $legacyQuery->whereNull('data->audience')
                        ->whereIn('data->event', $legacyEvents);
                });
        });
    }

    public function unreadCount(User $user, string $audience): int
    {
        return $this->query($user, $audience)->whereNull('read_at')->count();
    }

    public function markSubmissionAsRead(User $user, int $bookId): bool
    {
        return $this->query($user, 'admin')
            ->where('data->event', 'submitted')
            ->where('data->book_id', $bookId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]) > 0;
    }
}
