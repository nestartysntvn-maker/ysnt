#!/bin/bash
# Chuyển hệ thống YSNT từ máy chủ thử (nhaviettesting.go365.vn) sang máy chủ riêng của YSNT.
#
#   deploy/chuyen-may-chu.sh <user@ip-may-moi> <ten-mien> [--chay-that]
#
# Mặc định chỉ IN RA các bước sẽ chạy (chạy thử). Thêm --chay-that mới thực hiện.
# Mật khẩu VPS gõ khi ssh hỏi (hoặc dùng ssh key); script không lưu mật khẩu nào.
# Cần trước: tên miền đã trỏ A record về IP máy mới (để lấy SSL), máy mới là Ubuntu có quyền sudo.
# Giữ nguyên APP_KEY cũ: token sàn, khóa AI, mật khẩu mail trong app_settings được mã hóa bằng khóa này.
set -euo pipefail

HOST="${1:-}"; DOMAIN="${2:-}"; LIVE="${3:-}"
[[ -z "$HOST" || -z "$DOMAIN" ]] && { sed -n '2,10p' "$0"; exit 1; }
SRC=/var/www/nhaviettesting; DST=/var/www/ysnt; SRC_DB=ysnt_test; DB=ysnt; DBUSER=ysnt
SOCK=/tmp/ysnt-ssh-$$; SSH="ssh -o ControlPath=$SOCK"
say() { printf '\n== %s\n' "$*"; }
run() { if [[ "$LIVE" == "--chay-that" ]]; then bash -c "$1"; else printf '  [chạy thử] %s\n' "$1"; fi; }
remote() { if [[ "$LIVE" == "--chay-that" ]]; then $SSH "$HOST" "sudo bash -s" <<<"$1"; else printf '  [chạy thử trên %s]\n%s\n' "$HOST" "$(sed -e 's/^/    /' -e 's/APP_KEY=.*/APP_KEY=(giữ khóa của máy cũ)/' <<<"$1")"; fi; }

[[ "$LIVE" == "--chay-that" ]] || echo "CHẠY THỬ: chỉ in các bước. Thêm --chay-that để thực hiện."
if [[ "$LIVE" == "--chay-that" ]]; then
  ssh -o ControlMaster=yes -o ControlPath=$SOCK -o ControlPersist=30m -fN "$HOST"   # hỏi mật khẩu một lần
  trap 'ssh -o ControlPath=$SOCK -O exit "$HOST" 2>/dev/null || true' EXIT
fi

say "1. Cài nginx, MySQL, PHP, Composer, Certbot trên máy mới"
remote 'set -e; export DEBIAN_FRONTEND=noninteractive
apt-get update -q
apt-get install -yq nginx mysql-server certbot python3-certbot-nginx unzip rsync php-fpm php-cli php-mysql php-mbstring php-xml php-curl php-zip php-gd php-intl php-bcmath
command -v composer >/dev/null || { curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer; }
php -v | head -1'

say "2. Tạo database và tài khoản riêng (mật khẩu sinh ngẫu nhiên trên máy mới)"
remote "set -e; P=\$(openssl rand -hex 16); mysql -e \"CREATE DATABASE IF NOT EXISTS $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS '$DBUSER'@'localhost' IDENTIFIED BY '\$P'; ALTER USER '$DBUSER'@'localhost' IDENTIFIED BY '\$P'; GRANT ALL ON $DB.* TO '$DBUSER'@'localhost'; FLUSH PRIVILEGES;\"; umask 077; echo \"\$P\" > /root/.ysnt-db-pass"

say "3. Chép code và tệp đã tải lên (không chép vendor, log, .env)"
remote "mkdir -p $DST && chown -R \$SUDO_USER $DST 2>/dev/null || true"
run "rsync -az --delete --exclude vendor --exclude node_modules --exclude .env --exclude 'storage/logs/*' --exclude 'storage/framework/*/*' --exclude public/storage -e '$SSH' --rsync-path='sudo rsync' $SRC/ $HOST:$DST/"

say "4. Chép dữ liệu từ $SRC_DB sang $DB"
run "mysqldump --default-character-set=utf8mb4 --single-transaction --no-tablespaces $SRC_DB | gzip | $SSH $HOST 'gunzip | sudo mysql $DB'"

say "5. Tạo .env cho máy mới (giữ APP_KEY cũ)"
APP_KEY=$(grep '^APP_KEY=' $SRC/.env | cut -d= -f2-)
remote "set -e; cd $DST; P=\$(cat /root/.ysnt-db-pass)
cat > .env <<ENV
APP_NAME=\"Yen Sao Nha Trang\"
APP_ENV=production
APP_KEY=$APP_KEY
APP_DEBUG=false
APP_URL=https://$DOMAIN
APP_LOCALE=vi
LOG_CHANNEL=daily
LOG_LEVEL=warning
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=$DB
DB_USERNAME=$DBUSER
DB_PASSWORD=\$P
SESSION_DRIVER=file
QUEUE_CONNECTION=database
CACHE_STORE=file
MAIL_MAILER=log
ENV
chown -R www-data:www-data $DST; chmod 640 .env"

say "6. Cài thư viện, chạy migration, tối ưu"
remote "set -e; cd $DST; sudo -u www-data env HOME=/tmp/composer-home COMPOSER_HOME=/tmp/composer-home composer install --no-dev --optimize-autoloader --no-interaction; sudo -u www-data php artisan migrate --force; sudo -u www-data php artisan storage:link; sudo -u www-data php artisan optimize"

say "7. PHP-FPM, nginx, hàng đợi, lịch chạy, sao lưu hằng đêm"
remote "set -e; V=\$(php -r 'echo PHP_MAJOR_VERSION.\".\".PHP_MINOR_VERSION;')
cat > /etc/php/\$V/fpm/pool.d/ysnt.conf <<POOL
[ysnt]
user = www-data
group = www-data
listen = /run/php/php\$V-fpm-ysnt.sock
listen.owner = www-data
listen.group = www-data
pm = ondemand
pm.max_children = 10
pm.process_idle_timeout = 10s
pm.max_requests = 500
POOL
cat > /etc/nginx/sites-available/ysnt <<NGX
server {
    listen 80; listen [::]:80;
    server_name $DOMAIN www.$DOMAIN;
    root $DST/public; index index.php; client_max_body_size 20M;
    location / { try_files \\\$uri \\\$uri/ /index.php?\\\$query_string; }
    location ~ \\\\.php\\\$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:/run/php/php\$V-fpm-ysnt.sock; }
    location ~ /\\\\.(?!well-known) { deny all; }
}
NGX
ln -sf /etc/nginx/sites-available/ysnt /etc/nginx/sites-enabled/ysnt
cat > /etc/systemd/system/ysnt-queue.service <<UNIT
[Unit]
Description=YSNT queue worker
After=network.target mysql.service
[Service]
User=www-data
Group=www-data
WorkingDirectory=$DST
ExecStart=/usr/bin/php artisan queue:work database --sleep=3 --tries=1 --max-time=3600 --memory=128
Restart=always
RestartSec=5
[Install]
WantedBy=multi-user.target
UNIT
cat > /usr/local/bin/ysnt-backup.sh <<BK
#!/bin/bash
set -euo pipefail
D=/root/ysnt-backups/daily; mkdir -p \\\$D; chmod 700 /root/ysnt-backups \\\$D; T=\\\$(date +%Y%m%d-%H%M)
mysqldump --default-character-set=utf8mb4 --single-transaction --no-tablespaces $DB | gzip > \\\$D/db-\\\$T.sql.gz
tar czf \\\$D/files-\\\$T.tgz -C $DST storage/app .env
chmod 600 \\\$D/*-\\\$T.*; find \\\$D -type f -mtime +14 -delete
BK
chmod 700 /usr/local/bin/ysnt-backup.sh
printf 'SHELL=/bin/bash\n30 2 * * * root /usr/local/bin/ysnt-backup.sh >/dev/null 2>&1\n* * * * * www-data cd $DST && php artisan schedule:run >> /dev/null 2>&1\n' > /etc/cron.d/ysnt
systemctl daemon-reload; systemctl enable --now ysnt-queue; systemctl restart php\$V-fpm; nginx -t && systemctl reload nginx"

say "8. SSL cho $DOMAIN (tên miền phải đã trỏ về máy mới)"
remote "certbot --nginx --non-interactive --agree-tos --redirect -m admin@$DOMAIN -d $DOMAIN -d www.$DOMAIN || echo 'Chưa lấy được SSL: kiểm tra DNS rồi chạy lại bước này.'"

say "9. Kiểm tra"
run "curl -sS -o /dev/null -w 'Trang chủ: %{http_code}\n' https://$DOMAIN/ || true"
cat <<'NEXT'

Sau khi chuyển:
- Đăng nhập /admin trên tên miền mới, mở "Tình trạng kết nối".
- Khai báo lại đường dẫn callback, webhook mới với Zalo, Meta, TikTok Shop, Shopee, Lazada (trang cấu hình hiện sẵn link).
- Thêm link cũ của ysnt.com.vn vào "Chuyển hướng link cũ", rồi bật "Cho Google lập chỉ mục" ở Cấu hình SEO.
NEXT
