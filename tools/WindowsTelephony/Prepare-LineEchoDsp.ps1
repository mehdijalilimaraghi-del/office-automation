#requires -Version 5.1
[CmdletBinding()]
param([Parameter(Mandatory=$true)][string]$OutputDirectory)
$ErrorActionPreference='Stop'
$target=[IO.Path]::GetFullPath($OutputDirectory)
if(Test-Path -LiteralPath $target){throw 'Choose a new output directory; existing files are preserved'}
[void][IO.Directory]::CreateDirectory($target)
$archive=Join-Path $target 'speexdsp.pkg.tar.zst'
[Net.ServicePointManager]::SecurityProtocol=[Net.SecurityProtocolType]::Tls12
Invoke-WebRequest -UseBasicParsing -Uri 'https://mirror.msys2.org/mingw/mingw64/mingw-w64-x86_64-speexdsp-1.2.1-2-any.pkg.tar.zst' -OutFile $archive
if((Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash.ToLowerInvariant() -ne 'c34c5a5f3b3a947fe3c5d0108346964b5ac026e6143f987bdd9e5726d890a29c'){throw 'DSP archive hash mismatch; extraction refused'}
# Extract only fixed, pinned archive members. Never execute an installer or add global packages.
& tar.exe -xf $archive -C $target 'mingw64/bin/libspeexdsp-1.dll' 'mingw64/share/licenses/speexdsp/LICENSE'
if($LASTEXITCODE -ne 0){throw 'DSP extraction failed'}
$dll=Join-Path $target 'mingw64/bin/libspeexdsp-1.dll'
if((Get-FileHash -LiteralPath $dll -Algorithm SHA256).Hash.ToLowerInvariant() -ne '6a832c7492e0f3e8e637d817568b58225d9e7b87ad68c6fe5b68ddebb3adf7b7'){throw 'Extracted DSP hash mismatch'}
$dsp=Join-Path $target 'Dsp';[void][IO.Directory]::CreateDirectory($dsp)
Copy-Item -LiteralPath $dll -Destination (Join-Path $dsp 'libspeexdsp-1.dll')
Copy-Item -LiteralPath (Join-Path $target 'mingw64/share/licenses/speexdsp/LICENSE') -Destination (Join-Path $dsp 'LICENSE')
Write-Host ('Verified SpeexDSP 1.2.1-2 x64 package: '+$dsp)