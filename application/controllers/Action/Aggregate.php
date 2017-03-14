<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Aggregate action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       1.0
 */
class AggregateAction extends Action_Base {

	/**
	 * method to execute the aggregate process
	 * it's called automatically by the cli main controller
	 */
	public function execute() {

		$possibleOptions = array(
			'type' => false,
			'stamp' => true,
			'page' => true,
			'size' => true,
			'fetchonly' => true,
		);

		if (($options = $this->_controller->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}
		if (!$this->shouldRunAggregate($options['stamp'])) {
			$this->_controller->addOutput("Can't run aggregate before end of billing cycle");
			return;
		}
		$this->_controller->addOutput("Loading aggregator");
		$aggregator = Billrun_Aggregator::getInstance($options);
		$this->_controller->addOutput("Aggregator loaded");

		if (!$aggregator || !$aggregator->isValid()) {
			$this->_controller->addOutput("Aggregator cannot be loaded");
			return;
		}

		$this->_controller->addOutput("Loading data to Aggregate...");
		$aggregator->load();
		if (isset($options['fetchonly'])) {
			$this->_controller->addOutput("Only fetched aggregate accounts info. Exit...");
		}

		$this->_controller->addOutput("Starting to Aggregate. This action can take a while...");
		$aggregator->aggregate();
		$this->_controller->addOutput("Finish to Aggregate.");
	}
	
	protected function shouldRunAggregate($stamp) {
		$allowPrematureRun = (int)Billrun_Factory::config()->getConfigValue('cycle.allow_premature_run');
		if (!$allowPrematureRun && time() < Billrun_Billingcycle::getEndTime($stamp)) {
			return false;
		}
		return true;
	}
	
}