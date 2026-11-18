[CmdletBinding(SupportsShouldProcess)]
param(
    [Parameter(Mandatory)][string]$ApplicationPath,
    [Parameter(Mandatory)][string]$PhpPath,
    [Parameter(Mandatory)][string]$RunnerPath,
    [Parameter(Mandatory)][string]$LogPath,
    [Parameter(Mandatory)][PSCredential]$Credential
)
$ErrorActionPreference = 'Stop'
$application = (Resolve-Path -LiteralPath $ApplicationPath).Path
$php = (Resolve-Path -LiteralPath $PhpPath).Path
$runner = (Resolve-Path -LiteralPath $RunnerPath).Path
if (-not (Test-Path -LiteralPath (Join-Path $application 'artisan'))) { throw 'ApplicationPath must point to the active application junction.' }
foreach ($taskArgument in @($application, $php, $runner, $LogPath)) {
    if ($taskArgument.Contains('"')) { throw 'Task paths cannot contain quotation marks.' }
}
$taskName = 'OfficeAutomation Application Scheduler'
if (Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue) {
    throw 'The application scheduler task already exists. Inspect it before updating.'
}
$arguments = '-NoProfile -NonInteractive -ExecutionPolicy Bypass -File "{0}" -ApplicationPath "{1}" -PhpPath "{2}" -LogPath "{3}"' -f $runner, $application, $php, $LogPath
$action = New-ScheduledTaskAction -Execute "$env:SystemRoot\System32\WindowsPowerShell\v1.0\powershell.exe" -Argument $arguments
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 10)
if ($PSCmdlet.ShouldProcess($taskName, 'Register the application scheduler every minute')) {
    Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -User $Credential.UserName -Password ($Credential.GetNetworkCredential().Password) -RunLevel Limited | Out-Null
    Write-Output 'Scheduler registered. Verify its result, the Laravel schedule and logs under the configured service account.'
}
