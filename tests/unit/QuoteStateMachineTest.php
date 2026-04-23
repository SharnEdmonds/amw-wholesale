<?php
/**
 * @package AMW\Wholesale\Tests
 */

declare( strict_types=1 );

namespace AMW\Wholesale\Tests\Unit;

use AMW\Wholesale\Quotes\Quote_State_Machine as SM;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class QuoteStateMachineTest extends TestCase {

	/**
	 * @dataProvider validTransitions
	 */
	public function test_valid_transitions_are_allowed( string $from, string $to ): void {
		$this->assertTrue( SM::can_transition( $from, $to ) );
	}

	/**
	 * @dataProvider invalidTransitions
	 */
	public function test_invalid_transitions_are_blocked( string $from, string $to ): void {
		$this->assertFalse( SM::can_transition( $from, $to ) );
	}

	public function test_assert_transition_throws_on_invalid(): void {
		$this->expectException( InvalidArgumentException::class );
		SM::assert_transition( SM::PAID, SM::INVOICED );
	}

	public function test_assert_transition_silent_on_valid(): void {
		SM::assert_transition( SM::DRAFT, SM::SUBMITTED );
		$this->addToAssertionCount( 1 );
	}

	public function test_terminal_states(): void {
		$this->assertTrue( SM::is_terminal( SM::PAID ) );
		$this->assertTrue( SM::is_terminal( SM::REJECTED ) );
		$this->assertTrue( SM::is_terminal( SM::EXPIRED ) );
		$this->assertFalse( SM::is_terminal( SM::DRAFT ) );
		$this->assertFalse( SM::is_terminal( SM::APPROVED ) );
	}

	public function test_all_returns_eight_states(): void {
		$states = SM::all();
		$this->assertCount( 8, $states );
		$this->assertSame( [
			SM::DRAFT, SM::SUBMITTED, SM::REVIEWING, SM::APPROVED,
			SM::REJECTED, SM::INVOICED, SM::PAID, SM::EXPIRED,
		], $states );
	}

	public static function validTransitions(): array {
		return [
			'draft to submitted'       => [ SM::DRAFT, SM::SUBMITTED ],
			'submitted to reviewing'   => [ SM::SUBMITTED, SM::REVIEWING ],
			'submitted to approved'    => [ SM::SUBMITTED, SM::APPROVED ],
			'submitted to rejected'    => [ SM::SUBMITTED, SM::REJECTED ],
			'submitted to expired'     => [ SM::SUBMITTED, SM::EXPIRED ],
			'reviewing to approved'    => [ SM::REVIEWING, SM::APPROVED ],
			'approved to invoiced'     => [ SM::APPROVED, SM::INVOICED ],
			'approved to rejected'     => [ SM::APPROVED, SM::REJECTED ],
			'approved to expired'      => [ SM::APPROVED, SM::EXPIRED ],
			'invoiced to paid'         => [ SM::INVOICED, SM::PAID ],
		];
	}

	public static function invalidTransitions(): array {
		return [
			'draft to approved'    => [ SM::DRAFT, SM::APPROVED ],
			'draft to paid'        => [ SM::DRAFT, SM::PAID ],
			'submitted to paid'    => [ SM::SUBMITTED, SM::PAID ],
			'submitted to invoiced'=> [ SM::SUBMITTED, SM::INVOICED ],
			'approved to draft'    => [ SM::APPROVED, SM::DRAFT ],
			'approved to paid'     => [ SM::APPROVED, SM::PAID ],
			'paid to invoiced'     => [ SM::PAID, SM::INVOICED ],
			'paid to draft'        => [ SM::PAID, SM::DRAFT ],
			'rejected to approved' => [ SM::REJECTED, SM::APPROVED ],
			'expired to approved'  => [ SM::EXPIRED, SM::APPROVED ],
			'expired to anything'  => [ SM::EXPIRED, SM::SUBMITTED ],
			'unknown from state'   => [ 'nonsense', SM::APPROVED ],
		];
	}
}
