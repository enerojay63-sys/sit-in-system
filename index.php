<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
if (isLoggedInStudent()) { header('Location: student/dashboard.php'); exit; }
if (isLoggedInAdmin())   { header('Location: admin/dashboard.php');  exit; }

$error = $success = '';
$tab = 'login';

// ── REGISTER ──
if (isset($_POST['register'])) {
  $tab        = 'register';
  $id_number  = trim($_POST['id_number']);
  $lastname   = trim($_POST['lastname']);
  $firstname  = trim($_POST['firstname']);
  $midname    = trim($_POST['midname']);
  $course     = $_POST['course'];
  $year_level = (int)$_POST['year_level'];
  $email      = trim($_POST['email']);
  $address    = trim($_POST['address']);
  $password   = $_POST['password'];
  $confirm    = $_POST['confirm_password'];

  if ($password !== $confirm) $error = 'Passwords do not match.';
  elseif (strlen($password) < 6) $error = 'Password must be at least 6 characters.';
  else {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO students (id_number,lastname,firstname,midname,course,year_level,email,address,password) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param('sssssisss', $id_number,$lastname,$firstname,$midname,$course,$year_level,$email,$address,$hash);
    if ($stmt->execute()) { $success = 'Account created! You can now log in.'; $tab = 'login'; }
    else $error = 'ID Number or Email already exists.';
    $stmt->close();
  }
}

// ── LOGIN ──
if (isset($_POST['login'])) {
  $identifier = trim($_POST['identifier']);
  $password   = $_POST['password'];

  $stmt = $conn->prepare("SELECT * FROM students WHERE id_number=?");
  $stmt->bind_param('s', $identifier); $stmt->execute();
  $student = $stmt->get_result()->fetch_assoc(); $stmt->close();

  if ($student && password_verify($password, $student['password'])) {
    $_SESSION['student_id']   = $student['id'];
    $_SESSION['student_data'] = $student;
    header('Location: student/dashboard.php'); exit;
  }

  $stmt = $conn->prepare("SELECT * FROM admins WHERE username=?");
  $stmt->bind_param('s', $identifier); $stmt->execute();
  $admin = $stmt->get_result()->fetch_assoc(); $stmt->close();

  if ($admin && password_verify($password, $admin['password'])) {
    $_SESSION['admin_id']   = $admin['id'];
    $_SESSION['admin_data'] = $admin;
    header('Location: admin/dashboard.php'); exit;
  }

  $error = 'Invalid ID Number or Password.';
}

// ── Top Students (leaderboard) ──
$top_students = $conn->query("
  SELECT s.id_number, s.firstname, s.lastname, s.course, s.year_level,
         COUNT(sr.id) as total_sitins,
         ROUND(SUM(TIMESTAMPDIFF(MINUTE,sr.time_in,IFNULL(sr.time_out,sr.time_in)))/60,1) as total_hours
  FROM students s
  LEFT JOIN sitin_records sr ON sr.student_id=s.id AND sr.status='done'
  GROUP BY s.id
  HAVING total_sitins > 0
  ORDER BY total_sitins DESC, total_hours DESC
  LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// ── Approved Testimonials ──
$testimonials = $conn->query("
  SELECT t.message, t.rating, t.created_at,
         s.firstname, s.lastname, s.id_number, s.course
  FROM testimonials t
  JOIN students s ON t.student_id = s.id
  WHERE t.status = 'approved'
  ORDER BY t.created_at DESC
  LIMIT 9
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>CCS Sit-in Monitoring System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/style.css">
  <style>
    body {
      background: linear-gradient(150deg, #023047 0%, #2b2d42 40%, #8d99ae 75%, #edf2f4 100%);
      background-attachment: fixed;
      display: flex; flex-direction: column; min-height: 100vh;
    }
    html.dark body {
      background: linear-gradient(150deg, #0a0d14 0%, #0f1117 40%, #1a1d27 100%);
    }
    body::before {
      content:''; display:block; height:4px;
      background: linear-gradient(90deg,#ef233c,#fb8500,#ffb703,#219ebc,#8ecae6);
      position:fixed; top:0; left:0; right:0; z-index:2000;
    }
    .land-nav {
      background: rgba(2,48,71,0.92); backdrop-filter:blur(14px);
      display:flex; align-items:center; justify-content:space-between;
      padding:0 32px; height:56px; border-bottom:2px solid #219ebc; margin-top:4px;
      position:sticky; top:4px; z-index:1000;
    }
    .land-nav .brand { color:#fff; font-size:0.95rem; font-weight:700; display:flex; align-items:center; gap:8px; }
    .land-nav-right  { display:flex; align-items:center; gap:8px; }
    .land-nav a {
      color:rgba(255,255,255,0.78); text-decoration:none; font-size:0.82rem;
      padding:6px 12px; border-radius:7px; transition:all .18s;
      display:inline-flex; align-items:center; gap:5px;
    }
    .land-nav a:hover { background:rgba(142,202,230,.18); color:#8ecae6; }
    .land-dark-btn {
      background:rgba(255,255,255,.1); border:1.5px solid rgba(255,255,255,.25);
      color:rgba(255,255,255,.8); border-radius:7px; padding:6px 10px;
      cursor:pointer; font-size:0.82rem; display:flex; align-items:center; gap:5px;
      font-family:inherit; transition:all .18s;
    }
    .land-dark-btn:hover { background:rgba(142,202,230,.18); border-color:#8ecae6; color:#8ecae6; }
    .land-main { flex:1; display:flex; align-items:center; justify-content:center; padding:40px 20px; }
    .land-wrap  { display:flex; align-items:center; gap:60px; width:100%; max-width:960px; }
    .logo-col   { flex-shrink:0; display:flex; flex-direction:column; align-items:center; gap:18px; }
    .logo-col img { width:220px; height:220px; object-fit:contain; filter:drop-shadow(0 8px 24px rgba(2,48,71,.5)); }
    .logo-col h1  { color:#edf2f4; font-size:1.05rem; font-weight:700; text-align:center; line-height:1.5; text-shadow:0 2px 10px rgba(0,0,0,.4); }
    .logo-col .tagline { color:#8ecae6; font-size:0.8rem; text-align:center; line-height:1.6; }
    .form-col { width:375px; flex-shrink:0; }
    .tab-hdr { display:flex; border-radius:12px 12px 0 0; overflow:hidden; }
    .tab-b {
      flex:1; padding:13px; background:rgba(43,45,66,.6);
      border:none; color:rgba(255,255,255,.6);
      font-family:inherit; font-size:0.85rem; font-weight:600;
      cursor:pointer; transition:all .2s;
      display:flex; align-items:center; justify-content:center; gap:6px;
    }
    .tab-b:hover  { background:rgba(43,45,66,.8); color:#8ecae6; }
    .tab-b.active { background:#edf2f4; color:#023047; border-top:3px solid #ffb703; }
    html.dark .tab-b.active { background:#1a1d27; color:#edf2f4; }
    .card-bx {
      background:#edf2f4; border-radius:0 0 12px 12px;
      padding:26px 26px 22px;
      box-shadow:0 20px 60px rgba(2,48,71,.4);
      border:1px solid rgba(141,153,174,.3); border-top:none;
    }
    html.dark .card-bx { background:#1a1d27; }
    .panel { display:none; } .panel.active { display:block; }
    .card-bx .form-group label { color:#2b2d42; font-size:0.78rem; font-weight:600; margin-bottom:6px; display:block; }
    html.dark .card-bx .form-group label { color:#c8d0dc; }
    .card-bx .form-control { background:#fff; border:1.5px solid #8d99ae; border-radius:7px; padding:10px 14px; font-family:inherit; font-size:0.85rem; color:#2b2d42; outline:none; width:100%; transition:all .18s; }
    html.dark .card-bx .form-control { background:#242838; border-color:#3d4060; color:#edf2f4; }
    .card-bx .form-control:focus { border-color:#219ebc; box-shadow:0 0 0 3px rgba(33,158,188,.15); }
    .card-bx .form-group { margin-bottom:16px; }
    .iw { position:relative; }
    .iw i.fi { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#8d99ae; font-size:0.8rem; pointer-events:none; }
    .iw .form-control { padding-left:34px; }
    .r2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .slbl { font-size:0.7rem; font-weight:700; color:#023047; text-transform:uppercase; letter-spacing:1px; margin:14px 0 10px; padding-bottom:6px; border-bottom:2px solid #219ebc; display:flex; align-items:center; gap:6px; }
    html.dark .slbl { color:#8ecae6; }
    .rem-row { display:flex; align-items:center; justify-content:space-between; margin:4px 0 16px; }
    .rem-row label { display:flex; align-items:center; gap:6px; font-size:0.78rem; color:#2b2d42; cursor:pointer; }
    html.dark .rem-row label { color:#c8d0dc; }
    .rem-row a { font-size:0.78rem; color:#219ebc; text-decoration:none; }
    .btn-land { width:100%; justify-content:center; padding:11px; font-size:0.88rem; border:none; border-radius:7px; font-family:inherit; font-weight:700; cursor:pointer; transition:all .18s; display:flex; align-items:center; gap:8px; background:linear-gradient(135deg,#023047,#219ebc); color:#fff; }
    .btn-land:hover { background:linear-gradient(135deg,#012030,#023047); box-shadow:0 4px 18px rgba(2,48,71,.35); transform:translateY(-1px); }
    .bl { text-align:center; margin-top:12px; font-size:0.79rem; color:#4a5568; }
    html.dark .bl { color:#8d99ae; }
    .bl a { color:#219ebc; text-decoration:none; font-weight:600; cursor:pointer; }
    .sbar  { height:4px; background:#8d99ae; border-radius:2px; margin-top:6px; overflow:hidden; }
    .sfill { height:100%; width:0; border-radius:2px; transition:width .3s, background .3s; }
    .public-section {
      background:rgba(2,48,71,.85); backdrop-filter:blur(14px);
      padding:60px 32px; border-top:2px solid rgba(142,202,230,.2);
    }
    html.dark .public-section { background:rgba(10,13,20,.9); }
    .section-heading {
      text-align:center; color:#edf2f4; font-size:1.3rem; font-weight:700;
      margin-bottom:8px; display:flex; align-items:center; justify-content:center; gap:10px;
    }
    .section-sub { text-align:center; color:#8ecae6; font-size:0.82rem; margin-bottom:36px; }
    .podium { display:flex; align-items:flex-end; justify-content:center; gap:16px; margin-bottom:36px; }
    .podium-item { display:flex; flex-direction:column; align-items:center; gap:8px; }
    .podium-base {
      border-radius:12px 12px 0 0; display:flex; flex-direction:column;
      align-items:center; justify-content:flex-end; padding:16px 20px 12px;
      min-width:120px; position:relative;
    }
    .podium-base.p1 { background:linear-gradient(135deg,#b8860b,#ffb703); height:130px; }
    .podium-base.p2 { background:linear-gradient(135deg,#6b7280,#9ca3af); height:100px; }
    .podium-base.p3 { background:linear-gradient(135deg,#7c4a1e,#cd7f32); height:80px; }
    .podium-rank   { font-size:1.6rem; margin-bottom:4px; }
    .podium-name   { font-weight:700; font-size:0.82rem; color:#fff; text-align:center; line-height:1.3; }
    .podium-course { font-size:0.68rem; color:rgba(255,255,255,.75); margin-top:2px; }
    .podium-count  { font-size:0.72rem; color:rgba(255,255,255,.85); margin-top:4px; font-weight:600; }
    .podium-avatar {
      width:52px; height:52px; border-radius:50%;
      background:linear-gradient(135deg,#023047,#219ebc);
      display:flex; align-items:center; justify-content:center;
      color:#fff; font-size:1.1rem; font-weight:700;
      border:3px solid rgba(255,255,255,.3);
      box-shadow:0 4px 14px rgba(0,0,0,.3);
    }
    .leaderboard-list { max-width:700px; margin:0 auto 48px; display:flex; flex-direction:column; gap:8px; }
    .lb-row {
      background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.1);
      border-radius:10px; padding:12px 16px; display:flex; align-items:center; gap:14px;
      transition:background .18s;
    }
    .lb-row:hover { background:rgba(255,255,255,.12); }
    .lb-rank   { font-size:0.85rem; font-weight:700; color:#8ecae6; width:24px; text-align:center; }
    .lb-avatar {
      width:36px; height:36px; border-radius:50%;
      background:linear-gradient(135deg,#023047,#219ebc);
      display:flex; align-items:center; justify-content:center;
      color:#fff; font-size:0.75rem; font-weight:700; flex-shrink:0;
    }
    .lb-info  { flex:1; }
    .lb-name  { font-weight:600; color:#edf2f4; font-size:0.85rem; }
    .lb-meta  { font-size:0.72rem; color:#8d99ae; margin-top:2px; }
    .lb-score { text-align:right; }
    .lb-sitins{ font-weight:700; color:#ffb703; font-size:0.9rem; }
    .lb-hours { font-size:0.7rem; color:#8d99ae; }
    .testi-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:14px; max-width:1100px; margin:0 auto; }
    .testi-card {
      background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.1);
      border-radius:12px; padding:16px; transition:background .18s;
    }
    .testi-card:hover { background:rgba(255,255,255,.12); }
    .testi-top  { display:flex; align-items:center; gap:10px; margin-bottom:10px; }
    .testi-av   {
      width:36px; height:36px; border-radius:50%;
      background:linear-gradient(135deg,#023047,#219ebc);
      display:flex; align-items:center; justify-content:center;
      color:#fff; font-size:0.72rem; font-weight:700; flex-shrink:0;
    }
    .testi-name { font-weight:700; color:#edf2f4; font-size:0.83rem; }
    .testi-meta { font-size:0.7rem; color:#8d99ae; }
    .testi-stars{ color:#ffb703; font-size:0.75rem; margin-left:auto; }
    .testi-msg  { font-size:0.8rem; color:rgba(237,242,244,.8); line-height:1.6; font-style:italic; }
    .testi-date { font-size:0.68rem; color:#8d99ae; margin-top:8px; }
    .land-footer {
      background:rgba(2,48,71,.95); border-top:1px solid rgba(142,202,230,.15);
      color:rgba(237,242,244,.5); text-align:center; padding:14px; font-size:0.73rem;
    }
    @media(max-width:780px){
      .land-wrap { flex-direction:column; gap:24px; }
      .logo-col img { width:120px; height:120px; }
      .form-col { width:100%; max-width:400px; }
      .podium { gap:8px; }
      .podium-base { min-width:90px; }
    }
  </style>
</head>
<body>

<nav class="land-nav">
  <span class="brand"><i class="fas fa-desktop" style="color:#8ecae6"></i> CCS Sit-in Monitoring System</span>
  <div class="land-nav-right">
    <a href="#leaderboard"><i class="fas fa-trophy"></i> Leaderboard</a>
    <a href="#testimonials"><i class="fas fa-quote-left"></i> Testimonials</a>
    <button class="land-dark-btn" onclick="toggleDark()" title="Toggle dark mode">
      <i class="fas fa-moon" id="darkIcon"></i>
    </button>
  </div>
</nav>

<div class="land-main">
  <div class="land-wrap">
    <div class="logo-col">
      <img src="images/uc_logo.png" alt="UC Logo">
      <h1>College of Computer Studies<br>Sit-in Monitoring System</h1>
      <p class="tagline">University of Cebu<br>Track your laboratory sessions with ease.</p>
    </div>

    <div class="form-col">
      <?php if ($error): ?>
      <div style="background:#ffe4e6;color:#d90429;border:1px solid #fca5a5;padding:11px 16px;border-radius:7px 7px 0 0;font-size:0.84rem;font-weight:500;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
      </div>
      <?php elseif ($success): ?>
      <div style="background:#dcfce7;color:#14532d;border:1px solid #86efac;padding:11px 16px;border-radius:7px 7px 0 0;font-size:0.84rem;font-weight:500;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
      </div>
      <?php endif; ?>

      <div class="tab-hdr">
        <button class="tab-b <?= $tab==='login'?'active':'' ?>" id="btn-login" onclick="switchTab('login')">
          <i class="fas fa-sign-in-alt"></i> Login
        </button>
        <button class="tab-b <?= $tab==='register'?'active':'' ?>" id="btn-register" onclick="switchTab('register')">
          <i class="fas fa-user-plus"></i> Register
        </button>
      </div>

      <div class="card-bx">
        <!-- LOGIN -->
        <div class="panel <?= $tab==='login'?'active':'' ?>" id="panel-login">
          <form method="POST">
            <div class="form-group">
              <label>ID Number</label>
              <div class="iw"><i class="fas fa-id-card fi"></i>
                <input type="text" name="identifier" class="form-control" placeholder="Enter your ID number" required autofocus>
              </div>
            </div>
            <div class="form-group">
              <label>Password</label>
              <div class="iw"><i class="fas fa-lock fi"></i>
                <input type="password" name="password" class="form-control" placeholder="Enter password" required>
              </div>
            </div>
            <div class="rem-row">
              <label><input type="checkbox" style="accent-color:#219ebc"> Remember me</label>
            </div>
            <button type="submit" name="login" class="btn-land"><i class="fas fa-sign-in-alt"></i> Login</button>
          </form>
          <p class="bl">No account? <a onclick="switchTab('register')">Register here</a></p>
        </div>

        <!-- REGISTER -->
        <div class="panel <?= $tab==='register'?'active':'' ?>" id="panel-register">
          <form method="POST">
            <div class="slbl"><i class="fas fa-user"></i> Personal Info</div>
            <div class="form-group">
              <label>ID Number *</label>
              <div class="iw"><i class="fas fa-id-card fi"></i>
                <input type="text" name="id_number" class="form-control" placeholder="e.g. 2024-00001" required>
              </div>
            </div>
            <div class="r2" style="margin-bottom:12px">
              <div class="form-group" style="margin-bottom:0"><label>Last Name *</label>
                <div class="iw"><i class="fas fa-user fi"></i><input type="text" name="lastname" class="form-control" placeholder="Last name" required></div></div>
              <div class="form-group" style="margin-bottom:0"><label>First Name *</label>
                <div class="iw"><i class="fas fa-user fi"></i><input type="text" name="firstname" class="form-control" placeholder="First name" required></div></div>
            </div>
            <div class="form-group"><label>Middle Name</label>
              <div class="iw"><i class="fas fa-user fi"></i><input type="text" name="midname" class="form-control" placeholder="Optional"></div></div>
            <div class="r2" style="margin-bottom:12px">
              <div class="form-group" style="margin-bottom:0"><label>Course *</label>
                <select name="course" class="form-control" required>
                  <option value="" disabled selected>Select</option>
                  <option>BSIT</option><option>BSCS</option><option>BSIS</option><option>ACT</option>
                </select></div>
              <div class="form-group" style="margin-bottom:0"><label>Year Level *</label>
                <select name="year_level" class="form-control" required>
                  <option value="" disabled selected>Select</option>
                  <option value="1">1st Year</option><option value="2">2nd Year</option>
                  <option value="3">3rd Year</option><option value="4">4th Year</option>
                </select></div>
            </div>
            <div class="form-group"><label>Email *</label>
              <div class="iw"><i class="fas fa-envelope fi"></i><input type="email" name="email" class="form-control" placeholder="your@email.com" required></div></div>
            <div class="form-group"><label>Address</label>
              <div class="iw"><i class="fas fa-map-marker-alt fi"></i><input type="text" name="address" class="form-control" placeholder="City / Barangay"></div></div>
            <div class="slbl"><i class="fas fa-lock"></i> Account Security</div>
            <div class="form-group"><label>Password *</label>
              <div class="iw"><i class="fas fa-lock fi"></i>
                <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" oninput="checkStr(this.value)" required></div>
              <div class="sbar"><div class="sfill" id="sbar"></div></div></div>
            <div class="form-group"><label>Confirm Password *</label>
              <div class="iw"><i class="fas fa-lock fi"></i>
                <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter password" required></div></div>
            <button type="submit" name="register" class="btn-land"><i class="fas fa-user-plus"></i> Create Account</button>
          </form>
          <p class="bl">Have an account? <a onclick="switchTab('login')">Login here</a></p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- LEADERBOARD + TESTIMONIALS -->
<div class="public-section" id="leaderboard">
  <div class="section-heading"><i class="fas fa-trophy" style="color:#ffb703"></i> Top Performing Students</div>
  <p class="section-sub">Students with the most completed sit-in sessions this semester</p>

  <?php if($top_students): ?>
  <?php if(count($top_students) >= 3): ?>
  <div class="podium">
    <div class="podium-item">
      <div class="podium-avatar"><?=strtoupper(substr($top_students[1]['firstname'],0,1).substr($top_students[1]['lastname'],0,1))?></div>
      <div class="podium-base p2">
        <div class="podium-rank">🥈</div>
        <div class="podium-name"><?=htmlspecialchars($top_students[1]['firstname'].' '.$top_students[1]['lastname'])?></div>
        <div class="podium-course"><?=$top_students[1]['course']?></div>
        <div class="podium-count"><?=$top_students[1]['total_sitins']?> sit-ins</div>
      </div>
    </div>
    <div class="podium-item">
      <div class="podium-avatar" style="width:60px;height:60px;font-size:1.2rem;border-color:#ffb703"><?=strtoupper(substr($top_students[0]['firstname'],0,1).substr($top_students[0]['lastname'],0,1))?></div>
      <div class="podium-base p1">
        <div class="podium-rank">🥇</div>
        <div class="podium-name"><?=htmlspecialchars($top_students[0]['firstname'].' '.$top_students[0]['lastname'])?></div>
        <div class="podium-course"><?=$top_students[0]['course']?></div>
        <div class="podium-count"><?=$top_students[0]['total_sitins']?> sit-ins</div>
      </div>
    </div>
    <div class="podium-item">
      <div class="podium-avatar"><?=strtoupper(substr($top_students[2]['firstname'],0,1).substr($top_students[2]['lastname'],0,1))?></div>
      <div class="podium-base p3">
        <div class="podium-rank">🥉</div>
        <div class="podium-name"><?=htmlspecialchars($top_students[2]['firstname'].' '.$top_students[2]['lastname'])?></div>
        <div class="podium-course"><?=$top_students[2]['course']?></div>
        <div class="podium-count"><?=$top_students[2]['total_sitins']?> sit-ins</div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php if(count($top_students) > 3): ?>
  <div class="leaderboard-list">
    <?php foreach(array_slice($top_students,3) as $i=>$st): ?>
    <div class="lb-row">
      <div class="lb-rank">#<?=$i+4?></div>
      <div class="lb-avatar"><?=strtoupper(substr($st['firstname'],0,1).substr($st['lastname'],0,1))?></div>
      <div class="lb-info">
        <div class="lb-name"><?=htmlspecialchars($st['firstname'].' '.$st['lastname'])?></div>
        <div class="lb-meta"><?=$st['id_number']?> &bull; <?=$st['course']?> Year <?=$st['year_level']?></div>
      </div>
      <div class="lb-score">
        <div class="lb-sitins"><?=$st['total_sitins']?> <span style="font-size:0.7rem;color:#8d99ae">sit-ins</span></div>
        <div class="lb-hours"><?=$st['total_hours']?> hrs</div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php else: ?>
    <p style="text-align:center;color:#8d99ae;padding:40px">No sit-in data yet. Check back soon!</p>
  <?php endif; ?>

  <?php if($testimonials): ?>
  <div id="testimonials" style="margin-top:20px">
    <div class="section-heading"><i class="fas fa-quote-left" style="color:#8ecae6"></i> What Students Say</div>
    <p class="section-sub">Verified testimonials from CCS lab users</p>
    <div class="testi-grid">
      <?php foreach($testimonials as $t): ?>
      <div class="testi-card">
        <div class="testi-top">
          <div class="testi-av"><?=strtoupper(substr($t['firstname'],0,1).substr($t['lastname'],0,1))?></div>
          <div>
            <div class="testi-name"><?=htmlspecialchars($t['firstname'].' '.$t['lastname'])?></div>
            <div class="testi-meta"><?=$t['id_number']?> &bull; <?=$t['course']?></div>
          </div>
          <div class="testi-stars"><?= str_repeat('★', (int)$t['rating']) . str_repeat('☆', 5 - (int)$t['rating']) ?></div>
        </div>
        <div class="testi-msg">"<?=htmlspecialchars($t['message'])?>"</div>
        <div class="testi-date"><i class="fas fa-clock"></i> <?=date('M d, Y',strtotime($t['created_at']))?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="land-footer">
  &copy; <?= date('Y') ?> University of Cebu — College of Computer Studies. All rights reserved.
</div>

<script>
(function(){
  const t = localStorage.getItem('theme') || 'light';
  if (t === 'dark') {
    document.documentElement.classList.add('dark');
    document.getElementById('darkIcon').className = 'fas fa-sun';
  }
})();
function switchTab(t) {
  ['login','register'].forEach(x => {
    document.getElementById('panel-'+x).classList.toggle('active', x===t);
    document.getElementById('btn-'+x).classList.toggle('active', x===t);
  });
}
function checkStr(v) {
  let s=0;
  if(v.length>=6)  s++;
  if(v.length>=10) s++;
  if(/[A-Z]/.test(v)) s++;
  if(/[0-9]/.test(v)) s++;
  if(/[^A-Za-z0-9]/.test(v)) s++;
  const b=document.getElementById('sbar');
  b.style.width      = s?['20%','40%','60%','80%','100%'][s-1]:'0';
  b.style.background = s?['#ef233c','#fb8500','#ffb703','#219ebc','#023047'][s-1]:'transparent';
}
function toggleDark() {
  const html   = document.documentElement;
  const isDark = html.classList.toggle('dark');
  document.getElementById('darkIcon').className = isDark ? 'fas fa-sun' : 'fas fa-moon';
  localStorage.setItem('theme', isDark ? 'dark' : 'light');
}
</script>
</body>
</html>