<?php

namespace App\Http\Controllers;

use App\Events\NotificationUnreadCountUpdated;
use App\Models\User;
use App\Services\NotificationFeedService;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function index(NotificationFeedService $notificationFeed): View
    {
        return $this->showFeed(
            $notificationFeed,
            'user',
            'layouts.app',
            'Thông báo',
            'Các cập nhật về sách cá nhân và đề xuất của bạn.',
            'notifications.read',
            'notifications.destroy',
            'notifications.read-all',
        );
    }

    public function indexAdmin(NotificationFeedService $notificationFeed): View
    {
        return $this->showFeed(
            $notificationFeed,
            'admin',
            'layouts.admin',
            'Thông báo',
            'Các đề xuất mới và cập nhật trong hàng chờ duyệt.',
            'admin.notifications.read',
            'admin.notifications.destroy',
            'admin.notifications.read-all',
        );
    }

    public function poll(NotificationFeedService $notificationFeed): JsonResponse
    {
        return $this->pollFeed('user', $notificationFeed);
    }

    public function pollAdmin(NotificationFeedService $notificationFeed): JsonResponse
    {
        return $this->pollFeed('admin', $notificationFeed);
    }

    public function markAsRead(Request $request, string $notification, NotificationFeedService $notificationFeed): RedirectResponse|JsonResponse
    {
        $this->markFeedNotificationAsRead($notification, 'user', $notificationFeed);

        if ($request->boolean('return_to_feed')) {
            return redirect()->route('notifications.index');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'read' => true,
                'unread_count' => $notificationFeed->unreadCount(Auth::user(), 'user'),
            ]);
        }

        return redirect()->route('library');
    }

    public function markAsReadAdmin(Request $request, string $notification, NotificationFeedService $notificationFeed): RedirectResponse|JsonResponse
    {
        $this->markFeedNotificationAsRead($notification, 'admin', $notificationFeed);

        if ($request->boolean('return_to_feed')) {
            return redirect()->route('admin.notifications.index');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'read' => true,
                'unread_count' => $notificationFeed->unreadCount(Auth::user(), 'admin'),
            ]);
        }

        return redirect()->route('admin.public-submissions.index');
    }

    public function destroy(string $notification, NotificationFeedService $notificationFeed): RedirectResponse
    {
        $this->deleteFeedNotification($notification, 'user', $notificationFeed);

        return redirect()->route('notifications.index')->with('status', 'Đã xóa thông báo.');
    }

    public function destroyAdmin(string $notification, NotificationFeedService $notificationFeed): RedirectResponse
    {
        $this->deleteFeedNotification($notification, 'admin', $notificationFeed);

        return redirect()->route('admin.notifications.index')->with('status', 'Đã xóa thông báo.');
    }

    public function markAllAsRead(NotificationFeedService $notificationFeed): RedirectResponse
    {
        $this->markFeedAsRead('user', $notificationFeed);

        return redirect()->route('notifications.index')->with('status', 'Đã đánh dấu thông báo thư viện là đã đọc.');
    }

    public function markAllAsReadAdmin(NotificationFeedService $notificationFeed): RedirectResponse
    {
        $this->markFeedAsRead('admin', $notificationFeed);

        return redirect()->route('admin.notifications.index')->with('status', 'Đã đánh dấu thông báo quản trị là đã đọc.');
    }

    private function showFeed(
        NotificationFeedService $notificationFeed,
        string $audience,
        string $layout,
        string $title,
        string $description,
        string $readRoute,
        string $deleteRoute,
        string $readAllRoute,
    ): View {
        $user = Auth::user();
        $notifications = $notificationFeed->query($user, $audience)->latest()->paginate(20);
        $unreadCount = $notificationFeed->unreadCount($user, $audience);

        return view('notifications.index', compact(
            'audience',
            'description',
            'deleteRoute',
            'layout',
            'notifications',
            'readAllRoute',
            'readRoute',
            'title',
            'unreadCount',
        ));
    }

    private function pollFeed(string $audience, NotificationFeedService $notificationFeed): JsonResponse
    {
        $user = Auth::user();
        $notifications = $notificationFeed->query($user, $audience)
            ->latest()
            ->limit(30)
            ->get();

        return response()->json([
            'unread_count' => $notificationFeed->unreadCount($user, $audience),
            'notifications' => $notifications->map(fn ($notification): array => array_merge(
                [
                    'id' => $notification->id,
                    'read_at' => $notification->read_at?->toIso8601String(),
                    'created_at' => $notification->created_at?->toIso8601String(),
                ],
                $notification->data,
            )),
        ]);
    }

    private function markFeedNotificationAsRead(string $id, string $audience, NotificationFeedService $notificationFeed): void
    {
        $user = Auth::user();
        $query = $notificationFeed->query($user, $audience)->where('id', $id);
        abort_unless((clone $query)->exists(), 404);

        $updated = $query->whereNull('read_at')->update(['read_at' => now()]);
        if ($updated > 0) {
            $this->broadcastUnreadCount($user, $audience, $notificationFeed->unreadCount($user, $audience));
        }
    }

    private function deleteFeedNotification(string $id, string $audience, NotificationFeedService $notificationFeed): void
    {
        $user = Auth::user();
        $notification = $notificationFeed->query($user, $audience)->where('id', $id)->firstOrFail();
        $wasUnread = $notification->read_at === null;
        $notification->delete();

        if ($wasUnread) {
            $this->broadcastUnreadCount($user, $audience, $notificationFeed->unreadCount($user, $audience));
        }
    }

    private function markFeedAsRead(string $audience, NotificationFeedService $notificationFeed): void
    {
        $user = Auth::user();
        $updated = $notificationFeed->query($user, $audience)->whereNull('read_at')->update(['read_at' => now()]);
        if ($updated > 0) {
            $this->broadcastUnreadCount($user, $audience, $notificationFeed->unreadCount($user, $audience));
        }
    }

    private function broadcastUnreadCount(User $user, string $audience, int $unreadCount): void
    {
        try {
            event(new NotificationUnreadCountUpdated((int) $user->id, $audience, $unreadCount));
        } catch (BroadcastException $exception) {
            Log::warning('The unread notification count could not be broadcast.', [
                'user_id' => $user->id,
                'audience' => $audience,
                'exception' => $exception::class,
            ]);
        }
    }
}
