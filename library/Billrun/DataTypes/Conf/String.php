<?php

class Billrun_DataTypes_Conf_String extends Billrun_DataTypes_Conf_Base {
	protected $reg = "";
	public function __construct($obj) {
		$this->val = $obj['v'];
		if(isset($obj['re'])) {
			$this->reg = $obj['re'];
		}
	}
	
	public function validate() {
		if(empty($this->val) || !is_string($this->val)) {
			return false;
		}
		
		// Check if has reg ex
		if(!empty($this->reg)) {
			// Validate regex.
			// http://stackoverflow.com/questions/4440626/how-can-i-validate-regex
			if(!is_string($this->reg) || (@preg_match($this->reg, null) === false)) {
				return false;
			}
			
			// Validate the regex
			return (preg_match($this->reg, $this->val) === 1);
		}
		
		return true;
	}
}
