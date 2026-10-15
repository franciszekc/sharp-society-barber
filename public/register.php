<?php

require_once '../config/database.php';

$errors = [];
$success = '';
$name = '';
$surname = '';
$email = '';
$phone = '';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
    $surname = trim(is_string($_POST['surname'] ?? null) ? $_POST['surname'] : '');
    $email = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
    $phone = trim(is_string($_POST['phone'] ?? null) ? $_POST['phone'] : '');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $passwordConfirm = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';

    if ($name === '' || $surname === '' || $email === '' || $phone === '' ||
        $password === '' || $passwordConfirm === '') {
        $errors[] = 'Wypełnij wszystkie wymagane pola.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Podaj poprawny adres e-mail.';
    }

    if (preg_match_all('/./us', $name) > 50 ||
        preg_match_all('/./us', $surname) > 50 ||
        strlen($email) > 100) {
        $errors[] = 'Jedno z pól przekracza dopuszczalną długość.';
    }

    if ($phone !== '' && !preg_match('/^[0-9]{1,9}$/D', $phone)) {
        $errors[] = 'Podaj poprawny numer telefonu.';
    }

    if ($password !== $passwordConfirm) {
        $errors[] = 'Podane hasła nie są takie same.';
    }

    if (!preg_match('//u', $password)) {
        $errors[] = 'Hasło zawiera nieprawidłowe znaki.';
    } else {
        $characterCount = preg_match_all('/./us', $password);

        if (strlen($password) > 72 || $characterCount > 64) {
            $errors[] = 'Hasło jest zbyt długie.';
        }
        if ($characterCount < 8) {
            $errors[] = 'Hasło musi mieć co najmniej 8 znaków.';
        }
        if (!preg_match('/\p{Ll}/u', $password)) {
            $errors[] = 'Hasło musi zawierać małą literę.';
        }
        if (!preg_match('/\p{Lu}/u', $password)) {
            $errors[] = 'Hasło musi zawierać wielką literę.';
        }
        if (!preg_match('/\p{N}/u', $password)) {
            $errors[] = 'Hasło musi zawierać cyfrę.';
        }
        if (!preg_match('/[^\p{L}\p{N}\s]/u', $password)) {
            $errors[] = 'Hasło musi zawierać znak specjalny (!, @, #, $, %...).';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $errors[] = 'Konto z tym adresem e-mail już istnieje.';
        }
    }

    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            'INSERT INTO users (name, surname, email, password, phone, role)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([$name, $surname, $email, $passwordHash, $phone, 'client']);

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejestracja — Sharp Society Barber</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 32px 16px; background: #f4f4f4; }
        .container { max-width: 470px; margin: 0 auto; padding: 28px; background: #fff; border-radius: 10px; box-shadow: 0 4px 16px #00000014; }
        h1 { margin-top: 0; text-align: center; }
        label { display: block; margin-top: 15px; margin-bottom: 5px; font-weight: 600; }
        input { box-sizing: border-box; width: 100%; padding: 11px; border: 1px solid #bbb; border-radius: 5px; font: inherit; }
        input:focus { outline: 2px solid #555; outline-offset: 1px; }
        .requirements { padding-left: 23px; margin: 10px 0 17px; }
        .requirements li { color: #727272; margin: 6px 0; transition: color .15s; }
        .requirements li.met { color: #15803d; }
        .password-match { color: #727272; margin: 8px 0 12px; transition: color .15s; }
        .password-match.met { color: #15803d; }
        .message-error { color: #a00000; }
        .message-success { color: #15803d; }
        button { width: 100%; margin-top: 20px; padding: 12px; border: 0; border-radius: 5px; background: #222; color: white; cursor: pointer; font: inherit; }
        .login-link { text-align: center; margin-top: 18px; }
    </style>
</head>
<body>
<main class="container">
    <h1>Rejestracja</h1>

    <?php foreach ($errors as $error): ?>
        <p class="message-error"><?= escape($error) ?></p>
    <?php endforeach; ?>

    <?php if ($success !== ''): ?>
        <p class="message-success"><?= escape($success) ?></p>
    <?php endif; ?>

    <form method="POST">
        <label for="name">Imię:</label>
        <input type="text" id="name" name="name" maxlength="50" value="<?= escape($name) ?>" required autocomplete="given-name">

        <label for="surname">Nazwisko:</label>
        <input type="text" id="surname" name="surname" maxlength="50" value="<?= escape($surname) ?>" required autocomplete="family-name">

        <label for="email">E-mail:</label>
        <input type="email" id="email" name="email" maxlength="100" value="<?= escape($email) ?>" required autocomplete="email">

        <label for="phone">Telefon:</label>
        <input type="tel" id="phone" name="phone" maxlength="9" inputmode="numeric" pattern="[0-9]{1,9}" value="<?= escape($phone) ?>" required autocomplete="tel">

        <label for="password">Hasło:</label>
        <input type="password" id="password" name="password" required autocomplete="new-password" aria-describedby="password-requirements" maxlength="64">

        <ul id="password-requirements" class="requirements" aria-label="Wymagania hasła">
            <li data-rule="length">Co najmniej 8 znaków</li>
            <li data-rule="lower">Co najmniej jedna mała litera</li>
            <li data-rule="upper">Co najmniej jedna wielka litera</li>
            <li data-rule="digit">Co najmniej jedna cyfra</li>
            <li data-rule="special">Co najmniej jeden znak specjalny (!, @, #, $, %...)</li>
        </ul>

        <label for="password_confirm">Powtórz hasło:</label>
        <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password" aria-describedby="password-match">
        <p id="password-match" class="password-match" aria-live="polite">● Hasła muszą być identyczne</p>

        <button type="submit">Zarejestruj się</button>
    </form>
    <p class="login-link">Masz już konto? 
        <a href="login.php">Zaloguj się</a></p>
</main>
<script>
    const phoneInput = document.getElementById('phone');
    phoneInput.addEventListener('input', () => {
        phoneInput.value = phoneInput.value.replace(/\D/g, '').slice(0, 9);
    });

    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('password_confirm');
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
            item.classList.toggle('met', rules[item.dataset.rule](value));
        });
    }

    function updatePasswordMatch() {
        const matches = confirmInput.value !== '' && passwordInput.value === confirmInput.value;
        matchIndicator.classList.toggle('met', matches);
        matchIndicator.textContent = matches ? '● Hasła są identyczne' : '● Hasła muszą być identyczne';
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
