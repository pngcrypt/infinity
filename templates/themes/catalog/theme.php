<?php
require 'info.php';

function catalog_build($action, $settings, $board) {
	// Possible values for $action:
	//	- all (rebuild everything, initialization)
	//	- news (news has been updated)
	//	- boards (board list changed)
	//	- post (a reply has been made)
	//	- post-thread (a thread has been made)

	if ($settings['all']) {
		$boards = listBoards(TRUE);
	} else {
		$boards = explode(' ', $settings['boards']);
	}

	if ($action == 'all') {
		foreach ($boards as $board) {
			$b = new Catalog();

			$action = generation_strategy("sb_catalog", array($board));
			if ($action == 'delete') {
				file_unlink(Vi::$config['dir']['home'] . $board . '/catalog.html');
				file_unlink(Vi::$config['dir']['home'] . $board . '/index.rss');
			} elseif ($action == 'rebuild') {
				$b->build($settings, $board);
			}

			if (php_sapi_name() === "cli") {
				echo "Rebuilding $board catalog...\n";
			}

		}
	} elseif ($action == 'post-thread' || ($settings['update_on_posts'] && $action == 'post') || ($settings['update_on_posts'] && $action == 'post-delete') && (in_array($board, $boards) | $settings['all'])) {
		$b = new Catalog();

		$action = generation_strategy("sb_catalog", array($board));
		if ($action == 'delete') {
			file_unlink(Vi::$config['dir']['home'] . $board . '/catalog.html');
			file_unlink(Vi::$config['dir']['home'] . $board . '/index.rss');
		} elseif ($action == 'rebuild') {
			$b->build($settings, $board);
		}
	}
}

// Wrap functions in a class so they don't interfere with normal Tinyboard operations
class Catalog {
	public function build($settings, $board_name) {
		if (Vi::$board['uri'] != $board_name) {
			if (!openBoard($board_name)) {
				error(sprintf(_("Board %s doesn't exist"), $board_name));
			}
		}

		$recent_images = array();
		$recent_posts  = array();
		$stats         = array();

		$query = query(sprintf("SELECT *, `id` AS `thread_id`,
				(SELECT COUNT(`id`) FROM ``posts_%s`` WHERE `thread` = `thread_id`) AS `reply_count`,
				(SELECT SUM(`num_files`) FROM ``posts_%s`` WHERE `thread` = `thread_id` AND `num_files` IS NOT NULL) AS `image_count`,
				'%s' AS `board` FROM ``posts_%s`` WHERE `thread`  IS NULL ORDER BY `sticky` DESC, `bump` DESC",
			$board_name, $board_name, $board_name, $board_name, $board_name)) or error(db_error());

		while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
			$post['link']       = Vi::$config['root'] . Vi::$board['dir'] . Vi::$config['dir']['res'] . sprintf(Vi::$config['file_page'], ($post['thread'] ? $post['thread'] : $post['id']));
			$post['board_name'] = Vi::$board['name'];

			if ($post['embed'] && preg_match('/^https?:\/\/(\w+\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9\-_]{10,11})(&.+)?$/i', $post['embed'], $matches)) {
				$post['youtube'] = $matches[2];
			}

			if (isset($post['files']) && $post['files']) {
				$files = json_decode($post['files']);

				if ($files[0]) {
					if ($files[0]->file == 'deleted') {
						$post['file'] = Vi::$config['root'] . Vi::$config['image_deleted'];
					} else if ($files[0]->thumb == 'spoiler') {
						$post['file'] = Vi::$config['root'] . Vi::$config['spoiler_image'];
					} else {
						if ($files[0]->thumb == 'file') {
							$post['file'] = Vi::$config['root'] . sprintf(Vi::$config['file_thumb'], 'file.png');
						} else {
							$post['file'] = Vi::$config['uri_thumb'] . $files[0]->thumb;
						}
						$post['fullimage'] = Vi::$config['uri_img'] . $files[0]->file;
					}
				}
			} else {
				$post['file'] = Vi::$config['root'] . Vi::$config['no_file_image'];
			}

			if (empty($post['image_count'])) {
				$post['image_count'] = 0;
			}

			$post['pubdate'] = date('r', $post['time']);
			$recent_posts[]  = $post;
		}

		$required_scripts = array('js/jquery.min.js', 'js/jquery.mixitup.min.js', 'js/catalog.js');

		foreach ($required_scripts as $i => $s) {
			if (!in_array($s, Vi::$config['additional_javascript'])) {
				Vi::$config['additional_javascript'][] = $s;
			}

		}

		file_write(Vi::$config['dir']['home'] . $board_name . '/catalog.html', Element('themes/catalog/catalog.html', Array(
			'settings'      => $settings,
			'config'        => Vi::$config,
			'boardlist'     => createBoardlist(),
			'recent_images' => $recent_images,
			'recent_posts'  => $recent_posts,
			'stats'         => $stats,
			'board'         => $board_name,
			'link'          => Vi::$config['root'] . Vi::$board['dir'],
		)));

		file_write(Vi::$config['dir']['home'] . $board_name . '/index.rss', Element('themes/catalog/index.rss', Array(
			'config'       => Vi::$config,
			'recent_posts' => $recent_posts,
			'board'        => Vi::$board,
		)));
	}
};
