#requires -Version 5.1
$ErrorActionPreference='Stop'
Add-Type -AssemblyName System.Security
function Resolve-TelephonyConnectionRoot([string]$DataRoot) {
 if(-not $DataRoot){$DataRoot=Join-Path $env:LOCALAPPDATA 'OfficeAutomation\Telephony\Connection'}
 $path=[IO.Path]::GetFullPath($DataRoot)
 if($path.StartsWith('\\')){throw 'connection_requires_local_storage'}
 for($parent=$path;$parent;$parent=[IO.Path]::GetDirectoryName($parent)){if(Test-Path -LiteralPath $parent){if(([IO.File]::GetAttributes($parent) -band [IO.FileAttributes]::ReparsePoint) -ne 0){throw 'connection_storage_redirected'}}}
 if(-not (Test-Path -LiteralPath $path)){
  [void][IO.Directory]::CreateDirectory($path);$acl=[Security.AccessControl.DirectorySecurity]::new();$sid=[Security.Principal.WindowsIdentity]::GetCurrent().User
  $acl.SetOwner($sid);$acl.SetAccessRuleProtection($true,$false)
  foreach($identity in @($sid,[Security.Principal.SecurityIdentifier]::new('S-1-5-18'))){$acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new($identity,'FullControl','ContainerInherit,ObjectInherit','None','Allow'))}
  Set-Acl -LiteralPath $path -AclObject $acl
 }
 return $path
}
function Write-TelephonyPrivateJson([string]$Path,$Value) {
 if((Test-Path -LiteralPath $Path) -and (([IO.File]::GetAttributes($Path) -band [IO.FileAttributes]::ReparsePoint) -ne 0)){throw 'connection_storage_redirected'}
 $temporary=$Path+'.'+[guid]::NewGuid().ToString('N')+'.new'
 try{[IO.File]::WriteAllText($temporary,($Value|ConvertTo-Json -Depth 12),[Text.UTF8Encoding]::new($false));if(Test-Path -LiteralPath $Path){[IO.File]::Replace($temporary,$Path,[System.Management.Automation.Language.NullString]::Value)}else{[IO.File]::Move($temporary,$Path)}}finally{if(Test-Path -LiteralPath $temporary){[IO.File]::Delete($temporary)}}
}
function Resolve-TelephonyServer([string]$ServerUrl) {
 $uri=$null
 if(-not [uri]::TryCreate($ServerUrl,[UriKind]::Absolute,[ref]$uri) -or $uri.Scheme -notin @('http','https') -or $uri.UserInfo -or $uri.Query -or $uri.Fragment){throw 'invalid_server_url'}
 return [uri]($uri.AbsoluteUri.TrimEnd('/')+'/')
}
function Read-TelephonyPrivateConnection([string]$Root) {
 $path=Join-Path $Root 'connection.json'
 if(-not (Test-Path -LiteralPath $path)){throw 'connection_missing'}
 if(([IO.File]::GetAttributes($path) -band [IO.FileAttributes]::ReparsePoint) -ne 0){throw 'connection_storage_redirected'}
 $value=[IO.File]::ReadAllText($path)|ConvertFrom-Json
 $entropy=[Text.Encoding]::UTF8.GetBytes('OfficeAutomation.Telephony.Connection.v1')
 $bytes=[Security.Cryptography.ProtectedData]::Unprotect([Convert]::FromBase64String($value.protected_token),$entropy,[Security.Cryptography.DataProtectionScope]::CurrentUser)
 try{$token=[Text.Encoding]::ASCII.GetString($bytes)}finally{[Array]::Clear($bytes,0,$bytes.Length)}
 if($token -notmatch '\A[a-f0-9]{64}\z' -or [long]$value.device_id -le 0){throw 'invalid_connection_record'}
 return [pscustomobject]@{server=(Resolve-TelephonyServer $value.server_url);device_id=[long]$value.device_id;name=[string]$value.name;token=$token}
}
function Read-TelephonySyncState([string]$Root) {
 $path=Join-Path $Root 'sync-state.json'
 if(Test-Path -LiteralPath $path){return ([IO.File]::ReadAllText($path)|ConvertFrom-Json)}
 return [pscustomobject]@{applied_revision=$null;configuration_revision=$null;pending_revision=$null;pending_path=$null;error_code=$null;last_contact_at=$null}
}
function Invoke-TelephonyHttp($Connection,[string]$Action,[string]$Method,$Body,[string]$OutputPath) {
 $uri=[uri]::new($Connection.server,('telephony/agent/'+$Action))
 $headers=@{'Accept'='application/json';'Cache-Control'='no-cache'}
 if($Connection.token){$headers.Authorization='Bearer '+$Connection.token}
 $parameters=@{Uri=$uri;Method=$Method;Headers=$headers;UseBasicParsing=$true;TimeoutSec=12;MaximumRedirection=0;ErrorAction='Stop'}
 if($null -ne $Body){$parameters.ContentType='application/json; charset=utf-8';$parameters.Body=[Text.Encoding]::UTF8.GetBytes(($Body|ConvertTo-Json -Depth 8 -Compress))}
 if($OutputPath){$parameters.OutFile=$OutputPath;$parameters.PassThru=$true}
 [Net.ServicePointManager]::SecurityProtocol=[Net.SecurityProtocolType]::Tls12
 return Invoke-WebRequest @parameters
}
function Get-TelephonyConnection {
 [CmdletBinding()]param([string]$DataRoot)
 $root=Resolve-TelephonyConnectionRoot $DataRoot;$state=Read-TelephonySyncState $root;$file=Join-Path $root 'connection.json'
 if(-not (Test-Path -LiteralPath $file)){return [pscustomobject]@{connected=$false;server_url=$null;device_id=$null;name=$null;state=$state}}
 $metadata=[IO.File]::ReadAllText($file)|ConvertFrom-Json
 return [pscustomobject]@{connected=$true;server_url=$metadata.server_url;device_id=$metadata.device_id;name=$metadata.name;state=$state}
}
function Connect-TelephonyAutomation {
 [CmdletBinding()]param([Parameter(Mandatory=$true)][string]$ServerUrl,[Parameter(Mandatory=$true)][string]$PairingCode,[string]$DataRoot)
 if($PairingCode -notmatch '\A[a-f0-9]{64}\z'){throw 'invalid_pairing_code'}
 $root=Resolve-TelephonyConnectionRoot $DataRoot;$connection=[pscustomobject]@{server=(Resolve-TelephonyServer $ServerUrl);token=$null}
 $response=Invoke-TelephonyHttp $connection 'pair' 'POST' @{pairing_code=$PairingCode;computer_name=$env:COMPUTERNAME} $null
 $value=$response.Content|ConvertFrom-Json
 if([string]$value.token -notmatch '\A[a-f0-9]{64}\z' -or [long]$value.device_id -le 0){throw 'invalid_pairing_response'}
 $entropy=[Text.Encoding]::UTF8.GetBytes('OfficeAutomation.Telephony.Connection.v1');$bytes=[Text.Encoding]::ASCII.GetBytes([string]$value.token)
 try{$protected=[Convert]::ToBase64String([Security.Cryptography.ProtectedData]::Protect($bytes,$entropy,[Security.Cryptography.DataProtectionScope]::CurrentUser))}finally{[Array]::Clear($bytes,0,$bytes.Length)}
 Write-TelephonyPrivateJson (Join-Path $root 'connection.json') @{server_url=$connection.server.AbsoluteUri;device_id=[long]$value.device_id;name=[string]$value.name;protected_token=$protected}
 Write-TelephonyPrivateJson (Join-Path $root 'sync-state.json') ([pscustomobject]@{applied_revision=$null;configuration_revision=$null;pending_revision=$null;pending_path=$null;error_code=$null;last_contact_at=$null})
 return Get-TelephonyConnection -DataRoot $root
}
function Read-TelephonyZipBytes($Entry,[int]$Maximum) {
 if(-not $Entry -or $Entry.Length -gt $Maximum){throw 'configuration_package_invalid'}
 $memory=[IO.MemoryStream]::new();$stream=$Entry.Open()
 try{$stream.CopyTo($memory);return ,$memory.ToArray()}finally{$stream.Dispose();$memory.Dispose()}
}
function Get-TelephonyHash([byte[]]$Bytes){$hash=[Security.Cryptography.SHA256]::Create();try{return [BitConverter]::ToString($hash.ComputeHash($Bytes)).Replace('-','').ToLowerInvariant()}finally{$hash.Dispose()}}
function Save-TelephonyPendingPackage($Connection,[string]$Root) {
 Add-Type -AssemblyName System.IO.Compression;Add-Type -AssemblyName System.IO.Compression.FileSystem
 $directory=Join-Path $Root ('pending-'+[guid]::NewGuid().ToString('N'));[void][IO.Directory]::CreateDirectory($directory)
 $zipPath=Join-Path $directory 'configuration.zip'
 $response=Invoke-TelephonyHttp $Connection 'configuration' 'GET' $null $zipPath
 if((Get-Item -LiteralPath $zipPath).Length -gt 25MB){throw 'configuration_package_too_large'}
 $revision=[string]$response.Headers['X-Office-Configuration-Revision'];if($revision -notmatch '\A[a-f0-9]{64}\z'){throw 'configuration_revision_missing'}
 $zip=[IO.Compression.ZipFile]::OpenRead($zipPath)
 try{
  foreach($entry in $zip.Entries){if($entry.FullName -notin @('office-telephony.json','greeting.wav')){throw 'configuration_package_invalid'}}
  $json=Read-TelephonyZipBytes ($zip.GetEntry('office-telephony.json')) 1MB
  $manifest=[Text.UTF8Encoding]::new($false,$true).GetString($json)|ConvertFrom-Json
  if($manifest.type -ne 'office_telephony_configuration' -or $manifest.schema_version -ne 1 -or @($manifest.extensions).Count -lt 1 -or @($manifest.extensions).Count -gt 1000 -or $manifest.ivr.enabled -isnot [bool]){throw 'configuration_package_invalid'}
  $audio=$null;$audioHash=''
  if($manifest.ivr.audio_file){if($manifest.ivr.audio_file -ne 'greeting.wav'){throw 'configuration_package_invalid'};$audio=Read-TelephonyZipBytes ($zip.GetEntry('greeting.wav')) 20MB;$audioHash=Get-TelephonyHash $audio}
  if($zip.Entries.Count -ne $(if($null -ne $audio){2}else{1})){throw 'configuration_package_invalid'}
  $tail=[Text.Encoding]::ASCII.GetBytes(([string][char]10)+$audioHash);$digestBytes=[byte[]]::new($json.Length+$tail.Length);[Array]::Copy($json,$digestBytes,$json.Length);[Array]::Copy($tail,0,$digestBytes,$json.Length,$tail.Length)
  if((Get-TelephonyHash $digestBytes) -ne $revision){throw 'configuration_hash_mismatch'}
  [IO.File]::WriteAllBytes((Join-Path $directory 'office-telephony.json'),$json)
  if($null -ne $audio){[IO.File]::WriteAllBytes((Join-Path $directory 'greeting.wav'),$audio)}
 }finally{$zip.Dispose()}
 return [pscustomobject]@{revision=$revision;path=$directory}
}
function Sync-TelephonyAutomation {
 [CmdletBinding()]param([ValidateSet('ready','busy','stopped','error')][string]$RuntimeState='stopped',[string]$DataRoot)
 $root=Resolve-TelephonyConnectionRoot $DataRoot;$state=Read-TelephonySyncState $root
 try{
  $connection=Read-TelephonyPrivateConnection $root
  $response=Invoke-TelephonyHttp $connection 'heartbeat' 'POST' @{runtime_state=$RuntimeState;applied_revision=$state.applied_revision;error_code=$state.error_code} $null
  $heartbeat=$response.Content|ConvertFrom-Json;$state.last_contact_at=[DateTimeOffset]::UtcNow.ToString('o');$state.configuration_revision=$heartbeat.configuration_revision;$state.error_code=$null
  if($heartbeat.configuration_ready -and $heartbeat.configuration_revision -notmatch '\A[a-f0-9]{64}\z'){throw 'configuration_revision_invalid'}
  if($heartbeat.configuration_ready -and $heartbeat.configuration_revision -ne $state.applied_revision -and ($state.pending_revision -ne $heartbeat.configuration_revision -or -not $state.pending_path -or -not (Test-Path -LiteralPath (Join-Path $state.pending_path 'office-telephony.json')))){
   $pending=Save-TelephonyPendingPackage $connection $root;$state.pending_revision=$pending.revision;$state.pending_path=$pending.path
  }
 }catch{
  $code=[string]$_.Exception.Message
  if($code -notmatch '\A(configuration_[a-z_]+|connection_missing|invalid_connection_record)\z'){$code=if($_.Exception.Response -and [int]$_.Exception.Response.StatusCode -in @(401,403)){'connection_revoked'}else{'connection_failed'}}
  $state.error_code=$code
 }
 Write-TelephonyPrivateJson (Join-Path $root 'sync-state.json') $state
 return Get-TelephonyConnection -DataRoot $root
}
function Complete-TelephonyConfiguration {
 [CmdletBinding()]param([Parameter(Mandatory=$true)][string]$Revision,[string]$DataRoot)
 if($Revision -notmatch '\A[a-f0-9]{64}\z'){throw 'configuration_revision_invalid'}
 $root=Resolve-TelephonyConnectionRoot $DataRoot;$state=Read-TelephonySyncState $root
 if($state.pending_revision -ne $Revision){throw 'configuration_revision_changed'}
 $state.applied_revision=$Revision;$state.pending_revision=$null;$state.pending_path=$null;$state.error_code=$null
 Write-TelephonyPrivateJson (Join-Path $root 'sync-state.json') $state
 return Get-TelephonyConnection -DataRoot $root
}

function Get-TelephonyPendingConfiguration {
 [CmdletBinding()]param([string]$DataRoot)
 $root=Resolve-TelephonyConnectionRoot $DataRoot;$state=Read-TelephonySyncState $root
 if(-not $state.pending_path -or -not $state.pending_revision){return $null}
 $directory=[IO.Path]::GetFullPath([string]$state.pending_path)
 if(-not $directory.StartsWith(($root.TrimEnd('\')+'\pending-'),[StringComparison]::OrdinalIgnoreCase)){throw 'configuration_storage_invalid'}
 for($path=$directory;$path -and $path.StartsWith($root,[StringComparison]::OrdinalIgnoreCase);$path=[IO.Path]::GetDirectoryName($path)){if(([IO.File]::GetAttributes($path) -band [IO.FileAttributes]::ReparsePoint) -ne 0){throw 'configuration_storage_invalid'}}
 $jsonPath=Join-Path $directory 'office-telephony.json'
 if((Get-Item -LiteralPath $jsonPath).Length -gt 1MB){throw 'configuration_package_too_large'}
 $json=[IO.File]::ReadAllBytes($jsonPath);$manifest=[Text.UTF8Encoding]::new($false,$true).GetString($json)|ConvertFrom-Json
 $audioHash=''
 if($manifest.ivr.audio_file){if($manifest.ivr.audio_file -ne 'greeting.wav'){throw 'configuration_package_invalid'};$audio=Join-Path $directory 'greeting.wav';if((Get-Item -LiteralPath $audio).Length -gt 20MB){throw 'configuration_package_too_large'};$audioHash=(Get-FileHash -LiteralPath $audio).Hash.ToLowerInvariant()}
 $tail=[Text.Encoding]::ASCII.GetBytes(([string][char]10)+$audioHash);$bytes=[byte[]]::new($json.Length+$tail.Length);[Array]::Copy($json,$bytes,$json.Length);[Array]::Copy($tail,0,$bytes,$json.Length,$tail.Length)
 if((Get-TelephonyHash $bytes) -ne $state.pending_revision){throw 'configuration_hash_mismatch'}
 return [pscustomobject]@{revision=$state.pending_revision;path=$directory;manifest=$manifest}
}
function Set-TelephonyConfigurationError {
 [CmdletBinding()]param([Parameter(Mandatory=$true)][ValidatePattern('\A[a-z0-9_]{1,80}\z')][string]$ErrorCode,[string]$DataRoot)
 $root=Resolve-TelephonyConnectionRoot $DataRoot;$state=Read-TelephonySyncState $root;$state.error_code=$ErrorCode
 Write-TelephonyPrivateJson (Join-Path $root 'sync-state.json') $state
}
function Reset-TelephonyAppliedRevision {
 param([string]$DataRoot)
 $root=Resolve-TelephonyConnectionRoot $DataRoot;$state=Read-TelephonySyncState $root
 $state.applied_revision=$null
 Write-TelephonyPrivateJson (Join-Path $root 'sync-state.json') $state
}
Export-ModuleMember -Function Reset-TelephonyAppliedRevision, Get-TelephonyConnection,Connect-TelephonyAutomation,Sync-TelephonyAutomation,Complete-TelephonyConfiguration,Get-TelephonyPendingConfiguration,Set-TelephonyConfigurationError
