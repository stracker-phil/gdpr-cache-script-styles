<?php
/**
 * Handles dismissible admin notices.
 *
 * @since   1.0.5
 * @package GdprCache
 */

namespace GdprCache;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;


// ----------------------------------------------------------------------------

add_action( 'admin_notices', __NAMESPACE__ . '\show_admin_notices' );

add_action( 'wp_ajax_gdpr-cache-dismiss', __NAMESPACE__ . '\ajax_check_dismiss' );
add_action( 'gdpr_cache_do_dismiss', __NAMESPACE__ . '\ajax_do_dismiss' );

// ----------------------------------------------------------------------------


/**
 * @return array
 */
function get_all_notices() {
	$result = [];

	/**
	 * Divi Theme.
	 * Check for "Improve Google Fonts Loading" option, which was added in
	 * version 4.1.0
	 */
	if ( class_exists( 'ET_Builder_Google_Fonts_Feature' ) ) {
		global $shortname;

		if ( et_is_builder_plugin_active() ) {
			$options      = get_option( 'et_pb_builder_options', [] );
			$option_state = isset( $options['performance_main_google_fonts_inline'] ) ? $options['performance_main_google_fonts_inline'] : 'on';

			$et_option_path = 'admin.php?page=et_divi_options';
			$product_name   = 'Divi Builder Plugin';
		} else {
			$option_state = et_get_option( $shortname . '_google_fonts_inline', 'on' );

			if ( defined( 'EXTRA_LAYOUT_POST_TYPE' ) ) {
				$et_option_path = 'admin.php?page=et_extra_options';
				$product_name   = 'Extra Theme';
			} else {
				$et_option_path = 'admin.php?page=et_divi_options';
				$product_name   = 'Divi Theme';
			}
		}

		if ( 'on' === $option_state ) {
			$result['divi'] = [
				'message' => sprintf(
					__( 'You need to adjust your %s settings, so we can cache Google Fonts for you. Visit "<a href="%s">Theme Options | Performance</a>" and <strong>disable</strong> the option "Improve Google Fonts Loading" (<a href="%s" target="_blank">Screenshot</a>)', 'gdpr-cache' ),
					esc_html( $product_name ),
					esc_url( admin_url( $et_option_path ) ),
					'https://raw.githubusercontent.com/divimode/gdpr-cache-script-styles/main/docs/divi-config.png'
				),
			];
		}
	}

	/**
	 * Filters the list of admin notices before they are output.
	 *
	 * @since 1.0.5
	 *
	 * @param array $notices List of admin notices.
	 */
	return apply_filters( 'gdpr_cache_admin_notices', $result );
}

/**
 * Displays admin notices to admins about possible issues or requires steps to
 * get this plugin to work correctly.
 *
 * @since 1.0.5
 * @return void
 */
function show_admin_notices() {
	if ( ! current_user_can( GDPR_CACHE_CAPABILITY ) ) {
		return;
	}

	$num_notices = 0;
	$allowed     = [
		'a'      => [
			'href'   => [],
			'title'  => [],
			'target' => [],
		],
		'br'     => [],
		'em'     => [],
		'strong' => [],
	];

	foreach ( get_all_notices() as $id => $notice ) {
		if ( ! is_array( $notice ) || empty( $notice['message'] ) || is_notice_dismissed( $id ) ) {
			continue;
		}

		$type = isset( $notice['type'] ) ? $notice['type'] : 'warning';
		$num_notices ++;

		printf(
			'<div id="%s" class="gdpr-cache-notice notice-%s notice is-dismissible"><p>%s</p></div>',
			esc_attr( $id ),
			esc_attr( $type ),
			wp_kses( $notice['message'], $allowed )
		);
	}

	if ( $num_notices ) {
		add_notice_js_logic();
	}
}


/**
 * Ajax handler that checks the nonce/action of a dismiss-request.
 *
 * @since 1.0.5
 * @return void
 */
function ajax_check_dismiss() {
	process_actions();
}


/**
 * Processes a dismiss-request after the nonce-check was completed.
 *
 * @since 1.0.5
 *
 * @param array $params Request parameters.
 *
 * @return void
 */
function ajax_do_dismiss( $params ) {
	if ( empty( $params['msg'] ) ) {
		return;
	}

	$id = sanitize_key( $params['msg'] );

	dismiss_notice( $id, true );

	echo '1';
	exit;
}


/**
 * Checks, whether the specified admin notice was dismissed already.
 *
 * @since 1.0.5
 *
 * @param string $id The message ID.
 *
 * @return bool True, when the specified notice is marked as "closed"
 */
function is_notice_dismissed( $id ) {
	$data         = get_dismissed();
	$is_closed    = false;
	$is_temporary = false;

	if ( isset( $data[ $id ] ) ) {
		$closed_on = (int) $data[ $id ];

		if ( 1 === $closed_on ) {
			$is_closed = true;
		} else {
			$is_temporary = true;

			/**
			 * Filters the lifespan of a temporary dismissal (in seconds).
			 *
			 * @since 1.0.5
			 *
			 * @param int    $lifespan For how long the dismissal is valid (seconds).
			 * @param string $id       The dismissed message ID.
			 */
			$lifespan = apply_filters(
				'gdpr_cache_dismissal_lifespan',
				GDPR_CACHE_DISMISSAL_LIFESPAN,
				$id
			);

			$closed_for = ( $closed_on + $lifespan ) - time();
			$is_closed  = $closed_on > 1 && $closed_for > 0;
		}
	}

	/**
	 * Filters the result of the is-dismissed check.
	 *
	 * @since 1.0.5
	 *
	 * @param bool   $is_closed Whether the notice was dismissed by the admin.
	 * @param string $id        The message ID.
	 */
	$is_closed = apply_filters( 'gdpr_cache_is_notice_closed', $is_closed, $id );

	if ( $is_temporary && ! $is_closed ) {
		unset( $data[ $id ] );
		set_dismissed( $data );
	}

	return $is_closed;
}


/**
 * Dismisses a message (marks it as "closed").
 *
 * @since 1.0.5
 *
 * @param string $id        ID of the message.
 * @param bool   $temporary Whether the dismissal is temporary.
 *
 * @return void
 */
function dismiss_notice( $id, $temporary = true ) {
	$data = get_dismissed();

	$data[ $id ] = $temporary ? time() : 1;
	set_dismissed( $data );
}


/**
 * Adds a small JS snippet to the current page to permanently dismiss notices.
 *
 * @since 1.0.5
 * @return void
 */
function add_notice_js_logic() {
	?>
	<script>
		(function ($) {
			function dismiss(id) {
				$.post('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
					action: 'gdpr-cache-dismiss',
					_wpnonce: '<?php echo esc_attr( wp_create_nonce( 'dismiss' ) ); ?>',
					msg: id
				});
			}

			function onClick() {
				dismiss($(this).closest('.notice').attr('id'));
			}

			function setup() {
				$(this).on('click', onClick);
			}

			function init() {
				$('.gdpr-cache-notice .notice-dismiss').each(setup);
			}

			$(function () {
				setTimeout(init, 100);
			});
		})(jQuery);
	</script>
	<?php
}
