{% raw %}
/* 
 * main.js - This file is compiled and contains code from the following scripts, concatenated together in order:
 * {% endraw %}{{ config.additional_javascript|join(', ') }}{% raw %}
 * Please see those files for licensing and authorship information.
 * Compiled on {% endraw %}{{ time()|date("%c") }}{% raw %}
 */

{% endraw %}
var Vi = {
	config: {
		favorites_def: ["b","rus","mod"], // default favorites
		cookie: {
			js: "{{ config.cookies.js }}"
		}
	},

	extend: function () {
		var i = 0, res = {}, k, attr;
		for (; i < arguments.length; i++) {
			attr = arguments[i];
			for (k in attr) {
				res[k] = attr[k];
			}
		}
		return res;
	},
	isDefined: function(v) {return (typeof v != 'undefined');},
	isNull: function(v) { return (v === null); },
	isArray: Array.isArray,
	isFunction: function(f) { return (typeof(f) == 'function'); },
	isString: function(v) { return (typeof(v) == 'string'); },
	isNumber: function(v) { return (typeof(v) == 'number'); },
	isObject: function(v) { return (typeof(v) == 'object'); },
};
{% raw %}

/* gettext-compatible _ function, example: alert(_("Hello!")); */
function _(s) {
	return (typeof l10n != 'undefined' && typeof l10n[s] != 'undefined') ? l10n[s] : s;
}

/* printf-like formatting function, example of usage:
 * > alert(fmt("There are {0} birds on {1} trees", [3,4]));
 * > alert(fmt(_("{0} users"), [3]));
 */
function fmt(s,a) {
	return s.replace(/\{([0-9]+)\}/g, function(x) { return a[x[1]]; });
}

function until($timestamp) {
		var $difference = $timestamp - Date.now()/1000|0, $num;
		switch(true){
		case ($difference < 60):
				return "" + $difference + ' ' + _('second(s)');
		case ($difference < 3600): //60*60 = 3600
				return "" + ($num = Math.round($difference/(60))) + ' ' + _('minute(s)');
		case ($difference < 86400): //60*60*24 = 86400
				return "" + ($num = Math.round($difference/(3600))) + ' ' + _('hour(s)');
		case ($difference < 604800): //60*60*24*7 = 604800
				return "" + ($num = Math.round($difference/(86400))) + ' ' + _('day(s)');
		case ($difference < 31536000): //60*60*24*365 = 31536000
				return "" + ($num = Math.round($difference/(604800))) + ' ' + _('week(s)');
		default:
				return "" + ($num = Math.round($difference/(31536000))) + ' ' + _('year(s)');
		}
}

function ago($timestamp) {
		var $difference = (Date.now()/1000|0) - $timestamp, $num;
		switch(true){
		case ($difference < 60) :
				return "" + $difference + ' ' + _('second(s)');
		case ($difference < 3600): //60*60 = 3600 
				return "" + ($num = Math.round($difference/(60))) + ' ' + _('minute(s)');
		case ($difference <  86400): //60*60*24 = 86400
				return "" + ($num = Math.round($difference/(3600))) + ' ' + _('hour(s)');
		case ($difference < 604800): //60*60*24*7 = 604800
				return "" + ($num = Math.round($difference/(86400))) + ' ' + _('day(s)');
		case ($difference < 31536000): //60*60*24*365 = 31536000
				return "" + ($num = Math.round($difference/(604800))) + ' ' + _('week(s)');
		default:
				return "" + ($num = Math.round($difference/(31536000))) + ' ' + _('year(s)');
		}
}

var datelocale =
		{ days: [_('Sunday'), _('Monday'), _('Tuesday'), _('Wednesday'), _('Thursday'), _('Friday'), _('Saturday')]
		, shortDays: [_("Sun"), _("Mon"), _("Tue"), _("Wed"), _("Thu"), _("Fri"), _("Sat")]
		, months: [_('January'), _('February'), _('March'), _('April'), _('May'), _('June'), _('July'), _('August'), _('September'), _('October'), _('November'), _('December')]
		, shortMonths: [_('Jan'), _('Feb'), _('Mar'), _('Apr'), _('May'), _('Jun'), _('Jul'), _('Aug'), _('Sep'), _('Oct'), _('Nov'), _('Dec')]
		, AM: _('AM')
		, PM: _('PM')
		, am: _('am')
		, pm: _('pm')
		};


function alert(a, do_confirm, confirm_ok_action, confirm_cancel_action) {
	var handler, div, bg, closebtn, okbtn;
	var close = function() {
		handler.fadeOut(400, function() { handler.remove(); });
		return false;
	};

	handler = $("<div id='alert_handler'></div>").hide().appendTo('body');

	bg = $("<div id='alert_background'></div>").appendTo(handler);

	div = $("<div id='alert_div'></div>").appendTo(handler);
	closebtn = $("<a id='alert_close' href='javascript:void(0)'><i class='fa fa-times'></i></div>")
		.appendTo(div);

	$("<div id='alert_message'></div>").html(a).appendTo(div);

	okbtn = $("<button class='button alert_button'>"+_("OK")+"</button>").appendTo(div);

	if (do_confirm) {
		confirm_ok_action = (typeof confirm_ok_action !== "function") ? function(){} : confirm_ok_action;
		confirm_cancel_action = (typeof confirm_cancel_action !== "function") ? function(){} : confirm_cancel_action;
		okbtn.click(confirm_ok_action);
		$("<button class='button alert_button'>"+_("Cancel")+"</button>").click(confirm_cancel_action).click(close).appendTo(div);
		bg.click(confirm_cancel_action);
		okbtn.click(confirm_cancel_action);
		closebtn.click(confirm_cancel_action);
	} 
	
	bg.click(close);
	okbtn.click(close);
	closebtn.click(close);

	handler.fadeIn(400);
}

var saved = {};

if (typeof board_name === "undefined") {
	var matches = document.URL.match({% endraw %}/\/([0-9a-zA-Z\+$_\u0080-\uFFFF]{1,58})\/($|{{ config.dir.res|replace({'/': '\\/'}) }}{{ config.file_page|replace({'%d': '\\d+', '.': '\\.'}) }}|{{ config.file_index|replace({'.': '\\.'}) }}|{{ config.dir.res|replace({'/': '\\/'}) }}{{ config.file_page50|replace({'+': '\\+', '%d': '\\d+', '.': '\\.'}) }}|{{ config.file_page|replace({'%d': '\\d+', '.': '\\.'}) }}|{{ config.catalog_link|replace({'.': '\\.'}) }})/{% raw %});
	var board_name = (matches ? matches[1] : false);
}

function get_cookie(cookie_name) {
	var results = document.cookie.match ( '(^|;) ?' + cookie_name + '=([^;]*)(;|$)');
	
	if (results) {
		return (unescape(results[2]));
	}
	else {
		return null;
	}
}

function highlightReply(id, event) {
	// check if external post
	var post_list, arr, i;
	id = id.toString();

	post_list = document.querySelectorAll('a.post_no');
	for (i = 0, arr = []; i<post_list.length; i++) {
		arr.push(post_list[i]);
	}
	arr = arr.filter(function (ele) {
		if (ele.hasAttribute('id') || ((' '+ ele.parentElement.parentElement.className +' ').indexOf(' hidden ') > -1)) {
			return false;
		} else {
			return true;
		}
	});
	for (i = 0, post_list = []; i < arr.length; i++) {
		post_list.push(arr[i].innerHTML);
	}

	if (post_list.indexOf(id) == -1)
		return true;
	
	// don't highlight on middle click
	var e = event || window.event;
	if (typeof e != "undefined") {
		if (e.which == 2) return true;
		if (active_page == 'thread' && typeof e.preventDefault != "undefined") e.preventDefault();
	}
	
	var divs = document.getElementsByTagName('div');
	for (var i = 0; i < divs.length; i++)
	{
		if (divs[i].className.indexOf('post') != -1)
			divs[i].className = divs[i].className.replace(/highlighted/, '');
	}
	if (id) {
		var post = document.getElementById('reply_'+id);
		// No reply? Try OP.
		if (!post) {
			var post = document.getElementById('op_'+id);
		}

		if (post) {
			post.className += ' highlighted';

			if (history.pushState) {
				history.pushState(null, null, window.document.location.protocol + "//" + window.document.location.host + window.document.location.pathname + window.document.location.search + '#' + id); 
			} else {
				window.location.hash = id;
			}			

			// Better offset to keep in mind new hovering boardlist
			var post_top = post.getBoundingClientRect().top;
			var body_top = document.body.getBoundingClientRect().top;
			var boardlist_height = document.getElementsByClassName('boardlist')[0].getBoundingClientRect().height;
			var offset = (post_top - body_top) - boardlist_height;

			window.scrollTo(0, offset);
		}
	}
	return true;
}

function generatePassword() {
	var pass = '';
	var chars = '{% endraw %}{{ config.genpassword_chars }}{% raw %}';
	for (var i = 0; i < 8; i++) {
		var rnd = Math.floor(Math.random() * chars.length);
		pass += chars.substring(rnd, rnd + 1);
	}
	return pass;
}

function dopost(form) {
	if (form.elements['name']) {
		localStorage.name = form.elements['name'].value.replace(/( |^)## .+$/, '');
	}
	if (form.elements['password']) {
		localStorage.password = form.elements['password'].value;
	}
	if (form.elements['user_flag']) {
		if (localStorage.userflags) {
			var userflags = JSON.parse(localStorage.userflags);
		} else {
			localStorage.userflags = '{}';
			userflags = {};
		}
		userflags[board_name] = form.elements['user_flag'].value;
		localStorage.userflags = JSON.stringify(userflags);
	}
	if (form.elements['email'] && form.elements['email'].value != 'sage') {
		localStorage.email = form.elements['email'].value;
	}
	
	saved[document.location] = form.elements['body'].value;
	sessionStorage.body = JSON.stringify(saved);
	
	return form.elements['body'].value != "" || (form.elements['file'] && form.elements['file'].value != "") || (form.elements.file_url && form.elements['file_url'].value != "");
}

function citeReply(id, with_link) {
	var textarea = document.getElementById('body');

	if (!textarea) return false;
	
	if (document.selection) {
		// IE
		textarea.focus();
		var sel = document.selection.createRange();
		sel.text = '>>' + id + '\n';
	} else if (textarea.selectionStart || textarea.selectionStart == '0') {
		var start = textarea.selectionStart;
		var end = textarea.selectionEnd;
		textarea.value = textarea.value.substring(0, start) + '>>' + id + '\n' + textarea.value.substring(end, textarea.value.length);
		
		textarea.selectionStart += ('>>' + id).length + 1;
		textarea.selectionEnd = textarea.selectionStart;
	} else {
		// ???
		textarea.value += '>>' + id + '\n';
	}

	// multiline quotes
	var select = sessionStorage.quoteClipboard;
	if (select) {
		select = select.split('\n');
		select.forEach(function (str) {
			if (str !== '') {
				str = '>' + str + '\n';
			} else {
				str = '\n';
			}
			textarea.value += str;
		});
		delete sessionStorage.quoteClipboard;
	}

	if (typeof $ != 'undefined') {
		$(window).trigger('cite', [id, with_link]);
		$(textarea).change();
	}
	return false;
}

function rememberStuff() {
	if (document.forms.post) {
		if (document.forms.post.password) {
			if (!localStorage.password)
				localStorage.password = generatePassword();
			document.forms.post.password.value = localStorage.password;
		}
		
		if (localStorage.name && document.forms.post.elements['name'])
			document.forms.post.elements['name'].value = localStorage.name;
		if (localStorage.email && document.forms.post.elements['email'])
			document.forms.post.elements['email'].value = localStorage.email;
		if (localStorage.userflags && document.forms.post.elements['user_flag']) {
			var userflags = JSON.parse(localStorage.userflags);
			document.forms.post.elements['user_flag'].value = userflags[board_name];
		}
		
		if (window.location.hash.indexOf('q') == 1)
			citeReply(window.location.hash.substring(2), true);
		
		if (sessionStorage.body) {
			var saved = JSON.parse(sessionStorage.body);
			if (get_cookie('{% endraw %}{{ config.cookies.js }}{% raw %}')) {
				// Remove successful posts
				var successful = JSON.parse(get_cookie('{% endraw %}{{ config.cookies.js }}{% raw %}'));
				for (var url in successful) {
					saved[url] = null;
				}
				sessionStorage.body = JSON.stringify(saved);
				
				document.cookie = '{% endraw %}{{ config.cookies.js }}{% raw %}={};expires=0;path=/;';
			}
			if (saved[document.location]) {
				document.forms.post.body.value = saved[document.location];
			}
		}
		
		if (localStorage.body) {
			document.forms.post.body.value = localStorage.body;
			localStorage.body = '';
		}
	}
}

var script_settings = function(script_name) {
	this.script_name = script_name;
	this.get = function(var_name, default_val) {
		if (typeof tb_settings == 'undefined' ||
			typeof tb_settings[this.script_name] == 'undefined' ||
			typeof tb_settings[this.script_name][var_name] == 'undefined')
			return default_val;
		return tb_settings[this.script_name][var_name];
	}
};

function init() {
	//	store highlighted text for citeReply()
	if (document.querySelector('form[name="postcontrols"]') !== null) {
		document.querySelector('form[name="postcontrols"]').addEventListener('mouseup', function (e) {
			sessionStorage.quoteClipboard = window.getSelection().toString();
		});
	}

	// just enable jquery, almost every script requires it by now. more and more main.js functions are going to start requiring it
	{% endraw %}	
	{% if config.allow_delete %}
	if (document.forms.postcontrols && document.forms.postcontrols.password) {
		document.forms.postcontrols.password.value = localStorage.password;
	}
	{% endif %}
	{% raw %}
	
	if (window.location.hash.indexOf('q') != 1 && window.location.hash.substring(1))
		highlightReply(window.location.hash.substring(1));
}

var RecaptchaOptions = {
	theme : 'clean'
};

onready_callbacks = [];
function onready(fnc) {
	onready_callbacks.push(fnc);
}

function ready() {
	for (var i = 0; i < onready_callbacks.length; i++) {
		onready_callbacks[i]();
	}
}

{% endraw %}

var post_date = "{{ config.post_date }}";
if (typeof active_page === "undefined") {
	active_page = "page";
}

{% if config.google_analytics %}{% raw %}

var _gaq = _gaq || [];_gaq.push(['_setAccount', '{% endraw %}{{ config.google_analytics }}{% raw %}']);{% endraw %}{% if config.google_analytics_domain %}{% raw %}_gaq.push(['_setDomainName', '{% endraw %}{{ config.google_analytics_domain }}{% raw %}']){% endraw %}{% endif %}{% if not config.google_analytics_domain %}{% raw %}_gaq.push(['_setDomainName', 'none']){% endraw %}{% endif %}{% raw %};_gaq.push(['_trackPageview']);(function() {var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;ga.src = ('https:' == document.location.protocol ? 'https://' : 'http://') + 'stats.g.doubleclick.net/dc.js';var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);})();{% endraw %}{% endif %}

{% if config.statcounter_project and config.statcounter_security %}
var sc = document.createElement('script');
sc.type = 'text/javascript';
sc.innerHTML = 'var sc_project={{ config.statcounter_project }};var sc_invisible=1;var sc_security="{{ config.statcounter_security }}";var scJsHost=(("https:" == document.location.protocol) ? "https://secure." : "http://www.");document.write("<sc"+"ript type=text/javascript src="+scJsHost+"statcounter.com/counter/counter.js></"+"script>");';
var s = document.getElementsByTagName('script')[0];
s.parentNode.insertBefore(sc, s);
{% endif %}

