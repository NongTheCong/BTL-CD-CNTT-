<?php
/**
 * Tang nghiep vu (Service) - moi quyet dinh nghiep vu deu do MAY CHU xac dinh.
 * Giao dien chi ho tro trai nghiem, khong duoc coi la noi kiem soat du lieu.
 */

require_once __DIR__ . '/helpers.php';

class NghiepVu
{
    /* =====================================================
     *  1. Ngu canh ban va phien - sau khi quet ma QR
     * ===================================================== */

    /**
     * Tra cuu ban va phien dang mo tu ma QR.
     * Bao cao: ma QR hop le, ban hoat dong, co phien dang mo (200 / 409).
     */
    public static function ngu_canh_ban(string $ma_qr): array
    {
        $ban = Db::mot('SELECT * FROM BanAn WHERE ma_qr = ?', [$ma_qr]);

        if (!$ban) {
            return ['ok' => false, 'ma' => 404, 'thong_bao' => 'Mã QR không hợp lệ.'];
        }
        if ($ban['trang_thai'] !== 'Hoạt động') {
            return ['ok' => false, 'ma' => 409,
                    'thong_bao' => 'Bàn ' . $ban['so_ban'] . ' đang tạm ngừng sử dụng.'];
        }

        $phien = Db::mot(
            "SELECT * FROM PhienPhucVu WHERE id_ban = ? AND trang_thai = 'Đang mở'",
            [$ban['id_ban']]
        );

        return [
            'ok'    => true,
            'ma'    => 200,
            'ban'   => $ban,
            'phien' => $phien,                 // null => chua mo phien
            // Chua co phien dang mo thi khach van xem duoc thuc don
            // nhung nut gui don bi khoa (ca kiem thu TC03).
            'duoc_gui_don' => $phien !== null,
        ];
    }

    /** Mo phien phuc vu cho mot ban (nhan vien phuc vu hoac quan tri). */
    public static function mo_phien_phuc_vu(int $id_ban, int $id_nguoi_mo): array
    {
        $ban = Db::mot('SELECT * FROM BanAn WHERE id_ban = ?', [$id_ban]);
        if (!$ban) {
            return ['ok' => false, 'ma' => 404, 'thong_bao' => 'Không tìm thấy bàn.'];
        }
        if ($ban['trang_thai'] !== 'Hoạt động') {
            return ['ok' => false, 'ma' => 409, 'thong_bao' => 'Bàn đang tạm ngừng sử dụng.'];
        }

        // Moi ban toi da MOT phien dang mo tai mot thoi diem
        $dang_mo = Db::mot(
            "SELECT id_phien FROM PhienPhucVu WHERE id_ban = ? AND trang_thai <> 'Đã đóng'",
            [$id_ban]
        );
        if ($dang_mo) {
            return ['ok' => false, 'ma' => 409,
                    'thong_bao' => 'Bàn ' . $ban['so_ban'] . ' đã có phiên đang mở.'];
        }

        Db::chay(
            "INSERT INTO PhienPhucVu (id_ban, mo_luc, trang_thai) VALUES (?, ?, 'Đang mở')",
            [$id_ban, Db::bay_gio()]
        );

        return ['ok' => true, 'ma' => 201, 'id_phien' => Db::id_moi(), 'ban' => $ban];
    }

    /* =====================================================
     *  2. Thuc don
     * ===================================================== */

    /**
     * Thuc don theo danh muc.
     * $tat_ca = false: chi tra mon dang ban cua danh muc dang hien thi (cho khach).
     */
    public static function thuc_don(bool $tat_ca = false): array
    {
        $dk_dm = $tat_ca ? '' : " WHERE trang_thai = 'Hiển thị'";
        $danh_muc = Db::tat_ca("SELECT * FROM DanhMuc$dk_dm ORDER BY thu_tu, id_danh_muc");

        foreach ($danh_muc as &$dm) {
            $dk_mon = $tat_ca ? '' : " AND trang_thai_ban = 'Đang bán'";
            $dm['mon'] = Db::tat_ca(
                "SELECT * FROM MonAn WHERE id_danh_muc = ?$dk_mon ORDER BY thu_tu, id_mon",
                [$dm['id_danh_muc']]
            );
        }
        return $danh_muc;
    }

    public static function mon(int $id_mon): ?array
    {
        return Db::mot(
            'SELECT m.*, d.ten_danh_muc FROM MonAn m
             JOIN DanhMuc d ON d.id_danh_muc = m.id_danh_muc
             WHERE m.id_mon = ?',
            [$id_mon]
        );
    }

    /* =====================================================
     *  3. Gui don goi mon - 5 dieu kien + 1 giao dich
     * ===================================================== */

    /**
     * Luu mot lan gui don.
     *
     * Nam dieu kien may chu kiem tra truoc khi luu (slide 17 va 19):
     *   1. Phien con mo
     *   2. Mon con ban
     *   3. So luong tu 1 den 99
     *   4. Gia khach vua xem khop gia trong CSDL
     *   5. Ma yeu cau chua dung trong phien nay
     *
     * @param array $mon Moi phan tu: ma_mon, so_luong, ghi_chu?, gia_vua_xem
     */
    public static function tao_don(int $id_phien, array $mon, string $ma_yeu_cau): array
    {
        $cf = cf();

        /* --- Dieu kien 5: chong gui trung (idempotency) -----------------
         * Cung ma yeu cau + cung noi dung  -> tra lai don da tao.
         * Cung ma yeu cau + khac noi dung  -> bao loi 409.             */
        $ma_yeu_cau = trim($ma_yeu_cau);
        if ($ma_yeu_cau === '' || mb_strlen($ma_yeu_cau) > 40) {
            return ['ok' => false, 'ma' => 422, 'thong_bao' => 'Mã yêu cầu không hợp lệ.'];
        }

        $don_cu = Db::mot(
            'SELECT * FROM DonHang WHERE id_phien = ? AND ma_yeu_cau = ?',
            [$id_phien, $ma_yeu_cau]
        );
        if ($don_cu) {
            if (self::cung_noi_dung((int) $don_cu['id_don'], $mon)) {
                return ['ok' => true, 'ma' => 200, 'id_don' => (int) $don_cu['id_don'],
                        'trang_thai' => $don_cu['trang_thai'], 'gui_trung' => true];
            }
            return ['ok' => false, 'ma' => 409,
                    'thong_bao' => 'Mã yêu cầu đã dùng cho một đơn khác nội dung.'];
        }

        /* --- Dieu kien 1: phien con mo ------------------------------- */
        $phien = Db::mot('SELECT * FROM PhienPhucVu WHERE id_phien = ?', [$id_phien]);
        if (!$phien) {
            return ['ok' => false, 'ma' => 404, 'thong_bao' => 'Không tìm thấy phiên phục vụ.'];
        }
        if ($phien['trang_thai'] !== 'Đang mở') {
            return ['ok' => false, 'ma' => 409,
                    'thong_bao' => 'Phiên phục vụ đã đóng, không nhận thêm đơn mới.'];
        }

        if (!$mon) {
            return ['ok' => false, 'ma' => 422, 'thong_bao' => 'Giỏ hàng đang trống.'];
        }

        /* --- Dieu kien 2, 3, 4 cho tung dong ------------------------- */
        $dong = [];
        foreach ($mon as $m) {
            $id_mon   = (int) ($m['ma_mon'] ?? 0);
            $so_luong = (int) ($m['so_luong'] ?? 0);
            $ghi_chu  = trim((string) ($m['ghi_chu'] ?? ''));

            $ban_ghi = Db::mot('SELECT * FROM MonAn WHERE id_mon = ?', [$id_mon]);
            if (!$ban_ghi) {
                return ['ok' => false, 'ma' => 422,
                        'thong_bao' => 'Món #' . $id_mon . ' không tồn tại.'];
            }
            // 2. Mon con ban
            if ($ban_ghi['trang_thai_ban'] !== 'Đang bán') {
                return ['ok' => false, 'ma' => 409,
                        'thong_bao' => 'Món "' . $ban_ghi['ten_mon'] . '" đã hết hàng.'];
            }
            // 3. So luong trong khoang cho phep
            if ($so_luong < $cf['so_luong_min'] || $so_luong > $cf['so_luong_max']) {
                return ['ok' => false, 'ma' => 422,
                        'thong_bao' => 'Số lượng của "' . $ban_ghi['ten_mon'] . '" phải từ '
                                     . $cf['so_luong_min'] . ' đến ' . $cf['so_luong_max'] . '.'];
            }
            // 4. Gia khach vua xem phai khop gia trong CSDL (chong gia mao gia)
            if (!array_key_exists('gia_vua_xem', $m)) {
                return ['ok' => false, 'ma' => 422,
                        'thong_bao' => 'Thiếu trường gia_vua_xem.'];
            }
            $gia_xem = (float) $m['gia_vua_xem'];
            if (abs($gia_xem - (float) $ban_ghi['gia']) > 0.001) {
                return ['ok' => false, 'ma' => 409, 'thong_bao' =>
                    'Giá món "' . $ban_ghi['ten_mon'] . '" đã thay đổi thành '
                    . tien($ban_ghi['gia']) . '. Vui lòng xác nhận lại giá mới.',
                    'gia_moi' => (float) $ban_ghi['gia'], 'ma_mon' => $id_mon];
            }

            if (mb_strlen($ghi_chu) > $cf['ghi_chu_max']) {
                $ghi_chu = mb_substr($ghi_chu, 0, $cf['ghi_chu_max']);
            }

            $dong[] = [
                'id_mon'          => $id_mon,
                // Chup lai ten va gia luc dat -> hoa don cu khong doi khi quan doi gia
                'ten_mon_luc_dat' => $ban_ghi['ten_mon'],
                'don_gia_luc_dat' => (float) $ban_ghi['gia'],
                'so_luong'        => $so_luong,
                'ghi_chu'         => $ghi_chu !== '' ? $ghi_chu : null,
            ];
        }

        /* --- Ghi DonHang + ChiTietDonHang trong MOT giao dich -------- */
        try {
            $id_don = Db::giao_dich(function () use ($id_phien, $ma_yeu_cau, $dong) {
                Db::chay(
                    "INSERT INTO DonHang (id_phien, ma_yeu_cau, trang_thai, tao_luc)
                     VALUES (?, ?, 'Mới nhận', ?)",
                    [$id_phien, $ma_yeu_cau, Db::bay_gio()]
                );
                $id = Db::id_moi();

                foreach ($dong as $d) {
                    Db::chay(
                        'INSERT INTO ChiTietDonHang
                         (id_don, id_mon, ten_mon_luc_dat, don_gia_luc_dat, so_luong, ghi_chu)
                         VALUES (?, ?, ?, ?, ?, ?)',
                        [$id, $d['id_mon'], $d['ten_mon_luc_dat'],
                         $d['don_gia_luc_dat'], $d['so_luong'], $d['ghi_chu']]
                    );
                }
                return $id;
            });
        } catch (Throwable $e) {
            // Co loi -> hoan tac, khong ghi bat ky dong nao
            return ['ok' => false, 'ma' => 409,
                    'thong_bao' => 'Không lưu được đơn, vui lòng gửi lại.'];
        }

        return ['ok' => true, 'ma' => 201, 'id_don' => $id_don, 'trang_thai' => 'Mới nhận'];
    }

    /** So sanh noi dung don da luu voi gio hang vua gui (chong gui trung). */
    private static function cung_noi_dung(int $id_don, array $mon): bool
    {
        $cu = Db::tat_ca(
            'SELECT id_mon, so_luong, ghi_chu FROM ChiTietDonHang
             WHERE id_don = ? ORDER BY id_mon',
            [$id_don]
        );
        $cu = array_map(fn($d) => [
            'id_mon'   => (int) $d['id_mon'],
            'so_luong' => (int) $d['so_luong'],
            'ghi_chu'  => ($d['ghi_chu'] ?? '') !== '' ? $d['ghi_chu'] : null,
        ], $cu);

        $moi = array_map(fn($m) => [
            'id_mon'   => (int) ($m['ma_mon'] ?? 0),
            'so_luong' => (int) ($m['so_luong'] ?? 0),
            'ghi_chu'  => trim((string) ($m['ghi_chu'] ?? '')) ?: null,
        ], $mon);
        usort($moi, fn($a, $b) => $a['id_mon'] <=> $b['id_mon']);

        return $cu === $moi;
    }

    /* =====================================================
     *  4. Trang thai don
     * ===================================================== */

    /** Danh sach don cua mot phien, kem chi tiet. */
    public static function don_cua_phien(int $id_phien): array
    {
        $don = Db::tat_ca(
            'SELECT * FROM DonHang WHERE id_phien = ? ORDER BY id_don',
            [$id_phien]
        );
        return self::gan_chi_tiet($don);
    }

    /** Don dang xu ly tren toan nha hang (man hinh bep / phuc vu). */
    public static function don_dang_xu_ly(array $trang_thai): array
    {
        if (!$trang_thai) {
            return [];
        }
        $dau = implode(',', array_fill(0, count($trang_thai), '?'));
        $don = Db::tat_ca(
            "SELECT d.*, b.so_ban, b.khu_vuc
             FROM DonHang d
             JOIN PhienPhucVu p ON p.id_phien = d.id_phien
             JOIN BanAn b       ON b.id_ban   = p.id_ban
             WHERE d.trang_thai IN ($dau)
             ORDER BY d.tao_luc",
            $trang_thai
        );
        return self::gan_chi_tiet($don);
    }

    private static function gan_chi_tiet(array $don): array
    {
        foreach ($don as &$d) {
            $d['chi_tiet'] = Db::tat_ca(
                'SELECT * FROM ChiTietDonHang WHERE id_don = ? ORDER BY id_chi_tiet',
                [$d['id_don']]
            );
            $d['tong'] = array_sum(array_map(
                fn($c) => (float) $c['don_gia_luc_dat'] * (int) $c['so_luong'],
                $d['chi_tiet']
            ));
        }
        return $don;
    }

    /**
     * Doi trang thai don. Khong cho chuyen nguoc, kiem tra vai tro tai may chu.
     * Huy don: chi tu "Moi nhan" va bat buoc co ly do.
     */
    public static function doi_trang_thai(
        int $id_don,
        string $sang,
        string $vai_tro,
        ?string $ly_do = null
    ): array {
        if (!in_array($sang, TT_DON, true)) {
            return ['ok' => false, 'ma' => 422, 'thong_bao' => 'Trạng thái không hợp lệ.'];
        }
        if (!vai_tro_duoc_dat($sang, $vai_tro)) {
            return ['ok' => false, 'ma' => 403,
                    'thong_bao' => 'Vai trò ' . (VAI_TRO[$vai_tro] ?? $vai_tro)
                                 . ' không được phép đặt trạng thái "' . $sang . '".'];
        }

        $don = Db::mot('SELECT * FROM DonHang WHERE id_don = ?', [$id_don]);
        if (!$don) {
            return ['ok' => false, 'ma' => 404, 'thong_bao' => 'Không tìm thấy đơn.'];
        }
        if (!duoc_chuyen($don['trang_thai'], $sang)) {
            return ['ok' => false, 'ma' => 409, 'thong_bao' =>
                'Không thể chuyển đơn từ "' . $don['trang_thai'] . '" sang "' . $sang . '".'];
        }

        if ($sang === 'Đã hủy') {
            $ly_do = trim((string) $ly_do);
            if ($ly_do === '') {
                return ['ok' => false, 'ma' => 422,
                        'thong_bao' => 'Phải ghi lý do khi hủy đơn.'];
            }
            Db::chay(
                'UPDATE DonHang SET trang_thai = ?, cap_nhat_luc = ?, ly_do_huy = ?
                 WHERE id_don = ?',
                [$sang, Db::bay_gio(), mb_substr($ly_do, 0, 200), $id_don]
            );
        } else {
            Db::chay(
                'UPDATE DonHang SET trang_thai = ?, cap_nhat_luc = ? WHERE id_don = ?',
                [$sang, Db::bay_gio(), $id_don]
            );
        }

        return ['ok' => true, 'ma' => 200, 'id_don' => $id_don, 'trang_thai' => $sang];
    }

    /* =====================================================
     *  5. Tong hop tien va thanh toan
     * ===================================================== */

    /**
     * Tong = sum(so luong x don gia luc dat), chi cong don thuoc dung phien
     * va chua bi huy.
     */
    public static function tong_tien_phien(int $id_phien): float
    {
        $r = Db::mot(
            "SELECT COALESCE(SUM(c.so_luong * c.don_gia_luc_dat), 0) AS tong
             FROM ChiTietDonHang c
             JOIN DonHang d ON d.id_don = c.id_don
             WHERE d.id_phien = ? AND d.trang_thai <> 'Đã hủy'",
            [$id_phien]
        );
        return (float) ($r['tong'] ?? 0);
    }

    /** Phien kem thong tin ban va tong tien. */
    public static function phien(int $id_phien): ?array
    {
        $p = Db::mot(
            'SELECT p.*, b.so_ban, b.khu_vuc
             FROM PhienPhucVu p JOIN BanAn b ON b.id_ban = p.id_ban
             WHERE p.id_phien = ?',
            [$id_phien]
        );
        if ($p) {
            $p['tong'] = self::tong_tien_phien($id_phien);
        }
        return $p;
    }

    /**
     * Thanh toan va dong phien.
     * Dieu kien: moi don da phuc vu hoac da huy, thu ngan xac nhan da thu tien.
     */
    public static function thanh_toan(int $id_phien, int $id_thu_ngan): array
    {
        $phien = Db::mot('SELECT * FROM PhienPhucVu WHERE id_phien = ?', [$id_phien]);
        if (!$phien) {
            return ['ok' => false, 'ma' => 404, 'thong_bao' => 'Không tìm thấy phiên.'];
        }
        if ($phien['trang_thai'] === 'Đã đóng') {
            return ['ok' => false, 'ma' => 409, 'thong_bao' => 'Phiên đã được đóng trước đó.'];
        }

        $con_lai = Db::mot(
            "SELECT COUNT(*) AS n FROM DonHang
             WHERE id_phien = ? AND trang_thai NOT IN ('Đã phục vụ', 'Đã hủy')",
            [$id_phien]
        );
        if ((int) $con_lai['n'] > 0) {
            return ['ok' => false, 'ma' => 409, 'thong_bao' =>
                'Còn ' . $con_lai['n'] . ' đơn chưa phục vụ xong, chưa thể đóng phiên.'];
        }

        $tong = self::tong_tien_phien($id_phien);

        Db::chay(
            "UPDATE PhienPhucVu
             SET trang_thai = 'Đã đóng', dong_luc = ?, tong_thanh_toan = ?, id_thu_ngan = ?
             WHERE id_phien = ?",
            [Db::bay_gio(), $tong, $id_thu_ngan, $id_phien]
        );

        return ['ok' => true, 'ma' => 200, 'id_phien' => $id_phien, 'tong_thanh_toan' => $tong];
    }

    /** Danh sach ban kem phien dang mo - man hinh phuc vu va thu ngan. */
    public static function ban_va_phien(): array
    {
        $ban = Db::tat_ca('SELECT * FROM BanAn ORDER BY khu_vuc, so_ban');
        foreach ($ban as &$b) {
            $p = Db::mot(
                "SELECT * FROM PhienPhucVu
                 WHERE id_ban = ? AND trang_thai <> 'Đã đóng'",
                [$b['id_ban']]
            );
            $b['phien'] = $p;
            if ($p) {
                $b['tong']   = self::tong_tien_phien((int) $p['id_phien']);
                $b['so_don'] = (int) (Db::mot(
                    'SELECT COUNT(*) AS n FROM DonHang WHERE id_phien = ?',
                    [$p['id_phien']]
                )['n'] ?? 0);
            }
        }
        return $ban;
    }
}
