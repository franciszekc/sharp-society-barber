
<?php

require_once '../config/database.php';

session_start();

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Wypełnij wszystkie pola.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Podaj poprawny adres e-mail.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, name, email, password, role, active
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            $user &&
            (int) $user['active'] === 1 &&
            password_verify($password, $user['password'])
        ) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            header('Location: profile.php');
            exit;
        } else {
            $error = 'Nieprawidłowy e-mail lub hasło.';
        }
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
    <title>Logowanie — Sharp Society Barber</title>
    <link rel="stylesheet" href="css/style-login.css">
</head>
<body>

<div class="container">
    <h1>Logowanie</h1>

    <?php if ($error !== ''): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="POST">

        <label for="email">E-mail</label>
        <input
            type="email"
            id="email"
            name="email"
            value="<?= e($email) ?>"
            autocomplete="email"
            required
        >

        <label for="password">Hasło</label>
        <input
            type="password"
            id="password"
            name="password"
            autocomplete="current-password"
            required
        >

        <button type="submit">Zaloguj się</button>

    </form>

    <div class="register-link">
        Nie masz konta?
        <a href="register.php">Zarejestruj się</a>
    </div>
</div>

</body>
</html>
