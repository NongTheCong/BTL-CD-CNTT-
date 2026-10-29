<?php
/**
 * MAN HINH NOI BO 3 - Thu ngan
 * Tong tien phien, xac nhan da thu, dong phien.
 * Tong = sum(so luong x don gia luc dat), chi cong don chua huy cua dung phien.
 */
require_once __DIR__ . '/app/layout.php';
bat_buoc_quyen('thanh_toan');

$hom_nay = date('Y-m-d');
$da_dong = Db::tat_ca(
    "SELECT p.*, b.so_ban, t.ho_ten AS thu_ngan
     FROM PhienPhucVu p
     JOIN BanAn b ON b.id_ban = p.id_ban
     LEFT JOIN TaiKhoan t ON t.id_tai_khoan = p.id_thu_ngan
     WHERE p.trang_thai = 'Đã đóng' AND p.dong_luc >= ?
     ORDER BY p.dong_luc DESC",
    [$hom_nay . ' 00:00:00']
);
$doanh_thu = array_sum(array_map(fn($p) => (float) $p['tong_thanh_toan'], $da_dong));

dau_trang('Thu ngân', 'noi_bo', 'thu-ngan.php');
?>

<div class="giua-hai cach-duoi">
    <h1 style="margin:0">💵 Thu ngân</h1>
    <div class="hang">
        <div class="o-so-lieu" style="padding:10px 16px">
            <div class="so" style="font-size:1.3rem"><?= tien($doanh_thu) ?></div>
            <div class="nhan">Đã thu hôm nay · <?= count($da_dong) ?> lượt khách</div>
        </div>
    </div>
</div>

<div style="display:grid;gap:20px;grid-template-columns:minmax(260px,.8fr) minmax(0,1.2fr);align-items:start" id="bo-cuc">
    <section>
        <h2>Bàn đang phục vụ</h2>
        <div id="ds-phien" class="doc"></div>
    </section>

    <section>
        <h2>Hóa đơn</h2>
        <div id="hoa-don" class="the-sang">
            <div class="trong-rong" style="color:#8a7a74">Chọn một bàn bên trái để xem hóa đơn.</div>
        </div>
    </section>
</div>

<h2 class="cach-tren">Lịch sử thanh toán hôm nay</h2>
<div class="the bang-cuon" style="padding:0">
    <table class="bang">
        <thead><tr><th>Phiên</th><th>Bàn</th><th>Mở lúc</th><th>Đóng lúc</th><th>Thu ngân</th><th class="phai">Tổng thu</th></tr></thead>
        <tbody>
        <?php foreach ($da_dong as $p): ?>
            <tr>
                <td>#<?= (int) $p['id_phien'] ?></td>
                <td class="dam"><?= h($p['so_ban']) ?></td>
                <td><?= gio($p['mo_luc']) ?></td>
                <td><?= gio($p['dong_luc']) ?></td>
                <td><?= h($p['thu_ngan'] ?? '—') ?></td>
                <td class="phai gia"><?= tien($p['tong_thanh_toan']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$da_dong): ?>
            <tr><td colspan="6" class="mo tren-giua" style="padding:22px">Chưa có lượt thanh toán nào hôm nay.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
@media (max-width: 860px) { #bo-cuc { grid-template-columns: 1fr !important; } }
@media print {
    body { background: #fff; }
    .dau-trang, .dieu-huong, .chan-trang, #ds-phien, h1, h2, .bang-cuon, .o-so-lieu, [data-khong-in] { display: none !important; }
    #bo-cuc { display: block !important; }
    #hoa-don { box-shadow: none; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const QUAN = <?= json_encode(cf()['quan'], JSON_UNESCAPED_UNICODE) ?>;
    let dangChon = null;

    async function taiDs() {
        const r = await fetch('/phuc-vu-du-lieu.php', { credentials: 'same-origin' });
        const kq = await r.json();
        const co = kq.ban.filter((b) => b.phien);
        document.getElementById('ds-phien').innerHTML = co.length ? co.map((b) => `
            <button class="the-ban co-khach" data-phien="${b.phien.id_phien}"
                    style="text-align:left;cursor:pointer;color:inherit;font:inherit;width:100%;
                           ${dangChon == b.phien.id_phien ? 'outline:2px solid var(--do)' : ''}">
                <div class="giua-hai"><span class="so">${App.h(b.so_ban)}</span>
                    <b class="gia">${App.tien(b.tong)}</b></div>
                <div class="khu" style="margin:0">Phiên #${b.phien.id_phien} · ${b.so_don} đơn · mở ${b.phien.mo_luc.slice(11, 16)}</div>
            </button>`).join('')
            : '<div class="the trong-rong nho">Không có bàn nào đang phục vụ.</div>';
    }

    async function taiHoaDon(id) {
        dangChon = id;
        const kq = await App.api('/api/orders?session=' + id);
        const hop = document.getElementById('hoa-don');
        if (!kq.thanh_cong) { hop.innerHTML = `<div class="tb tb-loi">${App.h(kq.thong_bao)}</div>`; return; }

        // Gop cac dong cung ten mon + cung don gia luc dat
        const gop = {};
        const huy = [];
        let chuaXong = 0;
        kq.don.forEach((d) => {
            if (d.trang_thai === 'Đã hủy') { d.chi_tiet.forEach((c) => huy.push(c)); return; }
            if (d.trang_thai !== 'Đã phục vụ') chuaXong++;
            d.chi_tiet.forEach((c) => {
                const k = c.ten_mon + '|' + c.don_gia;
                gop[k] = gop[k] || { ...c, so_luong: 0 };
                gop[k].so_luong += c.so_luong;
            });
        });
        const dong = Object.values(gop);

        hop.innerHTML = `
            <div class="tren-giua" style="margin-bottom:12px">
                <div style="font-weight:800;font-size:1.15rem">🌶️ ${App.h(QUAN.ten)}</div>
                <div style="font-size:.8rem;color:#7a6a64">${App.h(QUAN.dia_chi)} · ${App.h(QUAN.dien_thoai)}</div>
                <div style="margin-top:8px;font-weight:700">HÓA ĐƠN · BÀN ${App.h(kq.phien.so_ban)} · PHIÊN #${kq.phien.id_phien}</div>
                <div style="font-size:.8rem;color:#7a6a64">Mở phiên lúc ${kq.phien.mo_luc.slice(11, 16)} ngày ${kq.phien.mo_luc.slice(8, 10)}/${kq.phien.mo_luc.slice(5, 7)}</div>
            </div>
            <table style="width:100%;border-collapse:collapse;font-size:.92rem">
                <thead><tr style="border-bottom:2px solid #23191A">
                    <th style="text-align:left;padding:6px 0">Món (tên lúc đặt)</th><th style="text-align:center">SL</th>
                    <th style="text-align:right">Đơn giá</th><th style="text-align:right">Thành tiền</th></tr></thead>
                <tbody>
                ${dong.map((c) => `<tr style="border-bottom:1px dashed #d8ccc6">
                    <td style="padding:7px 0">${App.h(c.ten_mon)}</td><td style="text-align:center">${c.so_luong}</td>
                    <td style="text-align:right">${App.tien(c.don_gia)}</td>
                    <td style="text-align:right;font-weight:600">${App.tien(c.don_gia * c.so_luong)}</td></tr>`).join('')}
                ${huy.map((c) => `<tr style="border-bottom:1px dashed #d8ccc6;color:#9a8a84">
                    <td style="padding:7px 0"><s>${App.h(c.ten_mon)}</s> – đơn đã hủy</td><td style="text-align:center">${c.so_luong}</td>
                    <td style="text-align:right">${App.tien(c.don_gia)}</td><td style="text-align:right">không tính</td></tr>`).join('')}
                ${!dong.length && !huy.length ? '<tr><td colspan="4" style="padding:16px 0;text-align:center;color:#8a7a74">Bàn chưa gọi món nào.</td></tr>' : ''}
                </tbody>
                <tfoot><tr style="border-top:2px solid #23191A">
                    <td colspan="3" style="padding:10px 0;font-weight:800">TỔNG PHẢI THU</td>
                    <td style="text-align:right;font-weight:800;font-size:1.25rem;color:#9E1B32">${App.tien(kq.phien.tong)}</td></tr></tfoot>
            </table>
            <div style="font-size:.78rem;color:#7a6a64;margin-top:8px">Chưa gồm thuế, phụ phí. Thanh toán tiền mặt tại quầy.</div>

            <div data-khong-in style="margin-top:16px">
                ${chuaXong ? `<div class="tb tb-loi" style="color:#9B1C1C;background:#FDECEC">
                    Còn ${chuaXong} đơn chưa phục vụ xong — chưa thể đóng phiên.</div>` : ''}
                <div class="hang" style="flex-wrap:wrap">
                    <button class="nut nut-xanh lon-dan" id="nut-thu" ${chuaXong ? 'disabled' : ''}>
                        ✔ Đã thu ${App.tien(kq.phien.tong)} · Đóng phiên</button>
                    <button class="nut nut-xam" onclick="window.print()" style="color:#23191A;background:#eadfd9">🖨 In</button>
                </div>
            </div>`;

        document.getElementById('nut-thu').onclick = async (e) => {
            if (!confirm(`Xác nhận đã thu đủ ${App.tien(kq.phien.tong)} tiền mặt của bàn ${kq.phien.so_ban}?`)) return;
            e.target.disabled = true;
            const r = await App.api(`/api/sessions/${id}/checkout`, { method: 'POST', duLieu: {} });
            App.bao(r.thong_bao, r.thanh_cong ? 'ok' : 'loi');
            if (r.thanh_cong) setTimeout(() => location.reload(), 900);
            else e.target.disabled = false;
        };
        taiDs();
    }

    document.getElementById('ds-phien').addEventListener('click', (e) => {
        const n = e.target.closest('[data-phien]');
        if (n) taiHoaDon(+n.dataset.phien);
    });

    App.taiLaiDinhKy(<?= (int) cf()['chu_ky_tai_lai'] ?>, taiDs);
});
</script>

<?php chan_trang('noi_bo'); ?>
