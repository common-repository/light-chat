<?php
/**
 * @package light-chat
 * @version 1.0
 */
/*
Plugin Name: Light Chat
Plugin URI: http://wordpress.org/
Description: Very simple and light sidebar chat and does not waste your bandwidth.
Author: Djane Rey Mabelin
Version: 1.0
Author URI: http://blog.cdobiz.com
*/


global $light_chat_db_version;
$light_chat_db_version = "1.0";



// INSTALL

function lightchat_install () {
	global $wpdb;
	global $light_chat_db_version;
	$table_name = $wpdb->prefix . "lightchat";
   
   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
      
      $sql = "CREATE TABLE `".$table_name."` (
  `id` bigint(20) unsigned NOT NULL auto_increment,
  `timestamp` int(10) unsigned default NULL,
  `alias` varchar(10) default NULL,
  `ip` varchar(10) default NULL,
  `message` text,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($sql);

      $rows_affected = $wpdb->insert( $table_name, array( 'timestamp' => time(), 'alias' => "Djane", 'message' => 'Thanks for using "Light Chat"!','ip'=>$_SERVER['REMOTE_ADDR'] ) );
 
      add_option("light_chat_db_version", $light_chat_db_version);

   }
}
register_activation_hook(__FILE__,'lightchat_install');

// SEND INIT
function lightchat_init(){
	global $wpdb;
	$table_name = $wpdb->prefix . "lightchat";
	//message send
	if(isset($_POST['alias']) && isset($_POST['message']) && trim($_POST['alias'])!="" && trim(strip_tags($_POST['message']))!="" && isset($_POST['timestamp'])){	
		setcookie("lightchatAlias",$_POST['alias'],0,"/");
		$rows_affected = $wpdb->insert( $table_name, array( 'timestamp' => time(), 'alias' => $_POST['alias'], 'message' => strip_tags($_POST['message']),'ip'=>$_SERVER['REMOTE_ADDR'] ) );
		exit;
	}
	
	// updates
	if(isset($_POST['lightchat_update']) && (int)$_POST['lightchat_update']!=0){
		// clean up
		$result = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY timestamp DESC" );
		if(count($result)>=50){
			$last = end($result);
			$wpdb->get_results( "DELETE FROM $table_name WHERE timestamp<".$last->timestamp );
		}
		
		$startTime = time();
		while((time()-$startTime)<=25){			
			//the loop
			$history = $wpdb->get_results( "SELECT * FROM $table_name WHERE timestamp>".$_POST['lightchat_update']." ORDER BY timestamp DESC" );
			if($history){//there is update
				echo json_encode(array('success'=>1,'updates'=>$history));exit;
			}else{//no updates
				sleep(1);
			}
		}
		echo json_encode(array('success'=>0));exit;
	}

/* 	wp_deregister_script('jquery');
	wp_register_script('jquery', ("http://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"), false, '');
	wp_enqueue_script('jquery'); */
}
add_action('init','lightchat_init');

// WIDGET
function lightchat_box_widget($args){
	global $wpdb;
	$table_name = $wpdb->prefix . "lightchat";
	extract($args);

	$title = get_option("lightchat_widget_title");

	echo $before_widget;
	echo $before_title.$title.$after_title;
  
  
$history = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY timestamp DESC" );
$string = "";
?>
	<div id="lightchat-history-container" style="overflow-y:scroll;height:300px;">
<?php 
if($history!=null){
	foreach($history as $v){ 
		$string = "<div><strong>".$v->alias.": </strong>".$v->message."</div>".$string;
	} 
	echo $string;
	?>
	<div style="text-align:center;font-style:italic;"><?php echo date("M d, Y - h:i:s",$history[0]->timestamp) ?></div>
	
<?php }else{ ?>
<?php } ?>
</div>
	<div id="lightchat-input" style="margin:3px 0 0 0;">
		<input type="text" style="display:none;" name="timestamp" value="<?php echo time(); ?>" /><input value="<?php echo isset($_COOKIE['lightchatAlias'])?$_COOKIE['lightchatAlias']:"Guest" ?>" id="lightchat-alias" size="5" type="text" autocomplete="off" />&nbsp;:&nbsp;<input id="lightchat-message" type="text" autocomplete="off" />
	</div>
<?php
	echo $after_widget;
}




function lightchat_widget_control(){
	$options = get_option("lightchat_widget_title");
	
	if (!is_array( $options )){
		$options = array('title' => 'Chat');
		add_option('lightchat_widget_title', 'Chat');
	}
  if ($_POST['lightchat-WidgetTitle']){
    $options['title'] = htmlspecialchars($_POST['lightchat-WidgetTitle']);
    update_option("lightchat_widget_title", $options['title']);
  }

 
?>
  <p>
    <label for="lightchat-WidgetTitle">Widget Title: </label>
    <input type="text" id="lightchat-WidgetTitle" name="lightchat-WidgetTitle" value="<?php echo $options['title'];?>" />
  </p>
<?php
}

function lightchat_widget_init()
{
  //wp_register_sidebar_widget('lightchat_box_widget','Light Chat', 'lightchat_box_widget');
  register_sidebar_widget('Light Chat', 'lightchat_box_widget');
  register_widget_control('Light Chat', 'lightchat_widget_control', 300, 200 );	
}
add_action("plugins_loaded", "lightchat_widget_init");

// javascript
function lightchat_scripts(){
?>
<script type="text/javascript">
function stripslashes(str) {
	str=str.replace(/\\'/g,'\'');
	str=str.replace(/\\"/g,'"');
	str=str.replace(/\\0/g,'\0');
	str=str.replace(/\\\\/g,'\\');
	return str;
}
function lightchat_send(){
	messageText = jQuery("#lightchat-message").val();
	jQuery("#lightchat-message").val("");
	jQuery.post("index.php", { alias: jQuery("#lightchat-alias").val(), message: messageText ,timestamp:jQuery("input[name=timestamp]").val() });
	
}
function lightchat_update(){
	jQuery.post("index.php", {lightchat_update:jQuery("input[name=timestamp]").val() },
		function(data){
			if(data.success==1){
			updates = data.updates;
				for(i=0;updates[i]!=undefined;i++){
					jQuery("#lightchat-history-container").html(jQuery("#lightchat-history-container").html()+"<div><strong>"+updates[i].alias+": </strong>"+stripslashes(updates[i].message)+"</div>");
					jQuery("input[name=timestamp]").val(updates[i].timestamp);
				}
			}
			
			
			if(data.timestamp!=undefined)
				jQuery("input[name=timestamp]").val(data.timestamp);
			
			jQuery("#lightchat-history-container").scrollTop(jQuery("#lightchat-history-container div").size()*parseInt(jQuery("#lightchat-history-container div").css("height")));
			lightchat_update();
			
		},
		'json'
	);
}
jQuery(function() {
	jQuery("#lightchat-history-container").scrollTop(jQuery("#lightchat-history-container div").size()*parseInt(jQuery("#lightchat-history-container div").css("height")));
	jQuery("#lightchat-message").keydown(function(e){if(e.keyCode==13) lightchat_send();}); // send
	
	setTimeout("lightchat_update()",1000);
});

</script>
<?php
}
add_action('wp_head','lightchat_scripts');