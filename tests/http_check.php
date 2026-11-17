<?php
declare(strict_types=1);
// End-to-end HTTP assertions against a running instance (php -S + MariaDB).
// Usage: php .tools/api_check.php [baseUrl]

$base = $argv[1] ?? 'http://127.0.0.1:8080';
$jar = tempnam(sys_get_temp_dir(), 'ck');
$pass = 0;
$fail = 0;

function hit(string $url, array $opt = []): array
{
    global $jar;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $opt['follow'] ?? false,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_CUSTOMREQUEST => $opt['method'] ?? 'GET',
        CURLOPT_HTTPHEADER => $opt['headers'] ?? [],
        CURLOPT_POSTFIELDS => $opt['body'] ?? null,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    return ['code' => $code, 'body' => (string) $body, 'redirect' => (string) $redirect];
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  PASS  {$label}\n";
    } else {
        $fail++;
        echo "  FAIL  {$label}" . ($detail !== '' ? " -> {$detail}" : '') . "\n";
    }
}

echo "1. login page renders\n";
$r = hit("{$base}/login");
check('GET /login is 200', $r['code'] === 200, (string) $r['code']);
check('login page has csrf token', (bool) preg_match('/name="_csrf" value="([a-f0-9]+)"/', $r['body'], $m));
$csrf = $m[1] ?? '';

echo "2. protected route redirects when anonymous\n";
$r = hit("{$base}/tickets");
check('GET /tickets redirects', $r['code'] === 302 || $r['code'] === 303, (string) $r['code']);
check('redirect points to /login', str_contains($r['redirect'], '/login'), $r['redirect']);

echo "3. CSRF is enforced on login\n";
$r = hit("{$base}/login", ['method' => 'POST', 'body' => http_build_query(['email' => 'agent@example.com', 'password' => 'demo1234', '_csrf' => 'bogus'])]);
check('POST /login with bad csrf is 419', $r['code'] === 419, (string) $r['code']);

echo "4. wrong password is rejected\n";
$r = hit("{$base}/login", ['method' => 'POST', 'body' => http_build_query(['email' => 'agent@example.com', 'password' => 'wrong', '_csrf' => $csrf])]);
check('POST /login wrong password is not a redirect', $r['code'] === 200, (string) $r['code']);

echo "5. correct login and ticket list\n";
$r = hit("{$base}/login", ['method' => 'POST', 'body' => http_build_query(['email' => 'agent@example.com', 'password' => 'demo1234', '_csrf' => $csrf])]);
check('POST /login redirects', $r['code'] === 302 || $r['code'] === 303, (string) $r['code']);
$r = hit("{$base}/tickets");
check('GET /tickets is 200 when logged in', $r['code'] === 200, (string) $r['code']);
check('ticket table has rows', str_contains($r['body'], '<table'), 'no table');
check('csrf token exposed for ajax', (bool) preg_match('/window\.CSRF_TOKEN\s*=\s*"([a-f0-9]+)"/', $r['body'], $m2));
$token = $m2[1] ?? '';

echo "6. json api\n";
$r = hit("{$base}/api/tickets?per_page=10");
$data = json_decode($r['body'], true);
check('GET /api/tickets is 200', $r['code'] === 200, (string) $r['code']);
check('api returns items array', is_array($data['items'] ?? null));
check('per_page whitelist is honoured', ($data['per_page'] ?? 0) === 10 && count($data['items']) <= 10, 'per_page=' . ($data['per_page'] ?? 'null'));

echo "7. create a ticket, then drive the state machine over http\n";
$tag = 'check-' . bin2hex(random_bytes(4));
$r = hit("{$base}/tickets", ['method' => 'POST', 'body' => http_build_query([
    'subject' => $tag, 'body' => 'created by api_check', 'customer_email' => 'check@example.com', '_csrf' => $token,
])]);
check('valid create is accepted', $r['code'] === 200 || $r['code'] === 302, (string) $r['code']);
$r = hit("{$base}/api/tickets?q={$tag}");
$data = json_decode($r['body'], true);
$id = $data['items'][0]['id'] ?? 0;
check('created ticket is searchable', $id > 0, 'id=' . $id);
check('new ticket starts open', ($data['items'][0]['status'] ?? '') === 'open', (string) ($data['items'][0]['status'] ?? ''));


$post = static function (string $to) use ($base, $id, $token): array {
    return hit("{$base}/api/tickets/{$id}/status", [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json', "X-CSRF-Token: {$token}"],
        'body' => json_encode(['status' => $to]),
    ]);
};

check('open -> in_progress allowed', $post('in_progress')['code'] === 200);
check('in_progress -> open allowed', $post('open')['code'] === 200);
check('open -> closed allowed', $post('closed')['code'] === 200);
check('closed -> in_progress rejected with 409', $post('in_progress')['code'] === 409);
check('closed -> open allowed', $post('open')['code'] === 200);
check('unknown status rejected with 422', $post('archived')['code'] === 422, (string) $post('archived')['code']);
$detail = json_decode(hit("{$base}/api/tickets/{$id}")['body'], true);
check('audit trail has one row per accepted transition', count($detail['audit'] ?? []) === 4, 'rows=' . count($detail['audit'] ?? []));

echo "8. stats\n";
$r = hit("{$base}/api/stats");
$stats = json_decode($r['body'], true);
check('GET /api/stats is 200', $r['code'] === 200, (string) $r['code']);
check('stats has all three states', isset($stats['open'], $stats['in_progress'], $stats['closed']));
check('stats total matches sum', ($stats['total'] ?? -1) === ($stats['open'] + $stats['in_progress'] + $stats['closed']));

echo "9. validation and pagination edges\n";
$r = hit("{$base}/tickets", ['method' => 'POST', 'body' => http_build_query([
    'subject' => 'x', 'body' => 'too short subject', 'customer_email' => 'not-an-email', '_csrf' => $token,
])]);
check('rejects invalid subject/email', $r['code'] === 422 || str_contains($r['body'], 'alert'), (string) $r['code']);

$r = hit("{$base}/api/tickets?page=9999");
$data = json_decode($r['body'], true);
check('out-of-range page is clamped to a real page', $r['code'] === 200 && ($data['page'] ?? 0) >= 1 && ($data['page'] ?? 0) <= ($data['pages'] ?? 0), 'page=' . ($data['page'] ?? 'null'));

$r = hit("{$base}/api/tickets?q=" . urlencode("' OR 1=1 --"));
$data = json_decode($r['body'], true);
check('quote injection does not widen the result set', ($data['total'] ?? -1) === 0, 'total=' . ($data['total'] ?? 'null'));

echo "\n{$pass} passed, {$fail} failed\n";
@unlink($jar);
exit($fail === 0 ? 0 : 1);
