/* globals $, _ */
$(function() {
'use strict';
	if(window.disable_captcha) return; // debug

	var $cwrap = $('#captcha'), $cf = $cwrap.find('iframe');
	if(!$cwrap.length || !$cf.length)
		return;

	var tid, $cin, $cinf, $bg,
		tout = window.captcha_timeout|0,
		sel_inp = 'input:not(:submit), textarea',
		updating = false,
		timeout = false,
		cloudflare = false;

	if(tout < 1) 
		tout = 120;
	
	$('head').append('<style>'+
		'.captcha-cf {position:fixed; width:600px; height:600px; left:50%; top:50%; margin-left:-25%; margin-top:-25%;'+
			'z-index:10000; border:1px solid; box-shadow: 2px 2px 2px rgba(0,0,0,.35);}'+
		'.captcha-wrap {cursor:pointer; width:'+$cf.width()+'px; height:'+$cf.height()+'px;'+
			'line-height:'+$cf.height()+'px; background:white; box-sizing: border-box}'+
		'.captcha-loading {background-repeat:no-repeat; background-position:50% 50%; background-image:url("data:image/gif;base64,R0lGODlhEAALAPQAAO/v7wBmmc3b4sPV3tzk6AVomgBmmSt+qHqrxFqZua/K1x93o0WNsYGvxl6burLL2CN5pQNnmUmPs9jh5sva4eXp6zSDq8/c4+Pn6qvH1pa7zr7S3d/l6AAAAAAAAAAAACH/C05FVFNDQVBFMi4wAwEAAAAh/hpDcmVhdGVkIHdpdGggYWpheGxvYWQuaW5mbwAh+QQJCwAAACwAAAAAEAALAAAFLSAgjmRpnqSgCuLKAq5AEIM4zDVw03ve27ifDgfkEYe04kDIDC5zrtYKRa2WQgAh+QQJCwAAACwAAAAAEAALAAAFJGBhGAVgnqhpHIeRvsDawqns0qeN5+y967tYLyicBYE7EYkYAgAh+QQJCwAAACwAAAAAEAALAAAFNiAgjothLOOIJAkiGgxjpGKiKMkbz7SN6zIawJcDwIK9W/HISxGBzdHTuBNOmcJVCyoUlk7CEAAh+QQJCwAAACwAAAAAEAALAAAFNSAgjqQIRRFUAo3jNGIkSdHqPI8Tz3V55zuaDacDyIQ+YrBH+hWPzJFzOQQaeavWi7oqnVIhACH5BAkLAAAALAAAAAAQAAsAAAUyICCOZGme1rJY5kRRk7hI0mJSVUXJtF3iOl7tltsBZsNfUegjAY3I5sgFY55KqdX1GgIAIfkECQsAAAAsAAAAABAACwAABTcgII5kaZ4kcV2EqLJipmnZhWGXaOOitm2aXQ4g7P2Ct2ER4AMul00kj5g0Al8tADY2y6C+4FIIACH5BAkLAAAALAAAAAAQAAsAAAUvICCOZGme5ERRk6iy7qpyHCVStA3gNa/7txxwlwv2isSacYUc+l4tADQGQ1mvpBAAIfkECQsAAAAsAAAAABAACwAABS8gII5kaZ7kRFGTqLLuqnIcJVK0DeA1r/u3HHCXC/aKxJpxhRz6Xi0ANAZDWa+kEAA7AAAAAAAAAAAA");}'+
		'.captcha_info {display:inline-block; height:100%; width:100%; text-align:center;}'+
	'</style>');

	$cwrap.addClass('captcha-wrap');
	$cin = $cwrap.parents('form').find('input[name="captcha_text"]');
	$cinf = $('<span class="captcha_info captcha-loading"/>').appendTo($cwrap).hide();	

	function form_click(e) {
		if(!timeout || updating || [16,17,18,19,20,144,145].indexOf(e.which) >= 0)
			return;
		if(e.target && $(e.target).data('nocaptcha'))
			return;
		captcha_update();
	}

	function captcha_update() {
		if(updating) 
			return;
		updating = true;		
		clearTimeout(tid);
		$cf.attr('src', $cf.attr('src')); // update frame
	}

	function captcha_clone() {
		// clone captcha to quick-reply
		var $qr = $('#quick-reply #captcha_qr');
		if(!$qr.length)
			return;
		$qr.addClass('captcha-wrap');
		if($cinf.is(':visible'))
			$qr.html($cinf.clone()); // clone info
		else
			$qr.html($cf.contents().find('#captcha_img img').clone()); // clone image
		$qr.off('click').on('click', captcha_update);
	}

	function captcha_timeout() {
		timeout = true;
		$cf.hide();
		$cinf.text(_('Click to update')+'...').show();
		captcha_clone();
		$cin.val('').trigger('change'); // clear captcha_text
		$cwrap.off('click').on('click', captcha_update);
	}

	function quick_reply() {
    	// on quick-reply show
    	captcha_clone();
    	$('#quick-reply form')
    		.off('click keydown', sel_inp, form_click) // remove previous handler
    		.on('click keydown', sel_inp, form_click); // update by activity in quick-form
    }

	function cloudflare_show() {
		$bg = $("<div style='background:black; opacity:0.5; position:fixed; top:0; left:0; right:0; bottom:0; width:100%; height:100%; z-index: 9999;'></div>")
			.appendTo('body');
		$bg.on('click', function() {
			$cf.removeClass('captcha-cf');
			$bg.remove();
			$cf.hide();
		});
		$cf.show();
	}


	$cf.on('load', function() {
		// on frame load
		clearTimeout(tid);
		$cinf.removeClass('captcha-loading').hide();
		$cin.val('').trigger('change'); // clear captcha_text
		timeout = false;
		updating = false;
		$($cf.get(0).contentWindow).on('unload', function(){
			// on frame unload (reload)
			$cf.hide();
			$cinf.empty().addClass('captcha-loading').show();
			captcha_clone();
		});

		if($cf.contents().find('title').text().match('CloudFlare')) {
			cloudflare = true;
			$cinf.html("<input type='checkbox'> "+_("I'm not a robot")).show();
			captcha_clone();
			$cf.contents().find('head').append('<style>'+
				'#cf-wrapper h1, #cf-wrapper h2 {font-size: 100% !important;}'+
				'.cf-wrapper {padding: 2px !important;}'+
				'.cf-column {padding: 0 !important;}'+
			'</style>');
			$cf.addClass('captcha-cf');
			$cwrap.off('click').on('click', captcha_update);
			cloudflare_show();
			return;
		}
		else if(cloudflare) {
			cloudflare = false;
			$cf.removeClass('captcha-cf');
			if($bg)
				$bg.remove();
		}
		$cf.show();
		captcha_clone();
		tid = setTimeout(captcha_timeout, tout * 1000);
	})
	.parents('form').on('click keydown', sel_inp, form_click); // update by activity in main form

    $(window).on("quick-reply", quick_reply);
	$(document).on('ajax_on_success', captcha_update); // after post sent
	quick_reply();
});
