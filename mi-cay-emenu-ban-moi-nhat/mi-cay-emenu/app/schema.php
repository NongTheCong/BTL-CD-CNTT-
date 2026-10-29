<?php
/**
 * Luoc do CSDL - 7 bang nghiep vu theo so do thuc the - lien ket (ERD) cua bao cao.
 * Tra ve mang cau lenh CREATE TABLE theo tung he quan tri CSDL.
 */
function luoc_do(string $driver): array
{
    $mysql = $driver === 'mysql';

    // Kieu du lieu tuong duong giua MySQL 8 va SQLite
    $pk      = $mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $tien    = $mysql ? 'DECIMAL(12,0)' : 'NUMERIC';
    $nho     = $mysql ? 'SMALLINT'      : 'INTEGER';
    $dt      = 'DATETIME';
    $engine  = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    return [

        // 1. TaiKhoan - tai khoan nhan vien va vai tro su dung (6 truong)
        "CREATE TABLE IF NOT EXISTS TaiKhoan (
            id_tai_khoan   $pk,
            ten_dang_nhap  VARCHAR(50)  NOT NULL UNIQUE,
            mat_khau_bam   VARCHAR(255) NOT NULL,
            ho_ten         VARCHAR(100) NOT NULL,
            vai_tro        VARCHAR(20)  NOT NULL,
            trang_thai     VARCHAR(20)  NOT NULL DEFAULT 'Hoạt động'
        )$engine",

        // 2. DanhMuc - nhom mon de hien thi tren thuc don (4 truong)
        "CREATE TABLE IF NOT EXISTS DanhMuc (
            id_danh_muc    $pk,
            ten_danh_muc   VARCHAR(100) NOT NULL,
            thu_tu         $nho NOT NULL DEFAULT 0,
            trang_thai     VARCHAR(20)  NOT NULL DEFAULT 'Hiển thị'
        )$engine",

        // 3. MonAn - mon an, gia ban va trang thai con - het (8 truong)
        "CREATE TABLE IF NOT EXISTS MonAn (
            id_mon           $pk,
            id_danh_muc      INT NOT NULL,
            ten_mon          VARCHAR(150) NOT NULL,
            mo_ta            VARCHAR(500) NULL,
            anh              VARCHAR(255) NULL,
            gia              $tien NOT NULL,
            trang_thai_ban   VARCHAR(20) NOT NULL DEFAULT 'Đang bán',
            thu_tu           $nho NOT NULL DEFAULT 0,
            CONSTRAINT fk_mon_danh_muc FOREIGN KEY (id_danh_muc)
                REFERENCES DanhMuc(id_danh_muc)
        )$engine",

        // 4. BanAn - ban an va duong dan ma QR tuong ung (5 truong)
        "CREATE TABLE IF NOT EXISTS BanAn (
            id_ban      $pk,
            so_ban      VARCHAR(20) NOT NULL UNIQUE,
            ma_qr       VARCHAR(64) NOT NULL UNIQUE,
            khu_vuc     VARCHAR(50) NOT NULL,
            trang_thai  VARCHAR(20) NOT NULL DEFAULT 'Hoạt động'
        )$engine",

        // 5. PhienPhucVu - mot luot khach ngoi tai mot ban (7 truong)
        "CREATE TABLE IF NOT EXISTS PhienPhucVu (
            id_phien         $pk,
            id_ban           INT NOT NULL,
            id_thu_ngan      INT NULL,
            mo_luc           $dt NOT NULL,
            dong_luc         $dt NULL,
            trang_thai       VARCHAR(20) NOT NULL DEFAULT 'Đang mở',
            tong_thanh_toan  $tien NULL,
            CONSTRAINT fk_phien_ban FOREIGN KEY (id_ban)
                REFERENCES BanAn(id_ban),
            CONSTRAINT fk_phien_thu_ngan FOREIGN KEY (id_thu_ngan)
                REFERENCES TaiKhoan(id_tai_khoan)
        )$engine",

        // 6. DonHang - mot lan gui don va trang thai xu ly (7 truong)
        "CREATE TABLE IF NOT EXISTS DonHang (
            id_don        $pk,
            id_phien      INT NOT NULL,
            ma_yeu_cau    VARCHAR(40) NOT NULL,
            trang_thai    VARCHAR(20) NOT NULL DEFAULT 'Mới nhận',
            tao_luc       $dt NOT NULL,
            cap_nhat_luc  $dt NULL,
            ly_do_huy     VARCHAR(200) NULL,
            CONSTRAINT fk_don_phien FOREIGN KEY (id_phien)
                REFERENCES PhienPhucVu(id_phien),
            CONSTRAINT uq_don_ma_yeu_cau UNIQUE (id_phien, ma_yeu_cau)
        )$engine",

        // 7. ChiTietDonHang - tung mon trong don, giu gia luc dat (7 truong)
        "CREATE TABLE IF NOT EXISTS ChiTietDonHang (
            id_chi_tiet       $pk,
            id_don            INT NOT NULL,
            id_mon            INT NOT NULL,
            ten_mon_luc_dat   VARCHAR(150) NOT NULL,
            don_gia_luc_dat   $tien NOT NULL,
            so_luong          $nho NOT NULL,
            ghi_chu           VARCHAR(200) NULL,
            CONSTRAINT fk_ct_don FOREIGN KEY (id_don)
                REFERENCES DonHang(id_don),
            CONSTRAINT fk_ct_mon FOREIGN KEY (id_mon)
                REFERENCES MonAn(id_mon)
        )$engine",
    ];
}
