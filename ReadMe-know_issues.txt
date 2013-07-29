# This text file will list some of the known issues with the pia-* scripts
# I will use it as a reminder of things that need to be fixed or implemented in a better way


* calling it PIA Tunnel was a bit short sigted. this VM will work with any server that supports openvpn


* pia-daemon was not planned and it shows.
  the next version needs to support different states and respond to the current sate accordingly ... or needs 1 GOTO :)
  
  
* pia-start is redundant, mod pia-daemon to handle the inital connection better


* the ping function only accepts "0% packet loss" as proof that a connection is good.
  some grey areay needs to be implemented.
  
  
* pia-start is case sensitive and the menu displayed by "pia-start list" is not pretty


* pia-stop is a quick hack at best. the network interfaces should not be restarted for fun.

