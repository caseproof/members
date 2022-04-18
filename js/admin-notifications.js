jQuery(document).ready(function($) {

  $('#membersAdminHeaderNotifications').on('click', function(e) {
    e.preventDefault();
    $('#members-notifications').toggleClass('visible');
    $('#caseproofFlyoutButton').trigger('click');
  });
  $('#membersNotificationsClose').on('click', function(e) {
    e.preventDefault();
    $('#members-notifications').removeClass('visible');
  });

  var viewDismissed = $('#viewDismissed');
  var viewActive = $('#viewActive');
  var dismissedMessages = $('.dismissed-messages');
  var activeMessages = $('.active-messages');

  viewDismissed.on('click', function(event) {
    event.preventDefault();
    dismissedMessages.show();
    activeMessages.hide();
    viewActive.show();
    viewDismissed.hide();
  });
  viewActive.on('click', function(event) {
    event.preventDefault();
    dismissedMessages.hide();
    activeMessages.show();
    viewActive.hide();
    viewDismissed.show();
  });

  $('body').on('click', '.members-notice-dismiss', function(event) {

    event.preventDefault();

    var $this = $(this);
    var messageId = $this.data('message-id');
    var message = $('#members-notifications-message-' + messageId);
    var countEl = $('#membersAdminMenuUnreadCount');
    var mainCountEl = $('#membersAdminHeaderNotificationsCount');
    var trayCountEl = $('#membersNotificationsCountTray');
    var count = parseInt(mainCountEl.html());
    var adminMenuCount = $('#membersAdminMenuUnreadCount');

    var data = {
      action: 'members_notification_dismiss',
      nonce: MembersAdminNotifications.nonce,
      id: messageId,
    };

    $this.prop('disabled', 'disabled');
    message.fadeOut();

    $.post( MembersAdminNotifications.ajax_url, data, function( res ) {

      if ( ! res.success ) {
        console.debug( res );
      } else {
        message.prependTo(dismissedMessages);
        message.show();
        count--;

        if ( count < 0 ) {
          count = 0;
          countEl.hide();
          mainCountEl.hide();
          trayCountEl.hide();
          adminMenuCount.closest('.awaiting-mod').remove();
        } else if ( 0 == count ) {
          countEl.hide();
          mainCountEl.hide();
          trayCountEl.hide();
          $('.members-notifications-none').show();
          $('.dismiss-all').hide();
          adminMenuCount.closest('.awaiting-mod').remove();
        } else if ( count < 10 ) {
          countEl.addClass('single-digit');
          countEl.html('(' + count + ')');
          mainCountEl.html(count);
          trayCountEl.html(count);
          adminMenuCount.html(count);
        } else {
          countEl.html('(' + count + ')');
          mainCountEl.html(count);
          trayCountEl.html(count);
          adminMenuCount.html(count);
        }
      }

    } ).fail( function( xhr, textStatus, e ) {

      console.debug( xhr.responseText );
      message.show('Message could not be dismissed.');
    } );
  });

  $('body').on('click', '.dismiss-all' ,function(event) {

    event.preventDefault();

    var $this = $(this);
    var mainCountEl = $('#membersAdminHeaderNotificationsCount');
    var trayCountEl = $('#membersNotificationsCountTray');
    var count = parseInt(mainCountEl.html());
    var adminMenuCount = $('#membersAdminMenuUnreadCount');

    var data = {
      action: 'members_notification_dismiss',
      nonce: MembersAdminNotifications.nonce,
      id: 'all',
    };

    $this.prop('disabled', 'disabled');

    $.post( MembersAdminNotifications.ajax_url, data, function( res ) {

      if ( ! res.success ) {
        console.debug( res );
      } else {
        mainCountEl.hide();
        trayCountEl.hide();
        adminMenuCount.closest('.awaiting-mod').remove();
        $('.members-notifications-none').show();
        $('.dismiss-all').hide();

        $.each($('.active-messages .members-notifications-message'), function(i, el) {
          $(el).appendTo(dismissedMessages);
        });
      }

    } ).fail( function( xhr, textStatus, e ) {

      console.debug( xhr.responseText );
      message.show('Messages could not be dismissed.');
    } );
  });
});