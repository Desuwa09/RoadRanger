<?php
session_start();
require_once __DIR__ . '/../../db/db_con.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = db_connect();

$query = "SELECT gl.* FROM game_levels gl 
          JOIN games g ON gl.game_id = g.game_id 
          WHERE g.game_key = 'hotspot_test' 
          ORDER BY gl.created_at DESC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RoadRangers - Interactive Challenges</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8fafc; padding: 40px; margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; margin-top: 30px; }
        .card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); transition: 0.2s ease-in-out; display: flex; flex-direction: column; border: 1px solid #e2e8f0; }
        .card:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .card img { width: 100%; height: 200px; object-fit: cover; }
        .card-body { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
        .badge { display: inline-block; padding: 4px 8px; font-size: 11px; font-weight: bold; border-radius: 6px; text-transform: uppercase; margin-bottom: 12px; width: max-content; }
        .badge.easy { background: #d1fae5; color: #065f46; }
        .badge.medium { background: #fef3c7; color: #92400e; }
        .badge.hard { background: #fee2e2; color: #991b1b; }
        .play-btn { display: block; text-align: center; background: #6366f1; color: white; text-decoration: none; padding: 12px; border-radius: 8px; font-weight: 600; margin-top: auto; transition: 0.2s; }
        .play-btn:hover { background: #4f46e5; }
    </style>
</head>
<body>
<div class="container">
    <h1 style="color: #1e293b; margin: 0;">Road Interactive Challenges</h1>
    <p style="color: #64748b; margin-top: 5px;">Analyze structural snapshots and tap on elements violating municipal traffic regulations.</p>

    <div class="grid">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="card">
                    <img src="<?php echo htmlspecialchars($row['background_image']); ?>" alt="Traffic Assessment View">
                    <div class="card-body">
                        <span class="badge <?php echo htmlspecialchars($row['difficulty']); ?>"><?php echo htmlspecialchars($row['difficulty']); ?></span>
                        <h3 style="margin: 0 0 10px 0; color: #1e293b; font-size: 18px;"><?php echo htmlspecialchars($row['title']); ?></h3>
                        <p style="color: #64748b; font-size: 14px; margin: 0 0 20px 0; line-height: 1.5;"><?php echo htmlspecialchars($row['description']); ?></p>
                        <a href="play_scenario.php?level_id=<?php echo $row['level_id']; ?>" class="play-btn">Launch Assessment</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color: #94a3b8; grid-column: 1/-1; text-align: center; padding: 40px;">No assessment levels published yet.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>