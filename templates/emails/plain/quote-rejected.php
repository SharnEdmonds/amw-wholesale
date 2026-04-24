<?php
/** @var \AMW\Wholesale\Quotes\Quote $quote */
defined( 'ABSPATH' ) || exit;

echo "= " . esc_html( $email_heading ) . " =\n\n";
printf( esc_html__( 'We were unable to fulfil your quote #%d at this time.', 'amw-wholesale' ), (int) $quote->id );
echo "\n\n";
if ( ! empty( $quote->admin_notes ) ) {
	echo esc_html__( 'Notes from our team:', 'amw-wholesale' ) . "\n";
	echo wp_strip_all_tags( $quote->admin_notes ) . "\n\n";
}
echo esc_html__( 'Please contact us if you would like to discuss.', 'amw-wholesale' ) . "\n\n";
echo esc_html( wp_strip_all_tags( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) );
