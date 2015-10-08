<?php
/**
 * Plugin Name: FV bbPress Tweaks
 * Description: Improve your forum URL structure, allow guest posting and lot more
 * Version: 0.2.4.1
 * Author: Foliovision
 * Author URI: http://foliovision.com
 */


/*
NOTE:

TODO:
- cookies on WPE don't work so bbpressmoderation causes new poster to get 404 when posting a topic because of custom URLs
- on Patently-O, there is something with the rewrites for jobs..

UM issues/tasks:
+ approve UM membership to new forum users
-- this doesn't them an email so either trigger that email sending there or send the email here
+ assign this UM role to new forum users Member - subscriber
+ UM user accounts with the same firstname.lastname are not differenciated: solved by appending number to Last name
- UM doesn't give me the option to assign all posts to a specific user when deleting - just uses ID = 1
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
  var $options;
  var $aDefaultOptions;
  var $aOptionDependencies;
  var $aOptionTypes;
  
  var $bEnabled = true;


  public function __construct() {
    //    delete_option('fv_bbpress_tweaks');//remove this after testing
    $aDefaultOptions = array(
        'participant_importing'          => false,
        'participant_importing_welcome_email'      => false,
        'participant_importing_welcome_email_content'  => 'Hello %firstname%,

Thanks for being part of the %sitename% forums.

Please log in on %login_page% with following credentials or click the "login" link below an article to post comments/topics/replies:

LOGIN: %login%
PASSWORD: %password%

See you in the forum section!

The %sitename% Team',
    'participant_importing_welcome_email_subject'  => '%sitename%: Welcome to our forums',
    'um_support'            => false,
    'um_auto_approve'            => false,
    'participant_importing_pending_email_content'  => 'Hello %firstname%,

Thanks for being part of the %sitename% forums. Your new account is currently being reviewed by a member of our team.

Please allow us some time to process your request.

Once you get approved, you can log in on %login_page% with following credentials:

LOGIN: %login%
PASSWORD: %password%

See you in the forum section!

The %sitename% Team',
        'um_default_role'            => 'member'
      );
    $this->aOptionDependencies = array(
        //keys must be in order from top to lowest option
        //this causes given options to be enabled/disabled if their parent is enabled/disabled
        //+  means when checked
        //-  means when unchecked
        'participant_importing' => array(
          '+participant_importing_welcome_email',
          '+participant_importing_welcome_email_subject',
          '+um_support',
          '+um_auto_approve',
          '+participant_importing_welcome_email_content',
          '+participant_importing_pending_email_content',
          '+um_default_role',
          '-participant_importing_welcome_email',
          '-participant_importing_welcome_email_subject',
          '-um_support',
          '-um_auto_approve',
          '-participant_importing_welcome_email_content',
          '-participant_importing_pending_email_content',
          '-um_default_role'
        ),
        'participant_importing_welcome_email' => array(
          '+participant_importing_welcome_email_subject',
          '-participant_importing_welcome_email_subject',
          '-participant_importing_welcome_email_content',
          '-participant_importing_pending_email_content',
        ),
        'um_support' => array(
          '+um_auto_approve',
          '+um_default_role',
          '-um_auto_approve',
          '-um_default_role',
          '-participant_importing_pending_email_content'
        )
      );
    $this->aOptionTypes = array(
        'participant_importing'          => 'checkbox',
        'participant_importing_welcome_email'      => 'checkbox',
        'participant_importing_welcome_email_content'  => 'text',
        'participant_importing_welcome_email_subject'  => 'text',
        'um_support'            => 'checkbox',
        'um_auto_approve'            => 'checkbox',
        'participant_importing_pending_email_content'  => 'text',
        'um_default_role'            => 'text'
      );

    $this->aDefaultOptions = $aDefaultOptions;
    if( !($aOptions = get_option( 'fv_bbpress_tweaks', false ) ) ) {
      $this->options = $aDefaultOptions;
      update_option( 'fv_bbpress_tweaks', $this->options );
    } else {
      $bChanged = false;
      foreach( array_keys( $aDefaultOptions ) as $key ) {
        if( !isset( $aOptions[$key] ) ) {
        $bChanged = true;
        $aOptions[$key] = $aDefaultOptions[$key];
        }
      }
      $this->options = $aOptions;
      if( $bChanged ) {
        update_option( 'fv_bbpress_tweaks', $this->options );
      }
    }

    add_filter( 'init', array( $this, 'cache_forums') );  //  todo: only load this when needed
    /* NOTE: added this:
    add_filter( 'cptp_excluded_post_types', function($aExcludedPostTypes) { return array('forum','topic','reply'); } );
    into functions.php and changed the code in plugins/custom-post-type-permalinks/CPTP/Util.php -> get_post_types()*/
    add_filter( 'topic_rewrite_rules', array( $this, 'topic_rewrite_rules' ), 100000 ); //fvKajo 20150612
    add_filter( 'forum_rewrite_rules', array( $this, 'forum_rewrite_rules' ), 100000 ); //fvKajo 20150612
    add_filter( 'post_type_link', array( $this, 'forum_post_type_link' ), 100000, 4); //fvKajo 20150612

    if( $this->options['participant_importing'] ) {
      add_filter('bbp_new_topic_pre_insert', array($this, 'pre_insert'), 2);  // 2 because of bbPress Akismet module
      add_filter('bbp_new_reply_pre_insert', array($this, 'pre_insert'), 2);
      add_action('bbp_new_topic', array($this, 'new_topic'), 10, 4);
      add_action('bbp_new_reply', array($this, 'new_reply'), 10, 5);

      //    add_filter( 'pre_get_posts', array( $this, 'moderated_posts_for_poster' ) ) ;//improve this to show new topic to new user only
      //    add_filter('bbp_new_topic_redirect_to', array($this, 'redirect_to'), 10 , 2);//maybe use this to show new topic to new user?
    }

    add_action( 'admin_menu', array($this, 'admin_menu') );


    add_filter( 'bbp_is_topic_published', array( $this, 'allow_notifications_for_pending' ), 10, 2 );
    add_action( 'bbp_new_reply', array( $this, 'allow_notifications_for_pending_record_id' ), 10, 2 );
    
    add_action( 'bbp_theme_before_topic_admin_links', array( $this, 'disable' ) );
    add_action( 'bbp_theme_after_topic_admin_links', array( $this, 'enable' ) );
    add_action( 'bbp_theme_before_reply_admin_links', array( $this, 'disable' ) );
    add_action( 'bbp_theme_after_reply_admin_links', array( $this, 'enable' ) );
  }




  function admin_menu(){
    add_management_page( 'FV BBPress Tweaks', 'FV BBPress Tweaks', 'manage_options', 'fv-bbpress-tweaks', array($this, 'options_panel') );
  }
  
  
  
  
  function allow_notifications_for_pending( $topic_status) {
    $aArgs = func_get_args();
    
    if( isset($this->idTopicJustPosted) && $this->idTopicJustPosted == $aArgs[1] ) {     
      return true;
    }
    return (bool) $topic_status;
  }
  
  
  
  
  function allow_notifications_for_pending_record_id( $reply_id ) {
    $aArgs = func_get_args();
    $this->idTopicJustPosted = $aArgs[1];
  }
  
  
  
  
  function disable() {
    $this->bEnabled = false;
  }
  
  
  
  
  function enable() {
    $this->bEnabled = true;
  }




  function options_panel() {

    add_meta_box( 'option_panel_general_settings', 'General Settings', array( $this,'option_panel_general_settings' ), 'fv_bbpress_tweaks_meta_boxes', 'normal' );

    if (!empty($_POST)) :
    check_admin_referer('fv_bbpress_tweaks');
    $aOptions = array();
    foreach( array_keys( $this->aDefaultOptions ) as $key ) {
      if( !isset( $_POST[$key] ) || empty( $_POST[$key] ) ) {
        switch( $this->aOptionTypes[$key] ) {
          case 'text':
            $aOptions[$key] = '';
            if( in_array( $key, array( 'um_default_role' ) ) ) {
           $aOptions[$key] = $this->aDefaultOptions[$key];
            }
            break;
          case 'checkbox':
            $aOptions[$key] = false;
            break;
          default:
            $aOptions[$key] = $this->aDefaultOptions[$key];
        }
      } else {
        if ( $this->aOptionTypes[$key] === 'text' ) {
          $_POST[$key] = stripslashes( $_POST[$key] );
        }
        $aOptions[$key] = $_POST[$key];
      }
    }
    if( update_option( 'fv_bbpress_tweaks', $aOptions ) ) :
?>
      <div id="message" class="updated fade">
      <p>
        <strong>
         <?php _e('Settings saved', 'fv_bbpress_tweaks'); ?>
        </strong>
      </p>
      </div>
<?php
      $this->options = $aOptions;
    endif;  //  update_option
    endif;  //  $_POST
?>
    <div class="wrap">
    <div style="position: absolute; right: 20px; margin-top: 5px">
       <a href="http://foliovision.com/" target="_blank"><img alt="visit foliovision" src="http://foliovision.com/shared/fv-logo.png" /></a>
    </div>
<!--    <div>
       <div id="icon-options-general" class="icon32"><br /></div>
       <h2>FV Thoughtful Comments</h2>
    </div>-->
    <form method="post" action="">
       <?php wp_nonce_field('fv_bbpress_tweaks') ?>
       <div id="poststuff" class="ui-sortable">
        <?php
          do_meta_boxes( 'fv_bbpress_tweaks_meta_boxes', 'normal', false );
          wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
          wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
        ?>
       </div>
    </form>
    </div>

    <style>
      #poststuff{
        padding-top: 50px;
      }
      #option_panel_general_settings_optiontable ul {
        padding-left: 3em;
      }
      #option_panel_general_settings_optiontable ul li {
        list-style: initial;
      }
    </style>
    <script type="text/javascript">
      //<![CDATA[
      jQuery(document).ready( function($) {
          // close postboxes that should be closed
          $('.if-js-closed').removeClass('if-js-closed').addClass('closed');
          // postboxes setup
          //postboxes.add_postbox_toggles('fv_tc_settings');
        });
      jQuery(document).ready( function() {
      jQuery('#option_panel_general_settings .handlediv').click(function(){
        if(jQuery("#option_panel_general_settings .inside").is(":visible")) {
          jQuery("#option_panel_general_settings .inside").hide();
        } else {
          jQuery("#option_panel_general_settings .inside").show();
        }
      });
<?php
      fv_bbpress_option_dependencies( $this->aOptionDependencies );
?>
      jQuery( "#fv_bbpress_tweaks_submit" ).click(function(){
<?php
        fv_bbpress_options_enable_all_jq( array_keys( $this->aDefaultOptions ) );
?>
      });
      });
      //]]>
    </script>
<?php
  }




  public function option_panel_general_settings() {
   $aOptions = $this->options;
?>
   <table class="optiontable form-table" id="option_panel_general_settings_optiontable">
      <tr valign="top">
        <th scope="row"><?php _e('Import guest posters as users', 'fv_bbpress_tweaks'); ?> </th>
        <td><fieldset><legend class="screen-reader-text"><span><?php _e('Import guest posters as users', 'fv_bbpress_tweaks'); ?></span></legend>
          <input id="participant_importing" type="checkbox" name="participant_importing" value="1"
          <?php if( isset($aOptions['participant_importing']) && $aOptions['participant_importing'] ) echo 'checked="checked"'; ?> />
          <label for="participant_importing"><span><?php _e('When a forum guest post (topic/reply) is made (also works if the forum topics/replies are not moderated, so make sure you use FV Antispam or Akismet or Cleantalk!) it checks if the email address is registered and if not, it creates a user account automatically. If such account exists, the reply/topic is linked to that account.<br /><em>"Anonymous posting" in bbPress must be enabled for this to work</em> - without it, no guest posts are made and thus none of the settings below will be applied.', 'fv_bbpress_tweaks'); ?></span></label>
          <p><?php _e('Note that if you leave this off, the plugin still does the following (so leave the plugin on):', 'fv_bbpress_tweaks'); ?></p><ul><li><?php _e('it <em>adjusts the URL structure</em> to <code>/{forum base}/{forum slug}/{topic slug}</code>.', 'fv_bbpress_tweaks'); ?></li></ul>
        </td>
      </tr>
      <tr valign="top">
        <th scope="row"><?php _e('Welcome/Pending email', 'fv_bbpress_tweaks'); ?> </th>
        <td><fieldset><legend class="screen-reader-text"><span><?php _e('Welcome email', 'fv_bbpress_tweaks'); ?></span></legend>
         <input id="participant_importing_welcome_email" type="checkbox" name="participant_importing_welcome_email" value="1"
          <?php if( isset($aOptions['participant_importing_welcome_email']) && $aOptions['participant_importing_welcome_email'] ) echo 'checked="checked"'; ?> />
          <label for="participant_importing_welcome_email"><span><?php _e('Send user email about account creation. If this is set to NO, users won\'t get their login information.', 'fv_bbpress_tweaks'); ?></span></label><br />
        </td>
      </tr>
      <tr valign="top">
        <th scope="row"><?php _e('Welcome/Pending email subject', 'fv_bbpress_tweaks'); ?> </th>
        <td>
          <input type="text" id="participant_importing_welcome_email_subject" name="participant_importing_welcome_email_subject" class="large-text code" value="<?php echo trim($aOptions['participant_importing_welcome_email_subject']); ?>" />
          <br/>
          <small>Available tags: %sitename%</small>
        </td>
      </tr>
      <tr valign="top">
        <th scope="row"><?php _e('Ultimate Member plugin support', 'fv_bbpress_tweaks'); ?> </th>
        <td><fieldset><legend class="screen-reader-text"><span><?php _e('Ultimate Member plugin support', 'fv_bbpress_tweaks'); ?></span></legend>
          <input id="um_support" type="checkbox" name="um_support" value="1"
          <?php if( isset($aOptions['um_support']) && $aOptions['um_support'] ) echo 'checked="checked"'; ?> />
          <label for="um_support"><span><?php _e('Check this if Ultimate Member plugin is present on your site', 'fv_bbpress_tweaks'); ?></span></label><br />
        </td>
      </tr>
      <tr valign="top">
        <th scope="row"><?php _e('Automatically approve new user\'s Ultimate Member membership', 'fv_bbpress_tweaks'); ?> </th>
        <td><fieldset><legend class="screen-reader-text"><span><?php _e('Automatically approve new user\'s Ultimate Member membership', 'fv_bbpress_tweaks'); ?></span></legend>
          <input id="um_auto_approve" type="checkbox" name="um_auto_approve" value="1"
          <?php if( isset($aOptions['um_support']) && $aOptions['um_auto_approve'] ) echo 'checked="checked"'; ?> />
          <label for="um_auto_approve"><span><?php _e('Check this if you want the user\'s Ultimate Member plugin membership to be automatically approved. They will  get the welcome email below.<br />
          If you leave this unchecked, they will be set as "pending" and the pending email will be sent to them. Please note that UM will send them an email once they are approved if you haven\'t configured it differently.', 'fv_bbpress_tweaks'); ?></span></label><br />
        </td>
      </tr>
      <tr valign="top">
        <th scope="row"><?php _e('Welcome email content', 'fv_bbpress_tweaks'); ?> </th>
        <td>
          <textarea rows="10" id="participant_importing_welcome_email_content" name="participant_importing_welcome_email_content" class="large-text code"><?php echo trim( $aOptions['participant_importing_welcome_email_content'] ); ?></textarea>
          <br/>
          <small>Available tags: %login%, %password%, %firstname%, %lastname%, %sitename%, %login_page%</small>
        </td>
      </tr>
      <tr valign="top">
        <th scope="row"><?php _e('Pending email content', 'fv_bbpress_tweaks'); ?> </th>
        <td>
          <textarea rows="10" id="participant_importing_pending_email_content" name="participant_importing_pending_email_content" class="large-text code"><?php echo trim( $aOptions['participant_importing_pending_email_content'] ); ?></textarea>
          <br/>
          <small>Available tags: %login%, %password%, %firstname%, %lastname%, %sitename%, %login_page%</small>
        </td>
      </tr>
      <tr valign="top">
        <th scope="row"><?php _e('Choose default Ultimate Member role used for new users', 'fv_bbpress_tweaks'); ?> </th>
        <td><fieldset><legend class="screen-reader-text"><span><?php _e('Choose default Ultimate Member role used for new users', 'fv_bbpress_tweaks'); ?></span></legend>
          <select id="um_default_role" type="checkbox" name="um_default_role">
            <option value="">Select...</option>
            <?php
                  $aObjRoles = get_posts(
                    array(
                      'posts_per_page' => -1,
                      'post_type' => 'um_role',
                      'post_status' => 'publish'
                    )
                  );
                  $aRoles = array();
                  if( !empty( $aObjRoles ) ) {
                    foreach( $aObjRoles as $objRole ) {
                      $aRoles[$objRole->post_name] = $objRole->post_title;
                    }
                  }
                  if( !empty( $aRoles ) ) {
                    foreach( $aRoles as $strRoleName => $strRoleTitle ) {
                      $strSelected = '';
                      if( isset($aOptions['um_default_role']) && $aOptions['um_default_role'] == $strRoleName ) {
                     $strSelected = ' selected="selected"';
                      }
                      echo '<option value="'.$strRoleName.'"'.$strSelected.'>'.$strRoleTitle.'</option>'."\n";
                    }
                  }
            ?>
          <label for="um_support"><span><?php /*_e('Check this if Ultimate Member plugin is present on your site', 'fv_bbpress_tweaks');*/ ?></span></label><br />
        </td>
      </tr>
    </table>
   <p>
      <input type="submit" id="fv_bbpress_tweaks_submit" name="fv_bbpress_tweaks_submit" class="button-primary" value="<?php _e('Save Changes', 'fv_bbpress_tweaks') ?>" />
   </p>
<?php
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




  function moderated_posts_for_poster( $query ) { //  users with cookie get even the pending posts
    if( isset($query->query['post_type']) && $query->query['post_type'] == 'topic' ) {
      $query->query_vars['post_status'] = 'publish,pending';
    }

    if( false && $query->query['post_type'] == 'topic' ) {
      var_dump($this->cookie, 'comment_author_email_'.COOKIEHASH, $_COOKIE);
      $this->cookie_check();
      var_dump($this->cookie, $query);
      die('poiu');
    }
/**/
  }




  /**
   * Before inserting a new topic/reply
   *
   * @param array $data - new topic/reply data
   */
  public function pre_insert($data) {
    if( 0 !== $data['post_author'] && '0' !== $data['post_author'] ) {
      return $data;
    }

    $anonymous_data = false;
    if ( bbp_is_anonymous() ) {
      // Filter anonymous data
      $anonymous_data = bbp_filter_anonymous_post_data();
    } else {
      return $data;
    }
    if( false === $anonymous_data ) {
      return $data;
    }
    $iUserID = $this->new_topicorreply( $data, $anonymous_data );
    $data['post_author'] = $iUserID;
    $GLOBALS['fv_bbpress_tweaks_'.$anonymous_data['bbp_anonymous_email']] = $iUserID;

    return $data;
  }




  /**
   *
   *
   * @param int $reply_id
   * @param int $topic_id
   * @param int $forum_id
   * @param boolean $anonymous_data
   * @param int $reply_author
   */
  public function new_reply($reply_id = 0, $topic_id = 0, $forum_id = 0, $anonymous_data = false, $reply_author = 0) {
    //    $this->new_topicorreply( $topic_id, $forum_id, $anonymous_data, $topicorreply_author, $reply_id );
    if( false !== $anonymous_data && isset( $GLOBALS['fv_bbpress_tweaks_'.$anonymous_data['bbp_anonymous_email']] ) ) {
      $reply_author = $GLOBALS['fv_bbpress_tweaks_'.$anonymous_data['bbp_anonymous_email']];
      unset( $GLOBALS['fv_bbpress_tweaks_'.$anonymous_data['bbp_anonymous_email']] );
    }
    if( false !== $anonymous_data && 0 !== $reply_author ) {
      $this->fv_fix_new_topicorreply( $reply_author, $reply_id, $anonymous_data["bbp_anonymous_website"] );
    }
  }




  /**
   *
   *
   * @param int $topic_id
   * @param int $forum_id
   * @param boolean $anonymous_data
   * @param int $reply_author
   */
  public function new_topic($topic_id = 0, $forum_id = 0, $anonymous_data = false, $topic_author = 0) {
    //    $this->new_topicorreply( $topic_id, $forum_id, $anonymous_data, $topicorreply_author );
    if( false !== $anonymous_data && isset( $GLOBALS['fv_bbpress_tweaks_'.$anonymous_data['bbp_anonymous_email']] ) ) {
      $topic_author = $GLOBALS['fv_bbpress_tweaks_'.$anonymous_data['bbp_anonymous_email']];
      unset( $GLOBALS['fv_bbpress_tweaks_'.$anonymous_data['bbp_anonymous_email']] );
    }
    if( false !== $anonymous_data && 0 !== $topic_author ) {
    $this->fv_fix_new_topicorreply( $topic_author, $topic_id, $anonymous_data["bbp_anonymous_website"] );
    }
    //    add_option('fv_bbpress_rewrite_rules_flush', 'true');
    //    var_dump( $anonymous_data, $topic_author );die('poiu');
  }




  /**
   *
   *
   * @param array $data
   * @param mix $anonymous_data
   */
  private function new_topicorreply( $data, $anonymous_data = false ) {
    if( empty($data) || $anonymous_data === false || $anonymous_data === 0 ) {
      var_dump( $data, $anonymous_data );
      echo 'something is missing';
      return 0;
    }

    if ( defined('WP_IMPORTING') && WP_IMPORTING == true ) {
      return 0;
    }

    $strMail = trim( $anonymous_data['bbp_anonymous_email'] );
    if( !isset( $strMail ) || empty( $strMail ) ) {
      var_dump( $topic_id, $forum_id, $anonymous_data, $topicorreply_author, $reply_id );
      echo 'mail was empty';
      return 0;
    }

    $aData = $anonymous_data;

    //if no user with this email address exists, create them
    $iUserID = email_exists( $strMail );
    if( false === $iUserID ) {
      if( isset($data['bbp_akismet_result']) && $data['bbp_akismet_result'] == 'true' ) {
        file_put_contents(ABSPATH.'fv_add_forum_participant_spam.log',date('r')." ----:\n".var_export($aData,true)."\n".var_export($data,true)."\n",FILE_APPEND);    
      } else {
        file_put_contents(ABSPATH.'fv_add_forum_participant.log',date('r')." ----:\n".var_export($aData,true)."\n".var_export($data,true)."\n",FILE_APPEND);    
        $iUserID = $this->fv_add_forum_participant( $aData );
      }

    }
    return $iUserID;
  }




  private function fv_add_forum_participant( $aData ) {
    global $wpdb;
/*
$aData:
  ["bbp_anonymous_name"]    => string(4) "kajo"
  ["bbp_anonymous_email"]    => string(21) "kosar.karol@gmail.com"
  ["bbp_anonymous_website"]  => string(0) ""
*/
    $strQueryUserLogins = "
      select user_login
      from {$wpdb->users}
      ";

    $aUserLogins = $wpdb->get_col( $strQueryUserLogins );

    $aUserName = explode(' ', $aData["bbp_anonymous_name"], 2);
    $sFirstName = $aUserName[0];
    if( isset( $aUserName[1] ) )
      $sLastName = $aUserName[1];
    else
      $sLastName = '';

    if( !empty($sLastName) )
      $sDisName = $sFirstName . ' ' . $sLastName[0] . '.';
    else
      $sDisName = $sFirstName;
    $g_login = sanitize_user( strtolower( $aUserName[0] ), true );
    $g_login = preg_replace("/[^A-Za-z0-9 ]/", '', $g_login);
    unset( $aUserName[0] );
    $user_append = '';
    $user_append = sanitize_user( strtolower( implode( '', $aUserName ) ) );
    $user_append = preg_replace("/[^A-Za-z0-9 ]/", '', $user_append);

    if( !empty( $user_append ) )
      $g_login .= $user_append[0];

    $i = '';
    if( in_array( $g_login, $aUserLogins ) !== FALSE ) {
      $i=1;
      while( in_array( $g_login . $i, $aUserLogins ) !==FALSE )
        $i++;
      $g_login .= $i;
    }

    $strGeneratedPW = wp_generate_password( 12, false );
    $aUserData = array(
        'user_login'    => $g_login,
        'user_nicename'  => $g_login,
        'user_pass'     => $strGeneratedPW,
        'user_email'    => $aData["bbp_anonymous_email"],
        'user_url'      => $aData["bbp_anonymous_website"],
        'display_name'   => $aData["bbp_anonymous_name"],
        'first_name'    => $sFirstName,
        'last_name'     => $sLastName.$i,
        'role'      => 'subscriber'
      );
    /* NO! this would cancel other actions out!
    if( isset( $GLOBALS['fv_bbpress_tweaks_'.$aData['bbp_anonymous_email']] ) ) {
    unset( $GLOBALS['fv_bbpress_tweaks_'.$aData['bbp_anonymous_email']] );
    }
    */

    $user_id = wp_insert_user( $aUserData );
    add_user_meta( $user_id, '_fv_user_imported', 'automatically imported forum participant from aData '.var_export( $aData, true ), true );
    add_user_meta( $user_id, '_fv_user_imported_p', 'fv'.$strGeneratedPW.'poiu', true );

    $objUser = new WP_User( $user_id );
    $strBBPRole = get_option( '_bbp_default_role', 'bbp_participant' );
    $objUser->add_role( $strBBPRole );

    if( isset($this->options['um_support']) && $this->options['um_support'] ) {
      if( isset($this->options['um_default_role']) && $this->options['um_default_role'] ) {
        $strUMRole = $this->options['um_default_role'];
      } else {
        $strUMRole = 'member';
      }
      if( isset($this->options['um_auto_approve']) && $this->options['um_auto_approve'] ) {
        $strUMStatus = 'approved';
      } else {
        $strUMStatus = 'awaiting_admin_review';
      }
      add_user_meta( $user_id, 'role', $strUMRole, true );
      add_user_meta( $user_id, 'account_status', $strUMStatus, true );
    }

    $send_welcome_email = ( isset($this->options['participant_importing_welcome_email']) && $this->options['participant_importing_welcome_email'] ) ? true : false;
    $bPending = false;
    if( $send_welcome_email && isset( $this->options['um_support'], $this->options['um_auto_approve'] ) && $this->options['um_support'] && $this->options['um_auto_approve'] ) {
      $bPending = true;
    }

    if( $send_welcome_email ){
      $this->fv_send_mail_invite( $aData["bbp_anonymous_name"], $strGeneratedPW, $aData["bbp_anonymous_email"], $sFirstName, $sLastName, $bPending );
    }
    return $user_id;
  }




  private function fv_fix_new_topicorreply( $user_id, $iPostIDtoFix, $strWebsite = '' ) {
    global $wpdb;

    foreach( array( '_bbp_anonymous_name', '_bbp_anonymous_email', '_bbp_anonymous_website' ) as $strMetaKey ) {
      $strData = get_post_meta( $iPostIDtoFix, $strMetaKey, true );
      add_post_meta( $iPostIDtoFix, '_fv'.$strMetaKey, $strData, true ); //because of moderation
      delete_post_meta( $iPostIDtoFix, $strMetaKey );
    }
    $aDataToUpdate = array(
        'post_author' => $user_id
      );
    $aWhere = array(
        'ID' => $iPostIDtoFix
      );
    $wpdb->update(
      $wpdb->posts,
      $aDataToUpdate,
      $aWhere
    );
    $res = add_post_meta( $iPostIDtoFix, '_fv_user_imported', 'automatically assigned topic/reply to user ' . $user_id , true );

    $strWebsite = trim( $strWebsite );
    if( !empty( $strWebsite ) ) {
      $objUser = get_userdata( $user_id );
      if( !isset( $objUser->user_url ) || empty( $objUser->user_url ) || $objUser->user_url !== $strWebsite ) {
        $objUser->user_url = $strWebsite;
        wp_update_user( $objUser );
      }
    }

    $objPost = get_post($iPostIDtoFix);
    if( $objPost->post_type == 'reply' ) {
      $objTopic = get_post($objPost->post_parent);
      $iPostIDtoFix = $objTopic->ID;
    }

    if( !is_user_logged_in() ) {  // we assume that if user is not logged in he needs the subscription
      $res = bbp_add_user_topic_subscription( $user_id, $iPostIDtoFix );
    }
    
  }




  private function fv_send_mail_invite( $sLogin, $sPassword , $sEmail, $sFirstName, $sLastName, $bPending = false ) {
    $subject = $this->options['participant_importing_welcome_email_subject'];
    $subject = str_replace( '%sitename%', get_bloginfo('name'), $subject );
    $subject = str_replace( '%firstname%', $sFirstName, $subject );

    $content = $this->options['participant_importing_welcome_email_content'];
    if( $bPending ) $content = $this->options['participant_importing_pending_email_content'];
    $content = str_replace( '%login%', $sLogin, $content );
    $content = str_replace( '%password%', $sPassword, $content );
    $content = str_replace( '%firstname%', $sFirstName, $content );
    $content = str_replace( '%lastname%', $sLastName, $content );
    $content = str_replace( '%sitename%', get_bloginfo('name'), $content );
    $content = str_replace( '%login_page%', site_url('wp-login.php'), $content );

    //TESTING!!
    //file_put_contents( ABSPATH.'/fv-tc-mails.txt', date('r'). "\n" . $sEmail . "\n". $subject ."\n". $content . "\n" . "------------------\n", FILE_APPEND);
    wp_mail( $sEmail, $subject, $content );
  }




  public function cache_forums() {
    $this->forums = wp_cache_get( 'fv_bbpress_forums' );
    if( !$this->forums ) {
      $this->forums = get_posts( array( 'post_type' => 'forum', 'posts_per_page' => -1 ) );
      wp_cache_set( 'fv_bbpress_forums', $this->forums, 'fv_bbpress', 10 );
    }
  }




  function get_link_recursively($post,$link=''){
    if($post->post_parent==0){
      return $post->post_name.'/'.$link;
    }else{
      $link = $post->post_name.'/'.$link;
      return $this->get_link_recursively($this->get_post_from_forums($post),$link);
    }
  }




  function get_post_from_forums($post){
    if($this->forums){
      foreach( $this->forums AS $objForum ) {
        if( $objForum->ID == $post->post_parent ) {
          return $objForum;
        }
      }
    }
    return get_post($post->post_parent);
  }




  public function forum_post_type_link($link) {
    $args = func_get_args();
    $post = $args[1];

    if( !$this->bEnabled || stripos($link,'/edit') !== false ) {
      return $link;
    }

    if( is_object($post) && $post->post_type == 'forum' && in_array( $post->post_status, array( 'publish', 'hidden' ) ) ) {
      $link = user_trailingslashit( home_url(bbp_get_root_slug().'/'.$this->get_link_recursively($post)) );
    
    }elseif( is_object($post) && $post->post_type == 'topic' /*&& in_array( $post->post_status, array( 'publish', 'pending' ) )*/ ) {
      $link = user_trailingslashit( home_url(bbp_get_root_slug().'/'.$this->get_link_recursively($post)) );
      //wp_cache_set( 'fv_bbpress_topic_link-'.$post->ID, $link, 'fv_bbpress' );
    
    }elseif( is_object($post) && $post->post_type == 'reply' && in_array( $post->post_status, array( 'publish', 'pending' ) ) ) {
      $link = user_trailingslashit( home_url(bbp_get_root_slug().'/'.$this->get_link_recursively($post) )); // todo : check links to replies
    
    }

    return $link;
  }




  public function forum_rewrite_rules( $aRules ) {

    $aForums = $this->forums;
    if( !$aForums ) {
      return $aRules;
    }

    $aNewRules = array();
    foreach( $aForums AS $objForum ) {
      foreach( $aRules AS $k => $v ) {
        if( stripos($k, '/attachment/') !== false ) continue;
        
        $link = rtrim($this->get_link_recursively($objForum),"/");      
        $k = str_replace( '/forum/(.+?)', '/('.$link.')', $k );
        $k = str_replace( '/forum)/(.+?)', ')/('.$link.')', $k ); //fvKajo 20150612
        $aNewRules[$k] = $v;
      }
  
    }
    
    return $aNewRules;
  }




  public function get_forums_descending(){
    $aParents = array();
    $aChilds = array();
    
    foreach( $this->forums AS $objForum ) {
      if( $objForum->post_parent != 0 ) {
        array_push($aChilds,$objForum);
      }else{
        array_push($aParents,$objForum);
      }
    }
    
    return array_merge($aChilds,$aParents);
  }




  public function topic_rewrite_rules( $aRules ) {

    if( !$this->forums ) {
      return $aRules;
    }

    //$aRules["forums/topic/([^/]+)/edit/?$"] = 'index.php?' . bbp_get_topic_post_type()  . '=$matches[1]&' . bbp_get_edit_rewrite_id() . '=1';
    $aForums = $this->get_forums_descending();
    $aNewRules = array();
    foreach( $aForums AS $objForum ) {
      foreach( $aRules AS $k => $v ) {
        
        $link = $this->get_link_recursively($objForum);  
        $k = str_replace( '/topic/', '/'.$link, $k );
        $k = str_replace( '/topic)/', ')/'.$link, $k ); //fvKajo 20150612
        $aNewRules[$k] = $v;
    
        if( stripos($k, '/attachment/') === false && stripos($k, '([^/]+)/trackback/?$') !== false ) { //  todo: find a better way of adding this rule!
      $aNewRules["forums/".$link."([^/]+)/edit/?$"] = 'index.php?' . bbp_get_topic_post_type()  . '=$matches[1]&' . bbp_get_edit_rewrite_id() . '=1';
        }
        
    }

    }
    
    return $aNewRules;
  }




}

include( __DIR__ . "/fv_bbpress_option_dependencies.php" );

$FV_bbPress = new FV_bbPress;



add_filter( 'wp_mail', 'fv_bbpress_log_wp_mail' );

function fv_bbpress_log_wp_mail( $atts ) {
  file_put_contents( ABSPATH.'wp_mail-'.sanitize_title(NONCE_SALT).'.log', date('r').":\n".var_export($atts,true)."\n--------\n\n", FILE_APPEND );
  return $atts;
}