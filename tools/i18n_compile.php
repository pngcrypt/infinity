#!/usr/bin/php
<?php

/*
 *  i18n_compile.php - compiles the i18n
 *      
 */

require dirname(__FILE__) . '/inc/cli.php';

class i18n_compile {
	public static $in_php = "tinyboard.po"; // input php .po file
	public static $out_php = "tinyboard.mo"; // output compiled php file
	public static $in_js = "javascript.po"; // input js .po file
	public static $out_js = "javascript.js"; // output compiled js file
	public static $dir_locales = "inc/locale"; // locales root dir
	public static $dir_messages = "LC_MESSAGES"; // subdir of messages in locale


	static function usage($err="") {
		if($err !== "")
			echo "$err\n\n";
		echo "Usage:\n";
		echo "    i18n_compile -l <locale>\n";
		if($err !== "")
			die;
		echo "\nWhere:\n";
		echo "    <locale> - list of locales or all (default)\n";
		echo "\nExamples:\n";
		echo "    i18n_compile -l ru_RU\n";
		echo "    i18n_compile -l ru_RU,en_US,fr_FR\n";
		echo "    i18n_compile -l all\n";
		die;
	}


	static function main() {
		$locales = [];

		// parse command
		$opts = getopt('l:');
		if(!count($opts) || !isset($opts['l']))
			self::usage();

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

		echo "Compiling locales...\n";
		foreach ($locales as $loc) {
			echo "[$loc]: ";
			$dir = self::$dir_locales."/$loc/".self::$dir_messages;
			if(!is_dir($dir)) {
				echo "ERROR: dir not found: $dir\n";
				continue;
			}
			echo "\n";

			// compiling php .po
			$in = "$dir/".self::$in_php;
			$out = "$dir/".self::$out_php;
			echo "Compiling $in...";
			if(!file_exists($in)) {
				echo "ERROR: not found\n";
			}
			else {
				echo "\n";
				passthru("msgfmt -o $out $in", $ret);
			}

			// compiling js .po
			$in = "$dir/".self::$in_js;
			$out = "$dir/".self::$out_js;
			echo "Compiling $in...";
			if(!file_exists($in)) {
				echo "ERROR: not found\n";
			}
			else {
				echo "\n";
				passthru("php tools/inc/lib/jsgettext/po2json.php -i $in -o $out", $ret);
			}
			echo "\n";
		}
	}
} // class i18n_compile

i18n_compile::main();
