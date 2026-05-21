<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireStudent();
$s = $_SESSION['student_data'];
$stmt = $conn->prepare("SELECT * FROM students WHERE id=?");
$stmt->bind_param('i',$s['id']); $stmt->execute();
$s = $stmt->get_result()->fetch_assoc(); $stmt->close();
$_SESSION['student_data'] = $s;

// Summary stats
$summary = $conn->query("
  SELECT COUNT(*) as total_sessions,
    ROUND(SUM(CASE WHEN status='done' THEN TIMESTAMPDIFF(MINUTE,time_in,time_out) ELSE 0 END)/60,1) as total_hours,
    ROUND(AVG(CASE WHEN status='done' THEN TIMESTAMPDIFF(MINUTE,time_in,time_out) END),0) as avg_duration,
    MAX(CASE WHEN status='done' THEN TIMESTAMPDIFF(MINUTE,time_in,time_out) END) as longest_min
  FROM sitin_records WHERE student_id={$s['id']}
")->fetch_assoc();

$longest_fmt = '—';
if (!empty($summary['longest_min'])) {
  $h = floor($summary['longest_min']/60); $m = $summary['longest_min']%60;
  $longest_fmt = ($h>0?"$h hr ":"")."$m min";
}

// Reservation enabled?
$reserv_enabled = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='reservation_enabled'")->fetch_row()[0] ?? '1';

// All records
$rows = $conn->query("
  SELECT *, TIMESTAMPDIFF(MINUTE,time_in,IFNULL(time_out,NOW())) as duration_min
  FROM sitin_records WHERE student_id={$s['id']}
  ORDER BY time_in DESC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>History & Summary — CCS</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    html.dark body{background:linear-gradient(170deg,#0a0d14 0%,#0f1117 40%,#1a1d27 100%);}
    html.dark .topnav{background:linear-gradient(135deg,#05101a 0%,#0f1117 55%,#0d0f1a 100%);}
    html.dark .card{background:#1a1d27;border-color:rgba(255,255,255,0.07);}
    html.dark .card-header{background:linear-gradient(135deg,#05101a,#0f1117);}
    html.dark table.data-table tbody tr{border-color:rgba(255,255,255,0.07);}
    html.dark table.data-table tbody tr:hover{background:rgba(142,202,230,0.06);}
    html.dark table.data-table tbody tr:nth-child(even){background:rgba(255,255,255,0.03);}
    html.dark footer{background:rgba(5,16,26,0.9);color:rgba(200,208,220,0.5);}
    html.dark .dt-search input,html.dark .dt-entries select{background:#1e2130;border-color:#3d4060;color:#edf2f4;}
    html.dark .dt-pages button{background:#1e2130;border-color:#3d4060;color:#edf2f4;}
    .summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px;}
    .sum-card{background:var(--surface);border-radius:var(--radius);padding:18px 16px;box-shadow:var(--shadow-md);border:1px solid rgba(141,153,174,0.2);display:flex;align-items:center;gap:14px;transition:transform .18s,box-shadow .18s;}
    .sum-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-lg);}
    html.dark .sum-card{background:#1a1d27;border-color:rgba(255,255,255,0.07);}
    .sum-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#fff;flex-shrink:0;}
    .sum-icon.navy{background:linear-gradient(135deg,var(--prussian),var(--cerulean));}
    .sum-icon.green{background:linear-gradient(135deg,#065f46,#16a34a);}
    .sum-icon.gold{background:linear-gradient(135deg,var(--orange),var(--honey));}
    .sum-icon.purple{background:linear-gradient(135deg,#4c1d95,#7c3aed);}
    .sum-val{font-size:1.5rem;font-weight:800;color:var(--prussian);line-height:1;}
    html.dark .sum-val{color:#edf2f4;}
    .sum-label{font-size:0.73rem;color:var(--text-muted);font-weight:500;margin-top:3px;}
    .session-bar-track{height:9px;background:var(--alice);border-radius:20px;overflow:hidden;border:1px solid var(--border);}
    html.dark .session-bar-track{background:#242838;border-color:var(--border);}
    .session-bar-fill{height:100%;border-radius:20px;transition:width .6s ease;}
    @media(max-width:900px){.summary-grid{grid-template-columns:repeat(2,1fr);}}
    @media(max-width:600px){.summary-grid{grid-template-columns:1fr 1fr;}}
  </style>
</head>
<body>
<nav class="topnav">
  <a href="dashboard.php" class="topnav-brand">Dashboard</a>
  <div class="topnav-links">
    <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
    <a href="edit_profile.php"><i class="fas fa-user-edit"></i> Profile</a>
    <a href="history.php" class="active"><i class="fas fa-history"></i> History</a>
    <a href="reservation.php"><i class="fas fa-calendar-plus"></i> Reservation</a>
    <button class="dark-toggle" onclick="toggleDark()" title="Toggle dark mode"><i class="fas fa-moon" id="darkIcon"></i></button>
    <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Log out</a>
  </div>
</nav>

<div class="page-content">
  <h2 style="text-align:center;margin-bottom:22px;font-size:1.35rem;font-weight:700;color:var(--prussian)">
    <i class="fas fa-chart-bar" style="color:var(--cerulean)"></i> History &amp; Summary
  </h2>

  <!-- Summary Stats -->
  <div class="summary-grid">
    <div class="sum-card">
      <div class="sum-icon navy"><i class="fas fa-desktop"></i></div>
      <div><div class="sum-val"><?=$summary['total_sessions']??0?></div><div class="sum-label">Total Sit-ins</div></div>
    </div>
    <div class="sum-card">
      <div class="sum-icon green"><i class="fas fa-hourglass-half"></i></div>
      <div><div class="sum-val"><?=$summary['total_hours']??0?><span style="font-size:1rem;font-weight:500"> hrs</span></div><div class="sum-label">Total Hours</div></div>
    </div>
    <div class="sum-card">
      <div class="sum-icon gold"><i class="fas fa-clock"></i></div>
      <div><div class="sum-val"><?=$summary['avg_duration']?$summary['avg_duration'].'<span style="font-size:1rem;font-weight:500"> min</span>':'—'?></div><div class="sum-label">Avg Duration</div></div>
    </div>
    <div class="sum-card">
      <div class="sum-icon purple"><i class="fas fa-trophy"></i></div>
      <div><div class="sum-val" style="font-size:1.2rem"><?=$longest_fmt?></div><div class="sum-label">Longest Session</div></div>
    </div>
  </div>

  <!-- Sessions remaining bar -->
  <div class="card" style="margin-bottom:22px;">
    <div class="card-body" style="padding:16px 20px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;font-size:0.82rem;">
        <span style="font-weight:600;color:var(--text-soft)"><i class="fas fa-clock" style="color:var(--cerulean)"></i> Remaining Sessions</span>
        <span style="font-weight:700;color:<?=$s['remaining_session']<=5?'var(--red)':'var(--prussian)'?>"><?=$s['remaining_session']?> / 30</span>
      </div>
      <div class="session-bar-track">
        <div class="session-bar-fill" style="width:<?=round($s['remaining_session']/30*100)?>%;background:<?=$s['remaining_session']<=5?'linear-gradient(90deg,#7f0018,#ef233c)':'linear-gradient(90deg,var(--prussian),var(--cerulean))'?>"></div>
      </div>
      <div style="display:flex;justify-content:space-between;margin-top:6px;font-size:0.72rem;color:var(--text-muted)">
        <span><?=30-$s['remaining_session']?> sessions used</span>
        <?php if($s['remaining_session']<=5): ?><span style="color:var(--red);font-weight:700">⚠ Low sessions!</span><?php endif; ?>
        <?php if($reserv_enabled==='0'): ?><span style="color:var(--orange);font-weight:700"><i class="fas fa-ban"></i> Reservations disabled</span><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Session Table -->
  <div class="card">
    <div class="card-header"><i class="fas fa-list"></i> Session Records</div>
    <div class="card-body">
      <div class="dt-top">
        <div class="dt-top-left">
          <div class="dt-entries">
            <select id="entrySel" onchange="renderTable()"><option value="10">10</option><option value="25">25</option><option value="50">50</option></select>
            <span class="dt-label">entries per page</span>
          </div>
        </div>
        <div class="dt-top-right">
          <span class="dt-label">Search:</span>
          <div class="dt-search"><input type="text" id="searchBox" oninput="renderTable()" placeholder="Search..."></div>
        </div>
      </div>
      <div class="dt-wrapper">
        <table class="data-table">
          <thead>
            <tr><th>Date</th><th>Time In</th><th>Time Out</th><th>Duration</th><th>Lab</th><th>PC No.</th><th>Purpose</th><th>Status</th></tr>
          </thead>
          <tbody id="tableBody"></tbody>
        </table>
      </div>
      <div class="dt-pagination"><span id="showInfo"></span><div class="dt-pages" id="pages"></div></div>
    </div>
  </div>
</div>

<footer>&copy; <?=date('Y')?> University of Cebu — College of Computer Studies.</footer>

<script>
(function(){const t=localStorage.getItem('theme')||'light';if(t==='dark'){document.documentElement.classList.add('dark');const i=document.getElementById('darkIcon');if(i)i.className='fas fa-sun';}})();
function toggleDark(){const isDark=document.documentElement.classList.toggle('dark');document.getElementById('darkIcon').className=isDark?'fas fa-sun':'fas fa-moon';localStorage.setItem('theme',isDark?'dark':'light');}

const data=<?=json_encode($rows)?>;let page=1;
function renderTable(){
  const perPage=parseInt(document.getElementById('entrySel').value);
  const search=document.getElementById('searchBox').value.toLowerCase();
  const filtered=data.filter(r=>Object.values(r).some(v=>String(v).toLowerCase().includes(search)));
  const total=filtered.length,totalPages=Math.max(1,Math.ceil(total/perPage));
  if(page>totalPages)page=totalPages;
  const start=(page-1)*perPage,slice=filtered.slice(start,start+perPage);
  const tb=document.getElementById('tableBody');
  if(!slice.length){tb.innerHTML='<tr><td colspan="8" class="no-data">No sessions yet.</td></tr>';}
  else{
    tb.innerHTML=slice.map(r=>{
      const d=r.duration_min;
      const df=d?(Math.floor(d/60)>0?Math.floor(d/60)+'h ':'')+(d%60)+'m':'—';
      return `<tr>
        <td style="font-size:.82rem">${r.time_in?r.time_in.substring(0,10):'—'}</td>
        <td style="font-size:.82rem">${r.time_in?r.time_in.substring(11,16):'—'}</td>
        <td style="font-size:.82rem">${r.time_out?r.time_out.substring(11,16):'<span class="badge badge-success">Active</span>'}</td>
        <td style="font-size:.82rem;font-weight:600">${df}</td>
        <td><span class="badge badge-info">${r.lab}</span></td>
        <td style="font-size:.82rem">${r.pc_no?'PC '+r.pc_no:'—'}</td>
        <td style="font-size:.82rem">${r.purpose}</td>
        <td><span class="badge badge-${r.status==='active'?'success':'secondary'}">${r.status}</span></td>
      </tr>`;
    }).join('');
  }
  document.getElementById('showInfo').textContent=total?`Showing ${start+1} to ${Math.min(start+perPage,total)} of ${total} entries`:'Showing 0 entries';
  document.getElementById('pages').innerHTML=`<button onclick="goPage(1)" ${page===1?'disabled':''}>«</button><button onclick="goPage(${page-1})" ${page===1?'disabled':''}>‹</button><button class="active">${page}</button><button onclick="goPage(${page+1})" ${page===totalPages?'disabled':''}>›</button><button onclick="goPage(${totalPages})" ${page===totalPages?'disabled':''}>»</button>`;
}
function goPage(p){page=p;renderTable();}
renderTable();
</script>
</body>
</html>