<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();
$rows = $conn->query("SELECT * FROM sitin_records ORDER BY time_in DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Sit-in Records — CCS Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="page-wrapper">
  <div class="section-title"><i class="fas fa-list"></i> All Sit-in Records</div>
  <div class="card">
    <div class="card-body">
      <div class="dt-top">
        <div class="dt-top-left"><div class="dt-entries"><select id="entrySel" onchange="renderTable()"><option value="10">10</option><option value="25">25</option><option value="50">50</option></select><span class="dt-label">entries per page</span></div></div>
        <div class="dt-top-right"><span class="dt-label">Search:</span><div class="dt-search"><input type="text" id="searchBox" oninput="renderTable()" placeholder="Search..."></div></div>
      </div>
      <div class="dt-wrapper">
        <table class="data-table">
          <thead><tr><th>Sit ID</th><th>ID Number</th><th>Name</th><th>Purpose</th><th>Lab</th><th>Session</th><th>Status</th><th>Time In</th><th>Time Out</th></tr></thead>
          <tbody id="tableBody"></tbody>
        </table>
      </div>
      <div class="dt-pagination"><span id="showInfo"></span><div class="dt-pages" id="pages"></div></div>
    </div>
  </div>
</div>
<script>
const data=<?= json_encode($rows) ?>;let page=1;
function renderTable(){
  const perPage=parseInt(document.getElementById('entrySel').value);
  const search=document.getElementById('searchBox').value.toLowerCase();
  const filtered=data.filter(r=>Object.values(r).some(v=>String(v).toLowerCase().includes(search)));
  const total=filtered.length,totalPages=Math.max(1,Math.ceil(total/perPage));
  if(page>totalPages)page=totalPages;
  const start=(page-1)*perPage,slice=filtered.slice(start,start+perPage);
  const tb=document.getElementById('tableBody');
  if(!slice.length){tb.innerHTML='<tr><td colspan="9" class="no-data">No data available</td></tr>';}
  else{tb.innerHTML=slice.map(r=>`<tr><td>${r.id}</td><td>${r.id_number}</td><td>${r.student_name}</td><td>${r.purpose}</td><td>${r.lab}</td><td>${r.session}</td><td><span class="badge ${r.status==='active'?'badge-success':'badge-info'}">${r.status}</span></td><td>${r.time_in||'—'}</td><td>${r.time_out||'—'}</td></tr>`).join('');}
  document.getElementById('showInfo').textContent=total?`Showing ${start+1} to ${Math.min(start+perPage,total)} of ${total} entries`:'Showing 0 entries';
  const pages=document.getElementById('pages');
  pages.innerHTML=`<button onclick="goPage(1)" ${page===1?'disabled':''}>«</button><button onclick="goPage(${page-1})" ${page===1?'disabled':''}>‹</button><button class="active">${page}</button><button onclick="goPage(${page+1})" ${page===totalPages?'disabled':''}>›</button><button onclick="goPage(${totalPages})" ${page===totalPages?'disabled':''}>»</button>`;
}
function goPage(p){page=p;renderTable();}renderTable();
</script>
</body></html>
