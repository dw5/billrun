<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
class Mongodloid_Cursor implements Iterator {

	private $_cursor;

	public function __construct(MongoCursor $cursor) {
		$this->_cursor = $cursor;
		$timeout = Billrun_Factory::config()->getConfigValue('db.timeout', 3600000); // default 60 minutes
		$this->_cursor->timeout($timeout);
	}

	public function count() {
		return $this->_cursor->count();
	}

	public function current() {
		//If before the start of the vector move to the first element.
		if(!$this->_cursor->current() && $this->_cursor->hasNext()) {
				$this->_cursor->next();
		}
		return new Mongodloid_Entity($this->_cursor->current());
	}

	public function key() {
		return $this->_cursor->key();
	}

	public function next() {
		return $this->_cursor->next();
	}

	public function rewind() {
		$this->_cursor->rewind();
		return $this;
	}

	public function valid() {
		return $this->_cursor->valid();
	}

	public function sort(array $fields) {
		$this->_cursor->sort($fields);
		return $this;
	}

	public function limit($limit) {
		$this->_cursor->limit(intval($limit));
		return $this;
	}
	
	public function hint(array $key_pattern) {
		if (empty($key_pattern)) {
			return;
		}
		$this->_cursor->hint($key_pattern);
		return $this;
	}

}