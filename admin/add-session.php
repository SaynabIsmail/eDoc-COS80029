<?php
// Admin adds a new session
ob_start();
session_start();

if (empty($_SESSION['user']) || ($_SESSION['usertype'] ?? '') !== 'a') {
    header("location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include("../connection.php");
    $title = trim((string)($_POST["title"] ?? ''));
    $docid = (string)(int)($_POST["docid"] ?? 0);
    $nop   = max(1, (int)($_POST["nop"] ?? 0));
    $date  = (string)($_POST["date"] ?? '');
    $time  = (string)($_POST["time"] ?? '');

    $validDate = DateTime::createFromFormat('Y-m-d', $date);
    $validTime = preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time);
    if ($title === '' || $docid === '0' || !$validDate || !$validTime) {
        header("location: schedule.php?action=add-session&id=none&error=1");
        exit;
    }

    $stmt = $database->prepare("INSERT INTO schedule (docid, title, scheduledate, scheduletime, nop) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $docid, $title, $date, $time, $nop);
    $stmt->execute();
    header("location: schedule.php?action=session-added&title=" . urlencode($title));
    exit;
}
header("location: schedule.php");
exit;
