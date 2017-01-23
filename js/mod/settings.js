$(function() {
	'use strict';

	// filters
	$('#wf_add').on('click', function() {
		$('#wf').append('<tr><td><input name="replace[]"></td><td><input name="with[]"></td></tr>');
	});

	// tags
	$('#tag_add').on('click', function() {
		$('#tags').append('<tr><td><input name="tag_id[]"></td><td><input name="tag_desc[]"></td></tr>');
	});

	// board type
	$('#board_type').on('change', function() {
		$('.txtboard, .fileboard, .imgboard').hide();
		$('.'+$('#board_type').val()).show();
	}).change();

	// flags type
	$('#country_flags_select').on('change', function() {
		if($('#country_flags_select').val() !== 'disabled')
			$('#force_flag').show();
		else
			$('#force_flag').hide();
	}).change();
});