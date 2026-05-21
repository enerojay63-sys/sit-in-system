<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireStudent();

$s = $_SESSION['student_data'];
$stmt = $conn->prepare("SELECT * FROM students WHERE id=?");
$stmt->bind_param('i', $s['id']); $stmt->execute();
$s = $stmt->get_result()->fetch_assoc();
$_SESSION['student_data'] = $s;
$stmt->close();

// ── SUMMARY STATS ──
$summary = $conn->query("
  SELECT
    COUNT(*) as total_sessions,
    COUNT(CASE WHEN status='done' THEN 1 END) as done_sessions,
    COUNT(CASE WHEN status='active' THEN 1 END) as active_sessions,
    ROUND(SUM(CASE WHEN status='done' THEN TIMESTAMPDIFF(MINUTE,time_in,time_out) ELSE 0 END)/60,1) as total_hours,
    ROUND(AVG(CASE WHEN status='done' THEN TIMESTAMPDIFF(MINUTE,time_in,time_out) END),0) as avg_duration,
    MAX(CASE WHEN status='done' THEN TIMESTAMPDIFF(MINUTE,time_in,time_out) END) as longest_min
  FROM sitin_records WHERE student_id={$s['id']}
")->fetch_assoc();

// ── BY PURPOSE ──
$by_purpose = $conn->query("
  SELECT purpose, COUNT(*) as cnt
  FROM sitin_records WHERE student_id={$s['id']} AND status='done'
  GROUP BY purpose ORDER BY cnt DESC
")->fetch_all(MYSQLI_ASSOC);

// ── BY LAB ──
$by_lab = $conn->query("
  SELECT lab, COUNT(*) as cnt
  FROM sitin_records WHERE student_id={$s['id']} AND status='done'
  GROUP BY lab ORDER BY cnt DESC
")->fetch_all(MYSQLI_ASSOC);

// ── DAILY TREND (last 14 days) ──
$by_date = $conn->query("
  SELECT DATE(time_in) as date, COUNT(*) as cnt
  FROM sitin_records WHERE student_id={$s['id']} AND status='done'
  GROUP BY DATE(time_in) ORDER BY date DESC LIMIT 14
")->fetch_all(MYSQLI_ASSOC);
$by_date_rev = array_reverse($by_date);

// ── RECENT SESSIONS ──
$recent = $conn->query("
  SELECT *, TIMESTAMPDIFF(MINUTE,time_in,IFNULL(time_out,NOW())) as duration_min
  FROM sitin_records WHERE student_id={$s['id']}
  ORDER BY time_in DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// ── FEEDBACK STATS ──
$fb_stats = $conn->query("
  SELECT COUNT(*) as total_fb, ROUND(AVG(rating),1) as avg_rating
  FROM feedback WHERE student_id={$s['id']}
")->fetch_assoc();

// Format longest session
$longest_fmt = '—';
if (!empty($summary['longest_min'])) {
  $h = floor($summary['longest_min']/60);
  $m = $summary['longest_min'] % 60;
  $longest_fmt = ($h > 0 ? "{$h}h " : "") . "{$m}m";
}

// Sessions used
$sessions_used = 30 - $s['remaining_session'];
$session_pct   = round(($s['remaining_session'] / 30) * 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Sit-in Summary — <?= htmlspecialchars($s['firstname']) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
  <style>
    html.dark body { background: linear-gradient(170deg,#0a0d14 0%,#0f1117 40%,#1a1d27 100%); }
    html.dark .topnav { background: linear-gradient(135deg,#05101a 0%,#0f1117 55%,#0d0f1a 100%); }
    html.dark .card { background: #1a1d27; border-color: rgba(255,255,255,0.07); }
    html.dark .card-header { background: linear-gradient(135deg,#05101a,#0f1117); }
    html.dark .card-body { color: #c8d0dc; }
    html.dark table.data-table tbody tr { border-color: rgba(255,255,255,0.07); }
    html.dark table.data-table tbody tr:hover { background: rgba(142,202,230,0.06); }
    html.dark table.data-table tbody tr:nth-child(even) { background: rgba(255,255,255,0.03); }
    html.dark footer { background: rgba(5,16,26,0.9); color: rgba(200,208,220,0.5); }

    .summary-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 14px;
      margin-bottom: 22px;
    }
    .summary-card {
      background: var(--surface);
      border-radius: var(--radius);
      padding: 18px 16px;
      box-shadow: var(--shadow-md);
      border: 1px solid rgba(141,153,174,0.2);
      display: flex;
      align-items: center;
      gap: 14px;
      transition: transform 0.18s, box-shadow 0.18s;
    }
    .summary-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
    html.dark .summary-card { background: #1a1d27; border-color: rgba(255,255,255,0.07); }

    .s-icon {
      width: 48px; height: 48px; border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.2rem; color: #fff; flex-shrink: 0;
    }
    .s-icon.navy   { background: linear-gradient(135deg, var(--prussian), var(--cerulean)); }
    .s-icon.green  { background: linear-gradient(135deg, #065f46, #16a34a); }
    .s-icon.gold   { background: linear-gradient(135deg, var(--orange), var(--honey)); }
    .s-icon.red    { background: linear-gradient(135deg, #7f0018, var(--red)); }
    .s-icon.purple { background: linear-gradient(135deg, #4c1d95, #7c3aed); }
    .s-icon.sky    { background: linear-gradient(135deg, var(--prussian), #0ea5e9); }

    .s-val   { font-size: 1.6rem; font-weight: 800; color: var(--prussian); line-height: 1; }
    html.dark .s-val { color: #edf2f4; }
    .s-label { font-size: 0.73rem; color: var(--text-muted); font-weight: 500; margin-top: 3px; }

    .charts-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 22px;
    }

    .session-bar-wrap {
      margin: 8px 0 14px;
    }
    .session-bar-track {
      height: 10px;
      background: var(--alice);
      border-radius: 20px;
      overflow: hidden;
      border: 1px solid var(--border);
    }
    html.dark .session-bar-track { background: #242838; border-color: var(--border); }
    .session-bar-fill {
      height: 100%;
      border-radius: 20px;
      transition: width 0.6s ease;
    }

    .purpose-bar-row {
      display: flex; align-items: center; gap: 10px;
      margin-bottom: 10px; font-size: 0.8rem;
    }
    .purpose-label { width: 140px; font-weight: 600; color: var(--text-soft); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    html.dark .purpose-label { color: #c8d0dc; }
    .purpose-track { flex: 1; height: 8px; background: var(--alice); border-radius: 20px; overflow: hidden; }
    html.dark .purpose-track { background: #242838; }
    .purpose-fill  { height: 100%; border-radius: 20px; background: linear-gradient(90deg, var(--prussian), var(--cerulean)); }
    .purpose-count { width: 30px; text-align: right; font-weight: 700; color: var(--text-muted); }

    @media (max-width: 900px) {
      .summary-grid { grid-template-columns: repeat(2, 1fr); }
      .charts-grid  { grid-template-columns: 1fr; }
    }
    @media (max-width: 600px) {
      .summary-grid { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>

<nav class="topnav">
  <a href="dashboard.php" class="topnav-brand">Dashboard</a>
  <div class="topnav-links">
    <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
    <a href="sitin_summary.php" class="active"><i class="fas fa-chart-bar"></i> Summary</a>
    <a href="history.php"><i class="fas fa-history"></i> History</a>
    <a href="lab_availability.php"><i class="fas fa-desktop"></i> Labs</a>
    <a href="reservation.php"><i class="fas fa-calendar-plus"></i> Reservation</a>
    <a href="edit_profile.php"><i class="fas fa-user-edit"></i> Profile</a>
    <button class="dark-toggle" onclick="toggleDark()" title="Toggle dark mode">
      <i class="fas fa-moon" id="darkIcon"></i>
    </button>
    <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Log out</a>
  </div>
</nav>

<div class="page-content">

  <!-- Page Title -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:12px;">
    <div>
      <h2 style="font-size:1.25rem;font-weight:700;color:var(--prussian);margin-bottom:4px;">
        <i class="fas fa-chart-bar" style="color:var(--cerulean);"></i> Sit-in Summary
      </h2>
      <p style="font-size:0.8rem;color:var(--text-muted);">
        <?= htmlspecialchars($s['firstname'].' '.$s['lastname']) ?> &bull;
        <?= htmlspecialchars($s['course']) ?> Year <?= $s['year_level'] ?> &bull;
        <?= htmlspecialchars($s['id_number']) ?>
      </p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary btn-sm">
      <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
  </div>

  <!-- ── SUMMARY STAT CARDS ── -->
  <div class="summary-grid">
    <div class="summary-card">
      <div class="s-icon navy"><i class="fas fa-desktop"></i></div>
      <div>
        <div class="s-val"><?= $summary['total_sessions'] ?? 0 ?></div>
        <div class="s-label">Total Sit-ins</div>
      </div>
    </div>
    <div class="summary-card">
      <div class="s-icon green"><i class="fas fa-check-circle"></i></div>
      <div>
        <div class="s-val"><?= $summary['done_sessions'] ?? 0 ?></div>
        <div class="s-label">Completed</div>
      </div>
    </div>
    <div class="summary-card">
      <div class="s-icon gold"><i class="fas fa-hourglass-half"></i></div>
      <div>
        <div class="s-val"><?= $summary['total_hours'] ?? 0 ?><span style="font-size:1rem;font-weight:500;"> hrs</span></div>
        <div class="s-label">Total Hours</div>
      </div>
    </div>
    <div class="summary-card">
      <div class="s-icon sky"><i class="fas fa-clock"></i></div>
      <div>
        <div class="s-val"><?= $summary['avg_duration'] ? $summary['avg_duration'].'m' : '—' ?></div>
        <div class="s-label">Avg Duration</div>
      </div>
    </div>
    <div class="summary-card">
      <div class="s-icon purple"><i class="fas fa-trophy"></i></div>
      <div>
        <div class="s-val"><?= $longest_fmt ?></div>
        <div class="s-label">Longest Session</div>
      </div>
    </div>
    <div class="summary-card">
      <div class="s-icon red"><i class="fas fa-star"></i></div>
      <div>
        <div class="s-val"><?= $fb_stats['avg_rating'] ?? '—' ?><?= $fb_stats['avg_rating'] ? '<span style="font-size:1rem;font-weight:500;">/5</span>' : '' ?></div>
        <div class="s-label">Avg Feedback Rating</div>
      </div>
    </div>
    <div class="summary-card" style="grid-column: span 2;">
      <div style="flex:1;width:100%;">
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:0.78rem;">
          <span style="font-weight:600;color:var(--text-soft);">Remaining Sessions</span>
          <span style="font-weight:700;color:<?= $s['remaining_session'] <= 5 ? 'var(--red)' : 'var(--prussian)' ?>;">
            <?= $s['remaining_session'] ?> / 30
          </span>
        </div>
        <div class="session-bar-track">
          <div class="session-bar-fill" style="
            width: <?= $session_pct ?>%;
            background: <?= $s['remaining_session'] <= 5
              ? 'linear-gradient(90deg,#7f0018,#ef233c)'
              : 'linear-gradient(90deg,var(--prussian),var(--cerulean))' ?>;
          "></div>
        </div>
        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:6px;">
          <?= $sessions_used ?> sessions used out of 30
          <?php if ($s['remaining_session'] <= 5): ?>
          &nbsp;<span style="color:var(--red);font-weight:700;">⚠ Low!</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ── CHARTS ROW ── -->
  <div class="charts-grid">

    <!-- Purpose Breakdown -->
    <div class="card">
      <div class="card-header"><i class="fas fa-chart-pie"></i> Sit-ins by Purpose</div>
      <div class="card-body">
        <?php if ($by_purpose): ?>
        <canvas id="purposeChart" height="200"></canvas>
        <?php else: ?>
        <p style="text-align:center;color:var(--text-muted);padding:30px;font-size:0.84rem;">
          <i class="fas fa-chart-pie" style="font-size:2rem;display:block;margin-bottom:10px;opacity:0.25;"></i>
          No completed sessions yet.
        </p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Daily Trend -->
    <div class="card">
      <div class="card-header"><i class="fas fa-chart-line"></i> Daily Trend (Last 14 Days)</div>
      <div class="card-body">
        <?php if ($by_date): ?>
        <canvas id="trendChart" height="200"></canvas>
        <?php else: ?>
        <p style="text-align:center;color:var(--text-muted);padding:30px;font-size:0.84rem;">
          <i class="fas fa-chart-line" style="font-size:2rem;display:block;margin-bottom:10px;opacity:0.25;"></i>
          No session data yet.
        </p>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- ── BOTTOM ROW ── -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:22px;">

    <!-- Purpose + Lab breakdown -->
    <div class="card">
      <div class="card-header"><i class="fas fa-list"></i> Purpose Breakdown</div>
      <div class="card-body">
        <?php if ($by_purpose):
          $grand = array_sum(array_column($by_purpose,'cnt'));
          foreach ($by_purpose as $p):
            $pct = $grand ? round($p['cnt']/$grand*100) : 0;
        ?>
        <div class="purpose-bar-row">
          <div class="purpose-label" title="<?= htmlspecialchars($p['purpose']) ?>"><?= htmlspecialchars($p['purpose']) ?></div>
          <div class="purpose-track">
            <div class="purpose-fill" style="width:<?= $pct ?>%"></div>
          </div>
          <div class="purpose-count"><?= $p['cnt'] ?></div>
        </div>
        <?php endforeach; else: ?>
        <p style="text-align:center;color:var(--text-muted);padding:20px;font-size:0.84rem;">No data yet.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Lab Breakdown -->
    <div class="card">
      <div class="card-header"><i class="fas fa-door-open"></i> Lab Breakdown</div>
      <div class="card-body">
        <?php if ($by_lab):
          $grand_lab = array_sum(array_column($by_lab,'cnt'));
          foreach ($by_lab as $l):
            $pct = $grand_lab ? round($l['cnt']/$grand_lab*100) : 0;
        ?>
        <div class="purpose-bar-row">
          <div class="purpose-label">Lab <?= htmlspecialchars($l['lab']) ?></div>
          <div class="purpose-track">
            <div class="purpose-fill" style="width:<?= $pct ?>%;background:linear-gradient(90deg,var(--orange),var(--honey));"></div>
          </div>
          <div class="purpose-count"><?= $l['cnt'] ?></div>
        </div>
        <?php endforeach; else: ?>
        <p style="text-align:center;color:var(--text-muted);padding:20px;font-size:0.84rem;">No data yet.</p>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- ── RECENT SESSIONS TABLE ── -->
  <div class="card">
    <div class="card-header"><i class="fas fa-history"></i> Recent Sessions</div>
    <div class="card-body" style="padding:0;">
      <div class="dt-wrapper">
        <table class="data-table">
          <thead>
            <tr><th>Date</th><th>Time In</th><th>Time Out</th><th>Duration</th><th>Lab</th><th>Purpose</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php if ($recent): foreach ($recent as $r):
              $d = $r['duration_min'];
              $df = $d ? floor($d/60).'h '.($d%60).'m' : '—';
            ?>
            <tr>
              <td style="font-size:0.82rem;"><?= date('M d, Y', strtotime($r['time_in'])) ?></td>
              <td style="font-size:0.82rem;"><?= date('h:i A', strtotime($r['time_in'])) ?></td>
              <td style="font-size:0.82rem;"><?= $r['time_out'] ? date('h:i A', strtotime($r['time_out'])) : '<span class="badge badge-success">Active</span>' ?></td>
              <td style="font-size:0.82rem;"><?= $df ?></td>
              <td><span class="badge badge-info"><?= htmlspecialchars($r['lab']) ?></span></td>
              <td style="font-size:0.82rem;"><?= htmlspecialchars($r['purpose']) ?></td>
              <td><span class="badge badge-<?= $r['status']==='active'?'success':'secondary' ?>"><?= ucfirst($r['status']) ?></span></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7" class="no-data">No sessions yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<footer>&copy; <?= date('Y') ?> University of Cebu — College of Computer Studies. All rights reserved.</footer>

<script>
(function(){
  const t = localStorage.getItem('theme') || 'light';
  if (t === 'dark') {
    document.documentElement.classList.add('dark');
    const icon = document.getElementById('darkIcon');
    if (icon) icon.className = 'fas fa-sun';
  }
})();
function toggleDark() {
  const html   = document.documentElement;
  const isDark = html.classList.toggle('dark');
  document.getElementById('darkIcon').className = isDark ? 'fas fa-sun' : 'fas fa-moon';
  localStorage.setItem('theme', isDark ? 'dark' : 'light');
}

<?php if ($by_purpose): ?>
new Chart(document.getElementById('purposeChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_column($by_purpose,'purpose')) ?>,
    datasets: [{
      data: <?= json_encode(array_column($by_purpose,'cnt')) ?>,
      backgroundColor: ['#023047','#219ebc','#ffb703','#ef233c','#16a34a','#7c3aed','#fb8500','#0ea5e9'],
      borderWidth: 3, borderColor: 'transparent', hoverOffset: 8
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { position: 'bottom', labels: { padding: 14, font: { size: 11 }, usePointStyle: true } },
      tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed} session${ctx.parsed!==1?'s':''}` } }
    }
  }
});
<?php endif; ?>

<?php if ($by_date): ?>
new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode(array_column($by_date_rev,'date')) ?>,
    datasets: [{
      label: 'Sessions',
      data: <?= json_encode(array_column($by_date_rev,'cnt')) ?>,
      borderColor: '#219ebc',
      backgroundColor: 'rgba(33,158,188,0.1)',
      pointBackgroundColor: '#023047',
      pointRadius: 5,
      fill: true,
      tension: 0.35
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, ticks: { stepSize: 1, color: '#8d99ae' }, grid: { color: 'rgba(141,153,174,0.15)' } },
      x: { ticks: { color: '#8d99ae', font: { size: 11 } }, grid: { display: false } }
    }
  }
});
<?php endif; ?>
</script>
</body>
</html>