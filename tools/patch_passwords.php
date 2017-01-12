<?php
// Only run this once, and only if you need to. This will salt and hash all previously set passwords in the database
// Storing post deletion passwords in plain text like before decreases anonymity and would allow tracking of users, even if they change their ip/proxy.

include 'inc/functions.php';

if (php_sapi_name() != 'cli') {
	error(Vi::$config['error']['directly_run']);
}

$boards = listboards();

foreach ($boards as &$board) {
	$query = prepare(sprintf("SELECT `id` FROM ``posts_%s``", $board['uri']));
	$query->execute() or error(db_error($query));
	$ids = $query->fetchAll(PDO::FETCH_COLUMN);

	foreach ($ids as $id) {
		$query = prepare(sprintf("SELECT `time`,`password` FROM ``posts_%s`` WHERE `id` = :id", $board['uri']));
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		$post = $query->fetch(PDO::FETCH_ASSOC);

		$salt = $post['time'] .  $board['uri'];
		$new_pass = hash_pbkdf2("sha256", $post['password'], $salt, 10000, 20);

		$query = prepare(sprintf("UPDATE ``posts_%s`` SET ``password`` = :password WHERE `id` = :id", $board['uri']));
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->bindValue(':password', $new_pass);
		$query->execute() or error(db_error($query));
	}
}

?>
