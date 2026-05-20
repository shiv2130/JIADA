<?php

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

class NotificationService
{
    public static function notifyApplicationSubmitted(int $userId, string $applicationNo): void
    {
        if (!class_exists('Notification')) {
            return;
        }
        Notification::createInApp(
            $userId,
            'Application Submitted',
            'Land application submitted successfully. Ref: ' . $applicationNo,
            Notification::PRIORITY_MEDIUM
        );
    }
}

