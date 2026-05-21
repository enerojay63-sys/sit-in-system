<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

$success = $error = '';
$admin_name = $_SESSION['admin_data']['name'] ?? 'CCS Admin';

// ── Add software ──
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_software'])) {
  $lab  = trim($_POST['lab']);
  $name = trim($_POST['name']);
  $ver  = trim($_POST['version']);
  $desc = trim($_POST['description']);
  if ($lab && $name) {
    $stmt = $conn->prepare("INSERT INTO software (lab, name, version, description) VALUES (?,?,?,?)");
    $stmt->bind_param('ssss', $lab, $name, $ver, $desc);
    $stmt->execute(); $stmt->close();
    $conn->query("INSERT INTO activity_logs (actor,action,target) VALUES ('$admin_name','Added software: $name','Lab $lab')");
    $success = "Software '$name' added to Lab $lab!";
  } else {
    $error = "Lab and software name are required.";
  }
}

// ── Delete software ──
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  $sw = $conn->query("SELECT name, lab FROM software WHERE id=$id")->fetch_assoc();
  if ($sw) {
    $conn->query("DELETE FROM software WHERE id=$id");
    $conn->query("INSERT INTO activity_logs (actor,action,target) VALUES ('$admin_name','Deleted software: {$sw['name']}','Lab {$sw['lab']}')");
    $success = "Software deleted.";
  }
}

// FIXED: labs match pc_control (524, 526, 528 etc.) — pull from DB dynamically
$lab_res = $conn->query("SELECT DISTINCT lab FROM pc_control ORDER BY lab");
$labs = [];
while ($r = $lab_res->fetch_row()) $labs[] = $r[0];
if (empty($labs)) $labs = ['524','526','528'];

// ── Software per lab ──
$software = [];
foreach ($labs as $lab) {
  $safe = $conn->real_escape_string($lab);
  $software[$lab] = $conn->query("SELECT * FROM software WHERE lab='$safe' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
}

$icons = [
  'visual studio'=>'fab fa-microsoft','vscode'=>'fab fa-microsoft','xampp'=>'fas fa-server',
  'android'=>'fab fa-android','java'=>'fab fa-java','photoshop'=>'fas fa-image',
  'figma'=>'fab fa-figma','python'=>'fab fa-python','jupyter'=>'fas fa-book',
  'mysql'=>'fas fa-database','postman'=>'fas fa-paper-plane','git'=>'fab fa-git-alt',
  'chrome'=>'fab fa-chrome','node'=>'fab fa-node-js','php'=>'fab fa-php',
  'html'=>'fab fa-html5','css'=>'fab fa-css3-alt','js'=>'fab fa-js',
  'react'=>'fab fa-react','wordpress'=>'fab fa-wordpress',
];

function getSoftwareIcon($name, $icons) {
  $lower = strtolower($name);
  foreach ($icons as $key => $icon) { if (str_contains($lower,$key)) return $icon; }
  return 'fas fa-cube';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Software — CCS Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    .software-item { display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid var(--border); }
    .software-item:last-child { border-bottom:none; }
    .software-icon { width:36px;height:36px;border-radius:8px;background:linear-gradient(135deg,var(--prussian),var(--cerulean));
                     color:#fff;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0; }
    .sw-name  { font-weight:600;font-size:0.85rem;color:var(--prussian); }
    html.dark .sw-name { color:var(--text); }
    .sw-version { font-size:0.72rem;color:var(--text-muted);margin-top:2px; }

    .tab-nav  { display:flex;gap:4px;margin-bottom:16px;border-bottom:2px solid var(--border);padding-bottom:0; }
    .tab-btn  { background:none;border:none;padding:9px 16px;font-family:inherit;font-size:0.82rem;
                font-weight:600;color:var(--text-muted);cursor:pointer;border-radius:6px 6px 0 0;
                transition:all .18s;display:flex;align-items:center;gap:6px;
                border-bottom:3px solid transparent;margin-bottom:-2px; }
    .tab-btn:hover  { color:var(--cerulean);background:rgba(33,158,188,.07); }
    .tab-btn.active { color:var(--prussian);border-bottom-color:var(--cerulean);background:rgba(33,158,188,.07); }
    html.dark .tab-btn.active { color:var(--sky); }
    .tab-panel        { display:none; }
    .tab-panel.active { display:block; }
  </style>
</head>
<body>
<?php include 'nav.php'; ?>

<div class="page-wrapper">
  <div class="section-title"><i class="fas fa-cube"></i> Lab Software Management</div>

  <?php if($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($success)?></div><?php endif; ?>
  <?php if($error):   ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($error)?></div><?php endif; ?>

  <div style="display:grid;grid-template-columns:300px 1fr;gap:20px">

    <!-- Add Form -->
    <div class="card">
      <div class="card-header"><i class="fas fa-plus-circle"></i> Add Software</div>
      <div class="card-body">
        <form method="POST">
          <div class="form-group">
            <label>Lab</label>
            <select name="lab" class="form-control" required>
              <option value="">— Select Lab —</option>
              <?php foreach($labs as $lab): ?>
                <option value="<?=$lab?>">Lab <?=$lab?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Software Name</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Visual Studio Code" required>
          </div>
          <div class="form-group">
            <label>Version</label>
            <input type="text" name="version" class="form-control" placeholder="e.g. 1.88">
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Short description..."></textarea>
          </div>
          <button type="submit" name="add_software" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-plus"></i> Add Software</button>
        </form>
      </div>
    </div>

    <!-- Software List -->
    <div class="card">
      <div class="card-header"><i class="fas fa-list"></i> Installed Software by Lab</div>
      <div class="card-body">
        <div class="tab-nav">
          <?php foreach($labs as $i => $lab): ?>
            <button class="tab-btn <?=$i===0?'active':''?>" onclick="switchTab('sw<?=$i?>',this)">
              <i class="fas fa-desktop"></i> Lab <?=$lab?>
              <span class="badge badge-info"><?=count($software[$lab])?></span>
            </button>
          <?php endforeach; ?>
        </div>

        <?php foreach($labs as $i => $lab): ?>
          <div id="sw<?=$i?>" class="tab-panel <?=$i===0?'active':''?>">
            <?php if(empty($software[$lab])): ?>
              <p class="text-muted text-center" style="padding:20px">No software listed for Lab <?=$lab?>.</p>
            <?php else: ?>
              <?php foreach($software[$lab] as $sw): ?>
                <div class="software-item">
                  <div class="software-icon"><i class="<?=getSoftwareIcon($sw['name'],$icons)?>"></i></div>
                  <div style="flex:1">
                    <div class="sw-name"><?=htmlspecialchars($sw['name'])?>
                      <?php if($sw['version']): ?>
                        <span class="badge badge-secondary" style="font-size:0.65rem"><?=htmlspecialchars($sw['version'])?></span>
                      <?php endif; ?>
                    </div>
                    <?php if($sw['description']): ?>
                      <div class="sw-version"><?=htmlspecialchars($sw['description'])?></div>
                    <?php endif; ?>
                  </div>
                  <a href="?delete=<?=$sw['id']?>" class="btn btn-danger btn-sm"
                     onclick="return confirm('Delete <?=htmlspecialchars($sw['name'],ENT_QUOTES)?>?')">
                    <i class="fas fa-trash"></i>
                  </a>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script>
(function(){ const t=localStorage.getItem('theme')||'light'; if(t==='dark') document.documentElement.classList.add('dark'); })();
function switchTab(id,btn){
  document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.getElementById(id).classList.add('active'); btn.classList.add('active');
}
</script>
</body>
</html>
