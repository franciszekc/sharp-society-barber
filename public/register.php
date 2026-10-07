<?php

require_once '../config/database.php';

$errors = [];
$success = '';
$name = '';
$surname = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if (
        $name === '' ||
        $surname === '' ||
        $email === '' ||
        $phone === '' ||
        $password === '' ||
        $passwordConfirm === ''
    ) {
        $errors[] = 'Wypełnij wszystkie wymagane pola.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Podaj poprawny adres e-mail.';
    }

    if ($password !== $passwordConfirm) {
        $errors[] = 'Podane hasła nie są takie same.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Hasło musi mieć co najmniej 8 znaków.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'SELECT id FROM users WHERE email = ?'
        );

        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $errors[] = 'Konto z tym adresem e-mail już istnieje.';
        }
    }

    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            'INSERT INTO users
            (name, surname, email, password, phone, role)
            VALUES (?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $name,
            $surname,
            $email,
            $passwordHash,
            $phone,
            'client'
        ]);

        $success = 'Konto zostało utworzone.';
        $name = '';
        $surname = '';
        $email = '';
        $phone = '';
    }
}

?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Rejestracja - Sharp Society Barber</title>
</head>
<body>

    <h1>Rejestracja</h1>

    <?php foreach ($errors as $error): ?>
        <p>
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endforeach; ?>

    <?php if ($success !== ''): ?>
        <p>
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="POST">
        <label for="name">Imię:</label>
        <input
            type="text"
            id="name"
            name="name"
            value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
        >

        <label for="surname">Nazwisko:</label>
        <input
            type="text"
            id="surname"
            name="surname"
            value="<?= htmlspecialchars($surname, ENT_QUOTES, 'UTF-8') ?>"
        >

        <label for="email">E-mail:</label>
        <input
            type="email"
            id="email"
            name="email"
            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
        >

        <label for="phone">Telefon:</label>
        <input
            type="text"
            id="phone"
            name="phone"
            value="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>"
        >

        <label for="password">Hasło:</label>
        <input type="password" id="password" name="password">

        <label for="password_confirm">Powtórz hasło:</label>
        <input type="password" id="password_confirm" name="password_confirm">

        <button type="submit">Zarejestruj się</button>
    </form>

</body>
</html>