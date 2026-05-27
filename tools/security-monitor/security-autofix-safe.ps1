param(
    [string]$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path,
    [switch]$Apply,
    [string]$ReportPath
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if (-not $ReportPath) {
    $ReportPath = Join-Path $RepoRoot 'config\logs\security_autofix_report.json'
}

if (-not (Test-Path -LiteralPath $RepoRoot)) {
    throw "RepoRoot non trovato: $RepoRoot"
}

$repoRootResolved = (Resolve-Path -LiteralPath $RepoRoot).Path
$actions = New-Object 'System.Collections.Generic.List[object]'

function Add-Action {
    param(
        [System.Collections.Generic.List[object]]$List,
        [string]$Type,
        [string]$Target,
        [string]$Status,
        [string]$Message
    )

    $List.Add([PSCustomObject]@{
        type = $Type
        target = $Target
        status = $Status
        message = $Message
    })
}

function Ensure-FileContent {
    param(
        [string]$Path,
        [string]$Content,
        [switch]$Write
    )

    if (Test-Path -LiteralPath $Path) {
        return $false
    }

    if ($Write) {
        $dir = Split-Path -Parent $Path
        if (-not (Test-Path -LiteralPath $dir)) {
            New-Item -ItemType Directory -Path $dir -Force | Out-Null
        }
        Set-Content -LiteralPath $Path -Value $Content -Encoding UTF8
    }

    return $true
}

$templates = @{
    'config/.htaccess' = @"
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>
"@
    'tools/.htaccess' = @"
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>
"@
    'sql/.htaccess' = @"
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>
"@
    'inc/.htaccess' = @"
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>
"@
    'uploads/.htaccess' = @"
Options -ExecCGI

<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>

RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .phar
"@
}

foreach ($relativePath in $templates.Keys) {
    $targetPath = Join-Path $repoRootResolved ($relativePath -replace '/', '\\')
    $created = Ensure-FileContent -Path $targetPath -Content $templates[$relativePath] -Write:$Apply

    if ($created) {
        if ($Apply) {
            Add-Action -List $actions -Type 'create_htaccess' -Target $relativePath -Status 'applied' -Message 'Creato file hardening mancante.'
        } else {
            Add-Action -List $actions -Type 'create_htaccess' -Target $relativePath -Status 'planned' -Message 'File mancante: verrà creato con -Apply.'
        }
    } else {
        Add-Action -List $actions -Type 'create_htaccess' -Target $relativePath -Status 'skipped' -Message 'File già presente: nessuna modifica.'
    }
}

$rootHtPath = Join-Path $repoRootResolved '.htaccess'
if (-not (Test-Path -LiteralPath $rootHtPath)) {
    $rootTemplate = @"
RewriteEngine On

# Block dotfiles and dot-directories except .well-known
RewriteRule ^(?:^|/)\.(?!well-known(?:/|$)) - [F,L]
"@

    $createdRoot = Ensure-FileContent -Path $rootHtPath -Content $rootTemplate -Write:$Apply
    if ($createdRoot) {
        if ($Apply) {
            Add-Action -List $actions -Type 'create_htaccess' -Target '.htaccess' -Status 'applied' -Message 'Creato root .htaccess baseline.'
        } else {
            Add-Action -List $actions -Type 'create_htaccess' -Target '.htaccess' -Status 'planned' -Message 'Root .htaccess mancante: verrà creato con -Apply.'
        }
    }
} else {
    Add-Action -List $actions -Type 'create_htaccess' -Target '.htaccess' -Status 'skipped' -Message 'Root .htaccess già presente: nessuna modifica.'
}

$summary = [ordered]@{
    generatedAt = (Get-Date).ToString('o')
    repoRoot = $repoRootResolved
    mode = if ($Apply) { 'apply' } else { 'dry-run' }
    counts = [ordered]@{
        applied = @($actions | Where-Object { $_.status -eq 'applied' }).Count
        planned = @($actions | Where-Object { $_.status -eq 'planned' }).Count
        skipped = @($actions | Where-Object { $_.status -eq 'skipped' }).Count
        total = $actions.Count
    }
    actions = $actions
}

$reportDir = Split-Path -Parent $ReportPath
if (-not (Test-Path -LiteralPath $reportDir)) {
    New-Item -ItemType Directory -Path $reportDir -Force | Out-Null
}

$summary | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath $ReportPath -Encoding UTF8

Write-Host "[SECURITY-AUTOFIX] Mode: $($summary.mode)"
Write-Host "[SECURITY-AUTOFIX] Report salvato: $ReportPath"
Write-Host "[SECURITY-AUTOFIX] Applied: $($summary.counts.applied) | Planned: $($summary.counts.planned) | Skipped: $($summary.counts.skipped)"

exit 0
