<?php
/** @var \AMW\Wholesale\Invoices\Invoice $invoice */
/** @var \AMW\Wholesale\Quotes\Quote   $quote */
defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
$money = static fn( $v ) => function_exists( 'wc_price' ) ? wc_price( $v ) : number_format( (float) $v, 2 );
?>
<p><?php printf( esc_html__( 'Your invoice %s is attached.', 'amw-wholesale' ), '<strong>' . esc_html( $invoice->invoice_number ) . '</strong>' ); ?></p>
<ul>
	<li><strong><?php esc_html_e( 'Total:', 'amw-wholesale' ); ?></strong> <?php echo wp_kses_post( $money( $invoice->total ) ); ?></li>
	<?php if ( $invoice->due_date ) : ?>
		<li><strong><?php esc_html_e( 'Due:', 'amw-wholesale' ); ?></strong> <?php echo esc_html( $invoice->due_date ); ?></li>
	<?php endif; ?>
</ul>
<p><?php esc_html_e( 'Payment is by bank transfer. Please reference the invoice number on your transfer. Bank details will be sent separately if not already on file.', 'amw-wholesale' ); ?></p>
<?php do_action( 'woocommerce_email_footer', $email ); ?>
