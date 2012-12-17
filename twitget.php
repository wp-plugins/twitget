<?php

	/* 
		Plugin Name: Twitget
		Plugin URI: http://wpplugz.is-leet.com
		Description: A simple widget that shows your recent tweets with fully customizable HTML output.
		Version: 1.0
		Author: Bostjan Cigan
		Author URI: http://bostjan.gets-it.net
		License: GPL v2
	*/ 

	// Wordpress formalities here ...
	
	// Lets register things
	register_activation_hook(__FILE__, 'twitget_install');
	register_deactivation_hook(__FILE__, 'twitget_uninstall');
	add_action('admin_menu', 'twitget_admin_menu_create');
	add_action('widgets_init', create_function('', 'return register_widget("simple_tweet_widget");')); // Register the widget

	function twitget_install() {

		$plugin_options = array(
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
			'show_powered_by' => false
		);			

		add_option('twitget_settings', $plugin_options);
	
	}
	
	function twitget_uninstall() {
		delete_option('twitget_settings');
	}

	function twitget_admin_menu_create() {
		add_options_page('Twitget Settings', 'Twitget', 'administrator', __FILE__, 'twitget_settings');	
	}
	
	function twitter_status() {  

		$options = get_option('twitget_settings', true);
		$twitter_id = $options['twitter_username'];
		$number_limit = $options['number_of_tweets'];
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, "http://api.twitter.com/1/statuses/user_timeline/{$twitter_id}.json?count=".$number_limit);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$tweets = json_decode(curl_exec($curl), true);
		curl_close($curl);
		
		$options['twitter_data'] = $tweets;			

		update_option('twitget_settings', $options);

	}
 
	function process_links($text) {

		$text = preg_replace('@(https?://([-\w\.]+)+(d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', '<a href="$1">$1</a>',  $text);
		$text = preg_replace("#(^|[\n ])@([^ \"\t\n\r<]*)#ise", "'\\1<a href=\"http://www.twitter.com/\\2\" >@\\2</a>'", $text);
		$text = preg_replace("#(^|[\n ])\#([^ \"\t\n\r<]*)#ise", "'\\1<a href=\"https://twitter.com/search?q=%23\\2&src=hash\" >#\\2</a>'", $text);
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
			twitter_status();
		}
		
		$options = get_option('twitget_settings', true);
		$tweets = $options['twitter_data'];
		
		$result = "";

		$image_url = $tweets[0]['user']['profile_image_url'];
		$twitter_username = $tweets[0]['user']['screen_name'];

		if($options['show_avatar']) {
			$result = $result.$options['before_tweets_html'].'<img class="alignleft" src="'.$image_url.'"><span class="twitter_link"><a href="https://www.twitter.com/'.$twitter_username.'">@'.$twitter_username.'</a></span><br />'.$tweets[0]['user']['description'].$options['after_image_html'];
		}
		else {
			$result = $result.$options['before_tweets_html'].$options['after_image_html'];
		}

		$limit = $options['number_of_tweets'];
		
		$i = 0;
		foreach($tweets as $tweet) {
			$result = $result.$options['tweet_start_html'];
			$link_processed = process_links($tweet['text']);
			$result = $result.$link_processed.$options['tweet_middle_html'];
			$date = date($options['time_format'], strtotime($tweet['created_at']));
			$result = $result.$date;
			$result = $result.$options['tweet_end_html'];
			if($i == $limit - 1) {
				break;
			}
			$i = $i + 1;
		}
		
		$result = $result.$options['after_tweets_html'];
		
		if(isset($tweets['errors'][0]['code'])) {
			$result = $options['before_tweets_html'].'<p>The Twitter feed is currently unavailable or the username does not exist.</p>';
		}
		
		if($options['show_powered_by']) {
			$result = $result.'<p>Powered by <a href="http://wpplugz.is-leet.com">wpPlugz</a></p>';
		}
		
		echo $result;		

	}

	function twitget_settings() {
	
		$twitget_settings = get_option('twitget_settings');
		$message = '';
		
		if(isset($_POST['twitget_username'])) {
		
			$show_powered = $_POST['twitget_show_powered'];
			$show_avatar = $_POST['twitget_show_avatar'];
		
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
			update_option('twitget_settings', $twitget_settings);
			$message = "Settings updated.";
		}

		$twitget_options = get_option('twitget_settings');
		
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
							<p>Thank you for using this plugin. If you like the plugin, you can <a href="http://gum.co/twitget">buy me a cup of coffee</a><script type="text/javascript" src="https://gumroad.com/js/gumroad-button.js"></script><script type="text/javascript" src="https://gumroad.com/js/gumroad.js"></script> :)</p> 
							<p>Visit the official website @ <a href="http://wpplugz.is-leet.com">wpPlugz</a>.</p>
                        </td>
					</tr>		
					<tr>
						<th scope="row"><label for="twitget_username">Twitter username</label></th>
						<td>
							<input type="text" name="twitget_username" value="<?php echo esc_attr($twitget_options['twitter_username']); ?>" />
							<br />
            				<span class="description">Your Twitter username.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_refresh">Twitter feed refresh (in minutes)</label></th>
						<td>
							<input type="text" name="twitget_refresh" value="<?php echo $twitget_options['time_limit']; ?>" />
							<br />
            				<span class="description">In how many minutes does the Twitter feed refresh.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_number">Number of tweets</label></th>
						<td>
							<input type="text" name="twitget_number" value="<?php echo $twitget_options['number_of_tweets']; ?>" />
							<br />
            				<span class="description">How many tweets are shown.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_time">Time format</label></th>
						<td>
							<input type="text" name="twitget_time" value="<?php echo esc_html($twitget_options['time_format']); ?>" />
							<br />
            				<span class="description">The time format.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_show_avatar">Show profile box</label></th>
						<td>
		    	            <input type="checkbox" name="twitget_show_avatar" value="true" <?php if($twitget_options['show_avatar'] == true) { ?>checked="checked"<?php } ?> />
							<br />
            				<span class="description">Show the profile box before tweets (including the avatar).</span>
						</td>
					</tr>		
					<tr>
						<th scope="row"><label for="twitget_before_profile_html">HTML before all Twitget output</label></th>
						<td>
							<input type="text" name="twitget_before_profile_html" value="<?php echo esc_html($twitget_options['before_tweets_html']); ?>" />
							<br />
            				<span class="description">HTML that it outputted before any of the Twitter feed and profile is shown.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_after_image_html">HTML after profile box</label></th>
						<td>
							<input type="text" name="twitget_after_image_html" value="<?php echo esc_html($twitget_options['after_image_html']); ?>" />
							<br />
            				<span class="description">The HTML that is outputted after the profile box.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_before_tweets_html">HTML before single tweet</label></th>
						<td>
							<input type="text" name="twitget_before_tweets_html" value="<?php echo esc_html($twitget_options['tweet_start_html']); ?>" />
							<br />
            				<span class="description">HTML that it outputted before the tweet.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_tweet_end_html">HTML after single tweet</label></th>
						<td>
							<input type="text" name="twitget_tweet_end_html" value="<?php echo esc_html($twitget_options['tweet_end_html']); ?>" />
							<br />
            				<span class="description">HTML that it outputted after a single tweet.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_tweet_middle_html">HTML before tweet date</label></th>
						<td>
							<input type="text" name="twitget_tweet_middle_html" value="<?php echo esc_html($twitget_options['tweet_middle_html']); ?>" />
							<br />
            				<span class="description">HTML that it outputted before the date.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_after_tweets_html">HTML at the end of output</label></th>
						<td>
							<input type="text" name="twitget_after_tweets_html" value="<?php echo esc_html($twitget_options['after_tweets_html']); ?>" />
							<br />
            				<span class="description">HTML that it outputted after everything.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_show_powered">Show powered by message</label></th>
						<td>
		    	            <input type="checkbox" name="twitget_show_powered" value="true" <?php if($twitget_options['show_powered_by'] == true) { ?>checked="checked"<?php } ?> />
							<br />
            				<span class="description">Show powered by message, if you decide not to show it, please consider a <a href="http://gum.co/twitget">donation</a><script type="text/javascript" src="https://gumroad.com/js/gumroad-button.js"></script><script type="text/javascript" src="https://gumroad.com/js/gumroad.js"></script>.</span>
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
