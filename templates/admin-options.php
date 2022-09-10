<?php
/**
 * Admin options page for our GDPR cache plugin
 *
 * @package GdprCache
 */

namespace GdprCache;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$assets = get_cached_data();
$queue  = get_worker_queue();

$status_labels = [
	'all'      => __( 'All', 'gdpr-cache' ),
	'valid'    => __( 'Cached', 'gdpr-cache' ),
	'expired'  => __( 'Expired', 'gdpr-cache' ),
	'missing'  => __( 'Missing', 'gdpr-cache' ),
	'enqueued' => __( 'Enqueued', 'gdpr-cache' ),
];

$items     = [];
$counts    = [
	'all'      => 0,
	'valid'    => 0,
	'expired'  => 0,
	'missing'  => 0,
	'enqueued' => 0,
];
$subsubsub = [];

foreach ( $assets as $url => $item ) {
	$status_label = '';
	$status       = get_asset_status( $url );

	if ( 'valid' !== $status ) {
		enqueue_asset( $url );
	}
	if ( 'missing' === $status && in_array( $url, $queue ) ) {
		$status = 'enqueued';
	}
	if ( array_key_exists( $status, $status_labels ) ) {
		$status_label = $status_labels[ $status ];
	}
	$counts['all'] ++;
	$counts[ $status ] ++;

	$items[] = [
		'url'          => $url,
		'status'       => $status,
		'status_label' => $status_label,
		'created'      => date( 'Y-m-d H:i', $item['created'] ),
		'expires'      => date( 'Y-m-d H:i', $item['expires'] ),
	];
}

foreach ( $queue as $url ) {
	if ( ! empty( $assets[ $url ] ) ) {
		continue;
	}

	$status = 'enqueued';
	$counts['all'] ++;
	$counts[ $status ] ++;

	$items[] = [
		'url'          => $url,
		'status'       => $status,
		'status_label' => $status_labels[ $status ],
		'created'      => '',
		'expires'      => '',
	];
}

foreach ( $counts as $status => $count ) {
	if ( ! $count ) {
		continue;
	}

	$subsubsub[] = sprintf(
		'<li class="%s"> %s <span class="count">(%d)</span>',
		esc_attr( $status ),
		esc_html( $status_labels[ $status ] ),
		(int) $count
	);
}

?>
<div class="wrap">
	<h1><?php esc_html_e( 'GDPR Cache Options', 'gdpr-cache' ); ?></h1>

	<p>
		<?php esc_html_e( 'Overview and manage your locally cached assets', 'gdpr-cache' ); ?>
	</p>

	<form method="post">
		<h2><?php esc_html_e( 'Cache Control', 'gdpr-cache' ); ?></h2>
		<p>
			<?php esc_html_e( 'Invalidating the cache will expire all cached files. The plugin will start to download the latest version of all files on the next request. While the cache is regenerated, the expired files are served.', 'gdpr-cache' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'To complete delete the entire cache, you need to disable the plugin.', 'gdpr-cache' ); ?>
		</p>
		<div class="gdpr-cache-reset">
			<p class="submit">
				<input
						type="submit"
						name="submit"
						id="submit"
						class="button"
						value="<?php esc_attr_e( 'Invalidate Cache', 'gdpr-cache' ) ?>"
				>
			</p>
		</div>
		<?php wp_nonce_field( 'flush' ); ?>
		<input type="hidden" name="action" value="gdpr-cache-flush"/>
	</form>

	<h2><?php esc_html_e( 'Cached Assets', 'gdpr-cache' ); ?></h2>

	<ul class="subsubsub" style="margin-bottom:12px">
		<?php echo implode( ' | </li> ', $subsubsub ); ?>
	</ul>

	<?php if ( ! $items ): ?>
		<p class="widefat">
			<em><?php esc_html_e( 'No external assets found', 'gdpr-cache' ); ?></em>
		</p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped table-view-list">
			<thead>
			<tr>
				<th class="asset-url"><?php esc_html_e( 'URL', 'gdpr-cache' ); ?></th>
				<th
						class="asset-status" style="width:100px"
				><?php esc_html_e( 'Status', 'gdpr-cache' ); ?></th>
				<th
						class="asset-created" style="width:125px"
				><?php esc_html_e( 'Created', 'gdpr-cache' ); ?></th>
				<th
						class="asset-expires" style="width:125px"
				><?php esc_html_e( 'Expires', 'gdpr-cache' ); ?></th>
			</tr>
			</thead>
			<?php foreach ( $items as $item ): ?>
				<tr class="status-<?php echo esc_attr( $item['status'] ); ?>">
					<td class="asset-url"><?php echo esc_html( $item['url'] ); ?></td>
					<td class="asset-status">
						<?php echo esc_html( $item['status_label'] ); ?>
					</td>
					<td class="asset-created"><?php echo esc_html( $item['created'] ); ?></td>
					<td class="asset-expires"><?php echo esc_html( $item['expires'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php endif; ?>

</div>
