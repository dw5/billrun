<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing config class
 *
 * @package  Config
 * @since    0.5
 */
class Billrun_Config {

	/**
	 * the config instance (for singleton)
	 * 
	 * @var Billrun_Config 
	 */
	protected static $instance = null;

	/**
	 * the config container
	 * 
	 * @var Yaf_Config
	 */
	protected $config;
	
	/**
	 * the name of the tenant (or null if not running with tenant)
	 * 
	 * @var string
	 */
	protected $tenant = null;
	
	/**
	 * path for tenants config file
	 * 
	 * @var type 
	 */
	protected static $multitenantDir = null;
	
	/**
	 * save all available values for environment while running in production
	 * 
	 * @var array
	 */
	protected $productionValues = array('prod', 'product', 'production');

	/**
	 * constructor of the class
	 * protected for converting this class to singleton design pattern
	 */
	protected function __construct($config) {
		$this->config = $config;
		$configInclude = $config['configuration']['include'];
		if (!empty($configInclude) && $configInclude->valid()) {
			foreach ($config->toArray()['configuration']['include'] as $filePath) {
				$this->addConfig($filePath);
			}
		}
		if (!isset($config['disableHostConfigLoad']) && file_exists($env_conf = APPLICATION_PATH . '/conf/' . Billrun_Util::getHostName() . '.ini')) {
			$this->addConfig($env_conf);
		}
		
		if (defined('APPLICATION_TENANT')) { // specific defined tenant
			$this->tenant = APPLICATION_TENANT;
			$this->loadTenantConfig();
		} else if (defined('APPLICATION_MULTITENANT') && php_sapi_name() != "cli") { // running from web and with multitenant
			$this->initTenant();
			$this->loadTenantConfig();
		} else {
			$this->tenant = $this->getEnv();
		}
	}

	public function addConfig($path) {
		if (file_exists($path)) {
			$addedConf = new Yaf_Config_Ini($path);
			$this->config = new Yaf_Config_Simple($this->mergeConfigs($this->config->toArray(), $addedConf->toArray()));
		} else {
			error_log("Configuration File {$path} doesn't exists or BillRun lack access permissions!!");
		}
	}

	/**
	 * Merge to  configuration into one overiding  the  less important config  with  a newer config
	 * @param type $lessImportentConf the configuration array to merge into and override
	 * @param type $moreImportantConf the  configuration array to merge from.
	 * @return type array containing the  overriden values.
	 */
	protected function mergeConfigs($lessImportentConf, $moreImportantConf) {
		if (!is_array($moreImportantConf)) {
			return $moreImportantConf;
		}

		foreach ($moreImportantConf as $key => $value) {
			if (!isset($moreImportantConf[$key])) {
				continue;
			}

			$lessImportentConf[$key] = isset($lessImportentConf[$key]) ?
				$this->mergeConfigs($lessImportentConf[$key], $moreImportantConf[$key]) :
				$moreImportantConf[$key];
		}

		return $lessImportentConf;
	}

	/**
	 * magic method for backward compatability (Yaf_Config style)
	 * 
	 * @param string $key the key in the config container (Yaf_Config)
	 * 
	 * @return mixed the value in the config
	 */
	public function __get($key) {
		return $this->config->{$key};
	}

	/**
	 * method to get the instance of the class (singleton)
	 * @param type $config
	 * @return Billrun_Config
	 */
	static public function getInstance($config = null) {
		$stamp = Billrun_Util::generateArrayStamp($config);
		if (empty(self::$instance[$stamp])) {
			if (empty($config)) {
				$config = Yaf_Application::app()->getConfig();
			}
			self::$instance[$stamp] = new self($config);
			self::$instance[$stamp]->loadDbConfig();
		}
		return self::$instance[$stamp];
	}
	
	public function getFileTypeSettings($fileType) {
		$fileType = array_filter($this->getConfigValue('file_types'), function($fileSettings) use ($fileType) {
			return $fileSettings['file_type'] === $fileType;
		});
		if ($fileType) {
			$fileType = current($fileType);
		}
		return $fileType;
	}

	public function getFileTypes() {
		return array_map(function($fileSettings) {
			return $fileSettings['file_type'];
		}, $this->getConfigValue('file_types'));
	}

	public function loadDbConfig() {
		try {
			$configColl = Billrun_Factory::db()->configCollection();
			if ($configColl) {
				$dbCursor = $configColl->query()
					->cursor()->setReadPreference('RP_PRIMARY')
					->sort(array('_id' => -1))
					->limit(1)
					->current();
				if ($dbCursor->isEmpty()) {
					return true;
				}
				$dbConfig = $dbCursor->getRawData();
				
				// Set the timezone from the config.
				$this->setTenantTimezone($dbConfig);
				
				unset($dbConfig['_id']);
				$iniConfig = $this->config->toArray();
				$this->config = new Yaf_Config_Simple($this->mergeConfigs($iniConfig, $dbConfig));
			}
		} catch (Exception $e) {
			Billrun_Factory::log('Cannot load database config', Zend_Log::CRIT);
			Billrun_Factory::log($e->getCode() . ": " . $e->getMessage(), Zend_Log::CRIT);
			return false;
		}
	}

	/**
	 * Refresh the values from the config in the DB.
	 */
	public function refresh() {
		$this->setTenantTimezone($this->toArray());
	}
	
	protected function setTenantTimezone($dbConfig) {
		if(!isset($dbConfig['timezone'])){
			return;
		}
		
		// Get the timezone.
		$timezone = $this->getComplexValue($dbConfig['timezone']);
		if(empty($timezone)) {
			return;
		}
		
		// Setting the default timezone.
		$setTimezone = @date_default_timezone_set($timezone);
		Billrun_Factory::log("Timezone to set: " . date_default_timezone_get());
	}
	
	/**
	 * method to get config value
	 * 
	 * @param mixed  $keys array of keys or string divided by period
	 * @param mixed  $defVal the value return if the keys not found in the config
	 * @param string $retType the type of the return value (int, bool, string, float, array, object)
	 *               if null passed the return value type will be declare by the default value type
	 *               this argument is deprecated; the return value type is defined by the default value type
	 * @return mixed the config value
	 * 
	 * @todo add cache for the config get method
	 */
	public function getConfigValue($keys, $defVal = null, $retType = null) {
		$currConf = $this->config;

		if (!is_array($keys)) {
			$path = explode(".", $keys);
		}

		foreach ($path as $key) {
			if (!isset($currConf[$key])) {
				$currConf = $defVal;
				break;
			}
			$currConf = $currConf[$key];
		}

		if ($currConf instanceof Yaf_Config_Ini || $currConf instanceof Yaf_Config_Simple) {
			$currConf = $currConf->toArray();
		}

		if (isset($retType) && $retType) {
			settype($currConf, $retType);
		} else if (strtoupper($type = gettype($defVal)) != 'NULL') {
			settype($currConf, $type);
		}

		// Check if the value is complex.
		if(self::isComplex($currConf)) {
			return self::getComplexValue($currConf);
		}
		
		return $currConf;
	}

	/**
	 * Return a wrapper for input data.
	 * @param mixed $complex - Data to wrap with complex wrapper.
	 * @return \Billrun_DataTypes_Conf_Base
	 */
	public static function getComplexWrapper ($complex) {
		// Get complex wrapper.
		$name = "Billrun_DataTypes_Conf_" . ucfirst(strtolower($complex['t']));
		if(!@class_exists($name)) {
			return null;
		}
		
		return new $name($complex);
	}
	
	/**
	 * Check if complex data set is valid by creating a wrapper and validating.
	 * @param mixed $complex - Complex data
	 * @return boolean - True if valid.
	 */
	public static function isComplexValid($complex) {
		$wrapper = self::getComplexWrapper($complex);
		if(!$wrapper) {
			return false;
		}
		return $wrapper->validate();
	}
	
	public static function getComplexValue($complex) {
		$wrapper = self::getComplexWrapper($complex);
		if(!$wrapper) {
			return null;
		}
		return $wrapper->value();
	}
	
	/**
	 * Check if an object is complex (not primitive or array).
	 * @return true if complex.
	 */
	public static function isComplex($obj) {
		if(is_scalar($obj)) {
			return false;
		}
		
		if(!is_array($obj)) {
			return true;
		}
		
		// TODO: that means that 't' is a sensitive value! If a simple array 
		// will have a 't' field, we will treat it as a complex object.
		return isset($obj['t']);
	}
	
	/**
	 * method to receive the environment the app running
	 * 
	 * @return string the environment (prod, test or dev)
	 */
	public function getEnv() {
		return APPLICATION_ENV;
	}
	
	/**
	 * method to retrieve the tenant name
	 * 
	 * @return string
	 */
	public function getTenant() {
		if (empty($this->tenant)) {
			return $this->getEnv();
		}
		return $this->tenant;
	}

	/**
	 * method to set the tenant support
	 */
	protected function loadTenantConfig() {
		if (isset($this->config['billrun']['multitenant']['basedir'])) {
			$multitenant_basedir = $this->config['billrun']['multitenant']['basedir'] . DIRECTORY_SEPARATOR;
		} else {
			$multitenant_basedir = APPLICATION_PATH . '/conf/tenants/';
		}
		if (file_exists($tenant_conf = $multitenant_basedir . $this->tenant . '.ini')) {
			self::$multitenantDir = $multitenant_basedir;
			$this->addConfig($tenant_conf);
		}
	}
	
	/**
	 * method to initialize tenanat
	 */
	protected function initTenant() {
		if(!isset($_SERVER['HTTP_HOST'])) {
			return die('no tenant declare');
		}

		$server = $_SERVER['HTTP_HOST'];

		$subDomains = explode(".", $server);

		if (!isset($subDomains[0])) {
			return die('no tenant declare');
		}
		$this->tenant = $subDomains[0];
	}
	
	/**
	 * method to check if the environment is set under some specific environment
	 * 
	 * @param string $env the environment to check
	 * 
	 * @return boolean true if the environment is the one that supplied, else false
	 */
	public function checkEnv($env) {
		if (is_array($env) && in_array($this->getEnv(), $env)) {
			return true;
		}
		if ($this->getEnv() === $env) {
			return true;
		}
		return false;
	}

	/**
	 * method to check if the environment is production
	 * 
	 * @return boolean true if it's production, else false
	 */
	public function isProd() {
		if ($this->checkEnv($this->productionValues)) {
			return true;
		}
		if ($this->isCompanyInProd()) {
			return true;
		}
		return false;
	}

	public function toArray() {
		return $this->config->toArray();
	}
	
	protected function isCompanyInProd() {
		return in_array($this->getInstance()->getConfigValue("environment"), $this->productionValues);
	}
	
	public static function getMultitenantConfigPath() {
		return APPLICATION_PATH . '/conf/tenants/';
		return self::$multitenantDir;
	}

}
