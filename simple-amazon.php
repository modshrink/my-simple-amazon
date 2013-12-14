<?php
/*
Plugin Name: My Simple Amazon
Plugin URI: http://www.modshrink.com/
Description: ASIN を指定して Amazon から個別商品の情報を取出します。BOOKS, DVD, CD は詳細情報を取り出せます。
Author: mayoibi
Version: 0.0.1
Author URI: http://www.modshrink.com/
Code based on icoro(http://www.icoro.com/simple-amazon)
Special Thanks: tomokame (http://http://tomokame.moo.jp/)
Special Thanks: websitepublisher.net (http://www.websitepublisher.net/article/aws-php/)
Special Thanks: hiromasa.zone :o) (http://hiromasa.zone.ne.jp/)
Special Thanks: PEAR :: Package :: Cache_Lite (http://pear.php.net/package/Cache_Lite)
Special Thanks: Amazon® AWS HMAC signed request using PHP (http://mierendo.com/software/aws_signed_query/)
Special Thanks: PHP による Amazon PAAPI の毎秒ルール制限の実装とキャッシュの構築例 (http://sakuratan.biz/archives/1395)
*/

if( $_SERVER['SCRIPT_FILENAME'] == __FILE__ ) die();

/******************************************************************************
 * 定数の設定 (主にディレクトリのパスとか)
 *****************************************************************************/
if ( ! defined( 'SIMPLE_AMAZON_VER' ) )
	define( 'SIMPLE_AMAZON_VER', '0.0.1' );

if ( ! defined( 'SIMPLE_AMAZON_DIR_NAME' ) )
	define( 'SIMPLE_AMAZON_DIR_NAME', plugin_basename( dirname( __FILE__ ) ) );

if ( ! defined( 'SIMPLE_AMAZON_PLUGIN_DIR' ) )
	define( 'SIMPLE_AMAZON_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . SIMPLE_AMAZON_DIR_NAME );

if ( ! defined( 'SIMPLE_AMAZON_PLUGIN_URL' ) )
	define( 'SIMPLE_AMAZON_PLUGIN_URL', WP_PLUGIN_URL . '/' . SIMPLE_AMAZON_DIR_NAME );

if ( ! defined( 'SIMPLE_AMAZON_IMG_URL' ) )
	define( 'SIMPLE_AMAZON_IMG_URL', SIMPLE_AMAZON_PLUGIN_URL . '/images' );


/******************************************************************************
 * globalな変数の設定
 *****************************************************************************/
global $simple_amazon_options;

$simple_amazon_options = get_option('simple_amazon_admin_options');

if ( ! $simple_amazon_options ){
	$simple_amazon_options = array(
		'accesskeyid'     => '',
		'associatesid_ca' => '',
		'associatesid_cn' => '',
		'associatesid_de' => '',
		'associatesid_es' => '',
		'associatesid_fr' => '',
		'associatesid_it' => '',
		'associatesid_jp' => '',
		'associatesid_uk' => '',
		'associatesid_us' => '',
		'delete_setting'  => 'no',
		'imgsize'         => 'medium',
		'layout_type'     => 0,
		'secretaccesskey' => '',
		'setcss'          => 'yes',
		'windowtarget'    => 'self'
	);
	update_option( 'simple_amazon_admin_options', $simple_amazon_options );
}

/******************************************************************************
 * クラスの読み込み
 *****************************************************************************/
include_once(SIMPLE_AMAZON_PLUGIN_DIR . '/include/sa_view_class.php');
include_once(SIMPLE_AMAZON_PLUGIN_DIR . '/include/sa_xmlparse_class.php');
include_once(SIMPLE_AMAZON_PLUGIN_DIR . '/include/sa_cache_control_class.php');
include_once(SIMPLE_AMAZON_PLUGIN_DIR . '/include/sa_admin_class.php');
include_once(SIMPLE_AMAZON_PLUGIN_DIR . '/include/sa_widget_class.php');

$simpleAmazonView  = new SimpleAmazonView();
$simpleAmazonAdmin = new SimpleAmazonAdmin();


/******************************************************************************
 * アクション&フィルタの設定
 *****************************************************************************/

/* amazon のURLをhtmlに置き換える */
add_filter('the_content', array($simpleAmazonView, 'replace'));

/* simple amazonのcssを読み込む */
function add_simpleamazon_stylesheet(){

	global $simple_amazon_options;

	if( $simple_amazon_options['setcss'] == 'yes') {
		wp_enqueue_style('simple-amazon', SIMPLE_AMAZON_PLUGIN_URL.'/simple-amazon.css', array(), SIMPLE_AMAZON_VER);
	}
}
add_action('wp_head', 'add_simpleamazon_stylesheet', 1);

/* 定期的に期限切れのキャッシュを削除する feat. wp-cron */
function simple_amazon_clean_cache() {
	$SimpleAmazonCacheController = new SimpleAmazonCacheControl();
	$SimpleAmazonCacheController->clean();
}
add_action('simple_amazon_clear_chache_hook', 'simple_amazon_clean_cache');


/******************************************************************************
 * インストール&アンインストール時の設定
 *****************************************************************************/

/* インストール時 */
function simple_amazon_activation() {
	// simple_amazon_clear_chache_hook を wp-cron に追加する
	wp_schedule_event(time(), 'daily', 'simple_amazon_clear_chache_hook');
}

/* アンインストール時 */
function simple_amazon_deactivation() {
	global $simpleAmazonAdmin;

	// オプション値の削除
	$simpleAmazonAdmin->uninstall();

	// simple_amazon_clear_chache_hook を wp-cron から削除する
	wp_clear_scheduled_hook('simple_amazon_clear_chache_hook');
}

register_activation_hook(__FILE__, 'simple_amazon_activation');
register_deactivation_hook(__FILE__, 'simple_amazon_deactivation');


/******************************************************************************
 * 関数の設定
 *****************************************************************************/
 
/* 指定したasinの商品情報を表示する関数 */
function simple_amazon_view( $asin, $code = null, $style = null ) {
	global $simpleAmazonView;
	$simpleAmazonView->view( $asin, esc_html($code), $style );
}

/* カスタムフィールドから値を取得して表示する関数 */
function simple_amazon_custum_view() {
	global $simpleAmazonView;
	$simpleAmazonView->view_custom_field();
}

/* ウィジェット表示 */
add_action('widgets_init', create_function('', 'return register_widget("MyWidget");'));


/******************************************************************************
 * 投稿画面にASINとレート欄を追加
 *****************************************************************************/

// 中身
function sa_meta_box_content() {

	//レートの取得
	$checked0 ="";
	$checked1 ="";
	$checked2 ="";
	$checked3 ="";
	$checked4 ="";
	$checked5 ="";
	$checked6 ="";
	$checked7 ="";
	$checked8 ="";
	$checked9 ="";
	$sa_rate = "";
	$custom_fields = NULL;
	$rating_value = NULL;
	$asin_value = NULL;

	$custom_fields = get_post_custom();
	if(isset($custom_fields['rating'])) {
		$sa_custom_field_rating = $custom_fields['rating'];
	foreach ( $sa_custom_field_rating as $key => $rating_value ) {}
		if($rating_value==5){ $checked0 = ' checked="checked"';} 
		if($rating_value==4.5){ $checked1 = ' checked="checked"';} 
		if($rating_value==4){ $checked2 = ' checked="checked"';} 
		if($rating_value==3.5){ $checked3 = ' checked="checked"';} 
		if($rating_value==3){ $checked4 = ' checked="checked"';} 
		if($rating_value==2.5){ $checked5 = ' checked="checked"';} 
		if($rating_value==2){ $checked6 = ' checked="checked"';} 
		if($rating_value==1.5){ $checked7 = ' checked="checked"';} 
		if($rating_value==1){ $checked8 = ' checked="checked"';} 
		if($rating_value==0.5){ $checked9 = ' checked="checked"';} 
	}

	//ASINの取得
	if(isset($custom_fields['amazon'])) {
		$sa_custom_field_asin = $custom_fields['amazon'];
	foreach ( $sa_custom_field_asin as $key => $asin_value ){}
	}
?>

<p>My Simple Amazonウィジェットに表示される情報です。あなたの評価とAmazonの情報を入力してください。 </p>

<h4>レート</h4>
	<ul class="sa-admin-rating">
		<li>最低</li>
		<li><input id="rate0.5" type="radio" name="sa-rate" value="0.5"<?php echo $checked9; ?>><label for="rate0.5">0.5</label></li>
		<li><input id="rate1" type="radio" name="sa-rate" value="1"<?php echo $checked8; ?>><label for="rate1">1</label></li>
		<li><input id="rate1.5" type="radio" name="sa-rate" value="1.5"<?php echo $checked7; ?>><label for="rate1.5">1.5</label></li>
		<li><input id="rate2" type="radio" name="sa-rate" value="2"<?php echo $checked6; ?>><label for="rate2">2</label></li>
		<li><input id="rate2.5" type="radio" name="sa-rate" value="2.5"<?php echo $checked5; ?>><label for="rate2.5">2.5</label></li>
		<li><input id="rate3" type="radio" name="sa-rate" value="3"<?php echo $checked4; ?>><label for="rate3">3</label></li>
		<li><input id="rate3.5" type="radio" name="sa-rate" value="3.5"<?php echo $checked3; ?>><label for="rate3.5">3.5</label></li>
		<li><input id="rate4" type="radio" name="sa-rate" value="4"<?php echo $checked2; ?>><label for="rate4">4</label></li>
		<li><input id="rate4.5" type="radio" name="sa-rate" value="4.5"<?php echo $checked1; ?>><label for="rate4.5">4.5</label></li>
		<li><input id="rate5" type="radio" name="sa-rate" value="5"<?php echo $checked0; ?>><label for="rate5">5</label></li>
		<li>最高</li>
	</ul>
	<h4>AmazonへのリンクまたはASINコード</h4>
	<input type="text" name="sa-asin" value="<?php echo $asin_value; ?>" size="40" />
<?php

//update_post_meta($post_id, $meta_key, $meta_value, $prev_value);


} //sa_meta_box_content()

	function post_rating($post_id) {
			$post = get_post($post_id);
			$sa_post_id = $post->ID;
			$sa_rate = $_POST["sa-rate"];
			$sa_asin = $_POST["sa-asin"];
			$prev_rate = get_post_meta($sa_post_id, "rating", true);
			$prev_asin = get_post_meta($sa_post_id, "amazon", true);
			update_post_meta($post_id, "rating", $sa_rate, $prev_rate);
			update_post_meta($post_id, "amazon", $sa_asin, $prev_asin);

	}

	add_action('publish_post', 'post_rating');
 
// メタボックスを追加する関数
function sa_meta_box_output() {
    add_meta_box('nskw_meta_post_page', 'あなたの評価', 'sa_meta_box_content', 'post', 'side', 'high' );
}
 
// フックする
add_action('admin_menu', 'sa_meta_box_output' );


/******************************************************************************
 * 管理画面でAJAXを使用

add_action( 'admin_footer', 'my_action_javascript' );

function my_action_javascript() {
?>
<script type="text/javascript" >
jQuery(document).ready(function($) {

	var data = {
		action: 'my_action',
		whatever: 1234
	};

	$.post(ajaxurl, data, function(response) {
		alert('Got this from the server: ' + response);
	});
});
</script>
<?php
}

add_action( 'wp_ajax_my_action', 'my_action_callback' );

function my_action_callback() {
	global $wpdb; // this is how you get access to the database

	$whatever = intval( $_POST['whatever'] );

	$whatever += 10;

        echo $whatever;

	die();
}

 *****************************************************************************/

?>
