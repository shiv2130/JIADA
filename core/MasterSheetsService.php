<?php

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/FeeCalculationService.php';

class MasterSheetsService
{
    public static function currentFinancialYear(?DateTime $date = null): string
    {
        $date = $date ?: new DateTime();
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');
        $start = $month >= 4 ? $year : $year - 1;

        return $start . '-' . substr((string) ($start + 1), -2);
    }

    public static function mapVacancyToStatus(string $vacancyStatus): string
    {
        $vacancyStatus = strtolower(trim($vacancyStatus));

        return match ($vacancyStatus) {
            'allotted' => 'allotted',
            'reserved' => 'reserved',
            'notification_awaited', 'notification-awaited' => 'notification_awaited',
            default => 'available',
        };
    }

    public static function mapStatusToVacancy(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'allotted', 'reserved' => 'allotted',
            default => 'vacant',
        };
    }

    public static function calculatePlotFees(Database $db, array $plot, string $category = 'C'): array
    {
        $financialYear = self::currentFinancialYear();
        $areaSqm = (float) ($plot['area_sqm'] ?? 0);
        $areaAcres = FeeCalculationService::sqmToAcre($areaSqm);
        $category = strtoupper(trim($category ?: 'C'));

        $feeRule = $db->fetchOne(
            "SELECT * FROM master_fee_rules
             WHERE is_active = 1
               AND financial_year = ?
               AND land_category = ?
               AND area_unit = 'acre'
               AND min_area <= ?
               AND (max_area IS NULL OR max_area = 0 OR max_area >= ?)
             ORDER BY min_area DESC
             LIMIT 1",
            [$financialYear, $category, $areaAcres, $areaAcres]
        );

        $processingFee = $feeRule
            ? (float) $feeRule['processing_fee']
            : FeeCalculationService::calculateProcessingFee($category, $areaAcres);

        if ($feeRule && !empty($feeRule['min_fee'])) {
            $processingFee = max($processingFee, (float) $feeRule['min_fee']);
        }

        $baseRate = (float) ($plot['base_price_per_sqm'] ?? $plot['base_price'] ?? 0);
        $rate = $baseRate;

        if (!empty($plot['notification_rate_id'])) {
            $notificationRate = $db->fetchOne(
                'SELECT * FROM master_notification_rates WHERE id = ? AND is_active = 1',
                [(int) $plot['notification_rate_id']]
            );
            if ($notificationRate) {
                $rate = (float) ($notificationRate['rate_per_sqm'] ?? $rate);
                $rate *= (float) ($notificationRate['floor_price_multiplier'] ?? 1);
                $financialYear = (string) ($notificationRate['financial_year'] ?? $financialYear);
            }
        }

        $floorPrice = max(0, $rate) * max(0, $areaSqm);
        $otherCost = (float) ($plot['other_cost'] ?? 0);

        return [
            'financial_year' => $financialYear,
            'processing_fee' => round($processingFee, 2),
            'floor_price' => round($floorPrice, 2),
            'other_cost' => round($otherCost, 2),
            'total_fee' => round($processingFee + $floorPrice + $otherCost, 2),
        ];
    }

    public static function ensureSchema(Database $db): void
    {
        self::createMasterTables($db);
        self::ensureLandBankColumns($db);
    }

    private static function createMasterTables(Database $db): void
    {
        $db->query(
            "CREATE TABLE IF NOT EXISTS master_regional_offices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                region_id INT NOT NULL,
                office_name VARCHAR(150) NOT NULL,
                office_code VARCHAR(50) NULL,
                address TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_region_id (region_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $db->query(
            "CREATE TABLE IF NOT EXISTS master_districts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                region_id INT NOT NULL,
                district_name VARCHAR(120) NOT NULL,
                district_code VARCHAR(50) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_region_district (region_id, district_name),
                INDEX idx_region_id (region_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $db->query(
            "CREATE TABLE IF NOT EXISTS master_industrial_areas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                region_id INT NOT NULL,
                district_id INT NOT NULL,
                area_name VARCHAR(160) NOT NULL,
                area_code VARCHAR(50) NULL,
                land_price_per_unit DECIMAL(14,2) NULL,
                unit_type VARCHAR(20) NOT NULL DEFAULT 'acre',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_region_id (region_id),
                INDEX idx_district_id (district_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $db->query(
            "CREATE TABLE IF NOT EXISTS master_sectors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sector_code VARCHAR(60) NOT NULL UNIQUE,
                sector_name VARCHAR(120) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $db->query(
            "CREATE TABLE IF NOT EXISTS master_fee_rules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                financial_year VARCHAR(20) NOT NULL,
                land_category VARCHAR(10) NOT NULL,
                min_area DECIMAL(12,4) NOT NULL DEFAULT 0,
                max_area DECIMAL(12,4) NULL,
                area_unit VARCHAR(20) NOT NULL DEFAULT 'acre',
                processing_fee DECIMAL(14,2) NOT NULL DEFAULT 0,
                min_fee DECIMAL(14,2) NULL,
                rate_per_unit DECIMAL(14,2) NULL,
                remarks TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_fee_lookup (financial_year, land_category, area_unit, min_area)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $db->query(
            "CREATE TABLE IF NOT EXISTS master_notification_rates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                notification_ref VARCHAR(120) NOT NULL,
                financial_year VARCHAR(20) NOT NULL,
                rate_per_sqm DECIMAL(14,2) NULL,
                floor_price_multiplier DECIMAL(10,4) NOT NULL DEFAULT 1,
                effective_from DATE NULL,
                effective_to DATE NULL,
                remarks TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_notification_lookup (financial_year, notification_ref)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    private static function ensureLandBankColumns(Database $db): void
    {
        $columns = [
            'regional_office_id' => 'INT NULL',
            'district_id' => 'INT NULL',
            'industrial_area_id' => 'INT NULL',
            'sector_id' => 'INT NULL',
            'block' => 'VARCHAR(120) NULL',
            'circle' => 'VARCHAR(120) NULL',
            'mouja' => 'VARCHAR(120) NULL',
            'khata' => 'VARCHAR(120) NULL',
            'area_type' => 'VARCHAR(80) NULL',
            'area_acres' => 'DECIMAL(12,4) NULL',
            'notification_rate_id' => 'INT NULL',
            'financial_year' => 'VARCHAR(20) NULL',
            'vacancy_status' => "VARCHAR(40) NULL DEFAULT 'vacant'",
            'processing_fee' => 'DECIMAL(14,2) NULL',
            'floor_price' => 'DECIMAL(14,2) NULL',
            'other_cost' => 'DECIMAL(14,2) NULL',
        ];

        foreach ($columns as $column => $definition) {
            if (!self::columnExists($db, 'land_banks', $column)) {
                $db->query("ALTER TABLE land_banks ADD COLUMN {$column} {$definition}");
            }
        }
    }

    private static function columnExists(Database $db, string $table, string $column): bool
    {
        $result = $db->fetchOne(
            'SELECT COUNT(*) AS c
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return (int) ($result['c'] ?? 0) > 0;
    }
}
