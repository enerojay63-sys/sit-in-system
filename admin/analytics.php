<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

// ── Export CSV ──
if (isset($_GET['export'])) {
  $rows = $conn->query("
    SELECT sr.id, sr.id_number, sr.student_name, sr.purpose, sr.lab, sr.session,
           sr.status, sr.time_in, sr.time_out, sr.pc_no,
           TIMESTAMPDIFF(MINUTE, sr.time_in, IFNULL(sr.time_out, NOW())) as duration_min
    FROM sitin_records sr
    ORDER BY sr.time_in DESC
  ")->fetch_all(MYSQLI_ASSOC);

  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="sitin_report_'.date('Ymd_His').'.csv"');
  $f = fopen('php://output','w');
  fputcsv($f, ['ID','ID Number','Student Name','Purpose','Lab','Session Used','Status','Time In','Time Out','PC No','Duration (min)']);
  foreach ($rows as $r) fputcsv($f, $r);
  fclose($f);
  exit;
}

// ── Date range filter ──
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

$safe_from = $conn->real_escape_string($from);
$safe_to   = $conn->real_escape_string($to);

// ── Summary stats ──
$total_sessions  = $conn->query("SELECT COUNT(*) FROM sitin_records WHERE DATE(time_in) BETWEEN '$safe_from' AND '$safe_to'")->fetch_row()[0];
$unique_students = $conn->query("SELECT COUNT(DISTINCT student_id) FROM sitin_records WHERE DATE(time_in) BETWEEN '$safe_from' AND '$safe_to'")->fetch_row()[0];
$avg_duration    = $conn->query("SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, time_in, time_out)),0) FROM sitin_records WHERE status='done' AND DATE(time_in) BETWEEN '$safe_from' AND '$safe_to'")->fetch_row()[0] ?? 0;
$total_hours     = $conn->query("SELECT ROUND(SUM(TIMESTAMPDIFF(MINUTE, time_in, IFNULL(time_out,NOW())))/60,1) FROM sitin_records WHERE DATE(time_in) BETWEEN '$safe_from' AND '$safe_to'")->fetch_row()[0] ?? 0;

// ── Daily ──
$daily = $conn->query("
  SELECT DATE(time_in) as d, COUNT(*) as cnt
  FROM sitin_records
  WHERE DATE(time_in) BETWEEN '$safe_from' AND '$safe_to'
  GROUP BY DATE(time_in) ORDER BY d
")->fetch_all(MYSQLI_ASSOC);

// ── By lab ──
$by_lab = $conn->query("
  SELECT lab, COUNT(*) as cnt
  FROM sitin_records
  WHERE DATE(time_in) BETWEEN '$safe_from' AND '$safe_to'
  GROUP BY lab ORDER BY cnt DESC
")->fetch_all(MYSQLI_ASSOC);

// ── By purpose ──
$by_purpose = $conn->query("
  SELECT purpose, COUNT(*) as cnt
  FROM sitin_records
  WHERE DATE(time_in) BETWEEN '$safe_from' AND '$safe_to'
  GROUP BY purpose ORDER BY cnt DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// ── By course ──
$by_course = $conn->query("
  SELECT s.course, COUNT(sr.id) as cnt
  FROM sitin_records sr JOIN students s ON sr.student_id=s.id
  WHERE DATE(sr.time_in) BETWEEN '$safe_from' AND '$safe_to'
  GROUP BY s.course ORDER BY cnt DESC
")->fetch_all(MYSQLI_ASSOC);

// ── By hour of day ──
$by_hour = $conn->query("
  SELECT HOUR(time_in) as hr, COUNT(*) as cnt
  FROM sitin_records
  WHERE DATE(time_in) BETWEEN '$safe_from' AND '$safe_to'
  GROUP BY hr ORDER BY hr
")->fetch_all(MYSQLI_ASSOC);
$hour_labels = array_map(fn($h)=>date('g A',mktime($h['hr'],0,0)), $by_hour);

// ── Top students ──
$top_students = $conn->query("
  SELECT s.firstname, s.lastname, s.course, s.id_number, COUNT(sr.id) as total,
         ROUND(SUM(TIMESTAMPDIFF(MINUTE,sr.time_in,IFNULL(sr.time_out,NOW())))/60,1) as total_hours
  FROM students s JOIN sitin_records sr ON sr.student_id=s.id
  WHERE DATE(sr.time_in) BETWEEN '$safe_from' AND '$safe_to'
  GROUP BY s.id ORDER BY total DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// ── Feedback stats ──
$fb_dist = $conn->query("SELECT rating, COUNT(*) as cnt FROM feedback GROUP BY rating ORDER BY rating DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Analytics — CCS Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php include 'nav.php'; ?>

<div class="page-wrapper">

  <!-- Header + Export -->
  <div class="d-flex align-center justify-between mb-2 flex-wrap gap-1">
    <div class="section-title" style="margin-bottom:0"><i class="fas fa-chart-line"></i> Analytics & Reports</div>
    <div class="d-flex gap-1">
      <a href="?export=csv&from=<?=$from?>&to=<?=$to?>" class="btn btn-success btn-sm"><i class="fas fa-file-csv"></i> Export CSV</a>
      <button onclick="window.print()" class="btn btn-secondary btn-sm"><i class="fas fa-print"></i> Print</button>
    </div>
  </div>

  <!-- Date Range Filter -->
  <div class="card mb-2">
    <div class="card-body" style="padding:14px">
      <form method="GET" class="d-flex gap-1 flex-wrap align-center">
        <label style="font-size:0.82rem;font-weight:600;color:var(--text-muted)">From</label>
        <input type="date" name="from" class="form-control" style="width:160px" value="<?= $from ?>">
        <label style="font-size:0.82rem;font-weight:600;color:var(--text-muted)">To</label>
        <input type="date" name="to"   class="form-control" style="width:160px" value="<?= $to ?>">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Apply</button>
        <a href="analytics.php" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Reset</a>
      </form>
    </div>
  </div>

  <!-- Summary Stats -->
  <div class="stats-grid mb-2">
    <div class="stat-card">
      <div class="stat-icon red"><i class="fas fa-desktop"></i></div>
      <div class="stat-info"><div class="stat-value"><?= $total_sessions ?></div><div class="stat-label">Total Sessions</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon navy"><i class="fas fa-users"></i></div>
      <div class="stat-info"><div class="stat-value"><?= $unique_students ?></div><div class="stat-label">Unique Students</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon gold"><i class="fas fa-clock"></i></div>
      <div class="stat-info"><div class="stat-value"><?= $avg_duration ?> min</div><div class="stat-label">Avg Session Duration</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class="fas fa-hourglass-half"></i></div>
      <div class="stat-info"><div class="stat-value"><?= $total_hours ?> hrs</div><div class="stat-label">Total Hours Logged</div></div>
    </div>
  </div>

  <!-- Charts Row 1 -->
  <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px">
    <div class="card">
      <div class="card-header"><i class="fas fa-chart-line"></i> Daily Sit-ins</div>
      <div class="card-body"><div class="chart-container"><canvas id="dailyChart"></canvas></div></div>
    </div>
    <div class="card">
      <div class="card-header"><i class="fas fa-chart-pie"></i> By Lab</div>
      <div class="card-body"><div class="chart-container"><canvas id="labChart"></canvas></div></div>
    </div>
  </div>

  <!-- Charts Row 2 -->
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-bottom:20px">
    <div class="card">
      <div class="card-header"><i class="fas fa-bullseye"></i> By Purpose</div>
      <div class="card-body"><div class="chart-container"><canvas id="purposeChart"></canvas></div></div>
    </div>
    <div class="card">
      <div class="card-header"><i class="fas fa-graduation-cap"></i> By Course</div>
      <div class="card-body"><div class="chart-container"><canvas id="courseChart"></canvas></div></div>
    </div>
    <div class="card">
      <div class="card-header"><i class="fas fa-clock"></i> Peak Hours</div>
      <div class="card-body"><div class="chart-container"><canvas id="hourChart"></canvas></div></div>
    </div>
  </div>

  <!-- Feedback Distribution -->
  <div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;margin-bottom:20px">
    <div class="card">
      <div class="card-header"><i class="fas fa-star"></i> Feedback Distribution</div>
      <div class="card-body">
        <?php foreach($fb_dist as $fb): ?>
          <div class="d-flex align-center gap-1 mb-1">
            <span style="width:60px;font-size:0.82rem"><?= str_repeat('★',$fb['rating']) ?></span>
            <div class="progress" style="flex:1">
              <?php $max = max(array_column($fb_dist,'cnt') ?: [1]); ?>
              <div class="progress-bar gold" style="width:<?= round($fb['cnt']/$max*100) ?>%"></div>
            </div>
            <span style="width:30px;font-size:0.78rem;text-align:right"><?= $fb['cnt'] ?></span>
          </div>
        <?php endforeach; ?>
        <?php if(!$fb_dist): ?><p class="text-muted text-center">No feedback yet.</p><?php endif; ?>
      </div>
    </div>

    <!-- Top Students Table -->
    <div class="card">
      <div class="card-header"><i class="fas fa-trophy"></i> Top Students — Selected Period</div>
      <div class="table-wrap" style="padding:0">
        <table>
          <thead><tr><th>#</th><th>Student</th><th>Course</th><th>Sessions</th><th>Hours</th></tr></thead>
          <tbody>
            <?php foreach($top_students as $i => $st): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td>
                <div style="font-weight:600;font-size:0.83rem"><?= htmlspecialchars($st['firstname'].' '.$st['lastname']) ?></div>
                <div style="font-size:0.72rem;color:var(--text-muted)"><?= $st['id_number'] ?></div>
              </td>
              <td><?= $st['course'] ?></td>
              <td><span class="badge badge-info"><?= $st['total'] ?></span></td>
              <td><span class="badge badge-gold"><?= $st['total_hours'] ?> hrs</span></td>
            </tr>
            <?php endforeach; ?>
            <?php if(!$top_students): ?><tr><td colspan="5" class="text-center text-muted" style="padding:20px">No data for this period.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<script>
(function(){ const t=localStorage.getItem('theme')||'light'; document.documentElement.setAttribute('data-theme',t); })();

const COLORS = ['#ef233c','#2b2d42','#f4a022','#17a2b8','#28a745','#6f42c1','#fd7e14','#20c997'];
const gridColor = () => document.documentElement.getAttribute('data-theme')==='dark' ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
const textColor = () => document.documentElement.getAttribute('data-theme')==='dark' ? '#c0c8d8' : '#6c757d';

const scaleOpts = () => ({
  x: { grid:{color:gridColor()}, ticks:{color:textColor()} },
  y: { grid:{color:gridColor()}, ticks:{color:textColor()}, beginAtZero:true }
});

new Chart('dailyChart', {
  type:'line',
  data:{
    labels:<?= json_encode(array_column($daily,'d')) ?>,
    datasets:[{label:'Sessions',data:<?= json_encode(array_column($daily,'cnt')) ?>,
      borderColor:'#ef233c',backgroundColor:'rgba(239,35,60,0.08)',tension:0.4,fill:true,pointBackgroundColor:'#ef233c',pointRadius:4}]
  },
  options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:scaleOpts()}
});

new Chart('labChart', {
  type:'doughnut',
  data:{
    labels:<?= json_encode(array_column($by_lab,'lab')) ?>,
    datasets:[{data:<?= json_encode(array_column($by_lab,'cnt')) ?>,backgroundColor:COLORS,borderWidth:0}]
  },
  options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{color:textColor(),boxWidth:12,font:{size:10}}}}}
});

new Chart('purposeChart', {
  type:'bar',
  data:{
    labels:<?= json_encode(array_column($by_purpose,'purpose')) ?>,
    datasets:[{label:'Sessions',data:<?= json_encode(array_column($by_purpose,'cnt')) ?>,backgroundColor:COLORS,borderRadius:4}]
  },
  options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',plugins:{legend:{display:false}},scales:scaleOpts()}
});

new Chart('courseChart', {
  type:'bar',
  data:{
    labels:<?= json_encode(array_column($by_course,'course')) ?>,
    datasets:[{label:'Sessions',data:<?= json_encode(array_column($by_course,'cnt')) ?>,backgroundColor:COLORS,borderRadius:4}]
  },
  options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:scaleOpts()}
});

new Chart('hourChart', {
  type:'bar',
  data:{
    labels:<?= json_encode($hour_labels) ?>,
    datasets:[{label:'Sessions',data:<?= json_encode(array_column($by_hour,'cnt')) ?>,backgroundColor:'#2b2d42',borderRadius:4}]
  },
  options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:scaleOpts()}
});
</script>
</body>
</html>
