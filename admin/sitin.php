<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

$error = $success = '';

if (isset($_POST['sit_in'])) {
  $student_id = (int)$_POST['student_id'];
  $id_number  = trim($_POST['id_number']);
  $name       = trim($_POST['student_name']);
  $purpose    = trim($_POST['purpose']);
  $lab        = trim($_POST['lab']);
  $session    = (int)$_POST['remaining_session'];

  if (!$purpose || !$lab) { $error = 'Purpose and Lab are required.'; }
  elseif ($session <= 0)  { $error = 'Student has no remaining sessions.'; }
  else {
    $chk = $conn->prepare("SELECT id FROM sitin_records WHERE student_id=? AND status='active'");
    $chk->bind_param('i', $student_id); $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
      $error = 'Student already has an active sit-in.';
    } else {
      $stmt = $conn->prepare("INSERT INTO sitin_records (student_id,id_number,student_name,purpose,lab,session) VALUES (?,?,?,?,?,?)");
      $stmt->bind_param('issssi', $student_id,$id_number,$name,$purpose,$lab,$session);
      $stmt->execute(); $stmt->close();
      $success = "Student {$name} sat-in successfully!";
    }
    $chk->close();
  }
}

if (isset($_GET['timeout'])) {
  $sid = (int)$_GET['timeout'];
  $row = $conn->query("SELECT student_id FROM sitin_records WHERE id=$sid AND status='active'")->fetch_assoc();
  if ($row) {
    $conn->query("UPDATE sitin_records SET status='done', time_out=NOW() WHERE id=$sid");
    $conn->query("UPDATE students SET remaining_session=remaining_session-1 WHERE id={$row['student_id']} AND remaining_session > 0");
  }
  header('Location: sitin.php?msg=timeout'); exit;
}

if (isset($_GET['msg']) && $_GET['msg'] === 'timeout') $success = 'Student timed out successfully.';

$sitins = $conn->query("SELECT * FROM sitin_records WHERE status='active' ORDER BY time_in DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Sit-in — CCS Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="page-wrapper">
  <div class="section-title"><i class="fas fa-desktop"></i> Current Sit-in</div>

  <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

  <div class="card">
    <div class="card-header">
      <i class="fas fa-list"></i> Active Sit-ins
      <span style="margin-left:8px;background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:0.78rem;"><?= count($sitins) ?> active</span>
      <a href="search.php" class="btn btn-gold btn-sm" style="margin-left:auto;"><i class="fas fa-search"></i> Search Student to Sit-in</a>
    </div>
    <div class="card-body">
      <div class="dt-top">
        <div class="dt-top-left">
          <div class="dt-entries">
            <select id="entrySel" onchange="renderTable()"><option value="10">10</option><option value="25">25</option></select>
            <span class="dt-label">entries per page</span>
          </div>
        </div>
        <div class="dt-top-right">
          <span class="dt-label">Search:</span>
          <div class="dt-search"><input type="text" id="searchBox" oninput="renderTable()" placeholder="Filter..."></div>
        </div>
      </div>
      <div class="dt-wrapper">
        <table class="data-table">
          <thead><tr><th>#</th><th>ID Number</th><th>Name</th><th>Purpose</th><th>Lab</th><th>Session</th><th>Time In</th><th>Status</th><th>Action</th></tr></thead>
          <tbody id="tableBody"></tbody>
        </table>
      </div>
      <div class="dt-pagination"><span id="showInfo"></span><div class="dt-pages" id="pages"></div></div>
    </div>
  </div>
</div>

<script>
const data = <?= json_encode($sitins) ?>;
let page = 1;
function renderTable() {
  const perPage  = parseInt(document.getElementById('entrySel').value);
  const search   = document.getElementById('searchBox').value.toLowerCase();
  const filtered = data.filter(r => Object.values(r).some(v => String(v).toLowerCase().includes(search)));
  const total    = filtered.length, totalPages = Math.max(1, Math.ceil(total / perPage));
  if (page > totalPages) page = totalPages;
  const start = (page - 1) * perPage, slice = filtered.slice(start, start + perPage);
  const tb = document.getElementById('tableBody');
  if (!slice.length) {
    tb.innerHTML = '<tr><td colspan="9" class="no-data">No active sit-ins right now.</td></tr>';
  } else {
    tb.innerHTML = slice.map((r, i) => `<tr>
      <td style="color:var(--text-muted);font-size:0.78rem;">${start + i + 1}</td>
      <td><strong>${r.id_number}</strong></td>
      <td>${r.student_name}</td>
      <td>${r.purpose}</td>
      <td><span class="badge badge-info">${r.lab}</span></td>
      <td><span class="badge badge-warning">${r.session}</span></td>
      <td style="font-size:0.78rem;color:var(--text-muted);">${r.time_in ? r.time_in.substring(11,16) : '—'}</td>
      <td><span class="badge badge-success">Active</span></td>
      <td>
        <a href="sitin.php?timeout=${r.id}" class="btn btn-warning btn-sm" onclick="return confirm('Timeout ${r.student_name}?')">
          <i class="fas fa-sign-out-alt"></i> Timeout
        </a>
      </td></tr>`).join('');
  }
  document.getElementById('showInfo').textContent = total ? `Showing ${start+1} to ${Math.min(start+perPage,total)} of ${total} entries` : 'No entries';
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
