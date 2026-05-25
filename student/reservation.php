<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireStudent();

$s = $_SESSION['student_data'];
$stmt = $conn->prepare("SELECT * FROM students WHERE id=?");
$stmt->bind_param('i',$s['id']); $stmt->execute();
$s = $stmt->get_result()->fetch_assoc(); $stmt->close();
$_SESSION['student_data'] = $s;

$error = $success = '';

// Check if reservations are enabled
$reserv_enabled = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='reservation_enabled'")->fetch_row()[0] ?? '1';

// Handle cancel reservation
if (isset($_GET['cancel'])) {
  $cancel_id = (int)$_GET['cancel'];
  // Only allow canceling own pending reservations
  $chk = $conn->prepare("SELECT id FROM reservations WHERE id=? AND student_id=? AND status='pending'");
  $chk->bind_param('ii', $cancel_id, $s['id']); $chk->execute();
  if ($chk->get_result()->num_rows > 0) {
    $conn->query("UPDATE reservations SET status='cancelled' WHERE id=$cancel_id");
    $success = 'Reservation cancelled successfully.';
  } else {
    $error = 'Cannot cancel this reservation.';
  }
  $chk->close();
}

if ($_SERVER['REQUEST_METHOD']==='POST' && $reserv_enabled==='1') {
  $purpose = trim($_POST['purpose']);
  $lab     = trim($_POST['lab']);
  $time_in = $_POST['time_in'];
  $date    = $_POST['date'];
  $name    = fullName($s);
  if (!$purpose||!$lab||!$time_in||!$date) {
    $error = 'Please fill in all required fields.';
  } else {
    $stmt = $conn->prepare("INSERT INTO reservations (student_id,id_number,student_name,purpose,lab,time_in,date) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param('issssss',$s['id'],$s['id_number'],$name,$purpose,$lab,$time_in,$date);
    if ($stmt->execute()) $success = 'Reservation submitted! Wait for admin approval.';
    else $error = 'Failed to submit reservation.';
    $stmt->close();
  }
}

$res = $conn->prepare("SELECT * FROM reservations WHERE student_id=? ORDER BY created_at DESC LIMIT 10");
$res->bind_param('i',$s['id']); $res->execute();
$reservations = $res->get_result()->fetch_all(MYSQLI_ASSOC);
$res->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Reservation — CCS</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    html.dark body{background:linear-gradient(170deg,#0a0d14 0%,#0f1117 40%,#1a1d27 100%);}
    html.dark .topnav{background:linear-gradient(135deg,#05101a 0%,#0f1117 55%,#0d0f1a 100%);}
    html.dark .card{background:#1a1d27;border-color:rgba(255,255,255,0.07);}
    html.dark .card-header{background:linear-gradient(135deg,#05101a,#0f1117);}
    html.dark .form-control{background:#242838;border-color:#3d4060;color:#edf2f4;}
    html.dark table.data-table tbody tr{border-color:rgba(255,255,255,0.07);}
    html.dark table.data-table tbody tr:hover{background:rgba(142,202,230,0.06);}
    html.dark table.data-table tbody tr:nth-child(even){background:rgba(255,255,255,0.03);}
    html.dark footer{background:rgba(5,16,26,0.9);color:rgba(200,208,220,0.5);}

    /* FIX: dark mode table cell labels */
    html.dark .res-table-label { color: #c8d0dc !important; }
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
    <a href="reservation.php" class="active"><i class="fas fa-calendar-plus"></i> Reservation</a>
    <a href="leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a>
    <a href="testimonials.php"><i class="fas fa-quote-left"></i> Testimonials</a>
    <a href="edit_profile.php"><i class="fas fa-user-edit"></i> Profile</a>
    <button class="dark-toggle" onclick="toggleDark()" title="Toggle dark mode"><i class="fas fa-moon" id="darkIcon"></i></button>
    <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Log out</a>
  </div>
</nav>

<div class="page-content">
  <h2 style="text-align:center;margin-bottom:22px;font-size:1.35rem;font-weight:700;color:var(--prussian)">
    <i class="fas fa-calendar-plus" style="color:var(--cerulean)"></i> Reservation
  </h2>

  <?php if($error): ?><div class="alert alert-danger" style="max-width:700px;margin:0 auto 16px"><i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($error)?></div><?php endif; ?>
  <?php if($success): ?><div class="alert alert-success" style="max-width:700px;margin:0 auto 16px"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($success)?></div><?php endif; ?>

  <?php if($reserv_enabled==='0'): ?>
  <div class="alert alert-warning" style="max-width:700px;margin:0 auto 20px;justify-content:center;text-align:center">
    <i class="fas fa-ban" style="font-size:1.2rem"></i>
    <div>
      <strong>Reservations are currently disabled</strong><br>
      <span style="font-size:0.82rem">The admin has temporarily disabled the reservation system. Please check back later or contact the lab administrator.</span>
    </div>
  </div>
  <?php else: ?>
  <div class="card" style="max-width:700px;margin:0 auto 24px">
    <div class="card-header"><i class="fas fa-calendar-plus"></i> New Reservation</div>
    <div class="card-body">
      <form method="POST">
        <table style="width:100%;border-collapse:collapse">
          <tr><td style="padding:8px 0;width:160px;font-size:0.84rem;font-weight:600;color:var(--text-soft)" class="res-table-label">ID Number:</td><td style="padding:8px 0"><input type="text" class="form-control" value="<?=htmlspecialchars($s['id_number'])?>" readonly></td></tr>
          <tr><td style="padding:8px 0;font-size:0.84rem;font-weight:600;color:var(--text-soft)" class="res-table-label">Student Name:</td><td style="padding:8px 0"><input type="text" class="form-control" value="<?=htmlspecialchars(fullName($s))?>" readonly></td></tr>
          <tr><td style="padding:8px 0;font-size:0.84rem;font-weight:600;color:var(--text-soft)" class="res-table-label">Purpose: *</td><td style="padding:8px 0"><input type="text" name="purpose" class="form-control" placeholder="e.g. C Programming" required></td></tr>
          <tr><td style="padding:8px 0;font-size:0.84rem;font-weight:600;color:var(--text-soft)" class="res-table-label">Lab: *</td><td style="padding:8px 0"><input type="text" name="lab" class="form-control" placeholder="e.g. 524" required></td></tr>
          <tr><td style="padding:8px 0;font-size:0.84rem;font-weight:600;color:var(--text-soft)" class="res-table-label">Time In: *</td><td style="padding:8px 0"><input type="time" name="time_in" class="form-control" required></td></tr>
          <tr><td style="padding:8px 0;font-size:0.84rem;font-weight:600;color:var(--text-soft)" class="res-table-label">Date: *</td><td style="padding:8px 0"><input type="date" name="date" class="form-control" required></td></tr>
          <tr><td style="padding:8px 0;font-size:0.84rem;font-weight:600;color:var(--text-soft)" class="res-table-label">Remaining Session:</td><td style="padding:8px 0"><input type="text" class="form-control" value="<?=$s['remaining_session']?>" readonly></td></tr>
        </table>
        <button type="submit" class="btn btn-primary" style="margin-top:16px"><i class="fas fa-calendar-check"></i> Reserve</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <?php if($reservations): ?>
  <div class="card" style="max-width:700px;margin:0 auto">
    <div class="card-header"><i class="fas fa-list"></i> My Reservations</div>
    <div class="card-body" style="padding:0">
      <div class="dt-wrapper">
        <table class="data-table">
          <thead><tr><th>Lab</th><th>Purpose</th><th>Date</th><th>Time</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach($reservations as $r): ?>
            <tr>
              <td><?=htmlspecialchars($r['lab'])?></td>
              <td><?=htmlspecialchars($r['purpose'])?></td>
              <td><?=htmlspecialchars($r['date'])?></td>
              <td><?=htmlspecialchars($r['time_in'])?></td>
              <td>
                <?php
                  // Support 'cancelled' status if column allows it
                  $badge = 'badge-warning';
                  if ($r['status'] === 'approved') $badge = 'badge-success';
                  elseif ($r['status'] === 'rejected') $badge = 'badge-danger';
                  elseif ($r['status'] === 'cancelled') $badge = 'badge-secondary';
                ?>
                <span class="badge <?=$badge?>"><?=ucfirst($r['status'])?></span>
              </td>
              <td>
                <?php if ($r['status'] === 'pending'): ?>
                <a href="reservation.php?cancel=<?=$r['id']?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Cancel this reservation?')">
                  <i class="fas fa-times"></i> Cancel
                </a>
                <?php else: ?>
                <span style="font-size:0.75rem;color:var(--text-muted)">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<footer>&copy; <?=date('Y')?> University of Cebu — College of Computer Studies.</footer>
<script>
(function(){const t=localStorage.getItem('theme')||'light';if(t==='dark'){document.documentElement.classList.add('dark');const i=document.getElementById('darkIcon');if(i)i.className='fas fa-sun';}})();
function toggleDark(){const isDark=document.documentElement.classList.toggle('dark');document.getElementById('darkIcon').className=isDark?'fas fa-sun':'fas fa-moon';localStorage.setItem('theme',isDark?'dark':'light');}
</script>
</body>
</html>