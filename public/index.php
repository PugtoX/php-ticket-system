<?php
declare(strict_types=1);

use App\Auth;
use App\Db;
use App\TicketRepo;
use App\TicketStatus;

$root = dirname(__DIR__);
if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        if (str_starts_with($class, 'App\\')) {
            $file = dirname(__DIR__) . '/src/' . substr($class, 4) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
}

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

$pdo = Db::conn();
$repo = new TicketRepo($pdo);
$auth = new Auth($pdo);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';

function render(string $template, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require dirname(__DIR__) . '/templates/' . $template . '.php';
}

function json(mixed $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function isApi(): bool
{
    return str_starts_with(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '', '/api/');
}

function csrfFromRequest(): ?string
{
    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        return (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
    }

    return isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : null;
}

function requireCsrf(Auth $auth): void
{
    if (!$auth->verifyCsrf(csrfFromRequest())) {
        if (isApi()) {
            json(['error' => 'invalid csrf token'], 419);
        }
        http_response_code(419);
        echo 'Invalid CSRF token';
        exit;
    }
}

// ---- POST /login
if ($path === '/login' && $method === 'POST') {
    requireCsrf($auth);
    $ok = $auth->attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
    if ($ok) {
        header('Location: /tickets');
        exit;
    }
    render('login', ['error' => 'Wrong email or password', 'csrf' => $auth->csrfToken()]);
    exit;
}

// ---- GET /login
if ($path === '/login') {
    if ($auth->check()) {
        header('Location: /tickets');
        exit;
    }
    render('login', ['error' => null, 'csrf' => $auth->csrfToken()]);
    exit;
}

// ---- POST /logout
if ($path === '/logout' && $method === 'POST') {
    requireCsrf($auth);
    $auth->logout();
    header('Location: /login');
    exit;
}

// ---- GET /api/stats
if ($path === '/api/stats') {
    $auth->requireLogin();
    json($repo->stats());
}

// ---- GET /api/tickets
if ($path === '/api/tickets' && $method === 'GET') {
    $auth->requireLogin();
    json($repo->paginate(
        isset($_GET['status']) ? (string) $_GET['status'] : null,
        (string) ($_GET['q'] ?? ''),
        (int) ($_GET['page'] ?? 1),
        (int) ($_GET['per_page'] ?? 20)
    ));
}

// ---- /api/tickets/{id} and /api/tickets/{id}/status
if (preg_match('#^/api/tickets/(\d+)(/status)?$#', $path, $m) === 1) {
    $auth->requireLogin();
    $id = (int) $m[1];

    if (!empty($m[2]) && $method === 'POST') {
        requireCsrf($auth);
        $payload = json_decode((string) file_get_contents('php://input'), true);
        $to = is_array($payload) ? (string) ($payload['status'] ?? '') : '';

        if (!TicketStatus::isValid($to)) {
            json(['error' => 'unknown status'], 422);
        }
        $user = $auth->user();
        $ok = $repo->updateStatus($id, $to, (int) $user['id']);
        if (!$ok) {
            json(['error' => 'transition not allowed'], 409);
        }
        json(['ok' => true, 'id' => $id, 'status' => $to]);
    }

    $ticket = $repo->find($id);
    if ($ticket === null) {
        json(['error' => 'not found'], 404);
    }
    $ticket['audit'] = $repo->auditFor($id);
    $ticket['next_states'] = TicketStatus::nextStates((string) $ticket['status']);
    json($ticket);
}

// ---- POST /tickets (create)
if ($path === '/tickets' && $method === 'POST') {
    requireCsrf($auth);
    try {
        $repo->create(
            (string) ($_POST['subject'] ?? ''),
            (string) ($_POST['body'] ?? ''),
            (string) ($_POST['customer_email'] ?? '')
        );
    } catch (InvalidArgumentException $e) {
        $data = $repo->paginate(null, '', 1, 20);
        render('tickets', [
            'user' => $auth->user(),
            'data' => $data,
            'filters' => ['status' => '', 'q' => ''],
            'stats' => $repo->stats(),
            'csrf' => $auth->csrfToken(),
            'error' => $e->getMessage(),
        ]);
        exit;
    }
    header('Location: /tickets');
    exit;
}

// ---- GET /tickets
if ($path === '/tickets' || $path === '/') {
    $auth->requireLogin();
    $status = isset($_GET['status']) && $_GET['status'] !== '' ? (string) $_GET['status'] : null;
    $q = (string) ($_GET['q'] ?? '');
    $page = (int) ($_GET['page'] ?? 1);

    render('tickets', [
        'user' => $auth->user(),
        'data' => $repo->paginate($status, $q, $page, 20),
        'filters' => ['status' => (string) ($status ?? ''), 'q' => $q],
        'stats' => $repo->stats(),
        'csrf' => $auth->csrfToken(),
        'error' => null,
    ]);
    exit;
}

http_response_code(404);
echo 'Not found';
