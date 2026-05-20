<?php
$pageTitle = "Land Allotment Application";
$currentPage = 'land-bank';

$plotId = intval($_GET['plot'] ?? 0);
ob_start();
?>
<div class="container" style="padding-top: 30px; max-width: 900px;">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-file-signature"></i> Land Allotment Application Wizard</h2>
            <a href="<?php echo baseUrl('/land-bank'); ?>" class="btn btn-outline btn-sm">Back to Land Bank</a>
        </div>
        <div class="card-body">
            <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="alert alert-warning"><i class="fas fa-lock"></i> Please login to submit an application.</div>
                <a class="btn btn-primary" href="<?php echo baseUrl('/login'); ?>">Login</a>
            <?php else: ?>
                <div id="plotInfo" class="alert alert-info">Loading plot details...</div>
                <form id="landApplyForm">
                    <input type="hidden" name="plot_id" id="plotIdField" value="<?php echo $plotId; ?>">
                    <input type="hidden" name="application_id" id="applicationIdField" value="">

                    <h4>Section 1: Basic Details</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Applicant Type <span class="required">*</span></label>
                            <select name="applicant_type_code" id="applicantTypeField" class="form-control" required>
                                <option value="individual">Individual</option>
                                <option value="proprietorship">Proprietorship</option>
                                <option value="partnership">Partnership</option>
                                <option value="llp">LLP</option>
                                <option value="pvt_ltd">Private Limited</option>
                                <option value="consortium">Consortium</option>
                                <option value="government-company">Government Company</option>
                                <option value="pub_ltd">Public Limited</option>
                                <option value="society">Society</option>
                                <option value="trust">Trust</option>
                                <option value="huf">Hindu Undivided Family(HUF)</option>
                            </select>
                        </div>
                        <div class="form-group">
                             <label>Purpose of Allotment <span class="required">*</span></label>
                            <select name="purpose" id="purposeField" class="form-control" required>
                                <option value="industry">Industry</option>
                                <option value="institution">Institution</option>
                                <option value="society">Society</option>
                                <option value="trust">Trust</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Proposed Investment (INR)</label>
                            <input type="number" name="proposed_investment" class="form-control" min="0" step="0.01">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Employment Generation (Nos.)</label>
                            <input type="number" name="employment_generation" class="form-control" min="0">
                        </div>
                        <div class="form-group">
                            <label>Preferred Plot Visit Date</label>
                            <input type="date" name="visit_date" class="form-control">
                        </div>
                    </div>

                    <h4>Section 2: Project Details</h4>
                    <div class="form-group">
                        <label>Project Description <span class="required">*</span></label>
                        <textarea name="project_description" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Raw Materials</label>
                        <textarea name="project_raw_materials" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Machinery</label>
                        <textarea name="project_machinery" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Finished Products</label>
                        <textarea name="project_products" class="form-control" rows="2"></textarea>
                    </div>

                    <h4>Section 3: Finance</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Own Funds</label>
                            <input type="number" name="finance_own_funds" class="form-control" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Term Loan</label>
                            <input type="number" name="finance_term_loan" class="form-control" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Working Capital</label>
                            <input type="number" name="finance_working_capital" class="form-control" step="0.01">
                        </div>
                    </div>

                    <h4>Section 4: Land Use & Environment</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Built-up Area</label>
                            <input type="text" name="land_builtup_area" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Green Area</label>
                            <input type="text" name="land_green_area" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Environment Plan</label>
                        <textarea name="env_plan" class="form-control" rows="2"></textarea>
                    </div>

                    <h4>Section 5: Declaration & Documents</h4>
                    <div class="form-group">
                        <label><input type="checkbox" name="declaration_accept" value="1"> I declare all details are correct.</label>
                    </div>
                    <div class="form-group">
                        <label>Mandatory Document Checklist</label>
                        <div id="documentChecklist" class="card" style="padding:10px;">Loading checklist...</div>
                    </div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <button type="button" id="saveDraftBtn" class="btn btn-outline"><i class="fas fa-save"></i> Save Draft</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Final Submit</button>
                    </div>
                </form>
                <div id="paymentBox" class="card mt-20" style="display:none; padding: 20px;">
                    <h3>Processing Fee</h3>
                    <p id="feeText"></p>
                    <button id="payNowBtn" class="btn btn-success">Pay Processing Fee</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const plotId = parseInt($('#plotIdField').val(), 10);
    if (plotId) {
        $.get('<?php echo APP_URL; ?>/api/land/plot/' + plotId, function(res) {
            if (res.success) {
                const p = res.data;
                $('#plotInfo').html(`<strong>Plot:</strong> ${p.plot_number || p.plot_no} | <strong>Area:</strong> ${p.area_sqm || p.area_sqft} ${p.area_sqm ? 'sqm' : 'sqft'} | <strong>Zone:</strong> ${p.zone || '-'} | <strong>Status:</strong> ${p.status}`);
            } else {
                $('#plotInfo').removeClass('alert-info').addClass('alert-warning').text(res.message || 'Unable to load plot');
            }
        });
    } else {
        $('#plotInfo').removeClass('alert-info').addClass('alert-warning').text('No plot selected. Please choose a plot from the Land Bank.');
    }

    function renderChecklist(typeCode) {
        $.get('<?php echo APP_URL; ?>/api/land/document-checklist', { applicant_type_code: typeCode }, function(res) {
            if (!res.success) {
                $('#documentChecklist').html('<p>Checklist unavailable.</p>');
                return;
            }
            const docs = res.data || [];
            if (docs.length === 0) {
                $('#documentChecklist').html('<p>No document configuration found. You can still upload supporting docs as needed.</p>');
                return;
            }
            let html = '<div style="display:grid; gap:10px;">';
            docs.forEach(d => {
                html += `<div><label><strong>${d.document_name}</strong>${parseInt(d.is_mandatory,10)===1?' <span class="required">*</span>':''}</label><input type="file" name="doc_files[${d.document_code}]" class="form-control" ${parseInt(d.is_mandatory,10)===1?'required':''}></div>`;
            });
            html += '</div>';
            $('#documentChecklist').html(html);
        });
    }
    renderChecklist($('#applicantTypeField').val());
    $('#applicantTypeField').on('change', function() {
        renderChecklist($(this).val());
    });

    function saveWizardSection(appId, section, payloadObj) {
        return $.post('<?php echo APP_URL; ?>/api/land/application-save', {
            application_id: appId,
            section: section,
            payload: JSON.stringify(payloadObj)
        });
    }

    function getAjaxErrorMessage(xhr, fallback = 'Network error. Please try again.') {
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
            return xhr.responseJSON.message;
        }

        if (xhr && typeof xhr.responseText === 'string' && xhr.responseText.trim() !== '') {
            try {
                const parsed = JSON.parse(xhr.responseText);
                if (parsed && parsed.message) {
                    return parsed.message;
                }
            } catch (e) {
                // Ignore parse errors and fall back below.
            }
        }

        return fallback;
    }

    function createOrUpdateDraft(callback) {
        const formData = new FormData(document.getElementById('landApplyForm'));
        formData.append('final_submit', '0');
        $.ajax({
            url: '<?php echo APP_URL; ?>/api/land/apply',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                if (!res.success) {
                    showAlert(res.message || 'Draft save failed', 'danger');
                    return;
                }
                const appId = res.data.application_id;
                $('#applicationIdField').val(appId);

                const calls = [];
                calls.push(saveWizardSection(appId, 'profile', {
                        purpose: $('#purposeField').val(),
                    proposed_investment: $('input[name="proposed_investment"]').val(),
                    employment_generation: $('input[name="employment_generation"]').val()
                }));
                calls.push(saveWizardSection(appId, 'project', {
                    description: $('textarea[name="project_description"]').val(),
                    raw_materials: $('textarea[name="project_raw_materials"]').val(),
                    machinery: $('textarea[name="project_machinery"]').val(),
                    products: $('textarea[name="project_products"]').val()
                }));
                calls.push(saveWizardSection(appId, 'finance', {
                    own_funds: $('input[name="finance_own_funds"]').val(),
                    term_loan: $('input[name="finance_term_loan"]').val(),
                    working_capital: $('input[name="finance_working_capital"]').val()
                }));
                calls.push(saveWizardSection(appId, 'land_use', {
                    builtup_area: $('input[name="land_builtup_area"]').val(),
                    green_area: $('input[name="land_green_area"]').val()
                }));
                calls.push(saveWizardSection(appId, 'environment', {
                    plan: $('textarea[name="env_plan"]').val()
                }));
                calls.push(saveWizardSection(appId, 'declaration', {
                    accepted: $('input[name="declaration_accept"]').is(':checked') ? 1 : 0
                }));

                $.when.apply($, calls).always(function() {
                    if (callback) callback(res);
                });
            },
            error: function(xhr) {
                showAlert(getAjaxErrorMessage(xhr, 'Draft save failed. Please try again.'), 'danger');
            }
        });
    }

    $('#saveDraftBtn').on('click', function() {
        createOrUpdateDraft(function() {
            showAlert('Draft saved successfully', 'success');
        });
    });

    $('#landApplyForm').on('submit', function(e) {
        e.preventDefault();
        createOrUpdateDraft(function(draftRes) {
            const formData = new FormData(document.getElementById('landApplyForm'));
            formData.append('final_submit', '1');
            formData.append('application_id', draftRes.data.application_id);
            $.ajax({
                url: '<?php echo APP_URL; ?>/api/land/apply',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    if (res.success) {
                        showAlert('Application submitted successfully. Reference: ' + res.data.application_number, 'success');
                        $('#feeText').text('Processing fee: INR ' + res.data.processing_fee);
                        $('#payNowBtn').data('app', res.data.application_id).data('fee', res.data.processing_fee);
                        $('#paymentBox').show();
                        $('#saveDraftBtn, #landApplyForm button[type="submit"]').prop('disabled', true);
                        document.getElementById('paymentBox')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    } else {
                        showAlert(res.message || 'Submission failed', 'danger');
                    }
                },
                error: function(xhr) {
                    showAlert(getAjaxErrorMessage(xhr, 'Submission failed. Please try again.'), 'danger');
                }
            });
        });
    });

    // Resume latest draft for this plot if present
    $.get('<?php echo APP_URL; ?>/api/land/applications', function(res) {
        if (!res.success || !Array.isArray(res.data)) return;
        const draft = res.data.find(a => parseInt(a.plot_id, 10) === plotId && a.status === 'draft');
        if (!draft) return;
        $('#applicationIdField').val(draft.id);
        $.get('<?php echo APP_URL; ?>/api/land/application-draft', { application_id: draft.id }, function(dres) {
            if (!dres.success || !dres.data || !dres.data.wizard) return;
            const w = dres.data.wizard;
            if (dres.data.application) {
                $('#purposeField').val(dres.data.application.purpose || 'industry');
                $('input[name="proposed_investment"]').val(dres.data.application.proposed_investment || '');
                $('input[name="employment_generation"]').val(dres.data.application.employment_generation || '');
                $('textarea[name="project_description"]').val(dres.data.application.project_description || '');
                if (dres.data.application.applicant_type_code) {
                    $('#applicantTypeField').val(dres.data.application.applicant_type_code);
                    renderChecklist(dres.data.application.applicant_type_code);
                }
            }
            if (w.project_json) {
                $('textarea[name="project_raw_materials"]').val(w.project_json.raw_materials || '');
                $('textarea[name="project_machinery"]').val(w.project_json.machinery || '');
                $('textarea[name="project_products"]').val(w.project_json.products || '');
            }
            if (w.finance_json) {
                $('input[name="finance_own_funds"]').val(w.finance_json.own_funds || '');
                $('input[name="finance_term_loan"]').val(w.finance_json.term_loan || '');
                $('input[name="finance_working_capital"]').val(w.finance_json.working_capital || '');
            }
            if (w.land_use_json) {
                $('input[name="land_builtup_area"]').val(w.land_use_json.builtup_area || '');
                $('input[name="land_green_area"]').val(w.land_use_json.green_area || '');
            }
            if (w.environment_json) {
                $('textarea[name="env_plan"]').val(w.environment_json.plan || '');
            }
            if (w.declaration_json && parseInt(w.declaration_json.accepted || 0, 10) === 1) {
                $('input[name="declaration_accept"]').prop('checked', true);
            }
            showAlert('Draft loaded. Continue editing and submit when ready.', 'info');
        });
    });

    $('#payNowBtn').on('click', function() {
        const appId = $(this).data('app');
        const fee = $(this).data('fee');
        if (!appId || !fee) return;
        $.post('<?php echo APP_URL; ?>/api/payment/initiate', {
            payment_type: 'processing_fee',
            reference_type: 'land_application',
            reference_id: appId,
            amount: fee
        }, function(res) {
            if (res.success) showAlert('Payment initiated. Proceed to gateway.', 'success');
            else showAlert(res.message || 'Payment initiation failed', 'danger');
        });
    });
});
</script>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
