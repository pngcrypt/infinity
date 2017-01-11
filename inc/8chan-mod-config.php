<?php

Vi::$config['mod']['show_ip']          = GLOBALVOLUNTEER;
Vi::$config['mod']['show_ip_less']     = BOARDVOLUNTEER;
Vi::$config['mod']['manageusers']      = GLOBALVOLUNTEER;
Vi::$config['mod']['noticeboard_post'] = GLOBALVOLUNTEER;
Vi::$config['mod']['search']           = GLOBALVOLUNTEER;
Vi::$config['mod']['clean_global']     = GLOBALVOLUNTEER;
Vi::$config['mod']['view_notes']       = DISABLED;
Vi::$config['mod']['create_notes']     = DISABLED;
Vi::$config['mod']['edit_config']      = DISABLED;
Vi::$config['mod']['debug_recent']     = ADMIN;
Vi::$config['mod']['debug_antispam']   = ADMIN;
Vi::$config['mod']['noticeboard_post'] = ADMIN;
Vi::$config['mod']['modlog']           = GLOBALVOLUNTEER;
Vi::$config['mod']['mod_board_log']    = MOD;
Vi::$config['mod']['editpost']         = BOARDVOLUNTEER;
Vi::$config['mod']['edit_banners']     = MOD;
Vi::$config['mod']['edit_assets']      = MOD;
Vi::$config['mod']['edit_flags']       = MOD;
Vi::$config['mod']['edit_settings']    = MOD;
Vi::$config['mod']['edit_volunteers']  = MOD;
Vi::$config['mod']['edit_tags']        = MOD;
Vi::$config['mod']['clean']            = BOARDVOLUNTEER;
// new perms

Vi::$config['mod']['ban']                  = BOARDVOLUNTEER;
Vi::$config['mod']['bandelete']            = BOARDVOLUNTEER;
Vi::$config['mod']['unban']                = BOARDVOLUNTEER;
Vi::$config['mod']['deletebyip']           = BOARDVOLUNTEER;
Vi::$config['mod']['sticky']               = BOARDVOLUNTEER;
Vi::$config['mod']['cycle']                = BOARDVOLUNTEER;
Vi::$config['mod']['lock']                 = BOARDVOLUNTEER;
Vi::$config['mod']['postinlocked']         = BOARDVOLUNTEER;
Vi::$config['mod']['bumplock']             = BOARDVOLUNTEER;
Vi::$config['mod']['view_bumplock']        = BOARDVOLUNTEER;
Vi::$config['mod']['bypass_field_disable'] = BOARDVOLUNTEER;
Vi::$config['mod']['view_banlist']         = BOARDVOLUNTEER;
Vi::$config['mod']['view_banstaff']        = BOARDVOLUNTEER;
Vi::$config['mod']['public_ban']           = BOARDVOLUNTEER;
Vi::$config['mod']['recent']               = BOARDVOLUNTEER;
Vi::$config['mod']['ban_appeals']          = BOARDVOLUNTEER;
Vi::$config['mod']['view_ban_appeals']     = BOARDVOLUNTEER;
Vi::$config['mod']['view_ban']             = BOARDVOLUNTEER;
Vi::$config['mod']['reassign_board']       = GLOBALVOLUNTEER;
Vi::$config['mod']['move']                 = GLOBALVOLUNTEER;
Vi::$config['mod']['pm_all']               = GLOBALVOLUNTEER;
Vi::$config['mod']['shadow_capcode']       = 'Global Volunteer';

// Mod pages assignment
Vi::$config['mod']['custom_pages']['/tags/(\%b)']       = '8_tags';
Vi::$config['mod']['custom_pages']['/reassign/(\%b)']   = '8_reassign';
Vi::$config['mod']['custom_pages']['/volunteers/(\%b)'] = '8_volunteers';
Vi::$config['mod']['custom_pages']['/flags/(\%b)']      = '8_flags';
Vi::$config['mod']['custom_pages']['/banners/(\%b)']    = '8_banners';
Vi::$config['mod']['custom_pages']['/settings/(\%b)']   = '8_settings';
Vi::$config['mod']['custom_pages']['/assets/(\%b)']     = '8_assets';
