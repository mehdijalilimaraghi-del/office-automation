#requires -Version 5.1
function Show-ModemVoiceTest {
    param([string]$Port, [string]$DeviceName)
    if ($Port -notmatch '^COM[1-9][0-9]{0,4}$') { throw 'درگاه مودم معتبر نیست.' }
    Add-Type -AssemblyName System.Windows.Forms
    Add-Type -AssemblyName System.Drawing
    if (-not ('OfficeAutomation.Telephony.Diagnostics.VoiceSession' -as [type])) {
        Add-Type -Path (Join-Path $PSScriptRoot 'VoiceDiagnostics.cs')
    }
    $state = @{ session=$null; wantClose=$false; result=$null; recording=$null; player=$null; report=$null; autoReport=$null }
    $challenge = [string](Get-Random -Minimum 100 -Maximum 1000)
    $window = New-Object Windows.Forms.Form
    $window.Text='آزمون عملی صدای مودم — نسخه ۳'
    $window.Size=New-Object Drawing.Size(820,700)
    $window.MinimumSize=New-Object Drawing.Size(700,620)
    $window.StartPosition='CenterParent'
    $window.RightToLeft='Yes'; $window.RightToLeftLayout=$true
    $window.Font=New-Object Drawing.Font('Segoe UI',10)
    $layout=New-Object Windows.Forms.TableLayoutPanel
    $layout.Dock='Fill'; $layout.Padding=New-Object Windows.Forms.Padding(18)
    $layout.ColumnCount=1; $layout.RowCount=7
    foreach($height in @(130,65,50,0,45,70,55)){
        $sizeType=if($height -eq 0){[Windows.Forms.SizeType]::Percent}else{[Windows.Forms.SizeType]::Absolute}
        $heightValue=if($height -eq 0){100}else{$height}
        [void]$layout.RowStyles.Add((New-Object Windows.Forms.RowStyle($sizeType,$heightValue)))
    }
    $instructions=New-Object Windows.Forms.Label
    $instructions.Dock='Fill'
    $instructions.Text="مودم: $DeviceName ($Port)"+[Environment]::NewLine+'۱. خط را وصل کنید و VentaFax و سایر برنامه‌های فکس و دایال‌آپ را کامل ببندید.'+[Environment]::NewLine+'۲. آزمون را آماده کنید؛ پس از پیام انتظار، با موبایل به شماره خط زنگ بزنید.'+[Environment]::NewLine+"۳. در موبایل باید بوق بشنوید. سپس عدد $challenge را واضح بگویید و بعد از حدود ۸ ثانیه تماس موبایل را قطع کنید."
    $consent=New-Object Windows.Forms.CheckBox
    $consent.Dock='Fill'
    $consent.Text='خط برای آزمون آزاد است و اجازه پاسخ‌گویی و ضبط همین تماس آزمایشی را می‌دهم. انتظار حداکثر ۶۰ ثانیه و ضبط حداکثر ۱۲ ثانیه است.'
    $actions=New-Object Windows.Forms.FlowLayoutPanel
    $actions.Dock='Fill'
    $start=New-Object Windows.Forms.Button
    $start.Text='آماده‌سازی آزمون'; $start.AutoSize=$true; $start.Enabled=$false
    $cancel=New-Object Windows.Forms.Button
    $cancel.Text='توقف و آزادکردن خط'; $cancel.AutoSize=$true; $cancel.Enabled=$false
    [void]$actions.Controls.Add($start); [void]$actions.Controls.Add($cancel)
    $progress=New-Object Windows.Forms.TextBox
    $progress.Dock='Fill'; $progress.Multiline=$true; $progress.ReadOnly=$true; $progress.ScrollBars='Vertical'
    $progress.Text='آزمون فقط با تأیید شما آغاز می‌شود. شماره‌گیری خروجی انجام نمی‌شود.'
    $play=New-Object Windows.Forms.Button
    $play.Text='شنیدن صدای دریافت‌شده'; $play.AutoSize=$true; $play.Enabled=$false
    $checks=New-Object Windows.Forms.FlowLayoutPanel
    $checks.Dock='Fill'; $checks.FlowDirection='TopDown'; $checks.WrapContents=$false
    $heardTone=New-Object Windows.Forms.CheckBox
    $heardTone.Text='بوق‌های آزمایشی را در موبایل واضح شنیدم.'; $heardTone.AutoSize=$true; $heardTone.Enabled=$false
    $heardSpeech=New-Object Windows.Forms.CheckBox
    $heardSpeech.Text="در فایل ضبط‌شده، گفتن عدد $challenge را واضح شنیدم."; $heardSpeech.AutoSize=$true; $heardSpeech.Enabled=$false
    [void]$checks.Controls.Add($heardTone); [void]$checks.Controls.Add($heardSpeech)
    $footer=New-Object Windows.Forms.FlowLayoutPanel
    $footer.Dock='Fill'
    $save=New-Object Windows.Forms.Button
    $save.Text='ذخیره نتیجه و فایل صدا'; $save.AutoSize=$true; $save.Enabled=$false
    $close=New-Object Windows.Forms.Button
    $close.Text='بستن'; $close.AutoSize=$true
    [void]$footer.Controls.Add($save); [void]$footer.Controls.Add($close)
    foreach($item in @(@($instructions,0),@($consent,1),@($actions,2),@($progress,3),@($play,4),@($checks,5),@($footer,6))){
        [void]$layout.Controls.Add($item[0],0,$item[1])
    }
    [void]$window.Controls.Add($layout)
    $timer=New-Object Windows.Forms.Timer
    $timer.Interval=100
    $assessment={
        if(-not $state.result){return}
        $r=$state.result
        $r.RemoteToneConfirmed=$heardTone.Checked
        $r.RecordingSpeechConfirmed=$heardSpeech.Checked
        $details="ارسال: $($r.TransmittedSamples) نمونه | دریافت: $($r.ReceivedSamples) نمونه"+[Environment]::NewLine
        if($r.CompatibleForNextStep){
            $details+='آزمون محلی صدای دوطرفه موفق بود. این مودم نامزد اتصال آزمایشی به مرکز تلفن است؛ کیفیت تماس شبکه هنوز آزموده نشده است.'
        } elseif($r.Status -eq 'captured'){
            $details+='آزمون هنوز موفق اعلام نشده است. فایل ضبط‌شده را گوش کنید و فقط در صورت شنیدن صحیح صدا در هر دو طرف، دو گزینه تأیید را علامت بزنید.'
        } elseif($r.Cancelled){
            $details+='آزمون به درخواست شما متوقف شد.'
        } elseif($r.ErrorCode -eq 'incoming_call_timeout'){
            $details+='در زمان انتظار، زنگ ورودی شناسایی نشد. سیم‌کشی خط و شماره تماس را بررسی و آزمون تازه ایجاد کنید.'
        } elseif($r.ErrorCode -eq 'cannot_preserve_original_format' -or $r.ErrorCode -eq 'cannot_preserve_flow_control'){
            $details+='پاسخ تنظیمات فعلی مودم قابل تشخیص نبود؛ هنوز به تماسی پاسخ داده نشده است. پاسخ خام در گزارش ثبت شده است. گزارش را برای بررسی ذخیره کنید.'
        } elseif($r.ErrorCode -like 'command_rejected:*' -or $r.ErrorCode -eq 'no_supported_8khz_mulaw'){
            $details+='مودم یا درایور، مسیر صوتی این واسط را نپذیرفت. این نتیجه تأیید ناسازگاری با همه روش‌ها نیست؛ علت در گزارش فنی ذخیره می‌شود.'
        } else{
            $details+='آزمون کامل نشد. نتیجه و مرحله خطا را ذخیره کنید تا علت بررسی شود.'
        }
        $details+=[Environment]::NewLine
        $details+=if($r.CleanupConfirmed){'پایان آزمون و بازگرداندن حالت مودم تأیید شد.'}else{'آزادشدن خط تأیید نشد؛ وضعیت خط را با گوشی بررسی کنید.'}
        $details+=[Environment]::NewLine
        $details+=if(-not $r.Answered){'آزمون تماس آغاز نشده است.'}elseif($r.Events -contains 'busy_tone'){'علامت بوق اشغال ثبت شد؛ ممکن است نشانه قطع طرف مقابل باشد.'}elseif($r.Events -contains 'stream_end'){'پایان جریان صوت از مودم ثبت شد؛ علت قطع از این علامت به‌تنهایی مشخص نیست.'}else{'علامت مشخصی برای قطع طرف مقابل ثبت نشد؛ این بخش تأیید نشده است.'}
        if($r.FailureStage){
            $stageName=switch($r.FailureStage){
                'open_port' {'بازکردن درگاه'}
                'read_original_class' {'خواندن حالت قبلی مودم'}
                'enter_voice_class' {'آماده‌سازی حالت صوتی'}
                'read_supported_formats' {'خواندن قالب‌های صوتی'}
                'read_current_format' {'خواندن تنظیم فعلی صدا'}
                'read_flow_control' {'خواندن تنظیم انتقال داده'}
                'configure_audio' {'تنظیم صدا'}
                'wait_for_incoming_call' {'انتظار تماس'}
                'answer_incoming_call' {'پاسخ‌گویی'}
                'start_duplex' {'شروع صدای هم‌زمان'}
                'stream_audio' {'ارسال و دریافت صدا'}
                'save_recording' {'ذخیره صدا'}
                'cleanup' {'آزادکردن خط و بازگرداندن تنظیمات'}
                default {'بررسی مودم'}
            }
            $details+=[Environment]::NewLine+"مرحله توقف: $stageName"
        }
        if($state.report -and $state.autoReport){
            try{
                [void][IO.Directory]::CreateDirectory((Split-Path -Parent $state.autoReport))
                [IO.File]::WriteAllText($state.autoReport,($state.report|ConvertTo-Json -Depth 8),[Text.UTF8Encoding]::new($false))
                $details+=[Environment]::NewLine+'گزارش فنی خودکار نگه‌داری شد. برای ارسال، «ذخیره نتیجه و فایل صدا» را بزنید.'
            }catch{
                $details+=[Environment]::NewLine+'ذخیره خودکار گزارش ممکن نشد. گزارش را با دکمه ذخیره در پوشه دیگری نگه دارید.'
            }
        }
        $progress.Text=$details
    }
    $heardTone.Add_CheckedChanged($assessment)
    $heardSpeech.Add_CheckedChanged($assessment)
    $consent.Add_CheckedChanged({$start.Enabled=$consent.Checked -and -not $state.session})
    $start.Add_Click({
        if(-not $consent.Checked -or $state.session){return}
        try{
            $folder=Join-Path $env:LOCALAPPDATA 'OfficeAutomation\Telephony\AudioTests'
            $state.autoReport=Join-Path $folder ('modem-audio-'+[Guid]::NewGuid().ToString('N')+'.json')
            $state.session=New-Object OfficeAutomation.Telephony.Diagnostics.VoiceSession($Port,$folder)
            $start.Enabled=$false; $consent.Enabled=$false; $cancel.Enabled=$true
            $state.session.Start(); $timer.Start()
        }catch{
            $progress.Text='آماده‌سازی آزمون ممکن نشد. نصب فایل‌های واسط و درگاه مودم را بررسی کنید.'
            $state.session=$null; $start.Enabled=$consent.Checked; $consent.Enabled=$true
        }
    })
    $cancel.Add_Click({if($state.session -and -not $state.session.Completed){$state.session.Cancel();$cancel.Enabled=$false;$progress.Text='در حال توقف و آزادکردن خط…'}})
    $timer.Add_Tick({
        if(-not $state.session){return}
        if($state.session.Completed){
            $timer.Stop()
            $state.result=$state.session.Result
            $state.report=@{report_type='modem_audio_test';schema_version=2;device_name=$DeviceName;port=$Port;challenge=$challenge;tested_at=[DateTimeOffset]::Now.ToString('o');result=$state.result}
            $state.recording=$state.session.RecordingPath
            $cancel.Enabled=$false
            $save.Enabled=$true
            $play.Enabled=$state.recording -and (Test-Path -LiteralPath $state.recording)
            $heardTone.Enabled=$state.result.DuplexStarted
            $heardSpeech.Enabled=$play.Enabled
            & $assessment
            if($state.wantClose){$window.Close()}
            return
        }
        $progress.Text=switch($state.session.Progress){
            'preparing' {'در حال بررسی و آماده‌سازی مودم؛ هنوز به تماس پاسخ داده نشده است.'}
            'waiting_ring' {"آماده دریافت تماس هستیم. اکنون از موبایل با شماره خط تماس بگیرید. پس از بوق‌ها، عدد $challenge را بگویید."}
            'answering' {'زنگ ورودی شناسایی شد؛ در حال پاسخ‌گویی به تماس آزمایشی…'}
            'streaming' {"ارسال و دریافت هم‌زمان صدا در حال آزمون است. در موبایل پس از بوق‌ها عدد $challenge را بگویید و بعد از حدود ۸ ثانیه تماس را قطع کنید."}
            'hanging_up' {'در حال پایان آزمون و بازگرداندن حالت مودم…'}
            default {'در حال آزمون…'}
        }
    })
    $play.Add_Click({
        try{
            if($state.player){$state.player.Stop();$state.player.Dispose()}
            $state.player=New-Object Media.SoundPlayer($state.recording)
            $state.player.Play()
        }catch{[Windows.Forms.MessageBox]::Show('پخش فایل ضبط‌شده ممکن نشد.','آزمون صدا')|Out-Null}
    })
    $save.Add_Click({
        $dialog=New-Object Windows.Forms.SaveFileDialog
        $dialog.Filter='JSON report (*.json)|*.json';$dialog.FileName='modem-audio-result.json'
        if($dialog.ShowDialog() -eq 'OK'){
            try{
                [IO.File]::WriteAllText($dialog.FileName,($state.report|ConvertTo-Json -Depth 8),[Text.UTF8Encoding]::new($false))
                if($state.recording){
                    $target=Join-Path (Split-Path -Parent $dialog.FileName) $state.result.RecordingFile
                    if([IO.Path]::GetFullPath($state.recording) -ne [IO.Path]::GetFullPath($target)){Copy-Item -LiteralPath $state.recording -Destination $target}
                }
                [Windows.Forms.MessageBox]::Show('نتیجه آزمون و فایل صدای موجود ذخیره شدند.','آزمون صدا')|Out-Null
            }catch{[Windows.Forms.MessageBox]::Show('ذخیره کامل نتیجه ممکن نشد. پوشه انتخاب‌شده را بررسی کنید.','آزمون صدا')|Out-Null}
        }
        $dialog.Dispose()
    })
    $close.Add_Click({$window.Close()})
    $window.Add_FormClosing({
        param($sender,$eventArgs)
        if($state.session -and -not $state.session.Completed){
            $eventArgs.Cancel=$true;$state.wantClose=$true;$state.session.Cancel()
            $cancel.Enabled=$false;$progress.Text='پیش از بستن، منتظر توقف و آزادکردن خط بمانید…'
        }
    })
    try{[void]$window.ShowDialog()}finally{
        $timer.Stop();$timer.Dispose()
        if($state.player){$state.player.Stop();$state.player.Dispose()}
        $window.Dispose()
    }
    return $state.result
}
