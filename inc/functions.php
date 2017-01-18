<?php

/*
 *  Copyright (c) 2010-2014 Tinyboard Development Group
 */

/*if(!defined('LC_MESSAGES'))
define('LC_MESSAGES', 5);
 */
if (realpath($_SERVER['SCRIPT_FILENAME']) == str_replace('\\', '/', __FILE__)) {
	// You cannot request this file directly.
	exit;
}

define('TINYBOARD', null);

class Vi {
	public static $config         = false;
	public static $config_board   = false;
	public static $board          = false;
	public static $mod            = false;
	public static $events         = false;
	public static $pdo            = false;
	public static $build_pages    = false;
	public static $debug          = false;
	public static $default_locale = false;
	public static $current_locale = false;
	public static $markup_urls    = [];
	public static $__ip           = false;
	public static $twig           = false;
}

Vi::$debug['start'] = microtime(true);
Vi::$default_locale = 'en_US';
Vi::$current_locale = Vi::$default_locale;

require_once 'inc/display.php';
require_once 'inc/template.php';
require_once 'inc/database.php';
require_once 'inc/events.php';
require_once 'inc/api.php';
require_once 'inc/bans.php';
require_once 'inc/mod/auth.php';
require_once 'inc/lock.php';
require_once 'inc/queue.php';

require_once 'inc/lib/parsedown/Parsedown.php'; // todo: option for parsedown instead of Tinyboard/STI markup
require_once 'inc/lib/captcha/captcha.php';
require_once 'inc/lib/cache/cache.php';
require_once 'inc/tor.class.php';

register_shutdown_function('fatal_error_handler');
mb_internal_encoding('UTF-8');
loadConfig();

function init_locale($locale, $error = 'error') {
	if (!$locale) {
		$locale = Vi::$default_locale;
	}

	if (strripos($locale, '.') === FALSE) {
		$locale .= '.UTF-8';
	}

	putenv("LANGUAGE={$locale}"); // шindoшs
	if (defined('LC_MESSAGES')) {
		setlocale(LC_MESSAGES, $locale);
		setlocale(LC_TIME, $locale);
	}

	bindtextdomain('tinyboard', './inc/locale');
	bind_textdomain_codeset('tinyboard', 'UTF-8');
	textdomain('tinyboard');
}

function loadConfig() {
	$error = function_exists('error') ? 'error' : 'basic_error_function_because_the_other_isnt_loaded_yet';

	$boardsuffix = isset(Vi::$board['uri']) ? Vi::$board['uri'] : '';

	// set locale
	Vi::$default_locale = Vi::$current_locale = BoardLocale(''); // default site locale
	if (isset(Vi::$board['uri'])) {
		Vi::$current_locale = BoardLocale(Vi::$board['uri']);
	}
	// board locale
	init_locale(Vi::$current_locale);

	if (!isset($_SERVER['REMOTE_ADDR'])) {
		$_SERVER['REMOTE_ADDR'] = '0.0.0.0';
	}

	if (file_exists('tmp/cache/cache_config.php')) {
		require_once 'tmp/cache/cache_config.php';
	}

	if (isset(Vi::$config['cache_config']) && Vi::$config['cache_config'] && Vi::$config = Cache::get('config_' . $boardsuffix)) {
		Vi::$events = Cache::get('events_' . $boardsuffix);

		define_groups();

		if (file_exists('inc/instance-functions.php')) {
			require_once 'inc/instance-functions.php';
		}
	} else {
		Vi::$config = array();

		// We will indent that later.
		reset_events();

		$arrays = array(
			'db',
			'api',
			'cache',
			'lock',
			'queue',
			'cookies',
			'error',
			'dir',
			'mod',
			'spam',
			'filters',
			'wordfilters',
			'custom_capcode',
			'custom_tripcode',
			'dnsbl',
			'dnsbl_exceptions',
			'remote',
			'allowed_ext',
			'allowed_ext_files',
			'file_icons',
			'footer',
			'stylesheets',
			'additional_javascript',
			'markup',
			'custom_pages',
			'dashboard_links',
		);

		foreach ($arrays as $key) {
			Vi::$config[$key] = array();
		}

		if (!file_exists('inc/instance-config.php')) {
			$error('Posting is down momentarily. Please try again later.');
		}

		require 'inc/config.php';
		require 'inc/instance-config.php';

		if (isset(Vi::$board['dir']) && file_exists(Vi::$board['dir'] . '/config.php')) {
			require Vi::$board['dir'] . '/config.php';
		}

		if (!isset(Vi::$config['global_message'])) {
			Vi::$config['global_message'] = false;
		}

		if (!isset(Vi::$config['post_url'])) {
			Vi::$config['post_url'] = Vi::$config['root'] . Vi::$config['file_post'];
		}

		if (!isset(Vi::$config['referer_match'])) {
			if (isset($_SERVER['HTTP_HOST'])) {
				Vi::$config['referer_match'] = '/^' .
				(preg_match('@^https?://@', Vi::$config['root']) ? '' :
					'https?:\/\/' . $_SERVER['HTTP_HOST']) .
				preg_quote(Vi::$config['root'], '/') .
				'(' .
				str_replace('%s', Vi::$config['board_regex'], preg_quote(Vi::$config['board_path'], '/')) .
				'(' .
				preg_quote(Vi::$config['file_index'], '/') . '|' .
				str_replace('%d', '\d+', preg_quote(Vi::$config['file_page'])) .
				')?' .
				'|' .
				str_replace('%s', Vi::$config['board_regex'], preg_quote(Vi::$config['board_path'], '/')) .
				preg_quote(Vi::$config['dir']['res'], '/') .
				'(' .
				str_replace('%d', '\d+', preg_quote(Vi::$config['file_page'], '/')) . '|' .
				str_replace('%d', '\d+', preg_quote(Vi::$config['file_page50'], '/')) .
				')' .
				'|' .
				preg_quote(Vi::$config['file_mod'], '/') . '\?\/.+' .
					')([#?](.+)?)?$/ui';
			} else {
				Vi::$config['referer_match'] = '//'; // CLI mode
			}
		}

		if (!isset(Vi::$config['cookies']['path'])) {
			Vi::$config['cookies']['path'] = &Vi::$config['root'];
		}

		if (!isset(Vi::$config['dir']['static'])) {
			Vi::$config['dir']['static'] = Vi::$config['root'] . 'static/';
		}

		if (!isset(Vi::$config['image_blank'])) {
			Vi::$config['image_blank'] = Vi::$config['dir']['static'] . 'blank.gif';
		}

		if (!isset(Vi::$config['image_sticky'])) {
			Vi::$config['image_sticky'] = Vi::$config['dir']['static'] . 'sticky.gif';
		}

		if (!isset(Vi::$config['image_locked'])) {
			Vi::$config['image_locked'] = Vi::$config['dir']['static'] . 'locked.gif';
		}

		if (!isset(Vi::$config['image_bumplocked'])) {
			Vi::$config['image_bumplocked'] = Vi::$config['dir']['static'] . 'sage.gif';
		}

		if (!isset(Vi::$config['image_deleted'])) {
			Vi::$config['image_deleted'] = Vi::$config['dir']['static'] . 'deleted.png';
		}

		if (!isset(Vi::$config['uri_thumb'])) {
			Vi::$config['uri_thumb'] = Vi::$config['root'] . Vi::$board['dir'] . Vi::$config['dir']['thumb'];
		} elseif (isset(Vi::$board['dir'])) {
			Vi::$config['uri_thumb'] = sprintf(Vi::$config['uri_thumb'], Vi::$board['dir']);
		}

		if (!isset(Vi::$config['uri_img'])) {
			Vi::$config['uri_img'] = Vi::$config['root'] . Vi::$board['dir'] . Vi::$config['dir']['img'];
		} elseif (isset(Vi::$board['dir'])) {
			Vi::$config['uri_img'] = sprintf(Vi::$config['uri_img'], Vi::$board['dir']);
		}

		if (!isset(Vi::$config['uri_stylesheets'])) {
			Vi::$config['uri_stylesheets'] = Vi::$config['root'] . 'stylesheets/';
		}

		if (!isset(Vi::$config['url_stylesheet'])) {
			Vi::$config['url_stylesheet'] = Vi::$config['uri_stylesheets'] . 'style.css';
		}

		if (!isset(Vi::$config['url_javascript'])) {
			Vi::$config['url_javascript'] = Vi::$config['root'] . Vi::$config['file_script'];
		}

		if (!isset(Vi::$config['additional_javascript_url'])) {
			Vi::$config['additional_javascript_url'] = Vi::$config['root'];
		}

		if (!isset(Vi::$config['uri_flags'])) {
			Vi::$config['uri_flags'] = Vi::$config['root'] . 'static/flags/%s.png';
		}

		if (!isset(Vi::$config['user_flag'])) {
			Vi::$config['user_flag'] = false;
		}

		if (!isset(Vi::$config['user_flags'])) {
			Vi::$config['user_flags'] = array();
		}

		Vi::$config['version'] = file_exists('.installed') ? trim(file_get_contents('.installed')) : false;

		if (Vi::$config['allow_roll']) {
			event_handler('post', 'diceRoller');
		}

		if (is_array(Vi::$config['anonymous'])) {
			Vi::$config['anonymous'] = Vi::$config['anonymous'][array_rand(Vi::$config['anonymous'])];
		}
	}

	// Effectful config processing below:

	date_default_timezone_set(Vi::$config['timezone']);

	if (Vi::$config['root_file']) {
		chdir(Vi::$config['root_file']);
	}

	// Keep the original address to properly comply with other board configurations
	if (!Vi::$__ip) {
		Vi::$__ip = $_SERVER['REMOTE_ADDR'];
	}

	// ::ffff:0.0.0.0
	if (preg_match('/^\:\:(ffff\:)?(\d+\.\d+\.\d+\.\d+)$/', Vi::$__ip, $m)) {
		$_SERVER['REMOTE_ADDR'] = $m[2];
	}

	if (Vi::$config['verbose_errors']) {
		set_error_handler('verbose_error_handler');
		error_reporting(E_ALL);
		ini_set('display_errors', true);
		ini_set('html_errors', false);
	} else {
		ini_set('display_errors', false);
	}

	if (Vi::$config['syslog']) {
		openlog('tinyboard', LOG_ODELAY, LOG_SYSLOG);
	}
	// open a connection to sysem logger

	if (Vi::$config['recaptcha']) {
		require_once 'inc/lib/recaptcha/recaptchalib.php';
	}
	if (Vi::$config['tor']['allow_posting']) {
		Tor_Session::init();
		event_handler('post', function($post){Tor_Session::post();});
	}

	if (in_array('webm', Vi::$config['allowed_ext_files'])) {
		require_once 'inc/lib/webm/posthandler.php';
		event_handler('post', 'postHandler');
	}

	event('load-config');

	if (Vi::$config['cache_config'] && !isset(Vi::$config['cache_config_loaded'])) {
		file_put_contents('tmp/cache/cache_config.php', '<?php ' .
			'Vi::$config = array();' .
			'Vi::$config[\'cache\'] = ' . var_export(Vi::$config['cache'], true) . ';' .
			'Vi::$config[\'cache_config\'] = true;' .
			'Vi::$config[\'debug\'] = ' . var_export(Vi::$config['debug'], true) . ';' .
			'require_once(\'inc/cache.php\');'
		);

		Vi::$config['cache_config_loaded'] = true;

		Cache::set('config_' . $boardsuffix, Vi::$config);
		Cache::set('events_' . $boardsuffix, Vi::$events);
	}

	if (Vi::$config['debug']) {
		if (!isset(Vi::$debug['start_debug'])) {
			Vi::$debug = array(
				'sql'         => array(),
				'exec'        => array(),
				'purge'       => array(),
				'cached'      => array(),
				'write'       => array(),
				'time'        => array(
					'db_queries' => 0,
					'exec'       => 0,
				),
				'start_debug' => microtime(true),
			);
		}
	}
}

function basic_error_function_because_the_other_isnt_loaded_yet($message, $priority = true) {
	if (Vi::$config['syslog'] && $priority !== false) {
		// Use LOG_NOTICE instead of LOG_ERR or LOG_WARNING because most error message are not significant.
		_syslog($priority !== true ? $priority : LOG_NOTICE, $message);
	}

	// Yes, this is horrible.
	die('<!DOCTYPE html><html><head><title>Error</title>' .
		'<style type="text/css">' .
		'body{text-align:center;font-family:arial, helvetica, sans-serif;font-size:10pt;}' .
		'p{padding:0;margin:20px 0;}' .
		'p.c{font-size:11px;}' .
		'</style></head>' .
		'<body><h2>Error</h2>' . $message . '<hr/>' .
		'<p class="c">This alternative error page is being displayed because the other couldn\'t be found or hasn\'t loaded yet.</p></body></html>');
}

function fatal_error_handler() {
	if ($error = error_get_last()) {
		if ($error['type'] == E_ERROR) {
			if (function_exists('error')) {
				error('Caught fatal error: ' . $error['message'] . ' in <strong>' . $error['file'] . '</strong> on line ' . $error['line'], LOG_ERR);
			} else {
				basic_error_function_because_the_other_isnt_loaded_yet('Caught fatal error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'], LOG_ERR);
			}
		}
	}
}

function _syslog($priority, $message) {
	if (isset($_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'])) {
		// CGI
		syslog($priority, $message . ' - client: ' . $_SERVER['REMOTE_ADDR'] . ', request: "' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . '"');
	} else {
		syslog($priority, $message);
	}
}

function verbose_error_handler($errno, $errstr, $errfile, $errline) {
	if (error_reporting() == 0) {
		return false;
	}
	// Looks like this warning was suppressed by the @ operator.

	error(utf8tohtml($errstr), true, array(
		'file'      => $errfile . ':' . $errline,
		'errno'     => $errno,
		'error'     => $errstr,
		'backtrace' => array_slice(debug_backtrace(), 1),
	));
}

function define_groups() {
	foreach (Vi::$config['mod']['groups'] as $group_value => $group_name) {
		$group_name = strtoupper($group_name);
		if (!defined($group_name)) {
			define($group_name, $group_value, true);
		}
	}

	ksort(Vi::$config['mod']['groups']);
}

function create_antibot($board, $thread = null) {
	require_once dirname(__FILE__) . '/anti-bot.php';

	return _create_antibot($board, $thread);
}

function rebuildThemes($action, $boardname = false) {
	// Save the global variables
	$_config = Vi::$config;
	$_board  = Vi::$board;

	// List themes
	if (Vi::$config['cache']['enabled'] !== false && $themes = Cache::get("themes")) {
		// OK, we already have themes loaded
	} else {
		$query = query("SELECT `theme` FROM ``theme_settings`` WHERE `name` IS NULL AND `value` IS NULL") or error(db_error());

		$themes = array();

		while ($theme = $query->fetch(PDO::FETCH_ASSOC)) {
			$themes[] = $theme;
		}

		Vi::$config['cache']['enabled'] !== false && Cache::set("themes", $themes);
	}

	foreach ($themes as $theme) {
		// Restore them
		Vi::$config = $_config;
		Vi::$board  = $_board;
		rebuildTheme($theme['theme'], $action, $boardname);
	}

	// Restore them again
	Vi::$config = $_config;
	Vi::$board  = $_board;
	// init_locale(Vi::$config['locale']);
}

function loadThemeConfig($theme_name) {
	if (!file_exists(Vi::$config['dir']['themes'] . '/' . $theme_name . '/info.php')) {
		return false;
	}

	// Load theme information into $theme
	include Vi::$config['dir']['themes'] . '/' . $theme_name . '/info.php';

	return $theme;
}

function rebuildTheme($theme_name, $action, $board = false) {
	$_locale = Vi::$current_locale;

	if ($board) {
		init_locale(BoardLocale($board));
	}
	$theme = loadThemeConfig($theme_name);

	if (file_exists(Vi::$config['dir']['themes'] . '/' . $theme_name . '/theme.php')) {
		require_once Vi::$config['dir']['themes'] . '/' . $theme_name . '/theme.php';

		$theme['build_function']($action, themeSettings($theme_name), $board);
	}
	if ($board) {
		init_locale($_locale);
	}
}

function themeSettings($theme_name) {
	if ($settings = Cache::get("theme_settings_" . $theme_name)) {
		return $settings;
	}

	$query = prepare("SELECT `name`, `value` FROM ``theme_settings`` WHERE `theme` = :theme AND `name` IS NOT NULL");
	$query->bindValue(':theme', $theme_name);
	$query->execute() or error(db_error($query));

	$settings = array();
	while ($s = $query->fetch(PDO::FETCH_ASSOC)) {
		$settings[$s['name']] = $s['value'];
	}

	Cache::set("theme_settings_" . $theme_name, $settings);

	return $settings;
}

function sprintf3($str, $vars, $delim = '%') {
	$replaces = array();
	foreach ($vars as $k => $v) {
		$replaces[$delim . $k . $delim] = $v;
	}
	return str_replace(array_keys($replaces),
		array_values($replaces), $str);
}

function mb_substr_replace($string, $replacement, $start, $length) {
	return mb_substr($string, 0, $start) . $replacement . mb_substr($string, $start + $length);
}

function setupBoard($array) {
	Vi::$board = array(
		'uri'         => $array['uri'],
		'title'       => $array['title'],
		'subtitle'    => isset($array['subtitle']) ? $array['subtitle'] : "",
		'indexed'     => isset($array['indexed']) ? $array['indexed'] : true,
		'public_logs' => isset($array['public_logs']) ? $array['public_logs'] : true,
		'public_bans' => $array['public_bans'] ? true : false,
	);

	// older versions
	Vi::$board['name'] = &Vi::$board['title'];

	Vi::$board['dir'] = sprintf(Vi::$config['board_path'], Vi::$board['uri']);
	Vi::$board['url'] = sprintf(Vi::$config['board_abbreviation'], Vi::$board['uri']);

	loadConfig();

	if (!file_exists(Vi::$board['dir'])) {
		@mkdir(Vi::$board['dir'], 0777) or error("Couldn't create " . Vi::$board['dir'] . ". Check permissions.", true);
	}

	if (!file_exists(Vi::$config['dir']['img_root'] . Vi::$board['dir'] . Vi::$config['dir']['img'])) {
		@mkdir(Vi::$config['dir']['img_root'] . Vi::$board['dir'] . Vi::$config['dir']['img'], 0777)
		or error("Couldn't create " . Vi::$config['dir']['img_root'] . Vi::$board['dir'] . Vi::$config['dir']['img'] . ". Check permissions.", true);
	}

	if (!file_exists(Vi::$config['dir']['img_root'] . Vi::$board['dir'] . Vi::$config['dir']['thumb'])) {
		@mkdir(Vi::$config['dir']['img_root'] . Vi::$board['dir'] . Vi::$config['dir']['thumb'], 0777)
		or error("Couldn't create " . Vi::$config['dir']['img_root'] . Vi::$board['dir'] . Vi::$config['dir']['img'] . ". Check permissions.", true);
	}

	if (!file_exists(Vi::$board['dir'] . Vi::$config['dir']['res'])) {
		@mkdir(Vi::$board['dir'] . Vi::$config['dir']['res'], 0777)
		or error("Couldn't create " . Vi::$board['dir'] . Vi::$config['dir']['img'] . ". Check permissions.", true);
	}

}

function openBoard($uri) {
	if (Vi::$config['try_smarter']) {
		Vi::$build_pages = [];
	}

	// And what if we don't really need to change a board we have opened?
	//if (Vi::$board && isset (Vi::$board['uri']) && Vi::$board['uri'] === $uri) {
	//	return true;
	//}

	$b = getBoardInfo($uri);
	if ($b) {
		setupBoard($b);

		if (function_exists('after_open_board')) {
			after_open_board();
		}

		return true;
	}
	return false;
}

function getBoardInfo($uri) {
	if (Vi::$config['cache']['enabled'] && ($board = cache::get('board_' . $uri))) {
		return $board;
	}

	$query = prepare("SELECT * FROM ``boards`` WHERE `uri` = :uri LIMIT 1");
	$query->bindValue(':uri', $uri);
	$query->execute() or error(db_error($query));

	if ($board = $query->fetch(PDO::FETCH_ASSOC)) {
		if (Vi::$config['cache']['enabled']) {
			cache::set('board_' . $uri, $board);
		}

		return $board;
	}

	return false;
}

function boardTitle($uri) {
	$board = getBoardInfo($uri);
	if ($board) {
		return $board['title'];
	}

	return false;
}

function cloudflare_purge($uri) {
	if (!Vi::$config['cloudflare']['enabled']) {
		return;
	}

	$fields = array(
		'a'     => 'zone_file_purge',
		'tkn'   => Vi::$config['cloudflare']['token'],
		'email' => Vi::$config['cloudflare']['email'],
		'z'     => Vi::$config['cloudflare']['domain'],
		'url'   => 'https://' . Vi::$config['cloudflare']['domain'] . '/' . $uri,
	);

	$fields_string = http_build_query($fields);

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, 'https://www.cloudflare.com/api_json.html');
	curl_setopt($ch, CURLOPT_POST, count($fields));
	curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$result = curl_exec($ch);

	curl_close($ch);

	return $result;
}

function purge($uri, $cloudflare = false) {
	if ($cloudflare) {
		cloudflare_purge($uri);
	}

	if (!isset(Vi::$config['purge'])) {
		return;
	}

	// Fix for Unicode
	$uri = rawurlencode($uri);

	$noescape     = "/!~*()+:";
	$noescape     = preg_split('//', $noescape);
	$noescape_url = array_map("rawurlencode", $noescape);
	$uri          = str_replace($noescape_url, $noescape, $uri);

	if (preg_match(Vi::$config['referer_match'], Vi::$config['root']) && isset($_SERVER['REQUEST_URI'])) {
		$uri = (str_replace('\\', '/', dirname($_SERVER['REQUEST_URI'])) == '/' ? '/' : str_replace('\\', '/', dirname($_SERVER['REQUEST_URI'])) . '/') . $uri;
	} else {
		$uri = Vi::$config['root'] . $uri;
	}

	if (Vi::$config['debug']) {
		Vi::$debug['purge'][] = $uri;
	}

	foreach (Vi::$config['purge'] as &$purge) {
		$host      = &$purge[0];
		$port      = &$purge[1];
		$http_host = isset($purge[2]) ? $purge[2] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
		$request   = "PURGE {$uri} HTTP/1.1\r\nHost: {$http_host}\r\nUser-Agent: Tinyboard\r\nConnection: Close\r\n\r\n";
		if ($fp = @fsockopen($host, $port, $errno, $errstr, Vi::$config['purge_timeout'])) {
			fwrite($fp, $request);
			fclose($fp);
		} else {
			// Cannot connect?
			error('Could not purge');
		}
	}
}

function file_write($path, $data, $simple = false, $skip_purge = false) {
	if (preg_match('/^remote:\/\/(.+)\:(.+)$/', $path, $m)) {
		if (isset(Vi::$config['remote'][$m[1]])) {
			require_once 'inc/remote.php';

			$remote = new Remote(Vi::$config['remote'][$m[1]]);
			$remote->write($data, $m[2]);
			return;
		} else {
			error('Invalid remote server: ' . $m[1]);
		}
	}

	if (!function_exists("dio_truncate")) {
		if (!$fp = fopen($path, $simple ? 'w' : 'c')) {
			error('Unable to open file for writing: ' . $path);
		}

		// File locking
		if (!$simple && !flock($fp, LOCK_EX)) {
			error('Unable to lock file: ' . $path);
		}

		// Truncate file
		if (!$simple && !ftruncate($fp, 0)) {
			error('Unable to truncate file: ' . $path);
		}

		// Write data
		if (($bytes = fwrite($fp, $data)) === false) {
			error('Unable to write to file: ' . $path);
		}

		// Unlock
		if (!$simple) {
			flock($fp, LOCK_UN);
		}

		// Close
		if (!fclose($fp)) {
			error('Unable to close file: ' . $path);
		}

	} else {
		if (!$fp = dio_open(getcwd() . DIRECTORY_SEPARATOR . $path, O_WRONLY | O_CREAT, 0644)) {
			error('Unable to open file for writing: ' . $path);
		}

		// File locking
		if (dio_fcntl($fp, F_SETLKW, array('type' => F_WRLCK)) === -1) {
			error('Unable to lock file: ' . $path);
		}

		// Truncate file
		if (!dio_truncate($fp, 0)) {
			error('Unable to truncate file: ' . $path);
		}

		// Write data
		if (($bytes = dio_write($fp, $data)) === false) {
			error('Unable to write to file: ' . $path);
		}

		// Unlock
		dio_fcntl($fp, F_SETLK, array('type' => F_UNLCK));

		// Close
		dio_close($fp);
	}

	/**
	 * Create gzipped file.
	 *
	 * When writing into a file foo.bar and the size is larger or equal to 1
	 * KiB, this also produces the gzipped version foo.bar.gz
	 *
	 * This is useful with nginx with gzip_static on.
	 */
	if (Vi::$config['gzip_static']) {
		$gzpath = "$path.gz";

		if ($bytes & ~0x3ff) {
			// if ($bytes >= 1024)
			if (file_put_contents($gzpath, gzencode($data), $simple ? 0 : LOCK_EX) === false) {
				error("Unable to write to file: $gzpath");
			}

			if (!touch($gzpath, filemtime($path), fileatime($path))) {
				error("Unable to touch file: $gzpath");
			}

		} else {
			@unlink($gzpath);
		}
	}

	if (!$skip_purge && isset(Vi::$config['purge'])) {
		// Purge cache
		if (basename($path) == Vi::$config['file_index']) {
			// Index file (/index.html); purge "/" as well
			$uri = dirname($path);
			// root
			if ($uri == '.') {
				$uri = '';
			} else {
				$uri .= '/';
			}

			purge($uri);
		}
		purge($path);
	}

	if (Vi::$config['debug']) {
		Vi::$debug['write'][] = $path . ': ' . $bytes . ' bytes';
	}

	event('write', $path);
}

function file_unlink($path) {
	if (Vi::$config['debug']) {
		if (!isset(Vi::$debug['unlink'])) {
			Vi::$debug['unlink'] = array();
		}

		Vi::$debug['unlink'][] = $path;
	}

	$ret = @unlink($path);

	if (Vi::$config['gzip_static']) {
		$gzpath = "$path.gz";

		@unlink($gzpath);
	}

	if (isset(Vi::$config['purge']) && $path[0] != '/' && isset($_SERVER['HTTP_HOST'])) {
		// Purge cache
		if (basename($path) == Vi::$config['file_index']) {
			// Index file (/index.html); purge "/" as well
			$uri = dirname($path);
			// root
			if ($uri == '.') {
				$uri = '';
			} else {
				$uri .= '/';
			}

			purge($uri);
		}
		purge($path);
	}

	event('unlink', $path);

	return $ret;
}

function hasPermission($action = null, $board = null, $_mod = null) {
	if (isset($_mod)) {
		$mod = &$_mod;
	} else {
		$mod = &Vi::$mod;
	}

	if (!is_array($mod)) {
		return false;
	}

	if (isset($action) && $mod['type'] < $action) {
		return false;
	}

	if (!isset($board) || Vi::$config['mod']['skip_per_board']) {
		return true;
	}

	if (!isset($mod['boards'])) {
		return false;
	}

	if (!in_array('*', $mod['boards']) && !in_array($board, $mod['boards'])) {
		return false;
	}

	return true;
}

function listBoards($just_uri = false, $indexed_only = false) {
	$just_uri ? $cache_name = 'all_boards_uri' : $cache_name = 'all_boards';
	$indexed_only ? $cache_name .= 'indexed' : false;

	if (Vi::$config['cache']['enabled'] && ($boards = cache::get($cache_name))) {
		return $boards;
	}

	if (!$just_uri) {
		$query = query(
			"SELECT
				``boards``.`uri` uri,
				``boards``.`title` title,
				``boards``.`subtitle` subtitle,
				``board_create``.`time` time,
				``boards``.`indexed` indexed,
				``boards``.`sfw` sfw,
				``boards``.`posts_total` posts_total
			FROM ``boards``
			LEFT JOIN ``board_create``
				ON ``boards``.`uri` = ``board_create``.`uri`" .
			($indexed_only ? " WHERE `indexed` = 1 " : "") .
			"ORDER BY ``boards``.`uri`") or error(db_error());

		$boards = $query->fetchAll(PDO::FETCH_ASSOC);
	} else {
		$boards = array();
		$query  = query("SELECT `uri` FROM ``boards``" . ($indexed_only ? " WHERE `indexed` = 1" : "") . " ORDER BY ``boards``.`uri`") or error(db_error());
		while (true) {
			$board = $query->fetchColumn();
			if ($board === FALSE) {
				break;
			}

			$boards[] = $board;
		}
	}

	if (Vi::$config['cache']['enabled']) {
		cache::set($cache_name, $boards);
	}

	return $boards;
}

function BoardLocale($uri, $default = NULL) {
//return board locale (if $uri == "" - return global (site) locale )
	if (!$default) {
		$default = Vi::$default_locale;
	}

	$locale = $default;
	if ($uri !== "") {
		$uri .= "/";
	}

	if (@file_exists($fn = "./{$uri}locale")) {
		$locale = trim(@file_get_contents($fn));
	}

	if (!$locale) {
		$locale = $default;
	}
	// default

	return $locale;
}

function fetchBoardActivity(array $uris = array(), $forTime = false, $detailed = false) {
	// Set our search time for now if we didn't pass one.
	if (!is_integer($forTime)) {
		$forTime = time();
	}

	// Get the last hour for this timestamp.
	$nowHour = ((int) (time() / 3600) * 3600);
	// Get the hour before. This is what we actually use for pulling data.
	$forHour = ((int) ($forTime / 3600) * 3600) - 3600;
	// Get the hour from yesterday to calculate posts per day.
	$yesterHour = $forHour - (3600 * 23);

	$boardActivity = array(
		'active'  => array(),
		'today'   => array(),
		'average' => array(),
		'last'    => array(),
	);

	// Query for stats for these boards.
	if (count($uris)) {
		$uriSearch = "`stat_uri` IN (\"" . implode((array) $uris, "\",\"") . "\") AND ";
	} else {
		$uriSearch = "";
	}

	if ($detailed === true) {
		$bsQuery = prepare("SELECT `stat_uri`, `stat_hour`, `post_count`, `author_ip_array` FROM ``board_stats`` WHERE {$uriSearch} ( `stat_hour` <= :hour AND `stat_hour` >= :hoursago )");
		$bsQuery->bindValue(':hour', $forHour, PDO::PARAM_INT);
		$bsQuery->bindValue(':hoursago', $forHour - (3600 * 72), PDO::PARAM_INT);
		$bsQuery->execute() or error(db_error($bsQuery));
		$bsResult = $bsQuery->fetchAll(PDO::FETCH_ASSOC);

		// Format the results.
		foreach ($bsResult as $bsRow) {
			// Do we need to define the arrays for this URI?
			if (!isset($boardActivity['active'][$bsRow['stat_uri']])) {
				// We are operating under the assumption that no arrays exist.
				// Because of that, we are flat defining their values.

				// Set the last hour count to 0 in case this isn't the row from this hour.
				$boardActivity['last'][$bsRow['stat_uri']] = 0;

				// If this post was made in the last 24 hours, define 'today' with it.
				if ($bsRow['stat_hour'] <= $forHour && $bsRow['stat_hour'] >= $yesterHour) {
					$boardActivity['today'][$bsRow['stat_uri']] = $bsRow['post_count'];

					// If this post was made the last hour, redefine 'last' with it.
					if ($bsRow['stat_hour'] == $forHour) {
						$boardActivity['last'][$bsRow['stat_uri']] = $bsRow['post_count'];
					}
				} else {
					// First record was not made today, define as zero.
					$boardActivity['today'][$bsRow['stat_uri']] = 0;
				}

				// Set the active posters as the unserialized array.
				$uns = @unserialize($bsRow['author_ip_array']);
				if (!$uns) {
					continue;
				}

				$boardActivity['active'][$bsRow['stat_uri']] = $uns;
				// Start the average PPH off at the current post count.
				$boardActivity['average'][$bsRow['stat_uri']] = $bsRow['post_count'];
			} else {
				// These arrays ARE defined so we ARE going to assume they exist and compound their values.

				// If this row came from today, add its post count to 'today'.
				if ($bsRow['stat_hour'] <= $forHour && $bsRow['stat_hour'] >= $yesterHour) {
					$boardActivity['today'][$bsRow['stat_uri']] += $bsRow['post_count'];

					// If this post came from this hour, set it to the post count.
					// This is an explicit set because we should never get two rows from the same hour.
					if ($bsRow['stat_hour'] == $forHour) {
						$boardActivity['last'][$bsRow['stat_uri']] = $bsRow['post_count'];
					}
				}

				// Merge our active poster arrays. Unique counting is done below.
				$uns = @unserialize($bsRow['author_ip_array']);
				if (!$uns) {
					continue;
				}

				$boardActivity['active'][$bsRow['stat_uri']] = array_merge($boardActivity['active'][$bsRow['stat_uri']], $uns);
				// Add our post count to the average. Averaging is done below.
				$boardActivity['average'][$bsRow['stat_uri']] += $bsRow['post_count'];
			}
		}

		// Count the unique posters for each board.
		foreach ($boardActivity['active'] as &$activity) {
			$activity = count(array_unique($activity));
		}
		// Average the number of posts made for each board.
		foreach ($boardActivity['average'] as &$activity) {
			$activity /= 72;
		}
	}
	// Simple return.
	else {
		$bsQuery = prepare("SELECT SUM(`post_count`) AS `post_count` FROM ``board_stats`` WHERE {$uriSearch} ( `stat_hour` = :hour )");
		$bsQuery->bindValue(':hour', $forHour, PDO::PARAM_INT);
		$bsQuery->execute() or error(db_error($bsQuery));
		$bsResult = $bsQuery->fetchAll(PDO::FETCH_ASSOC);

		$boardActivity = $bsResult[0]['post_count'];
	}

	return $boardActivity;
}

function fetchBoardTags($uris) {
	$boardTags = array();
	$uris      = "\"" . implode((array) $uris, "\",\"") . "\"";

	$tagQuery = prepare("SELECT * FROM ``board_tags`` WHERE `uri` IN ({$uris})");
	$tagQuery->execute() or error(db_error($tagQuery));
	$tagResult = $tagQuery->fetchAll(PDO::FETCH_ASSOC);

	if ($tagResult) {
		foreach ($tagResult as $tagRow) {
			$tag = $tagRow['tag'];
			$tag = trim($tag);
			$tag = strtolower($tag);
			$tag = str_replace(['_', ' '], '-', $tag);

			if (!isset($boardTags[$tagRow['uri']])) {
				$boardTags[$tagRow['uri']] = array();
			}

			$boardTags[$tagRow['uri']][] = strtolower($tag);
		}
	}

	return $boardTags;
}

function until($timestamp) {
	$difference = $timestamp - time();
	switch (TRUE) {
	case ($difference < 60):
		return $difference . ' ' . ngettext('second', 'seconds', $difference);
	case ($difference < 3600): //60*60 = 3600
		return ($num = round($difference / (60))) . ' ' . ngettext('minute', 'minutes', $num);
	case ($difference < 86400): //60*60*24 = 86400
		return ($num = round($difference / (3600))) . ' ' . ngettext('hour', 'hours', $num);
	case ($difference < 604800): //60*60*24*7 = 604800
		return ($num = round($difference / (86400))) . ' ' . ngettext('day', 'days', $num);
	case ($difference < 31536000): //60*60*24*365 = 31536000
		return ($num = round($difference / (604800))) . ' ' . ngettext('week', 'weeks', $num);
	default:
		return ($num = round($difference / (31536000))) . ' ' . ngettext('year', 'years', $num);
	}
}

function ago($timestamp) {
	$difference = time() - $timestamp;
	switch(TRUE){
	case ($difference < 60) :
		return $difference . ' ' . ngettext('second', 'seconds', $difference);
	case ($difference < 3600): //60*60 = 3600
		return ($num = round($difference/(60))) . ' ' . ngettext('minute', 'minutes', $num);
	case ($difference <  86400): //60*60*24 = 86400
		return ($num = round($difference/(3600))) . ' ' . ngettext('hour', 'hours', $num);
	case ($difference < 604800): //60*60*24*7 = 604800
		return ($num = round($difference/(86400))) . ' ' . ngettext('day', 'days', $num);
	case ($difference < 31536000): //60*60*24*365 = 31536000
		return ($num = round($difference/(604800))) . ' ' . ngettext('week', 'weeks', $num);
	default:
		return ($num = round($difference/(31536000))) . ' ' . ngettext('year', 'years', $num);
	}
}

function displayBan($ban) {
	if (!$ban['seen']) {
		Bans::seen($ban['id']);
	}

	$ban['ip'] = GetIp();

	if ($ban['post'] && isset($ban['post']['board'], $ban['post']['id'])) {
		if (openBoard($ban['post']['board'])) {
			$query = query(sprintf("SELECT `files` FROM ``posts_%s`` WHERE `id` = " .
				(int) $ban['post']['id'], Vi::$board['uri']));
			if ($_post = $query->fetch(PDO::FETCH_ASSOC)) {
				$ban['post'] = array_merge($ban['post'], $_post);
			}
		}
		if ($ban['post']['thread']) {
			$post = new Post($ban['post']);
		} else {
			$post = new Thread($ban['post'], null, false, false);
		}
	}

	if(Tor_Session::check()) {
		setcookie(Vi::$config['tor']['cookie_name'], NULL);
	}

	$denied_appeals = array();
	$pending_appeal = false;

	if (Vi::$config['ban_appeals'] && !Tor_Session::check()) {
		$query = query("SELECT `time`, `denied` FROM ``ban_appeals`` WHERE `ban_id` = " . (int) $ban['id']) or error(db_error());
		while ($ban_appeal = $query->fetch(PDO::FETCH_ASSOC)) {
			if ($ban_appeal['denied']) {
				$denied_appeals[] = $ban_appeal['time'];
			} else {
				$pending_appeal = $ban_appeal['time'];
			}
		}
	}

	// Show banned page and exit
	die(
		Element('page.html', array(
			'title'     => _('Banned!'),
			'config'    => Vi::$config,
			'boardlist' => createBoardlist(),
			'body'      => Element('banned.html', array(
				'config'         => Vi::$config,
				'ban'            => $ban,
				'board'          => Vi::$board,
				'post'           => isset($post) ? $post->build(true) : false,
				'denied_appeals' => $denied_appeals,
				'pending_appeal' => $pending_appeal,
				'tor'			 => Tor_Session::check(),
			)
			))
		));
}

function checkBan($board = false) {
	if (!isset($_SERVER['REMOTE_ADDR'])) {
		// Server misconfiguration
		return;
	}

	if (event('check-ban', $board)) {
		return true;
	}

	$bans = Bans::find(GetIp(), $board, Vi::$config['show_modname']);

	foreach ($bans as &$ban) {

		if ($ban['expires'] && $ban['expires'] < time()) {
			Bans::delete($ban['id']);
			if (Vi::$config['require_ban_view'] && !$ban['seen']) {
				if (!isset($_POST['json_response'])) {
					displayBan($ban);
				} else {
					header('Content-Type: text/json');
					die(json_encode(array('error' => true, 'banned' => true)));
				}
			}
		} else {
			if (!isset($_POST['json_response'])) {
				displayBan($ban);
			} else {
				header('Content-Type: text/json');
				die(json_encode(array('error' => true, 'banned' => true)));
			}
		}
	}

	// I'm not sure where else to put this. It doesn't really matter where; it just needs to be called every
	// now and then to keep the ban list tidy.
	if (Vi::$config['cache']['enabled'] && $last_time_purged = cache::get('purged_bans_last')) {
		if (time() - $last_time_purged < Vi::$config['purge_bans']) {
			return;
		}
	}

	//Bans::purge();

	if (Vi::$config['cache']['enabled']) {
		cache::set('purged_bans_last', time());
	}
}

function threadLocked($id) {
	if (event('check-locked', $id)) {
		return true;
	}

	$query = prepare(sprintf("SELECT `locked` FROM ``posts_%s`` WHERE `id` = :id AND `thread` IS NULL LIMIT 1", Vi::$board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error());

	if (($locked = $query->fetchColumn()) === false) {
		// Non-existant, so it can't be locked...
		return false;
	}

	return (bool) $locked;
}

function threadSageLocked($id) {
	if (event('check-sage-locked', $id)) {
		return true;
	}

	$query = prepare(sprintf("SELECT `sage` FROM ``posts_%s`` WHERE `id` = :id AND `thread` IS NULL LIMIT 1", Vi::$board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error());

	if (($sagelocked = $query->fetchColumn()) === false) {
		// Non-existant, so it can't be locked...
		return false;
	}

	return (bool) $sagelocked;
}

function threadExists($id) {
	$query = prepare(sprintf("SELECT 1 FROM ``posts_%s`` WHERE `id` = :id AND `thread` IS NULL LIMIT 1", Vi::$board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error());

	if ($query->rowCount()) {
		return true;
	}

	return false;
}

function insertFloodPost(array $post) {
	$query = prepare("INSERT INTO ``flood`` VALUES (NULL, :ip, :board, :time, :posthash, :filehash, :isreply)");
	$query->bindValue(':ip', GetIp());
	$query->bindValue(':board', Vi::$board['uri']);
	$query->bindValue(':time', time());
	$query->bindValue(':posthash', make_comment_hex($post['body_nomarkup']));

	if ($post['has_file']) {
		$query->bindValue(':filehash', $post['filehash']);
	} else {
		$query->bindValue(':filehash', null, PDO::PARAM_NULL);
	}

	$query->bindValue(':isreply', !$post['op'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
}

function post(array $post) {
	$query = prepare(sprintf("INSERT INTO ``posts_%s`` VALUES ( NULL, :thread, :subject, :email, :name, :trip, :capcode, :body, :body_nomarkup, :time, :time, :files, :num_files, :filehash, :password, :ip, :sticky, :locked, :cycle, 0, :embed, NULL)", Vi::$board['uri']));

	// Basic stuff
	if (!empty($post['subject'])) {
		$query->bindValue(':subject', $post['subject']);
	} else {
		$query->bindValue(':subject', null, PDO::PARAM_NULL);
	}

	if (!empty($post['email'])) {
		$query->bindValue(':email', $post['email']);
	} else {
		$query->bindValue(':email', null, PDO::PARAM_NULL);
	}

	if (!empty($post['trip'])) {
		$query->bindValue(':trip', $post['trip']);
	} else {
		$query->bindValue(':trip', null, PDO::PARAM_NULL);
	}

	$salt     = time() . Vi::$board['uri'];
	$password = hash_pbkdf2("sha256", $post['password'], $salt, 10000, 20);

	$query->bindValue(':name', $post['name']);
	$query->bindValue(':body', $post['body']);
	$query->bindValue(':body_nomarkup', $post['body_nomarkup']);
	$query->bindValue(':time', isset($post['time']) ? $post['time'] : time(), PDO::PARAM_INT);
	$query->bindValue(':password', $password);
	$query->bindValue(':ip', isset($post['ip']) ? $post['ip'] : GetIp());

	if ($post['op'] && $post['mod'] && isset($post['sticky']) && $post['sticky']) {
		$query->bindValue(':sticky', true, PDO::PARAM_INT);
	} else {
		$query->bindValue(':sticky', false, PDO::PARAM_INT);
	}

	if ($post['op'] && $post['mod'] && isset($post['locked']) && $post['locked']) {
		$query->bindValue(':locked', true, PDO::PARAM_INT);
	} else {
		$query->bindValue(':locked', false, PDO::PARAM_INT);
	}

	if ($post['op'] && $post['mod'] && isset($post['cycle']) && $post['cycle']) {
		$query->bindValue(':cycle', true, PDO::PARAM_INT);
	} else {
		$query->bindValue(':cycle', false, PDO::PARAM_INT);
	}

	if ($post['mod'] && isset($post['capcode']) && $post['capcode']) {
		$query->bindValue(':capcode', $post['capcode'], PDO::PARAM_INT);
	} else {
		$query->bindValue(':capcode', null, PDO::PARAM_NULL);
	}

	if (!empty($post['embed'])) {
		$query->bindValue(':embed', $post['embed']);
	} else {
		$query->bindValue(':embed', null, PDO::PARAM_NULL);
	}

	if ($post['op']) {
		// No parent thread, image
		$query->bindValue(':thread', null, PDO::PARAM_NULL);
	} else {
		$query->bindValue(':thread', $post['thread'], PDO::PARAM_INT);
	}

	if ($post['has_file']) {
		$query->bindValue(':files', json_encode($post['files']));
		$query->bindValue(':num_files', $post['num_files']);
		$query->bindValue(':filehash', $post['filehash']);
	} else {
		$query->bindValue(':files', null, PDO::PARAM_NULL);
		$query->bindValue(':num_files', 0);
		$query->bindValue(':filehash', null, PDO::PARAM_NULL);
	}

	if (!$query->execute()) {
		undoImage($post);
		error(db_error($query));
	}

	return Vi::$pdo->lastInsertId();
}

function bumpThread($id) {
	if (event('bump', $id)) {
		return true;
	}

	if (Vi::$config['try_smarter']) {
		Vi::$build_pages = array_merge(range(1, thread_find_page($id)), Vi::$build_pages);
	}

	$query = prepare(sprintf("UPDATE ``posts_%s`` SET `bump` = :time WHERE `id` = :id AND `thread` IS NULL", Vi::$board['uri']));
	$query->bindValue(':time', time(), PDO::PARAM_INT);
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
}

// Remove file from post
function deleteFile($id, $remove_entirely_if_already = true, $file = null) {
	$query = prepare(sprintf("SELECT `thread`, `files`, `num_files` FROM ``posts_%s`` WHERE `id` = :id LIMIT 1", Vi::$board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	if (!$post = $query->fetch(PDO::FETCH_ASSOC)) {
		error(Vi::$config['error']['invalidpost']);
	}

	$files          = json_decode($post['files']);
	$file_to_delete = $file !== false ? $files[(int) $file] : (object) array('file' => false);

	if (!$files[0]) {
		error(_('That post has no files.'));
	}

	if ($files[0]->file == 'deleted' && $post['num_files'] == 1 && !$post['thread']) {
		return;
	}
	// Can't delete OP's image completely.

	$query = prepare(sprintf("UPDATE ``posts_%s`` SET `files` = :file WHERE `id` = :id", Vi::$board['uri']));
	if (($file && $file_to_delete->file == 'deleted') && $remove_entirely_if_already) {
		// Already deleted; remove file fully
		$files[$file] = null;
	} else {
		foreach ($files as $i => $f) {
			if (($file !== false && $i == $file) || $file === null) {
				// Delete thumbnail
				if (isset($f->thumb) && $f->thumb) {
					file_unlink(Vi::$config['dir']['img_root'] . Vi::$board['dir'] . Vi::$config['dir']['thumb'] . $f->thumb);
					unset($files[$i]->thumb);
				}

				// Delete file
				file_unlink(Vi::$config['dir']['img_root'] . Vi::$board['dir'] . Vi::$config['dir']['img'] . $f->file);
				$files[$i]->file = 'deleted';
			}
		}
	}

	$query->bindValue(':file', json_encode($files), PDO::PARAM_STR);

	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if ($post['thread']) {
		buildThread($post['thread']);
	} else {
		buildThread($id);
	}

}

// rebuild post (markup)
function rebuildPost($id) {
	$query = prepare(sprintf("SELECT * FROM ``posts_%s`` WHERE `id` = :id", Vi::$board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if ((!$post = $query->fetch(PDO::FETCH_ASSOC)) || !$post['body_nomarkup']) {
		return false;
	}

	markup($post['body'] = &$post['body_nomarkup']);
	$post = (object) $post;
	event('rebuildpost', $post);
	$post = (array) $post;

	$query = prepare(sprintf("UPDATE ``posts_%s`` SET `body` = :body WHERE `id` = :id", Vi::$board['uri']));
	$query->bindValue(':body', $post['body']);
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	buildThread($post['thread'] ? $post['thread'] : $id);

	return true;
}

// Delete a post (reply or thread)
function deletePost($id, $error_if_doesnt_exist = true, $rebuild_after = true) {
	// Select post and replies (if thread) in one query
	$query = prepare(sprintf("SELECT `id`,`thread`,`files` FROM ``posts_%s`` WHERE `id` = :id OR `thread` = :id", Vi::$board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if ($query->rowCount() < 1) {
		if ($error_if_doesnt_exist) {
			error(Vi::$config['error']['invalidpost']);
		} else {
			return false;
		}

	}

	$ids = array();

	// Delete posts and maybe replies
	while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		event('delete', $post);

		if (!$post['thread']) {
			// Delete thread HTML page
			@file_unlink(Vi::$board['dir'] . Vi::$config['dir']['res'] . sprintf(Vi::$config['file_page'], $post['id']));
			@file_unlink(Vi::$board['dir'] . Vi::$config['dir']['res'] . sprintf(Vi::$config['file_page50'], $post['id']));
			@file_unlink(Vi::$board['dir'] . Vi::$config['dir']['res'] . sprintf('%d.json', $post['id']));

			$antispam_query = prepare('DELETE FROM ``antispam`` WHERE `board` = :board AND `thread` = :thread');
			$antispam_query->bindValue(':board', Vi::$board['uri']);
			$antispam_query->bindValue(':thread', $post['id']);
			$antispam_query->execute() or error(db_error($antispam_query));
		} elseif ($query->rowCount() == 1) {
			// Rebuild thread
			$rebuild = &$post['thread'];
		}
		if ($post['files']) {
			// Delete file
			foreach (json_decode($post['files']) as $i => $f) {
				if (isset($f->file, $f->thumb) && $f->file !== 'deleted') {
					@file_unlink(Vi::$config['dir']['img_root'] . Vi::$board['dir'] . Vi::$config['dir']['img'] . $f->file);
					@file_unlink(Vi::$config['dir']['img_root'] . Vi::$board['dir'] . Vi::$config['dir']['thumb'] . $f->thumb);
				}
			}
		}

		$ids[] = (int) $post['id'];
	}

	$query = prepare(sprintf("DELETE FROM ``posts_%s`` WHERE `id` = :id OR `thread` = :id", Vi::$board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	$query = prepare("SELECT `board`, `post` FROM ``cites`` WHERE `target_board` = :board AND (`target` = " . implode(' OR `target` = ', $ids) . ") ORDER BY `board`");
	$query->bindValue(':board', Vi::$board['uri']);
	$query->execute() or error(db_error($query));
	while ($cite = $query->fetch(PDO::FETCH_ASSOC)) {
		if (Vi::$board['uri'] != $cite['board']) {
			if (!isset($tmp_board)) {
				$tmp_board = Vi::$board['uri'];
			}

			openBoard($cite['board']);
		}
		rebuildPost($cite['post']);
	}

	if (isset($tmp_board)) {
		openBoard($tmp_board);
	}

	$query = prepare("DELETE FROM ``cites`` WHERE (`target_board` = :board AND (`target` = " . implode(' OR `target` = ', $ids) . ")) OR (`board` = :board AND (`post` = " . implode(' OR `post` = ', $ids) . "))");
	$query->bindValue(':board', Vi::$board['uri']);
	$query->execute() or error(db_error($query));

	if (isset($rebuild) && $rebuild_after) {
		buildThread($rebuild);
		buildIndex();
	}

	return true;
}

function clean($pid = false) {
	$offset = round(Vi::$config['max_pages'] * Vi::$config['threads_per_page']);

	// I too wish there was an easier way of doing this...
	$query = prepare(sprintf("SELECT `id` FROM ``posts_%s`` WHERE `thread` IS NULL ORDER BY `sticky` DESC, `bump` DESC LIMIT :offset, 9001", Vi::$board['uri']));
	$query->bindValue(':offset', $offset, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		deletePost($post['id'], false, false);
		if ($pid) {
			modLog("Automatically deleting thread #{$post['id']} due to new thread #{$pid}");
		}

	}

	// Bump off threads with X replies earlier, spam prevention method
	if (Vi::$config['early_404']) {
		$offset = round(Vi::$config['early_404_page'] * Vi::$config['threads_per_page']);
		$query  = prepare(sprintf("SELECT `id` AS `thread_id`, (SELECT COUNT(`id`) FROM ``posts_%s`` WHERE `thread` = `thread_id`) AS `reply_count` FROM ``posts_%s`` WHERE `thread` IS NULL ORDER BY `sticky` DESC, `bump` DESC LIMIT :offset, 9001", Vi::$board['uri'], Vi::$board['uri']));
		$query->bindValue(':offset', $offset, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
			if ($post['reply_count'] < Vi::$config['early_404_replies']) {
				deletePost($post['thread_id'], false, false);
				if ($pid) {
					modLog("Automatically deleting thread #{$post['thread_id']} due to new thread #{$pid} (early 404 is set, #{$post['thread_id']} had {$post['reply_count']} replies)");
				}

			}
		}
	}

}

function thread_find_page($thread) {
	$query   = query(sprintf("SELECT `id` FROM ``posts_%s`` WHERE `thread` IS NULL ORDER BY `sticky` DESC, `bump` DESC", Vi::$board['uri'])) or error(db_error($query));
	$threads = $query->fetchAll(PDO::FETCH_COLUMN);
	if (($index = array_search($thread, $threads)) === false) {
		return false;
	}

	return floor((Vi::$config['threads_per_page'] + $index) / Vi::$config['threads_per_page']);
}

// $brief means that we won't need to generate anything yet
function index($page, $mod = false, $brief = false) {
	$body   = '';
	$offset = round($page * Vi::$config['threads_per_page'] - Vi::$config['threads_per_page']);

	$query = prepare(sprintf("SELECT * FROM ``posts_%s`` WHERE `thread` IS NULL ORDER BY `sticky` DESC, `bump` DESC LIMIT :offset,:threads_per_page", Vi::$board['uri']));
	$query->bindValue(':offset', $offset, PDO::PARAM_INT);
	$query->bindValue(':threads_per_page', Vi::$config['threads_per_page'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if ($page == 1 && $query->rowCount() < Vi::$config['threads_per_page']) {
		Vi::$board['thread_count'] = $query->rowCount();
	}

	if ($query->rowCount() < 1 && $page > 1) {
		return false;
	}

	$threads = array();

	while ($th = $query->fetch(PDO::FETCH_ASSOC)) {
		$thread = new Thread($th, $mod ? '?/' : Vi::$config['root'], $mod);

		if (Vi::$config['cache']['enabled']) {
			$cached = cache::get("thread_index_" . Vi::$board['uri'] . "_{$th['id']}");
			if (isset($cached['replies'], $cached['omitted'])) {
				$replies = $cached['replies'];
				$omitted = $cached['omitted'];
			} else {
				unset($cached);
			}
		}

		if (!isset($cached)) {
			$posts = prepare(sprintf("SELECT * FROM ``posts_%s`` WHERE `thread` = :id ORDER BY `id` DESC LIMIT :limit", Vi::$board['uri']));
			$posts->bindValue(':id', $th['id']);
			$posts->bindValue(':limit', ($th['sticky'] ? Vi::$config['threads_preview_sticky'] : Vi::$config['threads_preview']), PDO::PARAM_INT);
			$posts->execute() or error(db_error($posts));

			$replies = array_reverse($posts->fetchAll(PDO::FETCH_ASSOC));

			if (count($replies) == ($th['sticky'] ? Vi::$config['threads_preview_sticky'] : Vi::$config['threads_preview'])) {
				$count   = numPosts($th['id']);
				$omitted = array('post_count' => $count['replies'], 'image_count' => $count['images']);
			} else {
				$omitted = false;
			}

			if (Vi::$config['cache']['enabled']) {
				cache::set("thread_index_" . Vi::$board['uri'] . "_{$th['id']}", array(
					'replies' => $replies,
					'omitted' => $omitted,
				));
			}

		}

		$num_images = 0;
		foreach ($replies as $po) {
			if ($po['num_files']) {
				$num_images += $po['num_files'];
			}

			$thread->add(new Post($po, $mod ? '?/' : Vi::$config['root'], $mod));
		}

		$thread->images  = $num_images;
		$thread->replies = isset($omitted['post_count']) ? $omitted['post_count'] : count($replies);

		if ($omitted) {
			$thread->omitted        = $omitted['post_count'] - ($th['sticky'] ? Vi::$config['threads_preview_sticky'] : Vi::$config['threads_preview']);
			$thread->omitted_images = $omitted['image_count'] - $num_images;
		}

		$threads[] = $thread;

		if (!$brief) {
			$body .= $thread->build(true);
		}
	}

	if (Vi::$config['file_board']) {
		$body = Element('fileboard.html', array('body' => $body, 'mod' => $mod));
	}

	return array(
		'board'     => Vi::$board,
		'body'      => $body,
		'post_url'  => Vi::$config['post_url'],
		'config'    => Vi::$config,
		'boardlist' => createBoardlist($mod),
		'threads'   => $threads,
	);
}

// Handle statistic tracking for a new post.
function updateStatisticsForPost($post, $new = true) {
	$postIp   = isset($post['ip']) ? $post['ip'] : GetIp();
	$postUri  = $post['board'];
	$postTime = (int) ($post['time'] / 3600) * 3600;

	$bsQuery = prepare("SELECT * FROM ``board_stats`` WHERE `stat_uri` = :uri AND `stat_hour` = :hour");
	$bsQuery->bindValue(':uri', $postUri);
	$bsQuery->bindValue(':hour', $postTime, PDO::PARAM_INT);
	$bsQuery->execute() or error(db_error($bsQuery));
	$bsResult = $bsQuery->fetchAll(PDO::FETCH_ASSOC);

	// Flesh out the new stats row.
	$boardStats = array();

	// If we already have a row, we're going to be adding this post to it.
	if (count($bsResult)) {
		$boardStats                    = $bsResult[0];
		$boardStats['stat_uri']        = $postUri;
		$boardStats['stat_hour']       = $postTime;
		$boardStats['post_id_array']   = unserialize($boardStats['post_id_array']);
		$boardStats['author_ip_array'] = unserialize($boardStats['author_ip_array']);

		++$boardStats['post_count'];
		$boardStats['post_id_array'][]   = (int) $post['id'];
		$boardStats['author_ip_array'][] = less_ip($postIp);
		$boardStats['author_ip_array']   = array_unique($boardStats['author_ip_array']);
	}
	// If this a new row, we're building the stat to only reflect this first post.
	else {
		$boardStats['stat_uri']        = $postUri;
		$boardStats['stat_hour']       = $postTime;
		$boardStats['post_count']      = 1;
		$boardStats['post_id_array']   = array((int) $post['id']);
		$boardStats['author_ip_count'] = 1;
		$boardStats['author_ip_array'] = array(less_ip($postIp));
	}

	// Cleanly serialize our array for insertion.
	$boardStats['post_id_array']   = str_replace("\"", "\\\"", serialize($boardStats['post_id_array']));
	$boardStats['author_ip_array'] = str_replace("\"", "\\\"", serialize($boardStats['author_ip_array']));

	// Insert this data into our statistics table.
	$statsInsert = "VALUES(\"{$boardStats['stat_uri']}\", \"{$boardStats['stat_hour']}\", \"{$boardStats['post_count']}\", \"{$boardStats['post_id_array']}\", \"{$boardStats['author_ip_count']}\", \"{$boardStats['author_ip_array']}\" )";

	$postStatQuery = prepare(
		"REPLACE INTO ``board_stats`` (stat_uri, stat_hour, post_count, post_id_array, author_ip_count, author_ip_array) {$statsInsert}"
	);
	$postStatQuery->execute() or error(db_error($postStatQuery));

	// Update the posts_total tracker on the board.
	if ($new) {
		query("UPDATE ``boards`` SET `posts_total`=`posts_total`+1 WHERE `uri`=\"{$postUri}\"");
	}

	return $boardStats;
}

function getPageButtons($pages, $mod = false) {
	$btn  = array();
	$root = ($mod ? '?/' : Vi::$config['root']) . Vi::$board['dir'];

	foreach ($pages as $num => $page) {
		if (isset($page['selected'])) {
			// Previous button
			if ($num == 0) {
				// There is no previous page.
				$btn['prev'] = _('Previous');
			} else {
				$loc = ($mod ? '?/' . Vi::$board['uri'] . '/' : '') .
					($num == 1 ?
					Vi::$config['file_index']
					:
					sprintf(Vi::$config['file_page'], $num)
				);

				$btn['prev'] = '<form action="' . ($mod ? '' : $root . $loc) . '" method="get">' .
				($mod ?
					'<input type="hidden" name="status" value="301" />' .
					'<input type="hidden" name="r" value="' . htmlentities($loc) . '" />'
					: '') .
				'<input type="submit" value="' . _('Previous') . '" /></form>';
			}

			if ($num == count($pages) - 1) {
				// There is no next page.
				$btn['next'] = _('Next');
			} else {
				$loc = ($mod ? '?/' . Vi::$board['uri'] . '/' : '') . sprintf(Vi::$config['file_page'], $num + 2);

				$btn['next'] = '<form action="' . ($mod ? '' : $root . $loc) . '" method="get">' .
				($mod ?
					'<input type="hidden" name="status" value="301" />' .
					'<input type="hidden" name="r" value="' . htmlentities($loc) . '" />'
					: '') .
				'<input type="submit" value="' . _('Next') . '" /></form>';
			}
		}
	}

	return $btn;
}

function getPages($mod = false) {
	if (isset(Vi::$board['thread_count'])) {
		$count = Vi::$board['thread_count'];
	} else {
		// Count threads
		$query = query(sprintf("SELECT COUNT(*) FROM ``posts_%s`` WHERE `thread` IS NULL", Vi::$board['uri'])) or error(db_error());
		$count = $query->fetchColumn();
	}
	$count = floor((Vi::$config['threads_per_page'] + $count - 1) / Vi::$config['threads_per_page']);

	if ($count < 1) {
		$count = 1;
	}

	$pages = array();
	for ($x = 0; $x < $count && $x < Vi::$config['max_pages']; $x++) {
		$pages[] = array(
			'num'  => $x + 1,
			'link' => $x == 0 ? ($mod ? '?/' : Vi::$config['root']) . Vi::$board['dir'] . Vi::$config['file_index'] : ($mod ? '?/' : Vi::$config['root']) . Vi::$board['dir'] . sprintf(Vi::$config['file_page'], $x + 1),
		);
	}

	return $pages;
}

// Stolen with permission from PlainIB (by Frank Usrs)
function make_comment_hex($str) {
	// remove cross-board citations
	// the numbers don't matter
	$str = preg_replace("!>>>/[A-Za-z0-9]+/!", '', $str);

	if (Vi::$config['robot_enable']) {
		if (function_exists('iconv')) {
			// remove diacritics and other noise
			// FIXME: this removes cyrillic entirely
			$oldstr = $str;
			$str    = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
			if (!$str) {
				$str = $oldstr;
			}

		}

		$str = strtolower($str);

		// strip all non-alphabet characters
		$str = preg_replace('/[^a-z]/', '', $str);
	}

	return md5($str);
}

function makerobot($body) {
	$body = strtolower($body);

	// Leave only letters
	$body = preg_replace('/[^a-z]/i', '', $body);
	// Remove repeating characters
	if (Vi::$config['robot_strip_repeating']) {
		$body = preg_replace('/(.)\\1+/', '$1', $body);
	}

	return sha1($body);
}

function checkRobot($body) {
	if (empty($body) || event('check-robot', $body)) {
		return true;
	}

	$body  = makerobot($body);
	$query = prepare("SELECT 1 FROM ``robot`` WHERE `hash` = :hash LIMIT 1");
	$query->bindValue(':hash', $body);
	$query->execute() or error(db_error($query));

	if ($query->fetchColumn()) {
		return true;
	}

	// Insert new hash
	$query = prepare("INSERT INTO ``robot`` VALUES (:hash)");
	$query->bindValue(':hash', $body);
	$query->execute() or error(db_error($query));

	return false;
}

// Returns an associative array with 'replies' and 'images' keys
function numPosts($id) {
	$query = prepare(sprintf("SELECT COUNT(*) AS `replies`, SUM(`num_files`) AS `images` FROM ``posts_%s`` WHERE `thread` = :thread", Vi::$board['uri'], Vi::$board['uri']));
	$query->bindValue(':thread', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	return $query->fetch(PDO::FETCH_ASSOC);
}

function muteTime() {
	if ($time = event('mute-time')) {
		return $time;
	}

	// Find number of mutes in the past X hours
	$query = prepare("SELECT COUNT(*) FROM ``mutes`` WHERE `time` >= :time AND `ip` = :ip");
	$query->bindValue(':time', time() - (Vi::$config['robot_mute_hour'] * 3600), PDO::PARAM_INT);
	$query->bindValue(':ip', GetIp());
	$query->execute() or error(db_error($query));

	if (!$result = $query->fetchColumn()) {
		return 0;
	}

	return pow(Vi::$config['robot_mute_multiplier'], $result);
}

function mute() {
	// Insert mute
	$query = prepare("INSERT INTO ``mutes`` VALUES (:ip, :time)");
	$query->bindValue(':time', time(), PDO::PARAM_INT);
	$query->bindValue(':ip', GetIp());
	$query->execute() or error(db_error($query));

	return muteTime();
}

function checkMute() {
	if (Vi::$config['cache']['enabled']) {
		// Cached mute?
		if (($mute = cache::get("mute_" . GetIp())) && ($mutetime = cache::get("mutetime_" . GetIp()))) {
			error(sprintf(Vi::$config['error']['youaremuted'], $mute['time'] + $mutetime - time()));
		}
	}

	$mutetime = muteTime();
	if ($mutetime > 0) {
		// Find last mute time
		$query = prepare("SELECT `time` FROM ``mutes`` WHERE `ip` = :ip ORDER BY `time` DESC LIMIT 1");
		$query->bindValue(':ip', GetIp());
		$query->execute() or error(db_error($query));

		if (!$mute = $query->fetch(PDO::FETCH_ASSOC)) {
			// What!? He's muted but he's not muted...
			return;
		}

		if ($mute['time'] + $mutetime > time()) {
			if (Vi::$config['cache']['enabled']) {
				cache::set("mute_" . $ip, $mute, $mute['time'] + $mutetime - time());
				cache::set("mutetime" . GetIp(), $mutetime, $mute['time'] + $mutetime - time());
			}
			// Not expired yet
			error(sprintf(Vi::$config['error']['youaremuted'], $mute['time'] + $mutetime - time()));
		} else {
			// Already expired
			return;
		}
	}
}

function buildIndex($global_api = "yes") {
	$catalog_api_action = generation_strategy('sb_api', array(Vi::$board['uri']));

	$pages   = null;
	$antibot = null;

	if (Vi::$config['api']['enabled']) {
		$api     = new Api();
		$catalog = array();
	}

	for ($page = 1; $page <= Vi::$config['max_pages']; $page++) {
		$filename     = Vi::$board['dir'] . ($page == 1 ? Vi::$config['file_index'] : sprintf(Vi::$config['file_page'], $page));
		$jsonFilename = Vi::$board['dir'] . ($page - 1) . '.json'; // pages should start from 0

		$wont_build_this_page = Vi::$config['try_smarter'] && Vi::$build_pages && !empty(Vi::$build_pages) && !in_array($page, Vi::$build_pages);

		if ((!Vi::$config['api']['enabled'] || $global_api == "skip") && $wont_build_this_page) {
			continue;
		}

		$action = generation_strategy('sb_board', array(Vi::$board['uri'], $page));
		if ($action == 'rebuild' || $catalog_api_action == 'rebuild') {
			$content = index($page, false, $wont_build_this_page);
			if (!$content) {
				break;
			}

			// json api
			if (Vi::$config['api']['enabled']) {
				$threads = $content['threads'];
				$json    = json_encode($api->translatePage($threads));
				file_write($jsonFilename, $json);

				$catalog[$page - 1] = $threads;

				if ($wont_build_this_page) {
					continue;
				}

			}

			if (Vi::$config['try_smarter']) {
				$antibot                 = create_antibot(Vi::$board['uri'], 0 - $page);
				$content['current_page'] = $page;
			} elseif (!$antibot) {
				$antibot = create_antibot(Vi::$board['uri']);
			}
			$antibot->reset();
			if (!$pages) {
				$pages = getPages();
			}
			$content['pages']                        = $pages;
			$content['pages'][$page - 1]['selected'] = true;
			$content['btn']                          = getPageButtons($content['pages']);
			$content['antibot']                      = $antibot;
			$content['return']                       = Vi::$config['root'] . Vi::$board['dir'] . Vi::$config['file_index'];

			file_write($filename, Element('index.html', $content));
		} elseif ($action == 'delete' || $catalog_api_action == 'delete') {
			file_unlink($filename);
			file_unlink($jsonFilename);
		}
	}

	// $action is an action for our last page
	if (($catalog_api_action == 'rebuild' || $action == 'rebuild' || $action == 'delete') && $page < Vi::$config['max_pages']) {
		for (; $page <= Vi::$config['max_pages']; $page++) {
			$filename = Vi::$board['dir'] . ($page == 1 ? Vi::$config['file_index'] : sprintf(Vi::$config['file_page'], $page));
			file_unlink($filename);

			if (Vi::$config['api']['enabled']) {
				$jsonFilename = Vi::$board['dir'] . ($page - 1) . '.json';
				file_unlink($jsonFilename);
			}
		}
	}

	// json api catalog
	if (Vi::$config['api']['enabled'] && $global_api != "skip") {
		if ($catalog_api_action == 'delete') {
			$jsonFilename = Vi::$board['dir'] . 'catalog.json';
			file_unlink($jsonFilename);
			$jsonFilename = Vi::$board['dir'] . 'threads.json';
			file_unlink($jsonFilename);
		} elseif ($catalog_api_action == 'rebuild') {
			$json         = json_encode($api->translateCatalog($catalog));
			$jsonFilename = Vi::$board['dir'] . 'catalog.json';
			file_write($jsonFilename, $json);

			$json         = json_encode($api->translateCatalog($catalog, true));
			$jsonFilename = Vi::$board['dir'] . 'threads.json';
			file_write($jsonFilename, $json);
		}
	}

	if (Vi::$config['try_smarter']) {
		Vi::$build_pages = array();
	}

}

function buildJavascript() {
	$script = Element('main.js', array(
		'config' => Vi::$config,
	));

	if (Vi::$config['additional_javascript_compile']) {
		foreach (array_unique(Vi::$config['additional_javascript']) as $file) {
			$script .= file_get_contents($file);
		}
	}

	if (Vi::$config['minify_js']) {
		require_once 'inc/lib/minify/JSMin.php';
		$script = JSMin::minify($script);
	}

	file_write(Vi::$config['file_script'], $script);
}

function checkDNSBL($use_ip = false) {
	if (!$use_ip && !isset($_SERVER['REMOTE_ADDR'])) {
		return;
	}
	// Fix your web server configuration
	$ip = ($use_ip ? $use_ip : GetIp());
	if ($ip == Vi::$config['tor_serviceip']) {
		return true;
	}

	if (isIPv6($ip)) {
		return;
	}
	// No IPv6 support yet.

	if (in_array($ip, Vi::$config['dnsbl_exceptions'])) {
		return;
	}

	if (preg_match("/^(::(ffff:)?)?(127\.|192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|0\.|255\.)/", $_SERVER['REMOTE_ADDR'])) {
		return;
	}
	// It's pointless to check for local IP addresses in dnsbls, isn't it?

	$ipaddr = ReverseIPOctets($ip);

	foreach (Vi::$config['dnsbl'] as $blacklist) {
		if (!is_array($blacklist)) {
			$blacklist = array($blacklist);
		}

		if (($lookup = str_replace('%', $ipaddr, $blacklist[0])) == $blacklist[0]) {
			$lookup = $ipaddr . '.' . $blacklist[0];
		}

		if (!$ip = DNS($lookup)) {
			continue;
		}
		// not in list

		$blacklist_name = isset($blacklist[2]) ? $blacklist[2] : $blacklist[0];

		if (!isset($blacklist[1])) {
			// If you're listed at all, you're blocked.
			if ($use_ip) {
				return true;
			} else {
				error(sprintf(Vi::$config['error']['dnsbl'], $blacklist_name));
			}
		} elseif (is_array($blacklist[1])) {
			foreach ($blacklist[1] as $octet) {
				if ($ip == $octet || $ip == '127.0.0.' . $octet) {
					return true;
				}
			}
		} elseif (is_callable($blacklist[1])) {
			if ($blacklist[1]($ip)) {
				return true;
			}
		} else {
			if ($ip == $blacklist[1] || $ip == '127.0.0.' . $blacklist[1]) {
				return true;
			}
		}
	}
}

function isIPv6($ip = false) {
	return $ip === filter_var($ip ?: $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
}

function ReverseIPOctets($ip) {
	return implode('.', array_reverse(explode('.', $ip)));
}

function wordfilters(&$body) {
	foreach (Vi::$config['wordfilters'] as $filter) {
		if (isset($filter[2]) && $filter[2]) {
			if (is_callable($filter[1])) {
				$body = preg_replace_callback($filter[0], $filter[1], $body);
			} else {
				$body = preg_replace($filter[0], $filter[1], $body);
			}

		} else {
			$body = str_ireplace($filter[0], $filter[1], $body);
		}
	}
}

function quote($body, $quote = true) {
	$body = str_replace('<br/>', "\n", $body);
	$body = strip_tags($body);
	$body = preg_replace("/(^|\n)/", '$1&gt;', $body);
	$body .= "\n";

	if (Vi::$config['minify_html']) {
		$body = str_replace("\n", '&#010;', $body);
	}

	return $body;
}

function markup_url($matches) {
	$url   = $matches[1];
	$after = $matches[2];

	Vi::$markup_urls[] = $url;

	$link = (object) array(
		'href'   => Vi::$config['link_prefix'] . $url,
		'text'   => $url,
		'rel'    => 'nofollow',
		'target' => '_blank',
	);

	event('markup-url', $link);
	$link = (array) $link;

	$parts = array();
	foreach ($link as $attr => $value) {
		if ($attr == 'text' || $attr == 'after') {
			continue;
		}

		$parts[] = $attr . '="' . $value . '"';
	}
	if (isset($link['after'])) {
		$after = $link['after'] . $after;
	}

	return '<a ' . implode(' ', $parts) . '>' . $link['text'] . '</a>' . $after;
}

function unicodify($body) {
	$body = str_replace('...', '&hellip;', $body);
	$body = str_replace('&lt;--', '&larr;', $body);
	$body = str_replace('--&gt;', '&rarr;', $body);

	// En and em- dashes are rendered exactly the same in
	// most monospace fonts (they look the same in code
	// editors).
	$body = str_replace('---', '&mdash;', $body); // em dash
	$body = str_replace('--', '&ndash;', $body); // en dash

	return $body;
}

function extract_modifiers($body) {
	$modifiers = array();

	if (preg_match_all('@<tinyboard ([\w\s]+)>(.*?)</tinyboard>@us', $body, $matches, PREG_SET_ORDER)) {
		foreach ($matches as $match) {
			if (preg_match('/^escape /', $match[1])) {
				continue;
			}

			$modifiers[$match[1]] = html_entity_decode($match[2]);
		}
	}

	return $modifiers;
}

function remove_modifiers($body) {
	return preg_replace('@<tinyboard ([\w\s]+)>(.+?)</tinyboard>@usm', '', $body);
}

function markup(&$body, $track_cites = false, $op = false) {
	$modifiers = extract_modifiers($body);

	$body = preg_replace('@<tinyboard (?!escape )([\w\s]+)>(.+?)</tinyboard>@us', '', $body);
	$body = preg_replace('@<(tinyboard) escape ([\w\s]+)>@i', '<$1 $2>', $body);

	if (isset($modifiers['raw html']) && $modifiers['raw html'] == '1') {
		return array();
	}

	$body = str_replace("\r", '', $body);
	$body = utf8tohtml($body);

	if (mysql_version() < 50503) {
		$body = mb_encode_numericentity($body, array(0x010000, 0xffffff, 0, 0xffffff), 'UTF-8');
	}

	foreach (Vi::$config['markup'] as $markup) {
		if (is_string($markup[1])) {
			$body = preg_replace($markup[0], $markup[1], $body);
		} elseif (is_callable($markup[1])) {
			$body = preg_replace_callback($markup[0], $markup[1], $body);
		}
	}

	if (Vi::$config['markup_urls']) {
		Vi::$markup_urls = array();

		$body = preg_replace_callback(
			'/((?:https?:\/\/|ftp:\/\/|irc:\/\/)[^\s<>()"]+?(?:\([^\s<>()"]*?\)[^\s<>()"]*?)*)((?:\s|<|>|"|\.||\]|!|\?|,|&#44;|&quot;)*(?:[\s<>()"]|$))/',
			'markup_url',
			$body,
			-1,
			$num_links);

		if ($num_links > Vi::$config['max_links']) {
			error(sprintf(Vi::$config['error']['toomanylinks'], $num_links, Vi::$config['max_links']));
		}

		if ($num_links < Vi::$config['min_links_op'] && $op) {
			error(sprintf(
				Vi::$config['error']['op_requiredatleast'],
				sprintf(ngettext('1 link', '%d links', Vi::$config['min_links_op']), Vi::$config['min_links_op'])
			));
		}
	}

	if (Vi::$config['markup_repair_tidy']) {
		$body = str_replace('  ', ' &nbsp;', $body);
	}

	if (Vi::$config['auto_unicode']) {
		$body = unicodify($body);

		if (Vi::$config['markup_urls']) {
			foreach (Vi::$markup_urls as &$url) {
				$body = str_replace(unicodify($url), $url, $body);
			}
		}
	}

	$tracked_cites = array();

	// Cites
	if (Vi::$board && preg_match_all('/(^|\s)&gt;&gt;(\d+?)([\s,.)?]|$)/m', $body, $cites, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
		if (count($cites[0]) > Vi::$config['max_cites']) {
			error(Vi::$config['error']['toomanycites']);
		}

		$skip_chars = 0;
		$body_tmp   = $body;

		$search_cites = array();
		foreach ($cites as $matches) {
			$search_cites[] = (int) $matches[2][0];
		}
		$search_cites = array_unique($search_cites);

		$query = query(sprintf('SELECT `thread`, `id` FROM ``posts_%s`` WHERE `id` IN (' . implode(',', $search_cites) . ')', Vi::$board['uri'])) or error(db_error());

		$cited_posts = array();
		while ($cited = $query->fetch(PDO::FETCH_ASSOC)) {
			$cited_posts[$cited['id']] = $cited['thread'] ? $cited['thread'] : false;
		}

		foreach ($cites as $matches) {
			$cite = $matches[2][0];

			// preg_match_all is not multibyte-safe
			foreach ($matches as &$match) {
				$match[1] = mb_strlen(substr($body_tmp, 0, $match[1]));
			}

			if (isset($cited_posts[$cite])) {
				$replacement = '<a onclick="highlightReply(\'' . $cite . '\', event);" href="' .
				Vi::$config['root'] . Vi::$board['dir'] . Vi::$config['dir']['res'] .
					($cited_posts[$cite] ? $cited_posts[$cite] : $cite) . '.html#' . $cite . '">' .
					'&gt;&gt;' . $cite .
					'</a>';

				$body = mb_substr_replace($body, $matches[1][0] . $replacement . $matches[3][0], $matches[0][1] + $skip_chars, mb_strlen($matches[0][0]));
				$skip_chars += mb_strlen($matches[1][0] . $replacement . $matches[3][0]) - mb_strlen($matches[0][0]);

				if ($track_cites && Vi::$config['track_cites']) {
					$tracked_cites[] = array(Vi::$board['uri'], $cite);
				}

			}
		}
	}

	// Cross-board linking
	if (preg_match_all('/(^|\s)&gt;&gt;&gt;\/(' . Vi::$config['board_regex'] . 'f?)\/(\d+)?([\s,.)?]|$)/um', $body, $cites, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
		if (count($cites[0]) > Vi::$config['max_cites']) {
			error(Vi::$config['error']['toomanycross']);
		}

		$skip_chars = 0;
		$body_tmp   = $body;

		if (isset($cited_posts)) {
			// Carry found posts from local board >>X links
			foreach ($cited_posts as $cite => $thread) {
				$cited_posts[$cite] = Vi::$config['root'] . Vi::$board['dir'] . Vi::$config['dir']['res'] .
					($thread ? $thread : $cite) . '.html#' . $cite;
			}

			$cited_posts = array(
				Vi::$board['uri'] => $cited_posts,
			);
		} else {
			$cited_posts = array();
		}

		$crossboard_indexes  = array();
		$search_cites_boards = array();

		foreach ($cites as $matches) {
			$_board = $matches[2][0];
			$cite   = @$matches[3][0];

			if (!isset($search_cites_boards[$_board])) {
				$search_cites_boards[$_board] = array();
			}

			$search_cites_boards[$_board][] = $cite;
		}

		$tmp_board = Vi::$board['uri'];

		foreach ($search_cites_boards as $_board => $search_cites) {
			$clauses = array();
			foreach ($search_cites as $cite) {
				if (!$cite || isset($cited_posts[$_board][$cite])) {
					continue;
				}

				$clauses[] = (int) $cite;
			}
			$clauses = array_unique($clauses);

			if (Vi::$board['uri'] != $_board) {
				if (!openBoard($_board)) {
					continue;
				}
				// Unknown board
			}

			if (!empty($clauses)) {
				$cited_posts[$_board] = array();

				$query = query(sprintf('SELECT `thread`, `id` FROM ``posts_%s`` WHERE `id` IN (' . implode(',', $clauses) . ')', Vi::$board['uri'])) or error(db_error());

				while ($cite = $query->fetch(PDO::FETCH_ASSOC)) {
					$cited_posts[$_board][$cite['id']] = Vi::$config['root'] . Vi::$board['dir'] . Vi::$config['dir']['res'] .
						($cite['thread'] ? $cite['thread'] : $cite['id']) . '.html#' . $cite['id'];
				}
			}

			$crossboard_indexes[$_board] = Vi::$config['root'] . Vi::$board['dir'] . Vi::$config['file_index'];
		}

		// Restore old board
		if (!$tmp_board) {
			Vi::$board = false;
		} elseif (Vi::$board['uri'] != $tmp_board) {
			openBoard($tmp_board);
		}

		foreach ($cites as $matches) {
			$_board = $matches[2][0];
			$cite   = @$matches[3][0];

			// preg_match_all is not multibyte-safe
			foreach ($matches as &$match) {
				$match[1] = mb_strlen(substr($body_tmp, 0, $match[1]));
			}

			if ($cite) {
				if (isset($cited_posts[$_board][$cite])) {
					$link = $cited_posts[$_board][$cite];

					$replacement = '<a ' .
						($_board == Vi::$board['uri'] ?
						'onclick="highlightReply(\'' . $cite . '\', event);" '
						: '') . 'href="' . $link . '">' .
						'&gt;&gt;&gt;/' . $_board . '/' . $cite .
						'</a>';

					$body = mb_substr_replace($body, $matches[1][0] . $replacement . $matches[4][0], $matches[0][1] + $skip_chars, mb_strlen($matches[0][0]));
					$skip_chars += mb_strlen($matches[1][0] . $replacement . $matches[4][0]) - mb_strlen($matches[0][0]);

					if ($track_cites && Vi::$config['track_cites']) {
						$tracked_cites[] = array($_board, $cite);
					}

				}
			} elseif (isset($crossboard_indexes[$_board])) {
				$replacement = '<a href="' . $crossboard_indexes[$_board] . '">' .
					'&gt;&gt;&gt;/' . $_board . '/' .
					'</a>';
				$body = mb_substr_replace($body, $matches[1][0] . $replacement . $matches[4][0], $matches[0][1] + $skip_chars, mb_strlen($matches[0][0]));
				$skip_chars += mb_strlen($matches[1][0] . $replacement . $matches[4][0]) - mb_strlen($matches[0][0]);
			}
		}
	}

	$tracked_cites = array_unique($tracked_cites, SORT_REGULAR);

	if (Vi::$config['strip_superfluous_returns']) {
		$body = preg_replace('/\s+$/', '', $body);
	}

	if (Vi::$config['markup_paragraphs']) {
		$paragraphs = explode("\n", $body);
		$bodyNew    = "";
		$tagsOpen   = false;

		// Matches <a>, <a href="" title="">, but not <img/> and returns a
		$matchOpen = "#<([A-Z][A-Z0-9]*)+(?:(?:\s+\w+(?:\s*=\s*(?:\".*?\"|'.*?'|[^'\">\s]+))?)+\s*|\s*)>#i";
		// Matches </a> returns a
		$matchClose = "#</([A-Z][A-Z0-9]*/?)>#i";
		$tagsOpened = array();
		$tagsClosed = array();

		foreach ($paragraphs as $paragraph) {

			// Determine if RTL based on content of line.
			if (strlen(trim($paragraph)) > 0) {
				$paragraphDirection = is_rtl($paragraph) ? "rtl" : "ltr";
			} else {
				$paragraphDirection = "empty";
			}

			// Add in a quote class for >quotes.
			if (strpos($paragraph, "&gt;") === 0) {
				$quoteClass = "quote";
			} else {
				$quoteClass = "";
			}

			// If tags are closed, start a new line.
			if ($tagsOpen === false) {
				$bodyNew .= "<p class=\"body-line {$paragraphDirection} {$quoteClass}\">";
			}

			// If tags are open, add the paragraph to our temporary holder instead.
			if ($tagsOpen !== false) {
				$tagsOpen .= $paragraph;

				// Recheck tags to see if we've formed a complete tag with this latest line.
				if (preg_match_all($matchOpen, $tagsOpen, $tagsOpened) === preg_match_all($matchClose, $tagsOpen, $tagsClosed)) {
					sort($tagsOpened[1]);
					sort($tagsClosed[1]);

					// Double-check to make sure these are the same tags.
					if (count(array_diff_assoc($tagsOpened[1], $tagsClosed[1])) === 0) {
						// Tags are closed! \o/
						$bodyNew .= $tagsOpen;
						$tagsOpen = false;
					}
				}
			}
			// If tags are closed, check to see if they are now open.
			// This counts the number of open tags (that are not self-closing) against the number of complete tags.
			// If they match completely, we are closed.
			else if (preg_match_all($matchOpen, $paragraph, $tagsOpened) === preg_match_all($matchClose, $paragraph, $tagsClosed)) {
				sort($tagsOpened[1]);
				sort($tagsClosed[1]);

				// Double-check to make sure these are the same tags.
				if (count(array_diff_assoc($tagsOpened[1], $tagsClosed[1])) === 0) {
					$bodyNew .= $paragraph;
				}
			} else {
				// Tags are open!
				$tagsOpen = $paragraph;
			}

			// If tags are open, do not close it.
			if (!$tagsOpen) {
				$bodyNew .= "</p>";
			} else if ($tagsOpen !== false) {
				$tagsOpen .= "<br />";
			}
		}

		if ($tagsOpen !== false) {
			$bodyNew .= $tagsOpen;
		}

		$body = $bodyNew;
	} else {
		$body = preg_replace("/^\s*&gt;.*$/m", '<span class="quote">$0</span>', $body);
		$body = preg_replace("/\n/", '<br/>', $body);
	}

	if (Vi::$config['markup_repair_tidy']) {
		$tidy = new tidy();
		$body = str_replace("\t", '&#09;', $body);
		$body = $tidy->repairString($body, array(
			'doctype'            => 'omit',
			'bare'               => true,
			'literal-attributes' => true,
			'indent'             => false,
			'show-body-only'     => true,
			'wrap'               => 0,
			'output-bom'         => false,
			'output-html'        => true,
			'newline'            => 'LF',
			'quiet'              => true,
		), 'utf8');
		$body = str_replace("\n", '', $body);
	}

	// replace tabs with 8 spaces
	$body = str_replace("\t", '&#09;', $body);

	return $tracked_cites;
}

function escape_markup_modifiers($string) {
	return preg_replace('@<(tinyboard) ([\w\s]+)>@mi', '<$1 escape $2>', $string);
}

function utf8tohtml($utf8) {
	return htmlspecialchars($utf8, ENT_NOQUOTES, 'UTF-8');
}

function ordutf8($string, &$offset) {
	$code = ord(substr($string, $offset, 1));
	if ($code >= 128) {
		// otherwise 0xxxxxxx
		if ($code < 224) {
			$bytesnumber = 2;
		}
		// 110xxxxx
		else if ($code < 240) {
			$bytesnumber = 3;
		}
		// 1110xxxx
		else if ($code < 248) {
			$bytesnumber = 4;
		}
		// 11110xxx
		$codetemp = $code - 192 - ($bytesnumber > 2 ? 32 : 0) - ($bytesnumber > 3 ? 16 : 0);
		for ($i = 2; $i <= $bytesnumber; $i++) {
			$offset++;
			$code2    = ord(substr($string, $offset, 1)) - 128; //10xxxxxx
			$codetemp = $codetemp * 64 + $code2;
		}
		$code = $codetemp;
	}
	$offset += 1;
	if ($offset >= strlen($string)) {
		$offset = -1;
	}

	return $code;
}

function uniord($u) {
	$k  = mb_convert_encoding($u, 'UCS-2LE', 'UTF-8');
	$k1 = ord(substr($k, 0, 1));
	$k2 = ord(substr($k, 1, 1));
	return $k2 * 256 + $k1;
}

function is_rtl($str) {
	if (mb_detect_encoding($str) !== 'UTF-8') {
		$str = mb_convert_encoding($str, mb_detect_encoding($str), 'UTF-8');
	}

	preg_match_all('/[^\n\s]+/', $str, $matches);
	preg_match_all('/.|\n\s/u', $str, $matches);
	$chars        = $matches[0];
	$arabic_count = 0;
	$latin_count  = 0;
	$total_count  = 0;

	foreach ($chars as $char) {
		$pos = uniord($char);

		if ($pos >= 1536 && $pos <= 1791) {
			$arabic_count++;
		} else if ($pos > 123 && $pos < 123) {
			$latin_count++;
		}
		$total_count++;
	}

	return (($arabic_count / $total_count) > 0.5);
}

function strip_combining_chars($str) {
	$chars = preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY);
	$str   = '';
	foreach ($chars as $char) {
		$o   = 0;
		$ord = ordutf8($char, $o);

		if (($ord >= 768 && $ord <= 879) || ($ord >= 1536 && $ord <= 1791) || ($ord >= 3655 && $ord <= 3659) || ($ord >= 7616 && $ord <= 7679) || ($ord >= 8400 && $ord <= 8447) || ($ord >= 65056 && $ord <= 65071)) {
			continue;
		}

		$str .= $char;
	}
	return $str;
}

function buildThread($id, $return = false, $mod = false) {
	$id = round($id);

	if (event('build-thread', $id)) {
		return;
	}

	if (Vi::$config['cache']['enabled'] && !$mod) {
		// Clear cache
		cache::delete("thread_index_" . Vi::$board['uri'] . "_{$id}");
		cache::delete("thread_" . Vi::$board['uri'] . "_{$id}");
	}

	if (Vi::$config['try_smarter'] && !$mod) {
		Vi::$build_pages[] = thread_find_page($id);
	}

	$action = generation_strategy('sb_thread', array(Vi::$board['uri'], $id));

	if ($action == 'rebuild' || $return || $mod) {
		$query = prepare(sprintf("SELECT * FROM ``posts_%s`` WHERE (`thread` IS NULL AND `id` = :id) OR `thread` = :id ORDER BY `thread`,`id`", Vi::$board['uri']));
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
			if (!isset($thread)) {
				$thread = new Thread($post, $mod ? '?/' : Vi::$config['root'], $mod);
			} else {
				$thread->add(new Post($post, $mod ? '?/' : Vi::$config['root'], $mod));
			}
		}

		// Check if any posts were found
		if (!isset($thread)) {
			error(Vi::$config['error']['nonexistant']);
		}

		$hasnoko50 = $thread->postCount() >= Vi::$config['noko50_min'];
		$antibot   = $mod || $return ? false : create_antibot(Vi::$board['uri'], $id);

		$body = Element('thread.html', array(
			'board'     => Vi::$board,
			'thread'    => $thread,
			'body'      => $thread->build(),
			'config'    => Vi::$config,
			'id'        => $id,
			'mod'       => $mod,
			'hasnoko50' => $hasnoko50,
			'isnoko50'  => false,
			'antibot'   => $antibot,
			'boardlist' => createBoardlist($mod),
			'return'    => ($mod ? '?' . Vi::$board['url'] . Vi::$config['file_index'] : Vi::$config['root'] . Vi::$board['dir'] . Vi::$config['file_index']),
		));

		// json api
		if (Vi::$config['api']['enabled'] && !$mod) {
			$api          = new Api();
			$json         = json_encode($api->translateThread($thread));
			$jsonFilename = Vi::$board['dir'] . Vi::$config['dir']['res'] . $id . '.json';
			file_write($jsonFilename, $json);
		}
	} elseif ($action == 'delete') {
		$jsonFilename = Vi::$board['dir'] . Vi::$config['dir']['res'] . $id . '.json';
		file_unlink($jsonFilename);
	}

	if ($action == 'delete' && !$return && !$mod) {
		$noko50fn = Vi::$board['dir'] . Vi::$config['dir']['res'] . sprintf(Vi::$config['file_page50'], $id);
		file_unlink($noko50fn);

		file_unlink(Vi::$board['dir'] . Vi::$config['dir']['res'] . sprintf(Vi::$config['file_page'], $id));
	} elseif ($return) {
		return $body;
	} elseif ($action == 'rebuild') {
		$noko50fn = Vi::$board['dir'] . Vi::$config['dir']['res'] . sprintf(Vi::$config['file_page50'], $id);
		if ($hasnoko50 || file_exists($noko50fn)) {
			buildThread50($id, $return, $mod, $thread, $antibot);
		}

		file_write(Vi::$board['dir'] . Vi::$config['dir']['res'] . sprintf(Vi::$config['file_page'], $id), $body);
	}
}

function buildThread50($id, $return = false, $mod = false, $thread = null, $antibot = false) {
	$id = round($id);

	if ($antibot) {
		$antibot->reset();
	}

	if (!$thread) {
		$query = prepare(sprintf("SELECT * FROM ``posts_%s`` WHERE (`thread` IS NULL AND `id` = :id) OR `thread` = :id ORDER BY `thread`,`id` DESC LIMIT :limit", Vi::$board['uri']));
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->bindValue(':limit', Vi::$config['noko50_count'] + 1, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		$num_images = 0;
		while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
			if (!isset($thread)) {
				$thread = new Thread($post, $mod ? '?/' : Vi::$config['root'], $mod);
			} else {
				if ($post['files']) {
					$num_images += $post['num_files'];
				}

				$thread->add(new Post($post, $mod ? '?/' : Vi::$config['root'], $mod));
			}
		}

		// Check if any posts were found
		if (!isset($thread)) {
			error(Vi::$config['error']['nonexistant']);
		}

		if ($query->rowCount() == Vi::$config['noko50_count'] + 1) {
			$count = prepare(sprintf("SELECT COUNT(`id`) as `num` FROM ``posts_%s`` WHERE `thread` = :thread UNION ALL
						  SELECT SUM(`num_files`) FROM ``posts_%s`` WHERE `files` IS NOT NULL AND `thread` = :thread", Vi::$board['uri'], Vi::$board['uri']));
			$count->bindValue(':thread', $id, PDO::PARAM_INT);
			$count->execute() or error(db_error($count));

			$c               = $count->fetch();
			$thread->omitted = $c['num'] - Vi::$config['noko50_count'];

			$c                      = $count->fetch();
			$thread->omitted_images = $c['num'] - $num_images;
		}

		$thread->posts = array_reverse($thread->posts);
	} else {
		$allPosts = $thread->posts;

		$thread->posts = array_slice($allPosts, -Vi::$config['noko50_count']);
		$thread->omitted += count($allPosts) - count($thread->posts);
		foreach ($allPosts as $index => $post) {
			if ($index == count($allPosts) - count($thread->posts)) {
				break;
			}

			if ($post->files) {
				$thread->omitted_images += $post->num_files;
			}

		}
	}

	$hasnoko50 = $thread->postCount() >= Vi::$config['noko50_min'];

	$body = Element('thread.html', array(
		'board'     => Vi::$board,
		'thread'    => $thread,
		'body'      => $thread->build(false, true),
		'config'    => Vi::$config,
		'id'        => $id,
		'mod'       => $mod,
		'hasnoko50' => $hasnoko50,
		'isnoko50'  => true,
		'antibot'   => $mod ? false : ($antibot ? $antibot : create_antibot(Vi::$board['uri'], $id)),
		'boardlist' => createBoardlist($mod),
		'return'    => ($mod ? '?' . Vi::$board['url'] . Vi::$config['file_index'] : Vi::$config['root'] . Vi::$board['dir'] . Vi::$config['file_index']),
	));

	if ($return) {
		return $body;
	} else {
		file_write(Vi::$board['dir'] . Vi::$config['dir']['res'] . sprintf(Vi::$config['file_page50'], $id), $body);
	}
}

function rrmdir($dir) {
	if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object) {
			if ($object != "." && $object != "..") {
				if (filetype($dir . "/" . $object) == "dir") {
					rrmdir($dir . "/" . $object);
				} else {
					file_unlink($dir . "/" . $object);
				}

			}
		}
		reset($objects);
		rmdir($dir);
	}
}

function poster_id($ip, $thread, $board) {
	if ($id = event('poster-id', $ip, $thread, $board)) {
		return $id;
	}

	// Confusing, hard to brute-force, but simple algorithm
	return substr(sha1(sha1($ip . Vi::$config['secure_trip_salt'] . $thread . $board) . Vi::$config['secure_trip_salt']), 0, Vi::$config['poster_id_length']);
}

function generate_tripcode($name) {
	if ($trip = event('tripcode', $name)) {
		return $trip;
	}

	if (!preg_match('/^([^#]+)?(##|#)(.+)$/', $name, $match)) {
		return array($name);
	}

	$name   = $match[1];
	$secure = $match[2] == '##';
	$trip   = $match[3];

	// convert to SHIT_JIS encoding
	$trip = mb_convert_encoding($trip, 'Shift_JIS', 'UTF-8');

	// generate salt
	$salt = substr($trip . 'H..', 1, 2);
	$salt = preg_replace('/[^.-z]/', '.', $salt);
	$salt = strtr($salt, ':;<=>?@[\]^_`', 'ABCDEFGabcdef');

	if ($secure) {
		if (isset(Vi::$config['custom_tripcode']["##{$trip}"])) {
			$trip = Vi::$config['custom_tripcode']["##{$trip}"];
		} else {
			$trip = '!!' . substr(crypt($trip, str_replace('+', '.', '_..A.' . substr(base64_encode(sha1($trip . Vi::$config['secure_trip_salt'], true)), 0, 4))), -10);
		}

	} else {
		if (isset(Vi::$config['custom_tripcode']["#{$trip}"])) {
			$trip = Vi::$config['custom_tripcode']["#{$trip}"];
		} else {
			$trip = '!' . substr(crypt($trip, $salt), -10);
		}

	}

	return array($name, $trip);
}

// Highest common factor
function hcf($a, $b) {
	$gcd = 1;
	if ($a > $b) {
		$a = $a + $b;
		$b = $a - $b;
		$a = $a - $b;
	}
	if ($b == (round($b / $a)) * $a) {
		$gcd = $a;
	} else {
		for ($i = round($a / 2); $i; $i--) {
			if ($a == round($a / $i) * $i && $b == round($b / $i) * $i) {
				$gcd = $i;
				$i   = false;
			}
		}
	}
	return $gcd;
}

function fraction($numerator, $denominator, $sep) {
	$gcf         = hcf($numerator, $denominator);
	$numerator   = $numerator / $gcf;
	$denominator = $denominator / $gcf;

	return "{$numerator}{$sep}{$denominator}";
}

function getPostByHash($hash) {
	$query = prepare(sprintf("SELECT `id`,`thread` FROM ``posts_%s`` WHERE `filehash` = :hash", Vi::$board['uri']));
	$query->bindValue(':hash', $hash, PDO::PARAM_STR);
	$query->execute() or error(db_error($query));

	if ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		return $post;
	}

	return false;
}

function getPostByHashInThread($hash, $thread) {
	$query = prepare(sprintf("SELECT `id`,`thread` FROM ``posts_%s`` WHERE `filehash` = :hash AND ( `thread` = :thread OR `id` = :thread )", Vi::$board['uri']));
	$query->bindValue(':hash', $hash, PDO::PARAM_STR);
	$query->bindValue(':thread', $thread, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		return $post;
	}

	return false;
}

function getPostByEmbed($embed) {
	$matches = array();
	foreach (Vi::$config['embedding'] as &$e) {
		if (preg_match($e[0], $embed, $matches) && isset($matches[1]) && !empty($matches[1])) {
			$embed = '%' . $matches[1] . '%';
			break;
		}
	}

	if (!isset($embed)) {
		return false;
	}

	$query = prepare(sprintf("SELECT `id`,`thread` FROM ``posts_%s`` WHERE `embed` LIKE :embed", Vi::$board['uri']));
	$query->bindValue(':embed', $embed, PDO::PARAM_STR);
	$query->execute() or error(db_error($query));

	if ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		return $post;
	}

	return false;
}

function getPostByEmbedInThread($embed, $thread) {
	$matches = array();
	foreach (Vi::$config['embedding'] as &$e) {
		if (preg_match($e[0], $embed, $matches) && isset($matches[1]) && !empty($matches[1])) {
			$embed = '%' . $matches[1] . '%';
			break;
		}
	}

	if (!isset($embed)) {
		return false;
	}

	$query = prepare(sprintf("SELECT `id`,`thread` FROM ``posts_%s`` WHERE `embed` = :embed AND ( `thread` = :thread OR `id` = :thread )", Vi::$board['uri']));
	$query->bindValue(':embed', $embed, PDO::PARAM_STR);
	$query->bindValue(':thread', $thread, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		return $post;
	}

	return false;
}

function undoImage(array $post) {
	if (!$post['has_file'] || !isset($post['files'])) {
		return;
	}

	foreach ($post['files'] as $key => $file) {
		if (isset($file['file_path'])) {
			file_unlink($file['file_path']);
		}

		if (isset($file['thumb_path'])) {
			file_unlink($file['thumb_path']);
		}

	}
}

function rDNS($ip_addr) {
	if(isTorIp($ip_addr)) {
		return $ip_addr;
	}
	if (Vi::$config['cache']['enabled'] && ($host = cache::get('rdns_' . $ip_addr))) {
		return $host;
	}

	if (!Vi::$config['dns_system']) {
		$host = gethostbyaddr($ip_addr);
	} else {
		$resp = shell_exec_error('host -W 1 ' . $ip_addr);
		if (preg_match('/domain name pointer ([^\s]+)$/', $resp, $m)) {
			$host = $m[1];
		} else {
			$host = $ip_addr;
		}

	}

	$isip = filter_var($host, FILTER_VALIDATE_IP);

	if (Vi::$config['fcrdns'] && !$isip && DNS($host) != $ip_addr) {
		$host = $ip_addr;
	}

	if (Vi::$config['cache']['enabled']) {
		cache::set('rdns_' . $ip_addr, $host);
	}

	return $host;
}

function DNS($host) {
	if (Vi::$config['cache']['enabled'] && ($ip_addr = cache::get('dns_' . $host))) {
		return $ip_addr != '?' ? $ip_addr : false;
	}

	if (!Vi::$config['dns_system']) {
		$ip_addr = gethostbyname($host);
		if ($ip_addr == $host) {
			$ip_addr = false;
		}

	} else {
		$resp = shell_exec_error('host -W 1 ' . $host);
		if (preg_match('/has address ([^\s]+)$/', $resp, $m)) {
			$ip_addr = $m[1];
		} else {
			$ip_addr = false;
		}

	}

	if (Vi::$config['cache']['enabled']) {
		cache::set('dns_' . $host, $ip_addr !== false ? $ip_addr : '?');
	}

	return $ip_addr;
}

function shell_exec_error($command, $suppress_stdout = false) {
	if (Vi::$config['debug']) {
		$start = microtime(true);
	}

	$return = trim(shell_exec('PATH="' . escapeshellcmd(Vi::$config['shell_path']) . ':$PATH";' .
		$command . ' 2>&1 ' . ($suppress_stdout ? '> /dev/null ' : '') . '&& echo "TB_SUCCESS"'));
	$return = preg_replace('/TB_SUCCESS$/', '', $return);

	if (Vi::$config['debug']) {
		$time = microtime(true) - $start;

		Vi::$debug['exec'][] = array(
			'command'  => $command,
			'time'     => '~' . round($time * 1000, 2) . 'ms',
			'response' => $return ? $return : null,
		);
		Vi::$debug['time']['exec'] += $time;
	}

	return $return === 'TB_SUCCESS' ? false : $return;
}

/* Die rolling:
 * If "##XdY##" is in the message field.
 * X Y-sided dice are rolled and summed.
 * The result is displayed at the the post.
 */
function diceRoller($post) {
	$post->body = preg_replace_callback('/\#\#(\d+)d(\d+)\#\#/', function ($input) {
		$diceX = intval($input[1]);
		$diceY = intval($input[2]);

		if (($diceX > 0 && $diceX <= 20) && ($diceY > 0 && $diceY <= 100)) {
			$dicerolls = array();
			$dicesum   = 0;
			for ($i = 0; $i < $diceX; $i++) {
				$roll        = mt_rand(1, $diceY);
				$dicerolls[] = $roll;
				$dicesum += $roll;
			}
			return '<span class="dice">' . $diceX . 'd' . $diceY . ': (' . implode(' + ', $dicerolls) . ') = ' . $dicesum . '</span>';
		}

		return $input[0];

	}, $post->body, Vi::$config['max_dice_rolls_on_post']);
}

function less_ip($ip, $board = '') {
	if (strstr($ip, '/') !== false) {
		$ip_a  = explode('/', $ip);
		$ip    = $ip_a[0];
		$range = $ip_a[1];
	}

	if(!isTorIp($ip)) {
		$in_addr = inet_pton($ip);

		if (isIPv6($ip)) {
			// Not sure how many to mask for IPv6, opinions?
			$mask = inet_pton('ffff:ffff:ffff:ffff:ffff:0:0:0');
		} else {
			$mask = inet_pton('255.255.0.0');
		}

		$final  = inet_ntop($in_addr & $mask);
		$masked = str_replace(array(':0', '.0'), array(':x', '.x'), $final);
	}
	else {
		$masked = $ip;
	}

	if (Vi::$config['hash_masked_ip']) {
		$masked = substr(sha1(sha1($masked . $board) . Vi::$config['secure_trip_salt']), 0, 10);
	}

	$masked .= (isset($range) ? '/' . $range : '');

	return $masked;
}

function less_hostmask($hostmask) {
	$parts = explode('.', $hostmask);

	if (sizeof($parts) < 3) {
		return $hostmask;
	}

	$parts[0] = 'x';
	$parts[1] = 'x';

	return implode('.', $parts);
}

function prettify_textarea($s) {
	return str_replace("\t", '&#09;', str_replace("\n", '&#13;&#10;', htmlentities($s)));
}

class HTMLPurifier_URIFilter_NoExternalImages extends HTMLPurifier_URIFilter {
	public $name = 'NoExternalImages';
	public function filter(&$uri, $c, $context) {
		$ct = $context->get('CurrentToken');

		if (!$ct || $ct->name !== 'img') {
			return true;
		}

		if (!isset($uri->host) && !isset($uri->scheme)) {
			return true;
		}

		if (!in_array($uri->scheme . '://' . $uri->host . '/', Vi::$config['allowed_offsite_urls'])) {
			error('No off-site links in board announcement images.');
		}

		return true;
	}
}

function purify_html($s) {
	$c = HTMLPurifier_Config::createDefault();
	$c->set('HTML.Allowed', Vi::$config['allowed_html']);
	$uri = $c->getDefinition('URI');
	$uri->addFilter(new HTMLPurifier_URIFilter_NoExternalImages(), $c);
	$purifier   = new HTMLPurifier($c);
	$clean_html = $purifier->purify($s);
	return $clean_html;
}

function markdown($s) {
	$pd = new Parsedown();
	$pd->setMarkupEscaped(true);
	$pd->setImagesEnabled(false);

	return $pd->text($s);
}

function generation_strategy($fun, $array = array()) {
	$action = false;

	foreach (Vi::$config['generation_strategies'] as $s) {
		if ($action = $s($fun, $array)) {
			break;
		}
	}

	switch ($action[0]) {
	case 'immediate' :
		return 'rebuild';
	case 'defer':
		// Ok, it gets interesting here :)
		get_queue('generate')->push(serialize(array('build', $fun, $array, $action)));
		return 'ignore';
	case 'build_on_load':
		return 'delete';
	}
}

function strategy_immediate($fun, $array) {
	return array('immediate');
}

function strategy_smart_build($fun, $array) {
	return array('build_on_load');
}

function strategy_sane($fun, $array) {
	if (php_sapi_name() == 'cli') {
		return false;
	} else if (isset($_POST['mod'])) {
		return false;
	}

	// Thread needs to be done instantly. Same with a board page, but only if posting a new thread.
	else if ($fun == 'sb_thread' || ($fun == 'sb_board' && $array[1] == 1 && isset($_POST['page']))) {
		return array('immediate');
	} else {
		return false;
	}

}

// My first, test strategy.
function strategy_first($fun, $array) {
	switch ($fun) {
	case 'sb_thread':
		return array('defer');
	case 'sb_board':
		if ($array[1] > 8) {
			return array('build_on_load');
		} else {
			return array('defer');
		}

	case 'sb_api':
		return array('defer');
	case 'sb_catalog':
		return array('defer');
	case 'sb_recent':
		return array('build_on_load');
	case 'sb_sitemap':
		return array('build_on_load');
	case 'sb_ukko':
		return array('defer');
	}
}

function ip_link($ip, $href = true) {
	return "<a id=\"ip\"" . ($href ? " href=\"?/IP/$ip\"" : "") . ">$ip</a>";
}

function format_bytes($size) {
	$units = array(_('B'), _('KB'), _('MB'), _('GB'), _('TB'));
	for ($i = 0; $size >= 1024 && $i < 4; $i++) {
		$size /= 1024;
	}

	return round($size, 2) . $units[$i];
}

function languageName($locale, $extended=false) {
/*
	возвращает массив с именем языка
	в "short" - короткое название, в "full" - полное (если прописано в конфиге Vi::$config['languages'])
	если $extended == true, к полному названию добаляется двухбуквенный код-расширение

	languageName('en_US') == ('short' => 'en', 'full' => 'English')
	languageName('en_US', true) == ('short' => 'en', 'full' => 'English (US)')

	если названия нет в конфиге:
		languageName('en_US') == ('short' => 'en', 'full' => 'en')
		languageName('en_US', true) == ('short' => 'en', 'full' => 'en (US)')
*/
	$ret = array(
		"short" => $locale,
		"full" => $locale
	);
	preg_match("/^([^\W_]+)(?:_([^\W_]+))?/", $locale, $matches);
	if(!count($matches))
		return $ret;

	$ret['short'] = $ret['full'] = strtolower($matches[1]);
	if(isset(Vi::$config['languages'][$ret['short']]))
		$ret['full'] = Vi::$config['languages'][$ret['short']];
	if($extended && count($matches) > 2)
		$ret['full'] .= " ({$matches[2]})";

	return $ret;
}

function _var_dump($var, $html = false) {
	// debug: return result of var_dump() function
	// if $html is true the result is wrapped with <pre> tag (for echo)

	ob_start();
	var_dump($var);
	$ret = ob_get_clean();
	$ret = preg_replace('/=>[\s\n]+/', ' => ', $ret); // remove \n after =>
	$ret = preg_replace('/{[\s\n]+}/', '{}', $ret); // don't open empty arrays

	return $html ? "<pre>$ret</pre>" : $ret;
}
