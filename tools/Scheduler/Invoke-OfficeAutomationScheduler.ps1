[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$ApplicationPath,
    [Parameter(Mandatory)][string]$PhpPath,
    [Parameter(Mandatory)][string]$LogPath
)
$ErrorActionPreference = 'Stop'
$application = (Resolve-Path -LiteralPath $ApplicationPath).Path
$php = (Resolve-Path -LiteralPath $PhpPath).Path
$artisan = Join-Path $application 'artisan'
if (-not (Test-Path -LiteralPath $artisan -PathType Leaf)) { throw 'The active application path does not contain artisan.' }
$logDirectory = Split-Path -Parent $LogPath
if (-not (Test-Path -LiteralPath $logDirectory)) { New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null }
Push-Location -LiteralPath $application
try {
    $output = & $php $artisan schedule:run --no-interaction 2>&1
    $schedulerExit = $LASTEXITCODE
    Add-Content -LiteralPath $LogPath -Value ("[{0:o}] exit={1}" -f (Get-Date), $schedulerExit)
    $output | Out-File -LiteralPath $LogPath -Append -Encoding utf8
    if ($schedulerExit -ne 0) { throw "Scheduler failed with exit code $schedulerExit. See $LogPath" }
} finally {
    Pop-Location
}
