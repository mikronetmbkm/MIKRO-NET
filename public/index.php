<?php
// Pintu masuk tunggal: semua permintaan web lewat berkas ini (diatur oleh .htaccess).
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('ASSET_VERSION', '16');

require ROOT . '/app/Core/Env.php';
require ROOT . '/app/Core/DB.php';
require ROOT . '/app/Core/Router.php';
require ROOT . '/app/Core/Respon.php';
require ROOT . '/app/Core/View.php';
require ROOT . '/app/Core/Auth.php';
require ROOT . '/app/Core/Middleware.php';
require ROOT . '/app/Core/Csrf.php';
require ROOT . '/app/Core/Kernel.php';
require ROOT . '/app/Core/helpers.php';

// Muat semua controller dan model secara otomatis berdasarkan nama kelas.
spl_autoload_register(function (string $kelas) {
    foreach (['Controllers', 'Models'] as $folder) {
        $berkas = ROOT . "/app/$folder/$kelas.php";
        if (is_file($berkas)) {
            require $berkas;
            return;
        }
    }
});

Kernel::jalankan();
