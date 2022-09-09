<?php
/**
 * Admin options page for our GDPR cache plugin
 *
 * @package GdprCache
 */

namespace GdprCache;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>
<div class="wrap">
	<h1><?php esc_html_e( 'GDPR Cache Options', 'gdpr-cache' ); ?></h1>

	<p>
		<?php esc_html_e( 'Overview and manage your locally cached assets', 'gdpr-cache' ); ?>
	</p>

	<form method="post">
		<h2><?php esc_html_e( 'Cache Control', 'gdpr-cache' ); ?></h2>
		<p>
			<?php esc_html_e( 'Flushing the cache will instantly delete any cached assets and start to download all assets from the remote servers on the next request.', 'gdpr-cache' ); ?>
		</p>
		<div class="gdpr-cache-reset">
			<p class="submit">
				<input
						type="submit" name="submit" id="submit" class="button"
						value="<?php esc_attr_e( 'Flush Cache', 'gdpr-cache' ) ?>"
				>
			</p>
		</div>
		<?php wp_nonce_field( 'flush' ); ?>
		<input type="hidden" name="action" value="gdpr-cache-flush"/>
	</form>

</div>
