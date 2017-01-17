<?php

defined('TINYBOARD') or exit;

class Cache_Blackhole implements CacheEngine {
	public function __construct($db_id = NULL) {}
	public function get($key) {return ;}
	public function set($key, $val, $expires = FALSE) {}
	public function delete($key) {}
	public function db($id) {return $this;}
	public function flush() {}
}
