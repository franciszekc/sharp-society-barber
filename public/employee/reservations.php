<?php

// Filtrowanie wizyt pracownika i dozwolone zmiany statusów.

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin('../login.php');
refreshUserSession($pdo, '../login.php');
requireRole('employee');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Profil pracownika zidentyfikowany na podstawie sesji.
$stmt = $pdo->prepare(
    "SELECT id, active
     FROM employees
     WHERE user_id = ?
     LIMIT 1"
);

$stmt->execute([getUserId()]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

$employeeId = $employee ? (int) $employee['id'] : 0;
$employeeActive = $employee && (int) $employee['active'] === 1;

// Dozwolone statusy filtrowania i edycji.
$allowedStatuses = [
    'pending',
    'confirmed',
    'completed',
    'cancelled'
];

// Kodowanie wartości przed wyświetleniem w HTML.
function escape($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Tłumaczenie kodów statusu na etykiety.
function statusName($status): string
{
    $statuses = [
        'pending' => 'Oczekująca',
        'confirmed' => 'Potwierdzona',
        'completed' => 'Zakończona',
        'cancelled' => 'Anulowana'
    ];

    return $statuses[$status] ?? $status;
}

// Parametry filtrowania listy.
$dateFilter = $_GET['date'] ?? '';
$statusFilter = $_GET['status'] ?? '';

if (!is_string($dateFilter)) {
    $dateFilter = '';
}

if (!is_string($statusFilter) ||
    !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

if ($dateFilter !== '') {
    $date = DateTime::createFromFormat('!Y-m-d', $dateFilter);

    if (!$date || $date->format('Y-m-d') !== $dateFilter) {
        $dateFilter = '';
    }
}

$filterParams = [];

if ($dateFilter !== '') {
    $filterParams['date'] = $dateFilter;
}

if ($statusFilter !== '') {
    $filterParams['status'] = $statusFilter;
}

$filterQuery = http_build_query($filterParams);
$redirectUrl = 'reservations.php';

if ($filterQuery !== '') {
    $redirectUrl .= '?' . $filterQuery;
}

// Obsługa żądania formularza i walidacja danych.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $message = '';
    $messageType = 'error';

    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)) {

        $message = 'Nieprawidłowy token formularza.';

    } elseif (!$employeeId || !$employeeActive) {

        $message = 'Nie masz aktywnego profilu pracownika.';

    } else {

        $reservationId = filter_var(
            $_POST['reservation_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $newStatus = $_POST['new_status'] ?? '';

        $transitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['completed', 'cancelled']
        ];

        if (!$reservationId || $reservationId <= 0 ||
            !is_string($newStatus) ||
            !in_array($newStatus, $allowedStatuses, true)) {

            $message = 'Nieprawidłowe dane formularza.';

        } else {

            $stmt = $pdo->prepare(
                "SELECT id, status
                 FROM reservations
                 WHERE id = ?
                   AND employee_id = ?
                 LIMIT 1"
            );

            $stmt->execute([
                $reservationId,
                $employeeId
            ]);

            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reservation) {

                $message = 'Nie znaleziono Twojej rezerwacji.';

            } else {

                $currentStatus = $reservation['status'];

                $allowedTransitions = $transitions[$currentStatus] ?? [];

                if (!in_array($newStatus, $allowedTransitions, true)) {

                    $message = 'Niedozwolona zmiana statusu.';

                } else {

                    $stmt = $pdo->prepare(
                        "UPDATE reservations
                         SET status = ?
                         WHERE id = ?
                           AND employee_id = ?
                           AND status = ?"
                    );

                    $stmt->execute([
                        $newStatus,
                        $reservationId,
                        $employeeId,
                        $currentStatus
                    ]);

                    if ($stmt->rowCount() === 1) {

                        $message = 'Status rezerwacji został zmieniony.';
                        $messageType = 'success';

                    } else {

                        $message = 'Status został już zmieniony. Odśwież listę.';
                    }
                }
            }
        }
    }

    $_SESSION['employee_message'] = $message;
    $_SESSION['employee_message_type'] = $messageType;

    header('Location: ' . $redirectUrl);
    exit;
}

$message = $_SESSION['employee_message'] ?? '';
$messageType = $_SESSION['employee_message_type'] ?? '';

unset(
    $_SESSION['employee_message'],
    $_SESSION['employee_message_type']
);

// Pobranie wizyt przypisanych do zalogowanego pracownika.
$reservations = [];

if ($employeeId > 0 && $employeeActive) {

    $sql = "
        SELECT
            r.id,
            r.reservation_date,
            r.start_time,
            r.end_time,
            r.status,
            u.name AS client_name,
            u.surname AS client_surname,
            s.name AS service_name
        FROM reservations r
        INNER JOIN users u ON u.id = r.user_id
        INNER JOIN services s ON s.id = r.service_id
        WHERE r.employee_id = ?
    ";

    $params = [$employeeId];

    if ($dateFilter !== '') {
        $sql .= " AND r.reservation_date = ?";
        $params[] = $dateFilter;
    }

    if ($statusFilter !== '') {
        $sql .= " AND r.status = ?";
        $params[] = $statusFilter;
    }

    $sql .= "
        ORDER BY
            r.reservation_date DESC,
            r.start_time DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<!-- Widok strony: Filtrowanie wizyt pracownika i dozwolone zmiany statusów -->
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Moje rezerwacje — Sharp Society Barber</title>

    <link rel="stylesheet" href="../css/style-employee-common.css">
    <link rel="stylesheet" href="../css/style-employee-reservations.css">
</head>

<body>

    <header class="topbar">
        <div class="container">

            <h2>Sharp Society Barber</h2>

            <nav>
                <a href="index.php">Panel główny</a>
                <a href="reservations.php">Rezerwacje</a>
                <a href="../profile.php">Mój profil</a>
                <form action="../logout.php" method="POST" class="logout-form">
                    <input type="hidden" name="csrf_token"
                           value="<?= escape($_SESSION['csrf_token']) ?>">
                    <button type="submit" class="logout-button">Wyloguj</button>
                </form>
            </nav>

        </div>
    </header>

    <!-- Zawartość główna panelu. -->

    <main class="container">

        <h1>Moje rezerwacje</h1>

        <?php if ($message !== ''): ?>

            <div class="employee-message <?= $messageType === 'success' ? 'message-success' : 'message-error' ?>">
                <?= escape($message) ?>
            </div>

        <?php endif; ?>

        <?php if (!$employeeId): ?>

            <div class="notice">
                Twoje konto nie ma przypisanego profilu pracownika.
                Skontaktuj się z administratorem.
            </div>

        <?php elseif (!$employeeActive): ?>

            <div class="notice">
                Twój profil pracownika jest nieaktywny.
            </div>

        <?php else: ?>

            <section class="card">

                <h2>Filtruj rezerwacje</h2>

                <form method="GET" class="reservation-filters">

                    <div class="filter-field">

                        <label for="date">Data wizyty</label>

                        <input
                            type="date"
                            id="date"
                            name="date"
                            value="<?= escape($dateFilter) ?>"
                        >

                    </div>

                    <div class="filter-field">

                        <label for="status">Status</label>

                        <select id="status" name="status">

                            <option value="">Wszystkie</option>

                            <?php foreach ($allowedStatuses as $status): ?>

                                <option
                                    value="<?= escape($status) ?>"
                                    <?= $statusFilter === $status ? 'selected' : '' ?>
                                >
                                    <?= escape(statusName($status)) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="filter-buttons">

                        <button type="submit">
                            Filtruj
                        </button>

                        <a href="reservations.php" class="clear-button">
                            Wyczyść filtry
                        </a>

                    </div>

                </form>

            </section>

            <section class="card">

                <h2>Lista rezerwacji</h2>

                <p>
                    Liczba znalezionych rezerwacji:
                    <strong><?= count($reservations) ?></strong>
                </p>

                <?php if (empty($reservations)): ?>

                    <p>Nie znaleziono rezerwacji.</p>

                <?php else: ?>

                    <div class="table-wrapper">

                        <table>

                            <thead>
                                <tr>
                                    <th>Klient</th>
                                    <th>Usługa</th>
                                    <th>Data</th>
                                    <th>Godzina</th>
                                    <th>Status</th>
                                    <th>Akcje</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($reservations as $reservation): ?>

                                    <tr>

                                        <td>
                                            <?= escape($reservation['client_name']) ?>
                                            <?= escape($reservation['client_surname']) ?>
                                        </td>

                                        <td>
                                            <?= escape($reservation['service_name']) ?>
                                        </td>

                                        <td>
                                            <?= escape($reservation['reservation_date']) ?>
                                        </td>

                                        <td>
                                            <?= escape(substr($reservation['start_time'], 0, 5)) ?>
                                            -
                                            <?= escape(substr($reservation['end_time'], 0, 5)) ?>
                                        </td>

                                        <td>
                                            <span class="status status-<?= escape($reservation['status']) ?>">
                                                <?= escape(statusName($reservation['status'])) ?>
                                            </span>
                                        </td>

                                        <td>

                                            <div class="reservation-actions">

                                                <?php

                                                $status = $reservation['status'];

                                                if ($status === 'pending') {
                                                    $actions = [
                                                        'confirmed' => 'Potwierdź',
                                                        'cancelled' => 'Anuluj'
                                                    ];
                                                } elseif ($status === 'confirmed') {
                                                    $actions = [
                                                        'completed' => 'Zakończ',
                                                        'cancelled' => 'Anuluj'
                                                    ];
                                                } else {
                                                    $actions = [];
                                                }

                                                ?>

                                                <?php foreach ($actions as $newStatus => $label): ?>

                                                    <form
                                                        method="POST"
                                                        action="<?= escape($redirectUrl) ?>"
                                                        class="action-form"
                                                        <?= $newStatus === 'cancelled' ? 'onsubmit="return confirm(\'Czy na pewno anulować rezerwację?\')"' : '' ?>
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="csrf_token"
                                                            value="<?= escape($_SESSION['csrf_token']) ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="reservation_id"
                                                            value="<?= (int) $reservation['id'] ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="new_status"
                                                            value="<?= escape($newStatus) ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="action-button action-<?= escape($newStatus) ?>"
                                                        >
                                                            <?= escape($label) ?>
                                                        </button>

                                                    </form>

                                                <?php endforeach; ?>

                                                <?php if (empty($actions)): ?>
                                                    <span>—</span>
                                                <?php endif; ?>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </section>

        <?php endif; ?>

    </main>

</body>
</html>