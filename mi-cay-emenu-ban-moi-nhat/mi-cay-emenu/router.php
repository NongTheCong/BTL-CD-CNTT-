<?php
/**
 * Bo dinh tuyen cho may chu PHP tich hop (php -S).
 * Giup cac duong dan API giong dung ban thiet ke trong bao cao,
 * vi du: /api/orders, /api/sessions/3/checkout
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Chan tai truc tiep ma nguon, CSDL va PHP di kem (vd /database/*.sqlite,
// /app/install.php?lam_lai=1) - quan trong khi dua web len mang
if (preg_match('#^/(app|database|php|\.vscode|\.claude)(/|$)|^/(README\.md|START\.bat|Dockerfile|router\.php)$#i', rawurldecode($uri))) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo '403 — Không được phép truy cập.';
    return true;
}

// Moi yeu cau /api/... deu di qua tang API
if (preg_match('#^/api(/|$)#', $uri)) {
    require __DIR__ . '/api/index.php';
    return true;
}

// Trang chu
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    return true;
}

// Tep tinh (CSS, JS) va cac trang .php khac do may chu tich hop phuc vu
$tep = __DIR__ . $uri;
if (is_file($tep)) {
    return false;
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<meta charset="utf-8"><body style="font:16px system-ui;padding:40px">'
   . '<h2>404 — Không tìm thấy trang</h2>'
   . '<p><a href="/index.php">Về trang chủ</a></p>';
return true;
