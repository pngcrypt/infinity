/*
 * favorites.js - Allow user to favorite boards and put them in the bar
 *
 * Usage:
 *   Vi::$config['additional_javascript'][] = Vi::$config['jquery_js'];
 *   $config['additional_javascript'][] = 'js/favorites.js';
 *
 * XX: favorites.js may conflict with watch.js and compact-boardlist.js // todo: check
 */

 /* global Vi, active_page, board_name */
Vi.favorites = function() {
	'use strict';
	var favorites = {
		items: [],
		load: fav_load,
		save: fav_save,
	}, $boardlist, $fav_star;

	fav_load();
	fav_save();
	$(fav_init); // on doc-ready

	return favorites;

	function fav_load() {
		try {
			favorites.items = JSON.parse(localStorage.getItem('favorites'));
		}
		catch(e) {}
		if(!Vi.isArray(favorites.items)) {
			if(Vi.config.favorites_def && Vi.isArray(Vi.config.favorites_def))
				favorites.items = Vi.config.favorites_def; // get defaults if localstorage is empty
			else
				favorites.items = [];
		}
		favorites.items = favorites.items.map(function(v) {
			return String(v).toLowerCase();
		});
	}

	function fav_save() {
		if(!Vi.isArray(favorites.items))
			favorites.items = [];
		else {
			favorites.items = favorites.items.map(function(v) {
				return String(v).toLowerCase();
			});
		}
		localStorage.setItem('favorites', JSON.stringify(favorites.items));
	}

	function fav_init() {
		$boardlist = $('.boardlist');
		if($boardlist.length)
			$boardlist = $('<span class="favorite-boards"/>').appendTo($boardlist);
		else
			$boardlist = null;

		if(['thread', 'index', 'catalog', 'ukko'].indexOf(active_page) >= 0) {
			// add star to board name
			$('header>h1').append(' <a id="favorite-star" href="#" style="text-decoration:none">\u2605</a>');
			$fav_star = $('#favorite-star');
			$fav_star.on('click', function(e) {
				e.preventDefault();
				fav_load();
				var is_fav = favorites.items.indexOf(board_name);
				if(is_fav < 0)
					favorites.items.push(board_name);
				else
					favorites.items.splice(is_fav, 1);
				fav_save();
				fav_update();
			});
		}

		$(document).on('favorites_change', fav_update); // event for redraw (from other scripts)
		fav_update();
	}

	function fav_update() {
		// redraw favorites
		fav_load();
		if($fav_star) {
			$fav_star.css('color', favorites.items.indexOf(board_name) < 0 ? 'grey' : 'yellow');
		}
		if($boardlist) {
			var links = [];
			favorites.items.forEach(function(f) {
				links.push('<a href="/' + f + (active_page === 'catalog' ? '/catalog.html' : '') + '">' + f + '</a>');
			});
			$boardlist.html(links.length ? ' [ ' + links.join(' / ') + ' ] ' : '');
		}
	}
}();
