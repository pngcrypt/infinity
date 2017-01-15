<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 *
 *  WARNING: This is a project-wide configuration file and is overwritten when upgrading to a newer
 *  version of Tinyboard. Please leave this file unchanged, or it will be a lot harder for you to upgrade.
 *  If you would like to make instance-specific changes to your own setup, please use instance-config.php.
 *
 *  This is the default configuration. You can copy values from here and use them in
 *  your instance-config.php
 *
 *  You can also create per-board configuration files. Once a board is created, locate its directory and
 *  create a new file named config.php (eg. b/config.php). Like instance-config.php, you can copy values
 *  from here and use them in your per-board configuration files.
 *
 *  Some directives are commented out. This is either because they are optional and examples, or because
 *  they are "optionally configurable", and given their default values by Tinyboard's code later if unset.
 *
 *  More information: http://tinyboard.org/docs/?p=Config
 *
 *  Tinyboard documentation: http://tinyboard.org/docs/
 *
 */

defined('TINYBOARD') or exit;

/*
 * =======================
 *  General/misc settings
 * =======================
 */

// Global announcement -- the very simple version.
// This used to be wrongly named Vi::$config['blotter'] (still exists as an alias).
// Vi::$config['global_message'] = 'This is an important announcement!';
Vi::$config['blotter'] = &Vi::$config['global_message'];

// Shows some extra information at the bottom of pages. Good for development/debugging.
Vi::$config['debug'] = false;

// write php errors to file (false or 'file name')
Vi::$config['debug_log'] = false;

// For development purposes. Displays (and "dies" on) all errors and warnings. Turn on with the above.
Vi::$config['verbose_errors'] = true;
// EXPLAIN all SQL queries (when in debug mode).
Vi::$config['debug_explain'] = false;

// Directory where temporary files will be created.
Vi::$config['tmp'] = sys_get_temp_dir();

// The HTTP status code to use when redirecting. http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
// Can be either 303 "See Other" or 302 "Found". (303 is more correct but both should work.)
// There is really no reason for you to ever need to change this.
Vi::$config['redirect_http'] = 303;

// A tiny text file in the main directory indicating that the script has been ran and the board(s) have
// been generated. This keeps the script from querying the database and causing strain when not needed.
Vi::$config['has_installed'] = '.installed';

// Use syslog() for logging all error messages and unauthorized login attempts.
Vi::$config['syslog'] = false;

// Use `host` via shell_exec() to lookup hostnames, avoiding query timeouts. May not work on your system.
// Requires safe_mode to be disabled.
Vi::$config['dns_system'] = false;

// Check validity of the reverse DNS of IP addresses. Highly recommended.
Vi::$config['fcrdns'] = true;

// When executing most command-line tools (such as `convert` for ImageMagick image processing), add this
// to the environment path (seperated by :).
Vi::$config['shell_path'] = '/usr/local/bin';

// Allow for users create a own boards
Vi::$config['allow_create_userboards'] = false;

/*
 * ====================
 *  Database settings
 * ====================
 */

// Database driver (http://www.php.net/manual/en/pdo.drivers.php)
// Only MySQL is supported by Tinyboard at the moment, sorry.
Vi::$config['db']['type'] = 'mysql';
// Hostname, IP address or Unix socket (prefixed with ":")
Vi::$config['db']['server'] = 'localhost';
// Example: Unix socket
// Vi::$config['db']['server'] = ':/tmp/mysql.sock';
// Login
Vi::$config['db']['user']     = '';
Vi::$config['db']['password'] = '';
// Tinyboard database
Vi::$config['db']['database'] = '';
// Table prefix (optional)
Vi::$config['db']['prefix'] = '';
// Use a persistent database connection when possible
Vi::$config['db']['persistent'] = false;
// Anything more to add to the DSN string (eg. port=xxx;foo=bar)
Vi::$config['db']['dsn'] = '';
// Connection timeout duration in seconds
Vi::$config['db']['timeout'] = 30;

/*
 * ====================
 *  Cache, lock and queue settings
 * ====================
 */

/*
 * On top of the static file caching system, you can enable the additional caching system which is
 * designed to minimize SQL queries and can significantly increase speed when posting or using the
 * moderator interface. APC is the recommended method of caching.
 *
 * http://tinyboard.org/docs/index.php?p=Config/Cache
 */

Vi::$config['cache']['enabled'] = false;
// Vi::$config['cache']['enabled'] = 'memcached';
// Vi::$config['cache']['enabled'] = 'redis';
// Vi::$config['cache']['enabled'] = 'fs';
// Vi::$config['cache']['enabled'] = 'php';

// Timeout for cached objects such as posts and HTML.
Vi::$config['cache']['timeout'] = 60 * 60 * 48; // 48 hours

// Optional prefix if you're running multiple Tinyboard instances on the same machine.
Vi::$config['cache']['prefix'] = '';

// Memcached servers to use. Read more: http://www.php.net/manual/en/memcached.addservers.php
Vi::$config['cache']['memcached'] = [
	['localhost', 11211],
];

// Redis server to use. Location, port, password, database id.
// Note that Tinyboard may clear the database at times, so you may want to pick a database id just for
// Tinyboard to use.
Vi::$config['cache']['redis'] = [
	'address'	=> 'localhost',
	'port'		=> 6379,
	'password'	=> '',
	'timeout'	=> 3,
	'databases'	=> [
		'default'		=> 0,
		'another_db'	=> 1,
	]
];

// EXPERIMENTAL: Should we cache configs? Warning: this changes board behaviour, i'd say, a lot.
// If you have any lambdas/includes present in your config, you should move them to instance-functions.php
// (this file will be explicitly loaded during cache hit, but not during cache miss).
Vi::$config['cache_config'] = false;

// Define a lock driver.
Vi::$config['lock']['enabled'] = 'fs';

// Define a queue driver.
Vi::$config['queue']['enabled'] = 'fs'; // xD

/*
 * ====================
 *  Cookie settings
 * ====================
 */

// Used for moderation login.
Vi::$config['cookies']['mod'] = 'mod';

// Used for communicating with Javascript; telling it when posts were successful.
Vi::$config['cookies']['js'] = 'serv';

// Cookies path. Defaults to Vi::$config['root']. If Vi::$config['root'] is a URL, you need to set this. Should
// be '/' or '/board/', depending on your installation.
// Vi::$config['cookies']['path'] = '/';
// Where to set the 'path' parameter to Vi::$config['cookies']['path'] when creating cookies. Recommended.
Vi::$config['cookies']['jail'] = true;

// How long should the cookies last (in seconds). Defines how long should moderators should remain logged
// in (0 = browser session).
Vi::$config['cookies']['expire'] = 60 * 60 * 24 * 30 * 6; // ~6 months

// Make this something long and random for security.
Vi::$config['cookies']['salt'] = 'abcdefghijklmnopqrstuvwxyz09123456789!@#$%^&*()';

// Whether or not you can access the mod cookie in JavaScript. Most users should not need to change this.
Vi::$config['cookies']['httponly'] = true;

// Used to salt secure tripcodes ("##trip") and poster IDs (if enabled).
Vi::$config['secure_trip_salt'] = ')(*&^%$#@!98765432190zyxwvutsrqponmlkjihgfedcba';

/*
 * ====================
 *  Flood/spam settings
 * ====================
 */

/*
 * To further prevent spam and abuse, you can use DNS blacklists (DNSBL). A DNSBL is a list of IP
 * addresses published through the Internet Domain Name Service (DNS) either as a zone file that can be
 * used by DNS server software, or as a live DNS zone that can be queried in real-time.
 *
 * Read more: http://tinyboard.org/docs/?p=Config/DNSBL
 */

// Prevents most Tor exit nodes from making posts. Recommended, as a lot of abuse comes from Tor because
// of the strong anonymity associated with it.
Vi::$config['dnsbl'][] = array('exitnodes.tor.dnsbl.sectoor.de', 1);

// http://www.sorbs.net/using.shtml
// Vi::$config['dnsbl'][] = array('dnsbl.sorbs.net', array(2, 3, 4, 5, 6, 7, 8, 9));

// http://www.projecthoneypot.org/httpbl.php
// Vi::$config['dnsbl'][] = array('<your access key>.%.dnsbl.httpbl.org', function($ip) {
//	$octets = explode('.', $ip);
//
//	// days since last activity
//	if ($octets[1] > 14)
//		return false;
//
//	// "threat score" (http://www.projecthoneypot.org/threat_info.php)
//	if ($octets[2] < 5)
//		return false;
//
//	return true;
// }, 'dnsbl.httpbl.org'); // hide our access key

// Skip checking certain IP addresses against blacklists (for troubleshooting or whatever)
Vi::$config['dnsbl_exceptions'][] = '127.0.0.1';

/*
 * Introduction to Tinyboard's spam filter:
 *
 * In simple terms, whenever a posting form on a page is generated (which happens whenever a
 * post is made), Tinyboard will add a random amount of hidden, obscure fields to it to
 * confuse bots and upset hackers. These fields and their respective obscure values are
 * validated upon posting with a 160-bit "hash". That hash can only be used as many times
 * as you specify; otherwise, flooding bots could just keep reusing the same hash.
 * Once a new set of inputs (and the hash) are generated, old hashes for the same thread
 * and board are set to expire. Because you have to reload the page to get the new set
 * of inputs and hash, if they expire too quickly and more than one person is viewing the
 * page at a given time, Tinyboard would return false positives (depending on how long the
 * user sits on the page before posting). If your imageboard is quite fast/popular, set
 * Vi::$config['spam']['hidden_inputs_max_pass'] and Vi::$config['spam']['hidden_inputs_expire'] to
 * something higher to avoid false positives.
 *
 * See also: http://tinyboard.org/docs/?p=Your_request_looks_automated
 *
 */

// Enable antibot, default: disable
Vi::$config['spam']['enable'] = false;

// Number of hidden fields to generate.
Vi::$config['spam']['hidden_inputs_min'] = 4;
Vi::$config['spam']['hidden_inputs_max'] = 12;

// How many times can a "hash" be used to post?
Vi::$config['spam']['hidden_inputs_max_pass'] = 12;

// How soon after regeneration do hashes expire (in seconds)?
Vi::$config['spam']['hidden_inputs_expire'] = 60 * 60 * 3; // three hours

// Whether to use Unicode characters in hidden input names and values.
Vi::$config['spam']['unicode'] = true;

// These are fields used to confuse the bots. Make sure they aren't actually used by Tinyboard, or it won't work.
Vi::$config['spam']['hidden_input_names'] = array(
	'user',
	'username',
	'login',
	'search',
	'q',
	'url',
	'firstname',
	'lastname',
	'text',
	'message',
);

// Always update this when adding new valid fields to the post form, or EVERYTHING WILL BE DETECTED AS SPAM!
Vi::$config['spam']['valid_inputs'] = array(
	'hash',
	'board',
	'thread',
	'mod',
	'name',
	'email',
	'subject',
	'post',
	'body',
	'password',
	'sticky',
	'lock',
	'raw',
	'embed',
	'recaptcha_challenge_field',
	'recaptcha_response_field',
	// 'captcha_cookie',
	'captcha_text',
	'spoiler',
	'page',
	'file_url',
	'json_response',
	'user_flag',
	'no_country',
	'tag',
	// 'nojs_user'
);

/* Uses are you a human to stop automated requests to make boards disabled by default
 * if you wish to use 'are you a human' to block automated board creation requests

 * to use AYAH you must enter your 'AYAH_PUBLISHER_KEY' and your 'AYAH_SCORING_KEY' in
 * the configuration file for AYAH. The config file for AYAH
 * is located in the following directory:'/inc/lib/ayah/ayah_config.php'
 */
Vi::$config['ayah_enabled'] = false;

// Enable reCaptcha to make spam even harder. Rarely necessary.
Vi::$config['recaptcha'] = false;
// Enable reCaptcha on create.php to prevent automated requests.
// Vi::$config['cbRecaptcha'] = false;

// Public and private key pair from https://www.google.com/recaptcha/admin/create
// Vi::$config['recaptcha_public'] = '6LcXTcUSAAAAAKBxyFWIt2SO8jwx4W7wcSMRoN3f';
// Vi::$config['recaptcha_private'] = '6LcXTcUSAAAAAOGVbVdhmEM1_SyRF4xTKe8jbzf_';

Vi::$config['captcha'] = array();

// Enable custom captcha provider
Vi::$config['captcha']['enabled'] = false;

// Custom CAPTCHA provider general settings

// Captcha image size
Vi::$config['captcha']['width']  = 200;
Vi::$config['captcha']['height'] = 80;

// Captcha expiration:
Vi::$config['captcha']['expires_in'] = 120; // 120 seconds

// Captcha length:
Vi::$config['captcha']['length'] = 6;

/*
 * Custom captcha provider path.
 * Specify http://yourimageboard.com/Vi::$config['root']/captcha.php for the default provider or write your own
 */
Vi::$config['captcha']['provider_get'] = '/captcha.php';

// Custom captcha extra field (eg. charset)
Vi::$config['captcha']['extra'] = 'abcdefghijklmnopqrstuvwxyz';

Vi::$config['tor_posting'] = false;
Vi::$config['tor_allow_delete'] = false;
Vi::$config['tor_image_posting'] = false;
Vi::$config['tor_serviceip'] = '192.168.0.2';

Vi::$config['tor'] = [];
Vi::$config['tor']['force_disable'] = false; // Принудительно запретить постинг
Vi::$config['tor']['allow_posting'] = false;
Vi::$config['tor']['allow_image_posting'] = false;
Vi::$config['tor']['cookie_name'] = 'amator';
Vi::$config['tor']['cookie_time'] = 60 * 60 * 6;
Vi::$config['tor']['max_posts'] = 30;
Vi::$config['tor']['max_fails'] = 3;
Vi::$config['tor']['need_capchas'] = 5;

/*
 * Custom filters detect certain posts and reject/ban accordingly. They are made up of a condition and an
 * action (for when ALL conditions are met). As every single post has to be put through each filter,
 * having hundreds probably isn't ideal as it could slow things down.
 *
 * By default, the custom filters array is populated with basic flood prevention conditions. This
 * includes forcing users to wait at least 5 seconds between posts. To disable (or amend) these flood
 * prevention settings, you will need to empty the Vi::$config['filters'] array first. You can do so by
 * adding "Vi::$config['filters'] = array();" to inc/instance-config.php. Basic flood prevention used to be
 * controlled solely by config variables such as Vi::$config['flood_time'] and Vi::$config['flood_time_ip'], and
 * it still is, as long as you leave the relevant Vi::$config['filters'] intact. These old config variables
 * still exist for backwards-compatability and general convenience.
 *
 * Read more: http://tinyboard.org/docs/index.php?p=Config/Filters
 */

// Minimum time between between each post by the same IP address.
Vi::$config['flood_time'] = 20;
// Minimum time between between each post with the exact same content AND same IP address.
Vi::$config['flood_time_ip'] = 120;
// Same as above but by a different IP address. (Same content, not necessarily same IP address.)
Vi::$config['flood_time_same'] = 30;

// Minimum time between posts by the same IP address (all boards).
Vi::$config['filters'][] = array(
	'condition' => array(
		'flood-match' => array('ip'), // Only match IP address
		'flood-time'  => &Vi::$config['flood_time'],
	),
	'action'    => 'reject',
	'message'   => &Vi::$config['error']['flood'],
);

// Minimum time between posts by the same IP address with the same text.
Vi::$config['filters'][] = array(
	'condition' => array(
		'flood-match' => array('ip', 'body'), // Match IP address and post body
		'flood-time'  => &Vi::$config['flood_time_ip'],
		'!body'       => '/^$/', // Post body is NOT empty
	),
	'action'    => 'reject',
	'message'   => &Vi::$config['error']['flood'],
);

// Minimum time between posts with the same text. (Same content, but not always the same IP address.)
/*Vi::$config['filters'][] = array(
'condition' => array(
'flood-match' => array('body'), // Match only post body
'flood-time' => &Vi::$config['flood_time_same'],
'!body' => '/^$/', // Post body is NOT empty
),
'action' => 'reject',
'message' => &Vi::$config['error']['flood']
);*/

// Example: Minimum time between posts with the same file hash.
// Vi::$config['filters'][] = array(
// 	'condition' => array(
// 		'flood-match' => array('file'), // Match file hash
// 		'flood-time' => 60 * 2 // 2 minutes minimum
// 	),
// 	'action' => 'reject',
// 	'message' => &Vi::$config['error']['flood']
// );

// Example: Use the "flood-count" condition to only match if the user has made at least two posts with
// the same content and IP address in the past 2 minutes.
// Vi::$config['filters'][] = array(
// 	'condition' => array(
// 		'flood-match' => array('ip', 'body'), // Match IP address and post body
// 		'flood-time' => 60 * 2, // 2 minutes
// 		'flood-count' => 2 // At least two recent posts
// 	),
// 	'!body' => '/^$/',
// 	'action' => 'reject',
// 	'message' => &Vi::$config['error']['flood']
// );

// Example: Blocking an imaginary known spammer, who keeps posting a reply with the name "surgeon",
// ending his posts with "regards, the surgeon" or similar.
// Vi::$config['filters'][] = array(
// 	'condition' => array(
// 		'name' => '/^surgeon$/',
// 		'body' => '/regards,\s+(the )?surgeon$/i',
// 		'OP' => false
// 	),
// 	'action' => 'reject',
// 	'message' => 'Go away, spammer.'
// );

// Example: Same as above, but issuing a 3-hour ban instead of just reject the post and
// add an IP note with the message body
// Vi::$config['filters'][] = array(
// 	'condition' => array(
// 		'name' => '/^surgeon$/',
// 		'body' => '/regards,\s+(the )?surgeon$/i',
// 		'OP' => false
// 	),
// 	'action' => 'ban',
//	'add_note' => true,
// 	'expires' => 60 * 60 * 3, // 3 hours
// 	'reason' => 'Go away, spammer.'
// );

// Example: PHP 5.3+ (anonymous functions)
// There is also a "custom" condition, making the possibilities of this feature pretty much endless.
// This is a bad example, because there is already a "name" condition built-in.
// Vi::$config['filters'][] = array(
// 	'condition' => array(
// 		'body' => '/h$/i',
// 		'OP' => false,
// 		'custom' => function($post) {
// 			if($post['name'] == 'Anonymous')
// 				return true;
// 			else
// 				return false;
// 		}
// 	),
// 	'action' => 'reject'
// );

// Filter flood prevention conditions ("flood-match") depend on a table which contains a cache of recent
// posts across all boards. This table is automatically purged of older posts, determining the maximum
// "age" by looking at each filter. However, when determining the maximum age, Tinyboard does not look
// outside the current board. This means that if you have a special flood condition for a specific board
// (contained in a board configuration file) which has a flood-time greater than any of those in the
// global configuration, you need to set the following variable to the maximum flood-time condition value.
// Vi::$config['flood_cache'] = 60 * 60 * 24; // 24 hours

/*
 * ====================
 *  Post settings
 * ====================
 */

//New thread captcha
//Require solving a captcha to post a thread.
//Default off.
Vi::$config['new_thread_capt'] = false;

// Do you need a body for your reply posts?
Vi::$config['force_body'] = false;
// Do you need a user or country flag for your posts?
Vi::$config['force_flag'] = false;
// Do you need a body for new threads?
Vi::$config['force_body_op'] = true;
// Require an image for threads?
Vi::$config['force_image_op'] = true;
// Require a subject for threads?
Vi::$config['force_subject_op'] = false;

// Strip superfluous new lines at the end of a post.
Vi::$config['strip_superfluous_returns'] = true;
// Strip combining characters from Unicode strings (eg. "Zalgo").
Vi::$config['strip_combining_chars'] = true;

// Maximum post body length.
Vi::$config['max_body'] = 1800;
// Maximum number of newlines. (0 for unlimited)
Vi::$config['max_newlines'] = 0;
// Maximum number of post body lines to show on the index page.
Vi::$config['body_truncate'] = 15;
// Maximum number of characters to show on the index page.
Vi::$config['body_truncate_char'] = 2500;

// Typically spambots try to post many links. Refuse a post with X links?
Vi::$config['max_links'] = 20;
// Maximum number of cites per post (prevents abuse, as more citations mean more database queries).
Vi::$config['max_cites'] = 45;
// Maximum number of cross-board links/citations per post.
Vi::$config['max_cross'] = Vi::$config['max_cites'];

// Track post citations (>>XX). Rebuilds posts after a cited post is deleted, removing broken links.
// Puts a little more load on the database.
Vi::$config['track_cites'] = true;

// Maximum filename length (will be truncated).
Vi::$config['max_filename_len'] = 255;
// Maximum filename length to display (the rest can be viewed upon mouseover).
Vi::$config['max_filename_display'] = 30;

// Allow users to delete their own posts?
Vi::$config['allow_delete'] = true;
// How long after posting should you have to wait before being able to delete that post? (In seconds.)
Vi::$config['delete_time'] = 10;
Vi::$config['delete_time_thread'] = 60 * 15;
// Reply limit (stops bumping thread when this is reached).
Vi::$config['reply_limit'] = 250;

// Image hard limit (stops allowing new image replies when this is reached if not zero).
Vi::$config['image_hard_limit'] = 0;
// Reply hard limit (stops allowing new replies when this is reached if not zero).
Vi::$config['reply_hard_limit'] = 0;

Vi::$config['robot_enable'] = false;
// Strip repeating characters when making hashes.
Vi::$config['robot_strip_repeating'] = true;
// Enabled mutes? Tinyboard uses ROBOT9000's original 2^x implementation where x is the number of times
// you have been muted in the past.
Vi::$config['robot_mute'] = true;
// How long before Tinyboard forgets about a mute?
Vi::$config['robot_mute_hour'] = 336; // 2 weeks
// If you want to alter the algorithm a bit. Default value is 2.
Vi::$config['robot_mute_multiplier']  = 2; // (n^x where x is the number of previous mutes)

// Automatically convert things like "..." to Unicode characters ("…").
Vi::$config['auto_unicode'] = true;
// Whether to turn URLs into functional links.
Vi::$config['markup_urls'] = true;

// Optional URL prefix for links (eg. "http://anonym.to/?").
Vi::$config['link_prefix'] = '';
Vi::$config['url_ads']     = &Vi::$config['link_prefix']; // leave alias

// Allow "uploading" images via URL as well. Users can enter the URL of the image and then Tinyboard will
// download it. Not usually recommended.
Vi::$config['allow_upload_by_url'] = false;
// The timeout for the above, in seconds.
Vi::$config['upload_by_url_timeout'] = 15;

// A wordfilter (sometimes referred to as just a "filter" or "censor") automatically scans users’ posts
// as they are submitted and changes or censors particular words or phrases.

// For a normal string replacement:
// Vi::$config['wordfilters'][] = array('cat', 'dog');
// Advanced raplcement (regular expressions):
// Vi::$config['wordfilters'][] = array('/ca[rt]/', 'dog', true); // 'true' means it's a regular expression

// Always act as if the user had typed "noko" into the email field.
Vi::$config['always_noko'] = false;

// Example: Custom tripcodes. The below example makes a tripcode of "#test123" evaluate to "!HelloWorld".
// Vi::$config['custom_tripcode']['#test123'] = '!HelloWorld';
// Example: Custom secure tripcode.
// Vi::$config['custom_tripcode']['##securetrip'] = '!!somethingelse';

// Allow users to mark their image as a "spoiler" when posting. The thumbnail will be replaced with a
// static spoiler image instead (see Vi::$config['spoiler_image']).
Vi::$config['spoiler_images'] = false;

// With the following, you can disable certain superfluous fields or enable "forced anonymous".

// When true, all names will be set to Vi::$config['anonymous'].
Vi::$config['field_disable_name'] = false;
// When true, there will be no email field.
Vi::$config['field_disable_email'] = false;
// When true, there will be no subject field.
Vi::$config['field_disable_subject'] = false;
// When true, there will be no subject field for replies.
Vi::$config['field_disable_reply_subject'] = false;
// When true, a blank password will be used for files (not usable for deletion).
Vi::$config['field_disable_password'] = false;

// When true, users are instead presented a selectbox for email. Contains, blank, noko and sage.
Vi::$config['field_email_selectbox'] = false;

// Prevent users from uploading files.
Vi::$config['disable_images'] = false;

// When true, the sage won't be displayed
Vi::$config['hide_sage'] = false;

// Attach country flags to posts.
Vi::$config['country_flags'] = false;

// Allow the user to decide whether or not he wants to display his country
Vi::$config['allow_no_country'] = false;

// Load all country flags from one file
Vi::$config['country_flags_condensed']     = true;
Vi::$config['country_flags_condensed_css'] = 'static/flags/flags.css';

// Allow the user choose a /pol/-like user_flag that will be shown in the post. For the user flags, please be aware
// that you will have to disable BOTH country_flags and contry_flags_condensed optimization (at least on a board
// where they are enabled).
Vi::$config['user_flag'] = false;

// List of user_flag the user can choose. Flags must be placed in the directory set by Vi::$config['uri_flags']
Vi::$config['user_flags'] = array();
/* example:
Vi::$config['user_flags'] = array (
'nz' => 'Nazi',
'cm' => 'Communist',
'eu' => 'Europe'
);
 */

// Allow dice rolling: an message field of the form "##XdY##" will result in X Y-sided dice rolled and summed, with the result displayed at post body.
Vi::$config['allow_roll']             = false;
Vi::$config['max_dice_rolls_on_post'] = 10;

Vi::$config['board_locked'] = false;

/*
 * ====================
 *  Ban settings
 * ====================
 */

// Require users to see the ban page at least once for a ban even if it has since expired.
Vi::$config['require_ban_view'] = true;

// Show the post the user was banned for on the "You are banned" page.
Vi::$config['ban_show_post'] = false;

// Optional HTML to append to "You are banned" pages. For example, you could include instructions and/or
// a link to an email address or IRC chat room to appeal the ban.
Vi::$config['ban_page_extra'] = '';

// Allow users to appeal bans through Tinyboard.
Vi::$config['ban_appeals'] = false;

// Do not allow users to appeal bans that are shorter than this length (in seconds).
Vi::$config['ban_appeals_min_length'] = 60 * 60 * 6; // 6 hours

// How many ban appeals can be made for a single ban?
Vi::$config['ban_appeals_max'] = 1;

// Blacklisted board names. Default values to protect existing folders in the core codebase.
Vi::$config['banned_boards'] = array(
	'.git',
	'inc',
	'js',
	'static',
	'stylesheets',
	'templates',
	'tools',
);

// Show moderator name on ban page.
Vi::$config['show_modname'] = false;

/*
 * ====================
 *  Markup settings
 * ====================
 */

// JIS ASCII art. This *must* be the first markup or it won't work.
Vi::$config['markup'][] = array(
	"/\[aa\](.+?)\[\/aa\]/ms",
	function ($matches) {
		$markupchars = array('_', '\'', '~', '*', '=', '-');
		$replacement = $markupchars;
		array_walk($replacement, function (&$v) {
			$v = "&#" . ord($v) . ";";
		});

		// These are hacky fixes for ###board-tags###, ellipses and >quotes.
		$markupchars[] = '###';
		$replacement[] = '&#35;&#35;&#35;';
		$markupchars[] = '&gt;';
		$replacement[] = '&#62;';
		$markupchars[] = '...';
		$replacement[] = '..&#46;';

		return '<span class="aa">' . str_replace($markupchars, $replacement, $matches[2]) . '</span>';
	}
);

// "Wiki" markup syntax (Vi::$config['wiki_markup'] in pervious versions):
Vi::$config['markup'][] = array("/'''(.+?)'''/s", "<strong>\$1</strong>");
Vi::$config['markup'][] = array("/''(.+?)''/s", "<em>\$1</em>");
Vi::$config['markup'][] = array("/\*\*(.+?)\*\*/s", "<span class=\"spoiler\">\$1</span>");
Vi::$config['markup'][] = array("/^[ |\t]*==(.+?)==[ |\t]*$/m", "<span class=\"heading\">\$1</span>");

Vi::$config['markup'][] = array("/\[b\](.+?)\[\/b\]/s", "<strong>\$1</strong>");
Vi::$config['markup'][] = array("/\[i\](.+?)\[\/i\]/s", "<i>\$1</i>");
Vi::$config['markup'][] = array("/\[u\](.+?)\[\/u\]/s", "<u>\$1</u>");
Vi::$config['markup'][] = array("/\[s\](.+?)\[\/s\]/s", "<s>\$1</s>");

// Highlight PHP code wrapped in <code> tags (PHP 5.3+)
// Vi::$config['markup'][] = array(
// 	'/^&lt;code&gt;(.+)&lt;\/code&gt;/ms',
// 	function($matches) {
// 		return highlight_string(html_entity_decode($matches[1]), true);
// 	}
// );

// Repair markup with HTML Tidy. This may be slower, but it solves nesting mistakes. Tinyboad, at the
// time of writing this, can not prevent out-of-order markup tags (eg. "**''test**'') without help from
// HTML Tidy.
Vi::$config['markup_repair_tidy'] = false;

// Always regenerate markup. This isn't recommended and should only be used for debugging; by default,
// Tinyboard only parses post markup when it needs to, and keeps post-markup HTML in the database. This
// will significantly impact performance when enabled.
Vi::$config['always_regenerate_markup'] = false;

/*
 * ====================
 *  Image settings
 * ====================
 */
// Maximum number of images allowed. Increasing this number enabled multi image.
// If you make it more than 1, make sure to enable the below script for the post form to change.
// Vi::$config['additional_javascript'][] = 'js/multi_image.js';
Vi::$config['max_images'] = 1;

// Method to use for determing the max filesize.
// "split" means that your max filesize is split between the images. For example, if your max filesize
// is 2MB, the filesizes of all files must add up to 2MB for it to work.
// "each" means that each file can be 2MB, so if your max_images is 3, each post could contain 6MB of
// images. "split" is recommended.
Vi::$config['multiimage_method'] = 'split';

Vi::$config['jpeg_force_progressive'] = false;

// For resizing, maximum thumbnail dimensions.
Vi::$config['thumb_width']  = 255;
Vi::$config['thumb_height'] = 255;
// Maximum thumbnail dimensions for thread (OP) images.
Vi::$config['thumb_op_width']  = 255;
Vi::$config['thumb_op_height'] = 255;

// Thumbnail extension ("png" recommended). Leave this empty if you want the extension to be inherited
// from the uploaded file.
Vi::$config['thumb_ext'] = 'png';

// Maximum amount of animated GIF frames to resize (more frames can mean more processing power). A value
// of "1" means thumbnails will not be animated. Requires Vi::$config['thumb_ext'] to be 'gif' (or blank) and
//  Vi::$config['thumb_method'] to be 'imagick', 'convert', or 'convert+gifsicle'. This value is not
// respected by 'convert'; will just resize all frames if this is > 1.
Vi::$config['thumb_keep_animation_frames'] = 1;

Vi::$config['gif_preview_animate'] = false;

/*
 * Thumbnailing method:
 *
 *   'gd'		   PHP GD (default). Only handles the most basic image formats (GIF, JPEG, PNG).
 *				  GD is a prerequisite for Tinyboard no matter what method you choose.
 *
 *   'imagick'	  PHP's ImageMagick bindings. Fast and efficient, supporting many image formats.
 *				  A few minor bugs. http://pecl.php.net/package/imagick
 *
 *   'convert'	  The command line version of ImageMagick (`convert`). Fixes most of the bugs in
 *				  PHP Imagick. `convert` produces the best still thumbnails and is highly recommended.
 *
 *   'gm'		   GraphicsMagick (`gm`) is a fork of ImageMagick with many improvements. It is more
 *				  efficient and gets thumbnailing done using fewer resources.
 *
 *   'convert+gifscale'
 *	OR  'gm+gifsicle'  Same as above, with the exception of using `gifsicle` (command line application)
 *					   instead of `convert` for resizing GIFs. It's faster and resulting animated
 *					   thumbnails have less artifacts than if resized with ImageMagick.
 */
Vi::$config['thumb_method'] = 'gd';
// Vi::$config['thumb_method'] = 'convert';

// Command-line options passed to ImageMagick when using `convert` for thumbnailing. Don't touch the
// placement of "%s" and "%d".
Vi::$config['convert_args'] = '-size %dx%d %s -thumbnail %dx%d -auto-orient +profile "*" %s';

// Strip EXIF metadata from JPEG files.
Vi::$config['strip_exif'] = false;
// Use the command-line `exiftool` tool to strip EXIF metadata without decompressing/recompressing JPEGs.
// Ignored when Vi::$config['redraw_image'] is true. This is also used to adjust the Orientation tag when
//  Vi::$config['strip_exif'] is false and Vi::$config['convert_manual_orient'] is true.
Vi::$config['use_exiftool'] = false;

// Redraw the image to strip any excess data (commonly ZIP archives) WARNING: This might strip the
// animation of GIFs, depending on the chosen thumbnailing method. It also requires recompressing
// the image, so more processing power is required.
Vi::$config['redraw_image'] = false;

// Automatically correct the orientation of JPEG files using -auto-orient in `convert`. This only works
// when `convert` or `gm` is selected for thumbnailing. Again, requires more processing power because
// this basically does the same thing as Vi::$config['redraw_image']. (If Vi::$config['redraw_image'] is enabled,
// this value doesn't matter as Vi::$config['redraw_image'] attempts to correct orientation too.)
Vi::$config['convert_auto_orient'] = false;

// Is your version of ImageMagick or GraphicsMagick old? Older versions may not include the -auto-orient
// switch. This is a manual replacement for that switch. This is independent from the above switch;
// -auto-orrient is applied when thumbnailing too.
Vi::$config['convert_manual_orient'] = false;

// Regular expression to check for an XSS exploit with IE 6 and 7. To disable, set to false.
// Details: https://github.com/savetheinternet/Tinyboard/issues/20
Vi::$config['ie_mime_type_detection'] = '/<(?:body|head|html|img|plaintext|pre|script|table|title|a href|channel|scriptlet)/i';

// Config panel, fileboard: allowed upload extensions
Vi::$config['fileboard_allowed_types'] = array('zip', '7z', 'tar', 'gz', 'bz2', 'xz', 'swf', 'txt', 'pdf', 'torrent');

// Config panel, imgboard: allowed upload extensions
Vi::$config['imgboard_allowed_types'] = array('mp4', 'webm', 'mp3', 'ogg', 'pdf', 'swf');

// Allowed image file extensions.
Vi::$config['allowed_ext'][] = 'jpg';
Vi::$config['allowed_ext'][] = 'jpeg';
Vi::$config['allowed_ext'][] = 'gif';
Vi::$config['allowed_ext'][] = 'png';
// Vi::$config['allowed_ext'][] = 'svg';

// Allowed extensions for OP. Inherits from the above setting if set to false. Otherwise, it overrides both allowed_ext and
// allowed_ext_files (filetypes for downloadable files should be set in allowed_ext_files as well). This setting is useful
// for creating fileboards.
Vi::$config['allowed_ext_op'] = false;

// Allowed additional file extensions (not images; downloadable files).
// Vi::$config['allowed_ext_files'][] = 'txt';
// Vi::$config['allowed_ext_files'][] = 'zip';

// An alternative function for generating image filenames, instead of the default UNIX timestamp.
// Vi::$config['filename_func'] = function($post) {
//	  return sprintf("%s", time() . substr(microtime(), 2, 3));
// };

// Thumbnail to use for the non-image file uploads.
Vi::$config['file_icons']['default'] = 'file.png';
Vi::$config['file_icons']['zip']     = 'zip.png';
Vi::$config['file_icons']['webm']    = 'video.png';
Vi::$config['file_icons']['mp4']     = 'video.png';
// Example: Custom thumbnail for certain file extension.
// Vi::$config['file_icons']['extension'] = 'some_file.png';

// Location of above images.
Vi::$config['file_thumb'] = 'static/%s';
// Location of thumbnail to use for spoiler images.
Vi::$config['spoiler_image'] = 'static/spoiler.png';
// Location of thumbnail to use for deleted images.
Vi::$config['image_deleted'] = 'static/deleted.png';
// Location of placeholder image for fileless posts in catalog.
Vi::$config['no_file_image'] = 'static/no-file.png';

// When a thumbnailed image is going to be the same (in dimension), just copy the entire file and use
// that as a thumbnail instead of resizing/redrawing.
Vi::$config['minimum_copy_resize'] = false;

// Maximum image upload size in bytes.
Vi::$config['max_filesize'] = 10 * 1024 * 1024; // 10MB
// Maximum image dimensions.
Vi::$config['max_width']  = 10000;
Vi::$config['max_height'] = Vi::$config['max_width'];
// Reject duplicate image uploads.
Vi::$config['image_reject_repost'] = true;
// Reject duplicate image uploads within the same thread. Doesn't change anything if
//  Vi::$config['image_reject_repost'] is true.
Vi::$config['image_reject_repost_in_thread'] = false;

// Display the aspect ratio of uploaded files.
Vi::$config['show_ratio'] = false;
// Display the file's original filename.
Vi::$config['show_filename'] = true;

// WebM Settings
Vi::$config['webm']['use_ffmpeg']   = false;
Vi::$config['webm']['allow_audio']  = false;
Vi::$config['webm']['max_length']   = 120;
Vi::$config['webm']['ffmpeg_path']  = 'ffmpeg';
Vi::$config['webm']['ffprobe_path'] = 'ffprobe';

// WebM preview frame sec, 'middle' - preview from middle
Vi::$config['webm']['preview_frame_sec'] = '0';

// Display image identification links for ImgOps, regex.info/exif, Google Images and iqdb.
Vi::$config['image_identification'] = false;
// Which of the identification links to display. Only works if Vi::$config['image_identification'] is true.
Vi::$config['image_identification_imgops'] = true;
Vi::$config['image_identification_exif']   = true;
Vi::$config['image_identification_google'] = true;
// Anime/manga search engine.
Vi::$config['image_identification_iqdb'] = false;

// Set this to true if you're using a BSD
Vi::$config['bsd_md5'] = false;

// Set this to true if you're using Linux and you can execute `md5sum` binary.
Vi::$config['gnu_md5'] = false;

// Use Tesseract OCR to retrieve text from images, so you can use it as a spamfilter.
Vi::$config['tesseract_ocr'] = false;

// Tesseract parameters
Vi::$config['tesseract_params'] = '';

// Tesseract preprocess command
Vi::$config['tesseract_preprocess_command'] = 'convert -monochrome %s -';

// Number of posts in a "View Last X Posts" page
Vi::$config['noko50_count'] = 50;
// Number of posts a thread needs before it gets a "View Last X Posts" page.
// Set to an arbitrarily large value to disable.
Vi::$config['noko50_min'] = 100;
/*
 * ====================
 *  Board settings
 * ====================
 */

// Maximum amount of threads to display per page.
Vi::$config['threads_per_page'] = 10;
// Maximum number of pages. Content past the last page is automatically purged.
Vi::$config['max_pages'] = 10;
// Replies to show per thread on the board index page.
Vi::$config['threads_preview'] = 5;
// Same as above, but for stickied threads.
Vi::$config['threads_preview_sticky'] = 1;

// How to display the URI of boards. Usually '/%s/' (/b/, /mu/, etc). This doesn't change the URL. Find
//  Vi::$config['board_path'] if you wish to change the URL.
Vi::$config['board_abbreviation'] = '/%s/';

// The default name (ie. Anonymous). Can be an array - in that case it's picked randomly from the array.
// Example: Vi::$config['anonymous'] = array('Bernd', 'Senpai', 'Jonne', 'ChanPro');
Vi::$config['anonymous'] = 'Anonymous';

// Number of reports you can create at once.
Vi::$config['report_limit'] = 3;

// Allow unfiltered HTML in board subtitle. This is useful for placing icons and links.
Vi::$config['allow_subtitle_html'] = false;

/*
 * ====================
 *  Display settings
 * ====================
 */

// Timezone to use for displaying dates/tiems.
Vi::$config['timezone'] = 'America/Los_Angeles';
// The format string passed to strftime() for displaying dates.
// http://www.php.net/manual/en/function.strftime.php
Vi::$config['post_date'] = '%m/%d/%y (%a) %H:%M:%S';
// Same as above, but used for "you are banned' pages.
Vi::$config['ban_date'] = '%A %e %B, %Y';

// The names on the post buttons. (On most imageboards, these are both just "Post").
Vi::$config['button_newtopic'] = _('New Topic');
Vi::$config['button_reply']    = _('New Reply');

// Assign each poster in a thread a unique ID, shown by "ID: xxxxx" before the post number.
Vi::$config['poster_ids'] = false;
// Number of characters in the poster ID (maximum is 40).
Vi::$config['poster_id_length'] = 5;

// Show thread subject in page title.
Vi::$config['thread_subject_in_title'] = false;

// Additional lines added to the footer of all pages.
// Vi::$config['footer'][] = _('All trademarks, copyrights, comments, and images on this page are owned by and are the responsibility of their respective parties.');

// Characters used to generate a random password (with Javascript).
Vi::$config['genpassword_chars'] = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+';

// Optional banner image at the top of every page.
// Vi::$config['url_banner'] = '/banner.php';
// Banner dimensions are also optional. As the banner loads after the rest of the page, everything may be
// shifted down a few pixels when it does. Making the banner a fixed size will prevent this.
// Vi::$config['banner_width'] = 300;
// Vi::$config['banner_height'] = 100;

// banner update time (sec)
Vi::$config['banner_timeout'] = 60;

// Custom stylesheets available for the user to choose. See the "stylesheets/" folder for a list of
// available stylesheets (or create your own).
Vi::$config['stylesheets']['Yotsuba B'] = ''; // Default; there is no additional/custom stylesheet for this.
Vi::$config['stylesheets']['Yotsuba']   = 'yotsuba.css';
// Vi::$config['stylesheets']['Futaba']    = 'futaba.css';
// Vi::$config['stylesheets']['Dark']      = 'dark.css';
Vi::$config['stylesheets']['Tomorrow'] = 'tomorrow.css';

// The prefix for each stylesheet URI. Defaults to Vi::$config['root']/stylesheets/
// Vi::$config['uri_stylesheets'] = 'http://static.example.org/stylesheets/';

// The default stylesheet to use.
Vi::$config['default_stylesheet'] = array('Yotsuba B', Vi::$config['stylesheets']['Yotsuba B']);

// Make stylesheet selections board-specific.
Vi::$config['stylesheets_board'] = false;

// Use Font-Awesome for displaying lock and pin icons, instead of the images in static/.
// http://fortawesome.github.io/Font-Awesome/icon/pushpin/
// http://fortawesome.github.io/Font-Awesome/icon/lock/
Vi::$config['font_awesome']     = true;
Vi::$config['font_awesome_css'] = 'stylesheets/font-awesome/css/font-awesome.min.css';

Vi::$config['categories'] = [
	'Misc' => ['b'],
];

/*
 * For lack of a better name, “boardlinks” are those sets of navigational links that appear at the top
 * and bottom of board pages. They can be a list of links to boards and/or other pages such as status
 * blogs and social network profiles/pages.
 *
 * "Groups" in the boardlinks are marked with square brackets. Tinyboard allows for infinite recursion
 * with groups. Each array() in Vi::$config['boards'] represents a new square bracket group.
 */

// Vi::$config['boards'] = array(
// 	array('a', 'b'),
// 	array('c', 'd', 'e', 'f', 'g'),
// 	array('h', 'i', 'j'),
// 	array('k', array('l', 'm')),
// 	array('status' => 'http://status.example.org/')
// );

// Whether or not to put brackets around the whole board list
Vi::$config['boardlist_wrap_bracket'] = false;

// Show page navigation links at the top as well.
Vi::$config['page_nav_top'] = false;

// Show "Catalog" link in page navigation. Use with the Catalog theme.
Vi::$config['catalog_link'] = 'catalog.html';

// Board categories. Only used in the "Categories" theme.
// Vi::$config['categories'] = array(
// 	'Group Name' => array('a', 'b', 'c'),
// 	'Another Group' => array('d')
// );
// Optional for the Categories theme. This is an array of name => (title, url) groups for categories
// with non-board links.
// Vi::$config['custom_categories'] = array(
// 	'Links' => array(
// 		'Tinyboard' => 'http://tinyboard.org',
// 		'Donate' => 'donate.html'
// 	)
// );

// Automatically remove unnecessary whitespace when compiling HTML files from templates.
Vi::$config['minify_html'] = false;

/*
 * Advertisement HTML to appear at the top and bottom of board pages.
 */

// Vi::$config['ad'] = array(
//	'top' => '',
//	'bottom' => '',
// );

// Display flags (when available). This config option has no effect unless poster flags are enabled (see
// Vi::$config['country_flags']). Disable this if you want all previously-assigned flags to be hidden.
Vi::$config['display_flags'] = true;

// Location of post flags/icons (where "%s" is the flag name). Defaults to static/flags/%s.png.
// Vi::$config['uri_flags'] = 'http://static.example.org/flags/%s.png';

// Width and height (and more?) of post flags. Can be overridden with the Tinyboard post modifier:
// <tinyboard flag style>.
// Vi::$config['flag_style'] = 'width:16px;height:11px;';
Vi::$config['flag_style'] = '';

/*
 * ====================
 *  Javascript
 * ====================
 */

// Additional Javascript files to include on board index and thread pages. See js/ for available scripts.
// Vi::$config['additional_javascript'][] = 'js/inline-expanding.js';
// Vi::$config['additional_javascript'][] = 'js/local-time.js';

// Some scripts require jQuery. Check the comments in script files to see what's needed. When enabling
// jQuery, you should first empty the array so that "js/query.min.js" can be the first, and then re-add
// "js/inline-expanding.js" or else the inline-expanding script might not interact properly with other
// scripts.
// Vi::$config['additional_javascript'] = array();
// Vi::$config['additional_javascript'][] = 'js/jquery.min.js';
// Vi::$config['additional_javascript'][] = 'js/inline-expanding.js';
// Vi::$config['additional_javascript'][] = 'js/auto-reload.js';
// Vi::$config['additional_javascript'][] = 'js/post-hover.js';
// Vi::$config['additional_javascript'][] = 'js/style-select.js';

// Where these script files are located on the web. Defaults to Vi::$config['root'].
// Vi::$config['additional_javascript_url'] = 'http://static.example.org/tinyboard-javascript-stuff/';

// Compile all additional scripts into one file (Vi::$config['file_script']) instead of including them seperately.
Vi::$config['additional_javascript_compile'] = false;

// Minify Javascript using http://code.google.com/p/minify/.
Vi::$config['minify_js'] = false;

// Dispatch thumbnail loading and image configuration with JavaScript. It will need a certain javascript
// code to work.
Vi::$config['javascript_image_dispatch'] = false;

// Allow [code][/code] tags
Vi::$config['code_tags'] = false;

/*
 * ====================
 *  Video embedding
 * ====================
 */

// Enable embedding (see below).
Vi::$config['enable_embedding'] = false;

// Youtube.js embed HTML code
Vi::$config['youtube_js_html'] = '<div class="video-container" data-video="$1" data-params="&$2&$3">' .
	'<span class="unimportant yt-help">YouTube embed. Click thumbnail to play.</span><br>' .
	'<a href="$0" target="_blank" class="file">' .
	'<img style="width:255px" src="//img.youtube.com/vi/$1/0.jpg" class="post-image"/>' .
	'</a></div>';

// Custom embedding (YouTube, vimeo, etc.)
// It's very important that you match the entire input (with ^ and $) or things will not work correctly.
Vi::$config['embedding'] = array(
	array(
		'/^https?:\/\/(?:\w+\.)?(?:youtube\.com\/watch\/?\?|youtu\.be\/)(?:(?:&?v=)?([a-zA-Z0-9\-_]{10,11}))$/i',
		Vi::$config['youtube_js_html'],
	),
);

// Embedding width and height.
Vi::$config['embed_width']  = 300;
Vi::$config['embed_height'] = 246;

/*
 * ====================
 *  Error messages
 * ====================
 */

// Error messages
Vi::$config['error']['bot']                = _('You look like a bot.');
Vi::$config['error']['referer']            = _('Your browser sent an invalid or no HTTP referer.');
Vi::$config['error']['toolong']            = _('The %s field was too long.');
Vi::$config['error']['toolong_body']       = _('The body was too long.');
Vi::$config['error']['tooshort_body']      = _('The body was too short or empty.');
Vi::$config['error']['noimage']            = _('You must upload an image.');
Vi::$config['error']['toomanyimages']      = _('You have attempted to upload too many images!');
Vi::$config['error']['nomove']             = _('The server failed to handle your upload.');
Vi::$config['error']['fileext']            = _('Unsupported image format.');
Vi::$config['error']['noboard']            = _('Invalid board!');
Vi::$config['error']['nonexistant']        = _('Thread specified does not exist.');
Vi::$config['error']['locked']             = _('Thread locked. You may not reply at this time.');
Vi::$config['error']['reply_hard_limit']   = _('Thread has reached its maximum reply limit.');
Vi::$config['error']['image_hard_limit']   = _('Thread has reached its maximum image limit.');
Vi::$config['error']['nopost']             = _('You didn\'t make a post.');
Vi::$config['error']['flood']              = _('Flood detected; Post discarded.');
Vi::$config['error']['spam']               = _('Your request looks automated; Post discarded. Try refreshing the page. If that doesn\'t work, please post the board, thread and browser this error occurred on on /operate/.');
Vi::$config['error']['unoriginal']         = _('Unoriginal content!');
Vi::$config['error']['muted']              = _('Unoriginal content! You have been muted for %d seconds.');
Vi::$config['error']['youaremuted']        = _('You are muted! Expires in %d seconds.');
Vi::$config['error']['dnsbl']              = _('Your IP address is listed in %s.');
Vi::$config['error']['toomanylinks']       = _('Too many links(%d of %d).');
Vi::$config['error']['op_requiredatleast'] = _('OP are required to have at least %s on this board.');
Vi::$config['error']['toomanycites']       = _('Too many cites; post discarded.');
Vi::$config['error']['toomanycross']       = _('Too many cross-board links; post discarded.');
Vi::$config['error']['nodelete']           = _('You didn\'t select anything to delete.');
Vi::$config['error']['noreport']           = _('You didn\'t select anything to report.');
Vi::$config['error']['toomanyreports']     = _('You can\'t report that many posts at once.');
Vi::$config['error']['invalidpassword']    = _('Wrong password…');
Vi::$config['error']['invalidimg']         = _('Invalid image.');
Vi::$config['error']['unknownext']         = _('Unknown file extension.');
Vi::$config['error']['filesize']           = _('Maximum file size: %maxsz% bytes<br>Your file\'s size: %filesz% bytes');
Vi::$config['error']['maxsize']            = _('The file was too big.');
Vi::$config['error']['genwebmerror']       = _('There was a problem processing your webm.');
Vi::$config['error']['webmerror']          = _('There was a problem processing your webm.'); //Is this error used anywhere ?
Vi::$config['error']['invalidwebm']        = _('Invalid webm uploaded.');
Vi::$config['error']['webmhasaudio']       = _('The uploaded webm contains an audio or another type of additional stream.');
Vi::$config['error']['webmtoolong']        = _('The uploaded webm is longer than %d seconds.');
Vi::$config['error']['fileexists']         = _('That file <a href="%s">already exists</a>!');
Vi::$config['error']['fileexistsinthread'] = _('That file <a href="%s">already exists</a> in this thread!');
Vi::$config['error']['delete_too_soon']    = _('You\'ll have to wait another %s before deleting that.');
Vi::$config['error']['delete_too_soon_thread'] = _('You can not deleting OP-post after %s.');
Vi::$config['error']['mime_exploit']       = _('MIME type detection XSS exploit (IE) detected; post discarded.');
Vi::$config['error']['invalid_embed']      = _('Couldn\'t make sense of the URL of the video you tried to embed.');
Vi::$config['error']['captcha']            = _('You seem to have mistyped the verification.');
Vi::$config['error']['images_disabled']    = _('Uploading files is disabled on this board.');
Vi::$config['error']['directly_run']       = _('Cannot be run directly.');

// mod.php errors
Vi::$config['error']['toomanyunban'] = _('You are only allowed to unban %s users at a time. You tried to unban %u users.');
Vi::$config['error']['invalid']      = _('Invalid username and/or password.');
Vi::$config['error']['notamod']      = _('You are not a mod…');
Vi::$config['error']['invalidafter'] = _('Invalid username and/or password. Your user may have been deleted or changed.');
Vi::$config['error']['malformed']    = _('Invalid/malformed cookies.');
Vi::$config['error']['missedafield'] = _('Your browser didn\'t submit an input when it should have.');
Vi::$config['error']['required']     = _('The %s field is required.');
Vi::$config['error']['invalidfield'] = _('The %s field was invalid.');
Vi::$config['error']['boardexists']  = _('There is already a %s board.');
Vi::$config['error']['noaccess']     = _('You don\'t have permission to do that.');
Vi::$config['error']['invalidpost']  = _('That post doesn\'t exist…');
Vi::$config['error']['404']          = _('Page not found.');
Vi::$config['error']['modexists']    = _('That mod <a href="%s">already exists</a>!');
Vi::$config['error']['invalidtheme'] = _('That theme doesn\'t exist!');
Vi::$config['error']['csrf']         = _('Invalid security token! Please go back and try again.');
Vi::$config['error']['badsyntax']    = _('Your code contained PHP syntax errors. Please go back and correct them. PHP says: ');

/*
 * =========================
 *  Directory/file settings
 * =========================
 */

// The root directory, including the trailing slash, for Tinyboard.
// Examples: '/', 'http://boards.chan.org/', '/chan/'.
if (isset($_SERVER['REQUEST_URI'])) {
	$request_uri = $_SERVER['REQUEST_URI'];
	if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '') {
		$request_uri = substr($request_uri, 0, -1 - strlen($_SERVER['QUERY_STRING']));
	}

	Vi::$config['root'] = str_replace('\\', '/', dirname($request_uri)) == '/'
	? '/' : str_replace('\\', '/', dirname($request_uri)) . '/';
	unset($request_uri);
} else {
	Vi::$config['root'] = '/';
}
// CLI mode

// The scheme and domain. This is used to get the site's absolute URL (eg. for image identification links).
// If you use the CLI tools, it would be wise to override this setting.
Vi::$config['domain'] = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https://' : 'http://';
Vi::$config['domain'] .= isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

// If for some reason the folders and static HTML index files aren't in the current working direcotry,
// enter the directory path here. Otherwise, keep it false.
Vi::$config['root_file'] = false;

// Location of files.
Vi::$config['file_index']  = 'index.html';
Vi::$config['file_page']   = '%d.html';
Vi::$config['file_page50'] = '%d+last.html';
Vi::$config['file_mod']    = 'mod.php';
Vi::$config['file_post']   = 'post.php';
Vi::$config['file_script'] = 'main.js';

// Board directory, followed by a forward-slash (/).
Vi::$config['board_path'] = '%s/';
// Misc directories.
Vi::$config['dir']['img']   = 'src/';
Vi::$config['dir']['thumb'] = 'thumb/';
Vi::$config['dir']['res']   = 'res/';

// Images in a seperate directory - For CDN or media servers
// This is a particularly advanced feature - contact ctrlcctrlv or rails unless you
//   really know what you're doing
Vi::$config['dir']['img_root'] = '';
// DO NOT COMMENT OUT, LEAVE BLANK AND OVERRIDE IN INSTANCE CONFIG
// Though, you shouldnt be editing this file, so what do I know?

// For load balancing, having a seperate server (and domain/subdomain) for serving static content is
// possible. This can either be a directory or a URL. Defaults to Vi::$config['root'] . 'static/'.
// Vi::$config['dir']['static'] = 'http://static.example.org/';

// Where to store the .html templates. This folder and the template files must exist.
Vi::$config['dir']['template'] = getcwd() . '/templates';
// Location of Tinyboard "themes".
Vi::$config['dir']['themes'] = getcwd() . '/templates/themes';
// Same as above, but a URI (accessable by web interface).
Vi::$config['dir']['themes_uri'] = 'templates/themes';
// Home directory. Used by themes.
Vi::$config['dir']['home'] = '';

// Location of a blank 1x1 gif file. Only used when country_flags_condensed is enabled
// Vi::$config['image_blank'] = 'static/blank.gif';

// Static images. These can be URLs OR base64 (data URI scheme). These are only used if
// Vi::$config['font_awesome'] is false (default).
// Vi::$config['image_sticky']	= 'static/sticky.png';
// Vi::$config['image_locked']	= 'static/locked.gif';
// Vi::$config['image_bumplocked']	= 'static/sage.png'.

// If you want to put images and other dynamic-static stuff on another (preferably cookieless) domain.
// This will override Vi::$config['root'] and Vi::$config['dir']['...'] directives. "%s" will get replaced with
//  Vi::$board['dir'], which includes a trailing slash.
// Vi::$config['uri_thumb'] = 'http://images.example.org/%sthumb/';
// Vi::$config['uri_img'] = 'http://images.example.org/%ssrc/';

// Set custom locations for stylesheets and the main script file. This can be used for load balancing
// across multiple servers or hostnames.
// Vi::$config['url_stylesheet'] = 'http://static.example.org/style.css'; // main/base stylesheet
// Vi::$config['url_javascript'] = 'http://static.example.org/main.js';

// Website favicon.
Vi::$config['url_favicon'] = 'static/favicon.ico';

// Try not to build pages when we shouldn't have to.
Vi::$config['try_smarter'] = true;

/*
 * ====================
 *  Advanced build
 * ====================
 */

// Strategies for file generation. Also known as an "advanced build". If you don't have performance
// issues, you can safely ignore that part, because it's hard to configure and won't even work on
// your free webhosting.
//
// A strategy is a function, that given the PHP environment and ($fun, $array) variable pair, returns
// an $action array or false.
//
// $fun - a controller function name, see inc/controller.php. This is named after functions, so that
//        we can generate the files in daemon.
//
// $array - arguments to be passed
//
// $action - action to be taken. It's an array, and the first element of it is one of the following:
//   * "immediate" - generate the page immediately
//   * "defer" - defer page generation to a moment a worker daemon gets to build it (serving a stale
//               page in the meantime). The remaining arguments are daemon-specific. Daemon isn't
//               implemented yet :DDDD inb4 while(true) { generate(Queue::Get()) }; (which is probably it).
//   * "build_on_load" - defer page generation to a moment, when the user actually accesses the page.
//                       This is a smart_build behaviour. You shouldn't use this one too much, if you
//                       use it for active boards, the server may choke due to a possible race condition.
//                       See my blog post: https://engine.vichan.net/blog/res/2.html
//
// So, let's assume we want to build a thread 1324 on board /b/, because a new post appeared there.
// We try the first strategy, giving it arguments: 'sb_thread', array('b', 1324). The strategy will
// now return a value $action, denoting an action to do. If $action is false, we try another strategy.
//
// As I said, configuration isn't easy.
//
// 1. chmod 0777 directories: tmp/locks/ and tmp/queue/.
// 2. serve 403 and 404 requests to go thru smart_build.php
//    for nginx, this blog post contains config snippets: https://engine.vichan.net/blog/res/2.html
// 3. disable indexes in your webserver
// 4. launch any number of daemons (eg. twice your number of threads?) using the command:
//    $ tools/worker.php
//    You don't need to do that step if you are not going to use the "defer" option.
// 5. enable smart_build_helper (see below)
// 6. edit the strategies (see inc/functions.php for the builtin ones). You can use lambdas. I will test
//    various ones and include one that works best for me.
Vi::$config['generation_strategies'] = array();
// Add a sane strategy. It forces to immediately generate a page user is about to land on. Otherwise,
// it has no opinion, so it needs a fallback strategy.
Vi::$config['generation_strategies'][] = 'strategy_sane';
// Add an immediate catch-all strategy. This is the default function of imageboards: generate all pages
// on post time.
Vi::$config['generation_strategies'][] = 'strategy_immediate';
// NOT RECOMMENDED: Instead of an all-"immediate" strategy, you can use an all-"build_on_load" one (used
// to be initialized using Vi::$config['smart_build']; ) for all pages instead of those to be build
// immediately. A rebuild done in this mode should remove all your static files
// Vi::$config['generation_strategies'][1] = 'strategy_smart_build';

// Deprecated. Leave it false. See above.
Vi::$config['smart_build'] = false;

// Use smart_build.php for dispatching missing requests. It may be useful without smart_build or advanced
// build, for example it will regenerate the missing files.
Vi::$config['smart_build_helper'] = true;

// smart_build.php: when a file doesn't exist, where should we redirect?
Vi::$config['page_404'] = '/404.html';

// Extra controller entrypoints. Controller is used only by smart_build and advanced build.
Vi::$config['controller_entrypoints'] = array();

/*
 * ====================
 *  Mod settings
 * ====================
 */

// Limit how many bans can be removed via the ban list. Set to false (or zero) for no limit.
Vi::$config['mod']['unban_limit'] = false;

// Whether or not to lock moderator sessions to IP addresses. This makes cookie theft ineffective.
Vi::$config['mod']['lock_ip'] = true;

// The page that is first shown when a moderator logs in. Defaults to the dashboard (?/).
Vi::$config['mod']['default'] = '/';

// Mod links (full HTML).
Vi::$config['mod']['link_delete']            = '[D]';
Vi::$config['mod']['link_ban']               = '[B]';
Vi::$config['mod']['link_bandelete']         = '[B&amp;D]';
Vi::$config['mod']['link_deletefile']        = '[F]';
Vi::$config['mod']['link_spoilerimage']      = '[S]';
Vi::$config['mod']['link_spoilerimages']     = '[S+]';
Vi::$config['mod']['link_deletebyip']        = '[D+]';
Vi::$config['mod']['link_deletebyip_global'] = '[D++]';
Vi::$config['mod']['link_sticky']            = '[Sticky]';
Vi::$config['mod']['link_desticky']          = '[-Sticky]';
Vi::$config['mod']['link_lock']              = '[Lock]';
Vi::$config['mod']['link_unlock']            = '[-Lock]';
Vi::$config['mod']['link_bumplock']          = '[Sage]';
Vi::$config['mod']['link_bumpunlock']        = '[-Sage]';
Vi::$config['mod']['link_editpost']          = '[Edit]';
Vi::$config['mod']['link_move']              = '[Move]';
Vi::$config['mod']['link_cycle']             = '[Cycle]';
Vi::$config['mod']['link_uncycle']           = '[-Cycle]';

// Moderator capcodes.
Vi::$config['capcode'] = ' <span class="capcode">## %s</span>';

// "## Custom" becomes lightgreen, italic and bold:
//Vi::$config['custom_capcode']['Custom'] ='<span class="capcode" style="color:lightgreen;font-style:italic;font-weight:bold"> ## %s</span>';

// "## Mod" makes everything purple, including the name and tripcode:
//Vi::$config['custom_capcode']['Mod'] = array(
//	'<span class="capcode" style="color:purple"> ## %s</span>',
//	'color:purple', // Change name style; optional
//	'color:purple' // Change tripcode style; optional
//);

// "## Admin" makes everything red and bold, including the name and tripcode:
//Vi::$config['custom_capcode']['Admin'] = array(
//	'<span class="capcode" style="color:red;font-weight:bold"> ## %s</span>',
//	'color:red;font-weight:bold', // Change name style; optional
//	'color:red;font-weight:bold' // Change tripcode style; optional
//);

// Enable the moving of single replies
Vi::$config['move_replies'] = false;

// How often (minimum) to purge the ban list of expired bans (which have been seen). Only works when
//  Vi::$config['cache'] is enabled and working.
Vi::$config['purge_bans'] = 60 * 60 * 12; // 12 hours

// Do DNS lookups on IP addresses to get their hostname for the moderator IP pages (?/IP/x.x.x.x).
Vi::$config['mod']['dns_lookup'] = true;
// How many recent posts, per board, to show in ?/IP/x.x.x.x.
Vi::$config['mod']['ip_recentposts'] = 5;

// Number of posts to display on the reports page.
Vi::$config['mod']['recent_reports'] = 10;
// Number of actions to show per page in the moderation log.
Vi::$config['mod']['modlog_page'] = 350;
// Number of bans to show per page in the ban list.
Vi::$config['mod']['banlist_page'] = 350;
// Number of news entries to display per page.
Vi::$config['mod']['news_page'] = 40;
// Number of results to display per page.
Vi::$config['mod']['search_page'] = 200;
// Number of entries to show per page in the moderator noticeboard.
Vi::$config['mod']['noticeboard_page'] = 50;
// Number of entries to summarize and display on the dashboard.
Vi::$config['mod']['noticeboard_dashboard'] = 5;

// Check public ban message by default.
Vi::$config['mod']['check_ban_message'] = false;
// Default public ban message. In public ban messages, %length% is replaced with "for x days" or
// "permanently" (with %LENGTH% being the uppercase equivalent).
Vi::$config['mod']['default_ban_message'] = _('USER WAS BANNED FOR THIS POST');
// Vi::$config['mod']['default_ban_message'] = 'USER WAS BANNED %LENGTH% FOR THIS POST';
// HTML to append to post bodies for public bans messages (where "%s" is the message).
Vi::$config['mod']['ban_message'] = '<span class="public_ban">(%s)</span>';

// When moving a thread to another board and choosing to keep a "shadow thread", an automated post (with
// a capcode) will be made, linking to the new location for the thread. "%s" will be replaced with a
// standard cross-board post citation (>>>/board/xxx)
Vi::$config['mod']['shadow_mesage'] = _('Moved to %s.');
// Capcode to use when posting the above message.
Vi::$config['mod']['shadow_capcode'] = 'Mod';
// Name to use when posting the above message. If false, Vi::$config['anonymous'] will be used.
Vi::$config['mod']['shadow_name'] = false;

// PHP time limit for ?/rebuild. A value of 0 should cause PHP to wait indefinitely.
Vi::$config['mod']['rebuild_timelimit'] = 0;

// PM snippet (for ?/inbox) length in characters.
Vi::$config['mod']['snippet_length'] = 75;

// Max PMs that can be sent by one user per hour.
Vi::$config['mod']['pm_ratelimit'] = 100;

// Maximum size of a PM.
Vi::$config['mod']['pm_maxsize'] = 8192;

// Edit raw HTML in posts by default.
Vi::$config['mod']['raw_html_default'] = false;

// Automatically dismiss all reports regarding a thread when it is locked.
Vi::$config['mod']['dismiss_reports_on_lock'] = true;

// Replace ?/config with a simple text editor for editing inc/instance-config.php.
Vi::$config['mod']['config_editor_php'] = false;

/*
 * ====================
 *  Mod permissions
 * ====================
 */

// Probably best not to change this unless you are smart enough to figure out what you're doing. If you
// decide to change it, remember that it is impossible to redefinite/overwrite groups; you may only add
// new ones.
Vi::$config['mod']['groups'] = array(
	10 => 'Janitor',
	20 => 'Mod',
	30 => 'Admin',
	// 98	=> 'God',
	99 => 'Disabled',
);

// If you add stuff to the above, you'll need to call this function immediately after.
define_groups();

// Example: Adding a new permissions group.
// Vi::$config['mod']['groups'][0] = 'NearlyPowerless';
// define_groups();

// Capcode permissions.
Vi::$config['mod']['capcode'] = array(
	//	JANITOR		=> array('Janitor'),
	MOD   => array('Mod'),
	ADMIN => true,
);

// Example: Allow mods to post with "## Moderator" as well
// Vi::$config['mod']['capcode'][MOD][] = 'Moderator';
// Example: Allow janitors to post with any capcode
// Vi::$config['mod']['capcode'][JANITOR] = true;

// Set any of the below to "DISABLED" to make them unavailable for everyone.

// Don't worry about per-board moderators. Let all mods moderate any board.
Vi::$config['mod']['skip_per_board'] = false;

/* Post Controls */
// View IP addresses
Vi::$config['mod']['show_ip'] = MOD;
// Delete a post
Vi::$config['mod']['delete'] = JANITOR;
// Ban a user for a post
Vi::$config['mod']['ban'] = MOD;
// Ban and delete (one click; instant)
Vi::$config['mod']['bandelete'] = MOD;
// Remove bans
Vi::$config['mod']['unban'] = MOD;
// Spoiler image
Vi::$config['mod']['spoilerimage'] = JANITOR;
// Delete file (and keep post)
Vi::$config['mod']['deletefile'] = JANITOR;
// Delete all posts by IP
Vi::$config['mod']['deletebyip'] = MOD;
// Delete all posts by IP across all boards
Vi::$config['mod']['deletebyip_global'] = ADMIN;
// Sticky a thread
Vi::$config['mod']['sticky'] = MOD;
// Cycle a thread
Vi::$config['mod']['cycle'] = MOD;
Vi::$config['cycle_limit']  = &Vi::$config['reply_limit'];
// Lock a thread
Vi::$config['mod']['lock'] = MOD;
// Post in a locked thread
Vi::$config['mod']['postinlocked'] = MOD;
// Prevent a thread from being bumped
Vi::$config['mod']['bumplock'] = MOD;
// View whether a thread has been bumplocked ("-1" to allow non-mods to see too)
Vi::$config['mod']['view_bumplock'] = MOD;
// Edit posts
Vi::$config['mod']['editpost'] = ADMIN;
// "Move" a thread to another board (EXPERIMENTAL; has some known bugs)
Vi::$config['mod']['move'] = DISABLED;
// Bypass "field_disable_*" (forced anonymity, etc.)
Vi::$config['mod']['bypass_field_disable'] = MOD;
// Post bypass unoriginal content check on robot-enabled boards
Vi::$config['mod']['postunoriginal'] = ADMIN;
// Bypass flood check
Vi::$config['mod']['bypass_filters'] = ADMIN;
Vi::$config['mod']['flood']          = &Vi::$config['mod']['bypass_filters'];
// Raw HTML posting
Vi::$config['mod']['rawhtml'] = ADMIN;

// Clean System
// Post edits remove local clean?
Vi::$config['clean']['edits_remove_local'] = true;
// Post edits remove global clean?
Vi::$config['clean']['edits_remove_global'] = true;
// Mark post clean for board rule
Vi::$config['mod']['clean'] = JANITOR;
// Mark post clean for global rule
Vi::$config['mod']['clean_global'] = MOD;

/* Administration */
// View the report queue
Vi::$config['mod']['reports'] = JANITOR;
// Dismiss an abuse report
Vi::$config['mod']['report_dismiss'] = JANITOR;
// Remove global status from a report
Vi::$config['mod']['report_demote'] = JANITOR;
// Elevate a global report to a local report.
Vi::$config['mod']['report_promote'] = JANITOR;
// Dismiss all abuse reports by an IP
Vi::$config['mod']['report_dismiss_ip'] = JANITOR;
// Dismiss all abuse reports on an individual post or thread
Vi::$config['mod']['report_dismiss_content'] = JANITOR;
// View list of bans
Vi::$config['mod']['view_banlist'] = MOD;
// View the username of the mod who made a ban
Vi::$config['mod']['view_banstaff'] = MOD;
// If the moderator doesn't fit the Vi::$config['mod']['view_banstaff'] (previous) permission, show him just
// a "?" instead. Otherwise, it will be "Mod" or "Admin".
Vi::$config['mod']['view_banquestionmark'] = false;
// Show expired bans in the ban list (they are kept in cache until the culprit returns)
Vi::$config['mod']['view_banexpired'] = true;
// View ban for IP address
Vi::$config['mod']['view_ban'] = Vi::$config['mod']['view_banlist'];
// View IP address notes
Vi::$config['mod']['view_notes'] = JANITOR;
// Create notes
Vi::$config['mod']['create_notes'] = Vi::$config['mod']['view_notes'];
// Remote notes
Vi::$config['mod']['remove_notes'] = ADMIN;
// Create a new board
Vi::$config['mod']['newboard'] = ADMIN;
// Manage existing boards (change title, etc)
Vi::$config['mod']['manageboards'] = ADMIN;
// Delete a board
Vi::$config['mod']['deleteboard'] = ADMIN;
// List/manage users
Vi::$config['mod']['manageusers'] = MOD;
// Promote/demote users
Vi::$config['mod']['promoteusers'] = ADMIN;
// Edit any users' login information
Vi::$config['mod']['editusers'] = ADMIN;
// Change user's own password
Vi::$config['mod']['edit_profile'] = JANITOR;
// Delete a user
Vi::$config['mod']['deleteusers'] = ADMIN;
// Create a user
Vi::$config['mod']['createusers'] = ADMIN;
// View the moderation log
Vi::$config['mod']['modlog'] = ADMIN;
// View IP addresses of other mods in ?/log
Vi::$config['mod']['show_ip_modlog'] = ADMIN;
// View relevant moderation log entries on IP address pages (ie. ban history, etc.) Warning: Can be
// pretty resource intensive if your mod logs are huge.
Vi::$config['mod']['modlog_ip'] = MOD;
// Create a PM (viewing mod usernames)
Vi::$config['mod']['create_pm'] = JANITOR;
// Create a PM for anyone
Vi::$config['mod']['pm_all'] = ADMIN;
// Bypass PM ratelimit
Vi::$config['mod']['bypass_pm_ratelimit'] = ADMIN;
// Read any PM, sent to or from anybody
Vi::$config['mod']['master_pm'] = ADMIN;
// Rebuild everything
Vi::$config['mod']['rebuild'] = ADMIN;
// Search through posts, IP address notes and bans
Vi::$config['mod']['search'] = JANITOR;
// Allow searching posts (can be used with board configuration file to disallow searching through a
// certain board)
Vi::$config['mod']['search_posts'] = JANITOR;
// Read the moderator noticeboard
Vi::$config['mod']['noticeboard'] = JANITOR;
// Post to the moderator noticeboard
Vi::$config['mod']['noticeboard_post'] = MOD;
// Delete entries from the noticeboard
Vi::$config['mod']['noticeboard_delete'] = ADMIN;
// Public ban messages; attached to posts
Vi::$config['mod']['public_ban'] = MOD;
// Manage and install themes for homepage
Vi::$config['mod']['themes'] = ADMIN;
// Post news entries
Vi::$config['mod']['news'] = ADMIN;
// Custom name when posting news
Vi::$config['mod']['news_custom'] = ADMIN;
// Delete news entries
Vi::$config['mod']['news_delete'] = ADMIN;
// Execute un-filtered SQL queries on the database (?/debug/sql)
Vi::$config['mod']['debug_sql'] = DISABLED;
// Look through all cache values for debugging when APC is enabled (?/debug/apc)
Vi::$config['mod']['debug_apc'] = ADMIN;
// Edit the current configuration (via web interface)
Vi::$config['mod']['edit_config'] = ADMIN;
// View ban appeals
Vi::$config['mod']['view_ban_appeals'] = MOD;
// Accept and deny ban appeals
Vi::$config['mod']['ban_appeals'] = MOD;
// View the recent posts page
Vi::$config['mod']['recent'] = MOD;
// Create pages
Vi::$config['mod']['edit_pages'] = MOD;
Vi::$config['pages_max']         = 10;

// Config editor permissions
Vi::$config['mod']['config'] = array();

// Disable the following configuration variables from being changed via ?/config. The following default
// banned variables are considered somewhat dangerous.
Vi::$config['mod']['config'][DISABLED] = array(
	'mod>config',
	'mod>config_editor_php',
	'mod>groups',
	'convert_args',
	'db>',
);

Vi::$config['mod']['config'][JANITOR] = array(
	'!', // Allow editing ONLY the variables listed (in this case, nothing).
);

Vi::$config['mod']['config'][MOD] = array(
	'!', // Allow editing ONLY the variables listed (plus that in Vi::$config['mod']['config'][JANITOR]).
	'global_message',
);

// Example: Disallow ADMIN from editing (and viewing) Vi::$config['db']['password'].
// Vi::$config['mod']['config'][ADMIN] = array(
// 	'db>password',
// );

// Example: Allow ADMIN to edit anything other than Vi::$config['db']
// (and Vi::$config['mod']['config'][DISABLED]).
// Vi::$config['mod']['config'][ADMIN] = array(
// 	'db',
// );

// Allow OP to remove arbitrary posts in his thread
Vi::$config['user_moderation'] = false;

// File board. Like 4chan /f/
Vi::$config['file_board'] = false;

// Thread tags. Set to false to disable
// Example: array('A' => 'Chinese cartoons', 'M' => 'Music', 'P' => 'Pornography');
Vi::$config['allowed_tags'] = false;

/*
 * ====================
 *  Public post search
 * ====================
 */
Vi::$config['search'] = array();

// Enable the search form
Vi::$config['search']['enable'] = false;

// Maximal number of queries per IP address per minutes
Vi::$config['search']['queries_per_minutes'] = Array(15, 2);

// Global maximal number of queries per minutes
Vi::$config['search']['queries_per_minutes_all'] = Array(50, 2);

// Limit of search results
Vi::$config['search']['search_limit'] = 100;

// Boards for searching
//Vi::$config['search']['boards'] = array('a', 'b', 'c', 'd', 'e');

/*
 * ====================
 *  Events (PHP 5.3.0+)
 * ====================
 */

// http://tinyboard.org/docs/?p=Events

// event_handler('post', function($post) {
// 	// do something
// });

// event_handler('post', function($post) {
// 	// do something else
//
// 	// return an error (reject post)
// 	return 'Sorry, you cannot post that!';
// });

/*
 * =============
 *  API settings
 * =============
 */

// Whether or not to enable the 4chan-compatible API, disabled by default. See
// https://github.com/4chan/4chan-API for API specification.
Vi::$config['api']['enabled'] = true;

// Extra fields in to be shown in the array that are not in the 4chan-API. You can get these by taking a
// look at the schema for posts_ tables. The array should be formatted as $db_column => $translated_name.
// Example: Adding the pre-markup post body to the API as "com_nomarkup".
// Vi::$config['api']['extra_fields'] = array('body_nomarkup' => 'com_nomarkup');

/*
 * ==================
 *  NNTPChan settings
 * ==================
 */

/*
 * Please keep in mind that NNTPChan support in vichan isn't finished yet / is in an experimental
 * state. Please join #nntpchan on Rizon in order to peer with someone.
 */

Vi::$config['nntpchan'] = array();

// Enable NNTPChan integration
Vi::$config['nntpchan']['enabled'] = false;

// NNTP server
Vi::$config['nntpchan']['server'] = "localhost:1119";

// Global dispatch array. Add your boards to it to enable them. Please make
// sure that this setting is set in a global context.
Vi::$config['nntpchan']['dispatch'] = array(); // 'overchan.test' => 'test'

// Trusted peer - an IP address of your NNTPChan instance. This peer will have
// increased capabilities, eg.: will evade spamfilter.
Vi::$config['nntpchan']['trusted_peer'] = '127.0.0.1';

// Salt for message ID generation. Keep it long and secure.
Vi::$config['nntpchan']['salt'] = 'change_me+please';

// A local message ID domain. Make sure to change it.
Vi::$config['nntpchan']['domain'] = 'example.vichan.net';

// An NNTPChan group name.
// Please set this setting in your board/config.php, not globally.
Vi::$config['nntpchan']['group'] = false; // eg. 'overchan.test'

/*
 * ====================
 *  Other/uncategorized
 * ====================
 */

// Meta keywords. It's probably best to include these in per-board configurations.
// Vi::$config['meta_keywords'] = 'chan,anonymous discussion,imageboard,tinyboard';

// Link imageboard to your Google Analytics account to track users and provide traffic insights.
// Vi::$config['google_analytics'] = 'UA-xxxxxxx-yy';
// Keep the Google Analytics cookies to one domain -- ga._setDomainName()
// Vi::$config['google_analytics_domain'] = 'www.example.org';

// Link imageboard to your Statcounter.com account to track users and provide traffic insights without the Google botnet.
// Extract these values from Statcounter's JS tracking code:
// Vi::$config['statcounter_project'] = '1234567';
// Vi::$config['statcounter_security'] = 'acbd1234';

// If you use Varnish, Squid, or any similar caching reverse-proxy in front of Tinyboard, you can
// configure Tinyboard to PURGE files when they're written to.
// Vi::$config['purge'] = array(
// 	array('127.0.0.1', 80)
// 	array('127.0.0.1', 80, 'example.org')
// );

// Connection timeout for Vi::$config['purge'], in seconds.
Vi::$config['purge_timeout'] = 3;

// Additional mod.php?/ pages. Look in inc/mod/pages.php for help.
// Vi::$config['mod']['custom_pages']['/something/(\d+)'] = function($id) {
// 	if (!hasPermission(Vi::$config['mod']['something']))
// 		error(Vi::$config['error']['noaccess']);
// 	// ...
// };

// You can also enable themes (like ukko) in mod panel like this:
// require_once("templates/themes/ukko/theme.php");
//
// Vi::$config['mod']['custom_pages']['/\*/'] = function() {
//        $ukko = new ukko();
//        $ukko->settings = array();
//        $ukko->settings['uri'] = '*';
//        $ukko->settings['title'] = 'derp';
//        $ukko->settings['subtitle'] = 'derpity';
//        $ukko->settings['thread_limit'] = 15;
//        $ukko->settings['exclude'] = '';
//
//        echo $ukko->build(Vi::$mod);
// };

// Example: Add links to dashboard (will all be in a new "Other" category).
// Vi::$config['mod']['dashboard_links']['Something'] = '?/something';

// Remote servers. I'm not even sure if this code works anymore. It might. Haven't tried it in a while.
// Vi::$config['remote']['static'] = array(
// 	'host' => 'static.example.org',
// 	'auth' => array(
// 		'method' => 'plain',
// 		'username' => 'username',
// 		'password' => 'password!123'
// 	),
// 	'type' => 'scp'
// );

// Create gzipped static files along with ungzipped.
// This is useful with nginx with gzip_static on.
Vi::$config['gzip_static'] = false;

// Regex for board URIs. Don't add "`" character or any Unicode that MySQL can't handle. 58 characters
// is the absolute maximum, because MySQL cannot handle table names greater than 64 characters.
Vi::$config['board_regex'] = '[0-9a-zA-Z\+$_\x{0080}-\x{FFFF}]{1,58}';

// Regex for matching links.
Vi::$config['link_regex'] = '((?:(?:https?:)?\/\/|ftp:\/\/|irc:\/\/)[^\s<>()"]+?(?:\([^\s<>()"]*?\)[^\s<>()"]*?)*)((?:\s|<|>|"|\.|\]|!|\?|,|&\#44;|&quot;)*(?:[\s<>()"]|$))';

// Allowed URLs in ?/settings
Vi::$config['allowed_offsite_urls'] = array('https://i.imgur.com/', 'https://media.8ch.net/', 'https://media.8chan.co/', 'https://a.pomf.se/', 'https://fonts.googleapis.com/', 'https://fonts.gstatic.com/');

// Use read.php?
// read.php is a file that dynamically displays pages to users instead of the build on demand system in use in Tinyboard since 2010.
//
// read.php is basically a watered down mod.php -- if coupled with caching, it improves performance and allows for easier replication
// across machines.
Vi::$config['use_read_php'] = false;

// Use oekaki?
Vi::$config['oekaki'] = false;

// Twig cache?
Vi::$config['twig_cache'] = false;

// Use CAPTCHA for reports?
Vi::$config['report_captcha'] = false;

// Allowed HTML tags in ?/edit_pages.
Vi::$config['allowed_html'] = 'a[href|title],p,br,li,ol,ul,strong,em,u,h2,b,i,tt,div,img[src|alt|title],hr,h1,h2,h3,h4,h5';

// Use custom assets? (spoiler file, etc; this is used by ?/settings and ?/assets)
Vi::$config['custom_assets'] = false;

// If you use CloudFlare set these for some features to work correctly.
Vi::$config['cloudflare']            = array();
Vi::$config['cloudflare']['enabled'] = false;
Vi::$config['cloudflare']['token']   = 'token';
Vi::$config['cloudflare']['email']   = 'email';
Vi::$config['cloudflare']['domain']  = 'example.com';

// Password hashing function
//
// $5$ <- SHA256
// $6$ <- SHA512
//
// 25000 rounds make for ~0.05s on my 2015 Core i3 computer.
//
// https://secure.php.net/manual/en/function.crypt.php
Vi::$config['password_crypt'] = '$6$rounds=25000$';

// Password hashing method version
// If set to 0, it won't upgrade hashes using old password encryption schema, only create new.
// You can set it to a higher value, to further migrate to other password hashing function.
Vi::$config['password_crypt_version'] = 1;
