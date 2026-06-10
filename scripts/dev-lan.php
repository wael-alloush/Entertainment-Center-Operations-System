<?php

declare(strict_types=1);

require __DIR__.'/lan-ip.php';

$ip = resolveLanIp();
$port = (int) (getenv('APP_PORT') ?: 8000);
$url = "http://{$ip}:{$port}";

putenv("APP_URL={$url}");
putenv("VITE_HMR_HOST={$ip}");

echo PHP_EOL;
echo '  رعد — وضع الشبكة المحلية'.PHP_EOL;
echo '  ─────────────────────────'.PHP_EOL;
echo "  من هذا الجهاز:  http://localhost:{$port}".PHP_EOL;
echo "  من جهاز آخر:    {$url}".PHP_EOL;
echo '  ─────────────────────────'.PHP_EOL;
echo '  إذا لم يفتح من جهاز آخر، شغّل scripts/open-firewall.ps1 كمسؤول'.PHP_EOL;
echo PHP_EOL;

$command = 'npx concurrently -c "#93c5fd,#c4b5fd,#fdba74" '
    .'"php artisan serve --host=0.0.0.0 --port='.$port.'" '
    .'"php artisan queue:listen --tries=1" '
    .'"npm run dev" '
    .'--names=server,queue,vite';

passthru($command, $exitCode);

exit($exitCode);
