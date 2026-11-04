<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin('../login.php');
refreshUserSession($pdo, '../login.php');
requireRole('client');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$firstName = htmlspecialchars(
    $_SESSION['user_name'],
    ENT_QUOTES,
    'UTF-8'
);

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel klienta — Sharp Society Barber</title>
    <link rel="stylesheet" href="../css/style-client.css">
</head>
<body>

    <main class="container">

        <h1>Sharp Society Barber</h1>

        <h2>Witaj, <?= $firstName ?>!</h2>

        <p>Wybierz, co chcesz zrobić.</p>

        <div class="menu">
            <a href="../profile.php">Mój profil</a>
        </div>

        <h3>Moje rezerwacje</h3>

        <p>Moduł rezerwacji będzie dostępny w kolejnym etapie.</p>

        <form action="../logout.php" method="POST">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
            >

            <button type="submit">Wyloguj się</button>
        </form>

    </main>

</body>
</html>
