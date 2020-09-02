<?php

namespace Members;

/**
 * Review admin notice.
 */
class ReviewPrompt {

	public function __construct() {
		add_action( 'admin_notices', array( $this, 'review_notice' ) );
		add_action( 'wp_ajax_members_dismiss_review_prompt', array( $this, 'dismiss_review_prompt' ) );
	}

	public function dismiss_review_prompt() {

		if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'members_dismiss_review_prompt' ) ) {
			die('Failed');
		}

		if ( ! empty( $_POST['type'] ) ) {
			if ( 'remove' === $_POST['type'] ) {
				update_option( 'members_review_prompt_removed', true );
				wp_send_json_success( array(
					'status' => 'removed'
				) );
			} else if ( 'delay' === $_POST['type'] ) {
				set_transient( 'members_review_prompt_delay', true, WEEK_IN_SECONDS );
				wp_send_json_success( array(
					'status' => 'delayed'
				) );
			}
		}
	}

	public function review_notice() {

		// Only show to admins
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Notice has been removed or delayed
		if ( get_option( 'members_review_prompt_removed' ) || get_transient( 'members_review_prompt_delay' ) ) {
			return;
		}

		// Don't bother if haven't been using long enough
		$transient = get_transient( 'members_30days_flag' );
		if ( ! empty( $transient ) ) {
			return;
		}

		?>
		<div class="notice notice-info is-dismissible members-review-notice" id="members_review_notice">
			<div id="members_review_intro">
				<p><?php _e( 'Are you enjoying using Members?', 'members' ); ?></p>
				<p><a data-review-selection="yes" class="members-review-selection" href="#">Yes, I love it</a> 🙂 | <a data-review-selection="no" class="members-review-selection" href="#">Not really...</a></p>
			</div>
			<div id="members_review_yes" style="display: none;">
				<p><?php _e( 'That\'s awesome! Could you please do me a BIG favor and give it a 5-star rating on WordPress to help us spread the word and boost our motivation?', 'members' ); ?></p>
				<p style="font-weight: bold;">~ Blair Williams<br>Co-Founder &amp; CEO of MemberPress</p>
				<p>
					<a style="display: inline-block; margin-right: 10px;" href="https://wordpress.org/support/plugin/members/reviews/?filter=5#new-post" onclick="delayReviewPrompt('remove')" target="_blank"><?php esc_html_e( 'Okay, you deserve it', 'members' ); ?></a>
					<a style="display: inline-block; margin-right: 10px;" href="#" onclick="delayReviewPrompt('delay')"><?php esc_html_e( 'Nope, maybe later', 'members' ); ?></a>
					<a href="#" onclick="delayReviewPrompt('remove')"><?php esc_html_e( 'I already did', 'members' ); ?></a>
				</p>
			</div>
			<div id="members_review_no" style="display: none;">
				<p><?php _e( 'We\'re sorry to hear you aren\'t enjoying Members. We would love a chance to improve. Could you take a minute and let us know what we can do better?', 'members' ); ?></p>
				<p>
					<a style="display: inline-block; margin-right: 10px;" href="https://memberpress.com/plugins/members/plugin-feedback/?utm_source=members&utm_medium=link&utm_campaign=in_plugin&utm_content=request_review" onclick="delayReviewPrompt('remove')" target="_blank"><?php esc_html_e( 'Give Feedback', 'members' ); ?></a>
					<a href="#" onclick="delayReviewPrompt('delay')"><?php esc_html_e( 'No thanks', 'members' ); ?></a>
				</p>
			</div>
		</div>
		<script>

			function delayReviewPrompt(type, triggerClick = true) {
				if ( triggerClick ) {
					jQuery('#members_review_notice').fadeOut();
				}
				jQuery.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'members_dismiss_review_prompt',
						nonce: "<?php echo wp_create_nonce( 'members_dismiss_review_prompt' ) ?>",
						type: type
					},
				})
				.done(function(data) {
					
				});
			}

			jQuery(document).ready(function($) {
				$('.members-review-selection').click(function(event) {
					event.preventDefault();
					var $this = $(this);
					var selection = $this.data('review-selection');
					$('#members_review_intro').hide();
					$('#members_review_' + selection).show();
				});
				$('body').on('click', '#members_review_notice .notice-dismiss', function(event) {
					delayReviewPrompt('delay', false);
				});
			});
		</script>
		<?php
	}
}

new ReviewPrompt;
