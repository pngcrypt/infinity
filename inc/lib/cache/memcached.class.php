<?php

defined('TINYBOARD') or exit;

class Cache_Memcached implements CacheEngine {
	private $instances = [];
	private $memcached = NULL;
	private $_prefix = 'default:';

	public function __construct($db_id = 'default') {
		if($db_id) {
			$this->_prefix = $db_id . ':';
		}

		$this->memcached = new Memcached();
		$this->memcached->addServers(Vi::$config['cache']['memcached']);
	}

	public function get($key) {
		return $this->memcached->get($this->_prefix . $key);
	}

	public function set($key, $value, $expires = FALSE) {
		$this->memcached->set($this->_prefix . $key, $value, $expires ?: Vi::$config['cache']['timeout']);
	}

	public function delete($key) {
		$this->memcached->delete($this->_prefix . $key);
	}

	public function db($id) {
		if(!isset($this->instances[$id])) {
			$this->instances[$id] = new Cache_Memcached($id);
		}

		return $this->instances[$id];
	}

	public function flush() {
		$this->memcached->flush();
	}
}
