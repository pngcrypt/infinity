<?php
require 'info.php';

function recentposts_build($action, $settings, $board) {
	// Possible values for $action:
	//	- all (rebuild everything, initialization)
	//	- news (news has been updated)
	//	- boards (board list changed)
	//	- post (a post has been made)
	//	- post-thread (a thread has been made)

	$b = new RecentPosts();
	$b->build($action, $settings);
}

// Wrap functions in a class so they don't interfere with normal Tinyboard operations
class RecentPosts {
	public function build($action, $settings) {
		if ($action == 'all') {
			copy('templates/themes/recent/' . $settings['basecss'], Vi::$config['dir']['home'] . $settings['css']);
		}

		$this->excluded = explode(' ', $settings['exclude']);

		if ($action == 'all' || $action == 'post' || $action == 'post-thread' || $action == 'post-delete') {
			$action = generation_strategy('sb_recent', array());
			if ($action == 'delete') {
				file_unlink(Vi::$config['dir']['home'] . $settings['html']);
			} elseif ($action == 'rebuild') {
				file_write(Vi::$config['dir']['home'] . $settings['html'], $this->homepage($settings));
			}
		}
	}

	// Build news page
	public function homepage($settings) {
		$recent_images = Array();
		$recent_posts  = Array();
		$stats         = Array();

		$boards = listBoards();

		$query = '';
		foreach ($boards as &$_board) {
			if (in_array($_board['uri'], $this->excluded)) {
				continue;
			}

			$query .= sprintf("SELECT *, '%s' AS `board` FROM ``posts_%s`` WHERE `files` IS NOT NULL UNION ALL ", $_board['uri'], $_board['uri']);
		}
		$query = preg_replace('/UNION ALL $/', 'ORDER BY `time` DESC LIMIT ' . (int) $settings['limit_images'], $query);

		if ($query == '') {
			error(_("Can't build the RecentPosts theme, because there are no boards to be fetched."));
		}

		$query = query($query) or error(db_error());

		while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
			openBoard($post['board']);

			if (isset($post['files'])) {
				$files = json_decode($post['files']);
			}

			if ($files[0]->file == 'deleted') {
				continue;
			}

			// board settings won't be available in the template file, so generate links now
			$post['link'] = Vi::$config['root'] . Vi::$board['dir'] . Vi::$config['dir']['res']
			. sprintf(Vi::$config['file_page'], ($post['thread'] ? $post['thread'] : $post['id'])) . '#' . $post['id'];

			if ($files) {
				if ($files[0]->thumb == 'spoiler') {
					$tn_size             = @getimagesize(Vi::$config['spoiler_image']);
					$post['src']         = Vi::$config['spoiler_image'];
					$post['thumbwidth']  = $tn_size[0];
					$post['thumbheight'] = $tn_size[1];
				} else {
					$post['src'] = Vi::$config['uri_thumb'] . $files[0]->thumb;
				}
			}

			$recent_images[] = $post;
		}

		$query = '';
		foreach ($boards as &$_board) {
			if (in_array($_board['uri'], $this->excluded)) {
				continue;
			}

			$query .= sprintf("SELECT *, '%s' AS `board` FROM ``posts_%s`` UNION ALL ", $_board['uri'], $_board['uri']);
		}
		$query = preg_replace('/UNION ALL $/', 'ORDER BY `time` DESC LIMIT ' . (int) $settings['limit_posts'], $query);
		$query = query($query) or error(db_error());

		while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
			openBoard($post['board']);

			$post['link'] = Vi::$config['root'] . Vi::$board['dir'] . Vi::$config['dir']['res'] . sprintf(Vi::$config['file_page'], ($post['thread'] ? $post['thread'] : $post['id'])) . '#' . $post['id'];
			if ($post['body'] != "") {
				$post['snippet'] = pm_snippet($post['body'], 30);
			} else {
				$post['snippet'] = "<em>" . _("(no comment)") . "</em>";
			}

			$post['board_name'] = Vi::$board['name'];

			$recent_posts[] = $post;
		}

		// Total posts
		$query = 'SELECT SUM(`top`) FROM (';
		foreach ($boards as &$_board) {
			if (in_array($_board['uri'], $this->excluded)) {
				continue;
			}

			$query .= sprintf("SELECT MAX(`id`) AS `top` FROM ``posts_%s`` UNION ALL ", $_board['uri']);
		}
		$query                = preg_replace('/UNION ALL $/', ') AS `posts_all`', $query);
		$query                = query($query) or error(db_error());
		$stats['total_posts'] = number_format($query->fetchColumn());

		// Unique IPs
		$query = 'SELECT COUNT(DISTINCT(`ip`)) FROM (';
		foreach ($boards as &$_board) {
			if (in_array($_board['uri'], $this->excluded)) {
				continue;
			}

			$query .= sprintf("SELECT `ip` FROM ``posts_%s`` UNION ALL ", $_board['uri']);
		}
		$query                   = preg_replace('/UNION ALL $/', ') AS `posts_all`', $query);
		$query                   = query($query) or error(db_error());
		$stats['unique_posters'] = number_format($query->fetchColumn());

		// Active content
		/*$query = 'SELECT SUM(`filesize`) FROM (';
		foreach ($boards as &$_board) {
		if (in_array($_board['uri'], $this->excluded))
		continue;
		$query .= sprintf("SELECT `filesize` FROM ``posts_%s`` UNION ALL ", $_board['uri']);
		}
		$query = preg_replace('/UNION ALL $/', ') AS `posts_all`', $query);
		$query = query($query) or error(db_error());
		$stats['active_content'] = $query->fetchColumn();*/

		return Element('themes/recent/recent.html', Array(
			'settings'      => $settings,
			'config'        => Vi::$config,
			'boardlist'     => createBoardlist(),
			'recent_images' => $recent_images,
			'recent_posts'  => $recent_posts,
			'stats'         => $stats,
		));
	}
};

?>
