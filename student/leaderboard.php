<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireStudent();

$s = $_SESSION['student_data'];

$stmt = $conn->prepare("SELECT * FROM students WHERE id=?");
$stmt->bind_param('i', $s['id']);
$stmt->execute();

$s = $stmt->get_result()->fetch_assoc();
$_SESSION['student_data'] = $s;

$stmt->close();


// ─────────────────────────────
// Leaderboard Query (FIXED)
// ─────────────────────────────
$top_students = $conn->query("
    SELECT 
        s.id,
        s.id_number,
        s.firstname,
        s.lastname,
        s.course,
        s.year_level,

        COUNT(sr.id) AS total_sitins,

        ROUND(
            SUM(
                CASE
                    WHEN sr.status = 'done'
                    THEN TIMESTAMPDIFF(MINUTE, sr.time_in, sr.time_out)
                    ELSE 0
                END
            ) / 60,
        1) AS total_hours

    FROM students s

    LEFT JOIN sitin_records sr
        ON sr.student_id = s.id
        AND sr.status = 'done'

    GROUP BY s.id

    HAVING total_sitins > 0

    ORDER BY total_sitins DESC, total_hours DESC

    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);


// ─────────────────────────────
// Find Current Student Rank
// ─────────────────────────────
$my_rank = null;

foreach ($top_students as $i => $st) {
    if ($st['id'] == $s['id']) {
        $my_rank = $i + 1;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">

<title>Leaderboard — CCS</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<link rel="stylesheet" href="../css/style.css">

<style>

html.dark body {
    background: linear-gradient(
        170deg,
        #0a0d14 0%,
        #0f1117 40%,
        #1a1d27 100%
    );
}

html.dark .topnav {
    background: linear-gradient(
        135deg,
        #05101a 0%,
        #0f1117 55%,
        #0d0f1a 100%
    );
}

html.dark .card {
    background: #1a1d27;
    border-color: rgba(255,255,255,0.07);
}

html.dark .card-header {
    background: linear-gradient(
        135deg,
        #05101a,
        #0f1117
    );
}

html.dark footer {
    background: rgba(5,16,26,0.9);
    color: rgba(200,208,220,0.5);
}


/* PODIUM */

.podium {
    display:flex;
    align-items:flex-end;
    justify-content:center;
    gap:16px;
    margin:30px 0;
}

.podium-item {
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:10px;
}

.podium-avatar {
    width:56px;
    height:56px;
    border-radius:50%;
    background:linear-gradient(
        135deg,
        var(--prussian),
        var(--cerulean)
    );

    display:flex;
    align-items:center;
    justify-content:center;

    color:#fff;
    font-size:1.1rem;
    font-weight:800;

    border:3px solid rgba(255,255,255,0.3);

    box-shadow:0 8px 20px rgba(0,0,0,0.25);
}

.podium-base {
    border-radius:12px 12px 0 0;

    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:flex-end;

    padding:16px 18px 12px;

    min-width:130px;

    box-shadow:var(--shadow-lg);
}

.podium-base.p1 {
    background:linear-gradient(
        135deg,
        #b8860b,
        #ffb703
    );

    height:130px;
}

.podium-base.p2 {
    background:linear-gradient(
        135deg,
        #6b7280,
        #9ca3af
    );

    height:100px;
}

.podium-base.p3 {
    background:linear-gradient(
        135deg,
        #7c4a1e,
        #cd7f32
    );

    height:80px;
}

.podium-rank {
    font-size:1.5rem;
    margin-bottom:4px;
}

.podium-name {
    font-weight:700;
    font-size:0.8rem;
    color:#fff;
    text-align:center;
    line-height:1.3;
}

.podium-course {
    font-size:0.68rem;
    color:rgba(255,255,255,0.8);
    margin-top:2px;
}

.podium-count {
    font-size:0.72rem;
    color:rgba(255,255,255,0.9);

    margin-top:5px;

    font-weight:700;

    background:rgba(0,0,0,0.2);

    padding:2px 8px;

    border-radius:12px;
}


/* LEADERBOARD */

.lb-list {
    display:flex;
    flex-direction:column;
    gap:8px;
    padding:16px;
}

.lb-row {
    display:flex;
    align-items:center;
    gap:14px;

    padding:12px 16px;

    border-radius:10px;

    background:var(--alice);

    border:1px solid var(--border);

    transition:all 0.18s;
}

.lb-row:hover {
    background:#e8f4fa;
    border-color:var(--cerulean);
    transform:translateX(4px);
}

.lb-row.my-row {
    background:rgba(33,158,188,0.1);
    border-color:var(--cerulean);
}

html.dark .lb-row {
    background:#1e2130;
    border-color:#3d4060;
}

html.dark .lb-row:hover {
    background:#242838;
    border-color:var(--cerulean);
}

html.dark .lb-row.my-row {
    background:rgba(33,158,188,0.12);
}

.lb-rank {
    font-size:0.9rem;
    font-weight:800;
    color:var(--cerulean);

    width:28px;
    text-align:center;

    flex-shrink:0;
}

.lb-avatar {
    width:38px;
    height:38px;

    border-radius:50%;

    background:linear-gradient(
        135deg,
        var(--prussian),
        var(--cerulean)
    );

    display:flex;
    align-items:center;
    justify-content:center;

    color:#fff;

    font-size:0.75rem;
    font-weight:700;

    flex-shrink:0;
}

.lb-info {
    flex:1;
}

.lb-name {
    font-weight:700;
    color:var(--prussian);
    font-size:0.86rem;
}

html.dark .lb-name {
    color:#edf2f4;
}

.lb-meta {
    font-size:0.73rem;
    color:var(--text-muted);
    margin-top:2px;
}

.lb-score {
    text-align:right;
    flex-shrink:0;
}

.lb-sitins {
    font-weight:800;
    color:var(--honey);
    font-size:0.9rem;
}

.lb-hours {
    font-size:0.72rem;
    color:var(--text-muted);
    margin-top:2px;
}


/* MY RANK CARD */

.my-rank-card {
    background:linear-gradient(
        135deg,
        var(--prussian),
        var(--cerulean)
    );

    border-radius:var(--radius);

    padding:18px 22px;

    color:#fff;

    display:flex;
    align-items:center;
    gap:16px;

    margin-bottom:22px;

    box-shadow:var(--shadow-lg);
}

.my-rank-icon {
    font-size:2rem;
    opacity:0.9;
}

.my-rank-val {
    font-size:2.5rem;
    font-weight:900;
    line-height:1;
}

.my-rank-lbl {
    font-size:0.8rem;
    opacity:0.8;
    margin-top:3px;
}

@media(max-width:600px){

    .podium {
        gap:8px;
    }

    .podium-base {
        min-width:95px;
        padding:10px;
    }
}

</style>
</head>

<body>

<nav class="topnav">

<a href="dashboard.php" class="topnav-brand">
    Dashboard
</a>

<div class="topnav-links">

    <a href="dashboard.php">
        <i class="fas fa-home"></i> Home
    </a>

    <a href="history.php">
        <i class="fas fa-history"></i> History
    </a>

    <a href="lab_availability.php">
        <i class="fas fa-desktop"></i> Labs
    </a>

    <a href="reservation.php">
        <i class="fas fa-calendar-plus"></i> Reservation
    </a>

    <a href="edit_profile.php">
        <i class="fas fa-user-edit"></i> Profile
    </a>

    <a href="leaderboard.php" class="active">
        <i class="fas fa-trophy"></i> Leaderboard
    </a>

    <button class="dark-toggle"
    onclick="toggleDark()"
    title="Toggle dark mode">

        <i class="fas fa-moon" id="darkIcon"></i>

    </button>

    <a href="logout.php" class="btn-logout">
        <i class="fas fa-sign-out-alt"></i> Log out
    </a>

</div>
</nav>

<div class="page-content">

<?php if (empty($top_students)): ?>

<div class="card">

<div class="card-body"
style="text-align:center;padding:60px;">

<i class="fas fa-trophy"
style="font-size:3rem;display:block;margin-bottom:14px;opacity:0.2;color:var(--honey);"></i>

<p style="font-size:0.88rem;color:var(--text-muted);">
No sit-in records yet.
</p>

</div>
</div>

<?php else: ?>

<div class="card">

<div class="card-header">
<i class="fas fa-list-ol"></i> Full Rankings
</div>

<div class="lb-list">

<?php foreach ($top_students as $i => $st): ?>

<div class="lb-row <?= $st['id'] == $s['id'] ? 'my-row' : '' ?>">

<div class="lb-rank">

<?php if ($i === 0): ?>
🥇

<?php elseif ($i === 1): ?>
🥈

<?php elseif ($i === 2): ?>
🥉

<?php else: ?>
#<?= $i + 1 ?>

<?php endif; ?>

</div>

<div class="lb-avatar">

<?= strtoupper(
    substr($st['firstname'],0,1) .
    substr($st['lastname'],0,1)
) ?>

</div>

<div class="lb-info">

<div class="lb-name">

<?= htmlspecialchars(
    $st['firstname'] . ' ' . $st['lastname']
) ?>

<?php if ($st['id'] == $s['id']): ?>

<span class="badge badge-info"
style="font-size:0.65rem;margin-left:6px;">
You
</span>

<?php endif; ?>

</div>

<div class="lb-meta">

<?= $st['id_number'] ?>

&bull;

<?= $st['course'] ?>

Year <?= $st['year_level'] ?>

</div>

</div>

<div class="lb-score">

<div class="lb-sitins">

<?= $st['total_sitins'] ?>

<span style="font-size:0.7rem;color:var(--text-muted);">
sessions
</span>

</div>

<div class="lb-hours">

<?= $st['total_hours'] ?? 0 ?> hrs logged

</div>

</div>
</div>

<?php endforeach; ?>

</div>
</div>

<?php endif; ?>

</div>

<footer>
&copy; <?= date('Y') ?>
University of Cebu — College of Computer Studies.
All rights reserved.
</footer>

<script>

(function(){

    const t = localStorage.getItem('theme') || 'light';

    if (t === 'dark') {

        document.documentElement.classList.add('dark');

        const icon = document.getElementById('darkIcon');

        if (icon) {
            icon.className = 'fas fa-sun';
        }
    }

})();

function toggleDark() {

    const html = document.documentElement;

    const isDark = html.classList.toggle('dark');

    document.getElementById('darkIcon').className =
        isDark
        ? 'fas fa-sun'
        : 'fas fa-moon';

    localStorage.setItem(
        'theme',
        isDark ? 'dark' : 'light'
    );
}

</script>

</body>
</html>