<?php
/**
 * @package AMW\Wholesale\Tests
 */

declare( strict_types=1 );

namespace AMW\Wholesale\Tests\Unit;

use AMW\Wholesale\Helpers\Sanitizer;
use PHPUnit\Framework\TestCase;

final class SanitizerTest extends TestCase {

	public function test_text_strips_tags_and_trims(): void {
		$this->assertSame( 'hello world', Sanitizer::text( '  <b>hello</b> world  ' ) );
	}

	public function test_text_handles_non_scalar(): void {
		$this->assertSame( '', Sanitizer::text( [ 'not', 'scalar' ] ) );
		$this->assertSame( '', Sanitizer::text( new \stdClass() ) );
	}

	public function test_email_accepts_valid(): void {
		$this->assertSame( 'user@example.com', Sanitizer::email( 'user@example.com' ) );
	}

	public function test_email_rejects_invalid(): void {
		$this->assertSame( '', Sanitizer::email( 'not-an-email' ) );
		$this->assertSame( '', Sanitizer::email( '<script>@x.com' ) );
	}

	/**
	 * @dataProvider intCases
	 */
	public function test_int_casts_absolute( $input, int $expected ): void {
		$this->assertSame( $expected, Sanitizer::int( $input ) );
	}

	public static function intCases(): array {
		return [
			'positive'   => [ '42', 42 ],
			'negative'   => [ '-42', 42 ],
			'zero'       => [ '0', 0 ],
			'float'      => [ '3.9', 3 ],
			'non-numeric' => [ 'abc', 0 ],
			'array'      => [ [ 1, 2 ], 0 ],
		];
	}

	/**
	 * @dataProvider moneyCases
	 */
	public function test_money_rounds_to_two_decimals( $input, float $expected ): void {
		$this->assertSame( $expected, Sanitizer::money( $input ) );
	}

	public static function moneyCases(): array {
		return [
			'plain'            => [ '12.5', 12.5 ],
			'dollar sign'      => [ '$12.50', 12.5 ],
			'commas stripped'  => [ '1,234.56', 1234.56 ],
			'three decimals'   => [ '1.239', 1.24 ],
			'negative'         => [ '-7.25', -7.25 ],
			'empty'            => [ '', 0.0 ],
		];
	}

	public function test_uuid_accepts_valid_v4(): void {
		$uuid = '12345678-1234-1234-1234-123456789012';
		$this->assertSame( $uuid, Sanitizer::uuid( $uuid ) );
	}

	public function test_uuid_rejects_bad_shape(): void {
		$this->assertSame( '', Sanitizer::uuid( 'not-a-uuid' ) );
		$this->assertSame( '', Sanitizer::uuid( '12345678-1234-1234-1234' ) );
		$this->assertSame( '', Sanitizer::uuid( 'GGGGGGGG-1234-1234-1234-123456789012' ) );
	}

	public function test_uuid_normalizes_case(): void {
		$in  = '12345678-ABCD-EF12-3456-789012345678';
		$out = '12345678-abcd-ef12-3456-789012345678';
		$this->assertSame( $out, Sanitizer::uuid( $in ) );
	}

	public function test_slug_lowercases_and_keeps_alnum_dash_underscore(): void {
		$this->assertSame( 'hello-world_2', Sanitizer::slug( 'Hello-World_2' ) );
		$this->assertSame( 'onlysafechars', Sanitizer::slug( 'only safe@chars!' ) );
		$this->assertSame( 'role_tier', Sanitizer::slug( 'Role_Tier' ) );
	}

	/**
	 * @dataProvider boolCases
	 */
	public function test_bool_truthy_matching( $input, bool $expected ): void {
		$this->assertSame( $expected, Sanitizer::bool( $input ) );
	}

	public static function boolCases(): array {
		return [
			'one'     => [ '1', true ],
			'true-s'  => [ 'true', true ],
			'on'      => [ 'on', true ],
			'zero'    => [ '0', false ],
			'off'     => [ 'off', false ],
			'empty'   => [ '', false ],
			'true-b'  => [ true, true ],
			'false-b' => [ false, false ],
		];
	}

	public function test_html_fragment_allows_safe_tags(): void {
		$this->assertStringContainsString( '<strong>', Sanitizer::html_fragment( '<strong>ok</strong>' ) );
	}

	public function test_html_fragment_strips_scripts(): void {
		$cleaned = Sanitizer::html_fragment( 'safe<script>alert(1)</script>' );
		$this->assertStringNotContainsString( '<script', $cleaned );
	}
}
