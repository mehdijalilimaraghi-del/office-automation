#requires -Version 5.1
param([Parameter(Mandatory=$true)][string]$TrustedInstallerPath)
$ErrorActionPreference='Stop'
$fixtureRoot=Join-Path ([IO.Path]::GetTempPath()) ('OfficePbx-'+[Guid]::NewGuid().ToString('N'))
if(Test-Path -LiteralPath $fixtureRoot){throw 'Fixture path already exists.'}
$fixture=Join-Path $fixtureRoot 'پوشه فارسی با فاصله'
[void][IO.Directory]::CreateDirectory($fixture)
try {
$source=Join-Path $PSScriptRoot 'Install-PbxEngine.ps1'
$body=[IO.File]::ReadAllText($source)
# All child copies prohibit process elevation even if argument forwarding regresses.
$guarded=$body.Replace('$expectedHash=', "function Start-Process { throw 'TEST_SAFETY: installation is forbidden' }"+[Environment]::NewLine+'$expectedHash=')
[IO.File]::WriteAllText((Join-Path $fixture 'Install-PbxEngine.ps1'),$guarded,[Text.UTF8Encoding]::new($true))
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'Install-PbxEngine.cmd') -Destination $fixture
$msi=Join-Path $fixture 'FreeSWITCH-1.11.4-Release-x64.msi'
try {New-Item -ItemType HardLink -Path $msi -Target ([IO.Path]::GetFullPath($TrustedInstallerPath)) -ErrorAction Stop|Out-Null}
catch {Copy-Item -LiteralPath $TrustedInstallerPath -Destination $msi}
$child=Join-Path $fixture 'Install-PbxEngine.ps1'
& powershell.exe -NoProfile -ExecutionPolicy Bypass -File $child -CheckOnly
if($LASTEXITCODE -ne 0){throw 'Default path failed in Persian folder.'}
& powershell.exe -NoProfile -ExecutionPolicy Bypass -File $child -CheckOnly -InstallerPath $msi
if($LASTEXITCODE -ne 0){throw 'Explicit path failed in Persian folder.'}
# Force the root fallback in a child copy; MyCommand.Path must resolve the source file.
$fallback=$guarded.Replace('$scriptDirectory=$PSScriptRoot',('$scriptDirectory='+"''")).Replace('$scriptFile=$PSCommandPath',('$scriptFile='+"''"))
$fallbackPath=Join-Path $fixture 'Fallback.ps1'
[IO.File]::WriteAllText($fallbackPath,$fallback,[Text.UTF8Encoding]::new($true))
& powershell.exe -NoProfile -ExecutionPolicy Bypass -File $fallbackPath -CheckOnly
if($LASTEXITCODE -ne 0){throw 'MyCommand.Path fallback failed.'}
Push-Location -LiteralPath $fixture
try {
    & cmd.exe /d /c 'Install-PbxEngine.cmd -CheckOnly <nul'
    $cmdExit=$LASTEXITCODE
} finally {Pop-Location}
if($cmdExit -ne 0){throw 'CMD launcher path or flag forwarding failed.'}
'PASS: default, explicit, missing-root fallback and CMD launch from a Persian path; installation blocked in every child.'

} finally {
    # Delete only the four named files created by this test; directories must be empty.
    foreach($name in @('Install-PbxEngine.ps1','Install-PbxEngine.cmd','Fallback.ps1','FreeSWITCH-1.11.4-Release-x64.msi')){
        $owned=Join-Path $fixture $name
        if([IO.File]::Exists($owned)){[IO.File]::Delete($owned)}
    }
    [IO.Directory]::Delete($fixture)
    [IO.Directory]::Delete($fixtureRoot)
}
