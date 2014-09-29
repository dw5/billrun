<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Lines model class to pull data from database for lines collection
 *
 * @package  Models
 * @subpackage Lines
 * @since    0.5
 */
class QueueModel extends TableModel {

	/**
	 *
	 * @var boolean show garbage lines
	 */
	protected $garbage = false;
	protected $lines_coll = null;

	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->queue;
		parent::__construct($params);
		$this->search_key = "stamp";
		$this->lines_coll = Billrun_Factory::db()->queueCollection();
	}

	public function getTableColumns() {
		$columns = array(
			'type' => 'Type',
			'aid' => 'Account',
			'sid' => 'Subscriber',
			'calc_name' => 'Next calculator',
			'calc_time' => 'Last calculation time',
			'urt' => 'Time',
		);
		if (!empty($this->extra_columns)) {
			$extra_columns = array_intersect_key($this->getExtraColumns(), array_fill_keys($this->extra_columns, ""));
			$columns = array_merge($columns, $extra_columns);
		}
		return $columns;
	}

	public function getFilterFields() {

		$filter_fields = array(
			'older_then' => array(
				'key' => 'older_then',
				'db_key' => 'urt',
				'input_type' => 'date',
				'comparison' => '$lte',
				'display' => 'Older then',
				'default' => (new Zend_Date(strtotime("+1 day"), null, new Zend_Locale('he_IL')))->toString('YYYY-MM-dd HH:mm:ss'),
			),
			'next_calculator' => array(
				'key' => 'next_calculator',
				'db_key' => 'calc_name',
				'input_type' => 'multiselect',
				'comparison' => '$in',
				'display' => 'Next Calculator',
				'values' => Billrun_Factory::config()->getConfigValue('queue.calculators'),
				'default' => array(),
			),
		);
		foreach ($filter_fields['next_calculator']['values'] as $key => $val) {
			$filter_fields['next_calculator']['values'][$val] = $val;
			unset($filter_fields['next_calculator']['values'][$key]);
		}

		return array_merge($filter_fields, parent::getFilterFields());
	}

	public function getFilterFieldsOrder() {
		$filter_field_order = array(
			0 => array(
				'older_then' => array(
					'width' => 2,
				),
			),
			1 => array(
				'next_calculator' => array(
					'width' => 2,
				),
			),
		);
		return $filter_field_order;
	}

	public function getSortFields() {
		return array(
			'urt' => 'Time',
			'type' => 'Type',
			'calc_name' => 'next calculator',
		);
	}

}
