<?php
session_start();

require_once __DIR__ . '/../../db/db_con.php';

$conn = db_connect();
header('Content-Type: application/json');


if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$game_name = isset($_POST['game_name']) ? trim($_POST['game_name']) : 'conveyor_mania';
$stage_number = isset($_POST['stage_number']) ? intval($_POST['stage_number']) : 1;
$is_completed = isset($_POST['is_completed']) ? intval($_POST['is_completed']) : 1;
$total_stages = isset($_POST['total_stages']) ? intval($_POST['total_stages']) : 1;
$progress_percent = isset($_POST['progress_percent']) ? floatval($_POST['progress_percent']) : 0;

$completion_date = date('Y-m-d H:i:s');

try {



    $stmtModule = $conn->prepare('SELECT module_id FROM learning_modules LIMIT 1');
    $stmtModule->execute();
    $moduleRow = $stmtModule->fetch(PDO::FETCH_ASSOC);
    $module_id = $moduleRow ? intval($moduleRow['module_id']) : null;

    $stmtCheck = $conn->prepare('SELECT progress_id FROM progress WHERE user_id = ? AND game_name = ? AND stage_number = ? LIMIT 1');
    $stmtCheck->execute([$user_id, $game_name, $stage_number]);
    $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existing) {

        $stmtUpdate = $conn->prepare('UPDATE progress SET is_completed = ?, progress_percent = ?, completion_date = ? WHERE progress_id = ?');
        $stmtUpdate->execute([$is_completed, $progress_percent, $completion_date, $existing['progress_id']]);
    } else {
        $stmtInsert = $conn->prepare('INSERT INTO progress (user_id, module_id, game_name, stage_number, is_completed, progress_percent, completion_date) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmtInsert->execute([$user_id, $module_id, $game_name, $stage_number, $is_completed, $progress_percent, $completion_date]);
    }

    $game_completed = false;

    if ($is_completed === 1) {
        $game_completed = true;
        $stage_zero = 0;
        $game_done = 1;

        $stmtGameCheck = $conn->prepare('SELECT progress_id FROM progress WHERE user_id = ? AND game_name = ? AND stage_number = 0 LIMIT 1');
        $stmtGameCheck->execute([$user_id, $game_name]);
        $gameRow = $stmtGameCheck->fetch(PDO::FETCH_ASSOC);

        if ($gameRow) {
            $stmtGameUpdate = $conn->prepare('UPDATE progress SET is_completed = ?, progress_percent = ?, completion_date = ? WHERE progress_id = ?');
            $stmtGameUpdate->execute([$game_done, $progress_percent, $completion_date, $gameRow['progress_id']]);
        } else {
            $stmtInsertGame = $conn->prepare('INSERT INTO progress (user_id, module_id, game_name, stage_number, is_completed, progress_percent, completion_date) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmtInsertGame->execute([$user_id, $module_id, $game_name, $stage_zero, $game_done, $progress_percent, $completion_date]);
        }
    }

    echo json_encode([
        'success' => true,
        'game_completed' => $game_completed,
        'current_stage' => $stage_number
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database operation failed: ' . $e->getMessage()]);
}
?>