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

$stale_labels = [
	'none'   => '',
	'low'    => _x( 'Low', 'Staleness', 'gdpr-cache' ),
	'medium' => _x( 'Medium', 'Staleness', 'gdpr-cache' ),
	'high'   => _x( 'High', 'Staleness', 'gdpr-cache' ),
];

$stale_tips = [
	'none'   => '',
	'low'    => _x( 'This file was recently used', 'Staleness', 'gdpr-cache' ),
	'medium' => _x( 'This file was not used for a while hours', 'Staleness', 'gdpr-cache' ),
	'high'   => _x( 'This file becomes stale and might be deleted soon', 'Staleness', 'gdpr-cache' ),
];

$items  = [];
$counts = [
	'all'      => 0,
	'valid'    => 0,
	'expired'  => 0,
	'missing'  => 0,
	'enqueued' => 0,
];

$stale_low    = ceil( GDPR_CACHE_STALE_HOURS / 20 );
$stale_medium = ceil( GDPR_CACHE_STALE_HOURS / 3 );

foreach ( $assets as $url => $item ) {
	$local_url   = '';
	$item_status = get_asset_status( $url );
	$item_type   = isset( $item['type'] ) ? $item['type'] : '';
	$staleness   = get_asset_staleness( $url );

	if ( ! $item_type ) {
		$item_type = get_url_type( $url );
	}
	if ( ! $item_type || 'tmp' === $item_type ) {
		$item_type = '?';
	}

	if ( 'valid' !== $item_status ) {
		enqueue_asset( $url );
	}
	if ( 'missing' === $item_status && in_array( $url, $queue ) ) {
		$item_status = 'enqueued';
	}
	if ( in_array( $item_status, [ 'valid', 'expired' ] ) ) {
		$local_url = build_cache_file_url( $item['file'] );
	}

	if ( $staleness < 0 ) {
		$stale_level = 'none';
	} elseif ( $staleness <= $stale_low ) {
		$stale_level = 'low';
	} elseif ( $staleness <= $stale_medium ) {
		$stale_level = 'medium';
	} else {
		$stale_level = 'high';
	}

	$counts['all'] ++;
	$counts[ $item_status ] ++;

	$items[] = [
		'url'         => $url,
		'status'      => $item_status,
		'type'        => $item_type,
		'local_url'   => $local_url,
		'stale_value' => $staleness,
		'stale_level' => $stale_level,
		'created'     => gmdate( 'Y-m-d H:i', $item['created'] ),
		'expires'     => gmdate( 'Y-m-d H:i', $item['expires'] ),
	];
}

foreach ( $queue as $url ) {
	if ( ! empty( $assets[ $url ] ) ) {
		continue;
	}

	$item_type = get_url_type( $url );
	if ( 'tmp' === $item_type ) {
		$item_type = '?';
	}

	$item_status = 'enqueued';
	$counts['all'] ++;
	$counts[ $item_status ] ++;

	$items[] = [
		'url'         => $url,
		'status'      => $item_status,
		'type'        => $item_type,
		'local_url'   => '',
		'stale_value' => - 1,
		'stale_level' => 'none',
		'created'     => '',
		'expires'     => '',
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
				<th class="asset-stale sorttable_numeric">
					<?php esc_html_e( 'Stale', 'gdpr-cache' ); ?>
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
				<?php
				$classes   = [];
				$classes[] = "status-{$item['status']}";
				$classes[] = "stale-{$item['stale_level']}";

				$status_label = $status_labels[ $item['status'] ];
				$stale_label  = $stale_labels[ $item['stale_level'] ];

				$stale_tip = sprintf(
					'%s (%s)',
					$stale_tips[ $item['stale_level'] ],
					sprintf(
						__( 'used %d hours ago', 'gdpr-cache' ),
						$item['stale_value']
					)
				);
				?>
				<tr class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
					<td class="asset-url"><?php echo esc_html( $item['url'] ); ?></td>
					<td class="asset-type"><?php echo esc_html( $item['type'] ); ?></td>
					<td class="asset-status">
						<?php if ( $item['local_url'] ): ?>
							<a
									href="<?php echo esc_url( $item['local_url'] ); ?>"
									title="<?php esc_attr_e( 'Open the cached file in a new window', 'gdpr-cache' ); ?>"
									target="_blank"
							>
								<?php echo esc_html( $status_label ); ?>
							</a>
						<?php else: ?>
							<?php echo esc_html( $status_label ); ?>
						<?php endif; ?>
					</td>
					<td class="asset-stale" title="<?php echo esc_attr( $stale_tip ); ?>">
						<span class="value">
							<?php echo esc_html( $item['stale_value'] ); ?>
						</span>
						<?php echo esc_html( $stale_label ); ?>
					</td>
					<td class="asset-created">
						<span class="date"><?php echo esc_html( substr( $item['created'], 0, 10 ) ); ?></span>
						<span class="time"><?php echo esc_html( substr( $item['created'], 11 ) ); ?></span>
					</td>
					<td class="asset-expires">
						<span class="date"><?php echo esc_html( substr( $item['expires'], 0, 10 ) ); ?></span>
						<span class="time"><?php echo esc_html( substr( $item['expires'], 11 ) ); ?></span>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php endif; ?>
</div>
