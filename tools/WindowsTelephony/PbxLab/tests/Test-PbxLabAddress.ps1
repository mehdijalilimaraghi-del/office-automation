#requires -Version 5.1
$ErrorActionPreference='Stop'
Import-Module (Join-Path $PSScriptRoot '../PbxLab.psm1') -Force -DisableNameChecking
$root=Join-Path $env:TEMP ('office-pbx-address-'+[guid]::NewGuid().ToString('N'))
try {
 $state=New-PbxLabProfile -Root $root -BindAddress '192.168.10.10'
 $passwords=$state.accounts.password -join ',';$identity=$state.profile_id
 & (Get-Module PbxLab) {
  param($root,$passwords,$identity)
  function Get-Process {}
  function Get-Service {}
  function Get-NetIPAddress {@([pscustomobject]@{IPAddress='192.168.1.101';InterfaceIndex=11;SkipAsSource=$false})}
  function Get-NetIPConfiguration {@([pscustomobject]@{InterfaceIndex=11;IPv4DefaultGateway='192.168.1.1'})}
  $updated=Sync-PbxLabAddress $root
  if($updated.bind_address -ne '192.168.1.101'){throw 'Address not updated'}
  if(($updated.accounts.password -join ',') -ne $passwords -or $updated.profile_id -ne $identity){throw 'Credentials or identity changed'}
  if(-not ([IO.File]::ReadAllText((Join-Path $root 'accounts.html'))).Contains('192.168.1.101:5160')){throw 'Account instructions stale'}
  if(([IO.File]::ReadAllText((Join-Path $root 'conf/freeswitch.xml'))).Contains('192.168.10.10')){throw 'Old IP retained in engine config'}
  $before=(Get-FileHash (Join-Path $root 'lab-state.json')).Hash
  [void](Sync-PbxLabAddress $root)
  if((Get-FileHash (Join-Path $root 'lab-state.json')).Hash -ne $before){throw 'Unnecessary profile rewrite'}
  function Get-NetIPAddress {@([pscustomobject]@{IPAddress='192.168.2.101';InterfaceIndex=12},[pscustomobject]@{IPAddress='192.168.3.101';InterfaceIndex=13})}
  function Get-NetIPConfiguration {}
  $rejected=$false;try{Sync-PbxLabAddress $root|Out-Null}catch{$rejected=$true};if(-not $rejected -or (Get-FileHash (Join-Path $root 'lab-state.json')).Hash -ne $before){throw 'Ambiguous network modified profile'}
  function Get-Process {[pscustomobject]@{Id=123}}
  $rejected=$false;try{Sync-PbxLabAddress $root|Out-Null}catch{$rejected=$true};if(-not $rejected){throw 'Active engine configuration modified'}
 } $root $passwords $identity
 Write-Host 'PASS: 7 automatic address migration checks'
}finally{
 $resolved=[IO.Path]::GetFullPath($root);$expected=[IO.Path]::GetFullPath($env:TEMP).TrimEnd('\')+'\office-pbx-address-'
 if($resolved.StartsWith($expected,[StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $resolved)){Remove-Item -LiteralPath $resolved -Recurse -Force}
}