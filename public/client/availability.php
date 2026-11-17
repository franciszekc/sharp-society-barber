
<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin('../login.php');
refreshUserSession($pdo, '../login.php');
requireRole('client');

date_default_timezone_set('Europe/Warsaw');

function escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function toMinutes(string $time): int
{
    $parts = explode(':', $time);

    return (int) $parts[0] * 60 + (int) $parts[1];
}

function toTime(int $minutes): string
{
    return sprintf(
        '%02d:%02d',
        intdiv($minutes, 60),
        $minutes % 60
    );
}

// Odczytanie identyfikatorów z adresu

$serviceId = filter_var(
    $_GET['service_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$employeeId = filter_var(
    $_GET['employee_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$serviceId || !$employeeId) {
    http_response_code(400);
    exit('Nieprawidłowa usługa lub pracownik.');
}

// Sprawdzenie usługi i przypisanego pracownika

$stmt = $pdo->prepare(
    "SELECT
        s.id AS service_id,
        s.name AS service_name,
        s.duration,
        s.price,
        e.id AS employee_id,
        u.name AS employee_name,
        u.surname AS employee_surname
     FROM services s
     INNER JOIN employee_services es
         ON es.service_id = s.id
     INNER JOIN employees e
         ON e.id = es.employee_id
     INNER JOIN users u
         ON u.id = e.user_id
     WHERE s.id = ?
       AND e.id = ?
       AND s.active = 1
       AND e.active = 1
       AND u.active = 1
       AND u.role = 'employee'
     LIMIT 1"
);

$stmt->execute([$serviceId, $employeeId]);
$selection = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$selection) {
    http_response_code(404);
    exit('Wybrana usługa lub pracownik nie są dostępni.');
}

// Ustalenie daty wizyty

$today = new DateTimeImmutable('today');
$maxDate = $today->modify('+30 days');

$selectedDate = $today;
$error = '';

if (isset($_GET['date'])) {

    $dateValue = $_GET['date'];

    $date = is_string($dateValue)
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue)
        : false;

    if (
        $date === false ||
        $date->format('Y-m-d') !== $dateValue ||
        $date < $today ||
        $date > $maxDate
    ) {
        $error = 'Wybierz poprawną datę z najbliższych 30 dni.';
    } else {
        $selectedDate = $date;
    }

}

$dateString = $selectedDate->format('Y-m-d');
$dayOfWeek = (int) $selectedDate->format('N');
$duration = (int) $selection['duration'];

// Pobranie godzin pracy barbera w danym dniu tygodnia

$stmt = $pdo->prepare(
    'SELECT start_time, end_time
     FROM employee_availability
     WHERE employee_id = ?
       AND day_of_week = ?
     ORDER BY start_time'
);

$stmt->execute([$employeeId, $dayOfWeek]);
$workingHours = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pobranie istniejących rezerwacji w wybranym dniu

$stmt = $pdo->prepare(
    "SELECT start_time, end_time
     FROM reservations
     WHERE employee_id = ?
       AND reservation_date = ?
       AND status IN ('pending', 'confirmed', 'completed')
     ORDER BY start_time"
);

$stmt->execute([$employeeId, $dateString]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Zamiana zajętych godzin na minuty

$busySlots = [];

foreach ($reservations as $reservation) {

    $busySlots[] = [
        'start' => toMinutes($reservation['start_time']),
        'end' => toMinutes($reservation['end_time'])
    ];

}

// Generowanie dostępnych terminów co 15 minut

$availableSlots = [];
$step = 15;

$now = new DateTimeImmutable();
$currentMinutes = (int) $now->format('H') * 60
    + (int) $now->format('i');

foreach ($workingHours as $workingHour) {

    $workStart = toMinutes($workingHour['start_time']);
    $workEnd = toMinutes($workingHour['end_time']);

    // Zaokrąglenie początku grafiku do kolejnego kwadransa

    $firstStart = (int) (ceil($workStart / $step) * $step);

    for (
        $start = $firstStart;
        $start + $duration <= $workEnd;
        $start += $step
    ) {

        $end = $start + $duration;

        // Pomijamy godziny, które już minęły

        if (
            $dateString === $today->format('Y-m-d') &&
            $start <= $currentMinutes
        ) {
            continue;
        }

        $hasConflict = false;

        foreach ($busySlots as $busySlot) {

            if (
                $start < $busySlot['end'] &&
                $end > $busySlot['start']
            ) {
                $hasConflict = true;
                break;
            }

        }

        if (!$hasConflict) {

            $availableSlots[$start] = [
                'start' => toTime($start),
                'end' => toTime($end)
            ];

        }

    }

}

ksort($availableSlots);

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wolne terminy — Sharp Society Barber</title>
    <link rel="stylesheet" href="../css/style-client.css">
</head>
<body>

    <main class="container">

        <h1>Sharp Society Barber</h1>

        <h2>Wybierz termin wizyty</h2>

        <p>
            <strong>Usługa:</strong>
            <?= escape($selection['service_name']) ?>
        </p>

        <p>
            <strong>Barber:</strong>
            <?= escape(
                $selection['employee_name'] . ' ' .
                $selection['employee_surname']
            ) ?>
        </p>

        <p>
            <strong>Czas trwania:</strong>
            <?= $duration ?> minut
        </p>

        <p>
            <strong>Cena:</strong>
            <?= number_format(
                (float) $selection['price'],
                2,
                ',',
                ' '
            ) ?> zł
        </p>

        <h3>Data wizyty</h3>

        <form method="GET">

            <input
                type="hidden"
                name="service_id"
                value="<?= (int) $serviceId ?>"
            >

            <input
                type="hidden"
                name="employee_id"
                value="<?= (int) $employeeId ?>"
            >

            <label for="date">Wybierz datę:</label>

            <input
                type="date"
                id="date"
                name="date"
                min="<?= $today->format('Y-m-d') ?>"
                max="<?= $maxDate->format('Y-m-d') ?>"
                value="<?= $dateString ?>"
                required
            >

            <button type="submit">Sprawdź terminy</button>

        </form>

        <?php if ($error !== ''): ?>

            <p class="date-error"><?= escape($error) ?></p>

        <?php endif; ?>

        <h3>Wolne godziny — <?= escape($dateString) ?></h3>

        <?php if (empty($workingHours)): ?>

            <p>Wybrany pracownik nie pracuje tego dnia.</p>

        <?php elseif (empty($availableSlots)): ?>

            <p>Brak wolnych terminów w wybranym dniu.</p>

        <?php else: ?>

            <ul class="time-slots">

                <?php foreach ($availableSlots as $slot): ?>

                    <li>
                        <strong><?= escape($slot['start']) ?></strong>
                        –
                        <?= escape($slot['end']) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

            <p>
                W następnym kroku dodamy możliwość
                zarezerwowania wybranej godziny.
            </p>

        <?php endif; ?>

        <div class="menu">
            <a
                href="services.php?service_id=<?= (int) $serviceId ?>&employee_id=<?= (int) $employeeId ?>"
            >
                Wróć do wyboru usługi i barbera
            </a>
        </div>

    </main>

</body>
</html>
