<?php
/*
Plugin Name: PageSpeed Optimizer for Wordpress
Plugin URI: https://www.sourcenest.com/
Description: PageSpeed Optimizer
Author: SourceNEST, LLC.
Version: 0.1
Author URI: https://www.sourcenest.com/
*/

class sn_pagespeed {
  static function init(){
    
    $f = function ( $src ) {
      if ( strpos( $src, 'ver=' . get_bloginfo( 'version' ) ) )
          $src = remove_query_arg( 'ver', $src );
      return $src;
    };
    
    add_filter( 'style_loader_src', $f, 9999 );
    add_filter( 'script_loader_src', $f, 9999 );
    
    add_action('template_redirect', function(){
      
      require_once 'html-minify.php';
      
      if( is_page( ) || is_front_page()|| is_single() || is_archive() ) {
        ob_start( array(sn_pagespeed,'buffer') );
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
      'js'             => '#(\s*<!--(\[if[^\n]*>)?\s*(<script.*</script>)+\s*(<!\[endif\])?-->)|(\s*<script.*</script>)#isU',
      'document_end'   => '#</body>\s*</html>#isU'
    );
    foreach($patterns as $pattern) {
      $matches = array();
      $success = preg_match_all($pattern, $buffer, $matches);
      if ($success) {
        foreach($matches[0] as $text){
          if(preg_match('/document.write/',$text)){
  
          } else {
            $buffer = str_replace($text, '', $buffer);
            $buffer = $buffer . $text;
          }
        }
      }
    }
    
    return new HTML_Minify($buffer);
  }
}

sn_pagespeed::init();