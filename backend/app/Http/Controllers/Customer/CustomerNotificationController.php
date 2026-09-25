<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\CurrentCustomer;
use App\Http\Resources\CustomerNotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Notifications\DatabaseNotification;

class CustomerNotificationController extends Controller
{
    public function index(CurrentCustomer $currentCustomer): AnonymousResourceCollection
    {
        $customer = $currentCustomer->get();
        $notifications = $customer->notifications()
            ->reorder()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);

        return CustomerNotificationResource::collection($notifications)
            ->additional([
                'meta' => [
                    'unread_count' => $customer->unreadNotifications()->count(),
                ],
            ]);
    }

    public function read(
        string $notification,
        CurrentCustomer $currentCustomer,
    ): CustomerNotificationResource {
        /** @var DatabaseNotification $ownedNotification */
        $ownedNotification = $currentCustomer->get()
            ->notifications()
            ->findOrFail($notification);

        $ownedNotification->markAsRead();

        return (new CustomerNotificationResource($ownedNotification))
            ->additional([
                'meta' => [
                    'unread_count' => $currentCustomer->get()->unreadNotifications()->count(),
                ],
            ]);
    }

    public function readAll(CurrentCustomer $currentCustomer): JsonResponse
    {
        $timestamp = now();

        $currentCustomer->get()->unreadNotifications()->update([
            'read_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return response()->json([
            'data' => [
                'unread_count' => 0,
            ],
        ]);
    }
}
