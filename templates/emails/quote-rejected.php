<?php
/** @var \AMW\Wholesale\Quotes\Quote $quote */
defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
?>
<p><?php printf( esc_html__( 'We were unable to fulfil your quote #%d at this time.', 'amw-wholesale' ), (int) $quote->id ); ?></p>
<?php if ( ! empty( $quote->admin_notes ) ) : ?>
	<p><strong><?php esc_html_e( 'Notes from our team:', 'amw-wholesale' ); ?></strong></p>
	<blockquote><?php echo esc_html( $quote->admin_notes ); ?></blockquote>
<?php endif; ?>
<p><?php esc_html_e( 'Please contact us if you would like to discuss.', 'amw-wholesale' ); ?></p>
<?php do_action( 'woocommerce_email_footer', $email ); ?>
