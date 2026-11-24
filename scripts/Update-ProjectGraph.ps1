#requires -Version 5.1
[CmdletBinding()]
param([switch]$Rebuild)
$ErrorActionPreference='Stop'
$projectRoot=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$command=Get-Command graphify -ErrorAction SilentlyContinue
$graphify=if($command){$command.Source}else{Join-Path $env:USERPROFILE '.local\bin\graphify.exe'}
if(-not (Test-Path -LiteralPath $graphify)){throw 'Graphify is not installed. Run uv tool install graphifyy, then graphify install --platform codex.'}
if(-not (Test-Path -LiteralPath (Join-Path $projectRoot 'artisan'))){throw 'Office Automation project root could not be verified'}
Push-Location -LiteralPath $projectRoot
try{
 if(-not $Rebuild -and (Test-Path -LiteralPath 'graphify-out/graph.json')){& $graphify update .}else{& $graphify extract . --code-only --max-workers 2}
 if($LASTEXITCODE -ne 0){throw ('Graphify failed with exit code '+$LASTEXITCODE)}
 & $graphify cluster-only .
 if($LASTEXITCODE -ne 0){throw ('Graphify report generation failed with exit code '+$LASTEXITCODE)}
}finally{Pop-Location}
