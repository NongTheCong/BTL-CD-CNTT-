<?php
/** Dang xuat nhan vien. */
require_once __DIR__ . '/app/helpers.php';

mo_phien();
$_SESSION = [];
session_destroy();
header('Location: /login.php');
