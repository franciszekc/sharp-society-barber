# Reorganizacja kodu — 20.11.2026

## Zakres

- Nazwy plików CSS: `style-<moduł>-<strona>.css`; style współdzielone: `*-common.css`.
- Nazwy skryptów JS: `script-<moduł>-<strona>.js`.
- Nazwy plików PHP złożone z kilku wyrazów: myślniki zamiast podkreśleń.
- Poprawione wszystkie odwołania do przeniesionych plików w PHP/HTML.
- Dodane rzeczowe komentarze-sekcje w plikach PHP, CSS, JS, SQL i DBML.
- Nawigacja administratora ma jeden formularz wylogowania POST z tokenem CSRF, używany na wszystkich stronach panelu.
- Wylogowanie na liście rezerwacji pracownika korzysta z POST zamiast linku GET.
- Usunięty nieaktualny komunikat o niedostępności rezerwacji z panelu klienta.
- Oddzielone style listy rezerwacji pracownika od wspólnych stylów panelu.
- Zastąpiony nieadekwatny plik `.gitignore` dla Autotools regułami dla projektu PHP.

## Zmiany nazw plików

| Stara ścieżka | Nowa ścieżka |
|---|---|
| `includes/admin_nav.php` | `includes/admin-nav.php` |
| `includes/profile_functions.php` | `includes/profile-functions.php` |
| `public/admin/employee_services.php` | `public/admin/employee-services.php` |
| `public/css/style-admin.css` | `public/css/style-admin-index.css` |
| `public/css/style-availability.css` | `public/css/style-admin-availability.css` |
| `public/css/style-booking.css` | `public/css/style-client-services.css` |
| `public/css/style-categories.css` | `public/css/style-admin-categories.css` |
| `public/css/style-chgpasswd.css` | `public/css/style-public-chgpasswd.css` |
| `public/css/style-client.css` | `public/css/style-client-index.css` |
| `public/css/style-employee-services.css` | `public/css/style-admin-employee-services.css` |
| `public/css/style-employee.css` | `public/css/style-employee-common.css` |
| `public/css/style-employees.css` | `public/css/style-admin-common.css` |
| `public/css/style-login.css` | `public/css/style-public-login.css` |
| `public/css/style-profile.css` | `public/css/style-public-profile.css` |
| `public/css/style-register.css` | `public/css/style-public-register.css` |
| `public/css/style-reservations.css` | `public/css/style-admin-reservations.css` |
| `public/css/style-services.css` | `public/css/style-admin-services.css` |
| `public/css/style-users.css` | `public/css/style-admin-users.css` |
| `public/js/booking.js` | `public/js/script-client-services.js` |
| `public/js/script-chgpasswd.js` | `public/js/script-public-chgpasswd.js` |
| `public/js/script-profile.js` | `public/js/script-public-profile.js` |
| `public/js/script-register.js` | `public/js/script-public-register.js` |
| `public/js/script-services.js` | `public/js/script-admin-services.js` |
| `public/js/script-users.js` | `public/js/script-admin-users.js` |

Dodatkowo utworzono `public/css/style-employee-reservations.css` z wydzielonych stylów.

## Usunięte pliki

- `includes/header.php`, `includes/footer.php` — oba pliki były puste i nie miały odwołań.
- `public/client/availability.php` — nieużywany stary widok; aktualny kalendarz korzysta z `public/client/services.php?api=slots`.
- `public/assets/images/.gitkeep` — pusty katalog bez używanych zasobów.
- Usunięto nieużywane reguły CSS starego widoku dostępności klienta i nieaktualnego nagłówka w panelu klienta.

## Co celowo pozostało bez zmian

- Nazwy tabel i kolumn bazy danych (np. `employee_services`, `reservation_date`).
- Nazwy parametrów GET/POST, zmiennych i funkcji PHP/JS, które nie są nazwami plików.
- Logika transakcji rezerwacji, statusy i limit trzech przyszłych wizyt.
- Diagram ERD `database/erd.png` (plik graficzny, bez możliwości komentarzy wewnątrz).

## Zastosowanie zmian w istniejącym repozytorium

1. Zrób commit bieżącego stanu i utwórz osobną gałąź refaktoryzacji.
2. Zachowaj swój katalog `.git` — nie znajduje się on w tym archiwum.
3. Skopiuj pliki z archiwum do repozytorium i usuń stare pliki z tabeli zmian nazw oraz listy usuniętych plików.
4. Wykonaj `git status`, `git diff --check`, `git add -A` i przejrzyj zmiany przed commitem.
5. Sprawdź logowanie/wylogowanie wszystkich ról, strony admina, formularz klienta, AJAX, rezerwacje i zmianę statusów.

Nie importuj ponownie `database/database.sql` do działającej bazy bez kopii zapasowej: skrypt usuwa bazę przed utworzeniem nowej.

## Sprawdzenia automatyczne

- Walidacja składni wszystkich plików PHP (`php -l`).
- Walidacja składni wszystkich skryptów JS (`node --check`).
- Sprawdzenie istnienia lokalnych zasobów i ścieżek `require`/`include`.

Testów działania na lokalnym Apache i MySQL nie można wykonać bez bazy i kont użytkowników.

## Wykaz plików

- `.gitignore`
- `README.md`
- `config/database.php`
- `database/database.sql`
- `database/erd.dbml`
- `database/erd.png`
- `includes/admin-nav.php`
- `includes/auth.php`
- `includes/booking-rules.php`
- `includes/profile-functions.php`
- `public/admin/availability.php`
- `public/admin/categories.php`
- `public/admin/employee-services.php`
- `public/admin/employees.php`
- `public/admin/index.php`
- `public/admin/reservations.php`
- `public/admin/services.php`
- `public/admin/users.php`
- `public/chgpasswd.php`
- `public/client/cancel-reservation.php`
- `public/client/index.php`
- `public/client/reservations.php`
- `public/client/services.php`
- `public/css/style-admin-availability.css`
- `public/css/style-admin-categories.css`
- `public/css/style-admin-common.css`
- `public/css/style-admin-employee-services.css`
- `public/css/style-admin-index.css`
- `public/css/style-admin-nav.css`
- `public/css/style-admin-reservations.css`
- `public/css/style-admin-services.css`
- `public/css/style-admin-users.css`
- `public/css/style-client-index.css`
- `public/css/style-client-reservations.css`
- `public/css/style-client-services.css`
- `public/css/style-employee-common.css`
- `public/css/style-employee-reservations.css`
- `public/css/style-public-chgpasswd.css`
- `public/css/style-public-login.css`
- `public/css/style-public-profile.css`
- `public/css/style-public-register.css`
- `public/employee/index.php`
- `public/employee/reservations.php`
- `public/js/script-admin-services.js`
- `public/js/script-admin-users.js`
- `public/js/script-client-services.js`
- `public/js/script-public-chgpasswd.js`
- `public/js/script-public-profile.js`
- `public/js/script-public-register.js`
- `public/login.php`
- `public/logout.php`
- `public/profile.php`
- `public/register.php`
