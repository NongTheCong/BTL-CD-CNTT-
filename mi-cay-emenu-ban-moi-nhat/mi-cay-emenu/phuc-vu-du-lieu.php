<?php
/**
 * Du lieu so do ban cho man hinh phuc vu va thu ngan (JSON).
 * Chi nhan vien dang nhap moi doc duoc.
 */
require_once __DIR__ . '/app/NghiepVu.php';

mo_phien();
if (!nhan_vien()) {
    tra_loi('Cần đăng nhập bằng tài khoản nhân viên.', 403);
}

tra_json(['thanh_cong' => true, 'ban' => NghiepVu::ban_va_phien()]);
