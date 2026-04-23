<?php
/**
 * Quote state machine.
 *
 * draft -> submitted -> reviewing -> approved -> invoiced -> paid
 *                                 \-> rejected
 * (submitted|reviewing|approved) -> expired (via cron when expires_at < NOW)
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Quotes;

defined( 'ABSPATH' ) || exit;

final class Quote_State_Machine {

	public const DRAFT     = 'draft';
	public const SUBMITTED = 'submitted';
	public const REVIEWING = 'reviewing';
	public const APPROVED  = 'approved';
	public const REJECTED  = 'rejected';
	public const INVOICED  = 'invoiced';
	public const PAID      = 'paid';
	public const EXPIRED   = 'expired';

	/** @var array<string,array<int,string>> */
	private const TRANSITIONS = [
		self::DRAFT     => [ self::SUBMITTED ],
		self::SUBMITTED => [ self::REVIEWING, self::APPROVED, self::REJECTED, self::EXPIRED ],
		self::REVIEWING => [ self::APPROVED, self::REJECTED, self::EXPIRED ],
		self::APPROVED  => [ self::INVOICED, self::REJECTED, self::EXPIRED ],
		self::REJECTED  => [],
		self::INVOICED  => [ self::PAID ],
		self::PAID      => [],
		self::EXPIRED   => [],
	];

	public static function all(): array {
		return array_keys( self::TRANSITIONS );
	}

	public static function can_transition( string $from, string $to ): bool {
		$allowed = self::TRANSITIONS[ $from ] ?? null;
		if ( null === $allowed ) {
			return false;
		}
		return in_array( $to, $allowed, true );
	}

	public static function assert_transition( string $from, string $to ): void {
		if ( ! self::can_transition( $from, $to ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Invalid quote state transition: %s -> %s', $from, $to )
			);
		}
	}

	public static function is_terminal( string $state ): bool {
		return empty( self::TRANSITIONS[ $state ] ?? [] );
	}
}
