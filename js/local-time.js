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
				do_localtime(document);
			});

			// allow to work with auto-reload.js, etc.
			$(document).on('new_post', function(e, post) {
				do_localtime(post);
			});
		}

		do_localtime(document);
	});

	return;

	function iso8601(s) {
		s = s.replace(/\.\d\d\d+/,""); // remove milliseconds
		s = s.replace(/-/,"/").replace(/-/,"/");
		s = s.replace(/T/," ").replace(/Z/," UTC");
		s = s.replace(/([\+\-]\d\d)\:?(\d\d)/," $1$2"); // -04:00 -> -0400
		return new Date(s);
	}

	function timeDifference(current, previous) {

		var msPerMinute = 60 * 1000;
		var msPerHour = msPerMinute * 60;
		var msPerDay = msPerHour * 24;
		var msPerMonth = msPerDay * 30;
		var msPerYear = msPerDay * 365;

		var elapsed = current - previous;

		if (elapsed < msPerMinute) {
			return _('Just now');
		} else if (elapsed < msPerHour) {
			return Math.round(elapsed/msPerMinute) + (Math.round(elapsed/msPerMinute)<=1 ? _(' minute ago'):_(' minutes ago'));
		} else if (elapsed < msPerDay ) {
			return Math.round(elapsed/msPerHour ) + (Math.round(elapsed/msPerHour)<=1 ? _(' hour ago'):_(' hours ago'));
		} else if (elapsed < msPerMonth) {
			return Math.round(elapsed/msPerDay) + (Math.round(elapsed/msPerDay)<=1 ? _(' day ago'):_(' days ago'));
		} else if (elapsed < msPerYear) {
			return Math.round(elapsed/msPerMonth) + (Math.round(elapsed/msPerMonth)<=1 ? _(' month ago'):_(' months ago'));
		} else {
			return Math.round(elapsed/msPerYear ) + (Math.round(elapsed/msPerYear)<=1 ? _(' year ago'):_(' years ago'));
		}
	}

	function do_localtime(elem) {	
		var currentTime = Date.now();
		$('time[datetime]', elem).each(function(){
			var $t = $(this),
				dt = $t.attr('datetime'),
				postTime = new Date(dt),
				fmt = $t.data('format');

			$(this).data('local', true);

			if (localStorage.show_relative_time === 'true') {
				$(this)
					.html(timeDifference(currentTime, postTime.getTime()))
					.attr('title', Vi.time.dateformat(iso8601(dt), fmt));
			} else {
				$(this)
					.html(Vi.time.dateformat(iso8601(dt), fmt))
					.attr('title', timeDifference(currentTime, postTime.getTime()));
			}
		});
	}
})();
