<?php

	$path = dirname(dirname(dirname(dirname(__FILE__))));
	require($path.'/wp-load.php');

    if(!session_id()) {
        session_start();
    }
	
	$options = get_option('twitget_settings');
	
	if(isset($_GET['time'])) {
		$time = $_GET['time'];
		if(is_numeric($time)) {
			$_SESSION['timezone'] = $time;
		}
	}

?>