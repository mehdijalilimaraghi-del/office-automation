#requires -Version 5.1
[CmdletBinding()]
param([string]$LabRoot,[string]$ConfigurationPath,[switch]$NoBrowser)
$ErrorActionPreference='Stop'
try {
 Import-Module (Join-Path $PSScriptRoot 'PbxLab.psm1') -Force -DisableNameChecking
 if(-not $LabRoot){$LabRoot=Join-Path $env:LOCALAPPDATA 'OfficeAutomation\Telephony\PbxLab'}
 if(-not $ConfigurationPath){$ConfigurationPath=Join-Path $PSScriptRoot 'office-telephony.json'}
 $LabRoot=[IO.Path]::GetFullPath($LabRoot)
 $configuration=Get-Content -LiteralPath $ConfigurationPath -Raw -Encoding UTF8|ConvertFrom-Json
 Import-PbxOfficeConfiguration -Root $LabRoot -Configuration $configuration -SourceDirectory ([IO.Path]::GetDirectoryName([IO.Path]::GetFullPath($ConfigurationPath)))
 Write-Host 'Office phone configuration applied. Existing matching extensions kept their passwords.' -ForegroundColor Green
 Write-Host 'Start the PBX lab, then update SIP Server AND SIP Proxy using the private account page.'
 if(-not $NoBrowser){Start-Process -FilePath (Join-Path $LabRoot 'accounts.html')}
}catch{Write-Host ('ERROR: '+$_.Exception.Message) -ForegroundColor Red;exit 1}
