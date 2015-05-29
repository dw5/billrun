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

	/**
	 * Holds path to decrypted file path
	 */
	protected $decrypted_file_path;

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
			$date_value /= 1000; // Converts milliseconds to seconds
			$row['credit_time'] = $date_value;
		}

		$row['credit_type'] = strtolower($row['record_type']);
		$row['service_name'] = 'GOOGLE_DCB';
		$row['reason'] = 'GOOGLE_DCB';
		$fundsModel = new FundsModel();
		$correlation = $fundsModel->getNotificationData($row['correlation_id']);

		if (!$correlation) {
			// TODO: check what to do in case no correlation was found
			Billrun_Factory::log()->log("Correlation id not found : " . $row['correlation_id'], Zend_Log::ALERT);
			return false;
		}

		require_once APPLICATION_PATH . '/application/helpers/Dcb/Soap/Handler.php';
		$amount = Dcb_Soap_Handler::fromMicros($correlation['ItemPrice']);
		$vatable = false;

		if ($correlation['Tax']) {
			$amount /= (1 + Billrun_Factory::config()->getConfigValue('pricing.vat'));
			$vatable = true;
		}

		$row = array_merge($row, $correlation);
		$row['amount_without_vat'] = $amount;
		$row['vatable'] = $vatable;
		$row['account_id'] = $row['aid'];
		$row['subscriber_id'] = $row['sid'];

		// If it's not a "cancel" line - it should be treated as credit line
		if ($row['credit_type'] !== 'cancel') {
			$ret = Billrun_Util::parseCreditRow($row); // Same behavior as credit 
			if (isset($ret['status']) && $ret['status'] == 0) {
				$error_message = isset($ret['desc']) ? $ret['desc'] : 'Error with credit row';
				return $this->setError($error_message, $row);
			}
			
		} 
		// If the line is of type "cancel", we should cancel the reservation of funds
		// and it should not be treated as "credit" line
		else {
			$ret = $row;
			$credit_time = new Zend_Date($ret['credit_time']);
			$ret['urt'] = new MongoDate($credit_time->getTimestamp());
			unset($ret['credit_time']);
			$ret['source'] = 'api';
			$ret['usaget'] = $ret['type'] = 'credit';
			ksort($ret);
			$ret['billable'] = false;
			$ret['stamp'] = Billrun_Util::generateArrayStamp($ret);
			$fundsModel->cancelNotification($ret);
		}

		$ret['log_stamp'] = $this->getFileStamp();
		$ret['process_time'] = $row['process_time'];
		$ret['correlation_id'] = $row['correlation_id'];
		$ret['billing_agreement'] = $row['billing_agreement'];
		return $ret;
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
		$this->decrypted_file_path = str_replace('.pgp', '', $file_path);
		Billrun_Pgp::getInstance($pgpConfig)->decrypt_file($file_path, $this->decrypted_file_path);
		$file_path = $this->decrypted_file_path;

		parent::loadFile($file_path, $retrivedHost);
	}

	/**
	 * removes backedup files from workspace, also removes decrypted files
	 * 
	 * @param string $filestamp
	 * 
	 * @return void
	 */
	protected function removeFromWorkspace($filestamp) {
		parent::removeFromWorkspace($filestamp);

		// Remove decrypted file as well
		Billrun_Factory::log()->log("Removing file {$this->decrypted_file_path} from the workspace", Zend_Log::INFO);
		unlink($this->decrypted_file_path);
		$this->clearDir(dirname($this->filePath));
	}

	protected function clearDir($dir) {
		// Can only remove up to 3 sub folders - YYYY/MM/DD
		for ($i = 0; $i < 3; $i++) {
			$filecount = count(glob($dir . "/*"));

			// If there are files in the folder
			if ($filecount) {
				return;
			}

			rmdir($dir);
			$dir = dirname($dir);
		}
	}

}

?>
