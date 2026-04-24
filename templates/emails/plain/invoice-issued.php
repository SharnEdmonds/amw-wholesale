<?php
/** @var \AMW\Wholesale\Invoices\Invoice $invoice */
/** @var \AMW\Wholesale\Quotes\Quote   $quote */
defined( 'ABSPATH' ) || exit;

echo "= " . esc_html( $email_heading ) . " =\n\n";
printf( esc_html__( 'Your invoice %s is attached.', 'amw-wholesale' ), $invoice->invoice_number );
echo "\n\n";
printf( "Total: %s\n", number_format( (float) $invoice->total, 2 ) );
if ( $invoice->due_date ) {
	printf( "Due:   %s\n", $invoice->due_date );
}
echo "\n" . esc_html__( 'Payment is by bank transfer. Please reference the invoice number on your transfer.', 'amw-wholesale' ) . "\n\n";
echo esc_html( wp_strip_all_tags( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) );
