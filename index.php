<?php


define('DIR_ORIGINALS',     '/data04/virt2350/domeenid/www.roots.ee/_PHOTOS'); //Original photos folder
define('DIR_THUMBNAILS',    './thumbnails'); //Thumbnails folder
define('CACHE_FILE',        '/data04/virt2350/domeenid/www.roots.ee/_PHOTOS/CACHE'); //Original photos folder

define('PAGE_URL',          'http://gallery.roots.ee');
define('PAGE_TITLE',        'Pildid');
define('PAGE_WIDTH',        '912px'); //Width of the page (example '100px' or '930px')

define('ANALYTICS_CODE',    'UA-260765-13'); //Google Analytics Web Property ID

define('SORT_GROUPS',       'DESC'); //Groups sort order - ASC or DESC
define('SORT_ALBUMS',       'DATE_DESC'); //Albums sort order - ASC, DESC, DATE_ASC or DATE_DESC
define('SORT_PHOTOS',       'DATE_ASC'); //Photos sort order - ASC, DESC, DATE_ASC or DATE_DESC

define('SHOW_QUOTA',        FALSE);






Class Photos {

    function __construct($group = NULL, $album = NULL, $photo = NULL) {
        $this->group = $group;
        $this->album = $album;
        $this->photo = $photo;
    }

    function get() {

        session_start();
        $_SESSION['gallery_cache'] = false;
        if($_SESSION['gallery_cache']) {
            $result = $_SESSION['gallery_cache'];
        } else {
            if(file_exists(CACHE_FILE)) {
                $result = $this->_cache_load();
            } else {
                $result = $this->get_gallery();
                $this->_cache_save($result);
            }
            $_SESSION['gallery_cache'] = $result;
        }

        return $result;
    }

    function get_gallery() {

        $groups = $this->get_dirs(str_replace('//', '/', DIR_ORIGINALS));
        foreach($groups as $group) {

            $group_size = 0;
            $group_url = $this->_to_url($group);

            $albums = $this->get_dirs(str_replace('//', '/', DIR_ORIGINALS.'/'.$group));
            foreach($albums as $album) {

                $album_size = 0;
                $album_url = $this->_to_url($album);

                $photos = $this->get_dirs(str_replace('//', '/', DIR_ORIGINALS.'/'.$group.'/'.$album));
                foreach($photos as $photo) {
                    $album_size += filesize(str_replace('//', '/', DIR_ORIGINALS.'/'.$group.'/'.$album.'/'.$photo));
                    if($this->_is_image(str_replace('//', '/', DIR_ORIGINALS.'/'.$group.'/'.$album.'/'.$photo))) {
                        $result[$group_url]['albums'][$album_url]['photos'][$photo] = $this->get_exif($group, $album, $photo);
                    }
                }
                $group_size += $album_size;

                if(is_array($result[$group_url]['albums'][$album_url]['photos'])) {

                    switch(SORT_PHOTOS) {
                        case 'DATE_DESC':
                        case 'DATE_ASC':
                            uasort($result[$group_url]['albums'][$album_url]['photos'], array($this, '_sort_photos'));
                            break;
                        case 'DESC':
                            krsort($result[$group_url]['albums'][$album_url]['photos']);
                            break;
                        default:
                            ksort($result[$group_url]['albums'][$album_url]['photos']);
                            break;
                    }

                    $result[$group_url]['albums'][$album_url]['name'] = $album;
                    $result[$group_url]['albums'][$album_url]['url'] = PAGE_URL .'/'. $group_url .'/'. $album_url;
                    $result[$group_url]['albums'][$album_url]['thumbnail'] = $result[$group_url]['albums'][$album_url]['photos'][key($result[$group_url]['albums'][$album_url]['photos'])]['thumbnail'];
                    $result[$group_url]['albums'][$album_url]['size'] = $album_size;

                    $min_date = time();
                    $max_date = 0;
                    foreach($result[$group_url]['albums'][$album_url]['photos'] as $photo) {
                        if($photo['Date'] > $max_date) $max_date = $photo['Date'];
                        if($photo['Date'] < $min_date) $min_date = $photo['Date'];
                    }
                    $result[$group_url]['albums'][$album_url]['min_date'] = $min_date;
                    $result[$group_url]['albums'][$album_url]['max_date'] = $max_date;

                }
            }

            switch(SORT_ALBUMS) {
                case 'DATE_DESC':
                case 'DATE_ASC':
                    uasort($result[$group_url]['albums'], array($this, '_sort_albums'));
                    break;
                case 'DESC':
                    krsort($result[$group_url]['albums']);
                    break;
                default:
                    ksort($result[$group_url]['albums']);
                    break;
            }

            $result[$group_url]['name'] = $group;
            $result[$group_url]['url'] = PAGE_URL .'/'. $group_url;
            $result[$group_url]['thumbnail'] = $result[$group_url]['albums'][key($result[$group_url]['albums'])]['thumbnail'];
            $result[$group_url]['size'] = $group_size;

        }

        switch(SORT_GROUPS) {
            case 'DESC':
                krsort($result);
                break;
            default:
                ksort($result);
                break;
        }
        //print_r($result);
        return $result;

    }

    function get_dirs($dir) {
        $result = array();

        if(is_dir($dir)) {
            $handle = opendir($dir);
            while (false !== ($file = readdir($handle))) {
                if ($file != "." AND $file != "..") {
                    $result[] = $file;
                }
            }
            closedir($handle);
        }

        return $result;
    }

    function _to_url($string) {

        $translation = array(
            'õ' => 'o',
            'Õ' => 'O',
            'ä' => 'a',
            'Ä' => 'A',
            'ü' => 'y',
            'Ü' => 'Y',
            'ö' => 'o',
            'Ö' => 'O',
            ' - ' => '-',
            ' ' => '-',
            '\'' => '',
            ',' => '',
        );
        $result = $string;

        foreach($translation as $search => $replace) {
            $result = str_replace($search, $replace, $result);
        }

        return $result;

    }

    function _sort_albums($a, $b) {

        $date1 = $a['max_date'];
        $date2 = $b['max_date'];

        if($date1 == $date2) $result = 0;
        if($date1 < $date2) $result = -1;
        if($date1 > $date2) $result = 1;

        if(SORT_ALBUMS == 'DATE_DESC') $result = ($result * -1);

        return $result;

    }

    function _sort_photos($a, $b) {

        $date1 = $a['Date'];
        $date2 = $b['Date'];

        if($date1 == $date2) $result = 0;
        if($date1 < $date2) $result = -1;
        if($date1 > $date2) $result = 1;

        if(SORT_PHOTOS == 'DATE_DESC') $result = ($result * -1);

        return $result;

    }

    function _is_image($filename) {

        $mime_types = array(
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'png' => 'image/png',
        );

        $ext = strtolower(array_pop(explode('.', $filename)));
        if (array_key_exists($ext, $mime_types)) {
            return TRUE;
        }
    }

    function _cache_save($data) {
        $file = fopen(CACHE_FILE, 'w');
        $string = base64_encode(serialize($data));
        fwrite($file, $string);
        fclose($file);
    }

    function _cache_load() {
        $file = fopen(CACHE_FILE, 'r');
        $string = fread($file, filesize(CACHE_FILE));
        fclose($file);
        $result = unserialize(base64_decode($string));
        return $result;
    }

    function get_exif($group, $album, $file) {

        $filepath = str_replace('//', '/', DIR_ORIGINALS.'/'.$group.'/'.$album.'/'.$file);

        if(!file_exists($filepath)) return FALSE;

        $result['name'] =        $file;
        $result['url'] =        $this->_to_url(PAGE_URL .'/'. $group.'/'.$album.'/'.$file);
        $result['thumbnail'] =        PAGE_URL .'/'. $group.'/'.$album.'/'.$file;

        if(exif_imagetype($filepath) == IMAGETYPE_JPEG) {

            $exif = exif_read_data($filepath, FALSE, TRUE, FALSE);

            $result['Date'] =            isset($exif['EXIF']['DateTimeOriginal']) ? strtotime($exif['EXIF']['DateTimeOriginal']) : filemtime($filepath);
            $result['Model'] =            $exif['IFD0']['Model'];

            $result['ISO'] =            $this->_exif_iso($exif);
            $result['Aperture'] =        $this->_fraction_to_decimal($exif['EXIF']['FNumber']);
            $result['ExposureTime'] =    ($exif['EXIF']['ExposureTime']) ? '1/'. round(1/$this->_fraction_to_decimal($exif['EXIF']['ExposureTime'])) : NULL;
            $result['FocalLength'] =    round($this->_fraction_to_decimal($exif['EXIF']['FocalLength']), 2);
            $result['ExposureBias'] =    $exif['EXIF']['ExposureBiasValue'];
            $result['Flash'] =            $this->_exif_flash($exif);

            $result['Software'] =        $exif['IFD0']['Software'];

            $result['Width'] =            $exif['COMPUTED']['Width'];
            $result['Height'] =            $exif['COMPUTED']['Height'];
            $result['Orientation'] =    $exif['IFD0']['Orientation'];

            $result['Latitude'] =        $this->_exif_gps($exif, 'Latitude');
            $result['Longitude'] =        $this->_exif_gps($exif, 'Longitude');
            //$result['EXIF'] =            $exif['EXIF'];
            $result['GPS'] =            $exif['GPS'];
        } else {
            $result['Date'] =            filemtime($filepath);
        }

        return $result;

    }

    function _exif_gps($exif, $x) {

        $gps = $exif['GPS']['GPS'.$x];

        if(is_array($gps)) {
            $deg = $this->_fraction_to_decimal($gps[0]);
            $min = $this->_fraction_to_decimal($gps[1]);
            $sec = $this->_fraction_to_decimal($gps[2]);

            $dec_min = ($min*60.0 + $sec)/60.0;

            $result  = round(($deg*60.0 + $dec_min)/60.0, 5);
        }

        return $exif['GPS']['GPS'.$x.'Ref'].$result;

    }

    function _exif_flash($exif) {

        if(isset($exif['EXIF']['Flash'])) return FALSE;

        $bin = substr('00000000'.decbin($exif['EXIF']['Flash']), -8);

        $flashfired = substr($bin, 7, 1);
        $returnd = substr($bin, 5, 2);
        $flashmode = substr($bin, 3, 2);
        $redeye = substr($bin, 1, 1);

        if($flashfired == '1') $result[] = 'Flash fired';
        if($flashfired == '0') $result[] = 'Flash did not fire';

        if($returnd == '10') $result[] = 'return light not detected';
        if($returnd == '11') $result[] = 'return light detected';

        if($flashmode == '01' OR $flashmode == '10') $result[] = 'compulsory flash mode';
        if($flashmode == '11') $result[] = 'auto mode';

        if($redeye == '1') $result[] = 'red-eye reduction';

        return implode(', ', $result);

    }

    function _exif_iso($exif) {

        if($exif['IFD0']['Make'] != 'Canon' OR isset($exif['EXIF']['ISOSpeedRatings'])) {
            $result = $exif['EXIF']['ISOSpeedRatings'];
        } else {
            if($exif['MAKERNOTE']['ModeArray'][16] == 0) $result = $exif['EXIF']['ISOSpeedRatings'];
            //if($exif['MAKERNOTE']['ModeArray'][16] == 15) $result = 'Auto';
            if($exif['MAKERNOTE']['ModeArray'][16] == 16) $result = '50';
            if($exif['MAKERNOTE']['ModeArray'][16] == 17) $result = '100';
            if($exif['MAKERNOTE']['ModeArray'][16] == 18) $result = '200';
            if($exif['MAKERNOTE']['ModeArray'][16] == 19) $result = '400';
        }

        return $result;

    }

    function _fraction_to_decimal($fraction) {
        if($fraction) eval ("\$result = 1.0*$fraction;");
        return $result;
    }

}



Class Thumbs {

    function __construct($group = NULL, $album = NULL, $photo = NULL, $size = NULL) {
        $this->group = $group;
        $this->album = $album;
        $this->photo = $photo;
        $this->size = $size;
    }

    function get_thumbnail() {

        $original = str_replace('//', '/', DIR_ORIGINALS.'/'.$this->group.'/'.$this->album.'/'.$this->photo);

        $thumbnail = str_replace('//', '/', DIR_THUMBNAILS.'/'.$this->group.'/'.$this->album.'/'.$this->size.'/'.$this->photo);
        $thumbnail = rtrim($thumbnail, array_pop(explode('.', $thumbnail))) .'jpg';

        if(file_exists($original)) {

            if(!file_exists($thumbnail)) {
                $this->mkdir($size);
                switch($this->size) {
                    case 'small':
                        $this->resize($original, $thumbnail, 100, TRUE);
                        break;
                    case 'medium':
                        $this->resize($original, $thumbnail, 800);
                        break;
                    case 'large':
                        $this->resize($original, $thumbnail, 1600);
                        break;
                }
            }

            header('Content-Type: image/jpeg');
            fpassthru(fopen($thumbnail, 'r'));

        }

    }

    function mkdir() {
        if(!file_exists(str_replace('//', '/', DIR_THUMBNAILS))) mkdir(str_replace('//', '/', DIR_THUMBNAILS));
        if(!file_exists(str_replace('//', '/', DIR_THUMBNAILS.'/'.$this->group))) mkdir(str_replace('//', '/', DIR_THUMBNAILS.'/'.$this->group));
        if(!file_exists(str_replace('//', '/', DIR_THUMBNAILS.'/'.$this->group.'/'.$this->album))) mkdir(str_replace('//', '/', DIR_THUMBNAILS.'/'.$this->group.'/'.$this->album));
        if(!file_exists(str_replace('//', '/', DIR_THUMBNAILS.'/'.$this->group.'/'.$this->album.'/'.$this->size))) mkdir(str_replace('//', '/', DIR_THUMBNAILS.'/'.$this->group.'/'.$this->album.'/'.$this->size));
    }

    function resize($original, $target, $size, $square = FALSE) {

        list($oldwidth, $oldheight) = getimagesize($original);

        if($square) {
            $width = $size;
            $height = $size;
            if ($oldwidth > $oldheight) {
                $x = ceil(($oldwidth - $oldheight) / 2);
                $y = 0;
                $owidth = $oldheight;
                $oheight = $oldheight;
            } else {
                $x = 0;
                $y = ceil(($oldheight - $oldwidth) / 2);
                $owidth = $oldwidth;
                $oheight = $oldwidth;
            }
        } else {
            if ($oldwidth > $oldheight) {
                $width = $size;
                $height = ($oldheight / $oldwidth) * $size;
            } else {
                $width = ($oldwidth / $oldheight) * $size;
                $height = $size;
            }
            $x = 0;
            $y = 0;
            $owidth = $oldwidth;
            $oheight = $oldheight;
        }
        $width = ($width>$oldwidth) ? $oldwidth : $width;
        $height = ($height>$oldheight) ? $oldheight : $height;

        switch(strtolower(array_pop(explode('.', $original)))) {
            case 'jpg':
            case 'jpeg':
                $src = imagecreatefromjpeg($original);
                break;
            case 'png':
                $src = imagecreatefrompng($original);
                break;
        }

        $tmp = imagecreatetruecolor($width, $height);

        imagecopyresampled($tmp, $src, 0, 0, $x, $y, $width, $height, $owidth, $oheight);

        imageinterlace($tmp, true);

        if (file_exists($target)) unlink($target);
        imagejpeg($tmp, $target, 85);

    }

}



Class HTML {

    function __construct($group = NULL, $album = NULL, $photo = NULL, $size = NULL) {
        $this->group = $group;
        $this->album = $album;
        $this->photo = $photo;
        $this->size = $size;
    }

    function get($data) {

        if($this->photo) {
            if($this->size == 'fullscreen') {
                echo $this->get_page($this->photo, $this->get_fullscreen($data));
            } else {
                echo $this->get_page($this->photo, $this->get_photo($data));
            }
        } elseif($this->album) {
            echo $this->get_page($data[$this->group]['albums'][$this->album]['name'], $this->get_album($data));
        } else {
            echo $this->get_page($data[$this->group]['name'], $this->get_group($data));
        }

        //echo '<pre>';
        //print_r($data);
        //echo '</pre>';

    }

    function get_group($data) {

        $result = '';
        $albumno = 0;
        $photocount = 0;
        $size = 0;

        if($this->group) {
            if(!is_array($data[$this->group])) header('Location: '. PAGE_URL);
            $groups[$this->group] = $data[$this->group];
        } else {
            $groups = $data;
        }


        foreach($groups as $group) {
            $size += $group['size'];

            $result .= '<h1>';
            $result .= ($this->group) ? '<a href="'. PAGE_URL .'" title="Back to '. PAGE_TITLE .'">'. $group['name'] .'</a>' : '<a href="'. $group['url'] .'">'. $group['name'] .'</a>';
            $result .= '</h1>'."\n";

            foreach($group['albums'] as $album) {

                $albumno += 1;

                $result .= '<img id="img_'. $albumno .'" class="img_small" src="'. $album['thumbnail'] .'/small" width="100px" height="100px" usemap="#skimming_'. $albumno .'" alt="'. $album['url'] .'" />'."\n";

                $photocount += count($album['photos']);

                $counter = 0;
                $img_width = 100;
                $count = count($album['photos']);
                $step = ($img_width/$count);
                $area = array();

                foreach($album['photos'] as $photo) {
                    $counter += $step;
                    $area[round($counter-$step)] = '<area class="area_'. $albumno .'" shape="rect" coords="'. round($counter-$step) .',0,'. round($counter) .',100" alt="'. $photo['name'] .'" />'."\n";
                }
                $result .= '<map id="skimming_'. $albumno .'" name="skimming_'. $albumno .'">'."\n";
                $result .= implode('', $area);
                $result .= '</map>'."\n";
                $result .= '
        <script type="text/javascript">
            $(document).ready(function(){
                $(".area_'. $albumno .'").hover(function() {
                    var alt = $(this).attr(\'alt\');
                    $("#img_'. $albumno .'").attr("src", "'. $group['name'] .'/'. $album['name'] .'/" + alt + "/small");
                    return false;
                });
                $(".area_'. $albumno .'").attr("href", "'. $album['url'] .'");
                $(".area_'. $albumno .'").attr("title", "'. $album['name'] .'");
            });
        </script>
                '."\n";

            }

        }

        if(SHOW_QUOTA == TRUE) {
            $quota_array = array_values(explode(' ', preg_replace('/\s\s+/', ' ', trim(exec('/usr/bin/quota')))));
            $quota = round(($quota_array[2]-$quota_array[1])/1024/1024, 2) .'GB free';
        }

        $result .= '<p style="margin-top: 10px;" title="'. $quota .'">'. $photocount .' photos, '. round($size/1024/1024/1024, 2) .'GB</p>';

                $result .= '
        <script type="text/javascript">

            var currentSelection = -1;
            var currentUrl = "";


            $(document).keydown(function(e) {
                if((e.charCode == 13 || e.keyCode == 13) && currentUrl != "") top.location.href = currentUrl;
                if(e.charCode == 27 || e.keyCode == 27) top.location.href = "'. PAGE_URL. '";
                if(e.charCode == 37 || e.keyCode == 37) navigate("up");
                if(e.charCode == 39 || e.keyCode == 39) navigate("down");
            });

            function navigate(direction) {
                // Check if any of the menu items is selected
                if($(".img_small").size() == 0) {
                    currentSelection = -1;
                }
                if(direction == \'up\' && currentSelection != -1) {
                    if(currentSelection != 0) {
                    currentSelection--;
                }
                } else if (direction == \'down\') {
                    if(currentSelection != $(".img_small").size() -1) {
                        currentSelection++;
                    }
                }
                setSelected(currentSelection);
            }

            function setSelected(menuitem) {
                $(".img_small").removeClass("img_selected");
                $(".img_small").eq(menuitem).addClass("img_selected");
                currentUrl = $(".img_small").eq(menuitem).attr("alt");
            }

            // Add data to let the hover know which index they have
            for(var i = 0; i < $(".img_small").size(); i++) {
                $(".img_small").eq(i).data("number", i);
            }

            // Simulate the "hover" effect with the mouse
            $(".img_small").hover(
                function () {
                    currentSelection = $(this).data("number");
                    setSelected(currentSelection);
                }, function() {
                    $(".img_small").removeClass("img_selected");
                    currentUrl = "";
                }
            );

        </script>
                '."\n";

        return $result;

    }

    function get_album($data) {

        $result = '';

        if($this->album) {
            if(!is_array($data[$this->group]['albums'][$this->album])) header('Location: '. PAGE_URL .'/'. $this->group);
            $albums[$this->group]['albums'][$this->album] = $data[$this->group]['albums'][$this->album];
        } else {
            $albums[$this->group]['albums'] = $data[$this->group]['albums'];
        }

        $group_name = $data[$this->group]['name'];
        $group_url = $data[$this->group]['url'];

        foreach($albums[$this->group]['albums'] as $album) {
            $result .= '<h1>';
            $result .= '<a href="'. $group_url .'" title="Back to '. $group_name .'">'.$album['name'] .'</a>';
            $result .= '</h1>'."\n";

            foreach($album['photos'] as $photo) {
                $result .= '<a href="'. $photo['url'] .'"><img class="img_small" src="'. $photo['thumbnail'] .'/small" width="100px" height="100px" alt="" /></a>'."\n";
            }

            if(($album['max_date']-$album['min_date']) > 86400) {
                $date = date('d.m.Y', $album['min_date']) .' - '. date('d.m.Y', $album['max_date']);
            } else {
                $date = date('d.m.Y H:i', $album['min_date']) .' - '. date('H:i', $album['max_date']);
            }
            $size = ($album['size']<1073741824) ? round($album['size']/1024/1024, 1) .'MB' : round($album['size']/1024/1024/1024, 1) .'GB';
            $result .=  '<p>'. $date .'<br />'. count($album['photos']) .' photos, '. $size .'</p>';

                $result .= '
        <script type="text/javascript">

            var currentSelection = -1;
            var currentUrl = "";

            // Register keypress events on the whole document
            $(document).keydown(function(e) {
                if((e.charCode == 13 || e.keyCode == 13) && currentUrl != "") top.location.href = currentUrl;
                if(e.charCode == 27 || e.keyCode == 27) top.location.href = "'. $group_url .'";
                if(e.charCode == 37 || e.keyCode == 37) navigate("up");
                if(e.charCode == 39 || e.keyCode == 39) navigate("down");

            });

            function navigate(direction) {
                // Check if any of the menu items is selected
                if($(".img_small").size() == 0) {
                    currentSelection = -1;
                }
                if(direction == "up" && currentSelection != -1) {
                    if(currentSelection != 0) {
                    currentSelection--;
                }
                } else if (direction == "down") {
                    if(currentSelection != $(".img_small").size() -1) {
                        currentSelection++;
                    }
                }
                setSelected(currentSelection);
            }

            function setSelected(menuitem) {
                $(".img_small").removeClass("img_selected");
                $(".img_small").eq(menuitem).addClass("img_selected");
                currentUrl = $(".img_small").eq(menuitem).parent().attr("href");
            }

            // Add data to let the hover know which index they have
            for(var i = 0; i < $(".img_small").size(); i++) {
                $(".img_small").eq(i).data("number", i);
            }

            // Simulate the "hover" effect with the mouse
            $(".img_small").hover(
                function () {
                    currentSelection = $(this).data("number");
                    setSelected(currentSelection);
                }, function() {
                    $(".img_small").removeClass("img_selected");
                    currentUrl = "";
                }
            );
        </script>
                '."\n";
        }

        return $result;

    }

    function get_photo($data) {

        $result = '';
        $rownum = 0;
        $rowcount = count($data[$this->group]['albums'][$this->album]['photos']);
        $album_name = $data[$this->group]['albums'][$this->album]['name'];
        $album_url = $data[$this->group]['albums'][$this->album]['url'];

        foreach($data[$this->group]['albums'][$this->album]['photos'] as $photo) {

            $rownum += 1;

            if(substr(strrchr($photo['url'], '/'), 1) == $this->photo) {

                if($rownum != 1 AND $rownum != $rowcount) {
                    prev($data[$this->group]['albums'][$this->album]['photos']);
                    $previous = prev($data[$this->group]['albums'][$this->album]['photos']);
                    next($data[$this->group]['albums'][$this->album]['photos']);
                    $next = next($data[$this->group]['albums'][$this->album]['photos']);
                }
                if($rownum == 1) {
                    $next = current($data[$this->group]['albums'][$this->album]['photos']);
                }
                if($rownum == $rowcount) {
                    end($data[$this->group]['albums'][$this->album]['photos']);
                    $previous = prev($data[$this->group]['albums'][$this->album]['photos']);
                }

                //print_r($photo);

                $exif = array();

                if($photo['Date']) $exif[] = date('d.m.Y H:i', $photo['Date']);
                if($photo['Model']) $exif[] = $photo['Model'];
                if($photo['ExposureTime']) $exif[] = $photo['ExposureTime'] .'sec';
                if($photo['Aperture']) $exif[] = 'f'. $photo['Aperture'];
                if($photo['FocalLength']) $exif[] = $photo['FocalLength'] .'mm';
                if($photo['ISO']) $exif[] = 'ISO '. $photo['ISO'];
                if($photo['Latitude'] AND $photo['Longitude']) $exif[] = '<a href="http://maps.google.com/?q='.$photo['name'].'@'.substr($photo['Latitude'], 1).','.substr($photo['Longitude'], 1).'&z=12&t=p&output=embed" target="_blank">'. $photo['Latitude'] .', '. $photo['Longitude'] .'</a>';

                $result .= '<h1><span style="float:right;">'. $rownum .'/'. $rowcount .'</span>';
                $result .= '<a href="'. $album_url .'" title="Back to '. $album_name .'">'. $album_name .'</a>';
                $result .= '</h1>'."\n";

                //$result .= '<div id="hidden" style="display:none;">'."\n";
                $result .= '<p>';
                $result .= '<a href="'. $photo['url'].'/fullscreen"><img id="big_image" src="'. $photo['thumbnail'] .'/medium" alt="'. $photo['name'] .'" /></a><br />';
                $result .= '</p>'."\n";

                $result .= '<p>';
                $result .= implode(' : ', $exif);
                $result .= '</p>'."\n";

                if($previous) {
                    $result .= '<a style="position:fixed; left:0px; bottom:0px;" href="'. $previous['url'] .'"><img class="img_small" src="'. $previous['thumbnail'] .'/small" width="50px" height="50px" alt="" /></a>'."\n";
                    $result .= '<img style="display:none;" src="'. $previous['thumbnail'] .'/medium" width="0px" height="0px" alt="" />'."\n";
                    $previous_js = 'if(e.charCode == 37 || e.keyCode == 37) top.location.href = "'. $previous['url'] .'";';
                }
                if($next) {
                    $result .= '<a style="position:fixed; right:0px; bottom:0px;" href="'. $next['url'] .'"><img class="img_small" src="'. $next['thumbnail'] .'/small" width="50px" height="50px" alt="" /></a>'."\n";
                    $result .= '<img style="display:none;" src="'. $next['thumbnail'] .'/medium" width="0px" height="0px" alt="" />'."\n";
                    $next_js = 'if(e.charCode == 39 || e.keyCode == 39) top.location.href = "'. $next['url'] .'";';
                }

                //$result .= '</div>'."\n";
                $result .= '
        <script type="text/javascript">
            $(document).keydown(function(e) {
                '. $previous_js .';
                '. $next_js .';
                if(e.charCode == 27 || e.keyCode == 27) top.location.href = "'. $album_url .'";
            });

            $("#big_image").load(function(){
                //$("#hidden").fadeIn("slow");
            });
        </script>
                '."\n";
            }
        }

        if(!$result) header('Location: '. PAGE_URL  .'/'. $this->group .'/'. $this->album);
        return $result;

    }

    function get_fullscreen($data) {

        $result = '';
        $rownum = 0;
        $rowcount = count($data[$this->group]['albums'][$this->album]['photos']);

        foreach($data[$this->group]['albums'][$this->album]['photos'] as $photo) {

            $rownum += 1;

            if(substr(strrchr($photo['url'], '/'), 1) == $this->photo) {

                if($rownum != 1 AND $rownum != $rowcount) {
                    prev($data[$this->group]['albums'][$this->album]['photos']);
                    $previous = prev($data[$this->group]['albums'][$this->album]['photos']);
                    next($data[$this->group]['albums'][$this->album]['photos']);
                    $next = next($data[$this->group]['albums'][$this->album]['photos']);
                }
                if($rownum == 1) {
                    $next = current($data[$this->group]['albums'][$this->album]['photos']);
                }
                if($rownum == $rowcount) {
                    end($data[$this->group]['albums'][$this->album]['photos']);
                    $previous = prev($data[$this->group]['albums'][$this->album]['photos']);
                }

                if($previous) $previous_photo = $previous['url'];
                if($next) $next_photo = $next['url'];

                $result .= '<a href="'. $photo['url'].'"><img id="big_image" style="display:none; position:fixed;" src="'. $photo['thumbnail'] .'/large" alt="'. $photo['name'] .'" /></a>'."\n";

                if($previous_photo) {
                    $result .= '<img style="display:none;" src="'. $previous['thumbnail'] .'/large" width="0px" height="0px" alt="" />'."\n";
                    $previous_js = 'if(e.charCode == 37 || e.keyCode == 37) $("#big_image").fadeOut("slow", function () {top.location.href = "'. $previous['url'] .'/fullscreen"});';
                }
                if($next_photo) {
                    $result .= '<img style="display:none;" src="'. $next['thumbnail'] .'/large" width="0px" height="0px" alt="" />'."\n";
                    $next_js = 'if(e.charCode == 39 || e.keyCode == 39) $("#big_image").fadeOut("slow", function () {top.location.href = "'. $next['url'] .'/fullscreen"});';
                }

                $result .= '
        <script type="text/javascript">
            $(document).keydown(function(e) {
                '. $previous_js .';
                '. $next_js .';
                if(e.charCode == 27 || e.keyCode == 27) top.location.href = "'. $photo['url'] .'";
            });

            $(window).resize(function(){
                resizeme();
            });

            function resizeme() {
                $("html, body, #content, #big_image").css("padding", "0px");
                $("html, body, #content, #big_image").css("margin", "0px");
                $("html, body, #content, #big_image").css("background", "#000000");
                $("html, body, #content").css("overflow", "hidden");
                $("#big_image").css("border", "none");

                $("#big_image").attr("height", $(window).height());
                $("#big_image").attr("width", $(window).height() / '. ($photo['Height'] / $photo['Width']) .');
                $("#big_image").css("left", ($(window).width() - $("#big_image").width())/2 +"px");
                $("#big_image").css("top", "0px");

                if($("#big_image").width() > $(window).width()) {
                    $("#big_image").attr("width", $(window).width());
                    $("#big_image").attr("height", $(window).width() / '. ($photo['Width'] / $photo['Height']) .');
                    $("#big_image").css("left", "0px");
                    $("#big_image").css("top", ($(window).height() - $("#big_image").height())/2 +"px");
                }
            }

            $("#big_image").load(function(){
                resizeme();
                $("#big_image").fadeIn("slow");
            });

            resizeme();
        </script>
                '."\n";
            }
        }

        if(!$result) header('Location: '. PAGE_URL .'/'. $this->group .'/'. $this->album);
        return $result;

    }

    function get_page($title, $body) {

        $title = $title ? $title : PAGE_TITLE;

        if(strlen(ANALYTICS_CODE) > 1) $analytics = '
        <script type="text/javascript">
            var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
            document.write(unescape("%3Cscript src=\'" + gaJsHost + "google-analytics.com/ga.js\' type=\'text/javascript\'%3E%3C/script%3E"));
        </script>
        <script type="text/javascript">
            try {
            var pageTracker = _gat._getTracker("'. ANALYTICS_CODE .'");
            pageTracker._trackPageview();
            } catch(err) {}
        </script>
        ';

        $result = '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head profile="http://gmpg.org/xfn/11">
        <meta http-equiv="content-type" content="text/html; charset=utf-8" />
        <title>'. $title .'</title>
        <script src="http://www.google.com/jsapi" type="text/javascript"></script>
        <script type="text/javascript">
            google.load("jquery", "1");
        </script>
        <style type="text/css">
            html, body {
                font-family: "Lucida Grande", tahoma, Verdana, Arial, sans-serif;
                font-size: 10px;
                color: #8B8B8B;
                background: #F0F0F0;
                margin: 0px;
                padding: 0px 0px 10px 0px;
            }
            h1 {
                text-shadow: 1px 1px 2px #91A0CD;
                color: #2041A2;
                display: block;
                margin: 0px;
                padding: 20px 2px 0px 2px;
                font-size: 14px;
                clear: both;
            }
            img {
                margin: 3px;
                padding: 3px;
                border: solid 1px #a2a2a2;
                background: #FFFFFF;
            }
            .img_small {
                float: left;
            }
            .img_selected {
                border: solid 2px #2041A2;
                padding: 2px;
            }
            p {
                margin: 0px;
                padding: 0px;
                text-align: center;
                clear: both;
            }
            #content {
                margin: 0px auto 0px auto;
                width: '. PAGE_WIDTH .';
            }
            a, a:visited, a:active {
                color: #8B8B8B;
                text-decoration: none;
            }
            a:hover {
                text-decoration: underline;
            }
            h1 a, h1 a:visited, h1 a:active {
                color: #2041A2;
                text-decoration: none;
            }
            h1 a:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    <body>

        <div id="content">
'. $body .'
        </div>
'. $analytics .'
    </body>
</html>
        ';

        return $result;

    }

}

Class Download {

    function __construct($group = NULL, $album = NULL, $photo = NULL) {
        $this->group = $group;
        $this->album = $album;
        $this->photo = $photo;
    }

    function album() {
        $dir = str_replace('//', '/', DIR_ORIGINALS.'/'.$this->group.'/'.$this->album);

        $zipfilename = './'. $this->group.'_'.$this->album .'.zip';

        set_time_limit(60);

        $zip = new ZipArchive();
        $zip->open($zipfilename, ZIPARCHIVE::OVERWRITE);

        if(is_dir($dir)) {
            $handle = opendir($dir);
            while (false !== ($file = readdir($handle))) {
                if ($file != "." AND $file != ".." AND is_file($dir.'/'.$file)) {
                    $zip->addFile(realpath($dir.'/'.$file), $file);
                }
            }
            closedir($handle);
        }

        $zip->close();

        $filesize = filesize($zipfilename);

        header('Content-Type: archive/zip');
        header('Content-Disposition: attachment; filename='. $this->album .'.zip');
        header('Content-Length: '. filesize($zipfilename));

        echo fpassthru(fopen($zipfilename, 'r'));

    }

    function photo() {

        $file = str_replace('//', '/', DIR_ORIGINALS.'/'.$this->group.'/'.$this->album.'/'.$this->photo);

        header('Content-Type: image/jpeg');
        header('Content-Disposition: attachment; filename='. $this->photo);
        header('Content-Length: '. filesize($file));

        echo fpassthru(fopen($file, 'r'));

    }
}

//if($_SERVER['REQUEST_URI'])        $path = $_SERVER['REQUEST_URI'];
//if($_SERVER['QUERY_STRING'])    $path = $_SERVER['QUERY_STRING'];
if($_SERVER['PATH_INFO'])        $path = $_SERVER['PATH_INFO'];
if($_SERVER['ORIG_PATH_INFO'])    $path = $_SERVER['ORIG_PATH_INFO'];

$path = explode('/', trim(str_replace($_SERVER['SCRIPT_NAME'], '', $path), '/'));
$group = $path[0];
$album = $path[1];
$photo = $path[2];
$size = $path[3];


if(strtolower($path[2]) == 'download') {
    $download = new Download($group, $album);
    $download->album();
} else {
    switch($path[3]) {
        case 'small':
            $thumbs = new Thumbs($group, $album, $photo, $size);
            $thumbs->get_thumbnail();
            break;
        case 'medium':
            $thumbs = new Thumbs($group, $album, $photo, $size);
            $thumbs->get_thumbnail();
            break;
        case 'large':
            $thumbs = new Thumbs($group, $album, $photo, $size);
            $thumbs->get_thumbnail();
            break;
        case 'download':
            $download = new Download($group, $album, $photo);
            $download->photo();
            break;
        default:
            $photos = new Photos($group, $album, $photo);
            $html = new HTML($group, $album, $photo, $size);
            $html->get($photos->get());
            break;
    }
}

?>
