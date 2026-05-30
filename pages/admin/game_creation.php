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
        $difficulty = $_POST['difficulty'] ?? 'easy';
        $hotspots_json = $_POST['hotspots_data'] ?? '[]'; 

        if (empty($scenario_name) || !isset($_FILES['scenario_image']) || $_FILES['scenario_image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Please fill out the scenario title and upload a valid image.");
        }

        $game_stmt = $conn->prepare("SELECT game_id FROM games WHERE game_key = 'hotspot_test' LIMIT 1");
        $game_stmt->execute();
        $game_data = $game_stmt->fetch(PDO::FETCH_ASSOC);
        $game_id = $game_data['game_id'] ?? null;

        if (!$game_id) {
            throw new Exception("Master game tracking record ('hotspot_test') was not initialized.");
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

        $level_stmt = $conn->prepare("INSERT INTO game_levels (game_id, title, description, difficulty, background_image) VALUES (?, ?, ?, ?, ?)");
        $level_stmt->execute([$game_id, $scenario_name, $description, $difficulty, $relative_db_path]);
        
        $new_level_id = $conn->lastInsertId();

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

        $conn->commit();
        $message = "Successfully created new Scenario Hotspot Game!";
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
        .workspace-grid { display: grid; grid-template-columns: 1fr 320px; gap: 25px; margin-top: 20px; }
        .canvas-wrapper { position: relative; background: #e2e8f0; border-radius: 6px; overflow: hidden; display: flex; align-items: flex-start; justify-content: center; min-height: 400px; }
        #canvas-container { position: relative; display: inline-block; cursor: crosshair; }
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
    <h1>Game Creator Workspace</h1>
    <?php if (!empty($message)): ?>
        <div class="alert <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <form action="game_creation.php" method="POST" enctype="multipart/form-data" id="main-creation-form">
        <input type="hidden" name="action" value="create_game">
        <input type="hidden" name="hotspots_data" id="hotspots_data" value="[]">
        <div class="form-group"><label>Scenario Title:</label><input type="text" name="scenario_name" required></div>
        <div class="form-group"><label>Description:</label><textarea name="description" rows="2"></textarea></div>
        <div class="form-group"><label>Upload Image:</label><input type="file" id="scenario_image" name="scenario_image" accept="image/*" required></div>
        
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
                    <button type="submit" style="background:#10b981; color:white; border:none; padding:12px; width:100%; border-radius:6px; cursor:pointer;">Save Scenario</button>
                </div>
            </div>
        </div>
    </form>
</div>
<script>
    const state = { hotspots: [], currentShape: 'rect', isDrawing: false, startX: 0, startY: 0, currentX: 0, currentY: 0, imgWidth: 0, imgHeight: 0, pendingHotspot: null };
    const refs = { 
        workspace: document.getElementById('creator-workspace'), sceneryImg: document.getElementById('scenery-img'), 
        canvasContainer: document.getElementById('canvas-container'), snipOverlay: document.getElementById('snip-overlay'), 
        fileInput: document.getElementById('scenario_image'), popup: document.getElementById('label-popup'), 
        popupInput: document.getElementById('popup-input') 
    };
    const ctx = refs.snipOverlay.getContext('2d');

    refs.fileInput.addEventListener('change', (e) => {
        if (!e.target.files.length) return;
        const reader = new FileReader();
        reader.onload = (e) => {
            refs.sceneryImg.src = e.target.result;
            refs.workspace.style.display = 'block';
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

    refs.canvasContainer.addEventListener('mousedown', (e) => {
        const b = refs.canvasContainer.getBoundingClientRect();
        state.isDrawing = true;
        state.startX = e.clientX - b.left;
        state.startY = e.clientY - b.top;
        refs.snipOverlay.style.display = 'block';
    });

    window.addEventListener('mousemove', (e) => {
        if (!state.isDrawing) return;
        const b = refs.canvasContainer.getBoundingClientRect();
        state.currentX = Math.max(0, Math.min(e.clientX - b.left, state.imgWidth));
        state.currentY = Math.max(0, Math.min(e.clientY - b.top, state.imgHeight));
        drawMask();
    });

    window.addEventListener('mouseup', () => {
        if (!state.isDrawing) return;
        state.isDrawing = false;
        const w = Math.abs(state.currentX - state.startX);
        const h = Math.abs(state.currentY - state.startY);
        if (w < 5 || h < 5) { refs.snipOverlay.style.display = 'none'; return; }
        state.pendingHotspot = { x: Math.min(state.startX, state.currentX), y: Math.min(state.startY, state.currentY), width: w, height: h, shape: state.currentShape };
        refs.popup.style.left = (state.pendingHotspot.x + 10) + 'px';
        refs.popup.style.top = state.pendingHotspot.y + 'px';
        refs.popup.style.display = 'block';
    });

    function drawMask() {
        ctx.clearRect(0, 0, state.imgWidth, state.imgHeight);
        ctx.fillStyle = 'rgba(0,0,0,0.3)';
        ctx.fillRect(0, 0, state.imgWidth, state.imgHeight);
        const x = Math.min(state.startX, state.currentX), y = Math.min(state.startY, state.currentY);
        const w = Math.abs(state.currentX - state.startX), h = Math.abs(state.currentY - state.startY);
        ctx.clearRect(x, y, w, h);
        ctx.strokeStyle = '#3b82f6';
        if (state.currentShape === 'circle') {
            ctx.beginPath();
            ctx.ellipse(x + w/2, y + h/2, w/2, h/2, 0, 0, 2 * Math.PI);
            ctx.stroke();
        } else { ctx.strokeRect(x, y, w, h); }
    }

    function closePopupModal(save) {
        refs.popup.style.display = 'none';
        refs.snipOverlay.style.display = 'none';
        if (save && state.pendingHotspot) {
            state.hotspots.push({
                shape: state.pendingHotspot.shape, label: refs.popupInput.value || 'Violation',
                x: (state.pendingHotspot.x / state.imgWidth) * 100, y: (state.pendingHotspot.y / state.imgHeight) * 100,
                w: (state.pendingHotspot.width / state.imgWidth) * 100, h: (state.pendingHotspot.height / state.imgHeight) * 100
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
</script>
</body>
</html>