/* global _, Vi */

// time functions
Vi.time = function() {
	'use strict';
	var time = {
		diff: diff,
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

	function diff(current, previous, justnow, suff) {
		var num, ret="",
			d = (current - previous) / 1000 | 0;
		if(d < 1 && justnow)
			return _('Just now');
		else if(d < 60)
			ret += d + ' ' + _('second(s)');
		else if(d < 3600) //60*60 = 3600
			ret += (num = Math.round(d/(60))) + ' ' + _('minute(s)');
		else if(d < 86400) //60*60*24 = 86400
			ret += (num = Math.round(d/(3600))) + ' ' + _('hour(s)');
		else if(d < 604800) //60*60*24*7 = 604800
			ret += (num = Math.round(d/(86400))) + ' ' + _('day(s)');
		else if(d < 31536000) //60*60*24*365 = 31536000
			ret += (num = Math.round(d/(604800))) + ' ' + _('week(s)');
		else
			ret += (num = Math.round(d/(31536000))) + ' ' + _('year(s)');
		if(suff)
			ret += ' ' + suff;
		return ret;
	}

	function until(timestamp, justnow, suff) {
		return diff(timestamp, Date.now(), justnow);
	}

	function ago(timestamp, justnow, suff) {
		return diff(Date.now(), timestamp, justnow, suff ? _("ago") : "");
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


