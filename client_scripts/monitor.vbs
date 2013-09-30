
Dim tmp, oShell, oFSO,file, appdata, oHTTP, http_return, torrent_client, current_port
Dim outer_loop, outer_protect, outer_sleep, pattern, PWD
Set oShell = WScript.CreateObject("WScript.Shell")
Set oFSO  = CreateObject("Scripting.FileSystemObject")
Set oHTTP = CreateObject("MSXML2.XMLHTTP")

torrent_process="qbittorrent.exe"
torrent_client="C:\Program Files (x86)\qBittorrent\qbittorrent.exe"
current_port = 0
outer_loop=true
outer_protect=0 'debug value
outer_sleep=5000 'recheck for port change every X ms
PWD = Left(WScript.ScriptFullName,(Len(WScript.ScriptFullName) - (Len(WScript.ScriptName) + 1))) 'working dir without trailing \

'load replacement pattern
tmp = file_get_text( PWD & "\deluge.pattern")
pattern = split(tmp, vbcrlf)

Dim cont 
cont = file_get_text("C:\Users\dev\AppData\Roaming\deluge\core.conf")
Set reg = New RegExp
reg.IgnoreCase = True
reg.Global = false 'only one match
reg.Pattern = pattern(0)
msgbox(reg.test(cont))

dim poo
tmp = replace(pattern(1), "PIAOPENPORT", "45678")
poo=reg.replace(cont, tmp)
poo=file_write_text("T:\development-home\public_g\PIA Tunnel\client_scripts\out.txt", poo)
wscript.quit


cont = file_get_text("C:\Users\dev\AppData\Roaming\deluge\core.conf")
Set reg = New RegExp
reg.IgnoreCase = True
reg.Global = false 'only one match
'reg.Pattern = "PortRangeMin=[0-9]{1,5}" 'qBittorrent
'reg.Pattern = ":bind_porti([0-9]{1,5})e7:" 'uTorrent
'reg.Pattern = """listen_ports"":([\s])\[([\s])([0-9]{1,5}),([\s])([0-9]{1,5})([\s])\]" 'Deluge
reg.Pattern = """listen_ports"":([\s]*)\[([\s]*)([0-9]{1,5}),([\s]*)([0-9]{1,5})([\s]*)\]" 'Deluge


msgbox(reg.test(cont))
'msgbox((cont))
wscript.quit

do while outer_loop=true
	'get current port number
	oHTTP.open "GET", "http://192.168.1.105/get_status.php?type=value&value=vpn_port", False
	oHTTP.send
	http_return = oHTTP.responseText
	'msgbox http_return
	if NOT http_return = "" AND IsNumeric(http_return) = true then
		' something returned
		if NOT http_return = current_port then
			msgbox("updating with " & http_return)
			current_port = http_return
			appdata=oShell.ExpandEnvironmentStrings( "%APPData%" )

			
Set reg = New RegExp
reg.IgnoreCase = True
reg.Global = True
reg.Pattern = "^PortRangeMin$"

msgbox(reg.test(":bind_porti46058e7:"))
wscript.quit
			
			foo=update_one_per_line( appdata & "\qBittorrent\qBittorrent.ini", "PortRangeMin=", http_return)
			'foo=update_in_between( appdata & "\qBittorrent\qBittorrent.ini", ":bind_porti", "e7", http_return)
			
			' Connection\PortRangeMin=46058
			' PortRangeMin=$VALUE
			
			' :bind_porti46058e7:
			'  bind_porti$VALUE$TAIL
			'  $TAIL=e7:
			
			' "listen_ports": [
			'			6881, 
			'			6881
			'	], 
			
			
			'resart the application
			foo=restart_process(torrent_process)
		end if
	else
		'not connected or not yet
		msgbox("Debug: not connected")
	end if

	if outer_protect > 100 then ' wscript.sleep times this value is the "timeout"
		outer_loop=false
	else
		outer_sleep = outer_sleep + 1
	end if
	wscript.sleep(outer_sleep)
loop

msgbox("loop done")
wscript.quit

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
			oShell.Exec "PSKill " & objProcess.ProcessId
			count = count + 1
		Next		
		wscript.sleep(1000) 'give process a little while to exit
		
		if count = 0 then
			'no more process left, exit
			run = false
		else
			if protect > 120 then ' wscript.sleep times this value is the "timeout"
				msgbox("unable to end process. please restart manually")
				run=false
			end if
			protect = protect + 1
			wscript.sleep(1000)
		end if
	loop

	'start it again
	'start the process
	cmd = "cmd /c """ & torrent_client &""""
	oShell.run cmd,6
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
						'msgbox( "Unkown error" & Err.Number &vbcrlf & "Source: " & Err.Source & "Desc: "&Err.Description)
						'wscript.quit
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