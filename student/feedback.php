<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireStudent();
$s = $_SESSION['student_data'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $sitin_id = (int)$_POST['sitin_id'];
  $rating   = (int)$_POST['rating'];
  $comment  = trim($_POST['comment'] ?? '');

  if ($rating >= 1 && $rating <= 5) {
    // Verify this sitin belongs to the student
    $chk = $conn->prepare("SELECT id FROM sitin_records WHERE id=? AND student_id=? AND status='done'");
    $chk->bind_param('ii', $sitin_id, $s['id']);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
      $stmt = $conn->prepare("INSERT INTO feedback (student_id, sitin_id, rating, comment) VALUES (?,?,?,?)");
      $stmt->bind_param('iiis', $s['id'], $sitin_id, $rating, $comment);
      $stmt->execute();
      $stmt->close();
    }
    $chk->close();
  }
}

header('Location: dashboard.php?fb=1');
exit;