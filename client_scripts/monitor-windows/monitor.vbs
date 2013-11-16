
Dim tmp, oShell, oFSO,file, oHTTP, http_return, torrent_exe, current_port
Dim outer_loop, outer_sleep, pattern, PWD, config_file, status_ip
Dim torrent_config, torrent_client, development_run, demo_mode, args
Set oShell = WScript.CreateObject("WScript.Shell")
Set oFSO  = CreateObject("Scripting.FileSystemObject")
set args = Wscript.Arguments
'Set oHTTP = CreateObject("MSXML2.XMLHTTP")
Set oHTTP = CreateObject("MSXML2.ServerXMLHTTP")
PWD = Left(WScript.ScriptFullName,(Len(WScript.ScriptFullName) - (Len(WScript.ScriptName) + 1))) 'working dir without trailing \

'must be started with cscript to provide better feedback while running
if NOT args.count = 1 then
	cmd = "cmd /c ""cscript monitor.vbs foo"""
	oShell.run cmd,1
	wscript.quit
end if


config_file = PWD & "\monitor.ini"
torrent_process = GetINIString("MAIN", "PROCESS_NAME", "", config_file)
torrent_exe = GetINIString("MAIN", "EXE_PATH", "", config_file)
torrent_client = GetINIString("MAIN", "SOFTWARE", "", config_file) 'deluge, utorrent, qbittorrent
torrent_config = GetINIString("MAIN", "CONFIG_PATH", "", config_file)
status_ip = GetINIString("MAIN", "STATUS_IP", "", config_file)
current_port = 0
outer_loop=true
outer_sleep=5000 'run check every n milliseconds
demo_mode=0 '0/1 will only log actions but will not terminate or start the torrent client

wscript.echo( Date() & " " & Time() & " -- Software to manage: " & torrent_client)
wscript.echo( Date() & " " & Time() & " -- Process to terminate: " & torrent_process)
wscript.echo( Date() & " " & Time() & " -- Exe Path: " & torrent_exe)
wscript.echo( Date() & " " & Time() & " -- Config Path: " & torrent_config)
wscript.echo( Date() & " " & Time() & " -- Checking every: " & outer_sleep & "ms")
wscript.echo( Date() & " " & Time() & " -- IP of PIA Tunnel: " & status_ip)

'load replacement pattern
tmp = file_get_text( PWD & "\" & torrent_client & ".pattern")
if tmp = false then
	wscript.echo( Date() & " " & Time() & " -- ERROR: Could not find pattern file: " & PWD & "\" & torrent_client & ".pattern")
	wscript.quit
else
	pattern = split(tmp, vbcrlf)
end if

wscript.echo( Date() & " " & Time() & " -- Torrent Monitor is now running.")
wscript.echo("")
wscript.sleep(1000)


do while outer_loop=true
	'get current port number
	http_return=""
	foo=oHTTP.setTimeouts( 10000, 5000, 10000, 10000)
	oHTTP.open "GET", "http://" & status_ip & "/get_status.php?type=value&value=vpn_port", False

	'open http and catch any errors
	On Error Resume Next
	oHTTP.send

	If Err.Number <> 0 Then
		select case Err.Number
			case -2146697211
				wscript.echo( Date() & " " & Time() & " -- ERROR: connecting to PIA Tunnel VM. Is the IP correct and the VM running?")
			case -2147012894
				wscript.echo( Date() & " " & Time() & " -- ERROR: connection to PIA Tunnel VM timed out. Is the IP correct?")
			case -2147012744
				'invalid response from server - ignore
			case -2147012866
				'connection terminated due to error - ignore
			case -2147019873
				' something is not ready ... just ignore it
			case else
				call msgbox( Date() & " " & Time() & " -- UNKOWN ERROR: fetching the latest port data" &vbcrlf _
						& "Please send the error information below to your support contact." &vbcrlf _
						& "Errorcode: " & Err.Number & vbcrlf _
						& "Source: " & Err.Source &vbcrlf _
						& "Description: " & Err.Description)
				wscript.quit
		end select
	else
		http_return = oHTTP.responseText
	end if
	On Error Goto 0	'Resume default error handeling
	
	
	'wscript.echo http_return
	if NOT http_return = "" AND IsNumeric(http_return) = true then
		' something returned
		if NOT http_return = current_port then
			wscript.echo( Date() & " " & Time() & " -- new port value: " & http_return)
			current_port = http_return

			'update the config file
			foo=update_config(http_return)			
			
			'resart the application
			foo=restart_process(torrent_process)
		end if
	elseif http_return = "not supported by location" then
		if NOT http_return = current_port then
			wscript.echo( Date() & " " & Time() & " -- location does not support port forwarding")
			current_port = http_return
			
			'resart the application
			foo=restart_process(torrent_process)
		end if		
	else
		'not connected or not yet
		wscript.echo( Date() & " " & Time() & " -- VPN is not connected")
	end if

	wscript.sleep(outer_sleep)
loop



'end of script






function restart_process( byval process_name )
	dim run, objWMIService, colProcessList, count, protect
	Const strComputer = "."
	Set objWMIService = GetObject("winmgmts:" & "{impersonationLevel=impersonate}!\\" & strComputer & "\root\cimv2")
	run=true
	protect=0 'endless loop protector

	'check if the process is already running and kill it
	do while run=true
		count=0
		Set colProcessList = objWMIService.ExecQuery("SELECT * FROM Win32_Process WHERE Name = '" & process_name & "'")
		For Each objProcess in colProcessList
			if NOt demo_mode = 1 then
				wscript.ech( Date() & " " & Time() & " -- terminating " & process_name)
				oShell.Exec "PSKill " & objProcess.ProcessId
				count = count + 1
			end if
		Next		
		wscript.sleep(1000) 'give process a little while to exit
		
		if count = 0 then
			'no more process left, exit
			run = false
		else
			if protect > 120 then ' wscript.sleep times this value is the "timeout"
				wscript.echo( Date() & " " & Time() & " -- ERROR: unable to end process. please restart manually")
				run=false
			end if
			protect = protect + 1
			wscript.sleep(1000)
		end if
	loop

	'start it again
	'start the process
	wscript.echo( Date() & " " & Time() & " -- starting " & process_name)
	if NOt demo_mode = 1 then
		cmd = "cmd /c """ & torrent_exe &""""
		oShell.run cmd,6
	end if
end function

' read in text file and replace according to foo.pattern
function update_config( byref openport )
	Dim cont, foo, newconf
	cont = file_get_text(torrent_config)
	
	Set reg = New RegExp
	reg.IgnoreCase = True
	reg.Global = false 'only one match
	reg.Pattern = pattern(0)
	'wscript.echo(reg.test(cont))

	tmp = replace(pattern(1), "PIAOPENPORT", openport)
	newconf=reg.replace(cont, tmp)
	del(torrent_config)
	foo=file_write_text( torrent_config, newconf)

end function

' update a setting like this
'		Connection\PortRangeMin=46058
' update_one_per_line( "foo\bar.txt", "PortRangeMin=", "46058")
function update_one_per_line( byref file, byref look4, byref newval)

	'loop over file and look for "look4" on every line
	' repalce value then write back
	if oFSO.FileExists(file) = true then
		Const ForReading = 1
		Dim line,newfile,changed
		changed=false
		newfile="" 'will contain the uptaded file contents
		Set oFile = oFSO.OpenTextFile(file, ForReading)
		Do While oFile.AtEndOfStream = False
			line = oFile.ReadLine
			if instr(line, look4) > 0 then
				'match found - uptade the option
				Dim position, currrent
				position = instr(line,separator)
				current = mid(line, 1, position)
				line=current & newval
				changed=true
			end if
			
			newfile = newfile & vbcrlf & line
		Loop
		oFile.Close
		
		'write content back
		if NOT newfile = "" AND changed = true then
			foo=del(file)
			foo=file_write_text( file, newfile)
		end if
	end if


end function

function file_write_text( byref file, byref content)
	'Writes information to text file, returns true if ok, false when something went wrong
	Dim oFSO
	Set oFSO = CreateObject("Scripting.FileSystemObject")
	
	Const ForAppending = 8
	Const ForWriting = 2
	if oFSO.FileExists(file) then
		Set oFile = oFSO.OpenTextFile(file, ForAppending)
	else
		Set oFile = oFSO.CreateTextFile (file, ForWriting)
	end if
	
	oFile.WriteLine content
	oFile.Close
	file_write_text = true
end function


function del( byref file)
	'Deletes files NOT folders, see del (MS-DOS)
	'Returns true if the file was deleted, false if not
	Dim oFSO
	Set oFSO = CreateObject("Scripting.FileSystemObject")
	
	if oFSO.FileExists(file) = true then
		
		On Error Resume Next
			call oFSO.DeleteFile( file, true)
			If Err.Number <> 0 Then
				select case Err.Number
					case 70 'permission denied
						del = false
						exit function
					case else
						'Unkown error
						del = false
						exit function
						wscript.echo( Date() & " " & Time() & " -- Unkown error" & Err.Number &vbcrlf & "Source: " & Err.Source & "Desc: "&Err.Description)
						wscript.quit
				end select
			End If
		On Error Goto 0	'Resume default error handeling		
				
		'check if the file is really gone
		if oFSO.FileExists(file) = true then
			del = false
		else
			del = true
		end if
	else
		del = true
	end if
	set oFSO = nothing
end function

function file_get_text( byref file)
	'Retrieves file content from text files and returns them, false when the file can not be found
	Dim oFSO,ret
	ret = ""
	Set oFSO = CreateObject("Scripting.FileSystemObject")
	if oFSO.FileExists(file) = true then
		Const ForReading = 1
		Set oFile = oFSO.OpenTextFile(file, ForReading)
		Do While oFile.AtEndOfStream = False
				ret = ret & oFile.ReadLine & vbcrlf
		Loop
		oFile.Close
		file_get_text = ret
		exit function
	end if
	file_get_text = false
end function

function strip_q( byref str )
'This function takes a, in quotes encased, string and removes the quotes.
'It works directly on the variable but will also return the changes.

	'Check if str starts with a quote
	if mid(str, 1, 1) = """" then
		str = mid(str, 2) 'Get String without quotes
	end if
	
	'Check if str ends with a quote
	if mid(str, len(str), 1) = """" then
		str = mid(str, 1, len(str)-1)
	end if

	'return
	strip_q = str
end function



'----------- Schreib und lese Funktionen für ini Datei Block START -----------------
'Work with INI files In VBS (ASP/WSH)
'v1.00
'2003 Antonin Foller, PSTRUH Software, http://www.motobit.com
'Function GetINIString(Section, KeyName, Default, FileName)
'Sub WriteINIString(Section, KeyName, Value, FileName)

Sub WriteINIString(Section, KeyName, Value, FileName)
  Dim INIContents, PosSection, PosEndSection
  
  'Get contents of the INI file As a string
  INIContents = GetFile(FileName)

  'Find section
  PosSection = InStr(1, INIContents, "[" & Section & "]", vbTextCompare)
  If PosSection>0 Then
    'Section exists. Find end of section
    PosEndSection = InStr(PosSection, INIContents, vbCrLf & "[")
    '?Is this last section?
    If PosEndSection = 0 Then PosEndSection = Len(INIContents)+1
    
    'Separate section contents
    Dim OldsContents, NewsContents, Line
    Dim sKeyName, Found
    OldsContents = Mid(INIContents, PosSection, PosEndSection - PosSection)
    OldsContents = split(OldsContents, vbCrLf)

    'Temp variable To find a Key
    sKeyName = LCase(KeyName & "=")

    'Enumerate section lines
    For Each Line In OldsContents
      If LCase(Left(Line, Len(sKeyName))) = sKeyName Then
        Line = KeyName & "=" & Value
        Found = True
      End If
      NewsContents = NewsContents & Line & vbCrLf
    Next

    If isempty(Found) Then
      'key Not found - add it at the end of section
      NewsContents = NewsContents & KeyName & "=" & Value
    Else
      'remove last vbCrLf - the vbCrLf is at PosEndSection
      NewsContents = Left(NewsContents, Len(NewsContents) - 2)
    End If

    'Combine pre-section, new section And post-section data.
    INIContents = Left(INIContents, PosSection-1) & _
      NewsContents & Mid(INIContents, PosEndSection)
  else'if PosSection>0 Then
    'Section Not found. Add section data at the end of file contents.
    If Right(INIContents, 2) <> vbCrLf And Len(INIContents)>0 Then 
      INIContents = INIContents & vbCrLf 
    End If
    INIContents = INIContents & "[" & Section & "]" & vbCrLf & _
      KeyName & "=" & Value
  end if'if PosSection>0 Then
  WriteFile FileName, INIContents
End Sub

Function GetINIString(Section, KeyName, Default, FileName)
  Dim INIContents, PosSection, PosEndSection, sContents, Value, Found
  
  'Get contents of the INI file As a string
  INIContents = GetFile(FileName)

  'Find section
  PosSection = InStr(1, INIContents, "[" & Section & "]", vbTextCompare)
  If PosSection>0 Then
    'Section exists. Find end of section
    PosEndSection = InStr(PosSection, INIContents, vbCrLf & "[")
    '?Is this last section?
    If PosEndSection = 0 Then PosEndSection = Len(INIContents)+1
    
    'Separate section contents
    sContents = Mid(INIContents, PosSection, PosEndSection - PosSection)

    If InStr(1, sContents, vbCrLf & KeyName & "=", vbTextCompare)>0 Then
      Found = True
      'Separate value of a key.
      Value = SeparateField(sContents, vbCrLf & KeyName & "=", vbCrLf)
    End If
  End If
  If isempty(Found) Then Value = Default
  GetINIString = Value
End Function

'Separates one field between sStart And sEnd
Function SeparateField(ByVal sFrom, ByVal sStart, ByVal sEnd)
  Dim PosB: PosB = InStr(1, sFrom, sStart, 1)
  If PosB > 0 Then
    PosB = PosB + Len(sStart)
    Dim PosE: PosE = InStr(PosB, sFrom, sEnd, 1)
    If PosE = 0 Then PosE = InStr(PosB, sFrom, vbCrLf, 1)
    If PosE = 0 Then PosE = Len(sFrom) + 1
    SeparateField = Mid(sFrom, PosB, PosE - PosB)
  End If
End Function


'File functions
Function GetFile(ByVal FileName)
  Dim FS: Set FS = CreateObject("Scripting.FileSystemObject")
  'Go To windows folder If full path Not specified.
  If InStr(FileName, ":\") = 0 And Left (FileName,2)<>"\\" Then 
    FileName = FS.GetSpecialFolder(0) & "\" & FileName
  End If
  On Error Resume Next

  GetFile = FS.OpenTextFile(FileName).ReadAll
End Function

Function WriteFile(ByVal FileName, ByVal Contents)
  
  Dim FS: Set FS = CreateObject("Scripting.FileSystemObject")
  'On Error Resume Next

  'Go To windows folder If full path Not specified.
  If InStr(FileName, ":\") = 0 And Left (FileName,2)<>"\\" Then 
    FileName = FS.GetSpecialFolder(0) & "\" & FileName
  End If

  Dim OutStream: Set OutStream = FS.OpenTextFile(FileName, 2, True)
  OutStream.Write Contents
End Function
'----------- Schreib und lese Funktionen für ini Datei Block Ende -----------------