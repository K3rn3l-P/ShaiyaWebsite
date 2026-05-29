param(
    [Parameter(Mandatory = $true)]
    [string]$TargetUrl,
    [string[]]$AuditPaths = @('/', '/admin', '/account'),
    [string[]]$SensitivePaths = @(
        '/.git/HEAD',
        '/.env',
        '/.htaccess',
        '/config/',
        '/tools/',
        '/sql/',
        '/inc/',
        '/uploads/test.php',
        '/phpinfo.php',
        '/server-status',
        '/server-info'
    ),
    [string]$ReportPath,
    [string]$MarkdownPath,
    [int]$TimeoutSeconds = 20,
    [int]$ZapProxyPort = 8095,
    [string[]]$ZapCriticalPaths = @(),
    [string[]]$DirectoryListingPaths = @('/assets/', '/css/', '/js/', '/uploads/', '/uploads/news/'),
    [string[]]$BackupFileCandidates = @(
        '/index.php.bak',
        '/index.php~',
        '/admin.php.bak',
        '/admin.php~',
        '/db.php.bak',
        '/db.php~',
        '/security_bootstrap.php.bak',
        '/security_bootstrap.php~',
        '/.env.bak',
        '/config.php.bak'
    ),
    [switch]$EnableZAP,
    [switch]$EnableZAPActiveScan,
    [switch]$EnableZAPAjaxSpider,
    [switch]$EnableNikto,
    [switch]$FailOnHigh,
    [switch]$FailOnMedium
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

try {
    [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12
} catch {
}

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$repoRoot = (Resolve-Path (Join-Path $scriptRoot '..\..')).Path

if (-not $ReportPath) {
    $ReportPath = Join-Path $repoRoot 'config\logs\security_web_audit_report.json'
}

if (-not $MarkdownPath) {
    $MarkdownPath = Join-Path $repoRoot 'config\logs\security_web_audit_report.md'
}

$reportDir = Split-Path -Parent $ReportPath
$markdownDir = Split-Path -Parent $MarkdownPath
if ($reportDir) {
    New-Item -ItemType Directory -Path $reportDir -Force | Out-Null
}
if ($markdownDir) {
    New-Item -ItemType Directory -Path $markdownDir -Force | Out-Null
}

function Add-Finding {
    param(
        [System.Collections.Generic.List[object]]$List,
        [ValidateSet('LOW', 'MEDIUM', 'HIGH')][string]$Severity,
        [string]$Category,
        [string]$Target,
        [string]$Message,
        [string]$Recommendation,
        [string]$Reference = ''
    )

    $List.Add([PSCustomObject]@{
        severity = $Severity
        category = $Category
        target = $Target
        message = $Message
        recommendation = $Recommendation
        reference = $Reference
    })
}

function Get-HeaderMap {
    param($Headers)

    $map = @{}
    if ($null -eq $Headers) {
        return $map
    }

    if ($Headers -is [System.Collections.IDictionary]) {
        foreach ($entry in $Headers.GetEnumerator()) {
            if ($null -eq $entry.Key) {
                continue
            }

            $map[[string]$entry.Key.ToLowerInvariant()] = [string]$entry.Value
        }

        return $map
    }

    if ($Headers.PSObject.Properties.Name -contains 'AllKeys') {
        foreach ($name in $Headers.AllKeys) {
            if ($null -eq $name) {
                continue
            }

            $map[$name.ToLowerInvariant()] = [string]$Headers[$name]
        }
    }

    return $map
}

function Join-TargetUrl {
    param(
        [Uri]$BaseUri,
        [string]$RelativePath
    )

    if ([string]::IsNullOrWhiteSpace($RelativePath)) {
        return $BaseUri.AbsoluteUri
    }

    $cleanPath = if ($RelativePath.StartsWith('/')) { $RelativePath.Substring(1) } else { $RelativePath }
    return ([Uri]::new($BaseUri, $cleanPath)).AbsoluteUri
}

function Invoke-AuditRequest {
    param(
        [string]$Url,
        [string]$Method = 'GET',
        [int]$Timeout = 20,
        [hashtable]$Headers,
        [string]$Body,
        [string]$ContentType
    )

    try {
        $normalizedMethod = $Method.ToUpperInvariant()
        $requestParams = @{
            Uri = $Url
            Method = $normalizedMethod
            MaximumRedirection = 5
            TimeoutSec = $Timeout
            UseBasicParsing = $true
            ErrorAction = 'Stop'
        }

        if ($Headers) {
            $requestParams['Headers'] = $Headers
        }

        $methodSupportsBody = $normalizedMethod -in @('POST', 'PUT', 'PATCH', 'DELETE')
        if ($methodSupportsBody -and $PSBoundParameters.ContainsKey('Body') -and -not [string]::IsNullOrWhiteSpace($Body)) {
            $requestParams['Body'] = $Body
            if (-not [string]::IsNullOrWhiteSpace($ContentType)) {
                $requestParams['ContentType'] = $ContentType
            }
        }

        $response = Invoke-WebRequest @requestParams
        return [PSCustomObject]@{
            ok = $true
            statusCode = [int]$response.StatusCode
            finalUrl = [string]$response.BaseResponse.ResponseUri.AbsoluteUri
            headers = Get-HeaderMap -Headers $response.Headers
            rawHeaders = $response.Headers
            body = [string]$response.Content
            error = $null
        }
    } catch {
        $webResponse = $null
        if ($_.Exception -and $_.Exception.PSObject.Properties.Name -contains 'Response') {
            $webResponse = $_.Exception.Response
        }

        if ($null -ne $webResponse) {
            $statusCode = 0
            try {
                $statusCode = [int]$webResponse.StatusCode
            } catch {
            }

            $responseUri = $Url
            try {
                if ($webResponse.ResponseUri) {
                    $responseUri = [string]$webResponse.ResponseUri.AbsoluteUri
                }
            } catch {
            }

            return [PSCustomObject]@{
                ok = $false
                statusCode = $statusCode
                finalUrl = $responseUri
                headers = Get-HeaderMap -Headers $webResponse.Headers
                rawHeaders = $webResponse.Headers
                body = ''
                error = $_.Exception.Message
            }
        }

        return [PSCustomObject]@{
            ok = $false
            statusCode = 0
            finalUrl = $Url
            headers = @{}
            rawHeaders = $null
            body = ''
            error = $_.Exception.Message
        }
    }
}

function Get-TlsCertificateInfo {
    param([Uri]$Uri)

    if ($Uri.Scheme -ne 'https') {
        return $null
    }

    $port = if ($Uri.Port -gt 0) { $Uri.Port } else { 443 }
    $client = $null
    $sslStream = $null

    try {
        $client = New-Object System.Net.Sockets.TcpClient
        $client.Connect($Uri.Host, $port)
        $sslStream = New-Object System.Net.Security.SslStream($client.GetStream(), $false, { $true })
        $sslStream.AuthenticateAsClient($Uri.Host)
        $certificate = New-Object System.Security.Cryptography.X509Certificates.X509Certificate2($sslStream.RemoteCertificate)
        $expiresAt = $certificate.NotAfter
        return [PSCustomObject]@{
            subject = $certificate.Subject
            issuer = $certificate.Issuer
            expiresAt = $expiresAt.ToString('o')
            daysRemaining = [int][Math]::Floor(($expiresAt - (Get-Date)).TotalDays)
        }
    } catch {
        return [PSCustomObject]@{
            error = $_.Exception.Message
        }
    } finally {
        if ($sslStream) {
            $sslStream.Dispose()
        }
        if ($client) {
            $client.Dispose()
        }
    }
}

function Test-CommandAvailable {
    param([string]$Name)
    return $null -ne (Get-Command $Name -ErrorAction SilentlyContinue)
}

function Get-HeaderValues {
    param(
        $Headers,
        [string]$Name
    )

    if ($null -eq $Headers) {
        return @()
    }

    if ($Headers -is [System.Collections.IDictionary]) {
        foreach ($entry in $Headers.GetEnumerator()) {
            if ($null -ne $entry.Key -and [string]::Equals([string]$entry.Key, $Name, [System.StringComparison]::OrdinalIgnoreCase)) {
                if ($entry.Value -is [System.Array]) {
                    return @($entry.Value)
                }

                return @([string]$entry.Value)
            }
        }

        return @()
    }

    if ($Headers.PSObject.Properties.Name -contains 'GetValues') {
        $values = $Headers.GetValues($Name)
        if ($values) {
            return @($values)
        }
    }

    return @()
}

function Test-IsLikelyDirectoryListing {
    param([string]$Body)

    if ([string]::IsNullOrWhiteSpace($Body)) {
        return $false
    }

    return ($Body -match '(?i)<title>\s*index of|>index of /|parent directory|directory listing for')
}

function Invoke-ZapBaseline {
    param(
        [string]$Url,
        [string]$OutputDirectory
    )

    $zapScript = Join-Path $scriptRoot 'security-zap-audit-automation.ps1'
    if (-not (Test-Path -LiteralPath $zapScript)) {
        $zapScript = Join-Path $scriptRoot 'security-zap-audit.ps1'
    }
    if (-not (Test-Path -LiteralPath $zapScript)) {
        return [PSCustomObject]@{ name = 'zap'; status = 'skipped'; detail = 'security-zap-audit-automation.ps1 non trovato.' }
    }

    $jsonPath = Join-Path $OutputDirectory 'security_zap_audit_report.json'
    $mdPath = Join-Path $OutputDirectory 'security_zap_audit_report.md'

    $zapArgs = @(
        '-NoProfile',
        '-ExecutionPolicy', 'Bypass',
        '-File', $zapScript,
        '-TargetUrl', $Url,
        '-ReportPath', $jsonPath,
        '-MarkdownPath', $mdPath
    )

    if ($EnableZAPActiveScan) {
        $zapArgs += '-EnableActiveScan'
    }
    if ($EnableZAPAjaxSpider) {
        $zapArgs += '-EnableAjaxSpider'
    }
    if ($ZapProxyPort -gt 0) {
        $zapArgs += @('-ZapProxyPort', [string]$ZapProxyPort)
    }
    if ($FailOnHigh) {
        $zapArgs += '-FailOnHigh'
    }
    if ($FailOnMedium) {
        $zapArgs += '-FailOnMedium'
    }

    $output = & powershell.exe @zapArgs 2>&1
    $exitCode = $LASTEXITCODE
    $zapReport = $null
    if (Test-Path -LiteralPath $jsonPath) {
        try {
            $zapReport = Get-Content -LiteralPath $jsonPath -Raw | ConvertFrom-Json -ErrorAction Stop
        } catch {
            $zapReport = $null
        }
    }

    return [PSCustomObject]@{
        name = 'zap'
        status = if ($exitCode -eq 0) { 'completed' } else { 'failed' }
        exitCode = $exitCode
        detail = ($output | Out-String).Trim()
        artifacts = @($jsonPath, $mdPath)
        report = $zapReport
    }
}

function Invoke-NiktoScan {
    param(
        [string]$Url,
        [string]$OutputDirectory
    )

    $niktoCommand = Get-Command 'nikto.pl' -ErrorAction SilentlyContinue
    if ($null -eq $niktoCommand) {
        $niktoCommand = Get-Command 'nikto' -ErrorAction SilentlyContinue
    }

    if ($null -eq $niktoCommand) {
        return [PSCustomObject]@{ name = 'nikto'; status = 'skipped'; detail = 'nikto non disponibile sul sistema.' }
    }

    $jsonPath = Join-Path $OutputDirectory 'nikto_report.json'
    $output = & $niktoCommand.Source '-h' $Url '-Format' 'json' '-output' $jsonPath '-nointeractive' '-maxtime' '10m' 2>&1
    $exitCode = $LASTEXITCODE

    return [PSCustomObject]@{
        name = 'nikto'
        status = if ($exitCode -eq 0) { 'completed' } else { 'failed' }
        exitCode = $exitCode
        detail = ($output | Out-String).Trim()
        artifacts = @($jsonPath)
    }
}

$targetUri = [Uri]$TargetUrl
$findings = New-Object 'System.Collections.Generic.List[object]'
$requestSnapshots = New-Object 'System.Collections.Generic.List[object]'
$externalScanners = New-Object 'System.Collections.Generic.List[object]'

$tlsInfo = Get-TlsCertificateInfo -Uri $targetUri
if ($null -ne $tlsInfo) {
    if ($tlsInfo.PSObject.Properties.Name -contains 'error') {
        Add-Finding -List $findings -Severity 'MEDIUM' -Category 'tls' -Target $targetUri.Host -Message ('TLS handshake/certificate check fallito: {0}' -f $tlsInfo.error) -Recommendation 'Verificare certificato, catena, nome host e reachability TLS dal server.' -Reference 'OWASP WSTG / WSTG-CRYP'
    } elseif ($tlsInfo.daysRemaining -lt 15) {
        Add-Finding -List $findings -Severity 'HIGH' -Category 'tls' -Target $targetUri.Host -Message ('Certificato TLS in scadenza tra {0} giorni.' -f $tlsInfo.daysRemaining) -Recommendation 'Rinnovare il certificato prima della scadenza e verificare il deploy.' -Reference 'OWASP WSTG / WSTG-CRYP-01'
    } elseif ($tlsInfo.daysRemaining -lt 30) {
        Add-Finding -List $findings -Severity 'MEDIUM' -Category 'tls' -Target $targetUri.Host -Message ('Certificato TLS in scadenza tra {0} giorni.' -f $tlsInfo.daysRemaining) -Recommendation 'Pianificare il rinnovo del certificato e monitorare la scadenza.' -Reference 'OWASP WSTG / WSTG-CRYP-01'
    }
}

foreach ($path in $AuditPaths) {
    $url = Join-TargetUrl -BaseUri $targetUri -RelativePath $path
    $snapshot = Invoke-AuditRequest -Url $url -Timeout $TimeoutSeconds
    $requestSnapshots.Add([PSCustomObject]@{
        url = $url
        statusCode = $snapshot.statusCode
        finalUrl = $snapshot.finalUrl
        error = $snapshot.error
        headers = $snapshot.headers
    })

    if ($snapshot.statusCode -eq 0) {
        $isProbeError = $snapshot.error -match 'Cannot send a content-body|verb-type'
        if (-not $isProbeError) {
            Add-Finding -List $findings -Severity 'HIGH' -Category 'availability' -Target $url -Message ('Richiesta fallita: {0}' -f $snapshot.error) -Recommendation 'Verificare DNS, TLS, Apache e raggiungibilita del target dal server.' -Reference 'OWASP WSTG / WSTG-INFO'
        }
        continue
    }

    $requiredHeaders = @(
        @{ name = 'content-security-policy'; severity = 'MEDIUM'; recommendation = 'Impostare una CSP restrittiva; per rollout graduale usare anche Report-Only.' },
        @{ name = 'x-frame-options'; severity = 'MEDIUM'; recommendation = 'Impostare X-Frame-Options: DENY o usare frame-ancestors in CSP.' },
        @{ name = 'x-content-type-options'; severity = 'MEDIUM'; recommendation = 'Impostare X-Content-Type-Options: nosniff.' },
        @{ name = 'referrer-policy'; severity = 'LOW'; recommendation = 'Impostare Referrer-Policy coerente con la privacy desiderata.' },
        @{ name = 'permissions-policy'; severity = 'LOW'; recommendation = 'Impostare Permissions-Policy limitando feature non usate.' }
    )

    foreach ($requiredHeader in $requiredHeaders) {
        if (-not $snapshot.headers.ContainsKey($requiredHeader.name)) {
            Add-Finding -List $findings -Severity $requiredHeader.severity -Category 'headers' -Target $url -Message ('Header di sicurezza mancante: {0}' -f $requiredHeader.name) -Recommendation $requiredHeader.recommendation -Reference 'OWASP WSTG / WSTG-CONF'
        }
    }

    if ($targetUri.Scheme -eq 'https' -and -not $snapshot.headers.ContainsKey('strict-transport-security')) {
        Add-Finding -List $findings -Severity 'MEDIUM' -Category 'headers' -Target $url -Message 'Header Strict-Transport-Security mancante su endpoint HTTPS.' -Recommendation 'Impostare HSTS lato applicazione o Apache solo su HTTPS.' -Reference 'OWASP WSTG / WSTG-CONF'
    }

    foreach ($leakyHeader in @('server', 'x-powered-by', 'x-aspnet-version')) {
        if ($snapshot.headers.ContainsKey($leakyHeader)) {
            Add-Finding -List $findings -Severity 'LOW' -Category 'fingerprint' -Target $url -Message ('Header informativo esposto: {0}={1}' -f $leakyHeader, $snapshot.headers[$leakyHeader]) -Recommendation 'Ridurre il banner disclosure lato Apache/PHP o reverse proxy.' -Reference 'OWASP WSTG-v42-INFO-02'
        }
    }

    if ($snapshot.rawHeaders) {
        $setCookies = Get-HeaderValues -Headers $snapshot.rawHeaders -Name 'Set-Cookie'
        if ($setCookies) {
            foreach ($cookieHeader in $setCookies) {
                if ($cookieHeader -notmatch '(?i)httponly') {
                    Add-Finding -List $findings -Severity 'MEDIUM' -Category 'cookies' -Target $url -Message ('Cookie senza HttpOnly: {0}' -f $cookieHeader) -Recommendation 'Aggiungere HttpOnly ai cookie di sessione e auth.' -Reference 'OWASP WSTG-v42-SESS-02'
                }
                if ($targetUri.Scheme -eq 'https' -and $cookieHeader -notmatch '(?i)secure') {
                    Add-Finding -List $findings -Severity 'MEDIUM' -Category 'cookies' -Target $url -Message ('Cookie senza Secure su HTTPS: {0}' -f $cookieHeader) -Recommendation 'Aggiungere Secure ai cookie emessi su connessioni HTTPS.' -Reference 'OWASP WSTG-v42-SESS-02'
                }
                if ($cookieHeader -notmatch '(?i)samesite') {
                    Add-Finding -List $findings -Severity 'LOW' -Category 'cookies' -Target $url -Message ('Cookie senza attributo SameSite: {0}' -f $cookieHeader) -Recommendation 'Impostare SameSite=Lax o Strict sui cookie sensibili.' -Reference 'OWASP WSTG-v42-SESS-02'
                }
            }
        }
    }

    if ($path -in @('/admin', '/account') -and -not $snapshot.headers.ContainsKey('cache-control')) {
        Add-Finding -List $findings -Severity 'LOW' -Category 'cache' -Target $url -Message 'Endpoint sensibile senza Cache-Control esplicito.' -Recommendation 'Impostare Cache-Control: no-store su pagine autenticate/admin.' -Reference 'OWASP WSTG-v42-AUTHZ'
    }
}

$optionsProbe = Invoke-AuditRequest -Url $TargetUrl -Method 'OPTIONS' -Timeout $TimeoutSeconds
if ($optionsProbe.headers.ContainsKey('allow')) {
    foreach ($methodName in @('TRACE', 'PUT', 'DELETE', 'CONNECT', 'PATCH')) {
        if ($optionsProbe.headers['allow'] -match ('(?i)\b{0}\b' -f $methodName)) {
            Add-Finding -List $findings -Severity 'MEDIUM' -Category 'http_methods' -Target $TargetUrl -Message ('Metodo HTTP potenzialmente rischioso esposto da Allow: {0}' -f $methodName) -Recommendation 'Disabilitare metodi HTTP non usati in Apache/app gateway.' -Reference 'OWASP WSTG-CONF-06'
        }
    }
}

$methodProbeTargets = @('/', '/admin', '/account', '/lucky_case_api.php') | ForEach-Object { Join-TargetUrl -BaseUri $targetUri -RelativePath $_ }
foreach ($probeUrl in $methodProbeTargets) {
    foreach ($probeMethod in @('TRACE', 'PUT', 'DELETE', 'CONNECT', 'PATCH')) {
        $probe = Invoke-AuditRequest -Url $probeUrl -Method $probeMethod -Timeout $TimeoutSeconds
        $probeBody = ''
        if ($null -ne $probe -and $null -ne $probe.body) {
            $probeBody = [string]$probe.body
        }
        $looksRejectedInBody = $probeBody -match '(?i)method not allowed|not supported|405\b|invalid method'

        if ($probe.statusCode -ge 200 -and $probe.statusCode -lt 400 -and -not $looksRejectedInBody) {
            $severity = if ($probeMethod -eq 'TRACE') { 'HIGH' } else { 'MEDIUM' }
            Add-Finding -List $findings -Severity $severity -Category 'http_methods' -Target $probeUrl -Message ('Metodo HTTP {0} accettato con status {1}' -f $probeMethod, $probe.statusCode) -Recommendation 'Limitare i metodi HTTP consentiti per endpoint; bloccare TRACE e metodi non necessari.' -Reference 'OWASP WSTG-CONF-06'
        }
    }

    $overrideProbe = Invoke-AuditRequest -Url $probeUrl -Method 'POST' -Timeout $TimeoutSeconds -Headers @{ 'X-HTTP-Method-Override' = 'PUT' } -Body 'wstg_override_probe=1' -ContentType 'application/x-www-form-urlencoded'
    $overrideBody = ''
    if ($null -ne $overrideProbe -and $null -ne $overrideProbe.body) {
        $overrideBody = [string]$overrideProbe.body
    }
    $overrideRejectedInBody = $overrideBody -match '(?i)method not allowed|not supported|405\b|invalid method|override not allowed'

    if ($overrideProbe.statusCode -ge 200 -and $overrideProbe.statusCode -lt 400 -and -not $overrideRejectedInBody) {
        Add-Finding -List $findings -Severity 'MEDIUM' -Category 'http_methods' -Target $probeUrl -Message ('Method override potenzialmente accettato (X-HTTP-Method-Override) con status {0}' -f $overrideProbe.statusCode) -Recommendation 'Disabilitare override dei metodi o limitarlo a endpoint strettamente necessari.' -Reference 'OWASP WSTG-CONF-06'
    }
}

foreach ($listingPath in $DirectoryListingPaths) {
    $listingUrl = Join-TargetUrl -BaseUri $targetUri -RelativePath $listingPath
    $listingProbe = Invoke-AuditRequest -Url $listingUrl -Timeout $TimeoutSeconds
    if ($listingProbe.statusCode -eq 200 -and (Test-IsLikelyDirectoryListing -Body $listingProbe.body)) {
        Add-Finding -List $findings -Severity 'MEDIUM' -Category 'directory_listing' -Target $listingUrl -Message ('Directory listing rilevato su percorso pubblico: {0}' -f $listingPath) -Recommendation 'Disabilitare indexing directory (`Options -Indexes`) e servire solo file esplicitamente pubblici.' -Reference 'OWASP WSTG-CONF-04'
    }
}

foreach ($backupCandidate in $BackupFileCandidates) {
    $backupUrl = Join-TargetUrl -BaseUri $targetUri -RelativePath $backupCandidate
    $backupProbe = Invoke-AuditRequest -Url $backupUrl -Timeout $TimeoutSeconds
    if ($backupProbe.statusCode -eq 200) {
        Add-Finding -List $findings -Severity 'HIGH' -Category 'backup_exposure' -Target $backupUrl -Message ('Backup/unreferenced file esposto: {0}' -f $backupCandidate) -Recommendation 'Rimuovere file di backup dal document root e bloccare pattern backup via web server.' -Reference 'OWASP WSTG-CONF-04'
    }
}

if ($targetUri.Scheme -eq 'https') {
    $httpUriBuilder = New-Object System.UriBuilder($targetUri)
    $httpUriBuilder.Scheme = 'http'
    $httpUriBuilder.Port = 80
    $httpProbe = Invoke-AuditRequest -Url $httpUriBuilder.Uri.AbsoluteUri -Timeout $TimeoutSeconds
    if ($httpProbe.statusCode -ne 0 -and -not ([string]$httpProbe.finalUrl).StartsWith('https://', [System.StringComparison]::OrdinalIgnoreCase)) {
        Add-Finding -List $findings -Severity 'MEDIUM' -Category 'transport' -Target $httpUriBuilder.Uri.AbsoluteUri -Message 'HTTP non forza redirect canonico verso HTTPS.' -Recommendation 'Applicare redirect 301/302 da HTTP a HTTPS a livello Apache/vhost.' -Reference 'OWASP WSTG-v42-CONF-07'
    }
}

foreach ($path in $SensitivePaths) {
    $url = Join-TargetUrl -BaseUri $targetUri -RelativePath $path
    $probe = Invoke-AuditRequest -Url $url -Timeout $TimeoutSeconds

    if ($probe.statusCode -eq 200) {
        $severity = if ($path -match '^/\.git|^/\.env|^/phpinfo\.php|^/server-(status|info)') { 'HIGH' } else { 'MEDIUM' }
        Add-Finding -List $findings -Severity $severity -Category 'exposure' -Target $url -Message ('Risorsa sensibile raggiungibile con HTTP 200: {0}' -f $path) -Recommendation 'Bloccare l accesso via Apache/.htaccess o rimuovere la risorsa dal document root.' -Reference 'OWASP WSTG-v42-CONF-05'
    }
}

$artifactRoot = Split-Path -Parent $ReportPath
if ($EnableZAP) {
    $zapResult = Invoke-ZapBaseline -Url $TargetUrl -OutputDirectory $artifactRoot
    $externalScanners.Add($zapResult)
    if ($zapResult.report -and $zapResult.report.findings) {
        foreach ($zapFinding in $zapResult.report.findings) {
            Add-Finding -List $findings -Severity ([string]$zapFinding.severity).ToUpperInvariant() -Category ('zap/{0}' -f $zapFinding.category) -Target ([string]$zapFinding.target) -Message ([string]$zapFinding.message) -Recommendation ([string]$zapFinding.recommendation) -Reference ([string]$zapFinding.reference)
        }
    }
}
if ($EnableNikto) {
    $externalScanners.Add((Invoke-NiktoScan -Url $TargetUrl -OutputDirectory $artifactRoot))
}

$stats = [ordered]@{
    high = @($findings | Where-Object { $_.severity -eq 'HIGH' }).Count
    medium = @($findings | Where-Object { $_.severity -eq 'MEDIUM' }).Count
    low = @($findings | Where-Object { $_.severity -eq 'LOW' }).Count
    total = $findings.Count
}

$report = [ordered]@{
    generatedAt = (Get-Date).ToString('o')
    targetUrl = $TargetUrl
    host = $targetUri.Host
    tls = $tlsInfo
    stats = $stats
    references = @(
        'OWASP WSTG (stable / v4.2 reference model)',
        'OWASP ZAP Baseline Scan (safe passive scan for production-like checks)',
        'Nikto 2.6.0 for optional server misconfiguration probing'
    )
    requests = $requestSnapshots
    externalScanners = $externalScanners
    findings = $findings
}

$resolvedReportPath = $ExecutionContext.SessionState.Path.GetUnresolvedProviderPathFromPSPath($ReportPath)
$resolvedMarkdownPath = $ExecutionContext.SessionState.Path.GetUnresolvedProviderPathFromPSPath($MarkdownPath)

$report | ConvertTo-Json -Depth 8 | Set-Content -Path $resolvedReportPath -Encoding UTF8

$markdown = New-Object System.Collections.Generic.List[string]
$markdown.Add('# Security Web Audit Report')
$markdown.Add('')
$markdown.Add(('Generated: {0}' -f $report.generatedAt))
$markdown.Add(('Target: {0}' -f $TargetUrl))
$markdown.Add('')
$markdown.Add(('High: {0} | Medium: {1} | Low: {2} | Total: {3}' -f $stats.high, $stats.medium, $stats.low, $stats.total))
$markdown.Add('')

if ($tlsInfo) {
    $markdown.Add('## TLS')
    if ($tlsInfo.PSObject.Properties.Name -contains 'error') {
        $markdown.Add(('- Error: {0}' -f $tlsInfo.error))
    } else {
        $markdown.Add(('- Subject: {0}' -f $tlsInfo.subject))
        $markdown.Add(('- Issuer: {0}' -f $tlsInfo.issuer))
        $markdown.Add(('- Expires: {0}' -f $tlsInfo.expiresAt))
        $markdown.Add(('- Days remaining: {0}' -f $tlsInfo.daysRemaining))
    }
    $markdown.Add('')
}

if ($externalScanners.Count -gt 0) {
    $markdown.Add('## External Scanners')
    foreach ($scanner in $externalScanners) {
        $markdown.Add(('- {0}: {1}' -f $scanner.name, $scanner.status))
        if ($scanner.PSObject.Properties.Name -contains 'detail' -and $scanner.detail) {
            $markdown.Add(('  Detail: {0}' -f $scanner.detail))
        }
    }
    $markdown.Add('')
}

$markdown.Add('## Findings')
if ($findings.Count -eq 0) {
    $markdown.Add('- No findings detected by the scheduled web audit.')
} else {
    foreach ($finding in $findings) {
        $markdown.Add(('- [{0}] {1} :: {2}' -f $finding.severity, $finding.target, $finding.message))
        $markdown.Add(('  Recommendation: {0}' -f $finding.recommendation))
        if ($finding.reference) {
            $markdown.Add(('  Reference: {0}' -f $finding.reference))
        }
    }
}

$markdown | Set-Content -Path $resolvedMarkdownPath -Encoding UTF8

Write-Host ('Security web audit completed for {0}' -f $TargetUrl)
Write-Host ('Report JSON: {0}' -f $resolvedReportPath)
Write-Host ('Report Markdown: {0}' -f $resolvedMarkdownPath)
Write-Host ('Findings -> High: {0}, Medium: {1}, Low: {2}, Total: {3}' -f $stats.high, $stats.medium, $stats.low, $stats.total)

if ($FailOnHigh -and $stats.high -gt 0) {
    exit 1
}

if ($FailOnMedium -and (($stats.high + $stats.medium) -gt 0)) {
    exit 1
}

exit 0