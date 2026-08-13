<?php
    session_start();
    require_once __DIR__ . '/../db/db_con.php';

    $conn = db_connect();
    $token = $_GET['token'] ?? '';

    if ($token === '') {
        header('Location: login.php?verified=0');
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT user_id, verification_expires_at FROM users WHERE verification_token = :token LIMIT 1"
    );
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch();

    $token_is_valid = $row && strtotime($row['verification_expires_at']) >= time();

    if ($token_is_valid) {
        $update = $conn->prepare(
            "UPDATE users SET is_verified = 1, verification_token = NULL, verification_expires_at = NULL WHERE user_id = :user_id"
        );
        $update->execute([':user_id' => $row['user_id']]);

        header('Location: login.php?verified=1');
        exit;
    }

    header('Location: login.php?verified=0');
    exit;
