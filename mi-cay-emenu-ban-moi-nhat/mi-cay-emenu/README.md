# Mì Cay Sasin — E Menu & Self Ordering

Website gọi món tại bàn cho quán mì cay, cài đặt theo báo cáo
*Phân tích và thiết kế hệ thống Thực đơn điện tử và tự gọi món tại bàn*
(Khoa CNTT — Trường Đại học Thủy lợi).

Khách quét mã QR trên bàn, xem thực đơn, chọn cấp độ cay 1–7 và gửi đơn ngay
trên điện thoại. Đơn chạy tới màn hình bếp, phục vụ và thu ngân.

---

## 1. Chạy nhanh

Yêu cầu: **Windows 10 hoặc 11**. Không cần cài XAMPP, PHP hay MySQL —
gói đã kèm sẵn PHP 8.2 thu gọn trong thư mục `php\`.

1. **Giải nén** file zip ra một thư mục (chuột phải → *Extract All*).
   Đừng chạy trực tiếp bên trong file zip.
2. Bấm đúp **`START.bat`**.
3. Trình duyệt tự mở trang web. Cửa sổ đen hiện địa chỉ đang chạy
   (thường là http://localhost:8080; nếu cổng 8080 bận sẽ tự chuyển sang
   8081, 8088…).
4. Muốn tắt trang web: đóng cửa sổ đen.

Lần chạy đầu, hệ thống tự tạo 7 bảng và nạp dữ liệu mẫu (6 danh mục, 36 món,
12 bàn, 5 tài khoản).

Máy cần có Internet để tải phông chữ và thư viện vẽ mã QR; không có mạng thì
trang vẫn chạy nhưng không hiện ảnh QR.

Nếu Windows hiện cảnh báo *“Windows protected your PC”* khi mở `START.bat`,
bấm **More info → Run anyway**.

**macOS / Linux:** cài PHP 8.1 trở lên (có `pdo_sqlite`), rồi chạy trong thư mục dự án:

```bash
php -S 0.0.0.0:8080 router.php
```

### Cơ sở dữ liệu

| Trường hợp | Hệ quản trị dùng |
|---|---|
| Mặc định (máy không chạy MySQL) | SQLite — tệp `database/mi_cay_emenu.sqlite` |
| Máy có **MySQL** đang chạy ở cổng 3306 (ví dụ bật trong XAMPP) | MySQL — CSDL `mi_cay_emenu` được tạo tự động |

Cả hai dùng cùng lược đồ 7 bảng. Chân trang mỗi màn hình ghi rõ đang dùng
hệ nào. Tài khoản MySQL mặc định là `root`, không mật khẩu; sửa trong
`app/config.php` nếu máy bạn khác.

Tệp `database/schema_mysql.sql` là câu lệnh tạo 7 bảng cho MySQL, có thể nhập
thẳng bằng phpMyAdmin nếu muốn tạo bảng thủ công.

### Quét QR bằng điện thoại

1. Điện thoại và máy tính dùng **cùng mạng Wi-Fi**.
2. Chạy `START.bat` (máy chủ mở cho cả mạng LAN để máy khác truy cập được).
   Nếu Windows Firewall hỏi, tick **Private networks** rồi chọn **Allow**.
3. Mở trang chủ trên máy tính: mỗi bàn có một mã QR trỏ tới địa chỉ IP
   trong mạng LAN của máy (ví dụ `http://192.168.1.10:8080/index.php?qr=…`).
4. Dùng camera điện thoại quét mã.

In mã QR dán bàn: đăng nhập `admin` → **Quản trị → Bàn & mã QR → In toàn bộ mã QR**.

---

## 2. Tài khoản dùng thử

Mật khẩu tất cả: **`123456`**

| Tên đăng nhập | Vai trò | Màn hình |
|---|---|---|
| `phucvu01` | Nhân viên phục vụ | Bàn & phục vụ |
| `bep01` | Nhân viên bếp | Màn hình bếp |
| `thungan01` | Thu ngân | Thu ngân |
| `admin` | Quản trị viên | Quản trị (và các màn hình khác) |

Khách hàng không cần đăng nhập.

## 3. Kịch bản demo

1. Đăng nhập `phucvu01` → **Mở phiên** cho một bàn trống (ví dụ B02).
2. Ở cửa sổ ẩn danh khác, mở trang chủ → **Vào bàn B02** (hoặc quét QR).
3. Chọn món, chọn cấp độ cay, thêm vào giỏ → **Xác nhận và gửi đơn**.
4. Đăng nhập `bep01` → **Bắt đầu nấu** → **Nấu xong**.
5. Đăng nhập `phucvu01` → **Đã mang ra bàn**.
6. Đăng nhập `thungan01` → chọn bàn → **Đã thu … · Đóng phiên**.
7. Quay lại trang khách cũ: trang bị chặn, không gửi thêm đơn được.

Bàn **B01** và **B05** đã có phiên mở sẵn để thử ngay. Muốn làm lại từ đầu:
`admin` → **Quản trị → Lịch sử → Cài lại dữ liệu mẫu**.

---

## 4. Đối chiếu với báo cáo

### Kiến trúc 3 tầng

| Tầng | Thư mục / tệp |
|---|---|
| Trình bày (HTML5, CSS3, JavaScript, Bootstrap 5) | `*.php` ở thư mục gốc, `assets/` |
| Ứng dụng — Controller | `api/index.php` |
| Ứng dụng — Service (nghiệp vụ) | `app/NghiepVu.php` |
| Ứng dụng — Model (truy xuất, giao dịch) | `app/Db.php` |
| Dữ liệu (MySQL / SQLite) | `app/schema.php`, `database/` |

> Báo cáo đề xuất tầng ứng dụng viết bằng Node.js + Express. Bản này dùng
> **PHP** để chạy được ngay trên XAMPP; kiến trúc, 7 bảng, 7 API và luồng
> nghiệp vụ giữ đúng như báo cáo.

### 7 bảng dữ liệu

`TaiKhoan` · `DanhMuc` · `MonAn` · `BanAn` · `PhienPhucVu` · `DonHang` · `ChiTietDonHang`

Ràng buộc: khóa ngoại ở mọi quan hệ; duy nhất `(id_phien, ma_yeu_cau)`;
`ChiTietDonHang` giữ tên và giá món lúc đặt; không xóa cứng (món, bàn,
tài khoản chỉ chuyển sang ẩn / tạm ngừng / khóa).

### 7 API

| Phương thức | Đường dẫn | Người gọi | Mã trả về |
|---|---|---|---|
| GET | `/api/table-context?qr=…` | Khách | 200 / 409 |
| GET | `/api/menu` | Khách | 200 |
| POST | `/api/orders` | Khách | 201 / 409 / 422 |
| GET | `/api/orders?session=…` | Khách, nhân viên | 200 / 403 |
| PATCH | `/api/orders/{id}/status` | Bếp, phục vụ | 200 / 403 / 409 |
| POST | `/api/sessions` | Phục vụ, quản trị | 201 / 409 |
| POST | `/api/sessions/{id}/checkout` | Thu ngân | 200 / 409 |

Nhân viên gọi `GET /api/orders` không kèm `session` sẽ nhận danh sách đơn
đang xử lý toàn quán (lọc bằng `?trang_thai=…`), dùng cho màn hình bếp và
phục vụ.

### Màn hình

| Khách hàng (điện thoại) | Nội bộ (đăng nhập theo vai trò) |
|---|---|
| `index.php` — Quét QR, nhận diện bàn | `login.php` — Đăng nhập |
| `menu.php` — Thực đơn theo danh mục | `bep.php` — Màn hình bếp |
| `mon.php` — Chi tiết món, chọn cấp cay | `phuc-vu.php` — Bàn & phục vụ |
| `gio-hang.php` — Giỏ hàng, xác nhận gửi đơn | `thu-ngan.php` — Thu ngân |
| `don-hang.php` — Theo dõi trạng thái đơn | `quan-tri.php` — Quản trị |

Bếp, phục vụ, thu ngân và trang theo dõi đơn tự tải lại mỗi 5 giây.

### Năm điều kiện máy chủ kiểm tra khi gửi đơn

Trong `NghiepVu::tao_don()`:

1. Phiên còn mở
2. Món còn bán
3. Số lượng từ 1 đến 99
4. Giá khách vừa xem khớp giá trong CSDL (chống giả mạo giá)
5. Mã yêu cầu chưa dùng trong phiên (chống gửi trùng)

`DonHang` và `ChiTietDonHang` được ghi trong **một giao dịch**; có lỗi thì
hoàn tác toàn bộ.

### Trạng thái đơn

`Mới nhận → Đang chế biến → Chờ phục vụ → Đã phục vụ`;
`Mới nhận → Đã hủy` (bắt buộc có lý do). Không cho chuyển ngược.

### Các ca kiểm thử đã chạy thử

| Mã | Nội dung | Kết quả |
|---|---|---|
| TC01 | Quét QR nhận diện đúng bàn B05 | 200, trả đúng bàn và phiên |
| TC02 | Gọi món đã hết hàng | 409 |
| TC03 | Gửi đơn khi bàn chưa mở phiên | Xem được thực đơn, gửi đơn bị 409 |
| TC05 | Trình duyệt gửi `gia_vua_xem = 1000` | 409, yêu cầu xác nhận giá mới |
| TC08 | `so_luong = 0` và `100` | 422, không ghi dòng nào |
| TC09 | Gửi 2 lần cùng mã yêu cầu, cùng nội dung | Chỉ 1 đơn, lần 2 trả lại đơn cũ |
| TC12 | Chờ phục vụ → Đang chế biến | 409, trạng thái giữ nguyên |
| TC16 | Đóng phiên #1, mở phiên #2 cho B05 | Phiên #2 không cộng đơn cũ, trang cũ bị chặn |
| — | Bếp đặt "Đã phục vụ" / bếp gọi thanh toán | 403 |
| — | Đóng phiên khi còn đơn chưa phục vụ | 409 |
| — | Hủy đơn đang nấu / hủy không có lý do | 409 / 422 |

## 5. Cấu trúc thư mục

```
mi-cay-emenu/
├── START.bat              Chạy máy chủ
├── php/                   PHP 8.2 thu gọn đi kèm (php.exe, pdo_sqlite, pdo_mysql, mbstring)
├── router.php             Định tuyến cho máy chủ PHP tích hợp
├── index.php … quan-tri.php   Các màn hình
├── phuc-vu-du-lieu.php    Dữ liệu sơ đồ bàn (JSON) cho phục vụ, thu ngân
├── api/index.php          7 API
├── app/
│   ├── config.php         Cấu hình quán, CSDL, cấp độ cay
│   ├── Db.php             Kết nối, truy vấn có tham số, giao dịch
│   ├── NghiepVu.php       Nghiệp vụ: phiên, đơn, trạng thái, thanh toán
│   ├── helpers.php        Phân quyền, máy trạng thái, định dạng
│   ├── schema.php         Lược đồ 7 bảng
│   ├── install.php        Tạo bảng + dữ liệu mẫu
│   └── layout.php         Khung trang dùng chung
├── assets/css/app.css
├── assets/js/app.js       Giỏ hàng, gọi API, tải lại định kỳ
└── database/
    └── schema_mysql.sql   Câu lệnh tạo 7 bảng cho MySQL
```

## 6. Giới hạn (giống báo cáo)

- Chỉ thu tiền mặt tại quầy; chưa tính thuế, phụ phí, khuyến mại.
- Mã QR tĩnh có thể bị chụp lại và dùng từ xa; nhân viên cần đối chiếu đơn bất thường.
  Quản trị viên có nút **Đổi mã QR** để vô hiệu mã cũ.
- Cập nhật giữa các bộ phận bằng tải lại 5 giây, chưa phải thời gian thực.
- Quét QR ngay trên trang chỉ hoạt động khi mở bằng `localhost` hoặc `https`;
  trên điện thoại qua mạng LAN, dùng ứng dụng camera để quét.
