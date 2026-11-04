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

if ($_SERVER['REQUEST_METHOD'] === 'POST') 
    {
    $name = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
    $surname = trim(is_string($_POST['surname'] ?? null) ? $_POST['surname'] : '');
    $email = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
    $phone = trim(is_string($_POST['phone'] ?? null) ? $_POST['phone'] : '');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $passwordConfirm = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';

    if ($name === '' || $surname === '' || $email === '' || $phone === '' || $password === '' || $passwordConfirm === '') 
        {
        $errors[] = 'Wypełnij wszystkie wymagane pola.';
        }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) 
        {
        $errors[] = 'Podaj poprawny adres e-mail.';
        }

    if (preg_match_all('/./us', $name) > 50 || preg_match_all('/./us', $surname) > 50 || strlen($email) > 100) 
        {
        $errors[] = 'Jedno z pól przekracza dopuszczalną długość.';
        }

    if ($phone !== '' && !preg_match('/^[0-9]{1,9}$/D', $phone)) 
        {
        $errors[] = 'Podaj poprawny numer telefonu.';
        }

    if ($password !== $passwordConfirm) 
        {
        $errors[] = 'Podane hasła nie są takie same.';
        }

    if (!preg_match('//u', $password)) 
        {
        $errors[] = 'Hasło zawiera nieprawidłowe znaki.';
        } 

    else 
        {
        $characterCount = preg_match_all('/./us', $password);

        if (strlen($password) > 72 || $characterCount > 64) 
            {
            $errors[] = 'Hasło jest zbyt długie.';
            }

        if ($characterCount < 8) 
            {
            $errors[] = 'Hasło musi mieć co najmniej 8 znaków.';
            }

        if (!preg_match('/\p{Ll}/u', $password)) 
            {
            $errors[] = 'Hasło musi zawierać małą literę.';
            }

        if (!preg_match('/\p{Lu}/u', $password)) 
            {
            $errors[] = 'Hasło musi zawierać wielką literę.';
            }

        if (!preg_match('/\p{N}/u', $password)) 
            {
            $errors[] = 'Hasło musi zawierać cyfrę.';
            }
        if (!preg_match('/[^\p{L}\p{N}\s]/u', $password)) 
            {
            $errors[] = 'Hasło musi zawierać znak specjalny (!, @, #, $, %...).';
            }
        }

    if (empty($errors)) 
        {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);

        if ($stmt->fetch()) 
            {
            $errors[] = 'Konto z tym adresem e-mail już istnieje.';
            }
        }

    if (empty($errors)) 
        {
        $stmt = $pdo->prepare(
            'SELECT id FROM users WHERE phone = ? LIMIT 1'
            );

        $stmt->execute([$phone]);

        if ($stmt->fetch()) 
            {
            $errors[] = 'Ten numer telefonu jest już wykorzystany. Użyj innego.';
            }
        }

    if (empty($errors)) 
        {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        try 
            {
            $stmt = $pdo->prepare(
                'INSERT INTO users (name, surname, email, password, phone, role) VALUES (?, ?, ?, ?, ?, ?)'
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

            } catch (PDOException $e) 
                {
                if ($e->getCode() === '23000') 
                    {
                    $errors[] = 'Adres e-mail lub numer telefonu jest już zajęty.';
                    } 
                else 
                    {
                    throw $e;
                    }
                }
        }
    }
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejestracja — Sharp Society Barber</title>
    <link rel="stylesheet" href="css/style-register.css">
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
<script src="js/script-register.js" defer></script>
</body>
</html>
