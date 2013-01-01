<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing parser class for binary size
 *
 * @package  Billing
 * @since    1.0
 * @todo should make first derivative parser text and then fixed parser will inherited text parser
 */
abstract class Billrun_Parser_Binary extends Billrun_Parser {

	protected $parsedBytes = 0;

	/**
	 * Get the amount of bytes that were parsed on the last parsing run.
	 * @return int	 containing the count of the bytes that were processed/parsed.
	 */
	public function getLastParseLength() {
		return $this->parsedBytes;
	}

	/**
	 * method to set the line of the parser
	 *
	 * @param string $line the line to set to the parser
	 * @return Object the parser itself (for concatening methods)
	 */
	public function setLine($line) {
		$this->line = $line;
		return $this;
	}

	/**
	 *
	 * @return string the line that parsed
	 */
	public function getLine() {
		return $this->line;
	}

}
