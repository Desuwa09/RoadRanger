<?php
session_start();
require_once __DIR__ . '/../../db/db_con.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = db_connect();
$level_id = isset($_GET['level_id']) ? intval($_GET['level_id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);

if ($level_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

try {
    $level_stmt = $conn->prepare("SELECT title, description, background_image FROM game_levels WHERE level_id = ?");
    $level_stmt->execute([$level_id]);
    $level = $level_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$level) {
        header('Location: dashboard.php');
        exit;
    }

    $items_stmt = $conn->prepare("SELECT item_id, shape_type, pos_x, pos_y, width, height FROM game_items WHERE level_id = ?");
    $items_stmt->execute([$level_id]);
    $hotspots = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($level['title']); ?></title>
    <style>
        .scenario-container {
            position: relative;
            display: inline-block;
            line-height: 0;
        }
        .scenario-img {
            max-width: 100%;
            height: auto;
            display: block;
        }
        .hotspot {
            position: absolute;
            z-index: 10;
            cursor: pointer;
            background: rgba(0, 0, 0, 0);
            border: none;
            outline: none;
        }
        .hotspot.circle {
            border-radius: 50%;
        }
    </style>
</head>
<body>

    <h1><?php echo htmlspecialchars($level['title']); ?></h1>
    <p><?php echo htmlspecialchars($level['description']); ?></p>

    <div class="scenario-container">
        <img src="<?php echo htmlspecialchars($level['background_image']); ?>" alt="Scenario" class="scenario-img">

        <?php foreach ($hotspots as $item): ?>
            <a href="submit_answer.php?level_id=<?php echo $level_id; ?>&item_id=<?php echo $item['item_id']; ?>" 
               class="hotspot <?php echo ($item['shape_type'] === 'circle') ? 'circle' : ''; ?>"
               style="left: <?php echo $item['pos_x']; ?>%; 
                      top: <?php echo $item['pos_y']; ?>%; 
                      width: <?php echo $item['width']; ?>%; 
                      height: <?php echo $item['height']; ?>%;">
            </a>
        <?php endforeach; ?>
    </div>

</body>
</html>