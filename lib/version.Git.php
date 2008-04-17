<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Git versioning plugin for the MoniWiki
//
// $Id$
// WARNING: experimental
//

class Version_Git extends Version_RCS {
  var $DB;

  function Version_Git($DB) {
    $this->DB=$DB;

    $this->cwd=getcwd();

    $this->NULL='';
    $this->pmark='^';
    if(getenv("OS")=="Windows_NT") {
      # "^^" under the Win32
      $this->pmark='^^';
    } else {
      $this->NULL=' 2>/dev/null';
    }
    if ($DB->rcs_error_log) $this->NULL='';

    $this->git_user=$DB->git_user;

    // init
    if (!is_dir($this->DB->text_dir.'/.git'))
      $log = $this->_init();
  }

  function _init() {
    chdir($this->DB->text_dir);
    $fp=popen("git-init",'r');

    $out='';
    if ($fp) {
      while (!feof($fp)) {
        $line=fgets($fp,2048);
        $out.= $line;
      }
      pclose($fp);
    }
    chdir($this->cwd);
    return $out;
  }

  function _filename($pagename) {
    # Return filename where this word/page should be stored.
    return $this->DB->_getPageKey($pagename);
  }

  function co($pagename,$rev='',$opt='') {
    # XXX
    $filename= $this->_filename($pagename);

    if ($rev) $rev=':'.$rev;

    chdir($this->DB->text_dir);
    $fp=@popen("git co $rev ".$filename,"r");
    chdir($this->cwd);
    $out='';
    if ($fp) {
      while (!feof($fp)) {
        $line=fgets($fp,2048);
        $out.= $line;
      }
      pclose($fp);
    }
    return $out;
  }

  function ci($pagename,$log) {
    $key=$this->_filename($pagename);
    chdir($this->DB->text_dir);
    $ret=system("git-add ".$key." ".$this->NULL);
    $ret=system("git-commit -m\"".$log."\" ".$key.$this->NULL);
    chdir($this->cwd);
  }

  function _ci($filename,$log) {
    $key=basename($filename); # XXX
    chdir($this->DB->text_dir);
    $fp=popen("git-add ".$key.$this->NULL,"r");
    if ($fp) pclose($fp);
    $fp=popen("git-commit -m\"".$log."\" ".$key.$this->NULL,"r");
    if ($fp) pclose($fp);

    chdir($this->cwd);
  }

  function _add($pagename,$log) {
    $key=$this->_filename($pagename);
    chdir($this->DB->text_dir);
    $ret=system("git-add ".$key.$this->NULL);
    chdir($this->cwd);
  }

  function rlog($pagename,$rev='',$opt='',$oldopt='') {
    // oldopts are incompatible options only supported by the rlog in the rcs
    if ($rev)
      $rev = ":$rev";
    $filename=$this->_filename($pagename);

    chdir($this->DB->text_dir);
    $fp= popen("git-log $opt $rev ".$filename.$this->NULL,"r");
    chdir($this->cwd);
    $out='';
    if ($fp) {
      while (!feof($fp)) {
        $line=fgets($fp,1024);
        $out .= $line;
      }
      pclose($fp);
    }
    return $out;
  }

  function diff($pagename,$rev='',$rev2='') {
    # XXX
    $filename=$this->_filename($pagename);
    chdir($this->DB->text_dir);
    $fp= popen("git-diff --no-color ".
      "HEAD".$this->pmark."$rev HEAD ".$filename,'r');

    chdir($this->cwd);

    if (!$fp) return '';
    while (!feof($fp)) {
      # trashing first two lines XXX
      $line=fgets($fp,1024);
      if (preg_match('/^--- /',$line)) {
        $line=fgets($fp,1024);
        break;
      }
    }
    while (!feof($fp)) {
      $line=fgets($fp,1024);
      $out.= $line;
    }
    pclose($fp);
    return $out;
  }

  function get_rev($pagename,$mtime,$last=0) {
    # FIXME
    if ($last==1) {
      $tag='head:';
      $opt='-h';
    } else $tag='revision';
    if ($mtime) {
      $date=gmdate('Y/m/d H:i:s',$mtime);
      if ($date) {
        $opt="-d\<'$date'";
        $tag='revision';
      }
    }

    return '1';
  }

  function purge($pagename,$rev) {
  }

  function delete($pagename) {
    $filename=$this->_filename($pagename);
    chdir($this->DB->text_dir);
    system("git rm ".$filename);
    chdir($this->cwd);
  }

  function rename($pagename,$new) {
    $keyname=$this->DB->_getPageKey($new);
    chdir($this->DB->text_dir);
    system("git mv ".$filename);
    chdir($this->cwd);
  }
}

// vim:et:ts=8:sts=2:sw=2
?>