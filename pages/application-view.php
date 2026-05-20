<?php
$pageTitle = "Application Details";
$currentPage = 'applications';

$appId = intval($_GET['id'] ?? 0);
ob_start();
?>
<div class="container" style="padding-top: 30px; max-width: 900px;">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-file-alt"></i> Application Details</h2>
            <a href="<?php echo baseUrl('/applications'); ?>" class="btn btn-outline btn-sm">Back to Applications</a>
        </div>
        <div class="card-body">
            <div id="appDetails" class="alert alert-info">Loading application...</div>
            <div id="trackSection" class="card" style="display:none; padding:12px; margin-bottom:12px;">
                <h3 style="margin-bottom:10px;">Workflow Timeline</h3>
                <div id="trackTimeline"></div>
                <h3 style="margin:12px 0 10px;">Objections</h3>
                <div id="objectionList"></div>
            </div>
            <div id="bidSection" style="display:none;">
                <h3>Closed Bidding</h3>
                <div id="bidInfo" class="alert alert-info">Loading bid schedule...</div>
                <div id="emdSection" class="card" style="display:none; padding:12px; margin-bottom:12px;">
                    <p id="emdStatus" style="margin-bottom:10px;"></p>
                    <button id="emdPayBtn" class="btn btn-success" style="display:none;">Pay EMD</button>
                </div>
                <form id="bidForm" style="display:none;">
                    <input type="hidden" name="application_id" value="<?php echo $appId; ?>">
                    <input type="hidden" name="bid_id" id="bidIdField">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Bid Amount (INR)</label>
                            <input type="number" name="bid_amount" class="form-control" min="0" step="0.01" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit Bid</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const appId = <?php echo $appId; ?>;
    if (!appId) {
        $('#appDetails').removeClass('alert-info').addClass('alert-warning').text('Application not found.');
        return;
    }

    $.get('<?php echo APP_URL; ?>/api/land/application/' + appId, function(res) {
        if (res.success) {
            const a = res.data;
            $('#appDetails').html(
                `<strong>Application No:</strong> ${a.application_number}<br>
                 <strong>Plot:</strong> ${a.plot_number || '-'} | ${a.area_sqm || '-'} sqm<br>
                 <strong>Status:</strong> ${a.status}<br>
                 <strong>Type:</strong> ${a.application_type}`
            );

            if (a.application_type === 'bidding') {
                $.get('<?php echo APP_URL; ?>/api/land/bids', {plot_id: a.plot_id, status: 'active'}, function(bres) {
                    if (bres.success && bres.data.length > 0) {
                        const bid = bres.data[0];
                        $('#bidIdField').val(bid.id);
                        $('#bidInfo').html(`<strong>Bid Window:</strong> ${bid.bid_start_date} to ${bid.bid_end_date} | <strong>EMD:</strong> INR ${bid.emd_amount}`);
                        $('#bidSection').show();
                        loadBidStatus(appId, bid.id, bid.emd_amount);
                    } else {
                        $('#bidInfo').removeClass('alert-info').addClass('alert-warning').text('No active bid window currently.');
                        $('#bidSection').show();
                    }
                });
            }
        } else {
            $('#appDetails').removeClass('alert-info').addClass('alert-warning').text(res.message || 'Application not found.');
        }
    });

    $.get('<?php echo APP_URL; ?>/api/land/track', {application_id: appId}, function(tres) {
        if (!tres.success) return;
        $('#trackSection').show();
        const wf = tres.data.workflow || [];
        const obs = tres.data.objections || [];
        let wfHtml = '';
        if (wf.length === 0) wfHtml = '<p class="text-muted">No workflow history yet.</p>';
        else {
            wfHtml = '<ul>';
            wf.forEach(w => {
                wfHtml += `<li><strong>${w.status_code}</strong> (${w.action_type}) - ${w.action_at}${w.action_note ? ' | ' + w.action_note : ''}</li>`;
            });
            wfHtml += '</ul>';
        }
        $('#trackTimeline').html(wfHtml);

        let obHtml = '';
        if (obs.length === 0) obHtml = '<p class="text-muted">No objections raised.</p>';
        else {
            obs.forEach(o => {
                obHtml += `<div class="alert alert-${o.status === 'open' ? 'warning' : 'info'}">
                    <strong>#${o.id}</strong> ${o.objection_text}<br>
                    <small>Status: ${o.status} | Due: ${o.due_date || '-'}</small>`;
                if (o.status === 'open' || o.status === 'reopened') {
                    obHtml += `<form class="clarificationForm" style="margin-top:8px;">
                        <input type="hidden" name="objection_id" value="${o.id}">
                        <textarea name="clarification_text" class="form-control" rows="2" placeholder="Enter clarification..." required></textarea>
                        <button class="btn btn-sm btn-primary" style="margin-top:6px;">Submit Clarification</button>
                    </form>`;
                }
                obHtml += '</div>';
            });
        }
        $('#objectionList').html(obHtml);
    });

    $(document).on('submit', '.clarificationForm', function(e) {
        e.preventDefault();
        const payload = $(this).serialize();
        $.post('<?php echo APP_URL; ?>/api/land/clarification', payload, function(res) {
            if (res.success) {
                showAlert('Clarification submitted successfully', 'success');
                window.location.reload();
            } else {
                showAlert(res.message || 'Clarification submission failed', 'danger');
            }
        });
    });

    function loadBidStatus(appId, bidId, emdAmount) {
        $.get('<?php echo APP_URL; ?>/api/land/bid-status', {application_id: appId}, function(sres) {
            if (!sres.success) {
                $('#emdSection').show();
                $('#emdStatus').text('Unable to load bid status.');
                return;
            }
            const p = sres.data;
            if (!p) {
                $('#emdSection').show();
                $('#emdStatus').text('You are not registered for this bid. Please contact support.');
                return;
            }
            if (parseInt(p.emd_paid, 10) === 1) {
                $('#emdSection').show();
                $('#emdStatus').text('EMD paid. You can submit your bid now.');
                $('#bidForm').show();
            } else {
                $('#emdSection').show();
                $('#emdStatus').text('EMD payment pending. Amount: INR ' + emdAmount);
                $('#emdPayBtn').show().data('amount', emdAmount).data('app', appId);
            }
        });
    }

    $('#emdPayBtn').on('click', function() {
        const appId = $(this).data('app');
        const amount = $(this).data('amount');
        if (!appId || !amount) return;
        $.post('<?php echo APP_URL; ?>/api/payment/initiate', {
            payment_type: 'emd',
            reference_type: 'bid',
            reference_id: appId,
            amount: amount
        }, function(res) {
            if (res.success) {
                showAlert('EMD payment initiated. Proceed to gateway.', 'success');
            } else {
                showAlert(res.message || 'Payment initiation failed', 'danger');
            }
        });
    });

    $('#bidForm').on('submit', function(e) {
        e.preventDefault();
        $.post('<?php echo APP_URL; ?>/api/land/bid', $(this).serialize(), function(res) {
            if (res.success) {
                showAlert('Bid submitted successfully', 'success');
            } else {
                showAlert(res.message || 'Bid submission failed', 'danger');
            }
        });
    });
});
</script>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>
