<?php

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

class WorkflowService
{
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_UNDER_PRELIMINARY_SCRUTINY = 'UNDER_PRELIMINARY_SCRUTINY';
    public const STATUS_DEFICIENCY_RAISED = 'DEFICIENCY_RAISED';
    public const STATUS_UNDER_IEO_REVIEW = 'UNDER_IEO_REVIEW';
    public const STATUS_UNDER_DO_REVIEW = 'UNDER_DO_REVIEW';
    public const STATUS_RD_PENDING = 'RD_PENDING';
    public const STATUS_APPROVED_FOR_ALLOTMENT = 'APPROVED_FOR_ALLOTMENT';
    public const STATUS_APPROVED_FOR_BIDDING = 'APPROVED_FOR_BIDDING';
    public const STATUS_REJECTED = 'REJECTED';

    public static function mapLegacyStatus(string $legacyStatus): string
    {
        $legacyStatus = strtolower(trim($legacyStatus));
        $map = [
            'draft' => 'DRAFT',
            'submitted' => self::STATUS_SUBMITTED,
            'under_scrutiny' => self::STATUS_UNDER_PRELIMINARY_SCRUTINY,
            'approved' => self::STATUS_APPROVED_FOR_ALLOTMENT,
            'rejected' => self::STATUS_REJECTED,
            'bidding' => self::STATUS_APPROVED_FOR_BIDDING,
            'provisional_allotted' => 'PROVISIONAL_ALLOTMENT_ISSUED',
            'allotted' => 'POSSESSION_PENDING',
            'cancelled' => 'ALLOTMENT_CANCELLED'
        ];
        return $map[$legacyStatus] ?? strtoupper($legacyStatus);
    }

    public static function syncToLegacyStatus(string $statusCode): string
    {
        $statusCode = strtoupper(trim($statusCode));
        $map = [
            self::STATUS_SUBMITTED => 'submitted',
            self::STATUS_UNDER_PRELIMINARY_SCRUTINY => 'under_scrutiny',
            self::STATUS_DEFICIENCY_RAISED => 'under_scrutiny',
            self::STATUS_UNDER_IEO_REVIEW => 'under_scrutiny',
            self::STATUS_UNDER_DO_REVIEW => 'under_scrutiny',
            self::STATUS_RD_PENDING => 'under_scrutiny',
            self::STATUS_APPROVED_FOR_ALLOTMENT => 'approved',
            self::STATUS_APPROVED_FOR_BIDDING => 'bidding',
            self::STATUS_REJECTED => 'rejected'
        ];
        return $map[$statusCode] ?? 'under_scrutiny';
    }

    public static function moveApplicationStatus(int $applicationId, string $newStatusCode, int $actionBy = 0, string $note = ''): void
    {
        $db = getDB();
        $legacyStatus = self::syncToLegacyStatus($newStatusCode);
        $db->update('land_applications', ['status' => $legacyStatus], 'id = ?', [$applicationId]);
        self::logAction($applicationId, strtoupper($newStatusCode), 'forward', $actionBy, $note);
    }

    public static function calculateStageDueDate(string $statusCode): string
    {
        $statusCode = strtoupper(trim($statusCode));
        $days = 5;
        if ($statusCode === self::STATUS_RD_PENDING) $days = 7;
        if ($statusCode === self::STATUS_DEFICIENCY_RAISED) $days = 10;
        return date('Y-m-d', strtotime('+' . $days . ' days'));
    }

    public static function logAction(int $applicationId, string $statusCode, string $actionType, int $actionBy = 0, string $note = ''): void
    {
        $db = getDB();

        $tableExists = $db->fetchOne(
            "SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'la_workflow_actions' LIMIT 1"
        );
        if (!$tableExists) {
            return;
        }

        $db->insert('la_workflow_actions', [
            'application_id' => $applicationId,
            'status_code' => $statusCode,
            'action_type' => $actionType,
            'action_by' => $actionBy ?: null,
            'action_note' => $note,
            'action_at' => date('Y-m-d H:i:s')
        ]);
    }
}
