param(
    [Parameter(Mandatory = $true)]
    [string]$TargetUrl,
    [string]$ZapInstallDir = 'C:\Program Files\ZAP\Zed Attack Proxy',
    [string]$ZapHomeDir,
    [string]$BindHost = '127.0.0.1',
    [int]$Port = 8092,
    [string]$ApiKey,
    [string]$ReportPath,
    [string]$MarkdownPath,
    [int]$StartTimeoutSeconds = 120,
    [int]$SpiderTimeoutSeconds = 300,
    [int]$PassiveScanTimeoutSeconds = 300,
    [switch]$EnableActiveScan,
    [switch]$FailOnHigh,
    [switch]$FailOnMedium
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

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
        source = 'ZAP'
    })
}

function Invoke-ZapApi {
    param(
        [Parameter(Mandatory = $true)]
        [string]$BaseUrl,
        [Parameter(Mandatory = $true)]
        [string]$Path,
        [hashtable]$Query = @{}
    )

    $builder = [System.UriBuilder]::new($BaseUrl.TrimEnd('/'))
    $builder.Path = $Path.TrimStart('/')
    if ($Query.Count -gt 0) {
        $pairs = New-Object System.Collections.Generic.List[string]
        foreach ($key in $Query.Keys) {
            $value = [string]$Query[$key]
            $pairs.Add(("{0}={1}" -f [System.Uri]::EscapeDataString([string]$key), [System.Uri]::EscapeDataString($value)))
        }
        $builder.Query = ($pairs -join '&')
    }

    return Invoke-RestMethod -Uri $builder.Uri.AbsoluteUri -Method Get -TimeoutSec 20 -ErrorAction Stop
}

function Wait-ZapStatus {
    param(
        [string]$ApiBaseUrl,
        [string]$ApiKeyValue,
        [string]$Path,
        [string]$Property,
        [int]$DesiredValue,
        [int]$TimeoutSeconds
    )

    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)
    do {
        try {
            $response = Invoke-ZapApi -BaseUrl $ApiBaseUrl -Path $Path -Query @{ apikey = $ApiKeyValue }
            $value = [int]$response.$Property
            if ($value -ge $DesiredValue) {
                return $true
            }
        } catch {
        }

        Start-Sleep -Seconds 2
    } while ((Get-Date) -lt $deadline)

    return $false
}

function Get-ZapAlerts {
    param(
        [string]$ApiBaseUrl,
        [string]$ApiKeyValue,
        [string]$TargetUrlValue
    )

    $alerts = @()
    try {
        $response = Invoke-ZapApi -BaseUrl $ApiBaseUrl -Path 'JSON/core/view/alerts/' -Query @{ apikey = $ApiKeyValue; baseurl = $TargetUrlValue; start = 0; count = 1000 }
        if ($response.alerts) {
            $alerts = @($response.alerts)
        }
    } catch {
        $alerts = @()
    }

    return $alerts
}

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$repoRoot = (Resolve-Path (Join-Path $scriptRoot '..\..')).Path

if (-not $ZapHomeDir) {
    $ZapHomeDir = Join-Path $repoRoot 'config\zap-home\security-zap-audit-legacy'
}

if (-not $ReportPath) {
    $ReportPath = Join-Path $repoRoot 'config\logs\security_zap_audit_report.json'
}

if (-not $MarkdownPath) {
    $MarkdownPath = Join-Path $repoRoot 'config\logs\security_zap_audit_report.md'
}

$zapBat = Join-Path $ZapInstallDir 'zap.bat'
if (-not (Test-Path -LiteralPath $zapBat)) {
    throw "ZAP startup script not found: $zapBat"
}

New-Item -ItemType Directory -Path $ZapHomeDir -Force | Out-Null
New-Item -ItemType Directory -Path (Split-Path -Parent $ReportPath) -Force | Out-Null
New-Item -ItemType Directory -Path (Split-Path -Parent $MarkdownPath) -Force | Out-Null

if (-not $ApiKey) {
    $ApiKey = ([guid]::NewGuid()).ToString('N')
}

$zapArgs = @(
    '-daemon',
    '-dir', $ZapHomeDir,
    '-host', $BindHost,
    '-port', [string]$Port,
    '-config', ('api.key={0}' -f $ApiKey),
    '-silent'
)

$process = Start-Process -FilePath $zapBat -ArgumentList $zapArgs -WorkingDirectory $ZapInstallDir -PassThru -WindowStyle Hidden
$baseApiUrl = 'http://127.0.0.1:{0}' -f $Port

try {
    $versionReady = $false
    $deadline = (Get-Date).AddSeconds($StartTimeoutSeconds)
    do {
        try {
            $versionResponse = Invoke-ZapApi -BaseUrl $baseApiUrl -Path 'JSON/core/view/version/' -Query @{ apikey = $ApiKey }
            if ($versionResponse.version) {
                $versionReady = $true
                break
            }
        } catch {
        }
        Start-Sleep -Seconds 2
    } while ((Get-Date) -lt $deadline)

    if (-not $versionReady) {
        throw 'ZAP API non ha risposto entro il timeout di avvio.'
    }

    $spider = Invoke-ZapApi -BaseUrl $baseApiUrl -Path 'JSON/spider/action/scan/' -Query @{ apikey = $ApiKey; url = $TargetUrl; recurse = 'true' }
    $scanId = [string]$spider.scan
    if ([string]::IsNullOrWhiteSpace($scanId)) {
        throw 'ZAP spider non ha restituito un scan id.'
    }

    if (-not (Wait-ZapStatus -ApiBaseUrl $baseApiUrl -ApiKeyValue $ApiKey -Path 'JSON/spider/view/status/' -Property 'status' -DesiredValue 100 -TimeoutSeconds $SpiderTimeoutSeconds)) {
        throw 'ZAP spider non ha completato entro il timeout.'
    }

    if (-not (Wait-ZapStatus -ApiBaseUrl $baseApiUrl -ApiKeyValue $ApiKey -Path 'JSON/pscan/view/recordsToScan/' -Property 'recordsToScan' -DesiredValue 0 -TimeoutSeconds $PassiveScanTimeoutSeconds)) {
        throw 'ZAP passive scan non ha completato entro il timeout.'
    }

    if ($EnableActiveScan) {
        $active = Invoke-ZapApi -BaseUrl $baseApiUrl -Path 'JSON/ascan/action/scan/' -Query @{ apikey = $ApiKey; url = $TargetUrl; recurse = 'true' }
        $activeScanId = [string]$active.scan
        if (-not [string]::IsNullOrWhiteSpace($activeScanId)) {
            $activeDeadline = (Get-Date).AddSeconds($SpiderTimeoutSeconds)
            do {
                try {
                    $statusResponse = Invoke-ZapApi -BaseUrl $baseApiUrl -Path 'JSON/ascan/view/status/' -Query @{ apikey = $ApiKey; scanId = $activeScanId }
                    if ([int]$statusResponse.status -ge 100) {
                        break
                    }
                } catch {
                }
                Start-Sleep -Seconds 3
            } while ((Get-Date) -lt $activeDeadline)
        }
    }

    $alerts = Get-ZapAlerts -ApiBaseUrl $baseApiUrl -ApiKeyValue $ApiKey -TargetUrlValue $TargetUrl
    $findings = New-Object 'System.Collections.Generic.List[object]'

    foreach ($alert in $alerts) {
        $risk = ([string]$alert.risk).ToLowerInvariant()
        $severity = switch -Regex ($risk) {
            'high' { 'HIGH' }
            'medium' { 'MEDIUM' }
            'low' { 'LOW' }
            default { 'LOW' }
        }

        $alertName = [string]$alert.alert
        $target = [string]$alert.url
        $evidence = [string]$alert.evidence
        $solution = [string]$alert.solution
        $reference = if ($alert.reference) { [string]$alert.reference } else { '' }
        $recommendation = $solution
        if ([string]::IsNullOrWhiteSpace($recommendation)) {
            $recommendation = 'Review the alert details in ZAP.'
        }

        Add-Finding -List $findings -Severity $severity -Category 'zap' -Target $target -Message $alertName -Recommendation $recommendation -Reference $reference
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
        zap = [ordered]@{
            installDir = $ZapInstallDir
            homeDir = $ZapHomeDir
            port = $Port
            apiKeyUsed = [bool]$ApiKey
            spider = $spider
            activeScanEnabled = [bool]$EnableActiveScan
        }
        stats = $stats
        findings = $findings
    }

    $resolvedReportPath = $ExecutionContext.SessionState.Path.GetUnresolvedProviderPathFromPSPath($ReportPath)
    $resolvedMarkdownPath = $ExecutionContext.SessionState.Path.GetUnresolvedProviderPathFromPSPath($MarkdownPath)

    $report | ConvertTo-Json -Depth 8 | Set-Content -Path $resolvedReportPath -Encoding UTF8

    $markdown = New-Object System.Collections.Generic.List[string]
    $markdown.Add('# ZAP Audit Report')
    $markdown.Add('')
    $markdown.Add(('Generated: {0}' -f $report.generatedAt))
    $markdown.Add(('Target: {0}' -f $TargetUrl))
    $markdown.Add(('InstallDir: {0}' -f $ZapInstallDir))
    $markdown.Add(('HomeDir: {0}' -f $ZapHomeDir))
    $markdown.Add(('Port: {0}' -f $Port))
    $markdown.Add('')
    $markdown.Add(('High: {0} | Medium: {1} | Low: {2} | Total: {3}' -f $stats.high, $stats.medium, $stats.low, $stats.total))
    $markdown.Add('')
    $markdown.Add('## Findings')

    if ($findings.Count -eq 0) {
        $markdown.Add('- No ZAP alerts were returned for the target.')
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

    Write-Host ('ZAP audit completed for {0}' -f $TargetUrl)
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
} finally {
    try {
        Invoke-ZapApi -BaseUrl $baseApiUrl -Path 'JSON/core/action/shutdown/' -Query @{ apikey = $ApiKey } | Out-Null
    } catch {
    }

    if ($process -and -not $process.HasExited) {
        try {
            $null = $process.WaitForExit(15000)
        } catch {
        }
        if (-not $process.HasExited) {
            try {
                $process.Kill()
            } catch {
            }
        }
    }
}