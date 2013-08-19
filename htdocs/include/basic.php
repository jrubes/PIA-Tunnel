<?php
/* basic include file used for all scripts */

date_default_timezone_set('Europe/Berlin');
define('APC_CACHE_PREFIX', 'piatunnel_');
if( session_id () == '' ) session_start ();
require_once $inc_dir.'class_loader.php';
require_once $inc_dir.'classes/class_files/class_files.php';

/* prepare global objects */
$_files = loader::loadFiles();

$header_type = 'foo'; //Change this later to add more headers
$body_type = 'foo'; //Use to select different code later
$footer_type = 'foo';
$disp_header = '';
$disp_body = '';
$disp_footer = '';
$language = 'eng';

$meta['title'] = 'PIA-Tunnel Management Interface'; //default prefix
$meta['name']['author'] = 'Mirko Kaiser';
$meta['name']['keywords'] = '';
$meta['name']['description'] = '';
$meta['name']['robots'] = 'INDEX,FOLLOW';
$meta['name']['copyright'] = 'Mirko Kaiser';
$meta['charset'] = 'UTF-8';
$meta['icon'] = ''; //'/favicon.ico';
$meta['stylesheet'] = '/style.css'; // '/css/twoColElsLtHdr.css';
$meta['javascript'] = '';

$CONF = array();
$CONF['date_format'] = 'H:i:s'; //PHP date() format


/**
 * function to generate the main menu
 * @return string the main menu in HTML string
 */
function load_menu(){
  
  /* define the main menu below 
   * this tends to come out of a db and go into a cache but this makes no sense here
   */
  $source = array(
      /* 'URL Name', 'URL target', 'URL ID' */
      array( 'name' => 'Overview', 'url' => '/?page=main', 'id' => ''),
      array( 'name' => 'VPN Config', 'url' => '/?page=config&amp;cmd=vpn', 'id' => 'cvpn'),
      array( 'name' => 'Network Config', 'url' => '/?page=config&amp;cmd=network', 'id' => 'cnet'),
      array( 'name' => 'Tools', 'url' => '/?page=tools', 'id' => 'tools'),
      array( 'name' => 'Logout', 'url' => '/?page=logout', 'id' => 'logout')
  );

  $selected = $_GET['cid'];
  $menu = "<div class=\"mainmenu\">\n";
  
  /* assemble the main menu */
  foreach( $source as $menu_entry )
  {
      /* $menu_entry['id'] must be added with ? or & - figure out which one */
      $highlight_id = ( strstr($menu_entry['url'], '?') === false ) ? "?cid=$menu_entry[id]" : "&amp;cid=$menu_entry[id]";
      
      $menu .= '<span';
      if( $selected == $menu_entry['id'] ){ $menu .= ' id="highlight"> '; }else{ $menu .= '>'; }
      $menu .= '<a href="'.$menu_entry['url'].$highlight_id.'">'.htmlentities($menu_entry['name']).'</a>';
      $menu .= "</span>\n";
  }
  
  $menu .= "</div>\n";
  $menu .= '<div style="clear:both;height:0;line-height:0;display:block;"></div>'."\n";
  return $menu;
}

?>