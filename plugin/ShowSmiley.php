<?php
// Copyright 2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a ShowSmiley macro plugin for the MoniWiki
//
// Usage: [[ShowSmiley]]
//
// $Id$

function macro_ShowSmiley($formatter,$value) {
  global $DBInfo;

  $idx=0;
  $out='<table class="wiki"><tr class="wiki">';
  $col=4;
  for ($i=0;$i<$col;$i++) {
    $out.='<td><b>Markup</b></td><td><b>Image</b></td>';
    if (($i+1) % $col) $out.='<td></td>';
  }
  $out.='</tr><tr class="wiki">';

  foreach ($DBInfo->smileys as $key=>$value) {
    $skey=str_replace("\\","\\\\",$key);
    $out.= '<td>'.$key.'</td><td>'.$formatter->smiley_repl($key)."</td>";
    $idx++;
    if (!($idx % $col)) $out.='</tr><tr class="wiki">';
    else $out.='<td></td>';
  }
  if ($idx % $col) {
    for (;$idx % $col;++$idx) {
      $out.='<td></td><td></td>';
      if (($idx+1) % $col) $out.='<td></td>';
    }
    $out.='</tr>';
  }
  $out.="<tr class='wiki'><th colspan='".($col*3-1)."'>Total $idx icons</th>";
  $out.='</tr></table>';
  return $out;
}

// vim:et:sts=2:
?>
