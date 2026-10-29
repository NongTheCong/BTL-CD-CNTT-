<?php
/**
 * MAN HINH NOI BO 2 - Ban va phuc vu
 * Mo phien cho ban, xac nhan da phuc vu, duyet yeu cau huy don.
 */
require_once __DIR__ . '/app/layout.php';
bat_buoc_quyen('mo_phien');

dau_trang('Bàn & phục vụ', 'noi_bo', 'phuc-vu.php');
?>

<div class="giua-hai cach-duoi">
    <div>
        <h1 style="margin:0">🧑‍🍳 Bàn &amp; phục vụ</h1>
        <div class="nho mo">Tự tải lại mỗi <?= (int) cf()['chu_ky_tai_lai'] ?> giây</div>
    </div>
</div>

<div style="display:grid;gap:20px;grid-template-columns:minmax(0,1.35fr) minmax(300px,1fr);align-items:start" id="bo-cuc">
    <section>
        <h2>Sơ đồ bàn</h2>
        <div id="so-do" class="doc"></div>
    </section>

    <section class="doc">
        <div>
            <h2>🛎️ Món chờ mang ra <span class="tt tt-cho" id="dem-cho">0</span></h2>
            <div id="ds-cho" class="doc"></div>
        </div>
        <div class="cach-tren">
            <h2>📝 Đơn mới nhận <span class="mo nho">(còn hủy được)</span></h2>
            <div id="ds-moi" class="doc"></div>
        </div>
    </section>
</div>

<style>
@media (max-width: 860px) { #bo-cuc { grid-template-columns: 1fr !important; } }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const ds = (d) => d.chi_tiet.map((c) =>
        `${c.so_luong} × ${App.h(c.ten_mon)}${c.ghi_chu ? ` ${App.ghiChu(c.ghi_chu)}` : ''}`
    ).join('<br>');

    async function taiBan() {
        const r = await fetch('/phuc-vu-du-lieu.php', { credentials: 'same-origin' });
        const kq = await r.json();
        const theoKhu = {};
        kq.ban.forEach((b) => { (theoKhu[b.khu_vuc] = theoKhu[b.khu_vuc] || []).push(b); });

        document.getElementById('so-do').innerHTML = Object.entries(theoKhu).map(([khu, ds]) => `
            <div>
                <div class="nho mo dam" style="margin-bottom:8px">${App.h(khu)}</div>
                <div class="luoi-ban">${ds.map((b) => {
                    const ngung = b.trang_thai !== 'Hoạt động';
                    const p = b.phien;
                    return `<div class="the-ban ${p ? 'co-khach' : ''} ${ngung ? 'ngung' : ''}">
                        <div class="giua-hai"><span class="so">${App.h(b.so_ban)}</span>
                            ${ngung ? '<span class="tt tt-huy">Tạm ngừng</span>'
                                : p ? `<span class="tt ${p.trang_thai === 'Đang mở' ? 'tt-moi' : 'tt-cho'}">${App.h(p.trang_thai)}</span>`
                                    : '<span class="tt tt-huy">Trống</span>'}</div>
                        ${p ? `<div class="khu">Phiên #${p.id_phien} · mở ${p.mo_luc.slice(11, 16)}<br>
                                ${b.so_don} đơn · <b style="color:var(--vang)">${App.tien(b.tong)}</b></div>`
                            : `<div class="khu">&nbsp;<br>&nbsp;</div>`}
                        ${!p && !ngung ? `<button class="nut nut-nho nut-rong" data-mo="${b.id_ban}">+ Mở phiên</button>` : ''}
                    </div>`;
                }).join('')}</div>
            </div>`).join('');
    }

    async function taiDon() {
        const kq = await App.api('/api/orders?trang_thai=' + encodeURIComponent('Mới nhận,Chờ phục vụ'));
        if (!kq.thanh_cong) return;
        const cho = kq.don.filter((d) => d.trang_thai === 'Chờ phục vụ');
        const moi = kq.don.filter((d) => d.trang_thai === 'Mới nhận');
        document.getElementById('dem-cho').textContent = cho.length;

        document.getElementById('ds-cho').innerHTML = cho.length ? cho.map((d) => `
            <div class="the-don cho">
                <div class="giua-hai"><span class="ban-lon">Bàn ${App.h(d.so_ban)}</span>
                    <span class="rat-nho mo">#${d.id_don}</span></div>
                <div class="nho" style="margin:8px 0 12px">${ds(d)}</div>
                <button class="nut nut-xanh nut-rong" data-pv="${d.id_don}">✔ Đã mang ra bàn</button>
            </div>`).join('') : '<div class="the trong-rong nho" style="padding:22px">Không có món chờ mang ra</div>';

        document.getElementById('ds-moi').innerHTML = moi.length ? moi.map((d) => `
            <div class="the-don">
                <div class="giua-hai"><span class="ban-lon">Bàn ${App.h(d.so_ban)}</span>
                    <span class="rat-nho mo">#${d.id_don} · ${d.tao_luc.slice(11, 16)}</span></div>
                <div class="nho" style="margin:8px 0 12px">${ds(d)}</div>
                <button class="nut nut-vien nut-nho" data-huy="${d.id_don}">Hủy đơn theo yêu cầu khách</button>
            </div>`).join('') : '<div class="the trong-rong nho" style="padding:22px">Không có đơn mới</div>';
    }

    const tai = () => Promise.all([taiBan(), taiDon()]);

    document.body.addEventListener('click', async (e) => {
        const n = e.target.closest('button');
        if (!n) return;
        let kq = null;

        if (n.dataset.mo) {
            n.disabled = true;
            kq = await App.api('/api/sessions', { method: 'POST', duLieu: { id_ban: +n.dataset.mo } });
        } else if (n.dataset.pv) {
            n.disabled = true;
            kq = await App.api(`/api/orders/${n.dataset.pv}/status`,
                               { method: 'PATCH', duLieu: { trang_thai: 'Đã phục vụ' } });
        } else if (n.dataset.huy) {
            const lyDo = prompt('Lý do hủy đơn #' + n.dataset.huy + ' (bắt buộc):', 'Khách đổi ý');
            if (lyDo === null) return;
            kq = await App.api(`/api/orders/${n.dataset.huy}/status`,
                               { method: 'PATCH', duLieu: { trang_thai: 'Đã hủy', ly_do_huy: lyDo } });
        }
        if (kq) App.bao(kq.thanh_cong ? (kq.thong_bao || 'Đã cập nhật') : kq.thong_bao, kq.thanh_cong ? 'ok' : 'loi');
        tai();
    });

    App.taiLaiDinhKy(<?= (int) cf()['chu_ky_tai_lai'] ?>, tai);
});
</script>

<?php chan_trang('noi_bo'); ?>
