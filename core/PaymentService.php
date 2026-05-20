<?php

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

class PaymentService
{
    public static function createPendingPayment(int $userId, string $paymentType, string $referenceType, int $referenceId, float $amount): ?int
    {
        $db = getDB();

        return $db->insert('payments', [
            'payment_id' => 'PAY' . date('YmdHis') . strtoupper(substr(uniqid(), -6)),
            'user_id' => $userId,
            'payment_type' => $paymentType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'amount' => $amount,
            'gst_amount' => 0,
            'total_amount' => $amount,
            'payment_mode' => 'online',
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public static function createPendingProcessingFee(int $userId, int $applicationId, float $amount): ?int
    {
        return self::createPendingPayment($userId, 'processing_fee', 'land_application', $applicationId, $amount);
    }
}
