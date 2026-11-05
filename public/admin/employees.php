<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin('../login.php');
refreshUserSession($pdo, '../login.php');
requireRole('admin');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function employeeEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$errors = [];
$success = $_SESSION['employee_success'] ?? '';
unset($_SESSION['employee_success']);

$editEmployee = null;
$description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Nieprawidłowy token formularza.');
    }

    $action = $_POST['action'] ?? '';

    if (!is_string($action)) {
        $action = '';
    }

    $id = filter_var(
        $_POST['id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($action === 'add') {

        $userId = filter_var(
            $_POST['user_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $description = trim(
            is_string($_POST['description'] ?? null)
                ? $_POST['description']
                : ''
        );

        if (!$userId) {
            $errors[] = 'Wybierz konto pracownika.';
        }

        if (mb_strlen($description) > 5000) {
            $errors[] = 'Opis może mieć maksymalnie 5000 znaków.';
        }

        if (empty($errors)) {

            $stmt = $pdo->prepare(
                "SELECT u.id
                 FROM users u
                 LEFT JOIN employees e ON e.user_id = u.id
                 WHERE u.id = ?
                   AND u.role = 'employee'
                   AND u.active = 1
                   AND e.id IS NULL"
            );

            $stmt->execute([$userId]);

            if (!$stmt->fetchColumn()) {
                $errors[] = 'Konto nie istnieje, jest nieaktywne lub zostało już przypisane.';
            } else {

                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO employees (user_id, description, active)
                         VALUES (?, ?, 1)'
                    );

                    $stmt->execute([$userId, $description]);

                    $_SESSION['employee_success'] = 'Dodano pracownika.';

                    header('Location: employees.php');
                    exit;

                } catch (PDOException $exception) {
                    $errors[] = 'Nie udało się dodać pracownika.';
                }
            }
        }

    } elseif ($action === 'edit') {

        $description = trim(
            is_string($_POST['description'] ?? null)
                ? $_POST['description']
                : ''
        );

        if (!$id) {
            $errors[] = 'Nieprawidłowy identyfikator pracownika.';
        }

        if (mb_strlen($description) > 5000) {
            $errors[] = 'Opis może mieć maksymalnie 5000 znaków.';
        }

        if (empty($errors)) {

            $stmt = $pdo->prepare(
                'UPDATE employees SET description = ? WHERE id = ?'
            );

            $stmt->execute([$description, $id]);

            $_SESSION['employee_success'] = 'Zapisano dane pracownika.';

            header('Location: employees.php');
            exit;
        }

        if ($id) {
            $editEmployee = [
                'id' => $id,
                'description' => $description
            ];
        }

    } elseif ($action === 'toggle') {

        if (!$id) {
            $errors[] = 'Nieprawidłowy identyfikator pracownika.';
        } else {

            $stmt = $pdo->prepare(
                'UPDATE employees
                 SET active = IF(active = 1, 0, 1)
                 WHERE id = ?'
            );

            $stmt->execute([$id]);

            $_SESSION['employee_success'] = $stmt->rowCount()
                ? 'Zmieniono aktywność pracownika.'
                : 'Nie znaleziono pracownika.';

            header('Location: employees.php');
            exit;
        }

    } else {
        $errors[] = 'Nieprawidłowa operacja.';
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit'])) {

    $id = filter_var(
        $_GET['edit'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (!$id) {
        $errors[] = 'Nieprawidłowy identyfikator pracownika.';
    } else {

        $stmt = $pdo->prepare(
            'SELECT id, description FROM employees WHERE id = ?'
        );

        $stmt->execute([$id]);
        $editEmployee = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$editEmployee) {
            $errors[] = 'Nie znaleziono pracownika.';
        }
    }
}

$availableUsers = $pdo->query(
    "SELECT u.id, u.name, u.surname, u.email
     FROM users u
     LEFT JOIN employees e ON e.user_id = u.id
     WHERE u.role = 'employee'
       AND u.active = 1
       AND e.id IS NULL
     ORDER BY u.surname, u.name"
)->fetchAll(PDO::FETCH_ASSOC);

$employees = $pdo->query(
    'SELECT
        e.id,
        e.user_id,
        e.description,
        e.active,
        u.name,
        u.surname,
        u.email,
        u.phone,
        u.active AS account_active,
        COUNT(DISTINCT es.service_id) AS services_count
     FROM employees e
     INNER JOIN users u ON u.id = e.user_id
     LEFT JOIN employee_services es ON es.employee_id = e.id
     GROUP BY
        e.id, e.user_id, e.description, e.active,
        u.name, u.surname, u.email, u.phone, u.active
     ORDER BY u.surname, u.name'
)->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pracownicy — Sharp Society Barber</title>
    <link rel="stylesheet" href="../css/style-employees.css">
    <link rel="stylesheet" href="../css/style-admin-nav.css">
</head>
<body>

    <header class="topbar">
        <h1>Sharp Society Barber</h1>
        <?php require '../../includes/admin_nav.php'; ?>
    </header>

    <main class="container">

        <h2>Zarządzanie pracownikami</h2>

        <?php foreach ($errors as $error): ?>
            <p class="message-error"><?= employeeEscape($error) ?></p>
        <?php endforeach; ?>

        <?php if ($success !== ''): ?>
            <p class="message-success"><?= employeeEscape($success) ?></p>
        <?php endif; ?>

        <section class="card">

            <h3>
                <?= $editEmployee ? 'Edytuj pracownika' : 'Dodaj pracownika' ?>
            </h3>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= employeeEscape($_SESSION['csrf_token']) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="<?= $editEmployee ? 'edit' : 'add' ?>"
                >

                <?php if ($editEmployee): ?>

                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int) $editEmployee['id'] ?>"
                    >

                <?php else: ?>

                    <label for="user_id">Konto pracownika</label>

                    <select id="user_id" name="user_id" required>
                        <option value="">Wybierz konto</option>

                        <?php foreach ($availableUsers as $user): ?>
                            <option
                                value="<?= (int) $user['id'] ?>"
                                <?= (string) ($_POST['user_id'] ?? '') === (string) $user['id'] ? 'selected' : '' ?>
                            >
                                <?= employeeEscape($user['name'] . ' ' . $user['surname']) ?>
                                (<?= employeeEscape($user['email']) ?>)
                            </option>
                        <?php endforeach; ?>

                    </select>

                    <?php if (empty($availableUsers)): ?>
                        <p class="hint">
                            Brak wolnych aktywnych kont z rolą pracownika.
                            Istniejące przypisania widoczne są poniżej.
                        </p>
                    <?php endif; ?>

                <?php endif; ?>

                <label for="description">Opis pracownika</label>

                <textarea
                    id="description"
                    name="description"
                    rows="4"
                    maxlength="5000"
                ><?= employeeEscape($editEmployee['description'] ?? $description) ?></textarea>

                <button type="submit">
                    <?= $editEmployee ? 'Zapisz zmiany' : 'Dodaj pracownika' ?>
                </button>

                <?php if ($editEmployee): ?>
                    <a href="employees.php" class="cancel-link">
                        Anuluj edycję
                    </a>
                <?php endif; ?>

            </form>

        </section>

        <section class="card">

            <h3>Lista pracowników (<?= count($employees) ?>)</h3>

            <div class="table-wrapper">

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Pracownik</th>
                            <th>E-mail</th>
                            <th>Telefon</th>
                            <th>Opis</th>
                            <th>Usługi</th>
                            <th>Status</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($employees as $employee): ?>

                            <tr id="employee-<?= (int) $employee['id'] ?>">

                                <td><?= (int) $employee['id'] ?></td>

                                <td>
                                    <?= employeeEscape(
                                        $employee['name'] . ' ' . $employee['surname']
                                    ) ?>
                                </td>

                                <td><?= employeeEscape($employee['email']) ?></td>
                                <td><?= employeeEscape($employee['phone']) ?></td>

                                <td><?= employeeEscape($employee['description'] ?? '') ?></td>

                                <td><?= (int) $employee['services_count'] ?></td>

                                <td>
                                    <?php if ((int) $employee['account_active'] !== 1): ?>
                                        <span class="inactive">Konto nieaktywne</span>
                                    <?php elseif ((int) $employee['active'] === 1): ?>
                                        <span class="active">Aktywny</span>
                                    <?php else: ?>
                                        <span class="inactive">Nieaktywny</span>
                                    <?php endif; ?>
                                </td>

                                <td class="actions">

                                    <a href="?edit=<?= (int) $employee['id'] ?>">
                                        Edytuj
                                    </a>

                                    <form method="POST">

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= employeeEscape($_SESSION['csrf_token']) ?>"
                                        >

                                        <input type="hidden" name="action" value="toggle">

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $employee['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="toggle-btn"
                                            onclick="return confirm('Czy zmienić aktywność pracownika?');"
                                        >
                                            <?= (int) $employee['active'] === 1
                                                ? 'Dezaktywuj'
                                                : 'Aktywuj' ?>
                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="8">Brak pracowników.</td>
                            </tr>
                        <?php endif; ?>

                    </tbody>
                </table>

            </div>

        </section>

    </main>

</body>
</html>