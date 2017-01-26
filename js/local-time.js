/*
 * local-time.js
 * https://github.com/savetheinternet/Tinyboard/blob/master/js/local-time.js
 *
 * Released under the MIT license
 * Copyright (c) 2012 Michael Save <savetheinternet@tinyboard.org>
 * Copyright (c) 2013-2014 Marcin Łabanowski <marcin@6irc.net>
 *
 * Usage:
 *   // Vi::$config['additional_javascript'][] = 'js/jquery.min.js';
 *   Vi::$config['additional_javascript'][] = 'js/local-time.js';
 *
 */

/* global _, Vi, Options */
(function(){
	'use strict';

	$(function() {
		if (window.Options && Options.get_tab('general')) {
			Options.extend_tab('general', '<label id="show-relative-time"><input type="checkbox">' + _('Show relative time') + '</label>');

			$('#show-relative-time>input').on('change', function() {
				localStorage.show_relative_time = localStorage.show_relative_time === 'true' ? 'false' : 'true';
				// no need to refresh page
				do_localtime(document.body);
			});

			// allow to work with auto-reload.js, etc.
			$(document).on('new_post', function(e, post) {
				do_localtime(post);
			});
		}

		do_localtime(document.body);
	});

	return;

	function iso8601(s) {
		s = s.replace(/\.\d\d\d+/,""); // remove milliseconds
		s = s.replace(/-/,"/").replace(/-/,"/");
		s = s.replace(/T/," ").replace(/Z/," UTC");
		s = s.replace(/([\+\-]\d\d)\:?(\d\d)/," $1$2"); // -04:00 -> -0400
		return new Date(s);
	}

	function do_localtime(elem) {	
		$('time[datetime]', elem).each(function(){
			var $t = $(this),
				dt = $t.attr('datetime'),
				postTime = new Date(dt),
				fmt = $t.data('format');

			$(this).data('local', true);


			if (localStorage.show_relative_time === 'true') {
				$(this)
					.html(Vi.time.ago(postTime.getTime(), true, true))
					.attr('title', Vi.time.dateformat(iso8601(dt), fmt));
			} else {
				$(this)
					.html(Vi.time.dateformat(iso8601(dt), fmt))
					.off('mouseenter')
					.on('mouseenter', update_title);
			}
		});
	}

	function update_title() {
		// update ago-time in title on mouse enter
		var d = new Date($(this).attr('datetime'));
		$(this).attr('title', Vi.time.ago(d.getTime(), true, true));
	}

})();
