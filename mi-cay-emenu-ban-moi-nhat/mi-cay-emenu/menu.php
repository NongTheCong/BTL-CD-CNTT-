<?php
/**
 * MAN HINH KHACH 2 - Thuc don theo danh muc
 * Danh muc, anh, mo ta va gia tung mon. Mon het hang hien mo, khong chon duoc.
 */
require_once __DIR__ . '/app/layout.php';

[$nc, $phien, $khoa] = ngu_canh_goi_mon();

$danh_muc = array_values(array_filter(
    NghiepVu::thuc_don(true),
    fn($dm) => $dm['trang_thai'] === 'Hiển thị' && $dm['mon']
));
// An mon da ngung ban; mon het hang van hien (mo) de khach biet
foreach ($danh_muc as &$dm) {
    $dm['mon'] = array_values(array_filter($dm['mon'], fn($m) => $m['trang_thai_ban'] !== 'Ngừng bán'));
}
unset($dm);

dau_trang('Thực đơn');
?>

<div class="cach-tren"></div>
<?php bao_khoa_gui_don($khoa); ?>

<div class="giua-hai" style="margin-top:6px">
    <div>
        <h1 style="margin:0">Thực đơn</h1>
        <div class="nho mo"><?= h($nc['khu_vuc']) ?> · Bàn <?= h($nc['so_ban']) ?></div>
    </div>
    <a class="nut nut-vien nut-nho" href="/don-hang.php">📋 Đơn của bàn</a>
</div>

<nav class="loc-danh-muc" style="margin-top:12px">
    <?php foreach ($danh_muc as $i => $dm): ?>
        <a class="chip <?= $i === 0 ? 'chon' : '' ?>" href="#dm-<?= (int) $dm['id_danh_muc'] ?>"
           data-chip="<?= (int) $dm['id_danh_muc'] ?>"><?= h($dm['ten_danh_muc']) ?></a>
    <?php endforeach; ?>
</nav>

<div class="the cach-tren" style="background:linear-gradient(120deg,#2B2416,#161617);border-color:rgba(212,162,76,.4)">
    <div class="hang">
        <div style="font-size:1.8rem">🌶️</div>
        <div class="nho">
            <b>Mì cay 7 cấp độ</b> — chọn từ cấp 1 (cay nhẹ) đến cấp 7 (thử thách).
            Lần đầu ăn nên chọn cấp 2 – 3.
        </div>
    </div>
</div>

<?php foreach ($danh_muc as $dm): ?>
    <section id="dm-<?= (int) $dm['id_danh_muc'] ?>" data-nhom="<?= (int) $dm['id_danh_muc'] ?>"
             style="scroll-margin-top:122px">
        <div class="ten-nhom"><?= h($dm['ten_danh_muc']) ?> <span><?= count($dm['mon']) ?> món</span></div>

        <?php foreach ($dm['mon'] as $m):
            $het = $m['trang_thai_ban'] !== 'Đang bán';
            $cay = can_chon_cay($m, $dm['ten_danh_muc']);
        ?>
            <div class="the-mon <?= $het ? 'het' : '' ?>">
                <a href="<?= $het ? '#' : '/mon.php?id=' . (int) $m['id_mon'] ?>"
                   class="anh-mon" style="text-decoration:none"><?= h($m['anh'] ?: '🍜') ?></a>
                <div class="lon-dan">
                    <a href="<?= $het ? '#' : '/mon.php?id=' . (int) $m['id_mon'] ?>"
                       style="color:inherit;text-decoration:none">
                        <div class="ten"><?= h($m['ten_mon']) ?></div>
                        <div class="mo-ta"><?= h($m['mo_ta']) ?></div>
                    </a>
                    <div class="giua-hai">
                        <span class="gia"><?= tien($m['gia']) ?></span>
                        <?php if ($het): ?>
                            <span class="tt tt-huy">Hết hàng</span>
                        <?php elseif ($cay): ?>
                            <a class="nut nut-nho" href="/mon.php?id=<?= (int) $m['id_mon'] ?>">Chọn cấp cay</a>
                        <?php else: ?>
                            <button class="nut nut-nho" type="button"
                                    data-them='<?= h(json_encode([
                                        'ma_mon'      => (int) $m['id_mon'],
                                        'ten_mon'     => $m['ten_mon'],
                                        'anh'         => $m['anh'],
                                        'gia_vua_xem' => (float) $m['gia'],
                                    ], JSON_UNESCAPED_UNICODE)) ?>'>+ Thêm</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </section>
<?php endforeach; ?>

<div class="thanh-duoi" id="thanh-gio">
    <div class="tong-tien">
        <span><span data-so-gio>0</span> món trong giỏ</span>
        <b id="tong-gio">0 đ</b>
    </div>
    <a class="nut nut-rong" href="/gio-hang.php">Xem giỏ hàng →</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const capNhatTong = () => {
        document.getElementById('tong-gio').textContent = App.tien(App.tongDuKien());
        document.getElementById('thanh-gio').style.display = App.layGio().length ? '' : 'none';
    };

    document.querySelectorAll('[data-them]').forEach((nut) => {
        nut.addEventListener('click', () => {
            const mon = JSON.parse(nut.dataset.them);
            App.themVaoGio({ ...mon, so_luong: 1, ghi_chu: '' });
            App.bao('Đã thêm ' + mon.ten_mon + ' vào giỏ');
            capNhatTong();
        });
    });

    // Danh dau danh muc dang xem khi cuon
    const chips = document.querySelectorAll('[data-chip]');
    const quan_sat = new IntersectionObserver((muc) => {
        muc.forEach((m) => {
            if (!m.isIntersecting) return;
            chips.forEach((c) => c.classList.toggle('chon', c.dataset.chip === m.target.dataset.nhom));
            const chon = document.querySelector('.chip.chon');
            if (chon) {
                const nav = chon.parentElement;
                nav.scrollTo({ left: chon.offsetLeft - nav.clientWidth / 2 + chon.clientWidth / 2,
                               behavior: 'smooth' });
            }
        });
    }, { rootMargin: '-130px 0px -60% 0px' });
    document.querySelectorAll('[data-nhom]').forEach((s) => quan_sat.observe(s));

    capNhatTong();
});
</script>

<?php chan_trang(); ?>
