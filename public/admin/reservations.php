<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin('../login.php');
refreshUserSession($pdo, '../login.php');
requireRole('admin');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function reservationEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function reservationId($value): int
{
    if (!is_string($value) && !is_int($value)) {
        return 0;
    }

    return (int) (filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]) ?: 0);
}

function reservationDate($value): bool
{
    if (!is_string($value)) {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date !== false && $date->format('Y-m-d') === $value;
}

$statuses = [
    'pending' => 'Oczekująca',
    'confirmed' => 'Potwierdzona',
    'completed' => 'Zakończona',
    'cancelled' => 'Anulowana'
];

$transitions = [
    'pending' => ['confirmed', 'cancelled'],
    'confirmed' => ['completed', 'cancelled'],
    'completed' => [],
    'cancelled' => []
];

$errors = [];
$success = $_SESSION['reservations_success'] ?? '';
unset($_SESSION['reservations_success']);

$employees = $pdo->query(
    'SELECT
        e.id,
        u.name,
        u.surname
     FROM employees e
     INNER JOIN users u ON u.id = e.user_id
     ORDER BY u.surname, u.name'
)->fetchAll(PDO::FETCH_ASSOC);

$employeeIds = [];

foreach ($employees as $employee) {
    $employeeIds[(int) $employee['id']] = true;
}

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

$filterSource = $isPost ? $_POST : $_GET;

$statusFilter = $filterSource['filter_status'] ?? '';
$employeeFilter = $filterSource['filter_employee'] ?? '';
$dateFilter = $filterSource['filter_date'] ?? '';

$statusFilter = is_string($statusFilter) ? trim($statusFilter) : '';
$employeeFilter = is_string($employeeFilter) ? trim($employeeFilter) : '';
$dateFilter = is_string($dateFilter) ? trim($dateFilter) : '';

if ($statusFilter !== '' && !isset($statuses[$statusFilter])) {
    $errors[] = 'Nieprawidłowy filtr statusu.';
    $statusFilter = '';
}

if ($employeeFilter !== '') {

    $employeeId = reservationId($employeeFilter);

    if (!$employeeId || !isset($employeeIds[$employeeId])) {
        $errors[] = 'Nieprawidłowy filtr pracownika.';
        $employeeFilter = '';
    } else {
        $employeeFilter = (string) $employeeId;
    }
}

if ($dateFilter !== '' && !reservationDate($dateFilter)) {
    $errors[] = 'Nieprawidłowa data filtrowania.';
    $dateFilter = '';
}

$filterParams = [];

if ($statusFilter !== '') {
    $filterParams['filter_status'] = $statusFilter;
}

if ($employeeFilter !== '') {
    $filterParams['filter_employee'] = $employeeFilter;
}

if ($dateFilter !== '') {
    $filterParams['filter_date'] = $dateFilter;
}

$returnUrl = 'reservations.php';

if (!empty($filterParams)) {
    $returnUrl .= '?' . http_build_query($filterParams);
}

if ($isPost) {

    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Nieprawidłowy token formularza.');
    }

    $action = $_POST['action'] ?? '';
    $action = is_string($action) ? $action : '';

    if ($action === 'change_status') {

        $reservationId = reservationId($_POST['reservation_id'] ?? null);
        $newStatus = $_POST['new_status'] ?? '';
        $newStatus = is_string($newStatus) ? $newStatus : '';

        if (!$reservationId) {
            $errors[] = 'Nieprawidłowy identyfikator rezerwacji.';
        }

        if (!isset($statuses[$newStatus])) {
            $errors[] = 'Nieprawidłowy status rezerwacji.';
        }

        if (empty($errors)) {

            try {

                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'SELECT employee_id
                     FROM reservations
                     WHERE id = ?'
                );

                $stmt->execute([$reservationId]);
                $reservationEmployeeId = $stmt->fetchColumn();

                if (!$reservationEmployeeId) {
                    throw new RuntimeException('Nie znaleziono rezerwacji.');
                }

                $stmt = $pdo->prepare(
                    'SELECT id FROM employees WHERE id = ? FOR UPDATE'
                );

                $stmt->execute([(int) $reservationEmployeeId]);

                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException('Nie znaleziono pracownika.');
                }

                $stmt = $pdo->prepare(
                    'SELECT
                        id,
                        employee_id,
                        reservation_date,
                        start_time,
                        end_time,
                        status
                     FROM reservations
                     WHERE id = ?
                     FOR UPDATE'
                );

                $stmt->execute([$reservationId]);
                $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$reservation) {
                    throw new RuntimeException('Nie znaleziono rezerwacji.');
                }

                $currentStatus = $reservation['status'];

                if (!in_array(
                    $newStatus,
                    $transitions[$currentStatus] ?? [],
                    true
                )) {
                    throw new RuntimeException(
                        'Niedozwolona zmiana statusu rezerwacji.'
                    );
                }

                if ($newStatus === 'confirmed') {

                    $stmt = $pdo->prepare(
                        "SELECT id
                         FROM reservations
                         WHERE employee_id = ?
                           AND reservation_date = ?
                           AND status IN ('confirmed', 'completed')
                           AND start_time < ?
                           AND end_time > ?
                           AND id <> ?
                         LIMIT 1"
                    );

                    $stmt->execute([
                        $reservation['employee_id'],
                        $reservation['reservation_date'],
                        $reservation['end_time'],
                        $reservation['start_time'],
                        $reservationId
                    ]);

                    if ($stmt->fetchColumn()) {
                        throw new RuntimeException(
                            'Pracownik ma już inną wizytę w tych godzinach.'
                        );
                    }
                }

                $stmt = $pdo->prepare(
                    'UPDATE reservations
                     SET status = ?
                     WHERE id = ?'
                );

                $stmt->execute([$newStatus, $reservationId]);

                $pdo->commit();

                $_SESSION['reservations_success'] =
                    'Zmieniono status rezerwacji na: ' . $statuses[$newStatus] . '.';

                header('Location: ' . $returnUrl);
                exit;

            } catch (Throwable $exception) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $errors[] = $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'Nie udało się zmienić statusu rezerwacji.';
            }
        }

    } else {
        $errors[] = 'Nieprawidłowa operacja.';
    }
}

$sql = '
    SELECT
        r.id,
        r.reservation_date,
        r.start_time,
        r.end_time,
        r.status,
        r.comment,
        c.name AS client_name,
        c.surname AS client_surname,
        c.email AS client_email,
        eu.name AS employee_name,
        eu.surname AS employee_surname,
        s.name AS service_name,
        s.price AS service_price,
        s.duration AS service_duration
    FROM reservations r
    INNER JOIN users c ON c.id = r.user_id
    INNER JOIN employees e ON e.id = r.employee_id
    INNER JOIN users eu ON eu.id = e.user_id
    INNER JOIN services s ON s.id = r.service_id
    WHERE 1 = 1
';

$params = [];

if ($statusFilter !== '') {
    $sql .= ' AND r.status = :status';
    $params['status'] = $statusFilter;
}

if ($employeeFilter !== '') {
    $sql .= ' AND r.employee_id = :employee_id';
    $params['employee_id'] = (int) $employeeFilter;
}

if ($dateFilter !== '') {
    $sql .= ' AND r.reservation_date = :reservation_date';
    $params['reservation_date'] = $dateFilter;
}

$sql .= ' ORDER BY r.reservation_date DESC, r.start_time DESC, r.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Rezerwacje — Sharp Society Barber</title>

    <link rel="stylesheet" href="../css/style-employees.css">
    <link rel="stylesheet" href="../css/style-reservations.css">
    <link rel="stylesheet" href="../css/style-admin-nav.css">
</head>
<body>

    <header class="topbar">
        <h1>Sharp Society Barber</h1>
        <?php require '../../includes/admin_nav.php'; ?>
    </header>

    <main class="container">

        <h2>Zarządzanie rezerwacjami</h2>

        <?php foreach ($errors as $error): ?>
            <p class="message-error">
                <?= reservationEscape($error) ?>
            </p>
        <?php endforeach; ?>

        <?php if ($success !== ''): ?>
            <p class="message-success">
                <?= reservationEscape($success) ?>
            </p>
        <?php endif; ?>

        <section class="card">

            <h3>Filtrowanie rezerwacji</h3>

            <form method="GET" class="reservation-filters">

                <div>
                    <label for="filter_status">Status</label>

                    <select id="filter_status" name="filter_status">
                        <option value="">Wszystkie statusy</option>

                        <?php foreach ($statuses as $value => $label): ?>

                            <option
                                value="<?= reservationEscape($value) ?>"
                                <?= $statusFilter === $value ? 'selected' : '' ?>
                            >
                                <?= reservationEscape($label) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div>
                    <label for="filter_employee">Pracownik</label>

                    <select id="filter_employee" name="filter_employee">
                        <option value="">Wszyscy pracownicy</option>

                        <?php foreach ($employees as $employee): ?>

                            <option
                                value="<?= (int) $employee['id'] ?>"
                                <?= $employeeFilter === (string) $employee['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= reservationEscape(
                                    $employee['name'] . ' ' . $employee['surname']
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div>
                    <label for="filter_date">Data wizyty</label>

                    <input
                        type="date"
                        id="filter_date"
                        name="filter_date"
                        value="<?= reservationEscape($dateFilter) ?>"
                    >
                </div>

                <div class="filter-actions">
                    <button type="submit">Filtruj</button>
                    <a href="reservations.php">Wyczyść filtry</a>
                </div>

            </form>

        </section>

        <section class="card">

            <h3>Lista rezerwacji (<?= count($reservations) ?>)</h3>

            <div class="table-wrapper">

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Data</th>
                            <th>Godziny</th>
                            <th>Klient</th>
                            <th>Pracownik</th>
                            <th>Usługa</th>
                            <th>Status</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($reservations as $reservation): ?>

                            <?php
                                $status = $reservation['status'];
                                $nextStatuses = $transitions[$status] ?? [];
                            ?>

                            <tr>

                                <td><?= (int) $reservation['id'] ?></td>

                                <td>
                                    <?= reservationEscape($reservation['reservation_date']) ?>
                                </td>

                                <td class="nowrap">
                                    <?= reservationEscape(substr($reservation['start_time'], 0, 5)) ?>
                                    –
                                    <?= reservationEscape(substr($reservation['end_time'], 0, 5)) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= reservationEscape(
                                            $reservation['client_name'] . ' ' .
                                            $reservation['client_surname']
                                        ) ?>
                                    </strong>

                                    <small>
                                        <?= reservationEscape($reservation['client_email']) ?>
                                    </small>
                                </td>

                                <td>
                                    <?= reservationEscape(
                                        $reservation['employee_name'] . ' ' .
                                        $reservation['employee_surname']
                                    ) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= reservationEscape($reservation['service_name']) ?>
                                    </strong>

                                    <small>
                                        <?= (int) $reservation['service_duration'] ?> min
                                        |
                                        <?= number_format(
                                            (float) $reservation['service_price'],
                                            2,
                                            ',',
                                            ' '
                                        ) ?> zł
                                    </small>

                                    <?php if (trim((string) $reservation['comment']) !== ''): ?>
                                        <small>
                                            Uwagi:
                                            <?= reservationEscape($reservation['comment']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="status status-<?= reservationEscape($status) ?>">
                                        <?= reservationEscape($statuses[$status] ?? $status) ?>
                                    </span>
                                </td>

                                <td class="reservation-actions">

                                    <?php if (empty($nextStatuses)): ?>

                                        <span class="no-actions">Brak akcji</span>

                                    <?php else: ?>

                                        <?php foreach ($nextStatuses as $nextStatus): ?>

                                            <form
                                                method="POST"
                                                onsubmit="return confirm('Czy zmienić status rezerwacji?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= reservationEscape($_SESSION['csrf_token']) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="change_status"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="reservation_id"
                                                    value="<?= (int) $reservation['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="new_status"
                                                    value="<?= reservationEscape($nextStatus) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="filter_status"
                                                    value="<?= reservationEscape($statusFilter) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="filter_employee"
                                                    value="<?= reservationEscape($employeeFilter) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="filter_date"
                                                    value="<?= reservationEscape($dateFilter) ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="action-<?= reservationEscape($nextStatus) ?>"
                                                >
                                                    <?php
                                                        if ($nextStatus === 'confirmed') {
                                                            echo 'Potwierdź';
                                                        } elseif ($nextStatus === 'completed') {
                                                            echo 'Zakończ';
                                                        } else {
                                                            echo 'Anuluj';
                                                        }
                                                    ?>
                                                </button>

                                            </form>

                                        <?php endforeach; ?>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <?php if (empty($reservations)): ?>

                            <tr>
                                <td colspan="8" class="empty-table">
                                    Brak rezerwacji spełniających wybrane kryteria.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>
                </table>

            </div>

        </section>

    </main>

</body>
</html>