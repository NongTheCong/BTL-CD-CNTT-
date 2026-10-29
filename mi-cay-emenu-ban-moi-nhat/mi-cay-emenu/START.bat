@echo off
chcp 65001 >nul
title Mi Cay Sasin - E Menu
cd /d "%~dp0"

rem ---- Chon PHP: uu tien ban di kem trong thu muc php\ ----
rem Dung duong dan TUONG DOI: PHP tren Windows khong doc duoc php.ini va
rem extension khi duong dan tuyet doi co dau tieng Viet (vd "Tai lieu").
set "PHP=php\php.exe"
set "PHPOPT=-n -c php\php.ini -d extension_dir=php\ext -d session.save_path=database\phien"
if not exist "database\phien" mkdir "database\phien"
if exist "%PHP%" goto co_php
set "PHPOPT="
set "PHP=C:\xampp\php\php.exe"
if exist "%PHP%" goto co_php
where php >nul 2>nul && (set "PHP=php" & goto co_php)
echo.
echo   Khong tim thay PHP. Hay giai nen lai day du file zip
echo   (can co thu muc php\ ben canh START.bat).
echo.
pause
exit /b 1

:co_php
rem ---- Chon cong con trong ----
set PORT=
for %%P in (8080 8081 8088 8090 8888 9000) do (
    if not defined PORT (
        netstat -ano | findstr /r /c:":%%P .*LISTENING" >nul || set PORT=%%P
    )
)
if not defined PORT set PORT=8099

rem ---- Dia chi LAN de dien thoai quet QR ----
set LANIP=
for /f "tokens=2 delims=:" %%A in ('ipconfig ^| findstr /c:"IPv4"') do (
    if not defined LANIP set "LANIP=%%A"
)
if defined LANIP set "LANIP=%LANIP: =%"

echo.
echo   ==============================================
echo     MI CAY SASIN - E Menu ^& Self Ordering
echo   ==============================================
echo.
echo   Trang khach hang : http://localhost:%PORT%
if defined LANIP echo   Dien thoai (cung Wi-Fi): http://%LANIP%:%PORT%
echo   Nhan vien        : http://localhost:%PORT%/login.php
echo   Tai khoan        : admin / bep01 / phucvu01 / thungan01
echo   Mat khau         : 123456
echo.
echo   Neu Windows Firewall hoi, chon "Allow" de dien thoai truy cap duoc.
echo   Dong cua so nay de tat trang web.
echo.

rem Mo trinh duyet SAU KHI may chu da nghe cong (toi da ~20 giay), tranh loi
rem ERR_CONNECTION_REFUSED khi PHP khoi dong cham (lan dau, bi antivirus quet).
if not defined MICAY_NO_BROWSER start "" /min powershell -NoProfile -WindowStyle Hidden -Command "for($i=0;$i -lt 80;$i++){try{(New-Object Net.Sockets.TcpClient('127.0.0.1',%PORT%)).Close();break}catch{Start-Sleep -Milliseconds 250}}; Start-Process 'http://localhost:%PORT%'"
rem MICAY_HOST=127.0.0.1 neu chi muon mo tren may nay (khong cho dien thoai vao)
if not defined MICAY_HOST set MICAY_HOST=0.0.0.0
"%PHP%" %PHPOPT% -S %MICAY_HOST%:%PORT% router.php
echo.
echo   May chu da dung.
pause
