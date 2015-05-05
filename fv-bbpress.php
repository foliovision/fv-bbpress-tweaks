<?php
/**
 * Plugin Name: FV bbPress Tweaks
 * Description: Improve your forum URL structure, allow guest posting and lot more
 * Version: 0.1
 * Author: Foliovision
 * Author URI: http://foliovision.com
 */

 
 

if( !class_exists('bbPressModeration') ) {
  include( dirname(__FILE__).'/bbpressmoderation.php' );
}
 
 
 

register_activation_hook(__FILE__,'fv_bbpress_refresh_rules');

function fv_bbpress_refresh_rules() {
	add_option('fv_bbpress_rewrite_rules_flush', 'true');
}
register_deactivation_hook(__FILE__,'fv_bbpress_deactivate');

function fv_bbpress_deactivate() {
	//remove_filter('category_rewrite_rules', 'fv_bbpress_refresh_rules'); // We don't want to insert our custom rules again
	delete_option('fv_bbpress_rewrite_rules_flush');
}


// Remove category base
add_action('init', 'fv_bbpress_permastruct');
function fv_bbpress_permastruct() {
	if (get_option('fv_bbpress_rewrite_rules_flush') == 'true') {
		flush_rewrite_rules();
		delete_option('fv_bbpress_rewrite_rules_flush');
	}	
}




class FV_bbPress {



  
  var $forums;  // store all the forum post objects

  
  
  
  public function __construct() {
    add_filter( 'init', array( $this, 'cache_forums') );  //  todo: only load this when needed
    add_filter( 'forum_rewrite_rules', array( $this, 'forum_rewrite_rules' ) );
    add_filter( 'post_type_link', array( $this, 'forum_post_type_link' ), 10, 4);
    add_filter( 'topic_rewrite_rules', array( $this, 'topic_rewrite_rules' ) );
  }
  
  
  
  
  function cache_forums() {
    $this->forums = wp_cache_get( 'fv_bbpress_forums' );
    if( !$this->forums ) {
      $this->forums = get_posts( array( 'post_type' => 'forum', 'posts_per_page' => -1 ) );
      wp_cache_set( 'fv_bbpress_forums', $this->forums, 'fv_bbpress', 10 ); 
    }
  }
  
  
  

  function forum_post_type_link($link) {
    $args = func_get_args();
    $post = $args[1];
    if( is_object($post) && $post->post_type == 'forum' && $post->post_status == 'publish' ) {
      $link = user_trailingslashit( home_url(bbp_get_root_slug().'/'.$post->post_name) );
      
    } else if( is_object($post) && $post->post_type == 'topic' && $post->post_status == 'publish' ) {
      $link = user_trailingslashit( home_url(bbp_get_root_slug().'/'.$post->post_name) );
      
      $link = wp_cache_get( 'fv_bbpress_topic_link-'.$post->ID );
      if( $link == false) { 
        if( $this->forums ) {
          foreach( $this->forums AS $objForum ) {
            if( $objForum->ID == $post->post_parent ) {              
              $sForum = $objForum->post_name;
            }
          }
        }
        if( $sForum ) {
          $link = user_trailingslashit( home_url(bbp_get_root_slug().'/'.$sForum.'/'.$post->post_name) );
        }
        
        wp_cache_set( 'fv_bbpress_topic_link-'.$post->ID, $link, 'fv_bbpress' ); 
      }
      
    }
    return $link;
  }
  
  
  
  
  function forum_rewrite_rules( $aRules ) {

    $aForums = $this->forums;
    if( !$aForums ) {
      return $rules;
    }
    
    $aNewRules = array();
    foreach( $aForums AS $objForum ) {
      foreach( $aRules AS $k => $v ) {
        if( stripos($k, '/attachment/') !== false ) continue;
        
        $k = str_replace( '/forum/(.+?)', '/('.$objForum->post_name.')', $k );
        $aNewRules[$k] = $v;
      }
        
    }
    
    return $aNewRules;
  }



  
  function topic_rewrite_rules( $aRules ) {
    
    $aForums = $this->forums;
    if( !$aForums ) {
      return $aRules;
    }      
    
    //$aRules["forums/topic/([^/]+)/edit/?$"] = 'index.php?' . bbp_get_topic_post_type()  . '=$matches[1]&' . bbp_get_edit_rewrite_id() . '=1';
        
    $aNewRules = array();
    foreach( $aForums AS $objForum ) {
      foreach( $aRules AS $k => $v ) {
        $k = str_replace( '/topic/', '/'.$objForum->post_name.'/', $k );
        $aNewRules[$k] = $v;
        
        if( stripos($k, '/attachment/') === false && stripos($k, '([^/]+)/trackback/?$') !== false ) { //  todo: find a better way of adding this rule!
          $aNewRules["forums/".$objForum->post_name."/([^/]+)/edit/?$"] = 'index.php?' . bbp_get_topic_post_type()  . '=$matches[1]&' . bbp_get_edit_rewrite_id() . '=1';
        }
      }
        
    }

    return $aNewRules;
  }
  
  
  
  
}

$FV_bbPress = new FV_bbPress;