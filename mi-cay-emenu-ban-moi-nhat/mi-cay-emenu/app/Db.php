<?php
/**
 * Tang du lieu (Model) - truy xuat CSDL va giao dich
 * Moi truy van dung tham so (prepared statement) de chong chen cau lenh SQL.
 */
class Db
{
    private static ?PDO $pdo = null;
    private static string $driver = '';

    /** Tra ve ket noi PDO dung chung cho ca vong doi request. */
    public static function ket_noi(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cf = require __DIR__ . '/config.php';
        $m  = $cf['csdl']['mysql'];

        $tuy_chon = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        // 1) Uu tien MySQL 8 nhu ban thiet ke trong bao cao.
        //    Thu mo cong truoc (0,3 giay) de khong phai cho PDO het thoi gian
        //    moi lan tai trang khi MySQL chua bat.
        //    Ket qua "MySQL chua bat" duoc nho 20 giay de cac trang tai lai nhanh.
        $tep_nho = sys_get_temp_dir() . '/mi_cay_mysql_tat.txt';
        $vua_thu = is_file($tep_nho) && time() - (int) @file_get_contents($tep_nho) < 20;
        $cong = $vua_thu ? false : @fsockopen($m['host'], (int) $m['port'], $ma_loi, $thong_bao, 0.3);
        if ($cong) {
            fclose($cong);
            @unlink($tep_nho);
        } elseif (!$vua_thu) {
            @file_put_contents($tep_nho, (string) time());
        }
        try {
            if (!$cong) {
                throw new PDOException('MySQL chưa bật');
            }
            $dsn = "mysql:host={$m['host']};port={$m['port']};charset=utf8mb4";
            $pdo = new PDO($dsn, $m['nguoi_dung'], $m['mat_khau'], $tuy_chon);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$m['ten_db']}`
                        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$m['ten_db']}`");
            self::$pdo    = $pdo;
            self::$driver = 'mysql';
            return $pdo;
        } catch (PDOException $e) {
            // MySQL chua bat -> chuyen sang SQLite de he thong van chay duoc
        }

        // 2) Du phong: SQLite (cung luoc do 7 bang)
        $duong_dan = $cf['csdl']['sqlite']['duong_dan'];
        $pdo = new PDO('sqlite:' . $duong_dan, null, null, $tuy_chon);
        $pdo->exec('PRAGMA foreign_keys = ON');
        self::$pdo    = $pdo;
        self::$driver = 'sqlite';
        return $pdo;
    }

    /** 'mysql' hoac 'sqlite' - dung de hien thi o man hinh quan tri. */
    public static function driver(): string
    {
        self::ket_noi();
        return self::$driver;
    }

    /** Truy van tra ve nhieu dong. */
    public static function tat_ca(string $sql, array $tham_so = []): array
    {
        $st = self::ket_noi()->prepare($sql);
        $st->execute($tham_so);
        return $st->fetchAll();
    }

    /** Truy van tra ve mot dong hoac null. */
    public static function mot(string $sql, array $tham_so = []): ?array
    {
        $st = self::ket_noi()->prepare($sql);
        $st->execute($tham_so);
        $dong = $st->fetch();
        return $dong === false ? null : $dong;
    }

    /** Lenh INSERT / UPDATE / DELETE, tra ve so dong bi anh huong. */
    public static function chay(string $sql, array $tham_so = []): int
    {
        $st = self::ket_noi()->prepare($sql);
        $st->execute($tham_so);
        return $st->rowCount();
    }

    /** Khoa chinh vua sinh ra sau INSERT. */
    public static function id_moi(): int
    {
        return (int) self::ket_noi()->lastInsertId();
    }

    /**
     * Chay mot khoi lenh trong MOT giao dich CSDL.
     * Bao cao yeu cau: ghi DonHang va ChiTietDonHang cung luc,
     * co loi thi hoan tac, khong de lai don thieu chi tiet.
     */
    public static function giao_dich(callable $viec)
    {
        $pdo = self::ket_noi();
        $pdo->beginTransaction();
        try {
            $kq = $viec($pdo);
            $pdo->commit();
            return $kq;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Thoi diem hien tai theo dinh dang DATETIME. */
    public static function bay_gio(): string
    {
        return date('Y-m-d H:i:s');
    }
}
