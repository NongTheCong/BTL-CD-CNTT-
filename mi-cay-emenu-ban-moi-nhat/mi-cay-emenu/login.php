<?php
/**
 * Man hinh dang nhap - nhan vien dang nhap theo vai tro.
 * Mat khau so khop voi ban bam (password_verify), khong luu dang ro.
 */
require_once __DIR__ . '/app/layout.php';

/** Man hinh mac dinh cua tung vai tro sau khi dang nhap. */
function trang_chinh(string $vai_tro): string
{
    return match ($vai_tro) {
        'bep'      => '/bep.php',
        'phuc_vu'  => '/phuc-vu.php',
        'thu_ngan' => '/thu-ngan.php',
        'quan_tri' => '/quan-tri.php',
        default    => '/index.php',
    };
}

$loi = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ten = trim((string) ($_POST['ten_dang_nhap'] ?? ''));
    $mk  = (string) ($_POST['mat_khau'] ?? '');

    $tk = Db::mot('SELECT * FROM TaiKhoan WHERE ten_dang_nhap = ?', [$ten]);

    if (!$tk || !password_verify($mk, $tk['mat_khau_bam'])) {
        $loi = 'Sai tên đăng nhập hoặc mật khẩu.';
    } elseif ($tk['trang_thai'] !== 'Hoạt động') {
        $loi = 'Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên.';
    } else {
        session_regenerate_id(true);
        unset($_SESSION['ngu_canh']);   // nhan vien khong mang ngu canh ban cua khach
        $_SESSION['nhan_vien'] = [
            'id_tai_khoan'  => (int) $tk['id_tai_khoan'],
            'ten_dang_nhap' => $tk['ten_dang_nhap'],
            'ho_ten'        => $tk['ho_ten'],
            'vai_tro'       => $tk['vai_tro'],
        ];
        header('Location: ' . trang_chinh($tk['vai_tro']));
        exit;
    }
}

if (($nv = nhan_vien()) && $_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['loi'])) {
    header('Location: ' . trang_chinh($nv['vai_tro']));
    exit;
}

$tai_khoan_mau = Db::tat_ca('SELECT ten_dang_nhap, ho_ten, vai_tro FROM TaiKhoan ORDER BY id_tai_khoan');

dau_trang('Đăng nhập nhân viên', 'noi_bo');
?>

<div style="max-width:440px;margin:30px auto 0">
    <div class="tren-giua cach-duoi">
        <div style="font-size:2.6rem">🔐</div>
        <h1>Đăng nhập nhân viên</h1>
        <p class="mo nho">Mỗi vai trò chỉ thấy màn hình thuộc quyền của mình.</p>
    </div>

    <?php if ($loi): ?>
        <div class="tb tb-loi"><?= h($loi) ?></div>
    <?php elseif (($_GET['loi'] ?? '') === 'quyen'): ?>
        <div class="tb tb-nhac">Tài khoản hiện tại không có quyền vào màn hình đó. Vui lòng đăng nhập đúng vai trò.</div>
    <?php endif; ?>

    <form method="post" class="the doc">
        <div>
            <label for="ten">Tên đăng nhập</label>
            <input type="text" id="ten" name="ten_dang_nhap" required autocomplete="username"
                   value="<?= h($_POST['ten_dang_nhap'] ?? '') ?>">
        </div>
        <div>
            <label for="mk">Mật khẩu</label>
            <input type="password" id="mk" name="mat_khau" required autocomplete="current-password">
        </div>
        <button class="nut nut-rong" type="submit">Đăng nhập</button>
    </form>

    <div class="the cach-tren">
        <div class="dam nho cach-duoi" style="margin-bottom:8px">Tài khoản dùng thử · mật khẩu <span class="ma-nguon" style="color:var(--vang)">123456</span></div>
        <?php foreach ($tai_khoan_mau as $t): ?>
            <button type="button" class="nut nut-vien nut-nho nut-rong" style="justify-content:space-between;margin-bottom:6px"
                    data-ten="<?= h($t['ten_dang_nhap']) ?>">
                <span class="ma-nguon"><?= h($t['ten_dang_nhap']) ?></span>
                <span class="mo"><?= h(VAI_TRO[$t['vai_tro']] ?? $t['vai_tro']) ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <p class="tren-giua cach-tren"><a href="/index.php">← Về trang khách hàng</a></p>
</div>

<script>
document.querySelectorAll('[data-ten]').forEach((n) => n.addEventListener('click', () => {
    document.getElementById('ten').value = n.dataset.ten;
    document.getElementById('mk').focus();
}));
</script>

<?php chan_trang('noi_bo'); ?>
