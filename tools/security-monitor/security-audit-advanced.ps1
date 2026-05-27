param(
    [string]$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path,
    [switch]$FailOnHigh,
    [string]$ReportPath
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$reportPathWasDefaulted = -not $ReportPath

if ($reportPathWasDefaulted) {
    $ReportPath = Join-Path $RepoRoot 'config\logs\security_audit_report.json'
}

function Add-Finding {
    param(
        [System.Collections.Generic.List[object]]$List,
        [ValidateSet('LOW','MEDIUM','HIGH')][string]$Severity,
        [string]$Category,
        [string]$Target,
        [string]$Message,
        [string]$Recommendation
    )

    $List.Add([PSCustomObject]@{
        severity = $Severity
        category = $Category
        target = $Target
        message = $Message
        recommendation = $Recommendation
    })
}

function Test-ContainsRegex {
    param(
        [string]$Path,
        [string]$Regex
    )

    if (-not (Test-Path -LiteralPath $Path)) {
        return $false
    }

    $content = Get-Content -LiteralPath $Path -Raw -ErrorAction SilentlyContinue
    if ($null -eq $content) {
        return $false
    }

    return [regex]::IsMatch($content, $Regex, [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)
}

function Get-RelativeTargetPath {
    param(
        [string]$BasePath,
        [string]$FullPath
    )

    try {
        return [System.IO.Path]::GetRelativePath($BasePath, $FullPath)
    } catch {
        if ($FullPath.StartsWith($BasePath, [System.StringComparison]::OrdinalIgnoreCase)) {
            return $FullPath.Substring($BasePath.Length).TrimStart('\\')
        }

        return $FullPath
    }
}

function Test-RawMatches {
    param(
        [string]$Content,
        [string]$Regex,
        [switch]$Singleline
    )

    $options = [System.Text.RegularExpressions.RegexOptions]::IgnoreCase
    if ($Singleline) {
        $options = $options -bor [System.Text.RegularExpressions.RegexOptions]::Singleline
    }

    return [regex]::IsMatch($Content, $Regex, $options)
}

$findings = New-Object 'System.Collections.Generic.List[object]'

if (-not (Test-Path -LiteralPath $RepoRoot)) {
    throw "RepoRoot non trovato: $RepoRoot"
}

$repoRootResolved = (Get-Item -LiteralPath $RepoRoot).FullName
if ($reportPathWasDefaulted) {
    $ReportPath = Join-Path $repoRootResolved 'config\logs\security_audit_report.json'
}
$legacyPathFragment = [regex]'[\\/]htdocs[\\/]htdocs(?:[\\/]|$)'

function Test-IsExcludedPhpPath {
    param(
        [string]$FullPath,
        [string]$FileName
    )

    if ($legacyPathFragment.IsMatch($FullPath)) {
        return $true
    }

    if ($FileName -like '*.example.php') {
        return $true
    }

    return $false
}

# 1) Hardening file presence checks
$rootHtaccess = Join-Path $repoRootResolved '.htaccess'
if (-not (Test-Path -LiteralPath $rootHtaccess)) {
    Add-Finding -List $findings -Severity 'HIGH' -Category 'hardening' -Target '.htaccess' -Message 'Root .htaccess mancante.' -Recommendation 'Creare root .htaccess con blocco dotfiles e regole baseline.'
}

$sensitiveDirs = @('config','tools','sql','inc')
foreach ($dir in $sensitiveDirs) {
    $fullDir = Join-Path $repoRootResolved $dir
    if (-not (Test-Path -LiteralPath $fullDir)) {
        Add-Finding -List $findings -Severity 'MEDIUM' -Category 'hardening' -Target $dir -Message "Cartella sensibile non trovata: $dir" -Recommendation 'Verificare struttura progetto e hardening directory.'
        continue
    }

    $htPath = Join-Path $fullDir '.htaccess'
    if (-not (Test-Path -LiteralPath $htPath)) {
        Add-Finding -List $findings -Severity 'HIGH' -Category 'hardening' -Target "$dir/.htaccess" -Message "File .htaccess mancante in cartella sensibile: $dir" -Recommendation 'Applicare deny-by-default (Require all denied + fallback legacy Apache).'
    }
}

$uploadsHt = Join-Path $repoRootResolved 'uploads\.htaccess'
if (-not (Test-Path -LiteralPath $uploadsHt)) {
    Add-Finding -List $findings -Severity 'HIGH' -Category 'hardening' -Target 'uploads/.htaccess' -Message 'uploads/.htaccess mancante.' -Recommendation 'Bloccare esecuzione script in uploads.'
} else {
    if (-not (Test-ContainsRegex -Path $uploadsHt -Regex 'Options\s+.*-ExecCGI')) {
        Add-Finding -List $findings -Severity 'MEDIUM' -Category 'hardening' -Target 'uploads/.htaccess' -Message 'Manca Options -ExecCGI in uploads/.htaccess.' -Recommendation 'Aggiungere Options -ExecCGI per ridurre superficie di esecuzione script.'
    }
    if (-not (Test-ContainsRegex -Path $uploadsHt -Regex 'RemoveHandler\s+\.php')) {
        Add-Finding -List $findings -Severity 'MEDIUM' -Category 'hardening' -Target 'uploads/.htaccess' -Message 'Manca RemoveHandler .php in uploads/.htaccess.' -Recommendation 'Rimuovere handler PHP nella cartella uploads.'
    }
}

if (Test-Path -LiteralPath $rootHtaccess) {
    if (-not (Test-ContainsRegex -Path $rootHtaccess -Regex 'well-known')) {
        Add-Finding -List $findings -Severity 'MEDIUM' -Category 'hardening' -Target '.htaccess' -Message 'Root .htaccess senza eccezione .well-known esplicita.' -Recommendation 'Aggiungere eccezione /.well-known/ mantenendo blocco dotfiles.'
    }

    if (-not (Test-ContainsRegex -Path $rootHtaccess -Regex 'RewriteRule.+\.\(\?!well-known')) {
        Add-Finding -List $findings -Severity 'LOW' -Category 'hardening' -Target '.htaccess' -Message 'Regex blocco dot-directories non rilevata nel formato atteso.' -Recommendation 'Verificare presenza blocco dotfiles/dot-directories compatibile.'
    }
}

# 2) Static scan PHP files (active root only)
$phpFiles = Get-ChildItem -Path $repoRootResolved -Recurse -File -Filter '*.php' -ErrorAction SilentlyContinue |
    Where-Object {
        -not (Test-IsExcludedPhpPath -FullPath $_.FullName -FileName $_.Name)
    }

$ruleSet = @(
    @{ severity = 'HIGH'; category = 'sqli'; regex = '\bodbc_exec\s*\('; message = 'Uso odbc_exec rilevato (vietato policy PDO-first).'; recommendation = 'Migrare a PDO prepared statements ($pdoConn/$pdoAdmin).' },
    @{ severity = 'HIGH'; category = 'sqli'; regex = '\bmysql_query\s*\('; message = 'Uso mysql_query rilevato.'; recommendation = 'Rimuovere API legacy e usare PDO prepared statements.' },
    @{ severity = 'MEDIUM'; category = 'redirect'; regex = 'header\s*\(\s*.+Location:\s*/.+\.php'; message = 'Redirect canonico verso URL .php rilevato.'; recommendation = 'Preferire clean URL (/route) per canonical flow.' },
    @{ severity = 'MEDIUM'; category = 'xss'; regex = 'echo\s+\$_(GET|POST|REQUEST|COOKIE)'; message = 'Output diretto da superglobal rilevato.'; recommendation = 'Usare htmlspecialchars per output HTML.' }
)

foreach ($file in $phpFiles) {
    $raw = Get-Content -LiteralPath $file.FullName -Raw -ErrorAction SilentlyContinue
    if ($null -eq $raw) {
        continue
    }

    $targetPath = Get-RelativeTargetPath -BasePath $repoRootResolved -FullPath $file.FullName

    foreach ($rule in $ruleSet) {
        if (Test-RawMatches -Content $raw -Regex $rule.regex) {
            Add-Finding -List $findings -Severity $rule.severity -Category $rule.category -Target $targetPath -Message $rule.message -Recommendation $rule.recommendation
        }
    }

    if ($file.Name -eq 'news_handler.php') {
        $hasPostedHtmlContent = Test-RawMatches -Content $raw -Regex '\$_POST\s*\[.*content.*\]'
        $writesNewsContent = Test-RawMatches -Content $raw -Regex '(INSERT\s+INTO|UPDATE)\s+PS_WebSite\.dbo\.Web_News' -Singleline
        $hasHtmlSanitizer = Test-RawMatches -Content $raw -Regex 'HTMLPurifier|strip_tags\s*\(|appSanitize[A-Za-z0-9_]*Html|appSanitize[A-Za-z0-9_]*Content'

        if ($hasPostedHtmlContent -and $writesNewsContent -and -not $hasHtmlSanitizer) {
            Add-Finding -List $findings -Severity 'HIGH' -Category 'stored_xss' -Target $targetPath -Message "HTML utente/news persistito senza sanitizzazione server-side rilevata." -Recommendation "Sanitizzare il contenuto rich text lato server con allowlist HTML prima del salvataggio."
        }
    }

    if ($file.Name -eq 'index.php' -and (Test-RawMatches -Content $raw -Regex 'echo\s+\$n_content\s*;')) {
        Add-Finding -List $findings -Severity 'MEDIUM' -Category 'stored_xss_sink' -Target $targetPath -Message "Rendering diretto di contenuto HTML news dal database rilevato." -Recommendation "Consentire solo HTML gia sanitizzato lato server o applicare sanitizzazione affidabile prima dell output."
    }

    if (Test-RawMatches -Content $raw -Regex '\bon(?:click|change|submit|mouseover|mouseout|load|error|input|focus|blur|keydown|keyup)\s*=') {
        Add-Finding -List $findings -Severity 'MEDIUM' -Category 'csp_inline_handler' -Target $targetPath -Message "Event handler inline rilevato (incompatibile con strict CSP)." -Recommendation "Spostare gli handler in file JS o addEventListener ed evitare attributi on-star inline."
    }

    if (Test-RawMatches -Content $raw -Regex '\bstyle\s*=') {
        Add-Finding -List $findings -Severity 'LOW' -Category 'csp_inline_style' -Target $targetPath -Message "Attributi style inline rilevati (gap rispetto a strict CSP)." -Recommendation "Migrare gli style inline verso classi CSS o blocchi style con nonce quando indispensabili."
    }

    if (Test-RawMatches -Content $raw -Regex 'javascript\s*:') {
        Add-Finding -List $findings -Severity 'MEDIUM' -Category 'csp_javascript_uri' -Target $targetPath -Message "javascript: URI rilevato." -Recommendation "Rimuovere javascript: URI e usare listener JS o href sicuri."
    }
}

# 3) Root-level page checks (security bootstrap + csrf for POST usage)
$rootPhpFiles = Get-ChildItem -Path $repoRootResolved -File -Filter '*.php' |
    Where-Object {
        $_.Name -notin @('db.php','db_pdo.php','db.example.php','db_pdo.example.php','security_bootstrap.php') -and
        -not (Test-IsExcludedPhpPath -FullPath $_.FullName -FileName $_.Name)
    }

foreach ($file in $rootPhpFiles) {
    $raw = Get-Content -LiteralPath $file.FullName -Raw -ErrorAction SilentlyContinue
    if ($null -eq $raw) {
        continue
    }

    $relative = $file.Name
    $sessionOptionalFiles = @('task_scheduler_worker_api.php')

    if (-not [regex]::IsMatch($raw, 'security_bootstrap\.php', [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)) {
        Add-Finding -List $findings -Severity 'MEDIUM' -Category 'bootstrap' -Target $relative -Message 'security_bootstrap.php non rilevato in root page.' -Recommendation 'Includere security_bootstrap.php in apertura file.'
    }

    if (-not [regex]::IsMatch($raw, 'appApplySecurityHeaders\s*\(', [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)) {
        Add-Finding -List $findings -Severity 'MEDIUM' -Category 'headers' -Target $relative -Message 'appApplySecurityHeaders() non rilevato.' -Recommendation 'Applicare security headers in apertura file.'
    }

    if ($relative -notin $sessionOptionalFiles -and -not [regex]::IsMatch($raw, 'appInitSession\s*\(', [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)) {
        Add-Finding -List $findings -Severity 'MEDIUM' -Category 'session' -Target $relative -Message 'appInitSession() non rilevato.' -Recommendation 'Inizializzare sessione sicura in apertura file.'
    }

    if ([regex]::IsMatch($raw, '\$_POST', [System.Text.RegularExpressions.RegexOptions]::IgnoreCase) -and
        -not [regex]::IsMatch($raw, 'appVerifyCsrfToken\s*\(', [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)) {
        Add-Finding -List $findings -Severity 'HIGH' -Category 'csrf' -Target $relative -Message 'Uso $_POST senza verifica CSRF rilevata.' -Recommendation 'Aggiungere appVerifyCsrfToken() prima di mutazioni POST.'
    }
}

# Build report
$stats = [ordered]@{
    high = @($findings | Where-Object { $_.severity -eq 'HIGH' }).Count
    medium = @($findings | Where-Object { $_.severity -eq 'MEDIUM' }).Count
    low = @($findings | Where-Object { $_.severity -eq 'LOW' }).Count
    total = $findings.Count
}

$report = [ordered]@{
    generatedAt = (Get-Date).ToString('o')
    repoRoot = $repoRootResolved
    scope = 'active_root_only'
    excluded = @('htdocs/htdocs')
    stats = $stats
    findings = $findings
}

$reportDir = Split-Path -Parent $ReportPath
if (-not (Test-Path -LiteralPath $reportDir)) {
    New-Item -ItemType Directory -Path $reportDir -Force | Out-Null
}

$report | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath $ReportPath -Encoding UTF8

Write-Host "[SECURITY-AUDIT] Report salvato: $ReportPath"
Write-Host "[SECURITY-AUDIT] High: $($stats.high) | Medium: $($stats.medium) | Low: $($stats.low) | Total: $($stats.total)"

$highFindings = @($findings | Where-Object { $_.severity -eq 'HIGH' })
if ($highFindings.Count -gt 0) {
    Write-Host '[SECURITY-AUDIT] HIGH findings:' -ForegroundColor Yellow
    foreach ($f in $highFindings) {
        Write-Host (" - [{0}] {1}: {2}" -f $f.category, $f.target, $f.message)
    }
}

if ($FailOnHigh -and $stats.high -gt 0) {
    exit 2
}

exit 0
