/* =====================================================================
   MÌ CAY SASIN — JavaScript phía trình duyệt
   Giỏ hàng lưu tạm trên điện thoại của khách. Mọi quyết định nghiệp vụ
   (giá, tổng tiền, trạng thái) vẫn do máy chủ xác định.
   ===================================================================== */

const App = (() => {
    const body   = document.body;
    const soBan  = body.dataset.ban || '';
    const phien  = body.dataset.phien || '';

    // Giỏ hàng gắn với đúng bàn + phiên: lượt khách mới thì giỏ mới
    const KHOA_GIO = `micay_gio_${soBan}_${phien || 'chua_mo'}`;
    const KHOA_MA  = `micay_ma_yeu_cau_${soBan}_${phien || 'chua_mo'}`;

    /* ---------- Lưu trữ an toàn (có thể bị chặn ở chế độ riêng tư) ---------- */
    function doc(khoa, macDinh) {
        try {
            const v = localStorage.getItem(khoa);
            return v === null ? macDinh : JSON.parse(v);
        } catch (e) { return macDinh; }
    }
    function ghi(khoa, giaTri) {
        try { localStorage.setItem(khoa, JSON.stringify(giaTri)); } catch (e) { /* bỏ qua */ }
    }
    function xoa(khoa) {
        try { localStorage.removeItem(khoa); } catch (e) { /* bỏ qua */ }
    }

    /* ---------- Định dạng ---------- */
    const tien = (so) => new Intl.NumberFormat('vi-VN').format(Math.round(so)) + ' đ';
    const h = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));

    /** Ghi chú "Cấp N · ghi chú riêng" (do mon.php tạo) -> nhãn cấp cay có màu + ghi chú. */
    function ghiChu(gc) {
        if (!gc) return '';
        const m = /^Cấp ([1-7])(?:\s*·\s*(.*))?$/.exec(gc);
        const rieng = m ? (m[2] || '') : gc;
        return (m ? `<span class="cay cay-${m[1]}">🌶 Cấp ${m[1]}</span>` : '')
             + (rieng ? `<span class="gc-rieng">📝 ${h(rieng)}</span>` : '');
    }

    /* ---------- Giỏ hàng ---------- */
    function layGio() { return doc(KHOA_GIO, []); }

    function luuGio(gio) {
        ghi(KHOA_GIO, gio);
        // Giỏ thay đổi nghĩa là một lần gọi món mới -> bỏ mã yêu cầu cũ
        xoa(KHOA_MA);
        capNhatSoGio();
    }

    /** Cùng món + cùng ghi chú (vd cùng cấp độ cay) thì gộp số lượng. */
    function themVaoGio(mon) {
        const gio = layGio();
        const ghiChu = (mon.ghi_chu || '').trim();
        const dong = gio.find((d) => d.ma_mon === mon.ma_mon && (d.ghi_chu || '') === ghiChu);
        if (dong) {
            dong.so_luong = Math.min(99, dong.so_luong + mon.so_luong);
        } else {
            gio.push({ ...mon, ghi_chu: ghiChu });
        }
        luuGio(gio);
    }

    function doiSoLuong(viTri, buoc) {
        const gio = layGio();
        if (!gio[viTri]) return;
        gio[viTri].so_luong += buoc;
        if (gio[viTri].so_luong <= 0) gio.splice(viTri, 1);
        else gio[viTri].so_luong = Math.min(99, gio[viTri].so_luong);
        luuGio(gio);
    }

    function xoaDong(viTri) {
        const gio = layGio();
        gio.splice(viTri, 1);
        luuGio(gio);
    }

    function xoaGio() { xoa(KHOA_GIO); xoa(KHOA_MA); capNhatSoGio(); }

    /** Tổng tiền DỰ KIẾN để khách xem trước; máy chủ mới là nơi tính chính thức. */
    function tongDuKien(gio = layGio()) {
        return gio.reduce((t, d) => t + d.gia_vua_xem * d.so_luong, 0);
    }

    function capNhatSoGio() {
        const n = layGio().reduce((t, d) => t + d.so_luong, 0);
        document.querySelectorAll('[data-so-gio]').forEach((el) => { el.textContent = n; });
    }

    /* ---------- Mã yêu cầu: chống gửi trùng một lần gọi món ----------
       Giữ nguyên mã khi gửi lại (mạng chập chờn) để máy chủ nhận ra
       là cùng một lần gọi món và trả lại đơn cũ thay vì tạo đơn mới. */
    function maYeuCau() {
        let ma = doc(KHOA_MA, null);
        if (!ma) {
            ma = (crypto.randomUUID ? crypto.randomUUID()
                 : Date.now().toString(36) + Math.random().toString(36).slice(2)).slice(0, 36);
            ghi(KHOA_MA, ma);
        }
        return ma;
    }

    /* ---------- Gọi API ---------- */
    async function api(duongDan, { method = 'GET', duLieu } = {}) {
        const tuyChon = {
            method,
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
        };
        if (duLieu !== undefined) {
            tuyChon.headers['Content-Type'] = 'application/json';
            tuyChon.body = JSON.stringify(duLieu);
        }
        let phanHoi;
        try {
            phanHoi = await fetch(duongDan, tuyChon);
        } catch (e) {
            return { ma: 0, thanh_cong: false, thong_bao: 'Mất kết nối mạng, vui lòng thử lại.' };
        }
        let json = {};
        try { json = await phanHoi.json(); } catch (e) { /* phản hồi rỗng */ }
        return { ma: phanHoi.status, ...json };
    }

    /* ---------- Thông báo nổi ---------- */
    function bao(noiDung, loai = 'ok') {
        let hop = document.getElementById('hop-bao');
        if (!hop) {
            hop = document.createElement('div');
            hop.id = 'hop-bao';
            hop.style.cssText = 'position:fixed;left:50%;bottom:22px;transform:translateX(-50%);'
                + 'z-index:100;width:min(520px,calc(100% - 32px));display:flex;'
                + 'flex-direction:column;gap:8px;pointer-events:none';
            document.body.appendChild(hop);
        }
        const o = document.createElement('div');
        o.className = 'tb tb-' + loai;
        o.style.cssText = 'margin:0;box-shadow:0 10px 30px rgba(0,0,0,.45);'
            + 'background:#1A1A1C;pointer-events:auto';
        o.textContent = noiDung;
        hop.appendChild(o);
        setTimeout(() => { o.style.transition = 'opacity .3s'; o.style.opacity = '0'; }, 3200);
        setTimeout(() => o.remove(), 3600);
    }

    /* ---------- Tự tải lại định kỳ (bếp, phục vụ: 5 giây) ---------- */
    function taiLaiDinhKy(giay, ham) {
        let dang = false;
        const chay = async () => {
            if (dang || document.hidden) return;
            dang = true;
            try { await ham(); } finally { dang = false; }
        };
        chay();
        return setInterval(chay, giay * 1000);
    }

    document.addEventListener('DOMContentLoaded', capNhatSoGio);

    return {
        soBan, phien, tien, h, ghiChu,
        layGio, datGio: luuGio, themVaoGio, doiSoLuong, xoaDong, xoaGio, tongDuKien,
        maYeuCau, api, bao, taiLaiDinhKy, capNhatSoGio,
    };
})();
