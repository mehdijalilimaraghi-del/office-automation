#requires -Version 5.1
$ErrorActionPreference = 'Stop'
$tokens=$null; $parseErrors=$null
$ast=[System.Management.Automation.Language.Parser]::ParseFile((Join-Path $PSScriptRoot 'ModemHelper.ps1'),[ref]$tokens,[ref]$parseErrors)
if($parseErrors.Count){throw($parseErrors|Out-String)}
$function=$ast.Find({param($node) $node -is [System.Management.Automation.Language.FunctionDefinitionAst] -and $node.Name -eq 'Test-VoiceClass'},$false)
if(-not $function){throw 'Voice parser function missing'}
. ([scriptblock]::Create($function.Extent.Text))
$nl=[Environment]::NewLine
$cases=@(
 @{value="AT+FCLASS=?"+$nl+"0,1,8"+$nl+"OK";expected=$true},
 @{value="+FCLASS: 0,1,8"+$nl+"OK";expected=$true},
 @{value="+FCLASS: (0,1,8)"+$nl+"OK";expected=$true},
 @{value="0, 1.0, 8"+$nl+"OK";expected=$true},
 @{value="0,1"+$nl+"OK";expected=$false},
 @{value="0,1,18"+$nl+"OK";expected=$false},
 @{value="0,1,80"+$nl+"OK";expected=$false},
 @{value="0,1,8";expected=$false},
 @{value="0,1,8"+$nl+"ERROR"+$nl+"OK";expected=$false},
 @{value="LSI SoftModem Version 2.2.98"+$nl+"OK";expected=$false}
)
foreach($case in $cases){if((Test-VoiceClass $case.value) -ne $case.expected){throw 'Voice parser case failed'}}
Write-Output ("Windows voice parser passed: {0} cases; no modem opened." -f $cases.Count)
