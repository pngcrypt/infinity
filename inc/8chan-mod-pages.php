<?php

function mod_8_tags($b) {
	if (!openBoard($b)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!hasPermission(Vi::$config['mod']['edit_tags'], $b)) {
		error(Vi::$config['error']['noaccess']);
	}

	if (isset($_POST['tags'])) {
		if (sizeof($_POST['tags']) > 5) {
			error(_('Too many tags.'));
		}

		$delete = prepare('DELETE FROM ``board_tags`` WHERE uri = :uri');
		$delete->bindValue(':uri', $b);
		$delete->execute();

		foreach ($_POST['tags'] as $i => $tag) {
			if ($tag) {
				if (strlen($tag) > 255) {
					continue;
				}

				$insert = prepare('INSERT INTO ``board_tags``(uri, tag) VALUES (:uri, :tag)');
				$insert->bindValue(':uri', $b);
				$insert->bindValue(':tag', utf8tohtml($tag));
				$insert->execute();
			}
		}

		$update = prepare('UPDATE ``boards`` SET sfw = :sfw WHERE uri = :uri');
		$update->bindValue(':uri', $b);
		$update->bindValue(':sfw', isset($_POST['sfw']));
		$update->execute();
	}
	$query = prepare('SELECT * FROM ``board_tags`` WHERE uri = :uri');
	$query->bindValue(':uri', $b);
	$query->execute();

	$tags = $query->fetchAll();

	$query = prepare('SELECT `sfw` FROM ``boards`` WHERE uri = :uri');
	$query->bindValue(':uri', $b);
	$query->execute();

	$sfw = $query->fetchColumn();

	mod_page(_('Edit tags'), 'mod/tags.html', array('board' => Vi::$board, 'token' => make_secure_link_token('tags/' . Vi::$board['uri']), 'tags' => $tags, 'sfw' => $sfw));
}

function mod_8_reassign($b) {
	if (!openBoard($b)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!hasPermission(Vi::$config['mod']['reassign_board'], $b)) {
		error(Vi::$config['error']['noaccess']);
	}

	$query = query("SELECT id, username FROM mods WHERE boards = '$b' AND type = 20");
	$mods  = $query->fetchAll();

	if (!$mods) {
		error(_('No mods?'));
	}

	$password = base64_encode(openssl_random_pseudo_bytes(9));

	list($version, $hashed) = crypt_password($password);

	$query = prepare('UPDATE ``mods`` SET `password` = :hashed, `version` = :version, `email` = NULL WHERE BINARY username = :mod');
	$query->bindValue(':hashed', $hashed);
	$query->bindValue(':version', $version);
	$query->bindValue(':mod', $mods[0]['username']);
	$query->execute();

	$body = "Thanks for your interest in this board. Kindly find the username and password below. You can login at https://8ch.net/mod.php.<br>Username: {$mods[0]['username']}<br>Password: {$password}<br>Thanks for using 8chan!";

	modLog("Reassigned board /$b/");

	mod_page(_('Edit reassign'), 'blank.html', array('board' => Vi::$board, 'token' => make_secure_link_token('reassign/' . Vi::$board['uri']), 'body' => $body));
}

function mod_8_volunteers($b) {
	if (!openBoard($b)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!hasPermission(Vi::$config['mod']['edit_volunteers'], $b)) {
		error(Vi::$config['error']['noaccess']);
	}

	if (isset($_POST['username'], $_POST['password'])) {
		$query = prepare('SELECT * FROM ``mods`` WHERE type = 19 AND boards = :board');
		$query->bindValue(':board', $b);
		$query->execute() or error(db_error($query));
		$count = $query->rowCount();
		$query = prepare('SELECT `username` FROM ``mods``');
		$query->execute() or error(db_error($query));
		$volunteers = $query->fetchAll(PDO::FETCH_ASSOC);

		if ($_POST['username'] == '') {
			error(sprintf(Vi::$config['error']['required'], 'username'));
		}

		if ($_POST['password'] == '') {
			error(sprintf(Vi::$config['error']['required'], 'password'));
		}

		if (!preg_match('/^[a-zA-Z0-9._]{1,30}$/', $_POST['username'])) {
			error(_('Invalid username'));
		}

		if ($count > 20) {
			error(_('Too many board volunteers!'));
		}

		foreach ($volunteers as $i => $v) {
			if (strtolower($_POST['username']) == strtolower($v['username'])) {
				error(_('Refusing to create a volunteer with the same username as an existing one.'));
			}
		}

		list($version, $password) = crypt_password($_POST['password']);

		$query = prepare('INSERT INTO ``mods`` VALUES (NULL, :username, :password, :version, 19, :board, "")');
		$query->bindValue(':username', $_POST['username']);
		$query->bindValue(':password', $password);
		$query->bindValue(':version', $version);
		$query->bindValue(':board', $b);
		$query->execute() or error(db_error($query));

		$userID = Vi::$pdo->lastInsertId();

		modLog('Created a new volunteer: ' . utf8tohtml($_POST['username']) . ' <small>(#' . $userID . ')</small>');
	}

	if (isset($_POST['delete'])) {
		foreach ($_POST['delete'] as $i => $d) {
			$query = prepare('SELECT * FROM ``mods`` WHERE id = :id');
			$query->bindValue(':id', $d);
			$query->execute() or error(db_error($query));

			$result = $query->fetch(PDO::FETCH_ASSOC);

			if (!$result) {
				error(_('Volunteer does not exist!'));
			}

			if ($result['boards'] != $b || $result['type'] != BOARDVOLUNTEER) {
				error(Vi::$config['error']['noaccess']);
			}

			$query = prepare('DELETE FROM ``mods`` WHERE id = :id');
			$query->bindValue(':id', $d);
			$query->execute() or error(db_error($query));
		}
	}

	$query = prepare('SELECT * FROM ``mods`` WHERE type = 19 AND boards = :board');
	$query->bindValue(':board', $b);
	$query->execute() or error(db_error($query));
	$volunteers = $query->fetchAll(PDO::FETCH_ASSOC);

	mod_page(_('Edit volunteers'), 'mod/volunteers.html', array('board' => Vi::$board, 'token' => make_secure_link_token('volunteers/' . Vi::$board['uri']), 'volunteers' => $volunteers));
}

function mod_8_flags($b) {
	if (!openBoard($b)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!hasPermission(Vi::$config['mod']['edit_flags'], $b)) {
		error(Vi::$config['error']['noaccess']);
	}

	if (file_exists("$b/flags.ser")) {
		Vi::$config['user_flags'] = unserialize(file_get_contents("$b/flags.ser"));
	}

	require_once 'inc/image.php';

	$dir = 'static/custom-flags/' . $b;

	if (!is_dir($dir)) {
		mkdir($dir, 0777, true);
	}

	function handle_file($id = false, $description, $b, $dir) {
		if ($id) {
			$f = 'flag-' . $id;
		} else {
			$f  = 'file';
			$id = time() . substr(microtime(), 2, 3);
		}

		$upload  = $_FILES[$f]['tmp_name'];
		$banners = array_diff(scandir($dir), array('..', '.'));

		if (empty($upload)) {
			error(Vi::$config['error']['noimage']);
		}

		if (!is_readable($upload)) {
			error(Vi::$config['error']['nomove']);
		}

		$extension = strtolower(mb_substr($_FILES[$f]['name'], mb_strrpos($_FILES[$f]['name'], '.') + 1));

		if ($extension != 'png') {
			error(_('Flags must be in PNG format.'));
		}

		if (filesize($upload) > 48000) {
			error(_('File too large!'));
		}

		if (!$size = @getimagesize($upload)) {
			error(Vi::$config['error']['invalidimg']);
		}

		if ($size[0] > 20 or $size[0] < 11 or $size[1] > 16 or $size[1] < 11) {
			error(_('Image wrong size!'));
		}
		if (sizeof($banners) > 65536) {
			error(_('Too many flags.'));
		}

		$description = trim($description);
		if (!strlen($description))
			$description = trim(substr($_FILES[$f]['name'], 0, -4)); // get filename if no description
		if (!strlen($description)) {
			error(_('You must enter a flag description!'));
		}
		else if (strlen($description) > 255) {
			error(_('Flag description too long!'));
		}

		copy($upload, "$dir/$id.$extension");
		purge("$dir/$id.$extension", true);
		Vi::$config['user_flags'][$id] = utf8tohtml($description);
		file_write($b . '/flags.ser', serialize(Vi::$config['user_flags']));
	}

	// Handle a new flag, if any.
	if (isset($_FILES['file'])) {
		handle_file(false, $_POST['description'], $b, $dir);
	}

	// Handle edits to existing flags.
	foreach ($_FILES as $k => $a) {
		if (empty($_FILES[$k]['tmp_name'])) {
			continue;
		}
		if (preg_match('/^flag-(\d+)$/', $k, $matches)) {
			$id = $matches[1];
			if (!isset($_POST['description-' . $id])) {
				continue;
			}
			if (isset(Vi::$config['user_flags'][$id])) {
				handle_file($id, $_POST['description-' . $id], $b, $dir);
			}
		}
	}

	// Description just changed, flag not edited.
	foreach ($_POST as $k => $v) {
		if (!preg_match('/^description-(\d+)$/', $k, $matches)) {
			continue;
		}

		$id = $matches[1];
		if (!isset($_POST['description-' . $id])) {
			continue;
		}

		$description = trim($_POST['description-' . $id]);

		if (!strlen($description)) {
			error(_('You must enter a flag description!'));
		}
		else if (strlen($description) > 255) {
			error(_('Flag description too long!'));
		}

		Vi::$config['user_flags'][$id] = utf8tohtml($description);
		file_write($b . '/flags.ser', serialize(Vi::$config['user_flags']));
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$flags = <<<FLAGS
<?php
defined('TINYBOARD') or exit;
Vi::\$config['country_flags'] = false;
Vi::\$config['country_flags_condensed'] = false;
Vi::\$config['user_flag'] = true;
Vi::\$config['uri_flags'] = '/static/custom-flags/$b/%s.png';
Vi::\$config['flag_style'] = '';
Vi::\$config['user_flags'] = unserialize(file_get_contents('$b/flags.ser'));
FLAGS;

		if (Vi::$config['cache']['enabled']) {
			cache::delete('config_' . $b);
			cache::delete('events_' . $b);
		}

		file_write($b . '/flags.php', $flags);
	}

	if (isset($_POST['delete'])) {
		foreach ($_POST['delete'] as $i => $d) {
			if (!preg_match('/^[0-9]+$/', $d)) {
				error(_('Nice try.'));
			}
			@unlink("$dir/$d.png");
			$id = explode('.', $d)[0];
			unset(Vi::$config['user_flags'][$id]);
		}
		if(count(Vi::$config['user_flags'])) {
			file_write($b . '/flags.ser', serialize(Vi::$config['user_flags']));
		}
		else {
			@unlink($b . '/flags.php');
			@unlink($b . '/flags.ser');
		}
	}

	if (isset($_POST['alphabetize'])) {
		asort(Vi::$config['user_flags'], SORT_NATURAL | SORT_FLAG_CASE);
		file_write($b . '/flags.ser', serialize(Vi::$config['user_flags']));
	}

	$banners = array_diff(scandir($dir), array('..', '.'));
	mod_page(_('Edit flags'), 'mod/flags.html', array('board' => Vi::$board, 'banners' => $banners, 'token' => make_secure_link_token('banners/' . Vi::$board['uri'])));
}

function mod_8_assets($b) {
	if (!openBoard($b)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!hasPermission(Vi::$config['mod']['edit_assets'], $b)) {
		error(Vi::$config['error']['noaccess']);
	}

	require_once 'inc/image.php';

	$dir = 'static/assets/' . $b;

	if (!is_dir($dir)) {
		mkdir($dir, 0777, true);

		symlink(getcwd() . '/' . Vi::$config['image_deleted'], "$dir/deleted.png");
		symlink(getcwd() . '/' . Vi::$config['spoiler_image'], "$dir/spoiler.png");
		symlink(getcwd() . '/' . Vi::$config['no_file_image'], "$dir/no-file.png");
	}

	// "File deleted"
	if (isset($_FILES['deleted_file']) && !empty($_FILES['deleted_file']['tmp_name'])) {
		$upload    = $_FILES['deleted_file']['tmp_name'];
		$extension = strtolower(mb_substr($_FILES['deleted_file']['name'], mb_strrpos($_FILES['deleted_file']['name'], '.') + 1));

		if (!is_readable($upload)) {
			error(Vi::$config['error']['nomove']);
		}

		if (filesize($upload) > 512000) {
			error(_('File too large!'));
		}

		if (!in_array($extension, array('png', 'gif'))) {
			error(_('File must be PNG or GIF format.'));
		}

		if (!$size = @getimagesize($upload)) {
			error(Vi::$config['error']['invalidimg']);
		}

		if ($size[0] != 140 or $size[1] != 50) {
			error(_('Image wrong size!'));
		}

		unlink("$dir/deleted.png");
		copy($upload, "$dir/deleted.png");
		purge("$dir/deleted.png", true);
	}

	// Spoiler file
	if (isset($_FILES['spoiler_file']) && !empty($_FILES['spoiler_file']['tmp_name'])) {
		$upload    = $_FILES['spoiler_file']['tmp_name'];
		$extension = strtolower(mb_substr($_FILES['spoiler_file']['name'], mb_strrpos($_FILES['spoiler_file']['name'], '.') + 1));

		if (!is_readable($upload)) {
			error(Vi::$config['error']['nomove']);
		}

		if (filesize($upload) > 512000) {
			error(_('File too large!'));
		}

		if (!in_array($extension, array('png', 'gif'))) {
			error(_('File must be PNG or GIF format.'));
		}

		if (!$size = @getimagesize($upload)) {
			error(Vi::$config['error']['invalidimg']);
		}

		if ($size[0] != 128 or $size[1] != 128) {
			error(_('Image wrong size!'));
		}

		unlink("$dir/spoiler.png");
		copy($upload, "$dir/spoiler.png");
		purge("$dir/spoiler.png", true);
	}

	// No file
	if (isset($_FILES['nofile_file']) && !empty($_FILES['nofile_file']['tmp_name'])) {
		$upload    = $_FILES['nofile_file']['tmp_name'];
		$extension = strtolower(mb_substr($_FILES['nofile_file']['name'], mb_strrpos($_FILES['nofile_file']['name'], '.') + 1));

		if (!is_readable($upload)) {
			error(Vi::$config['error']['nomove']);
		}

		if (filesize($upload) > 512000) {
			error(_('File too large!'));
		}

		if (!in_array($extension, array('png', 'gif'))) {
			error(_('File must be PNG or GIF format.'));
		}

		if (!$size = @getimagesize($upload)) {
			error(Vi::$config['error']['invalidimg']);
		}

		if ($size[0] != 500 or $size[1] != 500) {
			error(_('Image wrong size!'));
		}

		unlink("$dir/no-file.png");
		copy($upload, "$dir/no-file.png");
		purge("$dir/no-file.png", true);
	}

	mod_page(_('Edit board assets'), 'mod/assets.html', array('board' => Vi::$board, 'token' => make_secure_link_token('assets/' . Vi::$board['uri'])));
}

function mod_8_banners($b) {
	//error('Banner editing is currently disabled. Please check back later!');

	if (!openBoard($b)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!hasPermission(Vi::$config['mod']['edit_banners'], $b)) {
		error(Vi::$config['error']['noaccess']);
	}

	require_once 'inc/image.php';

	$dir = 'static/banners/' . $b;

	if (!is_dir($dir)) {
		mkdir($dir, 0777, true);
	}

	if (isset($_FILES['file'])) {
		$upload  = $_FILES['file']['tmp_name'];
		$banners = array_diff(scandir($dir), array('..', '.'));

		if (!is_readable($upload)) {
			error(Vi::$config['error']['nomove']);
		}

		$id        = time() . substr(microtime(), 2, 3);
		$extension = strtolower(mb_substr($_FILES['file']['name'], mb_strrpos($_FILES['file']['name'], '.') + 1));

		if (!in_array($extension, array('jpg', 'jpeg', 'png', 'gif'))) {
			error(_('Not an image extension.'));
		}

		if (filesize($upload) > 512000) {
			error(_('File too large!'));
		}

		if (!$size = @getimagesize($upload)) {
			error(Vi::$config['error']['invalidimg']);
		}

		if ($size[0] != 300 or $size[1] != 100) {
			error(_('Image wrong size!'));
		}
		if (sizeof($banners) >= 50) {
			error(_('Too many banners.'));
		}

		copy($upload, "$dir/$id.$extension");
	}

	if (isset($_POST['delete'])) {
		foreach ($_POST['delete'] as $i => $d) {
			if (!preg_match('/^[0-9]+\.(png|jpeg|jpg|gif)$/', $d)) {
				error(_('Nice try.'));
			}
			unlink("$dir/$d");
		}
	}

	$banners = array_diff(scandir($dir), array('..', '.'));
	mod_page(_('Edit banners'), 'mod/banners.html', array('board' => Vi::$board, 'banners' => $banners, 'token' => make_secure_link_token('banners/' . Vi::$board['uri'])));
}

function mod_8_settings($b) {
	//if ($b === 'infinity' && Vi::$mod['type'] !== ADMIN)
	//	error('Settings temporarily disabled for this board.');

	if (!openBoard($b)) {
		error(Vi::$config['error']['noboard']);
	}

	if (!in_array($b, Vi::$mod['boards']) and Vi::$mod['boards'][0] != '*') {
		error(Vi::$config['error']['noaccess']);
	}

	if (!hasPermission(Vi::$config['mod']['edit_settings'], $b)) {
		error(Vi::$config['error']['noaccess']);
	}

	// $possible_languages = array_diff(scandir('inc/locale/'), array('..', '.', '.tx', 'README.md'));
	$possible_languages = array_flip(array_diff(scandir('inc/locale/'), array('..', '.', '.tx', 'README.md')));
	
	ksort($possible_languages);
	foreach ($possible_languages as $k => $v) {		
		$possible_languages[$k] = languageName($k, true)['full'];
	}
	// echo _var_dump($possible_languages, true);

	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		$board_type = $_POST['board_type'];
		$imgboard   = $board_type == 'imgboard';
		$txtboard   = $board_type == 'txtboard';
		$fileboard  = $board_type == 'fileboard';

		$config = "<?php\ndefined('TINYBOARD') or exit;\n";

		$config.= "Vi::\$config['field_disable_name'] = " . (isset($_POST['field_disable_name']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['field_disable_email'] = " . (isset($_POST['field_disable_email']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['field_disable_subject'] = " . (isset($_POST['field_disable_subject']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['field_disable_reply_subject'] = " . (isset($_POST['field_disable_reply_subject']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['enable_embedding'] = " . (isset($_POST['enable_embedding']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['force_image_op'] = " . ($imgboard && isset($_POST['force_image_op']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['disable_images'] = " . ($txtboard ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['poster_ids'] = " . (isset($_POST['poster_ids']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['show_sages'] = " . (isset($_POST['show_sages']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['auto_unicode'] = " . (isset($_POST['auto_unicode']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['strip_combining_chars'] = " . (isset($_POST['strip_combining_chars']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['allow_roll'] = " . (isset($_POST['allow_roll']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['image_reject_repost'] = " . (isset($_POST['image_reject_repost']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['image_reject_repost_in_thread'] = " . (isset($_POST['image_reject_repost_in_thread']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['early_404'] = " . (isset($_POST['early_404']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['allow_delete'] = " . (isset($_POST['allow_delete']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['mod']['view_bumplock'] = " . (isset($_POST['view_bumplock']) ? '-1' : 'MOD') . ";\n";
		$config.= "Vi::\$config['captcha']['enabled'] = " . (isset($_POST['captcha']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['force_subject_op'] = " . (isset($_POST['force_subject_op']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['force_flag'] = " . (isset($_POST['force_flag']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['tor_posting'] = " . (isset($_POST['tor_posting']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['tor_image_posting'] = " . (isset($_POST['tor_image_posting']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['tor']['allow_posting'] = " . (isset($_POST['allow_posting']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['tor']['allow_image_posting'] = " . (isset($_POST['allow_image_posting']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['new_thread_capt'] = " . (isset($_POST['new_thread_capt']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['oekaki'] = " . (($imgboard || $fileboard) && isset($_POST['oekaki']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['code_tags'] = " . (isset($_POST['code_tags']) ? 'true' : 'false') . ";\n";
		$config.= "Vi::\$config['katex'] = " . (isset($_POST['katex']) ? 'true' : 'false') . ";\n";
		
		if(isset($_POST['katex'])) $config.= 'if(Vi::$config[\'katex\']) Vi::$config[\'markup\'][] = array("/\[tex\](.+?)\[\/tex\]/ms", "<span class=\'tex\'>\$1</span>");' . "\n";
		if(isset($_POST['code_tags'])) $config.= 'if(Vi::$config[\'code_tags\']) Vi::$config[\'markup\'][] = array("/\[code\](.+?)\[\/code\]/ms", "<code><pre class=\'prettyprint\' style=\'display:inline-block\'>$1</pre></code>");' . "\n";
		
		/*:)if (isset($_POST['tor_image_posting']) && isset($_POST['meta_noindex'])) {
			error(_('Please index your board to enable tor posting.'));
		}:)*/ 

		if (isset($_POST['max_images']) && (int) $_POST['max_images'] && (int) $_POST['max_images'] <= 5) {
			$_POST['max_images'] = (int) $_POST['max_images'];
			$config.= "Vi::\$config['max_images'] = {$_POST['max_images']};\n";
		}

		if (isset($_POST['custom_assets'])) {
			$config.= "Vi::\$config['custom_assets'] = true;\n";
			$config.= "Vi::\$config['spoiler_image'] = 'static/assets/$b/spoiler.png';\n";
			$config.= "Vi::\$config['image_deleted'] = 'static/assets/$b/deleted.png';\n";
			$config.= "Vi::\$config['no_file_image'] = 'static/assets/$b/no-file.png';\n";
		}

		if ($imgboard && isset($_POST['allowed_type_imgboard'])) {
			$config.= "Vi::\$config['allowed_ext_files'] = array();\n";
			foreach ($_POST['allowed_type_imgboard'] as $val) {
				if (in_array($val, Vi::$config['imgboard_allowed_types'])) {
					$config.= "Vi::\$config['allowed_ext_files'][] = '$val';\n";
				}
			}
		}

		if ($fileboard) {
			$config.= "Vi::\$config['force_image_op'] = true;\n";
			$config.= "Vi::\$config['threads_per_page'] = 30;\n";
			$config.= "Vi::\$config['file_board'] = true;\n";
			$config.= "Vi::\$config['threads_preview'] = 0;\n";
			$config.= "Vi::\$config['threads_preview_sticky'] = 0;\n";
			$config.= "Vi::\$config['allowed_ext_files'] = array();\n";

			if (isset($_POST['allowed_type_fileboard'])) {
				foreach ($_POST['allowed_type_fileboard'] as $val) {
					if (in_array($val, Vi::$config['fileboard_allowed_types'])) {
						$config.= "Vi::\$config['allowed_ext_files'][] = '$val';\n";
					}
				}
			}

			if (isset($_POST['allowed_ext_op'])) {
				$config .= "Vi::\$config['allowed_ext_op'] = Vi::\$config['allowed_ext_files'];\n";

				if (isset($_POST['allowed_ext_op_video'])) {
					$config.= "Vi::\$config['allowed_ext_op'][] = 'webm';\n";
					$config.= "Vi::\$config['allowed_ext_op'][] = 'mp4';\n";
				}
			}

			if (isset($_POST['tag_id'])) {
				$add = NULL;
				foreach ($_POST['tag_id'] as $id => $v) {
					if(empty($_POST['tag_id'][$id]) || empty($_POST['tag_desc'][$id])) {
						continue;
					}
					$add.= "Vi::\$config['allowed_tags'][";
					$add.= 'base64_decode("';
					$add.= base64_encode($_POST['tag_id'][$id]);
					$add.= '")';
					$add.= "] = ";
					$add.= 'base64_decode("';
					$add.= base64_encode($_POST['tag_desc'][$id]);
					$add.= '")';
					$add.= ";\n";
				}
				if($add) {
					$config.= "Vi::\$config['allowed_tags'] = [];\n";
					$config.= $add;
				}
			}
		}

		if(isset($_POST['anal_filenames']) && $fileboard) {
			$config.= "Vi::\$config['filename_func'] = 'filename_func';\n";
		} 

		$config.= "Vi::\$config['anonymous'] = base64_decode('" . base64_encode(htmlspecialchars($_POST['anonymous'])) . "');\n";
		$config.= "Vi::\$config['blotter'] = base64_decode('" . base64_encode(purify_html(html_entity_decode($_POST['blotter']))) . "');\n";

		if (isset($_POST['replace'])) {
			if (sizeof($_POST['replace']) > 200 || sizeof($_POST['with']) > 200) {
				error(_('Sorry, max 200 wordfilters allowed.'));
			}

			if (count($_POST['replace']) == count($_POST['with'])) {
				foreach ($_POST['replace'] as $i => $r) {
					if ($r !== '') {
						$w = $_POST['with'][$i];

						if (strlen($w) > 255) {
							error(sprintf(_('Sorry, %s is too long. Max replacement is 255 characters'), utf8tohtml($w)));
						}

						$config.= "Vi::\$config['wordfilters'][] = array(base64_decode('" . base64_encode($r) . "'), base64_decode('" . base64_encode($w) . "'));\n";
					}
				}
			}

			if (is_billion_laughs($_POST['replace'], $_POST['with'])) {
				error(_('Wordfilters may not wordfilter previous wordfilters. For example, if a filters to bb and b filters to cc, that is not allowed.'));
			}
		}

		$hour_max_threads = 'false';
		if (isset($_POST['hour_max_threads']) && (int) $_POST['hour_max_threads'] > 0 && (int) $_POST['hour_max_threads'] < 101) {
			$hour_max_threads = (int) $_POST['hour_max_threads'];
		}

		$config.= "Vi::\$config['hour_max_threads'] = $hour_max_threads;\n";

		$max_pages = 15;
		if (isset($_POST['max_pages'])) {
			$mp = (int) $_POST['max_pages'];
			if ($mp > 25 || $mp < 1) {
				$max_pages = 15;
			} else {
				$max_pages = $mp;
			}
		}

		$config.= "Vi::\$config['max_pages'] = " . $max_pages . ";\n";

		$reply_limit = 250;
		if (isset($_POST['reply_limit'])) {
			$rl = (int) $_POST['reply_limit'];
			if ($rl > 750 || $rl < 250 || $rl % 25) {
				$reply_limit = 250;
			} else {
				$reply_limit = $rl;
			}
		}
		$config.= "Vi::\$config['reply_limit'] = " . $reply_limit . ";\n";

		$max_newlines = 0;
		if (isset($_POST['max_newlines'])) {
			$mn = (int) $_POST['max_newlines'];
			if ($mn < 20 || $mn > 300) {
				$max_newlines = 0;
			} else {
				$max_newlines = $mn;
			}
		}
		$config.= "Vi::\$config['max_newlines'] = " . $max_newlines . ";\n";

		$min_body = 0;
		if (isset($_POST['min_body'])) {
			$mb = (int) $_POST['min_body'];
			if ($mb < 0 || $mb > 1024) {
				$min_body = 0;
			} else {
				$min_body = $mb;
			}
		}
		$config.= "Vi::\$config['min_body'] = " . $min_body . ";\n";

		switch($_POST['country_flags_select']) {
			case 'disabled':
			case 'enabled':
				$config.= "Vi::\$config['country_flags'] = " . ($_POST['country_flags_select'] == 'enabled' ? 'true' : 'false') . ";\n";
				break;

			case 'user_flag':
				if(!file_exists($b . '/flags.php')) {
					error(sprintf(_('Please edit a <a href="%s">user flags</a>'), (Vi::$config['file_mod'] . '?/flags/' . $b)));
				}
				$config.= "if (file_exists('$b/flags.php')) include '$b/flags.php';\n";
				break;
		}

		$locale = $locale_old = BoardLocale($b);

		// if (in_array($_POST['locale'], $possible_languages)) {
		if (array_key_exists($_POST['locale'], $possible_languages)) {
			$locale = $_POST['locale'];
			file_put_contents($b . '/locale', $locale);
		}

		$title = $_POST['title'];
		$subtitle = $_POST['subtitle'];

		if (!(strlen($title) < 40)) {
			error(_('Invalid title'));
		}

		if (!(strlen($subtitle) < 200)) {
			error(_('Invalid subtitle'));
		}

		$query = prepare('UPDATE ``boards`` SET `title` = :title, `subtitle` = :subtitle, `indexed` = :indexed, `public_bans` = :public_bans, `public_logs` = :public_logs WHERE `uri` = :uri');
		$query->bindValue(':title', $title);
		$query->bindValue(':subtitle', $subtitle);
		$query->bindValue(':uri', $b);
		$query->bindValue(':indexed', !isset($_POST['meta_noindex']));
		$query->bindValue(':public_bans', isset($_POST['public_bans']));
		$query->bindValue(':public_logs', (int) $_POST['public_logs']);
		$query->execute() or error(db_error($query));

		// Clean up our CSS...no more expression() or off-site URLs.
		$clean_css = preg_replace('/expression\s*\(/', '', $_POST['css']);

		$matched = array();

		preg_match_all("#" . Vi::$config['link_regex'] . "#im", $clean_css, $matched);

		if (isset($matched[0])) {
			foreach ($matched[0] as $match) {
				$match_okay = false;
				foreach (Vi::$config['allowed_offsite_urls'] as $allowed_url) {
					if (strpos($match, $allowed_url) === 0) {
						$match_okay = true;
					}
				}
				if ($match_okay !== true) {
					error(sprintf(_("Off-site link \"%s\" is not allowed in the board stylesheet"), $match));
				}
			}
		}

		//Filter out imports from sites with potentially unsafe content
		$match_imports = '@import[^;]*';
		$matched       = array();
		preg_match_all("#$match_imports#im", $clean_css, $matched);

		$unsafe_import_urls = array('https://a.pomf.se/');

		if (isset($matched[0])) {
			foreach ($matched[0] as $match) {
				$match_okay = true;
				foreach ($unsafe_import_urls as $unsafe_import_url) {
					if (strpos($match, $unsafe_import_url) !== false && strpos($match, '#') === false) {
						$match_okay = false;
					}
				}
				if ($match_okay !== true) {
					error(sprintf(_("Potentially unsafe import \"%s\" is not allowed in the board stylesheet"), $match));
				}
			}
		}

		$clean_css = str_replace(array("\n", "\r"), NULL, $clean_css);
		if (!empty($clean_css)) {
			$config.= "Vi::\$config['stylesheets']['Custom'] = 'board/$b.css';\n";
			$config.= "Vi::\$config['default_stylesheet'] = array('Custom', Vi::\$config['stylesheets']['Custom']);\n";
		}

		$config.= "if(Vi::\$config['disable_images']) Vi::\$config['max_pages'] = 10000;\n";
		$config.= @file_get_contents($b . '/extra_config.php');

		file_write($b . '/config.php', $config);
		file_write('stylesheets/board/' . $b . '.css', $clean_css);
		if(function_exists('opcache_invalidate')) opcache_invalidate(realpath($b) . '/config.php');
		
		$_config = Vi::$config;

		// Перезагрузить конфиги
		openBoard($b);

		$rebuild = false;

		if($locale != $locale_old) $rebuild = true;
		else if($_config['captcha']['enabled'] != Vi::$config['captcha']['enabled']) $rebuild = true;
		else if($_config['new_thread_capt'] != Vi::$config['new_thread_capt']) $rebuild = true;
		else if($_config['blotter'] != Vi::$config['blotter']) $rebuild = true;
		else if($_config['field_disable_name'] != Vi::$config['field_disable_name']) $rebuild = true;
		else if($_config['field_disable_email'] != Vi::$config['field_disable_email']) $rebuild = true;
		else if($_config['field_disable_subject'] != Vi::$config['field_disable_subject']) $rebuild = true;
		else if($_config['field_disable_reply_subject'] != Vi::$config['field_disable_reply_subject']) $rebuild = true;
		else if($_config['poster_ids'] != Vi::$config['poster_ids']) $rebuild = true;
		else if($_config['show_sages'] != Vi::$config['show_sages']) $rebuild = true;
		else if($_config['country_flags'] != Vi::$config['country_flags']) $rebuild = true;
		else if($_config['enable_embedding'] != Vi::$config['enable_embedding']) $rebuild = true;
		else if($_config['user_flags'] != Vi::$config['user_flags']) $rebuild = true;
		else if($_config['oekaki'] != Vi::$config['oekaki']) $rebuild = true;
		else if($_config['code_tags'] != Vi::$config['code_tags']) $rebuild = true;
		else if($_config['katex'] != Vi::$config['katex']) $rebuild = true;

		if($rebuild) {
			buildIndex();
			$query = query(sprintf("SELECT `id` FROM ``posts_%s`` WHERE `thread` IS NULL", $b)) or error(db_error());
			while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
				buildThread($post['id']);
			}
		}

		// Clean the cache
		if (Vi::$config['cache']['enabled']) {
			cache::delete('board_' . Vi::$board['uri']);
			cache::delete('all_boards');

			cache::delete('config_' . Vi::$board['uri']);
			cache::delete('events_' . Vi::$board['uri']);
		}

		modLog('Edited board settings', $b);
		header('Location: ?/settings/' . $b, true, Vi::$config['redirect_http']);
		exit;
	}

	$css = @file_get_contents('stylesheets/board/' . Vi::$board['uri'] . '.css');

	Vi::$config['locale'] = Vi::$current_locale;
	mod_page(_('Board configuration').' /'.Vi::$board['uri'].'/', 'mod/settings.html', array('board' => Vi::$board, 'css' => prettify_textarea($css), 'token' => make_secure_link_token('settings/' . Vi::$board['uri']), 'languages' => $possible_languages, 'allowed_urls' => Vi::$config['allowed_offsite_urls']));
}
