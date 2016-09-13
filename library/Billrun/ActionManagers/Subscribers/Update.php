
<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a parser to be used by the subscribers action.
 *
 */
class Billrun_ActionManagers_Subscribers_Update extends Billrun_ActionManagers_Subscribers_Action {
	use Billrun_ActionManagers_Subscribers_Servicehandler;
	use Billrun_ActionManagers_Subscribers_Validator {
		validateOverlap as baseValidateOverlap;
	}
	
	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $query = array();
	protected $update = array();
	protected $oldEntity = array();
	protected $time;
	
	/**
	 */
	public function __construct() {
		parent::__construct(array('error' => "Success creating subscriber"));
	}

	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		try {
			$this->time = new MongoDate();
			if (!$oldEntity = $this->getOldEntity()) {
				return false;
			}
			$newEntity = $this->updateEntity($this->oldEntity);
				
			// Check if changed plans.
			if($newEntity['plan'] !== $this->oldEntity['plan']) {
				$this->oldEntity['plan_deactivation'] = new MongoDate();
			}
			
			$this->closeEntity($this->oldEntity);
		} catch (\Exception $e) {
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 1;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			Billrun_Factory::log($e->getCode() . ": " . $e->getMessage(), Billrun_Log::WARN);
		}

		$outputResult = array(
			'status' => $this->errorCode == 0 ? 1 : 0,
			'desc' => $this->error,
			'error_code' => $this->errorCode,
		);

		if (isset($oldEntity)) {
			$outputResult['details']['before'] = $oldEntity->getRawData();
		}
		if (isset($newEntity)) {
			$outputResult['details']['after'] = $newEntity->getRawData();
		}
		return $outputResult;
	}
	
	protected function getOldEntity() {
		$old = Billrun_Factory::db()->subscribersCollection()->query($this->query)->cursor()->current();
		if ($old->isEmpty()) {
			return false;
		}
		return $old;
	}
	
	protected function updateEntity($oldEntity){
		$new = $oldEntity->getRawData();
		unset($new['_id']);
		$new['from'] = $this->time;
		foreach ($this->update as $field => $value) {
			$new[$field] = $value;
		}
		$newEntity = new Billrun_Subscriber_Entity($new, $oldEntity['plan']);
		$this->collection->save($newEntity, 1);
		return $newEntity;
	}
	
	protected function closeEntity($entity) {
		$entity['to'] = $this->time;
		$this->collection->save($entity, 1);
	}

	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
		if (!parent::parse($input) || !$this->setQueryRecord($input)) {
			return false;
		}
		
		$this->oldEntity = $this->getOldEntity();
		if($this->oldEntity === false) {
			// [SUBSCRIBERS error 1037]
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 37;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}
		
		return $this->validate();
	}

	protected function validateOverlap() {
		$this->validatorData['_id'] = $this->oldEntity['_id'];
		if(!isset($this->validatorData['sid'])) {
			$this->validatorData['sid'] = $this->query['sid'];
		}
		if(!isset($this->validatorData['aid'])) {
			$this->validatorData['aid'] = $this->query['aid'];
		}
		$this->validatorData['type'] = $this->type;
		return $this->baseValidateOverlap(false);
	}

		/**
	 * Set the values for the query record to be set.
	 * @param httpRequest $input - The input received from the user.
	 * @return true if successful false otherwise.
	 */
	protected function setQueryRecord($input) {
		$jsonData = null;
		$query = $input->get('query');
		if (empty($query) || (!($jsonData = json_decode($query, true)))) {
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 2;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}
		
		$invalidFields = $this->setQueryFields($jsonData);
		// If there were errors.
		if (!empty($invalidFields)) {
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 3;
			$this->reportError($errorCode, Zend_Log::NOTICE, array(implode(',', $invalidFields)));
			return false;
		}

		$update = $input->get('update');
		if (empty($update) || (!($jsonData = json_decode($update, true))) || !$this->setUpdateFields($jsonData)) {
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 2;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}
		

		return true;
	}

	/**
	 * Set all the query fields in the record with values.
	 * @param array $queryData - Data received.
	 * @return array - Array of strings of invalid field name. Empty if all is valid.
	 */
	protected function setQueryFields($queryData) {
		$this->query = Billrun_Utils_Mongo::getDateBoundQuery();
		$this->query['type'] = $this->type;
		if ($this->type === 'account') {
			$queryMandatoryFields = array('aid');
		} else {
			$queryMandatoryFields = array('sid');
		}
		// Array of errors to report if any error occurs.
		$invalidFields = array();

		// Get only the values to be set in the update record.
		foreach ($queryMandatoryFields as $fieldName) {
			if (!isset($queryData[$fieldName]) || empty($queryData[$fieldName])) {
				$invalidFields[] = $fieldName;
			} else if (isset($queryData[$fieldName])) {
				$this->query[$fieldName] = $queryData[$fieldName];
			}
		}

		return $invalidFields;
	}
	
	/**
	 * Set all the update fields in the record with values.
	 * @param array $updateData - Data received.
	 * @return bool
	 */
	protected function setUpdateFields($updateData) {
		// Get only the values to be set in the update record.
		foreach ($this->fields as $field) {
			$fieldName = $field['field_name'];
			if (isset($field['editable']) && !$field['editable']) {
				continue;
			}
			if (!isset($updateData[$fieldName])) {
				continue;
			}
			
			// TODO: Create some sort of polymorphic behaviour to correctly handle
			// the updating fields.
			if($fieldName === 'services') {
				$toSet = $this->getSubscriberServices($updateData['services']);
			} else {
				$toSet = $updateData[$fieldName];
			}
			
			if(empty($toSet)) {
				continue;
			}
			
			$this->update[$fieldName] = $toSet;
		}
		
		return true;
	}

	protected function getSubscriberData() {
		return $this->update;
	}

}
