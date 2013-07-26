This ReadMe assumes that you know your way around VMware and 
how to setup a private LAN segment on NIC 2


Once setup you should boot the VM, login (root:pia) and ensure that you are online. Ping something!
The rest ist really simple...

1) Run pia-update
   This command will pull the latest scripts and will start the creation process of your IP cache.
   The IP cache takes 3-5 minutes to build and is REQUIRED so please give the script 
   a few minutes to complete.
   
2) Edit /pia/login.conf Enter your PIA username on line 1 and your password on line 2

3) You are ready to Rock'n Roll!
   Use pia-start to create your first VPN connection but please keep in mind that pia-start is
   currently cAsE sEnSiTiVe so pia-start "UK London" and pia-start "UK london" are not the same.
   You must also enclose names containing spaces with quotes "UK London" or "US West"
    pia-start Germany
    pia-start "US West"
    
4) If the above step works then you may use pia-stop to terminate the VPN network connection.
   pia-stop will also reset your network so you may run it to fix things if you network is up
   but the VM can't get out.
   
5) You may now use pia-daemon
   pia-daemon is a looping shell script which ensures that your VPN is up and stays up. You may
   start pia-daemon instead of pia-start but it takes a bit longer until the initial connection
   is established and the VPN will be terminated when you close pia-daemon (CTRL+C).
   P.S. run pia-stop if you CTRL+C out of the pia-daemon to get the Internet working again. 
   Restarting pia-daemon will also resolv any/most network issues.
