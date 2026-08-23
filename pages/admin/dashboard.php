<?php
session_start();
require_once __DIR__ . '/../../db/db_con.php';


if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || (int)$_SESSION['is_admin'] !== 1) {
    header('Location: pages/login.php');
    exit;
}

$db_connection = db_connect();
$feedback_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    
    if ($_POST['form_action'] === 'create_event') {
        $event_title = trim($_POST['event_title'] ?? '');
        $event_desc = trim($_POST['event_description'] ?? '');
        $event_date = trim($_POST['event_date'] ?? '');
        
        if ($event_title === '' || $event_date === '') {
            $feedback_message = "<div class='bg-red-100 border border-red-300 text-red-800 p-3 rounded mb-4 font-medium text-xs'>Error: Event title and date are required.</div>";
        } else {
            try {
                $insert_event_sql = "INSERT INTO events (admin_id, title, description, event_date, status) VALUES (?, ?, ?, ?, 'active')";
                $run_event_insert = $db_connection->prepare($insert_event_sql);
                $run_event_insert->execute([$_SESSION['user_id'], $event_title, $event_desc, $event_date]);
                $feedback_message = "<div class='bg-green-100 border border-green-300 text-green-800 p-3 rounded mb-4 font-medium text-xs'>Success: Event has been created and posted to all users!</div>";
            } catch (PDOException $ex) {
                $feedback_message = "<div class='bg-red-100 border border-red-300 text-red-800 p-3 rounded mb-4 font-medium text-xs'>Database Error: " . $ex->getMessage() . "</div>";
            }
        }
    }

    
    if ($_POST['form_action'] === 'save_generated_tree') {
        $ch_num = (int)$_POST['chapter_number'];
        $mod_title = trim($_POST['title']);
        $mod_desc = trim($_POST['description']);
        $json_tree_string = $_POST['module_json'];

        try {
            $certificate_template_path = null;
            if (isset($_FILES['certificate_template']) && $_FILES['certificate_template']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['certificate_template']['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('The certificate template could not be uploaded.');
                }

                $certificate_extension = strtolower(pathinfo($_FILES['certificate_template']['name'], PATHINFO_EXTENSION));
                $allowed_certificate_extensions = ['jpg', 'jpeg', 'png', 'webp'];
                if (!in_array($certificate_extension, $allowed_certificate_extensions, true)) {
                    throw new RuntimeException('Certificate templates must be JPG, PNG, or WEBP images.');
                }

                $certificate_upload_dir = __DIR__ . '/../../assets/imgs/Certificates/';
                if (!is_dir($certificate_upload_dir) && !mkdir($certificate_upload_dir, 0755, true)) {
                    throw new RuntimeException('The certificate upload directory could not be created.');
                }

                $certificate_filename = uniqid('certificate_', true) . '.' . $certificate_extension;
                $certificate_target = $certificate_upload_dir . $certificate_filename;
                if (!move_uploaded_file($_FILES['certificate_template']['tmp_name'], $certificate_target)) {
                    throw new RuntimeException('The certificate template could not be saved.');
                }
                $certificate_template_path = '../../assets/imgs/Certificates/' . $certificate_filename;
            }

            $insert_sql = "INSERT INTO learning_modules (chapter_number, title, description, module_data, certificate_template, created_by) VALUES (?, ?, ?, ?, ?, ?)";
            $run_insert = $db_connection->prepare($insert_sql);
            $run_insert->execute([$ch_num, $mod_title, $mod_desc, $json_tree_string, $certificate_template_path, $_SESSION['user_id']]);
            $feedback_message = "<div class='bg-green-100 border border-green-300 text-green-800 p-3 rounded mb-4 font-medium text-xs'>Success: Chapter " . $ch_num . " has been successfully published to the citizen portal!</div>";
        } catch (PDOException $ex) {
            $feedback_message = "<div class='bg-red-100 border border-red-300 text-red-800 p-3 rounded mb-4 font-medium text-xs'>Database Save Error: " . $ex->getMessage() . "</div>";
        } catch (RuntimeException $ex) {
            $feedback_message = "<div class='bg-red-100 border border-red-300 text-red-800 p-3 rounded mb-4 font-medium text-xs'>Certificate Upload Error: " . htmlspecialchars($ex->getMessage()) . "</div>";
        }
    }

    
    if ($_POST['form_action'] === 'delete_game' && isset($_POST['level_id'])) {
        $target_level_id = (int)$_POST['level_id'];
        try {
            
            $delete_sql = "DELETE FROM game_levels WHERE level_id = ?";
            $run_delete = $db_connection->prepare($delete_sql);
            $run_delete->execute([$target_level_id]);
            $feedback_message = "<div class='bg-green-100 border border-green-300 text-green-800 p-3 rounded mb-4 font-medium text-xs'>Success: Scenario map game instance record removed successfully!</div>";
        } catch (PDOException $ex) {
            $feedback_message = "<div class='bg-red-100 border border-red-300 text-red-800 p-3 rounded mb-4 font-medium text-xs'>Database Deletion Error: " . $ex->getMessage() . "</div>";
        }
    }

    
    if ($_POST['form_action'] === 'delete_module' && isset($_POST['module_id'])) {
        $target_module_id = (int)$_POST['module_id'];
        try {
            
            $cleanup_sql = "DELETE FROM progress WHERE module_id = ? AND game_name = 'learning_module'";
            $cleanup_stmt = $db_connection->prepare($cleanup_sql);
            $cleanup_stmt->execute([$target_module_id]);

            $certificate_cleanup_stmt = $db_connection->prepare("DELETE FROM certificates WHERE module_id = ?");
            $certificate_cleanup_stmt->execute([$target_module_id]);

            $delete_sql = "DELETE FROM learning_modules WHERE module_id = ?";
            $run_delete = $db_connection->prepare($delete_sql);
            $run_delete->execute([$target_module_id]);
            $feedback_message = "<div class='bg-green-100 border border-green-300 text-green-800 p-3 rounded mb-4 font-medium text-xs'>Success: Learning module has been permanently deleted.</div>";
        } catch (PDOException $ex) {
            $feedback_message = "<div class='bg-red-100 border border-red-300 text-red-800 p-3 rounded mb-4 font-medium text-xs'>Database Deletion Error: " . $ex->getMessage() . "</div>";
        }
    }
}

$selected_month = $_GET['filter_month'] ?? date('Y-m');

$online_players = 0;
$gender_male = 0;
$gender_female = 0;
$age_under_18 = 0;
$age_19_24 = 0;
$age_25_34 = 0;
$age_above_35 = 0;

$weekly_trend_data = [0, 0, 0, 0, 0];

try {
    $stmtOnline = $db_connection->prepare("
        SELECT COUNT(*) as online_count 
        FROM users 
        WHERE is_admin = 0 
          AND last_active >= NOW() - INTERVAL 5 MINUTE
    ");
    $stmtOnline->execute();
    $online_players = (int)($stmtOnline->fetch(PDO::FETCH_ASSOC)['online_count'] ?? 0);

    $stmtGender = $db_connection->prepare("SELECT gender, COUNT(*) as count FROM users WHERE is_admin = 0 GROUP BY gender");
    $stmtGender->execute();
    while ($row = $stmtGender->fetch(PDO::FETCH_ASSOC)) {
        $g = strtolower(trim($row['gender']));
        if ($g === 'male' || $g === 'm') $gender_male = (int)$row['count'];
        if ($g === 'female' || $g === 'f') $gender_female = (int)$row['count'];
    }

    $age_query = "
        SELECT 
            SUM(CASE WHEN AGE < 18 THEN 1 ELSE 0 END) as under_18,
            SUM(CASE WHEN AGE >= 19 AND AGE <= 24 THEN 1 ELSE 0 END) as age_19_24,
            SUM(CASE WHEN AGE >= 25 AND AGE <= 34 THEN 1 ELSE 0 END) as age_25_34,
            SUM(CASE WHEN AGE >= 35 THEN 1 ELSE 0 END) as age_35
        FROM (
            SELECT TIMESTAMPDIFF(YEAR, birthday, CURDATE()) as AGE 
            FROM users 
            WHERE is_admin = 0 AND birthday IS NOT NULL AND birthday <> '0000-00-00'
        ) as user_ages
    ";
    $stmtAge = $db_connection->prepare($age_query);
    $stmtAge->execute();
    $age_data = $stmtAge->fetch(PDO::FETCH_ASSOC);
    if ($age_data) {
        $age_under_18 = (int)($age_data['under_18'] ?? 0);
        $age_19_24    = (int)($age_data['age_19_24'] ?? 0);
        $age_25_34    = (int)($age_data['age_25_34'] ?? 0);
        $age_above_35 = (int)($age_data['age_35'] ?? 0);
    }

    $trend_query = "
        SELECT DAY(completion_date) as play_day, COUNT(*) as session_count 
        FROM progress 
        WHERE DATE_FORMAT(completion_date, '%Y-%m') = ?
        GROUP BY DAY(completion_date)
    ";
    $stmtTrend = $db_connection->prepare($trend_query);
    $stmtTrend->execute([$selected_month]);
    
    while ($row = $stmtTrend->fetch(PDO::FETCH_ASSOC)) {
        $day = (int)$row['play_day'];
        $count = (int)$row['session_count'];
        if ($day <= 7)  $weekly_trend_data[0] += $count;  
        elseif ($day <= 14) $weekly_trend_data[1] += $count;  
        elseif ($day <= 21) $weekly_trend_data[2] += $count; 
        elseif ($day <= 28) $weekly_trend_data[3] += $count;  
        else $weekly_trend_data[4] += $count;                
    }

} catch (PDOException $e) {
    $feedback_message = "<div class='bg-amber-100 text-amber-800 p-3 rounded text-xs mb-4'>Notice: Syncing live schema parameters.</div>";
}


$active_games_list = [];
$learning_modules_list = [];
try {
    $games_stmt = $db_connection->query("SELECT level_id, title, description, created_at FROM game_levels ORDER BY level_id DESC");
    $active_games_list = $games_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    
}

try {
    $module_stmt = $db_connection->query("SELECT module_id, chapter_number, title, description, certificate_template, created_at FROM learning_modules ORDER BY chapter_number ASC, module_id DESC");
    $learning_modules_list = $module_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    
}

$monthly_events = [];
$month_only_digits = substr($selected_month, 5, 2);
$lookup_key = date('Y') . '-' . $month_only_digits;
$active_event_notice = $monthly_events[$lookup_key] ?? "No special community safety events registered for this selected month.";
$active_admin_tab = $_GET['tab'] ?? 'view-analytics';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RoadRangers Administrative Hub</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-slate-100 text-slate-800 antialiased flex flex-col min-h-screen">

    <nav class="bg-slate-900 text-white px-6 py-4 flex justify-between items-center shadow-md z-10">
        <div class="flex items-center space-x-2">
            <span class="text-xl font-black text-amber-500 tracking-wide">ROADRANGERS</span>
            <span class="text-[10px] bg-slate-800 px-2 py-0.5 rounded text-slate-400 font-bold uppercase tracking-wider">TMU System Control</span>
        </div>
        <div class="flex items-center space-x-4 text-sm">
            <span>Admin Status: <strong class="text-amber-400"><?php echo htmlspecialchars($_SESSION['username'] ?? 'TMU_Admin'); ?></strong></span>
            <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white font-bold text-xs px-3 py-1.5 rounded transition">Logout</a>
        </div>
    </nav>

    <div class="flex flex-1">
        
        <aside class="w-64 bg-slate-800 text-slate-200 min-h-full flex flex-col justify-between border-r border-slate-700 shadow-inner">
            <div class="p-4 space-y-1">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-3 mb-3">Management Control View</p>
                
                <a href="?tab=view-analytics" id="btn-analytics" class="w-full flex items-center space-x-3 px-3 py-2.5 rounded text-xs transition duration-150 <?php echo $active_admin_tab === 'view-analytics' ? 'font-bold bg-slate-700 text-amber-400' : 'font-semibold text-slate-400 hover:bg-slate-700 hover:text-white'; ?>">
                    <span> Dashboard</span>
                </a>
                
                <a href="?tab=view-events" id="btn-events" class="w-full flex items-center space-x-3 px-3 py-2.5 rounded text-xs transition duration-150 <?php echo $active_admin_tab === 'view-events' ? 'font-bold bg-slate-700 text-amber-400' : 'font-semibold text-slate-400 hover:bg-slate-700 hover:text-white'; ?>">
                    <span> Event Planner</span>
                </a>
                
                <a href="?tab=view-modules" id="btn-modules" class="w-full flex items-center space-x-3 px-3 py-2.5 rounded text-xs transition duration-150 <?php echo $active_admin_tab === 'view-modules' ? 'font-bold bg-slate-700 text-amber-400' : 'font-semibold text-slate-400 hover:bg-slate-700 hover:text-white'; ?>">
                    <span> Module Creation Companion</span>
                </a>
                
                <a href="?tab=view-games" id="btn-games" class="w-full flex items-center space-x-3 px-3 py-2.5 rounded text-xs transition duration-150 <?php echo $active_admin_tab === 'view-games' ? 'font-bold bg-slate-700 text-amber-400' : 'font-semibold text-slate-400 hover:bg-slate-700 hover:text-white'; ?>">
                    <span> Game Creator</span>
                </a>
            </div>
            <div class="p-4 border-t border-slate-700 text-[11px] text-slate-500 font-medium">
                San Ildefonso TMU System Portal v2.6
            </div>
        </aside>

        <main class="flex-1 p-8">
            <?php echo $feedback_message; ?>

            <div id="view-analytics" class="view-panel space-y-6 <?php echo $active_admin_tab !== 'view-analytics' ? 'hidden' : ''; ?>">
                <div class="flex justify-between items-center border-b border-slate-200 pb-3">
                    <div>
                        <h2 class="text-lg font-black text-slate-900 tracking-tight">System Performance & Demographics Dashboard</h2>
                        <p class="text-xs text-slate-500 font-medium">Real-time engagement telemetry logs captured across active citizens portals.</p>
                    </div>
                    
                    <form method="GET" action="" class="flex items-center space-x-2 bg-white px-3 py-1.5 border border-slate-200 rounded-md shadow-sm">
                        <label for="filter_month" class="text-xs font-bold text-slate-500">Target Month:</label>
                        <input type="month" id="filter_month" name="filter_month" value="<?php echo $selected_month; ?>" onchange="this.form.submit()" class="text-xs font-semibold text-slate-800 bg-transparent focus:outline-none">
                    </form>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-gradient-to-br from-emerald-500 to-teal-600 text-white p-4 rounded-xl shadow-sm">
                        <p class="text-[10px] font-bold uppercase tracking-wider opacity-75">Online Users</p>
                        <h3 class="text-2xl font-black mt-1"><?php echo $online_players; ?> <span class="text-xs font-normal opacity-80">active now</span></h3>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex flex-col justify-between">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Male Citizens Total</p>
                        <h3 class="text-2xl font-black text-slate-800 mt-1"><?php echo $gender_male; ?> <span class="text-xs font-normal text-slate-400">users</span></h3>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex flex-col justify-between">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Female Citizens Total</p>
                        <h3 class="text-2xl font-black text-slate-800 mt-1"><?php echo $gender_female; ?> <span class="text-xs font-normal text-slate-400">users</span></h3>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex flex-col justify-between">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Event Planner</p>
                        <p class="text-[11px] font-bold text-amber-600 wrap mt-2">Create and manage upcoming events for users</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between">
                        <div>
                            <h4 class="text-xs font-black text-slate-800 uppercase tracking-wide mb-1">Monthly Active Engagement Play Rate Tracker</h4>
                            <p class="text-[11px] text-slate-400 font-semibold mb-4">Historical database session tracking counts for the current filtered month view.</p>
                        </div>
                        <div class="relative h-64">
                            <canvas id="monthlyActivityChart"></canvas>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between">
                        <div>
                            <h4 class="text-xs font-black text-slate-800 uppercase tracking-wide mb-1">Age Demographic Segmentation Mix</h4>
                            <p class="text-[11px] text-slate-400 font-semibold mb-4">Citizen age tracking properties parsed dynamically via registered birthdates.</p>
                        </div>
                        <div class="relative h-48 flex justify-center items-center">
                            <canvas id="ageDemographicsChart"></canvas>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-[10px] text-slate-500 font-bold mt-4 pt-3 border-t border-slate-100">
                            <div> Under 18: <span class="text-slate-800"><?php echo $age_under_18; ?></span></div>
                            <div> 19-24: <span class="text-slate-800"><?php echo $age_19_24; ?></span></div>
                            <div> 25-34: <span class="text-slate-800"><?php echo $age_25_34; ?></span></div>
                            <div> 35+: <span class="text-slate-800"><?php echo $age_above_35; ?></span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="view-events" class="view-panel space-y-6 <?php echo $active_admin_tab !== 'view-events' ? 'hidden' : ''; ?>">
                <div>
                    <h2 class="text-lg font-black text-slate-900 tracking-tight">Event Planner</h2>
                    <p class="text-xs text-slate-500 font-medium">Create and manage events that will appear on user dashboards.</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                        <h3 class="text-sm font-black text-slate-800 uppercase tracking-wide mb-4">Create New Event</h3>
                        <form method="POST" action="" class="space-y-4">
                            <input type="hidden" name="form_action" value="create_event">
                            
                            <div>
                                <label for="event_title" class="block text-xs font-bold text-slate-600 mb-1">Event Title</label>
                                <input type="text" id="event_title" name="event_title" placeholder="e.g., Traffic Safety Seminar" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500" required>
                            </div>
                            
                            <div>
                                <label for="event_description" class="block text-xs font-bold text-slate-600 mb-1">Description</label>
                                <textarea id="event_description" name="event_description" placeholder="Event details..." rows="3" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500"></textarea>
                            </div>
                            
                            <div>
                                <label for="event_date" class="block text-xs font-bold text-slate-600 mb-1">Date & Time</label>
                                <input type="datetime-local" id="event_date" name="event_date" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500" required>
                            </div>
                            
                            <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs px-4 py-2 rounded-md transition">Create Event</button>
                        </form>
                    </div>

                    <div class="lg:col-span-2 bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                        <h3 class="text-sm font-black text-slate-800 uppercase tracking-wide mb-4">Upcoming Events</h3>
                        <div class="space-y-3">
                            <?php 
                                try {
                                    $events_list_stmt = $db_connection->prepare("SELECT event_id, title, description, event_date, status FROM events WHERE status = 'active' ORDER BY event_date ASC LIMIT 20");
                                    $events_list_stmt->execute();
                                    $events_list = $events_list_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (!empty($events_list)):
                                        foreach ($events_list as $event):
                                            $event_date_formatted = date('M d, Y g:i A', strtotime($event['event_date']));
                                            $is_upcoming = strtotime($event['event_date']) > time();
                            ?>
                                            <div class="border border-slate-200 rounded-lg p-3 <?php echo $is_upcoming ? 'bg-amber-50' : 'bg-slate-50'; ?>">
                                                <div class="flex justify-between items-start">
                                                    <div class="flex-1">
                                                        <h4 class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($event['title']); ?></h4>
                                                        <p class="text-xs text-slate-600 mt-1"><?php echo htmlspecialchars($event['description']); ?></p>
                                                        <p class="text-[11px] text-slate-500 font-semibold mt-2"><?php echo $event_date_formatted; ?></p>
                                                    </div>
                                                    <span class="text-[10px] font-bold px-2 py-1 rounded <?php echo $is_upcoming ? 'bg-amber-200 text-amber-800' : 'bg-slate-200 text-slate-800'; ?>"><?php echo $is_upcoming ? 'Upcoming' : 'Past'; ?></span>
                                                </div>
                                            </div>
                            <?php 
                                        endforeach;
                                    else:
                            ?>
                                        <p class="text-xs text-slate-500 italic text-center py-6">No events created yet. Create one to get started!</p>
                            <?php 
                                    endif;
                                } catch (PDOException $e) {
                                    echo "<p class='text-xs text-red-600'>Error loading events: " . htmlspecialchars($e->getMessage()) . "</p>";
                                }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <div id="view-modules" class="view-panel space-y-6 <?php echo $active_admin_tab !== 'view-modules' ? 'hidden' : ''; ?>">
                <div>
                    <h2 class="text-lg font-black text-slate-900 tracking-tight">Interactive Dialogue Tree Configuration Workspace</h2>
                    <p class="text-xs text-slate-500 font-medium">Upload LTO rulesets to build smart chat simulations for the user learning terminal.</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2 space-y-6">
                        <div class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
                            <h3 class="text-md font-bold text-slate-900 mb-1">Step 1: Input LTO Reference Material</h3>
                            <p class="text-xs text-slate-500 mb-4">Upload an official driving guide handbook (**PDF** or **TXT**) to process rules natively into nodes.</p>
                            
                            <div id="drop-target-area" class="border-2 border-dashed border-slate-300 rounded-lg p-6 text-center cursor-pointer hover:bg-slate-50 transition mb-4">
                                <p id="drop-zone-text" class="text-xs font-semibold text-slate-600">Drag & Drop LTO PDF or TXT File Here or Click to Browse System Folders</p>
                                <input type="file" id="txt-file-file-input" accept=".txt,.pdf" class="hidden">
                            </div>
                            <textarea id="lto-raw-text-field" rows="6" class="w-full text-xs border border-slate-200 rounded p-3 focus:outline-none focus:ring-1 focus:ring-slate-900 resize-none" placeholder="Paste driving regulations context manually here..."></textarea>
                                <div class="grid grid-cols-2 gap-3">
                                    <button type="button" id="trigger-ai-parse-btn" class="w-full mt-3 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs uppercase py-3 rounded tracking-wider transition shadow-sm">⚡ Generate Dynamic Chatbot Game Tree Structure via Gemini AI</button>
                                    <button type="button" id="translate-tl-btn" class="w-full mt-3 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs uppercase py-3 rounded tracking-wider transition shadow-sm" disabled>🌏 Translate to Tagalog</button>
                                </div>
                                <p class="text-[11px] text-slate-500 mt-3">After generating the English structure, click Translate to Tagalog to generate a second language version for this module.</p>
                                <div class="mt-4">
                                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-tight">Generated Module JSON</label>
                                    <textarea id="live-tree-code-editor" rows="12" class="w-full mt-2 text-xs border border-slate-200 rounded p-3 focus:outline-none font-mono bg-slate-950 text-slate-100" placeholder="Generated module JSON appears here after Gemini AI processing..." readonly></textarea>
                                </div>
                            </div>
                    <div class="space-y-6">
                        <div class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
                            <h3 class="text-md font-bold text-slate-900 mb-1">Step 3: Interactive Chatbot Emulator</h3>
                            <div class="w-full max-w-[290px] h-[460px] bg-slate-200 border-[6px] border-slate-950 rounded-[2rem] mx-auto overflow-hidden relative shadow-md flex flex-col justify-between">
                                <div class="bg-slate-950 text-amber-400 text-[9px] font-black py-1 text-center tracking-widest uppercase">RoadRangers Preview Node</div>
                                <div id="emulator-chat-viewport" class="p-4 space-y-3 overflow-y-auto flex-1 text-[11px]">
                                    <div class="text-slate-400 text-center italic mt-16">Awaiting pipeline compilation execution dataset blocks...</div>
                                </div>
                                <div id="emulator-choices-action-tray" class="p-3 bg-white border-t border-slate-200 space-y-2"></div>
                            </div>
                        </div>

                        <form action="" method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded-lg shadow-sm border border-slate-200 space-y-4">
                            <input type="hidden" name="form_action" value="save_generated_tree">
                            <input type="hidden" id="hidden-submission-json-field" name="module_json">
                            <h3 class="text-sm font-black text-slate-900 uppercase border-b border-slate-100 pb-2">Step 4: Publication Panel</h3>
                            <div class="grid grid-cols-4 gap-2">
                                <div class="col-span-1">
                                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-tight">Chap. #</label>
                                    <input type="number" name="chapter_number" class="w-full border border-slate-200 rounded p-1.5 text-xs text-center focus:outline-none" required min="1">
                                </div>
                                <div class="col-span-3">
                                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-tight">Public Chapter Topic Title</label>
                                    <input type="text" name="title" class="w-full border border-slate-200 rounded p-1.5 text-xs focus:outline-none" required placeholder="Ex: Intersection Right-of-Way">
                                </div>
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-tight">Short Module Description</label>
                                <input type="text" name="description" class="w-full border border-slate-200 rounded p-1.5 text-xs focus:outline-none" required placeholder="Ex: Learning road signs guidelines...">
                            </div>
                            <div>
                                <label for="certificate_template" class="text-[10px] font-bold text-slate-500 uppercase tracking-tight">Certificate Template (optional)</label>
                                <input type="file" id="certificate_template" name="certificate_template" accept=".jpg,.jpeg,.png,.webp" class="w-full border border-slate-200 rounded p-1.5 text-xs focus:outline-none">
                                <p class="text-[10px] text-slate-400 mt-1">Upload a certificate background image. The learner name and completion date will be placed on it.</p>
                            </div>
                            <button type="submit" id="commit-production-publish-btn" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs uppercase py-3 rounded tracking-wider transition shadow-md" disabled>Publish Module to Live System</button>
                        </form>

                        <div class="bg-white p-6 rounded-lg shadow-sm border border-slate-200 mt-6">
                            <h3 class="text-sm font-black text-slate-900 uppercase border-b border-slate-100 pb-2">Published Modules</h3>
                            <div class="overflow-hidden rounded-lg border border-slate-200 mt-4">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead>
                                        <tr class="bg-slate-50 text-slate-500 uppercase text-[10px] tracking-wide">
                                            <th class="p-3">Chapter</th>
                                            <th class="p-3">Title</th>
                                            <th class="p-3">Description</th>
                                            <th class="p-3">Published</th>
                                            <th class="p-3 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 font-medium">
                                        <?php if (!empty($learning_modules_list)): ?>
                                            <?php foreach ($learning_modules_list as $module_row): ?>
                                                <tr class="hover:bg-slate-50/60 transition">
                                                    <td class="p-3 font-bold text-slate-800"><?php echo intval($module_row['chapter_number']); ?></td>
                                                    <td class="p-3 text-slate-600"><?php echo htmlspecialchars($module_row['title']); ?></td>
                                                    <td class="p-3 text-slate-500 max-w-xs truncate"><?php echo htmlspecialchars($module_row['description']); ?></td>
                                                    <td class="p-3 text-slate-400 text-[10px]"><?php echo date('M d, Y', strtotime($module_row['created_at'])); ?></td>
                                                    <td class="p-3 text-right">
                                                        <form method="POST" action="" onsubmit="return confirm('Are you sure you want to permanently delete this learning module?');" style="display:inline-block;">
                                                            <input type="hidden" name="form_action" value="delete_module">
                                                            <input type="hidden" name="module_id" value="<?php echo intval($module_row['module_id']); ?>">
                                                            <button type="submit" class="text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 font-bold px-2.5 py-1 rounded text-[10px] uppercase tracking-wide transition">Delete</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="p-4 text-center text-slate-400 italic">No learning modules have been published yet.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

            <div id="view-games" class="view-panel space-y-6 <?php echo $active_admin_tab !== 'view-games' ? 'hidden' : ''; ?>">
                <div class="flex justify-between items-center border-b border-slate-200 pb-3">
                    <div>
                        <h2 class="text-lg font-black text-slate-900 tracking-tight">Scenario Hotspot Game Creator</h2>
                        <p class="text-xs text-slate-500 font-medium">Link out to access your specialized visual target hot-zone bounding setup environment.</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                    <div class="bg-white p-8 rounded-xl border border-slate-200 text-center shadow-sm lg:col-span-1">
                        <p class="text-sm text-slate-600 mb-4 font-medium">Launch the standalone canvas map generator environment workspace to drop real-life images and configure violation zones coordinates mapping parameters securely.</p>
                        <a href="game_creation.php" class="inline-block w-full bg-slate-900 hover:bg-slate-800 text-amber-400 font-bold text-xs uppercase tracking-wider px-6 py-3 rounded-md transition shadow-md text-center">
                            🛠️ Launch Game Creator Canvas Workspace &rarr;
                        </a>
                    </div>
                    
                    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm lg:col-span-2 space-y-4">
                        <div>
                            <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider">Active Published Canvas Games</h3>
                            <p class="text-[11px] text-slate-400 font-semibold">Manage system entries generated within the bounding editor environment.</p>
                        </div>
                        
                        <div class="overflow-hidden border border-slate-100 rounded-lg max-h-96 overflow-y-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50 text-slate-400 uppercase text-[9px] font-black border-b border-slate-100">
                                        <th class="p-3">Game Title</th>
                                        <th class="p-3">Description</th>
                                        <th class="p-3">Created</th>
                                        <th class="p-3 text-center">Control Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 font-medium">
                                    <?php if (!empty($active_games_list)): ?>
                                        <?php foreach ($active_games_list as $game_row): ?>
                                            <tr class="hover:bg-slate-50/50 transition">
                                                <td class="p-3 font-bold text-slate-800"><?php echo htmlspecialchars($game_row['title']); ?></td>
                                                <td class="p-3 text-slate-500 max-w-xs truncate"><?php echo htmlspecialchars($game_row['description']); ?></td>
                                                <td class="p-3 text-slate-400 text-[10px]"><?php echo date('M d, Y', strtotime($game_row['created_at'])); ?></td>
                                                <td class="p-3 text-center">
                                                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to permanently remove this canvas violation game module instance?');">
                                                        <input type="hidden" name="form_action" value="delete_game">
                                                        <input type="hidden" name="level_id" value="<?php echo $game_row['level_id']; ?>">
                                                        <button type="submit" class="text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 font-bold px-2.5 py-1 rounded text-[10px] uppercase tracking-wide transition">
                                                            Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="p-4 text-center text-slate-400 italic">No games configured inside system database schema fields currently.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <script>
        function switchView(targetPanelId) {
            document.querySelectorAll('.view-panel').forEach(panel => panel.classList.add('hidden'));
            let targetPanel = document.getElementById(targetPanelId);
            if (!targetPanel) {
                console.warn('switchView: panel not found:', targetPanelId, 'falling back to view-analytics');
                targetPanelId = 'view-analytics';
                targetPanel = document.getElementById(targetPanelId);
            }
            if (targetPanel) targetPanel.classList.remove('hidden');

            const tabs = {
                'view-analytics': 'btn-analytics',
                'view-modules': 'btn-modules',
                'view-games': 'btn-games'
            };

            Object.keys(tabs).forEach(viewId => {
                const element = document.getElementById(tabs[viewId]);
                if (viewId === targetPanelId) {
                    element.className = "w-full flex items-center space-x-3 px-3 py-2.5 rounded text-xs font-bold transition duration-150 bg-slate-700 text-amber-400";
                } else {
                    element.className = "w-full flex items-center space-x-3 px-3 py-2.5 rounded text-xs font-semibold text-slate-400 hover:bg-slate-700 hover:text-white transition duration-150";
                }
            });
            
            const url = new URL(window.location);
            url.searchParams.set('tab', targetPanelId);
            window.history.replaceState({}, '', url);
        }

        document.addEventListener("DOMContentLoaded", () => {
            const activeTabUrlParam = "<?php echo $_GET['tab'] ?? 'view-analytics'; ?>";
            switchView(activeTabUrlParam);

            if (typeof Chart !== 'undefined') {
                const monthlyChartEl = document.getElementById('monthlyActivityChart');
                if (monthlyChartEl) {
                    const trendCtx = monthlyChartEl.getContext('2d');
                    new Chart(trendCtx, {
                        type: 'line',
                        data: {
                            labels: ['Week 1 (1-7)', 'Week 2 (8-14)', 'Week 3 (15-21)', 'Week 4 (22-28)', 'End Span (29+)'],
                            datasets: [{
                                label: 'Total Completed Gameplay Sessions',
                                data: <?php echo json_encode($weekly_trend_data); ?>,
                                borderColor: '#f59e0b',
                                backgroundColor: 'rgba(245, 158, 11, 0.08)',
                                borderWidth: 3,
                                tension: 0.25,
                                fill: true,
                                pointBackgroundColor: '#1e293b',
                                pointRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 }, stepSize: 5 } },
                                x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                            }
                        }
                    });
                }

                const ageCtx = document.getElementById('ageDemographicsChart');
                if (ageCtx) {
                    new Chart(ageCtx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Under 18', '19-24', '25-34', '35+'],
                            datasets: [{
                                data: [<?php echo "$age_under_18, $age_19_24, $age_25_34, $age_above_35"; ?>],
                                backgroundColor: ['#f87171', '#3b82f6', '#34d399', '#a78bfa'],
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } }
                        }
                    });
                }
            } else {
                console.warn('Chart.js is not available; analytics charts will not render.');
            }
        });

        if (typeof pdfjsLib !== 'undefined' && pdfjsLib.GlobalWorkerOptions) {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
        } else {
            console.warn('pdfjsLib is not available; PDF upload preview will not work until the library loads.');
        }
        let interactiveModuleDataTree = null;

        const dropTarget = document.getElementById('drop-target-area');
        const dropZoneText = document.getElementById('drop-zone-text');
        const manualFileInputField = document.getElementById('txt-file-file-input');
        const targetTextContentField = document.getElementById('lto-raw-text-field');

        if (dropTarget && dropZoneText && manualFileInputField && targetTextContentField) {
            dropTarget.addEventListener('click', () => manualFileInputField.click());
            manualFileInputField.addEventListener('change', (e) => parseSelectedFileObject(e.target.files[0]));
            
            dropTarget.addEventListener('dragover', (e) => { e.preventDefault(); dropTarget.classList.add('bg-slate-50'); });
            dropTarget.addEventListener('dragleave', () => dropTarget.classList.remove('bg-slate-50'));
            dropTarget.addEventListener('drop', (e) => {
                e.preventDefault();
                dropTarget.classList.remove('bg-slate-50');
                parseSelectedFileObject(e.dataTransfer.files[0]);
            });
        }

        function parseSelectedFileObject(fileObj) {
            if (!fileObj) return;
            pendingSourceImageData = null;
            pendingSourceImageMime = 'image/png';

            if (fileObj.type.startsWith('image/')) {
                dropZoneText.innerText = "Reading image file: " + fileObj.name;
                const docReader = new FileReader();
                docReader.onload = function(evt) {
                    pendingSourceImageData = evt.target.result;
                    pendingSourceImageMime = fileObj.type || 'image/png';
                    targetTextContentField.value = "Image reference attached: " + fileObj.name + "\n\nUse the visual as a scenario reference during module generation.";
                    dropZoneText.innerText = "Successfully loaded image: " + fileObj.name;
                };
                docReader.readAsDataURL(fileObj);
            }
            else if (fileObj.type === "text/plain" || fileObj.name.endsWith('.txt')) {
                dropZoneText.innerText = "Reading TXT file: " + fileObj.name;
                const docReader = new FileReader();
                docReader.onload = function(evt) { targetTextContentField.value = evt.target.result; };
                docReader.readAsText(fileObj);
            } 
            else if (fileObj.type === "application/pdf" || fileObj.name.endsWith('.pdf')) {
                dropZoneText.innerText = "Extracting text from PDF: " + fileObj.name + "...";
                const pdfReader = new FileReader();
                pdfReader.onload = async function() {
                    try {
                        const typedarray = new Uint8Array(this.result);
                        const pdfDoc = await pdfjsLib.getDocument(typedarray).promise;
                        let fullExtractedText = "";

                        try {
                            const firstPage = await pdfDoc.getPage(1);
                            const viewport = firstPage.getViewport({ scale: 1.25 });
                            const pageCanvas = document.createElement('canvas');
                            const pageContext = pageCanvas.getContext('2d');
                            pageCanvas.width = viewport.width;
                            pageCanvas.height = viewport.height;
                            await firstPage.render({ canvasContext: pageContext, viewport }).promise;
                            pendingSourceImageData = pageCanvas.toDataURL('image/png');
                            pendingSourceImageMime = 'image/png';
                        } catch (pageRenderError) {
                            console.warn('PDF preview extraction failed:', pageRenderError);
                        }

                        for (let pageNum = 1; pageNum <= pdfDoc.numPages; pageNum++) {
                            const activePage = await pdfDoc.getPage(pageNum);
                            const contentBlocks = await activePage.getTextContent();
                            const pageTextString = contentBlocks.items.map(item => item.str).join(" ");
                            fullExtractedText += pageTextString + "\n";
                        }
                        targetTextContentField.value = fullExtractedText.trim();
                        dropZoneText.innerText = "Successfully loaded PDF: " + fileObj.name;
                    } catch (pdfError) {
                        alert("PDF Processing Error: Unable to extract text data from this file document.");
                        dropZoneText.innerText = "Drag & Drop LTO PDF or TXT File Here";
                    }
                };
                pdfReader.readAsArrayBuffer(fileObj);
            } else {
                alert("File Format Exception: Supports .txt, .pdf, .png, .jpg, or .jpeg formats.");
            }
        }

        let pendingSourceImageData = null;
        let pendingSourceImageMime = 'image/png';

        const parseButton = document.getElementById('trigger-ai-parse-btn');
        const liveTreeEditor = document.getElementById('live-tree-code-editor');
        const hiddenJsonField = document.getElementById('hidden-submission-json-field');
        const commitPublishBtn = document.getElementById('commit-production-publish-btn');
        const translateButton = document.getElementById('translate-tl-btn');

        function getSafeElement(id) {
            const el = document.getElementById(id);
            if (!el) {
                console.error('Missing expected element:', id);
            }
            return el;
        }

        function setElementValue(id, value) {
            const el = getSafeElement(id);
            if (el) el.value = value;
        }

        function setElementDisabled(id, disabled) {
            const el = getSafeElement(id);
            if (el) el.disabled = disabled;
        }

        if (parseButton) {
            parseButton.addEventListener('click', async () => {
                const targetTextInputData = targetTextContentField.value.trim();
                if(!targetTextInputData) return alert("System Prompt Block: Please insert source documentation parameters first.");
                
                parseButton.innerText = "PROCESSING PAYLOAD GENERATION CHANNELS...";
                parseButton.disabled = true;

                const packagePayload = new FormData();
                packagePayload.append('content', targetTextInputData);
                if (pendingSourceImageData) {
                    packagePayload.append('image_data', pendingSourceImageData);
                    packagePayload.append('image_mime_type', pendingSourceImageMime || 'image/png');
                }

                try {
                    const responseStream = await fetch('parse_module.php', { method: 'POST', body: packagePayload });
                    const rawResponseText = await responseStream.text();
                    let serverJsonOutput;
                    try {
                        serverJsonOutput = JSON.parse(rawResponseText);
                    } catch (parseError) {
                        const responsePreview = rawResponseText.replace(/\s+/g, ' ').trim().slice(0, 500);
                        console.error('parse_module response:', responseStream.status, rawResponseText);
                        alert('AI Synthesis Fault Trace: The server returned invalid JSON (HTTP ' + responseStream.status + ').\n\nResponse: ' + responsePreview);
                        return;
                    }

                    if (serverJsonOutput.error) {
                        const message = serverJsonOutput.error + (serverJsonOutput.raw_output ? '\n\nPreview: ' + serverJsonOutput.raw_output : '');
                        alert("AI Synthesis Fault Trace: " + message);
                    } else {
                        interactiveModuleDataTree = serverJsonOutput;
                        if (liveTreeEditor) {
                            liveTreeEditor.value = JSON.stringify(serverJsonOutput, null, 2);
                            liveTreeEditor.removeAttribute('readonly');
                        }
                        if (hiddenJsonField) hiddenJsonField.value = JSON.stringify(serverJsonOutput);
                        if (commitPublishBtn) commitPublishBtn.removeAttribute('disabled');
                        renderEmulatorActiveNode("start");
                        if (translateButton) translateButton.removeAttribute('disabled');
                    }
                } catch (err) {
                    console.error('JSON parse or network error:', err);
                    alert("Critical System Integration Interrupt: " + err.message);
                } finally {
                    parseButton.innerText = "Generate Dynamic Chatbot Game Tree Structure via Gemini AI";
                    parseButton.disabled = false;
                }
            });
        }

        if (liveTreeEditor) {
            liveTreeEditor.addEventListener('input', (event) => {
                try {
                    const realTimeValidatedTree = JSON.parse(event.target.value);
                    interactiveModuleDataTree = realTimeValidatedTree;
                    if (hiddenJsonField) hiddenJsonField.value = JSON.stringify(realTimeValidatedTree);
                    renderEmulatorActiveNode("start");
                } catch(exception) {}
            });
        }

        if (translateButton) {
            translateButton.addEventListener('click', async () => {
                if (!liveTreeEditor) {
                    return alert('The module editor is not available. Refresh the page and try again.');
                }
                const editorValue = liveTreeEditor.value.trim();
                if (!editorValue) {
                    return alert('Please generate or paste the module JSON before translating to Tagalog.');
                }

                let parsedTree;
                try {
                    parsedTree = JSON.parse(editorValue);
                } catch (jsonErr) {
                    return alert('Your module JSON is not valid. Fix the JSON before translating to Tagalog.');
                }

                translateButton.textContent = 'Translating to Tagalog...';
                translateButton.disabled = true;

                const translatePayload = new FormData();
                translatePayload.append('action', 'translate_to_tagalog');
                translatePayload.append('content', JSON.stringify(parsedTree));

                try {
                    const translateResponse = await fetch('parse_module.php', { method: 'POST', body: translatePayload });
                    const translateOutput = await translateResponse.json();

                    if (translateOutput.error) {
                        const message = translateOutput.error + (translateOutput.raw_output ? '\n\nPreview: ' + translateOutput.raw_output : '');
                        alert('AI Translation Fault Trace: ' + message);
                        return;
                    }

                    if (translateOutput.translated_module) {
                        liveTreeEditor.value = JSON.stringify(translateOutput.translated_module, null, 2);
                        if (hiddenJsonField) hiddenJsonField.value = JSON.stringify(translateOutput.translated_module);
                        if (commitPublishBtn) commitPublishBtn.removeAttribute('disabled');
                        translateButton.textContent = 'Translate to Tagalog';
                        translateButton.disabled = false;
                        const enTree = translateOutput.translated_module.en || translateOutput.translated_module;
                        interactiveModuleDataTree = enTree;
                        renderEmulatorActiveNode('start');
                        alert('Tagalog translation generated successfully. English preview is shown in the emulator.');
                    } else {
                        alert('Translation completed but no translated module was returned.');
                    }
                } catch (translateErr) {
                    console.error('Translation error:', translateErr);
                    alert('Translation request failed: ' + translateErr.message);
                } finally {
                    translateButton.textContent = 'Translate to Tagalog';
                    translateButton.disabled = false;
                }
            });
        }

        function renderEmulatorActiveNode(targetNodeIdentifier) {
            const mainViewportTray = document.getElementById('emulator-chat-viewport');
            const choicesControlsContainer = document.getElementById('emulator-choices-action-tray');
            mainViewportTray.innerHTML = "";
            choicesControlsContainer.innerHTML = "";

            if (!interactiveModuleDataTree || !interactiveModuleDataTree.nodes || !interactiveModuleDataTree.nodes[targetNodeIdentifier]) {
                mainViewportTray.innerHTML = "<div class='text-red-600 font-bold p-2 text-center'>Missing Node Link ID Mapping Exception</div>";
                return;
            }

            const currentActiveNodeData = interactiveModuleDataTree.nodes[targetNodeIdentifier];
            const messageBubbleElement = document.createElement('div');
            messageBubbleElement.className = "bg-slate-800 text-slate-100 p-2.5 rounded-lg rounded-tl-none self-start max-w-[90%] shadow-sm leading-snug font-medium";
            messageBubbleElement.innerText = currentActiveNodeData.bot_message;
            mainViewportTray.appendChild(messageBubbleElement);

            if (currentActiveNodeData.image) {
                const imageElement = document.createElement('img');
                imageElement.src = currentActiveNodeData.image;
                imageElement.alt = 'Module visual';
                imageElement.style.maxWidth = '100%';
                imageElement.style.borderRadius = '12px';
                imageElement.style.marginTop = '8px';
                imageElement.style.border = '1px solid rgba(148, 163, 184, 0.35)';
                mainViewportTray.appendChild(imageElement);
            }

            if(currentActiveNodeData.choices && currentActiveNodeData.choices.length > 0) {
                currentActiveNodeData.choices.forEach(optionObject => {
                    const optionActionButton = document.createElement('button');
                    optionActionButton.type = "button";
                    optionActionButton.className = "w-full text-left bg-slate-100 hover:bg-slate-200 text-slate-800 p-2 rounded text-xs transition border border-slate-200 mb-1 font-medium";
                    optionActionButton.innerText = optionObject.text;
                    optionActionButton.addEventListener('click', () => {
                        renderEmulatorActiveNode(optionObject.next_node);
                    });
                    choicesControlsContainer.appendChild(optionActionButton);
                });
            }
        }
    </script>
</body>
</html>