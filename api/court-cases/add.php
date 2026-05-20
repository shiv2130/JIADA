<?php
/**
 * Court Case Add API - Add new court case
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/JWT.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405); }

$auth = JWT::authenticate();
if (!$auth['success']) { jsonResponse(['success' => false, 'message' => $auth['message']], $auth['code'] ?? 401); }
$userType = $auth['user']['user_type'] ?? 'applicant';
if (!in_array($userType, ['admin', 'jiada_officer', 'regional_officer'])) {
    jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
}

$db = getDB();
$cnrNumber = sanitize($_POST['cnr_number'] ?? '');
$caseType = sanitize($_POST['case_type'] ?? '');
$subject = sanitize($_POST['subject'] ?? '');
$petitioner = sanitize($_POST['petitioner_name'] ?? '');
$respondent = sanitize($_POST['respondent_name'] ?? '');
$courtName = sanitize($_POST['court_name'] ?? '');
$caseNumber = sanitize($_POST['case_number'] ?? '');
$nextHearingDate = $_POST['next_hearing_date'] ?? null;
$filingDate = $_POST['filing_date'] ?? null;
$status = sanitize($_POST['case_status'] ?? 'pending');
$title = sanitize($_POST['title'] ?? '');
$lastOrder = sanitize($_POST['last_hearing_order'] ?? '');
$description = sanitize($_POST['description'] ?? '');

// Basic validation
if (!$caseNumber || !$caseType || !$subject || !$courtName) {
    jsonResponse(['success' => false, 'message' => 'Case number, case type, subject, and court name are required']);
}
if (strlen($caseNumber) > 100 || strlen($caseType) > 50 || strlen($courtName) > 200) {
    jsonResponse(['success' => false, 'message' => 'Field length exceeded']);
}
if ($filingDate && !strtotime($filingDate)) {
    jsonResponse(['success' => false, 'message' => 'Invalid filing date']);
}
if ($nextHearingDate && !strtotime($nextHearingDate)) {
    jsonResponse(['success' => false, 'message' => 'Invalid next hearing date']);
}
if ($filingDate && $nextHearingDate && strtotime($nextHearingDate) < strtotime($filingDate)) {
    jsonResponse(['success' => false, 'message' => 'Next hearing date cannot be before filing date']);
}

$caseId = $db->insert('court_cases', [
    'case_number' => $caseNumber,
    'cnr_number' => $cnrNumber,
    'case_type' => $caseType,
    'subject' => $subject,
    'court_name' => $courtName,
    'filing_date' => $filingDate,
    'title' => $title ?: ($petitioner && $respondent ? ($petitioner . ' vs ' . $respondent) : $subject),
    'petitioner_name' => $petitioner,
    'respondent_name' => $respondent,
    'next_hearing_date' => $nextHearingDate,
    'case_status' => $status,
    'last_hearing_order' => $lastOrder,
    'description' => $description,
    'created_by' => $auth['user']['id'],
    'created_at' => date('Y-m-d H:i:s')
]);

jsonResponse(['success' => true, 'message' => 'Court case added successfully', 'data' => ['case_id' => $caseId]], 201);
