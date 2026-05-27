# Task Scheduler Worker - Test Plan

## Functional tests

1. Enable Task Scheduler in admin panel.
2. Create SERVICE_COMMAND job with whitelist-safe command.
3. Verify queue row is created in `Web_TaskQueue` with action `UPSERT`.
4. Run `worker.ps1 -Once` and verify:
   - Scheduled task `ShaiyaWebJob_<JobID>` exists.
   - Queue row transitions to `done`.
5. Trigger Run Now from admin and verify queue `RUN_NOW` is processed.
6. Create SQL_SCRIPT job (inline + upload cases) and verify execution log in `Web_TaskJobRuns`.

## Security tests

1. Call worker API without signature headers -> expect HTTP 403.
2. Call worker API with invalid signature -> expect HTTP 403.
3. Replay same nonce+timestamp -> expect HTTP 403.
4. Call worker API from non-loopback source -> expect HTTP 403.
5. Configure `TaskSchedulerAllowedAdminUIDs` and verify non-allowlisted admins cannot create/toggle/run jobs.
6. Submit SERVICE_COMMAND not matching `TaskSchedulerCommandWhitelist` -> action blocked.

## Resilience tests

1. Stop worker scheduled task for 5 minutes.
2. Enqueue multiple actions from panel.
3. Restart worker and verify queue recovers in order.
4. Force failure (invalid SQL) and verify queue row status `failed` + run log error.
5. Re-run worker to ensure idempotent handling of already processed queue items.
