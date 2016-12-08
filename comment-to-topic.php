<?php

class FvBbpressCommentToTopic {

  private $comment_author = false;

  function __construct() {
    add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
    add_action( 'save_post', array( $this, 'save_meta_boxes' ) );

    add_filter( 'preprocess_comment', array( $this, 'preprocess_comment' ) );
    add_filter( 'bbp_filter_anonymous_post_data', array( $this, 'bbp_filter_anonymous_post_data' ) );
  }



  function add_meta_boxes() {
    add_meta_box( 'fv-bbpress-comment-to-topic', 'FV BBPress Comments', array( $this, 'metabox_settings' ), 'page', 'side', 'low' );
  }

  function save_meta_boxes() {
    global $post;

    if ( !isset( $_POST['fv-bbpress-comment-to-topic'] ) || !intval( $_POST['fv-bbpress-comment-to-topic'] ) ) {
      return;
    }

    $fv_bbpress_reply_forum_id = intval( $_POST['fv-bbpress-comment-to-topic'] );
    update_post_meta( $post->ID, '_fv_bbpress_reply_forum_id', $fv_bbpress_reply_forum_id );
  }

  function metabox_settings() {
    global $post;

    $fv_bbpress_reply_forum_id = get_post_meta( $post->ID, '_fv_bbpress_reply_forum_id', true );
    $fv_bbpress_reply_forum_id = ( $fv_bbpress_reply_forum_id ) ? $fv_bbpress_reply_forum_id : 0;

    echo '<label for="fv-bbpress-comment-to-topic">Forum ID:</label> ';
    echo '<input type="number" name="fv-bbpress-comment-to-topic" id="fv-bbpress-comment-to-topic" value="'.$fv_bbpress_reply_forum_id.'" /><br/>'."\n";

    echo '<p>Set the <strong>bbpress forum ID</strong> where the comments will be posted as topics (replies).<br/></p>';
    echo '<p>Set to 0 for disabling this functionality.</p>';
  }

  function preprocess_comment( $commentdata  ) {
    global $wpdb;

    //var_dump( $commentdata ); die();

    $post_id  = $commentdata['comment_post_ID'];
    $forum_id = get_post_meta( $post_id, '_fv_bbpress_reply_forum_id', true );

    if( ! $forum_id ) {
      return $commentdata;
    }

    //author:
    if( $commentdata['user_ID'] == 0 ) {
      $this->comment_author = array (
        'bbp_anonymous_name'    => $commentdata['comment_author'],
        'bbp_anonymous_email'   => $commentdata['comment_author_email'],
        'bbp_anonymous_website' => $commentdata['comment_author_url']
      );
    }

    //topic:
    $post     = get_post( $commentdata['comment_post_ID'] );
    $title    = $post->post_title;
    $content  = $commentdata['comment_content'];

    $topic_id = $wpdb->get_var(
      "SELECT ID FROM {$wpdb->posts}
      WHERE post_title = '{$title}'
      AND post_parent = {$forum_id}"
    );

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
    }

    if( ! $forum_post_id ) {
      // something went wrong
      return false;
    }

    $url = get_permalink( $forum_post_id );
    if( ! $url ) {
      return false;
    }

    //var_dump( $forum_post_id, $url ); die();

    wp_redirect( $url );
    exit;
  }


  function bbp_filter_anonymous_post_data( $data ) {
    if( ! $this->comment_author ) {
      return $data;
    }

    return $this->comment_author;
  }


}
$fv_bbpress_comment_to_topic = new FvBbpressCommentToTopic();

?>