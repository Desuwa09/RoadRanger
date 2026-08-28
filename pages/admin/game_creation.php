<?php
session_start();
require_once __DIR__ . '/../../db/db_con.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || (int)$_SESSION['is_admin'] !== 1) {
    header('Location: login.php');
    exit;
}

$conn = db_connect();
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_game') {
    $conn->beginTransaction();
    try {
        $scenario_name = trim($_POST['scenario_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $selected_game_key = trim($_POST['game_key'] ?? 'hotspot_test');
        $difficulty = strtolower(trim($_POST['difficulty'] ?? 'easy'));
        $difficulty = in_array($difficulty, ['easy', 'medium', 'hard'], true) ? $difficulty : 'easy';
        $time_limit_seconds = max(0, (int)($_POST['time_limit_seconds'] ?? 0));
        $hotspots_json = $_POST['hotspots_data'] ?? '[]';

        if (empty($scenario_name)) {
            throw new Exception("Please fill out the level title.");
        }

        $game_stmt = $conn->prepare("SELECT game_id, game_name FROM games WHERE game_key = ? LIMIT 1");
        $game_stmt->execute([$selected_game_key]);
        $game_data = $game_stmt->fetch(PDO::FETCH_ASSOC);
        $game_id = $game_data['game_id'] ?? null;

        if (!$game_id) {
            throw new Exception("Selected game type was not initialized in the master games table.");
        }

        $relative_db_path = null;
        if ($selected_game_key === 'hotspot_test') {
            if (!isset($_FILES['scenario_image']) || $_FILES['scenario_image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Please upload a valid image for the hotspot scenario.");
            }

            $file_tmp = $_FILES['scenario_image']['tmp_name'];
            $file_name = basename($_FILES['scenario_image']['name']);
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($file_ext, $allowed_exts)) {
                throw new Exception("Invalid image extension.");
            }

            $upload_dir = __DIR__ . '/../../assets/imgs/Scenarios/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $unique_filename = uniqid('scenario_', true) . '.' . $file_ext;
            $target_path = $upload_dir . $unique_filename;

            if (!move_uploaded_file($file_tmp, $target_path)) {
                throw new Exception("Failed to save image.");
            }

            $relative_db_path = "../../assets/imgs/Scenarios/" . $unique_filename;
        } elseif (isset($_FILES['scenario_image']) && $_FILES['scenario_image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['scenario_image']['tmp_name'];
            $file_name = basename($_FILES['scenario_image']['name']);
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($file_ext, $allowed_exts)) {
                throw new Exception("Invalid image extension.");
            }

            $upload_dir = __DIR__ . '/../../assets/imgs/Scenarios/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $unique_filename = uniqid('scenario_', true) . '.' . $file_ext;
            $target_path = $upload_dir . $unique_filename;

            if (!move_uploaded_file($file_tmp, $target_path)) {
                throw new Exception("Failed to save image.");
            }

            $relative_db_path = "../../assets/imgs/Scenarios/" . $unique_filename;
        }

        $level_stmt = $conn->prepare("INSERT INTO game_levels (game_id, title, description, difficulty, background_image, time_limit_seconds) VALUES (?, ?, ?, ?, ?, ?)");
        $level_stmt->execute([$game_id, $scenario_name, $description, $difficulty, $relative_db_path, $time_limit_seconds]);
        $new_level_id = $conn->lastInsertId();

        if ($selected_game_key === 'conveyor_game') {
            $sign_images = $_FILES['conveyor_sign_images'] ?? null;
            $sign_meanings = $_POST['conveyor_sign_meanings'] ?? [];
            $sign_categories = $_POST['conveyor_sign_categories'] ?? [];
            $sign_count = is_array($sign_images['name'] ?? null) ? count($sign_images['name']) : 0;

            if ($sign_count === 0) {
                throw new Exception("Please add at least one sign image for Conveyor Mania.");
            }

            $sign_stmt = $conn->prepare("INSERT INTO game_items (level_id, item_label, item_image, target_category) VALUES (?, ?, ?, ?)");
            $allowed_sign_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $sign_upload_dir = __DIR__ . '/../../assets/imgs/Signs/';
            if (!is_dir($sign_upload_dir) && !mkdir($sign_upload_dir, 0755, true)) {
                throw new Exception("The sign image directory could not be created.");
            }

            $saved_sign_count = 0;
            for ($sign_index = 0; $sign_index < $sign_count; $sign_index++) {
                if (($sign_images['error'][$sign_index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new Exception("Every Conveyor Mania sign row must include a valid image.");
                }

                $sign_extension = strtolower(pathinfo($sign_images['name'][$sign_index], PATHINFO_EXTENSION));
                if (!in_array($sign_extension, $allowed_sign_extensions, true)) {
                    throw new Exception("Sign images must be JPG, PNG, WEBP, or GIF files.");
                }

                $sign_filename = uniqid('sign_', true) . '.' . $sign_extension;
                if (!move_uploaded_file($sign_images['tmp_name'][$sign_index], $sign_upload_dir . $sign_filename)) {
                    throw new Exception("A sign image could not be saved.");
                }

                $sign_meaning = trim($sign_meanings[$sign_index] ?? '');
                $sign_category = trim($sign_categories[$sign_index] ?? '');
                if ($sign_meaning === '' || !in_array($sign_category, ['regulatory', 'warning', 'informative'], true)) {
                    throw new Exception("Every sign needs a meaning and a valid category.");
                }

                $sign_stmt->execute([$new_level_id, $sign_meaning, '../../assets/imgs/Signs/' . $sign_filename, $sign_category]);
                $saved_sign_count++;
            }
        } elseif ($selected_game_key === 'hotspot_test') {
            $items_array = json_decode($hotspots_json, true);
            if (!is_array($items_array) || count($items_array) === 0) {
                throw new Exception("Please draw at least one target.");
            }

            $item_stmt = $conn->prepare("INSERT INTO game_items (level_id, item_label, shape_type, pos_x, pos_y, width, height) VALUES (?, ?, ?, ?, ?, ?, ?)");

            foreach ($items_array as $item) {
                $item_stmt->execute([
                    $new_level_id,
                    trim($item['label'] ?? 'Violation'),
                    $item['shape'] ?? 'rect',
                    $item['x'],
                    $item['y'],
                    $item['w'],
                    $item['h']
                ]);
            }
        }

        $conn->commit();
        $message = "Successfully created new level for " . htmlspecialchars($game_data['game_name'] ?? $selected_game_key) . "!";
        $message_type = "success";
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Create Scenario</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f6f9; padding: 20px; color: #333; }
        .container { max-width: 1100px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .form-group { margin-bottom: 15px; }
        label { font-weight: 600; display: block; margin-bottom: 5px; }
        input, textarea, select { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 4px; box-sizing: border-box; }
        .subtle-note { font-size: 12px; color: #64748b; margin-top: 6px; }
        .workspace-grid { display: grid; grid-template-columns: 1fr 320px; gap: 25px; margin-top: 20px; }
        .simple-builder { display: none; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 18px; margin-top: 15px; }
        .conveyor-sign-row { display: grid; grid-template-columns: 1.2fr 1.2fr 1fr auto; gap: 8px; align-items: center; margin: 10px 0; }
        .remove-sign-row { padding: 10px; border: 0; border-radius: 4px; background: #fee2e2; color: #991b1b; cursor: pointer; }
        @media (max-width: 760px) { .conveyor-sign-row { grid-template-columns: 1fr; } }
        .canvas-wrapper { position: relative; background: #e2e8f0; border-radius: 6px; overflow: hidden; display: flex; align-items: flex-start; justify-content: center; min-height: 400px; }
        #canvas-container { 
            position: relative; 
            display: inline-block; 
            cursor: crosshair; 
            touch-action: none; 
            user-select: none; 
        }
        #scenery-img { display: block; max-width: 100%; height: auto; pointer-events: none; }
        #snip-overlay { position: absolute; top: 0; left: 0; pointer-events: none; z-index: 5; display: none; }
        .hotspot-box { position: absolute; z-index: 2; border: 3px solid #ef4444; background: rgba(239, 68, 68, 0.3); pointer-events: none; }
        .toolbar { background: #1e293b; padding: 10px; border-radius: 6px; margin-bottom: 10px; display: flex; gap: 10px; }
        .tool-btn { padding: 8px 14px; cursor: pointer; background: #334155; color: white; border: none; border-radius: 4px; }
        .tool-btn.active { background: #3b82f6; }
        .custom-popup-modal { position: absolute; background: #1e293b; color: white; padding: 12px; border-radius: 6px; z-index: 100; display: none; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" style="text-decoration:none; color:#3b82f6; font-weight:bold;">&larr; Back to Admin Dashboard</a> 
    <h1>Game Creator Workspace</h1>
    <?php if (!empty($message)): ?>
        <div class="alert <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <form action="game_creation.php" method="POST" enctype="multipart/form-data" id="main-creation-form">
        <input type="hidden" name="action" value="create_game">
        <input type="hidden" name="hotspots_data" id="hotspots_data" value="[]">

        <div class="form-group">
            <label>Game Type:</label>
            <select name="game_key" id="game_type_select" required>
                <option value="hotspot_test">Hotspot / Scenario Game</option>
                <option value="memory_game">Memory Game</option>
                <option value="conveyor_game">Conveyor Mania</option>
            </select>
        </div>

        <div class="form-group"><label>Level Title:</label><input type="text" name="scenario_name" required></div>
        <div class="form-group"><label>Description:</label><textarea name="description" rows="2"></textarea></div>
        <div class="form-group">
            <label>Difficulty:</label>
            <select name="difficulty" required>
                <option value="easy">Easy</option>
                <option value="medium">Medium</option>
                <option value="hard">Hard</option>
            </select>
        </div>
        <div class="form-group">
            <label>Time Limit (Seconds):</label>
            <input type="number" name="time_limit_seconds" min="0" value="0" placeholder="Optional for memory or conveyor levels">
        </div>
        <div class="form-group"><label>Upload Image (optional for memory/conveyor):</label><input type="file" id="scenario_image" name="scenario_image" accept="image/*"></div>

        <div id="conveyor-sign-builder" class="simple-builder">
            <label>Conveyor Mania Signs:</label>
            <p class="subtle-note">Add each sign image, its meaning, and the category where users should place it.</p>
            <div id="conveyor-sign-rows"></div>
            <button type="button" class="tool-btn" id="add-conveyor-sign">Add Another Sign</button>
        </div>

        <div id="simple-level-builder" class="simple-builder">
            <p class="subtle-note">This builder is for memory and conveyor levels. You can add a title, description, difficulty, timing, and an optional image. The game data itself is handled later by the game engine.</p>
        </div>
        
        <div id="creator-workspace" style="display: none;">
            <div class="toolbar">
                <button type="button" class="tool-btn active" id="tool-rect" onclick="setShapeTool('rect')">Rectangle</button>
                <button type="button" class="tool-btn" id="tool-circle" onclick="setShapeTool('circle')">Circle</button>
            </div>
            <div class="workspace-grid">
                <div class="canvas-wrapper">
                    <div id="canvas-container">
                        <img id="scenery-img" src="" draggable="false">
                        <canvas id="snip-overlay"></canvas>
                        <div class="custom-popup-modal" id="label-popup">
                            <input type="text" id="popup-input" placeholder="Violation Details">
                            <button type="button" onclick="closePopupModal(true)">Save</button>
                            <button type="button" onclick="closePopupModal(false)">Cancel</button>
                        </div>
                    </div>
                </div>
                <div class="hotspot-sidebar">
                    <button type="submit" style="background:#10b981; color:white; border:none; padding:12px; width:100%; border-radius:6px; cursor:pointer;">Save Level</button>
                </div>
            </div>
        </div>

        <div id="non-hotspot-save" style="display:none; margin-top:15px;">
            <button type="submit" style="background:#10b981; color:white; border:none; padding:12px; width:100%; border-radius:6px; cursor:pointer;">Save Level</button>
        </div>
    </form>
</div>
<script>
    const state = { 
        hotspots: [], 
        currentShape: 'rect', 
        isDrawing: false, 
        startX: 0, 
        startY: 0, 
        currentX: 0, 
        currentY: 0, 
        imgWidth: 0, 
        imgHeight: 0, 
        pendingHotspot: null,
        animationFrameId: null 
    };
    
    const refs = { 
        workspace: document.getElementById('creator-workspace'), 
        simpleBuilder: document.getElementById('simple-level-builder'),
        conveyorSignBuilder: document.getElementById('conveyor-sign-builder'),
        conveyorSignRows: document.getElementById('conveyor-sign-rows'),
        addConveyorSign: document.getElementById('add-conveyor-sign'),
        nonHotspotSave: document.getElementById('non-hotspot-save'),
        sceneryImg: document.getElementById('scenery-img'), 
        canvasContainer: document.getElementById('canvas-container'), 
        snipOverlay: document.getElementById('snip-overlay'), 
        fileInput: document.getElementById('scenario_image'), 
        popup: document.getElementById('label-popup'), 
        popupInput: document.getElementById('popup-input'),
        gameTypeSelect: document.getElementById('game_type_select')
    };
    const ctx = refs.snipOverlay.getContext('2d');

    function syncGameBuilderMode() {
        const selectedGame = refs.gameTypeSelect.value;
        const isHotspot = selectedGame === 'hotspot_test';
        const isConveyor = selectedGame === 'conveyor_game';

        refs.workspace.style.display = isHotspot ? 'block' : 'none';
        refs.simpleBuilder.style.display = isHotspot ? 'none' : 'block';
        refs.conveyorSignBuilder.style.display = isConveyor ? 'block' : 'none';
        refs.conveyorSignRows.querySelectorAll('input, select').forEach((field) => {
            field.disabled = !isConveyor;
            field.required = isConveyor;
        });
        refs.nonHotspotSave.style.display = isHotspot ? 'none' : 'block';

        if (!isHotspot) {
            refs.snipOverlay.style.display = 'none';
            refs.popup.style.display = 'none';
            return;
        }

        if (!refs.fileInput.files.length) {
            refs.workspace.style.display = 'none';
        }
    }

    refs.gameTypeSelect.addEventListener('change', syncGameBuilderMode);

    function addConveyorSignRow() {
        const row = document.createElement('div');
        row.className = 'conveyor-sign-row';
        row.innerHTML = `
            <input type="file" name="conveyor_sign_images[]" accept="image/*" required>
            <input type="text" name="conveyor_sign_meanings[]" placeholder="Meaning, e.g. Stop" required>
            <select name="conveyor_sign_categories[]" required>
                <option value="">Answer category</option>
                <option value="regulatory">Regulatory</option>
                <option value="warning">Warning</option>
                <option value="informative">Informative</option>
            </select>
            <button type="button" class="remove-sign-row">Remove</button>
        `;
        row.querySelector('.remove-sign-row').addEventListener('click', () => {
            row.remove();
            if (!refs.conveyorSignRows.children.length) addConveyorSignRow();
        });
        refs.conveyorSignRows.appendChild(row);
    }

    refs.addConveyorSign.addEventListener('click', addConveyorSignRow);
    addConveyorSignRow();

    refs.fileInput.addEventListener('change', (e) => {
        if (!e.target.files.length) return;
        const reader = new FileReader();
        reader.onload = (e) => {
            refs.sceneryImg.src = e.target.result;
            if (refs.gameTypeSelect.value === 'hotspot_test') {
                refs.workspace.style.display = 'block';
            }
            refs.sceneryImg.onload = () => {
                state.imgWidth = refs.sceneryImg.clientWidth;
                state.imgHeight = refs.sceneryImg.clientHeight;
                refs.canvasContainer.style.width = state.imgWidth + 'px';
                refs.canvasContainer.style.height = state.imgHeight + 'px';
                refs.snipOverlay.width = state.imgWidth;
                refs.snipOverlay.height = state.imgHeight;
            };
        };
        reader.readAsDataURL(e.target.files[0]);
    });

    function setShapeTool(t) {
        state.currentShape = t;
        document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tool-' + t).classList.add('active');
    }
    refs.canvasContainer.addEventListener('pointerdown', (e) => {
        if (refs.popup.contains(e.target)) {
            return; 
        }

        const b = refs.canvasContainer.getBoundingClientRect();
        state.isDrawing = true;
        state.startX = e.clientX - b.left;
        state.startY = e.clientY - b.top;
        state.currentX = state.startX;
        state.currentY = state.startY;
        refs.snipOverlay.style.display = 'block';
        refs.canvasContainer.setPointerCapture(e.pointerId);
    });
    refs.canvasContainer.addEventListener('pointermove', (e) => {
        if (!state.isDrawing) return;
        const b = refs.canvasContainer.getBoundingClientRect();
        state.currentX = Math.max(0, Math.min(e.clientX - b.left, state.imgWidth));
        state.currentY = Math.max(0, Math.min(e.clientY - b.top, state.imgHeight));
        if (!state.animationFrameId) {
            state.animationFrameId = requestAnimationFrame(() => {
                drawMask();
                state.animationFrameId = null; 
            });
        }
    });
    refs.canvasContainer.addEventListener('pointerup', (e) => {
        if (!state.isDrawing) return;
        state.isDrawing = false;
        refs.canvasContainer.releasePointerCapture(e.pointerId);

        if (state.animationFrameId) {
            cancelAnimationFrame(state.animationFrameId);
            state.animationFrameId = null;
        }

        const w = Math.abs(state.currentX - state.startX);
        const h = Math.abs(state.currentY - state.startY);
        
        if (w < 5 || h < 5) { 
            refs.snipOverlay.style.display = 'none'; 
            return; 
        }
        
        state.pendingHotspot = { 
            x: Math.min(state.startX, state.currentX), 
            y: Math.min(state.startY, state.currentY), 
            width: w, 
            height: h, 
            shape: state.currentShape 
        };
        
        refs.popup.style.left = (state.pendingHotspot.x + 10) + 'px';
        refs.popup.style.top = state.pendingHotspot.y + 'px';
        refs.popup.style.display = 'block';
    });

    function drawMask() {
        ctx.clearRect(0, 0, state.imgWidth, state.imgHeight);
        
        ctx.fillStyle = 'rgba(0,0,0,0.3)';
        ctx.fillRect(0, 0, state.imgWidth, state.imgHeight);
        
        const x = Math.min(state.startX, state.currentX);
        const y = Math.min(state.startY, state.currentY);
        const w = Math.abs(state.currentX - state.startX);
        const h = Math.abs(state.currentY - state.startY);
        
        ctx.clearRect(x, y, w, h);
        
        ctx.strokeStyle = '#3b82f6';
        ctx.lineWidth = 2; 
        
        if (state.currentShape === 'circle') {
            ctx.beginPath();
            ctx.ellipse(x + w/2, y + h/2, w/2, h/2, 0, 0, 2 * Math.PI);
            ctx.stroke();
        } else { 
            ctx.strokeRect(x, y, w, h); 
        }
    }

    function closePopupModal(save) {
        refs.popup.style.display = 'none';
        refs.snipOverlay.style.display = 'none';
        
        if (save && state.pendingHotspot) {
            state.hotspots.push({
                shape: state.pendingHotspot.shape, 
                label: refs.popupInput.value || 'Violation',
                x: (state.pendingHotspot.x / state.imgWidth) * 100, 
                y: (state.pendingHotspot.y / state.imgHeight) * 100,
                w: (state.pendingHotspot.width / state.imgWidth) * 100, 
                h: (state.pendingHotspot.height / state.imgHeight) * 100
            });
            refs.popupInput.value = '';
        }
        redraw();
    }

    function redraw() {
        refs.canvasContainer.querySelectorAll('.hotspot-box').forEach(el => el.remove());
        state.hotspots.forEach(h => {
            const b = document.createElement('div');
            b.className = 'hotspot-box';
            Object.assign(b.style, { left: h.x+'%', top: h.y+'%', width: h.w+'%', height: h.h+'%' });
            if (h.shape === 'circle') b.style.borderRadius = '50%';
            refs.canvasContainer.appendChild(b);
        });
        document.getElementById('hotspots_data').value = JSON.stringify(state.hotspots);
    }

    syncGameBuilderMode();
</script>
</body>
</html>