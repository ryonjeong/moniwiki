<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// urlencode action plugin for the MoniWiki
//
// $Id$
// vim:et:ts=2:

function do_urlencode($formatter,$options) {
  $from=$options['from'];
  if (!$from) $from=$options['ie'];
  $to=$options['to'];
  if (!$to) $to=$options['oe'];

  if (function_exists("iconv")) {
    $new=iconv($from,$to,$options['page']);
    if ($new) {
      $url=$formatter->link_url($new);
      header("Location: $url");
    }
  } else {
    $buf=exec(escapeshellcmd("echo ".$options[page])." | ".escapeshellcmd("iconv -f $from -t $to"));
    $url=$formatter->link_url($buf);
    header("Location: $url");
  }
  return;
}

?>
