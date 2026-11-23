#requires -Version 5.1
[CmdletBinding()]
param([string]$LabRoot,[string]$EngineDirectory,[switch]$NominalTransmitVolume,[switch]$Pcm16Modem)
$ErrorActionPreference='Stop'
try {
 if($Pcm16Modem -and $NominalTransmitVolume){throw 'Run only one diagnostic mode at a time'}
 $module=Join-Path $PSScriptRoot 'PbxLab.psm1'
 if(-not (Test-Path -LiteralPath $module)){$module=Join-Path $PSScriptRoot 'PbxLab/PbxLab.psm1'}
 Import-Module $module -Force -DisableNameChecking
 if(-not $LabRoot){$LabRoot=Join-Path $env:LOCALAPPDATA 'OfficeAutomation\Telephony\PbxLab'}
 $LabRoot=[IO.Path]::GetFullPath($LabRoot);$stateProfile=Read-PbxLabState $LabRoot
 if(-not $stateProfile.modem_gateway){& (Join-Path $PSScriptRoot 'Prepare-ModemBridge.ps1') -LabRoot $LabRoot}
 if(-not (Get-Process -Name FreeSwitchConsole -ErrorAction SilentlyContinue)){
  & (Join-Path ([IO.Path]::GetDirectoryName($module)) 'Start-PbxLab.ps1') -LabRoot $LabRoot -EngineDirectory $EngineDirectory -NoBrowser -HideConsole
 }
 $stateProfile=Read-PbxLabState $LabRoot
 $pbxStatus=Invoke-PbxLabCommand $stateProfile 'status';if($pbxStatus -notmatch 'UP'){throw 'PBX is not ready'}
 Add-Type -AssemblyName System.Windows.Forms;Add-Type -AssemblyName System.Drawing
 if(-not ('OfficeAutomation.Telephony.Bridge.LocalSipAudioEndpoint' -as [type])){Add-Type -Path @((Join-Path $PSScriptRoot 'VoiceDiagnostics.cs'),(Join-Path $PSScriptRoot 'LocalSipAudioEndpoint.cs'))}
 if($NominalTransmitVolume){[Windows.Forms.MessageBox]::Show('آزمون صدای ارسال مودم با مقدار ۱۲۸؛ مقدار قبلی پس از پایان تماس بازیابی می‌شود. بلندی صدای این حالت هنوز تأیید نشده است.','آزمون کنترل‌شده صدا')|Out-Null}
 if($Pcm16Modem){[Windows.Forms.MessageBox]::Show('مسیر آزمایشی PCM شانزده‌بیتی مودم؛ تنظیم بلندی صدا تغییر نمی‌کند. کیفیت و بلندی این مسیر هنوز روی خط واقعی تأیید نشده است.','آزمون قالب صدای مودم')|Out-Null}
 $ports=@([IO.Ports.SerialPort]::GetPortNames()|Sort-Object)
 if(-not $ports.Count){throw 'No modem COM port is available'}
 $run=@{session=$null;endpoint=$null;wantClose=$false;finished=$false;report=$null}
 $form=New-Object Windows.Forms.Form;$form.Text='آزمون اتصال خط به داخلی';$form.Size=New-Object Drawing.Size(760,520);$form.StartPosition='CenterScreen';$form.Font=New-Object Drawing.Font('Segoe UI',10);$form.RightToLeft='Yes';$form.RightToLeftLayout=$true;$form.AutoScroll=$true
 $form.MaximumSize=New-Object Drawing.Size(1100,([int]([Windows.Forms.Screen]::PrimaryScreen.WorkingArea.Height*0.9)))
 $layout=New-Object Windows.Forms.TableLayoutPanel;$layout.Dock='Fill';$layout.Padding=New-Object Windows.Forms.Padding(20);$layout.ColumnCount=1;$layout.RowCount=6;$layout.AutoScroll=$true;$layout.AutoScrollMinSize=New-Object Drawing.Size(0,470)
 foreach($height in @(85,45,65,55,120,60)){[void]$layout.RowStyles.Add((New-Object Windows.Forms.RowStyle([Windows.Forms.SizeType]::Absolute,$height)))}
 $notice=New-Object Windows.Forms.Label;$notice.Dock='Fill';$notice.Text='این آزمون به یک تماس ورودی پاسخ می‌دهد و حداکثر ۶۰ ثانیه آن را به مرکز تلفن وصل می‌کند. ضبط مکالمه انجام نمی‌شود. داخلی مقصد باید آنلاین باشد. ابتدا VentaFax را کامل ببندید؛ سپس شروع را بزنید و با گوشی با خط شرکت تماس بگیرید.'
 $destination=New-Object Windows.Forms.Label;$destination.Dock='Fill';$destination.Text=('مقصد تماس: '+$stateProfile.modem_gateway.destination+'     |     مرکز تلفن: '+$stateProfile.bind_address)
 $portPanel=New-Object Windows.Forms.FlowLayoutPanel;$portPanel.Dock='Fill'
 $portLabel=New-Object Windows.Forms.Label;$portLabel.Text='درگاه مودم';$portLabel.AutoSize=$true
 $portChoice=New-Object Windows.Forms.ComboBox;$portChoice.Width=170;$portChoice.DropDownStyle='DropDownList';foreach($port in $ports){[void]$portChoice.Items.Add($port)};$portChoice.SelectedIndex=0;if($ports -contains 'COM3'){$portChoice.SelectedItem='COM3'}
 [void]$portPanel.Controls.Add($portLabel);[void]$portPanel.Controls.Add($portChoice)
 $consent=New-Object Windows.Forms.CheckBox;$consent.Dock='Fill';$consent.Text='VentaFax بسته است و برای پاسخ آزمایشی به یک تماس ورودی آماده‌ام.'
 $status=New-Object Windows.Forms.Label;$status.Dock='Fill';$status.Text='پس از شروع، منتظر پیام «آماده؛ اکنون تماس بگیرید» بمانید.';$status.BackColor=[Drawing.Color]::FromArgb(241,245,249)
 $buttons=New-Object Windows.Forms.FlowLayoutPanel;$buttons.Dock='Fill'
 $start=New-Object Windows.Forms.Button;$start.Text='شروع آزمون';$start.Size=New-Object Drawing.Size(160,42);$start.Enabled=$false
 $stop=New-Object Windows.Forms.Button;$stop.Text='توقف امن';$stop.Size=New-Object Drawing.Size(140,42);$stop.Enabled=$false
 [void]$buttons.Controls.Add($start);[void]$buttons.Controls.Add($stop)
 foreach($control in @($notice,$destination,$portPanel,$consent,$status,$buttons)){[void]$layout.Controls.Add($control)}
 [void]$form.Controls.Add($layout)
 $timer=New-Object Windows.Forms.Timer;$timer.Interval=200
 $consent.Add_CheckedChanged({$start.Enabled=$consent.Checked -and -not $run.session})
 $start.Add_Click({
  try {
   $run.endpoint=[OfficeAutomation.Telephony.Bridge.LocalSipAudioEndpoint]::new($stateProfile.bind_address,[int]$stateProfile.sip_port,$stateProfile.modem_gateway.username,$stateProfile.modem_gateway.password,$stateProfile.modem_gateway.destination)
   $serial=[OfficeAutomation.Telephony.Diagnostics.SerialVoicePort]::new([string]$portChoice.SelectedItem,$(if($Pcm16Modem){230400}else{115200}))
   $trialLevel=if($NominalTransmitVolume){[Nullable[int]]128}else{$null}
   $run.session=[OfficeAutomation.Telephony.Diagnostics.VoiceSession]::new($serial,(Join-Path $LabRoot 'bridge-reports'),60000,60000,$run.endpoint,$trialLevel,[bool]$Pcm16Modem)
   $start.Enabled=$false;$consent.Enabled=$false;$portChoice.Enabled=$false;$stop.Enabled=$true
   $run.session.Start();$timer.Start()
  }catch{if($run.session -and $run.session.Started){$run.session.Cancel();$timer.Start()}else{if($run.endpoint){$run.endpoint.Dispose()};$run.session=$null};$status.Text='شروع آزمون ممکن نشد: '+$_.Exception.Message;$start.Enabled=$false}
 })
 $stop.Add_Click({if($run.session -and -not $run.session.Completed){$run.session.Cancel();$stop.Enabled=$false;$status.Text='در حال قطع تماس و آزادسازی مودم…'}})
 $timer.Add_Tick({
  if(-not $run.session){return}
  if($run.session.Completed){
   $timer.Stop();$stop.Enabled=$false
   $result=$run.session.Result
   $run.report=Join-Path $PSScriptRoot ('modem-bridge-result-'+[datetime]::Now.ToString('yyyyMMdd-HHmmss')+'-'+[guid]::NewGuid().ToString('N').Substring(0,6)+'.json')
   $report=[ordered]@{report_type='modem_bridge_test';schema_version=1;destination=$stateProfile.modem_gateway.destination;port=[string]$portChoice.SelectedItem;audio_recorded=$false;result=($result|Select-Object DiagnosticVersion,ModemSampleFormat,ModemCodec,ModemTransmitBytes,MaxSerialAvailabilityMilliseconds,MaxSerialReadMilliseconds,MaxSerialWriteMilliseconds,MaxDecodeMilliseconds,MaxEncodeMilliseconds,MaxEndpointMilliseconds,MaxTransmitLagMilliseconds,RequestedTransmitLevel,OriginalTransmitLevel,TransmitLevelApplied,TransmitLevelRestored,Status,ErrorCode,FailureStage,FailureError,CleanupError,RingDetected,Answered,DuplexStarted,BridgeStarted,CleanupConfirmed,Cancelled,EndReason,TransmittedSamples,ReceivedSamples,RawReceivedSamples,RemovedRepeatedSamples,ReceiveFraming,StreamMilliseconds,SampleWindows,ReceiveRateAfterWarmup,TransmitRateAfterWarmup,BridgeTimingReady,Events,Trace);sip=$run.endpoint.Metrics}
   try{[IO.File]::WriteAllText($run.report,($report|ConvertTo-Json -Depth 9),[Text.UTF8Encoding]::new($false));if($result.Status -eq 'failed'){if($result.ErrorCode -eq 'audio_transmit_timing_failed'){$status.Text='ارسال صدا به مودم از زمان مجاز عقب افتاد؛ آزمون متوقف شد. نتیجه بازیابی تنظیمات در گزارش ذخیره شده است.'}else{$status.Text='اتصال خط کامل نشد. گزارش خطا در همین پوشه ذخیره شد.'}}elseif($result.Cancelled){$status.Text='آزمون متوقف و گزارش در همین پوشه ذخیره شد.'}else{$status.Text='آزمون پایان یافت. گزارش در همین پوشه ذخیره شد. نتیجه شنیدن صدا و قطع تماس را اعلام کنید.'}}catch{$status.Text='آزمون پایان یافت؛ ذخیره گزارش ممکن نشد: '+$_.Exception.Message}
   $run.finished=$true
   if($run.wantClose){$form.Close()}
  }else{
   $messages=@{preparing='در حال آماده‌سازی مودم…';waiting_ring='آماده؛ اکنون با گوشی با خط شرکت تماس بگیرید.';connecting_pbx='در حال اتصال به مرکز تلفن…';answering='در حال پاسخ به تماس…';streaming='پل صوتی برقرار است؛ از داخلی پاسخ دهید. اگر مقصد راهنمای صوتی است، کلید آن را از گوشی بزنید. برای پایان، از داخلی قطع کنید.';hanging_up='در حال قطع تماس و بازیابی تنظیمات مودم…'}
   $phase=$run.session.Progress;if($messages.ContainsKey($phase)){$status.Text=$messages[$phase]}
  }
 })
 $form.Add_FormClosing({param($sender,$event) if($run.session -and -not $run.finished){$event.Cancel=$true;$run.wantClose=$true;$run.session.Cancel();$status.Text='در حال قطع امن تماس…'}})
 try{[void]$form.ShowDialog()}finally{$timer.Stop();$timer.Dispose();$form.Dispose()}
 Write-Host ('Test finished. Report: '+$run.report)
} catch {
 $message='ERROR: '+$_.Exception.Message;Write-Host $message -ForegroundColor Red
 try{[IO.File]::WriteAllText((Join-Path $PSScriptRoot 'modem-bridge-error.txt'),$message,[Text.UTF8Encoding]::new($false))}catch{}
 exit 1
}
