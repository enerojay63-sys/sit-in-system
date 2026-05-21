<?php
// admin/nav.php
$nav_feedback_count = $conn->query("SELECT COUNT(*) FROM feedback WHERE DATE(created_at) = CURDATE()")->fetch_row()[0];
$nav_pending_reserv = $conn->query("SELECT COUNT(*) FROM reservations WHERE status='pending'")->fetch_row()[0];
$nav_active_sitins  = $conn->query("SELECT COUNT(*) FROM sitin_records WHERE status='active'")->fetch_row()[0];
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="topnav">
  <a href="dashboard.php" class="topnav-brand"><i class="fas fa-desktop"></i> CCS Admin</a>
  <div class="topnav-links">
    <a href="dashboard.php"         <?= $current_page==='dashboard.php'       ?'class="active"':'' ?>><i class="fas fa-home"></i> Home</a>
    <a href="search.php"            <?= $current_page==='search.php'          ?'class="active"':'' ?>><i class="fas fa-search"></i> Search</a>
    <a href="students.php"          <?= $current_page==='students.php'        ?'class="active"':'' ?>><i class="fas fa-users"></i> Students</a>
    <a href="sitin.php" <?= $current_page==='sitin.php'?'class="active"':'' ?> style="position:relative">
      <i class="fas fa-desktop"></i> Sit-in
      <?php if($nav_active_sitins>0): ?><span class="nav-badge nav-badge-gold"><?=$nav_active_sitins?></span><?php endif; ?>
    </a>
    <a href="sitin_records.php"     <?= $current_page==='sitin_records.php'   ?'class="active"':'' ?>><i class="fas fa-list"></i> Records</a>
    <a href="sitin_reports.php"     <?= $current_page==='sitin_reports.php'   ?'class="active"':'' ?>><i class="fas fa-chart-bar"></i> Reports</a>
    <a href="feedback_reports.php"  <?= $current_page==='feedback_reports.php'?'class="active"':'' ?> style="position:relative">
      <i class="fas fa-comments"></i> Feedback
      <?php if($nav_feedback_count>0): ?><span class="nav-badge nav-badge-red"><?=$nav_feedback_count?></span><?php endif; ?>
    </a>
    <a href="reservation_admin.php" <?= $current_page==='reservation_admin.php'?'class="active"':'' ?> style="position:relative">
      <i class="fas fa-calendar"></i> Reservations
      <?php if($nav_pending_reserv>0): ?><span class="nav-badge nav-badge-red"><?=$nav_pending_reserv?></span><?php endif; ?>
    </a>
    <a href="pc_control.php"    <?= $current_page==='pc_control.php'   ?'class="active"':'' ?>><i class="fas fa-tv"></i> PC Control</a>
    <a href="software.php"      <?= $current_page==='software.php'     ?'class="active"':'' ?>><i class="fas fa-cube"></i> Software</a>
    <a href="analytics.php"     <?= $current_page==='analytics.php'    ?'class="active"':'' ?>><i class="fas fa-chart-line"></i> Analytics</a>
    <a href="logs.php"          <?= $current_page==='logs.php'         ?'class="active"':'' ?>><i class="fas fa-clipboard-list"></i> Logs</a>
    <a href="testimonials.php"  <?= $current_page==='testimonials.php' ?'class="active"':'' ?>><i class="fas fa-quote-left"></i> Testimonials</a>
    <button class="dark-toggle" onclick="toggleDark()" id="darkBtn" title="Toggle dark mode">
      <i class="fas fa-moon" id="darkIcon"></i>
    </button>
    <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Log out</a>
  </div>
</nav>
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
  const icon   = document.getElementById('darkIcon');
  if (icon) icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
  localStorage.setItem('theme', isDark ? 'dark' : 'light');
}
</script>
