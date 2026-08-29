<?php
session_start();
require_once __DIR__ . '/../../db/db_con.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = db_connect();
$user_id = intval($_SESSION['user_id']);
$module_id = isset($_GET['module_id']) ? intval($_GET['module_id']) : 0;
$selected_lang = isset($_GET['lang']) && in_array(strtolower($_GET['lang']), ['en', 'tl']) ? strtolower($_GET['lang']) : 'en';

if ($module_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

try {
    $module_stmt = $conn->prepare('SELECT module_id, chapter_number, title, description, module_data, certificate_template FROM learning_modules WHERE module_id = ? LIMIT 1');
    $module_stmt->execute([$module_id]);
    $module = $module_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$module) {
        header('Location: dashboard.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['action']) || $_POST['action'] !== 'complete_module') {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid request action.']);
            exit;
        }

        $quiz_score = isset($_POST['quiz_score']) ? (float)$_POST['quiz_score'] : null;
        $pass_score = isset($_POST['pass_score']) ? (float)$_POST['pass_score'] : 60.0;
        $module_data = null;
        $decoded = json_decode($module['module_data'], true);
        if (is_array($decoded) && isset($decoded['content']) && is_array($decoded['content'])) {
            $module_data = $decoded;
        }

        if ($module_data !== null && is_array($module_data) && isset($module_data['quiz'])) {
            if ($quiz_score === null || $quiz_score < $pass_score) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'You need at least ' . $pass_score . '% to finish this module.']);
                exit;
            }
        }

        $completion_date = date('Y-m-d H:i:s');
        $progress_percent = 100.0;
        $is_completed = 1;

        $stmtCheck = $conn->prepare('SELECT progress_id FROM progress WHERE user_id = ? AND module_id = ? AND game_name = ? AND stage_number = 0 LIMIT 1');
        $stmtCheck->execute([$user_id, $module_id, 'learning_module']);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $stmtUpdate = $conn->prepare('UPDATE progress SET is_completed = ?, progress_percent = ?, completion_date = ? WHERE progress_id = ?');
            $stmtUpdate->execute([$is_completed, $progress_percent, $completion_date, $existing['progress_id']]);
        } else {
            $stmtInsert = $conn->prepare('INSERT INTO progress (user_id, module_id, game_name, stage_number, is_completed, progress_percent, completion_date) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmtInsert->execute([$user_id, $module_id, 'learning_module', 0, $is_completed, $progress_percent, $completion_date]);
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Module progress saved.', 'progress_percent' => $progress_percent]);
        exit;
    }

    $progress_stmt = $conn->prepare('SELECT progress_percent, is_completed FROM progress WHERE user_id = ? AND module_id = ? AND game_name = ? AND stage_number = 0 LIMIT 1');
    $progress_stmt->execute([$user_id, $module_id, 'learning_module']);
    $progress = $progress_stmt->fetch(PDO::FETCH_ASSOC);

    $progress_percent = $progress ? intval($progress['progress_percent']) : 0;
    $is_completed = $progress ? intval($progress['is_completed']) : 0;

    $module_data = null;
    $fallback_message = '';
    $module_format = 'legacy';
    $decoded = json_decode($module['module_data'], true);
    if (!is_array($decoded) && is_string($module['module_data'])) {
        $decoded = json_decode(trim($module['module_data']), true);
    }

    if (is_array($decoded)) {
        if (isset($decoded['lesson']) && is_array($decoded['lesson'])) {
            $decoded = $decoded['lesson'];
        }

        if (
            (isset($decoded['content']) && is_array($decoded['content'])) ||
            (isset($decoded['sections']) && is_array($decoded['sections'])) ||
            (isset($decoded['steps']) && is_array($decoded['steps'])) ||
            (isset($decoded['summary']) && (isset($decoded['quiz']) || isset($decoded['content']) || isset($decoded['sections'])))
        ) {
            $module_data = $decoded;
            $module_format = 'lesson';
            if (isset($module_data['content']) && !is_array($module_data['content'])) {
                $module_data['content'] = [];
            }
            if (isset($module_data['sections']) && is_array($module_data['sections']) && empty($module_data['content'])) {
                $module_data['content'] = $module_data['sections'];
            }
            if (isset($module_data['steps']) && is_array($module_data['steps']) && empty($module_data['content'])) {
                $module_data['content'] = $module_data['steps'];
            }
        } elseif (isset($decoded[$selected_lang]) && is_array($decoded[$selected_lang])) {
            $module_data = $decoded[$selected_lang];
        } elseif (isset($decoded['en']) && is_array($decoded['en'])) {
            $module_data = $decoded['en'];
            if ($selected_lang === 'tl') {
                $fallback_message = 'Tagalog version is not available for this module, so English is being shown instead.';
            }
        } elseif (isset($decoded['nodes']) && is_array($decoded['nodes'])) {
            $module_data = $decoded;
            if ($selected_lang === 'tl') {
                $fallback_message = 'This module only has English content. Tagalog selection is available once translation is added.';
            }
        }
    }
} catch (PDOException $e) {
    die('Database Error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($module['title']); ?> - Learning Module</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: radial-gradient(circle at top, #1f2937 0%, #0f172a 60%, #020617 100%); margin: 0; padding: 20px; color: #e2e8f0; }
        .container { max-width: 960px; margin: 0 auto; background: rgba(15, 23, 42, 0.95); border-radius: 24px; padding: 28px; box-shadow: 0 24px 80px rgba(15, 23, 42, 0.5); border: 1px solid rgba(148, 163, 184, 0.12); }
        h1 { margin-top: 0; color: #f8fafc; font-size: 2.25rem; }
        .tagline { color: #cbd5e1; margin-bottom: 22px; max-width: 780px; }
        .progress-container { background: rgba(148, 163, 184, 0.18); border-radius: 999px; overflow: hidden; position: relative; height: 28px; margin-bottom: 20px; border: 1px solid rgba(148, 163, 184, 0.2); }
        .progress-fill { background: linear-gradient(90deg, #38bdf8, #7c3aed); height: 100%; width: <?php echo $progress_percent; ?>%; transition: width 0.3s ease; }
        .progress-text { position: absolute; width: 100%; text-align: center; top: 0; left: 0; line-height: 28px; font-weight: 700; color: #f8fafc; text-shadow: 0 1px 3px rgba(0,0,0,0.4); }
        .module-card { border: 1px solid rgba(148, 163, 184, 0.18); border-radius: 22px; padding: 24px; margin-bottom: 20px; background: rgba(15, 23, 42, 0.9); backdrop-filter: blur(8px); }
        .module-card h2 { margin: 0 0 14px; font-size: 1.35rem; color: #f8fafc; }
        .chat-window { border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 28px; padding: 20px; background: linear-gradient(180deg, rgba(15,23,42,0.96), rgba(15,23,42,0.82)); min-height: 360px; position: relative; box-shadow: inset 0 0 0 1px rgba(255,255,255,0.04); display: flex; flex-direction: column; }
        .chat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
        .chat-header h3 { margin: 0; font-size: 0.95rem; letter-spacing: 0.14em; text-transform: uppercase; color: rgba(226, 232, 240, 0.7); }
        .chat-header .status-chip { padding: 6px 12px; font-size: 0.75rem; }
        .message-list { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 14px; padding-right: 4px; }
        .message-bubble { max-width: 72%; padding: 16px 18px; border-radius: 24px; line-height: 1.7; position: relative; word-break: break-word; }
        .message-bubble.bot { align-self: flex-start; background: rgba(255,255,255,0.09); border: 1px solid rgba(148, 163, 184, 0.18); color: #e2e8f0; }
        .message-bubble.bot::before { content: 'Assistant'; position: absolute; top: -18px; left: 18px; font-size: 0.72rem; letter-spacing: 0.08em; text-transform: uppercase; color: #93c5fd; }
        .message-bubble.user { align-self: flex-end; background: linear-gradient(135deg, rgba(56,189,248,0.25), rgba(139,92,246,0.25)); border: 1px solid rgba(56, 189, 248, 0.4); color: #f8fafc; }
        .choices { display: grid; gap: 12px; margin-top: 16px; }
        .choice-btn { background: rgba(59, 130, 246, 0.18); color: #e2e8f0; border: 1px solid rgba(59, 130, 246, 0.32); border-radius: 18px; padding: 14px 18px; font-size: 0.95rem; cursor: pointer; transition: transform 0.2s ease, background 0.2s ease, border-color 0.2s ease; text-align: left; }
        .choice-btn:hover { transform: translateY(-1px); background: rgba(56, 189, 248, 0.22); border-color: rgba(56, 189, 248, 0.6); }
        .choice-btn:disabled { cursor: not-allowed; opacity: 0.6; transform: none; }
        .status-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 20px; }
        .status-chip { padding: 10px 14px; border-radius: 999px; background: rgba(148, 163, 184, 0.12); color: #f8fafc; font-size: 0.85rem; letter-spacing: 0.02em; }
        .complete-btn { background: linear-gradient(135deg, #10b981 0%, #14b8a6 100%); color: white; padding: 14px 20px; border: none; border-radius: 16px; cursor: pointer; font-weight: 700; letter-spacing: 0.02em; transition: transform 0.2s ease, filter 0.2s ease; }
        .complete-btn:hover { transform: translateY(-1px); filter: brightness(1.05); }
        .complete-btn:disabled { opacity: 0.55; cursor: not-allowed; transform: none; }
        .message { padding: 16px 18px; border-radius: 18px; margin-top: 18px; background: rgba(148, 163, 184, 0.12); color: #cbd5e1; }
        .back-link { display: inline-block; margin-top: 20px; text-decoration: none; color: #93c5fd; font-weight: 600; }
    </style>
</head>
<body>
<div class="container">
    <a class="back-link" href="dashboard.php">← Back to Dashboard</a>
    <h1><?php echo htmlspecialchars($module['title']); ?></h1>
    <p class="tagline"><?php echo htmlspecialchars($module['description']); ?></p>

    <div class="progress-container">
        <div class="progress-fill"></div>
        <div class="progress-text"><?php echo $progress_percent; ?>%</div>
    </div>

    <div class="module-card">
        <div class="status-row">
            <span class="status-chip">Chapter <?php echo intval($module['chapter_number']); ?></span>
            <span class="status-chip"><?php echo $is_completed ? 'Completed' : 'In progress'; ?></span>
        </div>
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 16px;">
            <label for="language-select" style="font-weight: 700; color: #cbd5e1;">Conversation language:</label>
            <select id="language-select" onchange="window.location.href='?module_id=<?php echo $module_id; ?>&lang=' + this.value" style="padding: 10px 12px; border-radius: 14px; border: 1px solid rgba(148, 163, 184, 0.25); background: rgba(255,255,255,0.08); color: #e2e8f0;">
                <option value="en" <?php echo $selected_lang === 'en' ? 'selected' : ''; ?>>English</option>
                <option value="tl" <?php echo $selected_lang === 'tl' ? 'selected' : ''; ?>>Tagalog</option>
            </select>
        </div>
        <?php if (!empty($fallback_message)): ?>
            <div class="message" style="background: rgba(245, 158, 11, 0.12); color: #f8e7c0; border-color: rgba(245, 158, 11, 0.35);">
                <?php echo htmlspecialchars($fallback_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($module_format === 'lesson' && is_array($module_data)): ?>
            <div class="chat-window" id="lesson-chat-window" style="margin-top: 12px;">
                <div class="chat-header">
                    <h3>Lesson conversation</h3>
                    <span class="status-chip">Pass requirement: <?php echo (int)($module_data['pass_score'] ?? 60); ?>%</span>
                </div>
                <div id="lesson-message-list" class="message-list"></div>
                <div id="lesson-choices" class="choices"></div>
            </div>

            <div id="lesson-quiz" style="display:none; margin-top:24px; background: rgba(15, 23, 42, 0.8); border: 1px solid rgba(148, 163, 184, 0.18); border-radius:22px; padding:24px;">
                <h3 style="margin-top:0; color:#f8fafc; font-size:1.2rem;">Knowledge Check</h3>
                <p style="color:#cbd5e1; margin-bottom:16px;">Answer all questions. You must score at least <?php echo (int)($module_data['pass_score'] ?? 60); ?>% to complete this module.</p>
                <div id="quiz-container"></div>
                <div id="quiz-status" class="message" style="display:none; margin-top:16px;"></div>
                <div style="margin-top: 20px;">
                    <button id="completeBtn" class="complete-btn" disabled>Finish Module</button>
                </div>
            </div>
        <?php elseif (!$module_data || !isset($module_data['nodes']) || !is_array($module_data['nodes'])): ?>
            <div class="message">Module content is unavailable or invalid. Please contact the administrator.</div>
        <?php else: ?>
            <div class="chat-window">
                <div class="chat-header">
                    <h3>Conversation</h3>
                    <span class="status-chip"><?php echo $is_completed ? 'Completed' : 'In progress'; ?></span>
                </div>
                <div id="messageList" class="message-list"></div>
                <div id="choices" class="choices"></div>
                <div id="finishArea" style="margin-top: 20px; display: none;">
                    <button id="completeBtn" class="complete-btn">Mark as Completed</button>
                </div>
                <div id="moduleStatus" class="message" style="display: none;"></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const moduleData = <?php echo json_encode($module_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const currentUserCompleted = <?php echo $is_completed ? 'true' : 'false'; ?>;
const moduleFormat = <?php echo $module_format === 'lesson' ? '"lesson"' : '"legacy"'; ?>;
const passScore = Number((moduleData && moduleData.pass_score) || 60);
const messageList = document.getElementById('messageList');
const choicesEl = document.getElementById('choices');
const finishArea = document.getElementById('finishArea');
const completeBtn = document.getElementById('completeBtn');
const moduleStatus = document.getElementById('moduleStatus');
let currentNodeKey = 'start';
let isCompleted = currentUserCompleted;
let chatLog = [];

function getStartNode() {
    if (moduleData?.nodes?.start) return 'start';
    const keys = moduleData?.nodes ? Object.keys(moduleData.nodes) : [];
    return keys.length ? keys[0] : null;
}

function addChatMessage(text, sender, image = null) {
    chatLog.push({ text, sender, image });
    renderChat();
}

function renderChat() {
    messageList.innerHTML = '';
    chatLog.forEach(item => {
        const bubble = document.createElement('div');
        bubble.className = `message-bubble ${item.sender}`;

        if (item.text) {
            bubble.textContent = item.text;
        }

        if (item.image) {
            const imageEl = document.createElement('img');
            imageEl.src = item.image;
            imageEl.alt = 'Module illustration';
            imageEl.style.display = 'block';
            imageEl.style.maxWidth = '100%';
            imageEl.style.marginTop = '12px';
            imageEl.style.borderRadius = '14px';
            imageEl.style.border = '1px solid rgba(148, 163, 184, 0.25)';
            bubble.appendChild(imageEl);
        }

        messageList.appendChild(bubble);
    });
    messageList.scrollTop = messageList.scrollHeight;
}

function renderNode(nodeKey) {
    const node = moduleData?.nodes?.[nodeKey];
    if (!node) {
        addChatMessage('Unable to load this module node.', 'bot');
        choicesEl.innerHTML = '';
        finishArea.style.display = 'none';
        return;
    }

    addChatMessage(node.bot_message || 'No message available.', 'bot', node.image || null);
    choicesEl.innerHTML = '';

    const choices = Array.isArray(node.choices) ? node.choices : [];
    if (choices.length === 0) {
        const finishText = isCompleted ? 'This module is already marked completed.' : 'This is the end of the module. Mark it as completed when you are ready.';
        moduleStatus.style.display = 'block';
        moduleStatus.textContent = finishText;
        finishArea.style.display = 'block';
        completeBtn.disabled = isCompleted;
        return;
    }

    moduleStatus.style.display = 'none';
    finishArea.style.display = 'none';

    choices.forEach(choice => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'choice-btn';
        button.textContent = choice.text || 'Choose';
        button.onclick = () => {
            addChatMessage(choice.text || 'Response', 'user');
            if (!choice.next_node) {
                moduleStatus.style.display = 'block';
                moduleStatus.textContent = 'That response does not lead anywhere.';
                return;
            }
            currentNodeKey = choice.next_node;
            renderNode(currentNodeKey);
        };
        choicesEl.appendChild(button);
    });
}

async function saveModuleCompletion(extraData = {}) {
    completeBtn.disabled = true;
    completeBtn.textContent = 'Saving...';

    try {
        const payload = new URLSearchParams({ action: 'complete_module' });
        Object.entries(extraData).forEach(([key, value]) => payload.append(key, String(value)));

        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: payload
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Save failed');
        }

        isCompleted = true;
        if (moduleStatus) {
            moduleStatus.textContent = 'Module progress saved successfully.';
            moduleStatus.style.display = 'block';
        }
        if (completeBtn) {
            completeBtn.disabled = true;
        }
        const progressFill = document.querySelector('.progress-fill');
        if (progressFill) progressFill.style.width = '100%';
        const progressText = document.querySelector('.progress-text');
        if (progressText) progressText.textContent = '100%';
    } catch (error) {
        if (moduleStatus) {
            moduleStatus.textContent = 'Error saving progress: ' + error.message;
            moduleStatus.style.display = 'block';
        }
        if (completeBtn) {
            completeBtn.disabled = false;
        }
    } finally {
        if (completeBtn) {
            completeBtn.textContent = 'Finish Module';
        }
    }
}

if (moduleFormat === 'lesson') {
    const quizContainer = document.getElementById('quiz-container');
    const quizStatus = document.getElementById('quiz-status');
    const lessonMessageList = document.getElementById('lesson-message-list');
    const lessonChoices = document.getElementById('lesson-choices');
    const lessonQuestions = Array.isArray(moduleData?.quiz) ? moduleData.quiz : [];
    const answeredState = {};
    const lessonNodes = {};
    const sections = Array.isArray(moduleData?.content)
        ? moduleData.content
        : Array.isArray(moduleData?.sections)
            ? moduleData.sections
            : Array.isArray(moduleData?.steps)
                ? moduleData.steps
                : [];

    function createImageElement(src, alt) {
        const imageEl = document.createElement('img');
        imageEl.src = src;
        imageEl.alt = alt;
        imageEl.style.display = 'block';
        imageEl.style.maxWidth = '100%';
        imageEl.style.marginTop = '12px';
        imageEl.style.borderRadius = '14px';
        imageEl.style.border = '1px solid rgba(148, 163, 184, 0.25)';
        imageEl.onerror = function() {
            const fallback = document.createElement('div');
            fallback.textContent = 'Lesson illustration unavailable.';
            fallback.style.marginTop = '12px';
            fallback.style.padding = '12px 14px';
            fallback.style.borderRadius = '14px';
            fallback.style.background = 'rgba(148, 163, 184, 0.12)';
            fallback.style.color = '#cbd5e1';
            fallback.style.fontSize = '0.85rem';
            imageEl.replaceWith(fallback);
        };
        return imageEl;
    }

    function renderLessonChatEntry(text, sender, image = null) {
        const bubble = document.createElement('div');
        bubble.className = `message-bubble ${sender}`;
        if (text) bubble.textContent = text;
        if (image) bubble.appendChild(createImageElement(image, 'Lesson illustration'));
        lessonMessageList.appendChild(bubble);
        lessonMessageList.scrollTop = lessonMessageList.scrollHeight;
    }

    function buildLessonChatNodes() {
        const nodes = {};
        const summaryText = moduleData?.summary || moduleData?.title || 'Let’s begin this lesson.';
        const startNode = {
            bot_message: summaryText,
            image: moduleData?.cover_image || null,
            choices: [{ text: 'Start lesson', next_node: sections.length ? 'section-0' : 'quiz' }]
        };
        nodes.start = startNode;

        sections.forEach((section, index) => {
            const nextNode = index < sections.length - 1 ? `section-${index + 1}` : 'lesson-quiz-intro';
            nodes[`section-${index}`] = {
                bot_message: section.text || section.heading || 'Lesson point',
                image: section.image || null,
                choices: [{ text: 'Continue', next_node: nextNode }]
            };
        });

        nodes['lesson-quiz-intro'] = {
            bot_message: 'You reached the end of the lesson. Let’s check what you learned.',
            image: null,
            choices: [{ text: 'Start the test', next_node: 'quiz' }]
        };

        nodes.quiz = {
            bot_message: 'Answer the questions below to finish this module.',
            image: null,
            choices: []
        };

        return nodes;
    }

    lessonNodes = buildLessonChatNodes();
    let currentLessonNodeKey = 'start';

    function renderLessonNode(nodeKey) {
        const node = lessonNodes[nodeKey];
        if (!node) return;

        if (nodeKey === 'quiz') {
            lessonChoices.innerHTML = '';
            lessonMessageList.innerHTML = '';
            renderLessonChatEntry(node.bot_message || 'Answer the questions below.', 'bot', node.image || null);
            document.getElementById('lesson-quiz').style.display = 'block';
            return;
        }

        lessonMessageList.innerHTML = '';
        renderLessonChatEntry(node.bot_message || 'Lesson details.', 'bot', node.image || null);
        lessonChoices.innerHTML = '';

        if (!Array.isArray(node.choices) || !node.choices.length) {
            return;
        }

        node.choices.forEach((choice) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'choice-btn';
            button.textContent = choice.text || 'Continue';
            button.onclick = () => {
                renderLessonChatEntry(choice.text || 'Continue', 'user');
                const nextKey = choice.next_node || 'quiz';
                currentLessonNodeKey = nextKey;
                if (nextKey === 'quiz') {
                    lessonChoices.innerHTML = '';
                    document.getElementById('lesson-quiz').style.display = 'block';
                    renderLessonNode(nextKey);
                    return;
                }
                renderLessonNode(nextKey);
            };
            lessonChoices.appendChild(button);
        });
    }

    function evaluateLessonQuiz() {
        const answeredCount = Object.keys(answeredState).length;
        const total = lessonQuestions.length;
        if (!total) {
            if (completeBtn) completeBtn.disabled = true;
            return;
        }

        let correctCount = 0;
        lessonQuestions.forEach((question, index) => {
            const selectedIndex = answeredState[index];
            if (selectedIndex === undefined) return;
            const selectedOption = question.options?.[selectedIndex];
            const isCorrect = selectedOption && selectedOption.correct === true;
            if (isCorrect) correctCount += 1;
        });

        const score = total ? Math.round((correctCount / total) * 100) : 0;
        const passed = score >= passScore;
        if (completeBtn) {
            completeBtn.disabled = !(answeredCount === total && passed);
        }

        if (answeredCount === total) {
            quizStatus.style.display = 'block';
            quizStatus.textContent = passed
                ? `You passed with ${score}% and are ready to finish the module.`
                : `You scored ${score}%. You need ${passScore}% to finish this module.`;
            if (!passed) {
                quizStatus.style.background = 'rgba(239, 68, 68, 0.12)';
                quizStatus.style.borderColor = 'rgba(239, 68, 68, 0.35)';
                quizStatus.style.color = '#fecaca';
            } else {
                quizStatus.style.background = 'rgba(16, 185, 129, 0.12)';
                quizStatus.style.borderColor = 'rgba(16, 185, 129, 0.35)';
                quizStatus.style.color = '#d1fae5';
            }
        } else {
            quizStatus.style.display = 'none';
        }
    }

    if (quizContainer) {
        lessonQuestions.forEach((question, questionIndex) => {
            const questionCard = document.createElement('div');
            questionCard.className = 'message';
            questionCard.style.marginTop = '18px';
            questionCard.innerHTML = `
                <h4 style="margin:0 0 12px; color:#f8fafc;">${questionIndex + 1}. ${question.question}</h4>
                <div class="choices" style="margin-top:0; grid-template-columns:1fr; gap:10px;"></div>
                <div class="quiz-feedback" style="display:none; margin-top:12px; color:#dbeafe; font-size:0.9rem; line-height:1.6;"></div>
            `;

            const optionsWrap = questionCard.querySelector('.choices');
            question.options.forEach((option, optionIndex) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'choice-btn';
                button.textContent = option.text;
                button.style.width = '100%';
                button.addEventListener('click', () => {
                    if (answeredState[questionIndex] !== undefined) return;
                    answeredState[questionIndex] = optionIndex;
                    Array.from(optionsWrap.children).forEach(child => child.disabled = true);
                    const isCorrect = option.correct === true;
                    const feedback = questionCard.querySelector('.quiz-feedback');
                    feedback.style.display = 'block';
                    feedback.textContent = isCorrect
                        ? `Correct. ${question.explanation || 'This answer is correct for the lesson.'}`
                        : `Not quite. ${question.explanation || 'Review the lesson content and try again.'}`;
                    feedback.style.color = isCorrect ? '#d1fae5' : '#fecaca';
                    evaluateLessonQuiz();
                });
                optionsWrap.appendChild(button);
            });

            quizContainer.appendChild(questionCard);
        });

        if (!lessonQuestions.length) {
            quizStatus.style.display = 'block';
            quizStatus.textContent = 'No quiz questions are available for this lesson yet.';
        }
    }

    renderLessonNode('start');

    completeBtn?.addEventListener('click', () => {
        const total = lessonQuestions.length;
        let correct = 0;
        lessonQuestions.forEach((question, index) => {
            const selectedIndex = answeredState[index];
            if (selectedIndex !== undefined && question.options?.[selectedIndex]?.correct) correct += 1;
        });
        const score = total ? Math.round((correct / total) * 100) : 0;
        if (score < passScore) {
            if (quizStatus) {
                quizStatus.style.display = 'block';
                quizStatus.style.background = 'rgba(239, 68, 68, 0.12)';
                quizStatus.style.borderColor = 'rgba(239, 68, 68, 0.35)';
                quizStatus.style.color = '#fecaca';
                quizStatus.textContent = `You scored ${score}%. You need ${passScore}% to finish this module.`;
            }
            return;
        }
        saveModuleCompletion({ quiz_score: score, pass_score: passScore });
    });
} else if (moduleData && moduleData.nodes) {
    const startNode = getStartNode();
    if (startNode) {
        currentNodeKey = startNode;
        renderNode(currentNodeKey);
    } else {
        addChatMessage('This module has no start node.', 'bot');
        choicesEl.innerHTML = '';
        finishArea.style.display = 'none';
    }
} else {
    addChatMessage('This module cannot be displayed because the content data is missing or malformed.', 'bot');
    choicesEl.innerHTML = '';
    finishArea.style.display = 'none';
}

if (moduleFormat !== 'lesson') {
    completeBtn?.addEventListener('click', saveModuleCompletion);
}
</script>
</body>
</html>
