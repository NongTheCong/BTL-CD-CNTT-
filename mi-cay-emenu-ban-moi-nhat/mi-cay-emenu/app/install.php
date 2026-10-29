<?php
/**
 * Tao 7 bang va nap du lieu mau cho quan mi cay.
 * Chay tu dong khi mo trang lan dau, hoac goi lai: /app/install.php?lam_lai=1
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/schema.php';

function da_cai_dat(): bool
{
    try {
        $r = Db::mot('SELECT COUNT(*) AS n FROM MonAn');
        return $r !== null && (int) $r['n'] > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function tao_bang(): void
{
    $pdo = Db::ket_noi();
    foreach (luoc_do(Db::driver()) as $sql) {
        $pdo->exec($sql);
    }
}

function xoa_het(): void
{
    $pdo = Db::ket_noi();
    $bang = ['ChiTietDonHang', 'DonHang', 'PhienPhucVu', 'MonAn', 'DanhMuc', 'BanAn', 'TaiKhoan'];
    if (Db::driver() === 'mysql') {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    } else {
        $pdo->exec('PRAGMA foreign_keys = OFF');
    }
    foreach ($bang as $b) {
        $pdo->exec("DROP TABLE IF EXISTS $b");
    }
    if (Db::driver() === 'mysql') {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    } else {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
}

/* ---------------------------------------------------------------
 *  Du lieu mau
 * --------------------------------------------------------------- */

/** 5 tai khoan noi bo - mat khau luu duoi dang bam, khong luu dang ro. */
function nap_tai_khoan(): void
{
    $ds = [
        ['phucvu01', 'Trần Thu Hà',    'phuc_vu'],
        ['bep01',    'Nguyễn Văn Khoa', 'bep'],
        ['thungan01', 'Lê Minh Anh',    'thu_ngan'],
        ['admin',    'Quản trị hệ thống', 'quan_tri'],
        ['phucvu02', 'Phạm Quốc Bảo',  'phuc_vu'],
    ];
    foreach ($ds as [$ten, $ho_ten, $vai_tro]) {
        Db::chay(
            "INSERT INTO TaiKhoan (ten_dang_nhap, mat_khau_bam, ho_ten, vai_tro, trang_thai)
             VALUES (?, ?, ?, ?, 'Hoạt động')",
            [$ten, password_hash('123456', PASSWORD_DEFAULT), $ho_ten, $vai_tro]
        );
    }
}

/** 12 ban an chia 3 khu vuc, moi ban mot ma QR rieng. */
function nap_ban(): void
{
    $khu = [
        'Tầng 1 – Khu thường' => ['B01', 'B02', 'B03', 'B04', 'B05'],
        'Tầng 1 – Quầy bar'   => ['Q01', 'Q02', 'Q03'],
        'Tầng 2 – Phòng lạnh' => ['P01', 'P02', 'P03', 'P04'],
    ];
    foreach ($khu as $ten_khu => $ds) {
        foreach ($ds as $so) {
            // Ma QR tinh, duy nhat theo ban
            $ma_qr = 'MC-' . $so . '-' . substr(md5($so . 'mi-cay-sasin'), 0, 8);
            Db::chay(
                "INSERT INTO BanAn (so_ban, ma_qr, khu_vuc, trang_thai)
                 VALUES (?, ?, ?, 'Hoạt động')",
                [$so, $ma_qr, $ten_khu]
            );
        }
    }
    // Mot ban tam ngung de minh hoa rang buoc "ban phai dang hoat dong"
    Db::chay("UPDATE BanAn SET trang_thai = 'Tạm ngừng' WHERE so_ban = 'P04'");
}

/**
 * Thuc don quan mi cay: 6 danh muc, 36 mon.
 * Moi mon: [ten, mo ta, gia, emoji dung lam anh, trang thai ban]
 */
function nap_thuc_don(): void
{
    $thuc_don = [
        'Mì cay truyền thống' => [
            ['Mì cay hải sản', 'Tôm, mực, thanh cua, nghêu và cải thảo trong nước dùng cay đậm vị Hàn.', 69000, '🍜'],
            ['Mì cay bò Mỹ', 'Bò Mỹ thái mỏng, nấm kim châm, hành tây và trứng lòng đào.', 75000, '🥩'],
            ['Mì cay gà phô mai', 'Gà rán giòn phủ phô mai mozzarella kéo sợi trên mì cay.', 72000, '🧀'],
            ['Mì cay kimchi ba chỉ', 'Ba chỉ heo áp chảo cùng kimchi chua cay lên men tự nhiên.', 65000, '🥓'],
            ['Mì cay bạch tuộc', 'Bạch tuộc baby tươi, rau cải và nước dùng xương hầm 8 tiếng.', 85000, '🐙'],
            ['Mì cay thập cẩm đặc biệt', 'Đủ hải sản, bò, xúc xích, phô mai và trứng — món bán chạy nhất quán.', 95000, '🔥'],
            ['Mì cay chay nấm', 'Nấm đông cô, nấm kim châm, đậu hũ non và rau củ theo mùa.', 55000, '🍄'],
            ['Mì cay tôm hùm baby', 'Tôm hùm baby nửa con, nước dùng cay ngọt đậm đà.', 139000, '🦞'],
        ],
        'Mì cay nước ngoài vị' => [
            ['Mì cay tom yum', 'Vị Thái chua cay với sả, lá chanh, riềng và tôm sú.', 79000, '🍲'],
            ['Mì cay sốt cà ri', 'Cà ri Nhật sánh mịn, khoai tây, cà rốt và thịt gà.', 73000, '🍛'],
            ['Mì cay jjajang', 'Sốt tương đen Hàn Quốc, thịt băm và hành tây xào.', 68000, '🍝'],
            ['Mì cay sốt kem trứng muối', 'Sốt kem trứng muối béo nhẹ cân bằng độ cay.', 78000, '🥚'],
        ],
        'Cơm và món chính' => [
            ['Cơm trộn Hàn Quốc (Bibimbap)', 'Cơm trộn đá nóng với thịt bò, rau củ, trứng và sốt gochujang.', 65000, '🍚'],
            ['Cơm rang kimchi ba chỉ', 'Cơm rang kimchi tỏi thơm, ba chỉ heo và trứng ốp la.', 58000, '🍳'],
            ['Cơm cháy chà bông sốt cay', 'Cơm cháy giòn rụm, chà bông và sốt cay nhà làm.', 45000, '🍘'],
            ['Gà sốt cay Hàn Quốc', 'Gà rán phủ sốt gochujang cay ngọt, rắc mè rang.', 89000, '🍗'],
            ['Lẩu mì cay 2 người', 'Nồi lẩu mì cay đầy đủ hải sản và thịt, dùng cho 2 người.', 199000, '🍲'],
        ],
        'Topping thêm' => [
            ['Thêm mì', 'Một phần mì tươi Hàn Quốc.', 15000, '🍥'],
            ['Thêm phô mai mozzarella', 'Một lớp phô mai kéo sợi.', 20000, '🧈'],
            ['Thêm bò Mỹ', 'Khoảng 80g bò Mỹ thái mỏng.', 35000, '🥓'],
            ['Thêm hải sản', 'Tôm, mực và thanh cua.', 35000, '🦐'],
            ['Thêm xúc xích', 'Hai cây xúc xích Đức.', 18000, '🌭'],
            ['Thêm trứng', 'Trứng lòng đào hoặc trứng luộc.', 10000, '🥚'],
            ['Thêm nấm', 'Nấm kim châm và nấm đông cô.', 15000, '🍄'],
            ['Thêm bánh gạo tteokbokki', 'Bánh gạo Hàn Quốc dai mềm.', 20000, '🍢'],
        ],
        'Món ăn kèm' => [
            ['Gà viên phô mai', 'Sáu viên gà phô mai chiên giòn, kèm sốt mayonnaise.', 45000, '🍗'],
            ['Khoai tây lắc phô mai', 'Khoai tây chiên lắc bột phô mai.', 35000, '🍟'],
            ['Gimbap cuộn', 'Tám miếng gimbap nhân trứng, cà rốt và thanh cua.', 42000, '🍙'],
            ['Bánh gạo cay tteokbokki', 'Bánh gạo sốt cay ngọt rắc mè, phủ phô mai.', 49000, '🍢'],
            ['Trứng cuộn Hàn Quốc', 'Trứng cuộn nhiều lớp mềm mịn.', 32000, '🍳'],
        ],
        'Đồ uống' => [
            ['Trà đào cam sả', 'Trà đào lạnh với cam tươi và sả, giải cay rất tốt.', 30000, '🍑'],
            ['Sữa chua đá', 'Sữa chua nhà làm đánh đá, dịu vị cay.', 25000, '🥛'],
            ['Soda chanh bạc hà', 'Soda mát lạnh với chanh tươi và lá bạc hà.', 28000, '🥤'],
            ['Nước ngọt lon', 'Coca Cola, Pepsi, 7Up hoặc Sting.', 15000, '🥫'],
            ['Trà đá', 'Trà đá truyền thống.', 5000, '🧊'],
            ['Bia Hàn Quốc', 'Bia Cass hoặc Hite, lon 330ml.', 35000, '🍺'],
        ],
    ];

    $thu_tu_dm = 1;
    foreach ($thuc_don as $ten_dm => $ds_mon) {
        Db::chay(
            "INSERT INTO DanhMuc (ten_danh_muc, thu_tu, trang_thai) VALUES (?, ?, 'Hiển thị')",
            [$ten_dm, $thu_tu_dm++]
        );
        $id_dm = Db::id_moi();

        $thu_tu_mon = 1;
        foreach ($ds_mon as [$ten, $mo_ta, $gia, $anh]) {
            Db::chay(
                "INSERT INTO MonAn
                 (id_danh_muc, ten_mon, mo_ta, anh, gia, trang_thai_ban, thu_tu)
                 VALUES (?, ?, ?, ?, ?, 'Đang bán', ?)",
                [$id_dm, $ten, $mo_ta, $anh, $gia, $thu_tu_mon++]
            );
        }
    }

    // Hai mon het hang de minh hoa rang buoc "mon con ban" (ca kiem thu TC02)
    Db::chay("UPDATE MonAn SET trang_thai_ban = 'Hết hàng'
              WHERE ten_mon IN ('Mì cay tôm hùm baby', 'Thêm hải sản')");
}

/** Mot phien dang mo cho ban B05 de co du lieu demo ngay tu dau. */
function nap_phien_mau(): void
{
    $b05 = Db::mot("SELECT id_ban FROM BanAn WHERE so_ban = 'B05'");
    if ($b05) {
        Db::chay(
            "INSERT INTO PhienPhucVu (id_ban, mo_luc, trang_thai) VALUES (?, ?, 'Đang mở')",
            [$b05['id_ban'], Db::bay_gio()]
        );
    }
    $b01 = Db::mot("SELECT id_ban FROM BanAn WHERE so_ban = 'B01'");
    if ($b01) {
        Db::chay(
            "INSERT INTO PhienPhucVu (id_ban, mo_luc, trang_thai) VALUES (?, ?, 'Đang mở')",
            [$b01['id_ban'], Db::bay_gio()]
        );
    }
}

/** Cai dat day du. */
function cai_dat(bool $lam_lai = false): string
{
    if ($lam_lai) {
        xoa_het();
    }
    tao_bang();

    if (da_cai_dat() && !$lam_lai) {
        return 'Hệ thống đã được cài đặt trước đó.';
    }

    Db::giao_dich(function () {
        nap_tai_khoan();
        nap_ban();
        nap_thuc_don();
        nap_phien_mau();
    });

    return 'Đã tạo 7 bảng và nạp dữ liệu mẫu cho quán mì cay.';
}

/** Bao dam CSDL san sang truoc khi mo bat ky trang nao. */
function bao_dam_cai_dat(): void
{
    try {
        if (!da_cai_dat()) {
            cai_dat(false);
        }
    } catch (Throwable $e) {
        cai_dat(true);
    }
}

// Cho phep goi truc tiep: /app/install.php?lam_lai=1
if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'install.php') {
    mo_phien();
    // Chi quan tri vien moi duoc cai dat lai (tranh nguoi la xoa du lieu khi web dua len mang)
    if (!co_quyen('quan_ly_he_thong')) {
        http_response_code(403);
        exit('403 — Chỉ quản trị viên được cài đặt lại dữ liệu.');
    }
    $lam_lai = isset($_GET['lam_lai']);
    $tb = cai_dat($lam_lai);
    header('Content-Type: text/html; charset=utf-8');
    echo '<meta charset="utf-8"><body style="font:16px system-ui;padding:40px">';
    echo '<h2>Cài đặt cơ sở dữ liệu</h2>';
    echo '<p><b>' . h($tb) . '</b></p>';
    echo '<p>Hệ quản trị CSDL đang dùng: <b>' . h(strtoupper(Db::driver())) . '</b></p>';
    echo '<p><a href="../index.php">&larr; Về trang chủ</a></p>';
}
