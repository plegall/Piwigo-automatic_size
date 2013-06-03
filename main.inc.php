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

  if ( !empty($content) )
  {// someone hooked us - so we skip;
    return $content;
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
        // echo $type.' => '.$size[0].' x '.$size[1].'<br>';
        if ($size[0] <= $available_size[0] and $size[1] <= $available_size[1])
        {
          $autosize = $type;
        }
      }
    }
  }

  global $page, $template;

  $template->set_prefilter('picture', 'asize_picture_prefilter');

  $is_automatic_size = true;
  if (@$_COOKIE['is_automatic_size'] == 'no')
  {
    $is_automatic_size = false;
  }
  $template->assign('is_automatic_size', $is_automatic_size);
 

  if (isset($autosize))
  {
    if ($is_automatic_size)
    {
      $selected_derivative = $element_info['derivatives'][$autosize];
    }
    $template->assign('autosize', $autosize);
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
      )
    );
  return $template->parse( 'default_content', true);
}

function asize_picture_prefilter($content, &$smarty)
{
  // step 1
  $pattern = '#\{foreach from=\$current\.unique_derivatives#';
  $replacement = '
<span class="switchCheck" id="aSizeChecked"{if !$is_automatic_size} style="visibility:hidden"{/if}>&#x2714; </span> <a id="aSize" href="" data-checked="{if $is_automatic_size}yes{else}no{/if}">{\'Automatic\'|@translate}</a>
<br><br>
{foreach from=$current.unique_derivatives';
  $content = preg_replace($pattern, $replacement, $content);

  // step 2
  $pattern = '#PLUGIN_PICTURE_BEFORE\}\{/if\}#';
  $replacement = 'PLUGIN_PICTURE_BEFORE}{/if}'."\n".file_get_contents(ASIZE_PATH.'picture_js.tpl');

  $content = preg_replace($pattern, $replacement, $content);

  return $content;
}

?>
