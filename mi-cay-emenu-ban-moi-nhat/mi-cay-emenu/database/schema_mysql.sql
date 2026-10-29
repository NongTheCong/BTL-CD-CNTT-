-- =============================================================
-- Mi Cay Sasin - E Menu & Self Ordering
-- Luoc do 7 bang nghiep vu cho MySQL 8 / MariaDB (XAMPP)
-- He thong tu tao cac bang nay khi chay lan dau; tep nay dung de
-- nhap thu cong bang phpMyAdmin hoac dua vao phu luc bao cao.
-- =============================================================

CREATE DATABASE IF NOT EXISTS `mi_cay_emenu`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `mi_cay_emenu`;

CREATE TABLE IF NOT EXISTS TaiKhoan (
        id_tai_khoan   INT AUTO_INCREMENT PRIMARY KEY,
        ten_dang_nhap  VARCHAR(50)  NOT NULL UNIQUE,
        mat_khau_bam   VARCHAR(255) NOT NULL,
        ho_ten         VARCHAR(100) NOT NULL,
        vai_tro        VARCHAR(20)  NOT NULL,
        trang_thai     VARCHAR(20)  NOT NULL DEFAULT 'Hoạt động'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS DanhMuc (
        id_danh_muc    INT AUTO_INCREMENT PRIMARY KEY,
        ten_danh_muc   VARCHAR(100) NOT NULL,
        thu_tu         SMALLINT NOT NULL DEFAULT 0,
        trang_thai     VARCHAR(20)  NOT NULL DEFAULT 'Hiển thị'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS MonAn (
        id_mon           INT AUTO_INCREMENT PRIMARY KEY,
        id_danh_muc      INT NOT NULL,
        ten_mon          VARCHAR(150) NOT NULL,
        mo_ta            VARCHAR(500) NULL,
        anh              VARCHAR(255) NULL,
        gia              DECIMAL(12,0) NOT NULL,
        trang_thai_ban   VARCHAR(20) NOT NULL DEFAULT 'Đang bán',
        thu_tu           SMALLINT NOT NULL DEFAULT 0,
        CONSTRAINT fk_mon_danh_muc FOREIGN KEY (id_danh_muc)
            REFERENCES DanhMuc(id_danh_muc)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS BanAn (
        id_ban      INT AUTO_INCREMENT PRIMARY KEY,
        so_ban      VARCHAR(20) NOT NULL UNIQUE,
        ma_qr       VARCHAR(64) NOT NULL UNIQUE,
        khu_vuc     VARCHAR(50) NOT NULL,
        trang_thai  VARCHAR(20) NOT NULL DEFAULT 'Hoạt động'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS PhienPhucVu (
        id_phien         INT AUTO_INCREMENT PRIMARY KEY,
        id_ban           INT NOT NULL,
        id_thu_ngan      INT NULL,
        mo_luc           DATETIME NOT NULL,
        dong_luc         DATETIME NULL,
        trang_thai       VARCHAR(20) NOT NULL DEFAULT 'Đang mở',
        tong_thanh_toan  DECIMAL(12,0) NULL,
        CONSTRAINT fk_phien_ban FOREIGN KEY (id_ban)
            REFERENCES BanAn(id_ban),
        CONSTRAINT fk_phien_thu_ngan FOREIGN KEY (id_thu_ngan)
            REFERENCES TaiKhoan(id_tai_khoan)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS DonHang (
        id_don        INT AUTO_INCREMENT PRIMARY KEY,
        id_phien      INT NOT NULL,
        ma_yeu_cau    VARCHAR(40) NOT NULL,
        trang_thai    VARCHAR(20) NOT NULL DEFAULT 'Mới nhận',
        tao_luc       DATETIME NOT NULL,
        cap_nhat_luc  DATETIME NULL,
        ly_do_huy     VARCHAR(200) NULL,
        CONSTRAINT fk_don_phien FOREIGN KEY (id_phien)
            REFERENCES PhienPhucVu(id_phien),
        CONSTRAINT uq_don_ma_yeu_cau UNIQUE (id_phien, ma_yeu_cau)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ChiTietDonHang (
        id_chi_tiet       INT AUTO_INCREMENT PRIMARY KEY,
        id_don            INT NOT NULL,
        id_mon            INT NOT NULL,
        ten_mon_luc_dat   VARCHAR(150) NOT NULL,
        don_gia_luc_dat   DECIMAL(12,0) NOT NULL,
        so_luong          SMALLINT NOT NULL,
        ghi_chu           VARCHAR(200) NULL,
        CONSTRAINT fk_ct_don FOREIGN KEY (id_don)
            REFERENCES DonHang(id_don),
        CONSTRAINT fk_ct_mon FOREIGN KEY (id_mon)
            REFERENCES MonAn(id_mon)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

