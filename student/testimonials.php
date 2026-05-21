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

$error = $success = '';

// ── SUBMIT TESTIMONIAL ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_testimonial'])) {
  $msg    = trim($_POST['testimonial_msg']);
  $rating = (int)$_POST['testimonial_rating'];
  if ($msg && $rating >= 1 && $rating <= 5) {
    // Check if already submitted
    $chk = $conn->query("SELECT id FROM testimonials WHERE student_id={$s['id']} LIMIT 1");
    if ($chk->num_rows > 0) {
      $error = 'You have already submitted a testimonial.';
    } else {
      $stmt = $conn->prepare("INSERT INTO testimonials (student_id, message, rating) VALUES (?,?,?)");
      $stmt->bind_param('isi', $s['id'], $msg, $rating);
      $stmt->execute(); $stmt->close();
      header('Location: testimonials.php?submitted=1'); exit;
    }
  } else {
    $error = 'Please fill in your message and select a rating.';
  }
}

if (isset($_GET['submitted'])) $success = 'Your testimonial has been submitted and is pending review. Thank you!';

// Has student already submitted?
$has_testimonial = $conn->query("SELECT id, status FROM testimonials WHERE student_id={$s['id']} LIMIT 1")->fetch_assoc();

// ── APPROVED TESTIMONIALS ──
$testimonials = $conn->query("
  SELECT t.*, s.firstname, s.lastname, s.course, s.year_level, s.id_number
  FROM testimonials t
  JOIN students s ON t.student_id = s.id
  WHERE t.status = 'approved'
  ORDER BY t.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// ── STATS ──
$stats = $conn->query("
  SELECT
    COUNT(*) as total,
    ROUND(AVG(rating),1) as avg_rating,
    COUNT(CASE WHEN rating=5 THEN 1 END) as five_star,
    COUNT(CASE WHEN rating=4 THEN 1 END) as four_star,
    COUNT(CASE WHEN rating=3 THEN 1 END) as three_star,
    COUNT(CASE WHEN rating<=2 THEN 1 END) as low_star
  FROM testimonials WHERE status='approved'
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Testimonials — CCS</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    html.dark body { background: linear-gradient(170deg,#0a0d14 0%,#0f1117 40%,#1a1d27 100%); }
    html.dark .topnav { background: linear-gradient(135deg,#05101a 0%,#0f1117 55%,#0d0f1a 100%); }
    html.dark .card { background: #1a1d27; border-color: rgba(255,255,255,0.07); }
    html.dark .card-header { background: linear-gradient(135deg,#05101a,#0f1117); }
    html.dark .card-body { color: #c8d0dc; }
    html.dark .form-control { background: #242838; border-color: #3d4060; color: #edf2f4; }
    html.dark footer { background: rgba(5,16,26,0.9); color: rgba(200,208,220,0.5); }
    html.dark .testi-card-view { background: #1e2130; border-color: rgba(255,255,255,0.07); }
    html.dark .testi-name-v { color: #edf2f4; }
    html.dark .testi-msg-v  { color: #a0aab8; }

    /* Stats row */
    .testi-stats {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 14px;
      margin-bottom: 22px;
    }
    .testi-stat-card {
      background: var(--surface);
      border-radius: var(--radius);
      padding: 18px 16px;
      text-align: center;
      box-shadow: var(--shadow-md);
      border: 1px solid rgba(141,153,174,0.2);
    }
    html.dark .testi-stat-card { background: #1a1d27; border-color: rgba(255,255,255,0.07); }
    .testi-stat-val   { font-size: 2rem; font-weight: 800; color: var(--prussian); line-height: 1; }
    html.dark .testi-stat-val { color: #edf2f4; }
    .testi-stat-label { font-size: 0.74rem; color: var(--text-muted); margin-top: 5px; font-weight: 500; }
    .testi-stars-big  { color: var(--honey); font-size: 1.1rem; letter-spacing: 2px; margin: 6px 0; }

    /* Rating breakdown */
    .rating-bar-row {
      display: flex; align-items: center; gap: 8px;
      margin-bottom: 8px; font-size: 0.78rem;
    }
    .rating-bar-label { width: 50px; color: var(--text-muted); font-weight: 600; }
    .rating-bar-track { flex: 1; height: 7px; background: var(--alice); border-radius: 20px; overflow: hidden; }
    html.dark .rating-bar-track { background: #242838; }
    .rating-bar-fill  { height: 100%; border-radius: 20px; background: linear-gradient(90deg,var(--orange),var(--honey)); }
    .rating-bar-count { width: 24px; text-align: right; font-weight: 700; color: var(--text-muted); }

    /* Testimonial cards grid */
    .testi-grid-view {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 14px;
    }
    .testi-card-view {
      background: var(--alice);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 18px;
      transition: box-shadow 0.18s, transform 0.18s;
    }
    .testi-card-view:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }

    .testi-head-v {
      display: flex; align-items: center; gap: 10px; margin-bottom: 12px;
    }
    .testi-av-v {
      width: 40px; height: 40px; border-radius: 50%;
      background: linear-gradient(135deg, var(--prussian), var(--cerulean));
      color: #fff; font-size: 0.8rem; font-weight: 700;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .testi-name-v  { font-weight: 700; color: var(--prussian); font-size: 0.85rem; }
    .testi-course-v{ font-size: 0.72rem; color: var(--text-muted); margin-top: 2px; }
    .testi-stars-v { color: var(--honey); font-size: 0.8rem; margin-left: auto; letter-spacing: 1px; }
    .testi-msg-v   { font-size: 0.82rem; color: var(--text-soft); line-height: 1.6; font-style: italic; margin-bottom: 10px; }
    .testi-date-v  { font-size: 0.7rem; color: var(--text-muted); }

    /* Star input */
    .star-input { display: flex; flex-direction: row-reverse; gap: 4px; }
    .star-input input { display: none; }
    .star-input label {
      font-size: 1.8rem; cursor: pointer; color: #d1d5db;
      transition: color 0.15s;
    }
    .star-input input:checked ~ label,
    .star-input label:hover,
    .star-input label:hover ~ label { color: var(--honey); }

    /* My testimonial status */
    .my-testi-status {
      display: flex; align-items: center; gap: 12px;
      padding: 14px 16px; border-radius: var(--radius-sm);
      font-size: 0.84rem; font-weight: 500;
      margin-bottom: 0;
    }
    .my-testi-status.pending  { background: #fff8e1; border: 1px solid var(--honey); color: #6b3a00; }
    .my-testi-status.approved { background: #dcfce7; border: 1px solid #86efac; color: #14532d; }
    .my-testi-status.rejected { background: #ffe4e6; border: 1px solid #fca5a5; color: var(--dark-red); }

    @media (max-width: 700px) {
      .testi-stats { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>

<nav class="topnav">
  <a href="dashboard.php" class="topnav-brand">Dashboard</a>
  <div class="topnav-links">
    <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
    <a href="sitin_summary.php"><i class="fas fa-chart-bar"></i> Summary</a>
    <a href="history.php"><i class="fas fa-history"></i> History</a>
    <a href="lab_availability.php"><i class="fas fa-desktop"></i> Labs</a>
    <a href="reservation.php"><i class="fas fa-calendar-plus"></i> Reservation</a>
    <a href="edit_profile.php"><i class="fas fa-user-edit"></i> Profile</a>
    <a href="testimonials.php" class="active"><i class="fas fa-quote-left"></i> Testimonials</a>
    <button class="dark-toggle" onclick="toggleDark()" title="Toggle dark mode">
      <i class="fas fa-moon" id="darkIcon"></i>
    </button>
    <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Log out</a>
  </div>
</nav>

<div class="page-content">

  <!-- Header -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:12px;">
    <div>
      <h2 style="font-size:1.25rem;font-weight:700;color:var(--prussian);margin-bottom:4px;">
        <i class="fas fa-quote-left" style="color:var(--cerulean);"></i> Student Testimonials
      </h2>
      <p style="font-size:0.8rem;color:var(--text-muted);">See what students say about the CCS laboratory experience</p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary btn-sm">
      <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
  </div>

  <?php if ($error): ?>
  <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
  <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <!-- ── STATS ── -->
  <?php if ($stats['total'] > 0): ?>
  <div class="testi-stats">
    <div class="testi-stat-card">
      <div class="testi-stat-val"><?= $stats['total'] ?></div>
      <div class="testi-stars-big">★★★★★</div>
      <div class="testi-stat-label">Total Reviews</div>
    </div>
    <div class="testi-stat-card">
      <div class="testi-stat-val"><?= $stats['avg_rating'] ?></div>
      <div class="testi-stars-big"><?= str_repeat('★', (int)round($stats['avg_rating'])) ?><?= str_repeat('☆', 5-(int)round($stats['avg_rating'])) ?></div>
      <div class="testi-stat-label">Average Rating</div>
    </div>
    <div class="testi-stat-card">
      <div style="padding-top:4px;">
        <?php
        $dist = [
          '5 Stars' => (int)$stats['five_star'],
          '4 Stars' => (int)$stats['four_star'],
          '3 Stars' => (int)$stats['three_star'],
          '≤2 Stars'=> (int)$stats['low_star'],
        ];
        foreach ($dist as $label => $count):
          $pct = $stats['total'] ? round($count/$stats['total']*100) : 0;
        ?>
        <div class="rating-bar-row">
          <div class="rating-bar-label"><?= $label ?></div>
          <div class="rating-bar-track"><div class="rating-bar-fill" style="width:<?= $pct ?>%"></div></div>
          <div class="rating-bar-count"><?= $count ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── SUBMIT / STATUS ── -->
  <div style="display:grid;grid-template-columns:<?= $has_testimonial ? '1fr' : '1fr 1fr' ?>;gap:20px;margin-bottom:24px;">

    <?php if (!$has_testimonial): ?>
    <!-- Submit form -->
    <div class="card">
      <div class="card-header"><i class="fas fa-edit"></i> Share Your Experience</div>
      <div class="card-body">
        <form method="POST">
          <div class="form-group">
            <label style="font-weight:600;font-size:0.82rem;margin-bottom:8px;display:block;">Your Rating *</label>
            <div class="star-input">
              <?php for ($i = 5; $i >= 1; $i--): ?>
              <input type="radio" name="testimonial_rating" id="ts<?= $i ?>" value="<?= $i ?>" required>
              <label for="ts<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">★</label>
              <?php endfor; ?>
            </div>
            <p style="font-size:0.72rem;color:var(--text-muted);margin-top:5px;">Click a star to rate (5 = Excellent)</p>
          </div>
          <div class="form-group">
            <label style="font-weight:600;font-size:0.82rem;margin-bottom:8px;display:block;">Your Message *</label>
            <textarea name="testimonial_msg" class="form-control" rows="4"
              placeholder="Tell us about your experience using the CCS lab..."
              required maxlength="500"></textarea>
            <p style="font-size:0.7rem;color:var(--text-muted);margin-top:4px;">Max 500 characters. Your testimonial will be reviewed before publishing.</p>
          </div>
          <button type="submit" name="submit_testimonial" class="btn btn-primary" style="width:100%;justify-content:center;">
            <i class="fas fa-paper-plane"></i> Submit Testimonial
          </button>
        </form>
      </div>
    </div>
    <?php else: ?>
    <!-- Already submitted -->
    <div class="card">
      <div class="card-header"><i class="fas fa-check-circle"></i> Your Testimonial</div>
      <div class="card-body">
        <div class="my-testi-status <?= $has_testimonial['status'] ?>">
          <?php if ($has_testimonial['status'] === 'pending'): ?>
            <i class="fas fa-clock" style="font-size:1.2rem;"></i>
            <div>
              <strong>Pending Review</strong><br>
              <span style="font-size:0.78rem;opacity:0.8;">Your testimonial is awaiting admin approval. Thank you for sharing!</span>
            </div>
          <?php elseif ($has_testimonial['status'] === 'approved'): ?>
            <i class="fas fa-check-circle" style="font-size:1.2rem;"></i>
            <div>
              <strong>Published!</strong><br>
              <span style="font-size:0.78rem;opacity:0.8;">Your testimonial has been approved and is visible below.</span>
            </div>
          <?php else: ?>
            <i class="fas fa-times-circle" style="font-size:1.2rem;"></i>
            <div>
              <strong>Not Approved</strong><br>
              <span style="font-size:0.78rem;opacity:0.8;">Your testimonial was not approved. Please contact the admin for details.</span>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Info card -->
    <div class="card" style="<?= $has_testimonial ? '' : '' ?>">
      <div class="card-header"><i class="fas fa-info-circle"></i> About Testimonials</div>
      <div class="card-body">
        <div style="display:flex;flex-direction:column;gap:12px;font-size:0.82rem;color:var(--text-soft);">
          <div style="display:flex;gap:10px;align-items:flex-start;">
            <i class="fas fa-check-circle" style="color:#16a34a;margin-top:2px;flex-shrink:0;"></i>
            <span>Testimonials are <strong>reviewed by admin</strong> before appearing publicly.</span>
          </div>
          <div style="display:flex;gap:10px;align-items:flex-start;">
            <i class="fas fa-check-circle" style="color:#16a34a;margin-top:2px;flex-shrink:0;"></i>
            <span>Each student may submit <strong>one testimonial</strong>.</span>
          </div>
          <div style="display:flex;gap:10px;align-items:flex-start;">
            <i class="fas fa-check-circle" style="color:#16a34a;margin-top:2px;flex-shrink:0;"></i>
            <span>Approved testimonials are shown on the <strong>public login page</strong> too.</span>
          </div>
          <div style="display:flex;gap:10px;align-items:flex-start;">
            <i class="fas fa-star" style="color:var(--honey);margin-top:2px;flex-shrink:0;"></i>
            <span>Your honest feedback helps improve the CCS lab experience for everyone.</span>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- ── APPROVED TESTIMONIALS ── -->
  <div class="card">
    <div class="card-header">
      <i class="fas fa-quote-left"></i> What Students Say
      <span style="margin-left:8px;background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:0.75rem;">
        <?= count($testimonials) ?> reviews
      </span>
    </div>
    <div class="card-body">
      <?php if (empty($testimonials)): ?>
      <div style="text-align:center;padding:40px;color:var(--text-muted);">
        <i class="fas fa-quote-left" style="font-size:3rem;display:block;margin-bottom:14px;opacity:0.2;"></i>
        <p style="font-size:0.88rem;">No approved testimonials yet. Be the first to share!</p>
      </div>
      <?php else: ?>
      <div class="testi-grid-view">
        <?php foreach ($testimonials as $t): ?>
        <div class="testi-card-view">
          <div class="testi-head-v">
            <div class="testi-av-v"><?= strtoupper(substr($t['firstname'],0,1).substr($t['lastname'],0,1)) ?></div>
            <div style="min-width:0;flex:1;">
              <div class="testi-name-v"><?= htmlspecialchars($t['firstname'].' '.$t['lastname']) ?></div>
              <div class="testi-course-v"><?= $t['course'] ?> — Year <?= $t['year_level'] ?> &bull; <?= $t['id_number'] ?></div>
            </div>
            <div class="testi-stars-v"><?= str_repeat('★', (int)$t['rating']) ?><?= str_repeat('☆', 5-(int)$t['rating']) ?></div>
          </div>
          <div class="testi-msg-v">"<?= htmlspecialchars($t['message']) ?>"</div>
          <div class="testi-date-v">
            <i class="fas fa-clock" style="font-size:0.65rem;"></i>
            <?= date('M d, Y', strtotime($t['created_at'])) ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
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
