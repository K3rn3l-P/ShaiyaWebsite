<?php
declare(strict_types=1);

require_once __DIR__ . '/security_bootstrap.php';
appApplySecurityHeaders();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/db_pdo.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function workerApiRespond(int $statusCode, array $payload): void
{
    if ($statusCode >= 500) {
        $message = (string) ($payload['message'] ?? 'internal_error');
        $actionForLog = isset($GLOBALS['workerApiCurrentAction']) ? (string) $GLOBALS['workerApiCurrentAction'] : 'unknown';
        $odbcError = function_exists('odbc_errormsg') ? (string) @odbc_errormsg() : '';
        appSecurityLog('Worker API 500 response: action=' . $actionForLog . ' message=' . $message . ($odbcError !== '' ? ' odbc=' . $odbcError : ''));
    }

    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function workerApiWebsiteDbEscaped(): string
{
    global $website_db_name;

    $dbName = trim((string) $website_db_name);
    if ($dbName === '' || !preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
        $dbName = 'PS_WebSite';
    }

    return '[' . $dbName . ']';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    workerApiRespond(405, ['ok' => false, 'message' => 'Method not allowed.']);
}

if (!appIsLoopbackIp(appGetClientIp())) {
    appSecurityLog('Blocked non-local worker API call from IP: ' . appGetClientIp());
    workerApiRespond(403, ['ok' => false, 'message' => 'Worker API is localhost-only.']);
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody)) {
    workerApiRespond(400, ['ok' => false, 'message' => 'Invalid request body.']);
}

$workerId = trim((string) ($_SERVER['HTTP_X_WORKER_ID'] ?? ''));
$timestamp = trim((string) ($_SERVER['HTTP_X_WORKER_TS'] ?? ''));
$nonce = trim((string) ($_SERVER['HTTP_X_WORKER_NONCE'] ?? ''));
$signature = trim((string) ($_SERVER['HTTP_X_WORKER_SIG'] ?? ''));

$expectedWorkerId = trim(appGetWebSetting('TaskSchedulerWorkerId', 'worker-main'));
if ($expectedWorkerId !== '' && !hash_equals($expectedWorkerId, $workerId)) {
    appSecurityLog('Worker API rejected worker_id mismatch: ' . $workerId);
    workerApiRespond(403, ['ok' => false, 'message' => 'Invalid worker identity.']);
}

if (!preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $workerId)) {
    appSecurityLog('Worker API rejected invalid worker_id format: ' . $workerId);
    workerApiRespond(403, ['ok' => false, 'message' => 'Invalid worker identity format.']);
}

$workerSharedSecret = appWorkerSharedSecret();
if ($workerSharedSecret === '') {
    $workerSharedSecret = trim(appGetWebSetting('TaskSchedulerWorkerSecret', ''));
    if ($workerSharedSecret !== '') {
        appSecurityLog('Worker API using TaskSchedulerWorkerSecret fallback from Web_Settings because WORKER_HMAC_SECRET is missing');
    }
}

if (!appVerifyWorkerSignature($rawBody, $workerId, $timestamp, $nonce, $signature, $signatureFailureReason, $workerSharedSecret)) {
    $details = [
        'worker' => $workerId,
        'reason' => (string) $signatureFailureReason,
        'ip' => appGetClientIp(),
        'ts_len' => strlen($timestamp),
        'nonce_len' => strlen($nonce),
        'sig_len' => strlen($signature),
    ];
    appSecurityLog('Worker API signature/nonce validation failed: ' . json_encode($details));
    workerApiRespond(403, ['ok' => false, 'message' => 'Invalid signature.']);
}

$data = json_decode($rawBody, true);
if (!is_array($data)) {
    workerApiRespond(400, ['ok' => false, 'message' => 'Malformed JSON.']);
}

$action = strtolower(trim((string) ($data['action'] ?? '')));
if ($action === '') {
    workerApiRespond(400, ['ok' => false, 'message' => 'Missing action.']);
}
$GLOBALS['workerApiCurrentAction'] = $action;

$schedulerEnabled = appGetWebSetting('TaskSchedulerEnabled', '0');
if ($schedulerEnabled !== '1' && $action !== 'heartbeat') {
    workerApiRespond(409, ['ok' => false, 'message' => 'Scheduler is disabled.']);
}

switch ($action) {
    case 'heartbeat':
        $websiteDbEscaped = workerApiWebsiteDbEscaped();
        $heartbeatValue = date('Y-m-d H:i:s') . '|' . $workerId;
        try {
            $existsStmt = $pdoAdmin->prepare("SELECT 1 FROM {$websiteDbEscaped}.dbo.Web_Settings WHERE SettingKey = ?");
            $existsStmt->execute(['TaskSchedulerLastHeartbeat']);
            $existsRow = $existsStmt->fetch(PDO::FETCH_ASSOC);

            if ($existsRow) {
                $updateStmt = $pdoAdmin->prepare("UPDATE {$websiteDbEscaped}.dbo.Web_Settings SET SettingValue = ? WHERE SettingKey = ?");
                $updateStmt->execute([$heartbeatValue, 'TaskSchedulerLastHeartbeat']);
            } else {
                $insertStmt = $pdoAdmin->prepare("INSERT INTO {$websiteDbEscaped}.dbo.Web_Settings (SettingKey, SettingValue) VALUES (?, ?)");
                $insertStmt->execute(['TaskSchedulerLastHeartbeat', $heartbeatValue]);
            }
        } catch (Throwable $e) {
            workerApiRespond(500, ['ok' => false, 'message' => 'Unable to persist heartbeat.']);
        }

        workerApiRespond(200, ['ok' => true, 'message' => 'heartbeat_ok']);
        break;

    case 'pull_queue':
        $websiteDbEscaped = workerApiWebsiteDbEscaped();
        $limit = isset($data['limit']) ? (int) $data['limit'] : 10;
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 30) {
            $limit = 30;
        }

        try {
            $pdoAdmin->exec("UPDATE {$websiteDbEscaped}.dbo.Web_TaskQueue SET Status = 'pending', LockedAt = NULL, LockedBy = NULL WHERE Status = 'processing' AND LockedAt < DATEADD(MINUTE, -5, GETDATE())");
        } catch (Throwable $e) {
        }

        $workerIdLiteral = "'" . str_replace("'", "''", $workerId) . "'";
        $lockSql = "WITH next_items AS (SELECT TOP ($limit) QueueID FROM {$websiteDbEscaped}.dbo.Web_TaskQueue WHERE Status = 'pending' ORDER BY QueueID ASC) UPDATE q SET q.Status = 'processing', q.LockedAt = GETDATE(), q.LockedBy = {$workerIdLiteral}, q.Attempts = q.Attempts + 1 FROM {$websiteDbEscaped}.dbo.Web_TaskQueue q INNER JOIN next_items n ON n.QueueID = q.QueueID";
        try {
            $pdoAdmin->exec($lockSql);
        } catch (Throwable $e) {
            workerApiRespond(500, ['ok' => false, 'message' => 'Unable to lock queue items.']);
        }

        $rows = [];
        try {
            $fetchStmt = $pdoAdmin->prepare("SELECT q.QueueID, q.JobID, q.QueueAction, q.Payload, q.Attempts, j.JobName, j.IsEnabled, j.Status, j.ScheduleMode, j.ScheduleValue, j.TaskType, j.ServiceName, j.CommandText, j.SqlPayload, j.SqlSource FROM {$websiteDbEscaped}.dbo.Web_TaskQueue q INNER JOIN {$websiteDbEscaped}.dbo.Web_TaskJobs j ON j.JobID = q.JobID WHERE q.Status = 'processing' AND q.LockedBy = ? AND q.ProcessedAt IS NULL ORDER BY q.QueueID ASC");
            $fetchStmt->execute([$workerId]);
        } catch (Throwable $e) {
            workerApiRespond(500, ['ok' => false, 'message' => 'Unable to fetch queue items.']);
        }

        $allowedQueueActions = ['UPSERT', 'PAUSE', 'RESUME', 'DELETE', 'RUN_NOW'];
        $allowedTaskTypes = ['SERVICE_COMMAND', 'SQL_SCRIPT'];

        while (($row = $fetchStmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $queueAction = strtoupper((string) ($row['QueueAction'] ?? ''));
            $taskType = strtoupper((string) ($row['TaskType'] ?? ''));

            if (!in_array($queueAction, $allowedQueueActions, true) || !in_array($taskType, $allowedTaskTypes, true)) {
                continue;
            }

            $rows[] = [
                'queue_id' => (int) ($row['QueueID'] ?? 0),
                'job_id' => (int) ($row['JobID'] ?? 0),
                'queue_action' => $queueAction,
                'payload' => (string) ($row['Payload'] ?? ''),
                'attempts' => (int) ($row['Attempts'] ?? 0),
                'job' => [
                    'name' => (string) ($row['JobName'] ?? ''),
                    'is_enabled' => (int) ($row['IsEnabled'] ?? 0) === 1,
                    'status' => (string) ($row['Status'] ?? ''),
                    'schedule_mode' => (string) ($row['ScheduleMode'] ?? 'simple'),
                    'schedule_value' => (string) ($row['ScheduleValue'] ?? ''),
                    'task_type' => $taskType,
                    'service_name' => (string) ($row['ServiceName'] ?? ''),
                    'command_text' => (string) ($row['CommandText'] ?? ''),
                    'sql_payload' => (string) ($row['SqlPayload'] ?? ''),
                    'sql_source' => (string) ($row['SqlSource'] ?? 'none'),
                ],
            ];
        }

        workerApiRespond(200, ['ok' => true, 'items' => $rows]);
        break;

    case 'get_job':
        $websiteDbEscaped = workerApiWebsiteDbEscaped();
        $jobId = isset($data['job_id']) ? (int) $data['job_id'] : 0;
        if ($jobId <= 0) {
            workerApiRespond(400, ['ok' => false, 'message' => 'Invalid job_id.']);
        }

        try {
            $jobStmt = $pdoAdmin->prepare("SELECT JobID, JobName, IsEnabled, Status, ScheduleMode, ScheduleValue, TaskType, ServiceName, CommandText, SqlPayload, SqlSource FROM {$websiteDbEscaped}.dbo.Web_TaskJobs WHERE JobID = ?");
            $jobStmt->execute([$jobId]);
        } catch (Throwable $e) {
            workerApiRespond(500, ['ok' => false, 'message' => 'Unable to load job.']);
        }

        $job = $jobStmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            workerApiRespond(404, ['ok' => false, 'message' => 'Job not found.']);
        }

        $taskType = strtoupper((string) ($job['TaskType'] ?? ''));
        if (!in_array($taskType, ['SERVICE_COMMAND', 'SQL_SCRIPT'], true)) {
            workerApiRespond(422, ['ok' => false, 'message' => 'Task type not allowed.']);
        }

        workerApiRespond(200, [
            'ok' => true,
            'job' => [
                'job_id' => (int) ($job['JobID'] ?? 0),
                'name' => (string) ($job['JobName'] ?? ''),
                'is_enabled' => (int) ($job['IsEnabled'] ?? 0) === 1,
                'status' => (string) ($job['Status'] ?? ''),
                'schedule_mode' => (string) ($job['ScheduleMode'] ?? ''),
                'schedule_value' => (string) ($job['ScheduleValue'] ?? ''),
                'task_type' => $taskType,
                'service_name' => (string) ($job['ServiceName'] ?? ''),
                'command_text' => (string) ($job['CommandText'] ?? ''),
                'sql_payload' => (string) ($job['SqlPayload'] ?? ''),
                'sql_source' => (string) ($job['SqlSource'] ?? 'none'),
            ],
        ]);
        break;

    case 'queue_result':
        $websiteDbEscaped = workerApiWebsiteDbEscaped();
        $queueId = isset($data['queue_id']) ? (int) $data['queue_id'] : 0;
        $jobId = isset($data['job_id']) ? (int) $data['job_id'] : 0;
        $runStatus = strtolower(trim((string) ($data['run_status'] ?? '')));
        $output = trim((string) ($data['output'] ?? ''));
        $errorMessage = trim((string) ($data['error_message'] ?? ''));

        if ($queueId <= 0 || $jobId <= 0 || !in_array($runStatus, ['success', 'error', 'skipped'], true)) {
            workerApiRespond(400, ['ok' => false, 'message' => 'Invalid queue_result payload.']);
        }

        $resultMessage = $runStatus === 'success' ? $output : $errorMessage;
        $resultMessage = mb_substr($resultMessage, 0, 4000);

        $queueStatus = $runStatus === 'success' ? 'done' : ($runStatus === 'skipped' ? 'skipped' : 'failed');
        try {
            $queueStmt = $pdoAdmin->prepare("UPDATE {$websiteDbEscaped}.dbo.Web_TaskQueue SET Status = ?, ProcessedAt = GETDATE(), ResultMessage = ? WHERE QueueID = ? AND JobID = ? AND LockedBy = ?");
            $queueStmt->execute([$queueStatus, $resultMessage, $queueId, $jobId, $workerId]);
        } catch (Throwable $e) {
            workerApiRespond(500, ['ok' => false, 'message' => 'Unable to update queue item.']);
        }

        $runOutput = $runStatus === 'success' ? mb_substr($output, 0, 4000) : '';
        $runError = $runStatus === 'success' ? '' : mb_substr($errorMessage, 0, 4000);
        try {
            $runStmt = $pdoAdmin->prepare("INSERT INTO {$websiteDbEscaped}.dbo.Web_TaskJobRuns (JobID, QueueID, RunStatus, StartedAt, FinishedAt, Output, ErrorMessage, TriggeredBy) VALUES (?, ?, ?, GETDATE(), GETDATE(), ?, ?, ?)");
            $runStmt->execute([$jobId, $queueId, $queueStatus, $runOutput, $runError, $workerId]);
        } catch (Throwable $e) {
            workerApiRespond(500, ['ok' => false, 'message' => 'Unable to log run.']);
        }

        workerApiRespond(200, ['ok' => true, 'message' => 'queue_result_logged']);
        break;

    case 'job_run_log':
        $websiteDbEscaped = workerApiWebsiteDbEscaped();
        $jobId = isset($data['job_id']) ? (int) $data['job_id'] : 0;
        $runStatus = strtolower(trim((string) ($data['run_status'] ?? '')));
        $output = trim((string) ($data['output'] ?? ''));
        $errorMessage = trim((string) ($data['error_message'] ?? ''));

        if ($jobId <= 0 || !in_array($runStatus, ['success', 'error', 'skipped'], true)) {
            workerApiRespond(400, ['ok' => false, 'message' => 'Invalid job_run_log payload.']);
        }

        $runOutput = $runStatus === 'success' ? mb_substr($output, 0, 4000) : '';
        $runError = $runStatus === 'success' ? '' : mb_substr($errorMessage, 0, 4000);
        try {
            $runStmt = $pdoAdmin->prepare("INSERT INTO {$websiteDbEscaped}.dbo.Web_TaskJobRuns (JobID, QueueID, RunStatus, StartedAt, FinishedAt, Output, ErrorMessage, TriggeredBy) VALUES (?, NULL, ?, GETDATE(), GETDATE(), ?, ?, ?)");
            $runStmt->execute([$jobId, $runStatus, $runOutput, $runError, $workerId]);
        } catch (Throwable $e) {
            workerApiRespond(500, ['ok' => false, 'message' => 'Unable to log run status.']);
        }

        workerApiRespond(200, ['ok' => true, 'message' => 'job_run_logged']);
        break;

    default:
        workerApiRespond(400, ['ok' => false, 'message' => 'Unknown action.']);
}
