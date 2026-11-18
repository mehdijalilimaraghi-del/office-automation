# Application scheduler

This task is separate from the deployment watcher. It runs Laravel scheduling every minute, including reminders:dispatch. Database notifications need no queue worker or external SMS/email transport.

## Server activation

Installing this task on a server is a separately approved deployment operation.

1. Deploy the additive reminder migration and verify migration status.
2. Copy both scripts into the persistent deployment Scripts directory. ApplicationPath must be the actual active application junction, never a numbered release or staging directory.
3. Use the actual PHP executable and a persistent log path. The service account needs application, shared-storage, log, database and trusted SQL-certificate access.
4. Run Register-OfficeAutomationScheduler.ps1 with ApplicationPath, PhpPath, RunnerPath, LogPath and Credential (Get-Credential). Credentials are passed to Windows, not stored in the scripts. Existing tasks are never overwritten. Use -WhatIf for review.
5. Verify artisan schedule:list, LastTaskResult, scheduler and Laravel logs. Create a controlled reminder due shortly, confirm its stored notification and visible browser message. Check delivery while signed out and visibility after signing in.
6. Reboot and confirm the task runs without interactive sign-in. The stable junction must follow subsequent approved releases.
7. Disable only OfficeAutomation Application Scheduler to pause dispatch, preserving the deployment watcher. Stop scheduling before rolling back the reminder migration.

The runner reports failures and logs each invocation. Rotate its log using the established server log-retention policy.

## Delivery rules

Task reminders send once per reminder and scheduled timestamp. A changed timestamp permits another delivery. Finalized tasks, cancelled/completed reminders and inactive or unauthorized recipients are skipped. Late runs deliver still-pending overdue reminders.

Active physical checkouts notify the responsible archivist (fallback: unit manager, then creator) and internal holder one day before and on the due date, then daily overdue. After three overdue days the manager is included. Returning the document stops subsequent notifications. New checkouts have distinct delivery identities. External and legacy free-text holders are followed up by the archive.

Recipients without document-view access receive generic text without document titles or links. The ledger and database notification are atomic, using unique delivery keys and user locks. Future SMS delivery can extend notification channels but must use an outbox/retry adapter instead of external calls inside the current database transaction.
