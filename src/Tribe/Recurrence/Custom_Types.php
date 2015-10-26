<?php


class Tribe__Events__Pro__Recurrence__Custom_Types {

	/**
	 * converts a custom type to a custom type array index slug
	 *
	 * @param string $custom_type Friendly Custom-Type value
	 *
	 * @return string
	 */
	public static function to_key( $custom_type ) {
		switch ( $custom_type ) {
			case 'Date':
				return 'date';
			case 'Yearly':
				return 'year';
			case 'Monthly':
				return 'month';
			case 'Weekly':
				return 'week';
			case 'Daily':
			default:
				return 'day';
		}
	}
}