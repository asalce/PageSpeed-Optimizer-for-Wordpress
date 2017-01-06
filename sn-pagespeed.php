<?php
/*
Plugin Name: PageSpeed Optimizer for Wordpress
Plugin URI: https://www.sourcenest.com/pagespeed-optimizer-plugin-for-wordpress/
Description: PageSpeed Optimizer
Author: SourceNEST, LLC.
Version: 0.2
Author URI: https://www.sourcenest.com/
*/

class sn_pagespeed {
  static function init(){
    
    // TODO: This should be an Option to Activate or Deactive
    $f = function ( $src ) {
      if ( strpos( $src, 'ver=' . get_bloginfo( 'version' ) ) )
          $src = remove_query_arg( 'ver', $src );
      return $src;
    };
    
    add_filter( 'style_loader_src', $f, 9999 );
    add_filter( 'script_loader_src', $f, 9999 );
    
    // REMOVE WP EMOJI
    // TODO: This should be an Option to Activate or Deactive
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'admin_print_styles', 'print_emoji_styles' );
    
    // Remove Recent Comments Style
    // TODO: This should be an Option to Activate or Deactive
    add_action('widgets_init', function () {
        global $wp_widget_factory;
        remove_action('wp_head', array($wp_widget_factory->widgets['WP_Widget_Recent_Comments'], 'recent_comments_style'));
    });
    
    add_action('template_redirect', function(){
      
      require_once 'html-minify.php';
      
      if( is_page( ) || is_front_page()|| is_single() || is_archive() ) {
        ob_start( array('sn_pagespeed','buffer') );
      }
    });

  }
  
  static function buffer($buffer){
    $pattern = '%(<!(|--)\[[^\]]+\]>)?(\r*\n*[ ]*)?<(link)(?=[^<>]*?(?:type="(text/css)"|>))(?=[^<>]*?(?:media="([^<>"]*)"|>))(?=[^<>]*?(?:href="(.*?)"|>))(?=[^<>]*(?:rel="([^<>"]*)"|>))(?:.*?</\1>|[^<>]*>)(\r*\n*[ ]*)?(<!\[endif\](|--)>)?%si';
    $tooBig = array();
    if(preg_match_all($pattern,$buffer,$css)){
      $url = parse_url(get_bloginfo("url"));
      foreach($css[0] as $i => $_css){
        if (preg_match('/rel..stylesheet/',$_css) && !preg_match('/(<!(|--)\[[^\]]+\]>)/',$_css)){
          // $css[] = $css;
          $buffer = str_replace($_css,'',$buffer);
        } else {
          unset($css[0][$i]);
        }
      }
  
      if(count($css[0])){
        $buffer = str_replace(
          '<head>',
          '<head>'.
          '<noscript id="deferred-styles">'.implode($css[0],"\n").'</noscript>'.
          '<script>
            var userAgent = navigator.userAgent || navigator.vendor || window.opera;
  
            if( (Object.prototype.toString.call(window.HTMLElement).indexOf(\'Constructor\') > 0) || (!!window.chrome && !!window.chrome.webstore) || ( typeof InstallTrigger !== \'undefined\' ) || userAgent.match(/SAMSUNG|SGH-[I|N|T]|GT-[I|P|N]|SM-[N|P|T|Z|G]|SHV-E|SCH-[I|J|R|S]|SPH-L/i))
              document.write('.json_encode(implode($css[0],"\n")).')
            else {
              var loadDeferredStyles=function(){var e=document.getElementById("deferred-styles"),t=document.createElement("div");t.innerHTML=e.textContent,document.head.insertBefore(t,document.head.firstChild),e.parentElement.removeChild(e)},raf=requestAnimationFrame||mozRequestAnimationFrame||webkitRequestAnimationFrame||msRequestAnimationFrame;raf?raf(function(){window.setTimeout(loadDeferredStyles,0)}):window.addEventListener("load",loadDeferredStyles);
            }
          </script>',
          $buffer
        );
      }
    };

    // move all JS to bottom
    $patterns = array(
      'js'             => '#(<!(|--)\[[^\]]+\]>)?(<!-->)?\s*<script((?!<\/script>).)*<\/script>(\r*\n*[ ]*)?\s*((<!--)\s*(<!\[endif\](|--)>)|(<!\[endif\](|--)>))?#si',
      'document_end'   => '#</body>\s*</html>#isU'
    );
    foreach($patterns as $parrent_name => $pattern) {
      $matches = array();
      $success = preg_match_all($pattern, $buffer, $matches);
      if ($success) {
        foreach($matches[0] as $i => $text){
          if(($parrent_name=='js' && !preg_match('/document.write|ld\+json/',$text)) || $parrent_name !='js'){
            $buffer = str_replace($text, '', $buffer);
            $buffer = $buffer . $text;
          }
        }
      }
    }
    
    return new HTML_Minify($buffer);
  }
}

if(!is_admin()) sn_pagespeed::init();
