<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Validate conditions on given entitiy
 */
trait Billrun_Traits_ConditionsCheck {

	/**
	 * get matching entities for all categories by the specific conditions of every category
	 * 
	 * @param array $entity
	 * @param array $conditions
	 * @param array $params
	 * @param string $logic ($and/$or)
	 * @return boolean
	 */
	public function isConditionsMeet($entity, $conditions = [], $params = [], $logic = '$and') {
		if (empty($conditions)) {
			return $this->getNoConditionsResult($entity, $params);
		}

		$query = [
			$logic => [],
		];

		foreach ($conditions as $condition) {
			$cond = $this->getConditionQuery($entity, $condition, $params);
			if (!is_null($cond)) {
				$query[$logic][] = $cond;
			}
		}

		return $this->isConditionMeet($entity, $query, $params);
	}

	/**
	 * build condition query that will be later used by isConditionMeet
	 * 
	 * @param array $entity
	 * @param array $condition
	 * @param array $params
	 * @return array
	 */
	protected function getConditionQuery($entity, $condition, $params = []) {
		$fieldName = $this->getFieldName($condition, $entity, $params = []);
		$operator = $this->getOperator($condition, $entity, $params = []);
		$value = $this->getValueToCompare($condition, $entity, $params = []);

		return $this->getQuery($fieldName, $operator, $value, $entity, $params);
	}

	/**
	 * get condition's field name
	 * 
	 * @param array $condition
	 * @param array $entity
	 * @param array $params
	 * @return string
	 */
	protected function getFieldName($condition, $entity = [], $params = []) {
		return Billrun_Util::getIn($condition, 'field', '');
	}

	/**
	 * get condition's operator
	 * 
	 * @param array $condition
	 * @param array $entity
	 * @param array $params
	 * @return string
	 */
	protected function getOperator($condition, $entity = [], $params = []) {
		$op = Billrun_Util::getIn($condition, 'op', '');
		return (strpos($op, '$') !== 0 ? '$' : '') . $op;
	}

	/**
	 * get condition's value to compare
	 * 
	 * @param array $condition
	 * @param array $entity
	 * @param array $params
	 * @return string
	 */
	protected function getValueToCompare($condition, $entity = [], $params = []) {
		return Billrun_Util::getIn($condition, 'value', '');
	}


	/**
	 * build query to be used in ArrayQuery
	 * 
	 * @param string $fieldName
	 * @param string $operator
	 * @param string $value
	 * @param array $entity
	 * @param array $params
	 * @return array if operator is valid, false otherwise
	 */
	protected function getQuery($fieldName, $operator, $value, $entity = [], $params = []) {
		switch ($operator) {
			case '$gt':
			case '$gte':
			case '$lt':
			case '$lte':
			case '$eq':
			case '$ne':
			case '$in':
			case '$nin':
			case '$not':
			case '$exists':
			case '$regex':
				return [
					$fieldName => [
						$operator => $value,
					],
				];
			case '$is':
				return $this->getIsOperatorQuery($fieldName, $operator, $value, $entity, $params);
		}
		
		return false;
	}
	
	/**
	 * build query for special "$is" operator
	 * 
	 * @param string $fieldName
	 * @param string $operator
	 * @param string  $value
	 * @param array $entity
	 * @param array $params
	 * @return array
	 */
	protected function getIsOperatorQuery($fieldName, $operator, $value, $entity = [], $params = []) {
		if ($value == 'isActive') {
			return [
				$fieldName => [
					'__callback' => [
						'callback' => [
							$this,
							'inRange'
						],
						'arguments' => [
							$entity['from']->sec,
							$entity['to']->sec,
						],
					],
				],
			];
		}
		
		return [
			$fieldName => [
				'$exists' => true,
				'$eq' => $value,
			],
		];
	}
	
	/**
	 * assistance function that will be used by ArrayQuery to check if value/s are in date time range
	 * 
	 * @param array $range - date
	 * @param array $values
	 * @return boolean
	 */
	public function inRange($range, $values) {
		if (!is_array($range) || empty($values)) {
			return false;
		}
		
		if (!is_array($values)) {
			$values = [$values];
		}
		
		foreach ($range as $interval) {
			foreach ($values as $value) {
				if (!($interval['from'] <= $value && $interval['to'] >= $value)) {
					return false;
				}
				
				return true;
			}
		}
		
		return false;
	}

	/**
	 * 
	 * 
	 * @param array $entity
	 * @param array $query
	 * @param array $params
	 * @return boolean
	 */
	public function isConditionMeet($entity, $query, $params = []) {
		return Billrun_Utils_Arrayquery_Query::exists($entity, $query);
	}

	/**
	 * get the result in case of no conditions
	 * 
	 * @param array $entity
	 * @param array $params
	 * @return boolean
	 */
	protected function getNoConditionsResult($entity, $params = []) {
		return true;
	}

}
