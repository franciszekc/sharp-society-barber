<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin('../login.php');
refreshUserSession($pdo, '../login.php');
requireRole('admin');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function employeeServicesEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$errors = [];
$success = $_SESSION['employee_services_success'] ?? '';
unset($_SESSION['employee_services_success']);

$employees = $pdo->query(
    'SELECT
        e.id,
        e.active,
        u.name,
        u.surname,
        u.active AS account_active
     FROM employees e
     INNER JOIN users u ON u.id = e.user_id
     ORDER BY u.surname, u.name'
)->fetchAll(PDO::FETCH_ASSOC);

$services = $pdo->query(
    'SELECT
        s.id,
        s.name,
        s.duration,
        s.price,
        s.active,
        c.name AS category_name
     FROM services s
     INNER JOIN service_categories c ON c.id = s.category_id
     ORDER BY c.name, s.name'
)->fetchAll(PDO::FETCH_ASSOC);

$employeeIds = [];
$serviceIds = [];

foreach ($employees as $employee) {
    $employeeIds[(int) $employee['id']] = true;
}

foreach ($services as $service) {
    $serviceIds[(int) $service['id']] = true;
}

$selectedId = filter_var(
    $_POST['employee_id'] ?? $_GET['employee_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$selectedId && !isset($_POST['employee_id']) && !isset($_GET['employee_id'])) {
    $selectedId = !empty($employees) ? (int) $employees[0]['id'] : null;
}

if ($selectedId && !isset($employeeIds[$selectedId])) {
    $errors[] = 'Nie znaleziono wybranego pracownika.';
    $selectedId = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Nieprawidłowy token formularza.');
    }

    if (!$selectedId) {
        $errors[] = 'Wybierz poprawnego pracownika.';
    }

    $submittedServices = $_POST['service_ids'] ?? [];

    if (!is_array($submittedServices)) {
        $errors[] = 'Nieprawidłowe dane usług.';
        $submittedServices = [];
    }

    $selectedServices = [];

    foreach ($submittedServices as $serviceId) {

        if (!is_string($serviceId) || !ctype_digit($serviceId)) {
            $errors[] = 'Nieprawidłowy identyfikator usługi.';
            break;
        }

        $serviceId = (int) $serviceId;

        if ($serviceId < 1 || !isset($serviceIds[$serviceId])) {
            $errors[] = 'Wybrano nieistniejącą usługę.';
            break;
        }

        $selectedServices[$serviceId] = true;
    }

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'DELETE FROM employee_services WHERE employee_id = ?'
            );

            $stmt->execute([$selectedId]);

            $stmt = $pdo->prepare(
                'INSERT INTO employee_services (employee_id, service_id)
                 VALUES (?, ?)'
            );

            foreach (array_keys($selectedServices) as $serviceId) {
                $stmt->execute([$selectedId, $serviceId]);
            }

            $pdo->commit();

            $_SESSION['employee_services_success'] =
                'Zapisano usługi przypisane do pracownika.';

            header('Location: employee_services.php?employee_id=' . $selectedId);
            exit;

        } catch (Throwable $exception) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = 'Nie udało się zapisać przypisanych usług.';
        }
    }
}

$assignedIds = [];

if ($selectedId) {

    $stmt = $pdo->prepare(
        'SELECT service_id
         FROM employee_services
         WHERE employee_id = ?'
    );

    $stmt->execute([$selectedId]);

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $serviceId) {
        $assignedIds[(int) $serviceId] = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($errors)) {

    $assignedIds = [];

    foreach ($selectedServices ?? [] as $serviceId => $unused) {
        $assignedIds[(int) $serviceId] = true;
    }
}

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Usługi pracowników — Sharp Society Barber</title>

    <link rel="stylesheet" href="../css/style-employees.css">
    <link rel="stylesheet" href="../css/style-employee-services.css">
    <link rel="stylesheet" href="../css/style-admin-nav.css">
</head>
<body>

    <header class="topbar">
        <h1>Sharp Society Barber</h1>
        <?php require '../../includes/admin_nav.php'; ?>
    </header>

    <main class="container">

        <h2>Przypisywanie usług pracownikom</h2>

        <p>
            <a href="employees.php">Powrót do zarządzania pracownikami</a>
        </p>

        <?php foreach ($errors as $error): ?>
            <p class="message-error">
                <?= employeeServicesEscape($error) ?>
            </p>
        <?php endforeach; ?>

        <?php if ($success !== ''): ?>
            <p class="message-success">
                <?= employeeServicesEscape($success) ?>
            </p>
        <?php endif; ?>

        <section class="card">

            <h3>Wybierz pracownika</h3>

            <?php if (empty($employees)): ?>

                <p>
                    Brak pracowników.
                    Najpierw dodaj pracownika w panelu administratora.
                </p>

            <?php else: ?>

                <form method="GET" class="employee-selector">

                    <label for="employee_id">Pracownik</label>

                    <select id="employee_id" name="employee_id" required>

                        <?php foreach ($employees as $employee): ?>

                            <option
                                value="<?= (int) $employee['id'] ?>"
                                <?= (int) $employee['id'] === (int) $selectedId ? 'selected' : '' ?>
                            >
                                <?= employeeServicesEscape(
                                    $employee['name'] . ' ' . $employee['surname']
                                ) ?>

                                <?= (int) $employee['active'] === 0
                                    || (int) $employee['account_active'] === 0
                                    ? '(nieaktywny)'
                                    : '' ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <button type="submit">Pokaż usługi</button>

                </form>

            <?php endif; ?>

        </section>

        <?php if ($selectedId): ?>

            <section class="card">

                <h3>Usługi przypisane do pracownika</h3>

                <p class="hint">
                    Zaznacz usługi, które pracownik może wykonywać.
                    Odznaczenie usługi usuwa jej przypisanie.
                </p>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= employeeServicesEscape($_SESSION['csrf_token']) ?>"
                    >

                    <input
                        type="hidden"
                        name="employee_id"
                        value="<?= (int) $selectedId ?>"
                    >

                    <?php if (empty($services)): ?>

                        <p>Brak usług w bazie danych.</p>

                    <?php else: ?>

                        <div class="services-grid">

                            <?php foreach ($services as $service): ?>

                                <label class="service-item">

                                    <input
                                        type="checkbox"
                                        name="service_ids[]"
                                        value="<?= (int) $service['id'] ?>"
                                        <?= isset($assignedIds[(int) $service['id']])
                                            ? 'checked'
                                            : '' ?>
                                    >

                                    <span class="service-details">

                                        <strong>
                                            <?= employeeServicesEscape($service['name']) ?>
                                        </strong>

                                        <span class="service-meta">
                                            <?= employeeServicesEscape($service['category_name']) ?>
                                            |
                                            <?= (int) $service['duration'] ?> min
                                            |
                                            <?= number_format((float) $service['price'], 2, ',', ' ') ?> zł
                                        </span>

                                        <?php if ((int) $service['active'] === 0): ?>
                                            <span class="inactive">
                                                Usługa nieaktywna
                                            </span>
                                        <?php endif; ?>

                                    </span>

                                </label>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                    <div class="save-row">
                        <button type="submit">Zapisz przypisane usługi</button>
                    </div>

                </form>

            </section>

        <?php endif; ?>

    </main>

</body>
</html>