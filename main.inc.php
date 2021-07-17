<?php
/*
Plugin Name: Automatic Size
Version: auto
Description: Automatically selects the biggest photo size
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=
Author: plg
Author URI: http://le-gall.net/pierrick
*/

if (!defined('PHPWG_ROOT_PATH'))
{
  die('Hacking attempt!');
}

define('ASIZE_PATH' , PHPWG_PLUGINS_PATH.basename(dirname(__FILE__)).'/');

add_event_handler(
  'render_element_content',
  'asize_picture_content',
  EVENT_HANDLER_PRIORITY_NEUTRAL-1,
  2
  );

function asize_picture_content($content, $element_info)
{
  global $conf;

  $asize_conf_default_values = array(
    'automatic_size_width_margin' => 12,
    'automatic_size_height_margin' => 40,
    'automatic_size_min_ratio' => 0.2,
    'automatic_size_max_ratio' => 5,
	// TFE, 20210620: add option to disable Automatic Size for a list of file types (e.g. movies and tracks)
    'automatic_size_ignore_file_types' => true,
    'automatic_size_ignore_file_type_list' => array('gpx', 'kml', 'mpg', 'ogg', 'mp4'),
    );

  foreach ($asize_conf_default_values as $key => $value)
  {
    if (!isset($conf[$key]))
    {
      $conf[$key] = $value;
    }
  }

  if ( !empty($content) )
  {// someone hooked us - so we skip;
    return $content;
  }
  
  $do_automatic_resize = true;
  // TFE, 20210620: check against ignored file types
  if ($conf['automatic_size_ignore_file_types']) {
	  if (isset($element_info['file']) && in_array(get_extension($element_info['file']), $conf['automatic_size_ignore_file_type_list'])) {
	    // we don't want automatic resize for this file type...
		//print_r('no automatic resize, please');
	    $do_automatic_resize = false;
	  }
  }

  if (isset($_COOKIE['picture_deriv']))
  {
    if ( array_key_exists($_COOKIE['picture_deriv'], ImageStdParams::get_defined_type_map()) )
    {
      pwg_set_session_var('picture_deriv', $_COOKIE['picture_deriv']);
    }
    setcookie('picture_deriv', false, 0, cookie_path() );
  }
  $deriv_type = pwg_get_session_var('picture_deriv', $conf['derivative_default_size']);
  $selected_derivative = $element_info['derivatives'][$deriv_type];

  $unique_derivatives = array();
  $show_original = isset($element_info['element_url']);
  $added = array();
  foreach($element_info['derivatives'] as $type => $derivative)
  {
    if ($type==IMG_SQUARE || $type==IMG_THUMB)
      continue;
    if (!array_key_exists($type, ImageStdParams::get_defined_type_map()))
      continue;
    $url = $derivative->get_url();
    if (isset($added[$url]))
      continue;
    $added[$url] = 1;
    $show_original &= !($derivative->same_as_source());
    $unique_derivatives[$type]= $derivative;

    if (isset($_COOKIE['available_size']))
    {
      $available_size = explode('x', $_COOKIE['available_size']);

      $size = $derivative->get_size();
      if ($size)
      {
        // if we have a very high picture (such as an infographic), we only try to match width
        if ($size[0]/$size[1] < $conf['automatic_size_min_ratio'])
        {
          if ($size[0] <= $available_size[0])
          {
            $automatic_size = $type;
          }
        }
        // if we have a very wide picture (panoramic), we only try to match height
        elseif ($size[0]/$size[1] > $conf['automatic_size_max_ratio'])
        {
          if ($size[1] <= $available_size[1])
          {
            $automatic_size = $type;
          }
        }
        else
        {
          if ($size[0] <= $available_size[0] and $size[1] <= $available_size[1])
          {
            $automatic_size = $type;
          }
        }
      }
    }
  }

  global $page, $template;

  load_language('plugin.lang', ASIZE_PATH);
  $template->set_prefilter('picture', 'asize_picture_prefilter');

  $is_automatic_size = true;
  if (@$_COOKIE['is_automatic_size'] == 'no' || !$do_automatic_resize)
  {
    $is_automatic_size = false;
  }
  $template->assign(
    array(
      'is_automatic_size' => $is_automatic_size,
      'ASIZE_URL' => duplicate_picture_url(),
      )
    );
 
  if (isset($automatic_size))
  {
	// check, if we want to do it for this file...
    if ($is_automatic_size && $do_automatic_resize)
    {
      $selected_derivative = $element_info['derivatives'][$automatic_size];
    }
    
    $template->assign(
      array(
        'automatic_size' => $automatic_size,
        'ASIZE_TITLE' => sprintf(
          l10n('The best adapted size for this photo and your screen is size %s'),
          l10n($automatic_size)
          )
        )
      );
  }

  if ($show_original)
  {
    $template->assign( 'U_ORIGINAL', $element_info['element_url'] );
  }

  $template->append('current', array(
	  'selected_derivative' => $selected_derivative,
	  'unique_derivatives' => $unique_derivatives,
	), true);

  $template->set_filenames(
    array('default_content'=>'picture_content.tpl')
    );

  $template->assign( array(
      'ALT_IMG' => $element_info['file'],
      'COOKIE_PATH' => cookie_path(),
      'asize_width_margin' => $conf['automatic_size_width_margin'],
      'asize_height_margin' => $conf['automatic_size_height_margin'],
      )
    );

  $template->set_filename('asize_picture_js', realpath(ASIZE_PATH.'picture_js.tpl'));
  $template->parse('asize_picture_js');
  
  return $template->parse( 'default_content', true);
}

function asize_picture_prefilter($content, &$smarty)
{
  $pattern = '#\{foreach from=\$current\.unique_derivatives#';
  $replacement = '
<span id="aSizeChecked"{if !$is_automatic_size} style="visibility:hidden"{/if}>&#x2714; </span> <a id="aSize" href="{$ASIZE_URL}" title="{$ASIZE_TITLE}" data-checked="{if $is_automatic_size}yes{else}no{/if}">{\'Automatic\'|@translate}</a>
<br><br>
{foreach from=$current.unique_derivatives';
  $content = preg_replace($pattern, $replacement, $content);

  return $content;
}

?>
