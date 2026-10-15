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
    string $phone
): array {
    $name = trim($name);
    $surname = trim($surname);
    $phone = trim($phone);

    $errors = [];

    if ($name === '' || $surname === '') {
        $errors[] = 'Imię i nazwisko są wymagane.';
    }

    if (mb_strlen($name) > 50 || mb_strlen($surname) > 50) {
        $errors[] = 'Imię i nazwisko mogą mieć maksymalnie 50 znaków.';
    }

    if (!preg_match('/^[0-9]{1,9}$/D', $phone)) {
        $errors[] = 'Numer telefonu musi zawierać wyłącznie cyfry (maksymalnie 9).';
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    $stmt = $pdo->prepare(
        "SELECT id
         FROM users
         WHERE phone = ? AND id != ?
         LIMIT 1"
    );

    $stmt->execute([$phone, $userId]);

    if ($stmt->fetch()) {
        return [
            'success' => false,
            'errors' => [
                'Ten numer telefonu jest już wykorzystany. Użyj innego.'
            ]
        ];
    }

    try {
        $stmt = $pdo->prepare(
            "UPDATE users
             SET name = ?, surname = ?, phone = ?
             WHERE id = ?"
        );

        $stmt->execute([
            $name,
            $surname,
            $phone,
            $userId
        ]);

    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return [
                'success' => false,
                'errors' => [
                    'Ten numer telefonu jest już przypisany do innego konta.'
                ]
            ];
        }

        throw $e;
    }

    return ['success' => true, 'errors' => []];
}
