<?php
/**
 * @var \AMW\Wholesale\Quotes\Quote $quote
 */
defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );

$money = static fn( $v ) => function_exists( 'wc_price' ) ? wc_price( $v ) : number_format( (float) $v, 2 );
?>
<p><?php printf( esc_html__( 'Thanks — we received your quote #%d.', 'amw-wholesale' ), (int) $quote->id ); ?></p>
<p><?php esc_html_e( 'Total (subject to review):', 'amw-wholesale' ); ?> <?php echo wp_kses_post( $money( $quote->total ) ); ?></p>
<p><?php esc_html_e( "We'll review the quote and get back to you shortly.", 'amw-wholesale' ); ?></p>

<?php if ( ! empty( $additional_content ) ) : ?>
	<p><?php echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) ); ?></p>
<?php endif;

do_action( 'woocommerce_email_footer', $email );
