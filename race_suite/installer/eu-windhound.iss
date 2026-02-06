[Setup]
AppName=EU Windhound Race Suite
AppVersion=1.1.0
DefaultDirName={autopf}\EU Windhound Race Suite
DefaultGroupName=EU Windhound Race Suite
OutputDir=.
OutputBaseFilename=Setup
Compression=lzma
SolidCompression=yes
WizardStyle=modern

[Files]
Source: "..\*"; DestDir: "{app}"; Flags: recursesubdirs createallsubdirs ignoreversion

[Icons]
Name: "{commondesktop}\EU Windhound Race Suite"; Filename: "{app}\installer\start_server.bat"
Name: "{group}\EU Windhound Race Suite"; Filename: "{app}\installer\start_server.bat"
Name: "{group}\EU Windhound Race Suite stoppen"; Filename: "{app}\installer\stop_server.bat"

[Run]
Filename: "{app}\installer\start_server.bat"; Description: "EU Windhound Race Suite starten"; Flags: postinstall shellexec
