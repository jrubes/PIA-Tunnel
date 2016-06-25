You may place your own openVPN connection files here. Files placed here will not be removed during an update.

1) 	Create a new directory. If the same directory already exists in the "ovpn" 
	directory then only the one	inside the "ovpn.d" direcory are loaded. 
	The name of the .ovpn file will be used as the VPN connection name.
	
2) 	Specify a file where the webUI can store your authentication data. The file must be 
	located in '/usr/local/pia/' and called 'login-SOMESTRING.conf' to be removed when
	'/usr/local/pia/reset-pia' is executed.
	
3) 	Also specify the location of the .crt and .pem file if it is not included within your
	.ovpn file.
	Example:
	ca /usr/local/pia/ovpn/PIAtcp/ca.crt
	crl-verify /usr/local/pia/ovpn/PIAtcp/crl.pem
	
4) 	Open the webUI and enable the new VPN connection in "Settings"

5) 	Goto "VPN Accounts" from the main menu and enter your username and password.

6) 	Every is setup at this point

