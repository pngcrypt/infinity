<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

defined('TINYBOARD') or exit;

// create a hash/salt pair for validate logins
function mkhash($username, $password, $salt = false) {
	if (!$salt) {
		// create some sort of salt for the hash
		$salt = substr(base64_encode(sha1(mt_rand() . time(), true) . Vi::$config['cookies']['salt']), 0, 15);

		$generated_salt = true;
	}

	// generate hash (method is not important as long as it's strong)
	$hash = substr(
		base64_encode(
			md5(
				$username . Vi::$config['cookies']['salt'] . sha1(
					$username . $password . $salt . (
						Vi::$config['mod']['lock_ip'] ? $_SERVER['REMOTE_ADDR'] : ''
					), true
				) . sha1(Vi::$config['password_crypt_version']) // Log out users being logged in with older password encryption schema
				, true
			)
		), 0, 20
	);

	if (isset($generated_salt)) {
		return array($hash, $salt);
	} else {
		return $hash;
	}

}

function crypt_password_old($password) {
	$salt     = generate_salt();
	$password = hash('sha256', $salt . sha1($password));
	return array($salt, $password);
}

function crypt_password($password) {
	// `salt` database field is reused as a version value. We don't want it to be 0.
	$version  = Vi::$config['password_crypt_version'] ? Vi::$config['password_crypt_version'] : 1;
	$new_salt = generate_salt();
	$password = crypt($password, Vi::$config['password_crypt'] . $new_salt . "$");
	return array($version, $password);
}

function test_password($password, $salt, $test) {
	// Version = 0 denotes an old password hashing schema. In the same column, the
	// password hash was kept previously
	$version = (strlen($salt) <= 8) ? (int) $salt : 0;

	if ($version == 0) {
		$comp = hash('sha256', $salt . sha1($test));
	} else {
		$comp = crypt($test, $password);
	}
	return array($version, hash_equals($password, $comp));
}

function generate_salt() {
	// 128 bits of entropy
	return strtr(base64_encode(random_bytes(16)), '+', '.');
}

function login($username, $password) {
	$query = prepare("SELECT `id`, `type`, `boards`, `password`, `version` FROM ``mods`` WHERE BINARY `username` = :username");
	$query->bindValue(':username', $username);
	$query->execute() or error(db_error($query));

	if ($user = $query->fetch(PDO::FETCH_ASSOC)) {
		list($version, $ok) = test_password($user['password'], $user['version'], $password);

		if ($ok) {
			if (Vi::$config['password_crypt_version'] > $version) {
				// It's time to upgrade the password hashing method!
				list($user['version'], $user['password']) = crypt_password($password);
				$query                                    = prepare("UPDATE ``mods`` SET `password` = :password, `version` = :version WHERE `id` = :id");
				$query->bindValue(':password', $user['password']);
				$query->bindValue(':version', $user['version']);
				$query->bindValue(':id', $user['id']);
				$query->execute() or error(db_error($query));
			}

			return Vi::$mod = array(
				'id'       => $user['id'],
				'type'     => $user['type'],
				'username' => $username,
				'hash'     => mkhash($username, $user['password']),
				'boards'   => explode(',', $user['boards']),
			);
		}
	}

	return false;
}

function setCookies() {
	if (!Vi::$mod) {
		error('setCookies() was called for a non-moderator!');
	}

	setcookie(Vi::$config['cookies']['mod'],
		Vi::$mod['username'] . // username
		':' .
		Vi::$mod['hash'][0] . // password
		':' .
		Vi::$mod['hash'][1], // salt
		time() + Vi::$config['cookies']['expire'], Vi::$config['cookies']['jail'] ? Vi::$config['cookies']['path'] : '/', null, !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off', Vi::$config['cookies']['httponly']);
}

function destroyCookies() {
	// Delete the cookies
	setcookie(Vi::$config['cookies']['mod'], 'deleted', time() - Vi::$config['cookies']['expire'], Vi::$config['cookies']['jail'] ? Vi::$config['cookies']['path'] : '/', null, !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off', true);
}

function modLog($action, $_board = null) {
	$query = prepare("INSERT INTO ``modlogs`` VALUES (:id, :ip, :board, :time, :text)");
	$query->bindValue(':id', (isset(Vi::$mod['id']) ? Vi::$mod['id'] : -1), PDO::PARAM_INT);
	$query->bindValue(':ip', GetIp());
	$query->bindValue(':time', time(), PDO::PARAM_INT);
	$query->bindValue(':text', $action);
	if (isset($_board)) {
		$query->bindValue(':board', $_board);
	} elseif (isset(Vi::$board)) {
		$query->bindValue(':board', Vi::$board['uri']);
	} else {
		$query->bindValue(':board', null, PDO::PARAM_NULL);
	}

	$query->execute() or error(db_error($query));

	if (Vi::$config['syslog']) {
		_syslog(LOG_INFO, '[mod/' . Vi::$mod['username'] . ']: ' . $action);
	}
}

function create_pm_header() {
	if (Vi::$config['cache']['enabled'] && ($header = cache::get('pm_unread_' . Vi::$mod['id'])) != false) {
		if ($header === true) {
			return false;
		}

		return $header;
	}

	$query = prepare("SELECT `id` FROM ``pms`` WHERE `to` = :id AND `unread` = 1");
	$query->bindValue(':id', Vi::$mod['id'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if ($pm = $query->fetch(PDO::FETCH_ASSOC)) {
		$header = array('id' => $pm['id'], 'waiting' => $query->rowCount() - 1);
	} else {
		$header = true;
	}

	if (Vi::$config['cache']['enabled']) {
		cache::set('pm_unread_' . Vi::$mod['id'], $header);
	}

	if ($header === true) {
		return false;
	}

	return $header;
}

function make_secure_link_token($uri) {
	return substr(sha1(Vi::$config['cookies']['salt'] . '-' . $uri . '-' . Vi::$mod['id']), 0, 8);
}

function check_login($prompt = false) {
	// Validate session
	if (isset($_COOKIE[Vi::$config['cookies']['mod']])) {
		// Should be username:hash:salt
		$cookie = explode(':', $_COOKIE[Vi::$config['cookies']['mod']]);
		if (count($cookie) != 3) {
			// Malformed cookies
			destroyCookies();
			if ($prompt) {
				mod_login();
			}

			exit;
		}

		$query = prepare("SELECT `id`, `type`, `boards`, `password` FROM ``mods`` WHERE `username` = :username");
		$query->bindValue(':username', $cookie[0]);
		$query->execute() or error(db_error($query));
		$user = $query->fetch(PDO::FETCH_ASSOC);

		// validate password hash
		if ($cookie[1] !== mkhash($cookie[0], $user['password'], $cookie[2])) {
			// Malformed cookies
			destroyCookies();
			if ($prompt) {
				mod_login();
			}

			exit;
		}

		Vi::$mod = array(
			'id'       => $user['id'],
			'type'     => $user['type'],
			'username' => $cookie[0],
			'boards'   => explode(',', $user['boards']),
		);
	}
}
