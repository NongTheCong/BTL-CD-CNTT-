<?php
/**
 * MAN HINH KHACH 1 - Trang quet QR (nhan dien ban)
 * Ma QR dan tren ban tro toi: /index.php?qr=<ma_qr>
 * May chu tu gan ngu canh ban va phien, khach khong nhap so ban.
 */
require_once __DIR__ . '/app/layout.php';

$loi = null;

if (isset($_GET['qr'])) {
    $kq = NghiepVu::ngu_canh_ban(trim((string) $_GET['qr']));
    if ($kq['ok']) {
        luu_ngu_canh_khach($kq['ban'], $kq['phien']);
        header('Location: /menu.php');
        exit;
    }
    unset($_SESSION['ngu_canh']);
    $loi = $kq['thong_bao'];
}

$ds_ban = Db::tat_ca('SELECT * FROM BanAn ORDER BY khu_vuc, so_ban');
$quan   = cf()['quan'];

dau_trang('Quét mã QR tại bàn');
?>

<section class="anh-hung">
    <div class="lua-lon">🍜🌶️</div>
    <h1><?= h($quan['ten']) ?></h1>
    <div class="slogan"><?= h($quan['slogan']) ?></div>
    <p class="mo-ta">Quét mã QR dán trên bàn để xem thực đơn và gọi món ngay trên điện thoại —
        không cần đăng nhập, không cần gọi nhân viên.</p>
</section>

<?php if ($loi): ?>
    <div class="tb tb-loi">⚠️ <?= h($loi) ?></div>
<?php endif; ?>

<div class="the cach-duoi">
    <div class="hang" style="align-items:flex-start">
        <div style="font-size:2rem;line-height:1">📱</div>
        <div>
            <h3>Cách gọi món</h3>
            <ol class="nho mo" style="margin:0;padding-left:18px">
                <li>Nhân viên mở phiên phục vụ khi bạn ngồi vào bàn.</li>
                <li>Quét mã QR trên bàn bằng camera điện thoại.</li>
                <li>Chọn món, chọn cấp độ cay từ 1 đến 7, gửi đơn.</li>
                <li>Theo dõi món đang nấu, thanh toán tại quầy khi xong bữa.</li>
            </ol>
        </div>
    </div>
</div>

<div class="the cach-duoi tren-giua" id="khung-quet">
    <h3>📷 Quét mã QR trên bàn</h3>
    <p class="nho mo" style="margin-bottom:12px">Mở camera điện thoại và hướng vào mã QR dán trên bàn,
        hoặc dùng nút bên dưới để quét ngay trên trang này.</p>
    <button class="nut nut-rong" type="button" id="nut-quet">Bật camera để quét</button>
    <div id="vung-quet" class="an" style="margin-top:14px;border-radius:12px;overflow:hidden"></div>
    <button class="nut nut-vien nut-rong an" type="button" id="nut-tat" style="margin-top:10px">Tắt camera</button>
    <div id="loi-quet" class="rat-nho" style="margin-top:8px;color:var(--do-nhat)"></div>
</div>

<h2 class="cach-tren">Mã QR của từng bàn</h2>
<p class="nho mo">Đây là mã QR dán trên mỗi bàn. Quét bằng điện thoại cùng mạng Wi-Fi với máy chủ,
    hoặc bấm <b>Vào bàn</b> để thử ngay trên máy tính.</p>

<?php
$theo_khu = [];
foreach ($ds_ban as $b) {
    $theo_khu[$b['khu_vuc']][] = $b;
}
foreach ($theo_khu as $khu => $ds): ?>
    <div class="ten-nhom"><?= h($khu) ?></div>
    <div class="luoi-ban">
        <?php foreach ($ds as $b):
            $ngung = $b['trang_thai'] !== 'Hoạt động';
            $mo = Db::mot("SELECT id_phien FROM PhienPhucVu WHERE id_ban = ? AND trang_thai = 'Đang mở'",
                          [$b['id_ban']]);
        ?>
            <div class="the-qr <?= $ngung ? 'ngung' : '' ?>">
                <div class="giua-hai">
                    <span class="so-ban-lon"><?= h($b['so_ban']) ?></span>
                    <?php if ($ngung): ?>
                        <span class="tt tt-huy">Tạm ngừng</span>
                    <?php elseif ($mo): ?>
                        <span class="tt tt-xong">Đang phục vụ</span>
                    <?php else: ?>
                        <span class="tt tt-huy">Chưa mở</span>
                    <?php endif; ?>
                </div>
                <div class="nen-qr">
                    <div class="qr" data-url="<?= h(duong_dan_qr($b['ma_qr'])) ?>"
                         role="img" aria-label="Mã QR bàn <?= h($b['so_ban']) ?>"></div>
                </div>
                <a class="nut nut-nho nut-rong" href="/index.php?qr=<?= urlencode($b['ma_qr']) ?>">Vào bàn <?= h($b['so_ban']) ?> →</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    /* Ve anh QR cho tung ban */
    document.querySelectorAll('.qr').forEach((el) => {
        if (window.QRCode) {
            // Vua khit the ban tren dien thoai man hinh hep
            const co = Math.max(96, Math.min(132, el.closest('.the-qr').clientWidth - 52));
            new QRCode(el, { text: el.dataset.url, width: co, height: co,
                             colorDark: '#16110F', colorLight: '#ffffff',
                             correctLevel: QRCode.CorrectLevel.M });
        } else {
            el.textContent = 'Không tải được ảnh QR';
        }
    });

    /* Quet QR bang camera ngay tren trang (can https hoac localhost) */
    const nutQuet = document.getElementById('nut-quet');
    const nutTat  = document.getElementById('nut-tat');
    const vung    = document.getElementById('vung-quet');
    const loi     = document.getElementById('loi-quet');
    let mayQuet   = null;

    if (!window.isSecureContext || !navigator.mediaDevices || !window.Html5Qrcode) {
        nutQuet.disabled = true;
        nutQuet.textContent = 'Dùng camera điện thoại để quét';
        loi.style.color = 'var(--xam)';
        loi.textContent = 'Trình duyệt chỉ cho quét trong trang khi mở bằng https hoặc localhost. '
                        + 'Trên điện thoại, hãy dùng ứng dụng camera để quét mã.';
        return;
    }

    const tat = async () => {
        if (mayQuet) { try { await mayQuet.stop(); } catch (e) {} mayQuet = null; }
        vung.classList.add('an'); nutTat.classList.add('an'); nutQuet.classList.remove('an');
    };

    nutQuet.addEventListener('click', async () => {
        loi.textContent = '';
        vung.innerHTML = '<div id="camera-quet"></div>';
        vung.classList.remove('an'); nutTat.classList.remove('an'); nutQuet.classList.add('an');
        mayQuet = new Html5Qrcode('camera-quet');
        try {
            await mayQuet.start({ facingMode: 'environment' }, { fps: 10, qrbox: 220 }, async (chu) => {
                // Chi nhan ma QR cua quan: lay tham so qr roi mo tren chinh may chu nay
                let ma = null;
                try { ma = new URL(chu).searchParams.get('qr'); } catch (e) { ma = null; }
                if (!ma) { loi.textContent = 'Đây không phải mã QR bàn của quán.'; return; }
                await tat();
                location.href = '/index.php?qr=' + encodeURIComponent(ma);
            });
        } catch (e) {
            await tat();
            loi.textContent = 'Không mở được camera: ' + (e && e.message ? e.message : e);
        }
    });
    nutTat.addEventListener('click', tat);
});
</script>

<?php chan_trang(); ?>
