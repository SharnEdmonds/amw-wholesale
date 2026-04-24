<?php
/**
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Pricing;

defined( 'ABSPATH' ) || exit;

interface Price_Rule_Interface {

	/**
	 * Rule type identifier (must match wp_amw_pricing_rules.type).
	 */
	public function type(): string;

	/**
	 * Return true when this rule applies to the given context + config.
	 *
	 * @param array<string,mixed> $config
	 */
	public function applies( Price_Context $context, array $config ): bool;

	/**
	 * Apply the rule. Must return a new Price_Context (do not mutate in place).
	 *
	 * @param array<string,mixed> $config
	 */
	public function apply( Price_Context $context, array $config ): Price_Context;
}
