<?php

include 'inc/functions.php';

$board_uri = NUll;
if(isset($_GET['board']) && !empty($_GET['board'])) {
	$board = basename($_GET['board']);
	Vi::$current_locale = BoardLocale($board);
	init_locale(Vi::$current_locale);
	$board_uri = $board;
}

if(!Tor_Session::check()) {
	error(_('You are not a .onion user'));
}

Tor_Session::set();

$message = NULL;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Vi::$config['tor']['allow_posting'] && !Tor_Session::$user['allow_post'] && !Vi::$config['tor']['force_disable']) {
	if (chanCaptcha::check()) {
		if(--Tor_Session::$user['capchas_left'] <= 0) {
			Tor_Session::$user['allow_post'] = TRUE;
		}
		else {
			$message = _('+1 captcha.');
		}
	}
	else {
		$message = '<span class="public_ban">' . _("Fail! Don't rush!.") . '</span>';
		if(--Tor_Session::$user['fails_left'] <= 0) {
			$message = '<span class="public_ban">' . _("Spin me right round...") . '</span>';
			Tor_Session::storage_reset();
		}
	}
	Tor_Session::storage_save();
}

$body = Element("8chan/tor_bypass.html", [
	'config' => Vi::$config,
	'user' => Tor_Session::$user,
	'message' => $message,
	'board' => ['uri'=>$board_uri],
]);

Vi::$config['site_logo'] = '/static/Tor-logo-2011-flat.svg';
echo Element('page.html', [
	'config' => Vi::$config,
	'body' => $body,
	'boardlist' => createBoardlist(),
	'title' => _("TOR entry point"),
	'subtitle' => _("Anonymous discussions")
]);
