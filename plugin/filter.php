<?php
// Copyright 2003-2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Usage: [[Test]]
//
// $Id$

function macro_Filter($formatter,$value) {
    return "HelloWorld !\n";
}

function do_filter($formatter,$options) {
    if (!$options['filter']) {
        do_invalid($formatter,$options);
        return;
    }
    $body=$formatter->page->get_raw_body($options);
    $filters=preg_split("/(\||,)/",$options['filter']);
    foreach ($filters as $ft)
        $body=$formatter->filter_repl(trim($ft),$body,$options);

    if ($options['raw']) {
        $formatter->send_header('Content-Type: text/plain');
        print $body;
        return;
    }
    $formatter->send_header('',$options);
    $formatter->send_title('','',$options);
    
    print '<pre>'.$body.'</pre>';
    $formatter->send_footer("",$options);
    return;
}

// vim:et:sts=4:
?>
