<?php
/**
 * Cau hinh he thong - Tang du lieu
 * E Menu & Self Ordering  |  Quan mi cay "Mi Cay Sasin"
 *
 * Bao cao thiet ke chi dinh MySQL 8. Neu chua bat MySQL trong XAMPP,
 * he thong tu chuyen sang SQLite de van chay duoc (cung luoc do 7 bang).
 */

return [
    // Thong tin quan - hien tren moi man hinh
    'quan' => [
        'ten'     => 'MÌ CAY SASIN',
        'slogan'  => 'Mì cay 7 cấp độ · Vị Hàn chuẩn gốc',
        'dia_chi' => '175 Tây Sơn, Đống Đa, Hà Nội',
        'dien_thoai' => '024 6688 7799',
        'gio_mo_cua' => '09:00 – 22:30 hằng ngày',
    ],

    // Tang du lieu: uu tien MySQL (dung XAMPP), fallback SQLite
    'csdl' => [
        'mysql' => [
            'host'     => '127.0.0.1',
            'port'     => 3306,
            'ten_db'   => 'mi_cay_emenu',
            'nguoi_dung' => 'root',
            'mat_khau' => '',
        ],
        'sqlite' => [
            'duong_dan' => __DIR__ . '/../database/mi_cay_emenu.sqlite',
        ],
    ],

    // Chu ky tai lai man hinh bep / phuc vu (giay) - bao cao: 5 giay
    'chu_ky_tai_lai' => 5,

    // Rang buoc nghiep vu theo bao cao
    'so_luong_min' => 1,
    'so_luong_max' => 99,
    'ghi_chu_max'  => 200,

    // Cac cap do cay cua mi cay (dac trung cua quan)
    'cap_do_cay' => [
        1 => 'Cấp 1 · Cay nhẹ',
        2 => 'Cấp 2 · Cay vừa',
        3 => 'Cấp 3 · Cay đậm',
        4 => 'Cấp 4 · Khá cay',
        5 => 'Cấp 5 · Rất cay',
        6 => 'Cấp 6 · Cực cay',
        7 => 'Cấp 7 · Thử thách',
    ],
];
