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
        $disp_header .= '<link rel="icon" type="image/ico" href="'.$val."\">\n";
      }
      break;
    case 'stylesheet':
      if( $val != '' ){
        $disp_header .= '<link rel="stylesheet" type="text/css" href="'.$val."\">\n";
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
          $disp_header .= '<script src="'.$val.'" type="text/javascript"></script>'."\n";
        }
      }
      break;
  }
}

$disp_header .= '<script type="text/javascript">
    /* hides / unhides an element */
/*
    ele_id = element where class="hidden"
    event_ele_id = "" OPTIONAL - id of the element causing the toggle operation
    switch_value = "" OPTIONAL - comma separated labels for toggle_ele. if innerHTML matches one then the other string is put in place

*/
function toggle_hide( ele_id , event_ele_id, switch_value ){
    var ele = document.getElementById( ele_id );
    var event_ele;
    var event_lbls;

    if( event_ele_id && event_ele_id != "" ){ event_ele = document.getElementById( event_ele_id ); }
    if( switch_value && switch_value != "" ){ event_lbls = switch_value.split(","); }

    if( ele ){
        if( ele.getAttribute("class") == "hidden" ){
            ele.removeAttribute("class");
        }else{
            ele.setAttribute("class", "hidden");
        }

        if( event_ele && event_lbls.length == 2 ){
            if( event_ele.innerHTML == event_lbls[0] ){
                event_ele.innerHTML = event_lbls[1];
            }else{
                event_ele.innerHTML = event_lbls[0];
            }
        }
    }
};

/* quick hack ... wil only unhide an element */
function unhide( ele_id ){
    var ele = document.getElementById( ele_id );

    if( ele ){
        if( ele.getAttribute("class") == "hidden" ){
            ele.removeAttribute("class");
        }
    }
};';
$disp_header .= '</script>';


$disp_header .= "</head>\n";

?>
