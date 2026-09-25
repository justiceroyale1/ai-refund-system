<?php

namespace App\Notifications\Customer;

use App\Enums\CustomerNotificationType;
use Illuminate\Notifications\Notification;

abstract class CustomerNotification extends Notification
{
    public function __construct(public readonly int $conversationId) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array{type: string, title: string, message: string, conversation_id: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type()->value,
            'title' => $this->title(),
            'message' => $this->message(),
            'conversation_id' => $this->conversationId,
        ];
    }

    public function databaseType(object $notifiable): string
    {
        return $this->type()->value;
    }

    public function broadcastType(): string
    {
        return $this->type()->value;
    }

    abstract protected function type(): CustomerNotificationType;

    abstract protected function title(): string;

    abstract protected function message(): string;
}
