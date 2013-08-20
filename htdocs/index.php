<?php
/*
 * basic web framework
 */
$inc_dir = './include/';
require_once $inc_dir.'basic.php';


// load body first because I get the title and meta stuff from the article which is loaded in body
require_once $inc_dir.'body.php';

// now the rest
require_once $inc_dir.'head.php';
require_once $inc_dir.'footer.php';


/* deliver the finished page */
echo "<html>\n"
      ."<head>\n"
      .$disp_header."\n"
    ."</head>\n"
    ."<body>\n"
      .$disp_body."\n"
      .$disp_footer."\n"
    ."</body>\n"
    ."</html>";
?>