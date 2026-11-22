#requires -Version 5.1
[CmdletBinding()]
param([string]$LabRoot,[string]$BindAddress,[string[]]$Extensions=@('1001','1002'),[int]$SipPort=5160,[int]$EslPort=18021)
$ErrorActionPreference='Stop'
Import-Module (Join-Path $PSScriptRoot 'PbxLab.psm1') -Force -DisableNameChecking
if(-not $LabRoot){$LabRoot=Join-Path $env:LOCALAPPDATA 'OfficeAutomation\Telephony\PbxLab'}
$LabRoot=[IO.Path]::GetFullPath($LabRoot)
if(Test-Path -LiteralPath (Join-Path $LabRoot 'lab-state.json')){
    $existing=Read-PbxLabState $LabRoot
    if($BindAddress -and $BindAddress -ne $existing.bind_address){throw 'An existing lab profile uses a different address. Existing settings were preserved.'}
    if(($PSBoundParameters.ContainsKey('Extensions') -and ($Extensions -join ',') -ne (($existing.accounts|ForEach-Object {$_.extension}) -join ',')) -or ($PSBoundParameters.ContainsKey('SipPort') -and $SipPort -ne $existing.sip_port) -or ($PSBoundParameters.ContainsKey('EslPort') -and $EslPort -ne $existing.esl_port)){throw 'Existing profile differs. Use a separate LabRoot for a different lab; existing credentials were preserved.'}
    Write-Output $LabRoot;return
}
if(-not $BindAddress){$BindAddress=Get-PbxLabLocalAddress}
New-PbxLabProfile -Root $LabRoot -BindAddress $BindAddress -Extensions $Extensions -SipPort $SipPort -EslPort $EslPort | Out-Null
Write-Output $LabRoot