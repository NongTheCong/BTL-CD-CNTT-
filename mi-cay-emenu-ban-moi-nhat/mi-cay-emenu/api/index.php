<?php
/**
 * TANG UNG DUNG - 7 API theo danh sach o slide 27 cua bao cao.
 *
 *   GET   /api/table-context?qr=...        Khach            200 / 409
 *   GET   /api/menu                        Khach            200
 *   POST  /api/orders                      Khach            201 / 409 / 422
 *   GET   /api/orders?session=...          Khach, nhan vien 200 / 403
 *   PATCH /api/orders/{id}/status          Bep, phuc vu     200 / 403 / 409
 *   POST  /api/sessions                    Phuc vu, quan tri 201 / 409
 *   POST  /api/sessions/{id}/checkout      Thu ngan         200 / 409
 *
 * Quy uoc ma tra ve: 200/201 thanh cong, 403 sai vai tro,
 * 409 xung dot trang thai, 422 du lieu khong hop le.
 */

require_once __DIR__ . '/../app/NghiepVu.php';
require_once __DIR__ . '/../app/install.php';

mo_phien();
bao_dam_cai_dat();

/* --- Phan giai duong dan ------------------------------------------- */
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$duong  = trim(preg_replace('#^.*?/api#', '', $uri), '/');   // vd: orders/12/status
$doan   = $duong === '' ? [] : explode('/', $duong);
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$ep     = $doan[0] ?? '';

/* --- Ngu canh ban do MAY CHU gan sau khi quet QR -------------------
 * Trinh duyet cua khach khong tu nhap so ban va khong tu chon phien. */
try {
    switch (true) {

        /* ===========================================================
         *  1. GET /api/table-context?qr=...
         *     Kiem tra: ma QR hop le, ban hoat dong, co phien dang mo
         * =========================================================== */
        case $ep === 'table-context' && $method === 'GET':

            $ma_qr = trim((string) ($_GET['qr'] ?? ''));
            if ($ma_qr === '') {
                tra_loi('Thiếu tham số qr.', 422);
            }

            $kq = NghiepVu::ngu_canh_ban($ma_qr);
            if (!$kq['ok']) {
                unset($_SESSION['ngu_canh']);
                tra_loi($kq['thong_bao'], $kq['ma']);
            }

            // May chu gan ngu canh vao phien lam viec cua trinh duyet khach
            luu_ngu_canh_khach($kq['ban'], $kq['phien']);

            tra_json([
                'thanh_cong'   => true,
                'ban'          => [
                    'so_ban'  => $kq['ban']['so_ban'],
                    'khu_vuc' => $kq['ban']['khu_vuc'],
                ],
                'id_phien'     => $kq['phien'] ? (int) $kq['phien']['id_phien'] : null,
                'duoc_gui_don' => $kq['duoc_gui_don'],
                'thong_bao'    => $kq['duoc_gui_don']
                    ? 'Đã nhận diện bàn ' . $kq['ban']['so_ban'] . '.'
                    : 'Bàn ' . $kq['ban']['so_ban'] . ' chưa mở phiên phục vụ. '
                      . 'Bạn vẫn xem được thực đơn nhưng chưa gửi đơn được.',
            ], 200);

        /* ===========================================================
         *  2. GET /api/menu
         *     Chi tra mon dang ban cua danh muc dang hien thi
         * =========================================================== */
        case $ep === 'menu' && $method === 'GET':

            $danh_muc = NghiepVu::thuc_don(false);
            $ra = array_map(fn($dm) => [
                'id_danh_muc'  => (int) $dm['id_danh_muc'],
                'ten_danh_muc' => $dm['ten_danh_muc'],
                'mon' => array_map(fn($m) => [
                    'ma_mon'  => (int) $m['id_mon'],
                    'ten_mon' => $m['ten_mon'],
                    'mo_ta'   => $m['mo_ta'],
                    'anh'     => $m['anh'],
                    'gia'     => (float) $m['gia'],
                ], $dm['mon']),
            ], $danh_muc);

            tra_json(['thanh_cong' => true, 'danh_muc' => $ra], 200);

        /* ===========================================================
         *  3. POST /api/orders   -> 201 / 409 / 422
         *     5 dieu kien may chu kiem tra, ghi trong 1 giao dich
         * =========================================================== */
        case $ep === 'orders' && $method === 'POST':

            if (!co_quyen('gui_don')) {
                tra_loi('Chỉ khách tại bàn mới gửi được đơn gọi món.', 403);
            }

            $nc = ngu_canh_khach();
            if (!$nc) {
                tra_loi('Chưa quét mã QR, máy chủ chưa xác định được bàn.', 409);
            }
            if (!$nc['id_phien']) {
                tra_loi('Bàn ' . $nc['so_ban'] . ' chưa mở phiên phục vụ.', 409);
            }

            $body       = doc_json_body();
            $mon        = is_array($body['mon'] ?? null) ? $body['mon'] : [];
            $ma_yeu_cau = (string) ($body['ma_yeu_cau'] ?? '');

            // Khong nhan tong tien tu trinh duyet: may chu tu lay gia trong CSDL
            $kq = NghiepVu::tao_don((int) $nc['id_phien'], $mon, $ma_yeu_cau);

            if (!$kq['ok']) {
                tra_loi($kq['thong_bao'], $kq['ma'],
                        isset($kq['gia_moi']) ? ['gia_moi' => $kq['gia_moi'],
                                                 'ma_mon'  => $kq['ma_mon']] : []);
            }

            tra_json([
                'thanh_cong' => true,
                'id_don'     => $kq['id_don'],
                'trang_thai' => $kq['trang_thai'],
                'gui_trung'  => $kq['gui_trung'] ?? false,
                'thong_bao'  => ($kq['gui_trung'] ?? false)
                    ? 'Đơn này đã được ghi nhận trước đó, hệ thống không tạo thêm đơn mới.'
                    : 'Đã gửi đơn tới bếp.',
            ], $kq['ma']);

        /* ===========================================================
         *  4. GET /api/orders?session=...   -> 200 / 403
         *     Khach chi thay don cua phien hien tai
         * =========================================================== */
        case $ep === 'orders' && $method === 'GET':

            $id_phien = (int) ($_GET['session'] ?? 0);
            $vai_tro  = vai_tro_hien_tai();

            if ($vai_tro === 'khach') {
                $nc = ngu_canh_khach();
                if (!$nc || !$nc['id_phien']) {
                    tra_loi('Chưa có phiên phục vụ cho bàn này.', 409);
                }
                // Chan khach doc don cua ban khac
                if ($id_phien !== 0 && $id_phien !== (int) $nc['id_phien']) {
                    tra_loi('Bạn chỉ xem được đơn của bàn mình đang ngồi.', 403);
                }
                $id_phien = (int) $nc['id_phien'];

                // Phien da dong: trang cu cua khach bi chan (ca kiem thu TC16)
                $p = Db::mot('SELECT trang_thai FROM PhienPhucVu WHERE id_phien = ?', [$id_phien]);
                if (!$p || $p['trang_thai'] === 'Đã đóng') {
                    tra_loi('Phiên phục vụ đã kết thúc. Vui lòng quét lại mã QR trên bàn.', 409);
                }
            }

            // Nhan vien khong truyen session: danh sach don theo trang thai tren
            // toan nha hang (man hinh bep / phuc vu), vd ?trang_thai=Mới nhận,Đang chế biến
            if ($id_phien === 0) {
                $loc = array_values(array_intersect(
                    array_map('trim', explode(',', (string) ($_GET['trang_thai'] ?? ''))),
                    TT_DON
                ));
                if (!$loc) {
                    $loc = ['Mới nhận', 'Đang chế biến', 'Chờ phục vụ'];
                }
                $don = NghiepVu::don_dang_xu_ly($loc);
                tra_json([
                    'thanh_cong' => true,
                    'luc'        => Db::bay_gio(),
                    'don' => array_map(fn($d) => [
                        'id_don'     => (int) $d['id_don'],
                        'id_phien'   => (int) $d['id_phien'],
                        'so_ban'     => $d['so_ban'],
                        'khu_vuc'    => $d['khu_vuc'],
                        'trang_thai' => $d['trang_thai'],
                        'tao_luc'    => $d['tao_luc'],
                        'cap_nhat_luc' => $d['cap_nhat_luc'],
                        'tong'       => $d['tong'],
                        'chi_tiet'   => array_map(fn($c) => [
                            'ten_mon'  => $c['ten_mon_luc_dat'],
                            'so_luong' => (int) $c['so_luong'],
                            'ghi_chu'  => $c['ghi_chu'],
                        ], $d['chi_tiet']),
                    ], $don),
                ], 200);
            }

            $phien = NghiepVu::phien($id_phien);
            if (!$phien) {
                tra_loi('Không tìm thấy phiên phục vụ.', 404);
            }

            $don = NghiepVu::don_cua_phien($id_phien);

            tra_json([
                'thanh_cong' => true,
                'phien' => [
                    'id_phien'   => (int) $phien['id_phien'],
                    'so_ban'     => $phien['so_ban'],
                    'trang_thai' => $phien['trang_thai'],
                    'mo_luc'     => $phien['mo_luc'],
                    'tong'       => $phien['tong'],
                ],
                'don' => array_map(fn($d) => [
                    'id_don'     => (int) $d['id_don'],
                    'trang_thai' => $d['trang_thai'],
                    'tao_luc'    => $d['tao_luc'],
                    'ly_do_huy'  => $d['ly_do_huy'],
                    'tong'       => $d['tong'],
                    'chi_tiet'   => array_map(fn($c) => [
                        'ten_mon'  => $c['ten_mon_luc_dat'],
                        'don_gia'  => (float) $c['don_gia_luc_dat'],
                        'so_luong' => (int) $c['so_luong'],
                        'ghi_chu'  => $c['ghi_chu'],
                    ], $d['chi_tiet']),
                ], $don),
            ], 200);

        /* ===========================================================
         *  5. PATCH /api/orders/{id}/status  -> 200 / 403 / 409
         *     Dung vai tro va chuyen trang thai dung trinh tu
         * =========================================================== */
        case $ep === 'orders' && ($doan[2] ?? '') === 'status'
             && in_array($method, ['PATCH', 'POST'], true):

            $id_don = (int) ($doan[1] ?? 0);
            $body   = doc_json_body();
            $sang   = trim((string) ($body['trang_thai'] ?? ''));
            $ly_do  = $body['ly_do_huy'] ?? null;

            $nv = nhan_vien();
            if (!$nv) {
                tra_loi('Cần đăng nhập bằng tài khoản nhân viên.', 403);
            }

            $kq = NghiepVu::doi_trang_thai($id_don, $sang, $nv['vai_tro'], $ly_do);
            if (!$kq['ok']) {
                tra_loi($kq['thong_bao'], $kq['ma']);
            }

            tra_json([
                'thanh_cong' => true,
                'id_don'     => $kq['id_don'],
                'trang_thai' => $kq['trang_thai'],
            ], 200);

        /* ===========================================================
         *  6. POST /api/sessions  -> 201 / 409
         *     Ban hoat dong va chua co phien nao dang mo
         * =========================================================== */
        case $ep === 'sessions' && $method === 'POST' && count($doan) === 1:

            if (!co_quyen('mo_phien')) {
                tra_loi('Chỉ nhân viên phục vụ hoặc quản trị viên được mở phiên.', 403);
            }

            $body   = doc_json_body();
            $id_ban = (int) ($body['id_ban'] ?? 0);
            $nv     = nhan_vien();

            $kq = NghiepVu::mo_phien_phuc_vu($id_ban, (int) $nv['id_tai_khoan']);
            if (!$kq['ok']) {
                tra_loi($kq['thong_bao'], $kq['ma']);
            }

            tra_json([
                'thanh_cong' => true,
                'id_phien'   => $kq['id_phien'],
                'thong_bao'  => 'Đã mở phiên phục vụ cho bàn ' . $kq['ban']['so_ban'] . '.',
            ], 201);

        /* ===========================================================
         *  7. POST /api/sessions/{id}/checkout  -> 200 / 409
         *     Moi don da phuc vu hoac da huy
         * =========================================================== */
        case $ep === 'sessions' && ($doan[2] ?? '') === 'checkout' && $method === 'POST':

            if (!co_quyen('thanh_toan')) {
                tra_loi('Chỉ thu ngân hoặc quản trị viên được tổng hợp tiền và đóng phiên.', 403);
            }

            $id_phien = (int) ($doan[1] ?? 0);
            $nv       = nhan_vien();

            $kq = NghiepVu::thanh_toan($id_phien, (int) $nv['id_tai_khoan']);
            if (!$kq['ok']) {
                tra_loi($kq['thong_bao'], $kq['ma']);
            }

            tra_json([
                'thanh_cong'      => true,
                'id_phien'        => $kq['id_phien'],
                'tong_thanh_toan' => $kq['tong_thanh_toan'],
                'thong_bao'       => 'Đã thu ' . tien($kq['tong_thanh_toan'])
                                   . ' và đóng phiên phục vụ.',
            ], 200);

        /* --------------------------------------------------------- */
        default:
            tra_loi('Không có API tương ứng với đường dẫn này.', 404);
    }
} catch (Throwable $e) {
    tra_json([
        'thanh_cong' => false,
        'thong_bao'  => 'Lỗi máy chủ: ' . $e->getMessage(),
    ], 500);
}
