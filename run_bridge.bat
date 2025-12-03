@echo off
echo QRGate ThingSpeak Bridge Starting...
echo =====================================
echo.
:loop
C:\xampp\php\php.exe C:\xampp\htdocs\qrgate\thingspeak_bridge.php
echo.
echo Waiting 10 seconds before next check...
timeout /t 10 /nobreak > nul
goto loop
