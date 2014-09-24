<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing Csv generator class
 *
 * @package  Billing
 * @since    2.8
 */
class Billrun_Generator_Googledcb extends Billrun_Generator_Csv {

	const GOOGLE_RESPONSE_HEADER = 'RESPONSEFILE';
	const GOOGLE_RESPONSE_CODE_SUCCESS = 'SUCCESS';
	const GOOGLE_RESPONSE_CODE_ACCOUNT_CLOSED = 'ACCOUNT_CLOSED';
	const GOOGLE_RESPONSE_CODE_CHARGE_TOO_OLD = 'CHARGE_TOO_OLD';

	protected $delete_after_generate = false;
	protected $log;

	public function __construct($options) {
		if (isset($options['delete_after_generate'])) {
			$this->delete_after_generate = $options['delete_after_generate'];
		}

		parent::__construct(array_merge($options, array(
			'export_directory' => Billrun_Factory::config()->getConfigValue('googledcb.response_backup_path'),
			'disable_stamp_export_directory' => true
		)));
	}

	protected function buildHeader() {
		$unique = str_replace(".csv", '', end(explode("_", $this->filename)));
		if (isset($this->log['header']['billing_agreement_id'])) {
			$this->headers = array(
				'credit_type' => self::GOOGLE_RESPONSE_HEADER,
				'process_time' => time(),
				'correlation_id' => $unique,
				'billing_agreement' => $this->log['header']['billing_agreement_id'],
			);
		}
	}

	protected function getRowContent($entity) {
		$row_contents = '';

		foreach ($this->headers as $key => $field_name) {
			if ($key == 'process_time') {
				$entity[$key] = strtotime($entity[$key]);
			}
			$row_contents.=(isset($entity[$key]) ? $entity[$key] : "") . $this->separator;
		}

		$result = self::GOOGLE_RESPONSE_CODE_SUCCESS;

		if (!isset($entity['aid']) && !is_null($entity['aid'])) {
			$result = self::GOOGLE_RESPONSE_CODE_ACCOUNT_CLOSED;
		}
		if (!isset($entity['aprice'])) {
			$result = self::GOOGLE_RESPONSE_CODE_CHARGE_TOO_OLD;
		}
		$row_contents.= $result . $this->separator;
		if (isset($entity['billrun'])) {
			$billrun = $entity['billrun'];
		}
		else {
			$billrun = '';
		}
		$row_contents.= 'Billrun: ' . $billrun . $this->separator;

		return $row_contents;
	}

	protected function setFilename() {
		$modelLog = new LogModel();
		$modelLines = new LinesModel();
		$qureyLog = array(
			"process_time" => array('$exists' => true),
			"generate_time" => array('$exists' => false),
		);
		$this->log = $modelLog->getDataByStamp($qureyLog)->getRawData();

		if (is_null($this->log) || !isset($this->log['path'])) {
			return;
		}

		$queryLines = array(
			"type" => "credit",
			"log_stamp" => $this->log['stamp']
		);
		$lines = $modelLines->getData($queryLines);

		if (is_null($lines) || !$lines) {
			Billrun_Factory::log('Generator: no line found with stamp: ' . $this->log['stamp'], Zend_log::ALERT);
			return;
		}

		$request_filename = basename($this->log['path']);
		$filenameConf = Billrun_Factory::config()->getConfigValue('googledcb.filename', array());
		$this->filename = str_replace($filenameConf['request_pref'], $filenameConf['response_pref'], $request_filename);
		$this->filename = str_replace('.pgp', '', $this->filename);
	}

	public function load() {
		$modelLines = new LinesModel();
		if (isset($this->log['stamp'])) {
			$log_stamp = $this->log['stamp'];
		}
		else {
			$log_stamp = 'no log';
		}

		$queryLines = array(
			"type" => "credit",
			"log_stamp" => $log_stamp
		);
		$this->data = $modelLines->getCursor($queryLines);
		Billrun_Factory::log()->log("aggregator entities loaded: " . $this->data->count(), Zend_Log::INFO);
		Billrun_Factory::dispatcher()->trigger('afterGeneratorLoadData', array('generator' => $this));
	}

	public function generate() {
		parent::generate();

		// Updates DB to generated
		$modelLog = new LogModel();
		$this->log['generate_time'] = date(Billrun_Base::base_dateformat);
		$modelLog->update($this->log);

		// Encrypt file
		$pgpConfig = Billrun_Factory::config()->getConfigValue('googledcb.pgp', array());
		$encrypted_file_path = $this->file_path . '.pgp';
		Billrun_Pgp::getInstance($pgpConfig)->encrypt_file($this->file_path, $encrypted_file_path);

		// Move files to Googles' SFTP
		$sshConfig = Billrun_Factory::config()->getConfigValue('googledcb.ssh', array());
		$ssh = new Billrun_Ssh_Seclibgateway($sshConfig['host'], array('key' => $sshConfig['key']), array());
		$ssh->connect($sshConfig['user']);
		$ssh->put($encrypted_file_path, $sshConfig['remote_directory'] . DIRECTORY_SEPARATOR .
			$sshConfig['response_directory'] . DIRECTORY_SEPARATOR . basename($encrypted_file_path));

		// Deletes temp file
		if ($this->delete_after_generate) {
			Billrun_Factory::log()->log("Removing file {$this->file_path} from the workspace", Zend_Log::INFO);
			unlink($this->file_path);
			Billrun_Factory::log()->log("Removing file {$encrypted_file_path} from the workspace", Zend_Log::INFO);
			unlink($encrypted_file_path);
		}
	}

}
