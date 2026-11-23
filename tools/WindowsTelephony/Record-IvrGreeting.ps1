#requires -Version 5.1
param()
$ErrorActionPreference='Stop'
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
Add-Type -Path (Join-Path $PSScriptRoot 'PromptRecorder.cs')
$form=New-Object Windows.Forms.Form
$form.Text='ضبط پیام راهنمای تلفن'
$form.Size=New-Object Drawing.Size(650,420)
$form.StartPosition='CenterScreen';$form.RightToLeft='Yes';$form.RightToLeftLayout=$true
$form.Font=New-Object Drawing.Font('Segoe UI',10)
$form.AutoScroll=$true;$form.MaximumSize=[Windows.Forms.Screen]::PrimaryScreen.WorkingArea.Size
$label=New-Object Windows.Forms.Label
$label.Text="روی کامپیوتری اجرا کنید که میکروفن فعال دارد. ضبط فقط با دکمه شروع انجام می‌شود؛ حداکثر ۳۰ ثانیه.`r`n`r`nمتن پیشنهادی: سلام، برای ارتباط با داخلی هزار و یک کلید یک و برای داخلی هزار و دو کلید دو را فشار دهید. در صورت عدم انتخاب، به داخلی هزار و دو متصل خواهید شد."
$label.SetBounds(20,20,590,160)
$status=New-Object Windows.Forms.Label;$status.SetBounds(20,270,590,70);$status.Text='آماده ضبط؛ میکروفن را وصل و فعال کنید.'
$record=New-Object Windows.Forms.Button;$record.Text='شروع ضبط';$record.SetBounds(420,205,170,45)
$stop=New-Object Windows.Forms.Button;$stop.Text='پایان و ذخیره';$stop.SetBounds(220,205,170,45);$stop.Enabled=$false
$play=New-Object Windows.Forms.Button;$play.Text='شنیدن پیام';$play.SetBounds(20,205,170,45);$play.Enabled=$false
$context=@{recorder=$null;path=$null;started=$null;player=$null}
$timer=New-Object Windows.Forms.Timer;$timer.Interval=250
$finish={
 try {
  $timer.Stop()
  $path=Join-Path $PSScriptRoot ('greeting-'+[guid]::NewGuid().ToString('N')+'.wav')
  $context.recorder.Save($path)
  $context.recorder.Dispose();$context.recorder=$null
  if((Get-Item -LiteralPath $path).Length -le 44){throw 'No audio captured'}
  $context.path=$path;$play.Enabled=$true;$status.Text='پیام ذخیره شد. آن را گوش دهید؛ سپس همین فایل WAV را در فعال‌سازی راهنما انتخاب کنید: '+[IO.Path]::GetFileName($path)
 }catch{$status.Text='ضبط ذخیره نشد؛ میکروفن و دسترسی ضبط ویندوز را بررسی کنید. '+$_.Exception.Message}
 finally{if($context.recorder){try{$context.recorder.Dispose();$context.recorder=$null}catch{}};$record.Enabled=$true;$stop.Enabled=$false}
}
$record.Add_Click({
 try{
  if($context.player){$context.player.Stop()}
  $context.recorder=New-Object OfficePromptRecorder;$context.recorder.Start()
  $context.started=[datetime]::UtcNow;$record.Enabled=$false;$stop.Enabled=$true;$play.Enabled=$false
  $status.Text='در حال ضبط؛ متن را بخوانید.';$timer.Start()
 }catch{if($context.recorder){try{$context.recorder.Dispose();$context.recorder=$null}catch{}};$status.Text='شروع ضبط ممکن نشد؛ میکروفن و مجوز ضبط را بررسی کنید. '+$_.Exception.Message}
})
$stop.Add_Click($finish)
$timer.Add_Tick({if(([datetime]::UtcNow-$context.started).TotalSeconds -ge 30){& $finish}})
$play.Add_Click({try{if($context.player){$context.player.Dispose()};$context.player=New-Object Media.SoundPlayer($context.path);$context.player.Play()}catch{$status.Text='پخش فایل ممکن نشد: '+$_.Exception.Message}})
$form.Add_FormClosing({param($sender,$eventArgs)
 if($context.recorder -and [Windows.Forms.MessageBox]::Show('ضبط ذخیره‌نشده کنار گذاشته شود؟','بستن ضبط','YesNo') -ne 'Yes'){$eventArgs.Cancel=$true}
})
foreach($control in @($label,$status,$record,$stop,$play)){[void]$form.Controls.Add($control)}
try{[void]$form.ShowDialog()}finally{$timer.Stop();$timer.Dispose();if($context.recorder){$context.recorder.Dispose()};if($context.player){$context.player.Dispose()};$form.Dispose()}
