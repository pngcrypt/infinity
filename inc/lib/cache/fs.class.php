<?php

defined('TINYBOARD') or exit;

class Cache_Fs implements CacheEngine {
	private $cache = [];
	private $instances = [];
	private $_prefix = 'default:';
	private $_path = 'tmp/cache/';

	public function __construct($db_id = NULL) {
		if($db_id) {
			$this->_prefix = $db_id . ':';
		}
	}

	public function get($key) {
		$key = $this->_path . $this->escape($this->_prefix . $key);
		$data = FALSE;
		if (file_exists($key)) {
			$data = file_get_contents($key);
			$data = json_decode($data, TRUE);
		}

		return $data;
	}

	public function set($key, $value, $expires = FALSE) {
		file_put_contents($this->_path . $this->escape($this->_prefix . $key), json_encode($value));
	}

	public function delete($key) {
		@unlink($this->_path . $this->escape($this->_prefix . $key));
	}

	public function db($id) {
		if(!isset($this->instances[$id])) {
			$this->instances[$id] = new Cache_Fs($id);
		}

		return $this->instances[$id];
	}

	private function escape($key) {
		return trim(str_replace(['/', "\0"], ['::', NULL], $key));
	}

	public function flush() {
		$files = glob($this->_path . $this->_prefix . '*');
		array_map('unlink', $files);
	}
}
