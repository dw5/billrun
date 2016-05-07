<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing send request controller class
 * Used to send request in a new fork
 * 
 * @package  Controller
 * @since    4.1
 */
class SendrequestController extends Yaf_Controller_Abstract {

	protected $start_time = 0;

	public function init() {
		Billrun_Factory::log("Start Sendrequest call", Zend_Log::INFO);
		if ($this->getRequest()->getServer('REMOTE_ADDR') != $this->getRequest()->getServer('SERVER_ADDR')) {
			Billrun_Factory::log('Remote access to sendrequest controller which is internal call. IP: ' . $this->getRequest()->getServer('REMOTE_ADDR'), Zend_Log::WARN);
			$this->forward('index', 'index');
		}
		$this->start_time = microtime(1);
	}

	/**
	 * default action
	 * 
	 * @return void
	 */
	public function indexAction() {
		$this->forward('index', 'index');
	}

	/**
	 * send a request
	 * 
	 * @return void
	 */
	public function sendRequestAction() {
		$request = $this->getRequest();
		if (!$request) {
			return false;
		}
		$requestBody = $request->get('request');
		$requestUrl = $request->get('requestUrl');
		$numOfTries = $request->get('numOfTries');
		if ($this->sendRequest($requestBody, $requestUrl, $numOfTries)) {
			$additionalParams = $request->get('additionalParams');
			$this->updateSubscriberInDB($additionalParams);
			return true;
		}
		return false;
	}
	
	protected function sendRequest($requestBody, $requestUrl, $numOfTries) {
		$logColl = Billrun_Factory::db()->logCollection();
		$saveData = array(
			'source' => 'pelephonePlugin',
			'type' => 'sendRequest',
			'process_time' => new MongoDate(),
			'request' => $requestBody,
			'response' => array(),
			'server_host' => gethostname(),
			'request_host' => $_SERVER['REMOTE_ADDR'],
			'rand' => rand(1, 1000000),
		);
		$saveData['stamp'] = Billrun_Util::generateArrayStamp($saveData);
		for ($i = 0; $i < $numOfTries; $i++) {
			Billrun_Factory::log('Sending request to prov, try number ' . ($i + 1) . '. Details: ' . $requestBody, Zend_Log::DEBUG);
			$response = Billrun_Util::sendRequest($requestUrl, $requestBody);
			if ($response) {
				array_push($saveData['response'], 'attempt ' . ($i + 1) . ': ' . $response);
				Billrun_Factory::log('Got response from prov. Details: ' . $response, Zend_Log::DEBUG);
				$decoder = new Billrun_Decoder_Xml();
				$response = $decoder->decode($response);
				if (isset($response['HEADER']['STATUS_CODE']) &&
					$response['HEADER']['STATUS_CODE'] === 'OK') {
					$saveData['time'] = (microtime(1) - $this->start_time) * 1000;
					$saveData['success'] = true;
					$logColl->save(new Mongodloid_Entity($saveData), 0);
					return true;
				}
			}
		}
		Billrun_Factory::log('No response from prov. Request details: ' . $requestBody, Zend_Log::ALERT);
		$saveData['time'] = (microtime(1) - $this->start_time) * 1000;
		$saveData['success'] = false;
		$logColl->save(new Mongodloid_Entity($saveData), 0);
		$this->handleSendRequestError();
		return false;
	}
	
	protected function updateSubscriberInDB($additionalParams) {
		if (isset($additionalParams['dataSlownessRequest']) && $additionalParams['dataSlownessRequest']) {
			$enterDataSlowness = $additionalParams['enterDataSlowness'];
			$sid = $additionalParams['sid'];
			// Update subscriber in DB
			$subscribersColl = Billrun_Factory::db()->subscribersCollection();
			$findQuery = array_merge(Billrun_Util::getDateBoundQuery(), array('sid' => $sid));
			if ($enterDataSlowness) {
				$updateQuery = array('$set' => array('in_data_slowness' => true));
			} else {
				$updateQuery = array('$unset' => array('in_data_slowness' => 1));
			}
			$subscribersColl->update($findQuery, $updateQuery);
		}
	}

	protected function handleSendRequestError() {
		
	}

}
