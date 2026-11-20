
<?php

// Sesja użytkownika, kontrola logowania i uprawnień.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sprawdzenie identyfikatora użytkownika w sesji.
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id'])
        && filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) !== false
        && (int) $_SESSION['user_id'] > 0;
}

// Wymuszenie logowania przed otwarciem strony.
function requireLogin(string $loginPath = 'login.php'): void
{
    if (!isLoggedIn()) {
        header('Location: ' . $loginPath);
        exit;
    }
}

// Odczyt identyfikatora zalogowanego użytkownika.
function getUserId(): ?int
{
    if (!isLoggedIn()) {
        return null;
    }

    return (int) $_SESSION['user_id'];
}

// Odczyt aktualnej roli z sesji.
function getUserRole(): ?string
{
    if (!isLoggedIn()) {
        return null;
    }

    return $_SESSION['user_role'] ?? null;
}

// Ograniczenie dostępu do wymaganej roli.
function requireRole(string $role): void
{
    requireLogin();

    if (getUserRole() !== $role) {
        http_response_code(403);
        exit('Nie masz uprawnień do tej strony.');
    }
}

// Synchronizacja sesji z aktywnym kontem w bazie.
function refreshUserSession(PDO $pdo, string $loginPath = 'login.php'): void
{
    requireLogin($loginPath);

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

        header('Location: ' . $loginPath);
        exit;
    }

    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
}
