<?php
/**
 * Ham dung chung: phien lam viec, phan quyen, dinh dang, may trang thai.
 */

require_once __DIR__ . '/Db.php';

// Gio Viet Nam cho moi moc thoi gian (mo_luc, tao_luc, dong_luc...)
date_default_timezone_set('Asia/Ho_Chi_Minh');

/** Cau hinh he thong (doc mot lan). */
function cf(): array
{
    static $cf = null;
    if ($cf === null) {
        $cf = require __DIR__ . '/config.php';
    }
    return $cf;
}

/** Trang dang mo qua https (truc tiep hoac sau proxy cua hosting nhu Render). */
function la_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/**
 * Mo phien lam viec. Cookie phien dat co HttpOnly va SameSite
 * nen JavaScript khong doc duoc (bien phap an toan trong bao cao).
 */
function mo_phien(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => la_https(),
        'path'     => '/',
    ]);
    session_start();
}

/* ---------------------------------------------------------------
 *  5 tac nhan va ma tran phan quyen (slide 29 cua bao cao)
 * --------------------------------------------------------------- */

const VAI_TRO = [
    'khach'    => 'Khách hàng',
    'phuc_vu'  => 'Nhân viên phục vụ',
    'bep'      => 'Nhân viên bếp',
    'thu_ngan' => 'Thu ngân',
    'quan_tri' => 'Quản trị viên',
];

/** Chuc nang => danh sach vai tro duoc phep. */
const QUYEN = [
    'xem_thuc_don'      => ['khach', 'phuc_vu', 'bep', 'thu_ngan', 'quan_tri'],
    'gui_don'           => ['khach'],
    'xem_don_phien'     => ['khach', 'phuc_vu', 'bep', 'thu_ngan', 'quan_tri'],
    'mo_phien'          => ['phuc_vu', 'quan_tri'],
    'cap_nhat_che_bien' => ['bep'],
    'xac_nhan_phuc_vu'  => ['phuc_vu'],
    'huy_don'           => ['phuc_vu', 'quan_tri'],
    'thanh_toan'        => ['thu_ngan', 'quan_tri'],
    'quan_ly_he_thong'  => ['quan_tri'],
];

/** Tai khoan nhan vien dang dang nhap, hoac null. */
function nhan_vien(): ?array
{
    mo_phien();
    return $_SESSION['nhan_vien'] ?? null;
}

/** Vai tro hien tai: 'khach' khi chua dang nhap. */
function vai_tro_hien_tai(): string
{
    $nv = nhan_vien();
    return $nv['vai_tro'] ?? 'khach';
}

/** Kiem tra quyen tai MAY CHU, khong chi an nut tren giao dien. */
function co_quyen(string $chuc_nang): bool
{
    $cho_phep = QUYEN[$chuc_nang] ?? [];
    return in_array(vai_tro_hien_tai(), $cho_phep, true);
}

/** Chan truy cap man hinh noi bo neu sai vai tro. */
function bat_buoc_quyen(string $chuc_nang): void
{
    if (!co_quyen($chuc_nang)) {
        header('Location: login.php?loi=quyen');
        exit;
    }
}

/* ---------------------------------------------------------------
 *  Ngu canh ban cua khach - do MAY CHU gan sau khi quet QR.
 *  Khach khong tu nhap so ban, khong doc duoc don cua ban khac.
 * --------------------------------------------------------------- */

function luu_ngu_canh_khach(array $ban, ?array $phien): void
{
    mo_phien();
    $_SESSION['ngu_canh'] = [
        'id_ban'   => (int) $ban['id_ban'],
        'so_ban'   => $ban['so_ban'],
        'khu_vuc'  => $ban['khu_vuc'],
        'ma_qr'    => $ban['ma_qr'],
        'id_phien' => $phien ? (int) $phien['id_phien'] : null,
    ];
}

/**
 * Duong dan ma QR dan tren ban: /index.php?qr=<ma_qr>.
 * Neu dang mo bang localhost thi doi sang dia chi IP trong mang LAN,
 * de dien thoai cung mang Wi-Fi quet la mo duoc.
 */
function duong_dan_qr(string $ma_qr): string
{
    static $goc = null;
    if ($goc === null) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
        if (preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host, $m)) {
            $ip = gethostbyname(gethostname());
            if ($ip && !str_starts_with($ip, '127.')) {
                $host = $ip . ($m[2] ?? '');
            }
        }
        $goc = (la_https() ? 'https://' : 'http://') . $host . '/index.php?qr=';
    }
    return $goc . urlencode($ma_qr);
}

function ngu_canh_khach(): ?array
{
    mo_phien();
    return $_SESSION['ngu_canh'] ?? null;
}

/* ---------------------------------------------------------------
 *  Tra loi JSON cho tang API
 * --------------------------------------------------------------- */

/** Tra ve JSON kem ma trang thai HTTP (200/201/403/409/422...). */
function tra_json(array $du_lieu, int $ma = 200): never
{
    http_response_code($ma);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($du_lieu, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Tra ve loi theo quy uoc ma trong bao cao. */
function tra_loi(string $thong_bao, int $ma = 422, array $them = []): never
{
    tra_json(['thanh_cong' => false, 'ma_loi' => $ma, 'thong_bao' => $thong_bao] + $them, $ma);
}

/** Doc than yeu cau dang JSON. */
function doc_json_body(): array
{
    $tho = file_get_contents('php://input') ?: '';
    $du  = json_decode($tho, true);
    return is_array($du) ? $du : [];
}

/* ---------------------------------------------------------------
 *  May trang thai cua don gol mon (slide 13)
 * --------------------------------------------------------------- */

const TT_DON = ['Mới nhận', 'Đang chế biến', 'Chờ phục vụ', 'Đã phục vụ', 'Đã hủy'];

/** Chuyen trang thai hop le: tu => [cac trang thai duoc phep sang]. */
const CHUYEN_TT = [
    'Mới nhận'      => ['Đang chế biến', 'Đã hủy'],
    'Đang chế biến' => ['Chờ phục vụ'],
    'Chờ phục vụ'   => ['Đã phục vụ'],
    'Đã phục vụ'    => [],   // trang thai ket thuc
    'Đã hủy'        => [],   // trang thai ket thuc
];

/** He thong khong cho chuyen nguoc trang thai. */
function duoc_chuyen(string $tu, string $sang): bool
{
    return in_array($sang, CHUYEN_TT[$tu] ?? [], true);
}

/** Vai tro nao duoc dat trang thai nao. */
function vai_tro_duoc_dat(string $sang, string $vai_tro): bool
{
    return match ($sang) {
        'Đang chế biến', 'Chờ phục vụ' => in_array($vai_tro, ['bep', 'quan_tri'], true),
        'Đã phục vụ'                   => in_array($vai_tro, ['phuc_vu', 'quan_tri'], true),
        'Đã hủy'                       => in_array($vai_tro, ['phuc_vu', 'quan_tri'], true),
        default                        => false,
    };
}

/* ---------------------------------------------------------------
 *  Dinh dang hien thi
 * --------------------------------------------------------------- */

/** 45000 => "45.000 đ" */
function tien(float|int|string $so): string
{
    return number_format((float) $so, 0, ',', '.') . ' đ';
}

/** Rut ngan thoi diem: "12:35" hoac "15/10 12:35". */
function gio(?string $dt, bool $kem_ngay = false): string
{
    if (!$dt) {
        return '—';
    }
    $ts = strtotime($dt);
    return date($kem_ngay ? 'd/m H:i' : 'H:i', $ts);
}

/** Thoat ky tu HTML. */
function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Lop mau cho nhan trang thai. */
function lop_trang_thai(string $tt): string
{
    return match ($tt) {
        'Mới nhận'      => 'tt-moi',
        'Đang chế biến' => 'tt-nau',
        'Chờ phục vụ'   => 'tt-cho',
        'Đã phục vụ'    => 'tt-xong',
        'Đã hủy'        => 'tt-huy',
        'Đang mở'       => 'tt-moi',
        'Chờ thanh toán'=> 'tt-cho',
        'Đã đóng'       => 'tt-xong',
        default         => 'tt-moi',
    };
}
