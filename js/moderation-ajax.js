jQuery(function(){
  jQuery(document).on('click', '.bbp-admin-links a.bbp-reply-approve-link,a.bbp-reply-trash-link,a.bbp-reply-restore-link,a.bbp-reply-edit-link,a.bbp-topic-approve-link,a.bbp-topic-trash-link,a.bbp-topic-restore-link,a.bbp-topic-edit-link', function(e){
    e.preventDefault();
    e.stopPropagation();

    var anchor = jQuery(this),
      current_url   =   anchor.attr('href'),
      current_class = anchor.attr('class'),
      current_id,
      is_topic = false;

    if( anchor.closest('.bbp-reply-header').length ) {
      current_id = anchor.closest('.bbp-reply-header').attr('id').replace('post-', '');
    } else if( anchor.closest('.bbp-lead-topic').length ) {
      current_id = anchor.closest('.bbp-lead-topic').attr('id').replace('bbp-topic-', '').replace('-lead', '');
      is_topic = true;
    } else {
      console.error('Unable to find the post id');
      return;
    }

    console.log('FV-bbpress-tweaks: current url', current_url, 'current id', current_id);

    jQuery.get(current_url, function(response) {
      // get the new header and body
      var new_head = jQuery(response).find('div#post-' + current_id),
       new_body = jQuery(response).find('div.post-' + current_id);

      if( current_class == 'bbp-reply-edit-link' || current_class == 'bbp-topic-edit-link' ) {
        // get lead topic 
        var lead_id = jQuery('.bbp-lead-topic').attr('id');
        lead_id = lead_id.replace('bbp-topic-', '');
        lead_id = lead_id.replace('-lead', '');
        
        var form;

        if( current_class == 'bbp-reply-edit-link' ) {
          form = jQuery(response).find('#new-reply-' + lead_id)
        } else {
          form = jQuery(response).find('#new-topic-' + lead_id)
        }

        if( is_topic ) {
          jQuery('div#post-' + current_id).replaceWith(form);
        } else {
          jQuery('.post-' + current_id).replaceWith(form);
          jQuery('#post-' + current_id).hide(); // hide the header
        }

      } else {
        if( new_head.length == 0 || new_body.length == 0 ) {
          alert( "Unable to parse response, please reload the page to see if your change was saved properly.")
          return;
        }

        // replace the old header and body
        if( is_topic ) {
          jQuery('div#post-' + current_id).replaceWith(new_head);
        } else {
          jQuery('#post-' + current_id).replaceWith(new_head);
          jQuery('.post-' + current_id).replaceWith(new_body); 
        }
      }

    }).fail(function() {
      console.error('Failed to get response from server');
    });

  });
});