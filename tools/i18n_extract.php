#!/usr/bin/php
<?php

/*
 *  i18n_extract.php - extracts the strings and updates all locales
 *
 */

require dirname(__FILE__) . '/inc/cli.php';

define("RECURSIVE", true);
define("NORECURSIVE", false);

class i18n_extract {
	public static $po_php = "tinyboard.po"; // php .po name
	public static $po_js = "javascript.po"; // js .po name
	public static $dir_locales = "inc/locale"; // locales root dir
	public static $dir_messages = "LC_MESSAGES"; // subdir of messages in locale
	public static $dir_temp = "_tmp_"; // temp work dir

	// php find patterns
	public static $fp_php = [
		["*.php", NORECURSIVE], // root, no recursive
		["inc/*.php", RECURSIVE, "#^inc/locale|^inc/lib/(htmlpurifier|parsedown|Twig|minify)#"], // /inc, recursive + exclude regexp
		["templates/*.php", RECURSIVE], // templates/ recursive
	];

	// twig find patterns
	public static $fp_twig = [
		["templates/*.html", RECURSIVE, "#^templates/cache#"],
	];

	// js find patterns
	public static $fp_js = [
		["js/*.js", RECURSIVE, "#^js/(code_tags|katex|longtable|mathjax|twemoji|jquery|wPaint/lib/jquery)#"],
		["templates/main.js"],
	];

	private static $exclude=false;
	private static $twig=null;
	private static $twig_check=false;
	private static $debug=false;

	//-----------------------------------------
	static function usage($err="") {
		if($err !== "")
			echo "$err\n\n";
		echo "Usage:\n";
		echo "    i18n_extract [-l <locale>] [-t <type>] [-d]\n";
		echo "    i18n_extract -c\n";
		if($err !== "")
			die;
		echo "\nWhere:\n";
		echo "    <locale> - list of locales or all\n";
		echo "    <type> - list of file types (js,php,twig) or all (default)\n\n";
		echo "    -d - debug mode (leave temp files)\n";
		echo "    -c - twig templates checking only\n\n";
		echo "Examples:\n";
		echo "    i18n_extract -l all\n";
		echo "    i18n_extract -l ru_RU\n";
		echo "    i18n_extract -l ru_RU,en_US -t js\n";
		echo "    i18n_extract -l pt_BR -t php,twig\n";
		echo "    i18n_extract -t twig\n";
		die;
	}

	//-----------------------------------------
	static function glob_recursive($pattern, $recursive = true, $flag = GLOB_NOSORT)
	{
		$files = glob($pattern, $flag);

		if($recursive) {
			foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir)
				$files = array_merge($files, self::glob_recursive($dir.'/'.basename($pattern), $recursive, $flag));
		}
		return $files;
	}

	//-----------------------------------------
	static function fl_make($findpattern, $files_only=true) {
		/*
			serach and return list of all found files as array

			$findpattern is array:
			[
				["file-mask1", RECURSIVE or NORECURSIVE, "exclude-regexp1"],
				...
				["file-maskN", RECURSIVE or NORECURSIVE, "exclude-regexpN"]
			]
		*/

		$ret = [];
		if(!is_array($findpattern))
			return $ret;

		foreach($findpattern as $inc) {
			if(!count($inc))
				continue;
			
			$recursive = count($inc) > 1 ? $inc[1] : NORECURSIVE;
			self::$exclude = count($inc) > 2 ? $inc[2] : false;

			$files = self::glob_recursive($inc[0], $recursive, GLOB_NOSORT);
			if($files_only)
				$files = array_filter($files, "is_file"); // exclude dirs
			$ret = array_merge($ret, array_filter($files, function($v) {
				if(self::$exclude === false) // no exclude regexp
					return true;
				return !preg_match(self::$exclude, $v); // check for exclude
			} ));
		}
		return $ret;
	}

	//-----------------------------------------
	static function twig_init() {		
		require 'inc/lib/Twig/Autoloader.php';

		Twig_Autoloader::register();
		Twig_Autoloader::autoload('Twig_Extensions_Node_Trans');
		Twig_Autoloader::autoload('Twig_Extensions_TokenParser_Trans');
		Twig_Autoloader::autoload('Twig_Extensions_Extension_I18n');
		Twig_Autoloader::autoload('Twig_Extensions_Extension_Tinyboard');

		self::$twig = new Twig_Environment(new Twig_Loader_String());
		self::$twig->addExtension(new Twig_Extensions_Extension_Tinyboard());
		self::$twig->addExtension(new Twig_Extensions_Extension_I18n());

		self::$twig->addFilter(new Twig_SimpleFilter('hex2bin', 'hex2bin'));
		self::$twig->addFilter(new Twig_SimpleFilter('base64_encode', 'base64_encode'));
	}

	//-----------------------------------------
	static function twig_to_php($in, $out) {
		// convert twig template $in to php code, save it in $out file
		// return true on succes or (string)"Error message"
		if(!self::$twig)
			self::twig_init();

		$buf = @file_get_contents($in); // load template
		if($buf === false)
			return "READ ERROR";

		try {
			$stream = self::$twig->tokenize($buf); // tokenize template
			$buf = self::$twig->compile(self::$twig->parse($stream)); // parse & compile to php
			if(self::$twig_check)
				return true;
			if(@file_put_contents($out, $buf) === false)
				return "WRITE ERROR";
		}
		catch(Exception $e) {
			return "ERROR: ".$e->getMessage();
		}
		return true;
	}


	//-----------------------------------------
	static function locale_merge($default, $new) {
		if(file_exists($default)) {
			echo "Merging $default...\n";
			passthru("msgmerge --update --backup=simple --no-wrap --sort-output --no-fuzzy-matching --no-location $default $new");
			echo "\n";
		}
		else {
			echo "Creating $default...";
			if(@copy($new, $default) === false)
				echo "ERROR\n";
			else
				echo "OK\n";
		}
	}

	//-----------------------------------------
	static function fix_ContentType($file) {
		$buf = @file_get_contents($file);
		if($buf === FALSE)
			return;
		$buf = preg_replace('|^("Content-Type: text/plain; charset=)CHARSET\\\\n"|m', '$1UTF-8\\n"', $buf, 1);
		if($buf !== NULL)
			file_put_contents($file, $buf);
	}

	//-----------------------------------------
	static function main() {

		$locales = [];
		$process_php = $process_twig = $process_js = true;

		// parse command line -->
		$opts = getopt('l:t:cd');
		if(!count($opts))
			self::usage();

		self::$twig_check = isset($opts['c']); // -c
		self::$debug = isset($opts['d']); // -d

		if(!self::$twig_check) {
			// -t
			if(isset($opts['t'])) {
				$i = explode(",", $opts['t']);
				if(!in_array('all', $i)) {
					$process_php = in_array('php', $i);
					$process_twig = in_array('twig', $i);
					$process_js = in_array('js', $i);
				}
			}
			if(!$process_php && !$process_twig && !$process_js)
				self::usage("ERROR: bad type: ".$opts['t']);

			// -l
			if(isset($opts['l'])) {
				$locales = explode(",", $opts['l']);
				if(in_array("all", $locales)) {
					// get all possible locales
					$locales = glob(self::$dir_locales . "/*", GLOB_ONLYDIR);
					$locales = array_map("basename", $locales);
				}
				else {
					// check locales list
					foreach ($locales as $loc) {
						if(!is_dir(self::$dir_locales."/$loc"))
							self::usage("ERROR: locale not found: $loc");					
					}
				}
			}
		}
		// <--

		if(!self::$twig_check) {
			// create temp dir
			@mkdir(self::$dir_temp);
			if(!is_dir(self::$dir_temp))
				die("Can't create tempdir: ".self::$dir_temp);
			self::$dir_temp .= "/";
			$lst_php = self::$dir_temp."__list_php__";
			$lst_js = self::$dir_temp."__list_js__";
		}

		// twig processing -->
		if(self::$twig_check || $process_twig) {
			echo "Generating twig file list... ";
			$files = self::fl_make(self::$fp_twig);
			echo "(" . count($files) . " files)\n";

			if(self::$debug && !self::$twig_check)
				file_put_contents(self::$dir_temp."__list_twig__", implode("\n", $files)."\n");

			echo (self::$twig_check ? "Checking" : "Compiling") ." twig templates...\n";
			$error = 0;
			foreach($files as $k => $f) {
				$out = self::$dir_temp . $f; //"$f.php";
				if(!self::$twig_check) {
					// create subdirs in temp if needed
					$i = dirname($out);
					if(!is_dir($i))
						mkdir($i, 0777, true);
				}
				$i = self::twig_to_php($f, $out); // compile template
				if($i !== true) {
					// compile error, skip file
					$out = "";
					echo "$f : $i\n";
					$error++;
				}
				$files[$k] = $out;
			}

			echo $error ? "\n$error error(s) found\n\n" : "No errors\n\n";

			if(self::$twig_check)
				die();
			else {
				if($error)
					$files = array_filter($files, "strlen"); // remove empty names (errors)

				// write file list
				file_put_contents($lst_php, implode("\n", $files)."\n");
			}
		}
		// <--

		if($process_php) {
			echo "Generating .php file list...";
			$files = self::fl_make(self::$fp_php);
			echo "(" . count($files) . " files)\n";

			// write file list, append if twig was processed
			file_put_contents($lst_php, implode("\n", $files)."\n", $process_twig ? FILE_APPEND : 0);
		}

		if($process_js) {
			echo "Generating .js file list...";
			$files = self::fl_make(self::$fp_js);
			echo "(" . count($files) . " files)\n";
			file_put_contents($lst_js, implode("\n", $files)."\n");
		}

		$process_php = ($process_php || $process_twig);

		if($process_php) {
			$po_php = self::$dir_temp . self::$po_php;
			echo "Generating $po_php...\n";
			passthru("xgettext -L PHP -o $po_php --force-po --no-wrap --sort-output --from-code UTF-8 -f $lst_php");
			self::fix_ContentType($po_php);
		}
		if($process_js) {
			$po_js = self::$dir_temp . self::$po_js;
			echo "Generating $po_js...\n";
			passthru("xgettext -L JavaScript -o $po_js --force-po --no-wrap --sort-output --from-code UTF-8 -f $lst_js");
			self::fix_ContentType($po_js);
		}

		// locales processing
		if(count($locales)) {
			echo "\nMerging locales...\n";
			foreach($locales as $loc) {
				echo "[$loc]: ";
				$i = self::$dir_locales."/$loc/".self::$dir_messages;
				if(!file_exists($i))
					@mkdir($i);
				if(!is_dir($i)) {
					echo "ERROR of dir creating: $i\n";
					continue;
				}
				echo "\n";
				if($process_php)
					self::locale_merge("$i/".self::$po_php, $po_php);
				if($process_js)
					self::locale_merge("$i/".self::$po_js, $po_js);
			}
		}

		if(!self::$debug && !self::$twig_check)
		{
			// clean temp dir
			echo "\nDeleting temp files...";
			$i = [[self::$dir_temp.'*', RECURSIVE]]; // find pattern
			if(!count($locales)) {
				// leave .po files in temp dir if no locale defined
				$i[0][2] = "/(".preg_quote(self::$po_php)."|".preg_quote(self::$po_js).")$/"; // exclude regexp
			}
			$files = self::fl_make($i, false); // get all temp files and dirs

			// delete files
			$files = array_filter($files, function($f) {
				if(is_file($f))
					return !@unlink($f);
				return true;
			});		

			// delete dirs
			rsort($files);
			$files = array_filter($files, function($f) {
				if(is_dir($f))
					return !@rmdir($f);
				return true;
			});
			@rmdir(self::$dir_temp);

			$i = count($files);
			echo ($i ? "($i failed)" : "OK")."\n";
		}

		echo "\nDone.\n";

	} // main
} // class i18n_extract

i18n_extract::main();

