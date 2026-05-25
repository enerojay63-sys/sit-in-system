<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

if (!isLoggedInAdmin()) {
    header('Location: ../admin/login.php');
    exit;
}

// Handle Status Updates
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $t_id = (int)$_GET['id'];
    
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE testimonials SET status = 'approved' WHERE id = ?");
        $stmt->bind_param('i', $t_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE testimonials SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param('i', $t_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM testimonials WHERE id = ?");
        $stmt->bind_param('i', $t_id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: testimonials.php?tab=' . ($_GET['tab'] ?? 'pending'));
    exit;
}

$current_tab = $_GET['tab'] ?? 'pending';
if (!in_array($current_tab, ['pending', 'approved', 'rejected'])) {
    $current_tab = 'pending';
}

$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
$count_res = $conn->query("SELECT status, COUNT(*) as cnt FROM testimonials GROUP BY status");
while ($row = $count_res->fetch_assoc()) {
    if (isset($counts[$row['status']])) $counts[$row['status']] = $row['cnt'];
}

$stmt = $conn->prepare("
    SELECT t.id, t.message, t.rating, t.status, t.created_at,
           s.firstname, s.lastname, s.id_number, s.course, s.year_level
    FROM testimonials t
    JOIN students s ON t.student_id = s.id
    WHERE t.status = ?
    ORDER BY t.created_at DESC
");
$stmt->bind_param('s', $current_tab);
$stmt->execute();
$testimonials = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Testimonials — CCS Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    /* ── TAB NAV ── */
    .testi-tab-nav {
      display: flex;
      background: var(--surface);
      border: 1px solid var(--border);
      padding: 6px;
      border-radius: var(--radius);
      gap: 8px;
      max-width: 480px;
      margin-bottom: 28px;
      box-shadow: var(--shadow-sm);
    }
    html.dark .testi-tab-nav { background: #1a1d27; border-color: rgba(255,255,255,0.07); }

    .testi-tab-link {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 10px 14px;
      color: var(--text-muted);
      text-decoration: none;
      font-size: 0.84rem;
      font-weight: 600;
      border-radius: var(--radius-sm);
      transition: all 0.2s ease;
    }
    .testi-tab-link:hover {
      color: var(--prussian);
      background: var(--alice);
    }
    html.dark .testi-tab-link:hover { background: rgba(255,255,255,0.05); color: #edf2f4; }
    .testi-tab-link.active {
      background: linear-gradient(135deg, var(--prussian), var(--cadet));
      color: #fff;
      box-shadow: var(--shadow-sm);
    }
    .testi-tab-link .tab-count {
      font-size: 0.72rem;
      font-weight: 700;
      padding: 2px 7px;
      border-radius: 20px;
      background: rgba(255,255,255,0.2);
      color: inherit;
    }
    .testi-tab-link:not(.active) .tab-count {
      background: var(--alice);
      color: var(--text-muted);
    }
    html.dark .testi-tab-link:not(.active) .tab-count { background: #242838; }

    /* ── CARDS GRID ── */
    .testi-admin-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
      gap: 20px;
    }
    .t-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 22px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      box-shadow: var(--shadow-md);
      transition: transform 0.2s, box-shadow 0.2s;
    }
    html.dark .t-card { background: #1a1d27; border-color: rgba(255,255,255,0.07); }
    .t-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }

    .t-author-row {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 16px;
    }
    .t-avatar {
      width: 42px; height: 42px; border-radius: 50%;
      background: linear-gradient(135deg, var(--prussian), var(--cerulean));
      display: flex; align-items: center; justify-content: center;
      color: #fff; font-weight: 700; font-size: 0.88rem; flex-shrink: 0;
    }
    .t-meta-info { flex: 1; }
    .t-name { font-weight: 700; color: var(--prussian); font-size: 0.9rem; }
    html.dark .t-name { color: #edf2f4; }
    .t-sub { font-size: 0.75rem; color: var(--text-muted); margin-top: 2px; }
    .t-rating { color: var(--honey); font-size: 0.82rem; letter-spacing: 1px; }

    .t-body {
      font-size: 0.86rem;
      color: var(--text-soft);
      line-height: 1.65;
      font-style: italic;
      margin-bottom: 20px;
      flex-grow: 1;
    }
    html.dark .t-body { color: #a0aab8; }

    .t-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding-top: 14px;
      border-top: 1px solid var(--border);
    }
    html.dark .t-footer { border-color: rgba(255,255,255,0.07); }
    .t-date { font-size: 0.74rem; color: var(--text-muted); display: flex; align-items: center; gap: 5px; }
    .t-actions { display: flex; align-items: center; gap: 8px; }

    .btn-taction {
      border: none;
      padding: 7px 12px;
      font-family: inherit;
      font-size: 0.78rem;
      font-weight: 600;
      border-radius: var(--radius-sm);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      text-decoration: none;
      transition: all 0.18s;
    }
    .btn-tapprove { background: #dcfce7; color: #14532d; border: 1px solid #86efac; }
    .btn-tapprove:hover { background: #16a34a; color: #fff; border-color: #16a34a; }
    .btn-treject { background: #fff8e1; color: #6b3a00; border: 1px solid var(--honey); }
    .btn-treject:hover { background: var(--orange); color: #fff; border-color: var(--orange); }
    .btn-tdelete { background: #ffe4e6; color: var(--dark-red); border: 1px solid #fca5a5; }
    .btn-tdelete:hover { background: var(--red); color: #fff; border-color: var(--red); }

    /* ── EMPTY STATE ── */
    .testi-empty {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 60px 40px;
      text-align: center;
      max-width: 480px;
      margin: 40px auto;
      box-shadow: var(--shadow-md);
    }
    html.dark .testi-empty { background: #1a1d27; border-color: rgba(255,255,255,0.07); }
    .testi-empty-icon {
      width: 64px; height: 64px;
      background: var(--alice);
      border: 1px solid var(--border);
      color: var(--text-muted);
      font-size: 1.6rem;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 18px;
    }
    .testi-empty h3 { font-size: 1.1rem; font-weight: 700; color: var(--prussian); margin-bottom: 8px; }
    html.dark .testi-empty h3 { color: #edf2f4; }
    .testi-empty p { font-size: 0.85rem; color: var(--text-muted); line-height: 1.6; }
  </style>
</head>
<body>
<?php include 'nav.php'; ?>

<div class="page-wrapper">

  <div class="d-flex align-center justify-between mb-2 flex-wrap gap-1" style="margin-bottom:22px;">
    <div>
      <div class="section-title" style="margin-bottom:4px;"><i class="fas fa-comments"></i> Student Testimonials</div>
      <p style="font-size:0.8rem;color:var(--text-muted);">Review, approve, or reject feedback submitted by students</p>
    </div>
  </div>

  <!-- Tab Navigation -->
  <div class="testi-tab-nav">
    <a href="testimonials.php?tab=pending" class="testi-tab-link <?= $current_tab === 'pending' ? 'active' : '' ?>">
      <i class="fas fa-clock"></i> Pending
      <span class="tab-count"><?= $counts['pending'] ?></span>
    </a>
    <a href="testimonials.php?tab=approved" class="testi-tab-link <?= $current_tab === 'approved' ? 'active' : '' ?>">
      <i class="fas fa-check-circle"></i> Approved
      <span class="tab-count"><?= $counts['approved'] ?></span>
    </a>
    <a href="testimonials.php?tab=rejected" class="testi-tab-link <?= $current_tab === 'rejected' ? 'active' : '' ?>">
      <i class="fas fa-times-circle"></i> Rejected
      <span class="tab-count"><?= $counts['rejected'] ?></span>
    </a>
  </div>

  <!-- Testimonials Grid -->
  <?php if (!empty($testimonials)): ?>
  <div class="testi-admin-grid">
    <?php foreach ($testimonials as $t): ?>
    <div class="t-card">
      <div>
        <div class="t-author-row">
          <div class="t-avatar">
            <?= strtoupper(substr($t['firstname'] ?? 'S', 0, 1) . substr($t['lastname'] ?? 'S', 0, 1)) ?>
          </div>
          <div class="t-meta-info">
            <div class="t-name"><?= htmlspecialchars(($t['firstname'] ?? '') . ' ' . ($t['lastname'] ?? '')) ?></div>
            <div class="t-sub"><?= htmlspecialchars($t['id_number'] ?? '') ?> &bull; <?= htmlspecialchars($t['course'] ?? '') ?> — Yr <?= $t['year_level'] ?></div>
          </div>
          <div class="t-rating">
            <?= str_repeat('★', (int)$t['rating']) . str_repeat('☆', 5 - (int)$t['rating']) ?>
          </div>
        </div>
        <div class="t-body">"<?= htmlspecialchars($t['message']) ?>"</div>
      </div>
      <div class="t-footer">
        <span class="t-date">
          <i class="far fa-calendar-alt"></i> <?= date('M d, Y', strtotime($t['created_at'])) ?>
        </span>
        <div class="t-actions">
          <?php if ($current_tab === 'pending'): ?>
            <a href="testimonials.php?action=approve&id=<?= $t['id'] ?>&tab=pending" class="btn-taction btn-tapprove"><i class="fas fa-check"></i> Approve</a>
            <a href="testimonials.php?action=reject&id=<?= $t['id'] ?>&tab=pending" class="btn-taction btn-treject"><i class="fas fa-ban"></i> Reject</a>
          <?php elseif ($current_tab === 'approved'): ?>
            <a href="testimonials.php?action=reject&id=<?= $t['id'] ?>&tab=approved" class="btn-taction btn-treject"><i class="fas fa-ban"></i> Revoke</a>
            <a href="testimonials.php?action=delete&id=<?= $t['id'] ?>&tab=approved" class="btn-taction btn-tdelete" onclick="return confirm('Permanently delete this testimonial?')"><i class="fas fa-trash"></i> Delete</a>
          <?php elseif ($current_tab === 'rejected'): ?>
            <a href="testimonials.php?action=approve&id=<?= $t['id'] ?>&tab=rejected" class="btn-taction btn-tapprove"><i class="fas fa-check"></i> Restore</a>
            <a href="testimonials.php?action=delete&id=<?= $t['id'] ?>&tab=rejected" class="btn-taction btn-tdelete" onclick="return confirm('Permanently delete this testimonial?')"><i class="fas fa-trash"></i> Delete</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php else: ?>
  <div class="testi-empty">
    <div class="testi-empty-icon"><i class="fas fa-folder-open"></i></div>
    <h3>No Testimonials Found</h3>
    <p>There are no <strong><?= ucfirst($current_tab) ?></strong> testimonials at this time.</p>
  </div>
  <?php endif; ?>

</div>

<script>
(function(){ const t=localStorage.getItem('theme')||'light'; if(t==='dark') document.documentElement.classList.add('dark'); })();
</script>
</body>
</html>