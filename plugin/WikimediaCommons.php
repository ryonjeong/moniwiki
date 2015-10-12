<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a wikimedia commons plugin for the MoniWiki
//
// Since: 2015-10-06
// Name: WikimediaCommons
// Author: wkpark at gmail.com
// Description: WikiMedia commons plugin to embed a wikimedia image.
// Credit: some part of script inspired from the Rigveda version of a WikiCommons macro but rewritten.
//
// Params: width, height, algin etc.
//
// Usage: [[WikimediaCommons(URL or filename,params...)]]
//

function do_wikimediacommons($formatter, $params = array()) {
    global $DBInfo;

    $ret = macro_WikimediaCommons($formatter, $params['value']);
    echo $ret;
}

function macro_WikimediaCommons($formatter, $value, $params = array()) {
    global $DBInfo, $Config;

    if (($p = strpos($value, ',')) !== false) {
        $arg = substr($value, $p + 1);
        $value = substr($value, 0, $p);
        $arg = preg_replace('@\s*,\s*@', ',', $arg);
        $arg = preg_replace('@\s*=\s*@', '=', $arg);
        $args = explode(',', $arg);
    }

    // check full url
    if (preg_match('@^https?://upload\.wikimedia\.org/wikipedia/commons/(thumb/)?./../([^/]+\.(?:gif|jpe?g|png|svg))(?(1)/(\d+px)-\2)@', $value, $m)) {
        $remain = substr($value, strlen($m[0]));

        $value = $m[2];
        if (!empty($m[3]))
            $width = intval($m[3]);
    }

    $styles = array();
    foreach ($args as $arg) {
        $k = $v = '';
        if (($p = strpos($arg, '=')) !== false) {
            $k = substr($arg, 0, $p);
            $v = substr($arg, $p + 1);
        } else {
            continue;
        }
        $k = strtolower($k);
        switch($k) {
            case 'width':
            case 'height':
                if (preg_match('@^(\d+)(px|%)?$@', $v, $m)) {
                    if (isset($m[2]) && $m[2] == '%') {
                        $styles[$k] = $v;
                    } else {
                        $styles[$k] = $v;
                        ${$k} = intval($m[1]);
                    }
                }
                break;
            case 'align':
                $v = strtolower($v);
                if (in_array($v, array('left', 'right', 'center')))
                    $addClass = ' img'.ucfirst($v);
                break;
        }
    }

    $common = new Cache_Text('wikicommons');
    $key = $value;
    if (isset($width)) $key.= $width;
    if (isset($height)) $key.= '.h'.$height;
    if (!empty($formatter->refresh) || ($images = $common->fetch($key)) === false) {
        $api_url = 'https://commons.wikimedia.org/w/api.php';

        $data = array(
            'action' => 'query',
            'titles' => 'Image:'.$value,
            'prop' => 'imageinfo',
            'iiprop' => 'extmetadata|url',
            'format' => 'json',
            'rawcontinue' => '1',
        );
        if (!empty($width)) {
            $data['iiurlwidth'] = min(1280, $width);
        } else if (!empty($height)) {
            $data['iiurlheight'] = min(1280, $height);
        } else {
            // default image width
            $data['iiurlwidth'] = 640;
        }
        require_once(dirname(__FILE__).'/../lib/HTTPClient.php');

        $http = new HTTPClient();
        $save = ini_get('max_execution_time');
        set_time_limit(0);
        $http->sendRequest($api_url, $data, 'POST');
        set_time_limit($save);

        // FIXME
        if ($http->status != 200) {
            return '';
        }
        $res = json_decode($http->resp_body);
        $images = $res->query->pages;

        $common->update($key, $images);
    }

    $image = current($images);
    $image_url = $image->imageinfo[0]->thumburl;
    $desc_url = $image->imageinfo[0]->descriptionurl;

    if (empty($styles['width'])) {
        $styles['width'] = $image->imageinfo[0]->thumbwidth.'px';
    }

    $style = '';
    foreach ($styles as $k=>$v)
        $style.= $k.':'.$v.';';
    if (!empty($style))
        $style = ' style="'.$style.'"';

    $copyright = $image->imageinfo[0]->extmetadata->Copyrighted->value;
    $description = $image->imageinfo[0]->extmetadata->ImageDescription->value;
    $author = $image->imageinfo[0]->extmetadata->Artist->value;
    $license = $image->imageinfo[0]->extmetadata->License->value;

    if (!empty($formatter->fetch_images)) {
        $image_url = $formatter->fetch_action. str_replace(array('&', '?'), array('%26', '%3f'), $image_url);
        // use thumbnails ?
        if (!empty($formatter->use_thumb_by_default))
            $image_url.= '&amp;thumbwidth='.$formatter->thumb_width;
    }
    $info = ($copyright == 'True' ? '&copy;' : '(&#596;) ').$author.($copyright == 'True' ? " ($license)" : '');

    $out = '<div class="externalImage">';
    if (empty($addClass))
        $cls = ' class="'.$addClass.'"';
    $out.= "<div".$cls."><img src='$image_url'$style>";
    $out.= "<div class='info'>".$info.' from '."<a href='$desc_url' target='_blank'>WikiMedia Commons</a></div>";
    $out.= "</div>";
    if (!empty($description))
        $out.= '<div>'.$description.'</div>';
    $out.= "</div>\n";

    return $out;
}

// vim:et:sts=4:sw=4:
