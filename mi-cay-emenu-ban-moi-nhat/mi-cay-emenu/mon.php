<?php
/**
 * MAN HINH KHACH 3 - Chi tiet mon
 * Xem chi tiet truoc khi them vao gio; chon cap do cay, so luong, ghi chu rieng.
 */
require_once __DIR__ . '/app/layout.php';

[$nc, $phien, $khoa] = ngu_canh_goi_mon();

$mon = NghiepVu::mon((int) ($_GET['id'] ?? 0));
if (!$mon) {
    header('Location: /menu.php');
    exit;
}
$het     = $mon['trang_thai_ban'] !== 'Đang bán';
$cay     = can_chon_cay($mon, $mon['ten_danh_muc']);
$cap_do  = cf()['cap_do_cay'];
$gc_max  = cf()['ghi_chu_max'];

dau_trang($mon['ten_mon']);
?>

<div class="cach-tren">
    <a href="/menu.php#dm-<?= (int) $mon['id_danh_muc'] ?>" class="nho">← Quay lại thực đơn</a>
</div>

<div class="tren-giua" style="padding:26px 0 12px">
    <div style="display:inline-grid;place-items:center;width:140px;height:140px;border-radius:28px;
                font-size:4.4rem;background:radial-gradient(circle at 35% 30%,#3A3326,#1A1A1C);
                border:1px solid rgba(212,162,76,.3);box-shadow:0 18px 50px rgba(212,162,76,.16)">
        <?= h($mon['anh'] ?: '🍜') ?>
    </div>
</div>

<div class="rat-nho mo tren-giua"><?= h($mon['ten_danh_muc']) ?></div>
<h1 class="tren-giua" style="margin-top:4px"><?= h($mon['ten_mon']) ?></h1>
<p class="tren-giua mo"><?= h($mon['mo_ta']) ?></p>
<div class="tren-giua"><span class="gia" style="font-size:1.5rem"><?= tien($mon['gia']) ?></span></div>

<?php if ($het): ?>
    <div class="tb tb-loi cach-tren">Món này đã hết hàng, vui lòng chọn món khác.</div>
<?php else: ?>

<form id="form-mon" class="doc cach-tren" onsubmit="return false">

    <?php if ($cay): ?>
    <div class="the">
        <label>Cấp độ cay <span style="color:var(--do-nhat)">*</span></label>
        <div class="chon-cay" id="chon-cay">
            <?php foreach ($cap_do as $so => $ten): ?>
                <div class="o-cay <?= $so === 2 ? 'chon' : '' ?>" data-cap="<?= $so ?>"
                     data-ten="<?= h($ten) ?>" role="button" tabindex="0"><?= $so ?></div>
            <?php endforeach; ?>
        </div>
        <div class="ten-cay" id="ten-cay" style="margin-top:8px"><?= h($cap_do[2]) ?></div>
    </div>
    <?php endif; ?>

    <div class="the">
        <label for="ghi-chu">Ghi chú cho bếp <span class="mo">(không bắt buộc)</span></label>
        <textarea id="ghi-chu" maxlength="<?= $gc_max - 30 ?>"
                  placeholder="Ví dụ: không hành, ít rau, thêm nước dùng…"></textarea>
        <div class="rat-nho mo" style="margin-top:6px">Tối đa <?= $gc_max ?> ký tự (kể cả cấp độ cay).</div>
    </div>

    <div class="the giua-hai">
        <span class="dam">Số lượng</span>
        <div class="so-luong">
            <button type="button" id="bot" aria-label="Giảm">−</button>
            <span class="so" id="so">1</span>
            <button type="button" id="them" aria-label="Tăng">+</button>
        </div>
    </div>

    <div class="thanh-duoi">
        <div class="tong-tien"><span>Thành tiền dự kiến</span><b id="thanh-tien"><?= tien($mon['gia']) ?></b></div>
        <button class="nut nut-rong" id="nut-them" type="button">🛒 Thêm vào giỏ hàng</button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const MON = <?= json_encode([
        'ma_mon'      => (int) $mon['id_mon'],
        'ten_mon'     => $mon['ten_mon'],
        'anh'         => $mon['anh'],
        'gia_vua_xem' => (float) $mon['gia'],
    ], JSON_UNESCAPED_UNICODE) ?>;
    const CO_CAY = <?= $cay ? 'true' : 'false' ?>;

    let soLuong = 1;
    let capCay  = 2;
    let tenCay  = <?= json_encode($cap_do[2], JSON_UNESCAPED_UNICODE) ?>;

    const ve = () => {
        document.getElementById('so').textContent = soLuong;
        document.getElementById('thanh-tien').textContent = App.tien(MON.gia_vua_xem * soLuong);
    };
    document.getElementById('bot').onclick  = () => { soLuong = Math.max(1, soLuong - 1); ve(); };
    document.getElementById('them').onclick = () => { soLuong = Math.min(99, soLuong + 1); ve(); };

    document.querySelectorAll('.o-cay').forEach((o) => {
        const chon = () => {
            document.querySelectorAll('.o-cay').forEach((x) => x.classList.remove('chon'));
            o.classList.add('chon');
            capCay = +o.dataset.cap;
            tenCay = o.dataset.ten;
            document.getElementById('ten-cay').textContent = tenCay + (capCay >= 6 ? ' 🔥🔥' : '');
        };
        o.addEventListener('click', chon);
        o.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') chon(); });
    });

    document.getElementById('nut-them').onclick = () => {
        const rieng = document.getElementById('ghi-chu').value.trim();
        // Cap do cay duoc ghi vao truong ghi_chu cua ChiTietDonHang
        const ghiChu = [CO_CAY ? 'Cấp ' + capCay : '', rieng].filter(Boolean).join(' · ');
        App.themVaoGio({ ...MON, so_luong: soLuong, ghi_chu: ghiChu });
        App.bao('Đã thêm ' + soLuong + ' × ' + MON.ten_mon);
        setTimeout(() => { location.href = '/menu.php'; }, 650);
    };
});
</script>
<?php endif; ?>

<?php chan_trang(); ?>
