jQuery(function(){
  jQuery(document).on('click', '.bbp-reply-header .bbp-admin-links a.bbp-reply-approve-link,a.bbp-reply-trash-link,a.bbp-reply-restore-link', function(e){
    e.preventDefault();
    e.stopPropagation();

    var anchor = jQuery(this),
      current_url =   anchor.attr('href'),
      current_id =    anchor.closest('.bbp-reply-header').attr('id').replace('post-', '');

    console.log('FV-bbpress-tweaks: current url', current_url, 'current id', current_id);

    jQuery.get(current_url, function(response) {
      // get the new header and body
      var new_head = jQuery(response).find('#post-' + current_id),
       new_body = jQuery(response).find('.post-' + current_id);

      // replace the old header and body
      jQuery('#post-' + current_id).replaceWith(new_head);
      jQuery('.post-' + current_id).replaceWith(new_body); 

    }).fail(function() {
      console.error('Failed to get response from server');
    });

  });
});