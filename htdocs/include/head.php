<?php

$disp_header = '';

$expires = date('D, d M Y H:i:s T', strtotime("+30 days", strtotime('now'))); //Wed, 26 Feb 1997 08:21:57 GMT
$disp_header .= "<!DOCTYPE html>\n";
$disp_header .= "<html>\n";
$disp_header .= "<head>\n";

/* priocess meta[] array into valid html <meta> tags */
reset($meta);
foreach( $meta as $key => $val ){
  switch($key){
    case 'charset':
      if( $val != '' ){
        $disp_header .= "<meta $key=\"$val\">\n";
      }else{
        $disp_header .= '<meta charset="UTF-8">';
      }
      break;
    case 'icon':
      if( $val != '' ){
        $disp_header .= '<link rel="icon" type="image/ico" href="'.urlencode($val)."\" />\n";
      }
      break;
    case 'stylesheet':
      if( $val != '' ){
        $disp_header .= '<link rel="stylesheet" type="text/css" href="'.urlencode($val)."\" />\n";
      }
      break;
    case 'title':
      $disp_header .= "<title>".htmlspecialchars($val)."</title>\n";
      break;
    case 'name':
      //meta names is mutidimensional array
      reset($val);
      foreach( $val as $nk => $nv ){
        $disp_header .= '<meta name="'.$nk.'" content="'.htmlspecialchars($nv)."\">\n";
      }
      break;
    case 'javascript':
      foreach( $meta[$key] as $val ){
        if( $val != '' ){
          $disp_header .= '<script src="'.urlencode($val).'" type="text/javascript"></script>'."\n";
        }
      }
      break;
  }
}

$disp_header .= "</head>\n";

?>
