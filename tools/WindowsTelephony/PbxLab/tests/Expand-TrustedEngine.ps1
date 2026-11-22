#requires -Version 5.1
param([Parameter(Mandatory=$true)][string]$InstallerPath,[Parameter(Mandatory=$true)][string]$OutputDirectory)
$ErrorActionPreference='Stop'
trap { Write-Host $_.ScriptStackTrace; throw $_ }
$msi=[IO.Path]::GetFullPath($InstallerPath)
if((Get-FileHash -LiteralPath $msi -Algorithm SHA256).Hash.ToLowerInvariant() -ne '331cdd876c7ee73cc6d60bbdd7c9b7e60c157d3760226986fd7d6e604c83d8b6'){throw 'Untrusted MSI'}
$root=[IO.Path]::GetFullPath($OutputDirectory)
if(Test-Path -LiteralPath $root){throw 'Output directory must not exist'}
[void][IO.Directory]::CreateDirectory($root)
$raw=Join-Path $root 'cab-files';[void][IO.Directory]::CreateDirectory($raw)
$image=Join-Path $root 'engine';[void][IO.Directory]::CreateDirectory($image)
Add-Type -Path (Join-Path $PSScriptRoot 'TrustedMsiCab.cs')
$cab=Join-Path $root 'media1.cab';[TrustedMsiCab]::Save($msi,$cab)
& "$env:SystemRoot\System32\expand.exe" '-F:*' $cab $raw *> (Join-Path $root 'expand.log')
if($LASTEXITCODE -ne 0){throw 'CAB expansion failed'}
$installer=New-Object -ComObject WindowsInstaller.Installer;$db=$installer.OpenDatabase($msi,0)
function Read-MsiRows([string]$Sql){$view=$db.OpenView($Sql);[void]$view.Execute();try{while($record=$view.Fetch()){$count=$record.GetType().InvokeMember('FieldCount','GetProperty',$null,$record,$null);$values=@();for($i=1;$i -le $count;$i++){$values+=$record.GetType().InvokeMember('StringData','GetProperty',$null,$record,@($i))};[pscustomobject]@{cells=$values}}}finally{[void]$view.Close()}}
$dirs=@{};foreach($item in @(Read-MsiRows 'SELECT `Directory`,`Directory_Parent`,`DefaultDir` FROM `Directory`')){$row=$item.cells;$dirs[$row[0]]=@($row[1],$row[2])}
$components=@{};foreach($item in @(Read-MsiRows 'SELECT `Component`,`Directory_` FROM `Component`')){$row=$item.cells;$components[$row[0]]=$row[1]}
function Get-RelativeDirectory([string]$Id){if($Id -eq 'INSTALLLOCATION'){return ''};if(-not $dirs.ContainsKey($Id) -or -not $dirs[$Id][0]){return $null};$parent=Get-RelativeDirectory $dirs[$Id][0];if($null -eq $parent){return $null};$name=($dirs[$Id][1].Split(':')[0].Split('|')[-1]);if($name -eq '.'){return $parent};if($parent){return ($parent+'\'+$name)};return $name}
$count=0
foreach($item in @(Read-MsiRows 'SELECT `File`,`Component_`,`FileName` FROM `File`')){
 $row=$item.cells
 $relative=Get-RelativeDirectory $components[$row[1]];if($null -eq $relative){continue}
 if($relative -match '^System(32|64)(\\|$)'){continue}
 $name=$row[2].Split('|')[-1];$destination=[IO.Path]::GetFullPath((Join-Path (Join-Path $image $relative) $name))
 if(-not $destination.StartsWith($image+'\',[StringComparison]::OrdinalIgnoreCase)){throw 'MSI path escaped image directory'}
 $inputFile=Join-Path $raw $row[0];if(-not (Test-Path -LiteralPath $inputFile)){throw ('Missing CAB entry: '+$row[0])}
 [void][IO.Directory]::CreateDirectory([IO.Path]::GetDirectoryName($destination));[IO.File]::Copy($inputFile,$destination,$false);$count++
}
[pscustomobject]@{engine_directory=$image;extracted_files=$count;msi_executed=$false}