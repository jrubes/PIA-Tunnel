#!/bin/sh

case "$1" in
start)
	/usr/local/pia/include/autostart.sh &
        echo "pia-tunnel aztostart"
        exit 0
        ;;
stop)
        echo "nothing to stop - this handles autostart only"
	;;
*)
        echo "Usage: `basename $0` {start}" >&2
        exit 64
        ;;
esac
