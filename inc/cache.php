<?php

interface CacheEngine {
	public function __construct($db_id = NULL);
	public function get($key);
	public function set($key, $val, $expires = FALSE);
	public function delete($key);
	public function db($id);
	public function flush();
}

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

class Cache {
	private static $provider = NULL;

	public static function provider() {
		if(self::$provider) {
			return self::$provider;
		}

		switch (Vi::$config['cache']['enabled']) {
			case 'redis':		self::$provider = new Cache_Redis(); break;
			case 'memcached':	self::$provider = new Cache_Memcached(); break;
			case 'fs':			self::$provider = new Cache_Fs(); break;
			default:			self::$provider = new Cache_Php(); break;
		}

		return self::$provider;
	}

	public static function get($key) {
		return self::provider()->get($key);
	}

	public static function set($key, $value, $expires = FALSE) {
		self::provider()->set($key, $value, $expires);
	}

	public static function delete($key) {
		self::provider()->delete($key);
	}

	public static function db($id) {
		return self::provider()->db($id);
	}

	public static function flush() {
		self::provider()->flush();
	}
}
