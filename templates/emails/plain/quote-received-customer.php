<?php
/** @var \AMW\Wholesale\Quotes\Quote $quote */
defined( 'ABSPATH' ) || exit;

echo "= " . esc_html( $email_heading ) . " =\n\n";
printf( esc_html__( 'Thanks — we received your quote #%d.', 'amw-wholesale' ), (int) $quote->id );
echo "\n\n";
printf( esc_html__( 'Total (subject to review): %s', 'amw-wholesale' ), number_format( (float) $quote->total, 2 ) );
echo "\n\n" . esc_html__( "We'll review the quote and get back to you shortly.", 'amw-wholesale' ) . "\n";

if ( ! empty( $additional_content ) ) {
	echo "\n----------\n\n" . wp_strip_all_tags( wptexturize( $additional_content ) ) . "\n";
}
echo "\n\n" . esc_html( wp_strip_all_tags( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) );
