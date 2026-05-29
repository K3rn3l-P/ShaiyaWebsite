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

function Test-CspWeaknesses {
    param([string]$Csp)
    $issues = [System.Collections.Generic.List[hashtable]]::new()
    if ($Csp -match "script-src[^;]*'unsafe-inline'") { $issues.Add(@{severity = 'HIGH'; msg = "script-src contiene 'unsafe-inline' (disabilita nonce/hash XSS protection)" }) }
    if ($Csp -match "script-src[^;]*'unsafe-eval'") { $issues.Add(@{severity = 'HIGH'; msg = "script-src contiene 'unsafe-eval' (permette eval/Function/setTimeout con stringa)" }) }
    if ($Csp -match 'script-src[^;]* \*(\s|;|$)') { $issues.Add(@{severity = 'HIGH'; msg = "script-src contiene wildcard * (qualsiasi origine per script)" }) }
    if ($Csp -match "script-src[^;]*\bdata:") { $issues.Add(@{severity = 'HIGH'; msg = "script-src contiene 'data:' (script via data URI)" }) }
    if ($Csp -match "style-src(?!-)[^;]*'unsafe-inline'") { $issues.Add(@{severity = 'MEDIUM'; msg = "style-src contiene 'unsafe-inline' (CSS injection/data exfil possibile; style-src-attr e' accettabile come fallback attributi)" }) }
    if ($Csp -notmatch 'frame-ancestors') { $issues.Add(@{severity = 'MEDIUM'; msg = "frame-ancestors mancante nella CSP (clickjacking se X-Frame-Options assente)" }) }
    if ($Csp -match "img-src[^;]*\bdata:\b" -and $Csp -match "img-src[^;]*\bhttps:\b[^;]*;") { $issues.Add(@{severity = 'LOW'; msg = "img-src permissiva: data: + https: wildcard (CSS exfil via img-src)" }) }
    return $issues
}

function Test-BodyForSqlErrors {
    param([string]$Body)
    $pats = @(
        'You have an error in your SQL syntax',
        'Unclosed quotation mark after the character string',
        'SQLSTATE\[',
        'PDOException',
        'SQL syntax.*MySQL',
        'Warning.*mysql_',
        "Incorrect syntax near '",
        'Microsoft.*ODBC.*SQL Server',
        'ORA-\d{5}',
        'DB Error:',
        'Query failed',
        'mysql_num_rows\(\)',
        'pg_query\(\)'
    )
    foreach ($p in $pats) { if ($Body -match $p) { return $p } }
    return $null
}

function Test-BodyForPhpErrors {
    param([string]$Body)
    $pats = @(
        'Warning:.*on line \d+',
        'Fatal error:.*on line \d+',
        'Parse error:.*on line \d+',
        'Notice:.*on line \d+',
        'Deprecated:.*on line \d+',
        'Stack trace:',
        'in (?:/[a-zA-Z]|C:\\\\).*\.php on line'
    )
    foreach ($p in $pats) { if ($Body -match $p) { return $p } }
    return $null
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

# =============================================================
# 7) CSP Strength Analysis (parse from already-fetched snapshots)
# =============================================================
Write-Host '[PENTEST] CSP strength analysis...'
$cspSnapshotsSeen = [System.Collections.Generic.HashSet[string]]::new()
foreach ($snap in $requestSnapshots) {
    if (-not $snap.headers.ContainsKey('content-security-policy')) { continue }
    $cspVal = $snap.headers['content-security-policy']
    if (-not $cspSnapshotsSeen.Add($snap.url)) { continue }

    $cspIssues = Test-CspWeaknesses -Csp $cspVal
    foreach ($issue in $cspIssues) {
        Add-Finding -List $findings -Severity $issue.severity -Category 'csp' -Target $snap.url `
            -Message ('CSP: {0}' -f $issue.msg) `
            -Recommendation 'Revisare la Content-Security-Policy: usare nonce/hash per script e style, rimuovere direttive unsafe-*.' `
            -Reference 'OWASP WSTG-CONF-12 / CSP Level 3'
    }

    # Duplicate header detection (value contains comma-separated repeated segment)
    foreach ($hdrName in @('content-security-policy', 'strict-transport-security', 'x-frame-options', 'x-content-type-options', 'referrer-policy', 'permissions-policy')) {
        if (-not $snap.headers.ContainsKey($hdrName)) { continue }
        $hdrVal = $snap.headers[$hdrName]
        $parts = $hdrVal -split ',\s*'
        if ($parts.Count -ge 2 -and $parts[0].Trim() -ieq $parts[-1].Trim()) {
            Add-Finding -List $findings -Severity 'MEDIUM' -Category 'header_duplicate' -Target $snap.url `
                -Message ("Header '$hdrName' inviato due volte (valore duplicato rilevato). Indica conflitto tra Apache e applicazione PHP.") `
                -Recommendation "Rimuovere la direttiva Header corrispondente da httpd.conf/vhost se l'applicazione PHP la imposta gia. Evitare duplicati." `
                -Reference 'OWASP WSTG-CONF / RFC 7230 s3.2.2'
        }
    }
}

# =============================================================
# 8) HSTS Strength + Deprecated Header Checks
# =============================================================
Write-Host '[PENTEST] HSTS + deprecated headers check...'
$hstsSeen = [System.Collections.Generic.HashSet[string]]::new()
foreach ($snap in $requestSnapshots) {
    if (-not $hstsSeen.Add($snap.url)) { continue }
    $hdrs = $snap.headers

    if ($hdrs.ContainsKey('strict-transport-security')) {
        $hstsVal = $hdrs['strict-transport-security']
        if ($hstsVal -notmatch '\bpreload\b') {
            Add-Finding -List $findings -Severity 'LOW' -Category 'hsts' -Target $snap.url `
                -Message "HSTS: direttiva 'preload' mancante. Il dominio non puo essere incluso nella HSTS preload list dei browser." `
                -Recommendation "Aggiungere 'preload' all'header HSTS e registrare il dominio su https://hstspreload.org (testare prima con includeSubDomains)." `
                -Reference 'MDN HSTS / OWASP WSTG-CONF'
        }
        $maxAgeMatch = [regex]::Match($hstsVal, 'max-age=(\d+)')
        if ($maxAgeMatch.Success) {
            $maxAgeSec = [int]$maxAgeMatch.Groups[1].Value
            if ($maxAgeSec -lt 31536000) {
                Add-Finding -List $findings -Severity 'MEDIUM' -Category 'hsts' -Target $snap.url `
                    -Message ("HSTS max-age={0}s e inferiore a 1 anno (31536000s). Gli attacchi di downgrade MITM sono possibili." -f $maxAgeSec) `
                    -Recommendation "Aumentare HSTS max-age ad almeno 31536000 (1 anno)." `
                    -Reference 'OWASP WSTG-CONF'
            }
        }
    }

    if ($hdrs.ContainsKey('x-xss-protection')) {
        $xxpVal = $hdrs['x-xss-protection']
        $xxpFirst = $xxpVal.Split(',')[0].Trim()
        if ($xxpFirst -ne '0') {
            Add-Finding -List $findings -Severity 'LOW' -Category 'headers' -Target $snap.url `
                -Message ("X-XSS-Protection: '{0}' e deprecato nei browser moderni e puo creare vulnerabilita in IE (XSS Auditor bypass). La CSP e la protezione corretta." -f $xxpVal.Split(',')[0].Trim()) `
                -Recommendation "Impostare X-XSS-Protection: 0 e affidarsi esclusivamente alla Content-Security-Policy per la protezione XSS." `
                -Reference 'MDN X-XSS-Protection / OWASP'
        }
    }
}

# =============================================================
# 9) CORS Misconfiguration Active Probe
# =============================================================
Write-Host '[PENTEST] CORS misconfiguration probe...'
$corsProbeOrigins = @('https://evil-attacker.com', 'null')
$corsTestPaths = ($AuditPaths + @('/lucky_case_api.php', '/task_scheduler_worker_api.php')) | Select-Object -Unique
$corsSeen = [System.Collections.Generic.HashSet[string]]::new()

foreach ($corsPath in $corsTestPaths) {
    $corsUrl = Join-TargetUrl -BaseUri $targetUri -RelativePath $corsPath
    if (-not $corsSeen.Add($corsUrl)) { continue }

    foreach ($originVal in $corsProbeOrigins) {
        $corsResp = Invoke-AuditRequest -Url $corsUrl -Method 'GET' -Timeout $TimeoutSeconds -Headers @{ 'Origin' = $originVal }
        if (-not $corsResp.headers.ContainsKey('access-control-allow-origin')) { continue }

        $acao = $corsResp.headers['access-control-allow-origin'].Split(',')[0].Trim()
        $acac = ''
        if ($corsResp.headers.ContainsKey('access-control-allow-credentials')) {
            $acac = $corsResp.headers['access-control-allow-credentials'].Split(',')[0].Trim()
        }

        if ($acao -ieq $originVal -and $acac -ieq 'true') {
            Add-Finding -List $findings -Severity 'HIGH' -Category 'cors' -Target $corsUrl `
                -Message ("CORS CRITICO: origine arbitraria '$originVal' riflessa con Allow-Credentials:true. Un attaccante cross-origin puo leggere cookies/sessioni autenticate.") `
                -Recommendation "Non riflettere il valore Origin dalla request. Usare whitelist esplicita. Non combinare con credentials=true se l'origine non e fidata." `
                -Reference 'OWASP WSTG-CONF-07 / PortSwigger CORS labs'
        } elseif ($acao -ieq $originVal) {
            Add-Finding -List $findings -Severity 'MEDIUM' -Category 'cors' -Target $corsUrl `
                -Message ("CORS: origine arbitraria '$originVal' riflessa in Access-Control-Allow-Origin (senza credentials). Possibile data leakage per risorse non autenticate.") `
                -Recommendation "Limitare CORS a origini esplicite in whitelist. Non usare riflesso dinamico dell'header Origin." `
                -Reference 'OWASP WSTG-CONF-07'
        } elseif ($acao -eq '*' -and $acac -ieq 'true') {
            Add-Finding -List $findings -Severity 'HIGH' -Category 'cors' -Target $corsUrl `
                -Message "CORS: Access-Control-Allow-Origin: * combinato con Allow-Credentials: true (spec violation ma pericoloso su client permissivi)." `
                -Recommendation "Non usare wildcard con credentials. Specificare origini esplicite." `
                -Reference 'OWASP WSTG-CONF-07'
        }
    }
}

# =============================================================
# 10) Error Page Information Disclosure
# =============================================================
Write-Host '[PENTEST] Error page disclosure check...'
$notFoundUrl = Join-TargetUrl -BaseUri $targetUri -RelativePath '/wstg-probe-nonexistent-xyz9871'
$errProbe = Invoke-AuditRequest -Url $notFoundUrl -Timeout $TimeoutSeconds
if ($errProbe.statusCode -eq 404) {
    $errBody = [string]$errProbe.body
    $apacheVerMatch = [regex]::Match($errBody, 'Apache/[\d\.]+[^\s<"]*')
    if ($apacheVerMatch.Success) {
        Add-Finding -List $findings -Severity 'MEDIUM' -Category 'fingerprint' -Target $targetUri.AbsoluteUri `
            -Message ('Error page 404 espone versione Apache completa: {0}. Facilita targeting exploit specifici.' -f $apacheVerMatch.Value) `
            -Recommendation 'Impostare ServerTokens Prod e ServerSignature Off in httpd.conf. Usare pagine 404 custom (ErrorDocument).' `
            -Reference 'OWASP WSTG-v42-INFO-02'
    }
    $phpVerMatch = [regex]::Match($errBody, 'PHP/[\d\.]+[^\s<"]*')
    if ($phpVerMatch.Success) {
        Add-Finding -List $findings -Severity 'MEDIUM' -Category 'fingerprint' -Target $targetUri.AbsoluteUri `
            -Message ('Error page espone versione PHP: {0}.' -f $phpVerMatch.Value) `
            -Recommendation 'Impostare expose_php=Off in php.ini e pagine di errore custom.' `
            -Reference 'OWASP WSTG-v42-INFO-02'
    }
    if ($errBody -match '(?i)server at .+\.(net|com|org|local) port \d+') {
        Add-Finding -List $findings -Severity 'LOW' -Category 'fingerprint' -Target $targetUri.AbsoluteUri `
            -Message 'Error page 404 espone nome server Apache (ServerSignature attiva).' `
            -Recommendation 'Aggiungere ServerSignature Off e usare ErrorDocument custom per tutte le pagine di errore.' `
            -Reference 'OWASP WSTG-v42-INFO-02'
    }
}

# PHP error disclosure via parameterized probes
$phpErrProbes = @(
    "/rankings.php?page=%27%22%3E",
    "/drops.php?category=%3C%3Fphp",
    "/bosses.php?id=%27%20OR%20%271",
    "/guilds.php?name=%27--"
)
foreach ($pep in $phpErrProbes) {
    $pepUrl = Join-TargetUrl -BaseUri $targetUri -RelativePath $pep
    $pepResp = Invoke-AuditRequest -Url $pepUrl -Timeout $TimeoutSeconds
    if ($pepResp.statusCode -in @(200, 500)) {
        $body = [string]$pepResp.body
        $phpErrMatch = Test-BodyForPhpErrors -Body $body
        if ($null -ne $phpErrMatch) {
            Add-Finding -List $findings -Severity 'HIGH' -Category 'error_disclosure' -Target $pepUrl `
                -Message ("PHP error disclosure visibile nella risposta HTTP (pattern: '$phpErrMatch'). Espone path filesystem e logica interna.") `
                -Recommendation 'Impostare display_errors=Off e log_errors=On in php.ini. Usare handler di errori custom senza output verso client.' `
                -Reference 'OWASP WSTG-v42-INFO-05 / CWE-209'
        }
    }
}

# =============================================================
# 11) SQL Injection Active Probe (Error-Based)
# =============================================================
Write-Host '[PENTEST] SQL injection error-based probe...'
$sqliProbeUrls = @(
    "/rankings.php?faction=%27--",
    "/drops.php?id=1%27",
    "/bosses.php?id=1%27+OR+%271%27%3D%271",
    "/guilds.php?id=1%27--",
    "/characters.php?id=1%27+AND+%271%27%3D%271",
    "/lucky_case.php?roll=1%27",
    "/index.php?username=admin%27--"
)
foreach ($sqliUrl in $sqliProbeUrls) {
    $sqliTarget = Join-TargetUrl -BaseUri $targetUri -RelativePath $sqliUrl
    $sqliResp = Invoke-AuditRequest -Url $sqliTarget -Timeout $TimeoutSeconds
    if ($sqliResp.statusCode -in @(200, 500, 503)) {
        $body = [string]$sqliResp.body
        $sqlErrMatch = Test-BodyForSqlErrors -Body $body
        if ($null -ne $sqlErrMatch) {
            Add-Finding -List $findings -Severity 'HIGH' -Category 'sqli' -Target $sqliTarget `
                -Message ("SQL Injection error-based: errore database esposto nella risposta (pattern: '$sqlErrMatch'). Vettore di estrazione dati.") `
                -Recommendation 'Usare PDO prepared statements ovunque. Rimuovere messaggi SQL dalle risposte HTTP (display_errors=Off).' `
                -Reference 'OWASP WSTG-v42-INPV-05 / A03:2021 Injection'
        }
    }
}

# =============================================================
# 12) Reflected XSS Active Probe
# =============================================================
Write-Host '[PENTEST] Reflected XSS probe...'
$xssToken = 'xss' + (Get-Random -Maximum 99999)
$xssPayload = "$xssToken<script>xss</script>"
$xssPayloadEncoded = [System.Uri]::EscapeDataString($xssPayload)

$xssTestTargets = @(
    @{ base = '/'; params = @('search', 'q', 'message', 'error', 'status') },
    @{ base = '/rankings.php'; params = @('faction', 'class', 'q', 'search') },
    @{ base = '/drops.php'; params = @('category', 'q', 'name') },
    @{ base = '/guilds.php'; params = @('name', 'q', 'search') },
    @{ base = '/bosses.php'; params = @('name', 'q') },
    @{ base = '/register.php'; params = @('message', 'ref') }
)
foreach ($xssTarget in $xssTestTargets) {
    foreach ($xssParam in $xssTarget.params) {
        $xssUrl = Join-TargetUrl -BaseUri $targetUri -RelativePath ('{0}?{1}={2}' -f $xssTarget.base, $xssParam, $xssPayloadEncoded)
        $xssResp = Invoke-AuditRequest -Url $xssUrl -Timeout $TimeoutSeconds
        if ($xssResp.ok -and $xssResp.statusCode -eq 200) {
            $body = [string]$xssResp.body
            # Check for unescaped reflection (raw < not &lt;)
            if ($body -match ([regex]::Escape("$xssToken<"))) {
                Add-Finding -List $findings -Severity 'HIGH' -Category 'xss_reflected' -Target $xssUrl `
                    -Message ("Reflected XSS confermato: payload non escaped nel parametro '$xssParam' di {0}. Exploit diretto possibile." -f $xssTarget.base) `
                    -Recommendation 'Applicare htmlspecialchars(ENT_QUOTES|ENT_HTML5) su tutti i parametri prima dell output HTML.' `
                    -Reference 'OWASP WSTG-v42-INPV-01 / CWE-79'
            }
        }
    }
}

# =============================================================
# 13) Open Redirect Active Probe
# =============================================================
Write-Host '[PENTEST] Open redirect probe...'
$openRedirectTarget = 'https://evil-attacker.com'
$openRedirectEncoded = [System.Uri]::EscapeDataString($openRedirectTarget)
$redirectParams = @('redirect', 'url', 'next', 'return', 'returnUrl', 'goto', 'dest', 'to', 'redir', 'r', 'forward', 'location', 'callback', 'continue', 'target')
$redirectTestBases = @('/', '/index.php', '/account.php', '/register.php', '/login.php')
foreach ($redirectBase in $redirectTestBases) {
    foreach ($rParam in $redirectParams) {
        $rUrl = Join-TargetUrl -BaseUri $targetUri -RelativePath ('{0}?{1}={2}' -f $redirectBase, $rParam, $openRedirectEncoded)
        $rResp = Invoke-AuditRequest -Url $rUrl -Timeout 8
        if ($rResp.statusCode -in @(301, 302, 303, 307, 308)) {
            $locHeader = if ($rResp.headers.ContainsKey('location')) { $rResp.headers['location'] } else { '' }
            if ($locHeader -match 'evil-attacker\.com') {
                Add-Finding -List $findings -Severity 'HIGH' -Category 'open_redirect' -Target $rUrl `
                    -Message ("Open Redirect confermato: parametro '$rParam' su $redirectBase redireziona a URL esterno ($locHeader).") `
                    -Recommendation 'Validare redirect con whitelist di percorsi interni. Mai usare input utente direttamente come header Location.' `
                    -Reference 'OWASP WSTG-v42-CLNT-04 / CWE-601'
            }
        }
    }
}

# =============================================================
# 14) Path Traversal Probe (LFI)
# =============================================================
Write-Host '[PENTEST] Path traversal probe...'
$lfiWindowsIndicator = 'WINDOWS'
$lfiLinuxIndicator = 'root:x:0:'
$traversalPayloads = @(
    '/../../../etc/passwd',
    '/..%2F..%2F..%2Fetc%2Fpasswd',
    '/%2e%2e%2f%2e%2e%2f%2e%2e%2fetc%2fpasswd',
    '/..%252f..%252f..%252fetc%252fpasswd',
    '/....//....//....//etc/passwd'
)
foreach ($tp in $traversalPayloads) {
    $tpUrl = Join-TargetUrl -BaseUri $targetUri -RelativePath $tp
    $tpResp = Invoke-AuditRequest -Url $tpUrl -Timeout $TimeoutSeconds
    if ($tpResp.statusCode -eq 200) {
        $body = [string]$tpResp.body
        if ($body -match $lfiLinuxIndicator -or $body -match $lfiWindowsIndicator) {
            Add-Finding -List $findings -Severity 'HIGH' -Category 'path_traversal' -Target $tpUrl `
                -Message ('Path Traversal CRITICO: /etc/passwd o file di sistema leakato nella risposta tramite payload URL: {0}' -f $tp) `
                -Recommendation 'Verificare configurazione Apache (AllowEncodedSlashes, mod_rewrite). Bloccare traversal nel vhost. Aggiornare Apache.' `
                -Reference 'OWASP WSTG-v42-INPV-01 / CWE-22'
        }
    }
}

# Parameter-based LFI
$lfiParamPayloads = @('../../../../etc/passwd', '..\\..\\..\\..\\windows\\system32\\drivers\\etc\\hosts')
$lfiTestPages = @('/index.php', '/drops.php', '/bosses.php', '/info.php')
$lfiParams = @('file', 'page', 'path', 'include', 'template', 'doc', 'load', 'filename')
foreach ($lfiPage in $lfiTestPages) {
    foreach ($lfiParam in $lfiParams) {
        foreach ($lfiPayload in $lfiParamPayloads) {
            $lfiUrl = Join-TargetUrl -BaseUri $targetUri -RelativePath ('{0}?{1}={2}' -f $lfiPage, $lfiParam, [System.Uri]::EscapeDataString($lfiPayload))
            $lfiResp = Invoke-AuditRequest -Url $lfiUrl -Timeout 8
            if ($lfiResp.statusCode -eq 200) {
                $body = [string]$lfiResp.body
                if ($body -match $lfiLinuxIndicator -or ($body -match 'localhost' -and $body -match '127\.0\.0\.1')) {
                    Add-Finding -List $findings -Severity 'HIGH' -Category 'path_traversal' -Target $lfiUrl `
                        -Message ("LFI confermato: file di sistema esposto tramite parametro '$lfiParam' su $lfiPage.") `
                        -Recommendation 'Non passare path a include/fopen senza validazione whitelist. Usare basename() e realpath() per verificare il percorso.' `
                        -Reference 'OWASP WSTG-v42-INPV-01 / CWE-22'
                }
            }
        }
    }
}

# =============================================================
# 15) Subresource Integrity (SRI) Check
# =============================================================
Write-Host '[PENTEST] Subresource Integrity check...'
$homeUrl = Join-TargetUrl -BaseUri $targetUri -RelativePath '/'
$sriResp = Invoke-AuditRequest -Url $homeUrl -Timeout $TimeoutSeconds
if ($sriResp.ok -and $sriResp.statusCode -eq 200) {
    $htmlBody = [string]$sriResp.body
    $sriSeen = [System.Collections.Generic.HashSet[string]]::new()

    $scriptTags = [regex]::Matches($htmlBody, '<script[^>]+src\s*=\s*["\x27]([^">\x27]+)["\x27][^>]*>', [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)
    foreach ($m in $scriptTags) {
        $src = $m.Groups[1].Value
        $isExternal = $src -match '^https?://' -and -not $src.StartsWith($targetUri.GetLeftPart('Authority'), [System.StringComparison]::OrdinalIgnoreCase)
        if ($isExternal -and $sriSeen.Add("script:$src")) {
            $hasIntegrity = $m.Value -match '\bintegrity\s*='
            if (-not $hasIntegrity) {
                Add-Finding -List $findings -Severity 'MEDIUM' -Category 'sri' -Target $src `
                    -Message ("Script CDN esterno senza Subresource Integrity (SRI): $src - Un CDN compromesso puo iniettare codice malevolo.") `
                    -Recommendation 'Aggiungere attributo integrity=sha384-... e crossorigin=anonymous al tag script CDN. Generare con: openssl dgst -sha384 -binary file.js | openssl base64 -A' `
                    -Reference 'OWASP WSTG-CONF-13 / W3C SRI / CWE-829'
            }
        }
    }

    $linkTags = [regex]::Matches($htmlBody, '<link[^>]+href\s*=\s*["\x27]([^">\x27]+)["\x27][^>]*>', [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)
    foreach ($m in $linkTags) {
        $href = $m.Groups[1].Value
        $isExternal = $href -match '^https?://' -and -not $href.StartsWith($targetUri.GetLeftPart('Authority'), [System.StringComparison]::OrdinalIgnoreCase)
        $isStylesheet = $m.Value -match "rel\s*=\s*['\x22]stylesheet['\x22]"
        if ($isExternal -and $isStylesheet -and $sriSeen.Add("link:$href")) {
            $hasIntegrity = $m.Value -match '\bintegrity\s*='
            if (-not $hasIntegrity) {
                Add-Finding -List $findings -Severity 'LOW' -Category 'sri' -Target $href `
                    -Message ("CSS CDN esterno senza SRI: $href") `
                    -Recommendation 'Aggiungere attributo integrity e crossorigin al tag link stylesheet CDN.' `
                    -Reference 'OWASP WSTG-CONF-13 / W3C SRI'
            }
        }
    }
}

# =============================================================
# 16) Extended Sensitive Path Enumeration
# =============================================================
Write-Host '[PENTEST] Extended sensitive path enumeration...'
$extPaths = @(
    @{ path = '/adminer.php'; sev = 'HIGH' },
    @{ path = '/adminer'; sev = 'HIGH' },
    @{ path = '/phpmyadmin/'; sev = 'HIGH' },
    @{ path = '/phpmyadmin'; sev = 'HIGH' },
    @{ path = '/phpMyAdmin/'; sev = 'HIGH' },
    @{ path = '/.htpasswd'; sev = 'HIGH' },
    @{ path = '/db.php'; sev = 'HIGH' },
    @{ path = '/db_pdo.php'; sev = 'HIGH' },
    @{ path = '/config.php'; sev = 'HIGH' },
    @{ path = '/setup.php'; sev = 'HIGH' },
    @{ path = '/install.php'; sev = 'HIGH' },
    @{ path = '/.git/config'; sev = 'HIGH' },
    @{ path = '/composer.json'; sev = 'MEDIUM' },
    @{ path = '/composer.lock'; sev = 'MEDIUM' },
    @{ path = '/package.json'; sev = 'MEDIUM' },
    @{ path = '/crossdomain.xml'; sev = 'MEDIUM' },
    @{ path = '/clientaccesspolicy.xml'; sev = 'MEDIUM' },
    @{ path = '/web.config'; sev = 'MEDIUM' },
    @{ path = '/wp-login.php'; sev = 'MEDIUM' },
    @{ path = '/xmlrpc.php'; sev = 'MEDIUM' },
    @{ path = '/graphql'; sev = 'MEDIUM' },
    @{ path = '/api/'; sev = 'MEDIUM' },
    @{ path = '/api/v1/'; sev = 'MEDIUM' },
    @{ path = '/swagger.json'; sev = 'MEDIUM' },
    @{ path = '/swagger-ui/'; sev = 'MEDIUM' },
    @{ path = '/actuator'; sev = 'HIGH' },
    @{ path = '/actuator/health'; sev = 'MEDIUM' },
    @{ path = '/debug'; sev = 'HIGH' },
    @{ path = '/test.php'; sev = 'MEDIUM' },
    @{ path = '/debug.log'; sev = 'HIGH' },
    @{ path = '/error.log'; sev = 'HIGH' },
    @{ path = '/README.md'; sev = 'LOW' },
    @{ path = '/CHANGELOG.md'; sev = 'LOW' }
)

$extPathsSeen = [System.Collections.Generic.HashSet[string]]::new()
foreach ($ep in $extPaths) {
    $epUrl = Join-TargetUrl -BaseUri $targetUri -RelativePath $ep.path
    if (-not $extPathsSeen.Add($epUrl)) { continue }
    $epProbe = Invoke-AuditRequest -Url $epUrl -Timeout 8
    if ($epProbe.statusCode -eq 200) {
        $epFinalUrl = [string]$epProbe.finalUrl
        $isRedirectHome = $epFinalUrl -ieq $TargetUrl -or $epFinalUrl -ieq ($TargetUrl + '/') -or $epFinalUrl -ieq ($TargetUrl.TrimEnd('/') + '/')
        if ($isRedirectHome) { continue }
        Add-Finding -List $findings -Severity $ep.sev -Category 'exposure' -Target $epUrl `
            -Message ('Risorsa sensibile raggiungibile HTTP 200 (percorso esteso): {0}' -f $ep.path) `
            -Recommendation 'Bloccare via .htaccess (Require all denied) o rimuovere dal document root.' `
            -Reference 'OWASP WSTG-v42-CONF-05'
    }
}

# =============================================================
# 17) Admin Panel Enumeration & Access Check
# =============================================================
Write-Host '[PENTEST] Admin panel enumeration...'
$adminPaths = @('/admin.php', '/administrator/', '/administrator', '/cp/', '/panel/', '/dashboard/', '/backend/', '/manage/', '/management/', '/controlpanel/')
foreach ($ap in $adminPaths) {
    $apUrl = Join-TargetUrl -BaseUri $targetUri -RelativePath $ap
    $apProbe = Invoke-AuditRequest -Url $apUrl -Timeout 8
    if ($apProbe.statusCode -eq 200) {
        $apFinalUrl = [string]$apProbe.finalUrl
        $redirectedHome = $apFinalUrl -ieq $TargetUrl -or $apFinalUrl -ieq ($TargetUrl + '/') -or $apFinalUrl -ieq ($TargetUrl.TrimEnd('/') + '/')
        if (-not $redirectedHome) {
            $apBody = [string]$apProbe.body
            $hasLoginForm = $apBody -match '(?i)(type\s*=\s*["\x27]password["\x27]|login.*form|admin.*login)'
            if (-not $hasLoginForm) {
                Add-Finding -List $findings -Severity 'HIGH' -Category 'admin_exposure' -Target $apUrl `
                    -Message ('Pannello admin/backend accessibile senza login rilevato: {0}. Potenziale accesso non autenticato.' -f $ap) `
                    -Recommendation 'Proteggere tutti i pannelli admin con autenticazione forte (MFA). Limitare accesso per IP via Apache.' `
                    -Reference 'OWASP WSTG-v42-ATHN-01 / A01:2021 Broken Access Control'
            }
        }
    }
}

# =============================================================
# 18) Rate Limit Verification on Login Endpoint
# =============================================================
Write-Host '[PENTEST] Rate limit verification on login...'
$loginUrl = Join-TargetUrl -BaseUri $targetUri -RelativePath '/'
$loginPayload = "username=testuser_wstg`&password=wrongpassword`&login=1`&csrf_token=invalid_token_probe"
$rateLimitHit = $false
$loginAttempts = 8
for ($rli = 0; $rli -lt $loginAttempts; $rli++) {
    $rlResp = Invoke-AuditRequest -Url $loginUrl -Method 'POST' -Timeout $TimeoutSeconds `
        -Body $loginPayload -ContentType 'application/x-www-form-urlencoded'
    if ($rlResp.statusCode -eq 429 -or ($rlResp.body -match '(?i)too many|rate limit|try again|ratelimit')) {
        $rateLimitHit = $true
        break
    }
}
if (-not $rateLimitHit) {
    Add-Finding -List $findings -Severity 'MEDIUM' -Category 'auth' -Target $loginUrl `
        -Message ("Rate limiting login non rilevato dopo $loginAttempts tentativi (400 Bad Request su CSRF e' accettabile, ma 429 non e' stato restituito).") `
        -Recommendation 'Verificare che appRateLimitAllow e appAuthLockoutRecordFailure siano operativi. Considerare aggiunta header Retry-After su risposta 429.' `
        -Reference 'OWASP WSTG-v42-ATHN-03 / CWE-307'
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