param(
    [string]$ConfigPath = "$PSScriptRoot\worker.config.json",
    [switch]$Once,
    [int]$RunJobId = 0
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-Config {
    param([string]$Path)

    if (-not (Test-Path -LiteralPath $Path)) {
        throw "Config not found: $Path"
    }

    $json = Get-Content -LiteralPath $Path -Raw -Encoding UTF8
    $cfg = $json | ConvertFrom-Json

    if (-not $cfg.api_url -or -not $cfg.worker_id -or -not $cfg.hmac_secret -or -not $cfg.sql_connection_string) {
        throw 'Invalid config: api_url, worker_id, hmac_secret, sql_connection_string are required.'
    }

    return $cfg
}

function Get-HexString {
    param([byte[]]$Bytes)
    return -join ($Bytes | ForEach-Object { $_.ToString('x2') })
}

function New-RandomNonce {
    $bytes = New-Object byte[] 16
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    return (Get-HexString -Bytes $bytes)
}

function Get-HmacSha256Hex {
    param(
        [string]$Secret,
        [string]$Data
    )

    $hmac = [System.Security.Cryptography.HMACSHA256]::new([Text.Encoding]::UTF8.GetBytes($Secret))
    try {
        $hashBytes = $hmac.ComputeHash([Text.Encoding]::UTF8.GetBytes($Data))
        return (Get-HexString -Bytes $hashBytes)
    }
    finally {
        $hmac.Dispose()
    }
}

function Get-WebExceptionBody {
    param([System.Exception]$Exception)

    $response = $Exception.Response
    if ($null -eq $response) {
        return ''
    }

    try {
        $stream = $response.GetResponseStream()
        if ($null -eq $stream) {
            return ''
        }

        $reader = New-Object System.IO.StreamReader($stream)
        try {
            return $reader.ReadToEnd()
        }
        finally {
            $reader.Dispose()
            $stream.Dispose()
        }
    }
    catch {
        return ''
    }
}

function Invoke-WorkerApi {
    param(
        [pscustomobject]$Config,
        [hashtable]$Payload
    )

    $body = ($Payload | ConvertTo-Json -Depth 8 -Compress)
    $ts = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds().ToString()
    $nonce = New-RandomNonce
    $toSign = "{0}.{1}.{2}.{3}" -f $Config.worker_id, $ts, $nonce, $body
    $sig = Get-HmacSha256Hex -Secret $Config.hmac_secret -Data $toSign

    $headers = @{
        'X-Worker-Id'    = [string]$Config.worker_id
        'X-Worker-Ts'    = $ts
        'X-Worker-Nonce' = $nonce
        'X-Worker-Sig'   = $sig
    }

    try {
        return Invoke-RestMethod -Uri $Config.api_url -Method Post -Headers $headers -ContentType 'application/json' -Body $body
    }
    catch {
        $rawBody = Get-WebExceptionBody -Exception $_.Exception
        if (-not [string]::IsNullOrWhiteSpace($rawBody)) {
            throw "Worker API request failed. Response body: $rawBody"
        }

        throw
    }
}

function New-TriggerFromSchedule {
    param(
        [string]$ScheduleMode,
        [string]$ScheduleValue
    )

    $mode = if ($null -ne $ScheduleMode) { [string]$ScheduleMode } else { '' }
    $value = if ($null -ne $ScheduleValue) { [string]$ScheduleValue } else { '' }
    $mode = $mode.ToLowerInvariant()
    $value = $value.ToLowerInvariant()

    if ($mode -eq 'cron') {
        switch ($value) {
            '*/10 * * * *' { $value = 'every_10m'; $mode = 'simple'; break }
            '*/15 * * * *' { $value = 'every_15m'; $mode = 'simple'; break }
            '*/30 * * * *' { $value = 'every_30m'; $mode = 'simple'; break }
            '0 * * * *'    { $value = 'hourly'; $mode = 'simple'; break }
            '0 3 * * *'    { $value = 'daily_03_00'; $mode = 'simple'; break }
            '0 0 * * *'    { $value = 'daily_00_00'; $mode = 'simple'; break }
            '0 6 * * *'    { $value = 'daily_06_00'; $mode = 'simple'; break }
            '0 12 * * *'   { $value = 'daily_12_00'; $mode = 'simple'; break }
            '0 18 * * *'   { $value = 'daily_18_00'; $mode = 'simple'; break }
            default         { $value = 'hourly'; $mode = 'simple'; break }
        }
    }

    $intervalStart = (Get-Date).AddMinutes(1)
    $longDuration = New-TimeSpan -Days 3650

    switch ($value) {
        'every_10m' {
            return New-ScheduledTaskTrigger -Once -At $intervalStart -RepetitionInterval (New-TimeSpan -Minutes 10) -RepetitionDuration $longDuration
        }
        'every_15m' {
            return New-ScheduledTaskTrigger -Once -At $intervalStart -RepetitionInterval (New-TimeSpan -Minutes 15) -RepetitionDuration $longDuration
        }
        'every_30m' {
            return New-ScheduledTaskTrigger -Once -At $intervalStart -RepetitionInterval (New-TimeSpan -Minutes 30) -RepetitionDuration $longDuration
        }
        'daily_03_00' { return New-ScheduledTaskTrigger -Daily -At '03:00' }
        'daily_00_00' { return New-ScheduledTaskTrigger -Daily -At '00:00' }
        'daily_06_00' { return New-ScheduledTaskTrigger -Daily -At '06:00' }
        'daily_12_00' { return New-ScheduledTaskTrigger -Daily -At '12:00' }
        'daily_18_00' { return New-ScheduledTaskTrigger -Daily -At '18:00' }
        default {
            return New-ScheduledTaskTrigger -Once -At $intervalStart -RepetitionInterval (New-TimeSpan -Hours 1) -RepetitionDuration $longDuration
        }
    }
}

function Sync-ScheduledTask {
    param(
        [pscustomobject]$Config,
        [int]$JobId,
        [string]$QueueAction,
        [pscustomobject]$Job
    )

    $taskName = "ShaiyaWebJob_$JobId"

    switch ($QueueAction) {
        'DELETE' {
            if (Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue) {
                Unregister-ScheduledTask -TaskName $taskName -Confirm:$false | Out-Null
            }
            return 'Task removed'
        }
        'PAUSE' {
            Disable-ScheduledTask -TaskName $taskName -ErrorAction Stop | Out-Null
            return 'Task paused'
        }
        'RESUME' {
            Enable-ScheduledTask -TaskName $taskName -ErrorAction Stop | Out-Null
            return 'Task resumed'
        }
        'RUN_NOW' {
            Start-ScheduledTask -TaskName $taskName -ErrorAction Stop
            return 'Task started'
        }
        'UPSERT' {
            $taskAction = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -ExecutionPolicy Bypass -File "{0}" -ConfigPath "{1}" -RunJobId {2}' -f $PSCommandPath, $ConfigPath, $JobId)
            $trigger = New-TriggerFromSchedule -ScheduleMode ([string]$Job.schedule_mode) -ScheduleValue ([string]$Job.schedule_value)
            $settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -StartWhenAvailable
            $principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest

            Register-ScheduledTask -TaskName $taskName -Action $taskAction -Trigger $trigger -Settings $settings -Principal $principal -Force | Out-Null

            if (-not [bool]$Job.is_enabled) {
                Disable-ScheduledTask -TaskName $taskName | Out-Null
            }

            return 'Task upserted'
        }
        default {
            throw "Unsupported queue action: $QueueAction"
        }
    }
}

function Invoke-SqlNonQuery {
    param(
        [string]$ConnectionString,
        [string]$CommandText,
        [hashtable]$Parameters
    )

    $conn = New-Object System.Data.SqlClient.SqlConnection($ConnectionString)
    $cmd = $conn.CreateCommand()
    $cmd.CommandText = $CommandText
    $cmd.CommandTimeout = 60

    if ($Parameters) {
        foreach ($key in $Parameters.Keys) {
            $null = $cmd.Parameters.AddWithValue($key, $Parameters[$key])
        }
    }

    try {
        $conn.Open()
        [void]$cmd.ExecuteNonQuery()
    }
    finally {
        $conn.Close()
        $conn.Dispose()
    }
}

function Get-SqlConnectionIdentity {
    param([string]$ConnectionString)

    $conn = New-Object System.Data.SqlClient.SqlConnection($ConnectionString)
    $cmd = $conn.CreateCommand()
    $cmd.CommandText = "SELECT CAST(@@SERVERNAME AS NVARCHAR(128)) AS ServerName, CAST(DB_NAME() AS NVARCHAR(128)) AS DatabaseName"
    $cmd.CommandTimeout = 10

    try {
        $conn.Open()
        $reader = $cmd.ExecuteReader()
        try {
            if ($reader.Read()) {
                $serverName = [string]$reader['ServerName']
                $databaseName = [string]$reader['DatabaseName']
                return @{ Server = $serverName; Database = $databaseName }
            }
        }
        finally {
            $reader.Close()
            $reader.Dispose()
        }
    }
    finally {
        $conn.Close()
        $conn.Dispose()
    }

    return @{ Server = ''; Database = '' }
}

function Try-ParseSimpleMeta {
    param(
        [pscustomobject]$Job
    )

    $sqlSource = [string]$Job.sql_source
    if ([string]::IsNullOrWhiteSpace($sqlSource) -or $sqlSource.ToLowerInvariant() -ne 'simple_meta') {
        return $null
    }

    $payloadRaw = [string]$Job.sql_payload
    if ([string]::IsNullOrWhiteSpace($payloadRaw)) {
        return $null
    }

    try {
        $meta = $payloadRaw | ConvertFrom-Json
    }
    catch {
        return $null
    }

    if ($null -eq $meta) {
        return $null
    }

    $databaseName = ([string]$meta.database).Trim()
    $tableName = ([string]$meta.table).Trim()
    $columnName = ([string]$meta.column).Trim()
    $operator = ([string]$meta.operator).Trim().ToLowerInvariant()
    $expectedValue = ([string]$meta.expected_value).Trim()
    $secondCommand = ([string]$meta.second_command).Trim()

    if ([string]::IsNullOrWhiteSpace($databaseName) -or [string]::IsNullOrWhiteSpace($tableName) -or [string]::IsNullOrWhiteSpace($columnName)) {
        return $null
    }

    if ($databaseName -notmatch '^[A-Za-z0-9_]+$' -or $tableName -notmatch '^[A-Za-z0-9_]+$' -or $columnName -notmatch '^[A-Za-z0-9_]+$') {
        return $null
    }

    if ($operator -notin @('eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'contains')) {
        $operator = 'eq'
    }

    return @{
        Database = $databaseName
        Table = $tableName
        Column = $columnName
        Operator = $operator
        ExpectedValue = $expectedValue
        SecondCommand = $secondCommand
    }
}

function Get-SimpleMetaResolvedValue {
    param(
        [string]$ConnectionString,
        [hashtable]$SimpleMeta
    )

    $dbEscaped = "[$($SimpleMeta.Database)]"
    $tableEscaped = "[$($SimpleMeta.Table)]"
    $columnEscaped = "[$($SimpleMeta.Column)]"

    $conn = New-Object System.Data.SqlClient.SqlConnection($ConnectionString)
    try {
        $conn.Open()

        $hasNameDayCmd = $conn.CreateCommand()
        $hasNameDayCmd.CommandText = "SELECT TOP 1 1 FROM $dbEscaped.INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = @TableName AND COLUMN_NAME = 'NameDay'"
        [void]$hasNameDayCmd.Parameters.AddWithValue('@TableName', $SimpleMeta.Table)
        $hasNameDay = $null -ne $hasNameDayCmd.ExecuteScalar()

        $currentDayCmd = $conn.CreateCommand()
        $currentDayCmd.CommandText = "SELECT CAST(DATENAME(dw, GETDATE()) AS NVARCHAR(50))"
        $currentDay = [string]$currentDayCmd.ExecuteScalar()

        if ($hasNameDay) {
            $dayCmd = $conn.CreateCommand()
            $dayCmd.CommandText = "SELECT TOP 1 CAST($columnEscaped AS NVARCHAR(255)) FROM $dbEscaped.dbo.$tableEscaped WHERE [NameDay] = DATENAME(dw, GETDATE())"
            $dayValue = $dayCmd.ExecuteScalar()
            if ($null -ne $dayValue -and -not [string]::IsNullOrWhiteSpace([string]$dayValue)) {
                return @{ Value = ([string]$dayValue).Trim(); Source = 'NameDay'; Day = $currentDay }
            }
        }

        $filterCmd = $conn.CreateCommand()
        $expected = [string]$SimpleMeta.ExpectedValue
        if ([string]::IsNullOrWhiteSpace($expected)) {
            $filterCmd.CommandText = "SELECT TOP 1 CAST($columnEscaped AS NVARCHAR(255)) FROM $dbEscaped.dbo.$tableEscaped ORDER BY 1 ASC"
        }
        else {
            switch ($SimpleMeta.Operator) {
                'contains' {
                    $filterCmd.CommandText = "SELECT TOP 1 CAST($columnEscaped AS NVARCHAR(255)) FROM $dbEscaped.dbo.$tableEscaped WHERE CAST($columnEscaped AS NVARCHAR(255)) LIKE @Expected ORDER BY 1 ASC"
                    [void]$filterCmd.Parameters.AddWithValue('@Expected', "%$expected%")
                }
                'gt' {
                    $filterCmd.CommandText = "SELECT TOP 1 CAST($columnEscaped AS NVARCHAR(255)) FROM $dbEscaped.dbo.$tableEscaped WHERE TRY_CONVERT(DECIMAL(18,4), $columnEscaped) > TRY_CONVERT(DECIMAL(18,4), @Expected) ORDER BY TRY_CONVERT(DECIMAL(18,4), $columnEscaped) ASC"
                    [void]$filterCmd.Parameters.AddWithValue('@Expected', $expected)
                }
                'gte' {
                    $filterCmd.CommandText = "SELECT TOP 1 CAST($columnEscaped AS NVARCHAR(255)) FROM $dbEscaped.dbo.$tableEscaped WHERE TRY_CONVERT(DECIMAL(18,4), $columnEscaped) >= TRY_CONVERT(DECIMAL(18,4), @Expected) ORDER BY TRY_CONVERT(DECIMAL(18,4), $columnEscaped) ASC"
                    [void]$filterCmd.Parameters.AddWithValue('@Expected', $expected)
                }
                'lt' {
                    $filterCmd.CommandText = "SELECT TOP 1 CAST($columnEscaped AS NVARCHAR(255)) FROM $dbEscaped.dbo.$tableEscaped WHERE TRY_CONVERT(DECIMAL(18,4), $columnEscaped) < TRY_CONVERT(DECIMAL(18,4), @Expected) ORDER BY TRY_CONVERT(DECIMAL(18,4), $columnEscaped) DESC"
                    [void]$filterCmd.Parameters.AddWithValue('@Expected', $expected)
                }
                'lte' {
                    $filterCmd.CommandText = "SELECT TOP 1 CAST($columnEscaped AS NVARCHAR(255)) FROM $dbEscaped.dbo.$tableEscaped WHERE TRY_CONVERT(DECIMAL(18,4), $columnEscaped) <= TRY_CONVERT(DECIMAL(18,4), @Expected) ORDER BY TRY_CONVERT(DECIMAL(18,4), $columnEscaped) DESC"
                    [void]$filterCmd.Parameters.AddWithValue('@Expected', $expected)
                }
                'neq' {
                    $filterCmd.CommandText = "SELECT TOP 1 CAST($columnEscaped AS NVARCHAR(255)) FROM $dbEscaped.dbo.$tableEscaped WHERE CAST($columnEscaped AS NVARCHAR(255)) <> @Expected ORDER BY 1 ASC"
                    [void]$filterCmd.Parameters.AddWithValue('@Expected', $expected)
                }
                default {
                    $filterCmd.CommandText = "SELECT TOP 1 CAST($columnEscaped AS NVARCHAR(255)) FROM $dbEscaped.dbo.$tableEscaped WHERE CAST($columnEscaped AS NVARCHAR(255)) = @Expected ORDER BY 1 ASC"
                    [void]$filterCmd.Parameters.AddWithValue('@Expected', $expected)
                }
            }
        }

        $resolvedValue = $filterCmd.ExecuteScalar()
        if ($null -eq $resolvedValue -or [string]::IsNullOrWhiteSpace([string]$resolvedValue)) {
            return $null
        }

        return @{ Value = ([string]$resolvedValue).Trim(); Source = 'comparison'; Day = $currentDay }
    }
    finally {
        $conn.Close()
        $conn.Dispose()
    }
}

function Build-EffectiveServiceCommand {
    param(
        [string]$ConnectionString,
        [pscustomobject]$Job,
        [string]$OriginalCommand
    )

    $commandText = [string]$OriginalCommand
    $meta = Try-ParseSimpleMeta -Job $Job
    if ($null -eq $meta) {
        return @{ Command = $commandText; SecondaryCommand = ''; Note = '' }
    }

    if ($commandText -notmatch '^\s*/exp2xenable(\s+.*)?$') {
        return @{ Command = $commandText; SecondaryCommand = ''; Note = '' }
    }

    $resolved = Get-SimpleMetaResolvedValue -ConnectionString $ConnectionString -SimpleMeta $meta
    if ($null -eq $resolved -or [string]::IsNullOrWhiteSpace([string]$resolved.Value)) {
        return @{ Command = $commandText; SecondaryCommand = ''; Note = ' | simple_meta=unresolved' }
    }

    $value = ([string]$resolved.Value).Trim()
    if ($value -match '^-?\d+(\.\d+)?$') {
        $numeric = [int][Math]::Round([double]$value)
        if ($numeric -lt 0) {
            $numeric = 0
        }
        $value = [string]$numeric
    }

    $resolvedDay = ([string]$resolved.Day).Trim()
    if ([string]::IsNullOrWhiteSpace($resolvedDay)) {
        $resolvedDay = [string](Get-Date).DayOfWeek
    }

    $secondaryCommand = ''
    $secondTemplate = ([string]$meta.SecondCommand).Trim()
    if (-not [string]::IsNullOrWhiteSpace($secondTemplate)) {
        $secondaryCommand = $secondTemplate
        $secondaryCommand = $secondaryCommand.Replace('{exp}', $value)
        $secondaryCommand = $secondaryCommand.Replace('{value}', $value)
        $secondaryCommand = $secondaryCommand.Replace('{day}', $resolvedDay)
    }

    return @{ Command = "/exp2xenable $value"; SecondaryCommand = $secondaryCommand; Note = " | simple_meta=$($meta.Database).$($meta.Table).$($meta.Column) | simple_meta_source=$($resolved.Source) | simple_meta_value=$value | simple_meta_day=$resolvedDay" }
}

function Test-SqlConnection {
    param([string]$ConnectionString)

    if ([string]::IsNullOrWhiteSpace($ConnectionString)) {
        throw 'sql_connection_string is empty.'
    }

    if ($ConnectionString -notmatch '=') {
        throw 'sql_connection_string must be a full SQL connection string (key=value;...), not only DB name.'
    }

    $conn = New-Object System.Data.SqlClient.SqlConnection($ConnectionString)
    $cmd = $conn.CreateCommand()
    $cmd.CommandText = 'SELECT 1'
    $cmd.CommandTimeout = 10

    try {
        $conn.Open()
        [void]$cmd.ExecuteScalar()
    }
    finally {
        $conn.Close()
        $conn.Dispose()
    }
}

function Invoke-JobExecution {
    param(
        [pscustomobject]$Config,
        [int]$JobId
    )

    $jobResponse = Invoke-WorkerApi -Config $Config -Payload @{
        action = 'get_job'
        job_id = $JobId
    }

    if (-not $jobResponse.ok) {
        throw "Unable to load job metadata for JobID $JobId"
    }

    $job = $jobResponse.job
    if (-not [bool]$job.is_enabled) {
        return @{ Status = 'skipped'; Output = 'Job disabled'; Error = '' }
    }

    $taskType = ([string]$job.task_type).ToUpperInvariant()

    if ($taskType -eq 'SERVICE_COMMAND') {
        $serviceName = [string]$job.service_name
        $commandText = [string]$job.command_text
        $effectiveCommand = Build-EffectiveServiceCommand -ConnectionString ([string]$Config.sql_connection_string) -Job $job -OriginalCommand $commandText
        $commandToRun = [string]$effectiveCommand.Command
        $secondCommandToRun = [string]$effectiveCommand.SecondaryCommand
        $sqlIdentity = Get-SqlConnectionIdentity -ConnectionString ([string]$Config.sql_connection_string)
        $sql = 'EXEC [PS_GameDefs].[dbo].[Command] @serviceName = @ServiceName, @cmmd = @CmdText'
        Invoke-SqlNonQuery -ConnectionString ([string]$Config.sql_connection_string) -CommandText $sql -Parameters @{
            '@ServiceName' = $serviceName
            '@CmdText'     = $commandToRun
        }

        if (-not [string]::IsNullOrWhiteSpace($secondCommandToRun)) {
            Invoke-SqlNonQuery -ConnectionString ([string]$Config.sql_connection_string) -CommandText $sql -Parameters @{
                '@ServiceName' = $serviceName
                '@CmdText'     = $secondCommandToRun
            }
        }

        $secondCommandLog = ''
        if (-not [string]::IsNullOrWhiteSpace($secondCommandToRun)) {
            $secondCommandLog = " | second_command=$secondCommandToRun"
        }

        return @{ Status = 'success'; Output = "SERVICE_COMMAND executed for JobID $JobId | service=$serviceName | command=$commandToRun$secondCommandLog | command_proc=PS_GameDefs.dbo.Command | sql_server=$($sqlIdentity.Server) | sql_db=$($sqlIdentity.Database)$([string]$effectiveCommand.Note)"; Error = '' }
    }

    if ($taskType -eq 'SQL_SCRIPT') {
        $sqlPayload = [string]$job.sql_payload
        if ([string]::IsNullOrWhiteSpace($sqlPayload)) {
            return @{ Status = 'skipped'; Output = ''; Error = 'SQL payload empty' }
        }

        Invoke-SqlNonQuery -ConnectionString ([string]$Config.sql_connection_string) -CommandText $sqlPayload -Parameters @{}
        return @{ Status = 'success'; Output = "SQL_SCRIPT executed for JobID $JobId"; Error = '' }
    }

    return @{ Status = 'error'; Output = ''; Error = "Unsupported task_type: $taskType" }
}

function Process-QueueOnce {
    param([pscustomobject]$Config)

    $limit = 10
    if ($Config.poll_limit) {
        $limit = [int]$Config.poll_limit
        if ($limit -lt 1) { $limit = 1 }
        if ($limit -gt 30) { $limit = 30 }
    }

    $response = Invoke-WorkerApi -Config $Config -Payload @{
        action = 'pull_queue'
        limit  = $limit
    }

    if (-not $response.ok) {
        throw 'pull_queue failed'
    }

    foreach ($item in @($response.items)) {
        $queueId = [int]$item.queue_id
        $jobId = [int]$item.job_id
        $queueAction = [string]$item.queue_action
        $job = [pscustomobject]$item.job

        $runStatus = 'success'
        $output = ''
        $errorMessage = ''

        try {
            if ($queueAction -eq 'RUN_NOW') {
                $runResult = Invoke-JobExecution -Config $Config -JobId $jobId
                $runStatus = [string]$runResult.Status
                $output = [string]$runResult.Output
                $errorMessage = [string]$runResult.Error
            }
            else {
                $output = Sync-ScheduledTask -Config $Config -JobId $jobId -QueueAction $queueAction -Job $job
            }
        }
        catch {
            $runStatus = 'error'
            $errorMessage = $_.Exception.Message
        }

        [void](Invoke-WorkerApi -Config $Config -Payload @{
            action = 'queue_result'
            queue_id = $queueId
            job_id = $jobId
            run_status = $runStatus
            output = $output
            error_message = $errorMessage
        })
    }
}

try {
    $config = Get-Config -Path $ConfigPath

    Test-SqlConnection -ConnectionString ([string]$config.sql_connection_string)

    [void](Invoke-WorkerApi -Config $config -Payload @{ action = 'heartbeat' })

    if ($RunJobId -gt 0) {
        $jobResult = Invoke-JobExecution -Config $config -JobId $RunJobId
        [void](Invoke-WorkerApi -Config $config -Payload @{
            action = 'job_run_log'
            job_id = $RunJobId
            run_status = $jobResult.Status
            output = $jobResult.Output
            error_message = $jobResult.Error
        })
        exit 0
    }

    Process-QueueOnce -Config $config

    if (-not $Once) {
        # Run single cycle by design. Polling cadence should be provided by Windows Task Scheduler.
        exit 0
    }

    exit 0
}
catch {
    $err = $_.Exception.Message
    $logDir = Join-Path $PSScriptRoot 'logs'
    if (-not (Test-Path -LiteralPath $logDir)) {
        New-Item -ItemType Directory -Path $logDir -Force | Out-Null
    }
    $logPath = Join-Path $logDir 'worker-error.log'
    $line = ('[{0}] {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $err)
    Add-Content -LiteralPath $logPath -Value $line -Encoding UTF8
    throw
}
