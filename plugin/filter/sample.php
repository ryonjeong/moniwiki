<?php
// Copyright 2005 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample filter plugin for the MoniWiki
//
// $Id$

function filter_sample($formatter,$value,$options) {
  return preg_replace($value);
}
// vim:et:sts=4:
?>
