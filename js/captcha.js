/* globals Vi, _ */

Vi.captcha = function() {
	'use strict';

	var captcha = {
		load: captcha_load,
	};

	var $cf, $cwrap, tid, $cinp, $cinf, $bg, tout, 
		// sel_inp = 'input:not(:submit), textarea',
		sel_inp = 'input[name="captcha_text"]', // selector of fields that can update the captcha
		loaded = false,
		updating = false,
		timeout = false,
		cloudflare = false,
		css = false;

    $(window).on("quick-reply", quick_reply);
	$(document).on('ajax_on_success', captcha_timeout); // after post sent

	return captcha;

	function captcha_init(el) {
		if($cf && $cf.length && $cf[0] === el)
			return;
		$cf = $(el);
		$cwrap = $cf.parents('#captcha');
		if(!$cwrap.length) {
			$cf = $cwrap = null;
			return;
		}
		if(!css) {
			css = true;
			$('head').append('<style>'+
				'.captcha-cf {position:fixed; width:600px; height:600px; left:50%; top:50%; margin-left:-25%; margin-top:-25%;'+
					'z-index:10000; border:1px solid; box-shadow: 2px 2px 2px rgba(0,0,0,.35);}'+
				'.captcha-wrap {cursor:pointer; width:'+$cf.width()+'px; height:'+$cf.height()+'px;'+
					'line-height:'+$cf.height()+'px; background:white; box-sizing: border-box}'+
				'.captcha-loading {background-repeat:no-repeat; background-position:50% 50%; background-image:url("data:image/gif;base64,R0lGODlhEAALAPQAAO/v7wBmmc3b4sPV3tzk6AVomgBmmSt+qHqrxFqZua/K1x93o0WNsYGvxl6burLL2CN5pQNnmUmPs9jh5sva4eXp6zSDq8/c4+Pn6qvH1pa7zr7S3d/l6AAAAAAAAAAAACH/C05FVFNDQVBFMi4wAwEAAAAh/hpDcmVhdGVkIHdpdGggYWpheGxvYWQuaW5mbwAh+QQJCwAAACwAAAAAEAALAAAFLSAgjmRpnqSgCuLKAq5AEIM4zDVw03ve27ifDgfkEYe04kDIDC5zrtYKRa2WQgAh+QQJCwAAACwAAAAAEAALAAAFJGBhGAVgnqhpHIeRvsDawqns0qeN5+y967tYLyicBYE7EYkYAgAh+QQJCwAAACwAAAAAEAALAAAFNiAgjothLOOIJAkiGgxjpGKiKMkbz7SN6zIawJcDwIK9W/HISxGBzdHTuBNOmcJVCyoUlk7CEAAh+QQJCwAAACwAAAAAEAALAAAFNSAgjqQIRRFUAo3jNGIkSdHqPI8Tz3V55zuaDacDyIQ+YrBH+hWPzJFzOQQaeavWi7oqnVIhACH5BAkLAAAALAAAAAAQAAsAAAUyICCOZGme1rJY5kRRk7hI0mJSVUXJtF3iOl7tltsBZsNfUegjAY3I5sgFY55KqdX1GgIAIfkECQsAAAAsAAAAABAACwAABTcgII5kaZ4kcV2EqLJipmnZhWGXaOOitm2aXQ4g7P2Ct2ER4AMul00kj5g0Al8tADY2y6C+4FIIACH5BAkLAAAALAAAAAAQAAsAAAUvICCOZGme5ERRk6iy7qpyHCVStA3gNa/7txxwlwv2isSacYUc+l4tADQGQ1mvpBAAIfkECQsAAAAsAAAAABAACwAABS8gII5kaZ7kRFGTqLLuqnIcJVK0DeA1r/u3HHCXC/aKxJpxhRz6Xi0ANAZDWa+kEAA7AAAAAAAAAAAA");}'+
				'.captcha-info {display:inline-block; height:100%; width:100%; text-align:center;}'+
			'</style>');
			tout = $cwrap.data('timeout')|0;
			if(tout < 1) 
				tout = 120;
		}

		$cwrap.addClass('captcha-wrap');
		$cinp = $cf.parents('form')
			.off('click keydown', sel_inp, form_click)
			.on('click keydown', sel_inp, form_click)	// update by activity in form
			.find('input[name="captcha_text"]') 		// $cinp
				.off('keydown', captcha_input_eng)
				.on('keydown', captcha_input_eng);		// eng locale in captcha_text

		$cinf = $cwrap.find('.captcha-info');
		if(!$cinf.length) {
			$cinf = $('<span class="captcha-info"/>').hide();
			$cwrap.append($cinf);
		}

		quick_reply(); // init quick-form
	}

	function captcha_load(el) {
		clearTimeout(tid);
		timeout = false;
		updating = false;
		loaded = false;
		captcha_init(el);
		if(!$cf)
			return;
		$cinf.removeClass('captcha-loading').hide();
		$cinp.val('').trigger('change'); // clear captcha_text

		$($cf.get(0).contentWindow).off().on('unload', function() {
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
			$cwrap.off().on('click', captcha_update);
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
		if(!loaded)
			captcha_timeout();
		else
			tid = setTimeout(captcha_timeout, tout * 1000);
	}

	function form_click(ev) {
		if(!$cf || !timeout || updating || [16,17,18,19,20,144,145].indexOf(ev.which) >= 0)
			return;
		if(ev.target && $(ev.target).data('nocaptcha'))
			return;
		captcha_update();
	}

	function captcha_update() {
		if(!$cf || updating) 
			return;
		updating = true;		
		clearTimeout(tid);
		$cf.attr('src', $cf.data('src')); // update frame
	}

	function captcha_clone() {
		// clone captcha to quick-reply
		if(!$cf)
			return;
		var $ci = $cf.contents().find('#captcha_img img'),
			$qr = $('#quick-reply #captcha_qr');

		loaded = !!$ci.length;
		if(!$qr.length)
			return;

		$qr.addClass('captcha-wrap');
		if($cinf.is(':visible'))
			$qr.html($cinf.clone()); // clone info
		else
			$qr.html($ci.clone()); // clone image
		$qr.off().on('click', captcha_update);
	}

	function captcha_timeout() {
		clearTimeout(tid);
		timeout = true;
		if(!$cf)
			return;
		$cf.hide();
		$cinf.text(_('Click to update')+'...').show();
		captcha_clone();
		$cinp.val('').trigger('change'); // clear captcha_text
		$cwrap.off().on('click', captcha_update);
	}

	function quick_reply() {
    	// on quick-reply show
    	captcha_clone();
    	$('#quick-reply form')
    		.off('click keydown', sel_inp, form_click) 	// remove previous handler
    		.on('click keydown', sel_inp, form_click) 	// update by activity in quick-form
			.find('input[name="captcha_text"]')
				.off('keydown', captcha_input_eng)
				.on('keydown', captcha_input_eng);		// eng locale in captcha_text
    }

    function captcha_input_eng(ev) {
    	// english locale in captcha's input field
		if(ev.altKey || ev.ctrlKey) // skip when ctrl/alt pressed
			return;

		var el = this,
			keys = "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
			chKC = String.fromCharCode(ev.keyCode);

		if(keys.indexOf(chKC) < 0) // check scan-code
			return;
		if(ev.key.toUpperCase() === chKC) // (scan-code == char code) ? english
			return;

		var val = el.value,
			ps = val.length,
			pe = ps;

		// get cursor position (selected fragment)
		if("selectionStart" in el) {
			ps = el.selectionStart;
			pe = el.selectionEnd;
		}
		else if(document.selection) {
			var r = document.selection.createRange();
			if(r) {	 
				ps = el.createTextRange();
				pe = ps.duplicate();
				pe.moveToBookmark(r.getBookmark());
				ps.setEndPoint('EndToStart', pe); 
				ps = ps.text.length;
				pe = ps + pe.text.length;
			}
		}

		// insert pressed char
		val = val.substr(0, ps) + chKC.toLowerCase() + val.substr(pe);
		if(el.maxLength > 0 && val.length > el.maxLength) // check maxlength attribute
			return false;
		el.value = val;
		ps++;
		el.setSelectionRange(ps,ps); // set new cursor position
		$(el).trigger('change'); // signal to other listeners

		return false;
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
}();
