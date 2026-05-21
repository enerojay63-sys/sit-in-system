<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

// ── Clear logs ──
if (isset($_GET['clear']) && $_GET['clear']==='all') {
  $admin_name = $_SESSION['admin_data']['name'] ?? 'CCS Admin';
  $conn->query("DELETE FROM activity_logs");
  $conn->query("INSERT INTO activity_logs (actor,action) VALUES ('$admin_name','Cleared all activity logs')");
  header('Location: logs.php?cleared=1'); exit;
}

// ── Filters ──
$where = '1';
$actor_filter = trim($_GET['actor'] ?? '');
$date_filter  = trim($_GET['date'] ?? '');
$q_filter     = trim($_GET['q'] ?? '');

if ($actor_filter) $where .= " AND actor LIKE '%".($conn->real_escape_string($actor_filter))."%'";
if ($date_filter)  $where .= " AND DATE(created_at) = '".$conn->real_escape_string($date_filter)."'";
if ($q_filter)     $where .= " AND (action LIKE '%".($conn->real_escape_string($q_filter))."%' OR target LIKE '%".($conn->real_escape_string($q_filter))."%')";

$logs = $conn->query("SELECT * FROM activity_logs WHERE $where ORDER BY created_at DESC LIMIT 200")->fetch_all(MYSQLI_ASSOC);
$total = $conn->query("SELECT COUNT(*) FROM activity_logs")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Activity Logs — CCS Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'nav.php'; ?>

<div class="page-wrapper">
  <div class="d-flex align-center justify-between mb-2">
    <div class="section-title" style="margin-bottom:0"><i class="fas fa-clipboard-list"></i> Activity Logs</div>
    <div class="d-flex gap-1">
      <span class="badge badge-secondary" style="padding:6px 12px"><i class="fas fa-database"></i> <?= $total ?> total entries</span>
      <a href="?clear=all" class="btn btn-danger btn-sm"
         onclick="return confirm('Clear all logs? This cannot be undone.')">
        <i class="fas fa-trash"></i> Clear All
      </a>
    </div>
  </div>

  <?php if(isset($_GET['cleared'])): ?>
    <div class="alert alert-info"><i class="fas fa-info-circle"></i> Logs cleared.</div>
  <?php endif; ?>

  <!-- Filters -->
  <div class="card mb-2">
    <div class="card-body" style="padding:14px">
      <form method="GET" class="d-flex gap-1 flex-wrap align-center">
        <input type="text" name="actor" class="form-control" style="width:180px" placeholder="Filter by actor..." value="<?= htmlspecialchars($actor_filter) ?>">
        <input type="text" name="q"    class="form-control" style="width:220px" placeholder="Search action/target..." value="<?= htmlspecialchars($q_filter) ?>">
        <input type="date" name="date" class="form-control" style="width:160px" value="<?= htmlspecialchars($date_filter) ?>">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
        <a href="logs.php" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
      </form>
    </div>
  </div>

  <!-- Logs Table -->
  <div class="card">
    <div class="table-wrap" style="padding:0">
      <table>
        <thead>
          <tr>
            <th style="width:160px">Date & Time</th>
            <th style="width:140px">Actor</th>
            <th>Action</th>
            <th style="width:140px">Target</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($logs as $log): ?>
            <?php
              $action = strtolower($log['action']);
              $iconClass = 'fas fa-circle';
              $badgeClass = 'badge-secondary';
              if (str_contains($action,'login'))    { $iconClass='fas fa-sign-in-alt';   $badgeClass='badge-info'; }
            elseif (str_contains($action,'delete')) { $iconClass='fas fa-trash';         $badgeClass='badge-danger'; }
            elseif (str_contains($action,'add'))    { $iconClass='fas fa-plus-circle';   $badgeClass='badge-success'; }
            elseif (str_contains($action,'update')) { $iconClass='fas fa-edit';          $badgeClass='badge-warning'; }
            elseif (str_contains($action,'reward')) { $iconClass='fas fa-star';          $badgeClass='badge-gold'; }
            elseif (str_contains($action,'sit'))    { $iconClass='fas fa-desktop';       $badgeClass='badge-info'; }
            elseif (str_contains($action,'reset'))  { $iconClass='fas fa-redo';          $badgeClass='badge-warning'; }
            elseif (str_contains($action,'clear'))  { $iconClass='fas fa-broom';         $badgeClass='badge-danger'; }
            elseif (str_contains($action,'post'))   { $iconClass='fas fa-bullhorn';      $badgeClass='badge-success'; }
            ?>
            <tr class="log-row">
              <td><?= date('M d, Y h:i:s A', strtotime($log['created_at'])) ?></td>
              <td>
                <span class="badge <?= $badgeClass ?>"><i class="<?= $iconClass ?>"></i> <?= htmlspecialchars($log['actor']) ?></span>
              </td>
              <td><?= htmlspecialchars($log['action']) ?></td>
              <td style="font-size:0.78rem;color:var(--text-muted)"><?= htmlspecialchars($log['target'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if(!$logs): ?>
            <tr><td colspan="4" class="text-center text-muted" style="padding:30px">No logs found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
<script>
(function(){ const t=localStorage.getItem('theme')||'light'; document.documentElement.setAttribute('data-theme',t); })();
</script>
</body>
</html>
