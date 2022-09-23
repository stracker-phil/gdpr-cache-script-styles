<?php
/**
 * Admin options page for our GDPR cache plugin
 *
 * @package GdprCache
 */

namespace GdprCache;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

wp_enqueue_script( 'gdpr-sortable', GDPR_CACHE_PLUGIN_URL . 'scripts/sortable.js' );
wp_enqueue_style( 'gdpr-sortable', GDPR_CACHE_PLUGIN_URL . 'styles/admin.css' );

$assets = get_cached_data();
$queue  = get_worker_queue();

$status_labels = [
	'all'      => __( 'All', 'gdpr-cache' ),
	'valid'    => __( 'Cached', 'gdpr-cache' ),
	'expired'  => __( 'Expired', 'gdpr-cache' ),
	'missing'  => __( 'Missing', 'gdpr-cache' ),
	'enqueued' => __( 'Enqueued', 'gdpr-cache' ),
];

$items  = [];
$counts = [
	'all'      => 0,
	'valid'    => 0,
	'expired'  => 0,
	'missing'  => 0,
	'enqueued' => 0,
];

foreach ( $assets as $url => $item ) {
	$status_label = '';
	$local_url    = '';
	$item_status  = get_asset_status( $url );
	$item_type    = isset( $item['type'] ) ? $item['type'] : '';

	if ( ! $item_type ) {
		$item_type = get_url_type( $url );
	}

	if ( 'valid' !== $item_status ) {
		enqueue_asset( $url );
	}
	if ( 'missing' === $item_status && in_array( $url, $queue ) ) {
		$item_status = 'enqueued';
	}
	if ( array_key_exists( $item_status, $status_labels ) ) {
		$status_label = $status_labels[ $item_status ];
	}
	if ( in_array( $item_status, [ 'valid', 'expired' ] ) ) {
		$local_url = build_cache_file_url( $item['file'] );
	}
	$counts['all'] ++;
	$counts[ $item_status ] ++;

	$items[] = [
		'url'          => $url,
		'status'       => $item_status,
		'status_label' => $status_label,
		'type'         => $item_type,
		'local_url'    => $local_url,
		'created'      => gmdate( 'Y-m-d H:i', $item['created'] ),
		'expires'      => gmdate( 'Y-m-d H:i', $item['expires'] ),
	];
}

foreach ( $queue as $url ) {
	if ( ! empty( $assets[ $url ] ) ) {
		continue;
	}

	$item_type   = get_url_type( $url );
	$item_status = 'enqueued';
	$counts['all'] ++;
	$counts[ $item_status ] ++;

	$items[] = [
		'url'          => $url,
		'status'       => $item_status,
		'status_label' => $status_labels[ $item_status ],
		'type'         => $item_type,
		'local_url'    => '',
		'created'      => '',
		'expires'      => '',
	];
}

$action_refresh = wp_nonce_url(
	add_query_arg( [ 'action' => 'gdpr-cache-refresh' ] ),
	'refresh'
);
$action_purge   = wp_nonce_url(
	add_query_arg( [ 'action' => 'gdpr-cache-purge' ] ),
	'purge'
);

?>
<div class="wrap" id="gdpr-cache">
	<h1><?php esc_html_e( 'GDPR Cache Options', 'gdpr-cache' ); ?></h1>

	<p>
		<?php esc_html_e( 'View and manage your locally cached assets', 'gdpr-cache' ); ?>
	</p>

	<h2><?php esc_html_e( 'Cache Control', 'gdpr-cache' ); ?></h2>
	<p>
		<?php esc_html_e( 'Refreshing the cache will start to download all external files in the background. While the cache is regenerated, the expired files are served.', 'gdpr-cache' ); ?>
	</p>
	<p>
		<?php esc_html_e( 'Purging the cache will instantly delete all cached files, and begin to build a new cache. Until the initialization is finished, assets are loaded from the external sites.', 'gdpr-cache' ); ?>
	</p>
	<div class="gdpr-cache-reset">
		<p class="submit">
			<a href="<?php echo esc_url_raw( $action_refresh ) ?>" class="button-primary">
				<?php esc_html_e( 'Refresh Cache', 'gdpr-cache' ) ?>
			</a>
			<a href="<?php echo esc_url_raw( $action_purge ) ?>" class="button">
				<?php esc_html_e( 'Purge Cache', 'gdpr-cache' ) ?>
			</a>
		</p>
	</div>
	<?php wp_nonce_field( 'flush' ); ?>
	<input type="hidden" name="action" value="gdpr-cache-flush"/>

	<h2><?php esc_html_e( 'Cached Assets', 'gdpr-cache' ); ?></h2>

	<ul class="subsubsub">
		<?php foreach ( $counts as $item_status => $count ) : ?>
			<?php if ( ! $count ) {
				continue;
			} ?>

			<li class="count-<?php echo esc_attr( $item_status ) ?>">
				<span class="status"><?php echo esc_html( $status_labels[ $item_status ] ) ?></span>
				<span class="count">(<?php echo esc_html( (int) $count ); ?>)</span>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php if ( ! $items ): ?>
		<p class="widefat">
			<em><?php esc_html_e( 'No external assets found', 'gdpr-cache' ); ?></em>
		</p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped table-view-list sortable">
			<thead>
			<tr>
				<th class="asset-url">
					<?php esc_html_e( 'URL', 'gdpr-cache' ); ?>
				</th>
				<th class="asset-type">
					<?php esc_html_e( 'Type', 'gdpr-cache' ); ?>
				</th>
				<th class="asset-status">
					<?php esc_html_e( 'Status', 'gdpr-cache' ); ?>
				</th>
				<th class="asset-created">
					<?php esc_html_e( 'Created', 'gdpr-cache' ); ?>

				</th>
				<th class="asset-expires">
					<?php esc_html_e( 'Expires', 'gdpr-cache' ); ?>
				</th>
			</tr>
			</thead>
			<?php foreach ( $items as $item ): ?>
				<tr class="status-<?php echo esc_attr( $item['status'] ); ?>">
					<td class="asset-url"><?php echo esc_html( $item['url'] ); ?></td>
					<td class="asset-type"><?php echo esc_html( $item['type'] ); ?></td>
					<td class="asset-status">
						<?php if ( $item['local_url'] ): ?>
							<a
									href="<?php echo esc_url( $item['local_url'] ); ?>"
									title="<?php esc_attr_e( 'Open the cached file in a new window', 'gdpr-cache' ); ?>"
									target="_blank"
							>
								<?php echo esc_html( $item['status_label'] ); ?>
							</a>
						<?php else: ?>
							<?php echo esc_html( $item['status_label'] ); ?>
						<?php endif; ?>
					</td>
					<td class="asset-created"><?php echo esc_html( $item['created'] ); ?></td>
					<td class="asset-expires"><?php echo esc_html( $item['expires'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php endif; ?>
</div>
