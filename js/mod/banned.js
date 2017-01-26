/* global Vi, _ */
(function(){
	'use strict';
	var $cnt = $('#countdown');
	if(!$cnt.length)
		return;

	var end = Date.now() + 1000*($cnt.data('left')|0);
	var int = setInterval(updateExpiresTime, 1000);
	updateExpiresTime();

	function updateExpiresTime() {
		if(end - Date.now() < 0) {
			clearInterval(int);
			$('#expires').html(_("and has since expired. Refresh the page to continue."));
		}
		else
			$cnt.text(Vi.time.until(end));
	}

})();