jQuery(function(){
  jQuery(document).on('click', '.bbp-reply-header .bbp-admin-links a.bbp-reply-approve-link,a.bbp-reply-trash-link,a.bbp-reply-restore-link', function(e){
    e.preventDefault();
    e.stopPropagation();

    var anchor = jQuery(this),
      current_class = anchor.attr('class'),
      current_url =   anchor.attr('href'),
      current_id =    anchor.closest('.bbp-reply-header').attr('id').replace('post-', '');

    console.log('FV-bbpress-tweaks: current url', current_url, 'current id', current_id);

    jQuery.get(current_url, function(response) {
      console.log('response', response);

      if ( current_class.indexOf('bbp-reply-approve-link') !== -1 ) { // approve
        // check if post is approved or not
        if ( jQuery('.post-' + current_id).hasClass('status-publish') ) {
          jQuery('.post-' + current_id).removeClass('status-publish').addClass('status-pending');
        } else {
          jQuery('.post-' + current_id).removeClass('status-unapproved').addClass('status-publish');
        }

      } else if( current_class.indexOf('bbp-reply-trash-link') !== -1 ) { // trash
        jQuery('.post-' + current_id).removeClass('status-publish').addClass('status-trash');
      } else if( current_class.indexOf('bbp-reply-restore-link') !== -1 ) { // restore
        jQuery('.post-' + current_id).removeClass('status-trash').addClass('status-publish');
      }

    }).fail(function() {
      console.error('Failed to get response from server');
    });

  });
});