<?php
$pageTitle = "Bids Management - Admin";
$currentPage = 'bids';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_bid') {
        $plotId = intval($_POST['plot_id'] ?? 0);
        $bidEnd = $_POST['bid_end_date'] ?? '';

        if (!$plotId || !$bidEnd) {
            $_SESSION['flash_error'] = 'Plot and bid date are required.';
        } else {
            $existing = $db->fetchOne("SELECT id FROM bids WHERE plot_id = ? AND status IN ('active','upcoming')", [$plotId]);
            if ($existing) {
                $_SESSION['flash_error'] = 'An active or upcoming bid already exists for this plot.';
            } else {
                $app = $db->fetchOne("SELECT * FROM land_applications WHERE plot_id = ? ORDER BY id ASC LIMIT 1", [$plotId]);
                if (!$app) {
                    $_SESSION['flash_error'] = 'No application found for selected plot.';
                } else {
                    BidService::createBidEventFromApplication($app, $bidEnd, intval($_SESSION['admin_id']));
                    $db->update('land_applications', ['status' => 'bidding'], "plot_id = ? AND status IN ('submitted','under_scrutiny','approved','bidding')", [$plotId]);
                    $_SESSION['flash_success'] = 'Bid created and applicants notified.';
                }
            }
        }
    }

    if ($action === 'evaluate_bid') {
        $bidId = intval($_POST['bid_id'] ?? 0);
        $result = evaluateBid($db, $bidId);
        if ($result['success']) {
            $_SESSION['flash_success'] = $result['message'];
        } else {
            $_SESSION['flash_error'] = $result['message'];
        }
    }

    header('Location: ' . APP_URL . '/admin/?page=bids');
    exit;
}

$bids = $db->fetchAll("SELECT b.*, lb.plot_number,
    (SELECT COUNT(*) FROM bid_participants bp WHERE bp.bid_id = b.id) as total_participants,
    (SELECT COUNT(*) FROM bid_participants bp WHERE bp.bid_id = b.id AND bp.emd_paid = 1) as emd_paid_count,
    (SELECT COUNT(*) FROM bid_participants bp WHERE bp.bid_id = b.id AND bp.bid_amount IS NOT NULL) as bid_count
    FROM bids b
    LEFT JOIN land_banks lb ON b.plot_id = lb.id
    ORDER BY b.id DESC LIMIT 100");

$plots = $db->fetchAll("SELECT id, plot_number FROM land_banks ORDER BY plot_number ASC");

function evaluateBid($db, $bidId) {
    if (!$bidId) return ['success' => false, 'message' => 'Bid ID is required'];

    $bid = $db->fetchOne("SELECT * FROM bids WHERE id = ?", [$bidId]);
    if (!$bid) return ['success' => false, 'message' => 'Bid not found'];

    $participants = $db->fetchAll("SELECT * FROM bid_participants WHERE bid_id = ? AND emd_paid = 1 AND bid_amount IS NOT NULL ORDER BY bid_amount DESC, bid_submitted_at ASC", [$bidId]);
    if (empty($participants)) {
        return ['success' => false, 'message' => 'No valid bids found for evaluation'];
    }

    $winner = $participants[0];
    $second = $participants[1] ?? null;
    foreach ($participants as $idx => $p) {
        $status = ($idx === 0) ? 'winner' : 'lost';
        $db->update('bid_participants', ['status' => $status, 'rank' => $idx + 1], 'id = ?', [$p['id']]);
    }

    $db->update('bids', ['status' => 'completed'], 'id = ?', [$bidId]);
    $db->update('la_bid_events', ['status' => 'completed'], 'bid_event_no = ?', [$bid['bid_number']]);

    $winnerApp = $db->fetchOne("SELECT * FROM land_applications WHERE id = ?", [$winner['application_id']]);
    if ($winnerApp) {
        issueProvisionalOrder($db, $winnerApp);
        WorkflowService::moveApplicationStatus(intval($winnerApp['id']), 'HIGHEST_BIDDER_SELECTED', intval($_SESSION['admin_id']), 'Highest bidder selected');
        if ($second) {
            $secondApp = $db->fetchOne("SELECT * FROM land_applications WHERE id = ?", [$second['application_id']]);
            if ($secondApp) {
                WorkflowService::moveApplicationStatus(intval($secondApp['id']), 'SECOND_HIGHEST_RESERVED', intval($_SESSION['admin_id']), 'Reserved as second highest bidder');
            }
        }
        Notification::sendApplicationStatus($winnerApp['user_id'], 'Land Allotment', 'approved', $winnerApp['application_number'] ?? '');
    }

    foreach ($participants as $idx => $p) {
        if ($idx === 0) continue;
        $db->update('land_applications', ['status' => 'rejected', 'rejected_reason' => 'Bid not highest'], 'id = ?', [$p['application_id']]);
        Notification::sendApplicationStatus($p['user_id'], 'Land Allotment', 'rejected', '');
    }

    return ['success' => true, 'message' => 'Bid evaluated, highest selected, second highest reserved'];
}

function issueProvisionalOrder($db, $app) {
    $plot = $db->fetchOne("SELECT * FROM land_banks WHERE id = ?", [$app['plot_id']]);
    $premium = 0;
    if ($plot && !empty($plot['base_price_per_sqm']) && !empty($plot['area_sqm'])) {
        $premium = $plot['base_price_per_sqm'] * $plot['area_sqm'];
    }
    $orderNumber = generateReferenceNumber('ALLOT');
    $deadline = date('Y-m-d', strtotime('+30 days'));
    $orderId = $db->insert('allotment_orders', [
        'order_number' => $orderNumber,
        'application_id' => $app['id'],
        'user_id' => $app['user_id'],
        'plot_id' => $app['plot_id'],
        'order_type' => 'provisional',
        'allotment_date' => date('Y-m-d'),
        'premium_amount' => $premium,
        'outstanding_amount' => $premium,
        'payment_deadline' => $deadline,
        'status' => 'payment_pending',
        'issued_by' => $_SESSION['admin_id'] ?? null
    ]);
    $db->update('land_applications', ['status' => 'provisional_allotted'], 'id = ?', [$app['id']]);
    $db->update('land_banks', ['status' => 'reserved'], 'id = ?', [$app['plot_id']]);
    if ($premium > 0) {
        $paymentId = 'PAY' . date('YmdHis') . strtoupper(substr(uniqid(), -6));
        $db->insert('payments', [
            'payment_id' => $paymentId,
            'user_id' => $app['user_id'],
            'payment_type' => 'premium',
            'reference_type' => 'allotment_order',
            'reference_id' => $orderId,
            'amount' => $premium,
            'gst_amount' => 0,
            'total_amount' => $premium,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'remarks' => 'Premium payment due within 30 days'
        ]);
        $db->insert('dues_outstanding', [
            'user_id' => $app['user_id'],
            'dues_type' => 'premium',
            'due_amount' => $premium,
            'due_date' => $deadline,
            'status' => 'pending'
        ]);
    }
    Notification::createInApp($app['user_id'], 'Provisional Allotment Issued', 'Your provisional allotment order has been issued. Premium payment due by ' . $deadline, Notification::PRIORITY_HIGH);
    return $orderId;
}

ob_start();
?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-gavel"></i> Bids Management</h3>
    </div>
    <div class="card-body">
        <div class="card" style="margin-bottom:16px;">
            <div class="card-header"><strong>Create Closed Bid</strong></div>
            <div class="card-body">
                <form method="post" style="display:flex; flex-direction:column; gap:12px;">
                    <input type="hidden" name="action" value="create_bid">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Plot</label>
                            <select name="plot_id" class="form-control" required>
                                <option value="">Select plot</option>
                                <?php foreach ($plots as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo e($p['plot_number']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Bid Date</label>
                            <input type="date" name="bid_end_date" class="form-control" required>
                        </div>
                    </div>
                    <small>Floor price and EMD are auto-calculated as per SOP rule.</small>
                    <button class="btn btn-primary" type="submit">Create Bid</button>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>ID</th><th>Bid#</th><th>Plot</th><th>Start</th><th>End</th><th>Base</th><th>EMD</th><th>Participants</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($bids as $b): ?>
                    <tr>
                        <td><?php echo $b['id']; ?></td>
                        <td><?php echo e($b['bid_number'] ?? ''); ?></td>
                        <td><?php echo e($b['plot_number'] ?? '-'); ?></td>
                        <td><?php echo e($b['bid_start_date']); ?></td>
                        <td><?php echo e($b['bid_end_date']); ?></td>
                        <td>INR <?php echo number_format(floatval($b['base_price'] ?? 0), 2); ?></td>
                        <td>INR <?php echo number_format(floatval($b['emd_amount'] ?? 0), 2); ?></td>
                        <td><?php echo intval($b['bid_count']); ?> bids | <?php echo intval($b['emd_paid_count']); ?> EMD paid</td>
                        <td><span class="badge badge-<?php echo ($b['status'] ?? '') === 'completed' ? 'success' : (($b['status'] ?? '') === 'active' ? 'warning' : 'info'); ?>"><?php echo e($b['status']); ?></span></td>
                        <td>
                            <form method="post" style="display:flex; gap:6px; align-items:center;">
                                <input type="hidden" name="action" value="evaluate_bid">
                                <input type="hidden" name="bid_id" value="<?php echo $b['id']; ?>">
                                <button class="btn btn-sm btn-primary" type="submit">Close & Evaluate</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($bids)): ?><tr><td colspan="10" class="text-center">No bids found</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
?>
