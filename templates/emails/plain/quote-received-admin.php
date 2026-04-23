<?php
/** @var \AMW\Wholesale\Quotes\Quote $quote */
defined( 'ABSPATH' ) || exit;

$user = get_user_by( 'id', $quote->customer_id );
$edit_url = add_query_arg(
	[ 'page' => 'amw-wholesale-quote', 'id' => $quote->id ],
	admin_url( 'admin.php' )
);

echo "= " . esc_html( $email_heading ) . " =\n\n";
printf( esc_html__( 'A new wholesale quote has been submitted: #%d.', 'amw-wholesale' ), (int) $quote->id );
echo "\n\n";
printf( "Customer: %s\n", $user ? $user->display_name . ' <' . $user->user_email . '>' : '—' );
printf( "Total:    %s\n", number_format( (float) $quote->total, 2 ) );
printf( "Submitted: %s\n\n", $quote->submitted_at ?? '—' );
echo "Review: " . esc_url_raw( $edit_url ) . "\n\n";
echo esc_html( wp_strip_all_tags( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) );
