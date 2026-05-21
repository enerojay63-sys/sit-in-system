<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

$success = $error = '';

// Approve / Reject
if (isset($_GET['approve'])) {
  $id = (int)$_GET['approve'];
  $conn->query("UPDATE reservations SET status='approved' WHERE id=$id");
  $success = 'Reservation approved.';
}
if (isset($_GET['reject'])) {
  $id = (int)$_GET['reject'];
  $conn->query("UPDATE reservations SET status='rejected' WHERE id=$id");
  $success = 'Reservation rejected.';
}

// FIXED: select student name from joined students table, not from reservations directly
$reservations = $conn->query("
  SELECT r.*,
         s.course, s.year_level,
         CONCAT(s.firstname,' ',s.lastname) AS student_fullname,
         s.id_number AS student_id_number
  FROM reservations r
  JOIN students s ON r.student_id = s.id
  ORDER BY r.created_at DESC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Reservations — CCS Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'nav.php'; ?>

<div class="page-wrapper">
  <div class="section-title"><i class="fas fa-calendar"></i> Reservations</div>

  <?php if($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

  <div class="card">
    <div class="card-header"><i class="fas fa-calendar"></i> All Reservations</div>
    <div class="card-body">
      <div class="dt-top">
        <div class="dt-top-left">
          <div class="dt-entries">
            <select id="entrySel" onchange="renderTable()">
              <option value="10">10</option><option value="25">25</option><option value="50">50</option>
            </select>
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
            <tr><th>ID Number</th><th>Name</th><th>Course</th><th>Purpose</th><th>Lab</th><th>Date</th><th>Time</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody id="tableBody"></tbody>
        </table>
      </div>
      <div class="dt-pagination"><span id="showInfo"></span><div class="dt-pages" id="pages"></div></div>
    </div>
  </div>
</div>

<script>
(function(){ const t=localStorage.getItem('theme')||'light'; if(t==='dark') document.documentElement.classList.add('dark'); })();

// Use fixed field names
const data = <?= json_encode($reservations) ?>;
let page = 1;
function renderTable() {
  const perPage = parseInt(document.getElementById('entrySel').value);
  const search  = document.getElementById('searchBox').value.toLowerCase();
  const filtered = data.filter(r => Object.values(r).some(v => String(v).toLowerCase().includes(search)));
  const total = filtered.length, totalPages = Math.max(1, Math.ceil(total / perPage));
  if (page > totalPages) page = totalPages;
  const start = (page - 1) * perPage, slice = filtered.slice(start, start + perPage);
  const tb = document.getElementById('tableBody');
  if (!slice.length) {
    tb.innerHTML = '<tr><td colspan="9" class="no-data">No reservations found</td></tr>';
  } else {
    tb.innerHTML = slice.map(r => {
      const badge = r.status === 'approved' ? 'badge-success' : r.status === 'rejected' ? 'badge-danger' : 'badge-warning';
      const actions = r.status === 'pending'
        ? `<a href="reservation_admin.php?approve=${r.id}" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve</a>
           <a href="reservation_admin.php?reject=${r.id}" class="btn btn-danger btn-sm" onclick="return confirm('Reject?')"><i class="fas fa-times"></i> Reject</a>`
        : `<span style="font-size:0.78rem;color:var(--text-muted)">—</span>`;
      return `<tr>
        <td>${r.student_id_number ?? r.id_number ?? '—'}</td>
        <td>${r.student_fullname ?? r.student_name ?? '—'}</td>
        <td>${r.course}</td>
        <td>${r.purpose}</td>
        <td>${r.lab}</td>
        <td>${r.date}</td>
        <td>${r.time_in}</td>
        <td><span class="badge ${badge}">${r.status}</span></td>
        <td style="white-space:nowrap;">${actions}</td>
      </tr>`;
    }).join('');
  }
  document.getElementById('showInfo').textContent = total
    ? `Showing ${start + 1} to ${Math.min(start + perPage, total)} of ${total} entries` : 'Showing 0 entries';
  const pages = document.getElementById('pages');
  pages.innerHTML = `
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
