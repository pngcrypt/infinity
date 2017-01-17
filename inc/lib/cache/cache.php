<?php

defined('TINYBOARD') or exit;

interface CacheEngine {
	public function __construct($db_id = NULL);
	public function get($key);
	public function set($key, $val, $expires = FALSE);
	public function delete($key);
	public function db($id);
	public function flush();
}

class Cache {
	private static $provider = NULL;

	public static function provider() {
		if(self::$provider) {
			return self::$provider;
		}

		switch (Vi::$config['cache']['enabled']) {
			case 'apcu':		self::$provider = 'Apcu'; break;
			case 'redis':		self::$provider = 'Redis'; break;
			case 'memcached':	self::$provider = 'Memcached'; break;
			case 'fs':			self::$provider = 'Fs'; break;
			case 'php':			self::$provider = 'Php'; break;
			default:			self::$provider = 'Blackhole'; break;
		}

		include_once 'inc/lib/cache/' . strtolower(self::$provider) . '.class.php';

		$class_name = 'Cache_' . self::$provider;
		self::$provider = new $class_name();

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
