<?php

require_once '../config/database.php';
require_once '../includes/profile_functions.php';

session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Musisz się zalogować, aby zobaczyć profil.');
}

$userId = (int) $_SESSION['user_id'];

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
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';

    $result = updateUserProfile(
        $pdo,
        $userId,
        $name,
        $surname,
        $email,
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
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 40px 15px;
        }

        .container {
            max-width: 500px;
            margin: 0 auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input {
            box-sizing: border-box;
            width: 100%;
            padding: 12px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            width: 100%;
            padding: 12px;
            margin-top: 25px;
            background: #222;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .error {
            color: #a00000;
            margin-bottom: 10px;
        }

        .success {
            color: #087a20;
            margin-bottom: 10px;
        }
    </style>
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
            name="email"
            value="<?= e($email) ?>"
            required
        >

        <label for="phone">Telefon</label>
        <input
            type="tel"
            id="phone"
            name="phone"
            value="<?= e($phone) ?>"
        >

        <button type="submit">Zapisz zmiany</button>

    </form>
</div>

</body>
</html>