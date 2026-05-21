<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

$total_students = $conn->query("SELECT COUNT(*) FROM students")->fetch_row()[0];
$active_sitin   = $conn->query("SELECT COUNT(*) FROM sitin_records WHERE status='active'")->fetch_row()[0];
$total_sitin    = $conn->query("SELECT COUNT(*) FROM sitin_records")->fetch_row()[0];

$by_purpose  = $conn->query("SELECT purpose, COUNT(*) as cnt FROM sitin_records GROUP BY purpose ORDER BY cnt DESC")->fetch_all(MYSQLI_ASSOC);
$by_lab      = $conn->query("SELECT lab, COUNT(*) as cnt FROM sitin_records GROUP BY lab ORDER BY cnt DESC")->fetch_all(MYSQLI_ASSOC);
$by_date     = $conn->query("SELECT DATE(time_in) as date, COUNT(*) as cnt FROM sitin_records GROUP BY DATE(time_in) ORDER BY date DESC LIMIT 14")->fetch_all(MYSQLI_ASSOC);
$by_date_rev = array_reverse($by_date);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Sit-in Reports — CCS Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="page-wrapper">
  <div class="section-title"><i class="fas fa-chart-bar"></i> Sit-in Reports</div>

  <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
    <div class="stat-card"><div class="stat-icon navy"><i class="fas fa-users"></i></div><div><div class="stat-value"><?=$total_students?></div><div class="stat-label">Students Registered</div></div></div>
    <div class="stat-card"><div class="stat-icon green"><i class="fas fa-desktop"></i></div><div><div class="stat-value"><?=$active_sitin?></div><div class="stat-label">Currently Sit-in</div></div></div>
    <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-chart-bar"></i></div><div><div class="stat-value"><?=$total_sitin?></div><div class="stat-label">Total Sit-in</div></div></div>
  </div>

  <div class="card mb-2">
    <div class="card-header"><i class="fas fa-chart-pie"></i> Sit-in by Purpose</div>
    <div class="card-body" style="display:flex;align-items:center;justify-content:center;padding:32px;">
      <?php if ($by_purpose): ?>
      <div style="width:100%;max-width:360px;"><canvas id="purposePie" height="280"></canvas></div>
      <?php else: ?><p class="text-muted text-center">No sit-in data yet.</p><?php endif; ?>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:22px;">
    <div class="card">
      <div class="card-header"><i class="fas fa-door-open"></i> Sit-in by Laboratory</div>
      <div class="card-body"><canvas id="labBar" height="220"></canvas></div>
    </div>
    <div class="card">
      <div class="card-header"><i class="fas fa-chart-line"></i> Daily Sit-ins (Last 14 Days)</div>
      <div class="card-body"><canvas id="dateChart" height="220"></canvas></div>
    </div>
  </div>

  <?php if ($by_purpose): ?>
  <div class="card">
    <div class="card-header"><i class="fas fa-list"></i> Purpose Breakdown</div>
    <div class="card-body" style="padding:0;">
      <table class="data-table">
        <thead><tr><th>#</th><th>Purpose</th><th>Count</th><th>Share</th></tr></thead>
        <tbody>
          <?php $grand = array_sum(array_column($by_purpose,'cnt')); ?>
          <?php foreach ($by_purpose as $i => $p): $pct = $grand ? round($p['cnt']/$grand*100,1) : 0; ?>
          <tr>
            <td style="color:var(--text-muted);font-size:0.78rem;"><?= $i+1 ?></td>
            <td style="font-weight:600;"><?= htmlspecialchars($p['purpose']) ?></td>
            <td><span class="badge badge-info"><?= $p['cnt'] ?></span></td>
            <td>
              <div style="display:flex;align-items:center;gap:10px;">
                <div style="flex:1;background:var(--alice);border-radius:20px;height:8px;overflow:hidden;min-width:80px;">
                  <div style="width:<?=$pct?>%;background:linear-gradient(90deg,var(--prussian),var(--cerulean));height:100%;border-radius:20px;"></div>
                </div>
                <span style="font-size:0.78rem;color:var(--text-muted);font-weight:600;width:38px;"><?=$pct?>%</span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
<?php if ($by_purpose): ?>
new Chart(document.getElementById('purposePie'), {
  type: 'pie',
  data: {
    labels: <?= json_encode(array_column($by_purpose,'purpose')) ?>,
    datasets: [{
      data: <?= json_encode(array_column($by_purpose,'cnt')) ?>,
      backgroundColor: ['#1a3a8a','#e6a817','#dc2626','#16a34a','#7c3aed','#0ea5e9','#ea580c','#db2777','#0d9488'],
      borderWidth: 3, borderColor: '#fff', hoverOffset: 10
    }]
  },
  options: { responsive:true, plugins:{ legend:{ position:'bottom', labels:{ padding:18, font:{size:12}, usePointStyle:true } } } }
});
<?php endif; ?>

<?php if ($by_lab): ?>
new Chart(document.getElementById('labBar'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($by_lab,'lab')) ?>,
    datasets: [{ label:'Sit-ins', data:<?= json_encode(array_column($by_lab,'cnt')) ?>, backgroundColor:'#1a3a8a', borderRadius:6, borderSkipped:false }]
  },
  options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1},grid:{color:'#f0f4f8'}},x:{grid:{display:false}}} }
});
<?php endif; ?>

<?php if ($by_date): ?>
new Chart(document.getElementById('dateChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode(array_column($by_date_rev,'date')) ?>,
    datasets: [{ label:'Sit-ins', data:<?= json_encode(array_column($by_date_rev,'cnt')) ?>, borderColor:'#1a3a8a', backgroundColor:'rgba(26,58,138,0.08)', pointBackgroundColor:'#1a3a8a', pointRadius:5, fill:true, tension:0.35 }]
  },
  options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1},grid:{color:'#f0f4f8'}},x:{grid:{display:false},ticks:{font:{size:11}}}} }
});
<?php endif; ?>
</script>
</body>
</html>
