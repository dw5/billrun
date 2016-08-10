<?php

/*
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test case class for the config module
 *
 * @package         Tests
 * @subpackage      Config
 * @since           4.4
 */
require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

class Tests_Config extends UnitTestCase {
	protected $complexWrapperTests = array(
			// Numbers
			array('v' => 1, 't' => "Number", "valid" => true, "msg" => "Positive number test"),
			array('v' => 10, 't' => "Number", "valid" => true, "msg" => "Positive number test"),
			array('v' => 0, 't' => "Number", "valid" => true, "msg" => "Zero number test"),
			array('v' => -1, 't' => "Number", "valid" => true, "msg" => "Negative number test"),
			array('v' => -10, 't' => "Number", "valid" => true, "msg" => "Negative number test"),
			array('v' => null, 't' => "Number", "valid" => false, "msg" => "Null number test"),
			array('v' => "44", 't' => "Number", "valid" => true, "msg" => "Numeric String number test"),
			array('v' => "bla bla", 't' => "Number", "valid" => false, "msg" => "String number test"),
			array('v' => 0.1, 't' => "Number", "valid" => false, "msg" => "Float number test"),
			array('v' => 1, 't' => "Number", "range"=> array("min" => 0, "max" => 10), "valid" => true, "msg" => "Positive number in Range"),
			array('v' => 10, 't' => "Number", "range"=> array("min" => 0, "max" => 10), "valid" => true, "msg" => "Positive number in Range"),
			array('v' => 0, 't' => "Number", "range"=> array("min" => 0, "max" => 10), "valid" => true, "msg" => "Zero number in Range"),
			array('v' => -1, 't' => "Number", "range"=> array("min" => -10, "max" => 0), "valid" => true, "msg" => "Negative number in Range"),
			array('v' => -10, 't' => "Number", "range"=> array("min" => -10, "max" => 0), "valid" => true, "msg" => "Negative number in Range"),
			array('v' => 1, 't' => "Number", "range"=> array("min" => -10, "max" => 0), "valid" => false, "msg" => "Positive number NOT in Range"),
			array('v' => -1, 't' => "Number", "range"=> array("min" => 0, "max" => 10), "valid" => false, "msg" => "Negative number NOT in Range"),
		
			// Float
			array('v' => 0.1, 't' => "Float", "valid" => true, "msg" => "Positive float test"),
			array('v' => 1.3, 't' => "Float", "valid" => true, "msg" => "Positive float test"),
			array('v' => 0, 't' => "Float", "valid" => true, "msg" => "Zero float test"),
			array('v' => -0.1, 't' => "Float", "valid" => true, "msg" => "Negative float test"),
			array('v' => -1.3, 't' => "Float", "valid" => true, "msg" => "Negative float test"),
			array('v' => null, 't' => "Float", "valid" => false, "msg" => "Null float test"),
			array('v' => "4.4", 't' => "Float", "valid" => true, "msg" => "Numeric String float test"),
			array('v' => "bla bla", 't' => "Float", "valid" => false, "msg" => "String float test"),
			array('v' => 4, 't' => "Float", "valid" => true, "msg" => "Float natural number test"),
			array('v' => 0.5, 't' => "Float", "range"=> array("min" => 0.1, "max" => 5.3), "valid" => true, "msg" => "Positive float in Range"),
			array('v' => 1.3, 't' => "Float", "range"=> array("min" => 0.1, "max" => 5.3), "valid" => true, "msg" => "Positive float in Range"),
			array('v' => 0, 't' => "Float", "range"=> array("min" => 0, "max" => 5.3), "valid" => true, "msg" => "Zero float in Range"),
			array('v' => -1.3, 't' => "Float", "range"=> array("min" => -6.4, "max" => 0.1), "valid" => true, "msg" => "Negative float in Range"),
			array('v' => -4.7, 't' => "Float", "range"=> array("min" => -10, "max" => 0), "valid" => true, "msg" => "Negative float in Range"),
			array('v' => 1.5, 't' => "Float", "range"=> array("min" => -10, "max" => 0), "valid" => false, "msg" => "Positive float NOT in Range"),
			array('v' => -1.5, 't' => "Float", "range"=> array("min" => 0, "max" => 10), "valid" => false, "msg" => "Negative float NOT in Range"),
		
			// String
			array('v' => "Hello", 't' => "String", "valid" => true, "msg" => "String"),
			array('v' => "1", 't' => "String", "valid" => true, "msg" => "Numeric string"),
			array('v' => 1, 't' => "String", "valid" => false, "msg" => "Number instead of string"),
			array('v' => null, 't' => "String", "valid" => false, "msg" => "Null string test"),
			array('v' => "", 't' => "String", "valid" => false, "msg" => "Empty string test"),
		
			// String range
			array('v' => "a", 't' => "String", "valid" => true, "range" => array() ,"msg" => "Valid string no range"),
			array('v' => "a", 't' => "String", "valid" => true, "range" => array("a", "b", "c") ,"msg" => "Valid string in range"),
			array('v' => "Bla", 't' => "String", "valid" => true,"range" => array("a", "Bla", "c"), "msg" => "Valid string in range 2"),
		
			array('v' => "b", 't' => "String", "valid" => false,"range" => array("a", "Bla", "c"), "msg" => "Invalid string not in range"),
			array('v' => "Bla", 't' => "String", "valid" => false,"range" => array("a", "b", "c"), "msg" => "Invalid string not in range 2"),
		
			// Regex tests
			array('v' => "127.0.0.1", 't' => "String", "valid" => true, "re" => "/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/", "msg" => "Valid re IP test"),
			array('v' => "Hello.World", 't' => "String", "valid" => false, "re" => "/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/", "msg" => "Invalid re IP test"),
			array('v' => "abcdefghij", 't' => "String", "valid" => true, "re" => "/c.*g/", "msg" => "Valid simple re"),
			array('v' => "defghij", 't' => "String", "valid" => false, "re" => "/c.*g/", "msg" => "Invalid simple re"),
			array('v' => 1, 't' => "String", "valid" => false, "re" => "/c.*g/", "msg" => "Number instead of string + RE"),
			array('v' => null, 't' => "String", "valid" => false, "re" => "/c.*g/", "msg" => "Null instead of string + RE"),
			array('v' => "defghij", 't' => "String", "valid" => false, "re" => 1, "msg" => "Number as RE"),
			array('v' => "defghij", 't' => "String", "valid" => true, "re" => null, "msg" => "Null as RE"),
		
			// Datetime tests
			array('v' => "3 seconds", 't' => "DateString", "valid" => true, "msg" => "Valid seconds date"),
			array('v' => "5 hours", 't' => "DateString", "valid" => true, "msg" => "Valid hours date"),
			array('v' => "3 days", 't' => "DateString", "valid" => true, "msg" => "Valid days date"),
			array('v' => "next month", 't' => "DateString", "valid" => true, "msg" => "Valid next month"),
			array('v' => "2016-05-12", 't' => "DateString", "valid" => true, "msg" => "Valid date"),
			array('v' => "2016-05-12 00:00:30", 't' => "DateString", "valid" => true, "msg" => "Valid date+time"),
			array('v' => "July 1st, 2008", 't' => "DateString", "valid" => true, "msg" => "Valid excplicit"),
		
			array('v' => "next month", 't' => "DateString", "range" => array("min" => "last year", "max" => "next year"), "valid" => true, "msg" => "Valid next month with range"),
			array('v' => "next month", 't' => "DateString", "range" => array("min" => "last week", "max" => "next week"), "valid" => false, "msg" => "Valid next month with invalid range"),
		
			array('v' => "05.2016.12", 't' => "DateString", "valid" => false, "msg" => "Invalid date"),
			array('v' => "2016-05/12 00:00:30", 't' => "DateString", "valid" => false, "msg" => "Invalid date+time"),
			array('v' => "Bob 1st, 2008", 't' => "DateString", "valid" => false, "msg" => "Invalid excplicit"),
		
			array('v' => "3.9 seconds", 't' => "DateString", "valid" => false, "msg" => "Invalid seconds date"),
			array('v' => "bla hours", 't' => "DateString", "valid" => false, "msg" => "Invalid hours date"),
			array('v' => 100, 't' => "DateString", "valid" => false, "msg" => "Invalid positive number date"),
			array('v' => -100, 't' => "DateString", "valid" => false, "msg" => "Invalid negative number date"),
			array('v' => null, 't' => "DateString", "valid" => false, "msg" => "Invalid null date"),
			array('v' => "", 't' => "DateString", "valid" => false, "msg" => "Invalid empty date"),
		
			array('v' => "UTC", 't' => "Timezone", "valid" => true, "msg" => "Valid UTC timezone"),
			array('v' => "Africa/Addis_Ababa", 't' => "Timezone", "valid" => true, "msg" => "Valid Africa Addis Ababa"),
			array('v' => "Asia/Jerusalem", 't' => "Timezone", "valid" => true, "msg" => "Valid Jerusalem"),
			array('v' => "Australia/Lord_Howe", 't' => "Timezone", "valid" => true, "msg" => "Valid Australia/Lord_Howe"),
		
			array('v' => "Israel/Bla", 't' => "Timezone", "valid" => false, "msg" => "Invalid string timezone"),
			array('v' => "Africa", 't' => "Timezone", "valid" => false, "msg" => "Invalid Africa"),
			array('v' => "Jerusalem", 't' => "Timezone", "valid" => false, "msg" => "Invalid Jerusalem"),
			array('v' => "Asia/", 't' => "Timezone", "valid" => false, "msg" => "Invalid Asia"),
			array('v' => null, 't' => "Timezone", "valid" => false, "msg" => "Invalid null timezone"),
			array('v' => 100, 't' => "Timezone", "valid" => false, "msg" => "Invalid number timezone"),
			array('v' => "", 't' => "Timezone", "valid" => false, "msg" => "Invalid empty timezone"),
		
			array('t' => "List", 'v' => array("field_name" => "a", "editable" => false), "valid" => true, "msg" => "Simple valid list 1", "template" => array("field_name"=>true, "generated"=>false, "unique"=>false, "editable"=>true), "list" => array(), "k"=>"field_name"),
			array('t' => "List", 'v' => array("field_name" => "a", "editable" => true,  "generated" => true), "valid" => true, "msg" => "Simple valid list 2", "template" => array("field_name"=>true, "generated"=>false, "unique"=>false, "editable"=>true),"k"=>"field_name", "list" => array()),
			array('t' => "List", 'v' => array("field_name" => "a", "editable" => false, "unique" => true), "valid" => true, "msg" => "Simple valid list 3", "template" => array("field_name"=>true, "generated"=>false, "unique"=>false, "editable"=>true), "k"=>"field_name", "list" => array()),
			array('t' => "List", 'v' => array("field_name" => "a", "editable" => false, "unique" => true), "valid" => true, "msg" => "Simple valid list 4", "template" => array("field_name"=>true, "generated"=>false, "unique"=>false, "editable"=>true), "k"=>"field_name", "list" => array(array("field_name" => "b", "editable"=>true))),
		
			array('t' => "List", 'v' => array(), "valid" => false, "msg" => "Invalid empty", "template" => array("field_name"=>true, "generated"=>false, "unique"=>false, "editable"=>true), "k"=>"field_name", "list" => array()),
			array('t' => "List", 'v' => null, "valid" => false, "msg" => "Invalid null", "template" => array("field_name"=>true, "generated"=>false, "unique"=>false, "editable"=>true), "k"=>"field_name", "list" => array()),
			array('t' => "List", 'v' => 10, "valid" => false, "msg" => "Invalid number", "template" => array("field_name"=>true, "generated"=>false, "unique"=>false, "editable"=>true), "k"=>"field_name", "list" => array()),
			array('t' => "List", 'v' => array("editable" => true), "valid" => false, "msg" => "Invalid missing mendatory field", "template" => array("field_name"=>true, "generated"=>false, "unique"=>false, "editable"=>true), "k"=>"field_name", "list" => array()),
			array('t' => "List", 'v' => array("field_name" => true), "valid" => false, "msg" => "Invalid missing mendatory field 2", "template" => array("field_name"=>true, "generated"=>false, "unique"=>false, "editable"=>true), "k"=>"field_name", "list" => array()),
			array('t' => "List", 'v' => array("field_name" => true, "unique" => true), "valid" => false, "msg" => "Invalid missing mendatory field 3", "template" => array("field_name"=>true, "generated"=>false, "unique"=>false, "Nonsense"=>false, "editable"=>true), "k"=>"field_name", "list" => array()),
			array('t' => "List", 'v' => array("editable" => true, "unique" => true), "valid" => false, "msg" => "Invalid missing mendatory field 4", "template" => array("field_name"=>true, "generated"=>false, "unique"=>false, "k"=>false, "editable"=>true), "k"=>"field_name", "list" => array()),
		
			array('t' => "List", 'v' => array("field_name" => "a", "editable" => true), "valid" => false, "msg" => "Already existing", "template" => array("field_name"=>true, "generated"=>false, "unique"=>false, "k"=>false, "editable"=>true), "k"=>"field_name", "list" => array(array("system" => true, "field_name" => "a", "editable"=>false))),
			array('t' => "List", 'v' => array("field_name" => "a", "editable" => true), "valid" => true, "msg" => "Already existing", "template" => array("field_name"=>true, "generated"=>false, "unique"=>false, "k"=>false, "editable"=>true), "k"=>"field_name", "list" => array(array("field_name" => "a", "editable"=>true))),
		
		
		);
	
	public function testComplexWrappers() {
		foreach ($this->complexWrapperTests as $test) {
			$wrapper = Billrun_Config::getComplexWrapper($test);
			if($wrapper === null) {
				continue;
			}
			$result = $wrapper->validate();
			$this->assertEqual($result, $test['valid'], $test['msg']);
		}
    }
	
//	protected function getWrapper($complex) {
//		$name = "Billrun_DataTypes_Conf_" . ucfirst(strtolower($complex['t']));
//		if(!@class_exists($name)) {
//			return null;
//		}
//		
//		$wrapper = new $name($complex);
//		return $wrapper;
//	}
}
