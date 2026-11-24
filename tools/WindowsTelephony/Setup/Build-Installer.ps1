#requires -Version 5.1
[CmdletBinding()]
param([Parameter(Mandatory=$true)][string]$EngineDirectory,[Parameter(Mandatory=$true)][string]$DspDirectory,[Parameter(Mandatory=$true)][string]$OutputDirectory)
$ErrorActionPreference='Stop'
$sourceRoot=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'));$output=[IO.Path]::GetFullPath($OutputDirectory)
if(Test-Path -LiteralPath $output){throw 'Choose a new output directory'}
if((Get-FileHash -LiteralPath (Join-Path $EngineDirectory 'FreeSwitchConsole.exe')).Hash.ToLowerInvariant() -ne 'b8e1b67d86de9fca27305464147df33a87118f513ed93d82074422eb56ff4cdc'){throw 'Unverified PBX engine'}
if((Get-FileHash -LiteralPath (Join-Path $DspDirectory 'libspeexdsp-1.dll')).Hash.ToLowerInvariant() -ne '6a832c7492e0f3e8e637d817568b58225d9e7b87ad68c6fe5b68ddebb3adf7b7'){throw 'Unverified DSP'}
[void][IO.Directory]::CreateDirectory($output);$payload=Join-Path $output 'payload';[void][IO.Directory]::CreateDirectory($payload)
foreach($name in @('VoiceDiagnostics.cs','LocalSipAudioEndpoint.cs','GreetingPrompt.cs','GreetingModeComparison.cs','ModemGreetingBridge.cs','ModemLineEcho.cs','ModemLineEcho.ps1','Start-ModemBridge.ps1','Start-OfficeTelephony.cmd','ModemHelper.ps1','Install.ps1','VoiceTest.ps1','Prepare-ModemBridge.ps1','Prepare-ModemBridge.cmd','Enable-ModemIvr.ps1','Enable-ModemIvr.cmd','Record-IvrGreeting.ps1','Record-IvrGreeting.cmd','PromptRecorder.cs','MODEM-BRIDGE.fa.md','TelephonyConnection.psm1','TelephonyConfiguration.psm1','TelephonyDesktop.psm1','Invoke-TelephonyOperation.ps1')){Copy-Item -LiteralPath (Join-Path $sourceRoot $name) -Destination (Join-Path $payload $name)}
foreach($name in @('PbxLab.psm1','Normalize-Prompt.cs','freeswitch.template.xml','Configure-PbxLab.ps1','Start-PbxLab.ps1','Start-PbxLab.cmd','Stop-PbxLab.ps1','Stop-PbxLab.cmd','Apply-OfficeConfig.ps1','Apply-OfficeConfig.cmd','Collect-PbxLabDiagnostics.ps1','Collect-PbxLabDiagnostics.cmd')){Copy-Item -LiteralPath (Join-Path (Join-Path $sourceRoot 'PbxLab') $name) -Destination (Join-Path $payload $name)}
$dsp=Join-Path $payload 'Dsp';[void][IO.Directory]::CreateDirectory($dsp);foreach($name in @('libspeexdsp-1.dll','LICENSE')){Copy-Item -LiteralPath (Join-Path $DspDirectory $name) -Destination (Join-Path $dsp $name)}
$engine=Join-Path $payload 'Engine';[void][IO.Directory]::CreateDirectory($engine)
foreach($item in Get-ChildItem -LiteralPath $EngineDirectory -Force){if($item.Name -in @('log','db','run','cache','temp','storage','recordings','lab-state.json','lab-session.json','accounts.html')){continue};if(($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0){throw 'Redirected engine path'};Copy-Item -LiteralPath $item.FullName -Destination (Join-Path $engine $item.Name) -Recurse}
$launcher=@('@echo off','powershell.exe -NoProfile -ExecutionPolicy Bypass -STA -File "%~dp0Start-ModemBridge.ps1" -DesktopMode -EngineDirectory "%~dp0Engine" %*','set "OfficeReceiverExit=%ERRORLEVEL%"','pause','exit /b %OfficeReceiverExit%') -join [Environment]::NewLine
[IO.File]::WriteAllText((Join-Path $payload 'Start-OfficeTelephony.cmd'),$launcher+[Environment]::NewLine,[Text.ASCIIEncoding]::new())
$compiler=Join-Path $env:WINDIR 'Microsoft.NET/Framework64/v4.0.30319/csc.exe'
$icon=Join-Path ([IO.Path]::GetFullPath((Join-Path $sourceRoot '../..'))) 'public/favicon.ico'
# The web favicon can be empty; generate a native, high-contrast application icon.
Add-Type -AssemblyName System.Drawing
$bitmap=[Drawing.Bitmap]::new(64,64);$graphics=[Drawing.Graphics]::FromImage($bitmap)
try {
 $graphics.SmoothingMode='AntiAlias';$graphics.Clear([Drawing.Color]::FromArgb(67,56,202))
 $pen=[Drawing.Pen]::new([Drawing.Color]::White,7);$pen.StartCap='Round';$pen.EndCap='Round'
 try {$graphics.DrawArc($pen,15,12,34,38,35,110);$graphics.DrawLine($pen,18,23,23,16);$graphics.DrawLine($pen,43,46,49,40)} finally {$pen.Dispose()}
 $icon=Join-Path $payload 'app.ico';$nativeIcon=[Drawing.Icon]::FromHandle($bitmap.GetHicon());$stream=[IO.File]::Create($icon)
 try {$nativeIcon.Save($stream)} finally {$stream.Dispose();$nativeIcon.Dispose()}
} finally {$graphics.Dispose();$bitmap.Dispose()}
& $compiler /nologo /target:winexe /platform:x64 /optimize+ /reference:System.Windows.Forms.dll ('/win32icon:'+$icon) ('/out:'+(Join-Path $payload 'OfficeAutomation.Telephony.exe')) (Join-Path $PSScriptRoot 'Launcher.cs')
if($LASTEXITCODE -ne 0){throw 'Application launcher compilation failed'}
$manifest=[Collections.Generic.List[string]]::new();foreach($file in Get-ChildItem -LiteralPath $payload -Recurse -File | Sort-Object FullName){$relative=$file.FullName.Substring($payload.Length+1).Replace('\','/');$manifest.Add((Get-FileHash -LiteralPath $file.FullName).Hash.ToLowerInvariant()+'  '+$relative)}
[IO.File]::WriteAllLines((Join-Path $payload 'SHA256SUMS'),$manifest,[Text.UTF8Encoding]::new($false))
Add-Type -AssemblyName System.IO.Compression.FileSystem;$zip=Join-Path $output 'payload.zip';[IO.Compression.ZipFile]::CreateFromDirectory($payload,$zip,[IO.Compression.CompressionLevel]::Optimal,$false)
$compiler=Join-Path $env:WINDIR 'Microsoft.NET/Framework64/v4.0.30319/csc.exe';$exe=Join-Path $output 'OfficeAutomation-Telephony-Setup.exe'
& $compiler /nologo /target:winexe /platform:x64 /optimize+ /reference:System.Windows.Forms.dll /reference:System.Drawing.dll /reference:System.IO.Compression.dll /reference:System.IO.Compression.FileSystem.dll ('/win32icon:'+$icon) ('/resource:'+$zip+',OfficeAutomation.Payload.zip') ('/out:'+$exe) (Join-Path $PSScriptRoot 'Installer.cs')
if($LASTEXITCODE -ne 0){throw 'Setup compilation failed'}
$check=Start-Process -FilePath $exe -ArgumentList '--verify' -WindowStyle Hidden -Wait -PassThru;if($check.ExitCode -ne 0){throw 'Embedded verification failed'}
Write-Output ('Built and verified: '+$exe);Write-Output ('SHA256: '+(Get-FileHash -LiteralPath $exe).Hash)
