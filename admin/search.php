<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

// Initialize or pull the search parameter history array context
if (!isset($_SESSION['search_history'])) {
  $_SESSION['search_history'] = [];
}

$results       = [];
$searched      = false;
$sitin_student = null;
$search_query_val = '';

// Check if a search processing loop runs via standard Form execution or historical query click
if (isset($_POST['search']) || isset($_GET['history_q'])) {
  $searched = true;
  $search_query_val = trim($_POST['q'] ?? $_GET['history_q'] ?? '');
  
  if ($search_query_val !== '') {
    // FIXED: Save search keyword history, eliminate duplicates, and cap at most recent 5 records
    if (($history_key = array_search($search_query_val, $_SESSION['search_history'])) !== false) {
      unset($_SESSION['search_history'][$history_key]);
    }
    array_unshift($_SESSION['search_history'], $search_query_val);
    $_SESSION['search_history'] = array_slice($_SESSION['search_history'], 0, 5);

    $q = '%'.$search_query_val.'%';
    $stmt = $conn->prepare("SELECT * FROM students WHERE id_number LIKE ? OR firstname LIKE ? OR lastname LIKE ? OR email LIKE ?");
    $stmt->bind_param('ssss', $q, $q, $q, $q);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
  }
}

if (isset($_GET['sitin_id'])) {
  $sid  = (int)$_GET['sitin_id'];
  $stmt = $conn->prepare("SELECT * FROM students WHERE id=?");
  $stmt->bind_param('i', $sid); $stmt->execute();
  $sitin_student = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $searched = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Search Student — CCS Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="page-wrapper">
  <div class="section-title" style="text-align: center;"><i class="fas fa-search"></i> Search Student</div>

  <div class="card mb-2" style="max-width:640px; margin: 0 auto 20px auto;">
    <div class="card-header"><i class="fas fa-search"></i> Find Student</div>
    <div class="card-body">
      <form method="POST" style="display:flex;gap:10px;">
        <input type="text" name="q" class="form-control"
               placeholder="Search by ID Number, name, or email..."
               value="<?= htmlspecialchars($search_query_val) ?>"
               required autofocus>
        <button type="submit" name="search" class="btn btn-primary" style="white-space:nowrap;">
          <i class="fas fa-search"></i> Search
        </button>
      </form>

      <?php if(!empty($_SESSION['search_history'])): ?>
        <div style="margin-top: 12px; font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
          <span style="font-weight: 500;"><i class="fas fa-clock-rotate-left" style="opacity: 0.7;"></i> Previous Searches:</span>
          <?php foreach($_SESSION['search_history'] as $prev_item): ?>
            <a href="search.php?history_q=<?= urlencode($prev_item) ?>" style="background: rgba(var(--cerulean-rgb, 33, 158, 188), 0.1); color: var(--cerulean, #219ebc); padding: 3px 10px; border-radius: 12px; text-decoration: none; font-weight: 500; font-size: 0.75rem; border: 1px solid rgba(33, 158, 188, 0.15); transition: all 0.2s;">
              <?= htmlspecialchars($prev_item) ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($searched && !$sitin_student): ?>
  <div class="card mb-2" style="max-width: 900px; margin: 0 auto;">
    <div class="card-header">
      <i class="fas fa-users"></i> Results
      <span style="margin-left:8px;background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:0.78rem;"><?= count($results) ?> found</span>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (!$results): ?>
        <p class="no-data" style="padding: 30px 0;"><i class="fas fa-search" style="font-size:2rem;display:block;margin-bottom:10px;opacity:0.25;"></i>No students found.</p>
      <?php else: ?>
      <div class="dt-wrapper">
        <table class="data-table">
          <thead><tr><th>ID Number</th><th>Name</th><th>Course</th><th>Year</th><th>Email</th><th>Session</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($results as $r): ?>
            <tr>
              <td><strong><?= htmlspecialchars($r['id_number']) ?></strong></td>
              <td><?= htmlspecialchars(fullName($r)) ?></td>
              <td><?= htmlspecialchars($r['course']) ?></td>
              <td><?= $r['year_level'] ?></td>
              <td style="font-size:0.78rem;"><?= htmlspecialchars($r['email']) ?></td>
              <td><span class="badge <?= $r['remaining_session'] <= 5 ? 'badge-danger' : 'badge-success' ?>"><?= $r['remaining_session'] ?></span></td>
              <td style="white-space:nowrap;">
                <a href="search.php?sitin_id=<?= $r['id'] ?>" class="btn btn-success btn-sm"><i class="fas fa-desktop"></i> Sit-in</a>
                <a href="students.php?edit=<?= $r['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i> Edit</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($sitin_student): ?>
  <div class="card" style="max-width:560px; margin: 0 auto;">
    <div class="card-header green">
      <i class="fas fa-desktop"></i> Sit In Form
      <a href="search.php" class="modal-close" style="margin-left:auto;font-size:1rem; color:#fff; text-decoration:none;"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
    <div class="card-body">
      <div style="background:var(--alice);border-radius:var(--radius-sm);padding:14px 16px;margin-bottom:18px;display:flex;gap:20px;flex-wrap:wrap;">
        <div>
          <div style="font-size:0.72rem;color:var(--text-muted);font-weight:600;margin-bottom:2px;">ID Number</div>
          <div style="font-weight:700;color:var(--prussian);"><?= htmlspecialchars($sitin_student['id_number']) ?></div>
        </div>
        <div>
          <div style="font-size:0.72rem;color:var(--text-muted);font-weight:600;margin-bottom:2px;">Name</div>
          <div style="font-weight:700;color:var(--prussian);"><?= htmlspecialchars(fullName($sitin_student)) ?></div>
        </div>
        <div>
          <div style="font-size:0.72rem;color:var(--text-muted);font-weight:600;margin-bottom:2px;">Course</div>
          <div style="font-weight:600;"><?= htmlspecialchars($sitin_student['course']) ?> <?= $sitin_student['year_level'] ?>Y</div>
        </div>
        <div>
          <div style="font-size:0.72rem;color:var(--text-muted);font-weight:600;margin-bottom:2px;">Remaining Session</div>
          <span style="background:<?= $sitin_student['remaining_session'] <= 5 ? 'linear-gradient(135deg,#7f0018,#ef233c)' : 'linear-gradient(135deg,#023047,#219ebc)' ?>;color:#fff;padding:2px 12px;border-radius:20px;font-weight:700;font-size:0.85rem;">
            <?= $sitin_student['remaining_session'] ?>
          </span>
        </div>
      </div>

      <form method="POST" action="sitin.php">
        <input type="hidden" name="student_id"       value="<?= $sitin_student['id'] ?>">
        <input type="hidden" name="id_number"         value="<?= htmlspecialchars($sitin_student['id_number']) ?>">
        <input type="hidden" name="student_name"      value="<?= htmlspecialchars(fullName($sitin_student)) ?>">
        <input type="hidden" name="remaining_session" value="<?= $sitin_student['remaining_session'] ?>">

        <div class="form-group">
          <label>Purpose *</label>
          <select name="purpose" class="form-control" required>
            <option value="" disabled selected>Select purpose</option>
            <option>C Programming</option><option>Java Programming</option>
            <option>PHP Programming</option><option>Python Programming</option>
            <option>ASP.Net</option><option>Database</option>
            <option>Research</option><option>Other</option>
          </select>
        </div>
        <div class="form-group">
          <label>Lab *</label>
          <input type="text" name="lab" class="form-control" placeholder="e.g. 524" required>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
          <a href="search.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Cancel</a>
          <button type="submit" name="sit_in" class="btn btn-primary btn-sm"><i class="fas fa-desktop"></i> Sit In</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!$searched): ?>
  <div style="text-align:center;padding:48px;color:var(--text-muted);">
    <i class="fas fa-search" style="font-size:3rem;display:block;margin-bottom:14px;opacity:0.2;"></i>
    <p style="font-size:0.88rem;">Enter a student ID, name, or email above to search.</p>
  </div>
  <?php endif; ?>
</div>
<script>
(function(){ 
  const t = localStorage.getItem('theme') || 'light'; 
  if (t === 'dark') document.documentElement.classList.add('dark');
})();
</script>
</body>
</html>