<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireStudent();

$s = $_SESSION['student_data'];

// Get all labs from pc_control
$labs_res = $conn->query("SELECT DISTINCT lab FROM pc_control ORDER BY lab");
$labs = [];
while ($r = $labs_res->fetch_row()) $labs[] = $r[0];
if (empty($labs)) $labs = ['524','526','528'];

// PC availability + software per lab
$lab_data = [];
foreach ($labs as $lab) {
  $sl = $conn->real_escape_string($lab);
  $pcs = $conn->query("
    SELECT p.*, s.firstname, s.lastname
    FROM pc_control p
    LEFT JOIN students s ON p.student_id = s.id
    WHERE p.lab='$sl'
    ORDER BY p.pc_number
  ")->fetch_all(MYSQLI_ASSOC);

  $stats = ['available'=>0,'occupied'=>0,'maintenance'=>0,'locked'=>0,'total'=>count($pcs)];
  foreach ($pcs as $pc) {
    if (isset($stats[$pc['status']])) $stats[$pc['status']]++;
  }

  $software = $conn->query("
    SELECT * FROM software WHERE lab='$sl' AND status='available' ORDER BY name
  ")->fetch_all(MYSQLI_ASSOC);

  $lab_data[$lab] = [
    'pcs'      => $pcs,
    'stats'    => $stats,
    'software' => $software,
    'pct'      => $stats['total'] > 0 ? round($stats['occupied'] / $stats['total'] * 100) : 0,
  ];
}

// Software icons map
$icons = [
  'visual studio' => 'fab fa-microsoft',
  'vscode'        => 'fab fa-microsoft',
  'xampp'         => 'fas fa-server',
  'android'       => 'fab fa-android',
  'java'          => 'fab fa-java',
  'photoshop'     => 'fas fa-image',
  'figma'         => 'fab fa-figma',
  'python'        => 'fab fa-python',
  'jupyter'       => 'fas fa-book',
  'mysql'         => 'fas fa-database',
  'postman'       => 'fas fa-paper-plane',
  'git'           => 'fab fa-git-alt',
  'chrome'        => 'fab fa-chrome',
  'node'          => 'fab fa-node-js',
  'php'           => 'fab fa-php',
  'html'          => 'fab fa-html5',
  'css'           => 'fab fa-css3-alt',
  'react'         => 'fab fa-react',
  'wordpress'     => 'fab fa-wordpress',
  'eclipse'       => 'fas fa-code',
];

function getSoftwareIcon($name, $icons) {
  $lower = strtolower($name);
  foreach ($icons as $key => $icon) {
    if (str_contains($lower, $key)) return $icon;
  }
  return 'fas fa-cube';
}

// Selected lab (default first)
$selected = $_GET['lab'] ?? $labs[0];
if (!in_array($selected, $labs)) $selected = $labs[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Lab Availability — CCS</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    html.dark body { background: linear-gradient(170deg,#0a0d14 0%,#0f1117 40%,#1a1d27 100%); }
    html.dark .topnav { background: linear-gradient(135deg,#05101a 0%,#0f1117 55%,#0d0f1a 100%); }
    html.dark .card { background: #1a1d27; border-color: rgba(255,255,255,0.07); }
    html.dark .card-header { background: linear-gradient(135deg,#05101a,#0f1117); }
    html.dark .card-body { color: #c8d0dc; }
    html.dark footer { background: rgba(5,16,26,0.9); color: rgba(200,208,220,0.5); }

    /* Lab tabs */
    .lab-tabs { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
    .lab-tab {
      padding: 9px 20px; border-radius: 20px;
      border: 2px solid var(--border);
      background: var(--surface); color: var(--text-muted);
      font-weight: 600; font-size: 0.82rem;
      text-decoration: none; transition: all 0.18s;
      display: flex; align-items: center; gap: 7px;
    }
    .lab-tab:hover { border-color: var(--cerulean); color: var(--cerulean); }
    .lab-tab.active { background: var(--prussian); color: #fff; border-color: var(--prussian); }
    html.dark .lab-tab { background: #1a1d27; border-color: rgba(255,255,255,0.1); color: #8d99ae; }
    html.dark .lab-tab.active { background: var(--prussian); color: #fff; }

    /* Legend */
    .legend {
      display: flex; gap: 16px; flex-wrap: wrap;
      padding: 10px 16px; background: var(--alice);
      border-radius: 8px; border: 1px solid var(--border);
      margin-bottom: 16px;
    }
    html.dark .legend { background: #242838; border-color: rgba(255,255,255,0.07); }
    .legend-item { display: flex; align-items: center; gap: 7px; font-size: 0.79rem; font-weight: 600; }
    .legend-dot  { width: 12px; height: 12px; border-radius: 3px; flex-shrink: 0; }
    .legend-dot.available   { background: #16a34a; }
    .legend-dot.occupied    { background: #ef233c; }
    .legend-dot.maintenance { background: #fb8500; }
    .legend-dot.locked      { background: #8d99ae; }

    /* Stats row */
    .stats-mini { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
    .stat-mini-pill {
      display: flex; align-items: center; gap: 7px;
      padding: 8px 14px; border-radius: 20px;
      font-size: 0.8rem; font-weight: 700;
      border: 1.5px solid; background: var(--surface);
    }
    html.dark .stat-mini-pill { background: #1a1d27; }

    /* Occupancy bar */
    .occ-bar-wrap { margin-bottom: 18px; }
    .occ-bar-track { height: 10px; background: var(--alice); border-radius: 20px; overflow: hidden; border: 1px solid var(--border); }
    html.dark .occ-bar-track { background: #242838; border-color: rgba(255,255,255,0.07); }
    .occ-bar-fill  { height: 100%; border-radius: 20px; transition: width 0.5s ease; }

    /* PC Grid */
    .pc-grid-view {
      display: grid;
      grid-template-columns: repeat(10, 1fr);
      gap: 6px;
    }
    @media (max-width: 1100px) { .pc-grid-view { grid-template-columns: repeat(7, 1fr); } }
    @media (max-width: 700px)  { .pc-grid-view { grid-template-columns: repeat(5, 1fr); } }

    .pc-tile {
      border: 2px solid var(--border);
      border-radius: 8px;
      padding: 7px 4px 6px;
      text-align: center;
      font-size: 0.68rem;
      background: var(--surface);
      transition: transform 0.15s;
      cursor: default;
      user-select: none;
    }
    .pc-tile:hover { transform: scale(1.06); }
    .pc-tile.available   { border-color: #16a34a; background: #f0fdf4; }
    .pc-tile.occupied    { border-color: #ef233c; background: #fff1f2; }
    .pc-tile.maintenance { border-color: #fb8500; background: #fff7ed; }
    .pc-tile.locked      { border-color: #8d99ae; background: #f8fafc; opacity: 0.6; }
    html.dark .pc-tile             { background: #1e2130; border-color: #3d4060; }
    html.dark .pc-tile.available   { background: rgba(22,163,74,0.12);  border-color: #16a34a; }
    html.dark .pc-tile.occupied    { background: rgba(239,35,60,0.12);  border-color: #ef233c; }
    html.dark .pc-tile.maintenance { background: rgba(251,133,0,0.12);  border-color: #fb8500; }
    html.dark .pc-tile.locked      { background: rgba(141,153,174,0.08);border-color: #8d99ae; }

    .pc-tile-icon { font-size: 0.85rem; margin-bottom: 2px; }
    .pc-tile-icon.available   { color: #16a34a; }
    .pc-tile-icon.occupied    { color: #ef233c; }
    .pc-tile-icon.maintenance { color: #fb8500; }
    .pc-tile-icon.locked      { color: #8d99ae; }
    .pc-tile-num { font-weight: 700; font-size: 0.65rem; color: var(--text-soft); }
    html.dark .pc-tile-num { color: #8d99ae; }
    .pc-tile-user { font-size: 0.55rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }

    /* Two-column layout */
    .lab-layout {
      display: grid;
      grid-template-columns: 1fr 280px;
      gap: 20px;
      align-items: start;
    }
    @media (max-width: 900px) { .lab-layout { grid-template-columns: 1fr; } }

    /* Software list */
    .sw-item {
      display: flex; align-items: center; gap: 10px;
      padding: 9px 0; border-bottom: 1px solid var(--border);
      font-size: 0.82rem;
    }
    .sw-item:last-child { border-bottom: none; }
    html.dark .sw-item { border-color: rgba(255,255,255,0.07); }
    .sw-icon-wrap {
      width: 32px; height: 32px; border-radius: 8px;
      background: linear-gradient(135deg, var(--prussian), var(--cerulean));
      display: flex; align-items: center; justify-content: center;
      color: #fff; font-size: 0.85rem; flex-shrink: 0;
    }
    .sw-name    { font-weight: 600; color: var(--prussian); font-size: 0.82rem; }
    html.dark .sw-name { color: #edf2f4; }
    .sw-version { font-size: 0.7rem; color: var(--text-muted); margin-top: 1px; }
  </style>
</head>
<body>

<nav class="topnav">
  <a href="dashboard.php" class="topnav-brand">Dashboard</a>
  <div class="topnav-links">
    <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
    <a href="sitin_summary.php"><i class="fas fa-chart-bar"></i> Summary</a>
    <a href="history.php"><i class="fas fa-history"></i> History</a>
    <a href="lab_availability.php" class="active"><i class="fas fa-desktop"></i> Labs</a>
    <a href="reservation.php"><i class="fas fa-calendar-plus"></i> Reservation</a>
    <a href="edit_profile.php"><i class="fas fa-user-edit"></i> Profile</a>
    <button class="dark-toggle" onclick="toggleDark()" title="Toggle dark mode">
      <i class="fas fa-moon" id="darkIcon"></i>
    </button>
    <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Log out</a>
  </div>
</nav>

<div class="page-content">

  <!-- Header -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
      <h2 style="font-size:1.25rem;font-weight:700;color:var(--prussian);margin-bottom:4px;">
        <i class="fas fa-desktop" style="color:var(--cerulean);"></i> Lab Availability
      </h2>
      <p style="font-size:0.8rem;color:var(--text-muted);">Real-time PC availability and software installed in each lab</p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary btn-sm">
      <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
  </div>

  <!-- Legend -->
  <div class="legend">
    <div class="legend-item"><div class="legend-dot available"></div> Available</div>
    <div class="legend-item"><div class="legend-dot occupied"></div> Occupied</div>
    <div class="legend-item"><div class="legend-dot maintenance"></div> Maintenance</div>
    <div class="legend-item"><div class="legend-dot locked"></div> Locked</div>
  </div>

  <!-- Lab Tabs -->
  <div class="lab-tabs">
    <?php foreach ($labs as $lab):
      $st = $lab_data[$lab]['stats'];
    ?>
    <a href="?lab=<?= $lab ?>" class="lab-tab <?= $selected===$lab?'active':'' ?>">
      <i class="fas fa-desktop"></i> Lab <?= $lab ?>
      <span style="font-size:0.7rem;opacity:0.85;">
        (<?= $st['available'] ?>/<?= $st['total'] ?> free)
      </span>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Selected Lab Content -->
  <?php
  $ld = $lab_data[$selected];
  $st = $ld['stats'];
  $pct = $ld['pct'];
  $bar_color = $pct > 70 ? '#ef233c' : ($pct > 40 ? '#fb8500' : '#16a34a');
  ?>

  <!-- Mini stats + occupancy -->
  <div class="stats-mini">
    <div class="stat-mini-pill" style="border-color:#16a34a;color:#16a34a;">
      <i class="fas fa-circle" style="font-size:0.55rem;"></i> <?= $st['available'] ?> Available
    </div>
    <div class="stat-mini-pill" style="border-color:#ef233c;color:#ef233c;">
      <i class="fas fa-circle" style="font-size:0.55rem;"></i> <?= $st['occupied'] ?> Occupied
    </div>
    <div class="stat-mini-pill" style="border-color:#fb8500;color:#fb8500;">
      <i class="fas fa-tools" style="font-size:0.7rem;"></i> <?= $st['maintenance'] ?> Maintenance
    </div>
    <div class="stat-mini-pill" style="border-color:#8d99ae;color:#8d99ae;">
      <i class="fas fa-lock" style="font-size:0.7rem;"></i> <?= $st['locked'] ?> Locked
    </div>
    <div class="stat-mini-pill" style="border-color:var(--cerulean);color:var(--cerulean);">
      <i class="fas fa-desktop" style="font-size:0.7rem;"></i> <?= $st['total'] ?> Total PCs
    </div>
  </div>

  <!-- Occupancy bar -->
  <div class="occ-bar-wrap">
    <div style="display:flex;justify-content:space-between;font-size:0.77rem;margin-bottom:6px;color:var(--text-muted);">
      <span>Lab <?= $selected ?> Occupancy</span>
      <span style="font-weight:700;color:<?= $bar_color ?>"><?= $pct ?>%</span>
    </div>
    <div class="occ-bar-track">
      <div class="occ-bar-fill" style="width:<?= $pct ?>%;background:<?= $bar_color ?>;"></div>
    </div>
  </div>

  <!-- Two-column layout: PC Map | Software -->
  <div class="lab-layout">

    <!-- PC Grid -->
    <div class="card">
      <div class="card-header">
        <i class="fas fa-th"></i> Lab <?= $selected ?> — PC Map
        <span style="margin-left:8px;background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:0.75rem;">
          <?= count($ld['pcs']) ?> PCs
        </span>
      </div>
      <div class="card-body" style="padding:14px;">
        <?php if (empty($ld['pcs'])): ?>
        <p style="text-align:center;color:var(--text-muted);padding:30px;font-size:0.84rem;">
          <i class="fas fa-desktop" style="font-size:2rem;display:block;margin-bottom:10px;opacity:0.25;"></i>
          No PC data for this lab yet.
        </p>
        <?php else: ?>
        <div class="pc-grid-view">
          <?php foreach ($ld['pcs'] as $pc):
            $st_pc = $pc['status'];
            $icon  = match($st_pc) {
              'available'   => 'fa-desktop',
              'occupied'    => 'fa-user',
              'maintenance' => 'fa-tools',
              'locked'      => 'fa-lock',
              default       => 'fa-desktop'
            };
            $user = $pc['firstname'] ? htmlspecialchars($pc['firstname']) : '';
          ?>
          <div class="pc-tile <?= $st_pc ?>"
               title="PC <?= $pc['pc_number'] ?> — <?= ucfirst($st_pc) ?><?= $user ? ' — '.$user : '' ?>">
            <div class="pc-tile-icon <?= $st_pc ?>"><i class="fas <?= $icon ?>"></i></div>
            <div class="pc-tile-num"><?= $pc['pc_number'] ?></div>
            <?php if ($st_pc === 'occupied' && $user): ?>
            <div class="pc-tile-user"><?= $user ?></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Software sidebar -->
    <div class="card">
      <div class="card-header">
        <i class="fas fa-cube"></i> Software
        <span style="margin-left:8px;background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:12px;font-size:0.72rem;">
          Lab <?= $selected ?>
        </span>
      </div>
      <div class="card-body" style="padding:12px 16px;">
        <?php if (empty($ld['software'])): ?>
        <p style="text-align:center;color:var(--text-muted);padding:20px;font-size:0.82rem;">
          <i class="fas fa-cube" style="font-size:2rem;display:block;margin-bottom:8px;opacity:0.25;"></i>
          No software listed for this lab.
        </p>
        <?php else: ?>
        <?php foreach ($ld['software'] as $sw): ?>
        <div class="sw-item">
          <div class="sw-icon-wrap">
            <i class="<?= getSoftwareIcon($sw['name'], $icons) ?>"></i>
          </div>
          <div style="min-width:0;">
            <div class="sw-name"><?= htmlspecialchars($sw['name']) ?>
              <?php if ($sw['version']): ?>
              <span class="badge badge-secondary" style="font-size:0.62rem;margin-left:4px;"><?= htmlspecialchars($sw['version']) ?></span>
              <?php endif; ?>
            </div>
            <?php if ($sw['description']): ?>
            <div class="sw-version"><?= htmlspecialchars($sw['description']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
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
</script>
</body>
</html>
