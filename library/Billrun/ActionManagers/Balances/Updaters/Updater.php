<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Description of Updater
 *
 * @author tom
 */
abstract class Billrun_ActionManagers_Balances_Updaters_Updater {
	
	/**
	 * If true then the values in mongo are updated by incrementation,
	 * if false then the values in the mongo are forceablly set.
	 * @var boolean. 
	 */
	protected $isIncrement = true;
	
	/**
	 * Any request for balance incrementation when "$ignoreOveruse" value is true and the current account balance queried
	 * exceeds the maximum allowance (balance is above zero), will reset the balance (to zero) and only then increment it.
	 * This means that if the user had a positive balance e.g 5 and then was loaded with 100 units, the blance will be -100 and not -95.
	 * @var boolean 
	 */
	protected $ignoreOveruse = true;
	
	/**
	 * Create a new instance of the updater class.
	 * @param array $options - Holding:
	 *						   increment - If true then the values in mongo are updated by incrementation,
	 *									   if false then the values in the mongo are forceablly set.
	 *						   zero - If requested to update by incrementing but the existing 
	 *								  value is larger than zero than zeroise the value.
	 */
	public function __construct($options) {
		// If it is not set, the default is used.
		if(isset($options['increment'])) {
			$this->isIncrement = $options['increment'];
		}
		
		// If it is not set, the default is used.
		if(isset($options['zero'])) {
			$this->ignoreOveruse = $options['zero'];
		}
	}

	/**
	 * TODO: This kind of translator might exist, but if it does we need a more generic way. Best if not needed at all.
	 * Update the field names to fit what is in the mongo.
	 * @param type $query - Record to be update in the db.
	 * @param type $translationTable - Table to use to translate the values.
	 */
	protected function translateFieldNames($query, $translationTable){
		$translatedQuery = array();
		foreach ($translationTable as $oldName => $newName) {
			if(isset($query[$oldName])){
				$translatedQuery[$newName] = $query[$oldName];
			}
		}
		
		return $translatedQuery;
	}
	
	/**
	 * Get the query to run on the plans collection in mongo.
	 * @param type $query Input query to proccess.
	 * @return type Query to run on plans collection.
	 */
	protected function getPlanQuery($query) {
		// Single the type to be charging.
		$planQuery = array('type' => 'charging', 'to' => array('$gt', new MongoDate()));
		
		$fieldNamesTranslate =
			array('charging_plan'			  => 'name',
				  'charging_plan_external_id' => 'external_id');
				
		// Fix the update record field names.
		return array_megrge($this->translateFieldNames($query, $fieldNamesTranslate), $planQuery);
	}
	
	/**
	 * Get the record plan according to the input query.
	 * @param type $query
	 * @param type $chargingPlanCollection
	 * @return type
	 */
	protected function getPlanRecord($query, $chargingPlanCollection) {
		$planQuery = $this->getPlanQuery($query);
		
		// TODO: Use the plans DB/API proxy.
		$planRecord = $chargingPlanCollection->query($planQuery)->cursor()->current();
		if(!$planRecord || $planRecord->isEmpty()) {
			// TODO: Report error.
			return null;
		}
		
		return $planRecord;
	}
	
	/**
	 * Get the ref to the monfo plan for the subscriber.
	 * @param type $subscriber
	 * @return type
	 */
	protected function getPlanRefForSubscriber($subscriber) {
		// TODO: This function should be more generic. Or move the implementation into subscriber.
		// Get the ref to the subscriber's plan.
		$planName = $subscriber->{'plan'};
		$plansCollection = Billrun_Factory::db()->plansCollection();
		
		// TODO: Is this right here to use the now time or should i use the times from the charging plan?
		$nowTime = new MongoDate();
		$plansQuery = array("name" => $planName,
							"to"   => array('$gt', $nowTime),
							"from" => array('$lt', $nowTime));
		$planRecord = $plansCollection->query($plansQuery)->cursor()->current();
		
		return $planRecord->createRef($plansCollection);
	}
	
	/**
	 * Update the balances.
	 * @param type $query - Query to find row to update.
	 * @param type $recordToSet - Values to update.
	 * @param type $subscriberId - Id for the subscriber to update.
	 */
	public abstract function update($query, $recordToSet, $subscriberId);
	
	/**
	 * Get billrun subscriber instance.
	 * @param type $subscriberId If of the subscriber to load.
	 * @param type $dateRecord Array that has to and from fields for the query.
	 */
	protected function getSubscriber($subscriberId, $dateRecord) {
		// Get subscriber query.
		$subscriberQuery = $this->getSubscriberQuery($subscriberId, $dateRecord);
		
		// Get the subscriber.
		return Billrun_Factory::subscriber()->load($subscriberQuery);
	}
	
	/**
	 * Get a subscriber query to get the subscriber.
	 * @param type $subscriberId - The ID of the subscriber.
	 * @param type $planRecord - Record that holds to and from fields.
	 * @return type Query to run.
	 */
	protected function getSubscriberQuery($subscriberId, $planRecord) {
		// Get subscriber query.
		$subscriberQuery = array('sid' => $subscriberId);
		
		// Add time to query.
		$subscriberQuery['from'] = $planRecord['from'];
		$subscriberQuery['to'] = $planRecord['to'];
		
		return $subscriberQuery;
	}
	
	/**
	 * Handle logic around setting the expiration date.
	 * @param type $recordToSet
	 * @param type $dataRecord
	 */
	protected function handleExpirationDate($recordToSet, $dataRecord) {
		if(!$recordToSet['to']) {
			$recordToSet['to'] = $this->getDateFromDataRecord($dataRecord);
		}
	}
	
	/**
	 * Get a mongo date object based on charging plan record.
	 * @param type $chargingPlan
	 * @return \MongoDate
	 */
	protected function getDateFromDataRecord($chargingPlan) {
		$period = $chargingPlan['period'];
		$unit = $period['units'];
		$duration = $period['duration'];
		return new MongoDate(strtotime("+ " . $duration . " " . $unit));
	}
	
	/**
	 * Validate the service provider fields.
	 * @param type $subscriber
	 * @param type $planRecord
	 * @return boolean
	 */
	protected function validateServiceProviders($subscriber, $planRecord) {
		// Get the service provider to check that it fits the subscriber's.
		$subscriberServiceProvider = $subscriber->{'service_provider'};
		
		// Check if mismatching serivce providers.
		if($planRecord['service_provider'] != $subscriberServiceProvider) {
			$planServiceProvider = $planRecord['service_provider'];
			Billrun_Factory::log("Failed updating balance! mismatching service prociders: subscriber: $subscriberServiceProvider plan: $planServiceProvider");
			return false;
		}
		
		return true;
	}
}
