<?php
/*
 * define location of commands for Linux and FreeBSD
 */

if( file_exists('/usr/local/bin/gawk') )
{
  //FreeBSD
  $CMD['sudo'] = '/usr/local/bin/sudo';
  $CMD['git'] = '/usr/local/bin/git';

}else{
  $CMD['sudo'] = '/usr/bin/sudo';
  $CMD['git'] = '/usr/bin/git';

}

?>