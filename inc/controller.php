<?php

// This file contains the controller part of vichan

// don't bother with that unless you use smart build or advanced build
// you can use those parts for your own implementations though :^)

defined('TINYBOARD') or exit;

function sb_board($b, $page = 1) {
	$page = (int) $page;
	if ($page < 1) {
		return false;
	}

	if (!openBoard($b)) {
		return false;
	}

	if ($page > Vi::$config['max_pages']) {
		return false;
	}

	Vi::$config['try_smarter'] = true;
	Vi::$build_pages           = array($page);
	buildIndex("skip");
	return true;
}

function sb_api_board($b, $page = 0) {
	$page = (int) $page;
	return sb_board($b, $page + 1);
}

function sb_thread($b, $thread, $slugcheck = false) {
	$thread = (int) $thread;
	if ($thread < 1) {
		return false;
	}

	if (!preg_match('/^' . Vi::$config['board_regex'] . '$/u', $b)) {
		return false;
	}

	if (Cache::get("thread_exists_" . $b . "_" . $thread) == "no") {
		return false;
	}

	$query = prepare(sprintf("SELECT MAX(`id`) AS `max` FROM ``posts_%s``", $b));
	if (!$query->execute()) {
		return false;
	}

	$s   = $query->fetch(PDO::FETCH_ASSOC);
	$max = $s['max'];

	if ($thread > $max) {
		return false;
	}

	$query = prepare(sprintf("SELECT `id` FROM ``posts_%s`` WHERE `id` = :id AND `thread` IS NULL", $b));
	$query->bindValue(':id', $thread);

	if (!$query->execute() || !$query->fetch(PDO::FETCH_ASSOC)) {
		Cache::set("thread_exists_" . $b . "_" . $thread, "no", 3600);
		return false;
	}

	if ($slugcheck == 50) {
		// Should we really generate +50 page? Maybe there are not enough posts anyway
		global $request;
		$r = str_replace("+50", "", $request);
		$r = substr($r, 1); // Cut the slash

		if (file_exists($r)) {
			return false;
		}

	}

	if (!openBoard($b)) {
		return false;
	}

	buildThread($thread);
	return true;
}

function sb_thread_slugcheck50($b, $thread) {
	return sb_thread($b, $thread, 50);
}

function sb_api($b) {
	if (!openBoard($b)) {
		return false;
	}

	Vi::$config['try_smarter'] = true;
	Vi::$build_pages           = array(-1);
	buildIndex();
	return true;
}

function sb_ukko() {
	rebuildTheme("ukko", "post-thread");
	return true;
}

function sb_catalog($b) {
	if (!openBoard($b)) {
		return false;
	}

	rebuildTheme("catalog", "post-thread", $b);
	return true;
}

function sb_recent() {
	rebuildTheme("recent", "post-thread");
	return true;
}

function sb_sitemap() {
	rebuildTheme("sitemap", "all");
	return true;
}
