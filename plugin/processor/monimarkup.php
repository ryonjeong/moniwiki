<?php
// Copyright 2006-2007 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a moniwiki formatting processor for the MoniWiki
//
// $Id$
/**
 * @date    2006-08-09
 * @name    Moniwiki Processor
 * @desc    Moniwiki default processor
 * @version $Revision$
 * @depend  1.1.3
 * @license GPL
 */

class processor_monimarkup
{
    var $_type='wikimarkup';

    function processor_monimarkup(&$formatter,$options=array())
    {
        $this->formatter=&$formatter;
    }

    function _pass1($text)
    {
        // NoSmoke MultilineCell to moniwiki for lower version compatibility
        $text=str_replace(array('{{|','|}}'),
            array("{{{:.closure\n",'}}}'),$text);
        // Pass #1: separate code inline/blocks.
        $chunk=preg_split('/({{{|}}})/',$text,-1,
            PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
        $state=0;
        $ci=-1;
        $ii=0;
        $inline=array();
        $block=array();
        $btype=array();
        $j=1;
        $k=1;
        $sz=count($chunk);
        for ($i=0;$i<$sz;++$i) {
            if ($chunk[$i] == '{{{') {
                ++$state;
                if ($state == 1) {
                    $ci=$i;
                } else if ($state > 1) {
                    $chunk[$ci].=$chunk[$i];
                    unset($chunk[$i]);
                }
            } else if ($state > 0) {
                $chunk[$ci].=$chunk[$i];
                if ($chunk[$i] == '}}}') {
                    --$state;
                    if ($state==0) {
                        if (strpos($chunk[$ci],"\n")) {
                            $block[$j]=substr($chunk[$ci],3,-3);
                            $chunk[$ci]="\007".$j."\007";
                            list($type,$dum)= explode("\n",$block[$j],2);
                            if (!empty($type)) {
                                if ($type{0}=='#' and $type{1}=='!') {
                                    list($tag,$dummy)= explode(' ',$type);
                                    $btype[$j]=substr($tag,2);
                                } else if ($type{0} == ':') {
                                    # for a quote block
                                    $block[$j]=substr($block[$j],1);
                                    $arg= substr($type,1);
                                    if ($type{1}=='#' or $type{1}=='.') {
                                        $btype[$j]='moni';
                                        $block[$j]='#!moni '.$arg."\n".$dum;
                                    } else {
                                        $btype[$j]='moni';
                                        $block[$j]="#!moni .quote\n$arg\n".$dum;
                                    }
                                } else {
                                    // XXX check processor/block type
                                    $btype[$j]='plain';
                                }
                            } else { // ignore first "\n"
                                $block[$j]=$dum;
                                $btype[$j]=$tag;
                            }
                            ++$j;
                        } else {
                            $inline[$k]=$chunk[$ci];
                            #$inline[$k]=substr($chunk[$ci],3,-3);
                            $chunk[$ci]="\035".$k."\035";
                            ++$k;
                        }
                    }
                }
                unset($chunk[$i]);
            }
        }
        # close last block/inline '}}}'
        #if ($state>0)
        #    for (;$state>0;$state--) $chunk[$ci].='}}}';

        #print_r($chunk);
        $body=implode('',$chunk);
        #print $body;
        return array($body,$inline,$block,$btype);
    }

    function _node($depth,&$node,$line)
    {
        if (is_array($node[$depth])) {
            $my=$node[$depth];
            $my['value']=$line;
            $my['depth']=$depth;
            return $my;
        } else {
            return $line;
        }
    }

    function _pass2($text)
    {
        $lines=explode("\n",$text);
        $chunk=array();
        $indlen=0;
        $myindlen=0;
        $_indlen=array(0);
        $_indtype=array(null);
        $_nodtype=array('');
        $_myindlen=array(0);
        $_in_li=0;
        $_eop=0; // end of paragraph
        $oline=null;
        foreach ($lines as $line) {
            if (!trim($line)) {
                if ($_in_li) $oline.="\n".$line;
                else {
                    $oline.=isset($oline) ? "\n".$line:$line;
                    $_eop=1;
                }
                continue;
            } else if (preg_match("/^([ ]*(={1,5})\s(.*\s*)\s\\2\s?)$/",$line,$m)) {
                $tag='HEAD';
                $depth=strlen($m[2]);
                if ($oline)
                    $chunk[]= $this->_node($_in_li,$_nodtype,$oline);
                $oline=null;
                $chunk[]=array('tag'=>'HEAD','type'=>'complete',
                    'depth'=>$depth,'value'=>$m[3]);
                $_eop=0;
                $_in_li=0;
                continue;
            } else if (preg_match("/^[ ]*(-{4,})$/",$line,$m)) {
                if ($oline)
                    $chunk[]= $this->_node($_in_li,$_nodtype,$oline);
                $oline=null;
                $_eop=0;
                $_in_li=0;
                $chunk[]=array('tag'=>'HR','type'=>'complete','value'=>$m[1]);
                continue;
            } else if (preg_match("/^((?:\>\s)*\>(\.\w+)?\s|\s+)/",$line,$m)) {
                $_eop=0;
                $mytype=array('tag'=>'LIST','type'=>'di');
                $indlen=$myindlen=strlen($m[0])-strlen($m[2]);
                if ($line{0}=='>') {
                    $myclass=$m[2] ? substr($m[2],1):'quote';
                    $mytype['attributes']=array('class'=>$myclass);
                    $mytype['type']='dq';
                    $indlen=$myindlen=$myindlen>>1;
                }
                #print_r($m);
                #print "==$indlen`".$m[1]."'".$line."<br>\n";
                $cutline=substr($line,strlen($m[0]));
                $indtype=null;
                if (preg_match("/^((\*\s?)|(?:([1-9]\d*|[aAiI])\.)(?:#(\d+))?\s)/",
                    $cutline,$m)) {
                    $myindlen=$indlen+strlen($m[1])-strlen($m[4]);
                    $type=$m[2] ? 'ul':$m[3];
                    $mytype['type']=$type;
                    $start=$m[4] ? $m[4]:($m[3] ? $m[3]:null);
                    if (isset($start) and is_numeric($start) and $start > 1)
                        $mytype['attributes']=array('start'=>$start);
                    $cutline=substr($cutline,strlen($m[1]));
                    $indtype='li';
                }
                if ($indlen < $_indlen[$_in_li]) {
                    // fix indlen XXX
                    if ($oline)
                        $chunk[]=$this->_node($_in_li,$_nodtype,$oline);
                    while($_in_li > 0 && $indlen < $_indlen[$_in_li]) {
                        unset($_indlen[$_in_li]);
                        unset($_indtype[$_in_li]);
                        unset($_nodtype[$_in_li]);
                        unset($_myindlen[$_in_li]);
                        --$_in_li;
                    }
                    if ($_in_li) {
                        if (!$indtype)
                            $_nodtype[$_in_li]['type']='cdata';
                        else {
                            $_myindlen[$_in_li]=$myindlen;
                            $_nodtype[$_in_li]=$mytype;
                        }
                    }
                    $oline=$cutline;
                    continue;
                }
                if ($_indtype[$_in_li] == $indtype) {
                    if ($indlen == $_indlen[$_in_li]) {
                        if ($indtype or
                            $_nodtype[$_in_li]['attributes']['class'] !=
                            $mytype['attributes']['class']) {
                            # another list/indent
                            if ($oline)
                                $chunk[]=
                                    $this->_node($_in_li,$_nodtype,$oline);
                            $_myindlen[$_in_li]=$myindlen;
                            $_nodtype[$_in_li]=$mytype;
                            $oline=$cutline;
                        } else # continued indent
                            $oline.= isset($oline) ? "\n".$cutline:$cutline;
                        continue;
                    }
                }
                if ($_indlen[$_in_li]) { // continued list ?
                    if ($indlen == $_indlen[$_in_li]) {
                        if ($indtype) {
                            if ($oline)
                                $chunk[]=
                                    $this->_node($_in_li,$_nodtype,$oline);
                            $_myindlen[$_in_li]=$myindlen;
                            $_nodtype[$_in_li]=$mytype;
                            $oline=$cutline;
                            continue;
                        } else 
                            $oline.= isset($oline) ? "\n".$cutline:$cutline;
                        $_myindlen[$_in_li]=$indlen; // reset myindlen
                        continue;
                    }
                    if (!$indtype and $indlen == $_myindlen[$_in_li]) {
                        $oline.="\n".substr($line,$myindlen);
                        continue;
                    }
                }
                if ($indlen > $_indlen[$_in_li]) {
                    if ($oline)
                        $chunk[]= $this->_node($_in_li,$_nodtype,$oline);
                    $_in_li++;
                    $_indtype[$_in_li]=$indtype; # add list type
                    $_indlen[$_in_li]=$indlen; # add list depth
                    $_nodtype[$_in_li]=$mytype; # add list depth
                    $_myindlen[$_in_li]=$myindlen; # add list depth
                    #if (!$indtype)
                    #$_nodtype[$_in_li]['type']='cdata';
                    $oline=$cutline;
                    continue;
                }
                // not reach
            }
            // paragraph block
            if ($_indlen[$_in_li]) {
                $chunk[]= $this->_node($_in_li,$_nodtype,$oline);
                $_in_li=0;
                $_eop=0;
                $oline=$line;
                continue;
            } else if ($_eop) {
            #} else if ($_eop and $oline) {
                $chunk[]= $oline;
                $_eop=0;
                $oline=$line;
                continue;
            }
            $oline.=isset($oline) ? "\n".$line:$line;
        }
        if ($oline)
            $chunk[]= $this->_node($_in_li,$_nodtype,$oline);
        #print_r($chunk);
        return $chunk;
    }

    function _parseTable($text) {
        if (substr($text,-1,1)=="\n") {
            $_del_cr=1;
            $text=substr($text,0,-1);
        }
        if (preg_match("/^(\010|\006).*\\1/s",$text,$m)) {
            $_diff=$m[1];
            $text=substr($text,1,-1);
        }
        $formatter=&$this->formatter;
        $_in_table=0;
        $lines=explode("\n",$text);
        $tout='';
        foreach ($lines as $line) {
            if (!trim($line)) {
                if ($_in_table) {
                    $tout.=$formatter->_table(0,$dumm);
                    $_in_table=0;
                }
                $tout.=$line."\n";
                continue;
            }
            $tr_diff='';
            if ($line{0}== "\010" or $line{1}=="\006") {
                $tr_diff=$line{0} == "\010" ? 'diff-added':'diff-removed';
                $line=substr($line,1,-1);
            }
            if (!$_in_table and $line[0]=='|' and
                preg_match("/^(\|([^\|]+)?\|((\|\|)*))(&lt;[^>\|]*>)?(.*)(\|\|)$/s",$line,$m)) {
                $open.=$formatter->_table(1,$m[5]);
                if ($m[2]) $open.='<caption>'.$m[2].'</caption>';
                if (!$m[5]) $line='||'.$m[3].$m[6].'||';
                $_in_table=1;
            } elseif ($_in_table and $line[0]!='|') {
                $close=$formatter->_table(0,$dumm).$close;
                $_in_table=0;
            }
            if ($_in_table) {
                $line=substr($line,0,-2);
                $cells=preg_split('/((?:\|\|)+)/',$line,-1,
                    PREG_SPLIT_DELIM_CAPTURE);
                $row='';
                $tr_attr=$tr_diff ? 'class="'.$tr_diff.'"':'';
                for ($i=1,$s=sizeof($cells);$i<$s;$i+=2) {
                    $align='';
                    preg_match('/^((&lt;[^>]+>)?)(\s?)(.*)(?<!\s)(\s*)?$/s',
                    $cells[$i+1],$m);
                    $cell=$m[3].$m[4].$m[5];
                    $cell=str_replace("\n","<br />\n",$cell);
                    if ($m[3] and $m[5]) $align='center';
                    else if (!$m[3]) $align='';
                    else if (!$m[5]) $align='right';

                    $attr=$formatter->_td_attr($m[1],$align);
                    if (!$tr_attr) $tr_attr=$m[1]; // XXX
                    $attr.=$formatter->_td_span($cells[$i]);
                    $row.="<td $attr>".$cell.'</td>';
                }
                $line='<tr '.$tr_attr.'>'.$row.'</tr>';
                $line=str_replace('\"','"',$line); # revert \\" to \"
            }
            $tout.=$close.$open.$line."\n";
            $close='';$open='';
        }
        if (isset($_del_cr) and substr($tout,-1,1)!="\n") $tout.="\n";
        if ($_in_table) $tout.=$formatter->_table(0,$dumm);
        $tout=substr($tout,0,-1); // trash last "\n"; // XXX

        if ($_diff) $tout=$_diff.$tout.$_diff;
        return $tout;
    }

    function process($body='',$options=array()) {
        global $Config;

        if (trim($body)=='') return '';
        #$body=rtrim($body); # delete last empty line
        $palign=array('&lt;'=>'text-align:left',
                         '='=>'text-align:center',
                         '>'=>'text-align:right');

        $inline=array();
        $block=array();
        $btype=array();
        $options['nodiff']=0;
        $options['nomarkup']=0;
        $formatter=&$this->formatter;

        $pi=&$formatter->pi;
        #$formatter->set_wordrule($pi);

        if ($body{0}=='#' and $body{1}=='!') {
            list($line,$body)=explode("\n",$body,2);
            $dum=preg_split('/\s+/',$line);
            $myarg=$dum[1];
        }

        $my_divopen='';
        $my_divclose='';
        if (isset($myarg)) {
            if ($myarg{0}=='.') $my_type='class';
            else if ($myarg{0}=='#') $my_type='id';
            if (isset($my_type)) {
                $my_name=substr($myarg,1);
                $my_divopen="<div $my_type='$my_name'>";
                $my_divclose='</div>';
            }
        }
        $wordrule="({{{(?U)(.+)}}})|".
              "\[\[([A-Za-z0-9]+(\(((?<!\]\]).)*\))?)\]\]|"; # macro
        if ($Config['inline_latex']) # single line latex syntax
            $wordrule.="(?<=\s|^|>)\\$([^\\$]+)\\$(?:\s|$)|".
                 "(?<=\s|^|>)\\$\\$([^\\$]+)\\$\\$(?:\s|$)|";
        #if ($Config['builtin_footnote']) # builtin footnote support
        $wordrule.=$formatter->footrule.'|';
        $wordrule.=$formatter->wordrule;

        # 1-pass
        list($body,$inline,$block,$btype)=$this->_pass1($body);
        # 2-pass
        $chunk=$this->_pass2($body);

        $hr_func=$Config['hr_type'].'_hr';

        $_lidep=array(0);
        $_lityp=array(0);
        $_li=0;
        $out='';
        foreach ($chunk as $c) {
            if (is_array($c)) {
                $val=&$c['value'];
                $val= preg_replace($formatter->baserule,
                    $formatter->baserepl,$val);
                if ($_li>0 and $c['tag']!='LIST')
                    while($_li>0 and $_lidep[$_li] > 0) {
                        $out.=$this->_li(0,$_lityp[$_li]);
                        $out.=$this->_list(0,$_lityp[$_li]);
                        --$_li;
                    }
                switch($c['tag']) {
                case 'HEAD':
                    $val=preg_replace_callback("/(".$wordrule.")/",
                        array(&$formatter,'link_repl'),$val);
                    ++$formatter->sect_num;
                    $anchor=$ed='';
                    if (!empty($formatter->section_edit) &&
                            empty($formatter->preview)) {
                        $act='edit';
                        $sect_num=&$formatter->sect_num;
                        if ($Config['sectionedit_attr']) {
                            if (!is_string($Config['sectionedit_attr']))
                                $sect_attr=' onclick='.
                                    '"javascript:sectionEdit(null,this,'.
                                    $sect_num.');return false;"';
                            else
                                $sect_attr=$Config['sectionedit_attr'];
                        }
                        $url=$formatter->link_url($formatter->page->urlname,
                            '?action='.$act.'&amp;section='.$sect_num);
                        $lab=_("edit");
                        $ed="<div class='sectionEdit' style='float:right;'>".
                            "[<a href='$url'$sect_attr>$lab</a>]</div>\n";
                        $anchor_id='sect-'.$sect_num;
                        $anchor="<a id='$anchor_id'></a>";
                    }

                    if ($sect_num >1) $out.=$this->_div(0);
                    $out.=$this->_div(1," class='level$c[depth]'");
                    $out.= $anchor.$ed.$formatter->head_repl($c['depth'],$val);
                    break;
                case 'HR':
                    $out.= $c['value'];
                    // already converted by $baserule
                    #$out.= $formatter->$hr_func($c['value']);
                    break;
                case 'LIST':
                    $test=$c['type'];
                    $type=is_numeric($test{0}) ? 'ol':$test;
                    $linfo='';
                    $listy='';
                    if ($type=='ol')
                        $linfo=$c['attributes'] ? $c['attributes']['start']:'';
                    else if ($test{0}=='d') {
                        $linfo=$c['attributes'] ? $c['attributes']['class']:'';
                        if (preg_match('/^((\s*)(&lt;|=|>)?{([^}]+)})/s',$val,
                                $sty)) {
                            if ($sty[3]) $sty[4].=';'.$palign[$sty[3]];
                            $val=$sty[2].substr($val,strlen($sty[1]));
                            $listy=$sty[4];
                        }
                    }

                    // new list/indent type
                    if ($_lidep[$_li] == $c['depth'] and 
                        $_li== 1 and $type!= $_lityp[$_li]) {
                        // close all
                        while($_li>0) {
                            $out.=$this->_li(0,$_lityp[$_li]);
                            $out.=$this->_list(0,$_lityp[$_li]);
                            --$_li;
                        }
                    }
                    if ($_lidep[$_li] < $c['depth']) {
                        $out.=$this->_list(1,$type,$linfo);
                        $out.=$this->_li(1,$type,$linfo,$listy);
                        ++$_li;
                        $_lidep[$_li]=$c['depth'];
                        $_lityp[$_li]=$type;
                    } else if ($_lidep[$_li] == $c['depth']) {
                        $out.=$this->_li(0,$type);
                        $out.=$this->_li(1,$type,$linfo,$listy);
                    } else {
                        while($_li>0 and $_lidep[$_li] > $c['depth']) {
                            $out.=$this->_li(0,$_lityp[$_li]);
                            $out.=$this->_list(0,$_lityp[$_li]);
                            --$_li;
                        }
                        if ($c['type']!='cdata') {
                            $out.=$this->_li(0,$type);
                            $out.=$this->_li(1,$type,$linfo,$listy);
                        }
                    }
                    if (strpos($val,'||')!== false)
                        $val=$this->_parseTable($val);
                    $val=preg_replace_callback("/(".$wordrule.")/",
                        array(&$formatter,'link_repl'),$val);
                    if ($formatter->auto_linebreak) {
                        $val1=$val;
                        $val=preg_replace("/(?<!>|\007)\n/","<br />\n",$val);
                        if ($val1!=$val) $val.="<br />";
                        unset($val1);
                    }
                    else {
                        $val1=$val;
                        $val=preg_replace("/^[ ]*$/m","<br />",$val1);
                        if ($val1!=$val) $val.="<br />";
                        unset($val1);
                    }
                    #print "<pre>".htmlspecialchars($val)."</pre>";
                    $out.=$val;
                    break;
                default:
                    break;
                }
            } else {
                $c= preg_replace($formatter->baserule,$formatter->baserepl,$c);
                if (strpos($c,'||')!== false) {
                    $c=$this->_parseTable($c);
                }

                if (preg_match('/^((\s*)(&lt;|=|>)?{([^}]+)})/s',$c,$sty)) {
                    if ($sty[3]) $sty[4].=';'.$palign[$sty[3]];
                    $c=$sty[2].substr($c,strlen($sty[1]));
                }

                if ($formatter->auto_linebreak)
                    $c=preg_replace("/(?<!>|\007|^)\n/","<br />\n",$c);
                else
                    $c=preg_replace("/^[ ]*$/m","<br />",$c);
                $c=preg_replace_callback("/(".$wordrule.")/",
                    array(&$formatter,'link_repl'),$c);
                while($_li>0 and $_lidep[$_li] > 0) {
                    $out.=$this->_li(0,$_lityp[$_li]);
                    $out.=$this->_list(0,$_lityp[$_li]);
                    --$_li;
                }

                $out.= $this->_div(1,' class="para"',$sty[4]).$c.$this->_div(0);
            }
        }
        while($_li>0 and $_lidep[$_li] > 0) {
            $out.=$this->_li(0,$_lityp[$_li]);
            $out.=$this->_list(0,$_lityp[$_li]);
            --$_li;
        }
        if ($formatter->sect_num >1) $out.=$this->_div(0);
        if (!empty($formatter->smiley_rule))
            $out=preg_replace($formatter->smiley_rule,
                $formatter->smiley_repl,$out);

        $out=preg_replace("/\007(\d+)\007/e",
            "\$formatter->processor_repl(\$btype[$1],\$block[$1])",$out);
        $out=preg_replace("/\035(\d+)\035/e", 
            "\$formatter->link_repl(\$inline[$1])",$out);

        return $my_divopen.$out.$my_divclose;
    }

    function _list($on,$type='',$linfo='')
    {
        $close=$on ? '':'/';
        if ($type{0}=='d') {
            if ($on) {
                $attr=$linfo ?  " class='$linfo'":" class='indent'";
                return "<blockquote$attr>\n";
            } else {
                return "</blockquote>\n";
            }
        }
        if ($on) {
            if ($linfo) {
                $start=substr($linfo,1);
                if ($start)
                    return "<$type type='$linfo[0]' start='$start'>";
                return "<$type type='$linfo[0]'>";
            }
            return "<$type>\n";
        }
        return "</$type>\n";
    }

    function _li($on,$type='',$start=null,$sty='')
    {
        if ($type{0}=='d') {
            if ($sty) $sty=' style="'.$sty.'"';
            return $on ? "<div$sty>":"</div>\n";
        }
        if ($on) {
            if ($start)
                return "<li value='$start'>";
            return "<li>";
        }
        return "</li>\n";
    }

    function _div($on,$attr='',$sty='') {
        if ($sty) $sty=' style="'.$sty.'"';
        $tag=array("</div>\n","<div$attr$sty>");
        return $tag[$on].$close;
    }
}

if (basename($_SERVER['argv'][0]) == basename(__FILE__)) {
//if (basename($_SERVER['SCRIPT_NAME']) == basename(__FILE__)) {

$text=<<<EOF
Paragraph
Paragraph
 Paragraph
 Paragraph
  PARA
  PARA
 * '''''Mix''' at the beginning''
   test test
   test
    * sublist
      sublist
      sublist
      sublist
      sublist

       ddd
       ddd

    sublist continue
    sublist

   test continue
   test
 * '''''Mix'' at the beginning'''
   TEst test second
   continue

   continue
 * '''Mix at the ''end'''''
   third
   continue
 * ''Mix at the '''end'''''
----
 1. first
    first
  1. hello world
     hello world
 2. second
    second
 3.#4 third
    third
EOF;
$text=<<<EOF
== Text Formatting Rules ==

Leave blank lines between paragraphs. Use {{{[[BR]]}}} to insert linebreaks into paragraphs.

You can render text in ''italics'' or '''bold'''.
To write italics, enclose the text in double single quotes.
To write bold, enclose the text in triple single quotes.
__Underlined text__ needs a double underscore on each side.
You get ^superscripted^ text by enclosing it into caret characters,
and ,,subscripts,, have to be embedded into double commas.

To insert program source without reformatting in a {{{monospace font}}}, use three curly braces:
{{{
10 PRINT "Hello, world!"
20 GOTO 10
}}}
Note that within code sections, both inline and display ones, any wiki markup is ignored. An alternative and shorter syntax for `inlined code` is to use backtick characters.

For more information on the possible markup, see HelpOnEditing.

=== Example ===
{{{
__Mixing__ ''italics'' and '''bold''':
 * '''''Mix''' at the beginning'' 
 * '''''Mix'' at the beginning'''
 * '''Mix at the ''end'''''
 * ''Mix at the '''end'''''

You might recall ''a''^2^ `+` ''b''^2^ `=` ''c''^2^ from your math lessons, unless your head is filled with H,,2,,O.

An {{{inline code sequence\}}} has the start and end markers on the same line. Or you use `backticks`.

A code display has them on different lines: {{{
'''No''' markup here!
\}}}
}}} 
/!\ In the above example, we "escaped" the markers for source code sequences by inserting \ character before the curly braces.

/!\ MoinMoin does not support escape "{''''''{{" markup in preblock.

=== Display ===
__Mixing__ ''italics'' and '''bold''':
 * '''''Mix''' at the beginning''
  * '' '''Mix''' at the beginning''
 * '''''Mix'' at the beginning'''
  * ''' ''Mix'' at the beginning'''
 * '''Mix at the ''end'''''
  * '''Mix at the ''end'' '''
 * ''Mix at the '''end'''''
  * ''Mix at the '''end''' ''

You might recall ''a''^2^ `+` ''b''^2^ `=` ''c''^2^ from your math lessons, unless your head is filled with H,,2,,O.

An {{{inline code sequence}}} has the start and end markers on the same line. Or you use `backticks`.


A code display has them on different lines: {{{
'''No''' markup here!
}}}

=== ColorizedSourceCode ===
Example:

{{{#!php
<?
phpinfo();
?>
}}}

== SixSingleQuotes and backticks ==
{{{
Wiki''''''Name vs Wiki``Name
}}}

Wiki''''''Name vs Wiki``Name
== MoniWiki extensions ==
To write --striked text--, enclose the text in double dashes.

Superscripted text also obtained by encloseing a string into double carets ^^like it^^.

/!\ MoinMoin does superscript texts contain space but, MoniWiki does not. You can superscript a string contains space by encloseing it into double carets.
=== coloring and sizing ===
 * {{{{{{#0000ff Hello World}}}}}} is renderd as {{{#0000ff Hello World}}}
 * {{{{{{+3 Hello World}}}}}} is rendered as {{{+3 Hello World}}}
 * {{{{{{-1 Hello World}}}}}} is rendered as {{{-2 Hello World}}}
----
''escape font styling syntax''
 * {{{{{{<space>#red Hello World}}}}}} is rendered as {{{ #red Hello World}}}

Please see also WikiSlide
----
[[Navigation(HelpOnEditing)]]
EOF;
#    header("Content-Type:text/plain");
print <<<HEAD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>wow</title>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
</head>
<body>
HEAD;

    $f=&new processor_simple($m);
    print $f->process($text);
    print "<a href='http://validator.w3.org/check/referer'>XHTML</a>";
print <<<FOOT
</body>
</html>
FOOT;

}

// vim:et:sts=4
?>