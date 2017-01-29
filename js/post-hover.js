/*
 * post-hover.js
 * https://github.com/savetheinternet/Tinyboard/blob/master/js/post-hover.js
 *
 * Released under the MIT license
 * Copyright (c) 2012 Michael Save <savetheinternet@tinyboard.org>
 * Copyright (c) 2013-2014 Marcin Łabanowski <marcin@6irc.net>
 * Copyright (c) 2013 Macil Tech <maciltech@gmail.com>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/post-hover.js';
 *
 */

/* global _, Vi */

$(function() {
	'use strict';

	window.init_hover = function() {
		var link = $(this);
		var id;
		var matches;

		if (link.is('[data-thread]')) {
				id = link.attr('data-thread');
		}
		else if( (matches = link.text().match(/^>>(?:>\/([^\/]+)\/)?(\d+)$/)) ) {
			id = matches[2];
		}
		else {
			return;
		}
		
		var board = $(this).parents('[data-board]');
		/*while (board.data('board') === undefined) {
			board = board.parent();
		}*/
		var threadid;
		if (link.is('[data-thread]')) threadid = 0;
		else {
			threadid = (board.attr('id') || '').replace("thread_", "");
			if(!threadid)
				return;
		}

		board = board.data('board');

		var parentboard = board;
		
		if (link.is('[data-thread]')) parentboard = $('form[name="post"] input[name="board"]').val();
		else if (matches[1] !== undefined) board = matches[1];

		var post = false;
		var hovering = false;
		link.hover(function() {
			hovering = true;

			var highlight_link = function(link, post) {
				var postLinks;
				var originalPost = link.parents('div.post').attr('id').replace("reply_", "").replace("inline_", "").replace("op_", "");
				if (link.hasClass('mentioned-'+id)) {
					postLinks = post.find('div.body a:not([rel="nofollow"])');
					if (postLinks.length > 0) {
						postLinks.each(function() {
							if ($(this).text() == ">>"+originalPost) {
								$(this).addClass('dashed-underline');
							}
						});
					}
				}
				else
					post.find('a.mentioned-'+originalPost).addClass('dashed-underline');

			};

			var start_hover = function(link) {
				/*if(post.is(':visible') &&
						post.offset().top >= $(window).scrollTop() &&
						post.offset().top + post.height() <= $(window).scrollTop() + $(window).height()) {
					// post is in view
					post.addClass('highlighted');
					highlight_link(link, post);
				} else {*/
					var newPost = post.clone();
					newPost.find('>.reply, >br').remove();
					newPost.find('a.post_anchor').remove();

					newPost
						.attr('id', 'post-hover-' + id)
						.attr('data-board', board)
						.addClass('post-hover')
						.css('border-style', 'solid')
						.css('box-shadow', '1px 1px 1px #999')
						.css('display', 'block')
						.css('position', 'absolute')
						.css('font-style', 'normal')
						.css('z-index', '100')
						.css('left', '0')
						.css('margin-left', '')
						.addClass('reply').addClass('post')
						.appendTo(link.closest('div.post'));
						
					// shrink expanded images
					newPost.find('div.file img.post-image').css({'display': '', 'opacity': ''});
					newPost.find('div.file img.full-image').remove();
					
					// Highlight references to the current post
					highlight_link(link, newPost);
					
					var previewWidth = newPost.outerWidth(true);
					var widthDiff = previewWidth - newPost.width();
					var linkLeft = link.offset().left;
					var left, top;
					var ww = $(window).width(),
						wh = $(window).height(),
						scrollTop = $(window).scrollTop();

					
					if (linkLeft < $(document).width() * 0.7) {
						left = linkLeft + link.width();
						if (left + previewWidth > ww) {
							newPost.css('width', ww - left - widthDiff);
						}
					} else {
						if (previewWidth > linkLeft) {
							newPost.css('width', linkLeft - widthDiff);
							previewWidth = linkLeft;
						}
						left = linkLeft - previewWidth;
					}
					newPost.css('left', left);
					
					top = link.offset().top - 10;
					
					if (link.is("[data-thread]")) {
						top -= scrollTop;
						scrollTop = 0;
					}
					
					if(top < scrollTop + 15) {
						top = scrollTop + 15;
					} else if(top > scrollTop + wh - newPost.height() - 45) {
						top = scrollTop + wh - newPost.height() - 45;
					}
					
					if (newPost.height() > wh) {
						top = scrollTop;
					}

					newPost.css('top', top);
				//}
			};
			
			
			post = $('[data-board="' + board + '"] div.post#reply_' + id + ', [data-board="' + board + '"]div#thread_' + id);
			if(post.length > 0) {
				start_hover($(this));
			} else {
				var url = link.attr('href').replace(/#.*$/, '').replace('.html', '.json');
				var dataPromise = getPost(id, url);

				dataPromise.done(function (data) {
					//	reconstruct post from json response
					var file_array = [];
					var multifile = false;

					var add_info = function (data) {
						var file = {
							'thumb_h': data.tn_h,
							'thumb_w': data.tn_w,
							'fsize': data.fsize,
							'filename': data.filename,
							'ext': data.ext,
							'tim': data.tim
						};

						if ('h' in data) {
							file.isImage = true; //(or video)
							file.h = data.h;
							file.w = data.w;
						} else {
							file.isImage = false;
						}
						// since response doens't indicate spoilered files,
						// we'll just make do by assuming any image with 128*128px thumbnail is spoilered.
						// which is probably 99% of the cases anyway.
						file.isSpoiler = (data.tn_h == 128 && data.tn_w == 128);

						file_array.push(file);
					};

					var bytesToSize = function (bytes) {
						var sizes = [_('Bytes'), _('KB'), _('MB')];
						var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));

						return (i === 0) ? bytes +' '+ sizes[i] : (bytes / Math.pow(1024, i)).toFixed(2) +' ' +sizes[i];
					};

					var time = (!localStorage.show_relative_time || localStorage.show_relative_time === 'false') ? Vi.time.dateformat(new Date(data.time*1000)) : Vi.time.ago(data.time*1000, true, true);
					var $post = $('<div class="post reply hidden" id="reply_'+ data.no +'">')
								.append($('<p class="intro"></p>')
									.append('<span class="name">'+ data.name +'</span> ')
									.append('<time datetime="'+ new Date(data.time*1000).toISOString() +'">'+ time +'</time>')
									.append('<a class="post_no"> No.'+ data.no +'</a>')
								)
								.append($('<div class="body"></div>')
									.html(data.com)
								)
								.css('display', 'none');

					//	other stuff
					if ('sub' in data) $post.find('.intro').prepend('<span class="subject">'+ data.sub +'</span> ');
					if ('trip' in data) $post.find('.name').after('<span class="trip">'+ data.trip +'</span>');
					if ('capcode' in data) $post.find('.post_no').before('<span class="capcode">## '+ data.capcode +'</span>');
					if ('id' in data) $post.find('.post_no').before('<span class="poster_id">'+ data.id +'</span>');
					if ('embed' in data) $post.find('p.intro').after(data.embed);

					if ('filename' in data) {
						var $files = $('<div class="files">');

						add_info(data);
						if ('extra_files' in data) {
							multifile = true;
							$.each(data.extra_files, function () {
								add_info(this);
							});
						}

						$.each(file_array, function () {
							var thumb_url;
							var file_ext = this.ext;

							if (this.isImage && !this.isSpoiler) {
								if (this.ext == '.gif' || this.ext === '.webm' || this.ext === '.mp4' || this.ext === '.jpeg') {
									this.ext = '.jpg';
								}

								thumb_url = '/'+ board +'/thumb/' + this.tim + this.ext;
							}
							else {
								thumb_url = (this.isSpoiler) ? '/static/spoiler.png' : '/static/file.png';
							}

							// truncate long filenames
							if (this.filename.length > 23) {
								this.filename = this.filename.substr(0, 22) + '…';
							}

							// file infos
							var $ele = $('<div class="file">')
										.append($('<p class="fileinfo">')
											.append('<span>' + _('File:') + ' </span>')
											.append('<a>'+ this.filename + file_ext +'</a>')
											.append('<span class="unimportant"> ('+ bytesToSize(this.fsize) +', '+ this.w +'x'+ this.h +')</span>')
										);
							if (multifile) $ele.addClass('multifile').css('width', this.thumb_w + 30);

							// image
							var $img = $('<img class="post-image">')
												.css('width', this.thumb_w)
												.css('height', this.thumb_h)
												.attr('src', thumb_url);

							$ele.append($img);
							$files.append($ele);
						});
						
						$post.children('p.intro').after($files);
					}

					if(window.emoji)
						window.emoji.parse($post[0]);

					var mythreadid = (data.resto !== 0) ? data.resto : data.no;

					if (mythreadid != threadid || parentboard != board) {
						// previewing post from external thread/board
						if ($('div#thread_'+ mythreadid +'[data-board="'+ board +'"]').length === 0) {
							$('form[name="postcontrols"]').prepend('<div class="thread" id="thread_'+ mythreadid +'" data-board="'+ board +'" style="display: none;"></div>');
						}
					}
					if ($('div#thread_'+ mythreadid +'[data-board="'+ board +'"]').children('#reply_'+ data.no).length === 0) {
						$('div#thread_'+ mythreadid +'[data-board="'+ board +'"]').prepend($post);
					}

					post = $('[data-board="' + board + '"] div.post#reply_' + id + ', [data-board="' + board + '"]div#thread_' + id);
					if (hovering && post.length > 0) {
						start_hover(link);
					}
				});
			}
		}, function() {
			hovering = false;
			if(!post)
				return;
			
			post.removeClass('highlighted');
			post.find('a.dashed-underline').removeClass('dashed-underline');
			if(post.hasClass('hidden'))
				post.css('display', 'none');
			$('.post-hover').remove();
		});
	};

	var getPost = (function () {
		var cache = {};
		return function (targetId, url) {
			var deferred = $.Deferred();
			var data, post;

			var findPost = function (targetId, data) {
				var arr = data.posts;
				for (var i=0; i<arr.length; i++) {
					if (arr[i].no == targetId)
						return arr[i];
				}
				return false;
			};
			var get = function (targetId, url) {
				$.ajax({
					url: url,
					success: function (response) {
						cache[url] = response;
						var post = findPost(targetId, response);
						deferred.resolve(post);
					}
				});
			};

			//	check for cached response and check if it's stale
			if ((data = cache[url]) !== undefined && (post = findPost(targetId, data))) {
				deferred.resolve(post);
			} else {
				get(targetId, url);
			}
			return deferred.promise();
		};
	})();

	$('div.body a:not([rel="nofollow"])').each(window.init_hover);
	
	// allow to work with auto-reload.js, etc.
	$(document).on('new_post', function(e, post) {
		$(post).find('div.body a:not([rel="nofollow"])').each(window.init_hover);
	});
});

