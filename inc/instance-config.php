<?php

/*
 *  Instance Configuration
 *  ----------------------
 *  Edit this file and not config.php for imageboard configuration.
 *
 *  You can copy values from config.php (defaults) and paste them here.
 */
// Note - you may want to change some of these in secrets.php instead of here
// See the secrets.example.php file
Vi::$config['debug'] = false;

Vi::$config['cookies']['mod']  = 'mod';

Vi::$config['cache']['enabled'] = 'redis';
Vi::$config['cache']['redis'] = [
	'address'	=> '/var/run/redis/redis.sock',
	'port'		=> 0,
	'password'	=> '',
	'timeout'	=> 3,
	'databases'	=> [
		'default'	=> 0,
		'captchas'	=> 1,
		'tor'		=> 2,
	]
];

Vi::$config['post_date'] = '%d/%m/%y (%a) %H:%M:%S';

Vi::$config['spam']['hidden_inputs_max_pass'] = 128;
Vi::$config['spam']['hidden_inputs_expire']   = 60 * 60 * 4; // three hours
Vi::$config['spam']['unicode']                = false;

Vi::$config['flood_time']              = 5;
Vi::$config['flood_time_ip']           = 30;
Vi::$config['flood_time_same']         = 2;
Vi::$config['max_body']                = 10000;
Vi::$config['reply_limit']             = 750;
Vi::$config['thumb_width']             = 255;
Vi::$config['thumb_height']            = 255;
Vi::$config['max_width']               = 10000;
Vi::$config['max_height']              = 10000;
Vi::$config['threads_per_page']        = 20;
Vi::$config['max_pages']               = 15;
Vi::$config['threads_preview']         = 3;
Vi::$config['root']                    = '/';
Vi::$config['always_noko']             = true;
Vi::$config['allow_no_country']        = true;
Vi::$config['thread_subject_in_title'] = true;

// Image shit
Vi::$config['thumb_method']                = 'imagick';
Vi::$config['thumb_ext']                   = 'jpg';
Vi::$config['thumb_keep_animation_frames'] = 1;
Vi::$config['show_ratio']                  = false;
//Vi::$config['allow_upload_by_url'] = true;
Vi::$config['max_filesize']        = 1024 * 1024 * 15; // 15MB
Vi::$config['spoiler_images']      = true;
Vi::$config['image_reject_repost'] = true;
Vi::$config['allowed_ext_files'][] = 'webm';
Vi::$config['allowed_ext_files'][] = 'mp4';
Vi::$config['allowed_ext_files'][] = 'mp3';
Vi::$config['allowed_ext_files'][] = 'ogg';
Vi::$config['allowed_ext_files'][] = 'pdf';
Vi::$config['webm']['use_ffmpeg']  = true;
Vi::$config['webm']['allow_audio'] = true;
Vi::$config['webm']['max_length']  = 60 * 60 * 4; // 4 hours

// Mod shit
Vi::$config['mod']['groups'][25] = 'GlobalVolunteer';
Vi::$config['mod']['groups'][19] = 'BoardVolunteer';
define_groups();
/*	Vi::$config['mod']['capcode'][BOARDVOLUNTEER] = array('Board Volunteer');
Vi::$config['mod']['capcode'][MOD] = array('Board Owner');
Vi::$config['mod']['capcode'][GLOBALVOLUNTEER] = array('Global Volunteer');
Vi::$config['mod']['capcode'][ADMIN] = array('Admin', 'Global Volunteer');
Vi::$config['custom_capcode']['Admin'] = array(
'<span class="capcode" title="This post was written by the global 8chan administrator."> <i class="fa fa-wheelchair" style="color:blue;"></i> <span style="color:red">8chan Administrator</span></span>',
);*/
//Vi::$config['mod']['view_banlist'] = GLOBALVOLUNTEER;
Vi::$config['mod']['recent_reports']      = 65535;
Vi::$config['mod']['ip_less_recentposts'] = 75;
Vi::$config['ban_show_post']              = true;

// Board shit
Vi::$config['page_404']         = false;
Vi::$config['max_links']        = 40;
Vi::$config['poster_id_length'] = 6;

Vi::$config['categories'] = [
	'Misc' => ['b', 'rus', 'mlp', 'vg', 'hi', 'bb'],
	'Technology' => ['tech', 'crypto'],
	'Politics' => ['pol', 'polru'], 
];
Vi::$config['threads_preview_sticky'] = 3;
// Vi::$config['cbRecaptcha'] = true;
Vi::$config['url_banner']                    = '/board_image.php';
Vi::$config['additional_javascript_compile'] = true;
Vi::$config['minify_html'] = true;
Vi::$config['minify_js'] = true;
//Vi::$config['default_stylesheet'] = array('Notsuba', 'notsuba.css');
Vi::$config['additional_javascript'][] = 'js/jquery.min.js';
Vi::$config['additional_javascript'][] = 'js/jquery.mixitup.min.js';
Vi::$config['additional_javascript'][] = 'js/jquery-ui.custom.min.js';
Vi::$config['additional_javascript'][] = 'js/catalog.js';
Vi::$config['additional_javascript'][] = 'js/captcha.js';
Vi::$config['additional_javascript'][] = 'js/board-banners.js';
Vi::$config['additional_javascript'][] = 'js/jquery.tablesorter.min.js';
Vi::$config['additional_javascript'][] = 'js/options.js';
Vi::$config['additional_javascript'][] = 'js/style-select.js';
Vi::$config['additional_javascript'][] = 'js/options/general.js';
Vi::$config['additional_javascript'][] = 'js/post-hover.js';
Vi::$config['additional_javascript'][] = 'js/update_boards.js';
Vi::$config['additional_javascript'][] = 'js/favorites.js';
Vi::$config['additional_javascript'][] = 'js/show-op.js';
Vi::$config['additional_javascript'][] = 'js/smartphone-spoiler.js';
Vi::$config['additional_javascript'][] = 'js/inline-expanding.js';
Vi::$config['additional_javascript'][] = 'js/show-backlinks.js';
Vi::$config['additional_javascript'][] = 'js/webm-settings.js';
Vi::$config['additional_javascript'][] = 'js/expand-video.js';
//	Vi::$config['additional_javascript'][] = 'js/treeview.js';
Vi::$config['additional_javascript'][] = 'js/expand-too-long.js';
Vi::$config['additional_javascript'][] = 'js/settings.js';
//	Vi::$config['additional_javascript'][] = 'js/hide-images.js';
Vi::$config['additional_javascript'][] = 'js/expand-all-images.js';
Vi::$config['additional_javascript'][] = 'js/strftime.min.js';
Vi::$config['additional_javascript'][] = 'js/local-time.js';
Vi::$config['additional_javascript'][] = 'js/expand.js';
Vi::$config['additional_javascript'][] = 'js/auto-reload.js';
Vi::$config['additional_javascript'][] = 'js/options/user-css.js';
Vi::$config['additional_javascript'][] = 'js/options/user-js.js';
Vi::$config['additional_javascript'][] = 'js/options/fav.js';
Vi::$config['additional_javascript'][] = 'js/forced-anon.js';
Vi::$config['additional_javascript'][] = 'js/toggle-locked-threads.js';
Vi::$config['additional_javascript'][] = 'js/toggle-images.js';
Vi::$config['additional_javascript'][] = 'js/mobile-style.js';
Vi::$config['additional_javascript'][] = 'js/id_highlighter.js';
Vi::$config['additional_javascript'][] = 'js/id_colors.js';
Vi::$config['additional_javascript'][] = 'js/inline.js';
Vi::$config['additional_javascript'][] = 'js/infinite-scroll.js';
//	Vi::$config['additional_javascript'][] = 'js/download-original.js';
Vi::$config['additional_javascript'][] = 'js/thread-watcher.js';
Vi::$config['additional_javascript'][] = 'js/ajax.js';
Vi::$config['additional_javascript'][] = 'js/quick-reply.js';
Vi::$config['additional_javascript'][] = 'js/quick-post-controls.js';
Vi::$config['additional_javascript'][] = 'js/show-own-posts.js';
Vi::$config['additional_javascript'][] = 'js/youtube.js';
Vi::$config['additional_javascript'][] = 'js/comment-toolbar.js';
Vi::$config['additional_javascript'][] = 'js/catalog-search.js';
//	Vi::$config['additional_javascript'][] = 'js/thread-stats.js';
Vi::$config['additional_javascript'][] = 'js/quote-selection.js';
Vi::$config['additional_javascript'][] = 'js/post-menu.js';
Vi::$config['additional_javascript'][] = 'js/post-filter.js';
Vi::$config['additional_javascript'][] = 'js/fix-report-delete-submit.js';
Vi::$config['additional_javascript'][] = 'js/image-hover.js';
Vi::$config['additional_javascript'][] = 'js/auto-scroll.js';
Vi::$config['additional_javascript'][] = 'js/emoji/emoji.js';
Vi::$config['additional_javascript'][] = 'js/file-selector.js';
//	Vi::$config['additional_javascript'][] = 'js/gallery-view.js';
Vi::$config['additional_javascript'][] = 'js/board-directory.js';
//	Vi::$config['additional_javascript'][] = 'js/live-index.js';
//	Vi::$config['additional_javascript'][] = 'js/altchans.js';
Vi::$config['additional_javascript'][] = 'js/ajax-post-controls.js';

//Vi::$config['font_awesome_css'] = '/netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css';

Vi::$config['stylesheets']['Brchan']    = 'brchan.css';
Vi::$config['default_stylesheet'] = array('Brchan', Vi::$config['stylesheets']['Brchan']);
Vi::$config['stylesheets']['Dark']      = 'dark.css';
Vi::$config['stylesheets']['Photon']    = 'photon.css';
Vi::$config['stylesheets']['Redchanit'] = 'redchanit.css';


Vi::$config['stylesheets_board'] = true;
Vi::$config['markup'][]          = array(["/\[spoiler\](.+?)\[\/spoiler\]/", "/%%(.+?)%%/"], "<span class=\"spoiler\">\$1</span>");
Vi::$config['markup'][]          = array("/~~(.+?)~~/", "<s>\$1</s>");
Vi::$config['markup'][]          = array("/__(.+?)__/", "<u>\$1</u>");
Vi::$config['markup'][]          = array("/###([^\s']+)###/", "<a href='/boards.html#\$1'>###\$1###</a>");

Vi::$config['markup_paragraphs'] = false;
Vi::$config['markup_rtl']        = true;

Vi::$config['boards'] = array(array(
	'<i class="fa fa-home"></i> ' . _('Home')              => '/',
	'<i class="fa fa-tags"></i> ' . _('Board list')        => '/boards.html',
	'<i class="fa fa-random"></i> ' . _('Random board')    => '/random.php',
	'<i class="fa fa-plus"></i> ' . _('Create your board') => '/create.php',
	'<i class="fa fa-search"></i> ' . _('Search')          => '/search.php',
	'<i class="fa fa-cog"></i> ' . _('Manage board')       => '/mod.php',
	'<i class="fa fa-quote-right"></i> irc'                => 'https://kiwiirc.com/client/irc.rizon.net/brchan.org',
	'<i class="fa fa-train fa-rotate-270"></i> ' . _('.onion')       => 'http://brchanansdnhvvnm.onion',
));
//Vi::$config['boards'] = array(array('<i class="fa fa-home" title="Home"></i>' => '/', '<i class="fa fa-tags" title="Boards"></i>' => '/boards.html', '<i class="fa fa-question" title="FAQ"></i>' => '/faq.html', '<i class="fa fa-random" title="Random"></i>' => '/random.php', '<i class="fa fa-plus" title="New board"></i>' => '/create.php', '<i class="fa fa-search" title="Search"></i>' => '/search.php', '<i class="fa fa-cog" title="Manage board"></i>' => '/mod.php', '<i class="fa fa-quote-right" title="Chat"></i>' => 'https://qchat.rizon.net/?channels=#8chan'), array('b', 'meta', 'int'), array('v', 'a', 'tg', 'fit', 'pol', 'tech', 'mu', 'co', 'sp', 'boards'), array('<i class="fa fa-twitter" title="Twitter"></i>'=>'https://twitter.com/infinitechan'));

//	Vi::$config['footer'][] = 'All posts on 8chan are the responsibility of the individual poster and not the administration of 8chan, pursuant to 47 U.S.C. &sect; 230.';
//	Vi::$config['footer'][] = 'We have not been served any secret court orders and are not under any gag orders.';
//	Vi::$config['footer'][] = 'To make a DMCA request or report illegal content, please email <a href="mailto:admin@8chan.co">admin@8chan.co</a>.';

Vi::$config['search']['enable'] = true;

Vi::$config['syslog'] = true;
Vi::$config['debug_log'] = 'tmp/error.log';

Vi::$config['hour_max_threads'] = 100;
Vi::$config['filters'][]        = array(
	'condition' => array(
		'custom' => 'max_posts_per_hour',
	),
	'action'    => 'reject',
	'message'   => 'On this board, to prevent raids the number of threads that can be created per hour is limited. Please try again later, or post in an existing thread.',
);

Vi::$config['create_thread_flood_time_ip'] = 120;
Vi::$config['filters'][] = array(
	'condition' => array(
		'flood-match' => array('ip'), // ip
		'flood-time'  => &Vi::$config['create_thread_flood_time_ip'], // time
		'op'          => true,// only on create thread
		'custom' => function($post, $filter) {
			foreach ($filter->flood_check as $flood_post) {
				if (time() - $flood_post['time'] <= Vi::$config['create_thread_flood_time_ip']) {
					$filter->message = sprintf($filter->message, until($flood_post['time'] + Vi::$config['create_thread_flood_time_ip']));
					return true;
				}
			}

		 	return false;
		}
	),
	'action'    => 'reject',
	'message'   => _('Wait %s for create new thread.'),
);

Vi::$config['filters'][] = array(
 	'condition' => array(
 		'flood-match' => array('ip', 'file'), // Match IP address and file
 		'flood-time' => 60 * 5, // 5 minutes
 		'flood-count' => 3 // At least recent posts
 	),
 	'action' => 'ban',
 	'reject' => true,
 	'add_note' => true,
 	'expires' => 60 * 15, // 15 min.
 	'reason' => 'Go away, spammer. (autoban)'
);

Vi::$config['filters'][] = array(
 	'condition' => array(
 		'flood-match' => array('ip'), // Match IP address and post body
 		'flood-time' => 70, // 30 sec.
 		'flood-count' => 5, // At least recent posts
 	),
 	'action' => 'ban',
 	'reject' => true,
 	'has_file' => true,
 	'add_note' => true,
 	'expires' => 60 * 15, // 15 min.
 	'reason' => 'Go away, spammer. (autoban)'
);

Vi::$config['filters'][] = array(
 	'condition' => array(
 		'flood-match' => array('ip', 'body'), // Match IP address and post body
 		'flood-time' => 60 * 5, // 5 minutes
 		'flood-count' => 3, // At least two recent posts
 		'!body' => '/^$/',
 	),
 	'action' => 'ban',
 	'reject' => true,
 	'add_note' => true,
 	'expires' => 60 * 15, // 15 min.
 	'reason' => 'Go away, spammer. (autoban body)'
);

Vi::$config['languages'] = array(
	'ar'  => "العربي",
	'ca'  => "Catalan",
	'ch'  => "汉语",
	'cs'  => "Čeština",
	'da'  => "Dansk",
	'de'  => "Deutsch",
	'en'  => "English",
	'eo'  => "Esperanto",
	'es'  => "Español",
	'et'  => "Eesti",
	'fi'  => "Suomi",
	'fr'  => "Français",
	'ga'  => "Irish",
	'he'  => "Hebrew",
	'hu'  => "Magyar",
	'is'  => "Icelandic",
	'it'  => "Italiano",
	'ja'  => "日本語",
	'jbo' => "Lojban",
	'lt'  => "Lietuvių Kalba",
	'lv'  => "Latviešu Valoda",
	'no'  => "Norsk",
	'nb'  => "Norwegian",
	'nl'  => "Nederlands Vlaams",
	'pl'  => "Polski",
	'pt'  => "Português",
	'ru'  => "Русский",
	'sk'  => "Slovenský Jazyk",
	'sv'  => "Swedish",
	'tr'  => "Turkish",
	'tr'  => "Turkish",
	'zh'  => "Chinese",
);

Vi::$config['gzip_static']       = false;
Vi::$config['hash_masked_ip']    = true;
Vi::$config['force_subject_op']  = false;
Vi::$config['min_links_op']      = 0;
Vi::$config['min_body']          = 0;
Vi::$config['early_404']         = false;
Vi::$config['early_404_page']    = 5;
Vi::$config['early_404_replies'] = 10;
Vi::$config['cron_bans']         = true;
Vi::$config['mask_db_error']     = true;
Vi::$config['ban_appeals']       = true;
Vi::$config['show_sages']        = false;
Vi::$config['katex']             = false;
Vi::$config['twig_cache']        = true;
Vi::$config['report_captcha']    = true;
Vi::$config['no_top_bar_boards'] = false;

Vi::$config['convert_args'] = '-size %dx%d %s -thumbnail %dx%d -quality 100%% -background \'#d6daf0\' -alpha remove -auto-orient +profile "*" %s';

// Flavor and design.
Vi::$config['domain'] = "http://brchan.org"; // Customize me
Vi::$config['tor_domain'] = "http://brchanansdnhvvnm.onion";

Vi::$config['site_name']        = "BRCHAN";
Vi::$config['site_slogan']      = "Desordem e Regresso";
Vi::$config['site_logo']        = "/static/Kuruminha_v2.png";
Vi::$config['site_description'] = "";
// Vi::$config['site_bitcoin'] = "1BUKMrz3BSr8tAnKGX7xpL7yPb1pVnJWkh";

Vi::$config['tor_posting'] = true;
Vi::$config['tor_image_posting'] = true;
Vi::$config['tor_serviceip'] = '127.0.0.1';
Vi::$config['tor_allow_delete'] = true;

Vi::$config['tor']['force_disable'] = false; // Принудительно запретить постинг
Vi::$config['tor']['allow_posting'] = true;
Vi::$config['tor']['allow_image_posting'] = true;
Vi::$config['tor']['cookie_name'] = 'amator';
Vi::$config['tor']['cookie_time'] = 60 * 61 * 60;
Vi::$config['tor']['max_posts'] = 30;
Vi::$config['tor']['max_fails'] = 3;
Vi::$config['tor']['need_capchas'] = 5;

// Allow for users create a own boards
Vi::$config['allow_create_userboards'] = true;

Vi::$config['country_flags_condensed'] = false;

// 8chan specific mod pages
require '8chan-mod-config.php';

// Load instance functions later on
require_once 'instance-functions.php';

// Load database credentials
require "secrets.php";
