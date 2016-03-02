<?php
/*
Copyright:       Ian Stanley, 2013- (email:iandstanley@gmail.com)
Maintainer:      Ian Stanley, 2013-  (email iandstanley@gmail.com)
Original Design by Ian Haycox, 2011-2013 (email : ian.haycox@gmail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details. 

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

class bbPressModeration {
  
  const TD = 'bbpressmoderation'; 
  
  var $cookie = false;
  
  var $sEmail = false;
  
  /**
  * Construct
  * 
  * Setup filters and actions for bbPress
  */
  function __construct() {
    add_filter( 'bbp_new_topic_pre_insert', array( $this, 'pre_insert' ) );
    add_filter( 'bbp_new_reply_pre_insert', array( $this, 'pre_insert' ) );
    
    add_filter( 'bbp_new_topic_redirect_to', array( $this, 'redirect_to' ), 10, 2 );
    
    add_filter( 'bbp_has_topics_query', array( $this, 'query' ) );  //  
    add_filter( 'bbp_has_replies_query', array( $this, 'query' ) );
    
    add_filter( 'bbp_get_topic_permalink', array( $this, 'permalink' ), 10, 2 );
    add_filter( 'bbp_get_reply_permalink', array( $this, 'permalink' ), 10, 2 );
    
    add_filter( 'bbp_get_topic_title', array( $this, 'title' ), 10, 2 );
    
    add_filter( 'bbp_get_reply_content', array( $this, 'content' ), 10, 2 );
    
    /* front-end moderation links for topics and replies */
    add_filter( 'bbp_topic_admin_links', array( $this, 'bbp_topic_admin_links' ), 10, 2 );
    add_filter( 'bbp_reply_admin_links', array( $this, 'bbp_reply_admin_links' ), 10, 2 );
    add_action( 'bbp_get_request', array( $this, 'bbp_approve_topic_handler' ), 2 );
    add_action( 'bbp_get_request', array( $this, 'bbp_approve_reply_handler' ), 2 );
    add_action( 'bbp_get_request', array( $this, 'bbp_solve_topic_handler' ), 2 );
    
    // set checkbox "Notify me of follow-up replies via email" as checked by default
    add_filter( 'bbp_get_form_topic_subscribed', array( $this, 'fv_bbpress_tweaks_auto_subscribe'), 10, 2 );
    
    add_filter( 'bbp_current_user_can_publish_replies', array( $this, 'can_reply' ) );
    
    add_action( 'bbp_new_topic', array( $this, 'new_topic' ), 999999, 4 );
    add_action( 'bbp_new_reply', array( $this, 'new_reply' ), 999999, 5 );
    
    //load_plugin_textdomain(self::TD, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    
    if (is_admin() ) {
      // Activation/deactivation functions
      register_activation_hook( __FILE__, array( &$this, 'activate' ) );
      register_deactivation_hook( __FILE__, array( &$this, 'deactivate' ) );
      
      // Register an uninstall hook to automatically remove options
      // register_uninstall_hook( __FILE__, array( 'bbPressModeration', 'deinstall' ) );
      
      add_action( 'admin_init', array( $this, 'admin_init' ) );
      add_action( 'admin_menu', array( $this, 'admin_menu' ) );
      add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( &$this, 'plugin_action_links' ) );
      add_filter( 'post_row_actions', array( $this, 'post_row_actions' ), 20, 2 );
      
      add_action( 'pending_to_publish', array( $this, 'pending_to_publish' ), 10, 1 );
      
      /* back-end moderation links for topics and replies */
      add_filter( 'post_row_actions', array( $this, 'add_approval_row_action_links' ), 10, 2 );
      add_action( 'load-edit.php',  array( $this, 'handle_row_actions_approve_topic' ) );
      add_action( 'admin_notices',  array( $this, 'handle_row_actions_approve_topic_notice' ) );
      add_action( 'load-edit.php',  array( $this, 'handle_row_actions_approve_reply' ) );
      add_action( 'admin_notices',  array( $this, 'handle_row_actions_approve_reply_notice' ) );
     
    }
    
    //  without this the intro reply would not show up. strange
    add_filter('bbp_show_lead_topic','__return_true');
        
    add_filter( 'bbp_get_topic_admin_links' , array( $this, 'topic_solve_link' ), 999, 3 );    
    
    if(get_option(self::TD.'limit_guest_access')){
      // If FV bbPress Settings->Limit guest access is checked
      
      add_action( 'template_redirect', array($this, 'fv_bbpress_tweaks_hide_user_profile') );
      add_filter('bbp_get_reply_content',array( $this,'fv_bbpress_tweaks_post_text') );
      add_filter('bbp_get_topic_content',array( $this,'fv_bbpress_tweaks_post_text') );
      add_filter( 'get_avatar',array( $this, 'fv_bbpress_tweaks_get_avatar' ),10,6 );         
      add_filter('bbp_get_reply_author_link',array( $this,'fv_bbpress_tweaks_forum_user_info_remove') );
      add_filter('bbp_get_topic_author_link',array( $this,'fv_bbpress_tweaks_forum_user_info_remove') );         
      add_filter('bbp_get_topic_author_avatar',array( $this,'empty_avatar') );
      add_action( 'bbp_theme_before_topic_started_by',array( $this, 'fv_bbpress_tweaks_forum_remove_started_by_before') );
      add_action( 'bbp_theme_after_topic_started_by',array( $this, 'fv_bbpress_tweaks_forum_remove_started_by_after') );
    }
    
    add_action( 'init', array( $this, 'cookie_check' ) );
    
    add_filter( 'bbp_current_user_can_access_create_reply_form', array( $this, 'moderated_posts_allow_reply' ) );
    //add_filter( 'post_type_link', array( $this, 'post_type_link' ), 11, 4 );  //  fix for broken reply editing
    add_filter( 'posts_results', array( $this, 'moderated_posts_remove' ) );
    add_filter( 'pre_get_posts', array( $this, 'moderated_posts_for_poster' ) );
    
    add_filter( 'bbp_get_do_not_reply_address', array( $this, 'fix_forum_from_address') );
  
    if( get_option('fv_bbpress_email') ) {
      $this->sEmail = get_option('fv_bbpress_email');
    } else if( get_option('admin_email') ) {
      $this->sEmail = get_option('admin_email');
    }
  
  }
  
  /**
  * Activate
  * @return boolean
  */
  function activate() {
    // Notify admin
    add_option(self::TD . 'search_before_post', 0);
    add_option(self::TD . 'limit_guest_access', 0);
    add_option(self::TD . 'always_display', 1);
    add_option(self::TD . 'notify', 1);
    add_option(self::TD . 'always_approve_topics', 1);
    add_option(self::TD . 'always_approve_replies', 1);
    add_option(self::TD . 'always_approve_topics_registered', 1);
    add_option(self::TD . 'always_approve_replies_registered', 1);
    add_option(self::TD . 'previously_approved', 1);
    add_option(self::TD . 'put_in_front_end_moderation_links', 1);
    
    return true;
  }
  
  function cookie_check() {
    if( isset($_COOKIE['comment_author_email_'.COOKIEHASH]) ) {
      $this->cookie = $_COOKIE['comment_author_email_'.COOKIEHASH];
    }
  }
  
  function cookie_get_ids() {
    if( $this->cookie ) {
      global $wpdb;
      return $wpdb->get_col( "SELECT ID FROM $wpdb->posts AS p JOIN $wpdb->postmeta AS m ON p.ID = m.post_id WHERE meta_value = '".esc_sql($this->cookie)."' AND post_type IN ( 'topic', 'reply' ) AND post_status != 'trash' " ); //fix by fvKajo from:
      //      return $wpdb->get_col( "SELECT ID FROM $wpdb->posts AS p JOIN $wpdb->postmeta AS m ON p.ID = m.post_id WHERE meta_value = '".esc_sql($this->cookie)."' AND post_type = 'topic'" );
    } else {
      return false;
    }
  }
  
  function fix_forum_from_address() {
    return $this->sEmail;
  }
  
  function fv_bbpress_tweaks_get_ids(){
    if( is_user_logged_in() ){
      global $wpdb;
      return $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_author = '".get_current_user_id()."' AND post_type IN ( 'topic', 'reply' )" );        
    }
    return false;
  }
  
  /**
  * Deactivate
  * @return boolean
  */
  function deactivate() {
    return true;
  }
  
  /**
  * Tidy up deleted plugin by removing options
  */
  /*static function deinstall() {
    delete_option(self::TD . 'always_display');
    delete_option(self::TD . 'notify');
    delete_option(self::TD . 'always_approve_topics');
    delete_option(self::TD . 'always_approve_replies');
    delete_option(self::TD . 'previously_approved');
    delete_option(self::TD . 'put_in_front_end_moderation_links');
    
    return true;
  }*/
  
  
  function moderated_posts_allow_reply( $can ) {
    if( !$this->cookie ) return $can;
    
    if( $aIds = $this->cookie_get_ids() ) {
      global $post;
        if( in_array($post->ID, $aIds ) ) {
        return true;
      }
    }
    
    return $can;
  }
  
  
  function moderated_posts_for_poster( $query ) { //  users with cookie get even the pending posts
    
    
    if( is_admin() ) return;
    
    if( (isset($query->query['post_type']) && ( $query->query['post_type'] == 'reply' || $query->query['post_type'] == 'topic' ) ) && ( $this->cookie || is_user_logged_in() )  ) {
      if( !( current_user_can('moderate_comments') && isset($_GET['view']) && $_GET['view'] == 'all' ) ) {        
        $query->query_vars['post_status'] = 'publish,closed,pending';
      }
    }
    
    
    if( isset($query->query['post_type']) && ( $query->query['post_type'] == 'reply' || $query->query['post_type'] == 'topic' ) && isset($query->query['edit']) && $query->query['edit'] == 1 ) {
      $query->query_vars['post_status'] = 'publish,closed,pending';
      /*$query->query['name'] = $query->query['name'];
      $query->query_vars['name'] = $query->query['name'];
      unset($query->query['reply']);
      unset($query->query['name']);
      unset($query->query_vars['reply']);
      unset($query->query_vars['name']);*/
    
    }
    
    if( !isset($query->query['post_type']) || ( $query->query['post_type'] != 'topic' && ( is_array($query->query['post_type']) && implode('',$query->query['post_type']) != 'topicreply' ) ) ) return;
    
    
    //var_dump($query);
  }
  
  
  function moderated_posts_remove( $aPosts ) {  //  pending posts are removed if their IDs don't match
    if( $aIds = $this->cookie_get_ids() ) {
      foreach( $aPosts AS $k => $objPost ) {
        if( $objPost->post_status != 'publish' && !in_array($objPost->ID,$aIds) && !in_array($objPost->post_parent,$aIds) ) {
          unset($aPosts[$k]);
        }
      }
    }
    return $aPosts;
  }
  
  function moderated_posts_where( $where = '' ) {
    if( $ids = $this->cookie_get_ids() ) {
      global $wpdb;
      $where = str_ireplace($wpdb->prefix."posts.post_status = 'publish'", $wpdb->prefix."posts.post_status = 'publish' OR ID IN (".implode(',',$ids).")", $where);
      $where = str_ireplace($wpdb->prefix."posts.post_status = 'pending'", "0=1", $where); //fix by fvKajo
    }
    //var_dump( $where );
    
    return $where;
  }
  
  
  function pending_post_add_name( $data, $postarr ) {
    
    if( !empty($data['post_title']) ) {
      $title = $data['post_title'];
    } else {
      $aTopic = get_post( $data['post_parent'] );
      $title = get_the_title($aTopic->ID);
    }
    
    $data['post_name'] = wp_unique_post_slug( sanitize_title($title), false, 'publish', $data['post_type'], $data['post_parent'] );
    
    return $data;
  }
  
  
  function post_type_link( $post_link ) {
    $aArgs = func_get_args();
    $post = $aArgs[1];
    
    if( $post->post_type != 'reply' /*|| $post->post_status != 'pending'*/ ) return $post_link;
    
    global $wp_rewrite;
    $post_link = $wp_rewrite->get_extra_permastruct($post->post_type);
    
    $post_type = get_post_type_object($post->post_type);
    
    $slug = $post->ID;
    
    $post_link = str_replace("%$post->post_type%", $slug, $post_link);
    $post_link = home_url($post_link);
    
    return $post_link;
  }
  
  
  /**
  * Before inserting a new topic/reply mark
  * this as 'pending' depending on settings
  * 
  * @param array $data - new topic/reply data
  */
  function pre_insert($data) {
    global $wpdb;
    
    if (@$data['post_status']=='spam') return $data; // fix for 1.8.2  hide spam 
    
    // Pointless moderating a post that the current user can approve
    if (current_user_can('moderate')) return $data;
    
    add_filter( 'wp_insert_post_data', array($this,'pending_post_add_name'), 10, 2 );
    
    if ($data['post_author'] == 0) {
      // Anon user - check if need to moderate
      
      if ( ( 'topic' == $data['post_type'] && get_option(self::TD . 'always_approve_topics') ) || ( 'reply' == $data['post_type'] && get_option(self::TD . 'always_approve_replies') ) ) {
        // fix for v.1.8.3 separate settings for anonymous posting
        $data['post_status'] = 'pending';
      }
    } else {
      // Registered user
      if (get_option(self::TD . 'previously_approved')) {
        // Check if user already published 
        $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d AND post_type IN ('topic','reply') AND post_status = 'publish'", $data['post_author']);
        $count = $wpdb->get_var($sql);
        if (!$count) {
          // Anon or User never published topic/reply so mark as pending.
          $data['post_status'] = 'pending';
        }
      } else {
        if ( ( 'topic' == $data['post_type'] && get_option(self::TD . 'always_approve_topics_registered') ) || ( 'reply' == $data['post_type'] && get_option(self::TD . 'always_approve_replies_registered') ) ) {
          $data['post_status'] = 'pending';
        }
      }     
    }
    return $data;
  }
  
  /// Deprecated!
  /**
  * Include in the return topic/reply list any pending posts
  * 
  * @param unknown_type $where
  */
  function posts_where($where = '') {
  
    /*
    * Typical $where is:
    * 
    * " AND wp_posts.post_parent = 490 AND wp_posts.post_type IN ('topic', 'reply') AND 
    * (wp_posts.post_status = 'publish' OR wp_posts.post_status = 'closed' OR wp_posts.post_author = 1 
    * AND wp_posts.post_status = 'private' OR wp_posts.post_author = 1 AND wp_posts.post_status = 'hidden') 
    * 
    */
    
    // Real UGLY hack to add 'pending' post statuses to the
    // list of returned posts. Changes to whitespace are gonna shoot us
    // in the foot - regex anyone ?
    //
    // Might possibly mess up the query for posts without publish status !!!!
    //
    // fix to allow DB prefixes
    global $wpdb;
    $where = str_ireplace($wpdb->prefix."posts.post_status = 'publish'", "(".$wpdb->prefix."posts.post_status = 'publish' OR ".$wpdb->prefix."posts.post_status = 'pending')", $where);          
    
    return $where;
  }
  
  /**
  * When querying for topics/replies include those
  * marked as pending otherwise we never see them
  * 
  * @param array $bbp topic/reply query data
  */
  function query($bbp) {
  
    if( current_user_can('moderate') ) {
      if( !( current_user_can('moderate_comments') && isset($_GET['view']) && $_GET['view'] == 'all'  ) ) {              
        $bbp['post_status'] = 'publish,pending';
      }
    } else if( $this->cookie ) {
      add_filter('posts_where', array($this, 'moderated_posts_where'));
    }
    
    
    
    
    /*if (get_option(self::TD . 'always_display')) {
    
    add_filter('posts_where', array($this, 'posts_where'));
    
    }
    else 
    {
    
    // remove_filter('posts_where', array($this, 'posts_where'));
    }*/
    
    
    return $bbp;
  }
  
  /**
  * Trash the permalink for pending topics/replies
  * Would be nice if we could remove the link entirely
  * but the filter is a bit too late
  * 
  * @param string $permalink - topic or reply permalink
  * @param int $topic_id - topic or reply ID
  */
  function permalink($permalink, $topic_id) {
    global $post;
    
    if( isset($post->post_status) && $post->post_status == 'pending' ) { //  we need to make the permalink pretty, even if it's pending
      remove_filter('bbp_get_topic_permalink', array($this, 'permalink'), 10, 2);      
      $post->post_status = 'publish';
      $topic_id_new = bbp_get_topic_id( $topic_id );
      
      if( $topic_id_new != $topic_id ) {
        $permalink = bbp_get_topic_permalink($topic_id);
      } else {
        $permalink = get_permalink($post);
      }
      
      $post->post_status = 'pending';      
      add_filter('bbp_get_topic_permalink', array($this, 'permalink'), 10, 2);
    }
    
    
    /*if (!current_user_can('moderate') && $post && $post->post_status == 'pending') {
    return '#';  // Fudge url to prevent viewing
    }*/
    
    return $permalink;
  }
  
  /**
  * Alter pending topic title to indicate it
  * is awaiting moderation
  * 
  * @param string $title - the title
  * @param int $post_id - the post ID
  * @return string New title
  */
  function title($title, $post_id) {    
    $post = get_post( $post_id );
    if ($post && $post->post_status == 'pending') {
      return $title . ' ' . __('(Awaiting moderation)', self::TD);
    }
    
    return $title;
  }
  
  /**
  * Hide content for pending replies
  * 
  * TODO-DONE We could drop a cookie in pre_insert so the original poster can see
  * the content but still hide it from other users. 
  * 
  * @param string $content - the content
  * @param int $post_id - the post ID
  * @return string - New content
  */
  function content($content, $post_id) {
  
    $post = get_post( $post_id );
    $aIds = $this->cookie_get_ids();
    $aIds_registered = $this->fv_bbpress_tweaks_get_ids();
    if ($post && $post->post_status == 'pending') {
      if (current_user_can('moderate')) {
        // Admin can see body
        return __('(Awaiting moderation)', self::TD) . '<br />' . $content;
      } elseif ( ( $aIds && ( in_array( $post_id, $aIds ) || in_array( $post->post_parent, $aIds ) ) ) || ( $aIds_registered && ( in_array( $post_id, $aIds_registered ) || in_array( $post->post_parent, $aIds_registered ) ) ) ) {
        // See the content if it belongs to you
        return __('(Awaiting moderation)', self::TD) . '<br />' . $content;
      } else {
        return __('(Awaiting moderation)', self::TD);//TODO does this ever get rendered? Seems like these are excluded from the query
      }
    }
    
    return $content;
  }
  
  function bbp_reply_admin_links( $args, $id ) {
    
    unset($args['spam']);
    
    if( !bbp_allow_threaded_replies() ) unset($args['reply']);
    
    $reply_status = bbp_get_reply_status( $id );
    if( !get_option(self::TD . 'put_in_front_end_moderation_links') || "pending" !== $reply_status || !current_user_can('moderate') ) {
      return $args;
    }
    $aNewArgs = array(
        'approve' => $this->bbp_get_reply_approve_link( $id ),
        //'approve_all' => $this->bbp_get_reply_approve_link( $id, true )
      );
    $aNewArgs = array_reverse($aNewArgs, true);
    $args = array_reverse($args, true);
    foreach( $aNewArgs as $key => $val ) {
      $args[$key] = $val;
    }
    
    return array_reverse($args, true);
  }
  
  function bbp_topic_admin_links( $args, $id ) {
    
    unset($args['stick']);
    unset($args['spam']);
    
    if( !bbp_allow_threaded_replies() ) unset($args['reply']);
    
    $topic_status = bbp_get_topic_status( $id );
    if( !get_option(self::TD . 'put_in_front_end_moderation_links') || "pending" !== $topic_status || !current_user_can('moderate') ) {
      return $args;
    }
    $aNewArgs = array(
        'approve' => $this->bbp_get_topic_approve_link( $id ),
        //'approve_all' => $this->bbp_get_topic_approve_link( $id, true )
      );
    $aNewArgs = array_reverse($aNewArgs, true);
    $args = array_reverse($args, true);
    foreach( $aNewArgs as $key => $val ) {
      $args[$key] = $val;
    }

    return array_reverse($args, true);
  }
  
  function add_approval_row_action_links( $actions, $post ) {
    if( !in_array( $post->post_type, array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ) ) || "pending" !== $post->post_status ) {
      return $actions;
    }
    $strMethod = "bbp_get_{$post->post_type}_approve_link";
    $actions['approve_post'] = $this->$strMethod( $post->ID, false, false );
    $actions['approve_all_posts'] = $this->$strMethod( $post->ID, true, false );
    //var_dump( $actions, $post ); die();
    
    return $actions;
  }
  
  function bbp_topic_approve_link( $id, $bAll = false, $bFrontEnd = true ) {
    echo $this->bbp_get_topic_approve_link( $id, $bAll, $bFrontEnd );
  }
  
  function bbp_get_topic_approve_link( $id, $bAll = false, $bFrontEnd = true ) {
  
    $topic = bbp_get_topic( bbp_get_topic_id( (int) $id ) );
    
    if ( empty( $topic ) || !current_user_can( 'moderate', $topic->ID ) )
      return '';
    $topic_status = bbp_get_topic_status( $id );
    if( "pending" !== $topic_status )
      return '';
    
    $display = !$bAll ? "Approve" : "Approve all by author";
    if( $bFrontEnd ) {
      $uri     = !$bAll ? add_query_arg( array( 'action' => 'bbp_approve_topic', 'topic_id' => $topic->ID ) ) : add_query_arg( array( 'action' => 'bbp_approve_topic_all', 'topic_id' => $topic->ID ) );
      $uri     = !$bAll ? wp_nonce_url( $uri, 'approve-topic_' . $topic->ID ) : wp_nonce_url( $uri, 'approve-topic-all_' . $topic->ID );
      $uri    .= '#new-post';
    } else {
      $uri     = !$bAll ? add_query_arg( array( 'action' => 'bbp_approve_topic', 'topic_id' => $topic->ID ) ) : add_query_arg( array( 'action' => 'bbp_approve_topic_all', 'topic_id' => $topic->ID ) );
      //$edit_url = admin_url( sprintf( get_post_type_object( bbp_get_topic_post_type() )->_edit_link, $topic->ID ) );
      //$uri     = !$bAll ? add_query_arg( array( 'post' => $topic->ID, 'action' => 'bbp_approve_topic' ), $edit_url ) : add_query_arg( array( 'post' => $topic->ID, 'action' => 'bbp_approve_topic_all' ), $edit_url );
      $uri     = remove_query_arg( array( 'handle_row_actions_approve_topic_notice', '_wpnonce', 'failed' ), $uri );
      $uri     = !$bAll ? wp_nonce_url( $uri, 'approve-topic_' . $topic->ID ) : wp_nonce_url( $uri, 'approve-topic-all_' . $topic->ID );
      //$uri    .= '#new-post';
    }
    $retval  = '<a href="' . esc_url( $uri ) . '" class="bbp-topic-approve-link">' . $display . '</a>';
    
    return apply_filters( 'bbp_get_topic_approve_link', $retval, array( $id, $bAll ) );
  }
  
  function bbp_reply_approve_link( $id, $bAll = false, $bFrontEnd = true ) {
    echo $this->bbp_get_reply_approve_link( $id, $bAll, $bFrontEnd );
  }
  
  function bbp_get_reply_approve_link( $id, $bAll = false, $bFrontEnd = true ) {
  
    $reply = bbp_get_reply( bbp_get_reply_id( (int) $id ) );
    
    if ( empty( $reply ) || !current_user_can( 'moderate', $reply->ID ) )
      return '';
    $reply_status = bbp_get_reply_status( $id );
    if( "pending" !== $reply_status )
      return '';
    
    $display = !$bAll ? "Approve" : "Approve all by author";
    if( $bFrontEnd ) {
      $uri     = !$bAll ? add_query_arg( array( 'action' => 'bbp_approve_reply', 'reply_id' => $reply->ID ) ) : add_query_arg( array( 'action' => 'bbp_approve_reply_all', 'reply_id' => $reply->ID ) );
      $uri     = !$bAll ? wp_nonce_url( $uri, 'approve-reply_' . $reply->ID ) : wp_nonce_url( $uri, 'approve-reply-all_' . $reply->ID );
      $uri    .= '#new-post';
    } else {
      //$edit_url = admin_url( sprintf( get_post_type_object( bbp_get_topic_post_type() )->_edit_link, $topic->ID ) );
      //$uri     = !$bAll ? add_query_arg( array( 'post' => $reply->ID, 'action' => 'bbp_approve_reply' ), $edit_url ) : add_query_arg( array( 'post' => $reply->ID, 'action' => 'bbp_approve_reply_all' ), $edit_url );
      $uri     = !$bAll ? add_query_arg( array( 'action' => 'bbp_approve_reply', 'reply_id' => $reply->ID ) ) : add_query_arg( array( 'action' => 'bbp_approve_reply_all', 'reply_id' => $reply->ID ) );
      $uri     = remove_query_arg( array( 'handle_row_actions_approve_reply_notice', '_wpnonce', 'failed' ), $uri );
      $uri     = !$bAll ? wp_nonce_url( $uri, 'approve-reply_' . $reply->ID ) : wp_nonce_url( $uri, 'approve-reply-all_' . $reply->ID );
      //$uri    .= '#new-post';
    }
    $retval  = '<a href="' . esc_url( $uri ) . '" class="bbp-reply-approve-link">' . $display . '</a>';
    
    return apply_filters( 'bbp_get_reply_approve_link', $retval, array( $id, $bAll ) );
  }
  
  function bbp_approve_topic_handler( $action = '' ) {
    if ( empty( $_GET['topic_id'] ) )
      return;
    
    // Setup possible get actions
    $possible_actions = array(
        'bbp_approve_topic',
        'bbp_approve_topic_all'
      );
    
    // Bail if actions aren't meant for this function
    if ( !in_array( $action, $possible_actions ) )
      return;
    
    $failure   = '';                         // Empty failure string
    $view_all  = false;                      // Assume not viewing all
    $topic_id  = (int) $_GET['topic_id'];    // What's the topic id?
    $success   = false;                      // Flag
    $post_data = array( 'ID' => $topic_id ); // Prelim array
    $redirect  = '';                         // Empty redirect URL
    
    // Make sure topic exists
    $topic = bbp_get_topic( $topic_id );
    if ( empty( $topic ) )
      return;
    
    // What is the user doing here?
    if ( !current_user_can( 'moderate', $topic->ID ) ) {
      bbp_add_error( 'bbp_approve_topic_permission', __( '<strong>ERROR:</strong> You do not have the permission to do that.', self::TD ) );
      return;
    }
    $topic_status = bbp_get_topic_status( $topic_id );
    if( "pending" !== $topic_status )
      return;   
    
    // What action are we trying to perform?
    switch ( $action ) {
      case 'bbp_approve_topic':
        check_ajax_referer( 'approve-topic_' . $topic_id );
        
        $success  = $this->bbp_approve_topic( $topic_id );
        $failure  = __( '<strong>ERROR</strong>: There was a problem approving the topic.', self::TD );
        break;
      case 'bbp_approve_topic_all':
        check_ajax_referer( 'approve-topic-all_' . $topic_id );
        
        $success  = $this->bbp_approve_all_topics_by_author( $topic_id );
        $failure  = __( '<strong>ERROR</strong>: There was a problem approving the user\'s all topics.', self::TD );
        break;
      default:
        break;
    }
    
    // Do additional topic toggle actions
    do_action( 'bbp_approve_topic_handler', $success, $post_data, $action );
    
    // No errors
    if ( false !== $success && !is_wp_error( $success ) ) {
    
      // Get the redirect detination
      $permalink = bbp_get_topic_permalink( $topic_id );
      $redirect  = bbp_add_view_all( $permalink, $view_all );
      $redirect .= "#post-$topic_id";
      
      wp_safe_redirect( $redirect );
      
      // For good measure
      exit();
      
      // Handle errors
    } else {
      if( is_wp_error( $success ) ) {
        $failure .= " \n".$success->get_error_message();
      }
      bbp_add_error( 'bbp_approve_topic', $failure );
    }
  }
  
  public function handle_row_actions_approve_topic() {
  
    // Setup possible get actions
    $possible_actions = array(
        'bbp_approve_topic',
        'bbp_approve_topic_all'
      );
    
    // Bail if actions aren't meant for this function
    if ( empty( $_GET['action'] ) || !in_array( $_GET['action'], $possible_actions ) ) {
      return;
    }
    
    // Only proceed if GET is a topic toggle action
    if ( function_exists('bbp_is_get_request') && bbp_is_get_request() && !empty( $_GET['topic_id'] ) ) {
      $action    = $_GET['action'];            // What action is taking place?
      $topic_id  = (int) $_GET['topic_id'];    // What's the topic id?
      $success   = false;                      // Flag
      $post_data = array( 'ID' => $topic_id ); // Prelim array
      $topic     = bbp_get_topic( $topic_id );
      
      // Bail if topic is missing
      if ( empty( $topic ) )
        wp_die( __( 'The topic was not found!', 'bbpress' ) );
      
      if ( !current_user_can( 'moderate', $topic->ID ) ) // What is the user doing here?
        wp_die( __( 'You do not have the permission to do that!', 'bbpress' ) );
      
      $message = array();
      switch ( $action ) {
        case 'bbp_approve_topic' :
          check_ajax_referer( 'approve-topic_' . $topic_id );
          
          $success  = $this->bbp_approve_topic( $topic_id );
          $failure  = __( '<strong>ERROR</strong>: There was a problem approving the topic.', self::TD );
          $message['all'] = '0';
          break;
          /*
          $is_open = bbp_is_topic_open( $topic_id );
          $message = true === $is_open ? 'closed' : 'opened';
          $success = true === $is_open ? bbp_close_topic( $topic_id ) : bbp_open_topic( $topic_id );
          
          break;
          */
        case 'bbp_approve_topic_all' :
          check_ajax_referer( 'approve-topic-all_' . $topic_id );
          
          $success  = $this->bbp_approve_all_topics_by_author( $topic_id, false );
          $failure  = __( '<strong>ERROR</strong>: There was a problem approving the user\'s all topics.', self::TD );
          $message['all'] = '1';
          break;
          /*
          $is_sticky = bbp_is_topic_sticky( $topic_id );
          $is_super  = false === $is_sticky && !empty( $_GET['super'] ) && ( "1" === $_GET['super'] ) ? true : false;
          $message   = true  === $is_sticky ? 'unsticked'     : 'sticked';
          $message   = true  === $is_super  ? 'super_sticked' : $message;
          $success   = true  === $is_sticky ? bbp_unstick_topic( $topic_id ) : bbp_stick_topic( $topic_id, $is_super );
          
          break;
          */
      }
      
      $message['topic_id'] = $topic->ID;
      
      if ( false === $success || is_wp_error( $success ) ) {
        $message['failed'] = '1';
        if( is_wp_error( $success ) ) {
          $message['handle_row_actions_approve_topic_notice'] = urlencode( $success->get_error_message() );
        }
      }
      
      // Do additional topic toggle actions (admin side)
      do_action( 'handle_row_actions_approve_topic', $success, $post_data, $action, $message );
      
      // Redirect back to the topic
      $redirect = add_query_arg( $message, remove_query_arg( array( 'action', 'topic_id' ) ) );
      wp_safe_redirect( $redirect );
      
      // For good measure
      exit();
    }
  }
  
  public function handle_row_actions_approve_topic_notice() {
    
    if( is_admin() ) return;
    
    // Only proceed if GET is a topic toggle action
    if ( function_exists('bbp_is_get_request') && bbp_is_get_request() && !empty( $_GET['topic_id'] ) ) {
      $notice     = isset( $_GET['handle_row_actions_approve_topic_notice'] ) ?  // Which notice?
      stripslashes( urldecode( $_GET['handle_row_actions_approve_topic_notice'] ) ):
      '';
      $topic_id   = (int) $_GET['topic_id'];                  // What's the topic id?
      $is_failure = !empty( $_GET['failed'] ) ? true : false; // Was that a failure?
      $bApproveAll = (bool) $_GET['all'];
      
      // Bais if no topic_id or notice
      if ( empty( $topic_id ) )
        return;
      
      // Bail if topic is missing
      $topic = bbp_get_topic( $topic_id );
      if ( empty( $topic ) )
        return;
      
      if( $bApproveAll ) {
        check_ajax_referer( 'approve-topic-all_' . $topic_id );
      } else {
        check_ajax_referer( 'approve-topic_' . $topic_id );
      }
      
      $topic_title = bbp_get_topic_title( $topic->ID );
      
      if( $bApproveAll ) {
        $message =
        $is_failure === true ?
        sprintf( __( 'There was a problem approving the topic "%1$s" and all topics by its author. %3$s', 'bbpress' ), $topic_title, $topic_id, $notice ) :
        sprintf( __( 'Topic "%1$s" and all topics by its author successfully approved.', 'bbpress' ), $topic_title, $topic_id );
      } else {
        $message =
        $is_failure === true ?
        sprintf( __( 'There was a problem approving the topic "%1$s" (ID: %2$d). %3$s', 'bbpress' ), $topic_title, $topic_id, $notice ) :
        sprintf( __( 'Topic "%1$s" (ID: %2$d) successfully approved.', 'bbpress' ), $topic_title, $topic_id );
      }
      
      // Do additional topic toggle notice filters (admin side)
      $message = apply_filters( 'handle_row_actions_approve_topic_notice', $message, $topic->ID, $notice, $is_failure );
      
      ?>
      
      <div id="message" class="<?php echo $is_failure === true ? 'error' : 'updated'; ?> fade">
        <p style="line-height: 150%"><?php echo esc_html( $message ); ?></p>
      </div>
      
      <?php
    }
  }
  
  public function handle_row_actions_approve_reply() {
  
    // Setup possible get actions
    $possible_actions = array(
        'bbp_approve_reply',
        'bbp_approve_reply_all'
      );
    
    // Bail if actions aren't meant for this function
    if ( empty( $_GET['action'] ) || !in_array( $_GET['action'], $possible_actions ) ) {
      return;
    }
    
    // Only proceed if GET is a reply toggle action
    if ( bbp_is_get_request() && !empty( $_GET['reply_id'] ) ) {
      $action    = $_GET['action'];            // What action is taking place?
      $reply_id  = (int) $_GET['reply_id'];    // What's the reply id?
      $success   = false;                      // Flag
      $post_data = array( 'ID' => $reply_id ); // Prelim array
      $reply     = bbp_get_reply( $reply_id );
      
      // Bail if reply is missing
      if ( empty( $reply ) )
        wp_die( __( 'The reply was not found!', 'bbpress' ) );
      
      if ( !current_user_can( 'moderate', $reply->ID ) ) // What is the user doing here?
        wp_die( __( 'You do not have the permission to do that!', 'bbpress' ) );
      
      $message = array();
      switch ( $action ) {
        case 'bbp_approve_reply' :
          die('tu sa to robi?');
          check_ajax_referer( 'approve-reply_' . $reply_id );
          
          $success  = $this->bbp_approve_reply( $reply_id );
          $failure  = __( '<strong>ERROR</strong>: There was a problem approving the reply.', self::TD );
          $message['all'] = '0';
          break;
          /*
          $is_open = bbp_is_reply_open( $reply_id );
          $message = true === $is_open ? 'closed' : 'opened';
          $success = true === $is_open ? bbp_close_reply( $reply_id ) : bbp_open_reply( $reply_id );
          
          break;
          */
        case 'bbp_approve_reply_all' :
          check_ajax_referer( 'approve-reply-all_' . $reply_id );
          
          $success  = $this->bbp_approve_all_replies_by_author( $reply_id, false );
          $failure  = __( '<strong>ERROR</strong>: There was a problem approving the user\'s all replies.', self::TD );
          $message['all'] = '1';
          break;
          /*
          $is_sticky = bbp_is_reply_sticky( $reply_id );
          $is_super  = false === $is_sticky && !empty( $_GET['super'] ) && ( "1" === $_GET['super'] ) ? true : false;
          $message   = true  === $is_sticky ? 'unsticked'     : 'sticked';
          $message   = true  === $is_super  ? 'super_sticked' : $message;
          $success   = true  === $is_sticky ? bbp_unstick_reply( $reply_id ) : bbp_stick_reply( $reply_id, $is_super );
          
          break;
          */
      }
      
      $message['reply_id'] = $reply->ID;
      
      if ( false === $success || is_wp_error( $success ) ) {
        $message['failed'] = '1';
        if( is_wp_error( $success ) ) {
          $message['handle_row_actions_approve_reply_notice'] = urlencode( $success->get_error_message() );
        }
      }
      
      // Do additional reply toggle actions (admin side)
      do_action( 'handle_row_actions_approve_reply', $success, $post_data, $action, $message );
      
      // Redirect back to the reply
      $redirect = add_query_arg( $message, remove_query_arg( array( 'action', 'reply_id' ) ) );
      wp_safe_redirect( $redirect );
      
      // For good measure
      exit();
    }
  }
  
  public function handle_row_actions_approve_reply_notice() {
  
    //if ( $this->bail() ) return;
    
    // Only proceed if GET is a reply toggle action
    if ( bbp_is_get_request() && !empty( $_GET['reply_id'] ) ) {
      $notice     = isset( $_GET['handle_row_actions_approve_reply_notice'] ) ?  // Which notice?
        stripslashes( urldecode( $_GET['handle_row_actions_approve_reply_notice'] ) ):
        '';
      $reply_id   = (int) $_GET['reply_id'];                  // What's the reply id?
      $is_failure = !empty( $_GET['failed'] ) ? true : false; // Was that a failure?
      $bApproveAll = (bool) $_GET['all'];
      
      // Bais if no reply_id or notice
      if ( empty( $reply_id ) )
        return;
      
      // Bail if reply is missing
      $reply = bbp_get_reply( $reply_id );
      if ( empty( $reply ) )
        return;
      
      if( $bApproveAll ) {
        check_ajax_referer( 'approve-reply-all_' . $reply_id );
      } else {
        check_ajax_referer( 'approve-reply_' . $reply_id );
      }
      
      $reply_title = bbp_get_reply_title( $reply->ID );
      
      if( $bApproveAll ) {
        $message =
        $is_failure === true ?
        sprintf( __( 'There was a problem approving the reply "%1$s" and all replies by its author. %3$s', 'bbpress' ), $reply_title, $reply_id, $notice ) :
        sprintf( __( 'Reply "%1$s" and all replies by its author successfully approved.', 'bbpress' ), $reply_title, $reply_id );
      } else {
        $message =
        $is_failure === true ?
        sprintf( __( 'There was a problem approving the reply "%1$s" (ID: %2$d). %3$s', 'bbpress' ), $reply_title, $reply_id, $notice ) :
        sprintf( __( 'Reply "%1$s" (ID: %2$d) successfully approved.', 'bbpress' ), $reply_title, $reply_id );
      }
      
      // Do additional reply toggle notice filters (admin side)
      $message = apply_filters( 'handle_row_actions_approve_reply_notice', $message, $reply->ID, $notice, $is_failure );
      
      ?>
      
      <div id="message" class="<?php echo $is_failure === true ? 'error' : 'updated'; ?> fade">
        <p style="line-height: 150%"><?php echo esc_html( $message ); ?></p>
      </div>
      
      <?php
    }
  }
  
  function bbp_approve_reply_handler( $action = '' ) {
    if ( empty( $_GET['reply_id'] ) )
      return;
    
    // Setup possible get actions
    $possible_actions = array(
        'bbp_approve_reply',
        'bbp_approve_reply_all'
      );
    
    // Bail if actions aren't meant for this function
    if ( !in_array( $action, $possible_actions ) )
      return;
    
    $failure   = '';                         // Empty failure string
    $view_all  = false;                      // Assume not viewing all
    $reply_id  = (int) $_GET['reply_id'];    // What's the reply id?
    $success   = false;                      // Flag
    $post_data = array( 'ID' => $reply_id ); // Prelim array
    $redirect  = '';                         // Empty redirect URL
    
    
    // Make sure reply exists
    $reply = bbp_get_reply( $reply_id );
    if ( empty( $reply ) )
      return;
    
    // What is the user doing here?
    if ( !current_user_can( 'moderate', $reply->ID ) ) {
      bbp_add_error( 'bbp_approve_reply_permission', __( '<strong>ERROR:</strong> You do not have the permission to do that.', self::TD ) );
      return;
    }
    $reply_status = bbp_get_reply_status( $reply_id );
    if( "pending" !== $reply_status )
      return;   
    
    // What action are we trying to perform?
    switch ( $action ) {
      case 'bbp_approve_reply':
        //$this->fv_bbpress_tweaks_sent_email_approve($reply_id);
        check_ajax_referer( 'approve-reply_' . $reply_id );
        
        $success  = $this->bbp_approve_reply( $reply_id );
        $failure  = __( '<strong>ERROR</strong>: There was a problem approving the reply.', self::TD );
        break;
      case 'bbp_approve_reply_all':
        check_ajax_referer( 'approve-reply-all_' . $reply_id );
        
        $success  = $this->bbp_approve_all_replies_by_author( $reply_id );
        $failure  = __( '<strong>ERROR</strong>: There was a problem approving the user\'s all replies.', self::TD );
        break;
      default:
        break;
    }
    
    // Do additional reply toggle actions
    do_action( 'bbp_approve_reply_handler', $success, $post_data, $action );
    
      // No errors
      if ( false !== $success && !is_wp_error( $success ) ) {
      
      /** Redirect **********************************************************/
      
      // Redirect to
      $redirect_to = bbp_get_redirect_to();
      
      // Get the reply URL
      $reply_url = bbp_get_reply_url( $reply_id, $redirect_to );
      
      // Add view all if needed
      if ( !empty( $view_all ) )
        $reply_url = bbp_add_view_all( $reply_url, true );
      
      // Sent email to user that his/her reply was approved
      
      // Redirect back to reply
      wp_safe_redirect( $reply_url );
      
      // For good measure
      exit();
      
      // Handle errors
    } else {
      if( is_wp_error( $success ) ) {
        $failure .= " \n".$success->get_error_message();
      }
      bbp_add_error( 'bbp_approve_reply', $failure );
    }
  }
  
  function fv_bbpress_tweaks_sent_email_approve($reply_id){
  
    /** Validation ************************************************************/
    
    $topic_id = bbp_get_topic_id( $topic_id );
    $forum_id = bbp_get_forum_id( $forum_id );
    
    // Poster name
    $reply_author_name = bbp_get_reply_author_display_name( $reply_id );
    
    // Poster author email
    $reply_author_email = bbp_get_reply_author_email($reply_id);
    
    /** Mail ******************************************************************/
    
    // Remove filters from reply content and topic title to prevent content
    // from being encoded with HTML entities, wrapped in paragraph tags, etc...
    remove_all_filters( 'bbp_get_reply_content' );
    remove_all_filters( 'bbp_get_topic_title'   );
    
    // Strip tags from text and setup mail data
    $topic_title   = strip_tags( bbp_get_topic_title( $topic_id ) );
    $reply_content = strip_tags( bbp_get_reply_content( $reply_id ) );
    $reply_url     = bbp_get_reply_url( $reply_id );
    $blog_name     = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
    
    // For plugins to filter messages per reply/topic/user
    $message = sprintf( __( '%1$s :
      
        %2$s
        
        Post Link: %3$s
        
        -----------
        
        Your reply was approved by admin.', 'bbpress' ),
        
        $reply_author_name,
        $reply_content,
        $reply_url
      );
    
    
    // For plugins to filter titles per reply/topic/user
    $subject = 'Approve your reply';
    
    // Get the noreply@ address
    $no_reply   = bbp_get_do_not_reply_address();
    
    // Setup "From" email address
    $from_email = apply_filters( 'bbp_subscription_from_email', $no_reply );
    
    // Setup the From header
    $header = array( 'From: ' . get_bloginfo( 'name' ) . ' <' . $from_email . '>' );
    
    $header[] = 'Bcc: ' . $reply_author_email;
    
    //var_dump($reply_author_email, $subject, $message, $header);
    //die();
    
    // Send notification email
    wp_mail( $reply_author_email, $subject, $message, $header );
  }
  
  function bbp_approve_topic( $topic_id ) {
    // Get topic
    $topic = bbp_get_topic( $topic_id );
    if ( empty( $topic ) )
      return new WP_Error( 'no_such_topic', __( 'Topic could not be found.', self::TD ) );
    
    // What is the user doing here?
    if ( !current_user_can( 'moderate', $topic_id ) ) {
      bbp_add_error( 'bbp_approve_topic_permission', __( '<strong>ERROR:</strong> You do not have the permission to do that.', self::TD ) );
      return;
    }
    
    // Bail if already approved
    $topic_status = bbp_get_topic_status( $topic_id );
    if( "pending" !== $topic_status )
      return;   
    
    // Update the topic
    wp_publish_post( $topic_id );
    
    $topic_status = bbp_get_topic_status( $topic_id );
    
    // Return topic_id or false
    return "publish" === $topic_status ? $topic_id : false;
  }
  
  function bbp_approve_all_topics_by_author( $topic_id, $bFrontEnd = true ) {
    $bSuccess  = true;
    $aFailed   = array();
    $iAuthorID = bbp_get_topic_author_id( $topic_id );
    $objAuthor = get_user_by( 'id', $iAuthorID );
    if( false === $objAuthor ) {
      return new WP_Error( 'nouser', __( 'This topic is not associated to any existing user.', self::TD ) );
    }
    $objTopics = new WP_Query(
        array(
            'suppress_filters' => true,
            'post_type'        => bbp_get_topic_post_type(),
            'post_status'      => "pending",
            'author'           => $iAuthorID,
            'posts_per_page'   => -1,
            'nopaging'         => true,
            'fields'           => 'ids'
          )
      );
    if( empty( $objTopics->posts ) ) {
      return new WP_Error( 'notopics', __( 'This topic\'s author has no pending topics. Weird, since at least this one should be theirs, right?', self::TD ) );
    }
    foreach( $objTopics->posts as $iTopicID ) {
      if( $topic_id == $iTopicID ) continue;//leaving the current one as last so that if something unforeseen breaks, the user gets back to a still unapproved post
      $mixRes = $this->bbp_approve_topic( $iTopicID );
      if( false === $mixRes ) {
        $bSuccess = false;
        $aFailed[] = $iTopicID;
      }
    }
    $mixRes = $this->bbp_approve_topic( $topic_id );
    //$mixRes = false;//testing error message
    if( false === $mixRes ) {
      $bSuccess = false;
      $aFailed[] = $topic_id;
    }
    $iAll = count( $objTopics->posts );
    if( true === $bSuccess ) {
      return $iAll;
    }
    $iFailed = count( $aFailed );
    if( $bFrontEnd ) {
      foreach( $aFailed as $key => $val ) {
        $aFailed[$key] = '<a target="_blank" href="' . bbp_get_topic_permalink( $val ) . '">'.$val.'</a>';
      }
    }
    $strError = sprintf(__('The user had %s pending topic(s), of which %s couldn\'t be approved. Topic(s) with these IDs were not approved: %s', self::TD), $iAll, $iFailed, implode( ', ', $aFailed ));
    return new WP_Error( 'some_topics_not_approved', $strError );
  }
  
  function bbp_approve_reply( $reply_id ) {
    // Get reply
    $reply = bbp_get_reply( $reply_id );
    //$reply = ''; // testing error messages
    if ( empty( $reply ) )
      return new WP_Error( 'no_such_reply', __( 'Reply could not be found.', self::TD ) );
    
    // What is the user doing here?
    if ( !current_user_can( 'moderate', $reply_id ) ) {
      bbp_add_error( 'bbp_approve_topic_permission', __( '<strong>ERROR:</strong> You do not have the permission to do that.', self::TD ) );
      return;
    }
    
    // Bail if already approved
    $reply_status = bbp_get_reply_status( $reply_id );
    if( "pending" !== $reply_status )
      return;   
    
    // Update the reply
    wp_publish_post( $reply_id );
    
    $reply_status = bbp_get_reply_status( $reply_id );
    
    // Return reply_id or false
    return "publish" === $reply_status ? $reply_id : false;
  }
  
  function bbp_approve_all_replies_by_author( $reply_id, $bFrontEnd = true ) {
    $bSuccess  = true;
    $aFailed   = array();
    $iAuthorID = bbp_get_reply_author_id( $reply_id );
    $objAuthor = get_user_by( 'id', $iAuthorID );
    if( false === $objAuthor ) {
      return new WP_Error( 'nouser', __( 'This reply is not associated to any existing user.', self::TD ) );
    }
    $objReplies = new WP_Query(
        array(
            'suppress_filters' => true,
            'post_type'        => bbp_get_reply_post_type(),
            'post_status'      => "pending",
            'author'           => $iAuthorID,
            'posts_per_page'   => -1,
            'nopaging'         => true,
            'fields'           => 'ids'
          )
      );
    if( empty( $objReplies->posts ) ) {
      return new WP_Error( 'noreplies', __( 'This reply\'s author has no pending replies. Weird, since at least this one should be theirs, right?', self::TD ) );
    }
    foreach( $objReplies->posts as $iReplyID ) {
      if( $reply_id == $iReplyID ) continue;//leaving the current one as last so that if something unforeseen breaks, the user gets back to a still unapproved post
      $mixRes = $this->bbp_approve_reply( $iReplyID );
      if( false === $mixRes ) {
        $bSuccess = false;
        $aFailed[] = $iReplyID;
      }
    }
    //$mixRes = $this->bbp_approve_reply( $reply_id );
    $mixRes = false;//testing error message
    if( false === $mixRes ) {
      $bSuccess = false;
      $aFailed[] = $reply_id;
    }
    $iAll = count( $objReplies->posts );
    if( true === $bSuccess ) {
      return $iAll;
    }
    $iFailed = count( $aFailed );
    if( $bFrontEnd ) {
      foreach( $aFailed as $key => $val ) {
        $aFailed[$key] = '<a target="_blank" href="' . bbp_get_reply_permalink( $val ) . '">'.$val.'</a>';
      }
    }
    $strError = sprintf(__('The user had %s pending replies, of which %s couldn\'t be approved. Replies with these IDs were not approved: %s', self::TD), $iAll, $iFailed, implode( ', ', $aFailed ));
    return new WP_Error( 'some_replies_not_approved', $strError );
  }
  
  /**
  * Check if newly created topic is published and
  * disable replies until it is.
  * 
  * @param boolean $retval - Indicator if user can reply
  * @return boolean - true can reply
  */
  function can_reply($retval) {
    if (!$retval) return $retval;
    
    $topic_id = bbp_get_topic_id();
    
    return ('publish' == bbp_get_topic_status($topic_id));
  }
  
  /**
  * Notify admin of new reply with pending status
  * 
  * @param int $reply_id
  * @param int $topic_id
  * @param int $forum_id
  * @param boolean $anonymous_data
  * @param int $reply_author
  */
  function new_reply($reply_id = 0, $topic_id = 0, $forum_id = 0, $anonymous_data = false, $reply_author = 0) {
    $reply_id = bbp_get_reply_id( $reply_id );
    
    $this->notify_admin($reply_id);
  
  }
  
  /**
  * Notify admin of new topic with pending status
  * 
  * @param int $topic_id
  * @param int $forum_id
  * @param boolean $anonymous_data
  * @param int $reply_author
  */
  function new_topic($topic_id = 0, $forum_id = 0, $anonymous_data = false, $topic_author = 0) {
    $topic_id = bbp_get_topic_id( $topic_id );
    
    $this->notify_admin($topic_id);
  
  }
  
  /**
  * Alert admin of pending topic/reply
  */
  function notify_admin($post_id) {
  
    if (get_option(self::TD . 'notify')) {
    
      $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
      
      $sSubject = '(something failed)';
      $sMessage = 'Unable to fetch post '.$post_id;
      
      $objPost = get_post($post_id);
      if( $objPost ) {
        if( $objPost->post_status == 'spam' ) return;
        
        $objAuthor = get_userdata($objPost->post_author);
        
        if( $objAuthor !== false ) {
          $name = $objAuthor->user_firstname . " " . $objAuthor->user_lastname;
          $name = trim($name);
          if( empty($name) ) {
            $name = $objAuthor->display_name;
          }
          if( $name == $objAuthor->user_login ) {
            $name = '';
          } else {
            $name = ' (' . $name . ')';
          }
          $sPosterName = $objAuthor->user_login .  $name;
        } else {
          $sPosterName = "Anonymous\r\n\r\n";
        }
        
        $sStatus = ( $objPost->post_status == 'publish' ) ? 'was posted': 'is awaiting moderation';
        
        $sMessage  = sprintf(__('New '.$objPost->post_type.' by '.$sPosterName.' '.$sStatus.' on your site %s: %s', self::TD), $blogname, get_permalink($objPost->ID)) . "\r\n\r\n";
        $sMessage .= $objPost->post_content;
        
        $sSubject = $objPost->post_title;
        if( $objPost->post_type == 'reply' ) {
          $objTopic = get_post($objPost->post_parent);
          $sSubject = $objTopic->post_title;
        }
      
      }
      
      add_filter( 'wp_mail_from_name', array( 'bbPressModeration', 'custom_wp_mail_from_name' ) );
      
      @wp_mail( $this->sEmail, $sSubject, $sMessage, array( 'Reply-To: '.$objAuthor->user_email ) );   // todo: notifications email address!
    }
  }
  
  function custom_wp_mail_from_name( $original_email_from ) {
    return get_option('blogname').' Support Forums';   // todo: option for what it should say!
  }   
  
  /**
  * Show pending counts for topics/replies and
  * add plugin options page to Settings
  */
  function admin_menu() {
    global $menu;
    global $wpdb;
    
    add_options_page(__('FV bbPress Tweaks', self::TD), __('FV bbPress Tweaks', self::TD), 
    'manage_options', self::TD, array($this, 'options'));
    
    /*
    * Are there any pending items ?
    */
    $sql = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'topic' AND post_status = 'pending'";
    $topic_count = $wpdb->get_var($sql);
    $sql = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'reply' AND post_status = 'pending'";
    $reply_count = $wpdb->get_var($sql);
    
    if ($reply_count || $topic_count) {
      /*
      * Have a quick butchers at the menu structure
      * looking for topics and replies and tack
      * on the pending bubble count. The 5th item seems
      * to be the class name
      * 
      * Bit hacky but seems to work 
      */
      foreach ($menu as $key=>$item) {
        if ($topic_count && isset($item[5]) && $item[5] == 'menu-posts-topic') {
          $bubble = '<span class="awaiting-mod count-'.$topic_count.'"><span class="pending-count">'.number_format_i18n($topic_count) .'</span></span>';
          $menu[$key][0] .= $bubble;
        }
        if ($reply_count && isset($item[5]) && $item[5] == 'menu-posts-reply') {
          $bubble = '<span class="awaiting-mod count-'.$reply_count.'"><span class="pending-count">'.number_format_i18n($reply_count) .'</span></span>';
          $menu[$key][0] .= $bubble;
        }
      }
    }
  }
  
  /**
  * Check if we need to redirect this pending topic
  * back to the forum list of topics
  * 
  * @param string $redirect_url
  * @param string $redirect_to
  * @return string
  */
  function redirect_to($redirect_url, $redirect_to) {
  
    if (!empty($redirect_url)) {
      $query = parse_url($redirect_url, PHP_URL_QUERY);
      $args = wp_parse_args($query);
      
      if (isset($args['p'])) {
        $topic_id = bbp_get_topic_id($args['p']);
        
        if ('pending' == bbp_get_topic_status($topic_id)) {
          return $_SERVER['HTTP_REFERER'];
        }
      }
    }
    
    return $redirect_url;
  }
  
  /**
  * Add settings link to plugin admin page
  * 
  * @param array $links - plugin links
  * @return array of links
  */
  function plugin_action_links($links) {
    $settings_link = '<a href="admin.php?page=bbpressmoderation">'.__('Settings', self::TD).'</a>';
    $links[] = $settings_link;
    return $links;
  }
  
  /**
  * Register our options
  */
  function admin_init() {
    register_setting( self::TD.'option-group', self::TD.'search_before_post');
    register_setting( self::TD.'option-group', self::TD.'limit_guest_access');
    register_setting( self::TD.'option-group', self::TD.'always_display');
    register_setting( self::TD.'option-group', self::TD.'notify');
    register_setting( self::TD.'option-group', self::TD.'always_approve_topics');
    register_setting( self::TD.'option-group', self::TD.'always_approve_replies');
    register_setting( self::TD.'option-group', self::TD.'always_approve_topics_registered');
    register_setting( self::TD.'option-group', self::TD.'always_approve_replies_registered');
    register_setting( self::TD.'option-group', self::TD.'previously_approved');
    register_setting( self::TD.'option-group', self::TD.'put_in_front_end_moderation_links');
    
  }
  
  /**
  * Plugin settings page for various options
  */
  function options() {
  ?>
  
  <div class="wrap">
  
    <div id="icon-options-general" class="icon32"><br/></div>
    <h2><?php _e('FV bbPress Settings', self::TD); ?></h2>
    
    <form method="post" action="options.php">
    
      <?php settings_fields( self::TD.'option-group' );?>
      
      <table class="form-table">
      
        <tr valign="top">
          <td>
            <h3><?php _e('General settings', self::TD); ?></h3>
          </td>
        </tr>
        
        <tr valign="top">
          <th scope="row"><?php _e('Search before posting', self::TD); ?></th>
          <td>
            <input type="checkbox" id="<?php echo self::TD; ?>search_before_post" name="<?php echo self::TD; ?>search_before_post" value="1" <?php echo (get_option(self::TD.'search_before_post', '') ? ' checked="checked" ' : ''); ?> />
            <label for="<?php echo self::TD; ?>search_before_post"><?php _e('Posting new topic will show an Ajax-powered search form allowing your users to post to the existing topics rather than creating new ones', self::TD); ?></label>
          </td>
        </tr>        
        
        <tr valign="top">
          <th scope="row"><?php _e('Limit guest access', self::TD); ?></th>
          <td>
            <input type="checkbox" id="<?php echo self::TD; ?>limit_guest_access" name="<?php echo self::TD; ?>limit_guest_access" value="1" <?php echo (get_option(self::TD.'limit_guest_access', '') ? ' checked="checked" ' : ''); ?> />
            <label for="<?php echo self::TD; ?>limit_guest_access"><?php _e('Limit guest users to first couple of sentences of each forum topic only, hide user names and avatars (Google will index your forums, good for SEO)', self::TD); ?></label>
          </td>
        </tr>
        
        <tr valign="top">
          <td>
            <h3><?php _e('Moderation settings', self::TD); ?></h3>
          </td>
        </tr>
        
        <tr valign="top">
          <th scope="row"><?php _e('Display pending posts on forums', self::TD); ?></th>
          <td>
            <input type="checkbox" id="<?php echo self::TD; ?>always_display" name="<?php echo self::TD; ?>always_display" value="1" <?php echo (get_option(self::TD.'always_display', '') ? ' checked="checked" ' : ''); ?> />
            <label for="<?php echo self::TD; ?>always_display"><?php _e('Always display', self::TD); ?></label>
          </td>
        </tr>
        
        <tr valign="top">
          <th scope="row"><?php _e('Email me Whenever', self::TD); ?></th>
          <td>
            <input type="checkbox" id="<?php echo self::TD; ?>notify" name="<?php echo self::TD; ?>notify" value="1" <?php echo (get_option(self::TD.'notify', '') ? ' checked="checked" ' : ''); ?> />
            <label for="<?php echo self::TD; ?>notify"><?php _e('A topic or reply is held for moderation', self::TD); ?></label>
          </td>
        </tr>
        
        <tr valign="top">
          <th scope="row"><?php _e('Anonymous topics and replies', self::TD); ?></th>
          <td>
            <input type="checkbox" id="<?php echo self::TD; ?>always_approve_topics" name="<?php echo self::TD; ?>always_approve_topics" value="1" <?php echo (get_option(self::TD.'always_approve_topics', '') ? ' checked="checked" ' : ''); ?> />
            <label for="<?php echo self::TD; ?>always_approve_topics"><?php _e('Always moderate topics', self::TD); ?></label>
          </td>
        </tr>
        
        <tr>
          <th scope="row"><?php _e('', self::TD); ?></th>
          <td>
            <input type="checkbox" id="<?php echo self::TD; ?>always_approve_replies" name="<?php echo self::TD; ?>always_approve_replies" value="1" <?php echo (get_option(self::TD.'always_approve_replies', '') ? ' checked="checked" ' : ''); ?> />
            <label for="<?php echo self::TD; ?>always_approve_replies"><?php _e('Always moderate replies', self::TD); ?></label>
          </td>
        </tr>
        
        <tr valign="top">
          <th scope="row"><?php _e('Registered user topics and replies', self::TD); ?></th>
          <td>
            <input type="checkbox" id="<?php echo self::TD; ?>always_approve_topics_registered" name="<?php echo self::TD; ?>always_approve_topics_registered" value="1" <?php echo (get_option(self::TD.'always_approve_topics_registered', '') ? ' checked="checked" ' : ''); ?> />
            <label for="<?php echo self::TD; ?>always_approve_topics_registered"><?php _e('Always moderate topics', self::TD); ?></label>
          </td>
        </tr>
        
        
        <tr>
          <th scope="row"><?php _e('', self::TD); ?></th>
          <td>
            <input type="checkbox" id="<?php echo self::TD; ?>always_approve_replies_registered" name="<?php echo self::TD; ?>always_approve_replies_registered" value="1" <?php echo (get_option(self::TD.'always_approve_replies_registered', '') ? ' checked="checked" ' : ''); ?> />
            <label for="<?php echo self::TD; ?>always_approve_replies_registered"><?php _e('Always moderate replies', self::TD); ?></label>
          </td>
        </tr>
        
        <tr valign="top">
          <th scope="row"><?php _e('Do not moderate', self::TD); ?></th>
          <td>
            <input type="checkbox" id="<?php echo self::TD; ?>previously_approved" name="<?php echo self::TD; ?>previously_approved" value="1" <?php echo (get_option(self::TD.'previously_approved', '') ? ' checked="checked" ' : ''); ?> />
            <label for="<?php echo self::TD; ?>previously_approved"><?php _e('A topic or reply by a previously approved author', self::TD); ?></label>
          </td>
        </tr>
        
        <tr>
          <th scope="row"><?php _e('Front end moderation', self::TD); ?></th>
          <td>
            <input type="checkbox" id="<?php echo self::TD; ?>put_in_front_end_moderation_links" name="<?php echo self::TD; ?>put_in_front_end_moderation_links" value="1" <?php echo (get_option(self::TD.'put_in_front_end_moderation_links', '') ? ' checked="checked" ' : ''); ?> />
            <label for="<?php echo self::TD; ?>put_in_front_end_moderation_links"><?php _e('Put in front end moderation links', self::TD); ?></label>
          </td>
        </tr> 
      
      </table>
    
    
      <p class="submit">
        <input type="submit" class="button" value="<?php _e('Save Changes', self::TD); ?>" />
      </p>
    </form>
    
    <p>
      <?php _e('If you have any problems, please read the <a href="http://wordpress.org/extend/plugins/bbpressmoderation/faq/">FAQ</a> and if necessary contact me through the support forum on the plugin homepage.', self::TD); ?>
    </p>
    <p>
      <?php //_e('If you like this plugin please consider <a href="http://codeincubator.co.uk">donating</a> to fund future development. Thank you.', self::TD); ?>
    </p>
  
  
  </div>
  
  <?php 
  }
  
  /**
  * Add a Spam row action
  * 
  * @param unknown_type $actions
  * @param unknown_type $post
  */
  function post_row_actions($actions, $post){
  
    global $wpdb;
    $the_id = $post->ID;
    
    // For replies:
    if ( $post->post_type =='reply' && $post->post_status == 'pending' && !array_key_exists('spam', $actions)){
      // Mark posts as spam
      $spam_uri  = esc_url( wp_nonce_url( add_query_arg( array( 'reply_id' => $the_id, 'action' => 'bbp_toggle_reply_spam' ), remove_query_arg( array( 'bbp_reply_toggle_notice', 'reply_id', 'failed', 'super' ) ) ), 'spam-reply_'  . $the_id ) );
      if ( bbp_is_reply_spam( $the_id ) ) {
        $actions['spam'] = '<a href="' . $spam_uri . '" title="' . esc_attr__( 'Mark the reply as not spam', self::TD ) . '">' . __( 'Not spam', self::TD ) . '</a>';
      } else {
        $actions['spam'] = '<a href="' . $spam_uri . '" title="' . esc_attr__( 'Mark this reply as spam',    self::TD ) . '">' . __( 'Spam',     self::TD ) . '</a>';
      }
    }
    
    // For Topics:
    if ( $post->post_type =='topic' && $post->post_status == 'pending' && !array_key_exists('spam', $actions)){
      // Mark posts as spam
      $spam_uri  = esc_url( wp_nonce_url( add_query_arg( array( 'topic_id' => $the_id, 'action' => 'bbp_toggle_topic_spam' ), remove_query_arg( array( 'bbp_topic_toggle_notice', 'topic_id', 'failed', 'super' ) ) ), 'spam-topic_'  . $the_id ) );
      if ( bbp_is_topic_spam( $the_id ) ) {
        $actions['spam'] = '<a href="' . $spam_uri . '" title="' . esc_attr__( 'Mark the topic as not spam', self::TD ) . '">' . __( 'Not spam', self::TD ) . '</a>';
      } else {
        $actions['spam'] = '<a href="' . $spam_uri . '" title="' . esc_attr__( 'Mark this topic as spam',    self::TD ) . '">' . __( 'Spam',     self::TD ) . '</a>';
      }
    }
    
    return $actions;
  }
  
  /**
  * Detect when a pending moderated post is promoted to
  * publish, i.e. Moderator has approved post.
  * 
  * Fixes bug - http://wordpress.org/support/topic/email-notifications-for-subscribed-topics-not-working
  *
  * @param unknown_type $post
  */
  function pending_to_publish($post) {
    if (!$post) return;
    
    // Only replies to topics need to notify parent
    if ($post->post_type == bbp_get_reply_post_type()) {
      $reply_id = bbp_get_reply_id( $post->ID);
      if ($reply_id) {
        // Get ancestors
        $ancestors = get_post_ancestors( $reply_id );
        
        if ($ancestors && count($ancestors) > 1) {
          $topic_id = $ancestors[0];
          $forum_id = $ancestors[count($ancestors)-1];
          bbp_notify_subscribers($reply_id, $topic_id, $forum_id);
        }
      }
      
    }
  }
  
  /*
  *  Forum tweaks
  */
  
  //  check if person who browse the forum is logged in.
  function fv_bbpress_tweaks_membership_user() {
    if( current_user_can('access_s2member_level1') )  {
      return true;
    }
    
    if( function_exists('rcp_is_active') && rcp_is_active() ) {
      return true;
    }      
    
    return false;
  }
  
  // If somebody isn't logged in, he (she) won't see whole reply. 
  function fv_bbpress_tweaks_post_text($content) {
    if( !$this->fv_bbpress_tweaks_membership_user() ) {
      $content = explode( ' ', $content, 12 );
      unset( $content[11] );
      $content = implode( ' ', $content ).'&hellip;<br /><br /><em>(message shortened)</em>';
    }
    
    return $content;
  }
  
  //  make sure guests don't see person's gravatar
  //add_filter( 'get_avatar', 'fv_bbpress_tweaks_get_avatar', 10, 6 );
  function fv_bbpress_tweaks_get_avatar( $avatar ) {
    if( ( bbp_is_forum_archive() || bbp_is_topic_archive() || bbp_is_single_forum() || bbp_is_single_topic() || bbp_is_single_reply() ) && !$this->fv_bbpress_tweaks_membership_user() ) {
      $aArgs = func_get_args();
      
      // copy from wp-includes/pluggable.php
      $avatar = sprintf(
          "<img alt='%s' src='%s' srcset='%s' class='%s' height='%d' width='%d' %s/>",
          esc_attr( $aArgs[4] ),
          esc_url( get_option('avatar_default') ),
          esc_attr( get_option('avatar_default')." 2x" ),
          esc_attr( join( ' ', array( 'avatar', 'avatar-' . (int) $aArgs[2], 'photo' ) ) ),
          (int) $aArgs[5]['height'],
          (int) $aArgs[5]['width'],
          $args['extra_attr']
        );
    
    }  
    
    return $avatar;
  }
  
  function fv_bbpress_tweaks_forum_user_info_remove( $sHTML ) {
    if( !$this->fv_bbpress_tweaks_membership_user() ) {
      if( !bbp_is_single_topic() ) {
        return '';
      }
    
      $sHTML = <<<HTML
<div class="bbp-author-avatar">
HTML;
      $sHTML .= get_avatar( 'defaultavatar@example.com', 80 );
      $sHTML .= <<<HTML
</div><br /><a name="anchor" class="bbp-author-name">*****</a><br /><div class="bbp-author-role"></div>
HTML;
    }
    return $sHTML;
  }
  
  function empty_avatar($avatar){
    if( !$this->fv_bbpress_tweaks_membership_user() ) {
      return "";
    }
    return $avatar;
  }   
  
  function fv_bbpress_tweaks_forum_remove_started_by_before() {
    if( !$this->fv_bbpress_tweaks_membership_user() ) {
      ob_start();
    }
  }   
  
  function fv_bbpress_tweaks_forum_remove_started_by_after() {
    if( !$this->fv_bbpress_tweaks_membership_user() ) {
      ob_get_clean();
    }
  }
  
  function fv_bbpress_tweaks_auto_subscribe( $checked, $topic_subscribed  ) {
    global $current_user;
    get_currentuserinfo();
    
    if( is_user_logged_in() && $this->sEmail == $current_user->user_email ) {
      $topic_subscribed = false; 
    } else if( $topic_subscribed == 0 ) {
      $topic_subscribed = true;
    }
    
    return checked( $topic_subscribed, true, false );
  }   
  
  function fv_bbpress_tweaks_hide_user_profile($content){
    if( !$this->fv_bbpress_tweaks_membership_user() && bbp_is_single_user()) {
      wp_redirect( home_url( get_option('_bbp_root_slug') ) );
      exit();
    }     
    return $content;
  }
  
  
  
  
  function bbp_solve_topic_handler( $action = '' ) {
    if ( empty( $_GET['topic_id'] ) )
      return;
    
    // Setup possible get actions
    $possible_actions = array(
        'bbp_toggle_topic_solve'
      );
    
    // Bail if actions aren't meant for this function
    if ( !in_array( $action, $possible_actions ) )
      return;
    
    $failure   = '';                         // Empty failure string
    $view_all  = false;                      // Assume not viewing all
    $topic_id  = (int) $_GET['topic_id'];    // What's the topic id?
    $success   = false;                      // Flag
    $post_data = array( 'ID' => $topic_id ); // Prelim array
    $redirect  = '';                         // Empty redirect URL
    
    check_ajax_referer( 'solve-' . bbp_get_topic_post_type() . '_' . $topic_id );
    
    // Make sure topic exists
    $topic = bbp_get_topic( $topic_id );
    if ( empty( $topic ) )
      return;
    
    // What is the user doing here?
    if ( !current_user_can( 'moderate', $topic->ID ) ) {
      bbp_add_error( 'bbp_solve_topic_permission', __( '<strong>ERROR:</strong> You do not have the permission to do that.', self::TD ) );
      return;
    }

    if( get_post_meta( $topic_id, 'fv_bbp_solved', true) ){      
      delete_post_meta( $topic_id, 'fv_bbp_solved', true );
    } else {
      add_post_meta( $topic_id, 'fv_bbp_solved', true );
    }
    
    $success = true;
    
    // Do additional topic toggle actions
    do_action( 'bbp_approve_topic_handler', $success, $post_data, $action );
    
    // No errors
    if ( false !== $success && !is_wp_error( $success ) ) {
    
      // Get the redirect detination
      $permalink = bbp_get_topic_permalink( $topic_id );
      $redirect  = bbp_add_view_all( $permalink, $view_all );
      
      wp_safe_redirect( $redirect );
      
      // For good measure
      exit();
      
      // Handle errors
    } else {
      if( is_wp_error( $success ) ) {
        $failure .= " \n".$success->get_error_message();
      }
      bbp_add_error( 'bbp_solve_topic', $failure );
    }    

  }
  

  
  
  function topic_solve_link( $retval, $r, $args ){    
    $topic = bbp_get_topic( bbp_get_topic_id( (int) $r['id'] ) );

    if( empty( $topic ) || !current_user_can( 'moderate', $topic->ID ) ) return $retval;    
    
    $uri = add_query_arg( array( 'action' => 'bbp_toggle_topic_solve', 'topic_id' => $topic->ID ) );
    $uri = wp_nonce_url( $uri, 'solve-topic_' . $topic->ID );
    
    if( get_post_meta( get_the_ID(),'fv_bbp_solved', true) ){      
      $text = "Mark as unsolved";    
    } else {
      $text = "Mark as solved";
    }
    $link = $r['sep'].( !empty($r['link_before']) ? $r['link_before'] : '' ) . '<a href="' . esc_url( $uri ) . '" class="bbp-topic-reply-link">' . $text . '</a>' . ( !empty($r['link_after']) ? $r['link_after'] : '' );
    
    $retval = str_replace( $r['after'], $link.$r['after'], $retval );
  
    return $retval;
  }
  
  
  
  
}

$bbpressmoderation = new bbPressModeration();