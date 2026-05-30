<?php
    session_start();
    require_once __DIR__ . '/../../db/db_con.php';

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    $conn = db_connect();
    $user_id = $_SESSION['user_id'];

    // Fetch the logged-in user's profile and current system difficulty setting
    $stmt = $conn->prepare("SELECT first_name, last_name, gender, current_difficulty FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]); 
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $hazard_modules = [];
    $learning_modules = [];
    try {
        // Fallback to 'EASY' if the column value hasn't been set in the database record yet
        $player_difficulty = $user['current_difficulty'] ?? 'EASY';

        // SQL CASE statement priorities: Matching difficulty gets weight 0, others get weight 1.
        // Sorting by weight ASC puts 0 (Recommended/System Difficulty) at the top.
        $module_stmt = $conn->prepare("SELECT gl.level_id, gl.title, gl.difficulty, gl.description 
                                       FROM game_levels gl 
                                       JOIN games g ON gl.game_id = g.game_id 
                                       WHERE g.game_key = 'hotspot_test' 
                                       ORDER BY 
                                           CASE WHEN LOWER(gl.difficulty) = LOWER(?) THEN 0 ELSE 1 END ASC,
                                           gl.level_id ASC");
        $module_stmt->execute([$player_difficulty]);
        $hazard_modules = $module_stmt->fetchAll(PDO::FETCH_ASSOC);

        $learning_stmt = $conn->prepare("SELECT lm.module_id, lm.chapter_number, lm.title, lm.description, lm.created_at, COALESCE(p.progress_percent, 0) AS progress_percent, COALESCE(p.is_completed, 0) AS is_completed FROM learning_modules lm LEFT JOIN progress p ON p.module_id = lm.module_id AND p.user_id = ? AND p.game_name = 'learning_module' AND p.stage_number = 0 ORDER BY lm.chapter_number ASC, lm.module_id ASC");
        $learning_stmt->execute([$user_id]);
        $learning_modules = $learning_stmt->fetchAll(PDO::FETCH_ASSOC);
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

    // Determine user avatar initials
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

        /* Side Navigation Menu Styling */
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
        .sidebar .logout-btn {
            color: #e74c3c;
            display: block;
            padding: 15px 20px;
            text-decoration: none;
            margin-top: 50px;
        }

        /* Main Content Structure */
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

        /* Backend Test Panel Styles */
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

        /* Shared Game Wrapper Structural Styles */
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
        
        /* Memory Game Layout Styles */
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

        /* Conveyor Mania UI Layout Elements */
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
        
        /* Action Sorting Feedback Colors */
        .cm-dropzone.correct-flash { background: #2ecc71 !important; color: white; border-color: #27ae60; }
        .cm-dropzone.wrong-flash { background: #e74c3c !important; color: white; border-color: #c0392b; }

        /* Spot the Hazard Interface Styling */
        .hazard-card-box {
            border-left: 5px solid #e67e22; /* High contrast hazard alert accent line */
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
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="main-content">

        <div id="view-dashboard" class="app-view active">
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
                <div id="mg-feedback" class="mg-feedback info">Click Start to begin!</div>
                <div class="game-controls">
                    <button id="mg-start-btn">Start Game</button>
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