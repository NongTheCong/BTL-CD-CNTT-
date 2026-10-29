<?php
/**
 * MAN HINH NOI BO 1 - Man hinh bep
 * Don moi, dang che bien, cho phuc vu. Tai lai du lieu moi 5 giay.
 * Bep chi chuyen: Moi nhan -> Dang che bien -> Cho phuc vu.
 */
require_once __DIR__ . '/app/layout.php';
bat_buoc_quyen('cap_nhat_che_bien');

dau_trang('Màn hình bếp', 'noi_bo', 'bep.php');
?>

<div class="giua-hai cach-duoi" style="flex-wrap:wrap">
    <div>
        <h1 style="margin:0">🔥 Màn hình bếp</h1>
        <div class="nho mo">Tự tải lại mỗi <?= (int) cf()['chu_ky_tai_lai'] ?> giây · cập nhật lúc <span id="luc">—</span></div>
    </div>
    <div class="hang">
        <div class="o-so-lieu" style="padding:10px 16px"><div class="so" id="dem-moi" style="font-size:1.4rem">0</div><div class="nhan">Mới nhận</div></div>
        <div class="o-so-lieu" style="padding:10px 16px"><div class="so" id="dem-nau" style="font-size:1.4rem;color:var(--do-nhat)">0</div><div class="nhan">Đang nấu</div></div>
        <div class="o-so-lieu" style="padding:10px 16px"><div class="so" id="dem-cho" style="font-size:1.4rem;color:#8FB4FF">0</div><div class="nhan">Chờ mang ra</div></div>
    </div>
</div>

<div style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));align-items:start">
    <section>
        <h2><span class="tt tt-moi">Mới nhận</span></h2>
        <div class="doc" id="cot-moi"></div>
    </section>
    <section>
        <h2><span class="tt tt-nau">Đang chế biến</span></h2>
        <div class="doc" id="cot-nau"></div>
    </section>
    <section>
        <h2><span class="tt tt-cho">Chờ phục vụ</span></h2>
        <div class="doc" id="cot-cho"></div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const phut = (s) => Math.max(0, Math.floor((Date.now() - new Date(s.replace(' ', 'T')).getTime()) / 60000));
    let soMoiTruoc = null;
    let daThay = null;          // id cac don da hien, de danh dau don moi den

    // Tieng "ting" ngan bao don moi (trinh duyet chi cho phat sau khi da bam vao trang)
    const chuong = () => {
        try {
            const ac = new (window.AudioContext || window.webkitAudioContext)();
            [880, 1320].forEach((tan, i) => {
                const o = ac.createOscillator(), g = ac.createGain();
                o.frequency.value = tan;
                g.gain.setValueAtTime(.18, ac.currentTime + i * .18);
                g.gain.exponentialRampToValueAtTime(.001, ac.currentTime + i * .18 + .35);
                o.connect(g).connect(ac.destination);
                o.start(ac.currentTime + i * .18);
                o.stop(ac.currentTime + i * .18 + .4);
            });
        } catch (e) { /* khong co am thanh thi thoi */ }
    };

    const the = (d, nut, lop) => {
        const p = phut(d.tao_luc);
        const moiDen = daThay && !daThay.has(d.id_don);
        return `
        <div class="the-don ${lop} ${moiDen ? 'moi-den' : ''}">
            <div class="giua-hai">
                <span class="ban-lon">Bàn ${App.h(d.so_ban)}</span>
                <span class="tuoi ${p >= 15 ? 'tuoi-do' : (p >= 10 ? 'tuoi-vang' : '')}">⏱ ${p} phút</span>
            </div>
            <div class="rat-nho mo">Đơn #${d.id_don} · gửi lúc ${d.tao_luc.slice(11, 16)}</div>
            <ul class="mon-bep">${d.chi_tiet.map((c) => `
                <li><span class="sl">${c.so_luong}×</span>
                    <div><div class="ten">${App.h(c.ten_mon)}</div>${App.ghiChu(c.ghi_chu)}</div></li>`).join('')}
            </ul>
            ${nut}
        </div>`;
    };

    const trong = (chu) => `<div class="the trong-rong nho" style="padding:24px">${chu}</div>`;

    async function tai() {
        const kq = await App.api('/api/orders?trang_thai=' + encodeURIComponent('Mới nhận,Đang chế biến,Chờ phục vụ'));
        if (!kq.thanh_cong) { App.bao(kq.thong_bao, 'loi'); return; }

        const theo = (tt) => kq.don.filter((d) => d.trang_thai === tt);
        const moi = theo('Mới nhận'), nau = theo('Đang chế biến'), cho = theo('Chờ phục vụ');

        document.getElementById('cot-moi').innerHTML = moi.length ? moi.map((d) => the(d,
            `<button class="nut nut-rong" data-id="${d.id_don}" data-tt="Đang chế biến">Bắt đầu nấu →</button>`, '')).join('')
            : trong('Chưa có đơn mới');
        document.getElementById('cot-nau').innerHTML = nau.length ? nau.map((d) => the(d,
            `<button class="nut nut-vang nut-rong" data-id="${d.id_don}" data-tt="Chờ phục vụ">✔ Nấu xong</button>`, 'nau')).join('')
            : trong('Không có món đang nấu');
        document.getElementById('cot-cho').innerHTML = cho.length ? cho.map((d) => the(d,
            `<div class="nho mo">Đợi nhân viên phục vụ mang ra bàn…</div>`, 'cho')).join('')
            : trong('Không có món chờ mang ra');

        document.getElementById('dem-moi').textContent = moi.length;
        document.getElementById('dem-nau').textContent = nau.length;
        document.getElementById('dem-cho').textContent = cho.length;
        document.getElementById('luc').textContent = kq.luc.slice(11, 19);

        if (soMoiTruoc !== null && moi.length > soMoiTruoc) { App.bao('🔔 Có đơn mới!', 'nhac'); chuong(); }
        soMoiTruoc = moi.length;
        daThay = new Set(kq.don.map((d) => d.id_don));
    }

    document.body.addEventListener('click', async (e) => {
        const n = e.target.closest('button[data-tt]');
        if (!n) return;
        n.disabled = true;
        const kq = await App.api(`/api/orders/${n.dataset.id}/status`,
                                 { method: 'PATCH', duLieu: { trang_thai: n.dataset.tt } });
        if (!kq.thanh_cong) App.bao(kq.thong_bao, 'loi');
        tai();
    });

    App.taiLaiDinhKy(<?= (int) cf()['chu_ky_tai_lai'] ?>, tai);
});
</script>

<?php chan_trang('noi_bo'); ?>
