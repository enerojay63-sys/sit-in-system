<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

// ── Build WHERE clause ──
$where = '1';
$from   = trim($_GET['from']   ?? '');
$to     = trim($_GET['to']     ?? '');
$lab    = trim($_GET['lab']    ?? '');
$status = trim($_GET['status'] ?? '');

if ($from)   $where .= " AND DATE(sr.time_in) >= '".$conn->real_escape_string($from)."'";
if ($to)     $where .= " AND DATE(sr.time_in) <= '".$conn->real_escape_string($to)."'";
if ($lab)    $where .= " AND sr.lab = '".$conn->real_escape_string($lab)."'";
if ($status) $where .= " AND sr.status = '".$conn->real_escape_string($status)."'";

// ── Get labs safely ──
$labs_res = $conn->query("SELECT DISTINCT lab FROM sitin_records ORDER BY lab");
$labs = [];
if ($labs_res) {
  while ($r = $labs_res->fetch_row()) $labs[] = $r[0];
}

// ── CSV Export ──
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  $rows = $conn->query("
    SELECT sr.id, sr.id_number, sr.student_name, sr.purpose, sr.lab, sr.pc_no,
           sr.session, sr.status, sr.time_in, sr.time_out,
           TIMESTAMPDIFF(MINUTE, sr.time_in, IFNULL(sr.time_out, NOW())) as duration_min,
           DATE(sr.time_in) as date
    FROM sitin_records sr
    WHERE $where
    ORDER BY sr.time_in DESC
  ")->fetch_all(MYSQLI_ASSOC);

  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="sitin_report_'.date('Ymd_His').'.csv"');
  $f = fopen('php://output', 'w');
  fputcsv($f, ['ID','ID Number','Student Name','Purpose','Lab','PC No','Session','Status','Time In','Time Out','Duration (min)','Date']);
  foreach ($rows as $r) {
    fputcsv($f, [
      $r['id'], $r['id_number'], $r['student_name'], $r['purpose'],
      $r['lab'], $r['pc_no'] ?? '—', $r['session'], $r['status'],
      $r['time_in'], $r['time_out'] ?? '—', $r['duration_min'], $r['date']
    ]);
  }
  fclose($f);
  exit;
}

// ── PDF Export ──
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
  $rows = $conn->query("
    SELECT sr.id, sr.id_number, sr.student_name, sr.purpose, sr.lab, sr.pc_no,
           sr.session, sr.status, sr.time_in, sr.time_out,
           DATE(sr.time_in) as date
    FROM sitin_records sr
    WHERE $where
    ORDER BY sr.time_in DESC
  ")->fetch_all(MYSQLI_ASSOC);

  $orientation = $_GET['orientation'] ?? 'portrait';
  $isLandscape = $orientation === 'landscape';

  $from_disp = $from ?: 'All';
  $to_disp   = $to   ?: 'All';
  $lab_disp  = $lab  ?: 'All Labs';
  $stat_disp = $status ? ucfirst($status) : 'All';

  ob_start();
  ?>
  <!DOCTYPE html>
  <html>
  <head>
    <meta charset="UTF-8">
    <title>Sit-in Report</title>
    <style>
      @page {
        <?= $isLandscape ? 'size: A4 landscape;' : 'size: A4 portrait;' ?>
        margin: 15mm;
      }
      body { font-family: Arial, sans-serif; font-size: 11px; color: #111; margin: 0; }
      .hdr { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; border-bottom: 2px solid #023047; padding-bottom: 10px; }
      .hdr-logos { display: flex; align-items: center; gap: 14px; }
      .hdr-logos img { width: 56px; height: 56px; object-fit: contain; }
      .hdr-title { text-align: center; flex: 1; }
      .hdr-title h2 { margin: 0; font-size: 16px; color: #023047; letter-spacing: 1px; }
      .hdr-title p  { margin: 3px 0 0; font-size: 11px; color: #666; }
      .meta { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 12px; font-size: 10px; color: #444; background: #f5f8fb; padding: 8px 12px; border-radius: 6px; }
      .meta span strong { color: #023047; }
      table { width: 100%; border-collapse: collapse; font-size: 10px; }
      thead tr { background: #023047; color: #fff; }
      thead th { padding: 7px 8px; text-align: left; }
      tbody tr:nth-child(even) { background: #f0f4f8; }
      tbody td { padding: 6px 8px; border-bottom: 1px solid #e0e6ef; vertical-align: middle; }
      .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 9px; font-weight: 700; }
      .badge-active { background: #dcfce7; color: #14532d; }
      .badge-done   { background: #e0f2fe; color: #023047; }
      .footer { margin-top: 16px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #e0e6ef; padding-top: 8px; }
      @media print { body { -webkit-print-color-adjust: exact; } }
    </style>
  </head>
  <body>
    <div class="hdr">
      <div class="hdr-logos">
        <img src="../images/uc_logo.png" alt="UC">
        <img src="../images/ccs_logo.png" alt="CCS">
      </div>
      <div class="hdr-title">
        <h2>UCMAIN</h2>
        <p style="font-weight:700;font-size:13px;">Sit-in Report</p>
        <p>Generated: <?= date('Y-m-d H:i:s') ?></p>
      </div>
      <div style="width:130px;"></div>
    </div>
    <div class="meta">
      <span><strong>From:</strong> <?= htmlspecialchars($from_disp) ?></span>
      <span><strong>To:</strong> <?= htmlspecialchars($to_disp) ?></span>
      <span><strong>Lab:</strong> <?= htmlspecialchars($lab_disp) ?></span>
      <span><strong>Status:</strong> <?= htmlspecialchars($stat_disp) ?></span>
      <span><strong>Total Records:</strong> <?= count($rows) ?></span>
    </div>
    <table>
      <thead>
        <tr>
          <th>ID</th><th>ID No.</th><th>Name</th><th>Purpose</th>
          <th>Lab</th><th>PC</th><th>Sess</th><th>Time In</th><th>Time Out</th><th>Status</th><th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= $r['id'] ?></td>
          <td><?= htmlspecialchars($r['id_number']) ?></td>
          <td><?= htmlspecialchars($r['student_name']) ?></td>
          <td><?= htmlspecialchars($r['purpose']) ?></td>
          <td><?= htmlspecialchars($r['lab']) ?></td>
          <td><?= $r['pc_no'] ?? '—' ?></td>
          <td><?= $r['session'] ?></td>
          <td><?= $r['time_in'] ? date('Y-m-d H:i', strtotime($r['time_in'])) : '—' ?></td>
          <td><?= $r['time_out'] ? date('Y-m-d H:i', strtotime($r['time_out'])) : ($r['status']==='active' ? 'Active' : '—') ?></td>
          <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
          <td><?= $r['date'] ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
        <tr><td colspan="11" style="text-align:center;padding:20px;color:#999;">No records found for the selected filters.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <div class="footer">
      University of Cebu — College of Computer Studies &nbsp;|&nbsp; CCS Sit-in Monitoring System
    </div>
    <script>window.onload = function(){ window.print(); }</script>
  </body>
  </html>
  <?php
  $html = ob_get_clean();
  echo $html;
  exit;
}

// ── Main page data ──
$rows = $conn->query("
  SELECT sr.id, sr.id_number, sr.student_name, sr.purpose, sr.lab, sr.pc_no,
         sr.session, sr.status, sr.time_in, sr.time_out,
         TIMESTAMPDIFF(MINUTE, sr.time_in, IFNULL(sr.time_out, NOW())) as duration_min,
         DATE(sr.time_in) as date
  FROM sitin_records sr
  WHERE $where
  ORDER BY sr.time_in DESC
")->fetch_all(MYSQLI_ASSOC);

$total  = count($rows);
$done   = count(array_filter($rows, fn($r) => $r['status'] === 'done'));
$active = count(array_filter($rows, fn($r) => $r['status'] === 'active'));

// Build current query string for export links (excluding 'export' param)
$export_params = array_filter($_GET, fn($k) => $k !== 'export' && $k !== 'orientation', ARRAY_FILTER_USE_KEY);
$export_qs = http_build_query($export_params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Generate Reports — CCS Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    .filter-card { background:var(--surface); border-radius:var(--radius); padding:20px 24px; box-shadow:var(--shadow-md); border:1px solid rgba(141,153,174,0.2); margin-bottom:22px; }
    html.dark .filter-card { background:#1a1d27; border-color:rgba(255,255,255,0.07); }
    .filter-title { font-size:0.88rem; font-weight:700; color:var(--prussian); margin-bottom:14px; display:flex; align-items:center; gap:8px; }
    html.dark .filter-title { color:#edf2f4; }
    .filter-row { display:flex; gap:14px; flex-wrap:wrap; align-items:flex-end; }
    .filter-group { display:flex; flex-direction:column; gap:6px; min-width:140px; }
    .filter-group label { font-size:0.73rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px; }
    .export-row { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-top:16px; padding-top:16px; border-top:1px solid var(--border); }
    html.dark .export-row { border-color:rgba(255,255,255,0.07); }
    .orient-label { font-size:0.8rem; font-weight:600; color:var(--text-muted); margin-right:6px; }
    .orient-btn {
      padding:6px 14px; border-radius:6px; border:1.5px solid var(--border);
      background:var(--alice); color:var(--text-muted); font-size:0.78rem; font-weight:600;
      cursor:pointer; transition:all 0.18s; display:inline-flex; align-items:center; gap:5px;
    }
    .orient-btn.active, .orient-btn:hover { border-color:var(--cerulean); color:var(--prussian); background:rgba(33,158,188,0.1); }
    html.dark .orient-btn { background:#1a1d27; border-color:#3d4060; color:#8d99ae; }
    html.dark .orient-btn.active { border-color:var(--cerulean); color:#edf2f4; background:rgba(33,158,188,0.15); }
    .stat-pills { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
    .stat-pill { display:flex; align-items:center; gap:7px; padding:8px 16px; border-radius:20px; font-size:0.8rem; font-weight:700; border:1.5px solid; background:var(--surface); }
    html.dark .stat-pill { background:#1a1d27; }
  </style>
</head>
<body>
<?php include 'nav.php'; ?>

<div class="page-wrapper">
  <div class="section-title"><i class="fas fa-file-alt"></i> Generate Reports</div>

  <!-- Filter Card -->
  <div class="filter-card">
    <div class="filter-title"><i class="fas fa-filter"></i> Filter Sit-in Records</div>
    <form method="GET" id="filterForm">
      <div class="filter-row">
        <div class="filter-group">
          <label>From Date</label>
          <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>" style="min-width:150px;">
        </div>
        <div class="filter-group">
          <label>To Date</label>
          <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>" style="min-width:150px;">
        </div>
        <div class="filter-group">
          <label>Lab</label>
          <select name="lab" class="form-control" style="min-width:130px;">
            <option value="">All Labs</option>
            <?php foreach ($labs as $l): ?>
            <option value="<?= htmlspecialchars($l) ?>" <?= $lab === $l ? 'selected' : '' ?>>Lab <?= htmlspecialchars($l) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-group">
          <label>Status</label>
          <select name="status" class="form-control" style="min-width:120px;">
            <option value="">All</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="done"   <?= $status === 'done'   ? 'selected' : '' ?>>Done</option>
          </select>
        </div>
        <div class="filter-group" style="justify-content:flex-end;">
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Apply Filters</button>
        </div>
        <div class="filter-group" style="justify-content:flex-end;">
          <a href="generate_report.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Reset</a>
        </div>
      </div>

      <!-- Export Row -->
      <div class="export-row">
        <a href="generate_report.php?<?= $export_qs ? $export_qs.'&' : '' ?>export=csv" class="btn btn-success btn-sm">
          <i class="fas fa-file-csv"></i> Export to CSV
        </a>
        <span class="orient-label">PDF Orientation:</span>
        <button type="button" class="orient-btn active" id="btnPortrait" onclick="setOrient('portrait')">
          <i class="fas fa-file-alt"></i> Portrait
        </button>
        <button type="button" class="orient-btn" id="btnLandscape" onclick="setOrient('landscape')">
          <i class="fas fa-file-alt fa-rotate-90"></i> Landscape
        </button>
        <a id="pdfBtn" href="generate_report.php?<?= $export_qs ? $export_qs.'&' : '' ?>export=pdf&orientation=portrait"
           class="btn btn-danger btn-sm" target="_blank">
          <i class="fas fa-file-pdf"></i> Export to PDF
        </a>
      </div>
    </form>
  </div>

  <!-- Stats Pills -->
  <div class="stat-pills">
    <div class="stat-pill" style="border-color:var(--cerulean);color:var(--cerulean);">
      <i class="fas fa-list" style="font-size:0.7rem;"></i> <?= $total ?> Total Records
    </div>
    <div class="stat-pill" style="border-color:#16a34a;color:#16a34a;">
      <i class="fas fa-check-circle" style="font-size:0.7rem;"></i> <?= $done ?> Done
    </div>
    <div class="stat-pill" style="border-color:#ef233c;color:#ef233c;">
      <i class="fas fa-circle" style="font-size:0.7rem;"></i> <?= $active ?> Active
    </div>
  </div>

  <!-- Table -->
  <div class="card">
    <div class="card-header">
      <i class="fas fa-table"></i> Sit-in Records
      <span style="margin-left:8px;background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:0.75rem;"><?= $total ?> records</span>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="dt-top" style="padding:16px 16px 0;">
        <div class="dt-top-left">
          <div class="dt-entries">
            <select id="entrySel" onchange="renderTable()">
              <option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option>
            </select>
            <span class="dt-label">entries per page</span>
          </div>
        </div>
        <div class="dt-top-right">
          <span class="dt-label">Search:</span>
          <div class="dt-search"><input type="text" id="searchBox" oninput="renderTable()" placeholder="Search records..."></div>
        </div>
      </div>
      <div class="dt-wrapper">
        <table class="data-table">
          <thead>
            <tr><th>ID</th><th>ID Number</th><th>Name</th><th>Purpose</th><th>Lab</th><th>PC</th><th>Session</th><th>Time In</th><th>Time Out</th><th>Status</th><th>Date</th></tr>
          </thead>
          <tbody id="tableBody"></tbody>
        </table>
      </div>
      <div class="dt-pagination" style="padding:0 16px 16px;">
        <span id="showInfo"></span>
        <div class="dt-pages" id="pages"></div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){ const t=localStorage.getItem('theme')||'light'; if(t==='dark') document.documentElement.classList.add('dark'); })();

const data = <?= json_encode($rows) ?>;
let page = 1;
let orientation = 'portrait';

function setOrient(o) {
  orientation = o;
  document.getElementById('btnPortrait').classList.toggle('active', o === 'portrait');
  document.getElementById('btnLandscape').classList.toggle('active', o === 'landscape');
  const params = new URLSearchParams(window.location.search);
  params.set('export', 'pdf');
  params.set('orientation', o);
  document.getElementById('pdfBtn').href = 'generate_report.php?' + params.toString();
}

function renderTable() {
  const perPage  = parseInt(document.getElementById('entrySel').value);
  const search   = document.getElementById('searchBox').value.toLowerCase();
  const filtered = data.filter(r => Object.values(r).some(v => String(v).toLowerCase().includes(search)));
  const total    = filtered.length, totalPages = Math.max(1, Math.ceil(total / perPage));
  if (page > totalPages) page = totalPages;
  const start = (page - 1) * perPage, slice = filtered.slice(start, start + perPage);
  const tb = document.getElementById('tableBody');

  if (!slice.length) {
    tb.innerHTML = '<tr><td colspan="11" class="no-data"><i class="fas fa-search" style="font-size:2rem;display:block;margin-bottom:10px;opacity:0.25;"></i>No records match your filters.</td></tr>';
  } else {
    tb.innerHTML = slice.map(r => `<tr>
      <td style="font-size:0.78rem;color:var(--text-muted);">${r.id}</td>
      <td><strong>${r.id_number}</strong></td>
      <td style="font-size:0.83rem;">${r.student_name}</td>
      <td style="font-size:0.82rem;">${r.purpose}</td>
      <td><span class="badge badge-info">${r.lab}</span></td>
      <td style="font-size:0.8rem;">${r.pc_no ?? '—'}</td>
      <td style="font-size:0.8rem;text-align:center;">${r.session}</td>
      <td style="font-size:0.78rem;color:var(--text-muted);">${r.time_in ? r.time_in.substring(0,16) : '—'}</td>
      <td style="font-size:0.78rem;color:var(--text-muted);">${r.time_out ? r.time_out.substring(0,16) : (r.status === 'active' ? '<span class="badge badge-success">Active</span>' : '—')}</td>
      <td><span class="badge badge-${r.status === 'active' ? 'success' : 'secondary'}">${r.status}</span></td>
      <td style="font-size:0.78rem;">${r.date ?? '—'}</td>
    </tr>`).join('');
  }

  document.getElementById('showInfo').textContent = total
    ? `Showing ${start + 1} to ${Math.min(start + perPage, total)} of ${total} entries`
    : 'No entries found';

  document.getElementById('pages').innerHTML = `
    <button onclick="goPage(1)" ${page===1?'disabled':''}>«</button>
    <button onclick="goPage(${page-1})" ${page===1?'disabled':''}>‹</button>
    <button class="active">${page}</button>
    <button onclick="goPage(${page+1})" ${page===totalPages?'disabled':''}>›</button>
    <button onclick="goPage(${totalPages})" ${page===totalPages?'disabled':''}>»</button>`;
}

function goPage(p) { page = p; renderTable(); }
renderTable();
</script>
</body>
</html>