<?php
session_start();
require_once __DIR__ . '/../../db/db_con.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];
$certificate_id = (int)($_GET['certificate_id'] ?? 0);
$module_id = (int)($_GET['module_id'] ?? 0);

try {
    if (($_GET['action'] ?? '') === 'claim' && $module_id > 0) {
        $claim_stmt = $conn->prepare("SELECT lm.module_id, lm.title, lm.certificate_template, u.first_name, u.last_name, p.is_completed, p.completion_date FROM learning_modules lm JOIN users u ON u.user_id = ? JOIN progress p ON p.module_id = lm.module_id AND p.user_id = u.user_id AND p.game_name = 'learning_module' AND p.stage_number = 0 AND p.is_completed = 1 WHERE lm.module_id = ? AND lm.certificate_template IS NOT NULL AND lm.certificate_template <> '' LIMIT 1");
        $claim_stmt->execute([$user_id, $module_id]);
        $claim = $claim_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$claim) {
            http_response_code(403);
            exit('This certificate is not available.');
        }

        $recipient_name = trim($claim['first_name'] . ' ' . $claim['last_name']);
        $insert_stmt = $conn->prepare("INSERT INTO certificates (user_id, module_id, recipient_name, certificate_code, issue_date, email_sent, status) VALUES (?, ?, ?, ?, ?, 0, 'issued') ON DUPLICATE KEY UPDATE certificate_id = LAST_INSERT_ID(certificate_id)");
        $insert_stmt->execute([$user_id, $module_id, $recipient_name, 'RR-' . strtoupper(bin2hex(random_bytes(5))), $claim['completion_date']]);
        $certificate_id = (int)$conn->lastInsertId();
    }

    $certificate_stmt = $conn->prepare("SELECT c.certificate_id, c.recipient_name, c.certificate_code, c.issue_date, lm.title, lm.certificate_template FROM certificates c JOIN learning_modules lm ON lm.module_id = c.module_id WHERE c.certificate_id = ? AND c.user_id = ? LIMIT 1");
    $certificate_stmt->execute([$certificate_id, $user_id]);
    $certificate = $certificate_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$certificate) {
        http_response_code(404);
        exit('Certificate not found.');
    }
} catch (PDOException $e) {
    http_response_code(500);
    exit('Certificate service is unavailable.');
}

$template_path = htmlspecialchars($certificate['certificate_template'], ENT_QUOTES, 'UTF-8');
$recipient_name = htmlspecialchars($certificate['recipient_name'], ENT_QUOTES, 'UTF-8');
$completion_date = htmlspecialchars(date('F j, Y', strtotime($certificate['issue_date'])), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $recipient_name; ?> - Certificate</title>
    <style>
        body { margin: 0; padding: 20px; background: #e2e8f0; font-family: Georgia, serif; color: #172033; }
        .actions { max-width: 1100px; margin: 0 auto 15px; font-family: Arial, sans-serif; }
        .actions a, .actions button { background: #0f172a; color: white; border: 0; padding: 10px 14px; text-decoration: none; cursor: pointer; border-radius: 4px; }
        .certificate { position: relative; max-width: 1100px; aspect-ratio: 1.414 / 1; margin: auto; background: white; overflow: hidden; }
        .certificate-template { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: 0; }
        .certificate > :not(.certificate-template) { z-index: 1; }
        .recipient { position: absolute; top: 48%; left: 10%; width: 80%; text-align: center; font-size: clamp(25px, 5vw, 68px); font-weight: bold; }
        .date { position: absolute; top: 66%; left: 10%; width: 80%; text-align: center; font: 18px Arial, sans-serif; }
        .code { position: absolute; bottom: 4%; left: 5%; font: 11px Arial, sans-serif; }
        @page { size: landscape; margin: 0; }
        @media print {
            body { padding: 0; background: white; }
            .actions { display: none; }
            .certificate { max-width: none; width: 100vw; height: 100vh; aspect-ratio: auto; }
            .certificate-template { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">Print / Save as PDF</button> <a href="dashboard.php">Back to settings</a></div>
    <main class="certificate" aria-label="Certificate of completion">
        <img class="certificate-template" src="<?php echo $template_path; ?>" alt="">
        <div class="recipient"><?php echo $recipient_name; ?></div>
        <div class="date">Completed <?php echo $completion_date; ?> - <?php echo htmlspecialchars($certificate['title'], ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="code">Certificate <?php echo htmlspecialchars($certificate['certificate_code'], ENT_QUOTES, 'UTF-8'); ?></div>
    </main>
</body>
</html>