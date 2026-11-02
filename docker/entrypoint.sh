#!/usr/bin/env bash
set -e

cd /var/www/html

echo "==> [1/6] Menyiapkan direktori storage & cache..."
mkdir -p \
    storage/app/public \
    storage/app/backups \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if [ ! -f .env ]; then
    echo "==> File .env belum ada, menyalin dari .env.docker.example..."
    cp .env.docker.example .env
fi

chmod 644 .env || true
php artisan config:clear --ansi >/dev/null 2>&1 || true

if ! grep -qE '^APP_KEY=base64:.+' .env; then
    echo "==> [2/6] Membuat APP_KEY baru..."
    if ! php artisan key:generate --force --ansi 2>/dev/null; then
        KEY_FILE="storage/app/public/.app_key"
        if [ ! -f "$KEY_FILE" ]; then
            php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$KEY_FILE"
        fi
        export APP_KEY=$(cat "$KEY_FILE")
        echo "    .env read-only di container -> menggunakan APP_KEY persisten dari $KEY_FILE"
    fi
else
    echo "==> [2/6] APP_KEY sudah tersedia."
fi

echo "==> [3/6] Menunggu koneksi database MariaDB siap..."
php -r '
$lines = file(".env", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
$env = [];
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === "" || str_starts_with($line, "#") || !str_contains($line, "=")) continue;
    [$k, $v] = explode("=", $line, 2);
    $env[trim($k)] = trim(trim($v), "\"'\''");
}
$host = getenv("DB_HOST") ?: ($env["DB_HOST"] ?? "db");
$port = getenv("DB_PORT") ?: ($env["DB_PORT"] ?? "3306");
$db   = getenv("DB_DATABASE") ?: ($env["DB_DATABASE"] ?? "db_administrasi_guru");
$user = getenv("DB_USERNAME") ?: ($env["DB_USERNAME"] ?? "root");
$pass = getenv("DB_PASSWORD") !== false ? getenv("DB_PASSWORD") : ($env["DB_PASSWORD"] ?? "");

for ($i = 1; $i <= 30; $i++) {
    try {
        new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass, [
            PDO::ATTR_TIMEOUT => 3,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        echo "    Database MariaDB terhubung!\n";
        exit(0);
    } catch (Throwable $e) {
        echo "    Percobaan {$i}/30: menunggu MariaDB ({$host}:{$port})...\n";
        sleep(2);
    }
}
fwrite(STDERR, "Gagal terhubung ke database setelah 30 percobaan.\n");
exit(1);
'

echo "==> [4/6] Menghubungkan symlink storage & menjalankan migrasi..."
rm -rf public/storage
ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage
php artisan migrate --force --ansi

echo "==> [5/6] Memeriksa data awal (seeder otomatis jika database masih kosong)..."
AUTO_SEED=$(grep -E '^AUTO_SEED_IF_EMPTY=' .env | cut -d '=' -f2 | tr -d '\r"'\''' || true)
if [ "${AUTO_SEED:-true}" = "true" ]; then
    USER_COUNT=$(php -r '
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        echo App\Models\User::count();
    ')
    if [ "${USER_COUNT:-0}" = "0" ]; then
        echo "    Database masih kosong -> menjalankan DatabaseSeeder..."
        php artisan db:seed --force --ansi
    else
        echo "    Database sudah berisi ${USER_COUNT} akun -> lewati seeder."
    fi
fi

echo "==> [6/6] Mengoptimalkan cache produksi & hak akses..."
php artisan config:cache --ansi
php artisan route:cache --ansi
php artisan view:cache --ansi

chown -R www-data:www-data /var/www/html/storage/framework /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage/app/public /var/www/html/storage/logs 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

echo "==> Sistem Administrasi Guru siap melayani request!"
exec "$@"
