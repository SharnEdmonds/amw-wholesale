<?php
/**
 * Catalog template. Variables:
 *   array  $rows       [{id, sku, name, stock, in_stock, unit_price}, ...]
 *   string $nonce      create('submit_quote') value
 *   string $rest_url   absolute URL to POST /amw/v1/quotes
 *
 * @package AMW\Wholesale
 */

defined( 'ABSPATH' ) || exit;

get_header();

$money = static function ( $v ): string {
	return function_exists( 'wc_price' ) ? wc_price( $v ) : esc_html( number_format( (float) $v, 2 ) );
};
?>
<main class="amw-wholesale-catalog">
	<h1><?php echo esc_html__( 'Wholesale catalog', 'amw-wholesale' ); ?></h1>
	<p class="amw-wholesale-intro">
		<?php echo esc_html__( 'Add items to your quote; submit when ready. An admin will review and respond with an invoice.', 'amw-wholesale' ); ?>
	</p>

	<table class="amw-wholesale-catalog-table"
		data-rest-url="<?php echo esc_url( $rest_url ); ?>"
		data-nonce="<?php echo esc_attr( $nonce ); ?>"
		data-wp-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'SKU', 'amw-wholesale' ); ?></th>
				<th><?php echo esc_html__( 'Name', 'amw-wholesale' ); ?></th>
				<th><?php echo esc_html__( 'Stock', 'amw-wholesale' ); ?></th>
				<th><?php echo esc_html__( 'Your price', 'amw-wholesale' ); ?></th>
				<th><?php echo esc_html__( 'Qty', 'amw-wholesale' ); ?></th>
				<th><?php echo esc_html__( 'Line total', 'amw-wholesale' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr data-product-id="<?php echo esc_attr( (string) $row['id'] ); ?>"
					data-unit-price="<?php echo esc_attr( (string) $row['unit_price'] ); ?>">
					<td><?php echo esc_html( $row['sku'] ); ?></td>
					<td><?php echo esc_html( $row['name'] ); ?></td>
					<td>
						<?php
						if ( ! $row['in_stock'] ) {
							echo esc_html__( 'Out of stock', 'amw-wholesale' );
						} elseif ( null !== $row['stock'] ) {
							echo esc_html( (string) $row['stock'] );
						} else {
							echo esc_html__( 'In stock', 'amw-wholesale' );
						}
						?>
					</td>
					<td class="amw-unit-price"><?php echo wp_kses_post( $money( $row['unit_price'] ) ); ?></td>
					<td>
						<input type="number" min="0" step="1" value="0" class="amw-qty-input" aria-label="<?php echo esc_attr__( 'Quantity', 'amw-wholesale' ); ?>">
					</td>
					<td class="amw-line-total">—</td>
					<td>
						<button type="button" class="amw-add-btn"><?php echo esc_html__( 'Add to quote', 'amw-wholesale' ); ?></button>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<section class="amw-wholesale-builder" aria-label="<?php echo esc_attr__( 'Your quote', 'amw-wholesale' ); ?>">
		<h2><?php echo esc_html__( 'Your quote', 'amw-wholesale' ); ?></h2>
		<ul class="amw-quote-lines"></ul>
		<p class="amw-quote-total"><strong><?php echo esc_html__( 'Subtotal:', 'amw-wholesale' ); ?></strong> <span class="amw-subtotal">0.00</span></p>
		<label for="amw-customer-notes"><?php echo esc_html__( 'Notes for admin (optional)', 'amw-wholesale' ); ?></label>
		<textarea id="amw-customer-notes" rows="3"></textarea>
		<button type="button" class="amw-submit-btn"><?php echo esc_html__( 'Submit quote', 'amw-wholesale' ); ?></button>
		<p class="amw-submit-result" role="status"></p>
	</section>
</main>
<?php
get_footer();
