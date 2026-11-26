<?php
declare(strict_types=1);

require __DIR__ . '/db.php';

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();

const SEVERITIES = ['critical', 'high', 'medium', 'low'];

function input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    return is_array($data) ? $data : [];
}

function fail(string $message, int $status = 400): never
{
    json_response(['success' => false, 'error' => $message], $status);
}

function ok($data = null): never
{
    json_response(['success' => true, 'data' => $data]);
}

function require_login(): int
{
    if (empty($_SESSION['user_id'])) {
        fail('Authentication Error: please log in again.', 401);
    }
    return (int) $_SESSION['user_id'];
}

function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        fail('POST required', 405);
    }
}

function public_user(array $u): array
{
    return [
        'id'        => (int) $u['id'],
        'username'  => $u['username'],
        'full_name' => $u['full_name'],
        'email'     => $u['email'],
        'role'      => $u['role'],
    ];
}

const REPORT_SELECT = "
    SELECT r.id, r.problem_type_id, r.latitude, r.longitude, r.address, r.description,
           r.severity, r.status, r.votes_up, r.votes_down, r.view_count,
           r.created_at, r.updated_at,
           pt.name AS problem_type, pt.title AS problem_title, pt.emoji, pt.color,
           u.username
    FROM reports r
    JOIN problem_types pt ON pt.id = r.problem_type_id
    LEFT JOIN users u ON u.id = r.user_id
";

$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        case 'types':
            $rows = db()->query('SELECT id, name, title, emoji, color FROM problem_types WHERE is_active = 1 ORDER BY id')->fetchAll();
            ok($rows);

        case 'list':
            $where = [];
            $params = [];

            if (!empty($_GET['types'])) {
                $names = array_filter(array_map('trim', explode(',', (string) $_GET['types'])));
                if ($names) {
                    $ph = implode(',', array_fill(0, count($names), '?'));
                    $where[] = "pt.name IN ($ph)";
                    array_push($params, ...$names);
                } 
            }
            if (!empty($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['start_date'])) {
                $where[] = 'r.created_at >= ?';
                $params[] = $_GET['start_date'] . ' 00:00:00';
            }
            if (!empty($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['end_date'])) {
                $where[] = 'r.created_at <= ?';
                $params[] = $_GET['end_date'] . ' 23:59:59';
            }

            $sql = REPORT_SELECT . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY r.created_at DESC LIMIT 500';
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            ok($stmt->fetchAll());

        case 'nearby':
            $lat = filter_var($_GET['lat'] ?? null, FILTER_VALIDATE_FLOAT);
            $lng = filter_var($_GET['lng'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($lat === false || $lng === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                fail('Invalid coordinates');
            }
            $radius = min(max((float) ($_GET['radius'] ?? 50), 0.1), 500);
            $limit  = min(max((int) ($_GET['limit'] ?? 50), 1), 200);

            $sql = "SELECT * FROM (
                        SELECT r.id, r.problem_type_id, r.latitude, r.longitude, r.address, r.description,
                               r.severity, r.status, r.votes_up, r.votes_down, r.view_count, r.created_at,
                               pt.name AS problem_type, pt.title AS problem_title, pt.emoji, u.username,
                               (6371 * ACOS(LEAST(1, COS(RADIANS(:lat1)) * COS(RADIANS(r.latitude))
                                 * COS(RADIANS(r.longitude) - RADIANS(:lng))
                                 + SIN(RADIANS(:lat2)) * SIN(RADIANS(r.latitude))))) AS distance_km
                        FROM reports r
                        JOIN problem_types pt ON pt.id = r.problem_type_id
                        LEFT JOIN users u ON u.id = r.user_id
                    ) t
                    WHERE distance_km <= :radius
                    ORDER BY distance_km ASC
                    LIMIT $limit";
            $stmt = db()->prepare($sql);
            $stmt->execute([':lat1' => $lat, ':lat2' => $lat, ':lng' => $lng, ':radius' => $radius]);
            ok($stmt->fetchAll());

        case 'get':
            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                fail('Invalid report id');
            }
            $pdo = db();
            $pdo->prepare('UPDATE reports SET view_count = view_count + 1 WHERE id = ?')->execute([$id]);

            $stmt = $pdo->prepare(REPORT_SELECT . ' WHERE r.id = ?');
            $stmt->execute([$id]);
            $report = $stmt->fetch();
            if (!$report) {
                fail('Report not found', 404);
            }

            $c = $pdo->prepare('SELECT c.id, c.comment, c.created_at, u.username
                                FROM comments c LEFT JOIN users u ON u.id = c.user_id
                                WHERE c.report_id = ? AND c.is_deleted = 0 ORDER BY c.created_at ASC');
            $c->execute([$id]);
            $report['comments'] = $c->fetchAll();

            $i = $pdo->prepare('SELECT id, image_url, alt_text FROM report_images WHERE report_id = ?');
            $i->execute([$id]);
            $report['images'] = $i->fetchAll();

            ok($report);

        case 'create':
            require_post();
            $userId = require_login();   // user comes from the session, never from the client
            $in = input();

            $typeId = (int) ($in['problem_type_id'] ?? 0);
            $desc   = trim((string) ($in['description'] ?? ''));
            $sev    = (string) ($in['severity'] ?? '');
            $lat    = filter_var($in['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
            $lng    = filter_var($in['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
            $addr   = mb_substr(trim((string) ($in['address'] ?? '')), 0, 255);

            if ($typeId <= 0 || $desc === '' || !in_array($sev, SEVERITIES, true) || $lat === false || $lng === false) {
                fail('Missing or invalid fields');
            }
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                fail('Invalid coordinates');
            }
            if (mb_strlen($desc) > 2000) {
                fail('Description is too long (max 2000 characters)');
            }

            $pdo = db();
            $chk = $pdo->prepare('SELECT id FROM problem_types WHERE id = ? AND is_active = 1');
            $chk->execute([$typeId]);
            if (!$chk->fetch()) {
                fail('Unknown problem type');
            }

            $pdo->prepare('INSERT INTO reports (problem_type_id, user_id, latitude, longitude, address, description, severity)
                           VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$typeId, $userId, $lat, $lng, $addr !== '' ? $addr : null, $desc, $sev]);

            ok(['id' => (int) $pdo->lastInsertId()]);

        case 'vote':
            require_post();
            $userId = require_login();
            $in = input();
            $reportId = (int) ($in['report_id'] ?? 0);
            $type = (string) ($in['vote_type'] ?? '');
            if ($reportId <= 0 || !in_array($type, ['up', 'down'], true)) {
                fail('Invalid vote');
            }

            $pdo = db();
            $pdo->beginTransaction();
            try {
                $r = $pdo->prepare('SELECT id FROM reports WHERE id = ? FOR UPDATE');
                $r->execute([$reportId]);
                if (!$r->fetch()) {
                    $pdo->rollBack();
                    fail('Report not found', 404);
                }

                $ex = $pdo->prepare('SELECT id, vote_type FROM report_votes WHERE report_id = ? AND user_id = ?');
                $ex->execute([$reportId, $userId]);
                $existing = $ex->fetch();

                if ($existing && $existing['vote_type'] === $type) {
                    // same button again = remove the vote
                    $pdo->prepare('DELETE FROM report_votes WHERE id = ?')->execute([$existing['id']]);
                } elseif ($existing) {
                    $pdo->prepare('UPDATE report_votes SET vote_type = ? WHERE id = ?')->execute([$type, $existing['id']]);
                } else {
                    $pdo->prepare('INSERT INTO report_votes (report_id, user_id, ip_address, vote_type) VALUES (?, ?, ?, ?)')
                        ->execute([$reportId, $userId, $_SERVER['REMOTE_ADDR'] ?? null, $type]);
                }

                $pdo->prepare("UPDATE reports SET
                        votes_up   = (SELECT COUNT(*) FROM report_votes WHERE report_id = ? AND vote_type = 'up'),
                        votes_down = (SELECT COUNT(*) FROM report_votes WHERE report_id = ? AND vote_type = 'down')
                        WHERE id = ?")
                    ->execute([$reportId, $reportId, $reportId]);

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            $s = $pdo->prepare('SELECT votes_up, votes_down FROM reports WHERE id = ?');
            $s->execute([$reportId]);
            ok($s->fetch());

        case 'register':
            require_post();
            $in = input();
            $fullName = trim((string) ($in['full_name'] ?? ''));
            $username = trim((string) ($in['username'] ?? ''));
            $email    = strtolower(trim((string) ($in['email'] ?? '')));
            $password = (string) ($in['password'] ?? '');

            if ($fullName === '' || mb_strlen($fullName) > 100) {
                fail('Invalid full name');
            }
            if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
                fail('Username must be 3-50 characters (letters, digits, _ . -)');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
                fail('Invalid email address');
            }
            if (strlen($password) < 8) {
                fail('Password must be at least 8 characters');
            }

            $pdo = db();
            $dup = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
            $dup->execute([$username, $email]);
            if ($dup->fetch()) {
                fail('Username or email already in use', 409);
            }

            $pdo->prepare('INSERT INTO users (username, full_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)')
                ->execute([$username, $fullName, $email, password_hash($password, PASSWORD_DEFAULT), 'user']);
            $id = (int) $pdo->lastInsertId();

            session_regenerate_id(true);
            $_SESSION['user_id'] = $id;

            ok(['id' => $id, 'username' => $username, 'full_name' => $fullName, 'email' => $email, 'role' => 'user']);

        case 'login':
            require_post();
            $in = input();
            $login    = strtolower(trim((string) ($in['email'] ?? $in['username'] ?? '')));
            $password = (string) ($in['password'] ?? '');
            if ($login === '' || $password === '') {
                fail('Please fill in all fields');
            }

            $stmt = db()->prepare('SELECT * FROM users WHERE (email = ? OR username = ?) AND is_active = 1 LIMIT 1');
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();

            if (!$user || !$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
                fail('Invalid email or password', 401);
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            ok(public_user($user));

        case 'logout':
            $_SESSION = [];
            session_destroy();
            ok();

        default:
            fail('Unknown action', 404);
    }
} catch (Throwable $e) {
    error_log('reports.php error: ' . $e->getMessage());
    fail('Server error', 500);
}
