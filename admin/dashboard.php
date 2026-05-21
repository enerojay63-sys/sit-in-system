<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

$total_students = $conn->query("SELECT COUNT(*) FROM students")->fetch_row()[0];
$active_sitin   = $conn->query("SELECT COUNT(*) FROM sitin_records WHERE status='active'")->fetch_row()[0];
$total_sitin    = $conn->query("SELECT COUNT(*) FROM sitin_records")->fetch_row()[0];
$pending_reserv = $conn->query("SELECT COUNT(*) FROM reservations WHERE status='pending'")->fetch_row()[0];
$avg_rating     = $conn->query("SELECT ROUND(AVG(rating),1) FROM feedback")->fetch_row()[0] ?? 0;
$pending_testi  = $conn->query("SELECT COUNT(*) FROM testimonials WHERE status='pending'")->fetch_row()[0];

$announcements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

$recent_sitins = $conn->query("
  SELECT sr.*, s.firstname, s.lastname
  FROM sitin_records sr JOIN students s ON sr.student_id=s.id
  ORDER BY sr.time_in DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

$pending_res = $conn->query("
  SELECT r.*, s.firstname, s.lastname, s.course
  FROM reservations r JOIN students s ON r.student_id=s.id
  WHERE r.status='pending' ORDER BY r.created_at DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

$daily = $conn->query("
  SELECT DATE(time_in) as d, COUNT(*) as cnt
  FROM sitin_records WHERE time_in >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
  GROUP BY DATE(time_in) ORDER BY d
")->fetch_all(MYSQLI_ASSOC);

$per_lab     = $conn->query("SELECT lab, COUNT(*) as cnt FROM sitin_records GROUP BY lab ORDER BY cnt DESC")->fetch_all(MYSQLI_ASSOC);
$per_purpose = $conn->query("SELECT purpose, COUNT(*) as cnt FROM sitin_records GROUP BY purpose ORDER BY cnt DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);

$top_students = $conn->query("
  SELECT s.firstname, s.lastname, s.course, COUNT(sr.id) as total
  FROM students s LEFT JOIN sitin_records sr ON sr.student_id=s.id
  GROUP BY s.id ORDER BY total DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

$testimonials = $conn->query("
  SELECT t.*, s.firstname, s.lastname, s.course, s.year_level
  FROM testimonials t JOIN students s ON t.student_id=s.id
  WHERE t.status='approved' ORDER BY t.created_at DESC LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['post_announcement'])) {
  $content    = trim($_POST['content']);
  $admin_name = $_SESSION['admin_data']['name'] ?? 'CCS Admin';
  if ($content) {
    $stmt = $conn->prepare("INSERT INTO announcements (content,posted_by) VALUES (?,?)");
    $stmt->bind_param('ss',$content,$admin_name);
    $stmt->execute(); $stmt->close();
    $conn->query("INSERT INTO activity_logs (actor,action) VALUES ('$admin_name','Posted announcement')");
    header('Location: dashboard.php?ann=1'); exit;
  }
}

if (isset($_GET['toggle_reserv'])) {
  $cur = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='reservation_enabled'")->fetch_row()[0] ?? '1';
  $new = $cur==='1'?'0':'1';
  $conn->query("INSERT INTO system_settings (setting_key,setting_value) VALUES ('reservation_enabled','$new') ON DUPLICATE KEY UPDATE setting_value='$new'");
  $admin_name = $_SESSION['admin_data']['name'] ?? 'CCS Admin';
  $conn->query("INSERT INTO activity_logs (actor,action) VALUES ('$admin_name','Toggled reservation: ".($new==='1'?'Enabled':'Disabled')."')");
  header('Location: dashboard.php'); exit;
}

$reserv_enabled = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='reservation_enabled'")->fetch_row()[0] ?? '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Admin Dashboard — CCS</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="page-wrapper">

  <?php if(isset($_GET['ann'])): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> Announcement posted!</div>
  <?php endif; ?>

  <div class="stats-grid">
    <div class="stat-card"><div class="stat-icon navy"><i class="fas fa-users"></i></div><div><div class="stat-value"><?=$total_students?></div><div class="stat-label">Total Students</div></div></div>
    <div class="stat-card"><div class="stat-icon red"><i class="fas fa-desktop"></i></div><div><div class="stat-value"><?=$active_sitin?></div><div class="stat-label">Active Sit-ins</div></div></div>
    <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-list"></i></div><div><div class="stat-value"><?=$total_sitin?></div><div class="stat-label">Total Sit-ins</div></div></div>
    <div class="stat-card"><div class="stat-icon gold"><i class="fas fa-calendar"></i></div><div><div class="stat-value"><?=$pending_reserv?></div><div class="stat-label">Pending Reservations</div></div></div>
    <div class="stat-card"><div class="stat-icon green"><i class="fas fa-star"></i></div><div><div class="stat-value"><?=$avg_rating?> ★</div><div class="stat-label">Avg Rating</div></div></div>
    <div class="stat-card"><div class="stat-icon purple"><i class="fas fa-quote-left"></i></div><div><div class="stat-value"><?=$pending_testi?></div><div class="stat-label">Pending Testimonials</div></div></div>
  </div>

  <div class="d-flex flex-wrap gap-1 mb-2">
    <a href="analytics.php"    class="btn btn-primary"><i class="fas fa-chart-line"></i> Analytics</a>
    <a href="pc_control.php"   class="btn btn-info"><i class="fas fa-tv"></i> PC Control</a>
    <a href="software.php"     class="btn btn-secondary"><i class="fas fa-cube"></i> Software</a>
    <a href="logs.php"         class="btn btn-secondary"><i class="fas fa-clipboard-list"></i> Logs</a>
    <a href="testimonials.php" class="btn btn-secondary">
      <i class="fas fa-quote-left"></i> Testimonials
      <?php if($pending_testi>0): ?><span class="badge badge-danger"><?=$pending_testi?></span><?php endif; ?>
    </a>
    <a href="?toggle_reserv=1"
       class="btn <?=$reserv_enabled==='1'?'btn-danger':'btn-success'?>"
       onclick="return confirm('<?=$reserv_enabled==='1'?'Disable':'Enable'?> reservations?')">
      <i class="fas fa-<?=$reserv_enabled==='1'?'ban':'check'?>"></i>
      <?=$reserv_enabled==='1'?'Disable':'Enable'?> Reservations
    </a>
    <a href="analytics.php?export=csv" class="btn btn-success"><i class="fas fa-download"></i> Export CSV</a>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <div class="card">
      <div class="card-header"><i class="fas fa-chart-line"></i> Sit-ins (Last 7 Days)</div>
      <div class="card-body"><div class="chart-container"><canvas id="dailyChart"></canvas></div></div>
    </div>
    <div class="card">
      <div class="card-header"><i class="fas fa-chart-bar"></i> Sit-ins by Lab</div>
      <div class="card-body"><div class="chart-container"><canvas id="labChart"></canvas></div></div>
    </div>
    <div class="card">
      <div class="card-header"><i class="fas fa-chart-pie"></i> Sit-ins by Purpose</div>
      <div class="card-body"><div class="chart-container"><canvas id="purposeChart"></canvas></div></div>
    </div>
    <div class="card">
      <div class="card-header"><i class="fas fa-trophy"></i> Top Students</div>
      <div class="card-body">
        <?php foreach($top_students as $i=>$ts): ?>
          <div class="d-flex align-center justify-between mb-1">
            <div class="d-flex align-center gap-1">
              <span style="width:20px;font-weight:700;color:var(--text-muted)">#<?=$i+1?></span>
              <span style="font-size:0.83rem"><?=htmlspecialchars($ts['firstname'].' '.$ts['lastname'])?></span>
              <span class="badge badge-secondary"><?=$ts['course']?></span>
            </div>
            <span class="badge badge-info"><?=$ts['total']?> sit-ins</span>
          </div>
          <div class="progress mb-1">
            <div class="progress-bar blue" style="width:<?=$top_students[0]['total']>0?round($ts['total']/$top_students[0]['total']*100):0?>%"></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php if($pending_res): ?>
  <div class="card mt-2">
    <div class="card-header"><i class="fas fa-calendar-check"></i> Pending Reservations <span class="badge badge-danger"><?=count($pending_res)?></span></div>
    <div class="table-wrap" style="padding:0">
      <table>
        <thead><tr><th>Student</th><th>Course</th><th>Lab</th><th>Purpose</th><th>Date</th><th>Time</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($pending_res as $r): ?>
          <tr>
            <td><?=htmlspecialchars($r['firstname'].' '.$r['lastname'])?></td>
            <td><?=$r['course']?></td>
            <td><?=$r['lab']?></td>
            <td><?=htmlspecialchars($r['purpose'])?></td>
            <td><?=$r['date']?></td>
            <td><?=date('h:i A',strtotime($r['time_in']))?></td>
            <td>
              <a href="reservation_admin.php?approve=<?=$r['id']?>" class="btn btn-success btn-sm"><i class="fas fa-check"></i></a>
              <a href="reservation_admin.php?reject=<?=$r['id']?>"  class="btn btn-danger btn-sm"><i class="fas fa-times"></i></a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px">
    <div class="card">
      <div class="card-header"><i class="fas fa-clock"></i> Recent Sit-ins</div>
      <div class="table-wrap" style="padding:0">
        <table>
          <thead><tr><th>Student</th><th>Lab</th><th>Status</th><th>Time In</th></tr></thead>
          <tbody>
            <?php foreach($recent_sitins as $r): ?>
            <tr>
              <td><?=htmlspecialchars($r['firstname'].' '.$r['lastname'])?></td>
              <td><?=$r['lab']?></td>
              <td><span class="badge badge-<?=$r['status']==='active'?'success':'secondary'?>"><?=$r['status']?></span></td>
              <td style="font-size:0.73rem"><?=date('M d, h:i A',strtotime($r['time_in']))?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="fas fa-bullhorn"></i> Announcements</div>
      <div class="card-body">
        <form method="POST" class="mb-2">
          <div class="form-group">
            <textarea name="content" class="form-control" rows="2" placeholder="Post a new announcement..." required></textarea>
          </div>
          <button type="submit" name="post_announcement" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane"></i> Post</button>
        </form>
        <?php foreach($announcements as $a): ?>
          <div class="announcement-item">
            <div class="ann-date"><i class="fas fa-clock"></i> <?=date('M d, Y h:i A',strtotime($a['created_at']))?> &mdash; <?=htmlspecialchars($a['posted_by'])?></div>
            <div class="ann-text"><?=htmlspecialchars($a['content'])?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php if($testimonials): ?>
  <div class="card mt-2">
    <div class="card-header"><i class="fas fa-quote-left"></i> Student Testimonials</div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:12px">
        <?php foreach($testimonials as $t): ?>
          <div class="testimonial-card">
            <div class="tc-header">
              <div class="tc-avatar"><?=strtoupper(substr($t['firstname'],0,1).substr($t['lastname'],0,1))?></div>
              <div>
                <div class="tc-name"><?=htmlspecialchars($t['firstname'].' '.$t['lastname'])?></div>
                <div class="tc-course"><?=$t['course']?> — Year <?=$t['year_level']?></div>
              </div>
              <div class="stars ml-auto"><?=str_repeat('★',$t['rating'])?></div>
            </div>
            <div class="tc-message">"<?=htmlspecialchars($t['message'])?>"</div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="mt-1 text-right">
        <a href="testimonials.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-right"></i> Manage All</a>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>

<script>
const COLORS = ['#ef233c','#2b2d42','#f4a022','#17a2b8','#28a745','#6f42c1'];
const gc = ()=> document.documentElement.classList.contains('dark') ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.05)';
const tc = ()=> document.documentElement.classList.contains('dark') ? '#8d99ae' : '#6c757d';
const so = ()=>({ x:{grid:{color:gc()},ticks:{color:tc()}}, y:{grid:{color:gc()},ticks:{color:tc()},beginAtZero:true} });

new Chart('dailyChart',{type:'line',data:{labels:<?=json_encode(array_column($daily,'d'))?>,datasets:[{label:'Sit-ins',data:<?=json_encode(array_column($daily,'cnt'))?>,borderColor:'#ef233c',backgroundColor:'rgba(239,35,60,0.08)',tension:.4,fill:true,pointBackgroundColor:'#ef233c',pointRadius:4}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:so()}});
new Chart('labChart',{type:'bar',data:{labels:<?=json_encode(array_column($per_lab,'lab'))?>,datasets:[{label:'Sit-ins',data:<?=json_encode(array_column($per_lab,'cnt'))?>,backgroundColor:COLORS,borderRadius:5}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:so()}});
new Chart('purposeChart',{type:'doughnut',data:{labels:<?=json_encode(array_column($per_purpose,'purpose'))?>,datasets:[{data:<?=json_encode(array_column($per_purpose,'cnt'))?>,backgroundColor:COLORS,borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'right',labels:{color:tc(),boxWidth:11,font:{size:10}}}}}});
</script>
</body>
</html>
