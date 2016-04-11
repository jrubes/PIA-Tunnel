<?php

/*
 * update notes are stored in the DB on my server. this ensures the repo always contains the
 * latest info as well.
 *
 * http://www.kaisersoft.net/pia_latest.xml
 * and
 * http://www.kaisersoft.net/pia_latest_changes.md
 *
 */


  $content = file_get_contents('http://www.kaisersoft.net/pia_latest.xml');
  file_put_contents('../pia_latest.xml', $content);

  $content = file_get_contents('http://www.kaisersoft.net/pia_latest_changes.md');
  file_put_contents('../pia_latest_changes.md', $content);
?>