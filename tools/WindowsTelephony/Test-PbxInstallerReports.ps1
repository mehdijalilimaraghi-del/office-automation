#requires -Version 5.1
param([Parameter(Mandatory=$true)][string]$TrustedInstallerPath)
$ErrorActionPreference='Stop'
$root=Join-Path ([IO.Path]::GetTempPath()) ('PbxReportTests-'+[Guid]::NewGuid().ToString('N'))
[void][IO.Directory]::CreateDirectory($root)
$trusted=[IO.Path]::GetFullPath($TrustedInstallerPath)
if((Get-FileHash -LiteralPath $trusted -Algorithm SHA256).Hash.ToLowerInvariant() -ne '331cdd876c7ee73cc6d60bbdd7c9b7e60c157d3760226986fd7d6e604c83d8b6'){throw 'Trusted MSI hash mismatch'}
$source=[IO.File]::ReadAllText((Join-Path $PSScriptRoot 'Install-PbxEngine.ps1'))
$doubles=Join-Path $PSScriptRoot 'tests\InstallerDoubles.ps1'
foreach($scenario in @('success','msi_failure','postcheck_failure')){
    $code=if($scenario -eq 'msi_failure'){1603}else{0}
    $postLiteral=if($scenario -eq 'postcheck_failure'){'$true'}else{'$false'}
    $case=Join-Path $root ('case-'+$scenario);[void][IO.Directory]::CreateDirectory($case)
    $appData=Join-Path $case 'AppData'
    $prefix='$env:LOCALAPPDATA='+"'"+$appData.Replace("'","''")+"'"+[Environment]::NewLine+'$script:MockExit='+$code+[Environment]::NewLine+'$script:ServiceCalls=0'+[Environment]::NewLine
    $prefix+='$script:MockPostCheckFailure='+$postLiteral+[Environment]::NewLine
    $prefix+='Import-Module Microsoft.PowerShell.Security; Import-Module Microsoft.PowerShell.Utility'+[Environment]::NewLine
    $prefix+=". '"+$doubles.Replace("'","''")+"'"+[Environment]::NewLine
    $child=$source.Replace('$expectedHash=',$prefix+'$expectedHash=')
    $file=Join-Path $case 'Install-PbxEngine.ps1';[IO.File]::WriteAllText($file,$child,[Text.UTF8Encoding]::new($false))
    & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $file -InstallerPath $trusted *> (Join-Path $case 'child-output.txt')
    $expectedExit=if($scenario -eq 'success'){0}else{1}
    if($LASTEXITCODE -ne $expectedExit){throw ('Unexpected wrapper exit in case '+$code+'. Inspect '+$case)}
    if($scenario -eq 'success'){$reports=@(Get-ChildItem -LiteralPath $appData -Recurse -Filter 'pbx-engine-install-result.json');if(-not $reports.Count){throw 'Success report missing'};$r=Get-Content -LiteralPath $reports[0].FullName -Raw|ConvertFrom-Json;if($r.binary_path -notmatch 'FreeSwitchConsole.exe$'){throw 'Wrong installed binary name'}}
    else{$reports=@(Get-ChildItem -LiteralPath $case -File -Filter 'pbx-engine-install-failed-*.json');if(-not $reports.Count){throw 'Failure report was not copied beside installer'};$r=Get-Content -LiteralPath $reports[0].FullName -Raw|ConvertFrom-Json;if($r.msi_exit_code -ne $code -or -not $r.error){throw 'MSI failure details missing'};if(-not @(Get-ChildItem -LiteralPath $case -File -Filter 'freeswitch-install-*.log').Count){throw 'MSI failure log copy missing'}}
}
'PASS: simulated MSI success verifies FreeSwitchConsole.exe; MSI and post-check failures retain exit code, JSON/text and MSI log. No real process elevation or service operation.'
