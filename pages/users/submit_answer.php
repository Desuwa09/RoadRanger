<?php
session_start();
require_once __DIR__ . '/../../db/db_con.php';

$level_id = isset($_GET['level_id']) ? intval($_GET['level_id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
if (!isset($_SESSION['user_id']) || $level_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

$conn = db_connect();
$user_id = intval($_SESSION['user_id']);

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("INSERT INTO scores (user_id, game_name, score, date_taken) VALUES (?, 'hotspot_test', 10, NOW())");
    $stmt->execute([$user_id]);

    $game_name = 'hotspot_test';
    $stage_number = $level_id;
    $progress_percent = 100.0;
    $completion_date = date('Y-m-d H:i:s');

    $stmtModule = $conn->prepare('SELECT module_id FROM learning_modules LIMIT 1');
    $stmtModule->execute();
    $moduleRow = $stmtModule->fetch(PDO::FETCH_ASSOC);
    $module_id = $moduleRow ? intval($moduleRow['module_id']) : null;

    $stmtCheck = $conn->prepare('SELECT progress_id FROM progress WHERE user_id = ? AND game_name = ? AND stage_number = ? LIMIT 1');
    $stmtCheck->execute([$user_id, $game_name, $stage_number]);
    $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $stmtUpdate = $conn->prepare('UPDATE progress SET is_completed = ?, progress_percent = ?, completion_date = ? WHERE progress_id = ?');
        $stmtUpdate->execute([1, $progress_percent, $completion_date, $existing['progress_id']]);
    } else {
        $stmtInsert = $conn->prepare('INSERT INTO progress (user_id, module_id, game_name, stage_number, is_completed, progress_percent, completion_date) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmtInsert->execute([$user_id, $module_id, $game_name, $stage_number, 1, $progress_percent, $completion_date]);
    }

    $totalStmt = $conn->prepare("SELECT COUNT(*) AS total_levels FROM game_levels gl JOIN games g ON gl.game_id = g.game_id WHERE g.game_key = 'hotspot_test'");
    $totalStmt->execute();
    $totalRow = $totalStmt->fetch(PDO::FETCH_ASSOC);
    $totalLevels = intval($totalRow['total_levels'] ?? 0);

    if ($totalLevels > 0) {
        $completedStmt = $conn->prepare('SELECT COUNT(*) AS completed_count FROM progress WHERE user_id = ? AND game_name = ? AND stage_number > 0 AND is_completed = 1');
        $completedStmt->execute([$user_id, $game_name]);
        $completedRow = $completedStmt->fetch(PDO::FETCH_ASSOC);
        $completedCount = intval($completedRow['completed_count'] ?? 0);

        if ($completedCount >= $totalLevels) {
            $stmtGameCheck = $conn->prepare('SELECT progress_id FROM progress WHERE user_id = ? AND game_name = ? AND stage_number = 0 LIMIT 1');
            $stmtGameCheck->execute([$user_id, $game_name]);
            $gameRow = $stmtGameCheck->fetch(PDO::FETCH_ASSOC);

            if ($gameRow) {
                $stmtGameUpdate = $conn->prepare('UPDATE progress SET is_completed = ?, progress_percent = ?, completion_date = ? WHERE progress_id = ?');
                $stmtGameUpdate->execute([1, $progress_percent, $completion_date, $gameRow['progress_id']]);
            } else {
                $stmtInsertGame = $conn->prepare('INSERT INTO progress (user_id, module_id, game_name, stage_number, is_completed, progress_percent, completion_date) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmtInsertGame->execute([$user_id, $module_id, $game_name, 0, 1, $progress_percent, $completion_date]);
            }
        }
    }

    $conn->commit();

    echo "<h2>Hazard Found!</h2>";
    echo "<p>Great job. Redirecting...</p>";
    echo "<script>setTimeout(() => { window.location.href = 'dashboard.php'; }, 2000);</script>";
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "Error saving progress: " . $e->getMessage();
}
?>