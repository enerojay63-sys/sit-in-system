<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireStudent();
$s = $_SESSION['student_data'];
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $lastname   = trim($_POST['lastname']);
  $firstname  = trim($_POST['firstname']);
  $midname    = trim($_POST['midname']);
  $course     = $_POST['course'];
  $year_level = (int)$_POST['year_level'];
  $email      = trim($_POST['email']);
  $address    = trim($_POST['address']);
  $new_pass   = $_POST['new_password'];
  $confirm    = $_POST['confirm_password'];
  $pic = $s['profile_pic'];
  if (!empty($_FILES['profile_pic']['name'])) {
    $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
    if (in_array($ext,['jpg','jpeg','png','gif','webp'])) {
      $dir = '../images/profiles/';
      if (!is_dir($dir)) mkdir($dir,0777,true);
      $filename = $s['id'].'_'.time().'.'.$ext;
      move_uploaded_file($_FILES['profile_pic']['tmp_name'],$dir.$filename);
      $pic = $filename;
    }
  }
  if ($new_pass !== '') {
    if ($new_pass !== $confirm) { $error = 'Passwords do not match.'; }
    elseif (strlen($new_pass) < 6) { $error = 'Password must be at least 6 characters.'; }
    else {
      $hash = password_hash($new_pass, PASSWORD_DEFAULT);
      $stmt = $conn->prepare("UPDATE students SET lastname=?,firstname=?,midname=?,course=?,year_level=?,email=?,address=?,password=?,profile_pic=? WHERE id=?");
      $stmt->bind_param('ssssissssi',$lastname,$firstname,$midname,$course,$year_level,$email,$address,$hash,$pic,$s['id']);
      $stmt->execute(); $stmt->close(); $success = 'Profile updated!';
    }
  } else {
    $stmt = $conn->prepare("UPDATE students SET lastname=?,firstname=?,midname=?,course=?,year_level=?,email=?,address=?,profile_pic=? WHERE id=?");
    $stmt->bind_param('ssssisssi',$lastname,$firstname,$midname,$course,$year_level,$email,$address,$pic,$s['id']);
    $stmt->execute(); $stmt->close(); $success = 'Profile updated!';
  }
  if (!$error) {
    $stmt = $conn->prepare("SELECT * FROM students WHERE id=?");
    $stmt->bind_param('i',$s['id']); $stmt->execute();
    $s = $stmt->get_result()->fetch_assoc(); $_SESSION['student_data'] = $s; $stmt->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Edit Profile — CCS</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    html.dark body{background:linear-gradient(170deg,#0a0d14 0%,#0f1117 40%,#1a1d27 100%);}
    html.dark .topnav{background:linear-gradient(135deg,#05101a 0%,#0f1117 55%,#0d0f1a 100%);}
    html.dark .card{background:#1a1d27;border-color:rgba(255,255,255,0.07);}
    html.dark .card-header{background:linear-gradient(135deg,#05101a,#0f1117);}
    html.dark .form-control{background:#242838;border-color:#3d4060;color:#edf2f4;}
    html.dark .form-group label{color:#c8d0dc;}
    html.dark footer{background:rgba(5,16,26,0.9);color:rgba(200,208,220,0.5);}
    html.dark .profile-avatar-side .avatar-placeholder{background:linear-gradient(135deg,#05101a,#0f1117);}
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
    <a href="leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a>
    <a href="testimonials.php"><i class="fas fa-quote-left"></i> Testimonials</a>
    <a href="edit_profile.php" class="active"><i class="fas fa-user-edit"></i> Profile</a>
    <button class="dark-toggle" onclick="toggleDark()" title="Toggle dark mode"><i class="fas fa-moon" id="darkIcon"></i></button>
    <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Log out</a>
  </div>
</nav>

<div class="page-content">
  <h2 style="margin-bottom:20px;font-size:1.25rem;font-weight:700;color:var(--prussian);text-align:center;"><i class="fas fa-user-edit" style="color:var(--cerulean)"></i> Edit Profile</h2>
  
  <?php if($error): ?><div class="alert alert-danger" style="max-width:700px; margin: 0 auto 20px auto;"><i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($error)?></div><?php endif; ?>
  <?php if($success): ?><div class="alert alert-success" style="max-width:700px; margin: 0 auto 20px auto;"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($success)?></div><?php endif; ?>

  <div class="card" style="max-width:700px; margin: 0 auto;">
    <div class="card-header"><i class="fas fa-user-edit"></i> Edit Your Information</div>
    <div class="card-body">
      <form method="POST" enctype="multipart/form-data">
        <div class="profile-grid">
          <div class="profile-avatar-side">
            <?php if($s['profile_pic'] && file_exists('../images/profiles/'.$s['profile_pic'])): ?>
              <img src="../images/profiles/<?=htmlspecialchars($s['profile_pic'])?>" alt="Avatar" id="preview">
            <?php else: ?>
              <div class="avatar-placeholder" id="preview-placeholder"><i class="fas fa-user"></i></div>
              <img src="" alt="" id="preview" style="display:none;width:130px;height:130px;border-radius:50%;object-fit:cover;border:4px solid var(--cerulean)">
            <?php endif; ?>
            <label class="btn btn-secondary btn-sm" style="cursor:pointer">
              <i class="fas fa-camera"></i> Change Photo
              <input type="file" name="profile_pic" accept="image/*" style="display:none" onchange="previewPic(this)">
            </label>
          </div>
          <div>
            <div class="form-group"><label>ID Number (cannot be changed)</label><input type="text" class="form-control" value="<?=htmlspecialchars($s['id_number'])?>" readonly></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div class="form-group"><label>Last Name *</label><input type="text" name="lastname" class="form-control" value="<?=htmlspecialchars($s['lastname'])?>" required></div>
              <div class="form-group"><label>First Name *</label><input type="text" name="firstname" class="form-control" value="<?=htmlspecialchars($s['firstname'])?>" required></div>
            </div>
            <div class="form-group"><label>Middle Name</label><input type="text" name="midname" class="form-control" value="<?=htmlspecialchars($s['midname'])?>"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div class="form-group"><label>Course *</label>
                <select name="course" class="form-control" required>
                  <?php foreach(['BSIT','BSCS','BSIS','ACT'] as $c): ?>
                  <option value="<?=$c?>" <?=$s['course']===$c?'selected':''?>><?=$c?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group"><label>Year Level *</label>
                <!-- FIX: Correct year level options with proper labels -->
                <select name="year_level" class="form-control" required>
                  <option value="1" <?=(int)$s['year_level']===1?'selected':''?>>1st Year</option>
                  <option value="2" <?=(int)$s['year_level']===2?'selected':''?>>2nd Year</option>
                  <option value="3" <?=(int)$s['year_level']===3?'selected':''?>>3rd Year</option>
                  <option value="4" <?=(int)$s['year_level']===4?'selected':''?>>4th Year</option>
                </select>
              </div>
            </div>
            <div class="form-group"><label>Email *</label><input type="email" name="email" class="form-control" value="<?=htmlspecialchars($s['email'])?>" required></div>
            <div class="form-group"><label>Address</label><input type="text" name="address" class="form-control" value="<?=htmlspecialchars($s['address'] ?? '')?>"></div>
            <hr style="margin:16px 0;border:none;border-top:1px solid var(--border)">
            <p style="font-size:0.78rem;color:var(--text-muted);margin-bottom:10px">Leave blank to keep current password</p>
            <div class="form-group"><label>New Password</label><input type="password" name="new_password" class="form-control" placeholder="New password (min. 6 chars)"></div>
            <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password"></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<footer>&copy; <?=date('Y')?> University of Cebu — College of Computer Studies.</footer>
<script>
(function(){const t=localStorage.getItem('theme')||'light';if(t==='dark'){document.documentElement.classList.add('dark');const i=document.getElementById('darkIcon');if(i)i.className='fas fa-sun';}})();
function toggleDark(){const isDark=document.documentElement.classList.toggle('dark');document.getElementById('darkIcon').className=isDark?'fas fa-sun':'fas fa-moon';localStorage.setItem('theme',isDark?'dark':'light');}
function previewPic(input){if(input.files&&input.files[0]){const r=new FileReader();r.onload=e=>{const p=document.getElementById('preview');const ph=document.getElementById('preview-placeholder');p.src=e.target.result;p.style.display='block';if(ph)ph.style.display='none';};r.readAsDataURL(input.files[0]);}}
</script>
</body>
</html>