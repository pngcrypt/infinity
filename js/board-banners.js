/* global $, configRoot */

$(function(){
'use strict';
	var $banner = $('div.board_banners').empty(),
		url = $banner.data('url'),
		timeout = $banner.data('timeout')|0,
		$img;

	if(!$banner.length || !url)
		return;

	if(!timeout)
		timeout = 60;

	(function banner_update() {
		$.ajax({
			url: url,
			cache: false,
			contentType: false,
			processData: false,
			dataType: 'json'
		})
		.success(function (data) {
			if(typeof data != 'object' || !data.board || !data.image)
				return;
			if(!$img) {
				$img = $('<img class="board_image"/>').appendTo($banner);
				$banner = $img.wrap('<a href="#"></a>').parent();
			}
			$banner.attr('href', configRoot+data.board);
			$img.attr('src', configRoot+data.image);
		})
		.always(function() {
			setTimeout(banner_update, timeout * 1000);
		});		
	})();
});
