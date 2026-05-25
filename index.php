<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedInStudent()) { header('Location: student/dashboard.php'); exit; }
if (isLoggedInAdmin())   { header('Location: admin/dashboard.php');  exit; }

$error = $success = '';
$initial_view = 'home'; 
$tab = 'login';         

// ── REGISTER ACTION ──
if (isset($_POST['register'])) {
  $initial_view = 'form';
  $tab          = 'register';
  $id_number  = trim($_POST['id_number']);
  $lastname   = trim($_POST['lastname']);
  $firstname  = trim($_POST['firstname']);
  $midname    = trim($_POST['midname']);
  $course     = $_POST['course'] ?? '';
  $year_level = (int)($_POST['year_level'] ?? 0);
  $email      = trim($_POST['email']);
  $address    = trim($_POST['address']);
  $password   = $_POST['password'];
  $confirm    = $_POST['confirm_password'];

  if ($password !== $confirm) {
    $error = 'Passwords do not match.';
  } elseif (strlen($password) < 6) {
    $error = 'Password must be at least 6 characters.';
  } else {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO students (id_number,lastname,firstname,midname,course,year_level,email,address,password) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param('sssssisss', $id_number,$lastname,$firstname,$midname,$course,$year_level,$email,$address,$hash);
    if ($stmt->execute()) { 
      $success = 'Account created successfully! You can now log in.'; 
      $tab = 'login'; 
      $_POST = [];
    } else {
      $error = 'ID Number or Email already exists.';
    }
    $stmt->close();
  }
}

// ── LOGIN ACTION ──
if (isset($_POST['login'])) {
  $initial_view = 'form';
  $tab          = 'login';
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

// ── Leaderboard Data ──
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

// ── Testimonials Data ──
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
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    
    * { box-sizing: border-box; margin: 0; padding: 0; }
    
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 40%, #0f172a 100%);
      background-attachment: fixed;
      display: flex; flex-direction: column; min-height: 100vh;
      color: #f8fafc; overflow-x: hidden;
    }
    
    html.dark body {
      background: linear-gradient(135deg, #020617 0%, #0f172a 50%, #020617 100%);
    }

    body::before {
      content:''; display:block; height:4px;
      background: linear-gradient(90deg, #3b82f6, #06b6d4, #10b981, #f59e0b, #ef4444);
      position:fixed; top:0; left:0; right:0; z-index:2000;
    }

    /* Premium Navigation Bar */
    .land-nav {
      background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 40px; height: 64px; border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      position: sticky; top: 0; z-index: 1000; transition: all 0.3s;
    }
    html.dark .land-nav { background: rgba(2, 6, 23, 0.7); border-bottom-color: rgba(255, 255, 255, 0.04); }
    .land-nav .brand { color: #fff; font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: 10px; letter-spacing: -0.5px; }
    .land-nav .brand i { background: linear-gradient(135deg, #3b82f6, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .land-nav-right { display: flex; align-items: center; gap: 12px; }
    .land-nav a {
      color: rgba(241, 245, 249, 0.8); text-decoration: none; font-size: 0.85rem; font-weight: 600;
      padding: 8px 16px; border-radius: 8px; transition: all .2s ease;
      display: inline-flex; align-items: center; gap: 6px; cursor: pointer;
    }
    .land-nav a:hover { background: rgba(255, 255, 255, 0.06); color: #3b82f6; }
    .land-dark-btn {
      background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1);
      color: #fff; border-radius: 8px; width: 36px; height: 36px;
      cursor: pointer; display: flex; align-items: center; justify-content: center;
      transition: all .2s;
    }
    .land-dark-btn:hover { background: rgba(255, 255, 255, 0.12); border-color: rgba(255, 255, 255, 0.2); transform: scale(1.05); }
    
    /* Layout Workspace */
    .land-main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 60px 40px; }
    
    /* FIX: Changed align-items to flex-start so content alignment doesn't shift when height changes */
    .land-wrap { display: flex; align-items: flex-start; justify-content: space-between; gap: 60px; width: 100%; max-width: 1100px; padding-top: 20px; }
    
    /* FIX: Added margin-top to keep the logo column clean and tracking beautifully */
    .logo-col { flex: 1; display: flex; flex-direction: column; align-items: flex-start; max-width: 520px; margin-top: 40px; }
    
    .logo-badge-container { display: flex; align-items: center; gap: -20px; margin-bottom: 28px; position: relative; }
    
    .logo-badge {
      width: 120px; height: 120px; border-radius: 50%; background: #ffffff;
      padding: 14px; display: flex; align-items: center; justify-content: center;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35), inset 0 0 0 2px rgba(255, 255, 255, 0.1);
      transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
      overflow: hidden;
    }
    .logo-badge img { width: 100%; height: 100%; object-fit: contain; }
    .badge-ccs { z-index: 2; margin-right: -25px; border: 4px solid #1e293b; }
    html.dark .badge-ccs { border-color: #0f172a; }
    .badge-uc { z-index: 1; transform: scale(0.92); border: 4px solid #1e293b; }
    html.dark .badge-uc { border-color: #0f172a; }
    
    .logo-badge-container:hover .badge-ccs { transform: translateX(-10px) rotate(-5px); }
    .logo-badge-container:hover .badge-uc { transform: scale(0.92) translateX(10px) rotate(5px); }

    .logo-col h1 { font-size: 2.2rem; font-weight: 800; line-height: 1.25; letter-spacing: -1px; margin-bottom: 12px; background: linear-gradient(to right, #ffffff, #cbd5e1); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .logo-col .tagline { color: #94a3b8; font-size: 1rem; line-height: 1.6; font-weight: 500; }
    .logo-col .institution { color: #3b82f6; font-weight: 700; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1.5px; margin-bottom: 6px; }

    /* Interactive Right Column Action States */
    .action-col { width: 440px; flex-shrink: 0; display: flex; align-items: center; justify-content: flex-end; min-height: 400px; }
    
    /* STATE 1: Modern Choice Pill Buttons */
    .view-choices { display: flex; align-items: center; gap: 20px; animation: layoutFade 0.4s ease; margin-top: 60px; }
    .choice-btn {
      padding: 16px 40px; font-size: 1.05rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: 1px; border-radius: 50px; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      text-decoration: none; display: inline-block; text-align: center;
    }
    .btn-choice-login {
      background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #fff;
      box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3); border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .btn-choice-login:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(59, 130, 246, 0.5); }
    
    .btn-choice-reg {
      background: rgba(255, 255, 255, 0.05); color: #f1f5f9;
      border: 1px solid rgba(255, 255, 255, 0.15); box-shadow: 0 10px 20px rgba(0,0,0,0.15);
    }
    .btn-choice-reg:hover { background: rgba(255, 255, 255, 0.1); color: #fff; border-color: rgba(255, 255, 255, 0.25); transform: translateY(-3px); }

    /* STATE 2: Premium Form Wrapper */
    .view-form-card { width: 100%; display: none; animation: layoutFade 0.4s ease; }
    .tab-hdr { display: flex; background: rgba(0, 0, 0, 0.2); border-radius: 14px 14px 0 0; padding: 6px 6px 0; border: 1px solid rgba(255,255,255,0.06); border-bottom: none; }
    .tab-b {
      flex: 1; padding: 14px; background: transparent; border: none; color: #94a3b8;
      font-family: inherit; font-size: 0.88rem; font-weight: 600; cursor: pointer;
      border-radius: 10px 10px 0 0; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .tab-b:hover { color: #f1f5f9; background: rgba(255,255,255,0.03); }
    .tab-b.active { background: #1e293b; color: #3b82f6; border-top: 2px solid #3b82f6; font-weight: 700; }
    html.dark .tab-b.active { background: #0f172a; }
    
    .card-bx {
      background: #1e293b; border-radius: 0 0 16px 16px; padding: 32px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); border: 1px solid rgba(255, 255, 255, 0.06); border-top: none;
    }
    html.dark .card-bx { background: #0f172a; border-color: rgba(255, 255, 255, 0.04); }
    .panel { display: none; } .panel.active { display: block; }
    
    /* Modern Inputs Styling */
    .card-bx .form-group label { color: #cbd5e1; font-size: 0.8rem; font-weight: 600; margin-bottom: 8px; display: block; }
    .card-bx .form-control {
      background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 10px;
      padding: 12px 16px; font-family: inherit; font-size: 0.9rem; color: #fff; outline: none; width: 100%;
      transition: all 0.2s;
    }
    .card-bx .form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25); background: rgba(15, 23, 42, 0.9); }
    .form-group { margin-bottom: 20px; }
    .iw { position: relative; }
    .iw i.fi { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 0.9rem; pointer-events: none; }
    .iw .form-control { padding-left: 42px; }
    .r2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .slbl { font-size: 0.72rem; font-weight: 700; color: #3b82f6; text-transform: uppercase; letter-spacing: 1.5px; margin: 24px 0 14px; padding-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; gap: 8px; }
    
    .rem-row { display: flex; align-items: center; justify-content: space-between; margin: 4px 0 20px; }
    .rem-row label { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #94a3b8; cursor: pointer; }
    .rem-row input[type="checkbox"] { width: 16px; height: 16px; accent-color: #3b82f6; cursor: pointer; }
    
    .btn-land {
      width: 100%; justify-content: center; padding: 14px; font-size: 0.95rem; border: none; border-radius: 10px; font-family: inherit; font-weight: 700; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 8px;
      background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #fff; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
    }
    .btn-land:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4); filter: brightness(1.1); }
    .bl { text-align: center; margin-top: 16px; font-size: 0.85rem; color: #94a3b8; }
    .bl a { color: #3b82f6; text-decoration: none; font-weight: 600; cursor: pointer; }
    .bl a:hover { text-decoration: underline; }
    
    /* Security Bars styling */
    .sbar  { height: 4px; background: rgba(255,255,255,0.1); border-radius: 2px; margin-top: 8px; overflow: hidden; }
    .sfill { height: 100%; width: 0; border-radius: 2px; transition: width .3s, background .3s; }
    
    /* Layout Public Component Modules */
    .public-section {
      background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
      padding: 80px 40px; border-top: 1px solid rgba(255, 255, 255, 0.08);
    }
    html.dark .public-section { background: rgba(2, 6, 23, 0.7); border-top-color: rgba(255, 255, 255, 0.04); }
    .section-heading { text-align: center; color: #fff; font-size: 1.6rem; font-weight: 800; margin-bottom: 8px; display: flex; align-items: center; justify-content: center; gap: 12px; letter-spacing: -0.5px; }
    .section-sub { text-align: center; color: #94a3b8; font-size: 0.9rem; margin-bottom: 48px; }
    
    /* Elegant Podium Module */
    .podium { display: flex; align-items: flex-end; justify-content: center; gap: 20px; margin-bottom: 48px; padding-top: 40px; }
    .podium-item { display: flex; flex-direction: column; align-items: center; gap: 12px; }
    .podium-base {
      border-radius: 16px 16px 0 0; display: flex; flex-direction: column;
      align-items: center; justify-content: flex-end; padding: 20px;
      min-width: 140px; position: relative; box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    .podium-base.p1 { background: linear-gradient(135deg, #eab308, #ca8a04); height: 150px; }
    .podium-base.p2 { background: linear-gradient(135deg, #94a3b8, #64748b); height: 115px; }
    .podium-base.p3 { background: linear-gradient(135deg, #b45309, #78350f); height: 95px; }
    .podium-rank   { font-size: 1.8rem; margin-bottom: 6px; }
    .podium-name   { font-weight: 700; font-size: 0.88rem; color: #fff; text-align: center; line-height: 1.4; }
    .podium-course { font-size: 0.72rem; color: rgba(255,255,255,0.8); margin-top: 3px; font-weight: 600; }
    .podium-count  { font-size: 0.78rem; color: #fff; margin-top: 6px; font-weight: 700; background: rgba(0,0,0,0.2); padding: 2px 8px; border-radius: 20px; }
    .podium-avatar {
      width: 56px; height: 56px; border-radius: 50%;
      background: linear-gradient(135deg, #1e293b, #334155);
      display: flex; align-items: center; justify-content: center;
      color: #fff; font-size: 1.1rem; font-weight: 800;
      border: 3px solid rgba(255,255,255,0.15); box-shadow: 0 10px 20px rgba(0,0,0,0.3);
    }
    
    /* Dynamic Leaderboard Row Modules */
    .leaderboard-list { max-width: 760px; margin: 0 auto 60px; display: flex; flex-direction: column; gap: 10px; }
    .lb-row {
      background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05);
      border-radius: 14px; padding: 14px 20px; display: flex; align-items: center; gap: 16px;
      transition: all 0.2s;
    }
    .lb-row:hover { background: rgba(255, 255, 255, 0.06); border-color: rgba(255, 255, 255, 0.1); transform: scale(1.01); }
    .lb-rank   { font-size: 0.9rem; font-weight: 800; color: #3b82f6; width: 28px; text-align: center; }
    .lb-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #06b6d4); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.8rem; font-weight: 700; flex-shrink: 0; }
    .lb-info   { flex: 1; }
    .lb-name   { font-weight: 700; color: #f1f5f9; font-size: 0.92rem; }
    .lb-meta   { font-size: 0.78rem; color: #94a3b8; margin-top: 3px; font-weight: 500; }
    .lb-score { text-align: right; }
    .lb-sitins{ font-weight: 800; color: #f59e0b; font-size: 0.95rem; }
    .lb-hours { font-size: 0.75rem; color: #94a3b8; margin-top: 2px; }
    
    /* Grid Testimonials system layout modules */
    .testi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 16px; max-width: 1100px; margin: 0 auto; }
    .testi-card {
      background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05);
      border-radius: 16px; padding: 20px; transition: all 0.2s;
    }
    .testi-card:hover { background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.08); }
    .testi-top  { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
    .testi-av   { width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #10b981, #059669); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.78rem; font-weight: 700; flex-shrink: 0; }
    .testi-name { font-weight: 700; color: #f1f5f9; font-size: 0.88rem; }
    .testi-meta { font-size: 0.75rem; color: #94a3b8; margin-top: 2px; }
    .testi-stars{ color: #f59e0b; font-size: 0.8rem; margin-left: auto; letter-spacing: 1px; }
    .testi-msg  { font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; font-style: italic; }
    .testi-date { font-size: 0.72rem; color: #64748b; margin-top: 12px; display: flex; align-items: center; gap: 5px; }
    
    .land-footer {
      background: rgba(15, 23, 42, 0.9); border-top: 1px solid rgba(255, 255, 255, 0.06);
      color: #64748b; text-align: center; padding: 20px; font-size: 0.8rem; font-weight: 500;
    }
    
    @keyframes layoutFade {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    @media(max-width:920px){
      .land-wrap { flex-direction: column; text-align: center; gap: 48px; }
      .logo-col { align-items: center; margin-top: 0; }
      .action-col { width: 100%; max-width: 440px; min-height: auto; margin: 0 auto; justify-content: center; }
      .podium { gap: 10px; }
      .podium-base { min-width: 105px; padding: 12px; }
    }
  </style>
</head>
<body>

<nav class="land-nav">
  <span class="brand"><i class="fas fa-desktop"></i> CCS Sit-in Monitoring System</span>
  <div class="land-nav-right">
    <a onclick="navigateToHome()"><i class="fas fa-home"></i> Home</a>
    <a href="#leaderboard"><i class="fas fa-trophy"></i> Leaderboard</a>
    <a href="#testimonials"><i class="fas fa-quote-left"></i> Testimonials</a>
    <button class="land-dark-btn" onclick="toggleDark()" title="Toggle Dark Mode">
      <i class="fas fa-moon" id="darkIcon"></i>
    </button>
  </div>
</nav>

<div class="land-main">
  <div class="land-wrap">
    <div class="logo-col">
      <div class="logo-badge-container">
        <div class="logo-badge badge-ccs">
          <img src="images/ccs_logo.png" alt="CCS Logo">
        </div>
        <div class="logo-badge badge-uc">
          <img src="images/uc_logo.png" alt="UC Logo">
        </div>
      </div>
      <div class="institution">University of Cebu</div>
      <h1>College of Computer Studies<br>Sit-in Monitoring System</h1>
      <p class="tagline">Manage and track your laboratory sit-in sessions securely with real-time analytics and dynamic verification.</p>
    </div>

    <div class="action-col">
      
      <div class="view-choices" id="wrapper-choices" style="display: <?= $initial_view === 'home' ? 'flex' : 'none' ?>;">
        <a class="choice-btn btn-choice-login" onclick="displayFormLayout('login')">Login</a>
        <a class="choice-btn btn-choice-reg" onclick="displayFormLayout('register')">Register</a>
      </div>

      <div class="view-form-card" id="wrapper-form" style="display: <?= $initial_view === 'form' ? 'block' : 'none' ?>;">
        <?php if ($error): ?>
        <div style="background:#fef2f2;color:#991b1b;border:1px solid #fee2e2;padding:12px 18px;border-radius:12px 12px 0 0;font-size:0.85rem;font-weight:600;display:flex;align-items:center;gap:8px;">
          <i class="fas fa-exclamation-circle" style="color:#ef4444"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php elseif ($success): ?>
        <div style="background:#f0fdf4;color:#166534;border:1px solid #dcfce7;padding:12px 18px;border-radius:12px 12px 0 0;font-size:0.85rem;font-weight:600;display:flex;align-items:center;gap:8px;">
          <i class="fas fa-check-circle" style="color:#22c55e"></i> <?= htmlspecialchars($success) ?>
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
          <div class="panel <?= $tab==='login'?'active':'' ?>" id="panel-login">
            <form method="POST">
              <div class="form-group">
                <label>ID Number / Username</label>
                <div class="iw"><i class="fas fa-id-card fi"></i>
                  <input type="text" name="identifier" class="form-control" placeholder="Enter ID or username" value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>" required>
                </div>
              </div>
              <div class="form-group">
                <label>Password</label>
                <div class="iw"><i class="fas fa-lock fi"></i>
                  <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                </div>
              </div>
              <div class="rem-row">
                <label><input type="checkbox"> Remember me</label>
              </div>
              <button type="submit" name="login" class="btn-land"><i class="fas fa-sign-in-alt"></i> Access Account</button>
            </form>
            <p class="bl">New to the system? <a onclick="switchTab('register')">Create an account</a></p>
          </div>

          <div class="panel <?= $tab==='register'?'active':'' ?>" id="panel-register">
            <form method="POST">
              <div class="slbl"><i class="fas fa-user"></i> Student Credentials</div>
              <div class="form-group">
                <label>ID Number *</label>
                <div class="iw"><i class="fas fa-id-card fi"></i>
                  <input type="text" name="id_number" class="form-control" placeholder="e.g. 2024-00001" value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>" required>
                </div>
              </div>
              <div class="r2" style="margin-bottom:14px">
                <div class="form-group" style="margin-bottom:0"><label>Last Name *</label>
                  <div class="iw"><i class="fas fa-user fi"></i><input type="text" name="lastname" class="form-control" placeholder="Surname" value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>" required></div></div>
                <div class="form-group" style="margin-bottom:0"><label>First Name *</label>
                  <div class="iw"><i class="fas fa-user fi"></i><input type="text" name="firstname" class="form-control" placeholder="Given name" value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>" required></div></div>
              </div>
              <div class="form-group"><label>Middle Name</label>
                <div class="iw"><i class="fas fa-user fi"></i><input type="text" name="midname" class="form-control" placeholder="Optional" value="<?= htmlspecialchars($_POST['midname'] ?? '') ?>"></div></div>
              <div class="r2" style="margin-bottom:14px">
                <div class="form-group" style="margin-bottom:0"><label>Course *</label>
                  <?php $sel_course = $_POST['course'] ?? ''; ?>
                  <select name="course" class="form-control" required>
                    <option value="" disabled <?= empty($sel_course) ? 'selected' : '' ?>>Select</option>
                    <option value="BSIT" <?= $sel_course === 'BSIT' ? 'selected' : '' ?>>BSIT</option>
                    <option value="BSCS" <?= $sel_course === 'BSCS' ? 'selected' : '' ?>>BSCS</option>
                    <option value="BSIS" <?= $sel_course === 'BSIS' ? 'selected' : '' ?>>BSIS</option>
                    <option value="ACT"  <?= $sel_course === 'ACT'  ? 'selected' : '' ?>>ACT</option>
                  </select></div>
                <div class="form-group" style="margin-bottom:0"><label>Year Level *</label>
                  <?php $sel_year = (int)($_POST['year_level'] ?? 0); ?>
                  <select name="year_level" class="form-control" required>
                    <option value="" disabled <?= $sel_year === 0 ? 'selected' : '' ?>>Select</option>
                    <option value="1" <?= $sel_year === 1 ? 'selected' : '' ?>>1st Year</option>
                    <option value="2" <?= $sel_year === 2 ? 'selected' : '' ?>>2nd Year</option>
                    <option value="3" <?= $sel_year === 3 ? 'selected' : '' ?>>3rd Year</option>
                    <option value="4" <?= $sel_year === 4 ? 'selected' : '' ?>>4th Year</option>
                  </select></div>
              </div>
              <div class="form-group"><label>Email Address *</label>
                <div class="iw"><i class="fas fa-envelope fi"></i><input type="email" name="email" class="form-control" placeholder="student@university.edu" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required></div></div>
              <div class="form-group"><label>Home Address</label>
                <div class="iw"><i class="fas fa-map-marker-alt fi"></i><input type="text" name="address" class="form-control" placeholder="City / State" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"></div></div>
              <div class="slbl"><i class="fas fa-lock"></i> Encryption & Security</div>
              <div class="form-group"><label>Password *</label>
                <div class="iw"><i class="fas fa-lock fi"></i>
                  <input type="password" name="password" class="form-control" placeholder="Min. 6 alphanumeric characters" oninput="checkStr(this.value)" required></div>
                <div class="sbar"><div class="sfill" id="sbar"></div></div></div>
              <div class="form-group"><label>Confirm Password *</label>
                <div class="iw"><i class="fas fa-lock fi"></i>
                  <input type="password" name="confirm_password" class="form-control" placeholder="Repeat system security password" required></div></div>
              <button type="submit" name="register" class="btn-land"><i class="fas fa-user-plus"></i> Complete Registration</button>
            </form>
            <p class="bl">Already registered? <a onclick="switchTab('login')">Login instead</a></p>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<div class="public-section" id="leaderboard">
  <div class="section-heading"><i class="fas fa-trophy" style="color:#f59e0b"></i> Leaderboard Hub</div>
  <p class="section-sub">Top active students with completed monitoring system lab usage hours</p>

  <?php if($top_students): ?>
  <?php if(count($top_students) >= 3): ?>
  <div class="podium">
    <div class="podium-item">
      <div class="podium-avatar"><?=strtoupper(substr($top_students[1]['firstname'],0,1).substr($top_students[1]['lastname'],0,1))?></div>
      <div class="podium-base p2">
        <div class="podium-rank">🥈</div>
        <div class="podium-name"><?=htmlspecialchars($top_students[1]['firstname'].' '.$top_students[1]['lastname'])?></div>
        <div class="podium-course"><?=$top_students[1]['course']?></div>
        <div class="podium-count"><?=$top_students[1]['total_sitins']?> Sessions</div>
      </div>
    </div>
    <div class="podium-item">
      <div class="podium-avatar" style="width:64px;height:64px;font-size:1.3rem;border-color:#eab308"><?=strtoupper(substr($top_students[0]['firstname'],0,1).substr($top_students[0]['lastname'],0,1))?></div>
      <div class="podium-base p1">
        <div class="podium-rank">🥇</div>
        <div class="podium-name"><?=htmlspecialchars($top_students[0]['firstname'].' '.$top_students[0]['lastname'])?></div>
        <div class="podium-course"><?=$top_students[0]['course']?></div>
        <div class="podium-count"><?=$top_students[0]['total_sitins']?> Sessions</div>
      </div>
    </div>
    <div class="podium-item">
      <div class="podium-avatar"><?=strtoupper(substr($top_students[2]['firstname'],0,1).substr($top_students[2]['lastname'],0,1))?></div>
      <div class="podium-base p3">
        <div class="podium-rank">🥉</div>
        <div class="podium-name"><?=htmlspecialchars($top_students[2]['firstname'].' '.$top_students[2]['lastname'])?></div>
        <div class="podium-course"><?=$top_students[2]['course']?></div>
        <div class="podium-count"><?=$top_students[2]['total_sitins']?> Sessions</div>
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
        <div class="lb-meta"><?=$st['id_number']?> &bull; <?=$st['course']?> (Year <?=$st['year_level']?>)</div>
      </div>
      <div class="lb-score">
        <div class="lb-sitins"><?=$st['total_sitins']?> <span style="font-size:0.75rem;color:#64748b">sessions</span></div>
        <div class="lb-hours"><?=$st['total_hours']?> Hours</div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php else: ?>
    <p style="text-align:center;color:#64748b;padding:40px;font-weight:500;">No system sit-in records compiled yet.</p>
  <?php endif; ?>

  <?php if($testimonials): ?>
  <div id="testimonials" style="margin-top:20px">
    <div class="section-heading"><i class="fas fa-quote-left" style="color:#10b981"></i> Student Experience</div>
    <p class="section-sub">Verified insights collected directly from user profiles</p>
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
        <div class="testi-date"><i class="far fa-calendar-alt"></i> Verified on <?=date('M d, Y',strtotime($t['created_at']))?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="land-footer">
  &copy; <?= date('Y') ?> University of Cebu — College of Computer Studies. All security rights reserved.
</div>

<script>
// Prevent visual flicker by setting theme execution before asset pipeline rendering
(function(){
  const t = localStorage.getItem('theme') || 'light';
  if (t === 'dark') {
    document.documentElement.classList.add('dark');
    const icon = document.getElementById('darkIcon');
    if(icon) icon.className = 'fas fa-sun';
  }
})();

// Clear layout to splash selection menu context
function navigateToHome() {
  document.getElementById('wrapper-form').style.display = 'none';
  document.getElementById('wrapper-choices').style.display = 'flex';
}

// Open active form element view states
function displayFormLayout(targetTab) {
  document.getElementById('wrapper-choices').style.display = 'none';
  document.getElementById('wrapper-form').style.display = 'block';
  switchTab(targetTab);
}

// Change tabs internally
function switchTab(t) {
  ['login','register'].forEach(x => {
    const panel = document.getElementById('panel-'+x);
    const btn = document.getElementById('btn-'+x);
    if(panel) panel.classList.toggle('active', x===t);
    if(btn) btn.classList.toggle('active', x===t);
  });
}

// Strength visual calculations
function checkStr(v) {
  let s=0;
  if(v.length>=6)  s++;
  if(v.length>=10) s++;
  if(/[A-Z]/.test(v)) s++;
  if(/[0-9]/.test(v)) s++;
  if(/[^A-Za-z0-9]/.test(v)) s++;
  const b=document.getElementById('sbar');
  if(b) {
    b.style.width      = s?['20%','40%','60%','80%','100%'][s-1]:'0';
    b.style.background = s?['#ef4444','#f97316','#f59e0b','#10b981','#3b82f6'][s-1]:'transparent';
  }
}

// Local storage persistent configuration theme toggle
function toggleDark() {
  const html   = document.documentElement;
  const isDark = html.classList.toggle('dark');
  const icon   = document.getElementById('darkIcon');
  if(icon) icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
  localStorage.setItem('theme', isDark ? 'dark' : 'light');
}
</script>
</body>
</html>