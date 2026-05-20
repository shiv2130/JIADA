<?php

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

class BidService
{
    public static function computeEmd(float $floorPrice): float
    {
        return FeeCalculationService::calculateEMD($floorPrice);
    }

    public static function isBidLeadTimeValid(string $noticeDate, string $bidDate): bool
    {
        $noticeTs = strtotime($noticeDate);
        $bidTs = strtotime($bidDate);
        if (!$noticeTs || !$bidTs) return false;
        return ($bidTs - $noticeTs) >= (10 * 24 * 60 * 60);
    }

    public static function createBidEventFromApplication(array $application, string $bidDate, int $createdBy): int
    {
        $db = getDB();
        $noticeDate = date('Y-m-d');
        if (!self::isBidLeadTimeValid($noticeDate, $bidDate)) {
            throw new Exception('Bid date must be at least 10 days after notice date');
        }

        $plot = $db->fetchOne("SELECT * FROM land_banks WHERE id = ?", [$application['plot_id']]);
        if (!$plot) {
            throw new Exception('Plot not found for bidding');
        }

        $floorPrice = floatval($plot['base_price_per_sqm'] ?? 0) * floatval($plot['area_sqm'] ?? 0);
        $emd = self::computeEmd($floorPrice);
        $bidEventNo = generateReferenceNumber('BID');

        $db->insert('la_bid_events', [
            'plot_id' => $application['plot_id'],
            'notification_id' => null,
            'bid_event_no' => $bidEventNo,
            'bid_notice_date' => $noticeDate,
            'bid_date' => $bidDate,
            'floor_price' => $floorPrice,
            'emd_amount' => $emd,
            'status' => 'published',
            'created_by' => $createdBy,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $laBidEventId = intval($db->getConnection()->lastInsertId());

        // Keep legacy bids table in sync for current UI/API compatibility.
        $legacyBidId = $db->insert('bids', [
            'bid_number' => $bidEventNo,
            'plot_id' => $application['plot_id'],
            'bid_start_date' => $noticeDate,
            'bid_end_date' => $bidDate,
            'base_price' => $floorPrice,
            'emd_amount' => $emd,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $apps = $db->fetchAll("SELECT id, user_id FROM land_applications WHERE plot_id = ? AND status IN ('submitted','under_scrutiny','approved','bidding')", [$application['plot_id']]);
        foreach ($apps as $a) {
            $db->insert('la_bid_participants', [
                'bid_event_id' => $laBidEventId,
                'application_id' => $a['id'],
                'user_id' => $a['user_id'],
                'is_eligible' => 1
            ]);
            $db->insert('bid_participants', [
                'bid_id' => $legacyBidId,
                'user_id' => $a['user_id'],
                'application_id' => $a['id'],
                'emd_paid' => 0,
                'status' => 'emd_pending'
            ]);
        }

        return $laBidEventId;
    }
}
