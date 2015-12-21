<?php

add_action( 'bbp_theme_after_topic_form_submit_button', 'fv_search_before_post_script_enable' );

function fv_search_before_post_script_enable() {
  add_action( 'wp_footer', 'fv_search_before_post_script', 999 );
}


function fv_search_before_post_script() {
  ?>
  <script>
  jQuery( function($) {    
    if( $('.bbp-template-notice.error').length ){
      $('html, body').animate({
        scrollTop: $("#new-post").offset().top
      }, 100);
      return;
    }
    
    $('#new-post .bbp-the-content-wrapper').hide();
    $('#new-post .bbp-submit-wrapper').hide();
    $('#new-post .form-allowed-tags').hide();
    
    var did_search = false;
    var dont_run = false;
    
    $('#bbp_topic_title').keyup( function () {
      dont_run = true;
    });
    
    setInterval( function() {
      var input = $('#bbp_topic_title').val();
      
      if( dont_run ) {
        dont_run = false;
        return;
      }
      
      if( input.trim().length > 3 && did_search != input ){
        did_search = input;
        var loading_string = 'Searching...';
        var loading = setInterval( function () {
          message(loading_string);
          loading_string += '.';
        }, 200 );
        
        $.get( '/support/search/'+input, function(html) {
          clearInterval(loading);
          
          //$('#fv_search_before_post_messages').after( jQuery('#bbp-search-results .bbp-topic-permalink',html) );
          
          var button = "<input type='button' class='fv-bbp-show-new-post-form' value='Create new discussion' />";
          
          if( $('#bbp-search-results .bbp-topic-permalink',html ).length == 0 ){
            message("<p>Can't find what you are looking for, unfortunately. "+button+"</p>");
            return; 
          }
          
          message( "<p>Here's some existing discussions which might include your answer or be the right place for a follow up question.</p><ul id='fv_search_before_posting'></ul><p>Can't find what you are looking for? "+button+"</p>" );
          
          var uniqe_links = {};
          $('#bbp-search-results .bbp-topic-permalink',html ).each( function() {
            if( uniqe_links[$(this).attr('href')]) {
              return;
            }
            uniqe_links[$(this).attr('href')] = true;
            
            $(this).attr('target','_blank');
            $('#fv_search_before_posting').append('<li>').append( $(this) );             
          });
          
        });
        
      } else if( did_search != input ) {
        message('Your title is too short.');
        
      } else if( !did_search ) {
        message('Please enter the topic title.');
        
      }
      
    }, 333 );
    
    function message(message) {
      $('#fv_search_before_post_messages').html(message);
    }
    
    $(document).on('click','.fv-bbp-show-new-post-form', function() {
      $('#new-post .bbp-the-content-wrapper').show();
      $('#new-post .bbp-submit-wrapper').show();
      $('#new-post .form-allowed-tags').show();
    })
  });
  </script>  
  <?php
}


add_action( 'bbp_theme_after_topic_form_title', 'fv_search_before_post_messages' );

function fv_search_before_post_messages() {
  echo "<div id='fv_search_before_post_messages'></div>\n";
}