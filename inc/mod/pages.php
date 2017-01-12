<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

defined('TINYBOARD') or exit;

function mod_page($title, $template, $args, $subtitle = false) {
	echo Element('page.html', array(
			'config'              => Vi::$config,
			'mod'                 => Vi::$mod,
			'hide_dashboard_link' => $template == 'mod/dashboard.html',
			'title'               => $title,
			'subtitle'            => $subtitle,
			'boardlist'           => createBoardlist(Vi::$mod),
			'body'                => Element($template,
				array_merge(
					array('config' => Vi::$config, 'mod' => Vi::$mod),
					$args
				)
			),
		)
	);
}

function mod_login($redirect = false) {
	$args = array();

	if (isset($_POST['login'])) {
		// Check if inputs are set and not empty
		if (!isset($_POST['username'], $_POST['password']) || $_POST['username'] == '' || $_POST['password'] == '') {
			$args['error'] = Vi::$config['error']['invalid'];
		} elseif (!login($_POST['username'], $_POST['password'])) {
			if (Vi::$config['syslog']) {
				_syslog(LOG_WARNING, 'Unauthorized login attempt!');
			}

			$args['error'] = Vi::$config['error']['invalid'];
		} else {
			modLog('Logged in');

			// Login successful
			// Set cookies
			setCookies();

			if ($redirect) {
				header('Location: ?' . $redirect, true, Vi::$config['redirect_http']);
			} else {
				header('Location: ?/', true, Vi::$config['redirect_http']);
			}

		}
	}

	if (isset($_POST['username'])) {
		$args['username'] = $_POST['username'];
	}

	mod_page(_('Login'), 'mod/login.html', $args);
}

function mod_confirm($request) {
	mod_page(_('Confirm action'), 'mod/confirm.html', array('request' => $request, 'token' => make_secure_link_token($request)));
}

function mod_logout() {
	destroyCookies();

	header('Location: ?/', true, Vi::$config['redirect_http']);
}

function mod_dashboard() {
	$args = array();

	$args['boards'] = listBoards();

	if (hasPermission(Vi::$config['mod']['noticeboard'])) {
		if (!Vi::$config['cache']['enabled'] || !$args['noticeboard'] = cache::get('noticeboard_preview')) {
			$query = prepare("SELECT ``noticeboard``.*, `username` FROM ``noticeboard`` LEFT JOIN ``mods`` ON ``mods``.`id` = `mod` ORDER BY `id` DESC LIMIT :limit");
			$query->bindValue(':limit', Vi::$config['mod']['noticeboard_dashboard'], PDO::PARAM_INT);
			$query->execute() or error(db_error($query));
			$args['noticeboard'] = $query->fetchAll(PDO::FETCH_ASSOC);

			if (Vi::$config['cache']['enabled']) {
				cache::set('noticeboard_preview', $args['noticeboard']);
			}

		}
	}

	if (!Vi::$config['cache']['enabled'] || ($args['unread_pms'] = (int)cache::get('pm_unreadcount_' . Vi::$mod['id'])) === false) {
		$query = prepare('SELECT COUNT(*) FROM ``pms`` WHERE `to` = :id AND `unread` = 1');
		$query->bindValue(':id', Vi::$mod['id']);
		$query->execute() or error(db_error($query));
		$args['unread_pms'] = $query->fetchColumn();

		if (Vi::$config['cache']['enabled']) {
			cache::set('pm_unreadcount_' . Vi::$mod['id'], $args['unread_pms']);
		}
	}

	$query = prepare('SELECT COUNT(*) AS `total_reports` FROM ``reports``' . ((Vi::$mod["type"] < GLOBALVOLUNTEER) ? " WHERE board = :board" : ""));

	if (Vi::$mod['type'] < GLOBALVOLUNTEER) {
		$query->bindValue(':board', Vi::$mod['boards'][0]);
	} else {
		$query = prepare('SELECT (SELECT COUNT(id) FROM reports WHERE global = 0) AS total_reports, (SELECT COUNT(id) FROM reports WHERE global = 1) AS global_reports');
	}

	$query->execute() or error(db_error($query));
	$row                    = $query->fetch();
	$args['reports']        = $row['total_reports'];
	$args['global_reports'] = isset($row['global_reports']) ? $row['global_reports'] : false;

	$args['logout_token'] = make_secure_link_token('logout');

	modLog('Looked at dashboard', false);

	mod_page(_('Dashboard'), 'mod/dashboard.html', $args);
}

function mod_search_redirect() {
	if (!hasPermission(Vi::$config['mod']['search'])) {
		error(Vi::$config['error']['noaccess']);
	}

	if (isset($_POST['query'], $_POST['type']) && in_array($_POST['type'], array('posts', 'IP_notes', 'bans', 'log'))) {
		$query = $_POST['query'];
		$query = urlencode($query);
		$query = str_replace('_', '%5F', $query);
		$query = str_replace('+', '_', $query);

		if ($query === '') {
			header('Location: ?/', true, Vi::$config['redirect_http']);
			return;
		}

		header('Location: ?/search/' . $_POST['type'] . '/' . $query, true, Vi::$config['redirect_http']);
	} else {
		header('Location: ?/', true, Vi::$config['redirect_http']);
	}
}

function mod_search($type, $search_query_escaped, $page_no = 1) {
	if (!hasPermission(Vi::$config['mod']['search'])) {
		error(Vi::$config['error']['noaccess']);
	}

	// Unescape query
	$query        = str_replace('_', ' ', $search_query_escaped);
	$query        = urldecode($query);
	$search_query = $query;

	// Form a series of LIKE clauses for the query.
	// This gets a little complicated.

	// Escape "escape" character
	$query = str_replace('!', '!!', $query);

	// Escape SQL wildcard
	$query = str_replace('%', '!%', $query);

	// Use asterisk as wildcard instead
	$query = str_replace('*', '%', $query);

	$query = str_replace('`', '!`', $query);

	// Array of phrases to match
	$match = array();

	// Exact phrases ("like this")
	if (preg_match_all('/"(.+?)"/', $query, $exact_phrases)) {
		$exact_phrases = $exact_phrases[1];
		foreach ($exact_phrases as $phrase) {
			$query   = str_replace("\"{$phrase}\"", '', $query);
			$match[] = Vi::$pdo->quote($phrase);
		}
	}

	// Non-exact phrases (ie. plain keywords)
	$keywords = explode(' ', $query);
	foreach ($keywords as $word) {
		if (empty($word)) {
			continue;
		}

		$match[] = Vi::$pdo->quote($word);
	}

	// Which `field` to search?
	if ($type == 'posts') {
		$sql_field = array('body_nomarkup', 'files', 'subject', 'filehash', 'ip', 'name', 'trip');
	}

	if ($type == 'IP_notes') {
		$sql_field = 'body';
	}

	if ($type == 'bans') {
		$sql_field = 'reason';
	}

	if ($type == 'log') {
		$sql_field = 'text';
	}

	// Build the "LIKE 'this' AND LIKE 'that'" etc. part of the SQL query
	$sql_like = '';
	foreach ($match as $phrase) {
		if (!empty($sql_like)) {
			$sql_like .= ' AND ';
		}

		$phrase = preg_replace('/^\'(.+)\'$/', '\'%$1%\'', $phrase);
		if (is_array($sql_field)) {
			foreach ($sql_field as $field) {
				$sql_like .= '`' . $field . '` LIKE ' . $phrase . ' ESCAPE \'!\' OR';
			}
			$sql_like = preg_replace('/ OR$/', '', $sql_like);
		} else {
			$sql_like .= '`' . $sql_field . '` LIKE ' . $phrase . ' ESCAPE \'!\'';
		}
	}

	// Compile SQL query

	if ($type == 'posts') {
		$query  = '';
		$boards = listBoards();
		if (empty($boards)) {
			error(_('There are no boards to search!'));
		}

		foreach ($boards as $board) {
			openBoard($board['uri']);
			if (!hasPermission(Vi::$config['mod']['search_posts'], $board['uri'])) {
				continue;
			}

			if (!empty($query)) {
				$query .= ' UNION ALL ';
			}

			$query .= sprintf("SELECT *, '%s' AS `board` FROM ``posts_%s`` WHERE %s", $board['uri'], $board['uri'], $sql_like);
		}

		// You weren't allowed to search any boards
		if (empty($query)) {
			error(Vi::$config['error']['noaccess']);
		}

		$query .= ' ORDER BY `sticky` DESC, `id` DESC';
	}

	if ($type == 'IP_notes') {
		$query     = 'SELECT * FROM ``ip_notes`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` WHERE ' . $sql_like . ' ORDER BY `time` DESC';
		$sql_table = 'ip_notes';
		if (!hasPermission(Vi::$config['mod']['view_notes']) || !hasPermission(Vi::$config['mod']['show_ip'])) {
			error(Vi::$config['error']['noaccess']);
		}

	}

	if ($type == 'bans') {
		$query     = 'SELECT ``bans``.*, `username` FROM ``bans`` LEFT JOIN ``mods`` ON `creator` = ``mods``.`id` WHERE ' . $sql_like . ' ORDER BY (`expires` IS NOT NULL AND `expires` < UNIX_TIMESTAMP()), `created` DESC';
		$sql_table = 'bans';
		if (!hasPermission(Vi::$config['mod']['view_banlist'])) {
			error(Vi::$config['error']['noaccess']);
		}

	}

	if ($type == 'log') {
		$query     = 'SELECT `username`, `mod`, `ip`, `board`, `time`, `text` FROM ``modlogs`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` WHERE ' . $sql_like . ' ORDER BY `time` DESC';
		$sql_table = 'modlogs';
		if (!hasPermission(Vi::$config['mod']['modlog'])) {
			error(Vi::$config['error']['noaccess']);
		}

	}

	// Execute SQL query (with pages)
	$q       = query($query . ' LIMIT ' . (($page_no - 1) * Vi::$config['mod']['search_page']) . ', ' . Vi::$config['mod']['search_page']) or error(db_error());
	$results = $q->fetchAll(PDO::FETCH_ASSOC);

	// Get total result count
	if ($type == 'posts') {
		$q            = query("SELECT COUNT(*) FROM ($query) AS `tmp_table`") or error(db_error());
		$result_count = $q->fetchColumn();
	} else {
		$q            = query('SELECT COUNT(*) FROM `' . $sql_table . '` WHERE ' . $sql_like) or error(db_error());
		$result_count = $q->fetchColumn();
	}

	if ($type == 'bans') {
		foreach ($results as &$ban) {
			$ban['mask'] = Bans::range_to_string(array($ban['ipstart'], $ban['ipend']));
			if (filter_var($ban['mask'], FILTER_VALIDATE_IP) !== false) {
				$ban['single_addr'] = true;
			}

		}
	}

	if ($type == 'posts') {
		foreach ($results as &$post) {
			$post['snippet'] = pm_snippet($post['body']);
		}
	}

	// $results now contains the search results

	mod_page(_('Search results'), 'mod/search_results.html', array(
		'search_type'          => $type,
		'search_query'         => $search_query,
		'search_query_escaped' => $search_query_escaped,
		'result_count'         => $result_count,
		'results'              => $results,
	));
}

function mod_edit_board($boardName) {
	if (!openBoard($boardName)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!hasPermission(Vi::$config['mod']['manageboards'], Vi::$board['uri'])) {
		error(Vi::$config['error']['noaccess']);
	}

	if (isset($_POST['title'], $_POST['subtitle'])) {
		if (isset($_POST['delete'])) {
			if (!hasPermission(Vi::$config['mod']['manageboards'], Vi::$board['uri'])) {
				error(Vi::$config['error']['deleteboard']);
			}

			$query = prepare('DELETE FROM ``boards`` WHERE `uri` = :uri');
			$query->bindValue(':uri', Vi::$board['uri']);
			$query->execute() or error(db_error($query));

			if (Vi::$config['cache']['enabled']) {
				cache::delete('board_' . Vi::$board['uri']);
				cache::delete('all_boards');
				cache::delete('all_boards_uri');
			}

			modLog('Deleted board: ' . sprintf(Vi::$config['board_abbreviation'], Vi::$board['uri']), false);

			// Delete posting table
			$query = query(sprintf('DROP TABLE IF EXISTS ``posts_%s``', Vi::$board['uri'])) or error(db_error());

			// Clear reports
			$query = prepare('DELETE FROM ``reports`` WHERE `board` = :id');
			$query->bindValue(':id', Vi::$board['uri'], PDO::PARAM_INT);
			$query->execute() or error(db_error($query));

			// Delete from table
			$query = prepare('DELETE FROM ``boards`` WHERE `uri` = :uri');
			$query->bindValue(':uri', Vi::$board['uri'], PDO::PARAM_INT);
			$query->execute() or error(db_error($query));

			$query = prepare("SELECT `board`, `post` FROM ``cites`` WHERE `target_board` = :board ORDER BY `board`");
			$query->bindValue(':board', Vi::$board['uri']);
			$query->execute() or error(db_error($query));
			while ($cite = $query->fetch(PDO::FETCH_ASSOC)) {
				if (Vi::$board['uri'] != $cite['board']) {
					if (!isset($tmp_board)) {
						$tmp_board = Vi::$board;
					}

					openBoard($cite['board']);
					rebuildPost($cite['post']);
				}
			}

			if (isset($tmp_board)) {
				Vi::$board = $tmp_board;
			}

			$query = prepare('DELETE FROM ``cites`` WHERE `board` = :board OR `target_board` = :board');
			$query->bindValue(':board', Vi::$board['uri']);
			$query->execute() or error(db_error($query));

			$query = prepare('DELETE FROM ``antispam`` WHERE `board` = :board');
			$query->bindValue(':board', Vi::$board['uri']);
			$query->execute() or error(db_error($query));

			// Remove board from users/permissions table
			$query = query('SELECT `id`,`boards` FROM ``mods``') or error(db_error());
			while ($user = $query->fetch(PDO::FETCH_ASSOC)) {
				$user_boards = explode(',', $user['boards']);
				if (in_array(Vi::$board['uri'], $user_boards)) {
					unset($user_boards[array_search(Vi::$board['uri'], $user_boards)]);
					$_query = prepare('UPDATE ``mods`` SET `boards` = :boards WHERE `id` = :id');
					$_query->bindValue(':boards', implode(',', $user_boards));
					$_query->bindValue(':id', $user['id']);
					$_query->execute() or error(db_error($_query));
				}
			}

			// Delete entire board directory
			rrmdir(Vi::$board['uri'] . '/');
			// To reiterate: HAAAAAX
			if (Vi::$config['dir']['img_root'] != '') {
				rrmdir(Vi::$config['dir']['img_root'] . Vi::$board['uri']);
			}

		} else {
			$query = prepare('UPDATE ``boards`` SET `title` = :title, `subtitle` = :subtitle WHERE `uri` = :uri');
			$query->bindValue(':uri', Vi::$board['uri']);
			$query->bindValue(':title', $_POST['title']);
			$query->bindValue(':subtitle', $_POST['subtitle']);
			$query->execute() or error(db_error($query));

			modLog('Edited board information for ' . sprintf(Vi::$config['board_abbreviation'], Vi::$board['uri']), false);
		}

		if (Vi::$config['cache']['enabled']) {
			cache::delete('board_' . Vi::$board['uri']);
			cache::delete('all_boards');
			cache::delete('all_boards_uri');
		}

		rebuildThemes('boards');

		header('Location: ?/', true, Vi::$config['redirect_http']);
	} else {
		mod_page(sprintf('%s: ' . Vi::$config['board_abbreviation'], _('Edit board'), Vi::$board['uri']), 'mod/board.html', array(
			'board' => Vi::$board,
			'token' => make_secure_link_token('edit/' . Vi::$board['uri']),
		));
	}
}

function mod_new_board() {
	if (!hasPermission(Vi::$config['mod']['newboard'])) {
		error(Vi::$config['error']['noaccess']);
	}

	if (isset($_POST['uri'], $_POST['title'], $_POST['subtitle'])) {
		if ($_POST['uri'] == '') {
			error(sprintf(Vi::$config['error']['required'], 'URI'));
		}

		if ($_POST['title'] == '') {
			error(sprintf(Vi::$config['error']['required'], 'title'));
		}

		if (!preg_match('/^' . Vi::$config['board_regex'] . '$/u', $_POST['uri'])) {
			error(sprintf(Vi::$config['error']['invalidfield'], 'URI'));
		}

		$bytes = 0;
		$chars = preg_split('//u', $_POST['uri'], -1, PREG_SPLIT_NO_EMPTY);
		foreach ($chars as $char) {
			$o   = 0;
			$ord = ordutf8($char, $o);
			if ($ord > 0x0080) {
				$bytes += 5;
			}
			// @01ff
			else {
				$bytes++;
			}

		}
		$bytes + strlen('posts_.frm');

		if ($bytes > 255) {
			error('Your filesystem cannot handle a board URI of that length (' . $bytes . '/255 bytes)');
			exit;
		}

		if (openBoard($_POST['uri'])) {
			error(sprintf(Vi::$config['error']['boardexists'], Vi::$board['url']));
		}
		foreach (Vi::$config['banned_boards'] as $i => $w) {
			if ($w[0] !== '/') {
				if (strpos($_POST['uri'], $w) !== false) {
					error(_("Cannot create board with banned word $w"));
				}

			} else {
				if (preg_match($w, $_POST['uri'])) {
					error(_("Cannot create board matching banned pattern $w"));
				}

			}
		}
		$query = prepare('INSERT INTO ``boards`` (``uri``, ``title``, ``subtitle``) VALUES (:uri, :title, :subtitle)');
		$query->bindValue(':uri', $_POST['uri']);
		$query->bindValue(':title', $_POST['title']);
		$query->bindValue(':subtitle', $_POST['subtitle']);
		$query->execute() or error(db_error($query));

		modLog('Created a new board: ' . sprintf(Vi::$config['board_abbreviation'], $_POST['uri']));

		if (!openBoard($_POST['uri'])) {
			error(_("Couldn't open board after creation."));
		}

		$query = Element('posts.sql', array('board' => Vi::$board['uri']));

		if (mysql_version() < 50503) {
			$query = preg_replace('/(CHARSET=|CHARACTER SET )utf8mb4/', '$1utf8', $query);
		}

		query($query) or error(db_error());

		if (Vi::$config['cache']['enabled']) {
			cache::delete('all_boards');
		}

		// Build the board
		buildIndex();

		rebuildThemes('boards');

		header('Location: ?/' . Vi::$board['uri'] . '/' . Vi::$config['file_index'], true, Vi::$config['redirect_http']);
	}

	mod_page(_('New board'), 'mod/board.html', array('new' => true, 'token' => make_secure_link_token('new-board')));
}

function mod_noticeboard($page_no = 1) {
	if ($page_no < 1) {
		error(Vi::$config['error']['404']);
	}

	if (!hasPermission(Vi::$config['mod']['noticeboard'])) {
		error(Vi::$config['error']['noaccess']);
	}

	if (isset($_POST['subject'], $_POST['body'])) {
		if (!hasPermission(Vi::$config['mod']['noticeboard_post'])) {
			error(Vi::$config['error']['noaccess']);
		}

		$_POST['body'] = escape_markup_modifiers($_POST['body']);
		markup($_POST['body']);

		$query = prepare('INSERT INTO ``noticeboard`` VALUES (NULL, :mod, :time, :subject, :body)');
		$query->bindValue(':mod', Vi::$mod['id']);
		$query->bindvalue(':time', time());
		$query->bindValue(':subject', $_POST['subject']);
		$query->bindValue(':body', $_POST['body']);
		$query->execute() or error(db_error($query));

		if (Vi::$config['cache']['enabled']) {
			cache::delete('noticeboard_preview');
		}

		modLog('Posted a noticeboard entry');

		header('Location: ?/noticeboard#' . Vi::$pdo->lastInsertId(), true, Vi::$config['redirect_http']);
	}

	$query = prepare("SELECT ``noticeboard``.*, `username` FROM ``noticeboard`` LEFT JOIN ``mods`` ON ``mods``.`id` = `mod` ORDER BY `id` DESC LIMIT :offset, :limit");
	$query->bindValue(':limit', Vi::$config['mod']['noticeboard_page'], PDO::PARAM_INT);
	$query->bindValue(':offset', ($page_no - 1) * Vi::$config['mod']['noticeboard_page'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$noticeboard = $query->fetchAll(PDO::FETCH_ASSOC);

	if (empty($noticeboard) && $page_no > 1) {
		error(Vi::$config['error']['404']);
	}

	foreach ($noticeboard as &$entry) {
		$entry['delete_token'] = make_secure_link_token('noticeboard/delete/' . $entry['id']);
	}

	$query = prepare("SELECT COUNT(*) FROM ``noticeboard``");
	$query->execute() or error(db_error($query));
	$count = $query->fetchColumn();

	mod_page(_('Noticeboard'), 'mod/noticeboard.html', array(
		'noticeboard' => $noticeboard,
		'count'       => $count,
		'token'       => make_secure_link_token('noticeboard'),
	));
}

function mod_noticeboard_delete($id) {
	if (!hasPermission(Vi::$config['mod']['noticeboard_delete'])) {
		error(Vi::$config['error']['noaccess']);
	}

	$query = prepare('DELETE FROM ``noticeboard`` WHERE `id` = :id');
	$query->bindValue(':id', $id);
	$query->execute() or error(db_error($query));

	modLog('Deleted a noticeboard entry');

	if (Vi::$config['cache']['enabled']) {
		cache::delete('noticeboard_preview');
	}

	header('Location: ?/noticeboard', true, Vi::$config['redirect_http']);
}

function mod_news($page_no = 1) {
	if ($page_no < 1) {
		error(Vi::$config['error']['404']);
	}

	if (isset($_POST['subject'], $_POST['body'])) {
		if (!hasPermission(Vi::$config['mod']['news'])) {
			error(Vi::$config['error']['noaccess']);
		}

		$_POST['body'] = escape_markup_modifiers($_POST['body']);
		markup($_POST['body']);

		$query = prepare('INSERT INTO ``news`` VALUES (NULL, :name, :time, :subject, :body)');
		$query->bindValue(':name', isset($_POST['name']) && hasPermission(Vi::$config['mod']['news_custom']) ? $_POST['name'] : Vi::$mod['username']);
		$query->bindvalue(':time', time());
		$query->bindValue(':subject', $_POST['subject']);
		$query->bindValue(':body', $_POST['body']);
		$query->execute() or error(db_error($query));

		modLog('Posted a news entry');

		rebuildThemes('news');

		header('Location: ?/edit_news#' . Vi::$pdo->lastInsertId(), true, Vi::$config['redirect_http']);
	}

	$query = prepare("SELECT * FROM ``news`` ORDER BY `id` DESC LIMIT :offset, :limit");
	$query->bindValue(':limit', Vi::$config['mod']['news_page'], PDO::PARAM_INT);
	$query->bindValue(':offset', ($page_no - 1) * Vi::$config['mod']['news_page'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$news = $query->fetchAll(PDO::FETCH_ASSOC);

	if (empty($news) && $page_no > 1) {
		error(Vi::$config['error']['404']);
	}

	foreach ($news as &$entry) {
		$entry['delete_token'] = make_secure_link_token('edit_news/delete/' . $entry['id']);
	}

	$query = prepare("SELECT COUNT(*) FROM ``news``");
	$query->execute() or error(db_error($query));
	$count = $query->fetchColumn();

	mod_page(_('News'), 'mod/news.html', array('news' => $news, 'count' => $count, 'token' => make_secure_link_token('edit_news')));
}

function mod_news_delete($id) {
	if (!hasPermission(Vi::$config['mod']['news_delete'])) {
		error(Vi::$config['error']['noaccess']);
	}

	$query = prepare('DELETE FROM ``news`` WHERE `id` = :id');
	$query->bindValue(':id', $id);
	$query->execute() or error(db_error($query));

	modLog('Deleted a news entry');

	header('Location: ?/edit_news', true, Vi::$config['redirect_http']);
}

function mod_log($page_no = 1) {
	if ($page_no < 1) {
		error(Vi::$config['error']['404']);
	}

	if (!hasPermission(Vi::$config['mod']['modlog'])) {
		error(Vi::$config['error']['noaccess']);
	}

	$query = prepare("SELECT `username`, `mod`, `ip`, `board`, `time`, `text` FROM ``modlogs`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` ORDER BY `time` DESC LIMIT :offset, :limit");
	$query->bindValue(':limit', Vi::$config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->bindValue(':offset', ($page_no - 1) * Vi::$config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$logs = $query->fetchAll(PDO::FETCH_ASSOC);

	if (empty($logs) && $page_no > 1) {
		error(Vi::$config['error']['404']);
	}

	$query = prepare("SELECT COUNT(*) FROM ``modlogs``");
	$query->execute() or error(db_error($query));
	$count = $query->fetchColumn();

	mod_page(_('Board log'), 'mod/log.html', array('logs' => $logs, 'count' => $count));
}

function mod_user_log($username, $page_no = 1) {
	if ($page_no < 1) {
		error(Vi::$config['error']['404']);
	}

	if (!hasPermission(Vi::$config['mod']['modlog'])) {
		error(Vi::$config['error']['noaccess']);
	}

	$query = prepare("SELECT `username`, `mod`, `ip`, `board`, `time`, `text` FROM ``modlogs`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` WHERE `username` = :username ORDER BY `time` DESC LIMIT :offset, :limit");
	$query->bindValue(':username', $username);
	$query->bindValue(':limit', Vi::$config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->bindValue(':offset', ($page_no - 1) * Vi::$config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$logs = $query->fetchAll(PDO::FETCH_ASSOC);

	if (empty($logs) && $page_no > 1) {
		error(Vi::$config['error']['404']);
	}

	$query = prepare("SELECT COUNT(*) FROM ``modlogs`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` WHERE `username` = :username");
	$query->bindValue(':username', $username);
	$query->execute() or error(db_error($query));
	$count = $query->fetchColumn();

	mod_page(_('Board log'), 'mod/log.html', array('logs' => $logs, 'count' => $count, 'username' => $username));
}

function mod_board_log($board, $page_no = 1, $hide_names = false, $public = false) {
	if ($page_no < 1) {
		error(Vi::$config['error']['404']);
	}

	if (!hasPermission(Vi::$config['mod']['mod_board_log'], $board) && !$public) {
		error(Vi::$config['error']['noaccess']);
	}

	$query = prepare("SELECT `username`, `mod`, `ip`, `board`, `time`, `text` FROM ``modlogs`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` WHERE `board` = :board ORDER BY `time` DESC LIMIT :offset, :limit");
	$query->bindValue(':board', $board);
	$query->bindValue(':limit', Vi::$config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->bindValue(':offset', ($page_no - 1) * Vi::$config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$logs = $query->fetchAll(PDO::FETCH_ASSOC);

	if (empty($logs) && $page_no > 1) {
		error(Vi::$config['error']['404']);
	}

	if (!hasPermission(Vi::$config['mod']['show_ip'])) {
		// Supports ipv4 only!
		foreach ($logs as $i => &$log) {
			$log['text'] = preg_replace_callback(array(
					'#(?:<a id="ip"[^>]*>)([^</]*)(/\d+)?(?:</a>)#',
					'/(?:<a href="\?\/IP\/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}">)?(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})(?:<\/a>)?/'
				), function ($matches) {
				return less_ip($matches[1], Vi::$board['uri']) . (count($matches) > 2 ? $matches[2] : "");
			}, $log['text']);
		}
	}

	$query = prepare("SELECT COUNT(*) FROM ``modlogs`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` WHERE `board` = :board");
	$query->bindValue(':board', $board);
	$query->execute() or error(db_error($query));
	$count = $query->fetchColumn();

	mod_page(_('Board log'), 'mod/log.html', array('logs' => $logs, 'count' => $count, 'board' => $board, 'hide_names' => $hide_names, 'public' => $public));
}

function mod_view_board($boardName, $page_no = 1) {
	if (!openBoard($boardName)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!$page = index($page_no, Vi::$mod)) {
		error(Vi::$config['error']['404']);
	}

	$page['pages']                           = getPages(true);
	$page['pages'][$page_no - 1]['selected'] = true;
	$page['btn']                             = getPageButtons($page['pages'], true);
	$page['mod']                             = true;
	$page['config']                          = Vi::$config;

	echo Element('index.html', $page);
}

function mod_view_thread($boardName, $thread) {
	if (!openBoard($boardName)) {
		error(Vi::$config['error']['noboard']);
	}

	$page = buildThread($thread, true, Vi::$mod);
	echo $page;
}

function mod_view_thread50($boardName, $thread) {
	if (!openBoard($boardName)) {
		error(Vi::$config['error']['noboard']);
	}

	$page = buildThread50($thread, true, Vi::$mod);
	echo $page;
}

function mod_ip_remove_note($ip, $id) {
	if (!hasPermission(Vi::$config['mod']['remove_notes'])) {
		error(Vi::$config['error']['noaccess']);
	}

	if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
		error("Invalid IP address.");
	}

	$query = prepare('DELETE FROM ``ip_notes`` WHERE `ip` = :ip AND `id` = :id');
	$query->bindValue(':ip', $ip);
	$query->bindValue(':id', $id);
	$query->execute() or error(db_error($query));

	modLog("Removed a note for " . ip_link($ip));

	header('Location: ?/IP/' . $ip . '#notes', true, Vi::$config['redirect_http']);
}

function mod_page_ip($ip) {
	if (!hasPermission(Vi::$config['mod']['show_ip'])) {
		error(Vi::$config['error']['noaccess']);
	}

	if (!isTorIp($ip) && filter_var($ip, FILTER_VALIDATE_IP) === false) {
		error("Invalid IP address.");
	}

	if (isset($_POST['ban_id'], $_POST['unban'])) {
		if (!hasPermission(Vi::$config['mod']['unban'])) {
			error(Vi::$config['error']['noaccess']);
		}

		Bans::delete($_POST['ban_id'], true, Vi::$mod['boards']);

		header('Location: ?/IP/' . $ip . '#bans', true, Vi::$config['redirect_http']);
		return;
	}

	if (isset($_POST['note'])) {
		if (!hasPermission(Vi::$config['mod']['create_notes'])) {
			error(Vi::$config['error']['noaccess']);
		}

		$_POST['note'] = escape_markup_modifiers($_POST['note']);
		markup($_POST['note']);
		$query = prepare('INSERT INTO ``ip_notes`` VALUES (NULL, :ip, :mod, :time, :body)');
		$query->bindValue(':ip', $ip);
		$query->bindValue(':mod', Vi::$mod['id']);
		$query->bindValue(':time', time());
		$query->bindValue(':body', $_POST['note']);
		$query->execute() or error(db_error($query));

		modLog("Added a note for " . ip_link($ip));

		header('Location: ?/IP/' . $ip . '#notes', true, Vi::$config['redirect_http']);
		return;
	}

	$args          = array();
	$args['ip']    = $ip;
	$args['posts'] = array();

	$args['hostname'] = NULL;
	if (Vi::$config['mod']['dns_lookup'] && !isTorIp($ip)) {
		$args['hostname'] = rDNS($ip);
	}

	$boards = listBoards();
	foreach ($boards as $board) {
		openBoard($board['uri']);
		if (!hasPermission(Vi::$config['mod']['show_ip'], $board['uri'])) {
			continue;
		}

		$query = prepare(sprintf('SELECT * FROM ``posts_%s`` WHERE `ip` = :ip ORDER BY `sticky` DESC, `id` DESC LIMIT :limit', $board['uri']));
		$query->bindValue(':ip', $ip);
		$query->bindValue(':limit', Vi::$config['mod']['ip_recentposts'], PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
			if (!$post['thread']) {
				$po = new Thread($post, '?/', Vi::$mod, false);
			} else {
				$po = new Post($post, '?/', Vi::$mod);
			}

			if (!isset($args['posts'][$board['uri']])) {
				$args['posts'][$board['uri']] = array('board' => $board, 'posts' => array());
			}

			$args['posts'][$board['uri']]['posts'][] = $po->build(true);
		}
	}

	$args['boards'] = $boards;
	$args['token']  = make_secure_link_token('ban');

	if (hasPermission(Vi::$config['mod']['view_ban'])) {
		$args['bans'] = Bans::find($ip, false, true);
	}

	if (hasPermission(Vi::$config['mod']['view_notes'])) {
		$query = prepare("SELECT ``ip_notes``.*, `username` FROM ``ip_notes`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` WHERE `ip` = :ip ORDER BY `time` DESC");
		$query->bindValue(':ip', $ip);
		$query->execute() or error(db_error($query));
		$args['notes'] = $query->fetchAll(PDO::FETCH_ASSOC);
	}

	if (hasPermission(Vi::$config['mod']['modlog_ip'])) {
		$query = prepare("SELECT `username`, `mod`, `ip`, `board`, `time`, `text` FROM ``modlogs`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` WHERE `text` LIKE :search ORDER BY `time` DESC LIMIT 50");
		$query->bindValue(':search', '%' . $ip . '%');
		$query->execute() or error(db_error($query));
		$args['logs'] = $query->fetchAll(PDO::FETCH_ASSOC);
	} else {
		$args['logs'] = array();
	}

	$args['security_token'] = make_secure_link_token('IP/' . $ip);

	mod_page(sprintf('%s: %s', _('IP'), htmlspecialchars($ip)), 'mod/view_ip.html', $args, $args['hostname']);
}

function mod_page_ip_less($b, $id) {
	if (!hasPermission(Vi::$config['mod']['show_ip_less'], $b)) {
		error(Vi::$config['error']['noaccess']);
	}

	if (!openBoard($b)) {
		error('No board.');
	}

	$query = prepare(sprintf('SELECT `ip` FROM ``posts_%s`` WHERE `id` = :id', $b));
	$query->bindValue(':id', $id);
	$query->execute() or error(db_error($query));

	$result = $query->fetch(PDO::FETCH_ASSOC);

	if ($result) {
		$ip = $result['ip'];
	} else {
		error(_('Could not find that post.'));
	}

	if (isTorIp($ip) === false && filter_var($ip, FILTER_VALIDATE_IP) === false) {
		error("Invalid IP address.");
	}

	if (isset($_POST['ban_id'], $_POST['unban'])) {
		if (!hasPermission(Vi::$config['mod']['unban'])) {
			error(Vi::$config['error']['noaccess']);
		}

		Bans::delete($_POST['ban_id'], true);

		header('Location: ?/IP_less/' . $b . '/' . $id . '#bans', true, Vi::$config['redirect_http']);
		return;
	}

	if (isset($_POST['note'])) {
		if (!hasPermission(Vi::$config['mod']['create_notes'])) {
			error(Vi::$config['error']['noaccess']);
		}

		$_POST['note'] = escape_markup_modifiers($_POST['note']);
		markup($_POST['note']);
		$query = prepare('INSERT INTO ``ip_notes`` VALUES (NULL, :ip, :mod, :time, :body)');
		$query->bindValue(':ip', $ip);
		$query->bindValue(':mod', Vi::$mod['id']);
		$query->bindValue(':time', time());
		$query->bindValue(':body', $_POST['note']);
		$query->execute() or error(db_error($query));

		modLog("Added a note for " . ip_link($ip));

		header('Location: ?/IP_less/' . $b . '/' . $id . '#notes', true, Vi::$config['redirect_http']);
		return;
	}

	$args          = array();
	$args['ip']    = $ip;
	$args['posts'] = array();

	if (Vi::$config['mod']['dns_lookup']) {
		$args['hostname'] = rDNS($ip);
	}

	$query = prepare(sprintf('SELECT * FROM ``posts_%s`` WHERE `ip` = :ip ORDER BY `sticky` DESC, `id` DESC LIMIT :limit', $b));
	$query->bindValue(':ip', $ip);
	$query->bindValue(':limit', Vi::$config['mod']['ip_less_recentposts'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		if (!$post['thread']) {
			$po = new Thread($post, '?/', Vi::$mod, false);
		} else {
			$po = new Post($post, '?/', Vi::$mod);
		}

		if (!isset($args['posts'][$b])) {
			$args['posts'][$b] = array('board' => $b, 'posts' => array());
		}

		$args['posts'][$b]['posts'][] = $po->build(true);
	}

	$args['boards'] = listBoards();
	$args['token']  = make_secure_link_token('ban');

	if (hasPermission(Vi::$config['mod']['view_ban'])) {
		$args['bans'] = Bans::find($ip, false, true);
	}

	if (hasPermission(Vi::$config['mod']['view_notes'])) {
		$query = prepare("SELECT ``ip_notes``.*, `username` FROM ``ip_notes`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` WHERE `ip` = :ip ORDER BY `time` DESC");
		$query->bindValue(':ip', $ip);
		$query->execute() or error(db_error($query));
		$args['notes'] = $query->fetchAll(PDO::FETCH_ASSOC);
	}

	if (hasPermission(Vi::$config['mod']['modlog_ip'])) {
		$query = prepare("SELECT `username`, `mod`, `ip`, `board`, `time`, `text` FROM ``modlogs`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` WHERE `text` LIKE :search ORDER BY `time` DESC LIMIT 50");
		$query->bindValue(':search', '%' . $ip . '%');
		$query->execute() or error(db_error($query));
		$args['logs'] = $query->fetchAll(PDO::FETCH_ASSOC);
	} else {
		$args['logs'] = array();
	}

	$args['security_token'] = make_secure_link_token('IP_less/' . $b . '/' . $id);

	mod_page(sprintf('%s: %s', _('IP'), less_ip($ip, $b)), 'mod/view_ip_less.html', $args);
}

function mod_ban() {
	if (!hasPermission(Vi::$config['mod']['ban'])) {
		error(Vi::$config['error']['noaccess']);
	}

	if (!isset($_POST['ip'], $_POST['reason'], $_POST['length'], $_POST['board'])) {
		mod_page(_('New ban'), 'mod/ban_form.html', array('token' => make_secure_link_token('ban')));
		return;
	}

	require_once 'inc/mod/ban.php';

	Bans::new_ban($_POST['ip'], $_POST['reason'], $_POST['length'], $_POST['board'] == '*' ? false : $_POST['board']);

	if (isset($_POST['redirect'])) {
		header('Location: ' . $_POST['redirect'], true, Vi::$config['redirect_http']);
	} else {
		header('Location: ?/', true, Vi::$config['redirect_http']);
	}

}

function mod_bans() {
	if (!hasPermission(Vi::$config['mod']['view_banlist'])) {
		error(Vi::$config['error']['noaccess']);
	}

	if (isset($_POST['unban'])) {
		if (!hasPermission(Vi::$config['mod']['unban'])) {
			error(Vi::$config['error']['noaccess']);
		}

		$unban = array();
		foreach ($_POST as $name => $unused) {
			if (preg_match('/^ban_(\d+)$/', $name, $match)) {
				$unban[] = $match[1];
			}

		}
		if (isset(Vi::$config['mod']['unban_limit']) && Vi::$config['mod']['unban_limit'] && count($unban) > Vi::$config['mod']['unban_limit']) {
			error(sprintf(Vi::$config['error']['toomanyunban'], Vi::$config['mod']['unban_limit'], count($unban)));
		}

		foreach ($unban as $id) {
			Bans::delete($id, true, Vi::$mod['boards'], true);
		}
		rebuildThemes('bans');
		header('Location: ?/bans', true, Vi::$config['redirect_http']);
		return;
	}

	mod_page(_('Ban list'), 'mod/ban_list.html', array(
		'mod'        => Vi::$mod,
		'boards'     => json_encode(Vi::$mod['boards']),
		'token'      => make_secure_link_token('bans'),
		'token_json' => make_secure_link_token('bans.json'),
	));
}

function mod_bans_json() {
	if (!hasPermission(Vi::$config['mod']['ban'])) {
		error(Vi::$config['error']['noaccess']);
	}

	// Compress the json for faster loads
	if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) {
		ob_start("ob_gzhandler");
	}

	Bans::stream_json(false, !hasPermission(Vi::$config['mod']['show_ip']), !hasPermission(Vi::$config['mod']['view_banstaff']), Vi::$mod['boards']);
}

function mod_ban_appeals() {
	if (!hasPermission(Vi::$config['mod']['view_ban_appeals'])) {
		error(Vi::$config['error']['noaccess']);
	}

	// Remove stale ban appeals
	query("DELETE FROM ``ban_appeals`` WHERE NOT EXISTS (SELECT 1 FROM ``bans`` WHERE `ban_id` = ``bans``.`id`)")
	or error(db_error());

	if (isset($_POST['appeal_id']) && (isset($_POST['unban']) || isset($_POST['deny']))) {
		if (!hasPermission(Vi::$config['mod']['ban_appeals'])) {
			error(Vi::$config['error']['noaccess']);
		}

		$query = query("SELECT *, ``ban_appeals``.`id` AS `id` FROM ``ban_appeals``
			LEFT JOIN ``bans`` ON `ban_id` = ``bans``.`id`
			WHERE ``ban_appeals``.`id` = " . (int) $_POST['appeal_id']) or error(db_error());
		if (!$ban = $query->fetch(PDO::FETCH_ASSOC)) {
			error(_('Ban appeal not found!'));
		}

		if (!in_array($ban['board'], Vi::$mod['boards']) && Vi::$mod['boards'][0] != '*') {
			error(Vi::$config['error']['noaccess']);
		}

		$ban['mask'] = Bans::range_to_string(array($ban['ipstart'], $ban['ipend']));

		if (isset($_POST['unban'])) {
			modLog('Accepted ban appeal #' . $ban['id'] . ' for ' . $ban['mask']);
			Bans::delete($ban['ban_id'], true);
			query("DELETE FROM ``ban_appeals`` WHERE `id` = " . $ban['id']) or error(db_error());
		} else {
			modLog('Denied ban appeal #' . $ban['id'] . ' for ' . $ban['mask']);
			query("UPDATE ``ban_appeals`` SET `denied` = 1 WHERE `id` = " . $ban['id']) or error(db_error());
		}

		header('Location: ?/ban-appeals', true, Vi::$config['redirect_http']);
		return;
	}

	$local = (Vi::$mod['type'] < GLOBALVOLUNTEER);

	$query = prepare("SELECT *, ``ban_appeals``.`id` AS `id` FROM ``ban_appeals``
		LEFT JOIN ``bans`` ON `ban_id` = ``bans``.`id`
		LEFT JOIN ``mods`` ON ``bans``.`creator` = ``mods``.`id`
		WHERE `denied` != 1 " . ($local ? " AND ``bans``.`board` = :board " : "") . " ORDER BY `time`");
	if ($local) {
		$query->bindValue(':board', Vi::$mod['boards'][0]);
	}
	$query->execute() or error(db_error());

	$ban_appeals = $query->fetchAll(PDO::FETCH_ASSOC);
	foreach ($ban_appeals as &$ban) {
		if ($ban['post']) {
			$ban['post'] = json_decode($ban['post'], true);
		}

		$ban['mask'] = Bans::range_to_string(array($ban['ipstart'], $ban['ipend']));

		if ($ban['post'] && isset($ban['post']['board'], $ban['post']['id'])) {
			openBoard($ban['post']['board']);

			if ($ban['post']['thread']) {
				$po          = new Post($ban['post']);
				$ban['post'] = $po->build(true);
			} else {
				$po          = new Thread($ban['post'], null, false, false);
				$ban['post'] = $po->build(true);
			}
		}
	}

	mod_page(_('Ban appeals'), 'mod/ban_appeals.html', array(
		'ban_appeals' => $ban_appeals,
		'token'       => make_secure_link_token('ban-appeals'),
	));
}

function mod_lock($board, $unlock, $post) {
	if (!openBoard($board)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!hasPermission(Vi::$config['mod']['lock'], $board)) {
		error(Vi::$config['error']['noaccess']);
	}

	$query = prepare(sprintf('UPDATE ``posts_%s`` SET `locked` = :locked WHERE `id` = :id AND `thread` IS NULL', $board));
	$query->bindValue(':id', $post);
	$query->bindValue(':locked', $unlock ? 0 : 1);
	$query->execute() or error(db_error($query));
	if ($query->rowCount()) {
		modLog(($unlock ? 'Unlocked' : 'Locked') . " thread #{$post}");
		buildThread($post);
		buildIndex();
	}

	if (Vi::$config['mod']['dismiss_reports_on_lock']) {
		$query = prepare('DELETE FROM ``reports`` WHERE `board` = :board AND `post` = :id');
		$query->bindValue(':board', $board);
		$query->bindValue(':id', $post);
		$query->execute() or error(db_error($query));
	}

	header('Location: ?/' . sprintf(Vi::$config['board_path'], $board) . Vi::$config['file_index'], true, Vi::$config['redirect_http']);

	if ($unlock) {
		event('unlock', $post);
	} else {
		event('lock', $post);
	}

}

function mod_sticky($board, $unsticky, $post) {
	if (!openBoard($board)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!hasPermission(Vi::$config['mod']['sticky'], $board)) {
		error(Vi::$config['error']['noaccess']);
	}

	$query = prepare(sprintf('UPDATE ``posts_%s`` SET `sticky` = :sticky WHERE `id` = :id AND `thread` IS NULL', $board));
	$query->bindValue(':id', $post);
	$query->bindValue(':sticky', $unsticky ? 0 : 1);
	$query->execute() or error(db_error($query));
	if ($query->rowCount()) {
		modLog(($unsticky ? 'Unstickied' : 'Stickied') . " thread #{$post}");
		buildThread($post);
		buildIndex();
	}

	header('Location: ?/' . sprintf(Vi::$config['board_path'], $board) . Vi::$config['file_index'], true, Vi::$config['redirect_http']);
}

function mod_cycle($board, $uncycle, $post) {
	if (!openBoard($board)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!hasPermission(Vi::$config['mod']['cycle'], $board)) {
		error(Vi::$config['error']['noaccess']);
	}

	$query = prepare(sprintf('UPDATE ``posts_%s`` SET `cycle` = :cycle WHERE `id` = :id AND `thread` IS NULL', $board));
	$query->bindValue(':id', $post);
	$query->bindValue(':cycle', $uncycle ? 0 : 1);
	$query->execute() or error(db_error($query));
	if ($query->rowCount()) {
		modLog(($uncycle ? 'Made not cyclical' : 'Made cyclical') . " thread #{$post}");
		buildThread($post);
		buildIndex();
	}

	header('Location: ?/' . sprintf(Vi::$config['board_path'], $board) . Vi::$config['file_index'], true, Vi::$config['redirect_http']);
}

function mod_bumplock($board, $unbumplock, $post) {
	if (!openBoard($board)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!hasPermission(Vi::$config['mod']['bumplock'], $board)) {
		error(Vi::$config['error']['noaccess']);
	}

	$query = prepare(sprintf('UPDATE ``posts_%s`` SET `sage` = :bumplock WHERE `id` = :id AND `thread` IS NULL', $board));
	$query->bindValue(':id', $post);
	$query->bindValue(':bumplock', $unbumplock ? 0 : 1);
	$query->execute() or error(db_error($query));
	if ($query->rowCount()) {
		modLog(($unbumplock ? 'Unbumplocked' : 'Bumplocked') . " thread #{$post}");
		buildThread($post);
		buildIndex();
	}

	header('Location: ?/' . sprintf(Vi::$config['board_path'], $board) . Vi::$config['file_index'], true, Vi::$config['redirect_http']);
}

function mod_move_reply($originBoard, $postID) {
	if (!openBoard($originBoard)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!hasPermission(Vi::$config['mod']['move'], $originBoard)) {
		error(Vi::$config['error']['noaccess']);
	}

	$query = prepare(sprintf('SELECT * FROM ``posts_%s`` WHERE `id` = :id', $originBoard));
	$query->bindValue(':id', $postID);
	$query->execute() or error(db_error($query));
	if (!$post = $query->fetch(PDO::FETCH_ASSOC)) {
		error(Vi::$config['error']['404']);
	}

	if (isset($_POST['board'])) {
		$targetBoard = $_POST['board'];

		if ($_POST['target_thread']) {
			$query = prepare(sprintf('SELECT * FROM ``posts_%s`` WHERE `id` = :id', $targetBoard));
			$query->bindValue(':id', $_POST['target_thread']);
			$query->execute() or error(db_error($query)); // If it fails, thread probably does not exist
			$post['op']     = false;
			$post['thread'] = $_POST['target_thread'];
		} else {
			$post['op'] = true;
		}

		if ($post['files']) {
			$post['files']    = json_decode($post['files'], TRUE);
			$post['has_file'] = true;
			foreach ($post['files'] as $i => &$file) {
				$file['file_path']  = sprintf(Vi::$config['board_path'], Vi::$config['dir']['img_root'] . Vi::$board['uri']) . Vi::$config['dir']['img'] . $file['file'];
				$file['thumb_path'] = sprintf(Vi::$config['board_path'], Vi::$config['dir']['img_root'] . Vi::$board['uri']) . Vi::$config['dir']['thumb'] . $file['thumb'];
			}
		} else {
			$post['has_file'] = false;
		}

		// allow thread to keep its same traits (stickied, locked, etc.)
		$post['mod'] = true;

		if (!openBoard($targetBoard)) {
			error(Vi::$config['error']['noboard']);
		}

		// create the new post
		$newID = post($post);

		if ($post['has_file']) {
			foreach ($post['files'] as $i => &$file) {
				// move the image
				rename($file['file_path'], sprintf(Vi::$config['board_path'], Vi::$config['dir']['img_root'] . Vi::$board['uri']) . Vi::$config['dir']['img'] . $file['file']);
				if ($file['thumb'] != 'spoiler') {
					//trying to move/copy the spoiler thumb raises an error
					rename($file['thumb_path'], sprintf(Vi::$config['board_path'], Vi::$config['dir']['img_root'] . Vi::$board['uri']) . Vi::$config['dir']['thumb'] . $file['thumb']);
				}
			}
		}

		// build index
		buildIndex();
		// build new thread
		buildThread($newID);

		// trigger themes
		rebuildThemes('post', $targetBoard);
		// mod log
		modLog("Moved post #${postID} to " . sprintf(Vi::$config['board_abbreviation'], $targetBoard) . " (#${newID})", $originBoard);

		// return to original board
		openBoard($originBoard);

		// delete original post
		deletePost($postID);
		buildIndex();

		// open target board for redirect
		openBoard($targetBoard);

		// Find new thread on our target board
		$query = prepare(sprintf('SELECT thread FROM ``posts_%s`` WHERE `id` = :id', $targetBoard));
		$query->bindValue(':id', $newID);
		$query->execute() or error(db_error($query));
		$post = $query->fetch(PDO::FETCH_ASSOC);

		// redirect
		header('Location: ?/' . sprintf(Vi::$config['board_path'], Vi::$board['uri']) . Vi::$config['dir']['res'] . sprintf(Vi::$config['file_page'], $post['thread'] ? $post['thread'] : $newID) . '#' . $newID, true, Vi::$config['redirect_http']);
	} else {
		$boards = listBoards();

		$security_token = make_secure_link_token($originBoard . '/move_reply/' . $postID);

		mod_page(_('Move reply'), 'mod/move_reply.html', array('post' => $postID, 'board' => $originBoard, 'boards' => $boards, 'token' => $security_token));
	}
}

function mod_move($originBoard, $postID) {
	if (!openBoard($originBoard)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!hasPermission(Vi::$config['mod']['move'], $originBoard)) {
		error(Vi::$config['error']['noaccess']);
	}

	$query = prepare(sprintf('SELECT * FROM ``posts_%s`` WHERE `id` = :id AND `thread` IS NULL', $originBoard));
	$query->bindValue(':id', $postID);
	$query->execute() or error(db_error($query));
	if (!$post = $query->fetch(PDO::FETCH_ASSOC)) {
		error(Vi::$config['error']['404']);
	}

	if (isset($_POST['board'])) {
		$targetBoard = $_POST['board'];
		$shadow      = isset($_POST['shadow']);

		if ($targetBoard === $originBoard) {
			error(_('Target and source board are the same.'));
		}

		// copy() if leaving a shadow thread behind; else, rename().
		$clone = $shadow ? 'copy' : 'rename';

		// indicate that the post is a thread
		$post['op'] = true;

		if ($post['files']) {
			$post['files']    = json_decode($post['files'], TRUE);
			$post['has_file'] = true;
			foreach ($post['files'] as $i => &$file) {
				if ($file['file'] === 'deleted') {
					continue;
				}

				$file['file_path']  = sprintf(Vi::$config['board_path'], Vi::$config['dir']['img_root'] . Vi::$board['uri']) . Vi::$config['dir']['img'] . $file['file'];
				$file['thumb_path'] = sprintf(Vi::$config['board_path'], Vi::$config['dir']['img_root'] . Vi::$board['uri']) . Vi::$config['dir']['thumb'] . $file['thumb'];
			}
		} else {
			$post['has_file'] = false;
		}

		// allow thread to keep its same traits (stickied, locked, etc.)
		$post['mod'] = true;

		if (!openBoard($targetBoard)) {
			error(Vi::$config['error']['noboard']);
		}

		// create the new thread
		$newID = post($post);

		if ($post['has_file']) {
			// copy image
			foreach ($post['files'] as $i => &$file) {
				if ($file['file'] !== 'deleted') {
					$clone($file['file_path'], sprintf(Vi::$config['board_path'], Vi::$config['dir']['img_root'] . Vi::$board['uri']) . Vi::$config['dir']['img'] . $file['file']);
				}

				if (isset($file['thumb']) && !in_array($file['thumb'], array('spoiler', 'deleted', 'file'))) {
					$clone($file['thumb_path'], sprintf(Vi::$config['board_path'], Vi::$config['dir']['img_root'] . Vi::$board['uri']) . Vi::$config['dir']['thumb'] . $file['thumb']);
				}

			}
		}

		// go back to the original board to fetch replies
		openBoard($originBoard);

		$query = prepare(sprintf('SELECT * FROM ``posts_%s`` WHERE `thread` = :id ORDER BY `id`', $originBoard));
		$query->bindValue(':id', $postID, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		$replies = array();

		while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
			$post['mod']    = true;
			$post['thread'] = $newID;

			if ($post['files']) {
				$post['files']    = json_decode($post['files'], TRUE);
				$post['has_file'] = true;
				foreach ($post['files'] as $i => &$file) {
					$file['file_path']  = sprintf(Vi::$config['board_path'], Vi::$config['dir']['img_root'] . Vi::$board['uri']) . Vi::$config['dir']['img'] . $file['file'];
					$file['thumb_path'] = sprintf(Vi::$config['board_path'], Vi::$config['dir']['img_root'] . Vi::$board['uri']) . Vi::$config['dir']['thumb'] . $file['thumb'];
				}
			} else {
				$post['has_file'] = false;
			}

			$replies[] = $post;
		}

		$newIDs = array($postID => $newID);

		openBoard($targetBoard);

		foreach ($replies as &$post) {
			$query = prepare('SELECT `target` FROM ``cites`` WHERE `target_board` = :board AND `board` = :board AND `post` = :post');
			$query->bindValue(':board', $originBoard);
			$query->bindValue(':post', $post['id'], PDO::PARAM_INT);
			$query->execute() or error(db_error($qurey));

			// correct >>X links
			while ($cite = $query->fetch(PDO::FETCH_ASSOC)) {
				if (isset($newIDs[$cite['target']])) {
					$post['body_nomarkup'] = preg_replace(
						'/(>>(>\/' . preg_quote($originBoard, '/') . '\/)?)' . preg_quote($cite['target'], '/') . '/',
						'>>' . $newIDs[$cite['target']],
						$post['body_nomarkup']);

					$post['body'] = $post['body_nomarkup'];
				}
			}

			$post['body'] = $post['body_nomarkup'];

			$post['op']            = false;
			$post['tracked_cites'] = markup($post['body'], true);

			if ($post['has_file']) {
				// copy image
				foreach ($post['files'] as $i => &$file) {
					if ($file['file'] !== 'deleted') {
						$clone($file['file_path'], sprintf(Vi::$config['board_path'], Vi::$config['dir']['img_root'] . Vi::$board['uri']) . Vi::$config['dir']['img'] . $file['file']);
					}

					if (isset($file['thumb']) && !in_array($file['thumb'], array('spoiler', 'deleted', 'file'))) {
						$clone($file['thumb_path'], sprintf(Vi::$config['board_path'], Vi::$config['dir']['img_root'] . Vi::$board['uri']) . Vi::$config['dir']['thumb'] . $file['thumb']);
					}

				}
			}
			// insert reply
			$newIDs[$post['id']] = $newPostID = post($post);

			if (!empty($post['tracked_cites'])) {
				$insert_rows = array();
				foreach ($post['tracked_cites'] as $cite) {
					$insert_rows[] = '(' .
					Vi::$pdo->quote(Vi::$board['uri']) . ', ' . $newPostID . ', ' .
					Vi::$pdo->quote($cite[0]) . ', ' . (int) $cite[1] . ')';
				}
				query('INSERT INTO ``cites`` VALUES ' . implode(', ', $insert_rows)) or error(db_error());
			}
		}

		modLog("Moved thread #${postID} to " . sprintf(Vi::$config['board_abbreviation'], $targetBoard) . " (#${newID})", $originBoard);

		// build new thread
		buildThread($newID);

		clean();
		buildIndex();

		// trigger themes
		rebuildThemes('post', $targetBoard);

		// return to original board
		openBoard($originBoard);

		if ($shadow) {
			// lock old thread
			$query = prepare(sprintf('UPDATE ``posts_%s`` SET `locked` = 1 WHERE `id` = :id', $originBoard));
			$query->bindValue(':id', $postID, PDO::PARAM_INT);
			$query->execute() or error(db_error($query));

			// leave a reply, linking to the new thread
			$post = array(
				'mod'      => true,
				'subject'  => '',
				'email'    => '',
				'name'     => (!Vi::$config['mod']['shadow_name'] ? Vi::$config['anonymous'] : Vi::$config['mod']['shadow_name']),
				'capcode'  => Vi::$config['mod']['shadow_capcode'],
				'trip'     => '',
				'password' => '',
				'has_file' => false,
				// attach to original thread
				'thread'   => $postID,
				'op'       => false,
			);

			$post['body'] = $post['body_nomarkup'] = sprintf(Vi::$config['mod']['shadow_mesage'], '>>>/' . $targetBoard . '/' . $newID);

			markup($post['body']);

			$botID = post($post);
			buildThread($postID);

			buildIndex();

			header('Location: ?/' . sprintf(Vi::$config['board_path'], $originBoard) . Vi::$config['dir']['res'] . sprintf(Vi::$config['file_page'], $postID) .
				'#' . $botID, true, Vi::$config['redirect_http']);
		} else {
			deletePost($postID);
			buildIndex();

			openBoard($targetBoard);
			header('Location: ?/' . sprintf(Vi::$config['board_path'], Vi::$board['uri']) . Vi::$config['dir']['res'] . sprintf(Vi::$config['file_page'], $newID), true, Vi::$config['redirect_http']);
		}
	}

	$boards = listBoards();
	if (count($boards) <= 1) {
		error(_('Impossible to move thread; there is only one board.'));
	}

	$security_token = make_secure_link_token($originBoard . '/move/' . $postID);

	mod_page(_('Move thread'), 'mod/move.html', array('post' => $postID, 'board' => $originBoard, 'boards' => $boards, 'token' => $security_token));
}

function mod_ban_post($board, $delete, $post, $token = false) {
	if (!openBoard($board)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!hasPermission(Vi::$config['mod']['delete'], $board)) {
		error(Vi::$config['error']['noaccess']);
	}

	$security_token = make_secure_link_token($board . '/ban/' . $post);

	$query = prepare(sprintf('SELECT ' . (Vi::$config['ban_show_post'] ? '*' : '`ip`, `thread`') .
		' FROM ``posts_%s`` WHERE `id` = :id', $board));
	$query->bindValue(':id', $post);
	$query->execute() or error(db_error($query));
	if (!$_post = $query->fetch(PDO::FETCH_ASSOC)) {
		error(Vi::$config['error']['404']);
	}

	$thread = $_post['thread'];
	$ip     = $_post['ip'];
	$tor    = checkDNSBL($ip);

	if (isset($_POST['new_ban'], $_POST['reason'], $_POST['length'], $_POST['board'])) {
		require_once 'inc/mod/ban.php';

		if (isset($_POST['ip'])) {
			$ip = $_POST['ip'];
		}

		if (isset($_POST['range'])) {
			$ip = $ip . $_POST['range'];
		}

		Bans::new_ban($ip, $_POST['reason'], $_POST['length'], $_POST['board'] == '*' ? false : $_POST['board'],
			false, Vi::$config['ban_show_post'] ? $_post : false);

		if (isset($_POST['public_message'], $_POST['message'])) {
			// public ban message
			$length_english   = Bans::parse_time($_POST['length']) ? 'for ' . until(Bans::parse_time($_POST['length'])) : 'permanently';
			$_POST['message'] = preg_replace('/[\r\n]/', '', $_POST['message']);
			$_POST['message'] = str_replace('%length%', $length_english, $_POST['message']);
			$_POST['message'] = str_replace('%LENGTH%', strtoupper($length_english), $_POST['message']);
			$query            = prepare(sprintf('UPDATE ``posts_%s`` SET `body_nomarkup` = CONCAT(`body_nomarkup`, :body_nomarkup) WHERE `id` = :id', $board));
			$query->bindValue(':id', $post);
			$query->bindValue(':body_nomarkup', sprintf("\n<tinyboard ban message>%s</tinyboard>", utf8tohtml($_POST['message'])));
			$query->execute() or error(db_error($query));
			rebuildPost($post);

			modLog("Attached a public ban message to post #{$post}: " . utf8tohtml($_POST['message']));
			buildThread($thread ? $thread : $post);
			buildIndex();
		} elseif (isset($_POST['delete']) && (int) $_POST['delete']) {
			// Delete post
			deletePost($post);
			modLog("Deleted post #{$post}");
			// Rebuild board
			buildIndex();
			// Rebuild themes
			rebuildThemes('post-delete', $board);
		}

		header('Location: ?/' . sprintf(Vi::$config['board_path'], $board) . Vi::$config['file_index'], true, Vi::$config['redirect_http']);
	}

	$args = array(
		'ip'      => $ip,
		'hide_ip' => !hasPermission(Vi::$config['mod']['show_ip'], $board),
		'post'    => $post,
		'board'   => $board,
		'tor'     => $tor,
		'delete'  => (bool) $delete,
		'boards'  => listBoards(),
		'token'   => $security_token,
	);

	mod_page(_('New ban'), 'mod/ban_form.html', $args);
}

function mod_edit_post($board, $edit_raw_html, $postID) {
	if (!openBoard($board)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!hasPermission(Vi::$config['mod']['editpost'], $board)) {
		error(Vi::$config['error']['noaccess']);
	}

	if ($edit_raw_html && !hasPermission(Vi::$config['mod']['rawhtml'], $board)) {
		error(Vi::$config['error']['noaccess']);
	}

	$security_token = make_secure_link_token($board . '/edit' . ($edit_raw_html ? '_raw' : '') . '/' . $postID);

	$query = prepare(sprintf('SELECT * FROM ``posts_%s`` WHERE `id` = :id', $board));
	$query->bindValue(':id', $postID);
	$query->execute() or error(db_error($query));

	if (!$post = $query->fetch(PDO::FETCH_ASSOC)) {
		error(Vi::$config['error']['404']);
	}

	if (isset($_POST['name'], $_POST['email'], $_POST['subject'], $_POST['body'])) {
		$trip = isset($_POST['remove_trip']) ? ' `trip` = NULL,' : '';

		// Remove any modifiers they may have put in
		$_POST['body'] = remove_modifiers($_POST['body']);

		// Add back modifiers in the original post
		$modifiers = extract_modifiers($post['body_nomarkup']);
		foreach ($modifiers as $key => $value) {
			if($key == 'raw html' && !hasPermission(Vi::$config['mod']['rawhtml'], $board)) {
				continue;
			}
			$_POST['body'] .= "<tinyboard $key>$value</tinyboard>";
		}

		// Handle embed edits...
		foreach (Vi::$config['embedding'] as &$embed) {
			if (preg_match($embed[0], $_POST['embed'])) {
				$embed_link = $_POST['embed'];
			}
		}

		if ($edit_raw_html) {
			$query = prepare(sprintf('UPDATE ``posts_%s`` SET `name` = :name,' . $trip . ' `email` = :email, `subject` = :subject, `body` = :body, `body_nomarkup` = :body_nomarkup, `embed` = :embed, `edited_at` = UNIX_TIMESTAMP(NOW()) WHERE `id` = :id', $board));
		} else {
			$query = prepare(sprintf('UPDATE ``posts_%s`` SET `name` = :name,' . $trip . ' `email` = :email, `subject` = :subject, `body_nomarkup` = :body, `embed` = :embed, `edited_at` = UNIX_TIMESTAMP(NOW()) WHERE `id` = :id', $board));
		}

		$query->bindValue(':id', $postID);
		$query->bindValue(':name', $_POST['name'] ? $_POST['name'] : Vi::$config['anonymous']);
		$query->bindValue(':email', $_POST['email']);
		$query->bindValue(':subject', $_POST['subject']);
		$query->bindValue(':body', $_POST['body']);
		if ($edit_raw_html) {
			$body_nomarkup = $_POST['body'] . "\n<tinyboard raw html>1</tinyboard>";
			$query->bindValue(':body_nomarkup', $body_nomarkup);
		}
		if (isset($embed_link)) {
			$query->bindValue(':embed', $embed_link);
		} else {
			$query->bindValue(':embed', NULL, PDO::PARAM_NULL);
		}
		$query->execute() or error(db_error($query));

		if (Vi::$config['clean']['edits_remove_local'] || Vi::$config['clean']['edits_remove_global']) {

			$query_global     = "`clean_global` = :clean";
			$query_global_mod = "`clean_global_mod_id` = :mod";
			$query_local      = "`clean_local` = :clean";
			$query_local_mod  = "`clean_local_mod_id` = :mod";

			if (Vi::$config['clean']['edits_remove_local'] && Vi::$config['clean']['edits_remove_global']) {
				$query = prepare("UPDATE `post_clean` SET {$query_global}, {$query_global_mod}, {$query_local}, {$query_local_mod} WHERE `board_id` = :board AND `post_id` = :post");
			} else if (Vi::$config['clean']['edits_remove_global']) {
				$query = prepare("UPDATE `post_clean` SET {$query_global}, {$query_global_mod} WHERE `board_id` = :board AND `post_id` = :post");
			} else {
				$query = prepare("UPDATE `post_clean` SET {$query_local}, {$query_local_mod} WHERE `board_id` = :board AND `post_id` = :post");
			}

			$query->bindValue(':clean', false);
			$query->bindValue(':mod', NULL);
			$query->bindValue(':board', $board);
			$query->bindValue(':post', $postID);

			$query->execute() or error(db_error($query));

			// Finally, run a query to tidy up our records.
			$cleanup = prepare("DELETE FROM `post_clean` WHERE `clean_local` = FALSE AND `clean_global` = FALSE");
			$query->execute() or error(db_error($query));
		}

		if ($edit_raw_html) {
			modLog("Edited raw HTML of post #{$postID}");
		} else {
			modLog("Edited post #{$postID}");
			rebuildPost($postID);
		}

		buildIndex();

		rebuildThemes('post', $board);

		header('Location: ?/' . sprintf(Vi::$config['board_path'], $board) . Vi::$config['dir']['res'] . sprintf(Vi::$config['file_page'], $post['thread'] ? $post['thread'] : $postID) . '#' . $postID, true, Vi::$config['redirect_http']);
	} else {
		// Remove modifiers
		$post['body_nomarkup'] = remove_modifiers($post['body_nomarkup']);

		$post['body_nomarkup'] = utf8tohtml($post['body_nomarkup']);
		$post['body']          = utf8tohtml($post['body']);
		if (Vi::$config['minify_html']) {
			$post['body_nomarkup'] = str_replace("\n", '&#010;', $post['body_nomarkup']);
			$post['body']          = str_replace("\n", '&#010;', $post['body']);
			$post['body_nomarkup'] = str_replace("\r", '', $post['body_nomarkup']);
			$post['body']          = str_replace("\r", '', $post['body']);
			$post['body_nomarkup'] = str_replace("\t", '&#09;', $post['body_nomarkup']);
			$post['body']          = str_replace("\t", '&#09;', $post['body']);
		}

		$preview = new Post($post);
		$html    = $preview->build(true);

		mod_page(_('Edit post'), 'mod/edit_post_form.html', array('token' => $security_token, 'board' => $board, 'raw' => $edit_raw_html, 'post' => $post, 'preview' => $html));
	}
}

function mod_delete($board, $post) {
	if (!openBoard($board)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!hasPermission(Vi::$config['mod']['delete'], $board)) {
		error(Vi::$config['error']['noaccess']);
	}

	// Delete post
	deletePost($post);
	// Record the action
	modLog("Deleted post #{$post}");
	// Rebuild board
	buildIndex();
	// Rebuild themes
	rebuildThemes('post-delete', $board);
	// Redirect
	header('Location: ?/' . sprintf(Vi::$config['board_path'], $board) . Vi::$config['file_index'], true, Vi::$config['redirect_http']);
}

function mod_deletefile($board, $post, $file) {
	if (!openBoard($board)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!hasPermission(Vi::$config['mod']['deletefile'], $board)) {
		error(Vi::$config['error']['noaccess']);
	}

	// Delete file
	deleteFile($post, TRUE, $file);
	// Record the action
	modLog("Deleted file from post #{$post}");

	// Rebuild board
	buildIndex();
	// Rebuild themes
	rebuildThemes('post-delete', $board);

	// Redirect
	header('Location: ?/' . sprintf(Vi::$config['board_path'], $board) . Vi::$config['file_index'], true, Vi::$config['redirect_http']);
}

function mod_banhashfile($board, $post, $file) {
	if (!openBoard($board)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!hasPermission(Vi::$config['mod']['deletefile'], $board)) {
		error(Vi::$config['error']['noaccess']);
	}

	// Delete file thumbnail
	$query = prepare(sprintf("SELECT `files`, `thread` FROM ``posts_%s`` WHERE id = :id", $board));
	$query->bindValue(':id', $post, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$result = $query->fetch(PDO::FETCH_ASSOC);
	$files  = json_decode($result['files']);

	if(!isset($files[$file])) {
		error(_('Banhash: File not exists'));
	}

	$hash = $files[$file]->hash;
	$type_hash = 'md5';
	if($files[$file]->is_an_image) {
		include_once 'inc/lib/imagehash/imagehash.php';
		$hasher = new Jenssegers\ImageHash\ImageHash;
		$hash = $hasher->hash($files[$file]->file_path);
		$type_hash = 'imagehash';
	}

	$query = prepare('INSERT INTO ``filters`` VALUES (NULL, :board, :type, :reason, :value)');
	$query->bindValue(':board', $board);
	$query->bindValue(':type', $type_hash);
	$query->bindValue(':reason', "Add to filter hash file from post #{$post}");
	$query->bindValue(':value', $hash);
	$query->execute() or error(db_error($query));
	
	// Record the action
	modLog("Add to filter hash file from post #{$post}");

	// Redirect
	header('Location: ?/' . sprintf(Vi::$config['board_path'], $board) . Vi::$config['file_index'], true, Vi::$config['redirect_http']);
}

function mod_spoiler_image($board, $post, $file) {
	if (!openBoard($board)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!hasPermission(Vi::$config['mod']['spoilerimage'], $board)) {
		error(Vi::$config['error']['noaccess']);
	}

	// Delete file thumbnail
	$query = prepare(sprintf("SELECT `files`, `thread` FROM ``posts_%s`` WHERE id = :id", $board));
	$query->bindValue(':id', $post, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$result = $query->fetch(PDO::FETCH_ASSOC);
	$files  = json_decode($result['files']);

	$size_spoiler_image = @getimagesize(Vi::$config['spoiler_image']);
	file_unlink(Vi::$config['dir']['img_root'] . $board . '/' . Vi::$config['dir']['thumb'] . $files[$file]->thumb);
	$files[$file]->thumb       = 'spoiler';
	$files[$file]->thumbwidth  = $size_spoiler_image[0];
	$files[$file]->thumbheight = $size_spoiler_image[1];

	// Make thumbnail spoiler
	$query = prepare(sprintf("UPDATE ``posts_%s`` SET `files` = :files WHERE `id` = :id", $board));
	$query->bindValue(':files', json_encode($files));
	$query->bindValue(':id', $post, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	// Record the action
	modLog("Spoilered file from post #{$post}");

	// Rebuild thread
	buildThread($result['thread'] ? $result['thread'] : $post);

	// Rebuild board
	buildIndex();

	// Rebuild themes
	rebuildThemes('post-delete', $board);

	// Redirect
	header('Location: ?/' . sprintf(Vi::$config['board_path'], $board) . Vi::$config['file_index'], true, Vi::$config['redirect_http']);
}

function mod_spoiler_images($board, $post) {
	if (!openBoard($board)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!hasPermission(Vi::$config['mod']['spoilerimage'], $board)) {
		error(Vi::$config['error']['noaccess']);
	}

	// Delete file thumbnails
	$query = prepare(sprintf("SELECT `files`, `thread` FROM ``posts_%s`` WHERE id = :id", $board));
	$query->bindValue(':id', $post, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$result = $query->fetch(PDO::FETCH_ASSOC);
	$files  = json_decode($result['files']);

	if(!count($files)) {
		error(_('That post has no files.'));
	}

	foreach ($files as $file => $name) {
		$size_spoiler_image = @getimagesize(Vi::$config['spoiler_image']);
		file_unlink(Vi::$config['dir']['img_root'] . $board . '/' . Vi::$config['dir']['thumb'] . $files[$file]->thumb);
		$files[$file]->thumb       = 'spoiler';
		$files[$file]->thumbwidth  = $size_spoiler_image[0];
		$files[$file]->thumbheight = $size_spoiler_image[1];
	}
	;
	// Make thumbnail spoiler
	$query = prepare(sprintf("UPDATE ``posts_%s`` SET `files` = :files WHERE `id` = :id", $board));
	$query->bindValue(':files', json_encode($files));
	$query->bindValue(':id', $post, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	// Record the action
	modLog("Spoilered file from post #{$post}");

	// Rebuild thread
	buildThread($result['thread'] ? $result['thread'] : $post);

	// Rebuild board
	buildIndex();

	// Rebuild themes
	rebuildThemes('post-delete', $board);

	// Redirect
	header('Location: ?/' . sprintf(Vi::$config['board_path'], $board) . Vi::$config['file_index'], true, Vi::$config['redirect_http']);
}

function mod_deletebyip($boardName, $post, $global = false) {
	$global = (bool) $global;

	if (!openBoard($boardName)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!$global && !hasPermission(Vi::$config['mod']['deletebyip'], $boardName)) {
		error(Vi::$config['error']['noaccess']);
	}

	if ($global && !hasPermission(Vi::$config['mod']['deletebyip_global'], $boardName)) {
		error(Vi::$config['error']['noaccess']);
	}

	// Find IP address
	$query = prepare(sprintf('SELECT `ip` FROM ``posts_%s`` WHERE `id` = :id', $boardName));
	$query->bindValue(':id', $post);
	$query->execute() or error(db_error($query));
	if (!$ip = $query->fetchColumn()) {
		error(Vi::$config['error']['invalidpost']);
	}

	$boards = $global ? listBoards() : array(array('uri' => $boardName));

	$query = '';
	foreach ($boards as $_board) {
		$query .= sprintf("SELECT `thread`, `id`, '%s' AS `board` FROM ``posts_%s`` WHERE `ip` = :ip UNION ALL ", $_board['uri'], $_board['uri']);
	}
	$query = preg_replace('/UNION ALL $/', '', $query);

	$query = prepare($query);
	$query->bindValue(':ip', $ip);
	$query->execute() or error(db_error($query));

	if ($query->rowCount() < 1) {
		error(Vi::$config['error']['invalidpost']);
	}

	@set_time_limit(Vi::$config['mod']['rebuild_timelimit']);

	$threads_to_rebuild = array();
	$threads_deleted    = array();
	while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		openBoard($post['board']);

		deletePost($post['id'], false, false);

		rebuildThemes('post-delete', Vi::$board['uri']);

		buildIndex();

		if ($post['thread']) {
			$threads_to_rebuild[$post['board']][$post['thread']] = true;
		} else {
			$threads_deleted[$post['board']][$post['id']] = true;
		}

	}

	foreach ($threads_to_rebuild as $_board => $_threads) {
		openBoard($_board);
		foreach ($_threads as $_thread => $_dummy) {
			if ($_dummy && !isset($threads_deleted[$_board][$_thread])) {
				buildThread($_thread);
			}

		}
		buildIndex();
	}

	// Record the action
	modLog("Deleted all posts by IP address: " . ip_link($ip));

	// Redirect
	header('Location: ?/' . sprintf(Vi::$config['board_path'], $boardName) . Vi::$config['file_index'], true, Vi::$config['redirect_http']);
}

function mod_user($uid) {
	if (!hasPermission(Vi::$config['mod']['editusers']) && !(hasPermission(Vi::$config['mod']['edit_profile']) && $uid == Vi::$mod['id'])) {
		error(Vi::$config['error']['noaccess']);
	}

	if (in_array(Vi::$mod['boards'][0], array('infinity', 'z'))) {
		error('This board has profile changing disabled.');
	}

	$query = prepare('SELECT * FROM ``mods`` WHERE `id` = :id');
	$query->bindValue(':id', $uid);
	$query->execute() or error(db_error($query));
	if (!$user = $query->fetch(PDO::FETCH_ASSOC)) {
		error(Vi::$config['error']['404']);
	}

	if (hasPermission(Vi::$config['mod']['editusers']) && isset($_POST['username'], $_POST['password'])) {
		if (isset($_POST['allboards'])) {
			$boards = array('*');
		} else {
			$_boards = listBoards();
			foreach ($_boards as &$board) {
				$board = $board['uri'];
			}

			$boards = array();
			foreach ($_POST as $name => $value) {
				if (preg_match('/^board_(' . Vi::$config['board_regex'] . ')$/u', $name, $matches) && in_array($matches[1], $_boards)) {
					$boards[] = $matches[1];
				}

			}
		}

		if (isset($_POST['delete'])) {
			if (!hasPermission(Vi::$config['mod']['deleteusers'])) {
				error(Vi::$config['error']['noaccess']);
			}

			$query = prepare('DELETE FROM ``mods`` WHERE `id` = :id');
			$query->bindValue(':id', $uid);
			$query->execute() or error(db_error($query));

			modLog('Deleted user ' . utf8tohtml($user['username']) . ' <small>(#' . $user['id'] . ')</small>');

			header('Location: ?/users', true, Vi::$config['redirect_http']);

			return;
		}

		if ($_POST['username'] == '') {
			error(sprintf(Vi::$config['error']['required'], 'username'));
		}

		$query = prepare('UPDATE ``mods`` SET `username` = :username, `boards` = :boards WHERE `id` = :id');
		$query->bindValue(':id', $uid);
		$query->bindValue(':username', $_POST['username']);
		$query->bindValue(':boards', implode(',', $boards));
		$query->execute() or error(db_error($query));

		if ($user['username'] !== $_POST['username']) {
			// account was renamed
			modLog('Renamed user "' . utf8tohtml($user['username']) . '" <small>(#' . $user['id'] . ')</small> to "' . utf8tohtml($_POST['username']) . '"');
		}

		if ($_POST['password'] != '') {
			list($version, $password) = crypt_password($_POST['password']);

			$query = prepare('UPDATE ``mods`` SET `password` = :password, `version` = :version WHERE `id` = :id');
			$query->bindValue(':id', $uid);
			$query->bindValue(':password', $password);
			$query->bindValue(':version', $version);
			$query->execute() or error(db_error($query));

			modLog('Changed password for ' . utf8tohtml($_POST['username']) . ' <small>(#' . $user['id'] . ')</small>');

			if ($uid == Vi::$mod['id']) {
				login($_POST['username'], $_POST['password']);
				setCookies();
			}
		}

		if (hasPermission(Vi::$config['mod']['manageusers'])) {
			header('Location: ?/users', true, Vi::$config['redirect_http']);
		} else {
			header('Location: ?/', true, Vi::$config['redirect_http']);
		}

		return;
	}

	if (hasPermission(Vi::$config['mod']['edit_profile']) && $uid == Vi::$mod['id']) {
		if (isset($_POST['password']) && $_POST['password'] != '') {
			list($version, $password) = crypt_password($_POST['password']);

			$query = prepare('UPDATE ``mods`` SET `password` = :password, `version` = :version WHERE `id` = :id');
			$query->bindValue(':id', $uid);
			$query->bindValue(':password', $password);
			$query->bindValue(':version', $version);
			$query->execute() or error(db_error($query));

			modLog('Changed own password');

			login($user['username'], $_POST['password']);
			setCookies();
		}

		if (isset($_POST['username']) && $user['username'] !== $_POST['username']) {
			if ($_POST['username'] == '') {
				error(sprintf(Vi::$config['error']['required'], 'username'));
			}

			if (!preg_match('/^[a-zA-Z0-9._]{1,30}$/', $_POST['username'])) {
				error(_('Invalid username'));
			}

			$query = prepare('SELECT `username`,`id` FROM ``mods``');
			$query->execute() or error(db_error($query));
			$users = $query->fetchAll(PDO::FETCH_ASSOC);

			foreach ($users as $i => $v) {
				if (strtolower($_POST['username']) == strtolower($v['username']) && $v['id'] !== $uid) {
					error(_('Refusing to change your username because another user is already using it.'));
				}
			}

			$query = prepare('UPDATE ``mods`` SET `username` = :username WHERE `id` = :id');
			$query->bindValue(':id', $uid);
			$query->bindValue(':username', $_POST['username']);
			$query->execute() or error(db_error($query));

			modLog('Renamed user "' . utf8tohtml($user['username']) . '" <small>(#' . $user['id'] . ')</small> to "' . utf8tohtml($_POST['username']) . '"');
		}

		if (isset($_POST['email']) && $user['email'] !== $_POST['email'] && (empty($_POST['email']) || filter_var($_POST['email'], FILTER_VALIDATE_EMAIL))) {
			// account was renamed
			$query = prepare('UPDATE ``mods`` SET `email` = :email WHERE `id` = :id');
			$query->bindValue(':id', $uid);
			$query->bindValue(':email', $_POST['email']);
			$query->execute() or error(db_error($query));

			modLog('Changed user\'s email "' . utf8tohtml($user['email']) . '" <small>(#' . $user['id'] . ')</small> to "' . utf8tohtml($_POST['email']) . '"');
		}

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			if (hasPermission(Vi::$config['mod']['manageusers'])) {
				header('Location: ?/users', true, Vi::$config['redirect_http']);
			} else {
				header('Location: ?/', true, Vi::$config['redirect_http']);
			}

			return;
		}
	}

	if (hasPermission(Vi::$config['mod']['modlog'])) {
		$query = prepare('SELECT * FROM ``modlogs`` WHERE `mod` = :id ORDER BY `time` DESC LIMIT 5');
		$query->bindValue(':id', $uid);
		$query->execute() or error(db_error($query));
		$log = $query->fetchAll(PDO::FETCH_ASSOC);
	} else {
		$log = array();
	}

	if (Vi::$mod['type'] >= ADMIN) {
		$boards = listBoards();
	} else {
		$boards2 = explode(',', $user['boards']);

		foreach ($boards2 as $string) {
			$boards[] = array("uri" => $string, "title" => _("My board"));
		}
	}

	$user['boards'] = explode(',', $user['boards']);

	mod_page(_('Edit user profile'), 'mod/user.html', array(
		'user'   => $user,
		'logs'   => $log,
		'boards' => $boards,
		'token'  => make_secure_link_token('users/' . $user['id']),
	));
}

function mod_user_new() {
	if (!hasPermission(Vi::$config['mod']['createusers'])) {
		error(Vi::$config['error']['noaccess']);
	}

	if (isset($_POST['username'], $_POST['password'], $_POST['type'])) {
		if ($_POST['username'] == '') {
			error(sprintf(Vi::$config['error']['required'], 'username'));
		}

		if ($_POST['password'] == '') {
			error(sprintf(Vi::$config['error']['required'], 'password'));
		}

		if (isset($_POST['allboards'])) {
			$boards = array('*');
		} else {
			$_boards = listBoards();
			foreach ($_boards as &$board) {
				$board = $board['uri'];
			}

			$boards = array();
			foreach ($_POST as $name => $value) {
				if (preg_match('/^board_(' . Vi::$config['board_regex'] . ')$/u', $name, $matches) && in_array($matches[1], $_boards)) {
					$boards[] = $matches[1];
				}

			}
		}

		$type = (int) $_POST['type'];
		if (!isset(Vi::$config['mod']['groups'][$type]) || $type == DISABLED) {
			error(sprintf(Vi::$config['error']['invalidfield'], 'type'));
		}

		$query = prepare('SELECT ``username``,``id`` FROM ``mods`` WHERE ``username`` = :username');
		$query->bindValue(':username', $_POST['username']);
		$query->execute() or error(db_error($query));
		$users = $query->fetch(PDO::FETCH_ASSOC);

		if (sizeof($users) > 0) {
			error(sprintf(_(Vi::$config['error']['modexists']), Vi::$config['file_mod'] . '?/users/' . $users['id']));
		}

		list($version, $password) = crypt_password($_POST['password']);

		$query = prepare('INSERT INTO ``mods`` VALUES (NULL, :username, :password, :version, :type, :boards, :email)');
		$query->bindValue(':username', $_POST['username']);
		$query->bindValue(':password', $password);
		$query->bindValue(':version', $version);
		$query->bindValue(':type', $type);
		$query->bindValue(':boards', implode(',', $boards));
		$query->bindValue(':email', (isset($_POST['email']) ? $_POST['email'] : ''));
		$query->execute() or error(db_error($query));

		$userID = Vi::$pdo->lastInsertId();

		modLog('Created a new user: ' . utf8tohtml($_POST['username']) . ' <small>(#' . $userID . ')</small>');

		header('Location: ?/users', true, Vi::$config['redirect_http']);
		return;
	}

	mod_page(_('New user'), 'mod/user.html', array('new' => true, 'boards' => listBoards(), 'token' => make_secure_link_token('users/new')));
}

function mod_users() {
	if (!hasPermission(Vi::$config['mod']['manageusers'])) {
		error(Vi::$config['error']['noaccess']);
	}

	$query = query("SELECT ``m``.`id`, ``m``.`username`, ``m``.`boards`, ``m``.`type`, ``m``.`email`,
	``ml``.`time` last, ``ml``.`text` action
	FROM ``mods`` AS m
	LEFT JOIN (
	    SELECT ml1.*
	    FROM ``modlogs`` AS ml1
	    JOIN (
	        SELECT `mod`, MAX(time) AS time
	        FROM ``modlogs``
	        GROUP BY `mod`
	    ) AS ml2 USING (`mod`, time)
	) AS ml ON m.id = ml.`mod` GROUP BY ``m``.`id` ORDER BY ``m``.`type` DESC;") or error(db_error());
	$users = $query->fetchAll(PDO::FETCH_ASSOC);

	foreach ($users as &$user) {
		$user['promote_token'] = make_secure_link_token("users/{$user['id']}/promote");
		$user['demote_token']  = make_secure_link_token("users/{$user['id']}/demote");
	}

	mod_page(sprintf('%s (%d)', _('Manage users'), count($users)), 'mod/users.html', array('users' => $users));
}

function mod_user_promote($uid, $action) {
	if (!hasPermission(Vi::$config['mod']['promoteusers'])) {
		error(Vi::$config['error']['noaccess']);
	}

	$query = prepare("SELECT `type`, `username` FROM ``mods`` WHERE `id` = :id");
	$query->bindValue(':id', $uid);
	$query->execute() or error(db_error($query));

	if (!$mod = $query->fetch(PDO::FETCH_ASSOC)) {
		error(Vi::$config['error']['404']);
	}

	$new_group = false;

	$groups = Vi::$config['mod']['groups'];
	if ($action == 'demote') {
		$groups = array_reverse($groups, true);
	}

	foreach ($groups as $group_value => $group_name) {
		if ($action == 'promote' && $group_value > $mod['type']) {
			$new_group = $group_value;
			break;
		} elseif ($action == 'demote' && $group_value < $mod['type']) {
			$new_group = $group_value;
			break;
		}
	}

	if ($new_group === false || $new_group == DISABLED) {
		error(_('Impossible to promote/demote user.'));
	}

	$query = prepare("UPDATE ``mods`` SET `type` = :group_value WHERE `id` = :id");
	$query->bindValue(':id', $uid);
	$query->bindValue(':group_value', $new_group);
	$query->execute() or error(db_error($query));

	modLog(($action == 'promote' ? 'Promoted' : 'Demoted') . ' user "' .
		utf8tohtml($mod['username']) . '" to ' . Vi::$config['mod']['groups'][$new_group]);

	header('Location: ?/users', true, Vi::$config['redirect_http']);
}

function mod_pm($id, $reply = false) {
	if ($reply && !hasPermission(Vi::$config['mod']['create_pm'])) {
		error(Vi::$config['error']['noaccess']);
	}

	$query = prepare("SELECT ``mods``.`username`, `mods_to`.`username` AS `to_username`, ``pms``.* FROM ``pms`` LEFT JOIN ``mods`` ON ``mods``.`id` = `sender` LEFT JOIN ``mods`` AS `mods_to` ON `mods_to`.`id` = `to` WHERE ``pms``.`id` = :id");
	$query->bindValue(':id', $id);
	$query->execute() or error(db_error($query));

	if ((!$pm = $query->fetch(PDO::FETCH_ASSOC)) || ($pm['to'] != Vi::$mod['id'] && !hasPermission(Vi::$config['mod']['master_pm']))) {
		error(Vi::$config['error']['404']);
	}

	if (isset($_POST['delete'])) {
		$query = prepare("DELETE FROM ``pms`` WHERE `id` = :id");
		$query->bindValue(':id', $id);
		$query->execute() or error(db_error($query));

		if (Vi::$config['cache']['enabled']) {
			cache::delete('pm_unread_' . Vi::$mod['id']);
			cache::delete('pm_unreadcount_' . Vi::$mod['id']);
		}

		header('Location: ?/', true, Vi::$config['redirect_http']);
		return;
	}

	if ($pm['unread'] && $pm['to'] == Vi::$mod['id']) {
		$query = prepare("UPDATE ``pms`` SET `unread` = 0 WHERE `id` = :id");
		$query->bindValue(':id', $id);
		$query->execute() or error(db_error($query));

		if (Vi::$config['cache']['enabled']) {
			cache::delete('pm_unread_' . Vi::$mod['id']);
			cache::delete('pm_unreadcount_' . Vi::$mod['id']);
		}

		modLog('Read a PM');
	}

	if ($reply) {
		if (!$pm['to_username']) {
			error(Vi::$config['error']['404']);
		}
		// deleted?

		mod_page(sprintf('%s %s', _('New PM for'), $pm['to_username']), 'mod/new_pm.html', array(
			'username' => $pm['username'],
			'id'       => $pm['sender'],
			'message'  => quote($pm['message']),
			'token'    => make_secure_link_token('new_PM/' . $pm['username']),
		));
	} else {
		mod_page(sprintf('%s &ndash; #%d', _('Private message'), $id), 'mod/pm.html', $pm);
	}
}

function mod_inbox() {
	$query = prepare('SELECT `unread`,``pms``.`id`, `time`, `sender`, `to`, `message`, `username` FROM ``pms`` LEFT JOIN ``mods`` ON ``mods``.`id` = `sender` WHERE `to` = :mod ORDER BY `unread` DESC, `time` DESC');
	$query->bindValue(':mod', Vi::$mod['id']);
	$query->execute() or error(db_error($query));
	$messages = $query->fetchAll(PDO::FETCH_ASSOC);

	$query = prepare('SELECT COUNT(*) FROM ``pms`` WHERE `to` = :mod AND `unread` = 1');
	$query->bindValue(':mod', Vi::$mod['id']);
	$query->execute() or error(db_error($query));
	$unread = $query->fetchColumn();

	foreach ($messages as &$message) {
		$message['snippet'] = pm_snippet($message['message']);
	}

	mod_page(sprintf('%s (%s)', _('PM inbox'), count($messages) > 0 ? $unread . ' ' . _('unread') : _('empty')), 'mod/inbox.html', array(
		'messages' => $messages,
		'unread'   => $unread,
	));
}

function mod_new_pm($username) {
	if (!hasPermission(Vi::$config['mod']['create_pm'])) {
		error(Vi::$config['error']['noaccess']);
	}

	$query = prepare("SELECT `id`, `boards` FROM ``mods`` WHERE `username` = :username");
	$query->bindValue(':username', $username);
	$query->execute() or error(db_error($query));
	if (!$row = $query->fetch()) {
		error(Vi::$config['error']['404']);
	}

	// Rate limit for PMs
	if (!hasPermission(Vi::$config['mod']['bypass_pm_ratelimit'])) {
		$ratelimit = prepare('SELECT `id` FROM ``pms`` WHERE FROM_UNIXTIME(`time`) > DATE_SUB(NOW(), INTERVAL 1 HOUR) AND `sender` = :sender');
		$ratelimit->bindValue(':sender', Vi::$mod['id']);
		$ratelimit->execute() or error(db_error($ratelimit));

		if ($ratelimit->rowCount() >= Vi::$config['mod']['pm_ratelimit']) {
			error(_('You are sending too many PMs per hour. Try again later.'));
		}
	}

	// Lock users into only being able to message users assigned to their board.
	if (!hasPermission(Vi::$config['mod']['pm_all'])) {
		if (Vi::$mod['boards'][0] != $row['boards'] && !($row['boards'] === '*')) {
			error(_('You may only PM users assigned to your board'));
		}

		if ($row['boards'] === '*') {
			// If the global user PM'd them first within the last month, they can reply.
			$check = prepare('SELECT * FROM ``pms`` WHERE FROM_UNIXTIME(`time`) > DATE_SUB(NOW(), INTERVAL 1 MONTH) AND `sender` = :sender AND `to` = :to');
			$check->bindValue(':sender', $row['id']);
			$check->bindValue(':to', Vi::$mod['id']);
			$check->execute() or error(db_error($check));
			if (!$check->rowCount()) {
				error(_('You may not PM a member of global staff who did not PM you within the last month. Try posting on /operate/ or emailing us instead: admin@8chan.co'));
			}
		}
	}

	if (isset($_POST['message'])) {
		$id = $row['id'];

		if (strlen($_POST['message']) > Vi::$config['mod']['pm_maxsize']) {
			error(sprintf(_('Your message exceeds %d characters, please shorten it.'), Vi::$config['mod']['pm_maxsize']));
		}

		$_POST['message'] = escape_markup_modifiers($_POST['message']);
		markup($_POST['message']);

		$query = prepare("INSERT INTO ``pms`` VALUES (NULL, :me, :id, :message, :time, 1)");
		$query->bindValue(':me', Vi::$mod['id']);
		$query->bindValue(':id', $id);
		$query->bindValue(':message', $_POST['message']);
		$query->bindValue(':time', time());
		$query->execute() or error(db_error($query));

		if (Vi::$config['cache']['enabled']) {
			cache::delete('pm_unread_' . $id);
			cache::delete('pm_unreadcount_' . $id);
		}

		modLog('Sent a PM to ' . utf8tohtml($username));

		header('Location: ?/', true, Vi::$config['redirect_http']);
	}

	mod_page(sprintf('%s %s', _('New PM for'), $username), 'mod/new_pm.html', array(
		'username' => $username,
		'id'       => $row['id'],
		'token'    => make_secure_link_token('new_PM/' . $username),
	));
}

function mod_rebuild() {
	if (!hasPermission(Vi::$config['mod']['rebuild'])) {
		error(Vi::$config['error']['noaccess']);
	}

	if (isset($_POST['rebuild'])) {
		@set_time_limit(Vi::$config['mod']['rebuild_timelimit']);

		$_locale         = Vi::$current_locale;
		$log             = array();
		$boards          = listBoards();
		$rebuilt_scripts = array();

		if (isset($_POST['rebuild_cache'])) {
			if (Vi::$config['cache']['enabled']) {
				$log[] = 'Flushing cache';
				Cache::flush();
			}

			$log[] = 'Clearing template cache';
			load_twig();
			Vi::$twig->clearCacheFiles();
		}

		if (isset($_POST['rebuild_themes'])) {
			$log[] = 'Regenerating theme files';
			rebuildThemes('all');
		}

		if (isset($_POST['rebuild_javascript'])) {
			$log[] = 'Rebuilding <strong>' . Vi::$config['file_script'] . '</strong>';
			buildJavascript();
			$rebuilt_scripts[] = Vi::$config['file_script'];
		}

		// Save the global variables
		$_config = Vi::$config;
		foreach ($boards as $board) {
			if (!(isset($_POST['boards_all']) || isset($_POST['board_' . $board['uri']]))) {
				continue;
			}

			openBoard($board['uri']);
			Vi::$config['try_smarter'] = false;

			if (isset($_POST['rebuild_index'])) {
				buildIndex();
				$log[] = '<strong>' . sprintf(Vi::$config['board_abbreviation'], $board['uri']) . '</strong>: Creating index pages';
			}

			if (isset($_POST['rebuild_javascript']) && !in_array(Vi::$config['file_script'], $rebuilt_scripts)) {
				$log[] = '<strong>' . sprintf(Vi::$config['board_abbreviation'], $board['uri']) . '</strong>: Rebuilding <strong>' . Vi::$config['file_script'] . '</strong>';
				buildJavascript();
				$rebuilt_scripts[] = Vi::$config['file_script'];
			}

			if (isset($_POST['rebuild_posts'])) {
				$query = query(sprintf("SELECT `id` FROM ``posts_%s``", $board['uri'])) or error(db_error());
				while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
					$log[] = '<strong>' . sprintf(Vi::$config['board_abbreviation'], $board['uri']) . '</strong>: Rebuilding post #' . $post['id'];
					rebuildPost($post['id']);
				}
			}

			if (isset($_POST['rebuild_thread'])) {
				$query = query(sprintf("SELECT `id` FROM ``posts_%s`` WHERE `thread` IS NULL", $board['uri'])) or error(db_error());
				while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
					$log[] = '<strong>' . sprintf(Vi::$config['board_abbreviation'], $board['uri']) . '</strong>: Rebuilding thread #' . $post['id'];
					buildThread($post['id']);
				}
			}
		}
		// Restore them again
		Vi::$config = $_config;
		init_locale($_locale);
		mod_page(_('Rebuild'), 'mod/rebuilt.html', array('logs' => $log));
		return;
	}

	mod_page(_('Rebuild'), 'mod/rebuild.html', array(
		'boards' => listBoards(),
		'token'  => make_secure_link_token('rebuild'),
	));
}

function mod_reports() {
	// Parse arguments.
	$urlArgs = func_get_args();
	$global  = in_array("global", $urlArgs);
	$json    = in_array("json", $urlArgs);

	if (!hasPermission(Vi::$config['mod']['reports'])) {
		error(Vi::$config['error']['noaccess']);
	}

	if ((Vi::$mod['type'] < GLOBALVOLUNTEER) and $global) {
		error(Vi::$config['error']['noaccess']);
	}

	// Limit reports to ONLY those in our scope.
	$report_scope = $global ? "global" : "local";

	// Get REPORTS.
	$query = prepare("SELECT * FROM ``reports`` WHERE " . ((Vi::$mod["type"] < GLOBALVOLUNTEER) ? "board = :board AND" : "") . " ``" . ($global ? "global" : "local") . "`` = TRUE  LIMIT :limit");

	// Limit reports by board if the moderator is local.
	if (Vi::$mod['type'] < GLOBALVOLUNTEER) {
		$query->bindValue(':board', Vi::$mod['boards'][0]);
	}

	// Limit by config ceiling.
	$query->bindValue(':limit', Vi::$config['mod']['recent_reports'], PDO::PARAM_INT);

	$query->execute() or error(db_error($query));
	$reports = $query->fetchAll(PDO::FETCH_ASSOC);

	// Cut off here if we don't have any reports.
	$reportCount = 0;
	$reportHTML  = '';
	if (count($reports) > 0) {

		// Build queries to fetch content.
		$report_queries = array();
		foreach ($reports as $report) {
			if (!isset($report_queries[$report['board']])) {
				$report_queries[$report['board']] = array();
			}

			$report_queries[$report['board']][] = $report['post'];
		}

		// Get reported CONTENT.
		$report_posts = array();
		foreach ($report_queries as $board => $posts) {
			$report_posts[$board] = array();

			$query = query(sprintf('SELECT * FROM ``posts_%s`` WHERE `id` = ' . implode(' OR `id` = ', $posts), $board)) or error(db_error());
			while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
				$report_posts[$board][$post['id']] = $post;
			}
		}

		// Develop an associative array of posts to reports.
		$report_index = array();
		foreach ($reports as &$report) {

			// Delete reports which are for removed content.
			if (!isset($report_posts[$report['board']][$report['post']])) {
				// Invalid report (post has since been deleted)
				$query = prepare("DELETE FROM ``reports`` WHERE `post` = :id AND `board` = :board");
				$query->bindValue(':id', $report['post'], PDO::PARAM_INT);
				$query->bindValue(':board', $report['board']);
				$query->execute() or error(db_error($query));
				continue;
			}

			// Build a unique ID.
			$content_key = "{$report['board']}.{$report['post']}";

			// Create a dummy array if it doesn't already exist.
			if (!isset($report_index[$content_key])) {
				$report_index[$content_key] = array(
					"board_id" => $report['board'],
					"post_id"  => $report['post'],
					"content"  => $report_posts[$report['board']][$report['post']],
					"reports"  => array(),
				);
			}

			// Add the report to the list of reports.
			$report_index[$content_key]['reports'][$report['id']] = $report;

			// Increment the total report count.
			++$reportCount;
		}

		// Only continue if we have something to do.
		// If there are no valid reports left, we're done.
		if ($reportCount > 0 && !$json) {

			// Sort this report index by number of reports, desc.
			usort($report_index, function ($a, $b) {
				$ra = count($a['reports']);
				$rb = count($b['reports']);

				if ($ra < $rb) {
					return 1;
				} else if ($rb > $ra) {
					return -1;
				} else {
					return 0;
				}
			});

			// Loop through the custom index.
			foreach ($report_index as &$report_item) {
				$content = $report_item['content'];

				// Load board content.
				openBoard($report_item['board_id']);
				init_locale(NULL);

				// Load the reported content.
				if (!$content['thread']) {
					// Still need to fix this:
					$po = new Thread($content, '?/', Vi::$mod, false);
				} else {
					$po = new Post($content, '?/', Vi::$mod);
				}

				// Fetch clean status.
				$po->getClean(true);
				$clean = $po->clean;

				// Add each report's template to this container.
				$report_html         = "";
				$reports_can_demote  = false;
				$reports_can_promote = false;
				$content_reports     = 0;
				foreach ($report_item['reports'] as $report) {
					$uri_report_base = "reports/" . ($global ? "global/" : "") . $report['id'];
					$report_html .= Element('mod/report.html', array(
						'report'        => $report,
						'config'        => Vi::$config,
						'mod'           => Vi::$mod,
						'global'        => $global,
						'clean'         => $clean,

						'uri_dismiss'   => "?/{$uri_report_base}/dismiss",
						'uri_ip'        => "?/{$uri_report_base}/dismissall",
						'uri_demote'    => "?/{$uri_report_base}/demote",
						'uri_promote'   => "?/{$uri_report_base}/promote",
						'token_dismiss' => make_secure_link_token($uri_report_base . '/dismiss'),
						'token_ip'      => make_secure_link_token($uri_report_base . '/dismissall'),
						'token_demote'  => make_secure_link_token($uri_report_base . '/demote'),
						'token_promote' => make_secure_link_token($uri_report_base . '/promote'),
					));

					// Determines if we can "Demote All" / "Promote All"
					// This logic only needs one instance of a demotable or promotable report to work.
					// DEMOTE can occur when we're global and the report has a 1 for local (meaning locally, it's not dismissed)
					// PROMOTE can occur when we're local and the report has a 0 for global (meaning it's not global).
					if ($global && $report['local'] == "1") {
						$reports_can_demote = true;
					} else if (!$global && $report['global'] != "1") {
						$reports_can_promote = true;
					}

					++$content_reports;
				}

				// Build the ">>>/b/ thread reported 3 times" title.
				$report_title = sprintf('<a href="%s" title="%s" target="_new">&gt;&gt;&gt;/%s/</a> %s',
					"?/{$report_item['board_id']}/res/" . ($content['thread'] ?: $content['id']) . ".html#{$content['id']}",
					_("View content"),
					$report_item['board_id'],
					($content['thread'] ? _("post reported") : _("thread reported")) . " " . sprintf(ngettext("%d time", "%d times", $content_reports), $content_reports)
				);

				// Figure out some stuff we need for the page.
				$reports_can_demote  = ($clean['clean_local'] ? false : $reports_can_demote);
				$reports_can_promote = ($clean['clean_global'] ? false : $reports_can_promote);
				$uri_content_base    = "reports/" . ($global ? "global/" : "") . "content/";
				$uri_clean_base      = "reports/" . ($global ? "global/" : "") . "{$report_item['board_id']}/clean/{$content['id']}";

				// Build the actions page.
				$content_html = Element('mod/report_content.html', array(
					'reports_html'          => $report_html,
					'reports_can_demote'    => $reports_can_demote,
					'reports_can_promote'   => $reports_can_promote,
					'report_count'          => $content_reports,
					'report_title'          => $report_title,

					'content_html'          => $po->build(true),
					'content_board'         => $report_item['board_id'],
					'content'               => (array) $content,

					'clean'                 => $clean,

					'uri_content_demote'    => "?/{$uri_content_base}{$report_item['board_id']}/{$content['id']}/demote",
					'uri_content_promote'   => "?/{$uri_content_base}{$report_item['board_id']}/{$content['id']}/promote",
					'uri_content_dismiss'   => "?/{$uri_content_base}{$report_item['board_id']}/{$content['id']}/dismiss",
					'token_content_demote'  => make_secure_link_token("{$uri_content_base}{$report_item['board_id']}/{$content['id']}/demote"),
					'token_content_promote' => make_secure_link_token("{$uri_content_base}{$report_item['board_id']}/{$content['id']}/promote"),
					'token_content_dismiss' => make_secure_link_token("{$uri_content_base}{$report_item['board_id']}/{$content['id']}/dismiss"),

					'uri_clean'             => "?/{$uri_clean_base}/local",
					'uri_clean_global'      => "?/{$uri_clean_base}/global",
					'uri_clean_both'        => "?/{$uri_clean_base}/global+local",
					'token_clean'           => make_secure_link_token($uri_clean_base . '/local'),
					'token_clean_global'    => make_secure_link_token($uri_clean_base . '/global'),
					'token_clean_both'      => make_secure_link_token($uri_clean_base . '/global+local'),

					'global'                => $global,
					'config'                => Vi::$config,
					'mod'                   => Vi::$mod,
				));

				$reportHTML .= $content_html;
			}
		}

		if ($reportCount > 0 && $json) {
			array_walk($reports, function (&$v, $k, $ud) {
				$global       = $ud['global'];
				$report_posts = $ud['report_posts'];

				$board = ($v['board'] ? $v['board'] : NULL);

				if (isset($v['ip']) && !$global) {
					$v['ip'] = less_ip($v['ip'], ($board ? $board : ''));
				}

				if (isset($report_posts[$v['board']][$v['post']])) {
					$post_content = $report_posts[$v['board']][$v['post']];
					unset($post_content['password']);
					if (!$global) {
						$post_content['ip'] = less_ip($post_content['ip'], ($board ? $board : ''));
					}
					$v['post_content'] = $post_content;
				}
			}, array('global' => $global, 'report_posts' => $report_posts));
		}
	}

	$pageArgs = array(
		'count'   => $reportCount,
		'reports' => $reportHTML,
		'global'  => $global,
	);

	if ($json) {
		header('Content-Type: application/json');
		echo json_encode($reports);
	} else {
		mod_page(sprintf('%s (%d)', _(($global ? 'Global report queue' : 'Report queue')), $reportCount), 'mod/reports.html', $pageArgs);
	}
}

function mod_report_dismiss() {
	// Parse arguments.
	$arguments = func_get_args();
	$global    = in_array("global", $arguments);
	$content   = in_array("content", $arguments);

	if ((Vi::$mod['type'] < GLOBALVOLUNTEER) and $global) {
		error(Vi::$config['error']['noaccess']);
	}

	if ($content) {
		$board = @$arguments[2];
		$post  = @$arguments[3];

		if (!hasPermission(Vi::$config['mod']['report_dismiss_content'], $board)) {
			error(Vi::$config['error']['noaccess']);
		}

		if ($board != "" && $post != "") {

			$query = prepare("SELECT `id` FROM `reports` WHERE `board` = :board AND `post` = :post");
			$query->bindValue(':board', $board);
			$query->bindValue(':post', $post);
			$query->execute() or error(db_error($query));
			if (count($reports = $query->fetchAll(PDO::FETCH_ASSOC)) > 0) {
				$report_ids = array();
				foreach ($reports as $report) {
					$report_ids[$report['id']] = $report['id'];
				}

				if ($global) {
					$scope = "``global`` = FALSE AND ``local`` = FALSE";
				} else {
					$scope = "``local`` = FALSE";
				}

				$query = prepare("UPDATE ``reports`` SET {$scope} WHERE `id` IN (" . implode(',', array_map('intval', $report_ids)) . ")");
				$query->execute() or error(db_error($query));

				// Cleanup - Remove reports that have been completely dismissed.
				$query = prepare("DELETE FROM `reports` WHERE `local` = FALSE AND `global` = FALSE");
				$query->execute() or error(db_error($query));

				modLog("Dismissed " . count($report_ids) . " local report(s) for post #{$post}", $board);
			} else {
				error(Vi::$config['error']['404']);
			}
		} else {
			error(Vi::$config['error']['404']);
		}
	} else {
		$report = @$arguments[1];
		$all    = in_array("all", $arguments);

		if ($report != "") {

			$query = prepare("SELECT `post`, `board`, `ip` FROM ``reports`` WHERE `id` = :id");
			$query->bindValue(':id', $report);
			$query->execute() or error(db_error($query));
			if ($reportobj = $query->fetch(PDO::FETCH_ASSOC)) {
				$ip    = $reportobj['ip'];
				$board = $reportobj['board'];
				$post  = $reportobj['post'];

				if (!$all && !hasPermission(Vi::$config['mod']['report_dismiss'], $board)) {
					error(Vi::$config['error']['noaccess']);
				}
				if ($all && !hasPermission(Vi::$config['mod']['report_dismiss_ip'], $board)) {
					error(Vi::$config['error']['noaccess']);
				}

				// Determine scope (local and global or just local) based on /global/ being in URI.
				if ($global) {
					$scope  = "`global` = FALSE";
					$boards = "";
				} else {
					$scope  = "`local` = FALSE";
					$boards = "AND `board` = '{$board}'";
				}

				// Prepare query.
				// We don't delete reports, only modify scope.
				if ($all) {
					$query = prepare("UPDATE ``reports`` SET {$scope} WHERE `ip` = :ip {$boards}");
					$query->bindValue(':ip', $ip);
				} else {
					$query = prepare("UPDATE ``reports`` SET {$scope} WHERE `id` = :id {$boards}");
					$query->bindValue(':id', $report);
				}

				$query->execute() or error(db_error($query));

				// Cleanup - Remove reports that have been completely dismissed.
				$query = prepare("DELETE FROM `reports` WHERE `local` = FALSE AND `global` = FALSE");
				$query->execute() or error(db_error($query));

				if ($all) {
					modLog("Dismissed all reports by " . ip_link($ip));
				} else {
					modLog("Dismissed a report for post #{$post}", $board);
				}
			} else {
				error(Vi::$config['error']['404']);
			}
		} else {
			error(Vi::$config['error']['404']);
		}
	}

	if ($global) {
		header('Location: ?/reports/global', true, Vi::$config['redirect_http']);
	} else {
		header('Location: ?/reports', true, Vi::$config['redirect_http']);
	}
}

function mod_report_demote() {
	if (Vi::$mod['type'] < GLOBALVOLUNTEER) {
		error(Vi::$config['error']['noaccess']);
	}

	// Parse arguments.
	$arguments = func_get_args();
	$content   = in_array("content", $arguments);

	if ($content) {
		$board = @$arguments[2];
		$post  = @$arguments[3];

		if (!hasPermission(Vi::$config['mod']['report_demote'], $board)) {
			error(Vi::$config['error']['noaccess']);
		}

		if ($board != "" && $post != "") {

			$query = prepare("SELECT `id` FROM `reports` WHERE `global` = TRUE AND `board` = :board AND `post` = :post");
			$query->bindValue(':board', $board);
			$query->bindValue(':post', $post);
			$query->execute() or error(db_error($query));
			if (count($reports = $query->fetchAll(PDO::FETCH_ASSOC)) > 0) {

				$report_ids = array();
				foreach ($reports as $report) {
					$report_ids[$report['id']] = $report['id'];
				}

				$query = prepare("UPDATE ``reports`` SET ``global`` = FALSE WHERE `id` IN (" . implode(',', array_map('intval', $report_ids)) . ")");
				$query->execute() or error(db_error($query));

				modLog("Demoted " . count($report_ids) . " global report(s) for post #{$post}", $board);
			} else {
				error(Vi::$config['error']['404']);
			}
		} else {
			error(Vi::$config['error']['404']);
		}
	} else {
		$report = @$arguments[1];

		if ($report != "") {

			$query = prepare("SELECT `post`, `board`, `ip` FROM ``reports`` WHERE `id` = :id AND ``global`` = TRUE");
			$query->bindValue(':id', $report);
			$query->execute() or error(db_error($query));
			if ($reportobj = $query->fetch(PDO::FETCH_ASSOC)) {
				$ip    = $reportobj['ip'];
				$board = $reportobj['board'];
				$post  = $reportobj['post'];

				if (!hasPermission(Vi::$config['mod']['report_demote'], $board)) {
					error(Vi::$config['error']['noaccess']);
				}

				$query = prepare("UPDATE ``reports`` SET ``global`` = FALSE WHERE `id` = :id");
				$query->bindValue(':id', $report);
				$query->execute() or error(db_error($query));

				modLog("Demoted a global report for post #{$report}", $board);
			} else {
				error(Vi::$config['error']['404']);
			}
		} else {
			error(Vi::$config['error']['404']);
		}
	}

	header('Location: ?/reports/global', true, Vi::$config['redirect_http']);
}

function mod_report_promote() {
	// Parse arguments.
	$arguments = func_get_args();
	$content   = in_array("content", $arguments);

	if ($content) {
		$board = @$arguments[2];
		$post  = @$arguments[3];

		if (!hasPermission(Vi::$config['mod']['report_promote'], $board)) {
			error(Vi::$config['error']['noaccess']);
		}

		if ($board != "" && $post != "") {
			$query = prepare("SELECT `id` FROM `reports` WHERE `global` = FALSE AND `board` = :board AND `post` = :post");
			$query->bindValue(':board', $board);
			$query->bindValue(':post', $post);
			$query->execute() or error(db_error($query));
			if (count($reports = $query->fetchAll(PDO::FETCH_ASSOC)) > 0) {

				$report_ids = array();
				foreach ($reports as $report) {
					$report_ids[$report['id']] = $report['id'];
				}

				$query = prepare("UPDATE ``reports`` SET ``global`` = TRUE WHERE `id` IN (" . implode(',', array_map('intval', $report_ids)) . ")");
				$query->execute() or error(db_error($query));

				modLog("Promoted " . count($report_ids) . " local report(s) for post #{$post}", $board);
			} else {
				error(Vi::$config['error']['404']);
			}
		} else {
			error(Vi::$config['error']['404']);
		}
	} else {
		$report = @$arguments[1];

		if ($report != "") {
			$query = prepare("SELECT `post`, `board`, `ip` FROM ``reports`` WHERE `id` = :id AND ``global`` = FALSE");
			$query->bindValue(':id', $report);
			$query->execute() or error(db_error($query));
			if ($reportobj = $query->fetch(PDO::FETCH_ASSOC)) {
				$ip    = $reportobj['ip'];
				$board = $reportobj['board'];
				$post  = $reportobj['post'];

				if (!hasPermission(Vi::$config['mod']['report_promote'], $board)) {
					error(Vi::$config['error']['noaccess']);
				}

				$query = prepare("UPDATE ``reports`` SET ``global`` = TRUE WHERE `id` = :id");
				$query->bindValue(':id', $report);
				$query->execute() or error(db_error($query));

				modLog("Promoted a local report for post #{$report}", $board);
			} else {
				error(Vi::$config['error']['404']);
			}
		} else {
			error(Vi::$config['error']['404']);
		}
	}

	header('Location: ?/reports', true, Vi::$config['redirect_http']);
}

function mod_recent_posts($lim) {
	if (!hasPermission(Vi::$config['mod']['recent'])) {
		error(Vi::$config['error']['noaccess']);
	}

	$limit     = (is_numeric($lim)) ? $lim : 25;
	$last_time = (isset($_GET['last']) && is_numeric($_GET['last'])) ? $_GET['last'] : 0;
	if ($limit > 100) {
		$limit = 100;
	}

	$mod_boards = array();
	$boards     = listBoards();

	//if not all boards
	if (Vi::$mod['boards'][0] != '*') {
		foreach ($boards as $board) {
			if (in_array($board['uri'], Vi::$mod['boards'])) {
				$mod_boards[] = $board;
			}
		}
	} else {
		$mod_boards = $boards;
	}

	// Manually build an SQL query
	$query = 'SELECT * FROM (';
	foreach ($mod_boards as $board) {
		$query .= sprintf('SELECT *, %s AS `board` FROM ``posts_%s`` UNION ALL ', Vi::$pdo->quote($board['uri']), $board['uri']);
	}
	// Remove the last "UNION ALL" seperator and complete the query
	$query = preg_replace('/UNION ALL $/', ') AS `all_posts` WHERE (`time` < :last_time OR NOT :last_time) ORDER BY `time` DESC LIMIT ' . $limit, $query);
	$query = prepare($query);
	$query->bindValue(':last_time', $last_time);
	$query->execute() or error(db_error($query));
	$posts = $query->fetchAll(PDO::FETCH_ASSOC);

	foreach ($posts as &$post) {
		openBoard($post['board']);
		if (!$post['thread']) {
			// Still need to fix this:
			$po            = new Thread($post, '?/', Vi::$mod, false);
			$post['built'] = $po->build(true);
		} else {
			$po            = new Post($post, '?/', Vi::$mod);
			$post['built'] = $po->build(true);
		}
		$last_time = $post['time'];
	}

	echo mod_page(_('Recent posts'), 'mod/recent_posts.html', array(
			'posts'     => $posts,
			'limit'     => $limit,
			'last_time' => $last_time,
		)
	);
}

function mod_report_clean($global_reports, $board, $unclean, $post, $global, $local) {
	if (!openBoard($board)) {
		error(Vi::$config['error']['noboard']);
	}

	$query_global     = "";
	$query_global_mod = "";
	if ($global) {
		if (!hasPermission(Vi::$config['mod']['clean_global'], $board)) {
			error(Vi::$config['error']['noaccess']);
		}

		$query_global     = "`clean_global` = :clean";
		$query_global_mod = "`clean_global_mod_id` = :mod";
	}

	$query_local     = "";
	$query_local_mod = "";
	if ($local) {
		if (!hasPermission(Vi::$config['mod']['clean'], $board)) {
			error(Vi::$config['error']['noaccess']);
		}

		$query_local     = "`clean_local` = :clean";
		$query_local_mod = "`clean_local_mod_id` = :mod";
	}

	// Marking this post as "Clean" (report immune?)
	if (!$unclean) {
		// Attempt to find a `post_clean` row for this content.
		$query = prepare("SELECT * FROM `post_clean` WHERE `board_id` = :board AND `post_id` = :post");
		$query->bindValue(':board', $board);
		$query->bindValue(':post', $post);

		$query->execute() or error(db_error($query));

		// If the $clean object doesn't exist we need to insert a row for this post.
		if (!($cleanRecord = $query->fetch(PDO::FETCH_ASSOC))) {
			$query = prepare("INSERT INTO `post_clean` (`post_id`, `board_id`) VALUES ( :post, :board )");
			$query->bindValue(':board', $board);
			$query->bindValue(':post', $post);

			$query->execute() or error(db_error($query));

			if ($query->rowCount() == 0) {
				error("The database failed to create a record for this content in `post_clean` to record clean status.");
			}

			$cleanRecord = true;
		}
	}
	// Revoking clean status (open it to reports?)
	else {
		// Attempt to find a `post_clean` row for this content.
		$query = prepare("SELECT * FROM `post_clean` WHERE `board_id` = :board AND `post_id` = :post");
		$query->bindValue(':board', $board);
		$query->bindValue(':post', $post);

		$query->execute() or error(db_error($query));

		if (!($cleanRecord = $query->fetch(PDO::FETCH_ASSOC))) {
			error(Vi::$config['error']['404']);
		}
	}

	// Update the `post_clean` row represented by $clean.
	if ($cleanRecord) {
		// Build our query based on the URI arguments.
		if ($global && $local) {
			$query = prepare("UPDATE `post_clean` SET {$query_global}, {$query_global_mod}, {$query_local}, {$query_local_mod} WHERE `board_id` = :board AND `post_id` = :post");
		} else if ($global) {
			$query = prepare("UPDATE `post_clean` SET {$query_global}, {$query_global_mod} WHERE `board_id` = :board AND `post_id` = :post");
		} else {
			$query = prepare("UPDATE `post_clean` SET {$query_local}, {$query_local_mod} WHERE `board_id` = :board AND `post_id` = :post");
		}

		$query->bindValue(':clean', !$unclean);
		$query->bindValue(':mod', $unclean ? NULL : Vi::$mod['id']);
		$query->bindValue(':board', $board);
		$query->bindValue(':post', $post);

		$query->execute() or error(db_error($query));

		// Finally, run a query to tidy up our records.
		if ($unclean) {
			// Query is removing clean status from content.
			// Remove any clean records that are now null.
			$cleanup = prepare("DELETE FROM `post_clean` WHERE `clean_local` = FALSE AND `clean_global` = FALSE");
			$query->execute() or error(db_error($query));
		} else {
			// Content is clean, auto-handle all reports.

			// If this is a total clean, we don't need to update records first.
			if (!($global && $local)) {
				$query = prepare("UPDATE `reports` SET `" . ($local ? "local" : "global") . "` = FALSE WHERE `board` = :board AND `post` = :post");
				$query->bindValue(':board', $board);
				$query->bindValue(':post', $post);

				$query->execute() or error(db_error($query));

				// If we didn't hit anything, this content doesn't have reports, so don't run the delete query.
				$require_delete = ($query->rowCount() > 0);

				if ($require_delete) {
					$query = prepare("DELETE FROM `reports` WHERE `local` = FALSE and `global` = FALSE");

					$query->execute() or error(db_error($query));
				}
			}
			// This is a total clean, so delete content by ID rather than via cleanup.
			else {
				$query = prepare("DELETE FROM `reports` WHERE `board` = :board AND `post` = :post");

				$query->bindValue(':board', $board);
				$query->bindValue(':post', $post);

				$query->execute() or error(db_error($query));
			}
		}

		// Log the action.
		// Having clear wording of ths log is very important because of the nature of clean status.
		$log_action = ($unclean ? "Closed" : "Re-opened");
		$log_scope  = ($local && $global ? "local and global" : ($local ? "local" : "global"));
		modLog("{$log_action} reports for post #{$post} in {$log_scope}.", $board);
		if (Vi::$config['cache']['enabled']) {
			cache::delete("post_clean_{$board}_{$post}");
		}

		rebuildPost($post);
	}

	// Redirect
	if ($global_reports) {
		header('Location: ?/reports/global', true, Vi::$config['redirect_http']);
	} else {
		header('Location: ?/reports', true, Vi::$config['redirect_http']);
	}
}

function mod_config($board_config = false) {
	if ($board_config && !openBoard($board_config)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!hasPermission(Vi::$config['mod']['edit_config'], $board_config)) {
		error(Vi::$config['error']['noaccess']);
	}

	$config_file = $board_config ? Vi::$board['dir'] . 'config.php' : 'inc/instance-config.php';

	if (Vi::$config['mod']['config_editor_php']) {
		$readonly = !(is_file($config_file) ? is_writable($config_file) : is_writable(dirname($config_file)));

		if (!$readonly && isset($_POST['code'])) {
			$code = $_POST['code'];
			// Save previous instance_config if php_check_syntax fails
			$old_code = file_get_contents($config_file);
			file_put_contents($config_file, $code);
			$resp = shell_exec_error('php -l ' . $config_file);
			if (preg_match('/No syntax errors detected/', $resp)) {
				header('Location: ?/config' . ($board_config ? '/' . $board_config : ''), true, Vi::$config['redirect_http']);
				return;
			} else {
				file_put_contents($config_file, $old_code);
				error(Vi::$config['error']['badsyntax'] . $resp);
			}
		}

		$instance_config = @file_get_contents($config_file);
		if ($instance_config === false) {
			$instance_config = "<?php\n\n// This file does not exist yet. You are creating it.";
		}
		$instance_config = str_replace("\n", '&#010;', utf8tohtml($instance_config));

		mod_page(_('Config editor'), 'mod/config-editor-php.html', array(
			'php'      => $instance_config,
			'readonly' => $readonly,
			'boards'   => listBoards(),
			'board'    => $board_config,
			'file'     => $config_file,
			'token'    => make_secure_link_token('config' . ($board_config ? '/' . $board_config : '')),
		));
		return;
	}

	require_once 'inc/mod/config-editor.php';

	$conf = config_vars();

	foreach ($conf as &$var) {
		if (is_array($var['name'])) {
			$c = &Vi::$config;
			foreach ($var['name'] as $n) {
				$c = &$c[$n];
			}

		} else {
			$c = @Vi::$config[$var['name']];
		}

		$var['value'] = $c;
	}
	unset($var);

	if (isset($_POST['save'])) {
		$config_append = '';

		foreach ($conf as $var) {
			$field_name = 'cf_' . (is_array($var['name']) ? implode('/', $var['name']) : $var['name']);

			if ($var['type'] == 'boolean') {
				$value = isset($_POST[$field_name]);
			} elseif (isset($_POST[$field_name])) {
				$value = $_POST[$field_name];
			} else {
				continue;
			}
			// ???

			if (!settype($value, $var['type'])) {
				continue;
			}
			// invalid

			if ($value != $var['value']) {
				// This value has been changed.

				$config_append .= '$config';

				if (is_array($var['name'])) {
					foreach ($var['name'] as $name) {
						$config_append .= '[' . var_export($name, true) . ']';
					}

				} else {
					$config_append .= '[' . var_export($var['name'], true) . ']';
				}

				$config_append .= ' = ';
				if (@$var['permissions'] && isset(Vi::$config['mod']['groups'][$value])) {
					$config_append .= Vi::$config['mod']['groups'][$value];
				} else {
					$config_append .= var_export($value, true);
				}
				$config_append .= ";\n";
			}
		}

		if (!empty($config_append)) {
			$config_append = "\n// Changes made via web editor by \"" . Vi::$mod['username'] . "\" @ " . date('r') . ":\n" . $config_append . "\n";
			if (!is_file($config_file)) {
				$config_append = "<?php\n\n$config_append";
			}

			if (!@file_put_contents($config_file, $config_append, FILE_APPEND)) {
				$config_append = htmlentities($config_append);

				if (Vi::$config['minify_html']) {
					$config_append = str_replace("\n", '&#010;', $config_append);
				}

				$page           = array();
				$page['title']  = 'Cannot write to file!';
				$page['config'] = Vi::$config;
				$page['body']   = '
					<p style="text-align:center">Tinyboard could not write to <strong>' . $config_file . '</strong> with the ammended configuration, probably due to a permissions error.</p>
					<p style="text-align:center">You may proceed with these changes manually by copying and pasting the following code to the end of <strong>' . $config_file . '</strong>:</p>
					<textarea style="width:700px;height:370px;margin:auto;display:block;background:white;color:black" readonly>' . $config_append . '</textarea>
				';
				echo Element('page.html', $page);
				exit;
			}
		}

		header('Location: ?/config' . ($board_config ? '/' . $board_config : ''), true, Vi::$config['redirect_http']);

		exit;
	}

	mod_page(_('Config editor') . ($board_config ? ': ' . sprintf(Vi::$config['board_abbreviation'], $board_config) : ''),
		'mod/config-editor.html', array(
			'boards' => listBoards(),
			'board'  => $board_config,
			'conf'   => $conf,
			'file'   => $config_file,
			'token'  => make_secure_link_token('config' . ($board_config ? '/' . $board_config : '')),
		));
}

function mod_themes_list() {
	if (!hasPermission(Vi::$config['mod']['themes'])) {
		error(Vi::$config['error']['noaccess']);
	}

	if (!is_dir(Vi::$config['dir']['themes'])) {
		error(_('Themes directory doesn\'t exist!'));
	}

	if (!$dir = opendir(Vi::$config['dir']['themes'])) {
		error(_('Cannot open themes directory; check permissions.'));
	}

	$query         = query('SELECT `theme` FROM ``theme_settings`` WHERE `name` IS NULL AND `value` IS NULL') or error(db_error());
	$themes_in_use = $query->fetchAll(PDO::FETCH_COLUMN);

	// Scan directory for themes
	$themes = array();
	while ($file = readdir($dir)) {
		if ($file[0] != '.' && is_dir(Vi::$config['dir']['themes'] . '/' . $file)) {
			$themes[$file] = loadThemeConfig($file);
		}
	}
	closedir($dir);

	foreach ($themes as $theme_name => &$theme) {
		$theme['rebuild_token']   = make_secure_link_token('themes/' . $theme_name . '/rebuild');
		$theme['uninstall_token'] = make_secure_link_token('themes/' . $theme_name . '/uninstall');
	}

	mod_page(_('Manage themes'), 'mod/themes.html', array(
		'themes'        => $themes,
		'themes_in_use' => $themes_in_use,
	));
}

function mod_theme_configure($theme_name) {
	if (!hasPermission(Vi::$config['mod']['themes'])) {
		error(Vi::$config['error']['noaccess']);
	}

	if (!$theme = loadThemeConfig($theme_name)) {
		error(Vi::$config['error']['invalidtheme']);
	}

	if (isset($_POST['install'])) {
		// Check if everything is submitted
		foreach ($theme['config'] as &$conf) {
			if (!isset($_POST[$conf['name']]) && $conf['type'] != 'checkbox') {
				error(sprintf(Vi::$config['error']['required'], $c['title']));
			}

		}

		// Clear previous settings
		$query = prepare("DELETE FROM ``theme_settings`` WHERE `theme` = :theme");
		$query->bindValue(':theme', $theme_name);
		$query->execute() or error(db_error($query));

		foreach ($theme['config'] as &$conf) {
			$query = prepare("INSERT INTO ``theme_settings`` VALUES(:theme, :name, :value)");
			$query->bindValue(':theme', $theme_name);
			$query->bindValue(':name', $conf['name']);
			if ($conf['type'] == 'checkbox') {
				$query->bindValue(':value', isset($_POST[$conf['name']]) ? 1 : 0);
			} else {
				$query->bindValue(':value', $_POST[$conf['name']]);
			}

			$query->execute() or error(db_error($query));
		}

		$query = prepare("INSERT INTO ``theme_settings`` VALUES(:theme, NULL, NULL)");
		$query->bindValue(':theme', $theme_name);
		$query->execute() or error(db_error($query));

		// Clean cache
		Cache::delete("themes");
		Cache::delete("theme_settings_" . $theme_name);

		$result  = true;
		$message = false;
		if (isset($theme['install_callback'])) {
			$ret = $theme['install_callback'](themeSettings($theme_name));
			if ($ret && !empty($ret)) {
				if (is_array($ret) && count($ret) == 2) {
					$result  = $ret[0];
					$message = $ret[1];
				}
			}
		}

		if (!$result) {
			// Install failed
			$query = prepare("DELETE FROM ``theme_settings`` WHERE `theme` = :theme");
			$query->bindValue(':theme', $theme_name);
			$query->execute() or error(db_error($query));
		}

		// Build themes
		rebuildThemes('all');

		mod_page(sprintf(_($result ? 'Installed theme: %s' : 'Installation failed: %s'), $theme['name']), 'mod/theme_installed.html', array(
			'theme_name' => $theme_name,
			'theme'      => $theme,
			'result'     => $result,
			'message'    => $message,
		));
		return;
	}

	$settings = themeSettings($theme_name);

	mod_page(sprintf(_('Configuring theme: %s'), $theme['name']), 'mod/theme_config.html', array(
		'theme_name' => $theme_name,
		'theme'      => $theme,
		'settings'   => $settings,
		'token'      => make_secure_link_token('themes/' . $theme_name),
	));
}

function mod_theme_uninstall($theme_name) {
	if (!hasPermission(Vi::$config['mod']['themes'])) {
		error(Vi::$config['error']['noaccess']);
	}

	$query = prepare("DELETE FROM ``theme_settings`` WHERE `theme` = :theme");
	$query->bindValue(':theme', $theme_name);
	$query->execute() or error(db_error($query));

	// Clean cache
	Cache::delete("themes");
	Cache::delete("theme_settings_" . $theme_name);

	header('Location: ?/themes', true, Vi::$config['redirect_http']);
}

function mod_theme_rebuild($theme_name) {
	if (!hasPermission(Vi::$config['mod']['themes'])) {
		error(Vi::$config['error']['noaccess']);
	}

	rebuildTheme($theme_name, 'all');

	mod_page(sprintf(_('Rebuilt theme: %s'), $theme_name), 'mod/theme_rebuilt.html', array(
		'theme_name' => $theme_name,
	));
}

// This needs to be done for `secure` CSRF prevention compatibility, otherwise the $board will be read in as the token if editing global pages.
function delete_page_base($page = '', $board = false) {
	if (empty($board)) {
		$board = false;
	}

	if (!$board && Vi::$mod['boards'][0] !== '*') {
		error(Vi::$config['error']['noaccess']);
	}

	if (!hasPermission(Vi::$config['mod']['edit_pages'], $board)) {
		error(Vi::$config['error']['noaccess']);
	}

	if ($board !== FALSE && !openBoard($board)) {
		error(Vi::$config['error']['noboard']);
	}

	if (preg_match('/^[a-z0-9]{1,255}$/', $page) && !preg_match('/^(index|catalog|index\+50)|(\d+)$/', $page)) {
		if ($board) {
			$query = prepare('DELETE FROM ``pages`` WHERE `board` = :board AND `name` = :name');
			$query->bindValue(':board', ($board ? $board : NULL));
		} else {
			$query = prepare('DELETE FROM ``pages`` WHERE `board` IS NULL AND `name` = :name');
		}
		$query->bindValue(':name', $page);
		$query->execute() or error(db_error($query));

		@file_unlink(($board ? ($board . '/') : '') . $page . '.html');
	}

	header('Location: ?/edit_pages' . ($board ? ('/' . $board) : ''), true, Vi::$config['redirect_http']);
}

function mod_delete_page($page = '') {
	delete_page_base($page);
}

function mod_delete_page_board($page = '', $board = false) {
	delete_page_base($page, $board);
}

function mod_edit_page($id) {
	$query = prepare('SELECT * FROM ``pages`` WHERE `id` = :id');
	$query->bindValue(':id', $id);
	$query->execute() or error(db_error($query));
	$page = $query->fetch();

	if (!$page) {
		error(_('Could not find the page you are trying to edit.'));
	}

	if (!$page['board'] && Vi::$mod['boards'][0] !== '*') {
		error(Vi::$config['error']['noaccess']);
	}

	if (!hasPermission(Vi::$config['mod']['edit_pages'], $page['board'])) {
		error(Vi::$config['error']['noaccess']);
	}

	if ($page['board'] && !openBoard($page['board'])) {
		error(Vi::$config['error']['noboard']);
	}

	if (isset($_POST['method'], $_POST['content'])) {
		$content      = $_POST['content'];
		$method       = $_POST['method'];
		$page['type'] = $method;

		if (!in_array($method, array('markdown', 'html', 'infinity'))) {
			error(_('Unrecognized page markup method.'));
		}

		switch ($method) {
		case 'markdown':
			$write = purify_html(markdown($content));
			break;
		case 'html':
			if (hasPermission(Vi::$config['mod']['rawhtml'])) {
				$write = $content;
			} else {
				$write = purify_html($content);
			}
			break;
		case 'infinity':
			$c                   = $content;
			Vi::$config['max_links'] = PHP_INT_MAX;
			markup($content);
			$write   = $content;
			$content = $c;
		}

		if (!isset($write) or !$write) {
			error(_('Failed to mark up your input for some reason...'));
		}

		$query = prepare('UPDATE ``pages`` SET `type` = :method, `content` = :content WHERE `id` = :id');
		$query->bindValue(':method', $method);
		$query->bindValue(':content', $content);
		$query->bindValue(':id', $id);
		$query->execute() or error(db_error($query));

		$fn   = (Vi::$board['uri'] ? (Vi::$board['uri'] . '/') : '') . $page['name'] . '.html';
		$body = "<div class='ban'>$write</div>";
		$html = Element('page.html', array('config' => Vi::$config, 'body' => $body, 'title' => utf8tohtml($page['title'])));
		file_write($fn, $html);

		modLog("Edited page {$page['name']} <span class='unimportant'>(#{$page['id']})</span>");
	}

	if (!isset($content)) {
		$query = prepare('SELECT `content` FROM ``pages`` WHERE `id` = :id');
		$query->bindValue(':id', $id);
		$query->execute() or error(db_error($query));
		$content = $query->fetchColumn();
	}

	mod_page(sprintf(_('Editing static page: %s'), $page['name']), 'mod/edit_page.html', array('page' => $page, 'token' => make_secure_link_token("edit_page/$id"), 'content' => prettify_textarea($content), 'board' => Vi::$board));
}

function mod_pages($board = false) {
	if (empty($board)) {
		$board = false;
	}

	if (!$board && Vi::$mod['boards'][0] !== '*') {
		error(Vi::$config['error']['noaccess']);
	}

	if (!hasPermission(Vi::$config['mod']['edit_pages'], $board)) {
		error(Vi::$config['error']['noaccess']);
	}

	if ($board !== FALSE && !openBoard($board)) {
		error(Vi::$config['error']['noboard']);
	}

	if ($board) {
		$query = prepare('SELECT * FROM ``pages`` WHERE `board` = :board');
		$query->bindValue(':board', $board);
	} else {
		$query = query('SELECT * FROM ``pages`` WHERE `board` IS NULL');
	}
	$query->execute() or error(db_error($query));
	$pages = $query->fetchAll(PDO::FETCH_ASSOC);

	if (isset($_POST['page'])) {
		if ($board and sizeof($pages) > Vi::$config['pages_max']) {
			error(sprintf(_('Sorry, this site only allows %d pages per board.'), Vi::$config['pages_max']));
		}

		if (!preg_match('/^[a-z0-9]{1,255}$/', $_POST['page'])) {
			error(_('Page names must be < 255 chars and may only contain lowercase letters A-Z and digits 1-9.'));
		}

		if (preg_match('/^(index|catalog|index\+50)|(\d+)$/', $_POST['page'])) {
			error(_('Nope.'));
		}

		foreach ($pages as $i => $p) {
			if ($_POST['page'] === $p['name']) {
				error(_('Refusing to create a new page with the same name as an existing one.'));
			}

		}

		$title = ($_POST['title'] ? $_POST['title'] : NULL);

		$query = prepare('INSERT INTO ``pages``(board, title, name) VALUES(:board, :title, :name)');
		$query->bindValue(':board', ($board ? $board : NULL));
		$query->bindValue(':title', $title);
		$query->bindValue(':name', $_POST['page']);
		$query->execute() or error(db_error($query));

		$pages[] = array('id' => Vi::$pdo->lastInsertId(), 'name' => $_POST['page'], 'board' => $board, 'title' => $title);
	}

	foreach ($pages as $i => &$p) {
		$p['delete_token'] = make_secure_link_token('edit_pages/delete/' . $p['name'] . ($board ? ('/' . $board) : ''));
	}

	mod_page(_('Pages'), 'mod/pages.html', array('pages' => $pages, 'token' => make_secure_link_token('edit_pages' . ($board ? ('/' . $board) : '')), 'board' => $board));
}

function mod_debug_antispam() {
	if (!hasPermission(Vi::$config['mod']['debug_antispam'])) {
		error(Vi::$config['error']['noaccess']);
	}

	$args = array();

	if (isset($_POST['board'], $_POST['thread'])) {
		$where = '`board` = ' . Vi::$pdo->quote($_POST['board']);
		if ($_POST['thread'] != '') {
			$where .= ' AND `thread` = ' . Vi::$pdo->quote($_POST['thread']);
		}

		if (isset($_POST['purge'])) {
			$query = prepare(', DATE ``antispam`` SET `expires` = UNIX_TIMESTAMP() + :expires WHERE' . $where);
			$query->bindValue(':expires', Vi::$config['spam']['hidden_inputs_expire']);
			$query->execute() or error(db_error());
		}

		$args['board']  = $_POST['board'];
		$args['thread'] = $_POST['thread'];
	} else {
		$where = '';
	}

	$query         = query('SELECT COUNT(*) FROM ``antispam``' . ($where ? " WHERE $where" : '')) or error(db_error());
	$args['total'] = number_format($query->fetchColumn());

	$query            = query('SELECT COUNT(*) FROM ``antispam`` WHERE `expires` IS NOT NULL' . ($where ? " AND $where" : '')) or error(db_error());
	$args['expiring'] = number_format($query->fetchColumn());

	$query       = query('SELECT * FROM ``antispam`` ' . ($where ? "WHERE $where" : '') . ' ORDER BY `passed` DESC LIMIT 40') or error(db_error());
	$args['top'] = $query->fetchAll(PDO::FETCH_ASSOC);

	$query          = query('SELECT * FROM ``antispam`` ' . ($where ? "WHERE $where" : '') . ' ORDER BY `created` DESC LIMIT 20') or error(db_error());
	$args['recent'] = $query->fetchAll(PDO::FETCH_ASSOC);

	mod_page(_('Debug: Anti-spam'), 'mod/debug/antispam.html', $args);
}

function mod_debug_recent_posts() {
	if (!hasPermission(Vi::$config['mod']['debug_recent'])) {
		error(Vi::$config['error']['noaccess']);
	}

	$limit = 500;

	$boards = listBoards();

	// Manually build an SQL query
	$query = 'SELECT * FROM (';
	foreach ($boards as $board) {
		$query .= sprintf('SELECT *, %s AS `board` FROM ``posts_%s`` UNION ALL ', Vi::$pdo->quote($board['uri']), $board['uri']);
	}
	// Remove the last "UNION ALL" seperator and complete the query
	$query = preg_replace('/UNION ALL $/', ') AS `all_posts` ORDER BY `time` DESC LIMIT ' . $limit, $query);
	$query = query($query) or error(db_error());
	$posts = $query->fetchAll(PDO::FETCH_ASSOC);

	// Fetch recent posts from flood prevention cache
	$query       = query("SELECT * FROM ``flood`` ORDER BY `time` DESC") or error(db_error());
	$flood_posts = $query->fetchAll(PDO::FETCH_ASSOC);

	foreach ($posts as &$post) {
		$post['snippet'] = pm_snippet($post['body']);
		foreach ($flood_posts as $flood_post) {
			if ($flood_post['time'] == $post['time'] &&
				$flood_post['posthash'] == make_comment_hex($post['body_nomarkup']) &&
				$flood_post['filehash'] == $post['filehash']) {
				$post['in_flood_table'] = true;
			}

		}
	}

	mod_page(_('Debug: Recent posts'), 'mod/debug/recent_posts.html', array('posts' => $posts, 'flood_posts' => $flood_posts));
}

function mod_debug_sql() {
	if (!hasPermission(Vi::$config['mod']['debug_sql'])) {
		error(Vi::$config['error']['noaccess']);
	}

	$args['security_token'] = make_secure_link_token('debug/sql');

	if (isset($_POST['query'])) {
		$args['query'] = $_POST['query'];
		if ($query = query($_POST['query'])) {
			$args['result'] = $query->fetchAll(PDO::FETCH_ASSOC);
			if (!empty($args['result'])) {
				$args['keys'] = array_keys($args['result'][0]);
			} else {
				$args['result'] = 'empty';
			}

		} else {
			$args['error'] = db_error();
		}
	}

	mod_page(_('Debug: SQL'), 'mod/debug/sql.html', $args);
}

function mod_debug_apc() {
	if (!hasPermission(Vi::$config['mod']['debug_apc'])) {
		error(Vi::$config['error']['noaccess']);
	}

	if (Vi::$config['cache']['enabled'] != 'apc') {
		error('APC is not enabled.');
	}

	$cache_info = apc_cache_info('user');

	// $cached_vars = new APCIterator('user', '/^' . Vi::$config['cache']['prefix'] . '/');
	$cached_vars = array();
	foreach ($cache_info['cache_list'] as $var) {
		if (Vi::$config['cache']['prefix'] != '' && strpos(isset($var['key']) ? $var['key'] : $var['info'], Vi::$config['cache']['prefix']) !== 0) {
			continue;
		}

		$cached_vars[] = $var;
	}

	mod_page(_('Debug: APC'), 'mod/debug/apc.html', array('cached_vars' => $cached_vars));
}
