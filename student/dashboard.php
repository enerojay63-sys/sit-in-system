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

// ── NOTIFICATIONS DATA ──
$latest_ann = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
$ann        = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");

$active_sitin = $conn->prepare("SELECT * FROM sitin_records WHERE student_id=? AND status='active' ORDER BY time_in DESC LIMIT 1");
$active_sitin->bind_param('i', $s['id']); $active_sitin->execute();
$active_sit = $active_sitin->get_result()->fetch_assoc();
$active_sitin->close();

$pending_fb = $conn->prepare("
  SELECT sr.id, sr.purpose, sr.lab, sr.time_out
  FROM sitin_records sr
  LEFT JOIN feedback f ON f.sitin_id = sr.id
  WHERE sr.student_id=? AND sr.status='done' AND f.id IS NULL
  ORDER BY sr.time_out DESC LIMIT 1
");
$pending_fb->bind_param('i', $s['id']); $pending_fb->execute();
$fb_needed = $pending_fb->get_result()->fetch_assoc();
$pending_fb->close();

$res_update = $conn->prepare("
  SELECT * FROM reservations
  WHERE student_id=? AND status IN ('approved','rejected')
  ORDER BY created_at DESC LIMIT 1
");
$res_update->bind_param('i', $s['id']); $res_update->execute();
$res_notif = $res_update->get_result()->fetch_assoc();
$res_update->close();

$low_session = $s['remaining_session'] <= 5;

// Build notifications
$notifications = [];
if ($active_sit) {
  $notifications[] = ['type'=>'active','icon'=>'fa-desktop','color'=>'#16a34a',
    'title'=>'You have an active sit-in','msg'=>'Lab: '.$active_sit['lab'].' · '.$active_sit['purpose'],
    'time'=>'Since '.date('h:i A',strtotime($active_sit['time_in'])),'action'=>null];
}
if ($fb_needed) {
  $notifications[] = ['type'=>'feedback','icon'=>'fa-star','color'=>'#fb8500',
    'title'=>'Rate your sit-in experience','msg'=>'Lab: '.$fb_needed['lab'].' · '.$fb_needed['purpose'],
    'time'=>'Completed '.date('M d',strtotime($fb_needed['time_out'])),'action'=>'feedback'];
}
if ($low_session) {
  $notifications[] = ['type'=>'session','icon'=>'fa-exclamation-triangle','color'=>'#ef233c',
    'title'=>'Low remaining sessions!','msg'=>'You only have '.$s['remaining_session'].' session'.($s['remaining_session']===1?'':'s').' left.',
    'time'=>'Check with admin','action'=>null];
}
if ($res_notif) {
  $approved = $res_notif['status']==='approved';
  $notifications[] = ['type'=>'reservation','icon'=>$approved?'fa-calendar-check':'fa-calendar-times',
    'color'=>$approved?'#219ebc':'#d90429','title'=>'Reservation '.ucfirst($res_notif['status']),
    'msg'=>'Lab '.$res_notif['lab'].' on '.date('M d',strtotime($res_notif['date'])),
    'time'=>date('M d, Y',strtotime($res_notif['created_at'])),'action'=>null];
}
if ($latest_ann) {
  $notifications[] = ['type'=>'announcement','icon'=>'fa-bullhorn','color'=>'#2b2d42',
    'title'=>'New Announcement','msg'=>mb_strimwidth($latest_ann['content'],0,60,'…'),
    'time'=>date('M d, Y',strtotime($latest_ann['created_at'])),'action'=>null];
}
$notif_count = count($notifications);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Dashboard — <?= htmlspecialchars($s['firstname']) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    /* ── NOTIFICATION DROPDOWN ── */
    .notif-wrap { position: relative; }
    .notif-trigger {
      display: flex; align-items: center; gap: 6px;
      color: rgba(255,255,255,0.85); text-decoration: none;
      font-size: 0.77rem; font-weight: 500; padding: 6px 10px;
      border-radius: 7px; transition: all 0.17s; cursor: pointer;
      background: none; border: none; font-family: inherit; position: relative;
    }
    .notif-trigger:hover { background: rgba(142,202,230,0.18); color: #8ecae6; }
    .notif-badge {
      position: absolute; top: 3px; right: 3px;
      background: #ef233c; color: #fff; font-size: 0.6rem; font-weight: 800;
      width: 16px; height: 16px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      border: 2px solid #023047; line-height: 1;
    }
    .notif-panel {
      display: none; position: absolute; top: calc(100% + 10px); right: 0;
      width: 320px; background: #fff; border-radius: 12px;
      box-shadow: 0 8px 32px rgba(2,48,71,0.2);
      border: 1px solid rgba(141,153,174,0.25); z-index: 500;
      overflow: hidden; animation: notifIn 0.2s ease;
    }
    @keyframes notifIn {
      from { opacity: 0; transform: translateY(-6px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .notif-wrap.open .notif-panel { display: block; }
    .notif-header {
      background: linear-gradient(135deg, #023047, #2b2d42); color: #fff;
      padding: 12px 16px; font-size: 0.84rem; font-weight: 700;
      display: flex; align-items: center; justify-content: space-between;
      border-bottom: 2px solid #219ebc;
    }
    .notif-header .count-pill {
      background: #ef233c; color: #fff; font-size: 0.7rem;
      font-weight: 800; padding: 2px 8px; border-radius: 20px;
    }
    .notif-list { max-height: 360px; overflow-y: auto; }
    .notif-item {
      display: flex; gap: 12px; padding: 13px 16px;
      border-bottom: 1px solid #f0f4f8; transition: background 0.14s; cursor: default;
    }
    .notif-item:last-child { border-bottom: none; }
    .notif-item:hover { background: #f7fbff; }
    .notif-item.clickable { cursor: pointer; }
    .notif-item.clickable:hover { background: #e8f4fa; }
    .notif-icon-wrap {
      width: 38px; height: 38px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.9rem; flex-shrink: 0; margin-top: 1px;
    }
    .notif-content { flex: 1; min-width: 0; }
    .notif-title { font-size: 0.81rem; font-weight: 700; color: #2b2d42; margin-bottom: 3px; line-height: 1.3; }
    .notif-msg   { font-size: 0.76rem; color: #4a5568; line-height: 1.45; margin-bottom: 4px; }
    .notif-time  { font-size: 0.7rem; color: #8d99ae; font-weight: 500; }
    .notif-empty { padding: 28px 16px; text-align: center; color: #8d99ae; font-size: 0.83rem; }
    .notif-footer {
      padding: 10px 16px; background: #f7f9fb;
      border-top: 1px solid #edf2f4; text-align: center; font-size: 0.75rem;
    }
    .notif-footer a { color: #219ebc; text-decoration: none; font-weight: 600; }
    .notif-footer a:hover { text-decoration: underline; }

    /* ── DASHBOARD GRID ── */
    /* Row 1: student info | announcements | rules */
    /* Row 2: quick actions (full width) */
    .dash-outer {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .dash-grid-top {
      display: grid;
      grid-template-columns: 240px 1fr 300px;
      gap: 20px;
      align-items: start;
    }
    .dash-grid-bottom {
      display: grid;
      grid-template-columns: 1fr;
      gap: 20px;
    }

    /* ── QUICK ACTIONS CARD ── */
    .quick-actions-grid {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 12px;
    }
    .qa-btn {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 10px;
      padding: 18px 10px;
      border-radius: var(--radius);
      text-decoration: none;
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--prussian);
      background: var(--alice);
      border: 1.5px solid var(--border);
      transition: all 0.2s;
      cursor: pointer;
      font-family: inherit;
      text-align: center;
      line-height: 1.3;
    }
    .qa-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(2,48,71,0.13);
      border-color: var(--cerulean);
      color: var(--prussian);
    }
    .qa-icon {
      width: 46px; height: 46px;
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.15rem;
      color: #fff;
      flex-shrink: 0;
    }
    .qa-icon.navy   { background: linear-gradient(135deg, var(--prussian), var(--cerulean)); }
    .qa-icon.gold   { background: linear-gradient(135deg, var(--orange), var(--honey)); }
    .qa-icon.green  { background: linear-gradient(135deg, #065f46, #16a34a); }
    .qa-icon.red    { background: linear-gradient(135deg, #7f0018, var(--red)); }
    .qa-icon.purple { background: linear-gradient(135deg, #4c1d95, #7c3aed); }
    .qa-icon.sky    { background: linear-gradient(135deg, var(--prussian), #0ea5e9); }

    /* ── DARK MODE ── */
    html.dark body { background: linear-gradient(170deg,#0a0d14 0%,#0f1117 40%,#1a1d27 100%); }
    html.dark .topnav { background: linear-gradient(135deg,#05101a 0%,#0f1117 55%,#0d0f1a 100%); }
    html.dark .card { background: #1a1d27; border-color: rgba(255,255,255,0.07); }
    html.dark .card-header { background: linear-gradient(135deg,#05101a,#0f1117); }
    html.dark .card-body, html.dark .info-list, html.dark .rules-box, html.dark .announcement-list { color: #c8d0dc; }
    html.dark .announcement-item { border-bottom-color: rgba(255,255,255,0.07); }
    html.dark .announcement-text { color: #a0aab8; }
    html.dark .info-item { border-bottom-color: rgba(255,255,255,0.07); color: #c8d0dc; }
    html.dark .info-label { color: #8ecae6; }
    html.dark .rules-box h3 { color: #edf2f4; }
    html.dark .rules-box h5 { color: #8ecae6; }
    html.dark .rules-box p  { color: #a0aab8; }
    html.dark .qa-btn { background: #242838; border-color: rgba(255,255,255,0.1); color: #c8d0dc; }
    html.dark .qa-btn:hover { background: #2d3247; border-color: #219ebc; color: #edf2f4; }
    html.dark footer { background: rgba(5,16,26,0.9); color: rgba(200,208,220,0.5); }
    html.dark .notif-panel { background: #1a1d27; border-color: rgba(255,255,255,0.1); }
    html.dark .notif-title { color: #edf2f4; }
    html.dark .notif-msg { color: #a0aab8; }
    html.dark .notif-item { border-bottom-color: rgba(255,255,255,0.07); }
    html.dark .notif-item:hover { background: #242838; }
    html.dark .notif-footer { background: #131620; border-top-color: rgba(255,255,255,0.07); }
    html.dark .modal-box { background: #1a1d27; }
    html.dark .modal-header { background: linear-gradient(135deg,#05101a,#0f1117); }
    html.dark .modal-body { color: #c8d0dc; }
    html.dark .modal-footer { background: #131620; }
    html.dark .form-control { background: #242838; border-color: #3d4060; color: #edf2f4; }
    html.dark .alert-info { background: rgba(33,158,188,0.12); color: #8ecae6; border-color: rgba(33,158,188,0.3); }
    html.dark .alert-warning { background: rgba(251,133,0,0.12); color: #ffb703; border-color: rgba(251,133,0,0.3); }

    /* Star rating */
    #starRating input:checked ~ label,
    #starRating label:hover,
    #starRating label:hover ~ label { color: #ffb703 !important; }

    /* Responsive */
    @media (max-width: 1100px) {
      .dash-grid-top { grid-template-columns: 220px 1fr; }
      .dash-grid-top > .card:last-child { grid-column: 1/-1; }
      .quick-actions-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 700px) {
      .dash-grid-top { grid-template-columns: 1fr; }
      .quick-actions-grid { grid-template-columns: repeat(2, 1fr); }
    }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="topnav">
  <a href="dashboard.php" class="topnav-brand">Dashboard</a>
  <div class="topnav-links">

    <!-- NOTIFICATION BELL -->
    <div class="notif-wrap" id="notifWrap">
      <button class="notif-trigger" onclick="toggleNotif(event)">
        <i class="fas fa-bell"></i> Notification
        <i class="fas fa-caret-down" style="font-size:0.6rem;"></i>
        <?php if ($notif_count > 0): ?>
        <span class="notif-badge"><?= $notif_count ?></span>
        <?php endif; ?>
      </button>
      <div class="notif-panel" id="notifPanel">
        <div class="notif-header">
          <span><i class="fas fa-bell"></i> &nbsp;Notifications</span>
          <?php if ($notif_count > 0): ?>
          <span class="count-pill"><?= $notif_count ?> new</span>
          <?php endif; ?>
        </div>
        <div class="notif-list">
          <?php if (empty($notifications)): ?>
          <div class="notif-empty">
            <i class="fas fa-check-circle" style="font-size:2rem;display:block;margin-bottom:8px;color:#8d99ae;"></i>
            You're all caught up!<br>
            <span style="font-size:0.76rem;">No new notifications.</span>
          </div>
          <?php else: ?>
          <?php foreach ($notifications as $n): ?>
          <div class="notif-item <?= $n['action']==='feedback'?'clickable':'' ?>"
               <?= $n['action']==='feedback' ? "onclick=\"document.getElementById('fbModal').classList.add('open')\"" : '' ?>>
            <div class="notif-icon-wrap" style="background:<?= $n['color'] ?>22;">
              <i class="fas <?= $n['icon'] ?>" style="color:<?= $n['color'] ?>;"></i>
            </div>
            <div class="notif-content">
              <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
              <div class="notif-msg"><?= htmlspecialchars($n['msg']) ?></div>
              <div class="notif-time"><i class="fas fa-clock" style="font-size:0.65rem;"></i> <?= htmlspecialchars($n['time']) ?></div>
            </div>
            <?php if ($n['action'] === 'feedback'): ?>
            <i class="fas fa-chevron-right" style="color:#8d99ae;font-size:0.75rem;margin-top:10px;flex-shrink:0;"></i>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <div class="notif-footer">
          <a href="history.php"><i class="fas fa-history"></i> View full history</a>
          &nbsp;·&nbsp;
          <a href="reservation.php"><i class="fas fa-calendar"></i> Reservations</a>
        </div>
      </div>
    </div>
    <!-- END NOTIFICATION -->

    <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Home</a>
    <a href="edit_profile.php"><i class="fas fa-user-edit"></i> Edit Profile</a>
    <a href="history.php"><i class="fas fa-history"></i> History</a>
    <a href="reservation.php"><i class="fas fa-calendar-plus"></i> Reservation</a>
    <button class="dark-toggle" onclick="toggleDark()" title="Toggle dark mode" style="
      background:rgba(255,255,255,.1); border:1.5px solid rgba(255,255,255,.25);
      color:rgba(255,255,255,.8); border-radius:7px; padding:6px 10px;
      cursor:pointer; font-size:0.82rem; display:flex; align-items:center; gap:5px;
      font-family:inherit; transition:all .18s;
    ">
      <i class="fas fa-moon" id="darkIcon"></i>
    </button>
    <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Log out</a>
  </div>
</nav>

<div class="page-content" style="position:relative;z-index:1;">

  <!-- Active sit-in banner -->
  <?php if ($active_sit): ?>
  <div class="alert alert-info" style="margin-bottom:20px;">
    <i class="fas fa-desktop"></i>
    <div>
      <strong>You have an active sit-in session</strong> —
      Lab: <strong><?= htmlspecialchars($active_sit['lab']) ?></strong> &nbsp;·&nbsp;
      Purpose: <strong><?= htmlspecialchars($active_sit['purpose']) ?></strong> &nbsp;·&nbsp;
      Since: <?= date('h:i A', strtotime($active_sit['time_in'])) ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Feedback nudge banner -->
  <?php if ($fb_needed): ?>
  <div class="alert alert-warning" style="cursor:pointer;margin-bottom:20px;"
       onclick="document.getElementById('fbModal').classList.add('open')">
    <i class="fas fa-star"></i>
    <div>
      <strong>Rate your experience!</strong>
      Pending feedback for sit-in at <strong><?= htmlspecialchars($fb_needed['lab']) ?></strong>
      (<?= htmlspecialchars($fb_needed['purpose']) ?>).
      <span style="text-decoration:underline;margin-left:6px;">Click to rate →</span>
    </div>
  </div>
  <?php endif; ?>

  <div class="dash-outer">

    <!-- ── ROW 1: Student Info | Announcements | Rules ── -->
    <div class="dash-grid-top">

      <!-- STUDENT INFO -->
      <div class="card student-info-card">
        <div class="card-header"><i class="fas fa-id-card"></i> Student Information</div>
        <div class="avatar-wrap">
          <?php if ($s['profile_pic'] && file_exists('../images/profiles/'.$s['profile_pic'])): ?>
            <img src="../images/profiles/<?= htmlspecialchars($s['profile_pic']) ?>" alt="Profile" class="avatar">
          <?php else: ?>
            <div class="avatar-placeholder"><i class="fas fa-user"></i></div>
          <?php endif; ?>
        </div>
        <div class="info-list">
          <div class="info-item">
            <i class="fas fa-user"></i>
            <span><span class="info-label">Name:</span> <?= htmlspecialchars(fullName($s)) ?></span>
          </div>
          <div class="info-item">
            <i class="fas fa-id-card"></i>
            <span><span class="info-label">ID:</span> <?= htmlspecialchars($s['id_number']) ?></span>
          </div>
          <div class="info-item">
            <i class="fas fa-graduation-cap"></i>
            <span><span class="info-label">Course:</span> <?= htmlspecialchars($s['course']) ?></span>
          </div>
          <div class="info-item">
            <i class="fas fa-sort-numeric-up"></i>
            <span><span class="info-label">Year:</span> <?= $s['year_level'] ?></span>
          </div>
          <div class="info-item">
            <i class="fas fa-envelope"></i>
            <span><span class="info-label">Email:</span> <?= htmlspecialchars($s['email']) ?></span>
          </div>
          <div class="info-item">
            <i class="fas fa-map-marker-alt"></i>
            <span><span class="info-label">Address:</span> <?= htmlspecialchars($s['address'] ?: '—') ?></span>
          </div>
          <div class="info-item">
            <i class="fas fa-clock"></i>
            <span>
              <span class="info-label">Session:</span>
              <span style="background:<?= $s['remaining_session'] <= 5 ? 'linear-gradient(135deg,#7f0018,#ef233c)' : 'linear-gradient(135deg,#023047,#219ebc)' ?>;color:#fff;border-radius:20px;padding:2px 12px;font-size:0.82rem;font-weight:700;margin-left:4px;">
                <?= $s['remaining_session'] ?>
              </span>
              <?php if ($low_session): ?>
              <span style="color:#ef233c;font-size:0.7rem;font-weight:700;margin-left:4px;">LOW!</span>
              <?php endif; ?>
            </span>
          </div>
        </div>
        <div style="padding:0 16px 18px;">
          <a href="edit_profile.php" class="btn btn-primary btn-sm" style="width:100%;justify-content:center;">
            <i class="fas fa-user-edit"></i> Edit Profile
          </a>
        </div>
      </div>

      <!-- ANNOUNCEMENTS -->
      <div class="card">
        <div class="card-header"><i class="fas fa-bullhorn"></i> Announcement</div>
        <div class="card-body">
          <div class="announcement-list">
            <?php $cnt = 0; while ($a = $ann->fetch_assoc()): $cnt++; ?>
            <div class="announcement-item">
              <div class="announcement-meta">
                <i class="fas fa-user-shield" style="font-size:0.68rem;"></i>
                &nbsp;<?= htmlspecialchars($a['posted_by']) ?>
                &nbsp;|&nbsp;
                <?= date('Y-M-d', strtotime($a['created_at'])) ?>
              </div>
              <?php if (trim($a['content'])): ?>
              <div class="announcement-text"><?= nl2br(htmlspecialchars($a['content'])) ?></div>
              <?php endif; ?>
            </div>
            <?php endwhile; ?>
            <?php if (!$cnt): ?>
            <p style="text-align:center;color:var(--text-muted);padding:24px;font-size:0.84rem;">
              <i class="fas fa-bullhorn" style="font-size:2rem;display:block;margin-bottom:8px;opacity:0.25;"></i>
              No announcements yet.
            </p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- RULES -->
      <div class="card">
        <div class="card-header"><i class="fas fa-book-open"></i> Rules and Regulation</div>
        <div class="card-body">
          <div class="rules-box">
            <h3>University of Cebu</h3>
            <h4>COLLEGE OF INFORMATION & COMPUTER STUDIES</h4>
            <h5>LABORATORY RULES AND REGULATIONS</h5>
            <p>To avoid embarrassment and maintain camaraderie with your friends and superiors at our laboratories, please observe the following:</p>
            <p><strong>1.</strong> Maintain silence, proper decorum, and discipline inside the laboratory. Mobile phones, walkmans and other personal pieces of equipment must be switched off.</p>
            <p><strong>2.</strong> Games are not allowed inside the lab. This includes computer-related games, card games and other games that may disturb the operation of the lab.</p>
            <p><strong>3.</strong> Surfing the Internet is allowed only with the permission of the instructor. Downloading and installing of software are strictly prohibited.</p>
            <p><strong>4.</strong> Getting access to other websites not related to the course (especially pornographic and illicit sites) is strictly prohibited.</p>
            <p><strong>5.</strong> Deleting computer files and changing the set-up of the computer is a major offense.</p>
            <p><strong>6.</strong> Observe computer time usage carefully. A fifteen-minute allowance is given for each use. Otherwise, the unit will be given to those who wish to "sit-in".</p>
            <p><strong>7.</strong> Observe proper decorum while inside the laboratory:</p>
            <p style="padding-left:12px;">
              • Do not get inside the lab unless the instructor is present.<br>
              • All bags, knapsacks, and the likes must be deposited at the counter.<br>
              • Follow the seating arrangement of your instructor.<br>
              • At the end of class, all software programs must be closed.<br>
              • Return all chairs to their proper places after using.
            </p>
            <p><strong>8.</strong> Chewing gum, eating, drinking, smoking, and other forms of vandalism are prohibited inside the lab.</p>
            <p><strong>9.</strong> Anyone causing a continual disturbance will be asked to leave the lab.</p>
            <p><strong>10.</strong> Persons exhibiting hostile or threatening behavior such as yelling, swearing, or disregarding requests made by lab personnel will be asked to leave the lab.</p>
            <p><strong>11.</strong> For serious offense, the lab personnel may call the Civil Security Office (CSU) for assistance.</p>
            <p><strong>12.</strong> Any technical problem or difficulty must be addressed to the laboratory supervisor, student assistant or instructor immediately.</p>
            <h5>DISCIPLINARY ACTION</h5>
            <p>• <strong>First Offense</strong> — The Head or the Dean recommends suspension from classes.</p>
            <p>• <strong>Second and Subsequent Offenses</strong> — A heavier sanction will be endorsed to the Guidance Center.</p>
          </div>
        </div>
      </div>

    </div><!-- /dash-grid-top -->

    <!-- ── ROW 2: Quick Actions (full width) ── -->
    <div class="dash-grid-bottom">
      <div class="card">
        <div class="card-header"><i class="fas fa-bolt"></i> Quick Actions</div>
        <div class="card-body">
          <div class="quick-actions-grid">

            <a href="history.php" class="qa-btn">
              <div class="qa-icon navy"><i class="fas fa-history"></i></div>
              View History
            </a>

            <a href="history.php#summary" class="qa-btn">
              <div class="qa-icon gold"><i class="fas fa-chart-bar"></i></div>
              Summary
            </a>

            <a href="lab_availability.php" class="qa-btn">
              <div class="qa-icon sky"><i class="fas fa-desktop"></i></div>
              Software / Lab
            </a>

            <a href="testimonials.php" class="qa-btn">
              <div class="qa-icon purple"><i class="fas fa-quote-left"></i></div>
              Testimonials
            </a>

            <a href="leaderboard.php" class="qa-btn">
              <div class="qa-icon green"><i class="fas fa-trophy"></i></div>
              View Leaderboard
            </a>

            <?php if ($fb_needed): ?>
            <button class="qa-btn" onclick="document.getElementById('fbModal').classList.add('open')">
              <div class="qa-icon red"><i class="fas fa-star"></i></div>
              Feedback
              <span style="background:#ef233c;color:#fff;font-size:0.6rem;font-weight:800;padding:1px 6px;border-radius:20px;margin-top:2px;">1 pending</span>
            </button>
            <?php else: ?>
            <a href="history.php" class="qa-btn">
              <div class="qa-icon red"><i class="fas fa-star"></i></div>
              Feedback
            </a>
            <?php endif; ?>

          </div>
        </div>
      </div>
    </div><!-- /dash-grid-bottom -->

  </div><!-- /dash-outer -->
</div><!-- /page-content -->

<!-- FEEDBACK MODAL -->
<?php if ($fb_needed): ?>
<div class="modal-overlay" id="fbModal">
  <div class="modal-box">
    <div class="modal-header">
      <span><i class="fas fa-star"></i> Rate Your Sit-in Experience</span>
      <button class="modal-close" onclick="document.getElementById('fbModal').classList.remove('open')">×</button>
    </div>
    <div class="modal-body">
      <div style="background:var(--alice);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:18px;font-size:0.83rem;color:var(--text-soft);display:flex;gap:16px;flex-wrap:wrap;">
        <span><i class="fas fa-door-open" style="color:var(--cerulean);"></i> <strong><?= htmlspecialchars($fb_needed['lab']) ?></strong></span>
        <span><i class="fas fa-code" style="color:var(--cerulean);"></i> <?= htmlspecialchars($fb_needed['purpose']) ?></span>
        <span><i class="fas fa-calendar" style="color:var(--cerulean);"></i> <?= date('M d, Y', strtotime($fb_needed['time_out'])) ?></span>
      </div>
      <form method="POST" action="feedback.php">
        <input type="hidden" name="sitin_id" value="<?= $fb_needed['id'] ?>">
        <div class="form-group">
          <label style="margin-bottom:10px;display:block;font-weight:600;color:var(--text);">Your Rating *</label>
          <div style="display:flex;flex-direction:row-reverse;justify-content:flex-end;gap:6px;" id="starRating">
            <?php for ($i = 5; $i >= 1; $i--): ?>
            <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" style="display:none;" <?= $i===5?'required':'' ?>>
            <label for="star<?= $i ?>" style="font-size:2rem;cursor:pointer;color:#e2e8f0;transition:color 0.15s;" title="<?= $i ?> star<?= $i>1?'s':'' ?>">★</label>
            <?php endfor; ?>
          </div>
          <p style="font-size:0.74rem;color:var(--text-muted);margin-top:6px;">Click a star to rate (5 = Excellent)</p>
        </div>
        <div class="form-group">
          <label>Comment <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
          <textarea name="comment" class="form-control" rows="3" placeholder="Share your experience..."></textarea>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:4px;">
          <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('fbModal').classList.remove('open')">Later</button>
          <button type="submit" class="btn btn-gold btn-sm"><i class="fas fa-paper-plane"></i> Submit Feedback</button>
        </div>
      </form>
    </div>
  </div>
</div>
<style>
#starRating input:checked ~ label,
#starRating label:hover,
#starRating label:hover ~ label { color: #ffb703 !important; }
</style>
<?php endif; ?>

<!-- WATERMARK BACKGROUND -->
<div style="position:fixed;bottom:0;left:0;right:0;top:56px;pointer-events:none;z-index:0;display:flex;align-items:center;justify-content:center;overflow:hidden;">
  <div style="position:relative;width:820px;height:480px;opacity:0.30;">
    <img src="../images/uc_logo.png" alt="" style="position:absolute;right:0;top:50%;transform:translateY(-50%);width:480px;height:480px;object-fit:contain;border-radius:50%;filter:grayscale(1%);z-index:1;">
    <img src="../images/ccs_logo.png" alt="" style="position:absolute;left:0;top:50%;transform:translateY(-50%);width:480px;height:480px;object-fit:contain;border-radius:50%;filter:grayscale(1%);z-index:2;">
  </div>
</div>

<footer>&copy; <?= date('Y') ?> University of Cebu — College of Computer Studies. All rights reserved.</footer>

<script>
// ── DARK MODE ──
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

// ── NOTIFICATION TOGGLE ──
function toggleNotif(e) {
  e.stopPropagation();
  document.getElementById('notifWrap').classList.toggle('open');
}
document.addEventListener('click', function(e) {
  const wrap = document.getElementById('notifWrap');
  if (wrap && !wrap.contains(e.target)) wrap.classList.remove('open');
});
</script>
</body>
</html>