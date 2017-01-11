<?php

include "inc/functions.php";

if(isset($_GET['board']) && !empty($_GET['board'])) {
	Vi::$current_locale = BoardLocale(basename($_GET['board']));
	init_locale(Vi::$current_locale);
}

chanCaptcha::get();
