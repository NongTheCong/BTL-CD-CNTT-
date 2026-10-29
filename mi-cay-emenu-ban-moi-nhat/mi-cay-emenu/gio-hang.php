<?php
/**
 * MAN HINH KHACH 4 - Gio hang, xac nhan va gui don
 * Them mon, sua so luong, xoa mon. Hien thi tong tien du kien truoc khi gui.
 * Chi gui duoc khi ban co phien dang mo. Gia va tong tien chinh thuc do may chu tinh.
 */
require_once __DIR__ . '/app/layout.php';

[$nc, $phien, $khoa] = ngu_canh_goi_mon();

dau_trang('Giỏ hàng');
?>

<div class="cach-tren">
    <a href="/menu.php" class="nho">← Tiếp tục chọn món</a>
</div>
<h1 style="margin-top:10px">Giỏ hàng</h1>
<p class="nho mo">Bàn <?= h($nc['so_ban']) ?> · <?= h($nc['khu_vuc']) ?></p>

<?php bao_khoa_gui_don($khoa); ?>

<div id="hop-loi"></div>

<div class="the" id="ds-gio"></div>

<div id="phan-gui" class="an">
    <div class="thanh-duoi">
        <div class="tong-tien"><span>Tổng tiền dự kiến</span><b id="tong">0 đ</b></div>
        <p class="rat-nho mo" style="margin:-4px 0 10px">
            Giá và tổng tiền chính thức do máy chủ xác định theo thực đơn hiện tại.
        </p>
        <button class="nut nut-rong" id="nut-gui" type="button"
                <?= $khoa ? 'disabled aria-disabled="true"' : '' ?>>
            🔥 Xác nhận và gửi đơn tới bếp
        </button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const KHOA = <?= json_encode($khoa) ?>;
    const ds   = document.getElementById('ds-gio');
    const hopLoi = document.getElementById('hop-loi');

    function ve() {
        const gio = App.layGio();
        document.getElementById('phan-gui').classList.toggle('an', gio.length === 0);

        if (!gio.length) {
            ds.innerHTML = `
                <div class="trong-rong">
                    <div class="hinh">🛒</div>
                    <p>Giỏ hàng đang trống.</p>
                    <a class="nut" href="/menu.php">Xem thực đơn</a>
                </div>`;
            return;
        }

        ds.innerHTML = gio.map((d, i) => `
            <div class="dong-gio">
                <div class="anh-mon" style="width:52px;height:52px;font-size:1.5rem">${App.h(d.anh || '🍜')}</div>
                <div class="lon-dan">
                    <div class="dam">${App.h(d.ten_mon)}</div>
                    ${d.ghi_chu ? `<div>${App.ghiChu(d.ghi_chu)}</div>` : ''}
                    <div class="giua-hai" style="margin-top:7px">
                        <div class="so-luong">
                            <button type="button" data-bot="${i}" aria-label="Giảm">−</button>
                            <span class="so">${d.so_luong}</span>
                            <button type="button" data-them="${i}" aria-label="Tăng">+</button>
                        </div>
                        <span class="gia">${App.tien(d.gia_vua_xem * d.so_luong)}</span>
                    </div>
                </div>
                <button type="button" class="nut nut-xam nut-nho" data-xoa="${i}"
                        aria-label="Xóa món" style="min-height:36px;padding:6px 10px">✕</button>
            </div>`).join('');

        document.getElementById('tong').textContent = App.tien(App.tongDuKien(gio));
    }

    ds.addEventListener('click', (e) => {
        const n = e.target.closest('button');
        if (!n) return;
        if (n.dataset.bot  !== undefined) App.doiSoLuong(+n.dataset.bot, -1);
        if (n.dataset.them !== undefined) App.doiSoLuong(+n.dataset.them, +1);
        if (n.dataset.xoa  !== undefined) App.xoaDong(+n.dataset.xoa);
        ve();
    });

    const nutGui = document.getElementById('nut-gui');
    nutGui.addEventListener('click', async () => {
        if (KHOA) return;
        const gio = App.layGio();
        if (!gio.length) return;

        nutGui.disabled = true;
        nutGui.textContent = 'Đang gửi đơn…';
        hopLoi.innerHTML = '';

        // Du lieu gui dung theo dac ta slide 17: KHONG gui tong tien
        const kq = await App.api('/api/orders', {
            method: 'POST',
            duLieu: {
                ma_yeu_cau: App.maYeuCau(),
                mon: gio.map((d) => ({
                    ma_mon: d.ma_mon,
                    so_luong: d.so_luong,
                    ghi_chu: d.ghi_chu || undefined,
                    gia_vua_xem: d.gia_vua_xem,
                })),
            },
        });

        if (kq.thanh_cong) {
            App.xoaGio();
            location.href = '/don-hang.php?moi=' + kq.id_don + (kq.gui_trung ? '&trung=1' : '');
            return;
        }

        // Gia da thay doi -> cap nhat gia moi trong gio, yeu cau khach xac nhan lai
        if (kq.gia_moi !== undefined) {
            const g = App.layGio();
            g.forEach((d) => { if (d.ma_mon === kq.ma_mon) d.gia_vua_xem = kq.gia_moi; });
            App.datGio(g);   // gio doi -> lan gui sau dung ma yeu cau moi
            ve();
        }

        hopLoi.innerHTML = `<div class="tb tb-loi">⚠️ ${App.h(kq.thong_bao || 'Không gửi được đơn.')}
            ${kq.ma ? `<span class="rat-nho mo">(mã ${kq.ma})</span>` : ''}</div>`;
        hopLoi.scrollIntoView({ behavior: 'smooth', block: 'center' });
        nutGui.disabled = false;
        nutGui.textContent = kq.gia_moi !== undefined
            ? '✔ Xác nhận giá mới và gửi đơn'
            : '🔥 Gửi lại đơn';
    });

    ve();
});
</script>

<?php chan_trang(); ?>
