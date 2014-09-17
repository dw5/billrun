<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Googledcb
 *
 * @author eran
 */
class Billrun_Processor_Googledcb extends Billrun_Processor_Base_SeparatorFieldLines {

	static protected $type = 'googledcb';

	/**
	 * Hold the structure configuration data.
	 */
	protected $structConfig = false;

	public function __construct($options = array()) {
		parent::__construct($options);

		$this->loadConfig(Billrun_Factory::config()->getConfigValue($this->getType() . '.config_path'));
	}

	protected function parse() {
		$this->parser->setSeparator($this->structConfig['config']['separator']);
//		if (isset($this->structConfig['config']['add_filename_data_to_header']) && $this->structConfig['config']['add_filename_data_to_header']) {
//			$this->data['header'] = array_merge($this->buildHeader(''), array_merge((isset($this->data['header']) ? $this->data['header'] : array()), $this->getFilenameData(basename($this->filePath))));
//		}
		// Billrun_Factory::log()->log("sms : ". print_r($this->data,1),Zend_Log::DEBUG);

		return parent::parse();
	}

	/**
	 * @see Billrun_Processor_Base_FixedFieldsLines::isValidDataRecord($dataLine)
	 */
	protected function isValidDataRecord($dataLine) {
		return true; //preg_match( $this->structConfig['config']['valid_data_line'], );
	}

	/**
	 * Find the line type  by checking  if the line match  a configuraed regex.
	 * @param type $line the line to check.
	 * @param type $length the lengthh of the line,
	 * @return string H/T/D  depending on the type of the line.
	 */
	protected function getLineType($line, $length = 1) {
		foreach ($this->structConfig['config']['line_types'] as $key => $val) {
			if (preg_match($val, $line)) {
				//	Billrun_Factory::log()->log("line type key : $key",Zend_Log::DEBUG);
				return $key;
			}
		}
		return parent::getLineType($line, $length);
	}

	/**
	 * the structure configuration
	 * @param type $path
	 */
	protected function loadConfig($path) {
		$this->structConfig = (new Yaf_Config_Ini($path))->toArray();

		$this->header_structure = $this->structConfig['header'];
		$this->data_structure = $this->structConfig['data'];
		$this->trailer_structure = $this->structConfig['trailer'];
	}

	protected function buildData($line, $line_number = null) {
		$row = parent::buildData($line, $line_number);
		if (isset($row[$this->structConfig['config']['date_field']])) {
			$date_value = $row[$this->structConfig['config']['date_field']];
			unset($row[$this->structConfig['config']['date_field']]);
			$row['urt'] = new MongoDate($date_value);
		}
		if (!empty($this->structConfig['stamp_fields'])) { // todo: apply to all processors
			$row['stamp'] = md5(serialize(array_intersect_key($row, array_flip($this->structConfig['stamp_fields']))));
		}
		return $row;
	}
	
	/**
	 * decrypt and then load file to be handle by the processor
	 * 
	 * @param string $file_path
	 * 
	 * @return void
	 */
	public function loadFile($file_path, $retrivedHost = '') {
		$pgpConfig = Billrun_Factory::config()->getConfigValue('googledcb.pgp', array());
		$decrypted_file_path = str_replace('.pgp', '', $file_path);
		Billrun_Pgp::getInstance($pgpConfig)->decrypt_file($file_path, $decrypted_file_path);
		$file_path = $decrypted_file_path;
		
		parent::loadFile($file_path, $retrivedHost);
	}
}

?>
