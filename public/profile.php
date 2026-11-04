<?php

require_once '../config/database.php';
require_once '../includes/profile_functions.php';

require_once '../includes/auth.php';

requireLogin();
refreshUserSession($pdo);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = getUserId();

$user = getUserProfile($pdo, $userId);

if ($user === false) {
    http_response_code(404);
    exit('Nie znaleziono użytkownika.');
}

$errors = [];
$success = '';

$name = $user['name'];
$surname = $user['surname'];
$email = $user['email'];
$phone = $user['phone'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
    !isset($_POST['csrf_token']) ||
    !is_string($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    exit('Nieprawidłowy token formularza.');
}
    $name = $_POST['name'] ?? '';
    $surname = $_POST['surname'] ?? '';
    $phone = $_POST['phone'] ?? '';

    $result = updateUserProfile(
        $pdo,
        $userId,
        $name,
        $surname,
        $phone
    );

    if ($result['success']) {
        $success = 'Dane profilu zostały zaktualizowane.';

        $user = getUserProfile($pdo, $userId);

        $name = $user['name'];
        $surname = $user['surname'];
        $email = $user['email'];
        $phone = $user['phone'] ?? '';
    } else {
        $errors = $result['errors'];
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mój profil — Sharp Society Barber</title>
    <link rel="stylesheet" href="css/style-profile.css">
</head>
<body>

<div class="container">
    <h1>Mój profil</h1>

    <?php if ($success !== ''): ?>
        <p class="success"><?= e($success) ?></p>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endforeach; ?>

    <form method="POST">
    <input
        type="hidden"
        name="csrf_token"
        value="<?= e($_SESSION['csrf_token']) ?>"
    >

        <label for="name">Imię</label>
        <input
            type="text"
            id="name"
            name="name"
            value="<?= e($name) ?>"
            required
        >

        <label for="surname">Nazwisko</label>
        <input
            type="text"
            id="surname"
            name="surname"
            value="<?= e($surname) ?>"
            required
        >

        <label for="email">E-mail</label>
        <input
            type="email"
            id="email"
            readonly
            value="<?= e($email) ?>"
            required
        >

        <label for="phone">Telefon</label>
        <input
            type="tel"
            id="phone"
            name="phone"
            maxlength="9"
            inputmode="numeric"
            pattern="[0-9]{1,9}"
            title="Wpisz maksymalnie 9 cyfr"
            required
            value="<?= e($phone) ?>"
        >

        <button type="submit">Zapisz zmiany</button>

    </form>

    <a href="chgpasswd.php" class="change-password-btn">Zmień hasło</a>

    <form action="logout.php" method="POST">
    <input
        type="hidden"
        name="csrf_token"
        value="<?= e($_SESSION['csrf_token']) ?>"
    >

    <button type="submit">Wyloguj się</button>
    
</form>
</div>

<script src="js/script-profile.js" defer></script>
</body>
</html>