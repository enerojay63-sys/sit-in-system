<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

$admin_name = $_SESSION['admin_data']['name'] ?? 'CCS Admin';

// ── Approve / Reject ──
if (isset($_GET['approve'])) {
  $id = (int)$_GET['approve'];
  $conn->query("UPDATE testimonials SET status='approved' WHERE id=$id");
  $conn->query("INSERT INTO activity_logs (actor,action,target) VALUES ('$admin_name','Approved testimonial','#$id')");
  header('Location: testimonials.php?success=approved'); exit;
}
if (isset($_GET['reject'])) {
  $id = (int)$_GET['reject'];
  $conn->query("UPDATE testimonials SET status='rejected' WHERE id=$id");
  header('Location: testimonials.php?success=rejected'); exit;
}
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  $conn->query("DELETE FROM testimonials WHERE id=$id");
  header('Location: testimonials.php?success=deleted'); exit;
}

$filter = $_GET['filter'] ?? 'pending';
$safe_filter = $conn->real_escape_string($filter);

$testimonials = $conn->query("
  SELECT t.*, s.firstname, s.lastname, s.course, s.year_level, s.id_number
  FROM testimonials t
  JOIN students s ON t.student_id = s.id
  WHERE t.status = '$safe_filter'
  ORDER BY t.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$counts = [];
foreach(['pending','approved','rejected'] as $st) {
  $counts[$st] = $conn->query("SELECT COUNT(*) FROM testimonials WHERE status='$st'")->fetch_row()[0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Testimonials — CCS Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'nav.php'; ?>

<div class="page-wrapper">
  <div class="section-title"><i class="fas fa-quote-left"></i> Student Testimonials</div>

  <?php if(isset($_GET['success'])): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i>
      Testimonial <?= htmlspecialchars($_GET['success']) ?> successfully.
    </div>
  <?php endif; ?>

  <!-- Filter Tabs -->
  <div class="tab-nav mb-2">
    <?php foreach(['pending'=>'warning','approved'=>'success','rejected'=>'danger'] as $st => $cls): ?>
      <a href="?filter=<?= $st ?>" class="tab-btn <?= $filter===$st?'active':'' ?>">
        <?= ucfirst($st) ?>
        <span class="badge badge-<?= $cls ?>"><?= $counts[$st] ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if(empty($testimonials)): ?>
    <div class="alert alert-info"><i class="fas fa-info-circle"></i> No <?= $filter ?> testimonials.</div>
  <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
      <?php foreach($testimonials as $t): ?>
        <div class="testimonial-card">
          <div class="tc-header">
            <div class="tc-avatar"><?= strtoupper(substr($t['firstname'],0,1).substr($t['lastname'],0,1)) ?></div>
            <div style="flex:1">
              <div class="tc-name"><?= htmlspecialchars($t['firstname'].' '.$t['lastname']) ?></div>
              <div class="tc-course"><?= $t['course'] ?> — Year <?= $t['year_level'] ?> &bull; <?= $t['id_number'] ?></div>
              <div style="font-size:0.7rem;color:var(--text-muted)"><?= date('M d, Y', strtotime($t['created_at'])) ?></div>
            </div>
          </div>
          <div class="stars mb-1"><?= str_repeat('★', $t['rating']) ?><span style="font-size:0.75rem;color:var(--text-muted)">(<?= $t['rating'] ?>/5)</span></div>
          <div class="tc-message mb-1">"<?= htmlspecialchars($t['message']) ?>"</div>
          <div class="d-flex gap-1 mt-1">
            <?php if($t['status']==='pending'): ?>
              <a href="?approve=<?= $t['id'] ?>&filter=<?= $filter ?>" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve</a>
              <a href="?reject=<?= $t['id'] ?>&filter=<?= $filter ?>"  class="btn btn-danger btn-sm"><i class="fas fa-times"></i> Reject</a>
            <?php elseif($t['status']==='rejected'): ?>
              <a href="?approve=<?= $t['id'] ?>&filter=<?= $filter ?>" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve</a>
            <?php endif; ?>
            <a href="?delete=<?= $t['id'] ?>&filter=<?= $filter ?>" class="btn btn-secondary btn-sm"
               onclick="return confirm('Delete this testimonial?')">
              <i class="fas fa-trash"></i>
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>
<script>
(function(){ const t=localStorage.getItem('theme')||'light'; document.documentElement.setAttribute('data-theme',t); })();
</script>
</body>
</html>
