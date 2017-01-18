#!/usr/bin/php
<?php

class emoji_create {
	public static $svg_sprites = 'tools/emoji/svg/emojione.sprites.svg';	
	public static $dir_out = 'tools/emoji';
	public static $subdir_png = "png-%dx%d";    // subdir for extracting icons (-e)  (%d - for substiotuion width & height )
	public static $fn_png = "emoji.%dx%d.png";  // output png sprite file
	public static $fn_css = "emoji.%dx%d.css";  // output css file 
	public static $fn_test = "test.%dx%d.html";  // output html test file

	private static $skins = [
		0 => ["code" => ""],		// default (yellow)
		1 => ["code" => "1f3fb"],	// light pink
		2 => ["code" => "1f3fc"],	// pink
		3 => ["code" => "1f3fd"],	// light brown
		4 => ["code" => "1f3fe"],	// brown
		5 => ["code" => "1f3ff"],	// dark brown
	];

	private static $symbols = [];

	//-----------------------------------------
	static function Usage($err = "") {
		if($err !== "")
			echo "$err\n\n";
		echo "Usage: emoji-create.php [-w N] [-l N] [-s SKINS] [-e]\n\n";
		echo "Where:\n";
		echo "  -w N - width of icon (default: 16; minimum: 4); height == width\n";
		echo "  -l N - limit max bytes of one char in UTF-8 (default: 0 == unlimited)\n";
		echo "  -e - extract each icon to .png file (otherwise sprite-file will be used)\n";
		echo "  -s SKINS - list of skins substitutions (single number == for all)\n";
		echo "    Examples:\n";
		echo "      -s 0 -- replace all skins (1..5) by 0-skin (yellow)\n";
		echo "      -s -1 -- remove all skinned icons\n";
		echo "      -s 0:2,3:2,5:4 -- replace 0 to 2, 3 to 2, 5 to 4\n";
		echo "      (skin number should be within 0..5)\n";
		die;
	}

	//-----------------------------------------
	static function getSymSkin($sym) {
		$uc = $sym["unicode"];
		if(count($uc) == 1) {
			// 1-word unicode: checking other skinned-items which starts with same unicode
			foreach(self::$skins as $id => $skin) {
				$idx = $uc[0] .'-'. $skin['code'];
				if(isset(self::$symbols[$idx]))
					return 0; // other items found, it's default skin
			}
			return -1; // no other items, not skinned icon
		}

		// more than 1-word unicode
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
	static function main() {

		// command line pares -->
		$opts = getopt('hw:l:s:e', ['help']);
		if(isset($opts['h']) || isset($opts['help']))
			self::Usage();

		if(!extension_loaded('imagick'))
			die('IMagick needed.');

		$width = $height = 16;
		if(isset($opts['w']))
			$height = $width = $opts['w']|0;
		if($width < 4)
			self::Usage("ERROR: Bad width of icon: ". $opts['w']);

		$limit = isset($opts['l']) ? abs($opts['l']|0) : 0;
		$extract = isset($opts['e']);

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
		self::$subdir_png = sprintf(self::$subdir_png, $width, $height);
		self::$fn_png = sprintf(self::$fn_png, $width, $height);
		self::$fn_css = sprintf(self::$fn_css, $width, $height);
		self::$fn_test = sprintf(self::$fn_test, $width, $height);

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
		$regexp = [];
		$html = "";
		$i = 0;
		foreach($sprites->symbol as $sym) {
			$id = strtolower($sym['id']);
			$uc = explode('-', $id);
			array_shift($uc); // delete 'emoji' prefix
			if(!count($uc))
				continue;
			$x = self::getMaxBytes($uc);
			if($x < 2 || ($limit && $x > $x)) {
				// echo implode('-', $uc), ': ', $x, '; ';
				continue;
			}
			$uc = array_map(function($v) {
				return preg_replace("/^0+/", '', $v);
			}, $uc);
			$id = implode('-', $uc);
			$sym["width"] = $width;
			$sym["height"] = $height;
			self::$symbols[$id] = [
				"id" => $id,
				"svg" => $sym,
				"unicode" => $uc,
				"entity" => "&#x".implode(";&#x", $uc).";", // html entity
			];
			$i++;

			// debug -->
			/*$y = html_entity_decode(self::$symbols[$id]['entity'], ENT_COMPAT, "UTF-8");
			$x = substr(json_encode($y), 1, -1);
			$html .= $y.' ';
			$regexp[$x] = $id;*/
			// <-- debug
		}
		ksort(self::$symbols);
		ksort($regexp);

		// debug -->
		/*ob_start();
		print_r($regexp);
		$x = ob_get_clean();
		$x = preg_replace('/=>[\s\n]+/', ' => ', $x); // remove \n after =>
		$x = preg_replace('/{[\s\n]+}/', '{}', $x); // don't open empty arrays
		file_put_contents(self::$dir_out .'/regexp.txt', $x);
		file_put_contents(self::$dir_out .'/chars.txt', $html);*/
		// die;
		// <-- debug

		$cnt_img = $cnt_chars = count(self::$symbols);

		if($count != $cnt_img)
			echo "Skipped: ", ($count - $cnt_img), " items: (1 < UTF-bytes", ($limit ? " <= $limit" : ""), ")\n\n";

		$count = $cnt_img;
		// die;

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
							$i = $sym['unicode'][0];
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
			echo "Used icons: $cnt_img of $count ($x removed, $y substituted)\n\n";
			$cnt_chars = $cnt_img + $y;
		}
		else
			echo "Used icons: $cnt_img\n\n";

		$svg_defs = $sprites->defs->asXML();
		$html_per_line = round(sqrt($cnt_chars));
		$icons_per_line = round(sqrt($cnt_img));
		$width_image = $width * $icons_per_line;
		$height_image = $height * ceil($cnt_img / $icons_per_line);

		if($extract) {
			$dir_out_png = self::$dir_out .'/'. self::$subdir_png;
			@mkdir($dir_out_png, 0777, true);
		}

		echo "Icon size: $width x $height px\n";
		if($extract)
			echo "Exctrating to $dir_out_png...\n";
		else
			echo "Creating PNG: $width_image x $height_image px ($icons_per_line icons per line)...\n";

		$transparent = new ImagickPixel('transparent');
		$im_svg = new Imagick(); // icon image
		$im_svg->setBackgroundColor($transparent);	

		if(!$extract) {
			$im_png = new Imagick(); // sprite container
			$im_png->newImage($width_image, $height_image, $transparent);
			$im_png->setImageFormat('png32'); 
		}

		$css = "/*\n"
			." * Emoji icons $width x $height px\n"
			.(!$extract	? " * Sprite file: $width_image x $height_image px ($icons_per_line icons per line)\n" : "")
			." *\n"
			." */\n\n"
			.".emoji {\n"
			."  display:inline-block;\n"
			."  width: {$width}px;\n"
			."  height: {$height}px;\n"
			."  font-size: inherit;\n"
			."  font-style: normal;\n"
			."  text-indent: -9999em;\n"
			."  line-height: normal;\n"
			."  vertical-align: middle;\n"
			."  border: 0;\n"
			."  margin: 0;\n"
			."  padding: 0;\n"
			."  background-repeat: no-repeat;\n"
			.(!$extract ? "  background-image: url(\"". self::$fn_png ."\");\n" : '')
		."}\n\n";

		$html = "<html>\n<head><meta charset='utf-8'><link rel='stylesheet' href='". self::$fn_css ."'></head>\n<body>\n";

		$y = $x = $i = $cnt_chars = $cnt = 0;
		$time = time();

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

				$im_svg->readImageBlob($svg);  
				$im_svg->setImageFormat("png32");

				$h = $y * $height;
				$w = $x * $width;
				if($extract) {
					$sym['css'] = 'background-image: url("'. self::$subdir_png .'/'. $id .'.png")';
					$im_svg->writeImage("$dir_out_png/$id.png");
				}
				else {
					$sym['css'] = 'background-position: ' . ($x > 0 ? "-{$w}px" : '0') . ' ' . ($y > 0 ? "-{$h}px" : '0');
					$im_png->compositeImage($im_svg, Imagick::COMPOSITE_OVER, $w, $h);
				}
				$x++;
				if($x >= $icons_per_line) {
					$y++;
				 	$x = 0;
				}
				$cnt++; // count created icons
			}

			$css .= '.emoji-'. $id .'{'. $sym['css'] ."}". ($subst !== false ? " /* subst {$sym['id']} */" : "") ."\n";
			$html .= '<i class="emoji emoji-'. $id .'">'. self::$symbols[$id]['entity'] .'</i> ';
			$cnt_chars++;
			if($cnt_chars >= $html_per_line) {
				$cnt_chars = 0;
			 	$html .= "<br>\n";
			}

			if($subst !== false) {
				$subst['css'] = $sym['css'];
			}

			$i++;
			if($i >= $count || time() - $time >= 3) {
				echo "$cnt of $cnt_img...\n";
				$time = time();
			}
		} // while

		echo "\n";
		$html .= "</body>\n</html>\n";

		echo "Saving css ", self::$dir_out, '/', self::$fn_css, "...\n";
		file_put_contents(self::$dir_out .'/'. self::$fn_css, $css);

		echo "Saving test ", self::$dir_out, '/', self::$fn_test, "...\n";
		file_put_contents(self::$dir_out .'/'. self::$fn_test, $html);

		if(!$extract) {
			echo "Saving sprite-file ", self::$dir_out, '/', self::$fn_png, "...\n";
			$im_png->writeImage(self::$dir_out .'/'. self::$fn_png);
			$im_png->clear();
			$im_png->destroy();
		}
		$im_svg->clear();
		$im_svg->destroy();

		echo "\nDone.\n";
	}
} // class emoji

emoji_create::main();
