
<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin('../login.php');
refreshUserSession($pdo, '../login.php');
requireRole('admin');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$errors = [];
$success = $_SESSION['users_success'] ?? '';


$returnSearch = trim(
    is_string($_POST['return_search'] ?? null)
        ? $_POST['return_search']
        : ''
);

$returnUrl = 'users.php';

if ($returnSearch !== '') {
    $returnUrl .= '?' . http_build_query([
        'search' => mb_substr($returnSearch, 0, 100)
    ]);
}


unset($_SESSION['users_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Nieprawidłowy token formularza.');
    }

    $action = $_POST['action'] ?? '';

    $id = filter_var(
        $_POST['id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (!$id) {
        $errors[] = 'Nieprawidłowy identyfikator użytkownika.';
    } else {

        $stmt = $pdo->prepare(
            'SELECT id, role, active FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $errors[] = 'Nie znaleziono użytkownika.';
        } elseif ($id === getUserId()) {
            $errors[] = 'Nie możesz zmieniać własnej roli ani aktywności.';
        } elseif ($action === 'toggle') {

            $stmt = $pdo->prepare(
                'UPDATE users SET active = ? WHERE id = ?'
            );
            $stmt->execute([
                (int) $user['active'] === 1 ? 0 : 1,
                $id
            ]);

            $_SESSION['users_success'] = 'Zmieniono aktywność konta.';

            header('Location: ' . $returnUrl);
            exit;

        } elseif ($action === 'role') {

            $role = $_POST['role'] ?? '';

            if (!is_string($role) || !in_array($role, ['client', 'admin'], true)) {
                $errors[] = 'Nieprawidłowa rola użytkownika.';
            } elseif ($user['role'] === 'employee') {
                $errors[] = 'Rolą pracownika zarządzaj w module pracowników.';
            } else {

                $stmt = $pdo->prepare(
                    'UPDATE users SET role = ? WHERE id = ?'
                );
                $stmt->execute([$role, $id]);

                $_SESSION['users_success'] = 'Zmieniono rolę użytkownika.';

                header('Location: ' . $returnUrl);
                exit;
            }

        } else {
            $errors[] = 'Nieprawidłowa operacja.';
        }
    }
}

$search = trim(
    is_string($_GET['search'] ?? null) ? $_GET['search'] : ''
);

if (mb_strlen($search) > 100) {
    $search = mb_substr($search, 0, 100);
}

$sql = '
    SELECT
        u.id,
        u.name,
        u.surname,
        u.email,
        u.phone,
        u.role,
        u.active,
        u.created_at
    FROM users u
';

$params = [];

if ($search !== '') {
    $sql .= '
        WHERE u.name LIKE ?
           OR u.surname LIKE ?
           OR u.email LIKE ?
           OR u.phone LIKE ?
    ';

    $pattern = '%' . $search . '%';
    $params = [$pattern, $pattern, $pattern, $pattern];
}

$sql .= ' ORDER BY u.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Użytkownicy — Sharp Society Barber</title>
    <link rel="stylesheet" href="../css/style-users.css">
    <link rel="stylesheet" href="../css/style-admin-nav.css">
</head>
<body>

    <header class="topbar">
        <h1>Sharp Society Barber</h1>
        <?php require '../../includes/admin_nav.php'; ?>
    </header>

    <main class="container">

        <h2>Zarządzanie użytkownikami</h2>

        <?php foreach ($errors as $error): ?>
            <p class="message-error"><?= e($error) ?></p>
        <?php endforeach; ?>

        <?php if ($success !== ''): ?>
            <p class="message-success"><?= e($success) ?></p>
        <?php endif; ?>

        <section class="card">

            <h3>Wyszukaj użytkownika</h3>

            <form method="GET" class="search-form">
                <input
                    type="search"
                    name="search"
                    placeholder="Imię, nazwisko, e-mail lub telefon"
                    value="<?= e($search) ?>"
                >

                <button type="submit">Szukaj</button>
                <a href="users.php">Wyczyść</a>
            </form>

        </section>

        <section class="card">

            <h3>Lista użytkowników (<?= count($users) ?>)</h3>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Imię i nazwisko</th>
                            <th>E-mail</th>
                            <th>Telefon</th>
                            <th>Rola</th>
                            <th>Status</th>
                            <th>Data rejestracji</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($users as $user): ?>

                            <?php $isSelf = (int) $user['id'] === getUserId(); ?>

                            <tr id="user-<?= (int) $user['id'] ?>">
                                <td><?= (int) $user['id'] ?></td>

                                <td>
                                    <?= e($user['name'] . ' ' . $user['surname']) ?>
                                    <?php if ($isSelf): ?>
                                        <span class="self-label">(Ty)</span>
                                    <?php endif; ?>
                                </td>

                                <td><?= e($user['email']) ?></td>
                                <td><?= e($user['phone']) ?></td>

                                <td>
                                    <?php
                                        $roleNames = [
                                            'client' => 'Klient',
                                            'employee' => 'Pracownik',
                                            'admin' => 'Administrator'
                                        ];
                                    ?>

                                    <?= e($roleNames[$user['role']] ?? $user['role']) ?>
                                </td>

                                <td>
                                    <span class="<?= (int) $user['active'] === 1 ? 'active' : 'inactive' ?>">
                                        <?= (int) $user['active'] === 1 ? 'Aktywne' : 'Nieaktywne' ?>
                                    </span>
                                </td>

                                <td><?= e($user['created_at']) ?></td>

                                <td class="actions">

                                    <?php if ($isSelf): ?>

                                        <span class="disabled">Własne konto</span>

                                    <?php else: ?>

                                        <?php if ($user['role'] !== 'employee'): ?>

                                            <form method="POST" class="role-form">
                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e($_SESSION['csrf_token']) ?>"
                                                >                                                
                                                <input
                                                    type="hidden"
                                                    name="return_search"
                                                    value="<?= e($search) ?>"
                                                >
                                                <input type="hidden" name="action" value="role">
                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int) $user['id'] ?>"
                                                >

                                                <select name="role" aria-label="Rola użytkownika">
                                                    <option value="client" <?= $user['role'] === 'client' ? 'selected' : '' ?>>
                                                        Klient
                                                    </option>
                                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>
                                                        Administrator
                                                    </option>
                                                </select>

                                                <button
                                                    type="submit"
                                                    onclick="return confirm('Czy na pewno zmienić rolę tego użytkownika?');"
                                                >
                                                    Zapisz rolę
                                                </button>
                                            </form>

                                        <?php else: ?>
                                            <span class="disabled">Rola pracownika</span>
                                        <?php endif; ?>

                                        <form method="POST">
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION['csrf_token']) ?>"
                                            >                                            
                                            <input
                                                type="hidden"
                                                name="return_search"
                                                value="<?= e($search) ?>"
                                            >
                                            <input type="hidden" name="action" value="toggle">
                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $user['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="toggle-btn"
                                                onclick="return confirm('Czy na pewno zmienić aktywność tego konta?');"
                                            >
                                                <?= (int) $user['active'] === 1 ? 'Dezaktywuj' : 'Aktywuj' ?>
                                            </button>
                                        </form>

                                    <?php endif; ?>

                                </td>
                            </tr>

                        <?php endforeach; ?>

                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8">Nie znaleziono użytkowników.</td>
                            </tr>
                        <?php endif; ?>

                    </tbody>
                </table>
            </div>

        </section>

    </main>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        const storageKey = 'users_scroll_position';

        const savedPosition = sessionStorage.getItem(storageKey);

        if (savedPosition !== null) {
            sessionStorage.removeItem(storageKey);

            try {
                const position = JSON.parse(savedPosition);
                const row = document.getElementById('user-' + position.id);

                if (row) {
                    requestAnimationFrame(function () {
                        const currentTop = row.getBoundingClientRect().top;

                        window.scrollBy({
                            top: currentTop - position.top,
                            behavior: 'instant'
                        });
                    });
                }
            } catch (error) {
                console.error('Nie udało się przywrócić pozycji listy.', error);
            }
        }

        const actionForms = document.querySelectorAll(
            '.actions form'
        );

        actionForms.forEach(function (form) {

            form.addEventListener('submit', function (event) {

                if (event.defaultPrevented) {
                    return;
                }

                const row = form.closest('tr');

                if (!row) {
                    return;
                }

                const id = row.id.replace('user-', '');

                sessionStorage.setItem(
                    storageKey,
                    JSON.stringify({
                        id: id,
                        top: row.getBoundingClientRect().top
                    })
                );
            });

        });

    });
</script>

</body>
</html>
