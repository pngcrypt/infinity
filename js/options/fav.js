/* global _, fmt, Vi, Options, alert */

$(function() {
	'use strict';

	if(typeof Options == 'undefined')
		return;

	var i,favorites;

	$('head').append('<style>'+
		'.fav-tab #sortable {display: inline-block;}'+
		'.fav-tab .fav-info {margin: 4px; border-bottom: 1px solid grey;}'+
		'.fav-tab .fav-board {cursor: pointer; padding-left: 16px; position: relative;}'+
		'.fav-tab .fav-remove {cursor: pointer; position: absolute; margin-left: -12px; color: grey;}'+
		'.fav-tab .fav-remove:hover {color: red;}'+
		'.fav-tab .fav-add {margin: 4px;}'+
		'.fav-tab input[type="button"] {width: 20px; padding: 0; margin: 0; height: 100%;}'+
	'</style>');

	fav_load();

	// list of boards 
	var $favList = $('<div id="sortable"/>');
	for (i = 0; i < favorites.length; i++) {
		fav_add_item(favorites[i]);
	}
	$favList
		.sortable() //Making boards with sortable id use the sortable jquery function
		.on('sortstop', function() {
			// sort names
			favorites = [];
			$favList.find('.fav-board').each(function(){
				favorites.push($(this).data('name'));
			});
			fav_save();
			$(document).trigger('favorites_change');
		})
		.on('mouseenter', '.fav-board', function() {
			// show delete link
			$(this).prepend($del_link);
			$del_link.show();
		})
		.on('mouseleave', '.fav-board', function() {
			// hide delete link
			$favList.append($del_link.hide());
		});
	
	// delete link
	var $del_link = $('<span class="fav-remove fa fa-times" title="'+ _('Delete') +'"/>')
		.hide()
		.on('click', function() {
			// remove item
			var $item = $del_link.parent();
			var bi = favorites.indexOf($item.data('name'));
			$favList.append($del_link.hide());			
			$item.remove();
			if(bi >= 0) {
				favorites.splice(bi, 1);
				fav_save();
				$(document).trigger('favorites_change');
			}
		})
		.appendTo($favList);

	// append div
	var $divAdd = $('<div class="fav-add"/>');
	var $inpAdd = $('<input type="text">')
		.on('keydown', function(ev) {
			if (ev.keyCode == 13) {
				$addBtn.click();
			}
		})
		.appendTo($divAdd);

	var $addBtn = $('<input type="button" value="+" title="'+ _('Add') +'"/>')
		.on("click", function() {
			// add board
			var board = $inpAdd.val().trim().toLowerCase();
			if(board === "")
				return;
			if(favorites.indexOf(board) >= 0) {
				alert(fmt(_('Board "{0}" already in favorites'), [board]));
				return;
			}
			$inpAdd.val("");
			fav_add_item(board);
			favorites.push(board);
			fav_save();
			$(document).trigger('favorites_change');
		})
		.appendTo($divAdd);

	// make tab
	Options.add_tab('fav-tab', 'star', _('Favorites')).content
		.append('<div class="fav-info">' + _("Drag the boards to sort them.") + "</div>")
		.append($favList)
		.append($divAdd);

	return;

	function fav_load() {
		try {
			favorites = JSON.parse(localStorage.getItem('favorites'));
		}
		catch(e) {}
		if(!Vi.isArray(favorites)) {
			if(Vi.config.favorites_def && Vi.isArray(Vi.config.favorites_def))
				favorites = Vi.config.favorites_def; // get defaults if localstorage is empty
			else
				favorites = [];
		}
		favorites = favorites.map(function(v) {
			return String(v).toLowerCase();
		});
	}

	function fav_save() {
		if(!Vi.isArray(favorites))
			favorites = [];
		else {
			favorites = favorites.map(function(v) {
				return String(v).toLowerCase();
			});
		}
		localStorage.setItem('favorites', JSON.stringify(favorites));
	}

	function fav_add_item(name) {
		$favList.append($('<div class="fav-board">' + name + '</div>').data('name', name));
	}

});
