<?php

namespace App\Traits;

use App\Models\Notification;
use App\Models\User;

trait NotificationTrait
{
    /**
     * Send notification to all admins.
     */
    protected function notifyAdmins(string $title, string $message, string $type, $relatedId = null): void
    {
        $admins = User::where('role', 'admin')->get();

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'related_id' => $relatedId,
            ]);
        }
    }

    /**
     * Send notification to a specific user.
     */
    protected function notifyUser(int $userId, string $title, string $message, string $type, $relatedId = null): void
    {
        Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'related_id' => $relatedId,
        ]);
    }

    /**
     * Send notification to multiple users.
     */
    protected function notifyUsers(array $userIds, string $title, string $message, string $type, $relatedId = null): void
    {
        foreach ($userIds as $userId) {
            $this->notifyUser($userId, $title, $message, $type, $relatedId);
        }
    }
}
