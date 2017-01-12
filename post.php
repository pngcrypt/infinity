<?php
/*
 *  Copyright (c) 2010-2014 Tinyboard Development Group
 */
require "./inc/functions.php";
require "./inc/anti-bot.php";

if ((!isset($_POST['mod']) || !$_POST['mod']) && Vi::$config['board_locked']) {
    error("Board is locked");
}

$dropped_post = false;

// Is it a post coming from NNTP? Let's extract it and pretend it's a normal post.
if (isset($_GET['Newsgroups']) && Vi::$config['nntpchan']['enabled']) {
	if ($_SERVER['REMOTE_ADDR'] != Vi::$config['nntpchan']['trusted_peer']) {
		error("NNTPChan: Forbidden. $_SERVER[REMOTE_ADDR] is not a trusted peer");
	}

	$_POST = array();
	$_POST['json_response'] = true;

	$headers = json_encode($_GET);

	if (!isset ($_GET['Message-Id'])) {
		if (!isset ($_GET['Message-ID'])) {
			error("NNTPChan: No message ID");
		}
		else $msgid = $_GET['Message-ID'];
	}
	else $msgid = $_GET['Message-Id'];

	$groups = preg_split("/,\s*/", $_GET['Newsgroups']);
	if (count($groups) != 1) {
		error("NNTPChan: Messages can go to only one newsgroup");
	}
	$group = $groups[0];

	if (!isset(Vi::$config['nntpchan']['dispatch'][$group])) {
		error("NNTPChan: We don't synchronize $group");
	}
	$xboard = Vi::$config['nntpchan']['dispatch'][$group];

	$ref = null;
	if (isset ($_GET['References'])) {
		$refs = preg_split("/,\s*/", $_GET['References']);

		if (count($refs) > 1) {
			error("NNTPChan: We don't support multiple references");
		}

		$ref = $refs[0];

		$query = prepare("SELECT `board`,`id` FROM ``nntp_references`` WHERE `message_id` = :ref");
                $query->bindValue(':ref', $ref);
                $query->execute() or error(db_error($query));

		$ary = $query->fetchAll(PDO::FETCH_ASSOC);

		if (count($ary) == 0) {
			error("NNTPChan: We don't have $ref that $msgid references");
		}

		$p_id = $ary[0]['id'];
		$p_board = $ary[0]['board'];

		if ($p_board != $xboard) {
			error("NNTPChan: Cross board references not allowed. Tried to reference $p_board on $xboard");
		}

		$_POST['thread'] = $p_id;
	}

	$date = isset($_GET['Date']) ? strtotime($_GET['Date']) : time();

	list($ct) = explode('; ', $_GET['Content-Type']);

	$query = prepare("SELECT COUNT(*) AS `c` FROM ``nntp_references`` WHERE `message_id` = :msgid");
	$query->bindValue(":msgid", $msgid);
	$query->execute() or error(db_error($query));

	$a = $query->fetch(PDO::FETCH_ASSOC);
	if ($a['c'] > 0) {
		error("NNTPChan: We already have this post. Post discarded.");
	}

	if ($ct == 'text/plain') {
		$content = file_get_contents("php://input");
	}
	elseif ($ct == 'multipart/mixed' || $ct == 'multipart/form-data') {
		_syslog(LOG_INFO, "MM: Files: ".print_r($GLOBALS, true)); // Debug

		$content = '';

		$newfiles = array();
		foreach ($_FILES['attachment']['error'] as $id => $error) {
			if ($_FILES['attachment']['type'][$id] == 'text/plain') {
				$content .= file_get_contents($_FILES['attachment']['tmp_name'][$id]);
			}
			elseif ($_FILES['attachment']['type'][$id] == 'message/rfc822') { // Signed message, ignore for now
			}
			else { // A real attachment :^)
				$file = array();
				$file['name']     = $_FILES['attachment']['name'][$id];
				$file['type']     = $_FILES['attachment']['type'][$id];
				$file['size']     = $_FILES['attachment']['size'][$id];
				$file['tmp_name'] = $_FILES['attachment']['tmp_name'][$id];
				$file['error']    = $_FILES['attachment']['error'][$id];

				$newfiles["file$id"] = $file;
			}
		}

		$_FILES = $newfiles;
	}
	else {
		error("NNTPChan: Wrong mime type: $ct");
	}

	$_POST['subject'] = isset($_GET['Subject']) ? ($_GET['Subject'] == 'None' ? '' : $_GET['Subject']) : '';
	$_POST['board'] = $xboard;

	if (isset ($_GET['From'])) {
		list($name, $mail) = explode(" <", $_GET['From'], 2);
		$mail = preg_replace('/>\s+$/', '', $mail);

		$_POST['name'] = $name;
		//$_POST['email'] = $mail;
		$_POST['email'] = '';
	}

	if (isset ($_GET['X_Sage'])) {
		$_POST['email'] = 'sage';
	}

	$content = preg_replace_callback('/>>([0-9a-fA-F]{6,})/', function($id) use ($xboard) {
		$id = $id[1];

		$query = prepare("SELECT `board`,`id` FROM ``nntp_references`` WHERE `message_id_digest` LIKE :rule");
		$idx = $id . "%";
                $query->bindValue(':rule', $idx);
                $query->execute() or error(db_error($query));

		$ary = $query->fetchAll(PDO::FETCH_ASSOC);
		if (count($ary) == 0) {
			return ">>>>$id";
		}
		else {
			$ret = array();
			foreach ($ary as $v) {
				if ($v['board'] != $xboard) {
					$ret[] = ">>>/".$v['board']."/".$v['id'];
				}
				else {
					$ret[] = ">>".$v['id'];
				}
			}
			return implode($ret, ", ");
		}
	}, $content);

	$_POST['body'] = $content;

	$dropped_post = array(
		'date' => $date,
		'board' => $xboard,
		'msgid' => $msgid,
		'headers' => $headers,
		'from_nntp' => true,
	);
}
elseif (isset($_GET['Newsgroups'])) {
	error("NNTPChan: NNTPChan support is disabled");
}

if (isset($_POST['delete'])) {
	// Delete

	if (!isset($_POST['board'], $_POST['password']))
		error(Vi::$config['error']['bot']);

	$password = &$_POST['password'];

	if ($password == '')
		error(Vi::$config['error']['invalidpassword']);

	$delete = array();
	foreach ($_POST as $post => $value) {
		if (preg_match('/^delete_(\d+)$/', $post, $m)) {
			$delete[] = (int)$m[1];
		}
	}

	if (!Vi::$config['tor_allow_delete'] && checkDNSBL()) error(_("Tor users may not delete posts."));

	// Check if board exists
	if (!openBoard($_POST['board']))
		error(Vi::$config['error']['noboard']);

	// Check if banned
	checkBan(Vi::$board['uri']);

	// Check if deletion enabled
	if (!Vi::$config['allow_delete'])
		error(_('Users are not allowed to delete their own posts on this board.'));

	if (empty($delete))
		error(Vi::$config['error']['nodelete']);

	foreach ($delete as &$id) {
		$query = prepare(sprintf("SELECT `thread`, `time`,`password` FROM ``posts_%s`` WHERE `id` = :id", Vi::$board['uri']));
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		if ($post = $query->fetch(PDO::FETCH_ASSOC)) {
			$thread = false;
			if (Vi::$config['user_moderation'] && $post['thread']) {
				$thread_query = prepare(sprintf("SELECT `time`,`password` FROM ``posts_%s`` WHERE `id` = :id", Vi::$board['uri']));
				$thread_query->bindValue(':id', $post['thread'], PDO::PARAM_INT);
				$thread_query->execute() or error(db_error($query));

				$thread = $thread_query->fetch(PDO::FETCH_ASSOC);
			}

			$salt = $post['time'] .  Vi::$board['uri'];
			$password = hash_pbkdf2("sha256", $password, $salt, 10000, 20);

			if ($password != '' && $post['password'] != $password && (!$thread || $thread['password'] != $password))
				error(Vi::$config['error']['invalidpassword']);

			$time = time();
			if ($post['time'] > $time - Vi::$config['delete_time'] && (!$thread || $thread['password'] != $password)) {
				error(sprintf(Vi::$config['error']['delete_too_soon'], until($post['time'] + Vi::$config['delete_time'])));
			}

			if (!$post['thread'] && $post['time'] < $time - Vi::$config['delete_time_thread']) {
				error(sprintf(Vi::$config['error']['delete_too_soon_thread'], ago($time - Vi::$config['delete_time_thread'])));
			}

			if (isset($_POST['file'])) {
				// Delete just the file
				deleteFile($id);
				modLog("User deleted file from his own post #$id");
			} else {
				// Delete entire post
				deletePost($id);
				modLog("User deleted his own post #$id");
			}

			_syslog(LOG_INFO, 'Deleted post: ' .
				'/' . Vi::$board['dir'] . Vi::$config['dir']['res'] . sprintf(Vi::$config['file_page'], $post['thread'] ? $post['thread'] : $id) . ($post['thread'] ? '#' . $id : '')
			);
		}
	}

	buildIndex();

	$is_mod = isset($_POST['mod']) && $_POST['mod'];
	$root = $is_mod ? Vi::$config['root'] . Vi::$config['file_mod'] . '?/' : Vi::$config['root'];

	if (!isset($_POST['json_response'])) {
		header('Location: ' . $root . Vi::$board['dir'] . Vi::$config['file_index'], true, Vi::$config['redirect_http']);
	} else {
		header('Content-Type: text/json');
		echo json_encode(array('success' => true));
	}

        // We are already done, let's continue our heavy-lifting work in the background (if we run off FastCGI)
        if (function_exists('fastcgi_finish_request'))
                @fastcgi_finish_request();

	rebuildThemes('post-delete', Vi::$board['uri']);

} elseif (isset($_POST['report'])) {
	if (!isset($_POST['board'], $_POST['reason']))
		error(Vi::$config['error']['bot']);

	$report = array();
	foreach ($_POST as $post => $value) {
		if (preg_match('/^delete_(\d+)$/', $post, $m)) {
			$report[] = (int)$m[1];
		}
	}

	if (checkDNSBL()) error("Tor users may not report posts.");

	// Check if board exists
	if (!openBoard($_POST['board']))
		error(Vi::$config['error']['noboard']);

	// Check if banned
	checkBan(Vi::$board['uri']);

	if (empty($report))
		error(Vi::$config['error']['noreport']);

	if (count($report) > Vi::$config['report_limit'])
		error(Vi::$config['error']['toomanyreports']);

	if (Vi::$config['report_captcha'] && !isset($_POST['captcha_text'])) {
		error(Vi::$config['error']['bot']);
	}

	if (Vi::$config['report_captcha'] && !chanCaptcha::check()) {
		$error = Vi::$config['error']['captcha'];
	}

	if (isset($error)) {
		$body = Element('report.html', array('board' => Vi::$board, 'config' => Vi::$config, 'error' => $error, 'reason_prefill' => $_POST['reason'], 'post' => 'delete_'.$report[0], 'global' => isset($_POST['global'])));
		echo Element('page.html', ['config' => Vi::$config, 'body' => $body, 'no_logo' => true]);
		die();
	}

	$reason = escape_markup_modifiers($_POST['reason']);
	markup($reason);

	foreach ($report as &$id) {
		$query = prepare(
			"SELECT
				`thread`,
				`post_clean`.`clean_local`,
				`post_clean`.`clean_global`
			FROM `posts_" . Vi::$board['uri'] . "`
			LEFT JOIN `post_clean`
				ON `post_clean`.`board_id` = '" . Vi::$board['uri'] . "'
				AND `post_clean`.`post_id` = :id
			WHERE `id` = :id"
		);
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		if( $post = $query->fetch(PDO::FETCH_ASSOC) ) {
			$report_local  = !$post['clean_local'];
			$report_global = isset($_POST['global']) && !$post['clean_global'];

			if( $report_local || $report_global ) {
				$thread = $post['thread'];

				if (Vi::$config['syslog']) {
					_syslog(LOG_INFO, 'Reported post: ' .
						'/' . Vi::$board['dir'] . Vi::$config['dir']['res'] . sprintf(Vi::$config['file_page'], $thread ? $thread : $id) . ($thread ? '#' . $id : '') .
						' for "' . $reason . '"'
					);
				}

				$query = prepare("INSERT INTO `reports` (`time`, `ip`, `board`, `post`, `reason`, `local`, `global`) VALUES (:time, :ip, :board, :post, :reason, :local, :global)");
				$query->bindValue(':time',   time(), PDO::PARAM_INT);
				$query->bindValue(':ip',     GetIp(), PDO::PARAM_STR);
				$query->bindValue(':board',  Vi::$board['uri'], PDO::PARAM_INT);
				$query->bindValue(':post',   $id, PDO::PARAM_INT);
				$query->bindValue(':reason', $reason, PDO::PARAM_STR);
				$query->bindValue(':local',  $report_local, PDO::PARAM_BOOL);
				$query->bindValue(':global', $report_global, PDO::PARAM_BOOL);
				$query->execute() or error(db_error($query));
			}
		}
	}

	$is_mod = isset($_POST['mod']) && $_POST['mod'];
	$root = $is_mod ? Vi::$config['root'] . Vi::$config['file_mod'] . '?/' : Vi::$config['root'];

	if (!isset($_POST['json_response'])) {
		$index = $root . Vi::$board['dir'] . Vi::$config['file_index'];
		echo Element('page.html', array('config' => Vi::$config, 'body' => '<div style="text-align:center"><a href="javascript:window.close()">[ ' . _('Close window') ." ]</a> <a href='$index'>[ " . _('Return') . ' ]</a></div>', 'title' => _('Report submitted!')));
	} else {
		header('Content-Type: text/json');
		echo json_encode(array('success' => true));
	}
} elseif (isset($_POST['post']) || $dropped_post) {
	if ((!isset($_POST['body'], $_POST['board']) || @$_POST['message'] != '') && !$dropped_post) {
		error(Vi::$config['error']['bot']);
	}

	$post = array(
		'board' => $_POST['board'],
		'files' => array(),
		'time'  => time(), // Timezone independent UNIX timecode.
	);

	// Check if board exists
	if (!openBoard($post['board']))
		error(Vi::$config['error']['noboard']);

	if (!isset($_POST['name']))
		$_POST['name'] = Vi::$config['anonymous'];

	if (!isset($_POST['email']))
		$_POST['email'] = '';

	if (!isset($_POST['subject']))
		$_POST['subject'] = '';

	if (!isset($_POST['password']))
		$_POST['password'] = '';

	if (isset($_POST['thread'])) {
		$post['op'] = false;
		$post['thread'] = round($_POST['thread']);
	} else
		$post['op'] = true;

	if (!$dropped_post) {
		// Check if banned
		checkBan(Vi::$board['uri']);

		// Check for CAPTCHA right after opening the board so the "return" link is in there
		if (Vi::$config['recaptcha']) {
			if (!isset($_POST['recaptcha_challenge_field']) || !isset($_POST['recaptcha_response_field']))
				error(Vi::$config['error']['bot']);
			// Check what reCAPTCHA has to say...
			$resp = recaptcha_check_answer(Vi::$config['recaptcha_private'],
				GetIp(),
				$_POST['recaptcha_challenge_field'],
				$_POST['recaptcha_response_field']);
			if (!$resp->is_valid) {
				error(Vi::$config['error']['captcha']);
			}
		}

		// Same, but now with our custom captcha provider
		//New thread captcha
		if ((Vi::$config['captcha']['enabled']) || (($post['op']) && (Vi::$config['new_thread_capt'])) ) {
			if (!chanCaptcha::check()) {
				error(Vi::$config['error']['captcha']);
			}
		}

		if (Tor_Session::check() && !Tor_Session::$user['allow_post'] && (Vi::$config['tor']['allow_posting'] || Vi::$config['tor']['force_disable'])) {
			error(sprintf(_('.onion users need to pass security check %s on this page %s'),
				'<a target="new" href="/tor_bypass.php?board='.Vi::$board['uri'].'">', '</a>')
			);
		}

		//if (!(($post['op'] && $_POST['post'] == Vi::$config['button_newtopic']) ||
			//(!$post['op'] && $_POST['post'] == Vi::$config['button_reply'])))
			//error(Vi::$config['error']['bot']);

		// Check the referrer
		if (!Tor_Session::check() && Vi::$config['referer_match'] !== false &&
			(!isset($_SERVER['HTTP_REFERER']) || !preg_match(Vi::$config['referer_match'], rawurldecode($_SERVER['HTTP_REFERER'])))) {
			error(Vi::$config['error']['referer']);
		}

		if ($post['mod'] = isset($_POST['mod']) && $_POST['mod']) {
			check_login(false);
			if (!Vi::$mod) {
				// Liar. You're not a mod.
				error(Vi::$config['error']['notamod']);
			}

			$post['sticky'] = $post['op'] && isset($_POST['sticky']);
			$post['locked'] = $post['op'] && isset($_POST['lock']);
			$post['raw'] = isset($_POST['raw']);

			if ($post['sticky'] && !hasPermission(Vi::$config['mod']['sticky'], Vi::$board['uri']))
				error(Vi::$config['error']['noaccess']);
			if ($post['locked'] && !hasPermission(Vi::$config['mod']['lock'], Vi::$board['uri']))
				error(Vi::$config['error']['noaccess']);
			if ($post['raw'] && !hasPermission(Vi::$config['mod']['rawhtml'], Vi::$board['uri']))
				error(Vi::$config['error']['noaccess']);
		}

		if (!$post['mod']) {
			$post['antispam_hash'] = checkSpam(array(Vi::$board['uri'], isset($post['thread']) ? $post['thread'] : (Vi::$config['try_smarter'] && isset($_POST['page']) ? 0 - (int)$_POST['page'] : null)));
			if ($post['antispam_hash'] === true)
				error(Vi::$config['error']['spam']);
		}

		if (Vi::$config['robot_enable'] && Vi::$config['robot_mute']) {
			checkMute();
		}
	}
	else {
		Vi::$mod = $post['mod'] = false;
	}

	//Check if thread exists
	if (!$post['op']) {
		$query = prepare(sprintf("SELECT `sticky`,`locked`,`cycle`,`sage` FROM ``posts_%s`` WHERE `id` = :id AND `thread` IS NULL LIMIT 1", Vi::$board['uri']));
		$query->bindValue(':id', $post['thread'], PDO::PARAM_INT);
		$query->execute() or error(db_error());

		if (!$thread = $query->fetch(PDO::FETCH_ASSOC)) {
			// Non-existant
			error(Vi::$config['error']['nonexistant']);
		}
	}


	// Check for an embed field
	if (Vi::$config['enable_embedding'] && isset($_POST['embed']) && !empty($_POST['embed'])) {
		// yep; validate it
		$value = $_POST['embed'];
		foreach (Vi::$config['embedding'] as &$embed) {
			if (preg_match($embed[0], $value)) {
				// Valid link
				$post['embed'] = $value;
				// This is bad, lol.
				$post['no_longer_require_an_image_for_op'] = true;
				break;
			}
		}
		if (!isset($post['embed'])) {
			error(Vi::$config['error']['invalid_embed']);
		}

		if (Vi::$config['image_reject_repost']) {
			if ($p = getPostByEmbed($post['embed'])) {
				error(sprintf(Vi::$config['error']['fileexists'],
					($post['mod'] ? Vi::$config['root'] . Vi::$config['file_mod'] . '?/' : Vi::$config['root']) .
					(Vi::$board['dir'] . Vi::$config['dir']['res'] . ($p['thread'] ? $p['thread'] . '.html#' . $p['id'] : $p['id'] . '.html'))
				));
			}
		} else if (!$post['op'] && Vi::$config['image_reject_repost_in_thread']) {
			if ($p = getPostByEmbedInThread($post['embed'], $post['thread'])) {
				error(sprintf(Vi::$config['error']['fileexistsinthread'],
					($post['mod'] ? Vi::$config['root'] . Vi::$config['file_mod'] . '?/' : Vi::$config['root']) .
					(Vi::$board['dir'] . Vi::$config['dir']['res'] . ($p['thread'] ? $p['thread'] . '.html#' . $p['id'] : $p['id'] . '.html'))
				));
			}
		}
	}

	if (!hasPermission(Vi::$config['mod']['bypass_field_disable'], Vi::$board['uri'])) {
		if (Vi::$config['field_disable_name'])
			$_POST['name'] = Vi::$config['anonymous']; // "forced anonymous"

		if (Vi::$config['field_disable_email'])
			$_POST['email'] = '';

		if (Vi::$config['field_email_selectbox'] && $_POST['email'] != 'sage')
			$_POST['email'] = '';

		if (Vi::$config['field_disable_password'])
			$_POST['password'] = '';

		if (Vi::$config['field_disable_subject'] || (!$post['op'] && Vi::$config['field_disable_reply_subject']))
			$_POST['subject'] = '';
	}

	if (Vi::$config['allow_upload_by_url'] && isset($_POST['file_url']) && !empty($_POST['file_url'])) {
		$post['file_url'] = $_POST['file_url'];
		if (!preg_match('@^https?://@', $post['file_url']))
			error(Vi::$config['error']['invalidimg']);

		if (mb_strpos($post['file_url'], '?') !== false)
			$url_without_params = mb_substr($post['file_url'], 0, mb_strpos($post['file_url'], '?'));
		else
			$url_without_params = $post['file_url'];

		$post['extension'] = strtolower(mb_substr($url_without_params, mb_strrpos($url_without_params, '.') + 1));

		if ($post['op'] && Vi::$config['allowed_ext_op']) {
			if (!in_array($post['extension'], Vi::$config['allowed_ext_op']))
				error(Vi::$config['error']['unknownext']);
		}
		else if (!in_array($post['extension'], Vi::$config['allowed_ext']) && !in_array($post['extension'], Vi::$config['allowed_ext_files']))
			error(Vi::$config['error']['unknownext']);

		$post['file_tmp'] = tempnam(Vi::$config['tmp'], 'url');
		function unlink_tmp_file($file) {
			@unlink($file);
			fatal_error_handler();
		}
		register_shutdown_function('unlink_tmp_file', $post['file_tmp']);

		$fp = fopen($post['file_tmp'], 'w');

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $post['file_url']);
		curl_setopt($curl, CURLOPT_FAILONERROR, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($curl, CURLOPT_TIMEOUT, Vi::$config['upload_by_url_timeout']);
		curl_setopt($curl, CURLOPT_USERAGENT, 'Tinyboard');
		curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
		curl_setopt($curl, CURLOPT_FILE, $fp);
		curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);

		if (curl_exec($curl) === false)
			error(Vi::$config['error']['nomove'] . '<br/>Curl says: ' . curl_error($curl));

		curl_close($curl);

		fclose($fp);

		$_FILES['file'] = array(
			'name' => basename($url_without_params),
			'tmp_name' => $post['file_tmp'],
			'file_tmp' => true,
			'error' => 0,
			'size' => filesize($post['file_tmp'])
		);
	}

	$post['name'] = $_POST['name'] != '' ? $_POST['name'] : Vi::$config['anonymous'];
	$post['subject'] = $_POST['subject'];
	$post['email'] = str_replace(' ', '%20', htmlspecialchars($_POST['email']));

	if (isset($_POST['no-bump'])) {
		if (!empty($post['email']) && $post['email'] !== 'sage') {
			$post['email'] .= '+sage';
		} else {
			$post['email'] = 'sage';
		}
	}

	$post['body'] = $_POST['body'];
	$post['password'] = $_POST['password'];
	$post['has_file'] = (!isset($post['embed']) && (($post['op'] && !isset($post['no_longer_require_an_image_for_op']) && Vi::$config['force_image_op']) || count($_FILES) > 0));

	if (!$dropped_post) {
		// Handle our Tor users
		$tor = checkDNSBL();
		//if ($tor && (isset(Vi::$config['tor_domain']) && !empty(Vi::$config['tor_domain'])))
		//	error('To post on this over Tor, you must use the hidden service for security reasons. You can find it at <a href="' . Vi::$config['tor_domain'] . '">' . Vi::$config['tor_domain'] . '/</a>.');
		if ($tor) {
			if($post['has_file'] && !Vi::$config['tor_image_posting'])
				error(_('Sorry. Tor users can\'t upload files on this board.'));
		
			if ($tor && !Vi::$config['tor_posting'])
				error(_('Sorry. The owner of this board has decided not to allow Tor posters for some reason...'));
		}

		if (Tor_Session::check()) {
			if(!Vi::$config['tor']['allow_posting'] || Vi::$config['tor']['force_disable'])
				error(_('Sorry. The owner of this board has decided not to allow .onion users for some reason...'));
		
			if ($post['has_file'] && !Vi::$config['tor']['allow_image_posting'])
				error(_('Sorry. .onion users can\'t upload files on this board.'));
		}

		if ($post['has_file'] && Vi::$config['disable_images']) {
			error(Vi::$config['error']['images_disabled']);
		}

		if (Vi::$config['force_subject_op'] && $post['op']) {
			$stripped_whitespace = preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $post['subject']);
			if ($stripped_whitespace == '') {
				error(_('It is required to enter a subject when starting a new thread on this board.'));
			}
		}

		if (!$post['op']) {
			// Check if thread is locked
			// but allow mods to post
			if ($thread['locked'] && !hasPermission(Vi::$config['mod']['postinlocked'], Vi::$board['uri']))
				error(Vi::$config['error']['locked']);

			$numposts = numPosts($post['thread']);

			if (Vi::$config['reply_hard_limit'] != 0 && Vi::$config['reply_hard_limit'] <= $numposts['replies'])
				error(Vi::$config['error']['reply_hard_limit']);

			if ($post['has_file'] && Vi::$config['image_hard_limit'] != 0 && Vi::$config['image_hard_limit'] <= $numposts['images'])
				error(Vi::$config['error']['image_hard_limit']);
		}
	}
	else if (!$post['op']) {
		$numposts = numPosts($post['thread']);
	}

	if ($post['has_file']) {
		// Determine size sanity
		$size = 0;
		if (Vi::$config['multiimage_method'] == 'split') {
			foreach ($_FILES as $key => $file) {
				$size += $file['size'];
			}
		} elseif (Vi::$config['multiimage_method'] == 'each') {
			foreach ($_FILES as $key => $file) {
				if ($file['size'] > $size) {
					$size = $file['size'];
				}
			}
		} else {
			error(_('Unrecognized file size determination method.'));
		}

		if ($size > Vi::$config['max_filesize'])
			error(sprintf3(Vi::$config['error']['filesize'], array(
				'sz' => number_format($size),
				'filesz' => number_format($size),
				'maxsz' => number_format(Vi::$config['max_filesize'])
			)));
		$post['filesize'] = $size;
	}

	$post['capcode'] = false;

	if (Vi::$mod && preg_match('/^((.+) )?## (.+)$/', $post['name'], $matches) && (in_array(Vi::$board['uri'], Vi::$mod['boards']) or Vi::$mod['boards'][0] == '*')) {
		$name = $matches[2] != '' ? $matches[2] : Vi::$config['anonymous'];
		$cap = $matches[3];

		if (isset(Vi::$config['mod']['capcode'][Vi::$mod['type']])) {
			if (Vi::$config['mod']['capcode'][Vi::$mod['type']] === true || (is_array(Vi::$config['mod']['capcode'][Vi::$mod['type']]) && in_array($cap, Vi::$config['mod']['capcode'][Vi::$mod['type']]))) {
				$post['capcode'] = utf8tohtml($cap);
				$post['name'] = $name;
			}
		}
	}

	$trip = generate_tripcode($post['name']);
	$post['name'] = $trip[0];
	$post['trip'] = isset($trip[1]) ? $trip[1] : ''; // XX: Dropped posts and tripcodes

	$noko = false;
	if (strtolower($post['email']) == 'noko') {
		$noko = true;
		$post['email'] = '';
	} elseif (strtolower($post['email']) == 'nonoko'){
		$noko = false;
		$post['email'] = '';
	} else $noko = Vi::$config['always_noko'];

	if ($post['has_file']) {
		$i = 0;
		foreach ($_FILES as $key => $file) {
			if ($file['size'] && $file['tmp_name']) {
				$file['filename'] = urldecode($file['name']);
				$file['extension'] = strtolower(mb_substr($file['filename'], mb_strrpos($file['filename'], '.') + 1));
				if (isset(Vi::$config['filename_func']))
					$file['file_id'] = Vi::$config['filename_func']($file);
				else
					$file['file_id'] = time() . substr(microtime(), 2, 3);

				if (sizeof($_FILES) > 1)
					$file['file_id'] .= "-$i";

				$file['file'] = Vi::$config['dir']['img_root'] . Vi::$board['dir'] . Vi::$config['dir']['img'] . $file['file_id'] . '.' . $file['extension'];

				while (file_exists ($file['file'])) {
					$file['file_id'] .= mt_rand(0,9);
					$file['file'] = Vi::$config['dir']['img_root'] . Vi::$board['dir'] . Vi::$config['dir']['img'] . $file['file_id'] . '.' . $file['extension'];
				}

				$ext = (Vi::$config['thumb_ext'] ? Vi::$config['thumb_ext'] : $file['extension']);
				
				if($file['extension'] == 'png') {
					$ext = $file['extension'];
				}
				else if($file['extension'] == 'gif' && Vi::$config['gif_preview_animate']) {
					$ext = $file['extension'];
				}
				$file['thumb'] = Vi::$config['dir']['img_root'] . Vi::$board['dir'] . Vi::$config['dir']['thumb'] . $file['file_id'] . '.' . $ext;
				$post['files'][] = $file;
				$i++;
			}
		}
	}

	if (empty($post['files'])) $post['has_file'] = false;

	if (!$dropped_post) {
		// Check for a file
		if ($post['op'] && !isset($post['no_longer_require_an_image_for_op'])) {
			if (!$post['has_file'] && Vi::$config['force_image_op'])
				error(Vi::$config['error']['noimage']);
		}

		if (!($post['has_file'] || isset($post['embed'])) || (($post['op'] && Vi::$config['force_body_op']) || (!$post['op'] && Vi::$config['force_body']))) {
			// http://stackoverflow.com/a/4167053
			$stripped_whitespace = preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $post['body']);
			if ($stripped_whitespace == '') {
				error(Vi::$config['error']['tooshort_body']);
			}
		}

		// Check for too many files
		if (sizeof($post['files']) > Vi::$config['max_images'])
			error(Vi::$config['error']['toomanyimages']);
	}

	if (Vi::$config['strip_combining_chars']) {
		$post['name'] = strip_combining_chars($post['name']);
		$post['email'] = strip_combining_chars($post['email']);
		$post['subject'] = strip_combining_chars($post['subject']);
		$post['body'] = strip_combining_chars($post['body']);
	}

	if (!$dropped_post) {
		// Check string lengths
		if (mb_strlen($post['name']) > 35)
			error(sprintf(Vi::$config['error']['toolong'], 'name'));
		if (mb_strlen($post['email']) > 40)
			error(sprintf(Vi::$config['error']['toolong'], 'email'));
		if (mb_strlen($post['subject']) > 100)
			error(sprintf(Vi::$config['error']['toolong'], 'subject'));
		if (!Vi::$mod && mb_strlen($post['body']) > Vi::$config['max_body'])
			error(Vi::$config['error']['toolong_body']);
		if (mb_strlen($post['body']) < Vi::$config['min_body'] && $post['op'])
			error(sprintf(_('OP must be at least %d chars on this board.'), Vi::$config['min_body']));
		if (mb_strlen($post['password']) > 20)
			error(sprintf(Vi::$config['error']['toolong'], 'password'));
	}

	wordfilters($post['body']);

	if (Vi::$config['max_newlines'] > 0) {
		preg_match_all("/\n/", $post['body'], $nlmatches);

		if (isset($nlmatches[0]) && sizeof($nlmatches[0]) > Vi::$config['max_newlines'])
			error(sprintf(_('Your post contains too many lines. This board only allows %d maximum.'), Vi::$config['max_newlines']));
	}


	$post['body'] = escape_markup_modifiers($post['body']);

	if (Vi::$mod && isset($post['raw']) && $post['raw']) {
		$post['body'] .= "\n<tinyboard raw html>1</tinyboard>";
	}

	if (!$dropped_post)
	if ((Vi::$config['country_flags'] && (!Vi::$config['allow_no_country'] || Vi::$config['force_flag'])) || (Vi::$config['country_flags'] && Vi::$config['allow_no_country'] && !isset($_POST['no_country']))) {
		require_once 'inc/lib/geoip/geoip.inc';
		require_once 'inc/lib/IP/Lifo/IP/IP.php';
		$gi=geoip\geoip_open('inc/lib/geoip/GeoIPv6.dat', GEOIP_STANDARD);

		$to_ipv6 = Lifo\IP\IP::isIPv4($_SERVER['REMOTE_ADDR']) ? Lifo\IP\IP::to_ipv6($_SERVER['REMOTE_ADDR'], true) : $_SERVER['REMOTE_ADDR'];
		$country_code = geoip\geoip_country_code_by_addr_v6($gi, $to_ipv6);
		$country_name = geoip\geoip_country_name_by_addr_v6($gi, $to_ipv6);
		geoip\geoip_close($gi);
		if (!$country_code) $country_code = 'A1';
		if (!$country_name) $country_name = 'Unknown';

		$post['body'] .= "\n<tinyboard flag>".strtolower($country_code)."</tinyboard>".
		"\n<tinyboard flag alt>$country_name</tinyboard>";
	}

	if (Vi::$config['user_flag'] && isset($_POST['user_flag'])) {
		if (!empty($_POST['user_flag']) ){
			$user_flag = $_POST['user_flag'];

			if (!isset(Vi::$config['user_flags'][$user_flag]))
				error(_('Invalid flag selection!'));

			$flag_alt = isset($user_flag_alt) ? $user_flag_alt : Vi::$config['user_flags'][$user_flag];

			$post['body'] .= "\n<tinyboard flag>" . strtolower($user_flag) . "</tinyboard>" .
			"\n<tinyboard flag alt>" . $flag_alt . "</tinyboard>";
		} else if (Vi::$config['force_flag']) {
			error(_('You must choose a flag to post on this board!'));
		}
	}

	if (Vi::$config['allowed_tags'] && $post['op'] && isset($_POST['tag']) && $_POST['tag'] && isset(Vi::$config['allowed_tags'][$_POST['tag']])) {
		$post['body'] .= "\n<tinyboard tag>" . $_POST['tag'] . "</tinyboard>";
	}

	// TODO: backport this part fully from vichan
	//if (!$dropped_post)
        //if (Vi::$config['proxy_save'] && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
	//	$proxy = preg_replace("/[^0-9a-fA-F.,: ]/", '', $_SERVER['HTTP_X_FORWARDED_FOR']);
	//	$post['body'] .= "\n<tinyboard proxy>".$proxy."</tinyboard>";
	//}

	if (mysql_version() >= 50503) {
		$post['body_nomarkup'] = $post['body']; // Assume we're using the utf8mb4 charset
	}
	else {
		// MySQL's `utf8` charset only supports up to 3-byte symbols
		// Remove anything >= 0x010000

		$chars = preg_split('//u', $post['body'], -1, PREG_SPLIT_NO_EMPTY);
		$post['body_nomarkup'] = '';
		foreach ($chars as $char) {
			$o = 0;
			$ord = ordutf8($char, $o);
			if ($ord >= 0x010000)
				continue;
			$post['body_nomarkup'] .= $char;
		}
	}

	$post['tracked_cites'] = markup($post['body'], true, $post['op']);

	if ($post['has_file']) {
		$md5cmd = false;
		if (Vi::$config['bsd_md5'])  $md5cmd = '/sbin/md5 -r';
		if (Vi::$config['gnu_md5'])  $md5cmd = 'md5sum';

		$allhashes = '';

		foreach ($post['files'] as $key => &$file) {
			if ($post['op'] && Vi::$config['allowed_ext_op']) {
				if (!in_array($file['extension'], Vi::$config['allowed_ext_op']))
					error(Vi::$config['error']['unknownext']);
			}
			elseif (!in_array($file['extension'], Vi::$config['allowed_ext']) && !in_array($file['extension'], Vi::$config['allowed_ext_files']))
				error(Vi::$config['error']['unknownext']);

			$file['is_an_image'] = !in_array($file['extension'], Vi::$config['allowed_ext_files']);

			// Truncate filename if it is too long
			$file['filename'] = mb_substr($file['filename'], 0, Vi::$config['max_filename_len']);

			$upload = $file['tmp_name'];

			if (!is_readable($upload))
				error(Vi::$config['error']['nomove']);

			if ($md5cmd) {
				$output = shell_exec_error($md5cmd . " < " . escapeshellarg($upload));
				$output = explode(' ', $output);
				$hash = $output[0];
				$_hash = $hash;
			}
			else {
				$hash = md5_file($upload);
				$_hash = $hash;
			}

			$type_hash = 'md5';

			if($file['is_an_image']) {
				include_once 'inc/lib/imagehash/imagehash.php';
				$hasher = new Jenssegers\ImageHash\ImageHash;
				$_hash = $hasher->hash($upload);
				$type_hash = 'imagehash';
			}

			// filter files by MD5
			$query = prepare('SELECT * FROM ``filters`` WHERE `type` = :typehash and `value` = :value');
			$query->bindValue(':typehash', $type_hash);
			$query->bindValue(':value', $_hash);
			$result = $query->execute() or error(db_error());
			if ($row = $query->fetch()) {
				//$reason = utf8tohtml($row['reason']);
				exit; // Временно
				//error(_("Sorry, cannot upload. Matched of disallowed file. Reason: ") . $reason);
			}

			$file['hash'] = $hash;
			$allhashes.= $hash;
		}

		if (count ($post['files']) == 1) {
			$post['filehash'] = $hash;
		}
		else {
			$post['filehash'] = md5($allhashes);
		}
	}

	if (!hasPermission(Vi::$config['mod']['bypass_filters'], Vi::$board['uri']) && !$dropped_post) {
		require_once 'inc/filters.php';

		do_filters($post);
	}

	if ($post['has_file']) {
		foreach ($post['files'] as $key => &$file) {
		if ($file['is_an_image']) {
			if (Vi::$config['ie_mime_type_detection'] !== false) {
				// Check IE MIME type detection XSS exploit
				$buffer = file_get_contents($upload, null, null, null, 255);
				if (preg_match(Vi::$config['ie_mime_type_detection'], $buffer)) {
					undoImage($post);
					error(Vi::$config['error']['mime_exploit']);
				}
			}

			require_once 'inc/image.php';

			// find dimensions of an image using GD
			if (!$size = @getimagesize($file['tmp_name'])) {
				error(Vi::$config['error']['invalidimg']);
			}
			if (!in_array($size[2], array(IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_BMP))) {
				error(Vi::$config['error']['invalidimg']);
			}
			if ($size[0] > Vi::$config['max_width'] || $size[1] > Vi::$config['max_height']) {
				error(Vi::$config['error']['maxsize']);
			}

			if (Vi::$config['convert_auto_orient'] && ($file['extension'] == 'jpg' || $file['extension'] == 'jpeg')) {
				// The following code corrects the image orientation.
				// Currently only works with the 'convert' option selected but it could easily be expanded to work with the rest if you can be bothered.
				if (!(Vi::$config['redraw_image'] || ((Vi::$config['strip_exif'] && !Vi::$config['use_exiftool']) && ($file['extension'] == 'jpg' || $file['extension'] == 'jpeg')))) {
					if (in_array(Vi::$config['thumb_method'], array('convert', 'convert+gifsicle', 'gm', 'gm+gifsicle'))) {
						$exif = @exif_read_data($file['tmp_name']);
						$gm = in_array(Vi::$config['thumb_method'], array('gm', 'gm+gifsicle'));
						if (isset($exif['Orientation']) && $exif['Orientation'] != 1) {
							if (Vi::$config['convert_manual_orient']) {
								$error = shell_exec_error(($gm ? 'gm ' : '') . 'convert ' .
									escapeshellarg($file['tmp_name']) . ' ' .
									ImageConvert::jpeg_exif_orientation(false, $exif) . ' ' .
									(Vi::$config['strip_exif'] ? '+profile "*"' :
										(Vi::$config['use_exiftool'] ? '' : '+profile "*"')
									) . ' ' .
									escapeshellarg($file['tmp_name']));
								if (Vi::$config['use_exiftool'] && !Vi::$config['strip_exif']) {
									if ($exiftool_error = shell_exec_error(
										'exiftool -overwrite_original -q -q -orientation=1 -n ' .
											escapeshellarg($file['tmp_name'])))
										error(_('exiftool failed!'), null, $exiftool_error);
								} else {
									// TODO: Find another way to remove the Orientation tag from the EXIF profile
									// without needing `exiftool`.
								}
							} else {
								$error = shell_exec_error(($gm ? 'gm ' : '') . 'convert ' .
										escapeshellarg($file['tmp_name']) . ' -auto-orient ' . escapeshellarg($upload));
							}
							if ($error)
								error(_('Could not auto-orient image!'), null, $error);
							$size = @getimagesize($file['tmp_name']);
							if (Vi::$config['strip_exif'])
								$file['exif_stripped'] = true;
						}
					}
				}
			}

			// create image object
			$image = new Image($file['tmp_name'], $file['extension'], $size);
			if ($image->size->width > Vi::$config['max_width'] || $image->size->height > Vi::$config['max_height']) {
				$image->delete();
				error(Vi::$config['error']['maxsize']);
			}

			$file['width'] = $image->size->width;
			$file['height'] = $image->size->height;

			if (Vi::$config['spoiler_images'] && isset($_POST['spoiler'])) {
				$file['thumb'] = 'spoiler';

				$size = @getimagesize(Vi::$config['spoiler_image']);
				$file['thumbwidth'] = $size[0];
				$file['thumbheight'] = $size[1];
			} elseif (Vi::$config['minimum_copy_resize'] &&
				$image->size->width <= Vi::$config['thumb_width'] &&
				$image->size->height <= Vi::$config['thumb_height'] &&
				$file['extension'] == (Vi::$config['thumb_ext'] ? Vi::$config['thumb_ext'] : $file['extension'])) {

				// Copy, because there's nothing to resize
				copy($file['tmp_name'], $file['thumb']);

				$file['thumbwidth'] = $image->size->width;
				$file['thumbheight'] = $image->size->height;
			} else {
				$ext = (Vi::$config['thumb_ext'] ? Vi::$config['thumb_ext'] : $file['extension']);
				
				if($file['extension'] == 'png') {
					$ext = $file['extension'];
				}
				else if($file['extension'] == 'gif' && Vi::$config['gif_preview_animate']) {
					$ext = $file['extension'];
				}
				$thumb = $image->resize(
					$ext,
					$post['op'] ? Vi::$config['thumb_op_width'] : Vi::$config['thumb_width'],
					$post['op'] ? Vi::$config['thumb_op_height'] : Vi::$config['thumb_height']
				);

				$thumb->to($file['thumb']);

				$file['thumbwidth'] = $thumb->width;
				$file['thumbheight'] = $thumb->height;

				$thumb->_destroy();
			}

			if (Vi::$config['redraw_image'] || ((!@$file['exif_stripped'] && Vi::$config['strip_exif'] || Vi::$config['jpeg_force_progressive']) && ($file['extension'] == 'jpg' || $file['extension'] == 'jpeg'))) {
				if (!Vi::$config['redraw_image'] && Vi::$config['use_exiftool']) {
					if($error = shell_exec_error('exiftool -overwrite_original -ignoreMinorErrors -q -q -all= ' .
						escapeshellarg($file['tmp_name'])))
						error(_('Could not strip EXIF metadata!'), null, $error);
				} else {
					$image->to($file['file']);
					$dont_copy_file = true;
				}
			}
			$image->destroy();
		} else {
			// not an image
			//copy(Vi::$config['file_thumb'], $post['thumb']);
			$file['thumb'] = 'file';

			$size = @getimagesize(sprintf(Vi::$config['file_thumb'],
				isset(Vi::$config['file_icons'][$file['extension']]) ?
					Vi::$config['file_icons'][$file['extension']] : Vi::$config['file_icons']['default']));
			$file['thumbwidth'] = $size[0] ?: Vi::$config[$post['op'] ? 'thumb_op_width' : 'thumb_width'];
			$file['thumbheight'] = $size[1] ?: '-1';
		}

		if (Vi::$config['tesseract_ocr'] && $file['thumb'] != 'file') { // Let's OCR it!
			$fname = $file['tmp_name'];

			if ($file['height'] > 500 || $file['width'] > 500) {
				$fname = $file['thumb'];
			}

			if ($fname == 'spoiler') { // We don't have that much CPU time, do we?
			}
			else {
				$tmpname = "tmp/tesseract/".mt_rand(0,10000000);

				// Preprocess command is an ImageMagick b/w quantization
				$error = shell_exec_error(sprintf(Vi::$config['tesseract_preprocess_command'], escapeshellarg($fname)) . " | " .
                                                          'tesseract stdin '.escapeshellarg($tmpname).' '.Vi::$config['tesseract_params']);
				$tmpname .= ".txt";

				$value = @file_get_contents($tmpname);
				@unlink($tmpname);

				if ($value && trim($value)) {
					// This one has an effect, that the body is appended to a post body. So you can write a correct
					// spamfilter.
					$post['body_nomarkup'] .= "<tinyboard ocr image $key>".htmlspecialchars($value)."</tinyboard>";
				}
			}
		}

		if (!isset($dont_copy_file) || !$dont_copy_file) {
			if (isset($file['file_tmp'])) {
				if (!@rename($file['tmp_name'], $file['file']))
					error(Vi::$config['error']['nomove']);
				chmod($file['file'], 0644);
			} elseif (!@move_uploaded_file($file['tmp_name'], $file['file']))
				error(Vi::$config['error']['nomove']);
			}
		}

		if (Vi::$config['image_reject_repost']) {
			if ($p = getPostByHash($post['filehash'])) {
				undoImage($post);
				error(sprintf(Vi::$config['error']['fileexists'],
					($post['mod'] ? Vi::$config['root'] . Vi::$config['file_mod'] . '?/' : Vi::$config['root']) .
					(Vi::$board['dir'] . Vi::$config['dir']['res'] . ($p['thread'] ? $p['thread'] . '.html#' . $p['id'] : $p['id'] . '.html'))
				));
			}
		} else if (!$post['op'] && Vi::$config['image_reject_repost_in_thread']) {
			if ($p = getPostByHashInThread($post['filehash'], $post['thread'])) {
				undoImage($post);
				error(sprintf(Vi::$config['error']['fileexistsinthread'],
					($post['mod'] ? Vi::$config['root'] . Vi::$config['file_mod'] . '?/' : Vi::$config['root']) .
					(Vi::$board['dir'] . Vi::$config['dir']['res'] . ($p['thread'] ? $p['thread'] . '.html#' . $p['id'] : $p['id'] . '.html'))
				));
			}
		}
	}

	// Do filters again if OCRing
	if (Vi::$config['tesseract_ocr'] && !hasPermission(Vi::$config['mod']['bypass_filters'], Vi::$board['uri']) && !$dropped_post) {
		do_filters($post);
	}

	if (!hasPermission(Vi::$config['mod']['postunoriginal'], Vi::$board['uri']) && Vi::$config['robot_enable'] && checkRobot($post['body_nomarkup']) && !$dropped_post) {
		undoImage($post);
		if (Vi::$config['robot_mute']) {
			error(sprintf(Vi::$config['error']['muted'], mute()));
		} else {
			error(Vi::$config['error']['unoriginal']);
		}
	}

	// Remove board directories before inserting them into the database.
	if ($post['has_file']) {
		foreach ($post['files'] as $key => &$file) {
			$file['file_path'] = $file['file'];
			$file['thumb_path'] = $file['thumb'];
			$file['file'] = mb_substr($file['file'], mb_strlen(Vi::$config['dir']['img_root'] . Vi::$board['dir'] . Vi::$config['dir']['img']));
			if ($file['is_an_image'] && $file['thumb'] != 'spoiler')
				$file['thumb'] = mb_substr($file['thumb'], mb_strlen(Vi::$config['dir']['img_root'] . Vi::$board['dir'] . Vi::$config['dir']['thumb']));
		}
	}

	$post = (object)$post;
	$post->files = array_map(function($a) { return (object)$a; }, $post->files);
	$error = event('post', $post);
	$post->files = array_map(function($a) { return (array)$a; }, $post->files);

	if ($error) {
		undoImage((array)$post);
		error($error);
	}
	$post = (array)$post;

	if ($post['files'])
		$post['files'] = $post['files'];
	$post['num_files'] = sizeof($post['files']);

	// Commit the post to the database.
	$post['id'] = $id = post($post);


	// Update statistics for this board.
	updateStatisticsForPost( $post );


	if ($dropped_post && $dropped_post['from_nntp']) {
	        $query = prepare("INSERT INTO ``nntp_references`` (`board`, `id`, `message_id`, `message_id_digest`, `own`, `headers`) VALUES ".
	                                                         "(:board , :id , :message_id , :message_id_digest , false, :headers)");

		$query->bindValue(':board', $dropped_post['board']);
		$query->bindValue(':id', $id);
		$query->bindValue(':message_id', $dropped_post['msgid']);
		$query->bindValue(':message_id_digest', sha1($dropped_post['msgid']));
		$query->bindValue(':headers', $dropped_post['headers']);
		$query->execute() or error(db_error($query));
	}	// ^^^^^ For inbound posts  ^^^^^
	elseif (Vi::$config['nntpchan']['enabled'] && Vi::$config['nntpchan']['group']) {
		// vvvvv For outbound posts vvvvv

		require_once('inc/nntpchan/nntpchan.php');
		$msgid = gen_msgid($post['board'], $post['id']);

		list($headers, $files) = post2nntp($post, $msgid);

		$message = gen_nntp($headers, $files);

	        $query = prepare("INSERT INTO ``nntp_references`` (`board`, `id`, `message_id`, `message_id_digest`, `own`, `headers`) VALUES ".
	                                                         "(:board , :id , :message_id , :message_id_digest , true , :headers)");

		$query->bindValue(':board', $post['board']);
                $query->bindValue(':id', $post['id']);
                $query->bindValue(':message_id', $msgid);
                $query->bindValue(':message_id_digest', sha1($msgid));
                $query->bindValue(':headers', json_encode($headers));
                $query->execute() or error(db_error($query));

		// Let's broadcast it!
		nntp_publish($message, $msgid);
	}

	insertFloodPost($post);

	// Handle cyclical threads
	if (!$post['op'] && isset($thread['cycle']) && $thread['cycle']) {
		// Query is a bit weird due to "This version of MariaDB doesn't yet support 'LIMIT & IN/ALL/ANY/SOME subquery'" (MariaDB Ver 15.1 Distrib 10.0.17-MariaDB, for Linux (x86_64))
		$query = prepare(sprintf('SELECT `id` FROM ``posts_%s`` WHERE `thread` = :thread AND `id` NOT IN (SELECT `id` FROM (SELECT `id` FROM ``posts_%s`` WHERE `thread` = :thread ORDER BY `id` DESC LIMIT :limit) i)', Vi::$board['uri'], Vi::$board['uri']));
		$query->bindValue(':thread', $post['thread']);
		$query->bindValue(':limit', Vi::$config['cycle_limit'], PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		while ($dpost = $query->fetch()) {
			deletePost($dpost['id'], false, false);
		}
	}

	if (isset($post['antispam_hash'])) {
		incrementSpamHash($post['antispam_hash']);
	}

	if (isset($post['tracked_cites']) && !empty($post['tracked_cites'])) {
		$insert_rows = array();
		foreach ($post['tracked_cites'] as $cite) {
			$insert_rows[] = '(' .
				Vi::$pdo->quote(Vi::$board['uri']) . ', ' . (int)$id . ', ' .
				Vi::$pdo->quote($cite[0]) . ', ' . (int)$cite[1] . ')';
		}
		query('INSERT INTO ``cites`` VALUES ' . implode(', ', $insert_rows)) or error(db_error());
	}

	if (!$post['op'] && !isset($_POST['no-bump']) && strpos(strtolower($post['email']), 'sage') === false && !$thread['sage'] && ($thread['cycle'] || Vi::$config['reply_limit'] == 0 || $numposts['replies']+1 < Vi::$config['reply_limit'])) {
		bumpThread($post['thread']);
	}

	if (isset($_SERVER['HTTP_REFERER']) && 0) {
		// Tell Javascript that we posted successfully
		if (isset($_COOKIE[Vi::$config['cookies']['js']]))
			$js = json_decode($_COOKIE[Vi::$config['cookies']['js']]);
		else
			$js = (object) array();
		// Tell it to delete the cached post for referer
		$js->{$_SERVER['HTTP_REFERER']} = true;
		// Encode and set cookie
		setcookie(Vi::$config['cookies']['js'], json_encode($js), 0, Vi::$config['cookies']['jail'] ? Vi::$config['cookies']['path'] : '/', null, false, false);
	}

	$root = $post['mod'] ? Vi::$config['root'] . Vi::$config['file_mod'] . '?/' : Vi::$config['root'];

	if ($noko) {
		$redirect = $root . Vi::$board['dir'] . Vi::$config['dir']['res'] .
			sprintf(Vi::$config['file_page'], $post['op'] ? $id:$post['thread']) . (!$post['op'] ? '#' . $id : '');

		if (!$post['op'] && isset($_SERVER['HTTP_REFERER'])) {
			$regex = array(
				'board' => str_replace('%s', '(\w{1,8})', preg_quote(Vi::$config['board_path'], '/')),
				'page' => str_replace('%d', '(\d+)', preg_quote(Vi::$config['file_page'], '/')),
				'page50' => str_replace('%d', '(\d+)', preg_quote(Vi::$config['file_page50'], '/')),
				'res' => preg_quote(Vi::$config['dir']['res'], '/'),
			);

			if (preg_match('/\/' . $regex['board'] . $regex['res'] . $regex['page50'] . '([?&].*)?$/', $_SERVER['HTTP_REFERER'])) {
				$redirect = $root . Vi::$board['dir'] . Vi::$config['dir']['res'] .
					sprintf(Vi::$config['file_page50'], $post['op'] ? $id:$post['thread']) . (!$post['op'] ? '#' . $id : '');
			}
		}
	} else {
		$redirect = $root . Vi::$board['dir'] . Vi::$config['file_index'];

	}

	buildThread($post['op'] ? $id : $post['thread']);

	if (Vi::$config['syslog'])
		_syslog(LOG_INFO, 'New post: /' . Vi::$board['dir'] . Vi::$config['dir']['res'] .
			sprintf(Vi::$config['file_page'], $post['op'] ? $id : $post['thread']) . (!$post['op'] ? '#' . $id : ''));

	if (!$post['mod']) header('X-Associated-Content: "' . $redirect . '"');

	if (!isset($_POST['json_response'])) {
		header('Location: ' . $redirect, true, Vi::$config['redirect_http']);
	} else {
		header('Content-Type: text/json; charset=utf-8');
		echo json_encode(array(
			'redirect' => $redirect,
			'noko' => $noko,
			'id' => $id
		));
	}

	if (Vi::$config['try_smarter'] && $post['op'])
		Vi::$build_pages = range(1, Vi::$config['max_pages']);

	if ($post['op'])
		clean($id);

	event('post-after', $post);

	// We are already done, let's continue our heavy-lifting work in the background (if we run off FastCGI)
	if (function_exists('fastcgi_finish_request')) {
		@fastcgi_finish_request();
	}

	buildIndex();

	if ($post['op']) {
		rebuildThemes('post-thread', Vi::$board['uri']);
	}
	else {
		rebuildThemes('post', Vi::$board['uri']);
	}
}
elseif (isset($_POST['appeal'])) {
	if (!isset($_POST['ban_id']))
		error(Vi::$config['error']['bot']);

	$ban_id = (int)$_POST['ban_id'];

	$bans = Bans::find(GetIp());
	foreach ($bans as $_ban) {
		if ($_ban['id'] == $ban_id) {
			$ban = $_ban;
			break;
		}
	}

	if (!isset($ban)) {
		error(_("That ban doesn't exist or is not for you."));
	}

	if ($ban['expires'] && $ban['expires'] - $ban['created'] <= Vi::$config['ban_appeals_min_length']) {
		error(_("You cannot appeal a ban of this length."));
	}

	$query = query("SELECT `denied` FROM ``ban_appeals`` WHERE `ban_id` = $ban_id") or error(db_error());
	$ban_appeals = $query->fetchAll(PDO::FETCH_COLUMN);

	if (count($ban_appeals) >= Vi::$config['ban_appeals_max']) {
		error(_("You cannot appeal this ban again."));
	}

	foreach ($ban_appeals as $is_denied) {
		if (!$is_denied)
			error(_("There is already a pending appeal for this ban."));
	}

	$query = prepare("INSERT INTO ``ban_appeals`` VALUES (NULL, :ban_id, :time, :message, 0)");
	$query->bindValue(':ban_id', $ban_id, PDO::PARAM_INT);
	$query->bindValue(':time', time(), PDO::PARAM_INT);
	$query->bindValue(':message', $_POST['appeal']);
	$query->execute() or error(db_error($query));

	displayBan($ban);
}
else {
	if (!file_exists(Vi::$config['has_installed'])) {
		header('Location: install.php', true, Vi::$config['redirect_http']);
	} else {
		// They opened post.php in their browser manually.
		error(Vi::$config['error']['nopost']);
	}
}
