<?php
$pageTitle = "BRAP Tracker";
$currentPage = 'brap';
ob_start();
?>
<div class="container" style="padding-top:30px;">
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-clipboard-check"></i> BRAP Compliance</h2></div>
    <div class="card-body">
      <?php if (!isset($_SESSION['user_id'])): ?>
        <div class="alert alert-warning"><i class="fas fa-lock"></i> Login required.</div>
      <?php else: ?>
        <form id="brapForm" class="grid-2" style="margin-bottom:15px;">
          <input class="form-control" name="title" placeholder="Initiative title" required>
          <input class="form-control" name="category" placeholder="Category (e.g., EoDB, BRAP)" required>
          <input class="form-control" name="owner_role" placeholder="Owner role (e.g., admin/jiada_officer)" required>
          <input type="date" class="form-control" name="due_date" required>
          <select class="form-control" name="status">
            <option value="pending">Pending</option>
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
            <option value="escalated">Escalated</option>
          </select>
          <input class="form-control" name="evidence_url" placeholder="Evidence URL (optional)">
          <button class="btn btn-primary" type="submit">Save</button>
        </form>
        <div class="d-flex gap-10 mb-10">
          <input id="filterStatus" class="form-control" style="max-width:180px;" placeholder="Status filter">
          <input id="filterOwner" class="form-control" style="max-width:180px;" placeholder="Owner role filter">
          <input id="filterSearch" class="form-control" style="max-width:220px;" placeholder="Search...">
          <button class="btn btn-outline btn-sm" onclick="loadBrap()">Filter</button>
        </div>
        <div id="brapList"></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php if (isset($_SESSION['user_id'])): ?>
<script>
function loadBrap() {
  const params = $.param({
    status: $('#filterStatus').val(),
    owner_role: $('#filterOwner').val(),
    q: $('#filterSearch').val()
  });
  $.get('<?php echo APP_URL; ?>/api/brap/items?'+params, function(res){
    if(!res.success){ showAlert(res.message||'Load failed','danger'); return; }
    let html = '<div class="table-responsive"><table class="table"><thead><tr><th>Title</th><th>Category</th><th>Owner</th><th>Due</th><th>Status</th><th>Evidence</th></tr></thead><tbody>';
    res.data.forEach(i=>{
      html += `<tr><td>${i.title}</td><td>${i.category}</td><td>${i.owner_role}</td><td>${i.due_date||'-'}</td><td>${i.status}</td><td>${i.evidence_url?'<a href="'+i.evidence_url+'" target="_blank">Link</a>':'-'}</td></tr>`;
    });
    html += '</tbody></table></div>';
    $('#brapList').html(html);
  });
}
$('#brapForm').on('submit', function(e){
  e.preventDefault();
  $.post('<?php echo APP_URL; ?>/api/brap/items', $(this).serialize(), function(res){
    if(res.success){ showAlert(res.message,'success'); $('#brapForm')[0].reset(); loadBrap(); }
    else { showAlert(res.message||'Save failed','danger'); }
  });
});
loadBrap();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>
