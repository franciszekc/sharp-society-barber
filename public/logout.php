<?php

session_start();

// Usunięcie wszystkich danych zapisanych w sesji
$_SESSION = [];

// Usunięcie ciasteczek sesji
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Zakończenie sesji po stronie serwera
session_destroy();

// Powrót do strony logowania
header('Location: login.php');
exit;
