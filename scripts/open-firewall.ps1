#Requires -RunAsAdministrator

$ports = @(8000, 5173)

foreach ($port in $ports) {
    $ruleName = "Raad Laravel Port $port"

    $existing = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue

    if ($existing) {
        Write-Host "قاعدة الجدار الناري موجودة مسبقاً للمنفذ $port"
        continue
    }

    New-NetFirewallRule `
        -DisplayName $ruleName `
        -Direction Inbound `
        -Action Allow `
        -Protocol TCP `
        -LocalPort $port `
        -Profile Private | Out-Null

    Write-Host "تم فتح المنفذ $port للشبكات الخاصة"
}

Write-Host ""
Write-Host "يمكنك الآن الوصول من أجهزة الشبكة المحلية."
