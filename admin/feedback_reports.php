<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

$feedbacks = $conn->query("
  SELECT f.*, s.id_number, s.firstname, s.lastname, s.course, s.year_level,
         sr.purpose, sr.lab, DATE(f.created_at) as fdate
  FROM feedback f
  JOIN students s ON f.student_id=s.id
  JOIN sitin_records sr ON f.sitin_id=sr.id
  ORDER BY f.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$total   = count($feedbacks);
$avg_row = $conn->query("SELECT AVG(rating) as avg FROM feedback")->fetch_assoc();
$avg     = $avg_row['avg'] ? round($avg_row['avg'],1) : 0;
$dist    = $conn->query("SELECT rating, COUNT(*) as cnt FROM feedback GROUP BY rating ORDER BY rating DESC")->fetch_all(MYSQLI_ASSOC);
$dist_map = array_column($dist,'cnt','rating');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Feedback Reports — CCS Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'nav.php'; ?>

<div class="page-wrapper">
  <div class="section-title"><i class="fas fa-comments"></i> Feedback Reports</div>
  
  <div class="stats-row" style="margin-bottom:22px;">
    <div class="stat-box">
      <div class="stat-icon"><i class="fas fa-comments"></i></div>
      <div><div class="stat-label">Total Feedback</div><div class="stat-value"><?= $total ?></div></div>
    </div>
    <div class="stat-box">
      <div class="stat-icon orange"><i class="fas fa-star"></i></div>
      <div><div class="stat-label">Average Rating</div>
        <div class="stat-value"><?= $avg ?><span style="font-size:1rem;color:var(--muted);">/5</span></div>
      </div>
    </div>
    <div class="stat-box">
      <div style="flex:1;width:100%;">
        <div class="stat-label" style="margin-bottom:8px;">Rating Distribution</div>
        <?php for($i=5;$i>=1;$i--): $cnt=$dist_map[$i]??0; $pct=$total?round($cnt/$total*100):0; ?>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;">
          <span style="font-size:0.74rem;color:var(--muted);width:12px;"><?=$i?></span>
          <i class="fas fa-star" style="color:#e6a817;font-size:0.7rem;"></i>
          <div style="flex:1;background:#f1f5f9;border-radius:4px;height:7px;overflow:hidden;">
            <div style="width:<?=$pct?>%;height:100%;background:linear-gradient(90deg,#d97706,#e6a817);border-radius:4px;transition:width 0.5s;"></div>
          </div>
          <span style="font-size:0.73rem;color:var(--muted);width:18px;text-align:right;"><?=$cnt?></span>
        </div>
        <?php endfor; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><i class="fas fa-comments"></i> All Student Feedback</div>
    <div class="card-body">
      <?php if(!$feedbacks): ?>
        <div style="text-align:center;padding:48px;color:var(--muted);">
          <i class="fas fa-comments" style="font-size:2.5rem;opacity:0.2;display:block;margin-bottom:12px;"></i>
          <p>No feedback submitted yet. Students can submit feedback after sit-in sessions.</p>
        </div>
      <?php else: ?>
      <div class="dt-top">
        <div class="dt-entries"><select id="entrySel" onchange="renderTable()"><option value="10">10</option><option value="25">25</option><option value="50">50</option></select><span style="font-size:0.82rem;color:var(--muted);">entries per page</span></div>
        <div class="dt-search"><input type="text" id="searchBox" oninput="renderTable()" placeholder="Search..."></div>
      </div>
      <div class="dt-wrapper">
        <table class="data-table">
          <thead><tr><th>Student</th><th>Course</th><th>Yr</th><th>Purpose</th><th>Lab</th><th>Rating</th><th>Comment</th><th>Date</th></tr></thead>
          <tbody id="tableBody"></tbody>
        </table>
      </div>
      <div class="dt-pagination"><span id="showInfo"></span><div class="dt-pages" id="pages"></div></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<footer>&copy; <?= date('Y') ?> University of Cebu — College of Computer Studies.</footer>
<script>
const data=<?= json_encode($feedbacks) ?>;let page=1;
function stars(n){let s='';for(let i=1;i<=5;i++)s+=`<i class="fas fa-star" style="color:${i<=n?'#e6a817':'#e2e8f0'};font-size:0.77rem;"></i>`;return s;}
function renderTable(){
  const perPage=parseInt(document.getElementById('entrySel').value);
  const search=document.getElementById('searchBox').value.toLowerCase();
  const filtered=data.filter(r=>Object.values(r).some(v=>String(v).toLowerCase().includes(search)));
  const total=filtered.length,totalPages=Math.max(1,Math.ceil(total/perPage));
  if(page>totalPages)page=totalPages;
  const start=(page-1)*perPage,slice=filtered.slice(start,start+perPage);
  const tb=document.getElementById('tableBody');
  if(!slice.length){tb.innerHTML='<tr><td colspan="8" class="no-data">No feedback found</td></tr>';}
  else{tb.innerHTML=slice.map(r=>`<tr>
    <td><strong>${r.firstname} ${r.lastname}</strong><br><small style="color:var(--muted)">${r.id_number}</small></td>
    <td>${r.course}</td><td>${r.year_level}</td><td>${r.purpose}</td>
    <td><span class="badge badge-info">${r.lab}</span></td>
    <td>${stars(r.rating)}<br><small style="color:var(--muted)">${r.rating}/5</small></td>
    <td style="max-width:180px;font-size:0.82rem;">${r.comment||'<span style="color:var(--muted)">—</span>'}</td>
    <td style="white-space:nowrap;font-size:0.78rem;color:var(--muted)">${r.fdate}</td>
  </tr>`).join('');}
  document.getElementById('showInfo').textContent=total?`Showing ${start+1} to ${Math.min(start+perPage,total)} of ${total} entries`:'No entries';
  const pages=document.getElementById('pages');
  pages.innerHTML=`<button onclick="goPage(1)" ${page===1?'disabled':''}>«</button><button onclick="goPage(${page-1})" ${page===1?'disabled':''}>‹</button><button class="active">${page}</button><button onclick="goPage(${page+1})" ${page===totalPages?'disabled':''}>›</button><button onclick="goPage(${totalPages})" ${page===totalPages?'disabled':''}>»</button>`;
}
function goPage(p){page=p;renderTable();}
renderTable();

// Syncing global theme layout class rule
(function(){ 
  const t = localStorage.getItem('theme') || 'light'; 
  if (t === 'dark') document.documentElement.classList.add('dark');
})();
</script>
</body>
</html>