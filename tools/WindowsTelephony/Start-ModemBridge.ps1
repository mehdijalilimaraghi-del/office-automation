#requires -Version 5.1
[CmdletBinding()]
param([string]$LabRoot,[string]$EngineDirectory,[switch]$NominalTransmitVolume,[switch]$Pcm16Modem,[switch]$CompareGreetingModes)
$ErrorActionPreference='Stop'
try {
 if($CompareGreetingModes -and ($Pcm16Modem -or $NominalTransmitVolume)){throw 'Greeting comparison uses the normal codec/gain only'}
 if($Pcm16Modem -and $NominalTransmitVolume){throw 'Run only one diagnostic mode at a time'}
 $module=Join-Path $PSScriptRoot 'PbxLab.psm1'
 if(-not (Test-Path -LiteralPath $module)){$module=Join-Path $PSScriptRoot 'PbxLab/PbxLab.psm1'}
 Import-Module $module -Force -DisableNameChecking
 if(-not $LabRoot){$LabRoot=Join-Path $env:LOCALAPPDATA 'OfficeAutomation\Telephony\PbxLab'}
 $LabRoot=[IO.Path]::GetFullPath($LabRoot);$stateProfile=Read-PbxLabState $LabRoot
 if(-not $CompareGreetingModes){
 if(-not $stateProfile.modem_gateway){& (Join-Path $PSScriptRoot 'Prepare-ModemBridge.ps1') -LabRoot $LabRoot;$stateProfile=Read-PbxLabState $LabRoot}
 $useLocalGreeting=$stateProfile.modem_gateway.destination -eq '9197' -and $stateProfile.ivr -and $stateProfile.ivr.enabled -and -not $Pcm16Modem -and -not $NominalTransmitVolume
 if($useLocalGreeting -and $stateProfile.modem_gateway.greeting_protocol -ne 'vtx_initial_v1'){
  if(Get-Process -Name FreeSwitchConsole -ErrorAction SilentlyContinue){throw 'Close the bridge and run Stop-PbxLab.cmd once; then run Start-ModemBridge.cmd. Accounts and greeting are preserved.'}
  Set-PbxModemGateway $LabRoot '9197';$stateProfile=Read-PbxLabState $LabRoot
 }
 if(-not (Get-Process -Name FreeSwitchConsole -ErrorAction SilentlyContinue)){
  & (Join-Path ([IO.Path]::GetDirectoryName($module)) 'Start-PbxLab.ps1') -LabRoot $LabRoot -EngineDirectory $EngineDirectory -NoBrowser -HideConsole
 }
 $stateProfile=Read-PbxLabState $LabRoot
 $pbxStatus=Invoke-PbxLabCommand $stateProfile 'status';if($pbxStatus -notmatch 'UP'){throw 'PBX is not ready'}
 }
 Add-Type -AssemblyName System.Windows.Forms;Add-Type -AssemblyName System.Drawing
 if(-not ('OfficeAutomation.Telephony.Bridge.LocalSipAudioEndpoint' -as [type])){$sources=@((Join-Path $PSScriptRoot 'VoiceDiagnostics.cs'),(Join-Path $PSScriptRoot 'LocalSipAudioEndpoint.cs'),(Join-Path $PSScriptRoot 'GreetingPrompt.cs'),(Join-Path $PSScriptRoot 'GreetingModeComparison.cs'),(Join-Path $PSScriptRoot 'ModemGreetingBridge.cs'));Add-Type -Path $sources}
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
 if($CompareGreetingModes){$notice.Text='مقایسه در یک تماس: پیام یکسان دو بار پخش می‌شود؛ بار اول حالت پخش VTX و بار دوم حالت دوطرفه VTR. ولوم، کدک و فایل در هر دو یکسان‌اند. این حالت به داخلی وصل نمی‌شود و مکالمه ضبط نمی‌کند. VentaFax و پنجره پل قبلی را ببندید.'}
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
 if($CompareGreetingModes){$form.Text='مقایسه حالت پخش پیام مودم';$destination.Text='نوبت اول: VTX — نوبت دوم: VTR؛ بدون انتقال به داخلی';$start.Text='شروع مقایسه'}
 [void]$buttons.Controls.Add($start);[void]$buttons.Controls.Add($stop)
 foreach($control in @($notice,$destination,$portPanel,$consent,$status,$buttons)){[void]$layout.Controls.Add($control)}
 [void]$form.Controls.Add($layout)
 $timer=New-Object Windows.Forms.Timer;$timer.Interval=200
 $consent.Add_CheckedChanged({$start.Enabled=$consent.Checked -and -not $run.session})
 $start.Add_Click({
  try {
   if($CompareGreetingModes){
    if(-not $stateProfile.ivr -or -not $stateProfile.ivr.enabled -or -not $stateProfile.ivr.prompt_path){throw 'A configured IVR greeting is required'}
    $run.endpoint=[OfficeAutomation.Telephony.Bridge.GreetingModeComparison]::new($stateProfile.ivr.prompt_path)
   }elseif($useLocalGreeting){
    $run.sip=[OfficeAutomation.Telephony.Bridge.LocalSipAudioEndpoint]::new($stateProfile.bind_address,[int]$stateProfile.sip_port,$stateProfile.modem_gateway.username,$stateProfile.modem_gateway.password,'9197',$true)
    $run.control=[OfficeAutomation.Telephony.Bridge.GreetingCallControl]::new([int]$stateProfile.esl_port,$stateProfile.esl_secret)
    try{$run.endpoint=[OfficeAutomation.Telephony.Bridge.ModemGreetingBridge]::new($run.sip,$run.control,$stateProfile.ivr.prompt_path)}catch{$run.sip.Dispose();$run.control.Dispose();throw}
   }else{$run.endpoint=[OfficeAutomation.Telephony.Bridge.LocalSipAudioEndpoint]::new($stateProfile.bind_address,[int]$stateProfile.sip_port,$stateProfile.modem_gateway.username,$stateProfile.modem_gateway.password,$stateProfile.modem_gateway.destination)}
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
   $run.report=Join-Path $PSScriptRoot ($(if($CompareGreetingModes){'modem-greeting-modes-'}else{'modem-bridge-result-'})+[datetime]::Now.ToString('yyyyMMdd-HHmmss')+'-'+[guid]::NewGuid().ToString('N').Substring(0,6)+'.json')
   $report=[ordered]@{report_type=$(if($CompareGreetingModes){'modem_greeting_mode_comparison'}else{'modem_bridge_test'});schema_version=1;comparison_mode=[bool]$CompareGreetingModes;destination=$stateProfile.modem_gateway.destination;port=[string]$portChoice.SelectedItem;audio_recorded=$false;result=($result|Select-Object DiagnosticVersion,DuplexStartedAtMilliseconds,TransmitOnlyStarted,SwitchedToDuplex,ModeTransitionMilliseconds,ModemSampleFormat,ModemCodec,ModemTransmitBytes,EffectiveFlowControl,HostFlowControlSynchronized,MaxSerialAvailabilityMilliseconds,MaxSerialReadMilliseconds,MaxSerialWriteMilliseconds,MaxDecodeMilliseconds,MaxEncodeMilliseconds,MaxEndpointMilliseconds,MaxTransmitLagMilliseconds,RequestedTransmitLevel,OriginalTransmitLevel,TransmitLevelApplied,TransmitLevelRestored,Status,ErrorCode,FailureStage,FailureError,CleanupError,RingDetected,Answered,DuplexStarted,BridgeStarted,CleanupConfirmed,Cancelled,EndReason,TransmittedSamples,ReceivedSamples,RawReceivedSamples,RemovedRepeatedSamples,ReceiveFraming,StreamMilliseconds,SampleWindows,ReceiveRateAfterWarmup,TransmitRateAfterWarmup,BridgeTimingReady,Events,Trace);greeting=$(if($useLocalGreeting){$run.endpoint|Select-Object PromptSha256,PromptSamples,GreetingSamples,GreetingPlaybackAudio,MenuStarted}else{$null});sip=$(if(-not $CompareGreetingModes){$run.endpoint.Metrics}else{$null});comparison=$(if($CompareGreetingModes){$run.endpoint|Select-Object PromptSha256,PromptSamples,CurrentPhase,FirstMode,SecondMode,FirstPhaseSamples,SecondPhaseSamples,AudioRecorded}else{$null})}
   try{[IO.File]::WriteAllText($run.report,($report|ConvertTo-Json -Depth 9),[Text.UTF8Encoding]::new($false));if($result.Status -eq 'failed'){if($result.ErrorCode -eq 'audio_transmit_timing_failed'){$status.Text='ارسال صدا به مودم از زمان مجاز عقب افتاد؛ آزمون متوقف شد. نتیجه بازیابی تنظیمات در گزارش ذخیره شده است.'}else{$status.Text='اتصال خط کامل نشد. گزارش خطا در همین پوشه ذخیره شد.'}}elseif($result.Cancelled){$status.Text='آزمون متوقف و گزارش در همین پوشه ذخیره شد.'}else{$status.Text='آزمون پایان یافت. گزارش در همین پوشه ذخیره شد. نتیجه شنیدن صدا و قطع تماس را اعلام کنید.'}}catch{$status.Text='آزمون پایان یافت؛ ذخیره گزارش ممکن نشد: '+$_.Exception.Message}
   $run.finished=$true
   if($run.wantClose){$form.Close()}
  }else{
   if($useLocalGreeting -and $run.session.Progress -eq 'streaming' -and -not $run.endpoint.MenuStarted){$status.Text='در حال پخش پیام راهنما؛ پس از پایان پیام شماره داخلی را وارد کنید.';return}
   if($CompareGreetingModes -and $run.session.Progress -eq 'streaming'){$status.Text=('در حال پخش نوبت '+$run.endpoint.CurrentPhase+' از دو نوبت؛ بلندی دو پیام را مقایسه کنید.');return}
   if(($CompareGreetingModes -or $useLocalGreeting) -and $run.session.Progress -eq 'switching_playback_mode'){$status.Text='در حال تغییر از پخش پیام به حالت دوطرفه…';return}
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
