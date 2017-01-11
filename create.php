<?php

include "inc/functions.php";

checkBan('*');

if (!Vi::$config['allow_create_userboards']) {
	error(_('Create user boards is not allowed.'));
}
else if (Tor_Session::check()) {
	error(_('Create user boards is not allowed for you.'));
}

if (!isset($_POST['uri'], $_POST['title'], $_POST['subtitle'], $_POST['username'], $_POST['password'])) {
	$password = strtr(base64_encode(random_bytes(9)), '+', '.');
	$body     = Element("8chan/create.html", array("config" => Vi::$config, "password" => $password));
	echo Element("page.html", array(
		"config" => Vi::$config,
		"body" => $body,
		"title" => _("Create your board"),
		"subtitle" => _("before someone else does"),
		'boardlist' => createBoardlist(),
	));
}
else {
	$uri      = $_POST['uri'];
	$title    = $_POST['title'];
	$subtitle = $_POST['subtitle'];
	$username = $_POST['username'];
	$password = $_POST['password'];
	$email    = (isset($_POST['email']) ? $_POST['email'] : '');

	if (!preg_match('/^[a-z0-9]{1,30}$/', $uri)) {
		error(_('Invalid URI'));
	}

	if (!(strlen($title) < 40)) {
		error(_('Invalid title'));
	}

	if (!(strlen($subtitle) < 200)) {
		error(_('Invalid subtitle'));
	}

	if (!preg_match('/^[a-zA-Z0-9._]{1,30}$/', $username)) {
		error(_('Invalid username'));
	}

	if (!chanCaptcha::check()) {
		error(Vi::$config['error']['captcha']);
	}

	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$email = '';
	}

	foreach (listBoards() as $i => $board) {
		if ($board['uri'] == $uri) {
			error(_('Board already exists!'));
		}
	}

	foreach (Vi::$config['banned_boards'] as $i => $w) {
		if ($w[0] !== '/') {
			if (strpos($uri, $w) !== false) {
				error(_("Cannot create board with banned word $w"));
			}

		} else {
			if (preg_match($w, $uri)) {
				error(_("Cannot create board matching banned pattern $w"));
			}

		}
	}
	$query = prepare('SELECT ``username`` FROM ``mods`` WHERE ``username`` = :username');
	$query->bindValue(':username', $username);
	$query->execute() or error(db_error($query));
	$users = $query->fetchAll(PDO::FETCH_ASSOC);

	if (sizeof($users) > 0) {
		error(_('The username you\'ve tried to enter already exists!'));
	}

	list($version, $password) = crypt_password($password);

	$query = prepare('INSERT INTO ``mods`` VALUES (NULL, :username, :password, :version, :type, :boards, :email)');
	$query->bindValue(':username', $username);
	$query->bindValue(':password', $password);
	$query->bindValue(':version', $version);
	$query->bindValue(':type', 20);
	$query->bindValue(':boards', $uri);
	$query->bindValue(':email', $email);
	$query->execute() or error(db_error($query));

	$query = prepare('INSERT INTO ``boards`` (`uri`, `title`, `subtitle`) VALUES (:uri, :title, :subtitle)');
	$query->bindValue(':uri', $_POST['uri']);
	$query->bindValue(':title', $_POST['title']);
	$query->bindValue(':subtitle', $_POST['subtitle']);
	$query->execute() or error(db_error($query));

	$query = Element('posts.sql', array('board' => $uri));
	query($query) or error(db_error());

	if (!openBoard($_POST['uri'])) {
		error(_("Couldn't open board after creation."));
	}

	if (Vi::$config['cache']['enabled']) {
		cache::delete('all_boards');
	}

	// Build the board
	buildIndex();

	rebuildThemes('boards');

	$query = prepare("INSERT INTO ``board_create``(uri) VALUES(:uri)");
	$query->bindValue(':uri', $uri);
	$query->execute() or error(db_error());

	_syslog(LOG_NOTICE, "New board: $uri");

	$body = Element("8chan/create_success.html", array("config" => Vi::$config, "password" => $_POST['password'], "uri" => $uri));

	echo Element("page.html", array(
		"config" => Vi::$config,
		"body" => $body,
		"title" => _("Success"),
		"subtitle" => _("This was a triumph"),
		'boardlist' => createBoardlist(),
	));
}
