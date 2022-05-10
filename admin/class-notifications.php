<?php
namespace Members\Admin;

if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

/**
 * PrliNotifications.
 *
 * Class for logging in-plugin notifications.
 * Includes:
 *     Notifications from our remote feed
 *     Plugin-related notifications (e.g. recent sales performances)
 */
class Notifications {

  /**
   * Source of notifications content.
   *
   * @var string
   */
  const SOURCE_URL = 'https://mbr.press/hWndcw';
  const SOURCE_URL_ARGS = [];

  /**
   * Option value.
   *
   * @var bool|array
   */
  public $option = false;

  public $members_settings;

  /**
   * Initialize class.
   */
  public function init() {

    add_action( 'admin_enqueue_scripts', [ $this, 'enqueues' ] );
    add_action( 'admin_footer', array( $this, 'admin_menu_append_count' ) );
    add_action( 'admin_init', array( $this, 'schedule_fetch' ) );

    add_action( 'admin_footer', [ $this, 'output' ] );

    add_action( 'members_admin_notifications_update', [ $this, 'update' ] );
    add_action( 'wp_ajax_members_notification_dismiss', [ $this, 'dismiss' ] );
  }

  /**
   * Check if user has access and is enabled.
   *
   * @return bool
   */
  public static function has_access() {

    $access = false;

    if (
      current_user_can( 'manage_options' )
      && ! get_option( 'members_hide_announcements' )
    ) {
      $access = true;
    }

    return apply_filters( 'members_admin_notifications_has_access', $access );
  }

  /**
   * Get option value.
   *
   * @param bool   $cache   Reference property cache if available.
   *
   * @return array
   */
  public function get_option( $cache = true ) {

    if ( $this->option && $cache ) {
      return $this->option;
    }

    $option = get_option( 'members_notifications', [] );

    $this->option = [
      'update'    => ! empty( $option['update'] ) ? $option['update'] : 0,
      'events'    => ! empty( $option['events'] ) ? $option['events'] : [],
      'feed'      => ! empty( $option['feed'] ) ? $option['feed'] : [],
      'dismissed' => ! empty( $option['dismissed'] ) ? $option['dismissed'] : [],
    ];

    return $this->option;
  }

  /**
   * Make sure the feed is fetched when needed.
   *
   * @return void
   */
  public function schedule_fetch() {

    $option = $this->get_option();

    // Update notifications using async task.
    if ( empty( $option['update'] ) || time() > $option['update'] + 3 * HOUR_IN_SECONDS ) {
      if ( false === wp_next_scheduled( 'members_admin_notifications_update' ) ) {
        wp_schedule_single_event( time() + 10, 'members_admin_notifications_update' );
      }
    }
  }

  /**
   * Fetch notifications from remote feed.
   *
   * @return array
   */
  public function fetch_feed() {

    $res = wp_remote_get( self::SOURCE_URL, self::SOURCE_URL_ARGS );

    if ( is_wp_error( $res ) ) {
      return [];
    }

    $body = wp_remote_retrieve_body( $res );

    if ( empty( $body ) ) {
      return [];
    }

    return $this->verify( json_decode( $body, true ) );
  }

  /**
   * Verify notification data before it is saved.
   *
   * @param array   $notifications   Array of notifications items to verify.
   *
   * @return array
   */
  public function verify( $notifications ) {

    $data = [];

    if ( ! is_array( $notifications ) || empty( $notifications ) ) {
      return $data;
    }

    $option = $this->get_option();

    foreach ( $notifications as $id => $notification ) {

      // The message should never be empty - if is, ignore.
      if ( empty( $notification['content'] ) ) {
        continue;
      }

      // Ignore if expired.
      if ( ! empty( $notification['end'] ) && time() > strtotime( $notification['end'] ) ) {
        continue;
      }

      // Ignore if notifcation has already been dismissed.
      if ( ! empty( $option['dismissed'] ) && array_key_exists( $notification['id'], $option['dismissed'] ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
        continue;
      }

      // Ignore if notification existed before installing the plugin.
      // Prevents bombarding the user with notifications after activation.
      $activated = get_option( 'members_activated' );

      if ( empty( $activated ) ) {
        $activated = time();
        update_option( 'members_activated', $activated );
      }

      if (
        ! empty( $activated ) &&
        ! empty( $notification['start'] ) &&
        $activated > strtotime( $notification['start'] )
      ) {
        continue;
      }

      $data[$id] = $notification;

      // Check if this notification has already been saved with a timestamp
      if ( ! empty( $option['feed'][$id] ) ) { // Already exists in feed, so use saved time
        $data[$id]['saved'] = $option['feed'][$id]['saved'];
      } else if ( ! empty( $option['events'][$id] ) ) { // Already exists in events, so use saved time
        $data[$id]['saved'] = $option['events'][$id]['saved'];
      } else { // Doesn't exist in feed or events, so save current time
        $data[$id]['saved'] = time();
      }
    }

    return $data;
  }

  /**
   * Verify saved notification data for active notifications.
   *
   * @param array   $notifications   Array of notifications items to verify.
   *
   * @return array
   */
  public function verify_active( $notifications ) {

    if ( ! is_array( $notifications ) || empty( $notifications ) ) {
      return [];
    }

    // Remove notfications that are not active.
    foreach ( $notifications as $key => $notification ) {
      if (
        ( ! empty( $notification['start'] ) && strtotime( $notification['start'] . ' America/Denver' ) < strtotime( $notification['start'] . ' America/Denver' ) ) ||
        ( ! empty( $notification['end'] ) && strtotime( $notification['end'] . ' America/Denver' ) > strtotime( $notification['end'] . ' America/Denver' ) )
      ) {
        unset( $notifications[ $key ] );
      }
    }

    return $notifications;
  }

  /**
   * Get notification data.
   *
   * @return array
   */
  public function get() {

    if ( ! self::has_access() ) {
      return [];
    }

    $option = $this->get_option();

    $events = ! empty( $option['events'] ) ? $this->verify_active( $option['events'] ) : array();
    $feed   = ! empty( $option['feed'] ) ? $this->verify_active( $option['feed'] ) : array();

    $notifications              = array();
    $notifications['active']    = array_merge( $events, $feed );
    $notifications['active']    = $this->get_notifications_with_human_readeable_start_time( $notifications['active'] );
    $notifications['active']    = $this->get_notifications_with_formatted_content( $notifications['active'] );
    $notifications['dismissed'] = ! empty( $option['dismissed'] ) ? $option['dismissed'] : array();
    $notifications['dismissed'] = $this->get_notifications_with_human_readeable_start_time( $notifications['dismissed'] );
    $notifications['dismissed'] = $this->get_notifications_with_formatted_content( $notifications['dismissed'] );

    return $notifications;
  }


  /**
   * Improve format of the content of notifications before display. By default just runs wpautop.
   *
   * @param array   $notifications   The notifications to be parsed.
   *
   * @return mixed
   */
  public function get_notifications_with_formatted_content( $notifications ) {
    if ( ! is_array( $notifications ) || empty( $notifications ) ) {
      return $notifications;
    }

    foreach ( $notifications as $key => $notification ) {
      if ( ! empty( $notification['content'] ) ) {
        $notifications[ $key ]['content'] = wpautop( $notification['content'] );
        $notifications[ $key ]['content'] = apply_filters( 'members_notification_content_display', $notifications[ $key ]['content'] );
      }
    }

    return $notifications;
  }

  /**
   * Get notifications start time with human time difference
   *
   * @return array $notifications
   */
  public function get_notifications_with_human_readeable_start_time( $notifications ) {

    if ( ! is_array( $notifications ) || empty( $notifications ) ) {
      return;
    }

    foreach ( $notifications as $key => $notification ) {
      if ( ! isset( $notification['start'] ) || empty( $notification['start'] ) ) {
        continue;
      }

      // Translators: Readable time to display
      $modified_start_time            = sprintf( __( '%1$s ago', 'members' ), human_time_diff( strtotime( $notification['start'] ), current_time( 'timestamp' ) ) );
      $notifications[ $key ]['start'] = $modified_start_time;
    }

    return $notifications;
  }

  /**
   * Get notification count.
   *
   * @return int
   */
  public function get_count() {
    return ! empty( $this->get()['active'] ) ? count( $this->get()['active'] ) : 0;
  }

  /**
   * Add an event notification. This is NOT for feed notifications.
   * Event notifications are for alerting the user to something internally (e.g. recent sales performances).
   *
   * @param array   $notification   Notification data.
   */
  public function add( $notification ) {

    if ( empty( $notification['id'] ) ) {
      return;
    }

    $option = $this->get_option();

    // Already dismissed
    if ( array_key_exists( $notification['id'], $option['dismissed'] ) ) {
      return;
    }

    // Already in events
    foreach ( $option['events'] as $item ) {
      if ( $item['id'] === $notification['id'] ) {
        return;
      }
    }

    // Associative key is notification id.
    $notification = $this->verify( [ $notification['id'] => $notification ] );

    // The only thing changing here is adding the notification to the events
    update_option(
      'members_notifications',
      [
        'update'    => $option['update'],
        'feed'      => $option['feed'],
        'events'    => array_merge( $notification, $option['events'] ),
        'dismissed' => $option['dismissed'],
      ]
    );
  }

  /**
   * Update notification data from feed.
   * This pulls the latest notifications from our remote feed.
   */
  public function update() {

    $feed   = $this->fetch_feed();
    $option = $this->get_option();

    update_option(
      'members_notifications',
      [
        'update'    => time(),
        'feed'      => $feed,
        'events'    => $option['events'],
        'dismissed' => $option['dismissed'],
      ]
    );
  }

  /**
   * Admin area enqueues.
   */
  public function enqueues() {

    if ( ! self::has_access() || ! members_is_admin_page() ) {
      return;
    }

    $notifications = $this->get();

    if ( empty( $notifications ) ) {
      return;
    }

    wp_enqueue_style(
      'members-admin-notifications',
      members_plugin()->uri . "css/admin-notifications.css",
      [],
      ''
    );

    wp_enqueue_script(
      'members-admin-notifications',
      members_plugin()->uri . "js/admin-notifications.js",
      [ 'jquery' ],
      '',
      true
    );

    wp_localize_script(
      'members-admin-notifications',
      'MembersAdminNotifications',
      [
        "ajax_url" => admin_url('admin-ajax.php'),
        "nonce" => wp_create_nonce( "members-admin-notifications" )
      ]
    );
  }

  /**
   * Admin script for adding notification count to the MemberPress admin menu list item.
   */
  public function admin_menu_append_count() {

    $option = get_option( 'members_notifications' );
    $notifications = ! empty( $option['feed'] ) ? $option['feed'] : array();
    $active_notifications = isset( $this->get()['active'] ) ? $this->get()['active'] : array();

    if ( ! empty( $active_notifications ) && count( $active_notifications ) > 0 ) {
      ob_start();

      ?>

      <span class="awaiting-mod">
        <span class="pending-count" id="membersAdminMenuUnreadCount" aria-hidden="true"><?php echo count( $active_notifications ); ?></span>
        <span class="comments-in-moderation-text screen-reader-text"><?php printf( _n( '%s unread message', '%s unread messages', count( $active_notifications ), 'members' ), count( $active_notifications ) ); ?></span>
      </span>

      <?php $admin_menu_output = ob_get_clean(); ?>

      <script>
        jQuery(document).ready(function($) {
          $('li.toplevel_page_members .wp-menu-name').append(`<?php echo $admin_menu_output; ?>`);
        });
      </script>
      <?php
    }

    if ( members_is_admin_page() ) {
      ob_start();

      ?>

      <button id="membersAdminHeaderNotifications" class="members-notifications-button button-secondary">
        <svg width="22" height="14" viewBox="0 0 22 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21.6944 6.5625C21.8981 6.85417 22 7.18229 22 7.54687V12.25C22 12.7361 21.8218 13.1493 21.4653 13.4896C21.1088 13.8299 20.6759 14 20.1667 14H1.83333C1.32407 14 0.891204 13.8299 0.534722 13.4896C0.178241 13.1493 0 12.7361 0 12.25V7.54687C0 7.18229 0.101852 6.85417 0.305556 6.5625L4.35417 0.765625C4.45602 0.644097 4.58333 0.522569 4.73611 0.401042C4.91435 0.279514 5.10532 0.182292 5.30903 0.109375C5.51273 0.0364583 5.7037 0 5.88194 0H16.1181C16.3981 0 16.6782 0.0850694 16.9583 0.255208C17.2639 0.401042 17.4931 0.571181 17.6458 0.765625L21.6944 6.5625ZM6.1875 2.33333L2.94097 7H7.63889L8.86111 9.33333H13.1389L14.3611 7H19.059L15.8125 2.33333H6.1875Z" fill="#2679C1"></path></svg>
        <?php if ( self::has_access() && ! empty( $notifications ) && count( $notifications ) > 0 ) : ?>
          <span id="membersAdminHeaderNotificationsCount" class="members-notifications-count"><?php echo count( $notifications ); ?></span>
        <?php endif; ?>
      </button>

      <?php $messages_toggle_output = ob_get_clean(); ?>

      <script>
        jQuery(document).ready(function($) {
          $('#wpbody-content .wrap > h1').append(`<?php echo $messages_toggle_output; ?>`);
        });
      </script>
      <?php
    }
  }

  /**
   * Output notifications in MemberPress admin area.
   */
  public function output() {

    // Only run on a Members page
    if ( ! members_is_admin_page() ) {
      return;
    }

    $notifications = $this->get();

    if ( empty( $notifications['active'] ) && empty( $notifications['dismissed'] ) ) {
      return;
    }

    $notifications_html = '<div class="active-messages">';
      if ( ! empty( $notifications['active'] ) ) {
        foreach ( $notifications['active'] as $notification ) {

          // Buttons HTML.
          $buttons_html = '';
          if ( ! empty( $notification['buttons'] ) && is_array( $notification['buttons'] ) ) {
            foreach ( $notification['buttons'] as $btn_type => $btn ) {
              if ( empty( $btn['url'] ) || empty( $btn['text'] ) ) {
                continue;
              }
              $buttons_html .= sprintf(
                '<a href="%1$s" class="button button-%2$s"%3$s>%4$s</a>',
                ! empty( $btn['url'] ) ? esc_url( $btn['url'] ) : '',
                $btn_type === 'main' ? 'primary' : 'secondary',
                ! empty( $btn['target'] ) && $btn['target'] === '_blank' ? ' target="_blank" rel="noopener noreferrer"' : '',
                ! empty( $btn['text'] ) ? sanitize_text_field( $btn['text'] ) : ''
              );
            }
            $buttons_html .= sprintf( '<button class="members-notice-dismiss" data-message-id="%s">%s</button>', $notification['id'], __( 'Dismiss', 'members' ) );
            $buttons_html = ! empty( $buttons_html ) ? '<div class="members-notifications-buttons">' . $buttons_html . '</div>' : '';
          }

          $time_diff = ceil( ( time() - $notification['saved'] ) );
          $time_diff_string = '';
          if ( $time_diff < MINUTE_IN_SECONDS ) {
            $time_diff_string = sprintf( _n( '%s second ago', '%s seconds ago', $time_diff, 'members' ), $time_diff );
          } else if ( $time_diff < HOUR_IN_SECONDS ) {
            $time_diff_string = sprintf( _n( '%s minute ago', '%s minutes ago', ceil( ( $time_diff / MINUTE_IN_SECONDS ) ), 'members' ), ceil( ( $time_diff / MINUTE_IN_SECONDS ) ) );
          } else if ( $time_diff < DAY_IN_SECONDS ) {
            $time_diff_string = sprintf( _n( '%s hour ago', '%s hours ago', ceil( ( $time_diff / HOUR_IN_SECONDS ) ), 'members' ), ceil( ( $time_diff / HOUR_IN_SECONDS ) ) );
          } else if ( $time_diff < WEEK_IN_SECONDS ) {
            $time_diff_string = sprintf( _n( '%s day ago', '%s days ago', ceil( ( $time_diff / DAY_IN_SECONDS ) ), 'members' ), ceil( ( $time_diff / DAY_IN_SECONDS ) ) );
          } else if ( $time_diff < MONTH_IN_SECONDS ) {
            $time_diff_string = sprintf( _n( '%s week ago', '%s weeks ago', ceil( ( $time_diff / WEEK_IN_SECONDS ) ), 'members' ), ceil( ( $time_diff / WEEK_IN_SECONDS ) ) );
          } else if ( $time_diff < YEAR_IN_SECONDS ) {
            $time_diff_string = sprintf( _n( '%s month ago', '%s months ago', ceil( ( $time_diff / MONTH_IN_SECONDS ) ), 'members' ), ceil( ( $time_diff / MONTH_IN_SECONDS ) ) );
          } else {
            $time_diff_string = sprintf( _n( '%s year ago', '%s years ago', ceil( ( $time_diff / YEAR_IN_SECONDS ) ), 'members' ), ceil( ( $time_diff / YEAR_IN_SECONDS ) ) );
          }

          // Notification HTML.
          $notifications_html .= sprintf(
            '<div id="members-notifications-message-%4$s" class="members-notifications-message" data-message-id="%4$s">
              <div class="members-notification-icon-title">
              <img src="%5$s" width="32" height="32">
              <h3 class="members-notifications-title">%1$s</h3>
              <time datetime="%6$s">%7$s</time>
              </div>
              <div class="members-notifications-content">%2$s</div>
              %3$s
            </div>',
            ! empty( $notification['title'] ) ? sanitize_text_field( $notification['title'] ) : '',
            ! empty( $notification['content'] ) ? apply_filters( 'the_content', $notification['content'] ) : '',
            $buttons_html,
            ! empty( $notification['id'] ) ? esc_attr( sanitize_text_field( $notification['id'] ) ) : 0,
            ! empty( $notification['icon'] ) ? esc_url( sanitize_text_field( $notification['icon'] ) ) : '',
            date( 'Y-m-d G:i a', $notification['saved'] ),
            $time_diff_string
          );
        }
      }
      $notifications_html .= sprintf( '<div class="members-notifications-none" %s>%s</div>', empty( $notifications['active'] ) || count( $notifications['active'] ) < 1 ? '' : 'style="display: none;"', __( 'You\'re all caught up!', 'members' ) );
    $notifications_html .=  '</div>';

    $notifications_html .= '<div class="dismissed-messages">';
      if ( ! empty( $notifications['dismissed'] ) ) {
        foreach ( $notifications['dismissed'] as $notification ) {

          // Buttons HTML.
          $buttons_html = '';
          if ( ! empty( $notification['buttons'] ) && is_array( $notification['buttons'] ) ) {
            foreach ( $notification['buttons'] as $btn_type => $btn ) {
              if ( empty( $btn['url'] ) || empty( $btn['text'] ) ) {
                continue;
              }
              $buttons_html .= sprintf(
                '<a href="%1$s" class="button button-%2$s"%3$s>%4$s</a>',
                ! empty( $btn['url'] ) ? esc_url( $btn['url'] ) : '',
                $btn_type === 'main' ? 'primary' : 'secondary',
                ! empty( $btn['target'] ) && $btn['target'] === '_blank' ? ' target="_blank" rel="noopener noreferrer"' : '',
                ! empty( $btn['text'] ) ? sanitize_text_field( $btn['text'] ) : ''
              );
            }
            $buttons_html .= sprintf( '<button class="members-notice-dismiss" data-message-id="%s">%s</button>', $notification['id'], __( 'Dismiss', 'members' ) );
            $buttons_html = ! empty( $buttons_html ) ? '<div class="members-notifications-buttons">' . $buttons_html . '</div>' : '';
          }

          $time_diff = ceil( ( time() - $notification['saved'] ) );
          $time_diff_string = '';
          if ( $time_diff < MINUTE_IN_SECONDS ) {
            $time_diff_string = sprintf( _n( '%s second ago', '%s seconds ago', $time_diff, 'members' ), $time_diff );
          } else if ( $time_diff < HOUR_IN_SECONDS ) {
            $time_diff_string = sprintf( _n( '%s minute ago', '%s minutes ago', ceil( ( $time_diff / MINUTE_IN_SECONDS ) ), 'members' ), ceil( ( $time_diff / MINUTE_IN_SECONDS ) ) );
          } else if ( $time_diff < DAY_IN_SECONDS ) {
            $time_diff_string = sprintf( _n( '%s hour ago', '%s hours ago', ceil( ( $time_diff / HOUR_IN_SECONDS ) ), 'members' ), ceil( ( $time_diff / HOUR_IN_SECONDS ) ) );
          } else if ( $time_diff < WEEK_IN_SECONDS ) {
            $time_diff_string = sprintf( _n( '%s day ago', '%s days ago', ceil( ( $time_diff / DAY_IN_SECONDS ) ), 'members' ), ceil( ( $time_diff / DAY_IN_SECONDS ) ) );
          } else if ( $time_diff < MONTH_IN_SECONDS ) {
            $time_diff_string = sprintf( _n( '%s week ago', '%s weeks ago', ceil( ( $time_diff / WEEK_IN_SECONDS ) ), 'members' ), ceil( ( $time_diff / WEEK_IN_SECONDS ) ) );
          } else if ( $time_diff < YEAR_IN_SECONDS ) {
            $time_diff_string = sprintf( _n( '%s month ago', '%s months ago', ceil( ( $time_diff / MONTH_IN_SECONDS ) ), 'members' ), ceil( ( $time_diff / MONTH_IN_SECONDS ) ) );
          } else {
            $time_diff_string = sprintf( _n( '%s year ago', '%s years ago', ceil( ( $time_diff / YEAR_IN_SECONDS ) ), 'members' ), ceil( ( $time_diff / YEAR_IN_SECONDS ) ) );
          }

          // Notification HTML.
          $notifications_html .= sprintf(
            '<div id="members-notifications-message-%4$s" class="members-notifications-message" data-message-id="%4$s">
              <div class="members-notification-icon-title">
              <img src="%5$s" width="32" height="32">
              <h3 class="members-notifications-title">%1$s</h3>
              <time datetime="%6$s">%7$s</time>
              </div>
              <div class="members-notifications-content">%2$s</div>
              %3$s
            </div>',
            ! empty( $notification['title'] ) ? sanitize_text_field( $notification['title'] ) : '',
            ! empty( $notification['content'] ) ? apply_filters( 'the_content', $notification['content'] ) : '',
            $buttons_html,
            ! empty( $notification['id'] ) ? esc_attr( sanitize_text_field( $notification['id'] ) ) : 0,
            ! empty( $notification['icon'] ) ? esc_url( sanitize_text_field( $notification['icon'] ) ) : '',
            date( 'Y-m-d G:i a', $notification['saved'] ),
            $time_diff_string
          );
        }
      }
    $notifications_html .=  '</div>';
    ?>

    <div id="members-notifications">

        <div class="members-notifications-container">

          <div class="members-notifications-top-title">
            <div class="members-notifications-top-title__left">
              <svg width="24" height="15" viewBox="0 0 24 15" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M23.6667 7.03125C23.8889 7.34375 24 7.69531 24 8.08594V13.125C24 13.6458 23.8056 14.0885 23.4167 14.4531C23.0278 14.8177 22.5556 15 22 15H2C1.44444 15 0.972222 14.8177 0.583333 14.4531C0.194444 14.0885 0 13.6458 0 13.125V8.08594C0 7.69531 0.111111 7.34375 0.333333 7.03125L4.75 0.820312C4.86111 0.690104 5 0.559896 5.16667 0.429688C5.36111 0.299479 5.56944 0.195312 5.79167 0.117188C6.01389 0.0390625 6.22222 0 6.41667 0H17.5833C17.8889 0 18.1944 0.0911458 18.5 0.273438C18.8333 0.429688 19.0833 0.611979 19.25 0.820312L23.6667 7.03125ZM6.75 2.5L3.20833 7.5H8.33333L9.66667 10H14.3333L15.6667 7.5H20.7917L17.25 2.5H6.75Z" fill="white"></path></svg>
              <h3><?php _e( 'Inbox', 'members' ); ?></h3>
            </div>
            <div class="members-notifications-top-title__right actions">
              <a href="#" id="viewDismissed"><?php _e( 'View Dismissed', 'members' ); ?></a>
              <a href="#" id="viewActive"><?php _e( 'View Active', 'members' ); ?></a>
              <a href="#" id="membersNotificationsClose" class="close" title="<?php _e( 'Close', 'members' ); ?>">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8.28409 6L11.6932 9.40909C11.8977 9.61364 12 9.86364 12 10.1591C12 10.4545 11.8977 10.7159 11.6932 10.9432L10.9432 11.6932C10.7159 11.8977 10.4545 12 10.1591 12C9.86364 12 9.61364 11.8977 9.40909 11.6932L6 8.28409L2.59091 11.6932C2.38636 11.8977 2.13636 12 1.84091 12C1.54545 12 1.28409 11.8977 1.05682 11.6932L0.306818 10.9432C0.102273 10.7159 0 10.4545 0 10.1591C0 9.86364 0.102273 9.61364 0.306818 9.40909L3.71591 6L0.306818 2.59091C0.102273 2.38636 0 2.13636 0 1.84091C0 1.54545 0.102273 1.28409 0.306818 1.05682L1.05682 0.306818C1.28409 0.102273 1.54545 0 1.84091 0C2.13636 0 2.38636 0.102273 2.59091 0.306818L6 3.71591L9.40909 0.306818C9.61364 0.102273 9.86364 0 10.1591 0C10.4545 0 10.7159 0.102273 10.9432 0.306818L11.6932 1.05682C11.8977 1.28409 12 1.54545 12 1.84091C12 2.13636 11.8977 2.38636 11.6932 2.59091L8.28409 6Z" fill="white"></path></svg>
              </a>
            </div>
          </div>
          <div class="members-notifications-header <?php echo ! empty( $notifications['active'] ) && count( $notifications['active'] ) < 10 ? 'single-digit' : ''; ?>">
            <div class="members-notifications-header-bell">
              <div class="members-notifications-bell">
                <svg viewBox="0 0 512 512" width="30" xmlns="http://www.w3.org/2000/svg"><path fill="#777777" d="m381.7 225.9c0-97.6-52.5-130.8-101.6-138.2 0-.5.1-1 .1-1.6 0-12.3-10.9-22.1-24.2-22.1s-23.8 9.8-23.8 22.1c0 .6 0 1.1.1 1.6-49.2 7.5-102 40.8-102 138.4 0 113.8-28.3 126-66.3 158h384c-37.8-32.1-66.3-44.4-66.3-158.2z"/><path fill="#777777" d="m256.2 448c26.8 0 48.8-19.9 51.7-43h-103.4c2.8 23.1 24.9 43 51.7 43z"/></svg>
                <?php if ( ! empty( $notifications['active'] ) ) : ?>
                  <span id="membersNotificationsCountTray" class="members-notifications-count"><?php echo count( $notifications['active'] ); ?></span>
                <?php endif; ?>
              </div>
              <div class="members-notifications-title"><?php esc_html_e( 'Notifications', 'members' ); ?></div>
            </div>
            <?php if ( ! empty( $notifications['active'] ) ) : ?>
              <button id="dismissAll" class="dismiss-all"><?php _e( 'Dismiss All', 'members' ); ?></button>
            <?php endif; ?>
          </div>

          <div class="members-notifications-body">
            <div class="members-notifications-messages">
              <?php echo $notifications_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
          </div>

        </div>

    </div>
    <?php
  }

  /**
   * Dismiss notification(s) via AJAX.
   */
  public function dismiss() {

    // Run a security check.
    check_ajax_referer( 'members-admin-notifications', 'nonce' );

    // Check for access and required param.
    if ( ! self::has_access() || empty( $_POST['id'] ) ) {
      wp_send_json_error();
    }

    $id = sanitize_text_field( wp_unslash( $_POST['id'] ) );
    $option = $this->get_option();

    if ( 'all' === $id ) { // Dismiss all notifications

      // Feed notifications
      if ( ! empty( $option['feed'] ) ) {
        foreach ( $option['feed'] as $key => $notification ) {
          $option['dismissed'][$key] = $option['feed'][$key];
          unset( $option['feed'][$key] );
        }
      }

      // Event notifications
      if ( ! empty( $option['events'] ) ) {
        foreach ( $option['events'] as $key => $notification ) {
          $option['dismissed'][$key] = $option['events'][$key];
          unset( $option['events'][$key] );
        }
      }

    } else { // Dismiss one notification

      // Event notifications need a prefix to distinguish them from feed notifications
      // For a naming convention, we'll use "event_{timestamp}"
      // If the notification ID includes "event_", we know it's an even notification
      $type = false !== strpos( $id, 'event_' ) ? 'events' : 'feed';

      if( $type == 'events' ){
        if( !empty($option[$type]) ){
            foreach( $option[$type] as $index => $event_notification ){
               if( $event_notification['id'] == $id ){
                  unset( $option[$type][$index] );
                  break;
               }
            }
        }
      }else{
        if ( ! empty( $option[$type][$id] ) ) {
          $option['dismissed'][$id] = $option[$type][$id];
          unset( $option[$type][$id] );
        }
      }
    }


    update_option( 'members_notifications', $option );

    wp_send_json_success();
  }

  public function dismiss_events( $type ) {

    $option = $this->get_option();

    // Event notifications.
    if ( ! empty( $option['events'] ) ) {
      $found = 0;
      foreach ( $option['events'] as $key => $notification ) {
        // We found event.
        if( $type  === $notification['type'] ){
            unset($option['events'][$key]);
            $found = 1;
        }
      }

      if( $found ){
        update_option( 'members_notifications', $option );
      }
    }
  }
}

$members_notifications = new Notifications;
$members_notifications->init();