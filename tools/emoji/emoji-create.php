#!/usr/bin/php
<?php

class emoji_create {
	public static $svg_sprites = 'emojione-sprites.svg';	
	public static $dir_out = 'out';
	public static $fn_map = "emoji-unicode-map.ser";  // serialized emoji map
	public static $fn_png = "emoji.%dx%d.png";  // output png sprite file
	public static $fn_css = "emoji.%dx%d.css";  // output css file 
	public static $fn_test = "test.%dx%d";  // output test file name (w/o extension)
	public static $subdir_png = "png-%dx%d";    // subdir for extracting icons (-e)  (%d - for substiotuion width & height )

	private static $skins = [
		0 => ["code" => ""],		// default (yellow)
		1 => ["code" => "1f3fb"],	// light pink
		2 => ["code" => "1f3fc"],	// pink
		3 => ["code" => "1f3fd"],	// light brown
		4 => ["code" => "1f3fe"],	// brown
		5 => ["code" => "1f3ff"],	// dark brown
	];

	private static $symbols = [];
	private static $emoji_map;
	private static $test = 0;
	private static $width;
	private static $height;
	private static $font_size;
	private static $cnt_img;
	private static $cnt_chars;

	//-----------------------------------------
	static function Usage($err = "") {
		if($err !== "")
			echo "$err\n\n";
		echo "Usage: emoji-create.php [-w N] [-l N] [-s SKINS] [-e] [--test|--test=N]\n\n";
		echo "Where:\n";
		echo "  -w N - width of icon (default: 16; minimum: 4); height == width\n";
		echo "  -l N - limit max bytes of one char in UTF-8 (default: 0 == unlimited)\n";
		echo "  -e - extract each icon to .png file (otherwise sprite-file will be used)\n";
		echo "  -s SKINS - list of skins substitutions (single number == for all)\n";
		echo "    Examples:\n";
		echo "      -s 0 -- replace all skins (1..5) by 0-skin (yellow)\n";
		echo "      -s -1 -- remove all skinned icons\n";
		echo "      -s 0:2,3:2,5:4 -- replace 0 to 2, 3 to 2, 5 to 4\n";
		echo "      (skin number should be within 0..5)\n\n";
		echo "  --test - create test and css files only (w/o images)\n";
		echo "  --test=0 - same as --test\n";
		echo "  --test=1 - same as --test, but with image processing\n";
		die;
	}

	//-----------------------------------------
	static function getSymSkin($sym) {
		$uc = $sym["code"];
		if(count($uc) == 1) {
			// 1-word: checking other skinned-items which starts with same code
			foreach(self::$skins as $id => $skin) {
				$idx = $uc[0] .'-'. $skin['code'];
				if(isset(self::$symbols[$idx]))
					return 0; // other items found, it's default skin
			}
			return -1; // no other items, not skinned icon
		}

		// more than 1-word
		foreach (self::$skins as $id => $skin) {
			if($skin['code'] == $uc[1]) // is it skin?
				return $id;
		}
		return -2;
	}

	//-----------------------------------------
	static function getMaxBytes($uc) {
		$max = 0;
		if(!is_array($uc))
			return $max;
		foreach($uc as $c) {
			$c = hexdec($c);
			if($c < 0x80) $b = 1;
			else if($c < 0x800) $b = 2;
			else if($c < 0x10000) $b = 3;
			else if($c < 0x200000) $b = 4;
			else if($c < 0x4000000) $b = 5;
			else $b = 6;
			if($b > $max)
				$max = $b;
		}
		return $max;
	}

	//-----------------------------------------
	static function test() {
		$chars_per_line = round(sqrt(self::$cnt_chars));

		$t1 = "<html>\n"
			."<head data-size=\"". self::$width ."\" data-css=\"". self::$fn_css ."\" data-fontsize=\"". self::$font_size ."\">\n"
			."<meta charset='utf-8'>\n"
			."<script type='text/javascript' src='../../../js/jquery-3.1.1.min.js'></script>\n"
			."<script type='text/javascript' src='../emoji-test.js'></script>\n"
			."<script type='text/javascript' src='../../../js/emoji/emoji.js'></script>\n"
			."</head>\n<body>\n"
			."<div class='container'>\n";
		$t2 = $t1;

		$regexp = [];
		$tc = 0;
		foreach(self::$symbols as $id => &$sym) {
			$x = html_entity_decode($sym['entity'], ENT_COMPAT, "UTF-8");
			$uc = substr(json_encode($x), 1, -1);
			$ucid = implode('-', $sym['unicode']);
			$regexp[$uc] = $id;

			$t1 .= "<div id='$ucid' data-uc='$uc'><i>". $sym['entity'] .'</i> ';
			$t1 .= '<i class="emoji emoji-'. ltrim($id, '0') .'"></i></div> ';

			$t2 .= "<i id='$ucid'>". $sym['entity'] .'</i> ';

			$tc++;
			if($tc >= $chars_per_line) {
				$t1 .= "\n";
				$t2 .= "\n";
				$tc = 0;
			}
		}

		$x = "\n</div>\n</body>\n</html>\n";
		$t1 .= $x;
		$t2 .= $x;

		echo "\nSaving test ", self::$dir_out, '/', self::$fn_test, ".html ...\n";
		file_put_contents(self::$dir_out .'/' . self::$fn_test. '.html', $t1);

		echo "Saving test ", self::$dir_out, '/', self::$fn_test, "-chars.html ...\n";
		file_put_contents(self::$dir_out .'/' . self::$fn_test. '-chars.html', $t2);

		// ksort($regexp, SORT_NATURAL);
		ob_start();
		print_r($regexp);
		$x = ob_get_clean();
		$x = preg_replace('/=>[\s\n]+/', ' => ', $x); // remove \n after =>
		$x = preg_replace('/{[\s\n]+}/', '{}', $x); // don't open empty arrays
		file_put_contents(self::$dir_out .'/regexp.txt', $x);
	}

	//-----------------------------------------
	static function main() {

		// command line pares -->
		$opts = getopt('hw:l:s:e', ['help', 'test::']);
		if(isset($opts['h']) || isset($opts['help']))
			self::Usage();

		if(!extension_loaded('imagick'))
			die("IMagick needed.\n");

		self::$width = self::$height = 16;
		if(isset($opts['w']))
			self::$height = self::$width = $opts['w']|0;
		if(self::$width < 4)
			self::Usage("ERROR: Bad width of icon: ". $opts['w']);
		self::$font_size = (self::$width*(11/16)) ."pt"; // font size in 'pt' (11pt in 16px)

		// -l
		$limit_bytes = true;
		$limit = 0;
		if(isset($opts['l'])) {
			$limit = $opts['l'];
			if(!is_numeric($limit)) {
				$x = strtolower(substr($limit, -1));
				$limit = substr($limit, 0, -1);
				if(!is_numeric($limit) || ($x != 'w' && $x != 'b'))
					self::Usage("ERROR: bad limit: ". $opts['l']);
				$limit_bytes = (bool)($x == 'b');
			}
			$limit = abs($limit|0);
		}

		// --test
		if(isset($opts['test'])) {
			self::$test = $opts['test'];
			if(self::$test === false)
				self::$test = -1;
			else {
				self::$test |= 0;
				self::$test = (self::$test < 1) ? -1 : 1;
			}
		}

		$extract = isset($opts['e']); // -e

		$reskin = false;
		if(isset($opts['s'])) {
			// -s 
			$reskin = $opts['s'];
			if(is_numeric($reskin)) {
				// single skin
				$reskin = (int)$reskin;
				if($reskin < -1 || $reskin > 5)
					self::Usage("ERROR: Bad skin number: $reskin");
			}
			else {
				// parse skins substitioins
				$i = explode(',', $reskin);
				$reskin = [];
				foreach($i as $s) {
					$s = explode(':', $s);
					$x = $y = -2;
					if(count($s) == 2) {
						if(is_numeric($s[0]))
							$x = $s[0]|0;
						if(is_numeric($s[1]))
							$y = $s[1]|0;
					}
					if($x < 0 || $x > 5 || $y < -1 || $y > 5) 
						self::Usage("ERROR: Bad syntax of skin: $s[0]:$s[1]");
					if($x != $y)
						$reskin[$x] = $y;
				}
				// check for loop errors
				foreach($reskin as $id => $s) {
					if(isset($reskin[$s]) && $id != $s && $reskin[$s] != $s)
						self::Usage("ERROR: Loop in skins: $id:$s --> $s:". $reskin[$s]);
				}
			}
			// create skins substitutions links
			foreach(self::$skins as $id => &$s) {
				if(is_array($reskin)) {
					// rules-based substitution
					if(isset($reskin[$id]))
						$s['subst'] = $reskin[$id];
				}
				else {
					// replace all skins with defined
					if($reskin == $id)
						continue;
					if($reskin > -1 || ($reskin < 0 && $id)) // if -1 (removing) leave default skin
						$s['subst'] = $reskin;
				}
			}
			$reskin = true;
		}
		// <-- parse command line

		// %d substitution in file names
		self::$subdir_png = sprintf(self::$subdir_png, self::$width, self::$height);
		self::$fn_png = sprintf(self::$fn_png, self::$width, self::$height);
		self::$fn_css = sprintf(self::$fn_css, self::$width, self::$height);
		self::$fn_test = sprintf(self::$fn_test, self::$width, self::$height);

		try {
			// load emoji's unicode map
			$x = file_get_contents(self::$fn_map);
			if($x !== false)
				self::$emoji_map = unserialize($x);

			// temp -->
			/*// load from json
			$x = file_get_contents('emojione-map.json');
			if($x !== false) {
				$x = json_decode($x, true);
				if(!is_array($x))
					die("JSON decode ERROR\n");
				self::$emoji_map = [];
				foreach($x as $y) {
					if(!is_array($y))
						continue;
					self::$emoji_map[$y['fname']] = $y['unicode'];
				}
				$x = serialize(self::$emoji_map);
				file_put_contents(self::$fn_map, $x);
			}*/
			// <-- temp

		}
		catch (Exception $e) {}
		if(!is_array(self::$emoji_map) || !count(self::$emoji_map))
			die("Map-file load ERROR: ". self::$fn_map ."\n");

		if(self::$test)
			echo "* TEST MODE * (". (self::$test < 0 ? "NO" : "WITH") ." image processing)\n\n";

		echo "Sprite-file: ", self::$svg_sprites, "\n";
		try {
			$sprites = simplexml_load_file(self::$svg_sprites);
		}
		catch (Exception $e) {
			$sprites = false;
		}
		if($sprites === false)
			die("ERROR: Couldn't load");

		$count = $sprites->symbol->count();
		if(!$count)
			die("ERROR: No sprites");

		echo "Total items: $count\n";

		// make symbols (icons) array
		$x = $y = 0;
		foreach($sprites->symbol as $sym) {
			$id = str_replace('emoji-', '', strtolower($sym['id']));
			$code = explode('-', $id);
			if(!count($code))
				continue;

			if(!isset(self::$emoji_map[$id])) {
				$x++;
				continue;
			}
			$uc = self::$emoji_map[$id];
			// $uc = count($uc) > 1 ? $uc[1] : $uc[0]; // simple codes
			$uc = $uc[0]; // standart codes
			$uc = explode('-', $uc);
			if($limit) {
				if(($limit_bytes && self::getMaxBytes($uc) > $limit) || (!$limit_bytes && count($code) > $limit)) {
					$y++;
					continue;
				}
			}
			$sym["width"] = self::$width;
			$sym["height"] = self::$height;
			self::$symbols[$id] = [
				"id" => $id,
				"svg" => $sym,
				"code" => $code,
				"unicode" => $uc,
				"entity" => "&#x".implode(";&#x", $uc).";", // html entity
			];
		}

		// sort by keys and length of codes
		uksort(self::$symbols, function($k1, $k2) {
			$k1 = self::$symbols[$k1]['code'];
			$k2 = self::$symbols[$k2]['code'];
			$k1[0] = str_pad($k1[0], 8, '0', STR_PAD_LEFT);
			$k2[0] = str_pad($k2[0], 8, '0', STR_PAD_LEFT);
			if(count($k1) != count($k2))
				$k1 = count($k1) < count($k2) ? -1 : 1;
			else
				$k1 = strcasecmp(implode('-', $k1), implode('-', $k2));

			// return (-1) * $k1; // backward sort
			return $k1; // forward sort
		});

		$cnt_img = $cnt_chars = count(self::$symbols);
		if($count != $cnt_img) {
			echo "Skipped: ";
			if($x)
				echo "Not in map:[$x]; ";
			if($y)
				echo ($limit_bytes ? "Max bytes" : "Max CodePoints"), " <= $limit:[$y]";
			echo "\n\n";
		}
		$count = $cnt_img;

		// skin substitution
		if($reskin) {
			$x = $y = $cnt_img = 0;
			foreach(self::$symbols as $id => &$sym) {
				$skin = self::getSymSkin($sym);
				if($skin >= 0) {
					$sym['skin'] = $skin;
					$skin = self::$skins[$skin];
					if(isset($skin['subst'])) {
						if($skin['subst'] < 0) {
							// remove item
							$sym['subst'] = -1;
							$x++; // count skipped
							continue;
						}
						else {
							$skin = self::$skins[$skin['subst']];
							$i = $skin['code'];
							$i = $sym['code'][0];
							if($skin['code'] != "")
								$i .= '-'.$skin['code'];
							if(isset(self::$symbols[$i])) {
								// subst item
								// $sym['subst'] = $i;
								$sym['subst'] = &self::$symbols[$i];
								$y++; // count substitutions
								continue;
							}
							else
								echo "WARNING: No item '$i' for substition: $id --> $i\n";
						}
					}
				}
				$cnt_img++;
			}
			echo "Used icons: $cnt_img of $count ($x removed, $y substituted)\n";
			$cnt_chars = $cnt_img + $y;
		}
		else
			echo "Used icons: $cnt_img\n";

		if(!$cnt_img)
			die("ERROR: Nothing to do\n");

		self::$cnt_img = $cnt_img;
		self::$cnt_chars = $cnt_chars;
		$svg_defs = $sprites->defs->asXML();
		$icons_per_line = round(sqrt($cnt_img));
		$width_image = self::$width * $icons_per_line;
		$height_image = self::$height * ceil($cnt_img / $icons_per_line);

		echo "Icon size: ". self::$width ." x ". self::$height ." px\n";

		if(self::$test) {
			self::test();
		}

		if(self::$test >= 0) {
			if($extract)
				echo "\nExctrating to $dir_out_png...\n";
			else
				echo "\nCreating PNG: $width_image x $height_image px ($icons_per_line icons per line)...\n";
		}

		if($extract && self::$test >= 0) {
			$dir_out_png = self::$dir_out .'/'. self::$subdir_png;
			@mkdir($dir_out_png, 0777, true);
		}

		if(self::$test >= 0) {
			$transparent = new ImagickPixel('transparent');
			$im_svg = new Imagick(); // icon image
			$im_svg->setBackgroundColor($transparent);	

			if(!$extract) {
				$im_png = new Imagick(); // sprite container
				$im_png->newImage($width_image, $height_image, $transparent);
				$im_png->setImageFormat('png32'); 
			}
		}

		$css = "/*\n"
			." * Emoji icons ". self::$width ." x ". self::$height ." px\n"
			.(!$extract	? " * Sprite file: $width_image x $height_image px ($icons_per_line icons per line)\n" : "")
			." *\n"
			." */\n\n"
			.".emoji {\n"
			."  display:inline-block;\n"
			."  width: ". self::$width ."px;\n"
			."  height: ". self::$height ."px;\n"
			."  overflow: hidden;\n"
			."  color: rgba(0,0,0,0);\n"
			."  line-height: ". self::$height ."px;\n"
			."  font-size: ". self::$font_size .";\n"
			."  font-style: normal;\n"
			."  text-align: center;\n"
			."  vertical-align: middle;\n"
			."  border: 0;\n"
			."  margin: 0;\n"
			."  padding: 0;\n"
			."  background-repeat: no-repeat;\n"
			.(!$extract ? "  background-image: url(\"". self::$fn_png ."\");\n" : '')
		."}\n\n";

		$y = $x = $i = $cnt_chars = $cnt = 0;
		$time = time();

		// creating icons
		foreach(self::$symbols as $id => &$sym) {
			$subst = false;
			if(isset($sym['subst'])) {
				// skin substitution
				if($sym['subst'] === -1) {
					// skip removed
					$i++;
					continue;
				}
				$subst = &$sym; // remember current element
				$sym = &$subst['subst']; // link to substitution
			}

			if(!isset($sym['css'])) {
				// make icon (if it's not created yet)
				$svg = preg_replace('#(</?s)ymbol#is', '$1vg', $sym["svg"]->asXML()); // <symbol> --> <svg>
				$svg = preg_replace('#([^>]+>)(.+)#is', '$1'. $svg_defs .'$2', $svg); // insert pre-defs
				$svg = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . $svg;

				if(self::$test >= 0) {
					$im_svg->readImageBlob($svg);  
					$im_svg->setImageFormat("png32");
				}

				$h = $y * self::$height;
				$w = $x * self::$width;
				if($extract && self::$test >= 0) {
					$sym['css'] = 'background-image: url("'. self::$subdir_png .'/'. $id .'.png")';
					$im_svg->writeImage("$dir_out_png/$id.png");
				}
				else {
					$sym['css'] = 'background-position: ' . ($x > 0 ? "-{$w}px" : '0') . ' ' . ($y > 0 ? "-{$h}px" : '0');
					if(self::$test >= 0)
						$im_png->compositeImage($im_svg, Imagick::COMPOSITE_OVER, $w, $h);
				}
				$x++;
				if($x >= $icons_per_line) {
					$y++;
				 	$x = 0;
				}
				$cnt++; // count created icons
			}

			$css .= '.emoji-'. ltrim($id, '0') .'{'. $sym['css'] ."}". ($subst !== false ? " /* subst {$sym['id']} */" : "") ."\n";
			if($subst !== false)
				$subst['css'] = $sym['css'];

			$i++;
			if(self::$test >= 0 && ($i >= $count || time() - $time >= 3)) {
				echo "$cnt of $cnt_img...\n";
				$time = time();
			}
		} // while

		echo "\n";

		echo "Saving css ", self::$dir_out, '/', self::$fn_css, "...\n";
		file_put_contents(self::$dir_out .'/'. self::$fn_css, $css);

		if(self::$test >= 0) {
			if(!$extract) {
				echo "Saving sprite-file ", self::$dir_out, '/', self::$fn_png, "...\n";
				$im_png->writeImage(self::$dir_out .'/'. self::$fn_png);
				$im_png->clear();
				$im_png->destroy();
			}
			$im_svg->clear();
			$im_svg->destroy();
		}

		echo "\nDone.\n";
	}
} // class emoji

emoji_create::main();
