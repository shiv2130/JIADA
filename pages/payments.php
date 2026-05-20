<?php
$pageTitle = "Online Payments";
$currentPage = 'payments';

ob_start();
?>
<div class="container" style="padding-top: 30px;">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-credit-card"></i> Online Payments</h2>
        </div>
        <div class="card-body">
            <div id="duesSection">
                <h3>Outstanding Dues</h3>
                <div id="duesList"></div>
            </div>
            <div id="paymentHistory" style="margin-top: 30px;">
                <h3>Payment History</h3>
                <div id="historyList"></div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="max-width: 500px; margin: 50px auto; background: white; border-radius: 10px; padding: 30px;">
        <h3>Make Payment</h3>
        <div id="paymentDetails"></div>
        <div style="margin-top: 20px;">
            <button id="payNowBtn" class="btn btn-primary" style="width: 100%;"><i class="fas fa-lock"></i> Pay Securely</button>
            <button onclick="$('#paymentModal').hide()" class="btn btn-outline" style="width: 100%; margin-top: 10px;">Cancel</button>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    function loadDues() {
        $.get('<?php echo APP_URL; ?>/api/payment/dues', function(res) {
            if(res.success) {
                let html = '';
                if(res.data.dues.length === 0) { html = '<p>No outstanding dues.</p>'; }
                else {
                    html = '<div class="alert alert-info"><strong>Total Outstanding:</strong> ₹' + res.data.total_outstanding.toLocaleString() + '</div>';
                    html += '<table class="table"><thead><tr><th>Receipt#</th><th>For</th><th>Amount</th><th>Paid</th><th>Due</th><th>Action</th></tr></thead><tbody>';
                    res.data.dues.forEach(d => {
                        d.receipt_no = d.receipt_no || d.payment_id || d.id;
                        d.payment_for = d.payment_for || d.payment_type || '-';
                        html += `<tr><td>${d.receipt_no}</td><td>${d.payment_for}</td><td>₹${d.amount.toLocaleString()}</td>
                            <td>₹${(d.paid_amount||0).toLocaleString()}</td><td>₹${d.outstanding.toLocaleString()}</td>
                            <td><button class="btn btn-sm btn-primary pay-btn" data-id="${d.id}" data-amount="${d.outstanding}" data-desc="${d.description || d.payment_for || ''}">Pay Now</button></td></tr>`;
                    });
                    html += '</tbody></table>';
                }
                $('#duesList').html(html);
                
                // Payment history
                if(res.data.payment_history.length > 0) {
                    let hHtml = '<table class="table"><thead><tr><th>Month</th><th>Total Paid</th><th>Transactions</th></tr></thead><tbody>';
                    res.data.payment_history.forEach(h => {
                        hHtml += `<tr><td>${h.month}</td><td>₹${h.total_paid.toLocaleString()}</td><td>${h.count}</td></tr>`;
                    });
                    hHtml += '</tbody></table>';
                    $('#historyList').html(hHtml);
                }
            }
        });
    }
    
    $(document).on('click', '.pay-btn', function() {
        const id = $(this).data('id');
        const amount = $(this).data('amount');
        const desc = $(this).data('desc');
        $('#paymentDetails').html(`<p><strong>Description:</strong> ${desc}</p><p><strong>Amount:</strong> ₹${amount.toLocaleString()}</p>`);
        $('#payNowBtn').data('id', id).data('amount', amount);
        $('#paymentModal').show();
    });
    
    $('#payNowBtn').on('click', function() {
        const id = $(this).data('id');
        const amount = $(this).data('amount');
        $.post('<?php echo APP_URL; ?>/api/payment/initiate', {
            payment_type: 'other',
            reference_type: 'payment_due',
            reference_id: id,
            amount: amount
        }, function(res) {
            if(res.success) {
                alert('Redirecting to payment gateway...');
                $('#paymentModal').hide();
                loadDues();
            } else {
                alert(res.message);
            }
        });
    });
    
    loadDues();
});
</script>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>
