<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin('../login.php');
refreshUserSession($pdo, '../login.php');
requireRole('admin');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function availabilityEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function availabilityId($value): int
{
    if (!is_string($value) && !is_int($value)) {
        return 0;
    }

    return (int) (filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]) ?: 0);
}

function availabilityTime($value): bool
{
    return is_string($value)
        && preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $value) === 1;
}

$days = [
    1 => 'Poniedziałek',
    2 => 'Wtorek',
    3 => 'Środa',
    4 => 'Czwartek',
    5 => 'Piątek',
    6 => 'Sobota',
    7 => 'Niedziela'
];

$errors = [];
$success = $_SESSION['availability_success'] ?? '';
unset($_SESSION['availability_success']);

$employees = $pdo->query(
    'SELECT
        e.id,
        u.name,
        u.surname,
        e.active,
        u.active AS account_active
     FROM employees e
     INNER JOIN users u ON u.id = e.user_id
     ORDER BY u.surname, u.name'
)->fetchAll(PDO::FETCH_ASSOC);

$employeeIds = [];

foreach ($employees as $employee) {
    $employeeIds[(int) $employee['id']] = true;
}

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

$rawEmployeeId = $isPost
    ? ($_POST['employee_id'] ?? null)
    : ($_GET['employee_id'] ?? null);

if ($rawEmployeeId === null && !$isPost && !empty($employees)) {
    $selectedId = (int) $employees[0]['id'];
} else {
    $selectedId = availabilityId($rawEmployeeId);
}

if ($selectedId && !isset($employeeIds[$selectedId])) {
    $errors[] = 'Nie znaleziono wybranego pracownika.';
    $selectedId = 0;
}

$form = [
    'id' => 0,
    'day_of_week' => 1,
    'start_time' => '09:00',
    'end_time' => '17:00'
];

if ($isPost) {

    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Nieprawidłowy token formularza.');
    }

    $action = $_POST['action'] ?? '';

    if (!is_string($action)) {
        $action = '';
    }

    if (!$selectedId) {
        $errors[] = 'Wybierz poprawnego pracownika.';
    }

    if ($action === 'save') {

        $rawSlotId = $_POST['slot_id'] ?? '';
        $slotId = $rawSlotId === '' ? 0 : availabilityId($rawSlotId);

        $day = availabilityId($_POST['day_of_week'] ?? null);

        $start = $_POST['start_time'] ?? '';
        $end = $_POST['end_time'] ?? '';

        $start = is_string($start) ? trim($start) : '';
        $end = is_string($end) ? trim($end) : '';

        $form = [
            'id' => $slotId,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end
        ];

        if ($rawSlotId !== '' && !$slotId) {
            $errors[] = 'Nieprawidłowy identyfikator grafiku.';
        }

        if (!isset($days[$day])) {
            $errors[] = 'Wybierz poprawny dzień tygodnia.';
        }

        if (!availabilityTime($start) || !availabilityTime($end)) {
            $errors[] = 'Podaj poprawne godziny w formacie GG:MM.';
        } elseif ($start >= $end) {
            $errors[] = 'Godzina rozpoczęcia musi być wcześniejsza niż zakończenia.';
        }

        if (empty($errors)) {

            try {

                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'SELECT id FROM employees WHERE id = ? FOR UPDATE'
                );

                $stmt->execute([$selectedId]);

                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException('Nie znaleziono pracownika.');
                }

                if ($slotId) {

                    $stmt = $pdo->prepare(
                        'SELECT id
                         FROM employee_availability
                         WHERE id = ? AND employee_id = ?'
                    );

                    $stmt->execute([$slotId, $selectedId]);

                    if (!$stmt->fetchColumn()) {
                        throw new RuntimeException('Nie znaleziono wpisu grafiku.');
                    }
                }

                $stmt = $pdo->prepare(
                    'SELECT id
                     FROM employee_availability
                     WHERE employee_id = ?
                       AND day_of_week = ?
                       AND start_time < ?
                       AND end_time > ?
                       AND id <> ?
                     LIMIT 1'
                );

                $stmt->execute([
                    $selectedId,
                    $day,
                    $end . ':00',
                    $start . ':00',
                    $slotId
                ]);

                if ($stmt->fetchColumn()) {

                    $pdo->rollBack();

                    $errors[] = 'Podane godziny nakładają się na istniejący grafik.';

                } else {

                    if ($slotId) {

                        $stmt = $pdo->prepare(
                            'UPDATE employee_availability
                             SET day_of_week = ?,
                                 start_time = ?,
                                 end_time = ?
                             WHERE id = ? AND employee_id = ?'
                        );

                        $stmt->execute([
                            $day,
                            $start,
                            $end,
                            $slotId,
                            $selectedId
                        ]);

                    } else {

                        $stmt = $pdo->prepare(
                            'INSERT INTO employee_availability
                                (employee_id, day_of_week, start_time, end_time)
                             VALUES (?, ?, ?, ?)'
                        );

                        $stmt->execute([
                            $selectedId,
                            $day,
                            $start,
                            $end
                        ]);
                    }

                    $pdo->commit();

                    $_SESSION['availability_success'] = 'Zapisano grafik pracownika.';

                    header('Location: availability.php?employee_id=' . $selectedId);
                    exit;
                }

            } catch (Throwable $exception) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $errors[] = 'Nie udało się zapisać grafiku.';
            }
        }

    } elseif ($action === 'delete') {

        $slotId = availabilityId($_POST['slot_id'] ?? null);

        if (!$slotId) {
            $errors[] = 'Nieprawidłowy identyfikator grafiku.';
        }

        if (empty($errors)) {

            try {

                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'SELECT id FROM employees WHERE id = ? FOR UPDATE'
                );

                $stmt->execute([$selectedId]);

                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException('Nie znaleziono pracownika.');
                }

                $stmt = $pdo->prepare(
                    'DELETE FROM employee_availability
                     WHERE id = ? AND employee_id = ?'
                );

                $stmt->execute([$slotId, $selectedId]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Nie znaleziono wpisu grafiku.');
                }

                $pdo->commit();

                $_SESSION['availability_success'] = 'Usunięto wpis grafiku.';

                header('Location: availability.php?employee_id=' . $selectedId);
                exit;

            } catch (Throwable $exception) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $errors[] = 'Nie udało się usunąć wpisu grafiku.';
            }
        }

    } else {
        $errors[] = 'Nieprawidłowa operacja.';
    }
}

if (!$isPost && isset($_GET['edit'])) {

    $editId = availabilityId($_GET['edit']);

    if (!$editId || !$selectedId) {
        $errors[] = 'Nieprawidłowy wpis grafiku.';
    } else {

        $stmt = $pdo->prepare(
            'SELECT id, day_of_week, start_time, end_time
             FROM employee_availability
             WHERE id = ? AND employee_id = ?'
        );

        $stmt->execute([$editId, $selectedId]);

        $editSlot = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$editSlot) {
            $errors[] = 'Nie znaleziono wpisu grafiku.';
        } else {
            $form = [
                'id' => (int) $editSlot['id'],
                'day_of_week' => (int) $editSlot['day_of_week'],
                'start_time' => substr($editSlot['start_time'], 0, 5),
                'end_time' => substr($editSlot['end_time'], 0, 5)
            ];
        }
    }
}

$schedule = [];

if ($selectedId) {

    $stmt = $pdo->prepare(
        'SELECT id, day_of_week, start_time, end_time
         FROM employee_availability
         WHERE employee_id = ?
         ORDER BY day_of_week, start_time'
    );

    $stmt->execute([$selectedId]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $slot) {
        $schedule[(int) $slot['day_of_week']][] = $slot;
    }
}

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dostępność — Sharp Society Barber</title>

    <link rel="stylesheet" href="../css/style-employees.css">
    <link rel="stylesheet" href="../css/style-availability.css">
    <link rel="stylesheet" href="../css/style-admin-nav.css">
</head>
<body>

    <header class="topbar">
        <h1>Sharp Society Barber</h1>
        <?php require '../../includes/admin_nav.php'; ?>
    </header>

    <main class="container">

        <h2>Dostępność pracowników</h2>

        <?php foreach ($errors as $error): ?>
            <p class="message-error"><?= availabilityEscape($error) ?></p>
        <?php endforeach; ?>

        <?php if ($success !== ''): ?>
            <p class="message-success"><?= availabilityEscape($success) ?></p>
        <?php endif; ?>

        <section class="card">

            <h3>Wybierz pracownika</h3>

            <?php if (empty($employees)): ?>

                <p>Brak pracowników w bazie danych.</p>

            <?php else: ?>

                <form method="GET" class="employee-selector">

                    <label for="employee_id">Pracownik</label>

                    <select id="employee_id" name="employee_id" required>

                        <?php foreach ($employees as $employee): ?>

                            <option
                                value="<?= (int) $employee['id'] ?>"
                                <?= (int) $employee['id'] === $selectedId ? 'selected' : '' ?>
                            >
                                <?= availabilityEscape(
                                    $employee['name'] . ' ' . $employee['surname']
                                ) ?>

                                <?= (int) $employee['active'] === 0
                                    || (int) $employee['account_active'] === 0
                                    ? '(nieaktywny)'
                                    : '' ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <button type="submit">Pokaż grafik</button>

                </form>

            <?php endif; ?>

        </section>

        <?php if ($selectedId): ?>

            <section class="card">

                <h3>
                    <?= $form['id'] ? 'Edytuj godziny pracy' : 'Dodaj godziny pracy' ?>
                </h3>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= availabilityEscape($_SESSION['csrf_token']) ?>"
                    >

                    <input type="hidden" name="action" value="save">

                    <input
                        type="hidden"
                        name="employee_id"
                        value="<?= $selectedId ?>"
                    >

                    <input
                        type="hidden"
                        name="slot_id"
                        value="<?= $form['id'] ? (int) $form['id'] : '' ?>"
                    >

                    <div class="availability-form">

                        <div>
                            <label for="day_of_week">Dzień tygodnia</label>

                            <select id="day_of_week" name="day_of_week" required>

                                <?php foreach ($days as $number => $name): ?>

                                    <option
                                        value="<?= $number ?>"
                                        <?= (int) $form['day_of_week'] === $number ? 'selected' : '' ?>
                                    >
                                        <?= availabilityEscape($name) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>
                        </div>

                        <div>
                            <label for="start_time">Od godziny</label>

                            <input
                                type="time"
                                id="start_time"
                                name="start_time"
                                value="<?= availabilityEscape($form['start_time']) ?>"
                                required
                            >
                        </div>

                        <div>
                            <label for="end_time">Do godziny</label>

                            <input
                                type="time"
                                id="end_time"
                                name="end_time"
                                value="<?= availabilityEscape($form['end_time']) ?>"
                                required
                            >
                        </div>

                    </div>

                    <button type="submit">
                        <?= $form['id'] ? 'Zapisz zmiany' : 'Dodaj godziny' ?>
                    </button>

                    <?php if ($form['id']): ?>
                        <a
                            class="cancel-link"
                            href="availability.php?employee_id=<?= $selectedId ?>"
                        >
                            Anuluj edycję
                        </a>
                    <?php endif; ?>

                </form>

            </section>

            <section class="card">

                <h3>Tygodniowy grafik pracownika</h3>

                <div class="table-wrapper">

                    <table>
                        <thead>
                            <tr>
                                <th>Dzień tygodnia</th>
                                <th>Godziny pracy</th>
                                <th>Akcje</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($days as $dayNumber => $dayName): ?>

                                <?php if (empty($schedule[$dayNumber])): ?>

                                    <tr>
                                        <td><?= availabilityEscape($dayName) ?></td>
                                        <td class="day-off">Wolne</td>
                                        <td>—</td>
                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($schedule[$dayNumber] as $index => $slot): ?>

                                        <tr>

                                            <td>
                                                <?= $index === 0
                                                    ? availabilityEscape($dayName)
                                                    : '' ?>
                                            </td>

                                            <td>
                                                <?= availabilityEscape(substr($slot['start_time'], 0, 5)) ?>
                                                –
                                                <?= availabilityEscape(substr($slot['end_time'], 0, 5)) ?>
                                            </td>

                                            <td class="actions">

                                                <a href="availability.php?employee_id=<?= $selectedId ?>&edit=<?= (int) $slot['id'] ?>">
                                                    Edytuj
                                                </a>

                                                <form
                                                    method="POST"
                                                    onsubmit="return confirm('Czy usunąć te godziny pracy?');"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= availabilityEscape($_SESSION['csrf_token']) ?>"
                                                    >

                                                    <input type="hidden" name="action" value="delete">

                                                    <input
                                                        type="hidden"
                                                        name="employee_id"
                                                        value="<?= $selectedId ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="slot_id"
                                                        value="<?= (int) $slot['id'] ?>"
                                                    >

                                                    <button type="submit" class="delete-btn">
                                                        Usuń
                                                    </button>

                                                </form>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            <?php endforeach; ?>

                        </tbody>
                    </table>

                </div>

            </section>

        <?php endif; ?>

    </main>

</body>
</html>