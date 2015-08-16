* /etc/ssh/sshd_config
# PermitRootLogin without-password
PermitRootLogin yes

* on FreeBSD make sure pkg is installed. run pkg update

* ansible requires python 2.6 or 2.7
	* freebsd: pkg install python
   	      ln -s /usr/local/bin/python /usr/bin/python


* For Virtual Box
	ansible-playbook -i hosts PIA-Tunnel.yml VBox.yml

* For VMware
	ansible-playbook -i hosts PIA-Tunnel.yml VMware.yml
