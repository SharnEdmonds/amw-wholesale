<?php
/** @var \AMW\Wholesale\Quotes\Quote $quote */
/** @var \AMW\Wholesale\Emails\Email_Quote_Approved $email */
defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );

$money      = static fn( $v ) => function_exists( 'wc_price' ) ? wc_price( $v ) : number_format( (float) $v, 2 );
$accept_url = $email->accept_url();
?>
<p><?php printf( esc_html__( 'Your quote #%d is approved.', 'amw-wholesale' ), (int) $quote->id ); ?></p>
<p><?php esc_html_e( 'Total:', 'amw-wholesale' ); ?> <?php echo wp_kses_post( $money( $quote->total ) ); ?></p>
<p><?php esc_html_e( 'Click below to accept the quote and receive your invoice by email:', 'amw-wholesale' ); ?></p>
<p><a class="button" href="<?php echo esc_url( $accept_url ); ?>"><?php esc_html_e( 'Accept and get invoice', 'amw-wholesale' ); ?></a></p>
<p style="font-size: 0.9em; color: #666;"><?php printf( esc_html__( 'This link is single-use and expires on %s.', 'amw-wholesale' ), esc_html( (string) $quote->expires_at ) ); ?></p>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
