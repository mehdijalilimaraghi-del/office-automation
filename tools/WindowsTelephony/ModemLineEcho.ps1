#requires -Version 5.1
function Initialize-ModemLineEchoLibrary {
 param([Parameter(Mandatory=$true)][string]$LabRoot,[Parameter(Mandatory=$true)][string]$SourceDirectory)
 $expected='6a832c7492e0f3e8e637d817568b58225d9e7b87ad68c6fe5b68ddebb3adf7b7'
 $localRoot=[IO.Path]::GetFullPath($LabRoot)
 if($localRoot.StartsWith('\\') -or -not (Test-Path -LiteralPath (Join-Path $localRoot 'lab-state.json'))){throw 'Echo DSP requires the existing protected local PBX profile'}
 # Refuse redirected ancestors, including a pre-existing dsp directory/file.
 $target=Join-Path $localRoot 'dsp/libspeexdsp-1.dll'
 $ancestor=$target
 while($ancestor){if(Test-Path -LiteralPath $ancestor){if(([IO.File]::GetAttributes($ancestor) -band [IO.FileAttributes]::ReparsePoint) -ne 0){throw 'Echo DSP local path must not be redirected'}};$parent=[IO.Path]::GetDirectoryName($ancestor);if($parent -eq $ancestor){break};$ancestor=$parent}
 $source=Join-Path $SourceDirectory 'libspeexdsp-1.dll'
 if(-not (Test-Path -LiteralPath $source) -or -not (Test-Path -LiteralPath (Join-Path $SourceDirectory 'LICENSE'))){throw 'Echo DSP package or license is missing; use the complete line-echo test package'}
 if((Get-FileHash -LiteralPath $source -Algorithm SHA256).Hash.ToLowerInvariant() -ne $expected){throw 'Echo DSP package integrity check failed'}
 [void][IO.Directory]::CreateDirectory((Split-Path -Parent $target))
 if(Test-Path -LiteralPath $target){if((Get-FileHash -LiteralPath $target -Algorithm SHA256).Hash.ToLowerInvariant() -ne $expected){throw 'Existing local echo DSP differs from the pinned release; no file changed'}}else{
  $bytes=[IO.File]::ReadAllBytes($source)
  $hash=[Security.Cryptography.SHA256]::Create();try{$actual=[BitConverter]::ToString($hash.ComputeHash($bytes)).Replace('-','').ToLowerInvariant()}finally{$hash.Dispose()}
  if($actual -ne $expected){throw 'Echo DSP changed during copy; no local DLL created'}
  $stream=[IO.File]::Open($target,[IO.FileMode]::CreateNew,[IO.FileAccess]::Write,[IO.FileShare]::None);try{$stream.Write($bytes,0,$bytes.Length)}finally{$stream.Dispose()}
 }
 [IO.File]::WriteAllText((Join-Path (Split-Path -Parent $target) 'LICENSE'),[IO.File]::ReadAllText((Join-Path $SourceDirectory 'LICENSE')),[Text.UTF8Encoding]::new($false))
 return $target
}