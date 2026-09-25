<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminNotificationResource;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Notifications\DatabaseNotification;

class AdminNotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $admin = $this->admin($request);
        $notifications = $admin->notifications()
            ->reorder()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);

        return AdminNotificationResource::collection($notifications)
            ->additional([
                'meta' => [
                    'unread_count' => $admin->unreadNotifications()->count(),
                ],
            ]);
    }

    public function read(string $notification, Request $request): AdminNotificationResource
    {
        $admin = $this->admin($request);
        /** @var DatabaseNotification $ownedNotification */
        $ownedNotification = $admin->notifications()->findOrFail($notification);

        $ownedNotification->markAsRead();

        return (new AdminNotificationResource($ownedNotification))
            ->additional([
                'meta' => [
                    'unread_count' => $admin->unreadNotifications()->count(),
                ],
            ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $admin = $this->admin($request);
        $timestamp = now();

        $admin->unreadNotifications()->update([
            'read_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return response()->json([
            'data' => [
                'unread_count' => 0,
            ],
        ]);
    }

    private function admin(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
