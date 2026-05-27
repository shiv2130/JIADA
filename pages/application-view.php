<?php
$pageTitle = "Application Details";
$currentPage = 'applications';

$appId = intval($_GET['id'] ?? 0);
$isAdminViewer = !empty($_SESSION['admin_id']);
$backUrl = $isAdminViewer ? APP_URL . '/admin/?page=applications' : baseUrl('/applications');
ob_start();
?>
<div class="container" style="padding-top: 30px; max-width: 900px;">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-file-alt"></i> Application Details</h2>
            <a href="<?php echo $backUrl; ?>" class="btn btn-outline btn-sm">Back to Applications</a>
        </div>
        <div class="card-body">
            <div id="appDetails" class="alert alert-info">Loading application...</div>
            <div id="detailSection" class="card" style="display:none; padding:12px; margin-bottom:12px;">
                <h3 style="margin-bottom:10px;">Full Project Details</h3>
                <div id="detailGrid"></div>
            </div>
            <div id="documentSection" class="card" style="display:none; padding:12px; margin-bottom:12px;">
                <h3 style="margin-bottom:10px;">Uploaded Documents</h3>
                <div id="documentList"></div>
            </div>
            <div id="inspectionSection" class="card" style="display:none; padding:12px; margin-bottom:12px;">
                <h3 style="margin-bottom:10px;">Inspection Notes / Reports</h3>
                <div id="inspectionList"></div>
            </div>
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
    function esc(value) {
        return $('<div>').text(value ?? '').html();
    }
    function renderDetailRow(label, value) {
        return `<tr><th style="width:35%;">${esc(label)}</th><td>${value === null || value === undefined || value === '' ? '-' : esc(value)}</td></tr>`;
    }

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

            const wizard = a.wizard || {};
            const project = wizard.project_json || {};
            const finance = wizard.finance_json || {};
            const landUse = wizard.land_use_json || {};
            const environment = wizard.environment_json || {};
            const declaration = wizard.declaration_json || {};

            let detailHtml = '';
            detailHtml += '<div class="table-container"><table class="table">';
            detailHtml += '<tbody>';
            detailHtml += renderDetailRow('Applicant Type', a.applicant_type_code || '');
            detailHtml += renderDetailRow('Purpose of Allotment', a.purpose || '');
            detailHtml += renderDetailRow('Proposed Investment (INR)', a.proposed_investment || '');
            detailHtml += renderDetailRow('Employment Generation', a.employment_generation || '');
            detailHtml += renderDetailRow('Project Description', a.project_description || '');
            detailHtml += renderDetailRow('Raw Materials', project.raw_materials || '');
            detailHtml += renderDetailRow('Machinery Details', project.machinery || '');
            detailHtml += renderDetailRow('Finished Products', project.products || '');
            detailHtml += renderDetailRow('Own Funds', finance.own_funds || '');
            detailHtml += renderDetailRow('Term Loan', finance.term_loan || '');
            detailHtml += renderDetailRow('Working Capital', finance.working_capital || '');
            detailHtml += renderDetailRow('Built-up Area', landUse.builtup_area || '');
            detailHtml += renderDetailRow('Green Area', landUse.green_area || '');
            detailHtml += renderDetailRow('Environment Plan', environment.plan || '');
            detailHtml += renderDetailRow('Declaration Accepted', parseInt(declaration.accepted || 0, 10) === 1 ? 'Yes' : 'No');
            detailHtml += renderDetailRow('Latest Scrutiny Remarks', a.scrutiny_remarks || '');
            detailHtml += '</tbody></table></div>';
            $('#detailGrid').html(detailHtml);
            $('#detailSection').show();

            const docs = Array.isArray(a.uploaded_documents) ? a.uploaded_documents : [];
            $('#documentSection').show();
            if (docs.length === 0) {
                $('#documentList').html('<p class="text-muted">No uploaded documents found for this application.</p>');
            } else {
                let docHtml = '<table class="table"><thead><tr><th>Document</th><th>File</th><th>Uploaded</th><th>Action</th></tr></thead><tbody>';
                docs.forEach(doc => {
                    const fileName = doc.file_name || '-';
                    const uploadedAt = doc.uploaded_at ? new Date(doc.uploaded_at.replace(' ', 'T')).toLocaleString() : '-';
                    const action = doc.file_url
                        ? `<a href="${doc.file_url}" class="btn btn-sm btn-outline" target="_blank" rel="noopener">Open</a>`
                        : '<span class="text-muted">Unavailable</span>';
                    docHtml += `<tr>
                        <td>${doc.document_name || doc.document_code || 'Document'}</td>
                        <td>${fileName}</td>
                        <td>${uploadedAt}</td>
                        <td>${action}</td>
                    </tr>`;
                });
                docHtml += '</tbody></table>';
                $('#documentList').html(docHtml);
            }

            const inspections = Array.isArray(a.inspection_updates) ? a.inspection_updates : [];
            const inspectionDocs = Array.isArray(a.inspection_documents) ? a.inspection_documents : [];
            $('#inspectionSection').show();
            if (inspections.length === 0 && inspectionDocs.length === 0) {
                $('#inspectionList').html('<p class="text-muted">No inspection report submitted yet.</p>');
            } else {
                let inspectionHtml = '';
                if (inspectionDocs.length > 0) {
                    inspectionHtml += '<table class="table" style="margin-bottom:12px;"><thead><tr><th>Inspection File</th><th>Uploaded</th><th>Action</th></tr></thead><tbody>';
                    inspectionDocs.forEach(doc => {
                        const uploadedAt = doc.uploaded_at ? new Date(doc.uploaded_at.replace(' ', 'T')).toLocaleString() : '-';
                        const action = doc.file_url
                            ? `<a href="${doc.file_url}" class="btn btn-sm btn-outline" target="_blank" rel="noopener">Open</a>`
                            : '<span class="text-muted">Unavailable</span>';
                        inspectionHtml += `<tr>
                            <td>${esc(doc.file_name || doc.document_name || 'Inspection Report')}</td>
                            <td>${esc(uploadedAt)}</td>
                            <td>${action}</td>
                        </tr>`;
                    });
                    inspectionHtml += '</tbody></table>';
                }
                if (inspections.length > 0) {
                    inspectionHtml += '<ul>';
                    inspections.forEach(item => {
                        inspectionHtml += `<li><strong>${esc(item.status_code || 'Inspection')}</strong> - ${esc(item.action_at || '')}${item.action_note ? ' | ' + esc(item.action_note) : ''}</li>`;
                    });
                    inspectionHtml += '</ul>';
                }
                $('#inspectionList').html(inspectionHtml);
            }

            const workflow = Array.isArray(a.workflow) ? a.workflow : [];
            const objections = Array.isArray(a.objections) ? a.objections : [];
            $('#trackSection').show();
            let wfHtml = '';
            if (workflow.length === 0) wfHtml = '<p class="text-muted">No workflow history yet.</p>';
            else {
                wfHtml = '<ul>';
                workflow.forEach(w => {
                    wfHtml += `<li><strong>${esc(w.status_code)}</strong> (${esc(w.action_type)}) - ${esc(w.action_at)}${w.action_note ? ' | ' + esc(w.action_note) : ''}</li>`;
                });
                wfHtml += '</ul>';
            }
            $('#trackTimeline').html(wfHtml);

            let obHtml = '';
            if (objections.length === 0) obHtml = '<p class="text-muted">No objections raised.</p>';
            else {
                objections.forEach(o => {
                    obHtml += `<div class="alert alert-${o.status === 'open' ? 'warning' : 'info'}">
                        <strong>#${esc(o.id)}</strong> ${esc(o.objection_text)}<br>
                        <small>Status: ${esc(o.status)} | Due: ${esc(o.due_date || '-')}</small>`;
                    if (o.status === 'open' || o.status === 'reopened') {
                        obHtml += `<form class="clarificationForm" style="margin-top:8px;">
                            <input type="hidden" name="objection_id" value="${esc(o.id)}">
                            <textarea name="clarification_text" class="form-control" rows="2" placeholder="Enter clarification..." required></textarea>
                            <button class="btn btn-sm btn-primary" style="margin-top:6px;">Submit Clarification</button>
                        </form>`;
                    }
                    obHtml += '</div>';
                });
            }
            $('#objectionList').html(obHtml);

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
