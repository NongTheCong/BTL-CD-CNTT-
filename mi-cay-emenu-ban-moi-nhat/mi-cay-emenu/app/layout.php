<?php
/**
 * Khung trang dung chung - tang trinh bay.
 * HTML5, CSS3, JavaScript, Bootstrap 5 (theo cong nghe de xuat o slide 23).
 */

require_once __DIR__ . '/NghiepVu.php';
require_once __DIR__ . '/install.php';

mo_phien();
bao_dam_cai_dat();

/**
 * Ngu canh cua khach tren cac man hinh goi mon.
 * - Chua quet QR             -> quay ve trang quet QR
 * - Quet luc ban chua mo phien -> kiem tra lai, neu nhan vien vua mo thi gan phien moi
 * - Phien da dong            -> chan trang cu (ca kiem thu TC16), yeu cau quet lai
 *
 * @return array [ngu_canh, phien|null, ly_do_khoa|null]
 */
function ngu_canh_goi_mon(): array
{
    $nc = ngu_canh_khach();
    if (!$nc) {
        header('Location: /index.php');
        exit;
    }

    if (!$nc['id_phien']) {
        $kq = NghiepVu::ngu_canh_ban($nc['ma_qr']);
        if ($kq['ok'] && $kq['phien']) {
            luu_ngu_canh_khach($kq['ban'], $kq['phien']);
            $nc = ngu_canh_khach();
        } else {
            return [$nc, null, 'chua_mo'];
        }
    }

    $phien = NghiepVu::phien((int) $nc['id_phien']);
    if (!$phien || $phien['trang_thai'] === 'Đã đóng') {
        return [$nc, $phien, 'het_phien'];
    }
    if ($phien['trang_thai'] !== 'Đang mở') {
        return [$nc, $phien, 'cho_thanh_toan'];
    }
    return [$nc, $phien, null];
}

/** Thong bao tuong ung voi ly do khoa gui don. */
function bao_khoa_gui_don(?string $ly_do): void
{
    if ($ly_do === 'chua_mo') {
        echo '<div class="tb tb-nhac">⏳ Bàn chưa mở phiên phục vụ. Bạn vẫn xem được thực đơn, '
           . 'nhưng cần nhờ nhân viên mở phiên trước khi gửi đơn. '
           . '<a href="?kiem_tra=1">Kiểm tra lại</a></div>';
    } elseif ($ly_do === 'het_phien') {
        echo '<div class="tb tb-loi">🔒 Phiên phục vụ của bạn đã kết thúc và đã thanh toán. '
           . 'Trang này không còn gửi đơn được. Vui lòng quét lại mã QR trên bàn nếu bạn là lượt khách mới.</div>';
    } elseif ($ly_do === 'cho_thanh_toan') {
        echo '<div class="tb tb-nhac">💵 Bàn đang chờ thanh toán nên tạm dừng nhận đơn mới.</div>';
    }
}

/** Mon thuoc nhom mi cay thi khach chon cap do cay 1 - 7. */
function can_chon_cay(array $mon, string $ten_danh_muc): bool
{
    return str_starts_with($ten_danh_muc, 'Mì cay')
        || str_contains($mon['ten_mon'], 'mì cay');
}

/** Cac man hinh noi bo theo vai tro - moi vai tro chi thay man hinh cua minh. */
function menu_noi_bo(): array
{
    $ds = [
        ['bep.php',      '🔥 Màn hình bếp', 'cap_nhat_che_bien'],
        ['phuc-vu.php',  '🧑‍🍳 Bàn & phục vụ', 'mo_phien'],
        ['thu-ngan.php', '💵 Thu ngân',      'thanh_toan'],
        ['quan-tri.php', '⚙️ Quản trị',      'quan_ly_he_thong'],
    ];
    return array_values(array_filter($ds, fn($m) => co_quyen($m[2])));
}

/**
 * In phan dau trang.
 * @param string $loai 'khach' (dien thoai, man hinh doc) hoac 'noi_bo'
 */
function dau_trang(string $tieu_de, string $loai = 'khach', string $trang = ''): void
{
    $quan = cf()['quan'];
    $nc   = ngu_canh_khach();
    $nv   = nhan_vien();
    ?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#111112">
    <title><?= h($tieu_de) ?> · <?= h($quan['ten']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap-reboot.min.css"
          rel="stylesheet">
    <link href="/assets/css/app.css?v=6" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🌶️</text></svg>">
</head>
<body data-loai="<?= h($loai) ?>"
      data-ban="<?= h($nc['so_ban'] ?? '') ?>"
      data-phien="<?= h((string) ($nc['id_phien'] ?? '')) ?>">

<header class="dau-trang">
    <div class="trong">
        <a class="logo" href="<?= $loai === 'khach' ? ($nc ? '/menu.php' : '/index.php') : '/login.php' ?>">
            <span class="lua">🌶️</span>
            <span><?= h($quan['ten']) ?><small><?= $loai === 'khach' ? h($quan['slogan']) : 'Khu vực nội bộ' ?></small></span>
        </a>
        <div class="lon-dan"></div>

        <?php if ($loai === 'khach'): ?>
            <?php if ($nc): ?>
                <span class="nhan-ban">🪑 Bàn <b><?= h($nc['so_ban']) ?></b></span>
                <a class="nut nut-nho" href="/gio-hang.php" aria-label="Giỏ hàng">
                    🛒 <span data-so-gio>0</span>
                </a>
            <?php endif; ?>
        <?php else: ?>
            <?php if ($nv): ?>
                <span class="nho mo" style="white-space:nowrap">
                    <span class="ten-nv"><?= h($nv['ho_ten']) ?> · </span><span class="dam" style="color:var(--vang)"><?= h(VAI_TRO[$nv['vai_tro']] ?? '') ?></span>
                </span>
                <a class="nut nut-vien nut-nho" href="/logout.php">Đăng xuất</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</header>

<?php if ($loai === 'noi_bo' && $nv): ?>
<div class="khung-rong">
    <nav class="dieu-huong">
        <?php foreach (menu_noi_bo() as [$url, $ten]): ?>
            <a href="/<?= h($url) ?>" class="<?= $trang === $url ? 'chon' : '' ?>"><?= h($ten) ?></a>
        <?php endforeach; ?>
    </nav>
</div>
<?php endif; ?>

<main class="<?= $loai === 'khach' ? 'khung' : 'khung-rong' ?>">
    <?php
}

/** In phan cuoi trang. */
function chan_trang(string $loai = 'khach'): void
{
    $quan = cf()['quan'];
    ?>
</main>

<footer class="chan-trang">
    <div><b style="color:var(--kem)"><?= h($quan['ten']) ?></b> · <?= h($quan['dia_chi']) ?></div>
    <div>☎ <?= h($quan['dien_thoai']) ?> · 🕘 <?= h($quan['gio_mo_cua']) ?></div>
    <div class="rat-nho" style="margin-top:8px">
        E Menu &amp; Self Ordering · CSDL: <?= h(strtoupper(Db::driver())) ?>
        <?php if ($loai === 'khach'): ?> · <a href="/login.php">Nhân viên đăng nhập</a><?php endif; ?>
    </div>
</footer>

<script src="/assets/js/app.js?v=3"></script>
</body>
</html>
    <?php
}
