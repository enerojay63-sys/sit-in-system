<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

$error = $success = '';

// DELETE
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  $conn->query("DELETE FROM students WHERE id=$id");
  $success = 'Student deleted.';
}

// RESET ALL SESSIONS
if (isset($_POST['reset_sessions'])) {
  $conn->query("UPDATE students SET remaining_session=30");
  $success = 'All sessions reset to 30.';
}

// EDIT
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_student'])) {
  $id         = (int)$_POST['edit_id'];
  $lastname   = trim($_POST['lastname']);
  $firstname  = trim($_POST['firstname']);
  $midname    = trim($_POST['midname']);
  $course     = $_POST['course'];
  $year_level = (int)$_POST['year_level'];
  $email      = trim($_POST['email']);
  $session    = (int)$_POST['remaining_session'];
  $stmt = $conn->prepare("UPDATE students SET lastname=?,firstname=?,midname=?,course=?,year_level=?,email=?,remaining_session=? WHERE id=?");
  $stmt->bind_param('ssssissi',$lastname,$firstname,$midname,$course,$year_level,$email,$session,$id);
  $stmt->execute(); $stmt->close();
  $success = 'Student updated.';
}

// ADD STUDENT
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_student'])) {
  $id_number  = trim($_POST['id_number']);
  $lastname   = trim($_POST['lastname']);
  $firstname  = trim($_POST['firstname']);
  $midname    = trim($_POST['midname']);
  $course     = $_POST['course'];
  $year_level = (int)$_POST['year_level'];
  $email      = trim($_POST['email']);
  $password   = password_hash('student123', PASSWORD_DEFAULT);
  $stmt = $conn->prepare("INSERT INTO students (id_number,lastname,firstname,midname,course,year_level,email,password) VALUES (?,?,?,?,?,?,?,?)");
  $stmt->bind_param('ssssiss',$id_number,$lastname,$firstname,$midname,$course,$year_level,$email,$password);
  if ($stmt->execute()) $success = 'Student added. Default password: student123';
  else $error = 'ID Number or Email already exists.';
  $stmt->close();
}

$students = $conn->query("SELECT * FROM students ORDER BY lastname, firstname");
$edit_student = null;
if (isset($_GET['edit'])) {
  $eid = (int)$_GET['edit'];
  $r = $conn->prepare("SELECT * FROM students WHERE id=?");
  $r->bind_param('i',$eid); $r->execute();
  $edit_student = $r->get_result()->fetch_assoc();
  $r->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Students</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- FIXED: was cdnds.cloudflare.com (typo) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'nav.php'; ?>

<div class="page-wrapper">
  <div class="section-title"><i class="fas fa-users"></i> Students Information</div>

  <?php if($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <div style="display:flex;gap:10px;margin-bottom:16px;">
    <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('open')"><i class="fas fa-plus"></i> Add Student</button>
    <form method="POST" style="display:inline;">
      <button type="submit" name="reset_sessions" class="btn btn-danger" onclick="return confirm('Reset ALL sessions to 30?')"><i class="fas fa-redo"></i> Reset All Sessions</button>
    </form>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="dt-top">
        <div class="dt-top-left">
          <div class="dt-entries">
            <select id="entrySel" onchange="renderTable()"><option value="10">10</option><option value="25">25</option><option value="50">50</option></select>
            <span class="dt-label">entries per page</span>
          </div>
        </div>
        <div class="dt-top-right">
          <span class="dt-label">Search:</span>
          <div class="dt-search"><input type="text" id="searchBox" oninput="renderTable()" placeholder="Search..."></div>
        </div>
      </div>
      <div class="dt-wrapper">
        <table class="data-table">
          <thead><tr><th>ID Number</th><th>Name</th><th>Year Level</th><th>Course</th><th>Remaining Session</th><th>Actions</th></tr></thead>
          <tbody id="tableBody"></tbody>
        </table>
      </div>
      <div class="dt-pagination">
        <span id="showInfo"></span>
        <div class="dt-pages" id="pages"></div>
      </div>
    </div>
  </div>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="addModal">
  <div class="modal-box" style="max-width:500px;">
    <div class="modal-header"><span><i class="fas fa-user-plus"></i> Add Student</span><button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">×</button></div>
    <div class="modal-body">
      <form method="POST">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group"><label>ID Number *</label><input type="text" name="id_number" class="form-control" required></div>
          <div class="form-group"><label>Email *</label><input type="email" name="email" class="form-control" required></div>
          <div class="form-group"><label>Last Name *</label><input type="text" name="lastname" class="form-control" required></div>
          <div class="form-group"><label>First Name *</label><input type="text" name="firstname" class="form-control" required></div>
          <div class="form-group"><label>Middle Name</label><input type="text" name="midname" class="form-control"></div>
          <div class="form-group"><label>Course *</label>
            <select name="course" class="form-control" required>
              <option value="BSIT">BSIT</option><option value="BSCS">BSCS</option><option value="BSIS">BSIS</option><option value="ACT">ACT</option>
            </select>
          </div>
          <div class="form-group"><label>Year Level *</label>
            <select name="year_level" class="form-control" required>
              <option value="1">1st Year</option><option value="2">2nd Year</option><option value="3">3rd Year</option><option value="4">4th Year</option>
            </select>
          </div>
        </div>
        <p style="font-size:0.77rem;color:#888;">Default password: <strong>student123</strong></p>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="document.getElementById('addModal').classList.remove('open')">Cancel</button>
          <button type="submit" name="add_student" class="btn btn-primary"><i class="fas fa-save"></i> Add</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- EDIT MODAL -->
<?php if ($edit_student): ?>
<div class="modal-overlay open" id="editModal">
  <div class="modal-box" style="max-width:500px;">
    <div class="modal-header"><span><i class="fas fa-edit"></i> Edit Student</span><a href="students.php" class="modal-close">×</a></div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="edit_id" value="<?= $edit_student['id'] ?>">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group"><label>ID Number</label><input type="text" class="form-control" value="<?= htmlspecialchars($edit_student['id_number']) ?>" readonly></div>
          <div class="form-group"><label>Email *</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($edit_student['email']) ?>" required></div>
          <div class="form-group"><label>Last Name *</label><input type="text" name="lastname" class="form-control" value="<?= htmlspecialchars($edit_student['lastname']) ?>" required></div>
          <div class="form-group"><label>First Name *</label><input type="text" name="firstname" class="form-control" value="<?= htmlspecialchars($edit_student['firstname']) ?>" required></div>
          <div class="form-group"><label>Middle Name</label><input type="text" name="midname" class="form-control" value="<?= htmlspecialchars($edit_student['midname'] ?? '') ?>"></div>
          <div class="form-group"><label>Course *</label>
            <select name="course" class="form-control" required>
              <?php foreach(['BSIT','BSCS','BSIS','ACT'] as $c): ?>
              <option value="<?=$c?>" <?=$edit_student['course']===$c?'selected':''?>><?=$c?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Year Level *</label>
            <select name="year_level" class="form-control" required>
              <?php for($y=1;$y<=4;$y++): ?>
              <option value="<?=$y?>" <?=$edit_student['year_level']==$y?'selected':''?>><?=$y?>st/nd/rd/th Year</option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="form-group"><label>Remaining Session</label><input type="number" name="remaining_session" class="form-control" value="<?= $edit_student['remaining_session'] ?>" min="0" max="30"></div>
        </div>
        <div class="modal-footer">
          <a href="students.php" class="btn btn-secondary">Cancel</a>
          <button type="submit" name="edit_student" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
(function(){ const t=localStorage.getItem('theme')||'light'; if(t==='dark') document.documentElement.classList.add('dark'); })();

const data = <?php
  $all = [];
  $res = $conn->query("SELECT * FROM students ORDER BY lastname,firstname");
  while($r=$res->fetch_assoc()) $all[]=$r;
  echo json_encode($all);
?>;
let page=1;
function renderTable(){
  const perPage=parseInt(document.getElementById('entrySel').value);
  const search=document.getElementById('searchBox').value.toLowerCase();
  const filtered=data.filter(r=>Object.values(r).some(v=>String(v).toLowerCase().includes(search)));
  const total=filtered.length;
  const totalPages=Math.max(1,Math.ceil(total/perPage));
  if(page>totalPages) page=totalPages;
  const start=(page-1)*perPage;
  const slice=filtered.slice(start,start+perPage);
  const tb=document.getElementById('tableBody');
  if(!slice.length){tb.innerHTML='<tr><td colspan="6" class="no-data">No data available</td></tr>';}
  else{
    tb.innerHTML=slice.map(r=>`<tr>
      <td>${r.id_number}</td>
      <td>${r.firstname} ${r.midname?r.midname+' ':''} ${r.lastname}</td>
      <td>${r.year_level}</td><td>${r.course}</td>
      <td><span class="badge ${parseInt(r.remaining_session)<=5?'badge-danger':'badge-success'}">${r.remaining_session}</span></td>
      <td>
        <a href="students.php?edit=${r.id}" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i> Edit</a>
        <a href="students.php?delete=${r.id}" class="btn btn-danger btn-sm" onclick="return confirm('Delete this student?')"><i class="fas fa-trash"></i> Delete</a>
      </td></tr>`).join('');
  }
  document.getElementById('showInfo').textContent=total?`Showing ${start+1} to ${Math.min(start+perPage,total)} of ${total} entries`:'Showing 0 entries';
  const pages=document.getElementById('pages');
  pages.innerHTML=`<button onclick="goPage(1)" ${page===1?'disabled':''}>«</button><button onclick="goPage(${page-1})" ${page===1?'disabled':''}>‹</button><button class="active">${page}</button><button onclick="goPage(${page+1})" ${page===totalPages?'disabled':''}>›</button><button onclick="goPage(${totalPages})" ${page===totalPages?'disabled':''}>»</button>`;
}
function goPage(p){page=p;renderTable();}
renderTable();
</script>
</body>
</html>
