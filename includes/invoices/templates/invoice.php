<?php
/**
 * Invoice template. Variables:
 *   string       $invoice_number
 *   float        $total
 *   Quote_Item[] $items
 *   \WP_User|false $customer
 *   string       $issued_at
 *   ?string      $due_date
 *
 * @package AMW\Wholesale
 */

defined( 'ABSPATH' ) || exit;

$company_name  = get_bloginfo( 'name' );
$customer_name = $customer instanceof \WP_User ? $customer->display_name : __( '(unknown customer)', 'amw-wholesale' );
$money         = static function ( $v ): string {
	return function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $v ) ) : number_format( (float) $v, 2 );
};
?><!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="UTF-8">
	<title><?php echo esc_html( $invoice_number ); ?></title>
	<style>
		body { font-family: Helvetica, Arial, sans-serif; color: #222; font-size: 12pt; }
		h1 { margin: 0 0 1em 0; font-size: 20pt; }
		table { width: 100%; border-collapse: collapse; margin-top: 1em; }
		th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #ccc; }
		th { background: #f3f3f3; }
		.right { text-align: right; }
		.total { font-weight: bold; font-size: 14pt; }
		.meta { color: #555; font-size: 10pt; }
	</style>
</head>
<body>
	<h1><?php echo esc_html( $company_name ); ?></h1>
	<p class="meta">
		<strong><?php echo esc_html__( 'Invoice', 'amw-wholesale' ); ?>:</strong>
		<?php echo esc_html( $invoice_number ); ?><br>
		<strong><?php echo esc_html__( 'Issued', 'amw-wholesale' ); ?>:</strong>
		<?php echo esc_html( $issued_at ); ?><br>
		<?php if ( $due_date ) : ?>
			<strong><?php echo esc_html__( 'Due', 'amw-wholesale' ); ?>:</strong>
			<?php echo esc_html( $due_date ); ?><br>
		<?php endif; ?>
		<strong><?php echo esc_html__( 'Bill to', 'amw-wholesale' ); ?>:</strong>
		<?php echo esc_html( $customer_name ); ?>
	</p>

	<table>
		<thead>
			<tr>
				<th><?php echo esc_html__( 'SKU', 'amw-wholesale' ); ?></th>
				<th><?php echo esc_html__( 'Item', 'amw-wholesale' ); ?></th>
				<th class="right"><?php echo esc_html__( 'Qty', 'amw-wholesale' ); ?></th>
				<th class="right"><?php echo esc_html__( 'Unit', 'amw-wholesale' ); ?></th>
				<th class="right"><?php echo esc_html__( 'Line total', 'amw-wholesale' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $items as $item ) : ?>
				<tr>
					<td><?php echo esc_html( $item->sku ); ?></td>
					<td><?php echo esc_html( $item->name ); ?></td>
					<td class="right"><?php echo esc_html( (string) $item->quantity ); ?></td>
					<td class="right"><?php echo esc_html( $money( $item->unit_price ) ); ?></td>
					<td class="right"><?php echo esc_html( $money( $item->line_total ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<td colspan="4" class="right total"><?php echo esc_html__( 'Total', 'amw-wholesale' ); ?></td>
				<td class="right total"><?php echo esc_html( $money( $total ) ); ?></td>
			</tr>
		</tbody>
	</table>

	<p class="meta" style="margin-top: 2em;">
		<?php echo esc_html__( 'Payment by bank transfer. Bank details sent separately. Please reference the invoice number on your transfer.', 'amw-wholesale' ); ?>
	</p>
</body>
</html>
