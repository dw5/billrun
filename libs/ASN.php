<?php
/**
 * @package			ASN
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * This calls is used to parse ASN.1 encoded data.
 *
 * @package  ASN
 * @since    1.0
 */

class ASN {
	public $asnData = null;
	private $cursor = 0;
	private $parent = null;

	public static $ASN_MARKERS = array(
		'ASN_UNIVERSAL'		=> 0x00,
		'ASN_APPLICATION'	=> 0x40,
		'ASN_CONTEXT'		=> 0x80,
		'ASN_PRIVATE'		=> 0xC0,

		'ASN_PRIMITIVE'		=> 0x00,
		'ASN_CONSTRUCTOR'	=> 0x20,

		'ASN_LONG_LEN'		=> 0x80,
		'ASN_EXTENSION_ID'	=> 0x1F,
		'ASN_BIT'		=> 0x80,
		'ASN_EOC'		=> 0x00,
	);

	public static $ASN_TYPES = array(
		0x0	=> 'ASN_GENERAL',
		0x1	=> 'ASN_BOOLEAN',
		0x2	=> 'ASN_INTEGER',
		0x3	=> 'ASN_BIT_STR',
		0x4	=> 'ASN_OCTET_STR',
		0x5	=> 'ASN_NULL',
		0x6	=> 'ASN_OBJECT_ID',
		0x7	=> 'ASN_OBJECT_DESC',
		0x8	=> 'ASN_EXTERNAL',
		0x9	=> 'ASN_REAL',
		0xA	=> 'ASN_ENUMERATED',
		0xB	=> 'ASN_EMBEDDED_PDV',
		0xC	=> 'ASN_UTF_STR',
		0xD	=> 'ASN_RELATIVE_OID',
		0x10	=> 'ASN_SEQUENCE',
		0x11	=> 'ASN_SET',
		0x12	=> 'ASN_NUM_STR',
		0x13	=> 'ASN_PRINT_STR',
		0x14	=> 'ASN_T61_STR',
		0x15	=> 'ASN_VIDTEX_STR',
		0x16	=> 'ASN_IA5_STR',
		0x17	=> 'ASN_UTC_TIME',
		0x18	=> 'ASN_GENERAL_TIME',
		0x19	=> 'ASN_GRAPHIC_STR',
		0x1A	=> 'ASN_VISIBLE_STR',
		0x1B	=> 'ASN_GENERAL_STR',
		0x1C	=> 'ASN_UNIVERSAL_STR',
		0x1D	=> 'ASN_CHAR_STR',
		0x1E	=> 'ASN_BMP_STR',
		0x1F	=> 'ASN_LONG_FORM',
	);

	function __construct($v = false)
	{
		if (false !== $v) {
			$this->asnData = $v;
			if (is_array($this->asnData)) {
				foreach ($this->asnData as $key => $value) {
					if (is_object($value)) {
						$this->asnData[$key]->setParent($this);
					}
				}
			} else {
				if (is_object($this->asnData)) {
					$this->asnData->setParent($this);
				}
			}
		}
	}

	public function setParent($parent)
	{
		if (false !== $parent) {
			$this->parent = $parent;
		}
	}

	/**
	 * This function will take the markers and types arrays and
	 * dynamically generate classes that extend this class for each one,
	 * and also define constants for them.
	 */
	public static function generateSubclasses()
	{
		define('ASN', 0);
		foreach (self::$ASN_MARKERS as $name => $bit)
			self::makeSubclass($name, $bit);
		foreach (self::$ASN_TYPES as $bit => $name)
			self::makeSubclass($name, $bit);
	}

	/**
	 * Helper function for generateSubclasses()
	 */
	public static function makeSubclass($name, $bit)
	{
		define($name, $bit);
		eval("class ".$name." extends ASN {}");
	}

	/**
	 * This function reset's the internal cursor used for value iteration.
	 */
	public function reset()
	{
		$this->cursor = 0;
	}

	/**
	 * This function catches calls to get the value for the type, typeName, value, values, and data
	 * from the object.  For type calls we just return the class name or the value of the constant that
	 * is named the same as the class.
	 */
	public function __get($name)
	{
		if ('type' == $name) {
			// int flag of the data type
			return constant(get_class($this));
		} elseif ('typeName' == $name) {
			// name of the data type
			return get_class($this);
		} elseif ('value' == $name) {
			// will always return one value and can be iterated over with:
			// while ($v = $obj->value) { ...
			// because $this->asnData["invalid key"] will return false
			return is_array($this->asnData) ? $this->asnData[$this->cursor++] : $this->asnData;
		} elseif ('values' == $name) {
			// will always return an array
			return is_array($this->asnData) ? $this->asnData : array($this->asnData);
		} elseif ('data' == $name) {
			// will always return the raw data
			return $this->asnData;
		}
	}

	/**
	 * Parse an ASN.1 binary string.
	 *
	 * This function takes a binary ASN.1 string and parses it into it's respective
	 * pieces and returns it.  It can optionally stop at any depth.
	 *
	 * @param	string	$string		The binary ASN.1 String
	 * @param	int	$level		The current parsing depth level
	 * @param	int	$maxLevel	The max parsing depth level
	 * @return	ASN	The array representation of the ASN.1 data contained in $string
	 */
	public static $parsedLength = 0;
	public static function parseASNString($string=false, $level=1, $maxLevels=false){
		if (!class_exists('ASN_UNIVERSAL'))
			self::generateSubclasses();
		if ($level>$maxLevels && $maxLevels)
			return array(new ASN($string));
		$parsed = array();
		$endLength = strlen($string);
		$bigLength = $length = $type = $p = 0;
		while ($p<$endLength){
			$type = ord($string[$p++]);
			if((count($parsed) > 0 && $level == 1)){
				ASN::$parsedLength = $p;
				break;
			}
			if ($type==0){ // if we are type 0, just continue
				break;
			} else {
				if( ($type & ASN_CONTEXT) && (($type & 0x1F) == 0x1F)) {
					$type = ord($string[$p++]);
// 					print("long ".dechex($type));
				}
				$length = ord($string[$p++]);
				if (($length & ASN_LONG_LEN) == ASN_LONG_LEN){
					$tempLength = 0;
					for ($x=($length-ASN_LONG_LEN); $x > 0; $x--){
						$tempLength = ord($string[$p++]) + ($tempLength << 8);
					}
					$length = $tempLength;

				}

// 				for($i=0;$i<$level;$i++) {
// 					print('	');
// 				}
// 				print(dechex($length)."	".dechex($type)."\n");
				//if($length==0) {print('33333333 '.dechex($type));}
				$data = substr($string, $p, ( $length > 0 ? $length : (strlen($string)-$p)) );
				$parsed[] = self::parseASNData($type, $data, $level, $maxLevels)->asnData;
				$p = $p + $length;
			}
		}
		return $parsed;
	}

	/**
	 * Parse an ASN.1 field value.
	 *
	 * This function takes a binary ASN.1 value and parses it according to it's specified type
	 *
	 * @param	int	$type		The type of data being provided
	 * @param	string	$data		The raw binary data string
	 * @param	int	$level		The current parsing depth
	 * @param	int	$maxLevels	The max parsing depth
	 * @return	mixed	The data that was parsed from the raw binary data string
	 */
	public static function parseASNData($type, $data, $level, $maxLevels){
		$constracted = $type & ASN_CONSTRUCTOR;
		$type = $type&0x1F; // strip out context
		switch($type) {
			case ASN_BOOLEAN: $data = (bool)$data;
				break;
			//case ASN_OBJECT_ID: $data = self::parseOID($data);
			//	break;
		}
		$val = $constracted || $type == 0x10  || $type == 0x1F ? self::parseASNString($data, $level+1, $maxLevels) : $data;
		if(isset(ASN::$ASN_TYPES[$type])) {
			$clsType = ASN::$ASN_TYPES[$type];
			$cls = new $clsType($val);
		} else {
// 			print("not detected : ". dechex($type));
			$cls = new ASN($val);
		}
		return $cls;
	}

	/**
	 * Parse an ASN.1 OID value.
	 *
	 * This takes the raw binary string that represents an OID value and parses it into its
	 * dot notation form.  example - 1.2.840.113549.1.1.5
	 * look up OID's here: http://www.oid-info.com/
	 * (the multi-byte OID section can be done in a more efficient way, I will fix it later)
	 *
	 * @param	string	$data		The raw binary data string
	 * @return	string	The OID contained in $data
	 */
	public static function parseOID($string){
		$ret = floor(ord($string[0])/40).".";
		$ret .= (ord($string[0]) % 40);
		$build = array();
		$cs = 0;

		for ($i=1; $i<strlen($string); $i++){
			$v = ord($string[$i]);
			if ($v>127){
				$build[] = ord($string[$i])-ASN_BIT;
			} elseif ($build){
				// do the build here for multibyte values
				$build[] = ord($string[$i])-ASN_BIT;
				// you know, it seems there should be a better way to do this...
				$build = array_reverse($build);
				$num = 0;
				for ($x=0; $x<count($build); $x++){
					$mult = $x==0?1:pow(256, $x);
					if ($x+1==count($build)){
						$value = ((($build[$x] & (ASN_BIT-1)) >> $x)) * $mult;
					} else {
						$value = ((($build[$x] & (ASN_BIT-1)) >> $x) ^ ($build[$x+1] << (7 - $x) & 255)) * $mult;
					}
					$num += $value;
				}
				$ret .= ".".$num;
				$build = array(); // start over
			} else {
				$ret .= ".".$v;
				$build = array();
			}
		}
		return $ret;
	}

}


