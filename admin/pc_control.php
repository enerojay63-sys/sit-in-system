<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

$success = $error = '';

// Handle PC status update
if (isset($_POST['update_pc'])) {
  $pc_id  = (int)$_POST['pc_id'];
  $status = $_POST['status'];
  $allowed = ['available','occupied','maintenance','locked'];
  if (in_array($status, $allowed)) {
    $conn->query("UPDATE pc_control SET status='$status' WHERE id=$pc_id");
    if ($status === 'available') {
      $conn->query("UPDATE pc_control SET student_id=NULL WHERE id=$pc_id");
    }
    $success = 'PC status updated.';
  }
}

// Handle bulk action
if (isset($_POST['bulk_action'])) {
  $lab    = $conn->real_escape_string($_POST['bulk_lab']);
  $action = $_POST['bulk_action'];
  $allowed = ['available','maintenance','locked'];
  if (in_array($action, $allowed)) {
    $conn->query("UPDATE pc_control SET status='$action' WHERE lab='$lab'");
    if ($action === 'available') {
      $conn->query("UPDATE pc_control SET student_id=NULL WHERE lab='$lab'");
    }
    $success = "All PCs in Lab $lab set to $action.";
  }
}

// Get selected lab
$selected_lab = $_GET['lab'] ?? '524';
$selected_lab = $conn->real_escape_string($selected_lab);

// Get all labs
$labs_res = $conn->query("SELECT DISTINCT lab FROM pc_control ORDER BY lab");
$labs = [];
while($r = $labs_res->fetch_row()) $labs[] = $r[0];
if (empty($labs)) $labs = ['524','526','528'];

// Get PCs for selected lab — uses pc_number column (fixed from pc_no)
$pcs = $conn->query("
  SELECT p.*, s.firstname, s.lastname, s.id_number,
         sr.purpose, sr.time_in
  FROM pc_control p
  LEFT JOIN students s ON p.student_id = s.id
  LEFT JOIN sitin_records sr ON sr.student_id = p.student_id AND sr.status='active'
  WHERE p.lab='$selected_lab'
  ORDER BY p.pc_number
")->fetch_all(MYSQLI_ASSOC);

// Stats
$stats = ['available'=>0,'occupied'=>0,'maintenance'=>0,'locked'=>0];
foreach ($pcs as $pc) {
  if (isset($stats[$pc['status']])) $stats[$pc['status']]++;
}

// Recent activity logs
$all_logs = $conn->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 30")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>PC Control — CCS</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    /* ── PC Grid: 10 columns for 50 PCs ── */
    .pc-grid-map {
      display: grid;
      grid-template-columns: repeat(10, 1fr);
      gap: 7px;
    }
    @media(max-width:1100px){ .pc-grid-map { grid-template-columns: repeat(7,1fr); } }
    @media(max-width:700px){  .pc-grid-map { grid-template-columns: repeat(5,1fr); } }

    .pc-tile {
      border: 2px solid var(--border);
      border-radius: 8px;
      padding: 7px 5px 6px;
      text-align: center;
      cursor: pointer;
      transition: all 0.18s;
      background: var(--surface);
      position: relative;
      user-select: none;
    }
    .pc-tile:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }

    /* Status colours */
    .pc-tile.available   { border-color: #16a34a; background: #f0fdf4; }
    .pc-tile.occupied    { border-color: #ef233c; background: #fff1f2; }
    .pc-tile.maintenance { border-color: #fb8500; background: #fff7ed; }
    .pc-tile.locked      { border-color: #8d99ae; background: #f8fafc; opacity: 0.65; }

    html.dark .pc-tile             { background: #1e2130; border-color: #3d4060; }
    html.dark .pc-tile.available   { background: rgba(22,163,74,.15);   border-color: #16a34a; }
    html.dark .pc-tile.occupied    { background: rgba(239,35,60,.15);   border-color: #ef233c; }
    html.dark .pc-tile.maintenance { background: rgba(251,133,0,.15);   border-color: #fb8500; }
    html.dark .pc-tile.locked      { background: rgba(141,153,174,.1);  border-color: #8d99ae; }

    .pc-icon { font-size: 1rem; margin-bottom: 1px; }
    .pc-icon.available   { color: #16a34a; }
    .pc-icon.occupied    { color: #ef233c; }
    .pc-icon.maintenance { color: #fb8500; }
    .pc-icon.locked      { color: #8d99ae; }

    .pc-num  { font-weight: 700; font-size: 0.72rem; color: var(--prussian); }
    html.dark .pc-num { color: #c8d0dc; }
    .pc-user { font-size: 0.58rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* Modal */
    .pc-modal { display:none; position:fixed; inset:0; background:rgba(2,48,71,0.55);
                backdrop-filter:blur(4px); z-index:3000; align-items:center; justify-content:center; }
    .pc-modal.open { display:flex; }
    .pc-modal-box  { background:var(--surface); border-radius:12px; width:90%; max-width:360px;
                     box-shadow:var(--shadow-lg); overflow:hidden; animation:modalIn .2s ease; }

    /* Lab tabs */
    .lab-tabs { display:flex; gap:6px; margin-bottom:18px; flex-wrap:wrap; }
    .lab-tab  { padding:8px 20px; border-radius:20px; border:2px solid var(--border);
                background:var(--surface); color:var(--text-muted); font-weight:600;
                font-size:0.82rem; cursor:pointer; text-decoration:none; transition:all .18s; }
    .lab-tab:hover  { border-color:var(--cerulean); color:var(--cerulean); }
    .lab-tab.active { background:var(--prussian); color:#fff; border-color:var(--prussian); }

    /* Legend */
    .legend { display:flex; gap:14px; flex-wrap:wrap; margin-bottom:14px;
              padding:10px 16px; background:var(--alice); border-radius:8px; border:1px solid var(--border); }
    .legend-item { display:flex; align-items:center; gap:6px; font-size:0.78rem; font-weight:600; }
    .legend-dot  { width:12px; height:12px; border-radius:3px; }
    .legend-dot.available   { background:#16a34a; }
    .legend-dot.occupied    { background:#ef233c; }
    .legend-dot.maintenance { background:#fb8500; }
    .legend-dot.locked      { background:#8d99ae; }

    .stats-row { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
    .stat-mini { background:var(--surface); border:1.5px solid var(--border); border-radius:8px;
                 padding:8px 14px; display:flex; align-items:center; gap:7px;
                 font-size:0.8rem; font-weight:600; }

    .page-layout { display:grid; grid-template-columns:1fr 270px; gap:18px; align-items:start; }
    @media(max-width:900px){ .page-layout { grid-template-columns:1fr; } }
  </style>
</head>
<body>
<?php include 'nav.php'; ?>

<div class="page-wrapper">
  <div class="section-title" style="margin-bottom:14px"><i class="fas fa-tv"></i> PC Control</div>

  <?php if($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($success)?></div><?php endif; ?>
  <?php if($error):   ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($error)?></div><?php endif; ?>

  <!-- Instructions / Legend always visible at top -->
  <div class="alert alert-info" style="margin-bottom:14px">
    <i class="fas fa-info-circle"></i>
    <span><strong>How to use:</strong> Click any PC tile to change its status. Use <strong>Bulk Actions</strong> to set all PCs in a lab at once.</span>
  </div>

  <div class="legend">
    <div class="legend-item"><div class="legend-dot available"></div> Available</div>
    <div class="legend-item"><div class="legend-dot occupied"></div> Occupied (student seated)</div>
    <div class="legend-item"><div class="legend-dot maintenance"></div> Under Maintenance</div>
    <div class="legend-item"><div class="legend-dot locked"></div> Locked / Disabled</div>
  </div>

  <!-- Lab tabs -->
  <div class="lab-tabs">
    <?php foreach($labs as $lab): ?>
      <?php
        $c = $conn->real_escape_string($lab);
        $avail_cnt = (int)$conn->query("SELECT COUNT(*) FROM pc_control WHERE lab='$c' AND status='available'")->fetch_row()[0];
        $total_cnt = (int)$conn->query("SELECT COUNT(*) FROM pc_control WHERE lab='$c'")->fetch_row()[0];
      ?>
      <a href="?lab=<?=$lab?>" class="lab-tab <?=$selected_lab===$lab?'active':''?>">
        <i class="fas fa-desktop"></i> Lab <?=$lab?>
        <span style="font-size:0.7rem;opacity:.8">(<?=$avail_cnt?>/<?=$total_cnt?> free)</span>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="page-layout">
    <!-- LEFT: PC Map -->
    <div>
      <!-- Mini stats -->
      <div class="stats-row">
        <div class="stat-mini" style="border-color:#16a34a"><i class="fas fa-circle" style="color:#16a34a;font-size:.6rem"></i> <?=$stats['available']?> Available</div>
        <div class="stat-mini" style="border-color:#ef233c"><i class="fas fa-circle" style="color:#ef233c;font-size:.6rem"></i> <?=$stats['occupied']?> Occupied</div>
        <div class="stat-mini" style="border-color:#fb8500"><i class="fas fa-circle" style="color:#fb8500;font-size:.6rem"></i> <?=$stats['maintenance']?> Maintenance</div>
        <div class="stat-mini" style="border-color:#8d99ae"><i class="fas fa-circle" style="color:#8d99ae;font-size:.6rem"></i> <?=$stats['locked']?> Locked</div>
        <div class="stat-mini" style="border-color:var(--cerulean)"><i class="fas fa-desktop" style="color:var(--cerulean);font-size:.7rem"></i> <?=count($pcs)?> Total PCs</div>
      </div>

      <!-- Bulk Actions -->
      <div class="card mb-2">
        <div class="card-header"><i class="fas fa-layer-group"></i> Bulk Actions — Lab <?=$selected_lab?></div>
        <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;padding:12px 16px">
          <form method="POST" style="display:contents">
            <input type="hidden" name="bulk_lab" value="<?=$selected_lab?>">
            <button type="submit" name="bulk_action" value="available"
              class="btn btn-success btn-sm"
              onclick="return confirm('Set ALL PCs in Lab <?=$selected_lab?> to Available?')">
              <i class="fas fa-check-circle"></i> All Available
            </button>
            <button type="submit" name="bulk_action" value="maintenance"
              class="btn btn-gold btn-sm"
              onclick="return confirm('Set ALL PCs in Lab <?=$selected_lab?> to Maintenance?')">
              <i class="fas fa-tools"></i> All Maintenance
            </button>
            <button type="submit" name="bulk_action" value="locked"
              class="btn btn-secondary btn-sm"
              onclick="return confirm('Lock ALL PCs in Lab <?=$selected_lab?>?')">
              <i class="fas fa-lock"></i> Lock All
            </button>
          </form>
        </div>
      </div>

      <!-- PC Grid -->
      <div class="card">
        <div class="card-header"><i class="fas fa-th"></i> Lab <?=$selected_lab?> — <?=count($pcs)?> PCs</div>
        <div class="card-body" style="padding:14px">
          <?php if(empty($pcs)): ?>
            <p class="text-muted text-center" style="padding:20px">No PCs found for Lab <?=$selected_lab?>. Run the SQL schema to populate them.</p>
          <?php else: ?>
          <div class="pc-grid-map">
            <?php foreach($pcs as $pc): ?>
              <?php
                $st = $pc['status'];
                $icon = match($st) {
                  'available'   => 'fa-desktop',
                  'occupied'    => 'fa-user',
                  'maintenance' => 'fa-tools',
                  'locked'      => 'fa-lock',
                  default       => 'fa-desktop'
                };
                $student_display = $pc['firstname'] ? htmlspecialchars($pc['firstname'].' '.$pc['lastname'], ENT_QUOTES) : '';
              ?>
              <div class="pc-tile <?=$st?>"
                   title="PC <?=$pc['pc_number']?> — <?=ucfirst($st)?><?=$student_display?' — '.$student_display:''?>"
                   onclick="openPcModal(<?=$pc['id']?>, <?=$pc['pc_number']?>, '<?=$st?>', '<?=$student_display?>')">
                <div class="pc-icon <?=$st?>"><i class="fas <?=$icon?>"></i></div>
                <div class="pc-num">PC <?=$pc['pc_number']?></div>
                <?php if($st==='occupied' && $pc['firstname']): ?>
                  <div class="pc-user"><?=htmlspecialchars($pc['firstname'])?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- RIGHT: Activity Log -->
    <div>
      <div class="card">
        <div class="card-header"><i class="fas fa-clipboard-list"></i> Recent Activity</div>
        <div style="max-height:580px;overflow-y:auto">
          <?php if($all_logs): ?>
            <?php foreach($all_logs as $log): ?>
              <div style="padding:10px 14px;border-bottom:1px solid var(--border);font-size:0.78rem">
                <div style="font-weight:600;color:var(--prussian)"><?=htmlspecialchars($log['actor'])?></div>
                <div style="color:var(--text-soft);margin:2px 0"><?=htmlspecialchars($log['action'])?></div>
                <div style="color:var(--text-muted);font-size:0.7rem"><?=date('M d, h:i A',strtotime($log['created_at']))?></div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p style="text-align:center;color:var(--text-muted);padding:20px;font-size:0.82rem">No logs yet.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- PC Status Modal -->
<div class="pc-modal" id="pcModal">
  <div class="pc-modal-box">
    <div class="modal-header">
      <span><i class="fas fa-desktop"></i> PC <span id="modalPcNum"></span> — Lab <?=$selected_lab?></span>
      <button class="modal-close" onclick="closePcModal()">×</button>
    </div>
    <div class="modal-body">
      <div id="modalStudentInfo" style="margin-bottom:14px;padding:10px 12px;background:var(--alice);border-radius:8px;font-size:0.82rem;display:none">
        <i class="fas fa-user" style="color:var(--cerulean)"></i> <span id="modalStudentName"></span>
      </div>
      <form method="POST">
        <input type="hidden" name="pc_id" id="modalPcId">
        <div class="form-group">
          <label>Set Status</label>
          <select name="status" id="modalStatus" class="form-control">
            <option value="available">✅ Available</option>
            <option value="occupied">🔴 Occupied</option>
            <option value="maintenance">🔧 Maintenance</option>
            <option value="locked">🔒 Locked</option>
          </select>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end">
          <button type="button" class="btn btn-secondary btn-sm" onclick="closePcModal()">Cancel</button>
          <button type="submit" name="update_pc" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){ const t=localStorage.getItem('theme')||'light'; if(t==='dark') document.documentElement.classList.add('dark'); })();

function openPcModal(id, num, status, student) {
  document.getElementById('modalPcId').value   = id;
  document.getElementById('modalPcNum').textContent = num;
  document.getElementById('modalStatus').value = status;
  const si = document.getElementById('modalStudentInfo');
  if (student && student.trim()) {
    document.getElementById('modalStudentName').textContent = student;
    si.style.display = 'block';
  } else {
    si.style.display = 'none';
  }
  document.getElementById('pcModal').classList.add('open');
}
function closePcModal() {
  document.getElementById('pcModal').classList.remove('open');
}
</script>
</body>
</html>
