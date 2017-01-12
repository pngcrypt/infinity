<?php
include 'inc/functions.php';

// todo: переделать капчу под cookie, капчу подключать из шаблона captcha.html
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	$body = Element("8chan/dnsbls.html", array("config" => Vi::$config));

	echo Element("page.html", array("config" => Vi::$config, "body" => $body, "title" => _("Bypass DNSBL"), "subtitle" => _("Post even if blocked")));
}
else if (chanCaptcha::check()) {
	$tor = checkDNSBL($_SERVER['REMOTE_ADDR']);
	if (!$tor) {
		$query = prepare('INSERT INTO ``dnsbl_bypass`` VALUES(:ip, NOW(), 0) ON DUPLICATE KEY UPDATE `created`=NOW(),`uses`=0');
		$query->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
		$query->execute() or error(db_error($query));
	}
	$cookie = bin2hex(openssl_random_pseudo_bytes(16));
	$query = prepare('INSERT INTO ``tor_cookies`` VALUES(:cookie, NOW(), 0)');
	$query->bindValue(':cookie', $cookie);
	$query->execute() or error(db_error($query));
	setcookie("tor", $cookie, time()+60*60*3);

	echo Element("page.html", array("config" => Vi::$config, "body" => '', "title" => _("Success"), "subtitle" => _("You may now go back and make your post.")));
}
else {
	error( sprintf(_("You failed the CAPTCHA. %s Try again %s If it's not working, ask admin for support."), '<a href="?">', "</a>") );
}
