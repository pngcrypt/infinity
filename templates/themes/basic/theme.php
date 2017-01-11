<?php
require 'info.php';

function basic_build($action, $settings, $board) {
	// Possible values for $action:
	//	- all (rebuild everything, initialization)
	//	- news (news has been updated)
	//	- boards (board list changed)

	Basic::build($action, $settings);
}

// Wrap functions in a class so they don't interfere with normal Tinyboard operations
class Basic {
	public static function build($action, $settings) {
		if ($action == 'all' || $action == 'news') {
			file_write(Vi::$config['dir']['home'] . $settings['file'], Basic::homepage($settings));
		}

	}

	// Build news page
	public static function homepage($settings) {
		$settings['no_recent'] = (int) $settings['no_recent'];

		$query = query("SELECT * FROM ``news`` ORDER BY `time` DESC" . ($settings['no_recent'] ? ' LIMIT ' . $settings['no_recent'] : '')) or error(db_error());
		$news  = $query->fetchAll(PDO::FETCH_ASSOC);

		$boards = array();

		if (isset(Vi::$config['categories'])) {
			foreach (Vi::$config['categories'] as $name => $category) {
				foreach ($category as $board) {
					$query           = query("SELECT uri, title, subtitle, sfw FROM ``boards`` WHERE uri='" . $board . "'") or error(db_error());
					$boards[$name][] = current($query->fetchAll(PDO::FETCH_ASSOC));
				}

				sort($boards[$name]);
			}
		}

		$categories = $boards;

		return Element('themes/basic/index.html', Array(
			'settings'   => $settings,
			'config'     => Vi::$config,
			'boardlist'  => createBoardlist(),
			'news'       => $news,
			'categories' => $categories,
		));
	}
};
