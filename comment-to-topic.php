<?php

class FvBbpressCommentToTopic {

  private $debug          = true;
  private $comment_author = false;

  function __construct() {
    add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
    add_action( 'save_post', array( $this, 'save_meta_boxes' ) );

    add_filter( 'comment_form_before', array( $this, 'add_admin_note_to_comment_fields' ) );
    add_filter( 'pre_comment_approved', array( $this, 'pre_comment_approved' ), 999999, 2 );
    add_filter( 'bbp_filter_anonymous_post_data', array( $this, 'bbp_filter_anonymous_post_data' ) );
  }



  function add_meta_boxes() {
    add_meta_box( 'fv-bbpress-comment-to-topic', 'FV BBPress Comments', array( $this, 'metabox_settings' ), 'page', 'side', 'low' );
  }

  function save_meta_boxes() {
    global $post;

    if ( !isset( $_POST['fv-bbpress-comment-to-topic'] ) ) {
      return;
    }

    $fv_bbpress_reply_forum_id = intval( $_POST['fv-bbpress-comment-to-topic'] );
    update_post_meta( $post->ID, '_fv_bbpress_reply_forum_id', $fv_bbpress_reply_forum_id );
  }

  function metabox_settings() {
    global $post;

    $fv_bbpress_reply_forum_id = get_post_meta( $post->ID, '_fv_bbpress_reply_forum_id', true );
    $fv_bbpress_reply_forum_id = ( $fv_bbpress_reply_forum_id ) ? $fv_bbpress_reply_forum_id : 0;

    echo '<label for="fv-bbpress-comment-to-topic">Forum:</label> ';
    //echo '<input type="number" name="fv-bbpress-comment-to-topic" id="fv-bbpress-comment-to-topic" value="'.$fv_bbpress_reply_forum_id.'" /><br/>'."\n";

    bbp_dropdown( array(
      'show_none' => __( '(Select plugin sub-forum)', 'bbpress' ),
      'selected'  => $fv_bbpress_reply_forum_id,
      'select_id' => 'fv-bbpress-comment-to-topic'
    ) );

    echo '<p>Set the <strong>bbpress forum</strong> where the comments will be posted as topics (replies).<br/></p>';
    echo '<p>Leave empty for disalbe this functionality.</p>';
  }

  function add_admin_note_to_comment_fields( $fields ) {
    global $post;

    $forum_id = get_post_meta( $post->ID, '_fv_bbpress_reply_forum_id', true );
    if( $forum_id ) {
      echo "\n<!-- FV bbPress Tweaks: comment from this form will be posted in bbpress forum: {$forum_id} -->\n";
    }
  }

  function pre_comment_approved( $approved, $commentdata  ) {
    global $wpdb;

    if( $approved == 'spam' ) {
      return $approved;
    }

    if( $this->debug ) {
      $this->debug_log( $commentdata );
    }

    $post_id  = $commentdata['comment_post_ID'];
    $forum_id = get_post_meta( $post_id, '_fv_bbpress_reply_forum_id', true );

    if( ! $forum_id ) {
      return $approved;
    }

    //author:
    if( $commentdata['user_ID'] == 0 ) {
      $this->comment_author = array (
        'bbp_anonymous_name'    => $commentdata['comment_author'],
        'bbp_anonymous_email'   => $commentdata['comment_author_email'],
        'bbp_anonymous_website' => $commentdata['comment_author_url']
      );
    }

    $author = bbp_get_current_user_id();

    //topic:
    $post     = get_post( $commentdata['comment_post_ID'] );
    $title    = $post->post_title;
    $content  = $commentdata['comment_content'];

    $query    = "SELECT ID FROM {$wpdb->posts} WHERE post_title = '".esc_sql( $title )."' AND post_parent = ".esc_sql( $forum_id )." AND post_status = 'publish'";
    $topic_id = $wpdb->get_var( $query );

    if( $this->debug ) {
      $this->debug_log( array(
        'author'    => $this->comment_author,
        'forum_id'  => $forum_id,
        'topic_id'  => $topic_id,
        'query'     => $query
      ) );
    }

    if( ! $topic_id ) {
      // create new topic

      $data = array(
        'post_title'      => $title,
        'post_content'    => $content,
        'post_author'     => bbp_get_current_user_id(),
        'post_status'     => bbp_get_public_status_id(),
        'comment_status'  => 'closed',
        'post_type'       => 'topic',
        'post_parent'     => $forum_id,
        'tax_input'       => ''
      );

      $topic_data    = apply_filters( 'bbp_new_topic_pre_insert', $data );
      $forum_post_id = bbp_insert_topic( $topic_data );

      do_action( 'bbp_new_reply', $forum_post_id, $topic_id, $forum_id, $this->comment_author, $author, false, 0 );
    }
    else {
      // topic exists, add reply

      $data = array(
        'post_title'      => $title,
        'post_content'    => $content,
        'post_author'     => bbp_get_current_user_id(),
        'post_status'     => bbp_get_public_status_id(),
        'comment_status'  => 'closed',
        'post_type'       => 'reply',
        'post_parent'     => $topic_id,
        'menu_order'      => bbp_get_topic_reply_count( $topic_id, false ) + 1
      );

      $reply_data    = apply_filters( 'bbp_new_reply_pre_insert', $data );
      $forum_post_id = bbp_insert_reply( $reply_data );

      do_action( 'bbp_new_topic', $forum_post_id, $forum_id, $this->comment_author, $author );
    }

    if( $this->debug ) {
      $this->debug_log( array(
        'data'          => $data,
        'forum_post_id' => $forum_post_id
      ) );
    }

    if( ! $forum_post_id ) {
      // something went wrong
      // comment pending
      return 0;
    }

    $url = get_permalink( $forum_post_id );
    
    if( $this->debug ) {
      $this->debug_log( $url );
    }

    wp_redirect( $url );
    exit;
  }


  function bbp_filter_anonymous_post_data( $data ) {
    if( ! $this->comment_author ) {
      return $data;
    }

    // set up cookies
    bbp_set_current_anonymous_user_data( $this->comment_author  );

    return $this->comment_author;
  }

  function debug_log( $data ) {
    $content = date('r')."\n".var_export( $data, true )."\n\n";
    file_put_contents( ABSPATH.'fv-bbpress-comment-to-topic-'.md5( AUTH_SALT ), $content, FILE_APPEND );
  }


}
$fv_bbpress_comment_to_topic = new FvBbpressCommentToTopic();

?>