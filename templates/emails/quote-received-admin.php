<?php
/** @var \AMW\Wholesale\Quotes\Quote $quote */
defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );

$money   = static fn( $v ) => function_exists( 'wc_price' ) ? wc_price( $v ) : number_format( (float) $v, 2 );
$user    = get_user_by( 'id', $quote->customer_id );
$edit_url = add_query_arg(
	[ 'page' => 'amw-wholesale-quote', 'id' => $quote->id ],
	admin_url( 'admin.php' )
);
?>
<p><?php printf( esc_html__( 'A new wholesale quote has been submitted: #%d.', 'amw-wholesale' ), (int) $quote->id ); ?></p>
<ul>
	<li><strong><?php esc_html_e( 'Customer:', 'amw-wholesale' ); ?></strong> <?php echo esc_html( $user ? $user->display_name . ' <' . $user->user_email . '>' : '—' ); ?></li>
	<li><strong><?php esc_html_e( 'Total:', 'amw-wholesale' ); ?></strong> <?php echo wp_kses_post( $money( $quote->total ) ); ?></li>
	<li><strong><?php esc_html_e( 'Submitted:', 'amw-wholesale' ); ?></strong> <?php echo esc_html( $quote->submitted_at ?? '—' ); ?></li>
</ul>
<p><a class="button" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Review quote', 'amw-wholesale' ); ?></a></p>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
