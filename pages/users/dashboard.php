<?php
    session_start();
    require_once __DIR__ . '/../../db/db_con.php';

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    $conn = db_connect();
    $user_id = $_SESSION['user_id'];

    $profile_message = '';
    $profile_message_type = '';
    $profile_form = [
        'first_name' => '',
        'last_name' => '',
        'gender' => '',
        'phone' => '',
        'birthday' => ''
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $profile_form['first_name'] = trim($_POST['first_name'] ?? '');
        $profile_form['last_name'] = trim($_POST['last_name'] ?? '');
        $profile_form['gender'] = trim($_POST['gender'] ?? '');
        $profile_form['phone'] = trim($_POST['phone'] ?? '');
        $profile_form['birthday'] = trim($_POST['birthday'] ?? '');

        if ($profile_form['first_name'] === '' || $profile_form['last_name'] === '') {
            $profile_message = 'Please enter both your first and last name.';
            $profile_message_type = 'error';
        } else {
            try {
                $update_stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, gender = ?, phone = ?, birthday = ? WHERE user_id = ?");
                $update_stmt->execute([
                    $profile_form['first_name'],
                    $profile_form['last_name'],
                    $profile_form['gender'],
                    $profile_form['phone'],
                    $profile_form['birthday'],
                    $user_id
                ]);

                $_SESSION['first_name'] = $profile_form['first_name'];
                $_SESSION['last_name'] = $profile_form['last_name'];

                $profile_message = 'Profile updated successfully.';
                $profile_message_type = 'success';
            } catch (PDOException $e) {
                $profile_message = 'Unable to update your profile right now.';
                $profile_message_type = 'error';
            }
        }
    }

    
    $stmt = $conn->prepare("SELECT first_name, last_name, gender, phone, birthday, current_difficulty FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]); 
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $profile_form['first_name'] = $profile_form['first_name'] !== '' ? $profile_form['first_name'] : ($user['first_name'] ?? '');
        $profile_form['last_name'] = $profile_form['last_name'] !== '' ? $profile_form['last_name'] : ($user['last_name'] ?? '');
        $profile_form['gender'] = $profile_form['gender'] !== '' ? $profile_form['gender'] : ($user['gender'] ?? '');
        $profile_form['phone'] = $profile_form['phone'] !== '' ? $profile_form['phone'] : ($user['phone'] ?? '');
        $profile_form['birthday'] = $profile_form['birthday'] !== '' ? $profile_form['birthday'] : ($user['birthday'] ?? '');
    }

    $hazard_modules = [];
    $learning_modules = [];
    $memory_progress = ['percent' => 0, 'is_completed' => 0];
    $conveyor_progress = ['percent' => 0, 'is_completed' => 0];
    $module_stats = ['total' => 0, 'completed' => 0];
    $certificate_modules = [];
    try {
        
        $player_difficulty = $user['current_difficulty'] ?? 'EASY';

        
        
        $module_stmt = $conn->prepare("SELECT gl.level_id, gl.title, gl.difficulty, gl.description 
                                       FROM game_levels gl 
                                       JOIN games g ON gl.game_id = g.game_id 
                                       WHERE g.game_key = 'hotspot_test' 
                                       ORDER BY 
                                           CASE WHEN LOWER(gl.difficulty) = LOWER(?) THEN 0 ELSE 1 END ASC,
                                           gl.level_id ASC");
        $module_stmt->execute([$player_difficulty]);
        $hazard_modules = $module_stmt->fetchAll(PDO::FETCH_ASSOC);

        $learning_stmt = $conn->prepare("SELECT lm.module_id, lm.chapter_number, lm.title, lm.description, lm.created_at, lm.certificate_template, COALESCE(p.progress_percent, 0) AS progress_percent, COALESCE(p.is_completed, 0) AS is_completed FROM learning_modules lm LEFT JOIN progress p ON p.module_id = lm.module_id AND p.user_id = ? AND p.game_name = 'learning_module' AND p.stage_number = 0 ORDER BY lm.chapter_number ASC, lm.module_id ASC");
        $learning_stmt->execute([$user_id]);
        $learning_modules = $learning_stmt->fetchAll(PDO::FETCH_ASSOC);

        $certificate_stmt = $conn->prepare("SELECT lm.module_id, lm.title, lm.certificate_template, COALESCE(p.is_completed, 0) AS is_completed, c.certificate_id, c.issue_date FROM learning_modules lm LEFT JOIN progress p ON p.module_id = lm.module_id AND p.user_id = ? AND p.game_name = 'learning_module' AND p.stage_number = 0 LEFT JOIN certificates c ON c.module_id = lm.module_id AND c.user_id = ? WHERE lm.certificate_template IS NOT NULL AND lm.certificate_template <> '' ORDER BY lm.chapter_number ASC, lm.module_id ASC");
        $certificate_stmt->execute([$user_id, $user_id]);
        $certificate_modules = $certificate_stmt->fetchAll(PDO::FETCH_ASSOC);

        $memory_stmt = $conn->prepare("SELECT progress_percent, is_completed FROM progress WHERE user_id = ? AND game_name = 'memory_game' AND stage_number = 0 LIMIT 1");
        $memory_stmt->execute([$user_id]);
        $memory_progress_row = $memory_stmt->fetch(PDO::FETCH_ASSOC);
        if ($memory_progress_row) {
            $memory_progress['percent'] = intval($memory_progress_row['progress_percent'] ?? 0);
            $memory_progress['is_completed'] = intval($memory_progress_row['is_completed'] ?? 0);
        }

        $conveyor_stmt = $conn->prepare("SELECT progress_percent, is_completed FROM progress WHERE user_id = ? AND game_name = 'conveyor_mania' AND stage_number = 0 LIMIT 1");
        $conveyor_stmt->execute([$user_id]);
        $conveyor_progress_row = $conveyor_stmt->fetch(PDO::FETCH_ASSOC);
        if ($conveyor_progress_row) {
            $conveyor_progress['percent'] = intval($conveyor_progress_row['progress_percent'] ?? 0);
            $conveyor_progress['is_completed'] = intval($conveyor_progress_row['is_completed'] ?? 0);
        }

        $module_count_stmt = $conn->prepare("SELECT COUNT(*) AS total_modules FROM learning_modules");
        $module_count_stmt->execute();
        $module_count_row = $module_count_stmt->fetch(PDO::FETCH_ASSOC);
        $module_stats['total'] = intval($module_count_row['total_modules'] ?? 0);

        $completed_module_stmt = $conn->prepare("SELECT COUNT(*) AS completed_modules FROM progress WHERE user_id = ? AND game_name = 'learning_module' AND stage_number = 0 AND is_completed = 1");
        $completed_module_stmt->execute([$user_id]);
        $completed_module_row = $completed_module_stmt->fetch(PDO::FETCH_ASSOC);
        $module_stats['completed'] = intval($completed_module_row['completed_modules'] ?? 0);
    } catch (PDOException $e) {
        $hazard_modules = [];
        $learning_modules = [];
    }

    $selected_module_language = isset($_GET['module_lang']) && in_array(strtolower($_GET['module_lang']), ['en', 'tl']) ? strtolower($_GET['module_lang']) : 'en';

    $hazard_progress_percent = 0;
    $hazard_completed_count = 0;
    $hazard_total_levels = 0;

    try {
        $totalStmt = $conn->prepare("SELECT COUNT(*) AS total_levels FROM game_levels gl JOIN games g ON gl.game_id = g.game_id WHERE g.game_key = 'hotspot_test'");
        $totalStmt->execute();
        $totalRow = $totalStmt->fetch(PDO::FETCH_ASSOC);
        $hazard_total_levels = intval($totalRow['total_levels'] ?? 0);

        $completedStmt = $conn->prepare("SELECT COUNT(*) AS completed_count FROM progress WHERE user_id = ? AND game_name = 'hotspot_test' AND stage_number > 0 AND is_completed = 1");
        $completedStmt->execute([$user_id]);
        $completedRow = $completedStmt->fetch(PDO::FETCH_ASSOC);
        $hazard_completed_count = intval($completedRow['completed_count'] ?? 0);

        if ($hazard_total_levels > 0) {
            $hazard_progress_percent = round(($hazard_completed_count / $hazard_total_levels) * 100);
        }
    } catch (PDOException $e) {
        $hazard_progress_percent = 0;
    }

    
    $initials = "";
    if ($user && !empty($user['first_name']) && !empty($user['last_name'])) {
        $initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
    } else {
        $initials = strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1));
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RoadRangers Dashboard</title>
    <style>
        body { 
            font-family: sans-serif; 
            margin: 0; 
            padding: 0; 
            background: #eef2f3; 
            display: flex;
        }

        .sidebar {
            width: 240px;
            background: #2c3e50;
            color: white;
            min-height: 100vh;
            padding-top: 20px;
        }
        .sidebar-title {
            text-align: center;
            font-size: 18px;
            margin-bottom: 30px;
            font-weight: bold;
        }
        .nav-btn {
            width: 100%;
            padding: 15px 20px;
            background: none;
            border: none;
            color: #b8c7ce;
            text-align: left;
            font-size: 16px;
            cursor: pointer;
            transition: 0.2s;
        }
        .nav-btn:hover, .nav-btn.active {
            background: #1a252f;
            color: white;
        }
        .nav-btn.profile-btn {
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: 8px;
        }
        .sidebar .logout-btn {
            color: #e74c3c;
            display: block;
            padding: 15px 20px;
            text-decoration: none;
            margin-top: 50px;
        }

        .main-content {
            flex-grow: 1;
            padding: 30px;
        }
        .app-view {
            display: none; 
        }
        .app-view.active {
            display: block; 
        }

        .test-panel { 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            max-width: 400px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        .profile-circle {
            width: 80px; height: 80px;
            background: #007bff; color: white;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 32px; font-weight: bold; margin-bottom: 15px;
        }
        .data-row { margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .label { font-weight: bold; color: #555; }

        .game-wrapper {
            background: white;
            padding: 25px;
            border-radius: 8px;
            max-width: 600px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        .mg-header, .cm-header, .sh-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        #mg-plate-display {
            background: #f8f9fa;
            border: 3px solid #333;
            border-radius: 6px;
            padding: 20px;
            text-align: center;
            font-size: 36px;
            font-weight: bold;
            letter-spacing: 2px;
            margin: 20px 0;
        }
        .progress-container {
            background: #e9ecef;
            border-radius: 4px;
            margin: 15px 0;
            overflow: hidden;
            position: relative;
            height: 20px;
        }
        #mg-progress-fill, #cm-progress-fill, #sh-progress-fill, .module-progress-fill {
            background: #2ecc71;
            height: 100%;
            width: 0%;
            transition: width 0.3s;
        }
        #mg-progress-text, #cm-progress-text, .module-progress-text {
            position: absolute; width: 100%; text-align: center;
            top: 0; left: 0; font-size: 12px; line-height: 20px; font-weight: bold;
        }
        .mg-feedback, .cm-feedback {
            padding: 10px; border-radius: 4px; margin: 15px 0; font-weight: bold;
        }
        .mg-feedback.info, .cm-feedback.info { background: #d1ecf1; color: #0c5460; }
        .mg-feedback.success, .cm-feedback.success { background: #d4edda; color: #155724; }
        .mg-feedback.error, .cm-feedback.error { background: #f8d7da; color: #721c24; }
        
        .game-controls button {
            padding: 10px 20px; font-size: 14px; font-weight: bold;
            cursor: pointer; margin-right: 5px;
        }
        .is-disabled { opacity: 0.5; pointer-events: none; }

        .cm-conveyor-belt {
            background: #34495e;
            border-radius: 6px;
            padding: 20px;
            min-height: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px 0;
            position: relative;
            border-bottom: 6px solid #2c3e50;
        }
        #cm-active-sign {
            width: 90px;
            height: 90px;
            object-fit: contain;
            cursor: grab;
            user-select: none;
            touch-action: none;
        }
        #cm-active-sign.is-dragging {
            cursor: grabbing;
            opacity: 0.8;
            z-index: 999;
        }
        .cm-dropzones-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        .cm-dropzone {
            background: #f1f2f6;
            border: 2px dashed #7f8c8d;
            border-radius: 6px;
            padding: 20px 10px;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: background 0.2s, border-color 0.2s;
        }
        .cm-dropzone.is-over {
            background: #dfe4ea;
            border-color: #2980b9;
        }
        
        .cm-dropzone.correct-flash { background: #2ecc71 !important; color: white; border-color: #27ae60; }
        .cm-dropzone.wrong-flash { background: #e74c3c !important; color: white; border-color: #c0392b; }

        .hazard-card-box {
            border-left: 5px solid #e67e22;
        }
        .sh-dropdown {
            width: 100%;
            padding: 12px;
            margin: 15px 0;
            border: 2px solid #ced4da;
            border-radius: 6px;
            font-size: 15px;
            background: #fff;
            font-weight: 500;
        }
        .sh-launch-btn {
            background: #e67e22;
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 15px;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            text-align: center;
            display: block;
            box-sizing: border-box;
            transition: background 0.2s;
        }
        .sh-launch-btn:hover {
            background: #d35400;
        }
        .empty-modules-notice {
            color: #7f8c8d;
            font-style: italic;
            padding: 10px 0;
        }
        .profile-settings-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 20px;
            max-width: 600px;
        }
        .stats-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .stat-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
        }
        .stat-box h4 {
            margin: 0 0 8px 0;
            color: #0f172a;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
        }
        .stat-label {
            color: #64748b;
            font-size: 13px;
            margin-top: 4px;
        }
        .module-progress-list {
            margin-top: 18px;
            display: grid;
            gap: 10px;
        }
        .module-progress-item {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            background: #fff;
        }
        .module-progress-item strong {
            display: block;
            margin-bottom: 6px;
        }
        .dashboard-wrapper {
            display: grid;
            grid-template-columns: 400px 1.8fr;
            gap: 25px;
            align-items: start;
        }
        .events-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 100%;
        }
        .events-card h3 {
            margin-top: 0;
            color: #0f172a;
        }
        .event-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .event-item {
            border-left: 4px solid #3b82f6;
            background: #f0f9ff;
            padding: 12px;
            border-radius: 6px;
        }
        .event-item.completed {
            border-left-color: #10b981;
            background: #f0fdf4;
        }
        .event-item.in-progress {
            border-left-color: #f59e0b;
            background: #fffbf0;
        }
        .event-time {
            font-size: 12px;
            color: #64748b;
        }
        .event-title {
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .event-desc {
            font-size: 13px;
            color: #475569;
        }
        .no-events {
            color: #94a3b8;
            font-style: italic;
            padding: 20px;
            text-align: center;
        }
        @media (max-width: 1200px) {
            .dashboard-wrapper {
                grid-template-columns: 1fr;
            }
        }
        .profile-settings-card h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        .profile-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }
        .profile-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .profile-form-group label {
            font-weight: bold;
            color: #34495e;
        }
        .profile-form-group input,
        .profile-form-group select {
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
        }
        .profile-form-actions {
            margin-top: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .profile-form-actions button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
        }
        .profile-message {
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 12px;
            font-size: 14px;
        }
        .profile-message.success {
            background: #d4edda;
            color: #155724;
        }
        .profile-message.error {
            background: #f8d7da;
            color: #721c24;
        }
        @media (max-width: 700px) {
            .profile-form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-title">RoadRangers Panel</div>
        <button id="btn-dashboard" class="nav-btn active" onclick="switchView('dashboard')">Dashboard</button>
        <button id="btn-memory" class="nav-btn" onclick="switchView('memory')">Memory Game</button>
        <button id="btn-conveyor" class="nav-btn" onclick="switchView('conveyor')">Conveyor Mania</button>
        <button id="btn-hazard" class="nav-btn" onclick="switchView('hazard')">Spot the Hazard</button>
        <button id="btn-modules" class="nav-btn" onclick="switchView('modules')">Modules</button>
        <button id="btn-profile" class="nav-btn profile-btn" onclick="switchView('profile')">Profile Settings</button>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="main-content">

        <div id="view-dashboard" class="app-view active">
            <div class="dashboard-wrapper">
                <div>
                    <div class="test-panel">
                        <h2>Student Dashboard Profile</h2>
                        <div class="profile-circle"><?php echo $initials; ?></div>
                        <div class="data-row"><span class="label">Full Name:</span> <?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></div>
                        <div class="data-row"><span class="label">Gender:</span> <?php echo htmlspecialchars($user['gender'] ?? 'Not Set'); ?></div>
                        <div class="data-row">
                            <span class="label">System Difficulty:</span> 
                            <span style="color: green; font-weight: bold;"><?php echo strtoupper(htmlspecialchars($user['current_difficulty'] ?? 'EASY')); ?></span>
                        </div>
                        <div class="data-row"><span class="label">User ID (Session):</span> <?php echo $_SESSION['user_id']; ?></div>
                    </div>
                </div>

                <div>
                    <div class="events-card">
                        <h3>Upcoming Events</h3>
                        <div class="event-list">
                            <?php 
                                $events = [];
                                try {
                                    $events_stmt = $conn->prepare("SELECT event_id, title, description, event_date, status FROM events WHERE status = 'active' ORDER BY event_date ASC LIMIT 10");
                                    $events_stmt->execute();
                                    $events = $events_stmt->fetchAll(PDO::FETCH_ASSOC);
                                } catch (PDOException $e) {
                                    $events = [];
                                }

                                if (!empty($events)):
                                    foreach ($events as $event):
                                        $is_upcoming = strtotime($event['event_date']) > time();
                                        $event_class = $is_upcoming ? 'in-progress' : 'completed';
                                        $event_date = date('M d, Y g:i A', strtotime($event['event_date']));
                                ?>
                                        <div class="event-item <?php echo $event_class; ?>">
                                            <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                                            <div class="event-desc"><?php echo htmlspecialchars($event['description']); ?></div>
                                            <div class="event-time"><?php echo $event_date; ?></div>
                                        </div>
                                <?php 
                                    endforeach;
                                else:
                            ?>
                                    <div class="no-events">No upcoming events. Check back later!</div>
                            <?php 
                                endif;
                            ?>
                        </div>
                    </div>

                    <div class="stats-card" style="margin-top: 25px;">
                        <h3>Your Progress Statistics</h3>
                        <div class="stats-grid">
                            <div class="stat-box">
                                <h4>Memory Game</h4>
                                <div class="stat-value"><?php echo intval($memory_progress['percent']); ?>%</div>
                                <div class="stat-label"><?php echo intval($memory_progress['is_completed']) ? 'Completed' : 'In progress'; ?></div>
                            </div>
                            <div class="stat-box">
                                <h4>Conveyor Mania</h4>
                                <div class="stat-value"><?php echo intval($conveyor_progress['percent']); ?>%</div>
                                <div class="stat-label"><?php echo intval($conveyor_progress['is_completed']) ? 'Completed' : 'In progress'; ?></div>
                            </div>
                            <div class="stat-box">
                                <h4>Spot the Hazard</h4>
                                <div class="stat-value"><?php echo intval($hazard_progress_percent); ?>%</div>
                                <div class="stat-label"><?php echo $hazard_completed_count; ?> of <?php echo $hazard_total_levels; ?> levels completed</div>
                            </div>
                            <div class="stat-box">
                                <h4>Learning Modules</h4>
                                <div class="stat-value"><?php echo intval($module_stats['completed']); ?>/<?php echo intval($module_stats['total']); ?></div>
                                <div class="stat-label">completed modules</div>
                            </div>
                        </div>

                        <?php if (!empty($learning_modules)): ?>
                            <div class="module-progress-list">
                                <h4 style="margin: 0 0 8px 0; color: #0f172a;">Module Progress</h4>
                                <?php foreach ($learning_modules as $module): ?>
                                    <div class="module-progress-item">
                                        <strong><?php echo htmlspecialchars($module['title']); ?></strong>
                                        <div class="progress-container" style="margin-bottom: 6px;">
                                            <div class="module-progress-fill" style="width: <?php echo intval($module['progress_percent']); ?>%;"></div>
                                            <div class="module-progress-text"><?php echo intval($module['progress_percent']); ?>%</div>
                                        </div>
                                        <span style="font-size: 12px; color: <?php echo intval($module['is_completed']) ? '#16a34a' : '#7f9cf5'; ?>; font-weight: bold;">
                                            <?php echo intval($module['is_completed']) ? 'Completed' : 'In progress'; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div id="view-profile" class="app-view">
            <div class="profile-settings-card">
                <h3>Profile Settings</h3>
                <?php if ($profile_message !== ''): ?>
                    <div class="profile-message <?php echo htmlspecialchars($profile_message_type); ?>">
                        <?php echo htmlspecialchars($profile_message); ?>
                    </div>
                <?php endif; ?>
                <form method="POST" action="">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="profile-form-grid">
                        <div class="profile-form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($profile_form['first_name']); ?>" required>
                        </div>
                        <div class="profile-form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($profile_form['last_name']); ?>" required>
                        </div>
                        <div class="profile-form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="" <?php echo ($profile_form['gender'] === '' ? 'selected' : ''); ?>>Select</option>
                                <option value="Male" <?php echo ($profile_form['gender'] === 'Male' ? 'selected' : ''); ?>>Male</option>
                                <option value="Female" <?php echo ($profile_form['gender'] === 'Female' ? 'selected' : ''); ?>>Female</option>
                                <option value="Other" <?php echo ($profile_form['gender'] === 'Other' ? 'selected' : ''); ?>>Other</option>
                            </select>
                        </div>
                        <div class="profile-form-group">
                            <label for="phone">Phone Number</label>
                            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($profile_form['phone']); ?>">
                        </div>
                        <div class="profile-form-group" style="grid-column: 1 / -1;">
                            <label for="birthday">Birthday</label>
                            <input type="date" id="birthday" name="birthday" value="<?php echo htmlspecialchars($profile_form['birthday']); ?>">
                        </div>
                    </div>
                    <div class="profile-form-actions">
                        <button type="submit">Save Profile</button>
                        <span style="color: #6c757d; font-size: 13px;">Your name will be updated instantly.</span>
                    </div>
                </form>
            </div>

            <div class="profile-settings-card">
                <h3>Certificates</h3>
                <?php if (empty($certificate_modules)): ?>
                    <p style="color: #6c757d;">Certificates will appear here when an administrator attaches one to a learning module.</p>
                <?php else: ?>
                    <?php foreach ($certificate_modules as $certificate_module): ?>
                        <div class="data-row" style="display: flex; justify-content: space-between; align-items: center; gap: 15px; flex-wrap: wrap;">
                            <div>
                                <strong><?php echo htmlspecialchars($certificate_module['title']); ?></strong>
                                <div style="font-size: 13px; color: #6c757d; margin-top: 4px;">
                                    <?php if ($certificate_module['certificate_id']): ?>
                                        Claimed on <?php echo date('M d, Y', strtotime($certificate_module['issue_date'])); ?>
                                    <?php elseif (intval($certificate_module['is_completed'])): ?>
                                        Completed and ready to claim
                                    <?php else: ?>
                                        Complete this module to unlock the certificate
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($certificate_module['certificate_id']): ?>
                                <a href="certificate.php?certificate_id=<?php echo intval($certificate_module['certificate_id']); ?>" target="_blank" rel="noopener" class="profile-form-actions button" style="background: #198754; color: white; padding: 9px 13px; border-radius: 6px; text-decoration: none; font-size: 13px;">View Certificate</a>
                            <?php elseif (intval($certificate_module['is_completed'])): ?>
                                <a href="certificate.php?action=claim&module_id=<?php echo intval($certificate_module['module_id']); ?>" class="profile-form-actions button" style="background: #007bff; color: white; padding: 9px 13px; border-radius: 6px; text-decoration: none; font-size: 13px;">Claim Certificate</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div id="view-memory" class="app-view">
            <div id="memory-game-card" class="game-wrapper">
                <h2>Memory Game Module</h2>
                <div class="mg-header">
                    <span id="mg-stage-display">Stage 1</span>
                    <span id="mg-level-display">Level 1</span>
                </div>
                <div class="progress-container">
                    <div id="mg-progress-fill"></div>
                    <div id="mg-progress-text">0%</div>
                </div>
                <div style="margin-bottom: 10px;">
                    Display Timer: <span id="mg-display-timer">00:00</span> | Guess Timer: <span id="mg-guess-timer">00:00</span>
                </div>
                <div id="mg-plate-display"><span class="plate-number">---</span></div>
                <div id="mg-input-container" style="display: none; text-align: center; margin: 20px 0;">
                    <label for="mg-user-input" style="display: block; font-weight: bold; margin-bottom: 8px;">Type the Plate Number:</label>
                    <input type="text" id="mg-user-input" placeholder="ABC-1234" autocomplete="off" style="padding: 12px; font-size: 24px; font-weight: bold; text-align: center; text-transform: uppercase; letter-spacing: 2px; width: 80%; max-width: 300px; border: 2px solid #ced4da; border-radius: 6px;">
                </div>
                <div id="mg-medium-builder" style="display: none; margin: 20px 0; padding: 16px; border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fafc; text-align: center;">
                    <div style="font-weight: bold; margin-bottom: 12px;">Choose the Exact Plate Parts</div>
                    <div style="margin-bottom: 10px; font-size: 13px; color: #475569;">Find the exact letter group and exact number group that match the plate number you saw.</div>
                    <div id="mg-builder-slot-label" style="margin-bottom: 10px; font-size: 13px; color: #1d4ed8; font-weight: bold;">Active selection: Letter group</div>
                    <div style="display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; margin: 8px 0 16px 0;">
                        <div id="mg-letter-slots" style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: center;"></div>
                        <div id="mg-number-slots" style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: center;"></div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 10px;">
                        <div>
                            <div style="font-weight: bold; margin-bottom: 8px;">Letters</div>
                            <div id="mg-letter-options" style="display: flex; flex-wrap: wrap; gap: 6px; justify-content: center;"></div>
                        </div>
                        <div>
                            <div style="font-weight: bold; margin-bottom: 8px;">Numbers</div>
                            <div id="mg-number-options" style="display: flex; flex-wrap: wrap; gap: 6px; justify-content: center;"></div>
                        </div>
                    </div>
                </div>
                <div style="margin: 12px 0 8px 0; text-align: center;">
                    <label for="mg-difficulty-select" style="display: block; font-weight: bold; margin-bottom: 6px;">Select Mode</label>
                    <select id="mg-difficulty-select" style="padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1; min-width: 220px; text-align: center;">
                        <option value="">Choose Easy / Medium / Hard</option>
                        <option value="easy">Easy</option>
                        <option value="medium">Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
                <div id="mg-feedback" class="mg-feedback info">Choose a mode to begin the Memory Game.</div>
                <div class="game-controls">
                    <button id="mg-start-btn" disabled>Start Game</button>
                    <button id="mg-complete-btn" disabled>Submit Answer</button>
                    <button id="mg-next-level-btn" style="display: none;">Next Level</button>
                </div>
            </div>
        </div>

        <div id="view-conveyor" class="app-view">
            <div id="conveyor-mania-game" class="game-wrapper">
                <h2>Conveyor Mania</h2>
                <div class="cm-header">
                    <span>Time Remaining: <span id="cm-time-left">02:00</span></span>
                    <span>Sorted: <span id="cm-sorted-count">0</span> / <span id="cm-total-count">0</span></span>
                </div>
                <div class="progress-container">
                    <div id="cm-progress-fill"></div>
                    <div id="cm-progress-text">0%</div>
                </div>
                
                <div id="cm-current-sign-label" style="font-weight: bold; color: #34495e;">Current Sign: --</div>
                <div class="cm-conveyor-belt">
                    <img id="cm-active-sign" src="" alt="Active sign element" style="display: none;">
                </div>

                <div class="cm-dropzones-container">
                    <div class="cm-dropzone" data-category="regulatory">
                        <strong>Regulatory</strong>
                        <span style="font-size: 11px; color: #7f8c8d; margin-top:4px;">(Mandatory Rules)</span>
                    </div>
                    <div class="cm-dropzone" data-category="warning">
                        <strong>Warning</strong>
                        <span style="font-size: 11px; color: #7f8c8d; margin-top:4px;">(Hazards Ahead)</span>
                    </div>
                    <div class="cm-dropzone" data-category="informative">
                        <strong>Informative</strong>
                        <span style="font-size: 11px; color: #7f8c8d; margin-top:4px;">(Directions/Info)</span>
                    </div>
                </div>

                <div id="cm-feedback" class="cm-feedback info">Conveyor ready. Swap tabs to activate game speed cycles!</div>
                <div class="game-controls">
                    <button id="cm-skip-btn">Skip Sign</button>
                </div>
            </div>
        </div>

        <div id="view-hazard" class="app-view">
            <div id="spot-hazard-card" class="game-wrapper hazard-card-box">
                <h2>🔍 Spot the Hazard (The Test)</h2>
                <div class="progress-container" style="margin-bottom: 20px;">
                    <div id="sh-progress-fill" style="width: <?php echo $hazard_progress_percent; ?>%;"></div>
                    <div id="sh-progress-text"><?php echo $hazard_progress_percent; ?>%</div>
                </div>
                <p style="margin: 0 0 20px 0; color: #475569; font-size: 14px;">
                    Completed <?php echo $hazard_completed_count; ?> of <?php echo $hazard_total_levels; ?> scenario levels
                </p>
                <div class="scenarios-list">
                    <?php if (empty($hazard_modules)): ?>
                        <p class="empty-modules-notice">No active hazard modules found.</p>
                    <?php else: ?>
                        <?php foreach ($hazard_modules as $level): ?>
                            <div class="scenario-card" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 5px; background: #fff;">
                                <h3><?php echo htmlspecialchars($level['title']); ?></h3>
                                <p><?php echo htmlspecialchars($level['description']); ?></p>
                                <p>
                                    <strong>Difficulty:</strong> 
                                    <span style="font-weight: bold; color: <?php echo (strtoupper($level['difficulty']) === 'HARD') ? '#e74c3c' : '#2ecc71'; ?>;">
                                        <?php echo strtoupper(htmlspecialchars($level['difficulty'])); ?>
                                    </span>
                                </p>
                                <a href="play_scenario.php?level_id=<?php echo $level['level_id']; ?>" class="sh-launch-btn" style="text-decoration: none;">Play Now</a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="view-modules" class="app-view">
            <div class="game-wrapper">
                <h2>📚 Learning Modules</h2>
                <p style="color: #475569; margin-bottom: 20px;">These modules are pulled directly from the admin-created content database.</p>

                <?php if (empty($learning_modules)): ?>
                    <p class="empty-modules-notice">No learning modules have been created yet. Please check back later.</p>
                <?php else: ?>
                    <div class="scenarios-list">
                        <?php foreach ($learning_modules as $module): ?>
                            <div class="scenario-card" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 5px; background: #fff; position: relative;">
                                <h3><?php echo htmlspecialchars($module['title']); ?></h3>
                                <p><?php echo htmlspecialchars($module['description']); ?></p>
                                <div class="progress-container" style="margin-bottom: 10px;">
                                    <div class="module-progress-fill" style="width: <?php echo intval($module['progress_percent']); ?>%;"></div>
                                    <div class="module-progress-text"><?php echo intval($module['progress_percent']); ?>%</div>
                                </div>
                                <p style="font-size: 12px; color: #64748b; margin-bottom: 12px;">Chapter <?php echo intval($module['chapter_number']); ?> • Published <?php echo date('M d, Y', strtotime($module['created_at'])); ?></p>
                                <p style="font-size: 12px; color: <?php echo intval($module['is_completed']) ? '#16a34a' : '#7f9cf5'; ?>; margin-bottom: 12px; font-weight: bold;">
                                    <?php echo intval($module['is_completed']) ? 'Completed' : 'Not completed'; ?>
                                </p>
                                <a href="view_module.php?module_id=<?php echo intval($module['module_id']); ?>&lang=<?php echo $selected_module_language; ?>" class="sh-launch-btn module-open-link" style="display: inline-block; background: #3b82f6; color: white; padding: 10px 14px; border-radius: 6px; text-decoration: none;">Open Module</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="scenario-card" style="border: 1px dashed #cbd5e1; padding: 15px; margin-top: 20px; border-radius: 5px; background: #f8fafc;">
                    <h3>Placeholder: System Module</h3>
                    <p>This card represents the other existing chatbot-style module that will be available soon.</p>
                    <p style="font-size: 12px; color: #64748b;">The UI is ready to show modules using the same database model when it is added.</p>
                    <button class="sh-launch-btn" style="text-decoration: none; cursor: not-allowed; opacity: 0.5;">Coming Soon</button>
                </div>
            </div>
        </div>

        

    </div>

    <script>
        function switchView(viewName) {
            document.querySelectorAll('.app-view').forEach(view => view.classList.remove('active'));
            document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('active'));

            document.getElementById('view-' + viewName).classList.add('active');
            
            const currentButton = document.getElementById('btn-' + viewName);
            if (currentButton) {
                currentButton.classList.add('active');
            }

            window.dispatchEvent(new CustomEvent("learnTab:sectionChanged", {
                detail: { sectionId: viewName }
            }));
        }

        function launchHazardSimulation() {
            const selector = document.getElementById('hazard-module-selector');
            if (!selector) return;
            
            const selectedId = selector.value;
            if (selectedId) {
                window.location.href = `play_scenario.php?level_id=${selectedId}`;
            }
        }

        function updateModuleLanguageLinks(language) {
            document.querySelectorAll('.module-open-link').forEach(link => {
                const url = new URL(link.href, window.location.origin);
                url.searchParams.set('lang', language);
                link.href = url.pathname + '?' + url.searchParams.toString();
            });
        }

        const moduleLanguageSelect = document.getElementById('module-language-select');
        if (moduleLanguageSelect) {
            moduleLanguageSelect.addEventListener('change', e => updateModuleLanguageLinks(e.target.value));
            updateModuleLanguageLinks(moduleLanguageSelect.value);
        }
    </script>
    <script>
        const ROADRANGER_SAVE_URL = "<?php echo '/' . basename(dirname(dirname(__DIR__))) . '/pages/users/memory_progress.php'; ?>";
        console.log("Global Application URL mapped as:", ROADRANGER_SAVE_URL);
    </script>
    
    <script src="../../assets/js/memory_game.js?v=<?php echo time(); ?>"></script>
    <script src="../../assets/js/conveyorMania.js?v=<?php echo time(); ?>"></script>
    
</body>
</html>