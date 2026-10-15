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
