<?php

function getUserProfile(PDO $pdo, int $userId): array|false
{
    $stmt = $pdo->prepare(
        "SELECT id, name, surname, email, phone
         FROM users
         WHERE id = ?"
    );

    $stmt->execute([$userId]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}


function updateUserProfile(
    PDO $pdo,
    int $userId,
    string $name,
    string $surname,
    string $email,
    string $phone
): array {
    $name = trim($name);
    $surname = trim($surname);
    $email = trim($email);
    $phone = trim($phone);

    $errors = [];

    if ($name === '' || $surname === '' || $email === '') {
        $errors[] = 'Imię, nazwisko i e-mail są wymagane.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Podaj poprawny adres e-mail.';
    }

    if (strlen($name) > 100 || strlen($surname) > 100) {
        $errors[] = 'Imię i nazwisko mogą mieć maksymalnie 100 znaków.';
    }

    if (strlen($email) > 255 || strlen($phone) > 20) {
        $errors[] = 'E-mail lub numer telefonu jest za długi.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    $stmt = $pdo->prepare(
        "SELECT id FROM users
         WHERE email = ? AND id != ?"
    );

    $stmt->execute([$email, $userId]);

    if ($stmt->fetch()) {
        return [
            'success' => false,
            'errors' => ['Ten adres e-mail jest już zajęty.']
        ];
    }

    $stmt = $pdo->prepare(
        "UPDATE users
         SET name = ?, surname = ?, email = ?, phone = ?
         WHERE id = ?"
    );

    $stmt->execute([
        $name,
        $surname,
        $email,
        $phone,
        $userId
    ]);

    return ['success' => true, 'errors' => []];
}
