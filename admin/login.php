<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
if (isLoggedInAdmin()) { header('Location: dashboard.php'); exit; }
$error = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $username = trim($_POST['username']);
  $password = $_POST['password'];
  $stmt = $conn->prepare("SELECT * FROM admins WHERE username=?");
  $stmt->bind_param('s',$username);
  $stmt->execute();
  $admin = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if ($admin && password_verify($password,$admin['password'])) {
    $_SESSION['admin_id']   = $admin['id'];
    $_SESSION['admin_data'] = $admin;
    header('Location: dashboard.php'); exit;
  } else {
    $error = 'Invalid username or password.';
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Admin Login — CCS</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    body{background:#eef0f3;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;}
    .login-card{width:360px;background:#fff;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,0.12);overflow:hidden;}
    .login-logo{text-align:center;padding:24px 0 10px;}
    .login-logo img{width:80px;height:80px;object-fit:contain;}
    .login-logo h2{font-size:1rem;color:#023047;margin-top:8px;font-weight:600;}
    .login-logo p{font-size:0.78rem;color:#888;}
    .input-wrap{position:relative;}
    .input-wrap i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#bbb;font-size:0.8rem;pointer-events:none;}
    .input-wrap .form-control{padding-left:30px;}
  </style>
</head>
<body>
<div class="login-card">
  <div class="login-logo">
    <img src="../images/ccs_logo.png" alt="CCS">
    <h2>CCS Admin Portal</h2>
    <p>Sit-in Monitoring System</p>
  </div>
  <div class="card-header"><i class="fas fa-user-shield"></i> Admin Login</div>
  <div style="padding:20px;">
    <?php if($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <label>Username</label>
        <div class="input-wrap"><i class="fas fa-user"></i>
          <input type="text" name="username" class="form-control" placeholder="Enter username" required autofocus>
        </div>
      </div>
      <div class="form-group">
        <label>Password</label>
        <div class="input-wrap"><i class="fas fa-lock"></i>
          <input type="password" name="password" class="form-control" placeholder="Enter password" required>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:6px;">
        <i class="fas fa-sign-in-alt"></i> Login
      </button>
    </form>
    <p style="text-align:center;margin-top:14px;font-size:0.78rem;">
      <a href="../index.php" style="color:#023047;text-decoration:none;"><i class="fas fa-arrow-left"></i> Back to Student Portal</a>
    </p>
  </div>
</div>
</body>
</html>
