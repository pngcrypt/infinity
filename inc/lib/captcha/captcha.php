<?php

include_once 'inc/lib/captcha/cool-php-captcha-0.3.1/captcha.php';

class chanCaptcha {

	public static function get() {
		$captcha = chanCaptcha::generate_captcha();
		$cookie  = $captcha['cookie'];
		$html    = $captcha['html'];

		$id = self::get_id($_GET);

		setcookie($id, $cookie, time() + Vi::$config['captcha']['expires_in']);
		echo '<a title="' . _('Click to update') . '" href="" id="captcha_img">' . $html . '</a>';
		exit;
	}

	public static function check() {
		if (!isset($_POST['captcha_text'])) {
			return false;
		}

		$id = self::get_id($_POST);

		if (!isset($_COOKIE[$id])) {
			return false;
		}

		$result = self::store_get($_COOKIE[$id]);

		if ($result && $result == strtolower($_POST['captcha_text'])) {
			setcookie($id, NULL);
			return true;
		}

		return false;
	}

	public static function get_id($method) {
		$id = 'captcha';

		if (isset($method['board']) && !empty($method['board'])) {
			$id .= '_' . strval($method['board']);
		}

		if (isset($method['thread'])) {
			$id .= '_' . intval($method['thread']);
		}

		return preg_replace('/\W/', '', $id); // remove any "non-word" character
	}

	public static function generate_captcha() {
		$text = chanCaptcha::rand_string(Vi::$config['captcha']['length'], Vi::$config['captcha']['extra']);

		$captcha         = new SimpleCaptcha();
		$captcha->width  = Vi::$config['captcha']['width'];
		$captcha->height = Vi::$config['captcha']['height'];

		$cookie = chanCaptcha::rand_string(20, "abcdefghijklmnopqrstuvwxyz");

		ob_start();
		$captcha->CreateImage($text);
		$image = ob_get_contents();
		ob_end_clean();
		$html = '<image src="data:image/png;base64,' . base64_encode($image) . '">';

		self::store_set($cookie, $text);

		return array("cookie" => $cookie, "html" => $html, "raw_image" => $image);
	}

	public static function rand_string($length, $charset) {
		$ret = "";
		while ($length--) {
			$ret .= mb_substr($charset, mt_rand(0, mb_strlen($charset, 'utf-8') - 1), 1, 'utf-8');
		}
		return $ret;
	}

	private static function store_get($cookie) {
		if(Vi::$config['cache']['enabled'] && Vi::$config['cache']['enabled'] == 'redis') {
			return Cache::db('captchas')->get('captchas:' . $cookie);
		}

		$query = prepare("SELECT * FROM `captchas` WHERE `cookie` = ? AND `extra` = ?");
		$query->execute([$cookie, Vi::$config['captcha']['extra']]);
		$result = $query->fetchAll();

		if(count($result)) {
			$query = prepare("DELETE FROM `captchas` WHERE `cookie` = ? AND `extra` = ?");
			$query->execute([$cookie, Vi::$config['captcha']['extra']]);
			prepare("DELETE FROM `captchas` WHERE `created_at` < ?")->execute([time() - Vi::$config['captcha']['expires_in']]);
			return $result[0]['text'];
		}

		return false;
	}

	private static function store_set($cookie, $value) {
		if(Vi::$config['cache']['enabled'] && Vi::$config['cache']['enabled'] == 'redis') {
			return Cache::db('captchas')->set('captchas:' . $cookie, $value, Vi::$config['captcha']['expires_in']);
		}

		$query = prepare("INSERT INTO `captchas` (`cookie`, `extra`, `text`, `created_at`) VALUES (?, ?, ?, ?)");
		$query->execute([$cookie, Vi::$config['captcha']['extra'], strtolower($value), time()]);

		return false;
	}
}
