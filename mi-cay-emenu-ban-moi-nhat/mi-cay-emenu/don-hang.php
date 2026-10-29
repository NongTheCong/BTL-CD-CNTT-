<?php
/**
 * MAN HINH KHACH 5 - Theo doi trang thai don
 * Xem trang thai tung don cua phien hien tai. Yeu cau huy phai qua nhan vien.
 * Du lieu lay qua GET /api/orders, tu tai lai moi 5 giay.
 */
require_once __DIR__ . '/app/layout.php';

[$nc, $phien, $khoa] = ngu_canh_goi_mon();

dau_trang('Đơn của bàn');
?>

<div class="cach-tren giua-hai">
    <a href="/menu.php" class="nho">← Gọi thêm món</a>
    <span class="rat-nho mo">Tự cập nhật mỗi <?= (int) cf()['chu_ky_tai_lai'] ?> giây</span>
</div>
<h1 style="margin-top:10px">Đơn của bàn <?= h($nc['so_ban']) ?></h1>

<?php if (isset($_GET['moi'])): ?>
    <div class="tb tb-ok">
        ✅ <?= isset($_GET['trung'])
            ? 'Đơn này đã được ghi nhận trước đó — hệ thống không tạo thêm đơn trùng.'
            : 'Đã gửi đơn #' . (int) $_GET['moi'] . ' tới bếp. Món sẽ được mang ra ngay khi nấu xong!' ?>
    </div>
<?php endif; ?>

<?php bao_khoa_gui_don($khoa); ?>

<?php if ($khoa === 'chua_mo' || $khoa === 'het_phien'): ?>
    <div class="trong-rong"><div class="hinh">🪑</div>
        <p><?= $khoa === 'het_phien' ? 'Phiên đã kết thúc.' : 'Chưa có đơn nào.' ?></p>
        <a class="nut nut-vien" href="/index.php">Quét lại mã QR</a></div>
<?php else: ?>
    <div class="o-so-lieu cach-duoi giua-hai">
        <div>
            <div class="nhan">Tạm tính của bàn (không gồm đơn đã hủy)</div>
            <div class="so" id="tong-phien">—</div>
        </div>
        <span class="tt" id="tt-phien">—</span>
    </div>

    <div id="ds-don" class="doc"></div>

    <div class="the cach-tren nho mo">
        💡 Muốn hủy món? Đơn chỉ hủy được khi bếp <b>chưa bắt đầu nấu</b> (trạng thái “Mới nhận”).
        Vui lòng báo nhân viên phục vụ để được hỗ trợ. Thanh toán bằng tiền mặt tại quầy thu ngân.
    </div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const BUOC = ['Mới nhận', 'Đang chế biến', 'Chờ phục vụ', 'Đã phục vụ'];
    const LOP  = {
        'Mới nhận': 'tt-moi', 'Đang chế biến': 'tt-nau', 'Chờ phục vụ': 'tt-cho',
        'Đã phục vụ': 'tt-xong', 'Đã hủy': 'tt-huy',
        'Đang mở': 'tt-moi', 'Chờ thanh toán': 'tt-cho', 'Đã đóng': 'tt-xong',
    };
    const gio = (s) => s ? s.slice(11, 16) : '';

    function moc(tt) {
        if (tt === 'Đã hủy') return '';
        const i = BUOC.indexOf(tt);
        return `<div class="moc">${BUOC.map((b, j) => `
            <div class="b ${j < i || tt === 'Đã phục vụ' ? 'xong' : (j === i ? 'dang' : '')}">
                <div class="diem">${j < i || tt === 'Đã phục vụ' ? '✓' : j + 1}</div>${b}
            </div>`).join('')}</div>`;
    }

    async function tai() {
        const kq = await App.api('/api/orders');
        const ds = document.getElementById('ds-don');
        if (!kq.thanh_cong) {
            ds.innerHTML = `<div class="tb tb-loi">${App.h(kq.thong_bao)}</div>`;
            return;
        }
        document.getElementById('tong-phien').textContent = App.tien(kq.phien.tong);
        const ttp = document.getElementById('tt-phien');
        ttp.textContent = 'Phiên ' + kq.phien.trang_thai.toLowerCase();
        ttp.className = 'tt ' + (LOP[kq.phien.trang_thai] || '');

        if (!kq.don.length) {
            ds.innerHTML = `<div class="trong-rong"><div class="hinh">🍜</div>
                <p>Bàn chưa gọi món nào.</p><a class="nut" href="/menu.php">Gọi món ngay</a></div>`;
            return;
        }

        ds.innerHTML = kq.don.slice().reverse().map((d) => `
            <div class="the" ${d.trang_thai === 'Đã hủy' ? 'style="opacity:.6"' : ''}>
                <div class="giua-hai">
                    <div><b>Đơn #${d.id_don}</b> <span class="rat-nho mo">· gửi lúc ${gio(d.tao_luc)}</span></div>
                    <span class="tt ${LOP[d.trang_thai] || ''}">${App.h(d.trang_thai)}</span>
                </div>
                ${moc(d.trang_thai)}
                <div style="margin-top:10px">
                ${d.chi_tiet.map((c) => `
                    <div class="giua-hai nho" style="padding:5px 0;border-top:1px dashed var(--vien)">
                        <div>${c.so_luong} × ${App.h(c.ten_mon)}
                            ${c.ghi_chu ? `<div>${App.ghiChu(c.ghi_chu)}</div>` : ''}</div>
                        <span>${App.tien(c.don_gia * c.so_luong)}</span>
                    </div>`).join('')}
                </div>
                ${d.trang_thai === 'Đã hủy'
                    ? `<div class="rat-nho mo" style="margin-top:6px">Lý do hủy: ${App.h(d.ly_do_huy)} · không tính tiền</div>`
                    : `<div class="giua-hai" style="margin-top:6px"><span class="nho mo">Cộng đơn</span>
                       <b class="gia">${App.tien(d.tong)}</b></div>`}
            </div>`).join('');
    }

    App.taiLaiDinhKy(<?= (int) cf()['chu_ky_tai_lai'] ?>, tai);
});
</script>
<?php endif; ?>

<?php chan_trang(); ?>
