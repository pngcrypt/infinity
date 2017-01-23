/* global _ */
(function(){
	'use strict';
	var countdown = document.getElementById("countdown");
	if(!countdown)
		return;

	var end = Date.now() + 1000*(countdown.getAttribute('data-left')|0);

	function updateExpiresTime() {
		countdown.firstChild.nodeValue = until(end);
	}

	function until(end) {
		var num, diff = Math.round((end - Date.now()) / 1000);
		if (diff < 0) {
			document.getElementById("expires").innerHTML = _("has since expired. Refresh the page to continue.");
			clearInterval(int);
			return "";
		} else if (diff < 60) {
			return diff + " second" + (diff == 1 ? "" : "s");
		} else if (diff < 60*60) {
			return (num = Math.round(diff/(60))) + " minute" + (num == 1 ? "" : "s");
		} else if (diff < 60*60*24) {
			return (num = Math.round(diff/(60*60))) + " hour" + (num == 1 ? "" : "s");
		} else if (diff < 60*60*24*7) {
			return (num = Math.round(diff/(60*60*24))) + " day" + (num == 1 ? "" : "s");
		} else if (diff < 60*60*24*365) {
			return (num = Math.round(diff/(60*60*24*7))) + " week" + (num == 1 ? "" : "s");
		} else {
			return (num = Math.round(diff/(60*60*24*365))) + " year" + (num == 1 ? "" : "s");
		}
	}
	
	var int = setInterval(updateExpiresTime, 1000);
	updateExpiresTime();
})();