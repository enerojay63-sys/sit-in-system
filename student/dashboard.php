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

// ── Stats Card Data ──
// Leaderboard rank
$rank_res = $conn->query("
  SELECT s2.id, COUNT(sr2.id) as total
  FROM students s2
  LEFT JOIN sitin_records sr2 ON sr2.student_id=s2.id AND sr2.status='done'
  GROUP BY s2.id
  HAVING total > 0
  ORDER BY total DESC
");
$leaderboard_rank = null;
$rank_pos = 1;
while ($rr = $rank_res->fetch_assoc()) {
  if ($rr['id'] == $s['id']) { $leaderboard_rank = $rank_pos; break; }
  $rank_pos++;
}

// Points
$total_points = (int)($s['total_points'] ?? 0);

// Total sessions
$total_sessions = $conn->query("SELECT COUNT(*) FROM sitin_records WHERE student_id={$s['id']} AND status='done'")->fetch_row()[0];

// ── Notifications ──
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
      overflow: hidden;
    }
    .notif-wrap.open .notif-panel { display: block; }
    .notif-header {
      background: linear-gradient(135deg, #023047, #2b2d42); color: #fff;
      padding: 12px 16px; font-size: 0.84rem; font-weight: 700;
      display: flex; align-items: center; justify-content: space-between;
      border-bottom: 2px solid #219ebc;
    }
    .notif-header .count-pill { background: #ef233c; color: #fff; font-size: 0.7rem; font-weight: 800; padding: 2px 8px; border-radius: 20px; }
    .notif-list { max-height: 360px; overflow-y: auto; }
    .notif-item { display: flex; gap: 12px; padding: 13px 16px; border-bottom: 1px solid #f0f4f8; transition: background 0.14s; cursor: default; }
    .notif-item:last-child { border-bottom: none; }
    .notif-item:hover { background: #f7fbff; }
    .notif-item.clickable { cursor: pointer; }
    .notif-item.clickable:hover { background: #e8f4fa; }
    .notif-icon-wrap { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0; margin-top: 1px; }
    .notif-content { flex: 1; min-width: 0; }
    .notif-title { font-size: 0.81rem; font-weight: 700; color: #2b2d42; margin-bottom: 3px; line-height: 1.3; }
    .notif-msg   { font-size: 0.76rem; color: #4a5568; line-height: 1.45; margin-bottom: 4px; }
    .notif-time  { font-size: 0.7rem; color: #8d99ae; font-weight: 500; }
    .notif-empty { padding: 28px 16px; text-align: center; color: #8d99ae; font-size: 0.83rem; }
    .notif-footer { padding: 10px 16px; background: #f7f9fb; border-top: 1px solid #edf2f4; text-align: center; font-size: 0.75rem; }
    .notif-footer a { color: #219ebc; text-decoration: none; font-weight: 600; }

    /* Settings dropdown */
    .settings-wrap { position: relative; }
    .settings-btn {
      display: flex; align-items: center; gap: 5px;
      color: rgba(255,255,255,0.82); text-decoration: none;
      font-size: 0.74rem; font-weight: 500; padding: 5px 8px;
      border-radius: var(--radius-sm); transition: all 0.17s;
      cursor: pointer; background: none; border: none; font-family: inherit;
      white-space: nowrap;
    }
    .settings-btn:hover { background: rgba(142,202,230,0.18); color: var(--sky); }
    .settings-panel {
      display: none; position: absolute; top: calc(100% + 6px); right: 0;
      background: var(--surface); border: 1px solid var(--border);
      border-radius: var(--radius-sm); min-width: 200px;
      box-shadow: var(--shadow-lg); z-index: 500; overflow: hidden;
    }
    .settings-wrap.open .settings-panel { display: block; }
    .settings-panel a {
      display: flex; align-items: center; gap: 10px;
      padding: 11px 16px; color: var(--text); text-decoration: none;
      font-size: 0.82rem; font-weight: 500; transition: background 0.14s;
      border-bottom: 1px solid var(--border);
    }
    .settings-panel a:last-child { border-bottom: none; }
    .settings-panel a:hover { background: #e8f4fa; color: var(--prussian); }
    html.dark .settings-panel { background: #1a1d27; border-color: #3d4060; }
    html.dark .settings-panel a { color: #c8d0dc; border-color: rgba(255,255,255,0.07); }
    html.dark .settings-panel a:hover { background: #242838; color: #edf2f4; }
    html.dark .settings-panel .settings-icon { color: var(--cerulean); }

    /* ── DASHBOARD LAYOUT ── */
    .dash-outer { display: flex; flex-direction: column; gap: 20px; }
    .dash-grid-top {
      display: grid;
      grid-template-columns: 240px 1fr 300px;
      gap: 20px;
      align-items: start;
    }
    /* 2-row right column: announcements + stats card */
    .right-col { display: flex; flex-direction: column; gap: 16px; }

    /* ── STATS MINI CARD ── */
    .stats-mini-card {
      background: var(--surface);
      border-radius: var(--radius);
      box-shadow: var(--shadow-md);
      overflow: hidden;
      border: 1px solid rgba(141,153,174,0.2);
    }
    html.dark .stats-mini-card { background: #1a1d27; border-color: rgba(255,255,255,0.07); }
    .stats-mini-header {
      background: linear-gradient(135deg, var(--prussian), var(--cadet));
      color: #fff; padding: 10px 14px; font-size: 0.82rem; font-weight: 600;
      display: flex; align-items: center; gap: 7px; border-bottom: 2px solid var(--cerulean);
    }
    .stats-mini-body { padding: 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .stat-mini-box {
      background: var(--alice); border-radius: var(--radius-sm);
      padding: 10px 12px; border: 1px solid var(--border); text-align: center;
      transition: transform 0.18s;
    }
    .stat-mini-box:hover { transform: translateY(-2px); }
    html.dark .stat-mini-box { background: #242838; border-color: #3d4060; }
    .smb-val { font-size: 1.3rem; font-weight: 800; color: var(--prussian); line-height: 1; }
    html.dark .smb-val { color: #edf2f4; }
    .smb-lbl { font-size: 0.68rem; color: var(--text-muted); margin-top: 4px; font-weight: 500; }
    .smb-icon { font-size: 1rem; margin-bottom: 4px; }

    /* Convert points button */
    .convert-btn {
      display: flex; align-items: center; justify-content: center; gap: 6px;
      width: 100%; padding: 8px; margin-top: 10px;
      background: linear-gradient(135deg, var(--orange), var(--honey));
      color: var(--prussian); font-weight: 700; font-size: 0.75rem;
      border: none; border-radius: var(--radius-sm); cursor: pointer;
      font-family: inherit; transition: all 0.18s;
    }
    .convert-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(255,183,3,0.4); }

    /* ── QUICK ACTIONS ── */
    .quick-actions-grid {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 12px;
    }
    .qa-btn {
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      gap: 10px; padding: 18px 10px; border-radius: var(--radius);
      text-decoration: none; font-size: 0.78rem; font-weight: 600;
      color: var(--prussian); background: var(--alice); border: 1.5px solid var(--border);
      transition: all 0.2s; cursor: pointer; font-family: inherit; text-align: center; line-height: 1.3;
    }
    .qa-btn:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(2,48,71,0.13); border-color: var(--cerulean); color: var(--prussian); }
    .qa-icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; color: #fff; flex-shrink: 0; }
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

    /* Convert Modal */
    html.dark .modal-box { background: #1a1d27; }
    html.dark .modal-header { background: linear-gradient(135deg,#05101a,#0f1117); }
    html.dark .modal-body { color: #c8d0dc; }
    html.dark .modal-footer { background: #131620; }
    html.dark .form-control { background: #242838; border-color: #3d4060; color: #edf2f4; }
    html.dark .alert-info    { background: rgba(33,158,188,0.12); color: #8ecae6; border-color: rgba(33,158,188,0.3); }
    html.dark .alert-warning { background: rgba(251,133,0,0.12); color: #ffb703; border-color: rgba(251,133,0,0.3); }

    /* Star rating */
    #starRating input:checked ~ label,
    #starRating label:hover,
    #starRating label:hover ~ label { color: #ffb703 !important; }

    @media (max-width: 1100px) {
      .dash-grid-top { grid-template-columns: 220px 1fr; }
      .right-col { grid-column: 1/-1; }
      .quick-actions-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 700px) {
      .dash-grid-top { grid-template-columns: 1fr; }
      .quick-actions-grid { grid-template-columns: repeat(2, 1fr); }
      .stats-mini-body { grid-template-columns: 1fr 1fr; }
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
        <i class="fas fa-bell"></i> Notifications
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
            You're all caught up!
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
          <a href="history.php"><i class="fas fa-history"></i> View history</a>
          &nbsp;·&nbsp;
          <a href="reservation.php"><i class="fas fa-calendar"></i> Reservations</a>
        </div>
      </div>
    </div>

    <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Home</a>
    <a href="history.php"><i class="fas fa-history"></i> History</a>
    <a href="reservation.php"><i class="fas fa-calendar-plus"></i> Reservation</a>
    <a href="leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a>

    <!-- SETTINGS DROPDOWN -->
    <div class="settings-wrap" id="settingsWrap">
      <button class="settings-btn" onclick="toggleSettings(event)">
        <i class="fas fa-cog"></i> Settings
        <i class="fas fa-caret-down" style="font-size:0.6rem;"></i>
      </button>
      <div class="settings-panel" id="settingsPanel">
        <a href="edit_profile.php"><i class="fas fa-user-edit" class="settings-icon" style="color:var(--cerulean);"></i> Edit Profile</a>
        <a href="lab_availability.php"><i class="fas fa-desktop" style="color:var(--cerulean);"></i> Lab Availability</a>
        <a href="sitin_summary.php"><i class="fas fa-chart-bar" style="color:var(--cerulean);"></i> Sit-in Summary</a>
        <a href="testimonials.php"><i class="fas fa-quote-left" style="color:var(--cerulean);"></i> Testimonials</a>
      </div>
    </div>

    <button class="dark-toggle" onclick="toggleDark()" title="Toggle dark mode">
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

    <!-- ── ROW 1: Student Info | Announcements + Stats | Rules ── -->
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
          <div class="info-item"><i class="fas fa-user"></i><span><span class="info-label">Name:</span> <?= htmlspecialchars(fullName($s)) ?></span></div>
          <div class="info-item"><i class="fas fa-id-card"></i><span><span class="info-label">ID:</span> <?= htmlspecialchars($s['id_number']) ?></span></div>
          <div class="info-item"><i class="fas fa-graduation-cap"></i><span><span class="info-label">Course:</span> <?= htmlspecialchars($s['course']) ?></span></div>
          <div class="info-item"><i class="fas fa-sort-numeric-up"></i><span><span class="info-label">Year:</span> <?= $s['year_level'] ?></span></div>
          <div class="info-item"><i class="fas fa-envelope"></i><span><span class="info-label">Email:</span> <?= htmlspecialchars($s['email']) ?></span></div>
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

      <!-- CENTER: Announcements + Stats Card -->
      <div class="right-col">

        <!-- ANNOUNCEMENTS -->
        <div class="card" style="flex:1;">
          <div class="card-header"><i class="fas fa-bullhorn"></i> Announcement</div>
          <div class="card-body" style="max-height:220px;overflow-y:auto;">
            <div class="announcement-list">
              <?php $cnt = 0; while ($a = $ann->fetch_assoc()): $cnt++; ?>
              <div class="announcement-item">
                <div class="announcement-meta">
                  <i class="fas fa-user-shield" style="font-size:0.68rem;"></i>
                  &nbsp;<?= htmlspecialchars($a['posted_by']) ?>
                  &nbsp;|&nbsp;<?= date('Y-M-d', strtotime($a['created_at'])) ?>
                </div>
                <?php if (trim($a['content'])): ?>
                <div class="announcement-text"><?= nl2br(htmlspecialchars($a['content'])) ?></div>
                <?php endif; ?>
              </div>
              <?php endwhile; ?>
              <?php if (!$cnt): ?>
              <p style="text-align:center;color:var(--text-muted);padding:18px;font-size:0.84rem;">
                <i class="fas fa-bullhorn" style="font-size:1.5rem;display:block;margin-bottom:6px;opacity:0.25;"></i>
                No announcements yet.
              </p>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- STATS MINI CARD: Sessions, Points, Rank, Convert -->
        <div class="stats-mini-card">
          <div class="stats-mini-header"><i class="fas fa-chart-bar"></i> My Stats</div>
          <div class="stats-mini-body">
            <div class="stat-mini-box">
              <div class="smb-icon" style="color:var(--cerulean);"><i class="fas fa-desktop"></i></div>
              <div class="smb-val"><?= $s['remaining_session'] ?></div>
              <div class="smb-lbl">Remaining Sessions</div>
            </div>
            <div class="stat-mini-box">
              <div class="smb-icon" style="color:var(--honey);"><i class="fas fa-star"></i></div>
              <div class="smb-val"><?= $total_points ?></div>
              <div class="smb-lbl">Reward Points</div>
            </div>
            <div class="stat-mini-box">
              <div class="smb-icon" style="color:var(--cerulean);"><i class="fas fa-trophy"></i></div>
              <div class="smb-val"><?= $leaderboard_rank ? '#'.$leaderboard_rank : '—' ?></div>
              <div class="smb-lbl">Leaderboard Rank</div>
            </div>
            <div class="stat-mini-box">
              <div class="smb-icon" style="color:#16a34a;"><i class="fas fa-check-circle"></i></div>
              <div class="smb-val"><?= $total_sessions ?></div>
              <div class="smb-lbl">Done Sessions</div>
            </div>
          </div>
          <div style="padding:0 12px 12px;">
            <button class="convert-btn" onclick="document.getElementById('convertModal').classList.add('open')"
                    <?= $total_points < 1 ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : '' ?>>
              <i class="fas fa-exchange-alt"></i>
              Convert Points to Session
              <?php if ($total_points > 0): ?>
              <span style="background:rgba(0,0,0,0.2);border-radius:10px;padding:1px 7px;font-size:0.7rem;"><?= $total_points ?> pts</span>
              <?php endif; ?>
            </button>
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

    <!-- ── ROW 2: Quick Actions ── -->
    <div class="card">
      <div class="card-header"><i class="fas fa-bolt"></i> Quick Actions</div>
      <div class="card-body">
        <div class="quick-actions-grid">

          <a href="history.php" class="qa-btn">
            <div class="qa-icon navy"><i class="fas fa-history"></i></div>
            View History
          </a>

          <a href="lab_availability.php" class="qa-btn">
            <div class="qa-icon sky"><i class="fas fa-desktop"></i></div>
            Lab Availability
          </a>

          <a href="testimonials.php" class="qa-btn">
            <div class="qa-icon purple"><i class="fas fa-quote-left"></i></div>
            Testimonials
          </a>

          <a href="leaderboard.php" class="qa-btn">
            <div class="qa-icon green"><i class="fas fa-trophy"></i></div>
            Leaderboard
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
            <label for="star<?= $i ?>" style="font-size:2rem;cursor:pointer;color:#e2e8f0;transition:color 0.15s;">★</label>
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
<?php endif; ?>

<!-- CONVERT POINTS MODAL -->
<div class="modal-overlay" id="convertModal">
  <div class="modal-box" style="max-width:420px;">
    <div class="modal-header">
      <span><i class="fas fa-exchange-alt"></i> Convert Points to Session</span>
      <button class="modal-close" onclick="document.getElementById('convertModal').classList.remove('open')">×</button>
    </div>
    <div class="modal-body">
      <div class="alert alert-info" style="margin-bottom:16px;">
        <i class="fas fa-info-circle"></i>
        <div>
          <strong>Points Balance:</strong> <?= $total_points ?> points<br>
          <span style="font-size:0.8rem;">Every <strong>100 points</strong> = 1 additional session</span>
        </div>
      </div>
      <?php if ($total_points >= 100): ?>
      <form method="POST" action="convert_points.php">
        <div class="form-group">
          <label>How many sessions to add?</label>
          <input type="number" name="sessions_to_add" class="form-control" min="1" max="<?= floor($total_points/100) ?>"
                 value="1" required>
          <p style="font-size:0.73rem;color:var(--text-muted);margin-top:5px;">
            Max: <?= floor($total_points/100) ?> session(s) from <?= $total_points ?> points
          </p>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;">
          <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('convertModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="btn btn-gold btn-sm"><i class="fas fa-exchange-alt"></i> Convert</button>
        </div>
      </form>
      <?php else: ?>
      <div style="text-align:center;padding:20px;color:var(--text-muted);">
        <i class="fas fa-star" style="font-size:2.5rem;display:block;margin-bottom:10px;color:var(--honey);opacity:0.5;"></i>
        <p style="font-size:0.85rem;">You need at least <strong>100 points</strong> to convert.<br>Keep earning points through sit-in sessions!</p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- WATERMARK -->
<div style="position:fixed;bottom:0;left:0;right:0;top:56px;pointer-events:none;z-index:0;display:flex;align-items:center;justify-content:center;overflow:hidden;">
  <div style="position:relative;width:820px;height:480px;opacity:0.25;">
    <img src="../images/uc_logo.png" alt="" style="position:absolute;right:0;top:50%;transform:translateY(-50%);width:480px;height:480px;object-fit:contain;z-index:1;">
    <img src="../images/ccs_logo.png" alt="" style="position:absolute;left:0;top:50%;transform:translateY(-50%);width:480px;height:480px;object-fit:contain;z-index:2;">
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

// Notification
function toggleNotif(e) {
  e.stopPropagation();
  document.getElementById('settingsWrap').classList.remove('open');
  document.getElementById('notifWrap').classList.toggle('open');
}
// Settings
function toggleSettings(e) {
  e.stopPropagation();
  document.getElementById('notifWrap').classList.remove('open');
  document.getElementById('settingsWrap').classList.toggle('open');
}
// Close on outside click
document.addEventListener('click', function(e) {
  const nw = document.getElementById('notifWrap');
  const sw = document.getElementById('settingsWrap');
  if (nw && !nw.contains(e.target)) nw.classList.remove('open');
  if (sw && !sw.contains(e.target)) sw.classList.remove('open');
});
</script>
</body>
</html>