<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

defined('TINYBOARD') or exit;

class PreparedQueryDebug {
	protected $query, $explain_query = false;

	public function __construct($query) {
		$query = preg_replace("/[\n\t]+/", ' ', $query);

		$this->query = Vi::$pdo->prepare($query);
		if (Vi::$config['debug'] && Vi::$config['debug_explain'] && preg_match('/^(SELECT|INSERT|UPDATE|DELETE) /i', $query)) {
			$this->explain_query = Vi::$pdo->prepare("EXPLAIN $query");
		}
	}

	public function __call($function, $args) {
		if (Vi::$config['debug'] && $function == 'execute') {
			if ($this->explain_query) {
				$this->explain_query->execute() or error(db_error($this->explain_query));
			}
			$start = microtime(true);
		}

		if ($this->explain_query && $function == 'bindValue') {
			call_user_func_array(array($this->explain_query, $function), $args);
		}

		$return = call_user_func_array(array($this->query, $function), $args);

		if (Vi::$config['debug'] && $function == 'execute') {
			$time               = microtime(true) - $start;
			Vi::$debug['sql'][] = array(
				'query'   => $this->query->queryString,
				'rows'    => $this->query->rowCount(),
				'explain' => $this->explain_query ? $this->explain_query->fetchAll(PDO::FETCH_ASSOC) : null,
				'time'    => '~' . round($time * 1000, 2) . 'ms',
			);
			Vi::$debug['time']['db_queries'] += $time;
		}

		return $return;
	}
}

function sql_open() {
	if (Vi::$pdo) {
		return true;
	}

	if (Vi::$config['debug']) {
		$start = microtime(true);
	}

	if (isset(Vi::$config['db']['server'][0]) && Vi::$config['db']['server'][0] == ':') {
		$unix_socket = substr(Vi::$config['db']['server'], 1);
	} else {
		$unix_socket = false;
	}

	$dsn = Vi::$config['db']['type'] . ':' .
		($unix_socket ? 'unix_socket=' . $unix_socket : 'host=' . Vi::$config['db']['server']) .
		';dbname=' . Vi::$config['db']['database'];
	if (!empty(Vi::$config['db']['dsn'])) {
		$dsn .= ';' . Vi::$config['db']['dsn'];
	}

	try {
		$options = array(
			PDO::ATTR_TIMEOUT                  => Vi::$config['db']['timeout'],
			PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
		);
		if (Vi::$config['db']['persistent']) {
			$options[PDO::ATTR_PERSISTENT] = true;
		}

		Vi::$pdo = new PDO($dsn, Vi::$config['db']['user'], Vi::$config['db']['password'], $options);

		if (Vi::$config['debug']) {
			Vi::$debug['time']['db_connect'] = '~' . round((microtime(true) - $start) * 1000, 2) . 'ms';
		}

		if (mysql_version() >= 50503) {
			query('SET NAMES utf8mb4') or error(db_error());
		} else {
			query('SET NAMES utf8') or error(db_error());
		}

		return Vi::$pdo;
	} catch (PDOException $e) {
		$message = $e->getMessage();

		// Remove any sensitive information
		$message = str_replace(Vi::$config['db']['user'], '<em>hidden</em>', $message);
		$message = str_replace(Vi::$config['db']['password'], '<em>hidden</em>', $message);

		// Print error
		if (Vi::$config['mask_db_error']) {
			error(_('Could not connect to the database. Please try again later.'));
		} else {
			error(_('Database error: ') . $message);
		}
	}
}

// 5.6.10 becomes 50610
function mysql_version() {
	$version = Vi::$pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
	preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $v); // MariaDB return like: "5.5.5-10.1.19-MariaDB"
	if (count($v) != 4) {
		return false;
	}

	return (int) sprintf("%02d%02d%02d", $v[1], $v[2], $v[3]);
}

function prepare($query) {
	$query = preg_replace('/``(' . Vi::$config['board_regex'] . ')``/u', '`' . Vi::$config['db']['prefix'] . '$1`', $query);

	sql_open();

	if (Vi::$config['debug']) {
		return new PreparedQueryDebug($query);
	}

	return Vi::$pdo->prepare($query);
}

function query($query) {
	$query = preg_replace('/``(' . Vi::$config['board_regex'] . ')``/u', '`' . Vi::$config['db']['prefix'] . '$1`', $query);

	sql_open();

	if (Vi::$config['debug']) {
		if (Vi::$config['debug_explain'] && preg_match('/^(SELECT|INSERT|UPDATE|DELETE) /i', $query)) {
			$explain = Vi::$pdo->query("EXPLAIN $query") or error(db_error());
		}
		$start = microtime(true);
		$query = Vi::$pdo->query($query);
		if (!$query) {
			return false;
		}

		$time           = microtime(true) - $start;
		Vi::$debug['sql'][] = array(
			'query'   => $query->queryString,
			'rows'    => $query->rowCount(),
			'explain' => isset($explain) ? $explain->fetchAll(PDO::FETCH_ASSOC) : null,
			'time'    => '~' . round($time * 1000, 2) . 'ms',
		);
		Vi::$debug['time']['db_queries'] += $time;
		return $query;
	}

	return Vi::$pdo->query($query);
}

function db_error($PDOStatement = null) {
	global $db_error;

	if (Vi::$config['mask_db_error']) {
		return _('The database returned an error while processing your request. Please try again later.');
	}

	if (isset($PDOStatement)) {
		$db_error = $PDOStatement->errorInfo();
		return $db_error[2];
	}

	$db_error = Vi::$pdo->errorInfo();
	return $db_error[2];
}
