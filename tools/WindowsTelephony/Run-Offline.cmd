@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -STA -File "%~dp0ModemHelper.ps1" -Offline
