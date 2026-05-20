<?php
/**
 * GET /api/land/document-checklist?applicant_type_code=...
 */
if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}
if (getRequestMethod() !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$type = strtolower(trim((string)($_GET['applicant_type_code'] ?? 'individual')));
$db = getDB();

$table = $db->fetchOne("SELECT 1 ok FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'la_document_types'");
if (!$table) {
    jsonResponse(['success' => true, 'data' => []]);
}

$docs = $db->fetchAll(
    "SELECT document_code, document_name, is_mandatory
     FROM la_document_types
     WHERE is_active = 1 AND (applicant_type_code IS NULL OR applicant_type_code = ?)
     ORDER BY id ASC",
    [$type]
);

jsonResponse(['success' => true, 'data' => $docs]);