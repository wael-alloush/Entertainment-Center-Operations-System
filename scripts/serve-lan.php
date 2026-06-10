<?php

declare(strict_types=1);

require __DIR__.'/lan-ip.php';

$ip = resolveLanIp();
$port = (int) (getenv('APP_PORT') ?: 8000);
$url = "http://{$ip}:{$port}";

putenv("APP_URL={$url}");

echo PHP_EOL;
echo '  بناء الواجهة...'.PHP_EOL;
passthru('npm run build', $buildExitCode);

if ($buildExitCode !== 0) {
    exit($buildExitCode);
}

echo PHP_EOL;
echo '  رعد — جاهز على الشبكة'.PHP_EOL;
echo '  ─────────────────────────'.PHP_EOL;
echo "  من هذا الجهاز:  http://localhost:{$port}".PHP_EOL;
echo "  من جهاز آخر:    {$url}".PHP_EOL;
echo PHP_EOL;

passthru("php artisan serve --host=0.0.0.0 --port={$port}", $serveExitCode);

exit($serveExitCode);
