<?php

defined('TINYBOARD') or exit;

class Cache_Redis implements CacheEngine {
	private $instances = [];
	private $redis = NULL;

	public function __construct($db_id = 'default') {
		if(!isset(Vi::$config['cache']['redis']['databases'][$db_id])) {
			die($db_id . ' not exists in config');
		}

		$this->redis = new Redis();
		$this->redis->pconnect(Vi::$config['cache']['redis']['address'], Vi::$config['cache']['redis']['port'], Vi::$config['cache']['redis']['timeout'], 'redis_' . $db_id) or die('cache connect failure');
		
		if (Vi::$config['cache']['redis']['password']) {
			$this->redis->auth(Vi::$config['cache']['redis']['password']);
		}
		
		$this->redis->select(Vi::$config['cache']['redis']['databases'][$db_id]) or die('cache select failure');
	}

	public function get($key) {
		return json_decode($this->redis->get($key), TRUE);
	}

	public function set($key, $value, $expires = FALSE) {
		$this->redis->setex($key, $expires ?: Vi::$config['cache']['timeout'], json_encode($value));
	}

	public function delete($key) {
		$this->redis->delete($key);
	}

	public function db($id) {
		if(!isset($this->instances[$id])) {
			$this->instances[$id] = new Cache_Redis($id);
		}

		return $this->instances[$id];
	}

	public function flush() {
		$this->redis->flushDB();
	}
}
