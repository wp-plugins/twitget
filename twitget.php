<?php

	/* 
		Plugin Name: Twitget
		Plugin URI: http://wpplugz.is-leet.com
		Description: A simple widget that shows your recent tweets with fully customizable HTML output.
		Version: 1.3.2
		Author: Bostjan Cigan
		Author URI: http://bostjan.gets-it.net
		License: GPL v2
	*/ 

	// Wordpress formalities here ...
	
	// Lets register things
	require 'lib/tmhOAuth.php';
	require 'lib/tmhUtilities.php';
	
	register_activation_hook(__FILE__, 'twitget_install');
	register_deactivation_hook(__FILE__, 'twitget_uninstall');
	add_action('admin_menu', 'twitget_admin_menu_create');
	add_action('widgets_init', create_function('', 'return register_widget("simple_tweet_widget");')); // Register the widget
	add_shortcode('twitget', 'twitget_shortcode_handler');
	
	global $twitget_plugin_install_options;
	$twitget_plugin_install_options = array(
		'twitter_username' => '',
		'twitter_data' => NULL,
		'last_access' => time(),
		'time_limit' => 5,
		'number_of_tweets' => 5,
		'show_avatar' => true,
		'after_image_html' => '<ul>',
		'before_tweets_html' => '',
		'tweet_start_html' => '<li>',
		'tweet_middle_html' => '<br />',
		'tweet_end_html' => '</li>',
		'after_tweets_html' => '</ul>',
		'time_format' => 'D jS M y H:i',
		'show_powered_by' => false,
		'version' => '1.32',
		'consumer_key' => '',
		'consumer_secret' => '',
		'user_token' => '',
		'user_secret' => '',
		'mode' => 0,
		'show_retweets' => false,
		'exclude_replies' => false,
		'use_custom' => false,
		'custom_string' => '<img class="alignleft" src="{$profile_image}">
<a href="https://www.twitter.com/{$user_twitter_name}">{$user_twitter_name}</a>
<br />
{$user_description}
<ul class="pages">
{$tweets_start}
	<li>{$tweet_text}<br />{$tweet_time}</li>
{$tweets_end}
</ul>'

	);
	
	// Get current options
	$plugin_options_settings = get_option('twitget_settings');

	// Check if version is smaller and update
	if(is_array($plugin_options_settings) && isset($plugin_options_settings['version'])) { 
		if(((float) ($plugin_options_settings['version'])) < 1.32) {
			twitget_update();
		}
	}
	
	function twitget_install() {
		global $twitget_plugin_install_options;
		add_option('twitget_settings', $twitget_plugin_install_options);
	}
	
	function twitget_update() {
		
		global $twitget_plugin_install_options;
		$plugin_options_settings = get_option('twitget_settings');		
		
		if((float) $plugin_options_settings['version'] < 1.32) {
			foreach($twitget_plugin_install_options as $key => $value) {
				$plugin_options_settings[$key] = (isset($plugin_options_settings[$key]) && strcmp($key, "version") != 0) ? $plugin_options_settings[$key] : $value;
			}
			update_option('twitget_settings', $plugin_options_settings);
		}
		
	}
	
	function twitget_uninstall() {
		delete_option('twitget_settings');
	}

	function twitget_admin_menu_create() {
		add_options_page('Twitget Settings', 'Twitget', 'administrator', __FILE__, 'twitget_settings');	
	}

	// Shortcode function
	function twitget_shortcode_handler($attributes, $content = null) {
		return show_recent_tweets();
	}

	function twitter_status_11() {
	
		$options = get_option('twitget_settings', true);

		$tmhOAuth = new tmhOAuth(
									array(
										'consumer_key' => $options['consumer_key'],
										'consumer_secret' => $options['consumer_secret'],
										'user_token' => $options['user_token'],
										'user_secret' => $options['user_secret'],
										'curl_ssl_verifypeer' => false 
									)
								);
 
		$request_array = array();
		$request_array['screen_name'] = $options['twitter_username'];
		$request_array['include_rts'] = $options['show_retweets'];
		$request_array['exclude_replies'] = $options['exclude_replies'];
 
		$code = $tmhOAuth->request('GET', $tmhOAuth->url('1.1/statuses/user_timeline'), $request_array);
 
		$response = $tmhOAuth->response['response'];
		$tweets = json_decode($response, true);
		
		$options['twitter_data'] = $tweets;

		update_option('twitget_settings', $options);
	
	}
	
	function twitter_status() {  

		$options = get_option('twitget_settings', true);
		$twitter_id = $options['twitter_username'];
		$number_limit = $options['number_of_tweets'];
		$curl = curl_init();
		$url = "http://api.twitter.com/1/statuses/user_timeline/{$twitter_id}.json?count={$number_limit}";
		if($options['show_retweets']) {
			$url = $url.'&include_rts=1';
		}
		if(!$options['exclude_replies']) {
			$url = $url.'&exclude_replies=1';
		}
		
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$tweets = json_decode(curl_exec($curl), true);
		curl_close($curl);
		
		$options['twitter_data'] = $tweets;			

		update_option('twitget_settings', $options);

	}
 
	function process_links($text) {

		$text = preg_replace('@(https?://([-\w\.]+)+(d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', '<a href="$1">$1</a>',  $text);
		$text = preg_replace('/@(\w+)/', '<a href="http://twitter.com/$1">@$1</a>', $text);
		$text = preg_replace('/\s#(\w+)/', ' <a href="http://search.twitter.com/search?q=%23$1">#$1</a>', $text);
		return $text;

	}
 
	function show_recent_tweets() {

		$options = get_option('twitget_settings', true);
		$get_data = false;
		
		if(!isset($options['twitter_data'])) {
			$get_data = true;
		}
		
		if(time() - $options['last_access'] > $options['time_limit'] * 60) {
			$get_data = true;
			$options['last_access'] = time();
			update_option('twitget_settings', $options);
		}
		
		if($get_data) {
			if($options['mode'] == 0) {
				twitter_status();
			}
			else {
				twitter_status_11();			
			}
		}
		
		$options = get_option('twitget_settings', true);
		$tweets = $options['twitter_data'];
		$limit = $options['number_of_tweets'];

		$image_url = $tweets[0]['user']['profile_image_url']; // {$profile_image}
		$twitter_username = $tweets[0]['user']['screen_name']; // {$user_twitter_name}
		$twitter_username_real = $tweets[0]['user']['name']; // {$user_real_name}
		$twitter_user_url = $tweets[0]['user']['url']; // {$url}
		$twitter_user_description = $tweets[0]['user']['description']; // {$user_description}
		$twitter_follower_count = $tweets[0]['user']['followers_count']; // {$follower_count}
		$twitter_friends_count = $tweets[0]['user']['friends_count']; // {$friends_count}
		$twitter_user_location = $tweets[0]['user']['location']; // {$user_location}		

		$result = "";

		if(!$options['use_custom']) {

			if($options['show_avatar']) {
				$result = $result.$options['before_tweets_html'].'<img class="alignleft" src="'.$image_url.'"><span class="twitter_link"><a href="https://www.twitter.com/'.$twitter_username.'">@'.$twitter_username.'</a></span><br />'.$twitter_user_description.$options['after_image_html'];
			}
			else {
				$result = $result.$options['before_tweets_html'].$options['after_image_html'];
			}

			$i = 0;
			foreach($tweets as $tweet) {
				$result = $result.$options['tweet_start_html'];
				$tweet_text = $tweet['text'];
				$link_processed = "";
				if(isset($tweet['retweeted_status'])) {
					$first = current(explode(":", $tweet_text));
					$whole_tweet = $first.": ";
					$whole_tweet .= $tweet['retweeted_status']['text'];
					$link_processed = process_links($whole_tweet);
				}
				else {
					$link_processed = process_links($tweet['text']);
				}
				$result = $result.$link_processed.$options['tweet_middle_html'];
				$date = date($options['time_format'], strtotime($tweet['created_at']));
				if($options['show_relative_time']) {
					$date = relativeTime(strtotime($date));
				}
				$result = $result.$date;
				$result = $result.$options['tweet_end_html'];
				if($i == $limit - 1) {
					break;
				}
				$i = $i + 1;
			}
		
			$result = $result.$options['after_tweets_html'];
		
		}

		// Use custom HTML and output
		else {

			$custom_string = $options['custom_string'];
			$feed_string = twitget_get_substring($custom_string, "{\$tweets_start}", "{\$tweets_end}");
		
			$feed_whole_string = "";

			$i = 0;
			foreach($tweets as $tweet) {
				$tweet_text = $tweet['text'];
				$tweet_location = $tweet['place']['full_name'];
				$link_processed = "";
				if(isset($tweet['retweeted_status'])) {
					$first = current(explode(":", $tweet_text));
					$whole_tweet = $first.": ";
					$whole_tweet .= $tweet['retweeted_status']['text'];
					$link_processed = process_links($whole_tweet);
				}
				else {
					$link_processed = process_links($tweet['text']);
				}
				$date = date($options['time_format'], strtotime($tweet['created_at']));
				if($options['show_relative_time']) {
					$date = relativeTime(strtotime($date));
				}
				$feed_string_tmp = str_replace("{\$tweet_text}", $link_processed, $feed_string);
				$feed_string_tmp = str_replace("{\$tweet_time}", $date, $feed_string_tmp);
				$feed_string_tmp = str_replace("{\$tweet_location}", $tweet_location, $feed_string_tmp);
				$feed_whole_string .= $feed_string_tmp;
				if($i == $limit - 1) {
					break;
				}
				$i = $i + 1;
			}
			
			$feed_start = "{\$tweets_start}";
			$feed_end = "{\$tweets_end}";

			$start_pos = strrpos($custom_string, $feed_start);
			$end_pos = strrpos($custom_string, $feed_end) + strlen($feed_end);
			$tag_length = $end_pos - $start_pos + 1;

			$feed_string = substr_replace($custom_string, $feed_whole_string, $start_pos, $tag_length);
			$feed_string = str_replace("{\$profile_image}", $image_url, $feed_string);
			$feed_string = str_replace("{\$user_twitter_name}", $twitter_username, $feed_string);
			$feed_string = str_replace("{\$user_real_name}", $twitter_username_real, $feed_string);
			$feed_string = str_replace("{\$url}", $twitter_user_url, $feed_string);
			$feed_string = str_replace("{\$user_description}", $twitter_user_description, $feed_string);
			$feed_string = str_replace("{\$follower_count}", $twitter_follower_count, $feed_string);
			$feed_string = str_replace("{\$friends_count}", $twitter_friends_count, $feed_string);
			$feed_string = str_replace("{\$user_location}", $twitter_user_location, $feed_string);
			$feed_string = str_replace("{\$tweet_location}", $tweet_location, $feed_string);

			$result = $feed_string;

		}

		if(isset($tweets['errors'][0]['code'])) {
			$result = $options['before_tweets_html'].'<p>The Twitter feed is currently unavailable or the username does not exist.</p>';
		}
		
		if($options['show_powered_by']) {
			$result = $result.'<p>Powered by <a href="http://wpplugz.is-leet.com">wpPlugz</a></p>';
		}
		
		echo $result;

	}

	// Get substring between two strings
	function twitget_get_substring($string, $start, $end) {

		$pos = stripos($string, $start);
		$str = substr($string, $pos);
		$str_two = substr($str, strlen($start));
		$second_pos = stripos($str_two, $end);
		$str_three = substr($str_two, 0, $second_pos);
		$unit = trim($str_three);
		
		return $unit;
	}

	function relativeTime($date) {

		$now = time();
		$diff = $now - $date;

		if ($diff < 60) {
			return sprintf($diff > 1 ? '%s seconds ago' : 'A second ago', $diff);
		}

		$diff = floor($diff/60);

		if ($diff < 60) {
			return sprintf($diff > 1 ? '%s minutes ago' : 'One minute ago', $diff);
		}

		$diff = floor($diff/60);

		if ($diff < 24) {
			return sprintf($diff > 1 ? '%s hours ago' : 'An hour ago', $diff);
		}

		$diff = floor($diff/24);

		if ($diff < 7) {
			return sprintf($diff > 1 ? '%s days ago' : 'Yesterday', $diff);
		}

		if ($diff < 30) {
			$diff = floor($diff / 7);
			return sprintf($diff > 1 ? '%s weeks ago' : 'One week ago', $diff);
		}

		$diff = floor($diff/30);

		if ($diff < 12) {
			return sprintf($diff > 1 ? '%s months ago' : 'Last month', $diff);
		}

		$diff = date('Y', $now) - date('Y', $date);

		return sprintf($diff > 1 ? '%s years ago' : 'Last year', $diff);

	}	
		
	function twitget_settings() {
	
		$twitget_settings = get_option('twitget_settings', true);
		$message = '';
		
		if(isset($_POST['twitget_username'])) {
		
			$show_powered = $_POST['twitget_show_powered'];
			$show_avatar = $_POST['twitget_show_avatar'];
			$show_retweets = $_POST['twitget_retweets'];
			$twitget_exclude = $_POST['twitget_exclude_replies'];
			$twitget_relative = $_POST['twitget_relative_time'];
			$twitget_custom = $_POST['twitget_use_custom'];
		
			$twitget_settings['twitter_username'] = stripslashes($_POST['twitget_username']);
			$twitget_settings['time_limit'] = (int) $_POST['twitget_refresh'];
			$twitget_settings['number_of_tweets'] = (int) $_POST['twitget_number'];
			$twitget_settings['show_avatar'] = (isset($show_avatar)) ? true : false;
			$twitget_settings['time_format'] = stripslashes($_POST['twitget_time']);
			$twitget_settings['show_powered_by'] = (isset($show_powered)) ? true : false;
			$twitget_settings['tweet_start_html'] = stripslashes($_POST['twitget_before_tweets_html']);
			$twitget_settings['tweet_middle_html'] = stripslashes($_POST['twitget_tweet_middle_html']);
			$twitget_settings['tweet_end_html'] = stripslashes($_POST['twitget_tweet_end_html']);
			$twitget_settings['after_tweets_html'] = stripslashes($_POST['twitget_after_tweets_html']);
			$twitget_settings['after_image_html'] = stripslashes($_POST['twitget_after_image_html']);
			$twitget_settings['before_tweets_html'] = stripslashes($_POST['twitget_before_profile_html']);
			$twitget_settings['consumer_key'] = stripslashes($_POST['twitget_consumer_key']);
			$twitget_settings['consumer_secret'] = stripslashes($_POST['twitget_consumer_secret']);
			$twitget_settings['user_token'] = stripslashes($_POST['twitget_user_token']);
			$twitget_settings['user_secret'] = stripslashes($_POST['twitget_user_secret']);
			$twitget_settings['mode'] = (int) ($_POST['twitget_api']);
			$twitget_settings['show_retweets'] = (isset($show_retweets)) ? true : false;
			$twitget_settings['exclude_replies'] = (isset($twitget_exclude)) ? true : false;
			$twitget_settings['show_relative_time'] = (isset($twitget_relative)) ? true : false;
			$twitget_settings['use_custom'] = (isset($twitget_custom)) ? true : false;
			$twitget_settings['custom_string'] = stripslashes(html_entity_decode($_POST['twitget_custom_output']));
			update_option('twitget_settings', $twitget_settings);
			$message = "Settings updated.";
		}

		$twitget_options = get_option('twitget_settings', true);
		
?>

		<div id="icon-options-general" class="icon32"></div><h2>Twitget Settings</h2>
<?php

		if(strlen($message) > 0) {
		
?>

			<div id="message" class="updated">
				<p><strong><?php echo $message; ?></strong></p>
			</div>

<?php
			
		}

?>
        
                <form method="post" action="">
				<table class="form-table">
					<tr>
						<th scope="row"><img src="<?php echo plugin_dir_url(__FILE__).'twitter.png'; ?>" height="96px" width="96px" /></th>
						<td>
							<p>Thank you for using this plugin. If you like the plugin, you can <a href="http://gum.co/twitget" target="_blank">buy me a cup of coffee</a> :)</p> 
							<p>Visit the official website @ <a href="http://wpplugz.is-leet.com">wpPlugz</a>.</p>
							<p>This plugin uses the <a href="https://github.com/themattharris/tmhOAuth">tmhOAuth</a> library by Matt Harris.</p>
                        </td>
					</tr>		
					<tr>
						<th scope="row"><label for="twitget_username">Twitter username</label></th>
						<td>
							<input type="text" name="twitget_username" id="twitget_username" value="<?php echo esc_attr($twitget_options['twitter_username']); ?>" />
							<br />
            				<span class="description">Your Twitter username.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_api">Twitter API</label></th>
						<td>
							<select name="twitget_api" id="twitget_api">
								<option value="0" <?php if($twitget_options['mode'] == 0) { ?> selected="selected" <?php } ?>>API 1.0</option>
								<option value="1" <?php if($twitget_options['mode'] == 1) { ?> selected="selected" <?php } ?>>API 1.1</option>
							</select>
							<br />
            				<span class="description">Set the API you will be using, note that API 1.0 expires on March 2013. To use API 1.1, create a Twitter aplication on the <a href="https://dev.twitter.com/apps/new" target="_blank">
							Twitter's developer page</a>. If you're lost, check <a href="http://youtu.be/noB3P-K-wb4" target="_blank">this tutorial</a> on Youtube.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_data">Twitter API 1.1 data</label></th>
						<td>
							<table class="form-table">
							<tr>
								<th scope="row"><label for="twitget_consumer_key">Consumer key</label></th>
								<td>
									<input type="text" name="twitget_consumer_key" id="twitget_consumer_key" size="70" value="<?php echo $twitget_options['consumer_key']; ?>" /><br />
									<span class="description">Enter your consumer key here.</span>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="twitget_consumer_secret">Consumer secret</label></th>
								<td>
									<input type="text" name="twitget_consumer_secret" id="twitget_consumer_secret" size="70" value="<?php echo $twitget_options['consumer_secret']; ?>" /><br />
									<span class="description">Enter your consumer secret key here.</span>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="twitget_user_token">Access token</label></th>
								<td>
									<input type="text" name="twitget_user_token" id="twitget_user_token" size="70" value="<?php echo $twitget_options['user_token']; ?>" /><br />
									<span class="description">Enter your access token key here.</span>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="twitget_user_secret">Access token secret</label></th>
								<td>
									<input type="text" name="twitget_user_secret" id="twitget_user_secret" size="70" value="<?php echo $twitget_options['user_secret']; ?>" /><br />
									<span class="description">Enter your access token secret key here.</span>
								</td>
							</tr>							
							</table>
							<span class="description">If you're using API 1.1, enter your keys here.</span>							
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_refresh">Twitter feed refresh (in minutes)</label></th>
						<td>
							<input type="text" name="twitget_refresh" id="twitget_refresh" value="<?php echo $twitget_options['time_limit']; ?>" />
							<br />
            				<span class="description">In how many minutes does the Twitter feed refresh.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_number">Number of tweets</label></th>
						<td>
							<input type="text" name="twitget_number" id="twitget_number" value="<?php echo $twitget_options['number_of_tweets']; ?>" />
							<br />
            				<span class="description">How many tweets are shown.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_time">Time format</label></th>
						<td>
							<input type="text" name="twitget_time" id="twitget_time" value="<?php echo esc_html($twitget_options['time_format']); ?>" />
							<br />
            				<span class="description">The time format.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_retweets">Show retweets</label></th>
						<td>
		    	            <input type="checkbox" name="twitget_retweets" id="twitget_retweets" value="true" <?php if($twitget_options['show_retweets'] == true) { ?>checked="checked"<?php } ?> />
							<br />
            				<span class="description">Check this if you want to include retweets in your feed.</span>
						</td>
					</tr>		
					<tr>
						<th scope="row"><label for="twitget_exclude_replies">Exclude replies</label></th>
						<td>
		    	            <input type="checkbox" name="twitget_exclude_replies" id="twitget_exclude_replies" value="true" <?php if($twitget_options['exclude_replies'] == true) { ?>checked="checked"<?php } ?> />
							<br />
            				<span class="description">Check this if you want to exclude replies in your feed.</span>
						</td>
					</tr>		
					<tr>
						<th scope="row"><label for="twitget_show_avatar">Show profile box</label></th>
						<td>
		    	            <input type="checkbox" name="twitget_show_avatar" id="twitget_show_avatar" value="true" <?php if($twitget_options['show_avatar'] == true) { ?>checked="checked"<?php } ?> />
							<br />
            				<span class="description">Show the profile box before tweets (including the avatar).</span>
						</td>
					</tr>		
					<tr>
						<th scope="row"><label for="twitget_relative_time">Show relative time</label></th>
						<td>
		    	            <input type="checkbox" name="twitget_relative_time" id="twitget_relative_time" value="true" <?php if($twitget_options['show_relative_time'] == true) { ?>checked="checked"<?php } ?> />
							<br />
            				<span class="description">Show relative time of tweets (for instance 5 minutes ago).</span>
						</td>
					</tr>		
					<tr>
						<th scope="row"><label for="twitget_before_profile_html">HTML before all Twitget output</label></th>
						<td>
							<input type="text" name="twitget_before_profile_html" id="twitget_before_profile_html" value="<?php echo esc_html($twitget_options['before_tweets_html']); ?>" />
							<br />
            				<span class="description">HTML that it outputted before any of the Twitter feed and profile is shown.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_after_image_html">HTML after profile box</label></th>
						<td>
							<input type="text" name="twitget_after_image_html" id="twitget_after_image_html" value="<?php echo esc_html($twitget_options['after_image_html']); ?>" />
							<br />
            				<span class="description">The HTML that is outputted after the profile box.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_before_tweets_html">HTML before single tweet</label></th>
						<td>
							<input type="text" name="twitget_before_tweets_html" id="twitget_before_tweets_html" value="<?php echo esc_html($twitget_options['tweet_start_html']); ?>" />
							<br />
            				<span class="description">HTML that it outputted before the tweet.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_tweet_end_html">HTML after single tweet</label></th>
						<td>
							<input type="text" name="twitget_tweet_end_html" id="twitget_tweet_end_html" value="<?php echo esc_html($twitget_options['tweet_end_html']); ?>" />
							<br />
            				<span class="description">HTML that it outputted after a single tweet.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_tweet_middle_html">HTML before tweet date</label></th>
						<td>
							<input type="text" name="twitget_tweet_middle_html" id="twitget_tweet_middle_html" value="<?php echo esc_html($twitget_options['tweet_middle_html']); ?>" />
							<br />
            				<span class="description">HTML that it outputted before the date.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_after_tweets_html">HTML at the end of output</label></th>
						<td>
							<input type="text" name="twitget_after_tweets_html" id="twitget_after_tweets_html" value="<?php echo esc_html($twitget_options['after_tweets_html']); ?>" />
							<br />
            				<span class="description">HTML that it outputted after everything.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_show_powered">Show powered by message</label></th>
						<td>
		    	            <input type="checkbox" name="twitget_show_powered" id="twitget_show_powered" value="true" <?php if($twitget_options['show_powered_by'] == true) { ?>checked="checked"<?php } ?> />
							<br />
            				<span class="description">Show powered by message, if you decide not to show it, please consider a <a href="http://gum.co/twitget" target="_blank">donation</a>.</span>
						</td>
					</tr>		
				</table>

				<h3>Advanced options</h3>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="twitget_use_custom">Use custom output</label></th>
						<td>
		    	            <input type="checkbox" name="twitget_use_custom" id="twitget_use_custom" value="true" <?php if($twitget_options['use_custom'] == true) { ?>checked="checked"<?php } ?> />
							<br />
            				<span class="description">Check this if you want to use a custom output defined in the textarea below. All HTML options from above will be ignored.</span>
						</td>
					</tr>		
					<tr>
						<th scope="row"><label for="twitget_instructions">Instructions and variables</label></th>
						<td><span class="description">
							<p>You can enter custom HTML in the box bellow and achieve the output you want.</p>
							<p>When marking the output of your twitter feed you must include {$tweets_start} at the start of your twitter feed and {$tweets_end} in the end.</p> 

							<strong><p>Available variables</p></strong>
							{$tweet_text} - the text of the tweet<br />
							{$tweet_time} - the time of the tweet<br />
							{$tweet_location} - the location of the tweet (example: Budapest)<br />
							{$profile_image} - the url to the profile image of the user<br />
							{$user_real_name} - the real name of the user<br />
							{$user_twitter_name} - username of the twitter user<br />
							{$url} - website url of the user<br />
							{$user_description} - description of the user<br />
							{$user_location} - user location<br />
							{$follower_count} - number of followers<br />
							{$friends_count} - number of friends
						</span>
						</td>
					</tr>		
					<tr>
						<th scope="row"><label for="twitget_custom_output">Use custom output</label></th>
						<td>
			    	            <textarea rows="10" cols="100" name="twitget_custom_output" id="twitget_custom_output" /><?php echo htmlentities($twitget_options['custom_string']); ?></textarea>
					<br />
            				<span class="description">Enter custom HTML for Twitget output.</span>
						</td>
					</tr>		
				</table>
				<p><input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Update options') ?>" /></p>
				</form>


<?php

	}
		
	// Here, the widget code begins
	class simple_tweet_widget extends WP_Widget {
		
		function simple_tweet_widget() {
			$widget_ops = array('classname' => 'simple_tweet_widget', 'description' => 'Display your recent tweets.' );			
			$this->WP_Widget('simple_tweet_widget', 'Twitget', $widget_ops);
		}
		
		function widget($args, $instance) {
			
			extract($args);
			$title = apply_filters('widget_title', $instance['title']);
			
			echo $before_widget;

			if($title) {
				echo $before_title . $title . $after_title;
			}
			
			// The widget code and the widgeet output
			
			show_recent_tweets();
			
			// End of widget output
			
			echo $after_widget;
			
		}
		
	    function update($new_instance, $old_instance) {		
			$instance = $old_instance;
			$instance['title'] = strip_tags($new_instance['title']);
	        return $instance;
    	}
		
		function form($instance) {	

        	$title = esc_attr($instance['title']);
		
?>

			<p>
				<label for="<?php echo $this->get_field_id('title'); ?>">
					<?php _e('Title: '); ?>
	            </label> 
				<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
			</p>

<?php 

		}

	}
	
?>
