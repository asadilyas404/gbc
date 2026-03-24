Option Explicit
Dim shell
Set shell = CreateObject("WScript.Shell")
shell.Run "cmd /c ""C:\xampp\htdocs\restaurant\run-schedule.cmd""", 0, False
Set shell = Nothing
