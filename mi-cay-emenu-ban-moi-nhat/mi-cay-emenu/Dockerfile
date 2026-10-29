# Dua web len Render / Railway / Fly.io (khong can XAMPP).
# Anh PHP chinh thuc da co san pdo_sqlite va mbstring.
FROM php:8.2-cli

WORKDIR /app
COPY . /app

# Bo PHP Windows di kem va CSDL tren may ca nhan; CSDL moi tu tao khi mo trang lan dau
RUN rm -rf php .vscode .claude database/*.sqlite \
 && mkdir -p database/phien \
 && chown -R www-data:www-data database

ENV TZ=Asia/Ho_Chi_Minh
USER www-data

# Hosting cap cong qua bien PORT (Render: 10000)
CMD ["sh", "-c", "php -d session.save_path=/app/database/phien -d display_errors=Off -S 0.0.0.0:${PORT:-8080} router.php"]
