<?php

require 'inc/lib/IP/Lifo/IP/IP.php';
require 'inc/lib/IP/Lifo/IP/BC.php';
require 'inc/lib/IP/Lifo/IP/CIDR.php';

use Lifo\IP\CIDR;

class Bans {
	public static function range_to_string($mask) {
		list($ipstart, $ipend) = $mask;

		if (!isset($ipend) || $ipend === false) {
			// Not a range. Single IP address.
			return isTorIp($ipstart) ? $ipstart : inet_ntop($ipstart);;
		}

		if (strlen($ipstart) != strlen($ipend)) {
			return '???';
		}
		// What the fuck are you doing, son?

		$range = CIDR::range_to_cidr(inet_ntop($ipstart), inet_ntop($ipend));
		if ($range !== false) {
			return $range;
		}

		return '???';
	}

	private static function calc_cidr($mask) {
		$cidr  = new CIDR($mask);
		$range = $cidr->getRange();

		return array(inet_pton($range[0]), inet_pton($range[1]));
	}

	public static function parse_time($str) {
		if (empty($str)) {
			return false;
		}

		if (($time = @strtotime($str)) !== false) {
			return $time;
		}

		if(is_numeric($str))
			return time() + $str; // simple number == seconds

		/* 
			examples:
			10s == 10 second
			1y20s == 1 year & 20 seconds
			3w4d == 3 weeks & 4 days
		*/

		if (!preg_match('/^'
			.'(?:(\d+)\s*ye?a?r?s?)?\s*'		// years   (#1): min-syntax: 1y
			.'(?:(\d+)\s*mon?t?h?s?)?\s*'		// months  (#2): min-syntax: 1mo
			.'(?:(\d+)\s*we?e?k?s?)?\s*'		// weeks   (#3): min-syntax: 1w
			.'(?:(\d+)\s*da?y?s?)?\s*'			// days    (#4): min-syntax: 1d
			.'(?:(\d+)\s*ho?u?r?s?)?\s*'		// hours   (#5): min-syntax: 1h
			.'(?:(\d+)\s*mi?n?u?t?e?s?)?\s*'	// minutes (#6): min-syntax: 1m
			.'(?:(\d+)\s*se?c?o?n?d?s?)?\s*'	// seconds (#7): min-syntax: 1s
		.'$/i', $str, $matches)) {
			return false;
		}

		$expire = 0;
		if (isset($matches[1])) {
			// Years
			$expire += (int)$matches[1] * 60 * 60 * 24 * 365;
		}
		if (isset($matches[2])) {
			// Months
			$expire += (int)$matches[2] * 60 * 60 * 24 * 30;
		}
		if (isset($matches[3])) {
			// Weeks
			$expire += (int)$matches[3] * 60 * 60 * 24 * 7;
		}
		if (isset($matches[4])) {
			// Days
			$expire += (int)$matches[4] * 60 * 60 * 24;
		}
		if (isset($matches[5])) {
			// Hours
			$expire += (int)$matches[5] * 60 * 60;
		}
		if (isset($matches[6])) {
			// Minutes
			$expire += (int)$matches[6] * 60;
		}
		if (isset($matches[7])) {
			// Seconds
			$expire += (int)$matches[7];
		}

		return time() + $expire;
	}

	public static function parse_range($mask) {
		$ipstart = false;
		$ipend   = false;

		if (preg_match('@^(\d{1,3}\.){1,3}([\d*]{1,3})?$@', $mask) && substr_count($mask, '*') == 1) {
			// IPv4 wildcard mask
			$parts = explode('.', $mask);
			$ipv4  = '';
			foreach ($parts as $part) {
				if ($part == '*') {
					$ipstart = inet_pton($ipv4 . '0' . str_repeat('.0', 3 - substr_count($ipv4, '.')));
					$ipend   = inet_pton($ipv4 . '255' . str_repeat('.255', 3 - substr_count($ipv4, '.')));
					break;
				} elseif (($wc = strpos($part, '*')) !== false) {
					$ipstart = inet_pton($ipv4 . substr($part, 0, $wc) . '0' . str_repeat('.0', 3 - substr_count($ipv4, '.')));
					$ipend   = inet_pton($ipv4 . substr($part, 0, $wc) . '9' . str_repeat('.255', 3 - substr_count($ipv4, '.')));
					break;
				}
				$ipv4 .= "$part.";
			}
		} elseif (preg_match('@^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/\d+$@', $mask)) {
			list($ipv4, $bits) = explode('/', $mask);
			if ($bits > 32) {
				return false;
			}

			list($ipstart, $ipend) = self::calc_cidr($mask);
		} elseif (preg_match('@^[:a-z\d]+/\d+$@i', $mask)) {
			list($ipv6, $bits) = explode('/', $mask);
			if ($bits > 128) {
				return false;
			}

			list($ipstart, $ipend) = self::calc_cidr($mask);
		} elseif(isTorIp($mask)) {
			$ipstart = $mask;
		} else if (($ipstart = @inet_pton($mask)) === false) {
			return false;
		}

		return array($ipstart, $ipend);
	}

	public static function find($criteria, $board = false, $get_mod_info = false, $id = false) {
		$query = prepare('SELECT ``bans``.*' . ($get_mod_info ? ', `username`' : '') . ' FROM ``bans``
		' . ($get_mod_info ? 'LEFT JOIN ``mods`` ON ``mods``.`id` = `creator`' : '') . '
		WHERE ' . ($id ? 'id = :id' : '
			(' . ($board !== false ? '(`board` IS NULL OR `board` = :board) AND' : '') . '
			(`ipstart` = :ip OR (:ip >= `ipstart` AND :ip <= `ipend`)))') . '
		ORDER BY `expires` IS NULL, `expires` DESC');

		if ($board !== false) {
			$query->bindValue(':board', $board, PDO::PARAM_STR);
		}

		if (!$id) {
			$query->bindValue(':ip', isTorIp($criteria) ? $criteria : inet_pton($criteria));
		} else {
			$query->bindValue(':id', $criteria);
		}

		$query->execute() or error(db_error($query));

		$ban_list = array();

		while ($ban = $query->fetch(PDO::FETCH_ASSOC)) {
			if ($ban['expires'] && ($ban['seen'] || !Vi::$config['require_ban_view']) && $ban['expires'] < time()) {
				self::delete($ban['id']);
			} else {
				if ($ban['post']) {
					$ban['post'] = json_decode($ban['post'], true);
				}

				$ban['mask'] = self::range_to_string(array($ban['ipstart'], $ban['ipend']));
				$ban_list[]  = $ban;
			}
		}

		return $ban_list;
	}

	public static function stream_json($out = false, $filter_ips = false, $filter_staff = false, $board_access = false) {
		if ($board_access && $board_access[0] == '*') {
			$board_access = false;
		}

		$query_addition = "";
		if ($board_access) {
			$boards = implode(", ", array_map(array(Vi::$pdo, "quote"), $board_access));
			$query_addition .= "WHERE `board` IN (" . $boards . ")";
		}
		if ($board_access !== FALSE) {
			if (!$query_addition) {
				$query_addition .= " WHERE (`public_bans` IS TRUE) OR ``bans``.`board` IS NULL";
			}
		}

		$query = query("SELECT ``bans``.*, `username`, `type` FROM ``bans``
			LEFT JOIN ``mods`` ON ``mods``.`id` = `creator`
			LEFT JOIN ``boards`` ON ``boards``.`uri` = ``bans``.`board`
				$query_addition
 			ORDER BY `created` DESC") or error(db_error());
		$bans = $query->fetchAll(PDO::FETCH_ASSOC);

		$out ? fputs($out, "[") : print("[");

		$end = end($bans);

		foreach ($bans as &$ban) {
			$ban['mask'] = self::range_to_string(array($ban['ipstart'], $ban['ipend']));

			if ($ban['post']) {
				$post = json_decode($ban['post']);

				if ($post && isset($post->body)) {
					$ban['message'] = $post->body;
				}
			}
			unset($ban['ipstart'], $ban['ipend'], $ban['post'], $ban['creator']);

			if ($board_access === false || in_array($ban['board'], $board_access)) {
				$ban['access'] = true;
			}

			if (filter_var($ban['mask'], FILTER_VALIDATE_IP) !== false) {
				$ban['single_addr'] = true;
			}
			if ($filter_staff || ($board_access !== false && !in_array($ban['board'], $board_access))) {
				switch ($ban['type']) {
				case ADMIN:
					$ban['username'] = 'Admin';
					break;
				case GLOBALVOLUNTEER:
					$ban['username'] = 'Global Volunteer';
					break;
				case MOD:
					$ban['username'] = 'Board Owner';
					break;
				case BOARDVOLUNTEER:
					$ban['username'] = 'Board Volunteer';
					break;
				default:
					$ban['username'] = '?';
				}
				$ban['vstaff'] = true;
			}
			unset($ban['type']);
			if ($filter_ips || ($board_access !== false && !in_array($ban['board'], $board_access))) {
				$ban['mask'] = @less_ip($ban['mask'], $ban['board']);

				$ban['masked'] = true;
			}

			$json = json_encode($ban);
			$out ? fputs($out, $json) : print($json);

			if ($ban['id'] != $end['id']) {
				$out ? fputs($out, ",") : print(",");
			}
		}

		$out ? fputs($out, "]") : print("]");

	}

	public static function seen($ban_id) {
		$query = query("UPDATE ``bans`` SET `seen` = 1 WHERE `id` = " . (int) $ban_id) or error(db_error());
		if (!Vi::$config['cron_bans']) {
			rebuildThemes('bans');
		}

	}

	public static function purge() {
		$query = query("DELETE FROM ``bans`` WHERE `expires` IS NOT NULL AND `expires` < " . time() . " AND `seen` = 1") or error(db_error());
		if (!Vi::$config['cron_bans']) {
			rebuildThemes('bans');
		}

	}

	public static function delete($ban_id, $modlog = false, $boards = false, $dont_rebuild = false) {
		if ($boards && $boards[0] == '*') {
			$boards = false;
		}

		if ($modlog) {
			$query = query("SELECT `ipstart`, `ipend`, `board` FROM ``bans`` WHERE `id` = " . (int) $ban_id) or error(db_error());
			if (!$ban = $query->fetch(PDO::FETCH_ASSOC)) {
				// Ban doesn't exist
				return false;
			}

			if ($boards !== false && !in_array($ban['board'], $boards)) {
				error(Vi::$config['error']['noaccess']);
			}

			if ($ban['board']) {
				openBoard($ban['board']);
			}

			$mask = self::range_to_string(array($ban['ipstart'], $ban['ipend']));

			modLog("Removed ban #{$ban_id} for " . ip_link($mask, (filter_var($mask, FILTER_VALIDATE_IP) !== false)));
		}

		query("DELETE FROM ``bans`` WHERE `id` = " . (int) $ban_id) or error(db_error());

		if (!$dont_rebuild || !Vi::$config['cron_bans']) {
			rebuildThemes('bans');
		}

		return true;
	}

	public static function new_ban($mask, $reason, $length = false, $ban_board = false, $mod_id = false, $post = false) {
		if ($mod_id === false) {
			$mod_id = isset(Vi::$mod['id']) ? Vi::$mod['id'] : -1;
		}

		if ($mod_id > 0 && !in_array($ban_board, Vi::$mod['boards']) && Vi::$mod['boards'][0] != '*') {
			error(Vi::$config['error']['noaccess']);
		}

		$range = self::parse_range($mask);
		$mask  = self::range_to_string($range);

		$query = prepare("INSERT INTO ``bans`` VALUES (NULL, :ipstart, :ipend, :time, :expires, :board, :mod, :reason, 0, :post)");

		$query->bindValue(':ipstart', $range[0]);
		if ($range[1] !== false && $range[1] != $range[0]) {
			$query->bindValue(':ipend', $range[1]);
		} else {
			$query->bindValue(':ipend', null, PDO::PARAM_NULL);
		}

		$query->bindValue(':mod', $mod_id);
		$query->bindValue(':time', time());

		if ($reason !== '') {
			$reason = escape_markup_modifiers($reason);
			markup($reason);
			$query->bindValue(':reason', $reason);
		} else {
			$query->bindValue(':reason', null, PDO::PARAM_NULL);
		}

		if ($length) {
			if (is_int($length) || ctype_digit($length)) {
				$length = time() + $length;
			} else {
				$length = self::parse_time($length);
			}
			$query->bindValue(':expires', $length);
		} else {
			$query->bindValue(':expires', null, PDO::PARAM_NULL);
		}

		if ($ban_board) {
			$query->bindValue(':board', $ban_board);
		} else {
			$query->bindValue(':board', null, PDO::PARAM_NULL);
		}

		if ($post) {
			$post['board'] = Vi::$board['uri'];
			$match_urls    = '(?xi)\b((?:https?://|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))';

			$matched = array();

			preg_match_all("#$match_urls#im", $post['body_nomarkup'], $matched);

			if (isset($matched[0]) && $matched[0]) {
				$post['body']          = str_replace($matched[0], '###Link-Removed###', $post['body']);
				$post['body_nomarkup'] = str_replace($matched[0], '###Link-Removed###', $post['body_nomarkup']);
			}

			$query->bindValue(':post', json_encode($post));
		} else {
			$query->bindValue(':post', null, PDO::PARAM_NULL);
		}

		$query->execute() or error(db_error($query));

		if (isset(Vi::$mod['id']) && Vi::$mod['id'] == $mod_id) {
			modLog('Created a new ' .
				($length > 0 ? preg_replace('/^(\d+) (\w+?)s?$/', '$1-$2', until($length)) : 'permanent') .
				' ban on ' .
				($ban_board ? '/' . $ban_board . '/' : 'all boards') .
				' for ' .
				ip_link($mask, (filter_var($mask, FILTER_VALIDATE_IP) !== false)) .
				' (<small>#' . Vi::$pdo->lastInsertId() . '</small>)' .
				' with ' . ($reason ? 'reason: ' . utf8tohtml($reason) . '' : 'no reason'));
		}

		if (!Vi::$config['cron_bans']) {
			rebuildThemes('bans');
		}

		return Vi::$pdo->lastInsertId();
	}
}
