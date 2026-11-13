#!/usr/bin/env bash
# THE DAK FILM PODCAST MEDIA — Ubuntu 22.04/24.04 (ARM64 or x86_64) VPS setup.
# Installs LAMP + FFmpeg + certbot, prepares the docroot and a cron entry for
# the rendition worker. Run as root:  sudo bash setup-vps.sh
set -euo pipefail

echo "==> Updating packages"
apt-get update -y

echo "==> Installing Apache, PHP 8.2+, MySQL, FFmpeg, certbot"
apt-get install -y apache2 mysql-server certbot python3-certbot-apache \
    ffmpeg unzip curl git
# Ubuntu 22.04 ships PHP 8.1; add Ondřej's PPA for 8.2/8.3 if desired:
# add-apt-repository -y ppa:ondrej/php && apt-get update -y
# apt-get install -y php8.2 libapache2-mod-php8.2 php8.2-mysql php8.2-gd php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip
apt-get install -y php libapache2-mod-php php-mysql php-gd php-mbstring php-xml php-curl php-zip

echo "==> PHP limits for large uploads (adjust to taste)"
PHP_INI=$(php -r 'echo php_ini_loaded_file();')
sed -i 's/^;\?upload_max_filesize.*/upload_max_filesize = 5G/' "$PHP_INI"
sed -i 's/^;\?post_max_size.*/post_max_size = 6G/' "$PHP_INI"
sed -i 's/^;\?max_execution_time.*/max_execution_time = 3600/' "$PHP_INI"
sed -i 's/^;\?max_input_time.*/max_input_time = 3600/' "$PHP_INI"
systemctl restart apache2

echo "==> Docroot"
mkdir -p /var/www/tmz
chown -R www-data:www-data /var/www/tmz

echo "==> Firewall"
ufw allow OpenSSH >/dev/null 2>&1 || true
ufw allow 'Apache Full' >/dev/null 2>&1 || true
ufw --force enable >/dev/null 2>&1 || true

echo "==> MySQL secure-ish baseline (set root password interactively after)"
mysql_secure_installation || true

echo "==> Done. Next:"
echo "  1. rsync the project to /var/www/tmz (see DEPLOYMENT.md section 4)"
echo "  2. Create DB + user, import database/tmz_podcast_complete.sql, edit config/database.php"
echo "  3. Point Apache DocumentRoot at /var/www/tmz and enable .htaccess (AllowOverride All)"
echo "  4. Certbot:  certbot --apache -d thedakfilmpodcastmedia.com"
echo "  5. Cron for renditions (as www-data):"
echo "     * * * * * cd /var/www/tmz && php tools/rendition-worker.php >> storage/logs/rendition-worker.log 2>&1"
echo "  6. Admin -> Settings: enable 'Compress uploaded videos automatically' + 'Allow downloads' as desired"
