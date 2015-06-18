<?php

function fv_bbpress_option_dependencies( $aOptionDependencies ) {
               //run dependencies at page load
               foreach( $aOptionDependencies as $strParent => $aChildren ) {
                  fv_bbpress_option_deps_jq( $strParent, $aChildren, $aOptionDependencies );
               }
               fv_bbpress_option_deps_jq_complex( false );
?>
               //bind dependencies to the click event
<?php
               foreach( $aOptionDependencies as $strParent => $aChildren ) {
?>
                  jQuery('#<?php echo $strParent; ?>').change(function(){
<?php
                        fv_bbpress_option_deps_jq( $strParent, $aChildren, $aOptionDependencies );
                        fv_bbpress_option_deps_jq_complex( false );
?>
                  });
<?php
               }
               fv_bbpress_option_deps_jq_complex( true );
}


   function fv_bbpress_option_deps_jq( $strParent, $aChildren, $aOptionDependencies ) {
      $aDepsToRun = array();
?>
            if(!jQuery("#<?php echo $strParent; ?>").prop("disabled") && jQuery("#<?php echo $strParent; ?>").is(":checked")) {
<?php
               foreach( $aChildren as $strChild ) {
                  $strFlag = substr( $strChild, 0, 1 );
                  $strChild = trim( $strChild, '+-' );
                  if( $strFlag !== "+" ) {
                     continue;
                  }

                  $strJQVal = 'false';
                  $strJQLog = 'enabled';
                  if( isset( $aOptionDependencies[ $strChild ] ) && !isset( $aDepsToRun[$strChild] ) ) {
                     $aDepsToRun[$strChild] = $aOptionDependencies[ $strChild ];
                  }
?>
                  jQuery("#<?php echo $strChild; ?>").prop('disabled', <?php echo $strJQVal; ?>);
                  //console.log( "<?php echo $strChild; ?> has been <?php echo $strJQLog; ?>" );
<?php
               }
?>
            } else if( !jQuery("#<?php echo $strParent; ?>").prop("disabled") ) {
<?php
               foreach( $aChildren as $strChild ) {
                  $strFlag = substr( $strChild, 0, 1 );
                  $strChild = trim( $strChild, '+-' );
                  if( $strFlag !== "-" ) {
                     continue;
                  }

                  $strJQVal = 'true';
                  $strJQLog = 'disabled';
                  if( isset( $aOptionDependencies[ $strChild ] ) && !isset( $aDepsToRun[$strChild] ) ) {
                     $aDepsToRun[$strChild] = $aOptionDependencies[ $strChild ];
                  }
?>
                  jQuery("#<?php echo $strChild; ?>").prop('disabled', <?php echo $strJQVal; ?>);
                  //console.log( "<?php echo $strChild; ?> has been <?php echo $strJQLog; ?>" );
<?php
               }
?>
            }
<?php
      foreach( $aDepsToRun as $strParent => $aChildren ) {
         fv_bbpress_option_deps_jq( $strParent, $aChildren, $aOptionDependencies );
      }
   }

   function fv_bbpress_options_enable_all_jq( $aOptions ) {
      foreach( $aOptions as $strKey ) {
?>
         jQuery("#<?php echo $strKey; ?>").prop('disabled', false);
<?php
      }
   }

   function fv_bbpress_option_deps_jq_complex( $bBind = true ) {
      $strParent = "#participant_importing_welcome_email";
      if( $bBind ) {
?>
               jQuery( "<?php echo $strParent; ?>" ).change(function(){
<?php
      }
?>
                  if(!jQuery( "<?php echo $strParent; ?>" ).prop("disabled") && jQuery( "<?php echo $strParent; ?>" ).is(":checked")) {
                     if( jQuery( "#um_support" ).is(":checked") ) {
                        if( jQuery( "#um_auto_approve" ).is(":checked") ) {
                           var bDisabled = false, strStatus = "enabled";
                           var strChild = "#participant_importing_welcome_email_content";
                           jQuery( strChild ).prop('disabled', bDisabled );
                           //console.log( strChild + " has been " + strStatus );

                           var bDisabled = true, strStatus = "disabled";
                           var strChild = "#participant_importing_pending_email_content";
                           jQuery( strChild ).prop('disabled', bDisabled );
                           //console.log( strChild + " has been " + strStatus );
                        } else {
                           var bDisabled = true, strStatus = "disabled";
                           var strChild = "#participant_importing_welcome_email_content";
                           jQuery( strChild ).prop('disabled', bDisabled );
                           //console.log( strChild + " has been " + strStatus );

                           var bDisabled = false, strStatus = "enabled";
                           var strChild = "#participant_importing_pending_email_content";
                           jQuery( strChild ).prop('disabled', bDisabled );
                           //console.log( strChild + " has been " + strStatus );
                        }
                     } else {
                        var bDisabled = false, strStatus = "enabled";
                        var strChild = "#participant_importing_welcome_email_content";
                        jQuery( strChild ).prop('disabled', bDisabled );
                        //console.log( strChild + " has been " + strStatus );

                        var bDisabled = true, strStatus = "disabled";
                        var strChild = "#participant_importing_pending_email_content";
                        jQuery( strChild ).prop('disabled', bDisabled );
                        //console.log( strChild + " has been " + strStatus );
                     }
                  }
<?php
      if( $bBind ) {
?>
               });
<?php
      }
      $strParent = "#um_support";
      if( $bBind ) {
?>
               jQuery( "<?php echo $strParent; ?>" ).change(function(){
<?php
      }
?>
                  if(!jQuery( "<?php echo $strParent; ?>" ).prop("disabled") && jQuery( "<?php echo $strParent; ?>" ).is(":checked")) {
<?php
                     fv_bbpress_um_auto_approve_rules();
?>
                  } else if(!jQuery( "<?php echo $strParent; ?>" ).prop("disabled") ) {
                     if( jQuery( "#participant_importing_welcome_email" ).is(":checked") ) {
                        var bDisabled = false, strStatus = "enabled";
                        var strChild = "#participant_importing_welcome_email_content";
                        jQuery( strChild ).prop('disabled', bDisabled );
                        //console.log( strChild + " has been " + strStatus );
                     } else {
                        var bDisabled = true, strStatus = "disabled";
                        var strChild = "#participant_importing_welcome_email_content";
                        jQuery( strChild ).prop('disabled', bDisabled );
                        //console.log( strChild + " has been " + strStatus );
                     }
                  }
<?php
      if( $bBind ) {
?>
               });
<?php
      }
               $strParent = "#um_auto_approve";
      if( $bBind ) {
?>
               jQuery( "<?php echo $strParent; ?>" ).change(function(){
<?php
      }
                  fv_bbpress_um_auto_approve_rules();

      if( $bBind ) {
?>
               });
<?php
      }
   }


   function fv_bbpress_um_auto_approve_rules( $strParent = "#um_auto_approve" ) {
?>
                  if(!jQuery( "<?php echo $strParent; ?>" ).prop("disabled") && jQuery( "<?php echo $strParent; ?>" ).is(":checked")) {
                     if( jQuery( "#participant_importing_welcome_email" ).is(":checked") ) {
                        var bDisabled = false, strStatus = "enabled";
                        var strChild = "#participant_importing_welcome_email_content";
                        jQuery( strChild ).prop('disabled', bDisabled );
                        //console.log( strChild + " has been " + strStatus );

                        var bDisabled = true, strStatus = "disabled";
                        var strChild = "#participant_importing_pending_email_content";
                        jQuery( strChild ).prop('disabled', bDisabled );
                        //console.log( strChild + " has been " + strStatus );
                     } else {
                        var bDisabled = true, strStatus = "disabled";
                        var strChild = "#participant_importing_welcome_email_content";
                        jQuery( strChild ).prop('disabled', bDisabled );
                        //console.log( strChild + " has been " + strStatus );

//                         var bDisabled = true, strStatus = "disabled";
                        var strChild = "#participant_importing_pending_email_content";
                        jQuery( strChild ).prop('disabled', bDisabled );
                        //console.log( strChild + " has been " + strStatus );
                     }
                  } else if(!jQuery( "<?php echo $strParent; ?>" ).prop("disabled") ) {
                     if( jQuery( "#participant_importing_welcome_email" ).is(":checked") ) {
                        var bDisabled = true, strStatus = "disabled";
                        var strChild = "#participant_importing_welcome_email_content";
                        jQuery( strChild ).prop('disabled', bDisabled );
                        //console.log( strChild + " has been " + strStatus );

                        var bDisabled = false, strStatus = "enabled";
                        var strChild = "#participant_importing_pending_email_content";
                        jQuery( strChild ).prop('disabled', bDisabled );
                        //console.log( strChild + " has been " + strStatus );
                     } else {
                        var bDisabled = true, strStatus = "disabled";
                        var strChild = "#participant_importing_welcome_email_content";
                        jQuery( strChild ).prop('disabled', bDisabled );
                        //console.log( strChild + " has been " + strStatus );

//                         var bDisabled = true, strStatus = "disabled";
                        var strChild = "#participant_importing_pending_email_content";
                        jQuery( strChild ).prop('disabled', bDisabled );
                        //console.log( strChild + " has been " + strStatus );
                     }
                  }
<?php
   }
