<?php

defined('TINYBOARD') or exit;

class Cache_Apcu implements CacheEngine {
	private $instances = [];
	private $memcached = NULL;
	private $_prefix = 'default:';

	public function __construct($db_id = 'default') {
		if($db_id) {
			$this->_prefix = $db_id . ':';
		}
	}

	public function get($key) {
		return apcu_fetch($this->_prefix . $key);
	}

	public function set($key, $value, $expires = FALSE) {
		apcu_store($this->_prefix . $key, $value, $expires ?: Vi::$config['cache']['timeout']);
	}

	public function delete($key) {
		apcu_delete($this->_prefix . $key);
	}

	public function db($id) {
		if(!isset($this->instances[$id])) {
			$this->instances[$id] = new Cache_Apcu($id);
		}

		return $this->instances[$id];
	}

	public function flush() {
		$toDelete = new APCUIterator('/^' . preg_quote($this->_prefix) .'/');
		apcu_delete($toDelete);
	}
}
