<?php

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

class FeeCalculationService
{
    public static function calculateProcessingFee(string $categoryCode, float $acre): float
    {
        $categoryCode = strtoupper(trim($categoryCode));
        $acre = max(0, $acre);

        if ($categoryCode === 'A') {
            if ($acre <= 1) return 10000;
            if ($acre <= 3) return 25000;
            return 50000;
        }
        if ($categoryCode === 'B') {
            if ($acre <= 1) return 7500;
            if ($acre <= 3) return 17500;
            return 37500;
        }

        if ($acre <= 1) return 5000;
        if ($acre <= 3) return 12000;
        return 25000;
    }

    public static function calculateLandRate(float $baseRate, string $categoryCode, string $sectorCode): float
    {
        $baseRate = max(0, $baseRate);
        $categoryCode = strtoupper(trim($categoryCode));
        $sectorCode = strtolower(trim($sectorCode));

        if ($sectorCode === 'production') return $baseRate;
        if ($categoryCode === 'A' && $sectorCode === 'service') return $baseRate * 1.25;
        if ($categoryCode === 'A' && $sectorCode === 'commercial') return $baseRate * 10;
        if ($categoryCode === 'B' && $sectorCode === 'service') return $baseRate * 1.17;
        if ($categoryCode === 'B' && $sectorCode === 'commercial') return $baseRate * 7.5;
        if ($categoryCode === 'C' && $sectorCode === 'service') return $baseRate * 1.07;
        if ($categoryCode === 'C' && $sectorCode === 'commercial') return $baseRate * 2;

        return $baseRate;
    }

    public static function calculateEMD(float $floorPrice): float
    {
        $floorPrice = max(0, $floorPrice);
        return max($floorPrice * 0.10, 150000);
    }

    public static function sqmToAcre(float $areaSqm): float
    {
        return max(0, $areaSqm) / 4046.86;
    }
}

