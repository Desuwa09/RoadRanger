<?php
require_once __DIR__ . '/../db/db_con.php';
session_start();

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $now = date('Y-m-d H:i:s');
    $conn = db_connect();

    try {
        $query = "SELECT * FROM users WHERE login_token = :token AND token_expires_at > :now";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':token' => $token,
            ':now'   => $now
        ]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['is_admin'] = (int)$user['is_admin'];

            $clear_query = "UPDATE users SET login_token = NULL, token_expires_at = NULL WHERE user_id = :user_id";
            $clear_stmt = $conn->prepare($clear_query);
            $clear_stmt->execute([':user_id' => $user['user_id']]);
            if ($_SESSION['is_admin'] === 1) {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: users/dashboard.php');
            }
            exit;
        } else {
            die("Invalid or expired login link. Please go back and request a new one.");
        }
    } catch (PDOException $e) {
        die("Authentication processing error: " . $e->getMessage());
    }
} else {
    die("Authentication token parameter missing.");
}
?>