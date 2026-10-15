<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id'])
        && filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) !== false
        && (int) $_SESSION['user_id'] > 0;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function getUserId(): ?int
{
    if (!isLoggedIn()) {
        return null;
    }

    return (int) $_SESSION['user_id'];
}

function getUserRole(): ?string
{
    if (!isLoggedIn()) {
        return null;
    }

    return $_SESSION['user_role'] ?? null;
}

function requireRole(string $role): void
{
    requireLogin();

    if (getUserRole() !== $role) {
        http_response_code(403);
        exit('Nie masz uprawnień do tej strony.');
    }
}

function refreshUserSession(PDO $pdo): void
{
    requireLogin();

    $stmt = $pdo->prepare(
        "SELECT id, name, role, active
         FROM users
         WHERE id = ?
         LIMIT 1"
    );

    $stmt->execute([getUserId()]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || (int) $user['active'] !== 1) {
        $_SESSION = [];
        session_regenerate_id(true);

        header('Location: login.php');
        exit;
    }

    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
}
