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
    $level_stmt = $conn->prepare("SELECT title, description, background_image, time_limit_seconds FROM game_levels WHERE level_id = ?");
    $level_stmt->execute([$level_id]);
    $level = $level_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$level) {
        header('Location: dashboard.php');
        exit;
    }

    $items_stmt = $conn->prepare("SELECT item_id, item_label, shape_type, pos_x, pos_y, width, height FROM game_items WHERE level_id = ?");
    $items_stmt->execute([$level_id]);
    $hotspots = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error");
}

$timeLimitSeconds = isset($level['time_limit_seconds']) ? max(0, intval($level['time_limit_seconds'])) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($level['title']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 24px;
            background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
            color: #0f172a;
        }
        .game-shell {
            max-width: 980px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid #cbd5e1;
            border-radius: 18px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.12);
            padding: 24px;
        }
        .page-title {
            margin: 0 0 6px 0;
            font-size: 28px;
        }
        .page-description {
            margin: 0 0 18px 0;
            color: #475569;
            font-size: 15px;
        }
        .top-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        .timer-panel {
            padding: 12px 16px;
            background: #fff;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-weight: bold;
        }
        .status-chip {
            padding: 8px 12px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 13px;
            font-weight: bold;
        }
        .scenario-container {
            position: relative;
            display: inline-block;
            line-height: 0;
            width: 100%;
            max-width: 880px;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #cbd5e1;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.65);
        }
        .scenario-img {
            width: 100%;
            max-width: 100%;
            height: auto;
            display: block;
        }
        .hotspot {
            position: absolute;
            z-index: 10;
            cursor: pointer;
            background: transparent;
            border: 2px solid transparent;
            box-shadow: none;
            outline: none;
            padding: 0;
            appearance: none;
            -webkit-appearance: none;
            transition: transform 0.15s ease, background 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .hotspot:hover,
        .hotspot:focus-visible {
            /* Keep hit area invisible until found — no answer reveal on hover */
            background: transparent;
            border-color: transparent;
            transform: none;
        }
        .hotspot.circle {
            border-radius: 50%;
        }
        .hotspot.is-found {
            border-color: #16a34a;
            background: rgba(22, 163, 74, 0.45);
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.65);
            cursor: default;
        }
        .hotspot.is-missed {
            border-color: #dc2626;
            background: rgba(239, 68, 68, 0.35);
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.65);
            cursor: default;
        }
        .summary-panel {
            display: none;
            margin-top: 20px;
            max-width: 720px;
            background: #fff;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            padding: 18px;
        }
        .click-feedback {
            margin-top: 16px;
            padding: 12px 14px;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            display: none;
            font-weight: bold;
        }
        .summary-panel h3 {
            margin: 0 0 10px 0;
        }
        .summary-list {
            margin: 12px 0 0 0;
            padding-left: 20px;
        }
        .summary-actions {
            margin-top: 16px;
        }
        .action-link {
            display: inline-block;
            padding: 10px 16px;
            background: #2563eb;
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }
        @media (max-width: 768px) {
            .game-shell {
                padding: 16px;
            }
            .page-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="game-shell">
        <h1 class="page-title"><?php echo htmlspecialchars($level['title']); ?></h1>
        <p class="page-description"><?php echo htmlspecialchars($level['description']); ?></p>

        <div class="top-bar">
            <div class="timer-panel">
                <span>Time Remaining:</span>
                <span id="sh-timer-countdown"><?php echo $timeLimitSeconds > 0 ? sprintf('%02d:%02d', floor($timeLimitSeconds / 60), $timeLimitSeconds % 60) : 'Unlimited'; ?></span>
            </div>
            <div class="status-chip">Find every hazard to finish the round</div>
        </div>

        <div class="scenario-container">
            <img src="<?php echo htmlspecialchars($level['background_image']); ?>" alt="Scenario" class="scenario-img">

            <?php foreach ($hotspots as $item): ?>
                <button type="button"
                    class="hotspot <?php echo ($item['shape_type'] === 'circle') ? 'circle' : ''; ?>"
                    data-item-id="<?php echo intval($item['item_id']); ?>"
                    style="left: <?php echo $item['pos_x']; ?>%; 
                           top: <?php echo $item['pos_y']; ?>%; 
                           width: <?php echo $item['width']; ?>%; 
                           height: <?php echo $item['height']; ?>%;">
                </button>
            <?php endforeach; ?>
        </div>

        <div id="sh-click-feedback" class="click-feedback"></div>

        <div id="sh-summary-panel" class="summary-panel">
            <h3>Scoring Summary</h3>
            <p id="sh-summary-count">Hazards found: 0</p>
            <ol id="sh-summary-list" class="summary-list"></ol>
            <div class="summary-actions">
                <a href="dashboard.php" class="action-link">Back to Dashboard</a>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const timerCountdown = document.getElementById('sh-timer-countdown');
            const timerLimit = <?php echo $timeLimitSeconds; ?>;
            const summaryPanel = document.getElementById('sh-summary-panel');
            const summaryCount = document.getElementById('sh-summary-count');
            const summaryList = document.getElementById('sh-summary-list');
            const clickFeedback = document.getElementById('sh-click-feedback');
            const hotspotButtons = Array.from(document.querySelectorAll('.hotspot'));
            const levelId = <?php echo $level_id; ?>;
            const foundHazards = [];
            let remainingSeconds = timerLimit;
            let timerInterval = null;
            let timerRunning = true;

            function formatSeconds(seconds) {
                const mins = String(Math.floor(seconds / 60)).padStart(2, '0');
                const secs = String(seconds % 60).padStart(2, '0');
                return `${mins}:${secs}`;
            }

            function markFound(button, elapsed, itemLabel) {
                const itemId = Number(button.dataset.itemId);
                if (foundHazards.some((entry) => entry.itemId === itemId)) {
                    return;
                }

                foundHazards.push({ itemId, itemLabel: itemLabel || 'Violation', elapsed });
                button.classList.add('is-found');
                button.disabled = true;
            }

            function setClickFeedback(message) {
                if (!clickFeedback) return;
                clickFeedback.textContent = message;
                clickFeedback.style.display = 'block';
            }

            async function saveHazardSelection(itemId, elapsedSeconds) {
                const url = `submit_answer.php?level_id=${levelId}&item_id=${itemId}&elapsed_seconds=${encodeURIComponent(elapsedSeconds)}&ajax=1`;
                try {
                    const response = await fetch(url, { method: 'GET' });
                    const data = await response.json();
                    return data;
                } catch (error) {
                    return null;
                }
            }

            function showSummary() {
                if (!summaryPanel) return;
                summaryPanel.style.display = 'block';
                summaryCount.textContent = `Hazards found: ${foundHazards.length}`;
                summaryList.innerHTML = '';

                if (!foundHazards.length) {
                    const emptyItem = document.createElement('li');
                    emptyItem.textContent = 'No hazards were found before the timer ended.';
                    summaryList.appendChild(emptyItem);
                    return;
                }

                foundHazards.sort((a, b) => a.elapsed - b.elapsed).forEach((hazard) => {
                    const item = document.createElement('li');
                    item.textContent = `${hazard.itemLabel} — found at ${formatSeconds(hazard.elapsed)}.`;
                    summaryList.appendChild(item);
                });
            }

            function stopTimer() {
                if (timerInterval) {
                    clearInterval(timerInterval);
                    timerInterval = null;
                }
                timerRunning = false;
                hotspotButtons.forEach((button) => {
                    button.disabled = true;
                    if (!button.classList.contains('is-found')) {
                        button.classList.add('is-missed');
                    }
                });
                showSummary();
            }

            function checkRoundCompletion() {
                if (foundHazards.length >= hotspotButtons.length) {
                    if (timerCountdown) {
                        timerCountdown.textContent = 'Completed';
                    }
                    stopTimer();
                }
            }

            if (timerLimit > 0) {
                timerInterval = setInterval(() => {
                    remainingSeconds -= 1;
                    if (timerCountdown) {
                        timerCountdown.textContent = formatSeconds(Math.max(remainingSeconds, 0));
                    }

                    if (remainingSeconds <= 0) {
                        stopTimer();
                    }
                }, 1000);
            }

            hotspotButtons.forEach((button) => {
                button.addEventListener('click', async () => {
                    if (!timerRunning || button.disabled) {
                        return;
                    }

                    const elapsedSeconds = timerLimit - remainingSeconds;
                    const itemId = Number(button.dataset.itemId);
                    const result = await saveHazardSelection(itemId, elapsedSeconds);
                    const itemLabel = result?.selected_violation || 'Violation';
                    markFound(button, elapsedSeconds, itemLabel);
                    setClickFeedback(`Violation found: ${itemLabel}`);
                    checkRoundCompletion();
                });
            });

            if (timerLimit <= 0) {
                timerCountdown.textContent = 'Unlimited';
            }
        })();
    </script>

</body>
</html>