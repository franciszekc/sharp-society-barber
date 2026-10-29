<?php

require_once '../config/database.php';
require_once '../includes/auth.php';

requireLogin();
refreshUserSession($pdo);

$errors = [];
$success = '';
$userId = getUserId();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$stmt = $pdo->prepare(
    'SELECT password FROM users WHERE id = ? LIMIT 1'
);

$stmt->execute([$userId]);

$user = $stmt->fetch();

if($user === false) {
    http_response_code(404);
    exit('Nie znaleziono użytkownika.');
}

$currentPasswordHash = $user['password'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !is_string($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit('Nieprawidłowy token formularza.');
    }

    $oldPassword = is_string($_POST['old_password'] ?? null)
        ? $_POST['old_password']
        : '';

    $newPassword = is_string($_POST['new_password'] ?? null)
        ? $_POST['new_password']
        : '';

    $confirmPassword = is_string($_POST['confirm_password'] ?? null)
        ? $_POST['confirm_password']
        : '';
    
    if (
        $oldPassword === '' ||
        $newPassword === '' ||
        $confirmPassword === ''
    ) {
        $errors[] = 'Wypełnij wszystkie wymagane pola.';
    }

    if (
        $oldPassword !== '' &&
        !password_verify($oldPassword, $currentPasswordHash)
    ) {
        $errors[] = 'Aktualne hasło jest nieprawidłowe.';
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = 'Podane hasła nie są takie same.';
    }

    if (
        $newPassword !== '' &&
        password_verify($newPassword, $currentPasswordHash)
    ) {
        $errors[] = 'Nowe hasło musi różnić się od aktualnego.';
    }

    if (!preg_match('//u', $newPassword)) {
        $errors[] = 'Hasło zawiera nieprawidłowe znaki.';
    } else {

        $characterCount = preg_match_all('/./us', $newPassword);

        if (
            strlen($newPassword) > 72 || 
            $characterCount > 64
        ) {
            $errors[] = 'Hasło jest zbyt długie.';
        }

        if ($characterCount < 8) {
            $errors[] = 'Hasło musi mieć co najmniej 8 znaków.';
        }

        if (!preg_match('/\p{Ll}/u', $newPassword)) {
            $errors[] = 'Hasło musi zawierać małą literę.';
        }

        if (!preg_match('/\p{Lu}/u', $newPassword)) {
            $errors[] = 'Hasło musi zawierać wielką literę';
        }

        if (!preg_match('/\p{N}/u', $newPassword)) {
            $errors[] = 'Hasło musi zawierać cyfrę.';
        }

        if (!preg_match('/[^\p{L}\p{N}\s]/u', $newPassword)) {
            $errors[] = 'Hasło musi zawierać znak specjalny (!, @, #, $, %...).';
        }
    }

    if (empty($errors)) {
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'UPDATE users SET password = ? WHERE id = ? AND password = ?'
        );

        $stmt->execute([
            $newPasswordHash,
            $userId,
            $currentPasswordHash
        ]);

        if ($stmt->rowCount() === 1) {
            $success = 'Hasło zostało zmienione pomyślnie.';
        } else {
            $errors[] = 'Nie udało się zmienić hasła. Spróbuj ponownie.';
        }
    }


}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zmiana Hasła</title>
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
            margin-top: 0;
        }
        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input {
            box-sizing: border-box;
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font: inherit;
        }

        input:focus {
            outline: 2px solid #555;
            outline-offset: 1px;
        }

        button,
        .back-link {
            width: 100%;
            padding: 12px;
            margin-top: 25px;
            background: #222;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            box-sizing: border-box;
            display: block;
            text-align: center;
            text-decoration: none;
            font: inherit;
        }

        button:hover,
        .back-link:hover {
            background-color: #555;
        }

        .message-error {
            color: #a00000;
            text-align: center;
        }

        .message-success {
            color: #15803d;
            text-align: center;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .requirements {
            padding-left: 23px;
            margin: 10px 0 17px;
        }

        .requirements li {
            color: #727272;
            margin: 6px 0;
            transition: color .15s;
        }

        .requirements li.met {
            color: #15803d;
        }

        .password-match {
            color: #727272;
            margin: 8px 0 12px;
            transition: color .15s;
        }

        .password-match.met {
            color: #15803d;
        }

    </style>
</head>
<body>
    
    <main class="container">

        <h1>Zmiana hasła</h1>

        <?php foreach ($errors as $error): ?>
            <p class="message-error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endforeach; ?>

        <?php if ($success !== ''): ?>
            <p class="message-success">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
            >

            <label for="old_password">Aktualne hasło:</label>
            <input
                type="password"
                id="old_password"
                name="old_password"
                required
                autocomplete="current-password"
            >

            <label for="new_password">Nowe hasło:</label>
            <input
                type="password"
                id="new_password"
                name="new_password"
                required
                maxlength="64"
                autocomplete="new-password"
                aria-describedby="password-requirements"
            >
            <ul  id="password-requirements" class="requirements">
                <li data-rule="length">Co najmniej 8 znaków</li>
                <li data-rule="lower">Co najmniej jedna mała litera</li>
                <li data-rule="upper">Co najmniej jedna wielka litera</li>
                <li data-rule="digit">Co najmniej jedna cyfra</li>
                <li data-rule="special">Co najmniej jeden znak specjalny (!, @, #, $, %...)</li>
            </ul>

            <label for="confirm_password">Powtórz nowe hasło:</label>
            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                required
                autocomplete="new-password"
                aria-describedby="password-match"
            >
            <p id="password-match" class="password-match" aria-live="polite">
                ● Hasła muszą być identyczne
            </p>

            <button type="submit">Zmień hasło</button>

        </form>
        
        <a href="profile.php" class="back-link">Powrót do profilu</a>
        
    </main>

<script>
    const passwordInput = document.getElementById('new_password');
    const confirmInput = document.getElementById('confirm_password');
    const matchIndicator = document.getElementById('password-match');
    const rules = {
        length: value => [...value].length >= 8,
        lower: value => /\p{Ll}/u.test(value),
        upper: value => /\p{Lu}/u.test(value),
        digit: value => /\p{N}/u.test(value),
        special: value => /[^\p{L}\p{N}\s]/u.test(value)
    };

    function updatePasswordRequirements() {
        
        const value = passwordInput.value;
        
        document.querySelectorAll('#password-requirements li').forEach(item => {

            const ruleName = item.dataset.rule;
            const isValid = rules[ruleName](value);

            item.classList.toggle('met', isValid);
        });
    }

    function updatePasswordMatch() {
        
        const matches = 
            confirmInput.value !== '' &&
            passwordInput.value === confirmInput.value;

        matchIndicator.classList.toggle('met', matches);

        matchIndicator.textContent = matches
            ? '● Hasła są identyczne'
            : '● Hasła muszą być identyczne';
    }

    passwordInput.addEventListener('input', () => {
        updatePasswordRequirements();
        updatePasswordMatch();
    });

    confirmInput.addEventListener('input', updatePasswordMatch);

    updatePasswordRequirements();
    updatePasswordMatch();
</script>

</body>
</html>

