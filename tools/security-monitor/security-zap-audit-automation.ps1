param(
    [Parameter(Mandatory = $true)]
    [string]$TargetUrl,
    [string]$ZapInstallDir = 'C:\Program Files\ZAP\Zed Attack Proxy',
    [string]$ZapHomeDir,
    [string]$ZapProxyHost = '127.0.0.1',
    [int]$ZapProxyPort = 8091,
    [string]$ReportPath,
    [string]$MarkdownPath,
    [switch]$EnableActiveScan,
    [switch]$EnableAjaxSpider,
    [ValidateRange(1, 20)][int]$AjaxSpiderMaxDurationMins = 3,
    [switch]$FailOnHigh,
    [switch]$FailOnMedium,
    [switch]$IncludeInformational,
    [ValidateRange(1, 50)][int]$KeepLatestRuns = 5,
    [ValidateRange(0, 365)][int]$MaxRunAgeDays = 7,
    [string[]]$CriticalPaths = @(
        '/',
        '/account',
        '/admin',
        '/change_password',
        '/characters',
        '/drops',
        '/rankings',
        '/lucky_case',
        '/lucky_case_api.php',
        '/news_handler.php',
        '/admin_actions.php',
        '/download.php',
        '/register',
        '/login',
        '/donate'
    )
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

try {
    Add-Type -AssemblyName System.Web -ErrorAction SilentlyContinue
} catch {
}

function Convert-ZapText {
    param([object]$Value)

    $text = [string]$Value
    if ([string]::IsNullOrWhiteSpace($text)) {
        return ''
    }

    $text = [System.Web.HttpUtility]::HtmlDecode($text)
    $text = [regex]::Replace($text, '<[^>]+>', ' ')
    $text = [regex]::Replace($text, '\s+', ' ')
    return $text.Trim()
}

function Get-ZapSeverity {
    param([object]$RiskCode)

    switch ([string]$RiskCode) {
        '3' { return 'HIGH' }
        '2' { return 'MEDIUM' }
        '1' { return 'LOW' }
        '0' { return 'INFORMATIONAL' }
        default { return 'LOW' }
    }
}

function Join-PlanUrl {
    param(
        [uri]$BaseUri,
        [string]$RelativePath
    )

    if ([string]::IsNullOrWhiteSpace($RelativePath) -or $RelativePath -eq '/') {
        return $BaseUri.AbsoluteUri.TrimEnd('/')
    }

    $normalizedPath = $RelativePath.Trim()
    if (-not $normalizedPath.StartsWith('/')) {
        $normalizedPath = '/' + $normalizedPath
    }

    return ($BaseUri.AbsoluteUri.TrimEnd('/') + $normalizedPath)
}

function New-ZapPlan {
    param(
        [ValidateSet('passive', 'active')][string]$ScanMode,
        [string]$PlanPath,
        [uri]$BaseUri,
        [string]$ReportFileName,
        [string]$ReportDir,
        [string]$PlanTargetUrl,
        [string[]]$CriticalPaths,
        [bool]$EnableActiveScan,
        [bool]$EnableAjaxSpider,
        [int]$AjaxSpiderMaxDurationMins,
        [bool]$IncludeInformational
    )

    $planLines = New-Object System.Collections.Generic.List[string]
    $planLines.Add('env:')
    $planLines.Add('  contexts:')
    $planLines.Add('    - name: default')
    $planLines.Add('      urls:')
    $planLines.Add(('        - "{0}"' -f $PlanTargetUrl))
    $planLines.Add('jobs:')
    $planLines.Add('  - type: spider')
    $planLines.Add('    parameters:')
    $planLines.Add(('      url: "{0}"' -f $PlanTargetUrl))
    $planLines.Add('      maxDuration: 5')
    $planLines.Add('      threadCount: 4')
    $planLines.Add('      parseRobotsTxt: true')
    $planLines.Add('      parseSitemapXml: true')
    $planLines.Add('      postForm: true')
    $planLines.Add('      processForm: true')
    $planLines.Add('  - type: passiveScan-wait')

    if ($EnableAjaxSpider) {
        $planLines.Add('  - type: spiderAjax')
        $planLines.Add('    parameters:')
        $planLines.Add(('      url: "{0}"' -f $PlanTargetUrl))
        $planLines.Add(('      maxDuration: {0}' -f $AjaxSpiderMaxDurationMins))
        $planLines.Add('  - type: passiveScan-wait')
    }

    if ($ScanMode -eq 'active' -and $EnableActiveScan) {
        foreach ($criticalPath in $CriticalPaths) {
            $criticalUrl = Join-PlanUrl -BaseUri $BaseUri -RelativePath $criticalPath
            $planLines.Add('  - type: activeScan')
            $planLines.Add('    parameters:')
            $planLines.Add(('      url: "{0}"' -f $criticalUrl))
            $planLines.Add('      maxScanDurationInMins: 6')
            $planLines.Add('  - type: passiveScan-wait')
        }
    }

    $planLines.Add('  - type: report')
    $planLines.Add('    parameters:')
    $planLines.Add('      template: traditional-json')
    $planLines.Add(('      reportDir: ''{0}''' -f ($ReportDir -replace '\\', '/')))
    $planLines.Add(('      reportFile: "{0}"' -f $ReportFileName))
    $planLines.Add(('      reportTitle: "Security ZAP Audit Report ({0})"' -f $ScanMode))
    $planLines.Add(('      reportDescription: "{0} scan for {1}"' -f $ScanMode, $PlanTargetUrl))
    $planLines.Add('      displayReport: false')
    $planLines.Add('    risks:')
    $planLines.Add('      - high')
    $planLines.Add('      - medium')
    $planLines.Add('      - low')
    if ($IncludeInformational) {
        $planLines.Add('      - info')
    }

    Set-Content -Path $PlanPath -Value ($planLines -join [Environment]::NewLine) -Encoding ASCII
}

function Remove-StaleZapRuns {
    param(
        [string]$ZapHomeRoot,
        [int]$KeepLatestRuns,
        [int]$MaxRunAgeDays
    )

    if (-not (Test-Path -LiteralPath $ZapHomeRoot)) {
        return
    }

    $runDirectories = @(
        Get-ChildItem -LiteralPath $ZapHomeRoot -Directory -Filter 'run-*' -ErrorAction SilentlyContinue |
            Sort-Object LastWriteTime -Descending
    )

    if ($runDirectories.Count -eq 0) {
        return
    }

    $cutoff = if ($MaxRunAgeDays -gt 0) {
        (Get-Date).AddDays(-1 * $MaxRunAgeDays)
    } else {
        $null
    }

    for ($index = 0; $index -lt $runDirectories.Count; $index++) {
        $directory = $runDirectories[$index]
        $removeByCount = $index -ge $KeepLatestRuns
        $removeByAge = ($null -ne $cutoff) -and ($directory.LastWriteTime -lt $cutoff)

        if (-not ($removeByCount -or $removeByAge)) {
            continue
        }

        try {
            Remove-Item -LiteralPath $directory.FullName -Recurse -Force -ErrorAction Stop
        } catch {
        }
    }
}

function Test-PortInUse {
    param(
        [int]$Port
    )

    try {
        $listeners = @(Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue)
        if ($listeners.Count -gt 0) {
            return $true
        }
    } catch {
    }

    try {
        $udpListeners = @(Get-NetUDPEndpoint -LocalPort $Port -ErrorAction SilentlyContinue)
        if ($udpListeners.Count -gt 0) {
            return $true
        }
    } catch {
    }

    return $false
}

function Resolve-ZapProxyPort {
    param(
        [string]$BindAddress,
        [int]$PreferredPort
    )

    if (-not (Test-PortInUse -Port $PreferredPort)) {
        return $PreferredPort
    }

    for ($offset = 1; $offset -le 50; $offset++) {
        $candidatePort = $PreferredPort + $offset
        if (-not (Test-PortInUse -Port $candidatePort)) {
            return $candidatePort
        }
    }

    throw "No available ZAP proxy port found starting from $PreferredPort on $BindAddress"
}

function Invoke-ZapPlan {
    param(
        [string]$PlanPath,
        [string]$ZapBat,
        [string]$ZapHomeDir,
        [string]$ProxyHost,
        [int]$ProxyPort,
        [string]$StdOutPath,
        [string]$StdErrPath,
        [int]$TimeoutSeconds
    )

    $zapArgs = @(
        '-cmd',
        '-autorun', $PlanPath,
        '-dir', $ZapHomeDir,
        '-host', $ProxyHost,
        '-port', [string]$ProxyPort,
        '-silent',
        '-config', 'start.checkForUpdates=false',
        '-config', 'start.downloadNewRelease=false',
        '-config', 'start.checkAddonUpdates=false',
        '-config', 'start.installAddonUpdates=false',
        '-config', 'start.installScannerRules=false',
        '-config', 'start.reportReleaseAddons=false',
        '-config', 'start.reportBetaAddons=false',
        '-config', 'start.reportAlphaAddons=false'
    )

    $process = Start-Process -FilePath $ZapBat -ArgumentList $zapArgs -WorkingDirectory (Split-Path -Parent $ZapBat) -PassThru -NoNewWindow -RedirectStandardOutput $StdOutPath -RedirectStandardError $StdErrPath
    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)

    while (-not $process.HasExited -and (Get-Date) -lt $deadline) {
        Start-Sleep -Seconds 2
        $process.Refresh()
    }

    if (-not $process.HasExited) {
        try {
            $process.Kill()
        } catch {
        }

        throw "ZAP automation framework non ha terminato entro $TimeoutSeconds secondi. Log: $StdOutPath"
    }

    $stdoutText = ''
    $stderrText = ''
    if (Test-Path -LiteralPath $StdOutPath) {
        $stdoutText = Get-Content -LiteralPath $StdOutPath -Raw -ErrorAction SilentlyContinue
    }
    if (Test-Path -LiteralPath $StdErrPath) {
        $stderrText = Get-Content -LiteralPath $StdErrPath -Raw -ErrorAction SilentlyContinue
    }

    return [PSCustomObject]@{
        exitCode = [int]$process.ExitCode
        stdout = [string]$stdoutText
        stderr = [string]$stderrText
    }
}

function Should-ExcludeZapFinding {
    param(
        [string]$AlertName,
        [string]$Target,
        [string]$Evidence
    )

    # Escludi CSP Header Not Set su file statici (robots.txt, sitemap.xml)
    if ($AlertName -match 'Content Security Policy.*Header Not Set' -and $Target -match '\.(txt|xml)$') {
        return $true
    }

    # Escludi CSP Wildcard Directive quando contiene nonce (è legittimo per CSP)
    if ($AlertName -match 'CSP.*Wildcard Directive' -and $Evidence -match 'nonce-') {
        return $true
    }

    # Escludi CSP unsafe-inline quando è per style-src (necessario per editor WYSIWYG)
    if ($AlertName -match 'CSP.*style-src.*unsafe-inline' -and $Evidence -match 'style-src.*unsafe-inline') {
        return $true
    }

    # Escludi SRI (Sub Resource Integrity) come buona pratica opzionale
    # Nota: SRI è importante ma non critico se il CSP permette i CDN.
    # Commentare questa riga per includere gli alert SRI.
    if ($AlertName -match 'Sub Resource Integrity.*Missing') {
        return $true
    }

    return $false
}

function Import-ZapFindingsFromReport {
    param(
        [string]$ReportFile,
        [string]$ScanMode,
        [System.Collections.Generic.List[object]]$ResultList,
        [switch]$IncludeInformational
    )

    if (-not (Test-Path -LiteralPath $ReportFile)) {
        return $null
    }

    $zapReport = Get-Content -LiteralPath $ReportFile -Raw | ConvertFrom-Json
    if (-not $zapReport.site) {
        return $zapReport
    }

    foreach ($site in @($zapReport.site)) {
        $siteName = [string]$site.'@name'
        if (-not $site.alerts) {
            continue
        }

        foreach ($alert in @($site.alerts)) {
            $severity = Get-ZapSeverity -RiskCode $alert.riskcode
            if ($severity -eq 'INFORMATIONAL' -and -not $IncludeInformational) {
                continue
            }

            $alertName = Convert-ZapText $alert.alert
            $solution = Convert-ZapText $alert.solution
            $reference = Convert-ZapText $alert.reference
            $confidence = Convert-ZapText $alert.confidence
            $cweId = Convert-ZapText $alert.cweid
            $pluginId = Convert-ZapText $alert.pluginid
            $instances = @($alert.instances)

            if ($instances.Count -gt 0) {
                foreach ($instance in $instances) {
                    $target = Convert-ZapText $instance.uri
                    if ([string]::IsNullOrWhiteSpace($target)) {
                        $target = $siteName
                    }

                    $message = $alertName
                    if ([string]::IsNullOrWhiteSpace($message)) {
                        $message = 'ZAP alert'
                    }

                    $recommendation = $solution
                    if ([string]::IsNullOrWhiteSpace($recommendation)) {
                        $recommendation = 'Review the full ZAP report for remediation details.'
                    }

                    $detail = Convert-ZapText $instance.evidence
                    if (-not [string]::IsNullOrWhiteSpace($detail)) {
                        $message = '{0} :: {1}' -f $message, $detail
                    }

                    # Escludi false positive noti
                    if (Should-ExcludeZapFinding -AlertName $alertName -Target $target -Evidence $detail) {
                        continue
                    }

                    $ResultList.Add([PSCustomObject]@{
                        severity = $severity
                        category = 'zap'
                        scanMode = $ScanMode
                        target = $target
                        message = ('{0} [{1}]' -f $message, $ScanMode)
                        recommendation = $recommendation
                        reference = $reference
                        confidence = $confidence
                        cweId = $cweId
                        pluginId = $pluginId
                        source = 'ZAP'
                    })
                }
            } else {
                $message = $alertName
                if ([string]::IsNullOrWhiteSpace($message)) {
                    $message = 'ZAP alert'
                }

                $recommendation = $solution
                if ([string]::IsNullOrWhiteSpace($recommendation)) {
                    $recommendation = 'Review the full ZAP report for remediation details.'
                }

                $ResultList.Add([PSCustomObject]@{
                    severity = $severity
                    category = 'zap'
                    scanMode = $ScanMode
                    target = $siteName
                    message = ('{0} [{1}]' -f $message, $ScanMode)
                    recommendation = $recommendation
                    reference = $reference
                    confidence = $confidence
                    cweId = $cweId
                    pluginId = $pluginId
                    source = 'ZAP'
                })
            }
        }
    }

    return $zapReport
}

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$repoRoot = (Resolve-Path (Join-Path $scriptRoot '..\..')).Path

$zapHomeRoot = if ($ZapHomeDir) {
    $ZapHomeDir
} else {
    Join-Path $repoRoot 'config\zap-home\security-zap-audit'
}

$reportDir = Join-Path $repoRoot 'config\logs'
if (-not $ReportPath) {
    $ReportPath = Join-Path $reportDir 'security_zap_audit_report.json'
}
if (-not $MarkdownPath) {
    $MarkdownPath = Join-Path $reportDir 'security_zap_audit_report.md'
}

$zapBat = Join-Path $ZapInstallDir 'zap.bat'
if (-not (Test-Path -LiteralPath $zapBat)) {
    throw "ZAP startup script not found: $zapBat"
}

New-Item -ItemType Directory -Path $zapHomeRoot -Force | Out-Null
New-Item -ItemType Directory -Path $reportDir -Force | Out-Null
New-Item -ItemType Directory -Path (Split-Path -Parent $MarkdownPath) -Force | Out-Null

Remove-StaleZapRuns -ZapHomeRoot $zapHomeRoot -KeepLatestRuns $KeepLatestRuns -MaxRunAgeDays $MaxRunAgeDays

$runStamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$runDir = Join-Path $zapHomeRoot ('run-' + $runStamp + '-' + $PID)
New-Item -ItemType Directory -Path $runDir -Force | Out-Null
$ZapHomeDir = Join-Path $runDir 'home'
New-Item -ItemType Directory -Path $ZapHomeDir -Force | Out-Null

$passivePlanPath = Join-Path $runDir 'zap-passive-plan.yaml'
$activePlanPath = Join-Path $runDir 'zap-active-plan.yaml'
$passiveStdOut = Join-Path $runDir 'zap-passive-stdout.log'
$passiveStdErr = Join-Path $runDir 'zap-passive-stderr.log'
$activeStdOut = Join-Path $runDir 'zap-active-stdout.log'
$activeStdErr = Join-Path $runDir 'zap-active-stderr.log'

$normalizedTarget = $TargetUrl.TrimEnd('/')
$targetUri = [Uri]$normalizedTarget
$targetHost = $targetUri.Host

$timeoutSeconds = [Math]::Max(1200, $(if ($EnableActiveScan) { 1800 } else { 1200 }))
$effectiveZapProxyPort = Resolve-ZapProxyPort -BindAddress $ZapProxyHost -PreferredPort $ZapProxyPort

New-ZapPlan -ScanMode passive -PlanPath $passivePlanPath -BaseUri $targetUri -ReportFileName 'security_zap_passive_report' -ReportDir $runDir -PlanTargetUrl $normalizedTarget -CriticalPaths $CriticalPaths -EnableActiveScan:$false -EnableAjaxSpider:$EnableAjaxSpider -AjaxSpiderMaxDurationMins $AjaxSpiderMaxDurationMins -IncludeInformational:$IncludeInformational
$passiveRun = Invoke-ZapPlan -PlanPath $passivePlanPath -ZapBat $zapBat -ZapHomeDir $ZapHomeDir -ProxyHost $ZapProxyHost -ProxyPort $effectiveZapProxyPort -StdOutPath $passiveStdOut -StdErrPath $passiveStdErr -TimeoutSeconds $timeoutSeconds
if ($passiveRun.exitCode -ne 0) {
    throw "ZAP passive scan failed with exit code $($passiveRun.exitCode). STDOUT=$($passiveRun.stdout) STDERR=$($passiveRun.stderr)"
}

$activeRun = $null
if ($EnableActiveScan) {
    New-ZapPlan -ScanMode active -PlanPath $activePlanPath -BaseUri $targetUri -ReportFileName 'security_zap_active_report' -ReportDir $runDir -PlanTargetUrl $normalizedTarget -CriticalPaths $CriticalPaths -EnableActiveScan:$true -EnableAjaxSpider:$EnableAjaxSpider -AjaxSpiderMaxDurationMins $AjaxSpiderMaxDurationMins -IncludeInformational:$IncludeInformational
    $activeRun = Invoke-ZapPlan -PlanPath $activePlanPath -ZapBat $zapBat -ZapHomeDir $ZapHomeDir -ProxyHost $ZapProxyHost -ProxyPort $effectiveZapProxyPort -StdOutPath $activeStdOut -StdErrPath $activeStdErr -TimeoutSeconds ($timeoutSeconds + 300)
    if ($activeRun.exitCode -ne 0) {
        throw "ZAP active scan failed with exit code $($activeRun.exitCode). STDOUT=$($activeRun.stdout) STDERR=$($activeRun.stderr)"
    }
}

$findings = New-Object 'System.Collections.Generic.List[object]'
$passiveReportPath = Join-Path $runDir 'security_zap_passive_report.json'
$activeReportPath = Join-Path $runDir 'security_zap_active_report.json'

$passiveReport = Import-ZapFindingsFromReport -ReportFile $passiveReportPath -ScanMode 'passive' -ResultList $findings -IncludeInformational:$IncludeInformational
$activeReport = $null
if ($EnableActiveScan) {
    $activeReport = Import-ZapFindingsFromReport -ReportFile $activeReportPath -ScanMode 'active' -ResultList $findings -IncludeInformational:$IncludeInformational
}

if (-not $passiveReport -and -not $activeReport) {
    throw "ZAP non ha prodotto un report JSON. STDOUT=$($passiveRun.stdout) STDERR=$($passiveRun.stderr)"
}

$dedupedFindings = New-Object 'System.Collections.Generic.List[object]'
$seenFindings = @{}
foreach ($finding in $findings) {
    $dedupeKey = '{0}|{1}|{2}|{3}|{4}' -f $finding.scanMode, $finding.severity, $finding.target, $finding.category, $finding.message
    if (-not $seenFindings.ContainsKey($dedupeKey)) {
        $seenFindings[$dedupeKey] = $true
        $dedupedFindings.Add($finding)
    }
}

$findings = $dedupedFindings

$stats = [ordered]@{
    high = @($findings | Where-Object { $_.severity -eq 'HIGH' }).Count
    medium = @($findings | Where-Object { $_.severity -eq 'MEDIUM' }).Count
    low = @($findings | Where-Object { $_.severity -eq 'LOW' }).Count
    informational = @($findings | Where-Object { $_.severity -eq 'INFORMATIONAL' }).Count
    total = $findings.Count
}

$scanModes = New-Object System.Collections.Generic.List[string]
if ($passiveReport) {
    $scanModes.Add('passive')
}
if ($EnableActiveScan -and $activeReport) {
    $scanModes.Add('active')
}

$report = [ordered]@{
    generatedAt = (Get-Date).ToString('o')
    targetUrl = $TargetUrl
    scanModes = @($scanModes)
    zap = [ordered]@{
        installDir = $ZapInstallDir
        homeDir = $ZapHomeDir
        proxyHost = $ZapProxyHost
        proxyPort = $effectiveZapProxyPort
        passivePlanPath = $passivePlanPath
        activePlanPath = $activePlanPath
        passiveReportPath = $passiveReportPath
        activeReportPath = $activeReportPath
        exitCode = if ($EnableActiveScan -and $activeRun) { [Math]::Max($passiveRun.exitCode, $activeRun.exitCode) } else { $passiveRun.exitCode }
        passiveExitCode = $passiveRun.exitCode
        activeExitCode = if ($activeRun) { $activeRun.exitCode } else { $null }
        activeScanEnabled = [bool]$EnableActiveScan
        ajaxSpiderEnabled = [bool]$EnableAjaxSpider
        ajaxSpiderMaxDurationMins = $AjaxSpiderMaxDurationMins
        targetHost = $targetHost
        runDirectory = $runDir
    }
    stats = $stats
    findings = $findings
}

$report | ConvertTo-Json -Depth 12 | Set-Content -Path $ReportPath -Encoding UTF8

$markdown = New-Object System.Collections.Generic.List[string]
$markdown.Add('# ZAP Audit Report')
$markdown.Add('')
$markdown.Add(('Generated: {0}' -f $report.generatedAt))
$markdown.Add(('Target: {0}' -f $TargetUrl))
$markdown.Add(('InstallDir: {0}' -f $ZapInstallDir))
$markdown.Add(('HomeDir: {0}' -f $ZapHomeDir))
$markdown.Add(('Proxy: {0}:{1}' -f $ZapProxyHost, $effectiveZapProxyPort))
$markdown.Add(('PassivePlan: {0}' -f $passivePlanPath))
$markdown.Add(('PassiveReport: {0}' -f $passiveReportPath))
if ($EnableActiveScan) {
    $markdown.Add(('ActivePlan: {0}' -f $activePlanPath))
    $markdown.Add(('ActiveReport: {0}' -f $activeReportPath))
}
$markdown.Add(('ExitCode: {0}' -f $report.zap.exitCode))
$markdown.Add('')
$markdown.Add(('High: {0} | Medium: {1} | Low: {2} | Info: {3} | Total: {4}' -f $stats.high, $stats.medium, $stats.low, $stats.informational, $stats.total))
$markdown.Add('')
$markdown.Add('## Scan Modes')
$markdown.Add('- passive: spider + passive scan + report')
if ($EnableActiveScan) {
    $markdown.Add(('- active: spider + passive scan + active scan on {0} critical path(s)' -f $CriticalPaths.Count))
}
$markdown.Add('')
$markdown.Add('## Findings')
if ($findings.Count -eq 0) {
    $markdown.Add('- No ZAP alerts reported.')
} else {
    foreach ($finding in $findings) {
        $markdown.Add(('- [{0}] [{1}] {2} :: {3}' -f $finding.severity, $finding.scanMode, $finding.target, $finding.message))
        if (-not [string]::IsNullOrWhiteSpace($finding.recommendation)) {
            $markdown.Add(('  Recommendation: {0}' -f $finding.recommendation))
        }
        if (-not [string]::IsNullOrWhiteSpace($finding.reference)) {
            $markdown.Add(('  Reference: {0}' -f $finding.reference))
        }
    }
}

$markdown | Set-Content -Path $MarkdownPath -Encoding UTF8

Remove-StaleZapRuns -ZapHomeRoot $zapHomeRoot -KeepLatestRuns $KeepLatestRuns -MaxRunAgeDays $MaxRunAgeDays

Write-Host "ZAP automation audit completed for $TargetUrl"
Write-Host "Passive plan file: $passivePlanPath"
Write-Host "Active plan file: $activePlanPath"
Write-Host "Report JSON: $ReportPath"
Write-Host "Report Markdown: $MarkdownPath"
Write-Host ('Findings -> High: {0}, Medium: {1}, Low: {2}, Total: {3}' -f $stats.high, $stats.medium, $stats.low, $stats.total)

if ($FailOnHigh -and $stats.high -gt 0) {
    throw "ZAP audit reported $($stats.high) high-severity finding(s)."
}

if ($FailOnMedium -and (($stats.high + $stats.medium) -gt 0)) {
    throw "ZAP audit reported $($stats.high) high and $($stats.medium) medium finding(s)."
}
