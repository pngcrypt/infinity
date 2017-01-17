<?php

defined('TINYBOARD') or exit;

class Cache_Php implements CacheEngine {
	private $cache = [];
	private $instances = [];

	public function __construct($db_id = NULL) {}

	public function get($key) {
		return isset($this->cache[$key]) ? $this->cache[$key] : FALSE;
	}

	public function set($key, $value, $expires = FALSE) {
		$this->cache[$key] = $value;
	}

	public function delete($key) {
		unset($this->cache[$key]);
	}

	public function db($id) {
		if(!isset($this->instances[$id])) {
			$this->instances[$id] = new Cache_Php($id);
		}

		return $this->instances[$id];
	}

	public function flush() {
		$this->cache = [];
	}
}
