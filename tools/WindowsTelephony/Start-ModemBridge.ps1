#requires -Version 5.1
[CmdletBinding()]
param([string]$LabRoot,[string]$EngineDirectory,[switch]$NominalTransmitVolume,[switch]$Pcm16Modem,[switch]$CompareGreetingModes,[switch]$LineEchoCancellation,[switch]$ReceiveCalls,[switch]$DesktopMode,[string]$ConnectionRoot)
$ErrorActionPreference='Stop'
$desktopMutex=$null
try {
 if($DesktopMode){
  Import-Module (Join-Path $PSScriptRoot 'TelephonyDesktop.psm1') -Force -DisableNameChecking
  $desktopMutex=[Threading.Mutex]::new($false,'Local\OfficeAutomation.Telephony.Desktop')
  $owned=$false;try{$owned=$desktopMutex.WaitOne(0)}catch [Threading.AbandonedMutexException]{$owned=$true}
  if(-not $owned){$desktopMutex.Dispose();$desktopMutex=$null;[Windows.Forms.MessageBox]::Show('برنامه تلفن سازمانی از قبل باز است. از همان پنجره استفاده کنید.','تلفن سازمانی')|Out-Null;return}
  $ReceiveCalls=$true
  if(-not $EngineDirectory){$EngineDirectory=if(Test-Path -LiteralPath (Join-Path $PSScriptRoot 'Engine/FreeSwitchConsole.exe')){Join-Path $PSScriptRoot 'Engine'}else{Join-Path $env:ProgramFiles 'FreeSWITCH'}}
 }

 if($ReceiveCalls){if($CompareGreetingModes -or $Pcm16Modem -or $NominalTransmitVolume){throw 'The receiver uses only the accepted normal codec and echo cancellation'};$LineEchoCancellation=$true}
 if($LineEchoCancellation -and ($CompareGreetingModes -or $Pcm16Modem -or $NominalTransmitVolume)){throw 'Use line echo cancellation with the normal codec and gain only'}
 if($CompareGreetingModes -and ($Pcm16Modem -or $NominalTransmitVolume)){throw 'Greeting comparison uses the normal codec/gain only'}
 if($Pcm16Modem -and $NominalTransmitVolume){throw 'Run only one diagnostic mode at a time'}
 $module=Join-Path $PSScriptRoot 'PbxLab.psm1'
 if(-not (Test-Path -LiteralPath $module)){$module=Join-Path $PSScriptRoot 'PbxLab/PbxLab.psm1'}
 Import-Module $module -Force -DisableNameChecking
 if(-not $LabRoot){$LabRoot=Join-Path $env:LOCALAPPDATA 'OfficeAutomation\Telephony\PbxLab'}
 $LabRoot=[IO.Path]::GetFullPath($LabRoot)
 if($DesktopMode -and -not (Test-Path -LiteralPath (Join-Path $LabRoot 'lab-state.json'))){
  New-PbxLabProfile -Root $LabRoot -BindAddress (Get-PbxLabLocalAddress) | Out-Null
 }
 $stateProfile=Read-PbxLabState $LabRoot
 if(-not $CompareGreetingModes){
 if(-not $stateProfile.modem_gateway){
  if($DesktopMode){Set-PbxModemGateway -Root $LabRoot -Destination ([string]$stateProfile.accounts[0].extension)}
  else{& (Join-Path $PSScriptRoot 'Prepare-ModemBridge.ps1') -LabRoot $LabRoot}
  $stateProfile=Read-PbxLabState $LabRoot
 }
 $useLocalGreeting=$stateProfile.modem_gateway.destination -eq '9197' -and $stateProfile.ivr -and $stateProfile.ivr.enabled -and -not $Pcm16Modem -and -not $NominalTransmitVolume
 if($stateProfile.modem_gateway.greeting_protocol -ne 'vtx_initial_v3'){
  if(Get-Process -Name FreeSwitchConsole -ErrorAction SilentlyContinue){throw 'Close the bridge and run Stop-PbxLab.cmd once; then run Start-ModemBridge.cmd. Accounts and greeting are preserved.'}
  Set-PbxModemGateway $LabRoot $stateProfile.modem_gateway.destination;$stateProfile=Read-PbxLabState $LabRoot
 }
 if(-not (Get-Process -Name FreeSwitchConsole -ErrorAction SilentlyContinue)){
  & (Join-Path ([IO.Path]::GetDirectoryName($module)) 'Start-PbxLab.ps1') -LabRoot $LabRoot -EngineDirectory $EngineDirectory -NoBrowser -HideConsole
 }
 $stateProfile=Read-PbxLabState $LabRoot
 $pbxStatus=Invoke-PbxLabCommand $stateProfile 'status';if($pbxStatus -notmatch 'UP'){throw 'PBX is not ready'}
 }
 Add-Type -AssemblyName System.Windows.Forms;Add-Type -AssemblyName System.Drawing
 if(-not ('OfficeAutomation.Telephony.Bridge.LocalSipAudioEndpoint' -as [type])){$sources=@((Join-Path $PSScriptRoot 'VoiceDiagnostics.cs'),(Join-Path $PSScriptRoot 'LocalSipAudioEndpoint.cs'),(Join-Path $PSScriptRoot 'GreetingPrompt.cs'),(Join-Path $PSScriptRoot 'GreetingModeComparison.cs'),(Join-Path $PSScriptRoot 'ModemGreetingBridge.cs'),(Join-Path $PSScriptRoot 'ModemLineEcho.cs'));Add-Type -Path $sources}
 if($NominalTransmitVolume){[Windows.Forms.MessageBox]::Show('آزمون صدای ارسال مودم با مقدار ۱۲۸؛ مقدار قبلی پس از پایان تماس بازیابی می‌شود. بلندی صدای این حالت هنوز تأیید نشده است.','آزمون کنترل‌شده صدا')|Out-Null}
 if($Pcm16Modem){[Windows.Forms.MessageBox]::Show('مسیر آزمایشی PCM شانزده‌بیتی مودم؛ تنظیم بلندی صدا تغییر نمی‌کند. کیفیت و بلندی این مسیر هنوز روی خط واقعی تأیید نشده است.','آزمون قالب صدای مودم')|Out-Null}
 if($LineEchoCancellation){. (Join-Path $PSScriptRoot 'ModemLineEcho.ps1');$lineEchoLibrary=Initialize-ModemLineEchoLibrary -LabRoot $LabRoot -SourceDirectory (Join-Path $PSScriptRoot 'Dsp')}
 $ports=@([IO.Ports.SerialPort]::GetPortNames()|Sort-Object)
 if(-not $ports.Count -and -not $DesktopMode){throw 'No modem COM port is available'}
 $run=@{profile=$stateProfile;useLocalGreeting=[bool]$useLocalGreeting;desktopBusy=$false;pauseForConfiguration=$false;autoResumeAfterConfig=$false;lastResult=$null;session=$null;endpoint=$null;echo=$null;mutex=$null;stopping=$false;nextStart=$null;completedCalls=0;wantClose=$false;finished=$false;report=$null}
 function Release-ReceiverLock { if($run.mutex){try{$run.mutex.ReleaseMutex()}finally{$run.mutex.Dispose();$run.mutex=$null}} }
 $reportFolder=if($ReceiveCalls){Join-Path $LabRoot 'calls'}else{$PSScriptRoot}
 if($ReceiveCalls){[void][IO.Directory]::CreateDirectory($reportFolder)}
 $form=New-Object Windows.Forms.Form;$form.Text='آزمون اتصال خط به داخلی';$form.Size=New-Object Drawing.Size(760,520);$form.StartPosition='CenterScreen';$form.Font=New-Object Drawing.Font('Segoe UI',10);$form.RightToLeft='Yes';$form.RightToLeftLayout=$true;$form.AutoScroll=$true
 $form.MaximumSize=New-Object Drawing.Size(1100,([int]([Windows.Forms.Screen]::PrimaryScreen.WorkingArea.Height*0.9)))
 $layout=New-Object Windows.Forms.TableLayoutPanel;$layout.Dock='Fill';$layout.Padding=New-Object Windows.Forms.Padding(20);$layout.ColumnCount=1;$layout.RowCount=6;$layout.AutoScroll=$true;$layout.AutoScrollMinSize=New-Object Drawing.Size(0,470)
 foreach($height in @(85,45,65,55,120,60)){[void]$layout.RowStyles.Add((New-Object Windows.Forms.RowStyle([Windows.Forms.SizeType]::Absolute,$height)))}
 $notice=New-Object Windows.Forms.Label;$notice.Dock='Fill';$notice.Text='این آزمون به یک تماس ورودی پاسخ می‌دهد و حداکثر ۶۰ ثانیه آن را به مرکز تلفن وصل می‌کند. ضبط مکالمه انجام نمی‌شود. داخلی مقصد باید آنلاین باشد. درگاه مودم باید آزاد باشد؛ سپس پاسخ‌گویی را فعال کنید.'
 if($CompareGreetingModes){$notice.Text='مقایسه در یک تماس: پیام یکسان دو بار پخش می‌شود؛ بار اول حالت پخش VTX و بار دوم حالت دوطرفه VTR. ولوم، کدک و فایل در هر دو یکسان‌اند. این حالت به داخلی وصل نمی‌شود و مکالمه ضبط نمی‌کند. برنامه دیگری نباید درگاه مودم را اشغال کرده باشد.'}
 if($LineEchoCancellation){$notice.Text='آزمون اکوگیر خط: برگشت صدای داخلی در مسیر دریافت مودم پردازش می‌شود. پخش پیام، ولوم و انتخاب داخلی تغییر نمی‌کند. نتیجه روی خط واقعی هنوز تأیید نشده است؛ ضبط مکالمه انجام نمی‌شود.'}
 $destination=New-Object Windows.Forms.Label;$destination.Dock='Fill';$destination.Text=('مقصد تماس: '+$run.profile.modem_gateway.destination+'     |     مرکز تلفن: '+$run.profile.bind_address)
 $portPanel=New-Object Windows.Forms.FlowLayoutPanel;$portPanel.Dock='Fill'
 $portLabel=New-Object Windows.Forms.Label;$portLabel.Text='درگاه مودم';$portLabel.AutoSize=$true
 $portChoice=New-Object Windows.Forms.ComboBox;$portChoice.Width=170;$portChoice.DropDownStyle='DropDownList';foreach($port in $ports){[void]$portChoice.Items.Add($port)};if($portChoice.Items.Count){$portChoice.SelectedIndex=0};if($ports -contains 'COM3'){$portChoice.SelectedItem='COM3'}
 [void]$portPanel.Controls.Add($portLabel);[void]$portPanel.Controls.Add($portChoice)
 $consent=New-Object Windows.Forms.CheckBox;$consent.Dock='Fill';$consent.Text='فعال‌سازی پاسخ‌گویی به خط تلفن'
 $status=New-Object Windows.Forms.Label;$status.Dock='Fill';$status.Text='پس از شروع، منتظر پیام «آماده؛ اکنون تماس بگیرید» بمانید.';$status.BackColor=[Drawing.Color]::FromArgb(241,245,249)
 $buttons=New-Object Windows.Forms.FlowLayoutPanel;$buttons.Dock='Fill'
 $start=New-Object Windows.Forms.Button;$start.Text='شروع آزمون';$start.Size=New-Object Drawing.Size(160,42);$start.Enabled=$false
 $stop=New-Object Windows.Forms.Button;$stop.Text='توقف امن';$stop.Size=New-Object Drawing.Size(140,42);$stop.Enabled=$false
 if($CompareGreetingModes){$form.Text='مقایسه حالت پخش پیام مودم';$destination.Text='نوبت اول: VTX — نوبت دوم: VTR؛ بدون انتقال به داخلی';$start.Text='شروع مقایسه'}
 if($ReceiveCalls){$form.Text='تلفن سازمانی — پاسخ‌گویی ورودی';$start.Text='شروع پاسخ‌گویی';$stop.Text='توقف پاسخ‌گویی';$consent.Text='فعال‌سازی پاسخ‌گویی به خط تلفن';$notice.Text='پس از شروع، برنامه منتظر تماس می‌ماند و پس از پایان صحیح هر تماس برای تماس بعدی آماده می‌شود. اکوگیر فعال است و صدا ضبط نمی‌شود. برای توقف از همین پنجره استفاده کنید. هر تماس حداکثر ۲۴ ساعت نگه داشته می‌شود.'}
 [void]$buttons.Controls.Add($start);[void]$buttons.Controls.Add($stop)
 foreach($control in @($notice,$destination,$portPanel,$consent,$status,$buttons)){[void]$layout.Controls.Add($control)}
 [void]$form.Controls.Add($layout)
 $timer=New-Object Windows.Forms.Timer;$timer.Interval=200
 $consent.Add_CheckedChanged({$start.Enabled=$consent.Checked -and -not $run.session})
 $start.Add_Click({
  try {
   if($DesktopMode){if($run.desktopBusy){return};$run.profile=Read-PbxLabState $LabRoot;$run.useLocalGreeting=$run.profile.modem_gateway.destination -eq '9197' -and $run.profile.ivr -and $run.profile.ivr.enabled}

   if($ReceiveCalls){
    if(-not $run.mutex){
     $run.mutex=[Threading.Mutex]::new($false,('Local\OfficeAutomation.ModemReceiver.'+[string]$portChoice.SelectedItem))
     $acquired=$false;try{$acquired=$run.mutex.WaitOne(0)}catch [Threading.AbandonedMutexException]{$acquired=$true}
     if(-not $acquired){$run.mutex.Dispose();$run.mutex=$null;throw 'Incoming reception is already running for this modem'}
    }
    $run.stopping=$false;$run.finished=$false
   }
   if($CompareGreetingModes){
    if(-not $run.profile.ivr -or -not $run.profile.ivr.enabled -or -not $run.profile.ivr.prompt_path){throw 'A configured IVR greeting is required'}
    $run.endpoint=[OfficeAutomation.Telephony.Bridge.GreetingModeComparison]::new($run.profile.ivr.prompt_path)
   }elseif($run.useLocalGreeting){
    $run.sip=[OfficeAutomation.Telephony.Bridge.LocalSipAudioEndpoint]::new($run.profile.bind_address,[int]$run.profile.sip_port,$run.profile.modem_gateway.username,$run.profile.modem_gateway.password,'9197',$true)
    $run.control=[OfficeAutomation.Telephony.Bridge.GreetingCallControl]::new([int]$run.profile.esl_port,$run.profile.esl_secret)
    try{$run.endpoint=[OfficeAutomation.Telephony.Bridge.ModemGreetingBridge]::new($run.sip,$run.control,$run.profile.ivr.prompt_path)}catch{$run.sip.Dispose();$run.control.Dispose();throw}
   }else{$run.endpoint=[OfficeAutomation.Telephony.Bridge.LocalSipAudioEndpoint]::new($run.profile.bind_address,[int]$run.profile.sip_port,$run.profile.modem_gateway.username,$run.profile.modem_gateway.password,$run.profile.modem_gateway.destination)}
   if($LineEchoCancellation){$run.echo=[OfficeAutomation.Telephony.Bridge.ModemLineEcho]::new($lineEchoLibrary)}
   $serial=[OfficeAutomation.Telephony.Diagnostics.SerialVoicePort]::new([string]$portChoice.SelectedItem,$(if($Pcm16Modem){230400}else{115200}))
   $trialLevel=if($NominalTransmitVolume){[Nullable[int]]128}else{$null}
   if($ReceiveCalls){$run.session=[OfficeAutomation.Telephony.Diagnostics.VoiceSession]::ForIncomingCalls($serial,$reportFolder,$run.endpoint,$run.echo)}else{$run.session=[OfficeAutomation.Telephony.Diagnostics.VoiceSession]::new($serial,(Join-Path $LabRoot 'bridge-reports'),60000,60000,$run.endpoint,$trialLevel,[bool]$Pcm16Modem,$run.echo)}
   $start.Enabled=$false;$consent.Enabled=$false;$portChoice.Enabled=$false;$stop.Enabled=$true
   $run.session.Start();$timer.Start()
  }catch{if($run.session -and $run.session.Started){$run.session.Cancel();$timer.Start()}else{if($run.endpoint){$run.endpoint.Dispose()};if($run.echo){$run.echo.Dispose()};if($ReceiveCalls){Release-ReceiverLock};$run.session=$null};if($DesktopMode){$status.Text=if($_.Exception.Message -eq 'Incoming reception is already running for this modem'){'این درگاه مودم در پنجره دیگری در حال استفاده است؛ آن را آزاد کنید و دوباره تلاش کنید.'}else{'شروع پاسخ‌گویی ممکن نشد؛ درگاه مودم و وضعیت مرکز تلفن را بررسی کنید.'};$start.Enabled=(-not $run.session -and $ports.Count -gt 0);$portChoice.Enabled=(-not $run.session)}else{$status.Text='شروع آزمون ممکن نشد: '+$_.Exception.Message;$start.Enabled=$false}}
 })
 $stop.Add_Click({if($DesktopMode -and $run.desktopBusy){$run.stopping=$true;$run.resumePending=$false;$run.autoResumeAfterConfig=$false;$stop.Enabled=$false;$status.Text='پاسخ‌گویی پس از پایان اعمال تنظیمات متوقف می‌ماند.';return};if($ReceiveCalls){$run.stopping=$true;$run.nextStart=$null;if(-not $run.session -or ($run.session.Completed -and $run.finished)){$timer.Stop();Release-ReceiverLock;$stop.Enabled=$false;$run.session=$null;$start.Enabled=$consent.Checked;$consent.Enabled=$true;$portChoice.Enabled=$true;$status.Text='پاسخ‌گویی متوقف شد.'}};if($run.session -and -not $run.session.Completed){$run.session.Cancel();$stop.Enabled=$false;$status.Text='در حال قطع تماس و آزادسازی مودم…'}})
 $timer.Add_Tick({
  if($ReceiveCalls -and $run.nextStart -and [datetime]::UtcNow -ge $run.nextStart){$run.nextStart=$null;$run.session=$null;$start.Enabled=$true;$start.PerformClick();return}
  if($ReceiveCalls -and $run.nextStart){return}
  if(-not $run.session){return}
  if($run.session.Completed){
   $timer.Stop();$stop.Enabled=$false
   $result=$run.session.Result
   $run.report=Join-Path $reportFolder ($(if($CompareGreetingModes){'modem-greeting-modes-'}else{'modem-bridge-result-'})+[datetime]::Now.ToString('yyyyMMdd-HHmmss')+'-'+[guid]::NewGuid().ToString('N').Substring(0,6)+'.json')
   $report=[ordered]@{report_type=$(if($CompareGreetingModes){'modem_greeting_mode_comparison'}else{'modem_bridge_test'});schema_version=1;receiver_mode=[bool]$ReceiveCalls;comparison_mode=[bool]$CompareGreetingModes;line_echo=$(if($run.echo){$run.echo.Metrics}else{$null});destination=$run.profile.modem_gateway.destination;port=[string]$portChoice.SelectedItem;audio_recorded=$false;result=($result|Select-Object DiagnosticVersion,DuplexStartedAtMilliseconds,TransmitOnlyStarted,SwitchedToDuplex,ModeTransitionMilliseconds,ModemSampleFormat,ModemCodec,ModemTransmitBytes,EffectiveFlowControl,HostFlowControlSynchronized,MaxSerialAvailabilityMilliseconds,MaxSerialReadMilliseconds,MaxSerialWriteMilliseconds,MaxDecodeMilliseconds,MaxEncodeMilliseconds,MaxEndpointMilliseconds,MaxTransmitLagMilliseconds,RequestedTransmitLevel,OriginalTransmitLevel,TransmitLevelApplied,TransmitLevelRestored,Status,ErrorCode,FailureStage,FailureError,CleanupError,RingDetected,Answered,DuplexStarted,BridgeStarted,CleanupConfirmed,Cancelled,PausedForConfiguration,EndReason,TransmittedSamples,ReceivedSamples,RawReceivedSamples,RemovedRepeatedSamples,ReceiveFraming,StreamMilliseconds,SampleWindows,ReceiveRateAfterWarmup,TransmitRateAfterWarmup,BridgeTimingReady,Events,Trace);greeting=$(if($run.useLocalGreeting){$run.endpoint|Select-Object PromptSha256,PromptSamples,GreetingSamples,GreetingPlaybackAudio,MenuStarted,BargeInRequested,HardwareKeypadMode,BufferedDigitCount,RelayedDigitCount}else{$null});sip=$(if(-not $CompareGreetingModes){$run.endpoint.Metrics}else{$null});comparison=$(if($CompareGreetingModes){$run.endpoint|Select-Object PromptSha256,PromptSamples,CurrentPhase,FirstMode,SecondMode,FirstPhaseSamples,SecondPhaseSamples,AudioRecorded}else{$null})}
   $reportSaved=$false
   try{[IO.File]::WriteAllText($run.report,($report|ConvertTo-Json -Depth 9),[Text.UTF8Encoding]::new($false));$reportSaved=$true;if($result.Status -eq 'failed'){if($result.ErrorCode -eq 'serial_port_unavailable'){$status.Text='درگاه مودم در دسترس نیست؛ ممکن است برنامه دیگری از آن استفاده کند یا دسترسی ویندوز محدود باشد. درگاه را آزاد کنید و دوباره شروع کنید.'}elseif($result.ErrorCode -eq 'audio_transmit_timing_failed'){$status.Text='ارسال صدا به مودم از زمان مجاز عقب افتاد؛ آزمون متوقف شد. نتیجه بازیابی تنظیمات در گزارش ذخیره شده است.'}else{$status.Text='اتصال خط کامل نشد. گزارش خطا در همین پوشه ذخیره شد.'}}elseif($result.Cancelled){$status.Text='آزمون متوقف و گزارش در همین پوشه ذخیره شد.'}else{$status.Text='آزمون پایان یافت. گزارش در همین پوشه ذخیره شد. نتیجه شنیدن صدا و قطع تماس را اعلام کنید.'}}catch{$status.Text='آزمون پایان یافت؛ ذخیره گزارش ممکن نشد: '+$_.Exception.Message}
   $run.finished=$true
   $run.lastResult=$result
   if($DesktopMode -and $run.pauseForConfiguration -and $result.CleanupConfirmed -and $reportSaved){
    $run.session=$null;$run.nextStart=$null
    Start-TelephonyDesktopApply -Resume:($run.autoResumeAfterConfig -and -not $run.stopping -and -not $run.wantClose)
    return
   }
   if($ReceiveCalls){
    if($result.Answered){$run.completedCalls++}
    if([OfficeAutomation.Telephony.Diagnostics.IncomingReception]::CanRearm($result,($run.stopping -or $run.wantClose),$reportSaved)){
     $run.nextStart=[datetime]::UtcNow.AddSeconds(1);$timer.Start();$stop.Enabled=$true;$status.Text=('تماس پایان یافت؛ در حال آماده‌سازی برای تماس بعدی. تعداد تماس‌ها: '+$run.completedCalls)
    }else{Release-ReceiverLock;$run.session=$null;$consent.Enabled=$true;$portChoice.Enabled=$true;$start.Enabled=$consent.Checked;if($run.stopping){$status.Text='پاسخ‌گویی متوقف و خط آزاد شد.'}else{if($reportSaved){$status.Text='پاسخ‌گویی به دلیل خطا متوقف شد. گزارش بررسی: '+$run.report}else{$status.Text+=' پاسخ‌گویی خودکار متوقف شد.'}}}
   }
   if($run.wantClose){$form.Close()}
  }else{
   if($run.useLocalGreeting -and $run.session.Progress -eq 'streaming' -and -not $run.endpoint.MenuStarted){$status.Text=$(if($run.endpoint.DuplexReady){'در حال آماده‌سازی انتخاب داخلی؛ شماره واردشده حفظ می‌شود.'}else{'در حال پخش پیام راهنما؛ می‌توانید همین حالا شماره را وارد کنید.'});return}
   if($CompareGreetingModes -and $run.session.Progress -eq 'streaming'){$status.Text=('در حال پخش نوبت '+$run.endpoint.CurrentPhase+' از دو نوبت؛ بلندی دو پیام را مقایسه کنید.');return}
   if(($CompareGreetingModes -or $run.useLocalGreeting) -and $run.session.Progress -eq 'switching_playback_mode'){$status.Text='در حال تغییر از پخش پیام به حالت دوطرفه…';return}
   $messages=@{preparing='در حال آماده‌سازی مودم…';waiting_ring='آماده؛ اکنون با گوشی با خط شرکت تماس بگیرید.';connecting_pbx='در حال اتصال به مرکز تلفن…';answering='در حال پاسخ به تماس…';streaming='پل صوتی برقرار است؛ از داخلی پاسخ دهید. اگر مقصد راهنمای صوتی است، کلید آن را از گوشی بزنید. برای پایان، از داخلی قطع کنید.';hanging_up='در حال قطع تماس و بازیابی تنظیمات مودم…'}
   if($ReceiveCalls){$messages.waiting_ring='آماده دریافت تماس ورودی؛ برنامه را باز نگه دارید.'}
   $phase=$run.session.Progress;if($messages.ContainsKey($phase)){$status.Text=$messages[$phase]}
  }
 })
 $form.Add_FormClosing({param($sender,$event) if($run.session -and -not $run.finished){$event.Cancel=$true;$run.wantClose=$true;$run.stopping=$true;$run.nextStart=$null;$run.session.Cancel();$status.Text='در حال قطع امن تماس…'}})
 if($DesktopMode){
  Initialize-TelephonyDesktop -Form $form -Layout $layout -Run $run -Start $start -Stop $stop -Consent $consent -LabRoot $LabRoot -EngineDirectory $EngineDirectory -DataRoot $ConnectionRoot
  if(-not $ports.Count){$consent.Enabled=$false;$start.Enabled=$false;$status.Text='مودم آماده‌ای پیدا نشد؛ اتصال دستگاه و نصب درایور را بررسی کنید. تنظیمات اتوماسیون همچنان در دسترس است.'}
  $form.AutoScaleMode='Dpi';$form.MinimumSize=[Drawing.Size]::new(820,700)
  $layout.Padding=[Windows.Forms.Padding]::new(28);$layout.BackColor=[Drawing.Color]::FromArgb(248,250,252)
  $layout.RowStyles[0].Height=100;$layout.RowStyles[1].Height=60;$layout.RowStyles[2].Height=65;$layout.RowStyles[3].Height=0
  $consent.Visible=$false;$consent.Checked=$true;$start.Enabled=($ports.Count -gt 0)
  $layout.Controls.Remove($consent)
  $desktopPanel=@($layout.Controls | Where-Object {$_ -is [Windows.Forms.TableLayoutPanel]})[0]
  $layout.RowStyles.Clear();$layout.RowCount=6
  foreach($height in @(100,60,65,100,65,160)){[void]$layout.RowStyles.Add([Windows.Forms.RowStyle]::new([Windows.Forms.SizeType]::Absolute,$height))}
  $layout.SetRow($notice,0);$layout.SetRow($destination,1);$layout.SetRow($portPanel,2);$layout.SetRow($status,3);$layout.SetRow($buttons,4);$layout.SetRow($desktopPanel,5)
  $notice.BackColor=[Drawing.Color]::FromArgb(49,46,129);$notice.ForeColor=[Drawing.Color]::White;$notice.Padding=[Windows.Forms.Padding]::new(18);$notice.Font=[OfficeAutomation.Telephony.DesktopFont]::Create(12,[Drawing.FontStyle]::Regular)
  $destination.BackColor=[Drawing.Color]::White;$destination.Padding=[Windows.Forms.Padding]::new(16)
  $portPanel.BackColor=[Drawing.Color]::White;$portPanel.Padding=[Windows.Forms.Padding]::new(12)
  $status.Padding=[Windows.Forms.Padding]::new(16);$status.ForeColor=[Drawing.Color]::FromArgb(15,23,42)
  foreach($button in @($start,$stop)){$button.FlatStyle='Flat';$button.FlatAppearance.BorderSize=0;$button.Height=46;$button.Cursor='Hand'}
  $start.BackColor=[Drawing.Color]::FromArgb(67,56,202);$start.ForeColor=[Drawing.Color]::White;$stop.BackColor=[Drawing.Color]::FromArgb(226,232,240)
  $notice.Text='تلفن سازمانی'+[Environment]::NewLine+'مدیریت تماس ورودی، داخلی‌ها و راهنمای صوتی — مستقل یا متصل به اتوماسیون'
  $status.Text=if($ports.Count){'برای دریافت تماس، درگاه مودم را انتخاب و «شروع پاسخ‌گویی» را بزنید.'}else{'درگاه مودم پیدا نشد؛ اتصال دستگاه و درایور را بررسی کنید.'}
 }
 try{[void]$form.ShowDialog()}finally{$timer.Stop();$timer.Dispose();$form.Dispose();Release-ReceiverLock}
 Write-Host ('Test finished. Report: '+$run.report)
} catch {
 if($DesktopMode){[void][Windows.Forms.MessageBox]::Show('شروع تلفن سازمانی کامل نشد. گزارش modem-bridge-error.txt در پوشه برنامه ذخیره می‌شود.','تلفن سازمانی',[Windows.Forms.MessageBoxButtons]::OK,[Windows.Forms.MessageBoxIcon]::Error)}
 $message='ERROR: '+$_.Exception.Message;Write-Host $message -ForegroundColor Red
 try{[IO.File]::WriteAllText((Join-Path $PSScriptRoot 'modem-bridge-error.txt'),$message,[Text.UTF8Encoding]::new($false))}catch{}
 exit 1
}

finally { if($desktopMutex){try{$desktopMutex.ReleaseMutex()}finally{$desktopMutex.Dispose()}} }
