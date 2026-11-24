#requires -Version 5.1
$ErrorActionPreference='Stop'
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
Import-Module (Join-Path $PSScriptRoot 'TelephonyConnection.psm1') -DisableNameChecking
$script:Desktop=$null;$script:PairUi=$null
function Get-TelephonyDesktopMessage([string]$Code) {
 $messages=@{
  pairing_rejected='کد اتصال معتبر نیست یا اعتبار آن تمام شده است. از اتوماسیون کد جدید بگیرید.'
  connection_missing='ابتدا این کامپیوتر را به اتوماسیون متصل کنید.'
  connection_revoked='دسترسی این کامپیوتر لغو شده است؛ کد اتصال جدید لازم است.'
  connection_failed='ارتباط با اتوماسیون برقرار نشد. آدرس سامانه، شبکه و گواهی ارتباط را بررسی کنید.'
  invalid_server_url='آدرس کامل سامانه را به صورت HTTP یا HTTPS وارد کنید.'
  invalid_pairing_code='کد اتصال را کامل و بدون فاصله وارد کنید.'
  configuration_not_ready='ابتدا داخلی‌ها و راهنمای صوتی را در اتوماسیون تنظیم کنید؛ سپس دوباره تلاش کنید.'
  configuration_busy='یک مکالمه در جریان است؛ تنظیمات پس از پایان تماس اعمال می‌شوند.'
  configuration_shutdown_pending='مرکز تلفن در حال پایان‌دادن به کار جاری است؛ تغییرات هنوز اعمال نشده‌اند.'
  configuration_hash_mismatch='فایل تنظیمات کامل یا معتبر نیست؛ دریافت مجدد لازم است.'
  configuration_package_invalid='قالب تنظیمات معتبر نیست؛ نسخه برنامه و تنظیمات اتوماسیون را بررسی کنید.'
  configuration_destination_invalid='مقصد تماس معتبر نیست؛ مسیر تماس را در اتوماسیون اصلاح کنید.'
  configuration_other_process_running='مرکز تلفن دیگری روی این کامپیوتر فعال است؛ تنظیمات آن تغییر نکرد.'
  configuration_service_active='سرویس دیگری از مرکز تلفن فعال است؛ برای جلوگیری از تداخل، تغییرات اعمال نشد.'
  configuration_apply_failed='اعمال تنظیمات کامل نشد؛ پیش از ادامه، وضعیت مرکز تلفن و گزارش خطا را بررسی کنید.'
  configuration_process_identity_changed='هویت مرکز تلفن با پروفایل فعلی مطابق نیست؛ هیچ فرایندی متوقف نشد.'
 }
 if($messages.ContainsKey($Code)){return $messages[$Code]}
 return 'عملیات کامل نشد؛ اتصال سامانه و وضعیت مرکز تلفن را بررسی کنید.'
}
function Start-TelephonyDesktopOperation([string]$Action,[hashtable]$Parameters) {
 $pipeline=[PowerShell]::Create()
 [void]$pipeline.AddCommand((Join-Path $PSScriptRoot 'Invoke-TelephonyOperation.ps1'))
 [void]$pipeline.AddParameter('Action',$Action)
 foreach($key in $Parameters.Keys){if($null -ne $Parameters[$key]){[void]$pipeline.AddParameter($key,$Parameters[$key])}}
 try{$handle=$pipeline.BeginInvoke();return @{pipeline=$pipeline;handle=$handle;action=$Action}}catch{$pipeline.Dispose();throw}
}
function Receive-TelephonyDesktopOperation($Operation) {
 if(-not $Operation -or -not $Operation.handle.IsCompleted){return $null}
 try{
  $output=@($Operation.pipeline.EndInvoke($Operation.handle));$result=@($output|Where-Object {'success' -in $_.PSObject.Properties.Name})|Select-Object -Last 1
  if(-not $result){return [pscustomobject]@{success=$false;paired=$false;error_code='connection_failed';connection=$null;pbx=$null}}
  return $result
 }catch{return [pscustomobject]@{success=$false;paired=$false;error_code='connection_failed';connection=$null;pbx=$null}}
 finally{$Operation.pipeline.Dispose()}
}
function Show-TelephonyConnectionDialog {
 [CmdletBinding()]param([Windows.Forms.Form]$Owner,[string]$DataRoot,[string]$LabRoot,[string]$EngineDirectory,[switch]$Initialize)
 $metadata=Get-TelephonyConnection -DataRoot $DataRoot
 $window=[Windows.Forms.Form]::new();$window.Text='اتصال تلفن سازمانی به اتوماسیون';$window.Size=[Drawing.Size]::new(760,510);$window.MinimumSize=[Drawing.Size]::new(620,460);$window.StartPosition='CenterScreen';$window.RightToLeft='Yes';$window.RightToLeftLayout=$true;$window.Font=[Drawing.Font]::new('Segoe UI',11);$window.BackColor=[Drawing.Color]::White
 $layout=[Windows.Forms.TableLayoutPanel]::new();$layout.Dock='Fill';$layout.Padding=[Windows.Forms.Padding]::new(24);$layout.ColumnCount=1;$layout.RowCount=7
 foreach($height in @(90,28,44,28,44,95,55)){[void]$layout.RowStyles.Add([Windows.Forms.RowStyle]::new([Windows.Forms.SizeType]::Absolute,$height))}
 $intro=[Windows.Forms.Label]::new();$intro.Dock='Fill';$intro.Text='در اتوماسیون، بخش «تلفن سازمانی ← اتصال کامپیوتر» را باز کنید و کد اتصال بگیرید. این کد فقط یک بار استفاده می‌شود. رمز داخلی‌ها تغییر نمی‌کند.'
 $serverLabel=[Windows.Forms.Label]::new();$serverLabel.Text='آدرس سامانه اتوماسیون';$serverLabel.Dock='Fill'
 $server=[Windows.Forms.TextBox]::new();$server.Dock='Fill';$server.RightToLeft='No';$server.Text=if($metadata.connected){$metadata.server_url}else{'https://'}
 $codeLabel=[Windows.Forms.Label]::new();$codeLabel.Text='کد یک‌بارمصرف اتصال';$codeLabel.Dock='Fill'
 $code=[Windows.Forms.TextBox]::new();$code.Dock='Fill';$code.RightToLeft='No';$code.MaxLength=64;$code.Font=[Drawing.Font]::new('Consolas',10)
 $message=[Windows.Forms.Label]::new();$message.Dock='Fill';$message.Text=if($metadata.connected){'اتصال قبلی موجود است؛ برای ادامه با همان اتصال، کد را خالی بگذارید. برای اتصال جدید، کد تازه وارد کنید.'}else{'پس از وارد کردن آدرس و کد، «اتصال» را بزنید. تأیید گواهی HTTPS ویندوز غیرفعال نمی‌شود.'}
 $buttons=[Windows.Forms.FlowLayoutPanel]::new();$buttons.Dock='Fill'
 $connect=[Windows.Forms.Button]::new();$connect.Text='اتصال و ادامه';$connect.Size=[Drawing.Size]::new(170,42);$connect.BackColor=[Drawing.Color]::FromArgb(67,56,202);$connect.ForeColor=[Drawing.Color]::White;$connect.FlatStyle='Flat'
 $cancel=[Windows.Forms.Button]::new();$cancel.Text='انصراف';$cancel.Size=[Drawing.Size]::new(110,42);$cancel.Add_Click({if(-not $script:PairUi.operation){$script:PairUi.window.Close()}})
 [void]$buttons.Controls.Add($connect);[void]$buttons.Controls.Add($cancel)
 foreach($control in @($intro,$serverLabel,$server,$codeLabel,$code,$message,$buttons)){[void]$layout.Controls.Add($control)}
 [void]$window.Controls.Add($layout);$window.AcceptButton=$connect
 $timer=[Windows.Forms.Timer]::new();$timer.Interval=200
 $script:PairUi=@{window=$window;server=$server;code=$code;message=$message;connect=$connect;cancel=$cancel;operation=$null;success=$false;timer=$timer;data=$DataRoot;lab=$LabRoot;engine=$EngineDirectory;initialize=[bool]$Initialize}
 $connect.Add_Click({
  $ui=$script:PairUi
  if($ui.operation){return}
  if($ui.server.Text -notmatch '^https?://'){$ui.message.Text='آدرس کامل سامانه را وارد کنید.';return}
  if($ui.code.Text.Trim() -and $ui.code.Text.Trim() -notmatch '\A[a-f0-9]{64}\z'){$ui.message.Text='کد اتصال باید کامل و بدون فاصله باشد.';return}
  try{
   $ui.connect.Enabled=$false;$ui.cancel.Enabled=$false;$ui.server.Enabled=$false;$ui.code.Enabled=$false;$ui.message.Text='در حال اتصال و بررسی تنظیمات…'
   $ui.operation=Start-TelephonyDesktopOperation 'Connect' @{ServerUrl=$ui.server.Text.Trim();PairingCode=$ui.code.Text.Trim();DataRoot=$ui.data;LabRoot=$ui.lab;EngineDirectory=$ui.engine;Initialize=$ui.initialize}
   $ui.timer.Start()
  }catch{$ui.message.Text=Get-TelephonyDesktopMessage 'connection_failed';$ui.connect.Enabled=$true;$ui.cancel.Enabled=$true;$ui.server.Enabled=$true;$ui.code.Enabled=$true}
 })
 $timer.Add_Tick({
  $ui=$script:PairUi;if(-not $ui.operation){return}
  if(-not $ui.operation.handle.IsCompleted){return}
  $result=Receive-TelephonyDesktopOperation $ui.operation;$ui.operation=$null;$ui.timer.Stop()
  if($result.paired){$ui.code.Text=''}
  $ui.connect.Enabled=$true;$ui.cancel.Enabled=$true;$ui.server.Enabled=$true;$ui.code.Enabled=$true
  if($result.success){$ui.success=$true;$ui.window.DialogResult='OK';$ui.window.Close()}
  else{$ui.message.Text=Get-TelephonyDesktopMessage $result.error_code}
 })
 $window.Add_FormClosing({param($sender,$event) if($script:PairUi.operation){$event.Cancel=$true;$script:PairUi.message.Text='در حال پایان عملیات اتصال؛ لطفاً صبر کنید.'}})
 try{if($Owner){[void]$window.ShowDialog($Owner)}else{[void]$window.ShowDialog()};return [bool]$script:PairUi.success}
 finally{$timer.Stop();$timer.Dispose();$window.Dispose();$script:PairUi=$null}
}
function Release-TelephonyDesktopPortLock {
 $run=$script:Desktop.run
 if($run.mutex){try{$run.mutex.ReleaseMutex()}finally{$run.mutex.Dispose();$run.mutex=$null}}
}
function Start-TelephonyDesktopApply {
 [CmdletBinding()]param([switch]$Resume)
 $desktop=$script:Desktop;if(-not $desktop -or $desktop.operation){return}
 $run=$desktop.run
 if($run.lastResult -and -not $run.lastResult.CleanupConfirmed){$desktop.status.Text='آزادشدن مودم تأیید نشده است؛ پیش از اعمال تنظیمات وضعیت خط را بررسی کنید.';return}
 $run.desktopBusy=$true;if($Resume){$run.resumePending=$true};$run.autoResumeAfterConfig=[bool]$run.resumePending;$desktop.stop.Enabled=[bool]$run.resumePending;$run.nextStart=$null;$desktop.start.Enabled=$false;$desktop.connect.Enabled=$false;$desktop.apply.Enabled=$false
 $desktop.status.Text='در حال اعمال تنظیمات در زمان بیکاری؛ لطفاً صبر کنید.'
 $desktop.operation=Start-TelephonyDesktopOperation 'Apply' @{DataRoot=$desktop.data;LabRoot=$desktop.lab;EngineDirectory=$desktop.engine}
}
function Request-TelephonyDesktopApply {
 $desktop=$script:Desktop;if(-not $desktop -or $desktop.operation){return}
 $run=$desktop.run
 if($run.session -and $run.session.Completed -and -not $run.finished){$run.pauseForConfiguration=$true;$run.autoResumeAfterConfig=(-not $run.stopping);return}
 if($desktop.pbx -and $desktop.pbx.active_calls -gt 0){$desktop.status.Text='تنظیمات پس از پایان تماس‌ها اعمال می‌شوند.';return}
 if($run.session -and -not $run.session.Completed){
  if($run.session.Progress -ne 'waiting_ring'){$desktop.status.Text='تنظیمات جدید پس از پایان تماس اعمال می‌شوند.';return}
  $run.pauseForConfiguration=$true;$run.autoResumeAfterConfig=$true;$run.session.RequestIdlePause()
  $desktop.status.Text='در حال آزادسازی مودم برای اعمال تنظیمات…'
 }else{Start-TelephonyDesktopApply -Resume:([bool]$run.nextStart -or [bool]$run.resumePending)}
}

function Initialize-TelephonyDesktop {
 [CmdletBinding()]param([Windows.Forms.Form]$Form,[Windows.Forms.TableLayoutPanel]$Layout,[hashtable]$Run,[Windows.Forms.Button]$Start,[Windows.Forms.Button]$Stop,[Windows.Forms.CheckBox]$Consent,[string]$LabRoot,[string]$EngineDirectory,[string]$DataRoot)
 $Form.Text='تلفن سازمانی — اتوماسیون اداری';$Form.Size=[Drawing.Size]::new(860,730);$Form.Font=[Drawing.Font]::new('Segoe UI',11);$Form.BackColor=[Drawing.Color]::FromArgb(248,250,252)
 $panel=[Windows.Forms.TableLayoutPanel]::new();$panel.Dock='Fill';$panel.RowCount=2;$panel.ColumnCount=1
 [void]$panel.RowStyles.Add([Windows.Forms.RowStyle]::new([Windows.Forms.SizeType]::Absolute,55));[void]$panel.RowStyles.Add([Windows.Forms.RowStyle]::new([Windows.Forms.SizeType]::Percent,100))
 $status=[Windows.Forms.Label]::new();$status.Dock='Fill';$status.Text='در حال بررسی اتصال به اتوماسیون…';$status.ForeColor=[Drawing.Color]::FromArgb(49,46,129)
 $buttons=[Windows.Forms.FlowLayoutPanel]::new();$buttons.Dock='Fill';$buttons.AutoScroll=$true
 $connect=[Windows.Forms.Button]::new();$connect.Text='اتصال به اتوماسیون';$connect.Size=[Drawing.Size]::new(175,40)
 $sync=[Windows.Forms.Button]::new();$sync.Text='دریافت تنظیمات';$sync.Size=[Drawing.Size]::new(145,40)
 $apply=[Windows.Forms.Button]::new();$apply.Text='اعمال تنظیمات';$apply.Size=[Drawing.Size]::new(145,40)
 $settings=[Windows.Forms.Button]::new();$settings.Text='مدیریت داخلی‌ها';$settings.Size=[Drawing.Size]::new(155,40)
 $ivr=[Windows.Forms.Button]::new();$ivr.Text='پیام و سناریوی تماس';$ivr.Size=[Drawing.Size]::new(175,40)
 foreach($button in @($connect,$sync,$apply,$settings,$ivr)){[void]$buttons.Controls.Add($button)}
 [void]$panel.Controls.Add($status);[void]$panel.Controls.Add($buttons)
 [void]$Layout.RowStyles.Add([Windows.Forms.RowStyle]::new([Windows.Forms.SizeType]::Absolute,160));$Layout.RowCount++;[void]$Layout.Controls.Add($panel,0,($Layout.RowCount-1))
 $timer=[Windows.Forms.Timer]::new();$timer.Interval=500
 $Run.desktopBusy=$false;$Run.resumePending=$false;$Run.pauseForConfiguration=$false;$Run.autoResumeAfterConfig=$false;$Run.lastResult=$null
 $script:Desktop=@{form=$Form;run=$Run;status=$status;connect=$connect;sync=$sync;apply=$apply;start=$Start;stop=$Stop;consent=$Consent;timer=$timer;operation=$null;pbx=$null;blockedRevision=$null;data=$DataRoot;lab=$LabRoot;engine=$EngineDirectory;nextSync=[datetime]::UtcNow;metadata=$null;pairingOpen=$false;retryAt=[datetime]::MinValue}
 $connect.Add_Click({
  $desktop=$script:Desktop;if($desktop.operation){return}
  $desktop.pairingOpen=$true
  try{if(Show-TelephonyConnectionDialog -Owner $desktop.form -DataRoot $desktop.data -LabRoot $desktop.lab -EngineDirectory $desktop.engine){$desktop.nextSync=[datetime]::UtcNow}}
  finally{$desktop.pairingOpen=$false}
 })
 $sync.Add_Click({$script:Desktop.nextSync=[datetime]::UtcNow})
 $apply.Add_Click({Request-TelephonyDesktopApply})
 $settings.Add_Click({Open-TelephonyDesktopPage 'extensions'})
 $ivr.Add_Click({Open-TelephonyDesktopPage 'ivr'})
 $timer.Add_Tick({
  $desktop=$script:Desktop;if(-not $desktop -or $desktop.pairingOpen){return}
  $run=$desktop.run
  if($desktop.operation){
   if(-not $desktop.operation.handle.IsCompleted){return}
   $action=$desktop.operation.action;$result=Receive-TelephonyDesktopOperation $desktop.operation;$desktop.operation=$null
   if($result.connection){$desktop.metadata=$result.connection};if($result.pbx){$desktop.pbx=$result.pbx}
   $desktop.connect.Enabled=$true;$desktop.sync.Enabled=$true
   if($action -eq 'Apply'){
    $run.desktopBusy=$false;$run.pauseForConfiguration=$false;$desktop.retryAt=[datetime]::UtcNow.AddSeconds(15)
    if($result.success){$desktop.blockedRevision=$null;$desktop.status.Text='آخرین تنظیمات اتوماسیون روی مرکز تلفن اعمال شد.';$desktop.nextSync=[datetime]::UtcNow}
    else{
     $desktop.status.Text=Get-TelephonyDesktopMessage $result.error_code
     if($result.error_code -notin @('configuration_busy','configuration_shutdown_pending')){$desktop.blockedRevision=$desktop.metadata.state.pending_revision;$run.resumePending=$false}
    }
    if($run.wantClose){$desktop.form.Close();return}
    $canResume=$result.success -or $result.error_code -eq 'configuration_busy'
    if($run.resumePending -and -not $run.stopping -and $canResume){$run.session=$null;$run.finished=$true;$desktop.start.Enabled=$true;$desktop.start.PerformClick();$run.resumePending=$false}
    else{Release-TelephonyDesktopPortLock;$desktop.start.Enabled=$desktop.consent.Checked;$desktop.stop.Enabled=$false}
    $run.autoResumeAfterConfig=$false
   }elseif(-not $result.success){$desktop.status.Text=Get-TelephonyDesktopMessage $result.error_code}
   elseif($result.connection){
    $desktop.status.Text=if($result.connection.state.pending_revision){'تنظیمات جدید دریافت شد؛ پس از پایان تماس در زمان بیکاری اعمال می‌شوند.'}else{'ارتباط با اتوماسیون برقرار است؛ تنظیمات همگام هستند.'}
   }
   if($run.wantClose -and (-not $run.session -or $run.session.Completed)){$desktop.form.Close();return}
  }
  if($run.desktopBusy){return}
  $metadata=Get-TelephonyConnection -DataRoot $desktop.data;$desktop.metadata=$metadata
  $desktop.apply.Enabled=[bool]($metadata.state.pending_revision -and -not $desktop.operation)
  $desktop.sync.Enabled=[bool]($metadata.connected -and -not $desktop.operation)
  if(-not $metadata.connected){$desktop.status.Text='این کامپیوتر هنوز به اتوماسیون متصل نشده است.';return}
  if($metadata.state.pending_revision -and $metadata.state.pending_revision -ne $desktop.blockedRevision -and [datetime]::UtcNow -ge $desktop.retryAt -and -not $run.wantClose -and -not $desktop.operation){
   if(-not $run.session -or $run.session.Completed -or $run.session.Progress -eq 'waiting_ring'){Request-TelephonyDesktopApply;if($run.desktopBusy -or $run.pauseForConfiguration){return}}
  }
  if([datetime]::UtcNow -ge $desktop.nextSync -and -not $desktop.operation -and -not $run.wantClose){
   $runtime=if($run.session -and -not $run.session.Completed){if($run.session.Progress -eq 'waiting_ring'){'ready'}else{'busy'}}else{'stopped'}
   $desktop.nextSync=[datetime]::UtcNow.AddSeconds(15);$desktop.connect.Enabled=$false;$desktop.sync.Enabled=$false
   $desktop.operation=Start-TelephonyDesktopOperation 'Sync' @{DataRoot=$desktop.data;LabRoot=$desktop.lab;EngineDirectory=$desktop.engine;RuntimeState=$runtime}
  }
 })
 $Form.Add_FormClosing({
  param($sender,$event)
  $desktop=$script:Desktop;if($desktop -and $desktop.operation){$event.Cancel=$true;$desktop.run.wantClose=$true;$desktop.run.stopping=$true;$desktop.run.autoResumeAfterConfig=$false;$desktop.run.nextStart=$null;$desktop.status.Text='در حال پایان عملیات و حفظ تنظیمات؛ لطفاً صبر کنید.'}
 })
 $Form.Add_FormClosed({if($script:Desktop){$script:Desktop.timer.Stop();$script:Desktop.timer.Dispose();$script:Desktop=$null}})
 $timer.Start()
}
function Open-TelephonyDesktopPage([ValidateSet('extensions','ivr')][string]$Page) {
 $desktop=$script:Desktop;$metadata=Get-TelephonyConnection -DataRoot $desktop.data
 if(-not $metadata.connected){$desktop.status.Text='ابتدا اتصال به اتوماسیون را برقرار کنید.';return}
 $uri=[uri]::new([uri]$metadata.server_url,('telephony/'+$Page))
 [Diagnostics.Process]::Start([Diagnostics.ProcessStartInfo]::new($uri.AbsoluteUri))|Out-Null
}
Export-ModuleMember -Function Show-TelephonyConnectionDialog,Initialize-TelephonyDesktop,Start-TelephonyDesktopApply
