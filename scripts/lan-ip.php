<?php

declare(strict_types=1);

function resolveLanIp(): string
{
    if (extension_loaded('sockets')) {
        $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        if ($socket !== false) {
            @socket_connect($socket, '8.8.8.8', 80);
            @socket_getsockname($socket, $ip);
            @socket_close($socket);

            if (! empty($ip) && $ip !== '127.0.0.1') {
                return $ip;
            }
        }
    }

    if (PHP_OS_FAMILY === 'Windows') {
        $output = shell_exec(
            'powershell -NoProfile -Command "(Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -notlike \'127.*\' -and $_.PrefixOrigin -ne \'WellKnown\' } | Select-Object -First 1).IPAddress"'
        );

        $ip = trim((string) $output);

        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    if (PHP_OS_FAMILY === 'Linux') {
        $output = shell_exec('hostname -I 2>/dev/null');
        $ip = trim(explode(' ', trim((string) $output))[0] ?? '');

        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    if (PHP_OS_FAMILY === 'Darwin') {
        $output = shell_exec('ipconfig getifaddr en0 2>/dev/null');
        $ip = trim((string) $output);

        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    $hostname = gethostname();

    if ($hostname !== false) {
        $ip = gethostbyname($hostname);

        if ($ip !== $hostname && $ip !== '127.0.0.1') {
            return $ip;
        }
    }

    return '127.0.0.1';
}
