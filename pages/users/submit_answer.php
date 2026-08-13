<?php
session_start();
require_once __DIR__ . '/../../db/db_con.php';

$level_id = isset($_GET['level_id']) ? intval($_GET['level_id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
$item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;
$elapsed_seconds = isset($_GET['elapsed_seconds']) ? max(0, intval($_GET['elapsed_seconds'])) : 0;
$is_ajax_request = isset($_GET['ajax']) && $_GET['ajax'] == 1;

if (!isset($_SESSION['user_id']) || $level_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

$conn = db_connect();
$user_id = intval($_SESSION['user_id']);

$selectedViolation = 'Unknown violation';
if ($item_id > 0) {
    $itemStmt = $conn->prepare('SELECT item_label FROM game_items WHERE item_id = ? AND level_id = ? LIMIT 1');
    $itemStmt->execute([$item_id, $level_id]);
    $itemRow = $itemStmt->fetch(PDO::FETCH_ASSOC);

    if ($itemRow && !empty($itemRow['item_label'])) {
        $selectedViolation = $itemRow['item_label'];
    }
}

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

    if ($is_ajax_request) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'level_id' => $level_id,
            'item_id' => $item_id,
            'elapsed_seconds' => $elapsed_seconds,
            'selected_violation' => $selectedViolation
        ]);
        exit;
    }

    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Spot the Hazard Result</title>';
    echo '<style>';
    echo 'body { font-family: Arial, sans-serif; background: #f8fafc; color: #1f2937; margin: 0; padding: 40px 20px; }';
    echo '.result-card { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px; box-shadow: 0 8px 25px rgba(15, 23, 42, 0.08); }';
    echo 'h2 { margin-top: 0; color: #0f172a; }';
    echo '.pill { display: inline-block; background: #dbeafe; color: #1d4ed8; padding: 6px 10px; border-radius: 999px; font-weight: bold; margin-top: 12px; }';
    echo '.btn { display: inline-block; margin-top: 20px; padding: 12px 18px; background: #2563eb; color: #fff; border-radius: 8px; text-decoration: none; font-weight: bold; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    echo '<div class="result-card">';
    echo '<h2>Hazard Found!</h2>';
    echo '<p><strong>Result:</strong> You correctly identified the hazard zone.</p>';
    echo '<p><strong>Violation shown:</strong> ' . htmlspecialchars($selectedViolation) . '</p>';
    echo '<div class="pill">Progress saved to your account</div>';
    echo '<p><a class="btn" href="dashboard.php">Back to Dashboard</a></p>';
    echo '</div>';
    echo '</body>';
    echo '</html>';
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "Error saving progress: " . $e->getMessage();
}
?>