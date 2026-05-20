<?php

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

class AuditService
{
    public static function log(string $action, int $userId, string $details = ''): void
    {
        logActivity($action . ($details ? ' | ' . $details : ''), $userId);
    }
}

