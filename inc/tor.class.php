<?php
defined('TINYBOARD') or exit;

class Tor_Session {
	public static $user = [
				'capchas_left'	=> 0,
				'capchas_need'	=> 0,
				'posts_left'	=> 0,
				'posts_max'		=> 0,
				'fails_left'	=> 0,
				'expire'		=> NULL,
				'allow_post'	=> FALSE,
				'cookie_id'		=> NULL,
			];

	public static function check() {
		return $_SERVER['REMOTE_ADDR'] == Vi::$config['tor_serviceip'] ?: FALSE;
	}

	public static function post() {
		if(!self::check() || !self::$user['allow_post']) {
			return ;
		}

		if(!--self::$user['posts_left']) {
			self::storage_reset();
			return ;
		}

		self::storage_save();
	}

	public static function ip() {
		return '!tor!' . self::$user['cookie_id'];
	}

	public static function storage_save() {
		Cache::db('tor')->set('tor_cookies:' . self::$user['cookie_id'], self::$user, Vi::$config['tor']['cookie_time']);
	}

	public static function storage_reset() {
		self::set(TRUE);
	}

	public static function set($new = FALSE) {
		if(!self::check()) {
			return FALSE;
		}

		$cookie = Vi::$config['tor']['cookie_name'];
		$cookie = isset($_COOKIE[$cookie]) ? $_COOKIE[$cookie] : NULL;

		if($new || !$cookie || strlen($cookie) !== 32 || !ctype_xdigit($cookie)) {
			$cookie_id = md5(bin2hex(random_bytes(16).time()));
			
			setcookie(Vi::$config['tor']['cookie_name'], isset(self::$user['cookie_id']) ? self::$user['cookie_id'] : $cookie_id, time() + Vi::$config['tor']['cookie_time']);
			
			self::$user = [
				'capchas_left'	=> Vi::$config['tor']['need_capchas'],
				'capchas_need'	=> Vi::$config['tor']['need_capchas'],
				'posts_left'	=> Vi::$config['tor']['max_posts'],
				'posts_max'		=> Vi::$config['tor']['max_posts'],
				'fails_left'	=> Vi::$config['tor']['max_fails'],
				'expire'		=> time() + Vi::$config['tor']['cookie_time'],
				'allow_post'	=> FALSE,
				'cookie_id'		=> isset(self::$user['cookie_id']) ? self::$user['cookie_id'] : $cookie_id,
			];

			self::storage_save();
		}
	}

	public static function init() {
		if(!self::check()) {
			return FALSE;
		}

		$cookie = Vi::$config['tor']['cookie_name'];
		$cookie = isset($_COOKIE[$cookie]) ? $_COOKIE[$cookie] : NULL;

		if(!$cookie || strlen($cookie) !== 32 || !ctype_xdigit($cookie)) {
			return ;
		}

		$data = Cache::db('tor')->get('tor_cookies:' . $cookie);
		if($data) {
			self::$user = $data;
		}
	}
}
