<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function requireStudent() {
  if (empty($_SESSION['student_id'])) {
    header('Location: ../index.php'); exit;
  }
}

function requireAdmin() {
  if (empty($_SESSION['admin_id'])) {
    header('Location: ../admin/login.php'); exit;
  }
}

function isLoggedInStudent() {
  return !empty($_SESSION['student_id']);
}

function isLoggedInAdmin() {
  return !empty($_SESSION['admin_id']);
}

function fullName($s) {
  $mid = $s['midname'] ? ' '.$s['midname'].' ' : ' ';
  return $s['firstname'].$mid.$s['lastname'];
}