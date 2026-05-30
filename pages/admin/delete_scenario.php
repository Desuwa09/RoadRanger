<?php
session_start();
require_once __DIR__ . '/../../db/db_con.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['level_id'])) {
    header('Location: dashboard.php');
    exit;
}

$conn = db_connect();
$level_id = intval($_GET['level_id']);

try {
    $stmt = $conn->prepare("DELETE FROM game_levels WHERE level_id = ?");
    $stmt->execute([$level_id]);
    
    header('Location: dashboard.php?message=deleted');
} catch (PDOException $e) {
    die("Error deleting scenario: " . $e->getMessage());
}
?>