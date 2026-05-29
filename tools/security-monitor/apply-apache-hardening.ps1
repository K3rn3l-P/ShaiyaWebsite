param(
    [string]$ApacheConfDir = "C:\xampp\apache\conf\extra"
)

$ErrorActionPreference = 'Stop'

$sslConfPath = Join-Path $ApacheConfDir "httpd-ssl.conf"
$htaccessPath = "C:\xampp\htdocs\.htaccess"

Write-Host "[APACHE HARDENING] Starting..."

# 1. Add TraceEnable off to httpd-ssl.conf
if (-not (Test-Path $sslConfPath)) {
    throw "Apache SSL config not found: $sslConfPath"
}

$sslContent = Get-Content -Path $sslConfPath -Raw

# Check if TraceEnable is already there
if ($sslContent -match 'TraceEnable\s+off') {
    Write-Host "[APACHE HARDENING] TraceEnable off already present"
} else {
    # Add TraceEnable off inside VirtualHost block after opening tag
    $newContent = $sslContent -replace '(<VirtualHost _default_:443>)', "`$1`n  TraceEnable off"
    Set-Content -Path $sslConfPath -Value $newContent -Encoding UTF8
    Write-Host "[APACHE HARDENING] Added TraceEnable off to httpd-ssl.conf"
}

# 2. Add HTTP method restrictions to .htaccess
if (Test-Path $htaccessPath) {
    $htContent = Get-Content -Path $htaccessPath -Raw
    
    # Check if method restrictions already present
    if ($htContent -match 'LimitExcept') {
        Write-Host "[APACHE HARDENING] HTTP method restrictions already in .htaccess"
    } else {
        # Add method restrictions at the end of file
        $methodRestrict = @"

# Restrict HTTP methods - allow only GET, HEAD, POST, OPTIONS
<LimitExcept GET HEAD POST OPTIONS>
    Require all denied
</LimitExcept>

"@
        Add-Content -Path $htaccessPath -Value $methodRestrict -Encoding UTF8
        Write-Host "[APACHE HARDENING] Added HTTP method restrictions to .htaccess"
    }
} else {
    Write-Host "[APACHE HARDENING] WARNING: .htaccess not found at $htaccessPath"
}

# 3. Restart Apache
Write-Host "[APACHE HARDENING] Restarting Apache service..."
try {
    Restart-Service -Name Apache2.4 -Force -WarningAction SilentlyContinue
    Start-Sleep -Seconds 3
    $svc = Get-Service -Name Apache2.4 -ErrorAction SilentlyContinue
    if ($svc.Status -eq 'Running') {
        Write-Host "[APACHE HARDENING] Apache restarted successfully"
    } else {
        Write-Host "[APACHE HARDENING] WARNING: Apache status is $($svc.Status)"
    }
} catch {
    Write-Host "[APACHE HARDENING] ERROR restarting Apache: $_"
    exit 1
}

Write-Host "[APACHE HARDENING] Complete"
exit 0
