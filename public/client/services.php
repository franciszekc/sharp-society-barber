
<?php

// Kalendarz terminów, API dostępności i transakcyjny zapis rezerwacji.

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin('../login.php');
refreshUserSession($pdo, '../login.php');
requireRole('client');

date_default_timezone_set('Europe/Warsaw');

require_once '../../includes/booking-rules.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Kodowanie tekstu przed wyświetleniem w HTML.
function bookingEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Walidacja dodatniego identyfikatora.
function bookingId($value): int
{
    return filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]) ?: 0;
}


// Zakres dat udostępnianych przez kalendarz.
$today = new DateTimeImmutable('today');
$lastDate = $today->modify('+30 days');

// Oferta i przypisania usług do pracowników.

$services = $pdo->query(
    'SELECT s.id, s.name, s.duration, s.price, c.name AS category
     FROM services s
     JOIN service_categories c ON c.id = s.category_id
     WHERE s.active = 1 AND s.duration > 0
     ORDER BY c.name, s.name'
)->fetchAll(PDO::FETCH_ASSOC);

// Aktywni pracownicy dostępni do rezerwacji.

$employees = $pdo->query(
    "SELECT e.id, u.name, u.surname
     FROM employees e
     JOIN users u ON u.id = e.user_id
     WHERE e.active = 1 AND u.active = 1 AND u.role = 'employee'
     ORDER BY u.surname, u.name"
)->fetchAll(PDO::FETCH_ASSOC);

// Relacje usług z aktywnymi pracownikami.

$assignments = $pdo->query(
    "SELECT es.employee_id, es.service_id
     FROM employee_services es
     JOIN employees e ON e.id = es.employee_id
     JOIN users u ON u.id = e.user_id
     JOIN services s ON s.id = es.service_id
     WHERE e.active = 1 AND u.active = 1
       AND u.role = 'employee' AND s.active = 1"
)->fetchAll(PDO::FETCH_ASSOC);

// Indeksy przyspieszające weryfikację wybranej pary usługa–barber.
$serviceById = [];
$validPairs = [];

foreach ($services as $service) {
    $serviceById[(int) $service['id']] = $service;
}

foreach ($assignments as $assignment) {

    if (isset($serviceById[(int) $assignment['service_id']])) {

        $validPairs[
            (int) $assignment['service_id'] . ':' .
            (int) $assignment['employee_id']
        ] = true;

    }

}

// API kalendarza i zapisu rezerwacji.

$api = $_GET['api'] ?? ($_POST['api'] ?? '');

if ($api !== '') {

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $send = function (array $data, int $status = 200): void {

        http_response_code($status);

        echo json_encode($data, JSON_UNESCAPED_UNICODE);

        exit;

    };

    // Dostępne terminy z grafiku i zajętych przedziałów.

    if ($api === 'slots' && $_SERVER['REQUEST_METHOD'] === 'GET') {

        $serviceId = bookingId($_GET['service_id'] ?? null);
        $employeeId = bookingId($_GET['employee_id'] ?? null);

        if (!isset($validPairs[$serviceId . ':' . $employeeId])) {
            $send(['error' => 'Wybierz dostępną usługę i barbera.'], 422);
        }

        $duration = (int) $serviceById[$serviceId]['duration'];

        if (bookingClientActiveCount($pdo, (int) getUserId()) >= 3) {

            $send([
                'error' => 'Masz już 3 aktywne przyszłe rezerwacje. Aby umówić kolejną wizytę, anuluj jedną z obecnych.'
            ], 409);

        }

        $stmt = $pdo->prepare(
            'SELECT day_of_week, start_time, end_time
             FROM employee_availability
             WHERE employee_id = ?'
        );

        $stmt->execute([$employeeId]);
        $workingHours = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $busyByDate = bookingBusyIntervals(
            $pdo,
            $employeeId,
            (int) getUserId(),
            $today->format('Y-m-d'),
            $lastDate->format('Y-m-d')
        );

        $now = new DateTimeImmutable();

        $nowMinutes = (int) $now->format('H') * 60
            + (int) $now->format('i');

        $days = [];

        // Generowanie terminarza na 31 dni.

        for ($offset = 0; $offset <= 30; $offset++) {

            $date = $today->modify('+' . $offset . ' days');
            $dateString = $date->format('Y-m-d');
            $weekday = (int) $date->format('N');

            $slots = [];
            $isWorking = false;

            foreach ($workingHours as $workingHour) {

                if ((int) $workingHour['day_of_week'] !== $weekday) {
                    continue;
                }

                $isWorking = true;

                $startWork = bookingMinutes($workingHour['start_time']);
                $endWork = bookingMinutes($workingHour['end_time']);

                $first = (int) (ceil($startWork / 15) * 15);

                for (
                    $start = $first;
                    $start + $duration <= $endWork;
                    $start += 15
                ) {

                    $end = $start + $duration;

                    if ($offset === 0 && $start <= $nowMinutes) {
                        continue;
                    }

                    $conflict = false;

                    foreach ($busyByDate[$dateString] ?? [] as [$busyStart, $busyEnd]) {

                        if (bookingIntervalsOverlap($start, $end, $busyStart, $busyEnd)) {
                            $conflict = true;
                            break;
                        }

                    }

                    if (!$conflict) {
                        $slots[bookingClock($start)] = bookingClock($end);
                    }

                }

            }

            ksort($slots);

            // Stan dostępności dnia w kalendarzu.

            if (!$isWorking) {
                $status = 'closed';
            } elseif (empty($slots)) {
                $status = 'full';
            } elseif (!empty($busyByDate[$dateString])) {
                $status = 'partial';
            } else {
                $status = 'free';
            }

            $days[] = [
                'date' => $dateString,
                'status' => $status,
                'slots' => array_keys($slots)
            ];

        }

        $send(['days' => $days]);

    }

    // Zapis wizyty: walidacja, blokady i transakcja.

    if ($api === 'book' && $_SERVER['REQUEST_METHOD'] === 'POST') {

        $token = $_POST['csrf_token'] ?? '';

        if (
            !is_string($token) ||
            !hash_equals($_SESSION['csrf_token'], $token)
        ) {
            $send(['error' => 'Nieprawidłowy token formularza.'], 403);
        }

        $serviceId = bookingId($_POST['service_id'] ?? null);
        $employeeId = bookingId($_POST['employee_id'] ?? null);
        $date = bookingDate($_POST['date'] ?? null);
        $time = $_POST['time'] ?? '';

        if (
            !isset($validPairs[$serviceId . ':' . $employeeId]) ||
            !$date ||
            $date < $today ||
            $date > $lastDate ||
            !is_string($time) ||
            !preg_match('/^([01][0-9]|2[0-3]):(00|15|30|45)$/', $time)
        ) {
            $send(['error' => 'Nieprawidłowe dane rezerwacji.'], 422);
        }

        $duration = (int) $serviceById[$serviceId]['duration'];

        $start = bookingMinutes($time);
        $end = $start + $duration;

        $startDateTime = $date->setTime(
            intdiv($start, 60),
            $start % 60
        );

        if ($startDateTime <= new DateTimeImmutable()) {
            $send(['error' => 'Ten termin już minął.'], 422);
        }

        try {

            $pdo->beginTransaction();

            // Blokada klienta: ochrona limitu i kolizji przy równoczesnych zapisach.

            $stmt = $pdo->prepare(
                "SELECT id
                FROM users
                WHERE id = ?
                AND active = 1
                AND role = 'client'
                FOR UPDATE"
            );

            $stmt->execute([getUserId()]);

            if (!$stmt->fetchColumn()) {
                throw new RuntimeException('Konto klienta jest niedostępne.');
            }

            // Blokujemy pracownika na czas zapisywania wizyty

            $stmt = $pdo->prepare(
                'SELECT id FROM employees WHERE id = ? FOR UPDATE'
            );

            $stmt->execute([$employeeId]);

            if (!$stmt->fetchColumn()) {
                throw new RuntimeException('Pracownik nie jest dostępny.');
            }

            // Ponowna weryfikacja dostępności usługi.

            $stmt = $pdo->prepare(
                "SELECT 1
                 FROM employee_services es
                 JOIN employees e ON e.id = es.employee_id
                 JOIN users u ON u.id = e.user_id
                 JOIN services s ON s.id = es.service_id
                 WHERE es.employee_id = ?
                   AND es.service_id = ?
                   AND e.active = 1
                   AND u.active = 1
                   AND u.role = 'employee'
                   AND s.active = 1
                 LIMIT 1"
            );

            $stmt->execute([$employeeId, $serviceId]);

            if (!$stmt->fetchColumn()) {
                throw new RuntimeException(
                    'Wybrana usługa nie jest już dostępna.'
                );
            }

            // Kontrola grafiku pracownika.

            $stmt = $pdo->prepare(
                'SELECT 1
                 FROM employee_availability
                 WHERE employee_id = ?
                   AND day_of_week = ?
                   AND start_time <= ?
                   AND end_time >= ?
                 LIMIT 1'
            );

            $stmt->execute([
                $employeeId,
                (int) $date->format('N'),
                $time . ':00',
                bookingClock($end) . ':00'
            ]);

            if (!$stmt->fetchColumn()) {
                throw new RuntimeException(
                    'Wizyta nie mieści się w grafiku barbera.'
                );
            }

            // Kontrola kolizji pracownika.

            if (
                bookingEmployeeHasConflict(
                    $pdo,
                    $employeeId,
                    $date->format('Y-m-d'),
                    $time . ':00',
                    bookingClock($end) . ':00'
                )
            ) {

                throw new RuntimeException(
                    'Ten termin został właśnie zajęty. Wybierz inny.'
                );

            }

            // Kontrola kolizji klienta i limitu trzech wizyt.

            $policyError = bookingClientPolicyError(
                $pdo,
                (int) getUserId(),
                $date->format('Y-m-d'),
                $time . ':00',
                bookingClock($end) . ':00'
            );

            if ($policyError !== null) {
                throw new RuntimeException($policyError);
            }


            // Zapis rezerwacji i zatwierdzenie transakcji.

            $stmt = $pdo->prepare(
                "INSERT INTO reservations
                 (
                     user_id,
                     employee_id,
                     service_id,
                     reservation_date,
                     start_time,
                     end_time,
                     status
                 )
                 VALUES (?, ?, ?, ?, ?, ?, 'pending')"
            );

            $stmt->execute([
                getUserId(),
                $employeeId,
                $serviceId,
                $date->format('Y-m-d'),
                $time . ':00',
                bookingClock($end) . ':00'
            ]);

            $pdo->commit();

            $send([
                'success' => 'Rezerwacja została zapisana i oczekuje na potwierdzenie.'
            ]);

        } catch (Throwable $error) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = $error instanceof RuntimeException
                ? $error->getMessage()
                : 'Nie udało się zapisać rezerwacji.';

            $send(['error' => $message], 409);

        }

    }

    $send(['error' => 'Nieznana operacja.'], 400);

}

// Dane początkowe przekazywane do skryptu kalendarza.
$bookingData = [
    'services' => $services,
    'employees' => $employees,
    'assignments' => $assignments,
    'csrf' => $_SESSION['csrf_token']
];

?>
<!-- Widok strony: Kalendarz terminów, API dostępności i transakcyjny zapis rezerwacji -->
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Umów wizytę — Sharp Society Barber</title>

    <link rel="stylesheet" href="../css/style-client-services.css">

    <script>
        window.bookingData = <?= json_encode(
            $bookingData,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) ?>;
    </script>

    <script src="../js/script-client-services.js" defer></script>
</head>
<body>

    <!-- Zawartość główna panelu. -->

    <main class="booking-page">

        <a class="booking-back" href="index.php">← Panel klienta</a>

        <header class="booking-heading">
            <span class="booking-eyebrow">SHARP SOCIETY BARBER</span>
            <h1>Umów wizytę</h1>
            <p>Wybierz usługę, barbera i dogodny termin.</p>
        </header>

        <section class="booking-panel">

            <h2>1. Usługa i barber</h2>

            <div class="booking-fields">

                <div class="booking-field">
                    <label for="service">Usługa</label>

                    <select id="service">
                        <option value="">Wybierz usługę</option>
                    </select>
                </div>

                <div class="booking-field">
                    <label for="employee">Barber</label>

                    <select id="employee">
                        <option value="">Wybierz barbera</option>
                    </select>
                </div>

            </div>

            <p class="booking-hint">
                Możesz zacząć od usługi albo od barbera —
                lista dopasuje się automatycznie.
            </p>

        </section>

        <section class="booking-panel">

            <div class="booking-section-heading">

                <h2>2. Wybierz termin</h2>

                <div class="booking-navigation">
                    <button type="button" id="prev-day" aria-label="Poprzedni dzień">‹</button>
                    <button type="button" id="next-day" aria-label="Następny dzień">›</button>
                </div>

            </div>

            <div class="booking-calendar" id="calendar">
                <p class="booking-empty">
                    Wybierz usługę i barbera, aby zobaczyć terminarz.
                </p>
            </div>

            <div class="booking-legend">
                <span><i class="legend-free"></i> Wolne</span>
                <span><i class="legend-partial"></i> Częściowo zajęte</span>
                <span><i class="legend-full"></i> Brak terminów</span>
                <span><i class="legend-closed"></i> Nie pracuje</span>
            </div>

        </section>

        <section class="booking-panel booking-summary">

            <h2>3. Podsumowanie</h2>

            <dl>

                <div>
                    <dt>Usługa</dt>
                    <dd id="summary-service">Nie wybrano</dd>
                </div>

                <div>
                    <dt>Barber</dt>
                    <dd id="summary-employee">Nie wybrano</dd>
                </div>

                <div>
                    <dt>Termin</dt>
                    <dd id="summary-date">Nie wybrano</dd>
                </div>

                <div>
                    <dt>Czas trwania</dt>
                    <dd id="summary-duration">—</dd>
                </div>

                <div class="booking-total">
                    <dt>Do zapłaty w salonie</dt>
                    <dd id="summary-price">—</dd>
                </div>

            </dl>

            <p
                id="booking-message"
                class="booking-message"
                role="status"
                aria-live="polite"
            ></p>

            <button
                type="button"
                id="book-button"
                class="booking-submit"
                disabled
            >
                Rezerwuj
            </button>

        </section>

    </main>

</body>
</html>
