/* global _, Vi */

// time functions
Vi.time = function() {
	'use strict';
	var time = {
		until: until,
		ago: ago,
		dateformat : dateformat,
		datelocale: {
			days: [_('Sunday'), _('Monday'), _('Tuesday'), _('Wednesday'), _('Thursday'), _('Friday'), _('Saturday'), _('Sunday')],
			shortDays: [_("Sun"), _("Mon"), _("Tue"), _("Wed"), _("Thu"), _("Fri"), _("Sat"), _("Sun")],
			months: [_('January'), _('February'), _('March'), _('April'), _('May'), _('June'), _('July'), _('August'), _('September'), _('October'), _('November'), _('December')],
			shortMonths: [_('Jan'), _('Feb'), _('Mar'), _('Apr'), _('May'), _('Jun'), _('Jul'), _('Aug'), _('Sep'), _('Oct'), _('Nov'), _('Dec')],
			AM: _('AM'),
			PM: _('PM'),
			am: _('am'),
			pm: _('pm')
		},
	};

	return time;

	function until(timestamp, unix) {
		var num,
			diff = unix ? timestamp - Date.now()/1000|0 : (timestamp - Date.now())/1000|0;
		switch(true){
			case (diff < 60):
				return "" + diff + ' ' + _('second(s)');
			case (diff < 3600): //60*60 = 3600
				return "" + (num = Math.round(diff/(60))) + ' ' + _('minute(s)');
			case (diff < 86400): //60*60*24 = 86400
				return "" + (num = Math.round(diff/(3600))) + ' ' + _('hour(s)');
			case (diff < 604800): //60*60*24*7 = 604800
				return "" + (num = Math.round(diff/(86400))) + ' ' + _('day(s)');
			case (diff < 31536000): //60*60*24*365 = 31536000
				return "" + (num = Math.round(diff/(604800))) + ' ' + _('week(s)');
			default:
				return "" + (num = Math.round(diff/(31536000))) + ' ' + _('year(s)');
		}
	}

	function ago(timestamp, unix) {
		var num,
			diff = unix ? (Date.now()/1000|0) - timestamp : (Date.now() - timestamp)/1000|0;

		switch(true){
			case (diff < 60) :
				return "" + diff + ' ' + _('second(s)');
			case (diff < 3600): //60*60 = 3600
				return "" + (num = Math.round(diff/(60))) + ' ' + _('minute(s)');
			case (diff <  86400): //60*60*24 = 86400
				return "" + (num = Math.round(diff/(3600))) + ' ' + _('hour(s)');
			case (diff < 604800): //60*60*24*7 = 604800
				return "" + (num = Math.round(diff/(86400))) + ' ' + _('day(s)');
			case (diff < 31536000): //60*60*24*365 = 31536000
				return "" + (num = Math.round(diff/(604800))) + ' ' + _('week(s)');
			default:
				return "" + (num = Math.round(diff/(31536000))) + ' ' + _('year(s)');
		}
	}

	function dateformat(t, format) {
		return Vi.isDefined(window.strftime) ? 
			window.strftime(format || window.post_date, t, time.datelocale)
			: zeropad(t.getMonth() + 1, 2) + "/" + zeropad(t.getDate(), 2) + "/" + t.getFullYear().toString().substring(2) +
				" (" + time.datelocale.shortDays[t.getDay()]  + ") " +
				zeropad(t.getHours(), 2) + ":" + zeropad(t.getMinutes(), 2) + ":" + zeropad(t.getSeconds(), 2);

	}

	function zeropad(num, count) {
		return [Math.pow(10, count - num.toString().length), num].join('').substr(1);
	}
}();


