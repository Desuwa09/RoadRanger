<?php
session_start();
require_once __DIR__ . '/../../db/db_con.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../users/login.php');
    exit;
}

$conn = db_connect();
$conn->begin_transaction();

try {
    $title = trim($_POST['scenario_name']);
    $description = trim($_POST['description']);
    $difficulty = $_POST['difficulty'] ?? 'easy';
    $hotspots_json = $_POST['hotspots_data'] ?? '[]';

    $game_stmt = $conn->prepare("SELECT game_id FROM games WHERE game_key = 'hotspot_test'");
    $game_stmt->execute();
    $game_result = $game_stmt->get_result();
    $game_data = $game_result->fetch_assoc();
    $game_id = $game_data['game_id'] ?? null;
    $game_stmt->close();

    if (!$game_id) {
        throw new Exception("Master game type tracking configuration not initialized.");
    }

    if (!isset($_FILES['scenario_image']) || $_FILES['scenario_image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Please upload a valid scenario background image.");
    }

    $file_tmp = $_FILES['scenario_image']['tmp_name'];
    $file_name = basename($_FILES['scenario_image']['name']);
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($file_ext, $allowed_exts)) {
        throw new Exception("Invalid image extension. System allows: JPG, PNG, WEBP.");
    }

    $upload_dir = __DIR__ . '/../../assets/imgs/Scenarios/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $unique_filename = uniqid('scene_', true) . '.' . $file_ext;
    $target_path = $upload_dir . $unique_filename;

    if (!move_uploaded_file($file_tmp, $target_path)) {
        throw new Exception("Failed to write image file asset to folder structure.");
    }

    $relative_db_path = "../../assets/imgs/Scenarios/" . $unique_filename;

    $level_stmt = $conn->prepare("INSERT INTO game_levels (game_id, title, description, difficulty, background_image) VALUES (?, ?, ?, ?, ?)");
    $level_stmt->bind_param("issss", $game_id, $title, $description, $difficulty, $relative_db_path);
    
    if (!$level_stmt->execute()) {
        throw new Exception("Failed to initialize game level record row.");
    }
    $new_level_id = $conn->insert_id;
    $level_stmt->close();

    $items_array = json_decode($hotspots_json, true);
    if (!is_array($items_array) || count($items_array) === 0) {
        throw new Exception("No active violation zones mapped on the canvas environment.");
    }

    $item_stmt = $conn->prepare("INSERT INTO game_items (level_id, item_label, shape_type, pos_x, pos_y, width, height) VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($items_array as $item) {
        $label = $item['label'] ?? 'Traffic Violation';
        $shape = $item['shape'] ?? 'rect';
        $posX  = $item['x'];
        $posY  = $item['y'];
        $w     = $item['w'];
        $h     = $item['h'];

        $item_stmt->bind_param("issdddd", $new_level_id, $label, $shape, $posX, $posY, $w, $h);
        if (!$item_stmt->execute()) {
            throw new Exception("Failure processing coordinates insert parameters.");
        }
    }
    $item_stmt->close();

    $conn->commit();
    $_SESSION['msg_success'] = "Successfully created and saved new level to the database!";
    header('Location: game_creation.php');
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['msg_error'] = "Error: " . $e->getMessage();
    header('Location: game_creation.php');
    exit;
}