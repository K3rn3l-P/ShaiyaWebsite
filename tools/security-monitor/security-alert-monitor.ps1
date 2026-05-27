param(
    [string]$AlertsLogPath = "config/logs/alerts.log",
    [int]$MinEventsInWindow = 12,
    [int]$WindowSeconds = 600,
    [int]$BlockMinutes = 60,
    [bool]$ApplyFirewallBlock = $false
)

$ErrorActionPreference = 'Stop'

function Write-MonitorLog {
    param([string]$Message)
    $line = "[{0}] {1}" -f (Get-Date -Format "yyyy-MM-dd HH:mm:ss"), $Message
    Write-Host $line
}

if (-not (Test-Path -LiteralPath $AlertsLogPath)) {
    Write-MonitorLog "Alerts log not found at '$AlertsLogPath'. Nothing to process."
    exit 0
}

$rawLines = Get-Content -LiteralPath $AlertsLogPath -ErrorAction Stop
if (-not $rawLines -or $rawLines.Count -eq 0) {
    Write-MonitorLog "Alerts log is empty."
    exit 0
}

$cutoff = (Get-Date).AddSeconds(-1 * [Math]::Abs($WindowSeconds))
$recentByIp = @{}

foreach ($line in $rawLines) {
    if ([string]::IsNullOrWhiteSpace($line)) { continue }
    try {
        $entry = $line | ConvertFrom-Json -ErrorAction Stop
    } catch {
        continue
    }

    $ip = [string]($entry.context.ip)
    if ([string]::IsNullOrWhiteSpace($ip)) {
        $ip = [string]($entry.ip)
    }
    if ([string]::IsNullOrWhiteSpace($ip)) { continue }

    $ts = $null
    if ($entry.ts) {
        try { $ts = [DateTime]::Parse($entry.ts) } catch { $ts = $null }
    }
    if (-not $ts) { continue }
    if ($ts -lt $cutoff) { continue }

    if (-not $recentByIp.ContainsKey($ip)) {
        $recentByIp[$ip] = @()
    }
    $recentByIp[$ip] += $entry
}

$flaggedIps = @()
foreach ($ip in $recentByIp.Keys) {
    $count = $recentByIp[$ip].Count
    if ($count -ge $MinEventsInWindow) {
        $flaggedIps += [PSCustomObject]@{
            IP = $ip
            Events = $count
            WindowSeconds = $WindowSeconds
        }
    }
}

if ($flaggedIps.Count -eq 0) {
    Write-MonitorLog "No IP exceeded threshold ($MinEventsInWindow events / ${WindowSeconds}s)."
    exit 0
}

$flaggedIps | Sort-Object -Property Events -Descending | Format-Table -AutoSize | Out-String | Write-Host

if (-not $ApplyFirewallBlock) {
    Write-MonitorLog "ApplyFirewallBlock not set: report-only mode completed."
    exit 0
}

foreach ($row in $flaggedIps) {
    $ip = $row.IP
    $ruleName = "ShaiyaTempBlock_$ip"

    $existing = netsh advfirewall firewall show rule name="$ruleName" 2>$null
    if ($LASTEXITCODE -eq 0 -and $existing -match $ruleName) {
        Write-MonitorLog "Firewall rule already exists for $ip ($ruleName)."
        continue
    }

    netsh advfirewall firewall add rule name="$ruleName" dir=in action=block remoteip=$ip profile=any | Out-Null
    netsh advfirewall firewall add rule name="$ruleName" dir=out action=block remoteip=$ip profile=any | Out-Null
    Write-MonitorLog "Applied temporary firewall block for $ip."
}

Write-MonitorLog "Monitor run completed."
