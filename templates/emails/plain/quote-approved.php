<?php
/** @var \AMW\Wholesale\Quotes\Quote $quote */
/** @var \AMW\Wholesale\Emails\Email_Quote_Approved $email */
defined( 'ABSPATH' ) || exit;

echo "= " . esc_html( $email_heading ) . " =\n\n";
printf( esc_html__( 'Your quote #%d is approved.', 'amw-wholesale' ), (int) $quote->id );
echo "\n\n";
printf( "Total: %s\n\n", number_format( (float) $quote->total, 2 ) );
echo esc_html__( 'Accept the quote at this link (single-use):', 'amw-wholesale' );
echo "\n" . esc_url_raw( $email->accept_url() ) . "\n\n";
echo esc_html( wp_strip_all_tags( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) );
