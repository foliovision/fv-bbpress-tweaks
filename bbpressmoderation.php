<?php
/*
Plugin Name: bbPress Moderation
Description: Moderate bbPress topics and replies
Author: Ian Stanley
Version: 1.8.3
Author URI: http://codeincubator.co.uk


 Copyright: 		Ian Stanley, 2013- (email:iandstanley@gmail.com)
 Maintainer:		Ian Stanley, 2013-  (email iandstanley@gmail.com)
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
  
	/**
	 * Construct
	 * 
	 * Setup filters and actions for bbPress
	 */
	function __construct() {
		add_filter('bbp_new_topic_pre_insert', array($this, 'pre_insert'));
		add_filter('bbp_new_reply_pre_insert', array($this, 'pre_insert'));

		add_filter('bbp_new_topic_redirect_to', array($this, 'redirect_to'), 10 , 2);
		
		add_filter('bbp_has_topics_query', array($this, 'query'));  //  
		add_filter('bbp_has_replies_query', array($this, 'query'));
		
		add_filter('bbp_get_topic_permalink', array($this, 'permalink'), 10, 2);
		add_filter('bbp_get_reply_permalink', array($this, 'permalink'), 10, 2);

		add_filter('bbp_get_topic_title', array($this, 'title'), 10, 2);

		add_filter('bbp_get_reply_content', array($this, 'content'), 10, 2);

		add_filter('bbp_current_user_can_publish_replies', array($this, 'can_reply'));
		
		add_action('bbp_new_topic', array($this, 'new_topic'), 10, 4);
		add_action('bbp_new_reply', array($this, 'new_reply'), 10, 5);
		
		//load_plugin_textdomain(self::TD, false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
		
		if (is_admin()) {
			// Activation/deactivation functions
			register_activation_hook(__FILE__, array(&$this, 'activate'));
			register_deactivation_hook(__FILE__, array(&$this, 'deactivate'));
		
			// Register an uninstall hook to automatically remove options
			register_uninstall_hook(__FILE__, array('bbPressModeration', 'deinstall') );
			
			add_action('admin_init', array($this, 'admin_init' ));
			add_action('admin_menu', array($this, 'admin_menu' ));
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(&$this, 'plugin_action_links'));
			add_filter('post_row_actions', array($this, 'post_row_actions'), 20, 2);
		
			add_action( 'pending_to_publish', array($this, 'pending_to_publish'), 10, 1 );
		
		}
    
    add_action( 'init', array( $this, 'cookie_check' ) );
    
    
    add_filter( 'bbp_current_user_can_access_create_reply_form', array( $this, 'moderated_posts_allow_reply' ) );
    add_filter( 'post_type_link', array( $this, 'post_type_link' ), 11, 4 );
    add_filter( 'posts_results', array( $this, 'moderated_posts_remove' ) ) ;
    add_filter( 'pre_get_posts', array( $this, 'moderated_posts_for_poster' ) ) ;
	}
	
	/**
	 * Activate
	 * @return boolean
	 */
	function activate() {
		// Notify admin 
		add_option(self::TD . 'always_display', 1);
		add_option(self::TD . 'notify', 1);
		add_option(self::TD . 'always_approve_topics', 1);
		add_option(self::TD . 'always_approve_replies', 1);
		add_option(self::TD . 'previously_approved', 1);
		
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
      return $wpdb->get_col( "SELECT ID FROM $wpdb->posts AS p JOIN $wpdb->postmeta AS m ON p.ID = m.post_id WHERE meta_value = '".esc_sql($this->cookie)."' AND post_type = 'topic'" );
    } else {
      return false;
    }
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
	static function deinstall() {
		delete_option(self::TD . 'always_display');
		delete_option(self::TD . 'notify');
		delete_option(self::TD . 'always_approve_topics');
		delete_option(self::TD . 'always_approve_replies');
		delete_option(self::TD . 'previously_approved');
		
		return true;
	}
  
  
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
    
    if( isset($query->query['post_type']) && $query->query['post_type'] == 'reply' && isset($query->query['edit']) && $query->query['edit'] = 1 ) {
      $query->query_vars['post_status'] = 'publish,pending';
      $query->query['p'] = $query->query['name'];
      $query->query_vars['p'] = $query->query['name'];
      unset($query->query['reply']);
      unset($query->query['name']);
      unset($query->query_vars['reply']);
      unset($query->query_vars['name']);
      
    }

    if( !isset($query->query['post_type']) || ( $query->query['post_type'] != 'topic' && ( is_array($query->query['post_type']) && implode('',$query->query['post_type']) != 'topicreply' ) ) ) return;
    
    if( $this->cookie ) {
      $query->query_vars['post_status'] = 'publish,pending';
    }
    
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
    }
    
    return $where;
  }
  
  
  function pending_post_add_name( $data, $postarr ) {
    $data['post_name'] = wp_unique_post_slug( sanitize_title($data['post_title']), false, 'publish', $data['post_type'], $data['post_parent'] );
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
    				$data['post_status'] = 'pending';
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
      $bbp['post_status'] = 'publish,pending';
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
	 * TODO We could drop a cookie in pre_insert so the original poster can see
	 * the content but still hide it from other users. 
	 * 
	 * @param string $content - the content
	 * @param int $post_id - the post ID
	 * @return string - New content
	 */
	function content($content, $post_id) {

		$post = get_post( $post_id );
    $aIds = $this->cookie_get_ids();
		if ($post && $post->post_status == 'pending') {
			if (current_user_can('moderate')) {
				// Admin can see body
				return __('(Awaiting moderation)', self::TD) . '<br />' . $content;
			} else if ( $aIds && ( in_array( $post_id, $aIds ) || in_array( $post->post_parent, $aIds ) ) ) {
				// See the content if it belongs to you
				return __('(Awaiting moderation)', self::TD) . '<br />' . $content;
			} else {
				return __('(Awaiting moderation)', self::TD);
			}
		}

		return $content;
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
		
		$status = bbp_get_reply_status($reply_id);
		
		if ($status == 'pending') {
			$this->notify_admin($reply_id);
		}
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
		
		$status = bbp_get_topic_status($topic_id);
		
		if ($status == 'pending') {
			$this->notify_admin($topic_id);
		}
	}
	
	/**
	 * Alert admin of pending topic/reply
	 */
	function notify_admin($post_id) {
		
		if (get_option(self::TD . 'notify')) {
			
			$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
			$blogurl = get_option('home');
			$message  = sprintf(__('New topic/reply awaiting moderation on your site %s: %s', self::TD), $blogname, $blogurl) . "\r\n\r\n";

			/* Add body of topic/reply to email */
			$post = get_post($post_id);
			$title = '';
			
			if ($post) {
				$title = $post->post_title;
				$message .= get_permalink($post->ID) . "\r\n\r\n";
				$author = get_userdata($post->post_author);
				$message .= "The following content was posted\r\n";
				if ($author !== false) {
					$name = $author->user_firstname . " " . $author->user_lastname;
					$name = trim($name);
					if (empty($name)) {
						$name = $author->display_name;
					}
					if ($name == $author->user_login) {
						$name = '';
					} else {
						$name = ' (' . $name . ')';
					}
					$message .= "by " . $author->user_login .  $name . "\r\n\r\n";
				} else {
					$message .= "by Anonymous\r\n\r\n";
				}
				$message .= $post->post_title . "\r\n" . $post->post_content . "\r\n\r\n";
			}
			
			@wp_mail(get_option('admin_email'), sprintf(__('[%s] bbPress Moderation - %s', self::TD), $blogname, $title), $message);
		}
	}
	
	/**
	 * Show pending counts for topics/replies and
	 * add plugin options page to Settings
	 */
	function admin_menu() {
		global $menu;
		global $wpdb;
		
		add_options_page(__('bbPress Moderation', self::TD), __('bbPress Moderation', self::TD), 
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
		register_setting( self::TD.'option-group', self::TD.'always_display');
		register_setting( self::TD.'option-group', self::TD.'notify');
		register_setting( self::TD.'option-group', self::TD.'always_approve_topics');
		register_setting( self::TD.'option-group', self::TD.'always_approve_replies');
		register_setting( self::TD.'option-group', self::TD.'previously_approved');
		
	}
	
	/**
	 * Plugin settings page for various options
	 */
	function options() {
		?>
		
		<div class="wrap">
		
		<div id="icon-options-general" class="icon32"><br/></div>
		<h2><?php _e('bbPress Moderation settings', self::TD); ?></h2>
		
		<form method="post" action="options.php">
		
		<?php settings_fields( self::TD.'option-group' );?>
		
		<table class="form-table">
			
		<tr valign="top">
		<th scope="row"><?php _e('Display pending posts on forums', self::TD); ?></th>
		<td>
			<input type="checkbox" id="<?php echo self::TD; ?>always_display" name="<?php echo self::TD; ?>always_display" value="1" <?php echo (get_option(self::TD.'always_display', '') ? ' checked ' : ''); ?>" />
			<label for="<?php echo self::TD; ?>always_display"><?php _e('Always display', self::TD); ?></label>
		</td>
		</tr>
			
		<tr valign="top">
		<th scope="row"><?php _e('Email me Whenever', self::TD); ?></th>
		<td>
			<input type="checkbox" id="<?php echo self::TD; ?>notify" name="<?php echo self::TD; ?>notify" value="1" <?php echo (get_option(self::TD.'notify', '') ? ' checked ' : ''); ?>" />
			<label for="<?php echo self::TD; ?>notify"><?php _e('A topic or reply is held for moderation', self::TD); ?></label>
		</td>
		</tr>
		
		<tr valign="top">
		<th scope="row"><?php _e('Do not moderate', self::TD); ?></th>
		<td>
			<input type="checkbox" id="<?php echo self::TD; ?>previously_approved" name="<?php echo self::TD; ?>previously_approved" value="1" <?php echo (get_option(self::TD.'previously_approved', '') ? ' checked ' : ''); ?>" />
			<label for="<?php echo self::TD; ?>previously_approved"><?php _e('A topic or reply by a previously approved author', self::TD); ?></label>
		</td>
		</tr>

		<tr valign="top">
		<th scope="row"><?php _e('Anonymous topics and replies', self::TD); ?></th>
		<td>
			<input type="checkbox" id="<?php echo self::TD; ?>Always_Approve_Topics" name="<?php echo self::TD; ?>always_approve_topics" value="1" <?php echo (get_option(self::TD.'always_approve_topics', '') ? ' checked ' : ''); ?>" />
			<label for="<?php echo self::TD; ?>always_approve_topics"><?php _e('Always moderate topics', self::TD); ?></label>
		</td>
	</tr>
	
	<tr>
				<th scope="row"><?php _e('', self::TD); ?></th>
		<td>
			<input type="checkbox" id="<?php echo self::TD; ?>Always_Approve_Replies" name="<?php echo self::TD; ?>always_approve_replies" value="1" <?php echo (get_option(self::TD.'always_approve_replies', '') ? ' checked ' : ''); ?>" />
			<label for="<?php echo self::TD; ?>always_approve_replies"><?php _e('Always moderate replies', self::TD); ?></label>
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
		<?php _e('If you like this plugin please consider <a href="http://codeincubator.co.uk">donating</a> to fund future development. Thank you.', self::TD); ?>
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
				$actions['spam'] = '<a href="' . $spam_uri . '" title="' . esc_attr__( 'Mark the reply as not spam', 'bbpress' ) . '">' . __( 'Not spam', 'bbpress' ) . '</a>';
			} else {
				$actions['spam'] = '<a href="' . $spam_uri . '" title="' . esc_attr__( 'Mark this reply as spam',    'bbpress' ) . '">' . __( 'Spam',     'bbpress' ) . '</a>';
			}
		}
	
		// For Topics:
		if ( $post->post_type =='topic' && $post->post_status == 'pending' && !array_key_exists('spam', $actions)){
			// Mark posts as spam
			$spam_uri  = esc_url( wp_nonce_url( add_query_arg( array( 'topic_id' => $the_id, 'action' => 'bbp_toggle_topic_spam' ), remove_query_arg( array( 'bbp_topic_toggle_notice', 'topic_id', 'failed', 'super' ) ) ), 'spam-topic_'  . $the_id ) );
			if ( bbp_is_topic_spam( $the_id ) ) {
				$actions['spam'] = '<a href="' . $spam_uri . '" title="' . esc_attr__( 'Mark the topic as not spam', 'bbpress' ) . '">' . __( 'Not spam', 'bbpress' ) . '</a>';
			} else {
				$actions['spam'] = '<a href="' . $spam_uri . '" title="' . esc_attr__( 'Mark this topic as spam',    'bbpress' ) . '">' . __( 'Spam',     'bbpress' ) . '</a>';
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
}

$bbpressmoderation = new bbPressModeration();