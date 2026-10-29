<?php
/**
 * MAN HINH NOI BO 4 - Trang quan tri
 * Danh muc, mon, ban, ma QR, tai khoan, du lieu lich su.
 * Nguyen tac: ngung su dung thay cho xoa cung du lieu dang duoc lich su tham chieu.
 */
require_once __DIR__ . '/app/layout.php';
bat_buoc_quyen('quan_ly_he_thong');

$tab = $_GET['tab'] ?? 'mon';
$nv  = nhan_vien();

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

/* ---------------------------------------------------------------
 *  Xu ly thao tac (POST -> chuyen huong lai)
 * --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Phiên làm việc không hợp lệ, vui lòng tải lại trang.');
    }

    $tb = null;
    $loi = null;
    $lam = $_POST['lam'] ?? '';

    try {
        switch ($lam) {
            /* ----- Mon an ----- */
            case 'mon_luu':
                $id   = (int) ($_POST['id_mon'] ?? 0);
                $ten  = trim((string) $_POST['ten_mon']);
                $gia  = (int) $_POST['gia'];
                $dm   = (int) $_POST['id_danh_muc'];
                $mota = trim((string) ($_POST['mo_ta'] ?? ''));
                $anh  = trim((string) ($_POST['anh'] ?? '')) ?: '🍜';
                if ($ten === '' || $gia <= 0 || !$dm) {
                    $loi = 'Cần nhập tên món, danh mục và giá lớn hơn 0.';
                    break;
                }
                if ($id) {
                    Db::chay('UPDATE MonAn SET ten_mon=?, id_danh_muc=?, gia=?, mo_ta=?, anh=? WHERE id_mon=?',
                             [$ten, $dm, $gia, $mota, $anh, $id]);
                    $tb = "Đã cập nhật món \"$ten\". Hóa đơn cũ vẫn giữ tên và giá lúc đặt.";
                } else {
                    Db::chay("INSERT INTO MonAn (id_danh_muc, ten_mon, mo_ta, anh, gia, trang_thai_ban, thu_tu)
                              VALUES (?, ?, ?, ?, ?, 'Đang bán', 99)", [$dm, $ten, $mota, $anh, $gia]);
                    $tb = "Đã thêm món \"$ten\".";
                }
                break;

            case 'mon_trang_thai':
                $tt = $_POST['trang_thai_ban'] ?? '';
                if (in_array($tt, ['Đang bán', 'Hết hàng', 'Ngừng bán'], true)) {
                    Db::chay('UPDATE MonAn SET trang_thai_ban = ? WHERE id_mon = ?', [$tt, (int) $_POST['id_mon']]);
                    $tb = "Đã chuyển món sang \"$tt\".";
                }
                break;

            /* ----- Danh muc ----- */
            case 'dm_luu':
                $ten = trim((string) $_POST['ten_danh_muc']);
                if ($ten === '') { $loi = 'Tên danh mục không được trống.'; break; }
                $id = (int) ($_POST['id_danh_muc'] ?? 0);
                if ($id) {
                    Db::chay('UPDATE DanhMuc SET ten_danh_muc=?, thu_tu=? WHERE id_danh_muc=?',
                             [$ten, (int) $_POST['thu_tu'], $id]);
                } else {
                    Db::chay("INSERT INTO DanhMuc (ten_danh_muc, thu_tu, trang_thai) VALUES (?, ?, 'Hiển thị')",
                             [$ten, (int) ($_POST['thu_tu'] ?? 50)]);
                }
                $tb = "Đã lưu danh mục \"$ten\".";
                break;

            case 'dm_trang_thai':
                $tt = $_POST['trang_thai'] === 'Ẩn' ? 'Ẩn' : 'Hiển thị';
                Db::chay('UPDATE DanhMuc SET trang_thai=? WHERE id_danh_muc=?', [$tt, (int) $_POST['id_danh_muc']]);
                $tb = "Danh mục đã chuyển sang \"$tt\".";
                break;

            /* ----- Ban an ----- */
            case 'ban_luu':
                $so  = strtoupper(trim((string) $_POST['so_ban']));
                $khu = trim((string) $_POST['khu_vuc']);
                if ($so === '' || $khu === '') { $loi = 'Cần nhập số bàn và khu vực.'; break; }
                if (Db::mot('SELECT 1 FROM BanAn WHERE so_ban = ?', [$so])) { $loi = "Bàn $so đã tồn tại."; break; }
                $ma = 'MC-' . $so . '-' . bin2hex(random_bytes(4));
                Db::chay("INSERT INTO BanAn (so_ban, ma_qr, khu_vuc, trang_thai) VALUES (?, ?, ?, 'Hoạt động')",
                         [$so, $ma, $khu]);
                $tb = "Đã thêm bàn $so và sinh mã QR.";
                break;

            case 'ban_trang_thai':
                $id = (int) $_POST['id_ban'];
                $tt = $_POST['trang_thai'] === 'Tạm ngừng' ? 'Tạm ngừng' : 'Hoạt động';
                if ($tt === 'Tạm ngừng' && Db::mot("SELECT 1 FROM PhienPhucVu WHERE id_ban=? AND trang_thai <> 'Đã đóng'", [$id])) {
                    $loi = 'Bàn đang có phiên phục vụ, chưa thể tạm ngừng.';
                    break;
                }
                Db::chay('UPDATE BanAn SET trang_thai=? WHERE id_ban=?', [$tt, $id]);
                $tb = "Bàn đã chuyển sang \"$tt\".";
                break;

            case 'ban_doi_qr':
                $b = Db::mot('SELECT so_ban FROM BanAn WHERE id_ban=?', [(int) $_POST['id_ban']]);
                if ($b) {
                    Db::chay('UPDATE BanAn SET ma_qr=? WHERE id_ban=?',
                             ['MC-' . $b['so_ban'] . '-' . bin2hex(random_bytes(4)), (int) $_POST['id_ban']]);
                    $tb = "Đã sinh mã QR mới cho bàn {$b['so_ban']}. Mã cũ hết hiệu lực.";
                }
                break;

            /* ----- Tai khoan ----- */
            case 'tk_luu':
                $ten = trim((string) $_POST['ten_dang_nhap']);
                $mk  = (string) $_POST['mat_khau'];
                $vt  = $_POST['vai_tro'] ?? '';
                if ($ten === '' || strlen($mk) < 6 || !isset(VAI_TRO[$vt]) || $vt === 'khach') {
                    $loi = 'Cần tên đăng nhập, mật khẩu tối thiểu 6 ký tự và vai trò hợp lệ.';
                    break;
                }
                if (Db::mot('SELECT 1 FROM TaiKhoan WHERE ten_dang_nhap=?', [$ten])) { $loi = 'Tên đăng nhập đã tồn tại.'; break; }
                Db::chay("INSERT INTO TaiKhoan (ten_dang_nhap, mat_khau_bam, ho_ten, vai_tro, trang_thai)
                          VALUES (?, ?, ?, ?, 'Hoạt động')",
                         [$ten, password_hash($mk, PASSWORD_DEFAULT), trim((string) $_POST['ho_ten']) ?: $ten, $vt]);
                $tb = "Đã tạo tài khoản $ten.";
                break;

            case 'tk_trang_thai':
                $id = (int) $_POST['id_tai_khoan'];
                if ($id === (int) $nv['id_tai_khoan']) { $loi = 'Không thể tự khóa tài khoản đang đăng nhập.'; break; }
                $tt = $_POST['trang_thai'] === 'Đã khóa' ? 'Đã khóa' : 'Hoạt động';
                Db::chay('UPDATE TaiKhoan SET trang_thai=? WHERE id_tai_khoan=?', [$tt, $id]);
                $tb = "Tài khoản đã chuyển sang \"$tt\".";
                break;

            case 'cai_lai':
                cai_dat(true);
                session_regenerate_id(true);
                $_SESSION = [];
                header('Location: /login.php');
                exit;
        }
    } catch (Throwable $e) {
        $loi = 'Lỗi: ' . $e->getMessage();
    }

    $_SESSION['flash'] = $loi ? ['loi', $loi] : ($tb ? ['ok', $tb] : null);
    header('Location: /quan-tri.php?tab=' . urlencode($_POST['tab'] ?? $tab));
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

/** Truong an CSRF + tab hien tai. */
function an(string $lam, string $tab): string
{
    return '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">'
         . '<input type="hidden" name="lam" value="' . h($lam) . '">'
         . '<input type="hidden" name="tab" value="' . h($tab) . '">';
}

/* ---------------------------------------------------------------
 *  So lieu tong quan
 * --------------------------------------------------------------- */
$bd_ngay = date('Y-m-d') . ' 00:00:00';
$so_lieu = [
    'mon'   => (int) Db::mot("SELECT COUNT(*) n FROM MonAn WHERE trang_thai_ban <> 'Ngừng bán'")['n'],
    'ban'   => (int) Db::mot("SELECT COUNT(*) n FROM BanAn WHERE trang_thai = 'Hoạt động'")['n'],
    'don'   => (int) Db::mot('SELECT COUNT(*) n FROM DonHang WHERE tao_luc >= ?', [$bd_ngay])['n'],
    'thu'   => (float) Db::mot("SELECT COALESCE(SUM(tong_thanh_toan),0) n FROM PhienPhucVu
                                WHERE trang_thai='Đã đóng' AND dong_luc >= ?", [$bd_ngay])['n'],
];

// Dia chi LAN de dien thoai trong quan quet duoc ma QR
$goc_qr = duong_dan_qr('');

dau_trang('Quản trị', 'noi_bo', 'quan-tri.php');
?>

<div class="giua-hai cach-duoi">
    <h1 style="margin:0">⚙️ Quản trị hệ thống</h1>
    <span class="rat-nho mo">Hệ quản trị CSDL: <b style="color:var(--vang)"><?= h(strtoupper(Db::driver())) ?></b></span>
</div>

<div class="luoi cach-duoi" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr))">
    <div class="o-so-lieu"><div class="so"><?= $so_lieu['mon'] ?></div><div class="nhan">Món trên thực đơn</div></div>
    <div class="o-so-lieu"><div class="so"><?= $so_lieu['ban'] ?></div><div class="nhan">Bàn đang hoạt động</div></div>
    <div class="o-so-lieu"><div class="so"><?= $so_lieu['don'] ?></div><div class="nhan">Đơn gọi món hôm nay</div></div>
    <div class="o-so-lieu"><div class="so" style="font-size:1.4rem"><?= tien($so_lieu['thu']) ?></div><div class="nhan">Doanh thu hôm nay</div></div>
</div>

<?php if ($flash): ?>
    <div class="tb tb-<?= $flash[0] === 'ok' ? 'ok' : 'loi' ?>"><?= h($flash[1]) ?></div>
<?php endif; ?>

<nav class="dieu-huong" style="border:none;margin-bottom:12px">
    <?php foreach (['mon' => '🍜 Món ăn', 'danh-muc' => '📂 Danh mục', 'ban' => '🪑 Bàn & mã QR',
                    'tai-khoan' => '👤 Tài khoản', 'lich-su' => '📜 Lịch sử'] as $k => $ten): ?>
        <a href="?tab=<?= $k ?>" class="<?= $tab === $k ? 'chon' : '' ?>"><?= $ten ?></a>
    <?php endforeach; ?>
</nav>

<?php /* ===================== MON AN ===================== */
if ($tab === 'mon'):
    $danh_muc = NghiepVu::thuc_don(true);
    $sua = isset($_GET['sua']) ? NghiepVu::mon((int) $_GET['sua']) : null;
?>
    <form method="post" class="the cach-duoi">
        <?= an('mon_luu', 'mon') ?>
        <input type="hidden" name="id_mon" value="<?= (int) ($sua['id_mon'] ?? 0) ?>">
        <h3><?= $sua ? 'Sửa món: ' . h($sua['ten_mon']) : 'Thêm món mới' ?></h3>
        <div class="luoi" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
            <div><label>Tên món</label><input type="text" name="ten_mon" required value="<?= h($sua['ten_mon'] ?? '') ?>"></div>
            <div><label>Danh mục</label>
                <select name="id_danh_muc">
                    <?php foreach ($danh_muc as $dm): ?>
                        <option value="<?= (int) $dm['id_danh_muc'] ?>" <?= ($sua['id_danh_muc'] ?? 0) == $dm['id_danh_muc'] ? 'selected' : '' ?>><?= h($dm['ten_danh_muc']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label>Giá bán (đ)</label><input type="number" name="gia" min="1000" step="1000" required value="<?= h((string) (int) ($sua['gia'] ?? '')) ?>"></div>
            <div><label>Ảnh (emoji)</label><input type="text" name="anh" maxlength="8" value="<?= h($sua['anh'] ?? '🍜') ?>"></div>
        </div>
        <div style="margin-top:12px"><label>Mô tả</label><input type="text" name="mo_ta" value="<?= h($sua['mo_ta'] ?? '') ?>"></div>
        <div class="hang" style="margin-top:12px">
            <button class="nut" type="submit"><?= $sua ? 'Lưu thay đổi' : '+ Thêm món' ?></button>
            <?php if ($sua): ?><a class="nut nut-vien" href="?tab=mon">Hủy</a><?php endif; ?>
        </div>
    </form>

    <?php foreach ($danh_muc as $dm): ?>
        <div class="ten-nhom"><?= h($dm['ten_danh_muc']) ?> <span><?= count($dm['mon']) ?> món<?= $dm['trang_thai'] !== 'Hiển thị' ? ' · danh mục đang ẩn' : '' ?></span></div>
        <div class="the bang-cuon" style="padding:0">
            <table class="bang">
                <thead><tr><th></th><th>Tên món</th><th class="phai">Giá</th><th>Trạng thái</th><th class="phai">Thao tác</th></tr></thead>
                <tbody>
                <?php foreach ($dm['mon'] as $m): ?>
                    <tr style="<?= $m['trang_thai_ban'] === 'Ngừng bán' ? 'opacity:.5' : '' ?>">
                        <td style="font-size:1.4rem;width:44px"><?= h($m['anh']) ?></td>
                        <td class="dam"><?= h($m['ten_mon']) ?></td>
                        <td class="phai gia"><?= tien($m['gia']) ?></td>
                        <td><span class="tt <?= $m['trang_thai_ban'] === 'Đang bán' ? 'tt-xong' : ($m['trang_thai_ban'] === 'Hết hàng' ? 'tt-nau' : 'tt-huy') ?>"><?= h($m['trang_thai_ban']) ?></span></td>
                        <td class="phai">
                            <form method="post" style="display:inline-flex;gap:6px">
                                <?= an('mon_trang_thai', 'mon') ?>
                                <input type="hidden" name="id_mon" value="<?= (int) $m['id_mon'] ?>">
                                <?php if ($m['trang_thai_ban'] === 'Đang bán'): ?>
                                    <button class="nut nut-vien nut-nho" name="trang_thai_ban" value="Hết hàng">Hết hàng</button>
                                <?php else: ?>
                                    <button class="nut nut-xanh nut-nho" name="trang_thai_ban" value="Đang bán">Mở bán</button>
                                <?php endif; ?>
                                <?php if ($m['trang_thai_ban'] !== 'Ngừng bán'): ?>
                                    <button class="nut nut-xam nut-nho" name="trang_thai_ban" value="Ngừng bán" title="Ẩn khỏi thực đơn, không xóa cứng">Ẩn</button>
                                <?php endif; ?>
                                <a class="nut nut-vien nut-nho" href="?tab=mon&sua=<?= (int) $m['id_mon'] ?>">Sửa</a>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>

<?php /* ===================== DANH MUC ===================== */
elseif ($tab === 'danh-muc'):
    $ds = Db::tat_ca('SELECT d.*, (SELECT COUNT(*) FROM MonAn m WHERE m.id_danh_muc = d.id_danh_muc) AS so_mon
                      FROM DanhMuc d ORDER BY thu_tu, id_danh_muc');
?>
    <form method="post" class="the cach-duoi hang" style="flex-wrap:wrap;align-items:flex-end">
        <?= an('dm_luu', 'danh-muc') ?>
        <div class="lon-dan" style="min-width:200px"><label>Tên danh mục mới</label><input type="text" name="ten_danh_muc" required></div>
        <div style="width:110px"><label>Thứ tự</label><input type="number" name="thu_tu" value="<?= count($ds) + 1 ?>"></div>
        <button class="nut" type="submit">+ Thêm</button>
    </form>
    <div class="the bang-cuon" style="padding:0">
        <table class="bang">
            <thead><tr><th>Thứ tự</th><th>Tên danh mục</th><th>Số món</th><th>Trạng thái</th><th class="phai">Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($ds as $d): ?>
                <tr>
                    <td colspan="2">
                        <form method="post" class="hang">
                            <?= an('dm_luu', 'danh-muc') ?>
                            <input type="hidden" name="id_danh_muc" value="<?= (int) $d['id_danh_muc'] ?>">
                            <input type="number" name="thu_tu" value="<?= (int) $d['thu_tu'] ?>" style="width:74px;min-height:38px;padding:6px 10px">
                            <input type="text" name="ten_danh_muc" value="<?= h($d['ten_danh_muc']) ?>" style="min-width:220px;min-height:38px;padding:6px 10px">
                            <button class="nut nut-vien nut-nho">Lưu</button>
                        </form>
                    </td>
                    <td><?= (int) $d['so_mon'] ?></td>
                    <td><span class="tt <?= $d['trang_thai'] === 'Hiển thị' ? 'tt-xong' : 'tt-huy' ?>"><?= h($d['trang_thai']) ?></span></td>
                    <td class="phai">
                        <form method="post"><?= an('dm_trang_thai', 'danh-muc') ?>
                            <input type="hidden" name="id_danh_muc" value="<?= (int) $d['id_danh_muc'] ?>">
                            <button class="nut nut-vien nut-nho" name="trang_thai" value="<?= $d['trang_thai'] === 'Hiển thị' ? 'Ẩn' : 'Hiển thị' ?>">
                                <?= $d['trang_thai'] === 'Hiển thị' ? 'Ẩn danh mục' : 'Hiển thị lại' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php /* ===================== BAN & QR ===================== */
elseif ($tab === 'ban'):
    $ds = Db::tat_ca('SELECT * FROM BanAn ORDER BY khu_vuc, so_ban');
    $khu = array_values(array_unique(array_column($ds, 'khu_vuc')));
?>
    <form method="post" class="the cach-duoi hang" style="flex-wrap:wrap;align-items:flex-end">
        <?= an('ban_luu', 'ban') ?>
        <div style="width:140px"><label>Số bàn</label><input type="text" name="so_ban" placeholder="VD: B06" required></div>
        <div class="lon-dan" style="min-width:200px"><label>Khu vực</label>
            <input type="text" name="khu_vuc" list="ds-khu" required value="<?= h($khu[0] ?? '') ?>">
            <datalist id="ds-khu"><?php foreach ($khu as $k): ?><option value="<?= h($k) ?>"><?php endforeach; ?></datalist></div>
        <button class="nut" type="submit">+ Thêm bàn</button>
        <button class="nut nut-vien" type="button" onclick="window.print()">🖨 In toàn bộ mã QR</button>
    </form>

    <p class="nho mo">Mỗi mã QR mở đường dẫn <span class="ma-nguon" style="color:var(--vang)"><?= h($goc_qr) ?>…</span>.
        Điện thoại cùng mạng Wi-Fi với máy chủ có thể quét trực tiếp.</p>

    <div class="luoi" style="grid-template-columns:repeat(auto-fill,minmax(210px,1fr))" id="luoi-qr">
        <?php foreach ($ds as $b): $ngung = $b['trang_thai'] !== 'Hoạt động'; ?>
            <div class="the tren-giua the-in" style="<?= $ngung ? 'opacity:.55' : '' ?>">
                <div class="giua-hai"><span class="dam" style="font-size:1.3rem"><?= h($b['so_ban']) ?></span>
                    <span class="tt <?= $ngung ? 'tt-huy' : 'tt-xong' ?>"><?= h($b['trang_thai']) ?></span></div>
                <div class="rat-nho mo" style="text-align:left"><?= h($b['khu_vuc']) ?></div>
                <div style="background:#fff;padding:10px;border-radius:10px;margin:10px auto;width:max-content">
                    <div class="qr" data-url="<?= h(duong_dan_qr($b['ma_qr'])) ?>"></div>
                </div>
                <div class="rat-nho mo ma-nguon" style="word-break:break-all"><?= h($b['ma_qr']) ?></div>
                <div class="hang-giua khong-in" style="margin-top:10px;flex-wrap:wrap;gap:6px">
                    <form method="post"><?= an('ban_trang_thai', 'ban') ?>
                        <input type="hidden" name="id_ban" value="<?= (int) $b['id_ban'] ?>">
                        <button class="nut nut-vien nut-nho" name="trang_thai" value="<?= $ngung ? 'Hoạt động' : 'Tạm ngừng' ?>"><?= $ngung ? 'Bật bàn' : 'Tạm ngừng' ?></button>
                    </form>
                    <form method="post" onsubmit="return confirm('Sinh mã QR mới cho bàn <?= h($b['so_ban']) ?>? Mã đang dán trên bàn sẽ hết hiệu lực.')">
                        <?= an('ban_doi_qr', 'ban') ?>
                        <input type="hidden" name="id_ban" value="<?= (int) $b['id_ban'] ?>">
                        <button class="nut nut-xam nut-nho">Đổi mã QR</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <style>
    @media print {
        body { background: #fff; color: #000; }
        .dau-trang, .dieu-huong, .chan-trang, h1, .luoi:not(#luoi-qr), form.the, p, .tb, .khong-in, .rat-nho.mo + * + .khong-in { display: none !important; }
        #luoi-qr { grid-template-columns: repeat(3, 1fr) !important; }
        .the-in { background: #fff !important; color: #000; border: 1px dashed #999 !important; break-inside: avoid; opacity: 1 !important; }
        .the-in .tt { display: none; }
    }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
    document.querySelectorAll('.qr').forEach((el) => {
        if (window.QRCode) {
            new QRCode(el, { text: el.dataset.url, width: 150, height: 150,
                             colorDark: '#16110F', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
        } else {
            el.innerHTML = '<a href="' + el.dataset.url + '" style="color:#B0141F;font-size:.8rem">Mở link bàn</a>';
        }
    });
    </script>

<?php /* ===================== TAI KHOAN ===================== */
elseif ($tab === 'tai-khoan'):
    $ds = Db::tat_ca('SELECT * FROM TaiKhoan ORDER BY id_tai_khoan');
?>
    <form method="post" class="the cach-duoi">
        <?= an('tk_luu', 'tai-khoan') ?>
        <h3>Tạo tài khoản nhân viên</h3>
        <div class="luoi" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr))">
            <div><label>Tên đăng nhập</label><input type="text" name="ten_dang_nhap" required></div>
            <div><label>Họ tên</label><input type="text" name="ho_ten"></div>
            <div><label>Mật khẩu (≥ 6 ký tự)</label><input type="password" name="mat_khau" minlength="6" required></div>
            <div><label>Vai trò</label>
                <select name="vai_tro">
                    <?php foreach (VAI_TRO as $k => $v): if ($k === 'khach') continue; ?>
                        <option value="<?= $k ?>"><?= h($v) ?></option>
                    <?php endforeach; ?>
                </select></div>
        </div>
        <button class="nut" type="submit" style="margin-top:12px">+ Tạo tài khoản</button>
        <div class="rat-nho mo" style="margin-top:8px">Mật khẩu được lưu dưới dạng băm (password_hash), không lưu dạng rõ.</div>
    </form>

    <div class="the bang-cuon" style="padding:0">
        <table class="bang">
            <thead><tr><th>Tên đăng nhập</th><th>Họ tên</th><th>Vai trò</th><th>Trạng thái</th><th class="phai">Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($ds as $t): $khoa = $t['trang_thai'] !== 'Hoạt động'; ?>
                <tr>
                    <td class="ma-nguon"><?= h($t['ten_dang_nhap']) ?></td>
                    <td><?= h($t['ho_ten']) ?></td>
                    <td><?= h(VAI_TRO[$t['vai_tro']] ?? $t['vai_tro']) ?></td>
                    <td><span class="tt <?= $khoa ? 'tt-huy' : 'tt-xong' ?>"><?= h($t['trang_thai']) ?></span></td>
                    <td class="phai">
                        <?php if ((int) $t['id_tai_khoan'] !== (int) $nv['id_tai_khoan']): ?>
                        <form method="post"><?= an('tk_trang_thai', 'tai-khoan') ?>
                            <input type="hidden" name="id_tai_khoan" value="<?= (int) $t['id_tai_khoan'] ?>">
                            <button class="nut nut-vien nut-nho" name="trang_thai" value="<?= $khoa ? 'Hoạt động' : 'Đã khóa' ?>"><?= $khoa ? 'Mở khóa' : 'Khóa' ?></button>
                        </form>
                        <?php else: ?><span class="rat-nho mo">Đang đăng nhập</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php /* ===================== LICH SU ===================== */
elseif ($tab === 'lich-su'):
    $ngay  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['ngay'] ?? '') ? $_GET['ngay'] : date('Y-m-d');
    $ban   = trim((string) ($_GET['ban'] ?? ''));
    $ds_ban = Db::tat_ca('SELECT so_ban FROM BanAn ORDER BY so_ban');

    $sql = "SELECT d.*, b.so_ban, p.id_phien FROM DonHang d
            JOIN PhienPhucVu p ON p.id_phien = d.id_phien
            JOIN BanAn b ON b.id_ban = p.id_ban
            WHERE d.tao_luc >= ? AND d.tao_luc <= ?";
    $ts  = [$ngay . ' 00:00:00', $ngay . ' 23:59:59'];
    if ($ban !== '') { $sql .= ' AND b.so_ban = ?'; $ts[] = $ban; }
    $don = Db::tat_ca($sql . ' ORDER BY d.tao_luc DESC', $ts);

    $ban_chay = Db::tat_ca(
        "SELECT c.ten_mon_luc_dat AS ten, SUM(c.so_luong) AS sl, SUM(c.so_luong * c.don_gia_luc_dat) AS tien
         FROM ChiTietDonHang c JOIN DonHang d ON d.id_don = c.id_don
         WHERE d.trang_thai <> 'Đã hủy' AND d.tao_luc >= ? AND d.tao_luc <= ?
         GROUP BY c.ten_mon_luc_dat ORDER BY sl DESC LIMIT 5",
        [$ngay . ' 00:00:00', $ngay . ' 23:59:59']
    );
?>
    <form method="get" class="the cach-duoi hang" style="flex-wrap:wrap;align-items:flex-end">
        <input type="hidden" name="tab" value="lich-su">
        <div style="width:190px"><label>Ngày</label><input type="text" name="ngay" value="<?= h($ngay) ?>" placeholder="YYYY-MM-DD"></div>
        <div style="width:160px"><label>Bàn</label>
            <select name="ban"><option value="">Tất cả</option>
                <?php foreach ($ds_ban as $b): ?><option <?= $ban === $b['so_ban'] ? 'selected' : '' ?>><?= h($b['so_ban']) ?></option><?php endforeach; ?>
            </select></div>
        <button class="nut" type="submit">Tra cứu</button>
    </form>

    <?php if ($ban_chay): ?>
        <h3>🏆 Món bán chạy trong ngày</h3>
        <div class="luoi cach-duoi" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
            <?php foreach ($ban_chay as $i => $m): ?>
                <div class="o-so-lieu"><div class="nhan">#<?= $i + 1 ?></div>
                    <div class="dam"><?= h($m['ten']) ?></div>
                    <div class="nho"><span class="gia"><?= (int) $m['sl'] ?> phần</span> · <span class="mo"><?= tien($m['tien']) ?></span></div></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="the bang-cuon" style="padding:0">
        <table class="bang">
            <thead><tr><th>Đơn</th><th>Phiên</th><th>Bàn</th><th>Gửi lúc</th><th>Món</th><th>Trạng thái</th><th class="phai">Cộng</th></tr></thead>
            <tbody>
            <?php foreach ($don as $d):
                $ct = Db::tat_ca('SELECT * FROM ChiTietDonHang WHERE id_don = ?', [$d['id_don']]);
                $tong = array_sum(array_map(fn($c) => $c['so_luong'] * $c['don_gia_luc_dat'], $ct)); ?>
                <tr>
                    <td>#<?= (int) $d['id_don'] ?></td>
                    <td>#<?= (int) $d['id_phien'] ?></td>
                    <td class="dam"><?= h($d['so_ban']) ?></td>
                    <td><?= gio($d['tao_luc']) ?></td>
                    <td class="co-the-xuong nho"><?= implode('<br>', array_map(fn($c) => (int) $c['so_luong'] . ' × ' . h($c['ten_mon_luc_dat'])
                        . ($c['ghi_chu'] ? ' <span style="color:var(--vang)">(' . h($c['ghi_chu']) . ')</span>' : ''), $ct)) ?>
                        <?= $d['ly_do_huy'] ? '<div class="rat-nho mo">Lý do hủy: ' . h($d['ly_do_huy']) . '</div>' : '' ?></td>
                    <td><span class="tt <?= lop_trang_thai($d['trang_thai']) ?>"><?= h($d['trang_thai']) ?></span></td>
                    <td class="phai <?= $d['trang_thai'] === 'Đã hủy' ? 'mo' : 'gia' ?>"><?= $d['trang_thai'] === 'Đã hủy' ? 'không tính' : tien($tong) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$don): ?>
                <tr><td colspan="7" class="mo tren-giua" style="padding:22px">Không có đơn nào trong ngày <?= h($ngay) ?>.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <form method="post" class="the cach-tren giua-hai" style="border-color:rgba(228,103,127,.4)"
          onsubmit="return confirm('Xóa toàn bộ dữ liệu và nạp lại dữ liệu mẫu? Thao tác này không hoàn tác được.')">
        <?= an('cai_lai', 'lich-su') ?>
        <div class="nho"><b>Khôi phục dữ liệu mẫu</b><div class="mo rat-nho">Dùng trước buổi demo để bắt đầu lại từ đầu.</div></div>
        <button class="nut nut-vien nut-nho">Cài lại dữ liệu mẫu</button>
    </form>
<?php endif; ?>

<?php chan_trang('noi_bo'); ?>
