/* global emoji, alert */

(function(){
	'use strict';

	window.emoji_nostart=true;

	var icon_size = $('head').data('size'),
		font_size = $('head').data('fontsize'),
		css_file = $('head').data('css');

	$('<style>'+
		'i {'+
			'display:inline-block;'+
			'width: '+ icon_size +'px;'+
			'max-width: '+ icon_size +'px;'+
			'line-height: '+ icon_size +'px;'+
			'font-size: '+ font_size +';'+
			'font-style: normal;'+
			'text-align: center;'+
			'vertical-align: top;'+
			'word-wrap: break-word;'+
			'border: 0;'+
			'margin: 0;'+
			'padding: 0;'+
		'}'+
		'.emoji {vertical-align: top !important;}'+
		'div {display:inline-block; border:1px solid grey; margin:2px;}'+
		'.container {position: absolute; left:0; right: 100px; }'+
	'</style>').appendTo('head');


	function checkErrors() {
		var err = 0;
		console.log('-------------------------------');

		$('.container i:not([class])').each(function(){
			var $parent = $(this).parent();
			if(this.clientHeight > icon_size) {
				err++;
				$parent.css({
					'border-color': 'red',
					'background-color': 'rgba(255,0,0,0.1)',
				});
				console.log('ERR: ', $parent.attr('id'), ':', $parent.data('uc'));
			}
			else {
				$parent.css({
					'border-color': 'grey',
					'background-color': 'rgba(0,0,0,0)',
				});
			}
		});
		return err;
	}

	$(function() {
		emoji.setCSS(css_file);
		checkErrors();

		var $btn = $('<input type="button" value="Parse Emoji"/>').appendTo($('body'));
		$btn.css({
			'position': 'fixed',
			'top': '8px',
			'right': '8px'
		})
		.on('click', function(){
			$btn.attr('disabled', '');
			emoji.parse(document.body);
			var err = checkErrors();
			setTimeout(function(){
				if(err)
					alert(err+' errors found');
				else
					alert('NO ERRORS!');
				$btn.removeAttr('disabled');
			}, 0);
		});
	});

})();