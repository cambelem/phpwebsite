<?php

/**
 * @version $Id$
 * @author Matthew McNaney <mcnaney at gmail dot com>
 */

PHPWS_Core::requireConfig('filecabinet');
PHPWS_Core::initModClass('filecabinet', 'File_Common.php');

define('GENERIC_VIDEO_ICON', 'images/mod/filecabinet/video_generic.png');

class PHPWS_Multimedia extends File_Common {
    var $width  = 0;
    var $height = 0;

    var $_classtype       = 'multimedia';

    function PHPWS_Multimedia($id=0)
    {
        $this->loadAllowedTypes();
        //        $this->loadDefaultDimensions();
        $this->setMaxSize(PHPWS_Settings::get('filecabinet', 'max_multimedia_size'));

        if (empty($id)) {
            return;
        }

        $this->id = (int)$id;
        $result = $this->init();
        if (PEAR::isError($result)) {
            $this->id = 0;
            $this->_errors[] = $result;
        } elseif (empty($result)) {
            $this->id = 0;
            $this->_errors[] = PHPWS_Error::get(FC_MULTIMEDIA_NOT_FOUND, 'filecabinet', 'PHPWS_Multimedia');
        }
    }

    function init()
    {
        if (empty($this->id)) {
            return false;
        }

        $db = new PHPWS_DB('multimedia');
        return $db->loadObject($this);
    }


    function loadAllowedTypes()
    {
        $this->_allowed_types = unserialize(ALLOWED_MULTIMEDIA_TYPES);
    }

    function getID3()
    {
        require_once PHPWS_SOURCE_DIR . 'lib/getid3/getid3/getid3.php';
        $getID3 = new getID3;

        // File to get info from
        $file_location = $this->getPath();

        // Get information from the file
        $fileinfo = $getID3->analyze($file_location);
        getid3_lib::CopyTagsToComments($fileinfo);
        return $fileinfo;
    }

    function loadVideoDimensions()
    {
        $fileinfo = $this->getID3();

        if (isset($fileinfo['video']['resolution_x'])) {
            $this->width = & $fileinfo['video']['resolution_x'];
            $this->height = & $fileinfo['video']['resolution_y'];
        } elseif (isset($fileinfo['video']['streams'][2]['resolution_x'])) {
            $this->width = & $fileinfo['video']['streams'][2]['resolution_x'];
            $this->height = & $fileinfo['video']['streams'][2]['resolution_y'];
        } else {
            $this->loadDefaultDimensions();
        }
    }

    function loadDefaultDimensions()
    {
        $this->width = PHPWS_Settings::get('filecabinet', 'default_mm_width');
        $this->height = PHPWS_Settings::get('filecabinet', 'default_mm_height');
    }


    function allowMultimediaType($type)
    {
        $mm = new PHPWS_Multimedia;
        return $mm->allowType($type);
    }

    function thumbnailDirectory()
    {
        return $this->file_directory . 'tn/';
    }

    function thumbnailPath()
    {
        if ($this->isVideo()) {
            $last_dot = strrpos($this->file_name, '.');
            $thumbnail_file = substr($this->file_name, 0, $last_dot) . '.jpg';
            
            $directory = $this->thumbnailDirectory() . $thumbnail_file;
            
            if (is_file($directory)) {
                return $directory;
            } else {
                return GENERIC_VIDEO_ICON;
            }
        } else {
            return 'images/mod/filecabinet/audio.png';
        }
    }


    function rowTags()
    {
        $links[] = PHPWS_Text::secureLink(dgettext('filecabinet', 'Clip'), 'filecabinet',
                                          array('aop'=>'clip_multimedia',
                                                'multimedia_id' => $this->id));
        
        if (Current_User::allow('filecabinet', 'edit_folder', $this->folder_id)) {
            $links[] = $this->editLink();
            $links[] = $this->deleteLink();
        }

        $tpl['ACTION'] = implode(' | ', $links);
        $tpl['SIZE'] = $this->getSize(TRUE);
        $tpl['FILE_NAME'] = $this->file_name;
        $tpl['THUMBNAIL'] = $this->getJSView(true);
        $tpl['TITLE']     = $this->getJSView(false, $this->title);

        if ($this->isVideo()) {
            $tpl['DIMENSIONS'] = sprintf('%s x %s', $this->width, $this->height);
        }

        return $tpl;
    }

    function popupAddress()
    {
        if (MOD_REWRITE_ENABLED) {
            return sprintf('filecabinet/%s/multimedia', $this->id);
        } else {
            return sprintf('index.php?module=filecabinet&amp;page=multimedia&amp;id=%s', $this->id);
        }

    }


    function popupSize()
    {
        static $sizes = null;

        $dimensions = array(FC_MAX_MULTIMEDIA_POPUP_WIDTH, FC_MAX_MULTIMEDIA_POPUP_HEIGHT);
        if (isset($sizes[$this->id])) {
            return $sizes[$this->id];
        }
        $padded_width = $this->width + 40;
        $padded_height = $this->height + 120;

        if (!empty($this->description)) {
            $padded_height += round( (strlen(strip_tags($this->description)) / ($this->width / 12)) * 12);
        }

        if ( $padded_width < FC_MAX_MULTIMEDIA_POPUP_WIDTH && $padded_height < FC_MAX_MULTIMEDIA_POPUP_HEIGHT ) {
            $final_width = $final_height = 0;
            
            for ($lmt = 250; $lmt += 50; $lmt < 1300) {
                if (!$final_width && ($padded_width + 25) < $lmt) {
                    $final_width = $lmt;
                }
                
                if (!$final_height && ($padded_height + 25) < $lmt ) {
                    $final_height = $lmt;
                }
                
                if ($final_width && $final_height) {
                    $dimensions = array($final_width, $final_height);
                    break;
                }
            }
        }
        $sizes[$this->id] = $dimensions;
        return $dimensions;
    }

    function getJSView($thumbnail=false, $link_override=null)
    {
        if ($link_override) {
            $values['label'] = $link_override;
        } else {
            if ($thumbnail) {
                $values['label'] = $this->getThumbnail();
            } else {
                $values['label'] = sprintf('<img src="images/mod/filecabinet/viewmag+.png" width="16" height="16" title="%s" />',
                                           dgettext('filecabinet', 'View full image'));
            }
        }

        $size = $this->popupSize();
        $values['address']     = $this->popupAddress();
        $values['width']       = $size[0];
        $values['height']      = $size[1];
        $values['window_name'] = 'multimedia_view';
        return Layout::getJavascript('open_window', $values);
    }


    function editLink($icon=false)
    {
        $vars['aop'] = 'upload_multimedia_form';
        $vars['multimedia_id'] = $this->id;
        $vars['folder_id'] = $this->folder_id;
        
        $jsvars['width'] = 550;
        $jsvars['height'] = 580;
        $jsvars['address'] = PHPWS_Text::linkAddress('filecabinet', $vars, true);
        $jsvars['window_name'] = 'edit_link';
        
        if ($icon) {
            $jsvars['label'] =sprintf('<img src="images/mod/filecabinet/edit.png" width="16" height="16" title="%s" />', dgettext('filecabinet', 'Edit multimedia file'));
        } else {
            $jsvars['label'] = dgettext('filecabinet', 'Edit');
        }
        return javascript('open_window', $jsvars);

    }

    function deleteLink()
    {
        $vars['aop'] = 'delete_multimedia';
        $vars['multimedia_id'] = $this->id;
        $vars['folder_id'] = $this->folder_id;
        
        $js['QUESTION'] = dgettext('filecabinet', 'Are you sure you want to delete this multimedia file?');
        $js['ADDRESS']  = PHPWS_Text::linkAddress('filecabinet', $vars, true);
        $js['LINK']     = dgettext('filecabinet', 'Delete');
        return javascript('confirm', $js);
    }
    
    function getTag()
    {
        $filter_tpl = $this->getFilter();

        if ($this->isVideo()) {
            $tpl['WIDTH']  = $this->width;
            $tpl['HEIGHT'] = $this->height;
        }
        $tpl['FILE_PATH'] = PHPWS_Core::getHomeHttp() . $this->getPath();

        // check for filter file
        $filter = 'templates/filecabinet/' . str_replace('.tpl', '', $filter_tpl) . '/filter.php';

        if (is_file($filter)) {
            include $filter;
        }

        return PHPWS_Template::process($tpl, 'filecabinet', $filter_tpl);
    }

    function getFilter()
    {

        switch ($this->file_type) {
        case 'application/x-extension-flv':
        case 'video/x-flv':
        case 'application/x-flash-video':
            return 'filters/flash.tpl';
            break;

        case 'audio/mpeg':
            return 'filters/mp3.tpl';
            break;

        case 'video/quicktime':
            return 'filters/quicktime.tpl';
            break;

        case 'video/mpeg':
        case 'video/x-msvideo':
        case 'video/x-ms-wmv':
            return 'filters/windows.tpl';
            break;
        }
    }


    function getThumbnail($css_id=null)
    {
        if (empty($css_id)) {
            $css_id = $this->id;
        }

        return sprintf('<img src="%s" title="%s" id="multimedia-thumbnail-%s" />',
                       $this->thumbnailPath(),
                       $this->title, $css_id);
    }

    function makeThumbnail()
    {
        $thumbnail_directory = $this->file_directory . 'tn/';

        if (!is_writable($thumbnail_directory)) {
            return;
        }

        $last_dot = strrpos($this->file_name, '.');
        $thumbnail_file = substr($this->file_name, 0, $last_dot) . '.jpg';

        // in case the above fails
        $thumbnail_png = substr($this->file_name, 0, $last_dot) . '.png';
        

        if (!PHPWS_Settings::get('filecabinet', 'use_ffmpeg')) {
            copy('images/mod/filecabinet/video_generic.png', $thumbnail_directory . $thumbnail_png);
            return;
        }

        $ffmpeg_directory = PHPWS_Settings::get('filecabinet', 'ffmpeg_directory');
        if (!is_file($ffmpeg_directory . 'ffmpeg')) {
            copy('images/mod/filecabinet/video_generic.png', $thumbnail_directory . $thumbnail_png);
            return;
        }

        $tmp_name = mt_rand();

        /**define('FC_MAX_IMAGE_POPUP_WIDTH', 1024);
define('FC_MAX_IMAGE_POPUP_HEIGHT', 768);

         * -i        filename
         * -an       disable audio
         * -ss       seek to position
         * -r        frame rate
         * -vframes  number of video frames to record
         * -y        overwrite output files
         * -f        force format
         */


        $command = sprintf('%sffmpeg -i %s -an -s 160x120 -ss 00:00:05 -r 1 -vframes 1 -y -f mjpeg %s%s',
                           $ffmpeg_directory, $this->getPath(), $thumbnail_directory, $thumbnail_file);

        $result = system($command);
        return true;
    }
    
    function delete()
    {
        $db = new PHPWS_DB('multimedia');
        $db->addWhere('id', $this->id);
        $result = $db->delete();
        if (PEAR::isError($result)) {
            return $result;
        }
        
        $path = $this->getPath();

        if (!@unlink($path)) {
            PHPWS_Error::log(FC_COULD_NOT_DELETE, 'filecabinet', 'PHPWS_Multimedia::delete', $path);
        }

        if ($this->isVideo()) {
            $tn = $this->thumbnailPath();
            if ($tn == GENERIC_VIDEO_ICON) {
                return true;
            }       
            if (!@unlink($tn)) {
                PHPWS_Error::log(FC_COULD_NOT_DELETE, 'filecabinet', 'PHPWS_Multimedia::delete', $path);
            }
        }

        return true;
    }

    function save($write=true, $thumbnail=true)
    {
        if (empty($this->file_directory)) {
            if ($this->folder_id) {
                $folder = new Folder($_POST['folder_id']);
                if ($folder->id) {
                    $this->setDirectory($folder->getFullDirectory());
                } else {
                    return PHPWS_Error::get(FC_MISSING_FOLDER, 'filecabinet', 'PHPWS_Multimedia::save');
                }
            } else {
                return PHPWS_Error::get(FC_DIRECTORY_NOT_SET, 'filecabinet', 'PHPWS_Multimedia::save');
            }
        }

        if (!is_writable($this->file_directory)) {
            return PHPWS_Error::get(FC_BAD_DIRECTORY, 'filecabinet', 'PHPWS_Multimedia::save', $this->file_directory);
        }

        if ($write) {
            $result = $this->write();
            if (PEAR::isError($result)) {
                return $result;
            }
        }

        if ($this->isVideo()) {
            if (!$this->width && !$this->height) {
                $this->loadVideoDimensions();
            }
            if ($thumbnail) {
                $this->makeThumbnail();
            }
        } else {
            $this->height = 0;
            $this->width = 0;
        }

        $db = new PHPWS_DB('multimedia');
        return $db->saveObject($this);
    }
}
?>