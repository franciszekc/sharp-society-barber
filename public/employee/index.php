
<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin('../login.php');
refreshUserSession($pdo, '../login.php');
requireRole('employee');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

date_default_timezone_set('Europe/Warsaw');

function employeeEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$stmt = $pdo->prepare(
    'SELECT id, active
     FROM employees
     WHERE user_id = ?
     LIMIT 1'
);

$stmt->execute([getUserId()]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

$employeeId = $employee ? (int) $employee['id'] : 0;
$employeeActive = $employee && (int) $employee['active'] === 1;

$today = date('Y-m-d');
$now = date('H:i:s');

$todayReservations = [];
$upcomingReservations = [];
$historyReservations = [];

if ($employeeId && $employeeActive) {

    $stmt = $pdo->prepare(
        "SELECT
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
           AND r.reservation_date = ?
           AND r.status <> 'cancelled'
         ORDER BY r.start_time ASC
         LIMIT 5"
    );

    $stmt->execute([$employeeId, $today]);
    $todayReservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT
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
           AND r.status IN ('pending', 'confirmed')
           AND (
               r.reservation_date > ?
               OR (
                   r.reservation_date = ?
                   AND r.start_time > ?
               )
           )
         ORDER BY r.reservation_date ASC, r.start_time ASC
         LIMIT 5"
    );

    $stmt->execute([$employeeId, $today, $today, $now]);
    $upcomingReservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT
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
           AND (
               r.status IN ('completed', 'cancelled')
               OR r.reservation_date < ?
               OR (
                   r.reservation_date = ?
                   AND r.end_time <= ?
               )
           )
         ORDER BY r.reservation_date DESC, r.start_time DESC
         LIMIT 5"
    );

    $stmt->execute([$employeeId, $today, $today, $now]);
    $historyReservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(
                CASE
                    WHEN reservation_date = ?
                         AND status <> 'cancelled'
                    THEN 1 ELSE 0
                END
            ), 0) AS today_count,

            COALESCE(SUM(
                CASE
                    WHEN status IN ('pending', 'confirmed')
                         AND (
                             reservation_date > ?
                             OR (
                                 reservation_date = ?
                                 AND start_time > ?
                             )
                         )
                    THEN 1 ELSE 0
                END
            ), 0) AS upcoming_count,

            COALESCE(SUM(
                CASE
                    WHEN status IN ('completed', 'cancelled')
                         OR reservation_date < ?
                         OR (
                             reservation_date = ?
                             AND end_time <= ?
                         )
                    THEN 1 ELSE 0
                END
            ), 0) AS history_count

         FROM reservations
         WHERE employee_id = ?"
    );

    $stmt->execute([
        $today,
        $today,
        $today,
        $now,
        $today,
        $today,
        $now,
        $employeeId
    ]);

    $counts = $stmt->fetch(PDO::FETCH_ASSOC);

} else {

    $counts = [
        'today_count' => 0,
        'upcoming_count' => 0,
        'history_count' => 0
    ];

}

$statuses = [
    'pending' => 'Oczekująca',
    'confirmed' => 'Potwierdzona',
    'completed' => 'Zakończona',
    'cancelled' => 'Anulowana'
];

function showReservations(array $reservations, array $statuses): void
{
    if (empty($reservations)) {
        echo '<p class="empty">Brak wizyt w tej kategorii.</p>';
        return;
    }

    echo '<div class="table-wrapper">';
    echo '<table>';

    echo '<thead><tr>';
    echo '<th>Data</th>';
    echo '<th>Godzina</th>';
    echo '<th>Klient</th>';
    echo '<th>Usługa</th>';
    echo '<th>Status</th>';
    echo '</tr></thead>';

    echo '<tbody>';

    foreach ($reservations as $reservation) {

        $status = $reservation['status'];

        echo '<tr>';

        echo '<td>' . employeeEscape(
            date('d.m.Y', strtotime($reservation['reservation_date']))
        ) . '</td>';

        echo '<td>' . employeeEscape(
            substr($reservation['start_time'], 0, 5)
        ) . '–' . employeeEscape(
            substr($reservation['end_time'], 0, 5)
        ) . '</td>';

        echo '<td>' . employeeEscape(
            $reservation['client_name'] . ' ' .
            $reservation['client_surname']
        ) . '</td>';

        echo '<td>' . employeeEscape(
            $reservation['service_name']
        ) . '</td>';

        echo '<td><span class="status status-' .
            employeeEscape($status) . '">' .
            employeeEscape($statuses[$status] ?? $status) .
            '</span></td>';

        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel pracownika — Sharp Society Barber</title>
    <link rel="stylesheet" href="../css/style-employee.css">
</head>
<body>
    <header class="topbar">

        <h1>Sharp Society Barber</h1>
        <nav>
            <a href="index.php">Panel główny</a>
            <a href="../profile.php">Mój profil</a>
            <a href="reservations.php">Rezerwacje</a>
            
            <form action="../logout.php" method="POST" class="logout-form">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                <button type="submit" class="logout-button">
                    Wyloguj
                </button>
            </form>

        </nav>
    </header>
    <main class="container">
        <h2>Panel pracownika</h2>
        <p class="welcome">
            Witaj, <?= employeeEscape($_SESSION['user_name'] ?? 'Pracowniku') ?>!
        </p>
        <?php if (!$employeeId): ?>
            <div class="notice">
                Twoje konto nie ma jeszcze przypisanego profilu pracownika.
                Skontaktuj się z administratorem.
            </div>
        <?php elseif (!$employeeActive): ?>
            <div class="notice">
                Twój profil pracownika jest nieaktywny.
                Skontaktuj się z administratorem.
            </div>
        <?php else: ?>
            <section class="stats">
                <div class="stat-card">
                    <h3>Dzisiejsze wizyty</h3>
                    <strong><?= (int) $counts['today_count'] ?></strong>
                </div>
                <div class="stat-card">
                    <h3>Nadchodzące wizyty</h3>
                    <strong><?= (int) $counts['upcoming_count'] ?></strong>
                </div>
                <div class="stat-card">
                    <h3>Historia wizyt</h3>
                    <strong><?= (int) $counts['history_count'] ?></strong>
                </div>
            </section>
            <section class="card">
                <h3>Dzisiejsze wizyty — <?= date('d.m.Y') ?></h3>
                <?php showReservations($todayReservations, $statuses); ?>
            </section>
            <section class="card">
                <h3>Najbliższe 5 wizyt</h3>
                <?php showReservations($upcomingReservations, $statuses); ?>
            </section>
            <section class="card">
                <h3>Ostatnie 5 wizyt z historii</h3>
                <?php showReservations($historyReservations, $statuses); ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
