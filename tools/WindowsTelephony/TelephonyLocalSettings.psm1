#requires -Version 5.1
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
$script:LocalUi=$null
function Show-TelephonyLocalSettings {
 param([Windows.Forms.Form]$Owner,[string]$LabRoot,[string]$EngineDirectory,[string]$DataRoot)
 $state=Read-PbxLabState $LabRoot
 $form=[Windows.Forms.Form]::new();$form.Text='تنظیمات مستقل تلفن سازمانی';$form.Size=[Drawing.Size]::new(850,760);$form.MinimumSize=[Drawing.Size]::new(700,600);$form.StartPosition='CenterParent';$form.Font=[OfficeAutomation.Telephony.DesktopFont]::Create(11,[Drawing.FontStyle]::Regular);$form.RightToLeft='Yes';$form.RightToLeftLayout=$true;$form.BackColor=[Drawing.Color]::FromArgb(248,250,252)
 $area=[Windows.Forms.Screen]::FromControl($Owner).WorkingArea
 $form.AutoScaleMode='Dpi';$form.Size=[Drawing.Size]::new([Math]::Min(850,[int]($area.Width*0.94)),[Math]::Min(760,[int]($area.Height*0.94)))
 $form.MinimumSize=[Drawing.Size]::new([Math]::Min(700,$form.Width),[Math]::Min(560,$form.Height))
 $layout=[Windows.Forms.TableLayoutPanel]::new();$layout.Dock='Fill';$layout.Padding=[Windows.Forms.Padding]::new(24);$layout.ColumnCount=1;$layout.RowCount=7
 foreach($height in @(60,210,55,55,70,75,60)){[void]$layout.RowStyles.Add([Windows.Forms.RowStyle]::new([Windows.Forms.SizeType]::Absolute,$height))}
 $layout.AutoScroll=$true
 $title=[Windows.Forms.Label]::new();$title.Text='داخلی‌ها و مسیر تماس را روی همین کامپیوتر مدیریت کنید.';$title.Dock='Fill';$title.ForeColor=[Drawing.Color]::FromArgb(49,46,129)
 $grid=[Windows.Forms.DataGridView]::new();$grid.Dock='Fill';$grid.BackgroundColor=[Drawing.Color]::White;$grid.AutoSizeColumnsMode='Fill';$grid.RowHeadersVisible=$true;$grid.AllowUserToAddRows=$true;$grid.AllowUserToDeleteRows=$true
 $grid.BorderStyle='None';$grid.CellBorderStyle='SingleHorizontal';$grid.GridColor=[Drawing.Color]::FromArgb(226,232,240);$grid.RowHeadersVisible=$false;$grid.RowTemplate.Height=40;$grid.EnableHeadersVisualStyles=$false
 $grid.ColumnHeadersDefaultCellStyle.BackColor=[Drawing.Color]::FromArgb(241,245,249);$grid.ColumnHeadersDefaultCellStyle.ForeColor=[Drawing.Color]::FromArgb(51,65,85);$grid.ColumnHeadersHeight=44
 $grid.DefaultCellStyle.SelectionBackColor=[Drawing.Color]::FromArgb(224,231,255);$grid.DefaultCellStyle.SelectionForeColor=[Drawing.Color]::FromArgb(49,46,129);$grid.DefaultCellStyle.Padding=[Windows.Forms.Padding]::new(8)
 [void]$grid.Columns.Add('number','شماره داخلی');[void]$grid.Columns.Add('label','نام شخص یا واحد')
 foreach($account in $state.accounts){[void]$grid.Rows.Add([string]$account.extension,[string]$account.label)}
 $routePanel=[Windows.Forms.FlowLayoutPanel]::new();$routePanel.Dock='Fill'
 $routeLabel=[Windows.Forms.Label]::new();$routeLabel.Text='مسیر تماس ورودی';$routeLabel.AutoSize=$true
 $mode=[Windows.Forms.ComboBox]::new();$mode.DropDownStyle='DropDownList';$mode.Width=210;[void]$mode.Items.Add('اتصال مستقیم به داخلی');[void]$mode.Items.Add('پخش راهنما و انتخاب داخلی');$mode.SelectedIndex=if($state.modem_gateway.destination -eq '9197'){1}else{0}
 $target=[Windows.Forms.TextBox]::new();$target.Width=110;$target.RightToLeft='No';$target.Text=if($mode.SelectedIndex -eq 1){$state.ivr.operator_number}else{$state.modem_gateway.destination}
 foreach($item in @($routeLabel,$mode,$target)){[void]$routePanel.Controls.Add($item)}
 $audioPanel=[Windows.Forms.FlowLayoutPanel]::new();$audioPanel.Dock='Fill'
 $audio=[Windows.Forms.TextBox]::new();$audio.ReadOnly=$true;$audio.Width=480;$audio.RightToLeft='No';$audio.Text=if($state.ivr){$state.ivr.prompt_path}else{''}
 $browse=[Windows.Forms.Button]::new();$browse.Text='انتخاب پیام WAV';$browse.Size=[Drawing.Size]::new(170,38)
 $browse.Add_Click({$dialog=[Windows.Forms.OpenFileDialog]::new();$dialog.Filter='WAV audio (*.wav)|*.wav';try{if($dialog.ShowDialog() -eq 'OK'){$script:LocalUi.audio.Text=$dialog.FileName}}finally{$dialog.Dispose()}})
 [void]$audioPanel.Controls.Add($audio);[void]$audioPanel.Controls.Add($browse)
 $choices=[Windows.Forms.DataGridView]::new();$choices.Dock='Fill';$choices.AutoSizeColumnsMode='Fill';$choices.BackgroundColor=[Drawing.Color]::White;$choices.RowHeadersVisible=$false;$choices.AllowUserToAddRows=$true;$choices.AllowUserToDeleteRows=$true;$choices.BorderStyle='None';$choices.RowTemplate.Height=38
 $choices.ColumnHeadersHeightSizeMode='DisableResizing';$choices.ColumnHeadersHeight=46;$choices.EnableHeadersVisualStyles=$false
 $choices.ColumnHeadersDefaultCellStyle.BackColor=[Drawing.Color]::FromArgb(241,245,249);$choices.ColumnHeadersDefaultCellStyle.Alignment='MiddleRight';$choices.DefaultCellStyle.Padding=[Windows.Forms.Padding]::new(6)
 $choices.CellBorderStyle='SingleHorizontal';$choices.GridColor=[Drawing.Color]::FromArgb(226,232,240);$choices.DefaultCellStyle.SelectionBackColor=[Drawing.Color]::FromArgb(224,231,255);$choices.DefaultCellStyle.SelectionForeColor=[Drawing.Color]::FromArgb(49,46,129)
 $digitColumn=[Windows.Forms.DataGridViewComboBoxColumn]::new();$digitColumn.Name='digit';$digitColumn.HeaderText='کلید تماس‌گیرنده';foreach($digit in 0..9){[void]$digitColumn.Items.Add([string]$digit)}
 [void]$choices.Columns.Add($digitColumn);[void]$choices.Columns.Add('number','داخلی مقصد')
 if($state.ivr){foreach($choice in $state.ivr.choices){[void]$choices.Rows.Add([string]$choice.digit,[string]$choice.number)}}
 $message=[Windows.Forms.Label]::new();$message.Dock='Fill';$message.Text='شماره کنار مسیر، مقصد مستقیم یا اپراتور راهنماست. کلیدهای راهنما و داخلی مقصدشان را در جدول «مسیر تماس» تعریف کنید. شماره کامل داخلی نیز قابل شماره‌گیری است.'
 $buttons=[Windows.Forms.FlowLayoutPanel]::new();$buttons.Dock='Fill'
 $save=[Windows.Forms.Button]::new();$save.Text='ذخیره و اعمال';$save.Size=[Drawing.Size]::new(170,42);$save.BackColor=[Drawing.Color]::FromArgb(67,56,202);$save.ForeColor=[Drawing.Color]::White;$save.FlatStyle='Flat'
 $cancel=[Windows.Forms.Button]::new();$cancel.Text='بستن';$cancel.Size=[Drawing.Size]::new(100,42);$cancel.Add_Click({$script:LocalUi.form.Close()})
 [void]$buttons.Controls.Add($save);[void]$buttons.Controls.Add($cancel)
 $layout.RowStyles.Clear();$layout.RowCount=3
 [void]$layout.RowStyles.Add([Windows.Forms.RowStyle]::new([Windows.Forms.SizeType]::Percent,100))
 [void]$layout.RowStyles.Add([Windows.Forms.RowStyle]::new([Windows.Forms.SizeType]::Absolute,100))
 [void]$layout.RowStyles.Add([Windows.Forms.RowStyle]::new([Windows.Forms.SizeType]::Absolute,65))
 $tabs=[Windows.Forms.TabControl]::new();$tabs.Dock='Fill';$tabs.RightToLeft='Yes';$tabs.RightToLeftLayout=$true;$tabs.Padding=[Drawing.Point]::new(20,12)
 $extensionTab=[Windows.Forms.TabPage]::new('داخلی‌ها');$routeTab=[Windows.Forms.TabPage]::new('مسیر تماس');$audioTab=[Windows.Forms.TabPage]::new('پیام راهنما')
 foreach($tab in @($extensionTab,$routeTab,$audioTab)){$tab.Padding=[Windows.Forms.Padding]::new(18);$tab.BackColor=[Drawing.Color]::White;[void]$tabs.TabPages.Add($tab)}
 $extensionLayout=[Windows.Forms.TableLayoutPanel]::new();$extensionLayout.Dock='Fill';$extensionLayout.RowCount=2
 [void]$extensionLayout.RowStyles.Add([Windows.Forms.RowStyle]::new([Windows.Forms.SizeType]::Absolute,70));[void]$extensionLayout.RowStyles.Add([Windows.Forms.RowStyle]::new([Windows.Forms.SizeType]::Percent,100))
 [void]$extensionLayout.Controls.Add($title);[void]$extensionLayout.Controls.Add($grid);[void]$extensionTab.Controls.Add($extensionLayout)
 $routeLayout=[Windows.Forms.TableLayoutPanel]::new();$routeLayout.Dock='Fill';$routeLayout.RowCount=3
 foreach($height in @(80,70,150)){[void]$routeLayout.RowStyles.Add([Windows.Forms.RowStyle]::new([Windows.Forms.SizeType]::Absolute,$height))}
 $hint=[Windows.Forms.Label]::new();$hint.Dock='Fill';$hint.Text='کلید تماس‌گیرنده و داخلی مقصد را انتخاب کنید. برای حذف، ردیف را انتخاب و کلید Delete را بزنید.'
 foreach($item in @($routePanel,$hint,$choices)){[void]$routeLayout.Controls.Add($item)};[void]$routeTab.Controls.Add($routeLayout)
 $audioLayout=[Windows.Forms.FlowLayoutPanel]::new();$audioLayout.Dock='Fill';$audioLayout.FlowDirection='TopDown';$audioLayout.WrapContents=$false
 $audioHint=[Windows.Forms.Label]::new();$audioHint.Text='فایل خوشامدگویی را انتخاب کنید. قبل از ذخیره می‌توانید آن را بشنوید.';$audioHint.Size=[Drawing.Size]::new(680,65)
 $audioPanel.Size=[Drawing.Size]::new(680,100);$audioPanel.Dock='None'
 $preview=[Windows.Forms.Button]::new();$preview.Text='شنیدن پیام انتخاب‌شده';$preview.Size=[Drawing.Size]::new(230,44)
 $preview.Add_Click({try{$ui=$script:LocalUi;if($ui.player){$ui.player.Stop();$ui.player.Dispose()};$ui.player=[Media.SoundPlayer]::new($ui.audio.Text);$ui.player.Play()}catch{$script:LocalUi.message.Text='پخش فایل ممکن نشد؛ یک فایل WAV معتبر انتخاب کنید.'}})
 foreach($item in @($audioHint,$audioPanel,$preview)){[void]$audioLayout.Controls.Add($item)};[void]$audioTab.Controls.Add($audioLayout)
 foreach($item in @($tabs,$message,$buttons)){[void]$layout.Controls.Add($item)}
 [void]$form.Controls.Add($layout)
 $timer=[Windows.Forms.Timer]::new();$timer.Interval=200
 $script:LocalUi=@{form=$form;grid=$grid;mode=$mode;target=$target;audio=$audio;choices=$choices;message=$message;save=$save;cancel=$cancel;timer=$timer;operation=$null;lab=$LabRoot;engine=$EngineDirectory;data=$DataRoot;success=$false;player=$null}
 $save.Add_Click({
  $ui=$script:LocalUi
  try{
   [void]$ui.grid.EndEdit();$extensions=@()
   foreach($row in $ui.grid.Rows){if($row.IsNewRow){continue};$extensions+=[pscustomobject]@{number=([string]$row.Cells[0].Value).Trim();label=([string]$row.Cells[1].Value).Trim()}}
   [void]$ui.choices.EndEdit();$choices=@()
   foreach($row in $ui.choices.Rows){if($row.IsNewRow){continue};$choices+=[pscustomobject]@{digit=([string]$row.Cells[0].Value).Trim();number=([string]$row.Cells[1].Value).Trim()}}
   if(-not $extensions.Count -or @($extensions.number|Select-Object -Unique).Count -ne $extensions.Count){throw 'حداقل یک داخلی با شماره غیرتکراری تعریف کنید.'}
   foreach($extension in $extensions){if($extension.number -notmatch '\A[1-9][0-9]{2,5}\z' -or $extension.number -in @('9196','9197')){throw 'شماره داخلی باید ۳ تا ۶ رقم باشد؛ ۹۱۹۶ و ۹۱۹۷ رزرو هستند.'}}
   foreach($choice in $choices){if($choice.digit -notmatch '\A[0-9]\z' -or $choice.number -notin $extensions.number){throw 'برای هر کلید، یک داخلی موجود انتخاب کنید.'}}
   if(@($choices.digit|Select-Object -Unique).Count -ne $choices.Count){throw 'کلیدهای راهنما نباید تکراری باشند.'}
   if($ui.target.Text.Trim() -notin $extensions.number){throw 'مقصد تماس یا اپراتور باید یکی از داخلی‌های موجود باشد.'}
   $enabled=$ui.mode.SelectedIndex -eq 1
   if($enabled -and -not (Test-Path -LiteralPath $ui.audio.Text)){throw 'برای راهنمای صوتی فایل WAV انتخاب کنید.'}
   $configuration=[pscustomobject]@{schema_version=1;type='office_telephony_configuration';extensions=$extensions;ivr=[pscustomobject]@{enabled=$enabled;timeout_seconds=10;retries=2;operator_number=if($enabled){$ui.target.Text.Trim()}else{$null};choices=$choices;audio_file=if($enabled){'greeting.wav'}else{$null}};incoming=[pscustomobject]@{mode=if($enabled){'ivr'}else{'extension'};number=$ui.target.Text.Trim()}}
   Assert-PbxOfficeConfiguration $configuration
   $directory=Join-Path $ui.lab ('local-settings-'+[guid]::NewGuid().ToString('N'));[void][IO.Directory]::CreateDirectory($directory)
   if($enabled){Copy-Item -LiteralPath $ui.audio.Text -Destination (Join-Path $directory 'greeting.wav')}
   $path=Join-Path $directory 'office-telephony.json';[IO.File]::WriteAllText($path,($configuration|ConvertTo-Json -Depth 12),[Text.UTF8Encoding]::new($false))
   $ui.save.Enabled=$false;$ui.grid.Enabled=$false;$ui.message.Text='در حال بررسی و اعمال تنظیمات…'
   $ui.operation=Start-TelephonyDesktopOperation 'LocalApply' @{LocalConfigurationPath=$path;DataRoot=$ui.data;LabRoot=$ui.lab;EngineDirectory=$ui.engine};$ui.timer.Start()
  }catch{$ui.message.Text='ذخیره نشد: '+$_.Exception.Message;$ui.save.Enabled=$true;$ui.grid.Enabled=$true}
 })
 $timer.Add_Tick({
  $ui=$script:LocalUi;if(-not $ui.operation -or -not $ui.operation.handle.IsCompleted){return}
  $result=Receive-TelephonyDesktopOperation $ui.operation;$ui.operation=$null;$ui.timer.Stop();$ui.save.Enabled=$true;$ui.grid.Enabled=$true
  if($result.success){$ui.success=$true;$ui.message.Text='تنظیمات مستقل ذخیره و روی مرکز تلفن اعمال شد.'}
  else{$ui.message.Text=Get-TelephonyDesktopMessage $result.error_code}
 })
 $form.Add_FormClosing({param($sender,$event)if($script:LocalUi.operation){$event.Cancel=$true;$script:LocalUi.message.Text='در حال پایان عملیات؛ لطفاً صبر کنید.'}})
 try{[void]$form.ShowDialog($Owner);return $script:LocalUi.success}
 finally{if($script:LocalUi.player){$script:LocalUi.player.Stop();$script:LocalUi.player.Dispose()};$timer.Stop();$timer.Dispose();$form.Dispose();$script:LocalUi=$null}
}

function Show-TelephonyLocalAccounts {
 param([Windows.Forms.Form]$Owner,[string]$LabRoot)
 $state=Read-PbxLabState $LabRoot
 $window=[Windows.Forms.Form]::new();$window.Text='مشخصات اتصال داخلی‌ها';$window.Size=[Drawing.Size]::new(880,580);$window.MinimumSize=[Drawing.Size]::new(720,480);$window.StartPosition='CenterParent';$window.RightToLeft='Yes';$window.RightToLeftLayout=$true;$window.Font=[OfficeAutomation.Telephony.DesktopFont]::Create(11,[Drawing.FontStyle]::Regular)
 $layout=[Windows.Forms.TableLayoutPanel]::new();$layout.Dock='Fill';$layout.Padding=[Windows.Forms.Padding]::new(24);$layout.RowCount=3;$layout.ColumnCount=1
 [void]$layout.RowStyles.Add([Windows.Forms.RowStyle]::new([Windows.Forms.SizeType]::Absolute,105));[void]$layout.RowStyles.Add([Windows.Forms.RowStyle]::new([Windows.Forms.SizeType]::Percent,100));[void]$layout.RowStyles.Add([Windows.Forms.RowStyle]::new([Windows.Forms.SizeType]::Absolute,85))
 $server=[Windows.Forms.TextBox]::new();$server.Dock='Fill';$server.ReadOnly=$true;$server.Multiline=$true;$server.RightToLeft='No';$server.Text='SIP Server / Proxy: '+$state.bind_address+':'+$state.sip_port+[Environment]::NewLine+'Domain: '+$state.bind_address+[Environment]::NewLine+'Transport: UDP'
 $grid=[Windows.Forms.DataGridView]::new();$grid.Dock='Fill';$grid.ReadOnly=$true;$grid.AllowUserToAddRows=$false;$grid.AllowUserToDeleteRows=$false;$grid.RowHeadersVisible=$false;$grid.AutoSizeColumnsMode='Fill';$grid.BackgroundColor=[Drawing.Color]::White;$grid.BorderStyle='None';$grid.RowTemplate.Height=40
 [void]$grid.Columns.Add('extension','داخلی / نام کاربری');[void]$grid.Columns.Add('password','رمز داخلی')
 $grid.Columns[1].FillWeight=250
 foreach($account in $state.accounts){[void]$grid.Rows.Add([string]$account.extension,[string]$account.password)}
 $notice=[Windows.Forms.Label]::new();$notice.Dock='Fill';$notice.Padding=[Windows.Forms.Padding]::new(0,15,0,0);$notice.Text='این اطلاعات خصوصی‌اند. در MicroSIP یا برنامه SIP گوشی، شماره داخلی را در نام کاربری و شناسه ورود وارد کنید. رمزها را در گزارش یا تصویر ارسالی منتشر نکنید.'
 foreach($item in @($server,$grid,$notice)){[void]$layout.Controls.Add($item)};[void]$window.Controls.Add($layout)
 try{[void]$window.ShowDialog($Owner)}finally{$window.Dispose()}
}

Export-ModuleMember -Function Show-TelephonyLocalSettings,Show-TelephonyLocalAccounts
