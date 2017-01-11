<?php

// vichan's routing mechanism

// don't bother with that unless you use smart build or advanced build
// you can use those parts for your own implementations though :^)

defined('TINYBOARD') or exit;

function route($path) {
	$entrypoints = array();

	$entrypoints['/%b/']                             = 'sb_board';
	$entrypoints['/%b/' . Vi::$config['file_index']] = 'sb_board';
	$entrypoints['/%b/' . Vi::$config['file_page']]  = 'sb_board';
	$entrypoints['/%b/%d.json']                      = 'sb_api_board';

	if (Vi::$config['api']['enabled']) {
		$entrypoints['/%b/threads.json'] = 'sb_api';
		$entrypoints['/%b/catalog.json'] = 'sb_api';
	}

	$entrypoints['/%b/' . Vi::$config['dir']['res'] . Vi::$config['file_page']]   = 'sb_thread';
	$entrypoints['/%b/' . Vi::$config['dir']['res'] . Vi::$config['file_page50']] = 'sb_thread_slugcheck50';
	if (Vi::$config['api']['enabled']) {
		$entrypoints['/%b/' . Vi::$config['dir']['res'] . '%d.json'] = 'sb_thread';
	}

	$entrypoints['/*/']              = 'sb_ukko';
	$entrypoints['/*/index.html']    = 'sb_ukko';
	$entrypoints['/recent.html']     = 'sb_recent';
	$entrypoints['/%b/catalog.html'] = 'sb_catalog';
	$entrypoints['/%b/index.rss']    = 'sb_catalog';
	$entrypoints['/sitemap.xml']     = 'sb_sitemap';

	$entrypoints = array_merge($entrypoints, Vi::$config['controller_entrypoints']);

	$reached = false;

	list($request) = explode('?', $path);

	foreach ($entrypoints as $id => $fun) {
		$id = '@^' . preg_quote($id, '@') . '$@u';

		$id = str_replace('%b', '(' . Vi::$config['board_regex'] . ')', $id);
		$id = str_replace('%d', '([0-9]+)', $id);
		$id = str_replace('%s', '[a-zA-Z0-9-]+', $id);

		$matches = null;

		if (preg_match($id, $request, $matches)) {
			array_shift($matches);

			$reached = array($fun, $matches);

			break;
		}
	}

	return $reached;
}
