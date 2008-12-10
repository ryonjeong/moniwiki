<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a simple filter plugin for the nforge
//
// Usage: set $filters='nforge'; in the config.php
//   $filters='abbr,nforge';
//
// $Id$

function filter_nforge($formatter,$value,$options) {
    global $Config;

    preg_match("@\/([^\/]+)$@", $formatter->url_prefix, $proj_name);
    $group_id = $Config['group_id'];

    $issue = qualifiedUrl('/tracker/index.php?func=detail&group_id='.$group_id.'&aid=');
    $svn = qualifiedUrl('/scm/viewvc.php/?root='.$proj_name[1].'&view=rev&revision=');

    $_rule=array(
        # link to an issue #210
        '/(?<![a-zA-Z])\!?\#([0-9]+)/',
        # link to an revision r452
        "/(?<![a-zA-Z])\!?r([0-9]+)/",
    );
    $_repl=array(
        "[^$issue".'\\1 #\\1]',
        "[^$svn".'\\1 r\\1]',
    );
    return preg_replace($_rule,$_repl,$value);
}
// vim:et:sts=4:sw=4:
?>