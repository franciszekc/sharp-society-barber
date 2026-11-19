
<?php

function bookingMinutes(string $time): int
{
    [$hour, $minute] = array_map('intval', explode(':', $time));

    return $hour * 60 + $minute;
}

function bookingClock(int $minutes): string
{
    return sprintf(
        '%02d:%02d',
        intdiv($minutes, 60),
        $minutes % 60
    );
}

function bookingDate($value): ?DateTimeImmutable
{
    if (!is_string($value)) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date && $date->format('Y-m-d') === $value
        ? $date
        : null;
}

function bookingIntervalsOverlap(
    int $start,
    int $end,
    int $busyStart,
    int $busyEnd
): bool {
    return $start < $busyEnd && $end > $busyStart;
}

// Zajęte godziny barbera i zalogowanego klienta

function bookingBusyIntervals(
    PDO $pdo,
    int $employeeId,
    int $userId,
    string $fromDate,
    string $toDate
): array {

    $stmt = $pdo->prepare(
        "SELECT reservation_date, start_time, end_time
         FROM reservations
         WHERE reservation_date BETWEEN ? AND ?
           AND (
               (
                   employee_id = ?
                   AND status IN ('pending', 'confirmed', 'completed')
               )
               OR
               (
                   user_id = ?
                   AND status IN ('pending', 'confirmed')
               )
           )"
    );

    $stmt->execute([
        $fromDate,
        $toDate,
        $employeeId,
        $userId
    ]);

    $busyByDate = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $reservation) {

        $busyByDate[$reservation['reservation_date']][] = [
            bookingMinutes($reservation['start_time']),
            bookingMinutes($reservation['end_time'])
        ];

    }

    return $busyByDate;
}

// Konflikt z rezerwacją pracownika

function bookingEmployeeHasConflict(
    PDO $pdo,
    int $employeeId,
    string $date,
    string $startTime,
    string $endTime
): bool {

    $stmt = $pdo->prepare(
        "SELECT 1
         FROM reservations
         WHERE employee_id = ?
           AND reservation_date = ?
           AND status IN ('pending', 'confirmed', 'completed')
           AND start_time < ?
           AND end_time > ?
         LIMIT 1"
    );

    $stmt->execute([
        $employeeId,
        $date,
        $endTime,
        $startTime
    ]);

    return (bool) $stmt->fetchColumn();
}

// Konflikt z inną rezerwacją klienta

function bookingClientHasConflict(
    PDO $pdo,
    int $userId,
    string $date,
    string $startTime,
    string $endTime
): bool {

    $stmt = $pdo->prepare(
        "SELECT 1
         FROM reservations
         WHERE user_id = ?
           AND reservation_date = ?
           AND status IN ('pending', 'confirmed')
           AND start_time < ?
           AND end_time > ?
         LIMIT 1"
    );

    $stmt->execute([
        $userId,
        $date,
        $endTime,
        $startTime
    ]);

    return (bool) $stmt->fetchColumn();
}

// Liczba aktywnych przyszłych rezerwacji klienta

function bookingClientActiveCount(
    PDO $pdo,
    int $userId
): int {

    $now = new DateTimeImmutable(
        'now',
        new DateTimeZone('Europe/Warsaw')
    );

    $today = $now->format('Y-m-d');
    $currentTime = $now->format('H:i:s');

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM reservations
         WHERE user_id = ?
           AND status IN ('pending', 'confirmed')
           AND (
               reservation_date > ?
               OR (
                   reservation_date = ?
                   AND start_time > ?
               )
           )"
    );

    $stmt->execute([
        $userId,
        $today,
        $today,
        $currentTime
    ]);

    return (int) $stmt->fetchColumn();
}

// Sprawdzenie ograniczeń klienta przed zapisem

function bookingClientPolicyError(
    PDO $pdo,
    int $userId,
    string $date,
    string $startTime,
    string $endTime
): ?string {

    if (bookingClientActiveCount($pdo, $userId) >= 3) {

        return 'Możesz mieć maksymalnie 3 aktywne przyszłe rezerwacje.';

    }

    if (
        bookingClientHasConflict(
            $pdo,
            $userId,
            $date,
            $startTime,
            $endTime
        )
    ) {

        return 'Masz już inną wizytę w wybranym terminie.';

    }

    return null;
}
