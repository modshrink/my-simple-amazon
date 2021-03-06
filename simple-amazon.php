<?php
/*
Plugin Name: My Simple Amazon
Plugin URI:https://github.com/modshrink/simple-amazon-widget
Description: ウィジェットに対応した、Amazonアソシエイト挿入プラグインです。
Author: modshrink
Version: 0.0.1-dev
Author URI: http://www.modshrink.com/

Contributor
Tomokame: Create wp-tmkm-amazon plugin first development in 2007. (http://tomokame.moo.jp/)
icoro: Fixed legacy codes and built this plugin bases. (http://www.icoro.com/simple-amazon)

Special Thanks: websitepublisher.net (http://www.websitepublisher.net/article/aws-php/)
Special Thanks: hiromasa.zone :o) (http://hiromasa.zone.ne.jp/)
Special Thanks: PEAR :: Package :: Cache_Lite (http://pear.php.net/package/Cache_Lite)
Special Thanks: Amazon® AWS HMAC signed request using PHP (http://mierendo.com/software/aws_signed_query/)
Special Thanks: PHP による Amazon PAAPI の毎秒ルール制限の実装とキャッシュの構築例 (http://sakuratan.biz/archives/1395)
Special Thanks: jRating (http://www.myjqueryplugins.com/jquery-plugin/jrating)
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

function simple_amazon_item_view($asin, $style) {
	global $simpleAmazonView;
	if(preg_match("/[A-Z0-9]{10,13}/", $asin)) {
		preg_match("/[A-Z0-9]{10,13}/", $asin, $match);
	} else {
		preg_match("/\/[A-Z0-9]{10,13}\//", $asin, $match);
	}
	// $asin = $match["0"];
	$asin = str_replace("/", "", $match["0"]);
	$sa_item = $simpleAmazonView->generate($asin, $style);
	return $sa_item;
}

/* ウィジェット表示 */
add_action('widgets_init', create_function('', 'return register_widget("MyWidget");'));


/******************************************************************************
 * 投稿画面にASINとレート欄を追加
 *****************************************************************************/

// 中身
function sa_meta_box_content() {

	//レートの取得
	$checked0 = "";
	$checked1 = "";
	$checked2 = "";
	$checked3 = "";
	$checked4 = "";
	$checked5 = "";
	$checked6 = "";
	$checked7 = "";
	$checked8 = "";
	$checked9 = "";
	$checked10 = "";
	$sa_rate = "";
	$custom_fields = NULL;
	$rating_value = NULL;
	$asin_value = NULL;
	$item_value = NULL;


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
		if($rating_value==0){ $checked10 = ' checked="checked"';} 
	}

	//ASINの取得
	if(isset($custom_fields['amazon'])) {
		$sa_custom_field_asin = $custom_fields['amazon'];
	foreach ( $sa_custom_field_asin as $key => $asin_value ){}
	}
	if(isset($custom_fields['item'])) {
		$sa_custom_field_asin = $custom_fields['item'];
	foreach ( $sa_custom_field_asin as $key => $item_value ){}
	}
?>

	<p>ウィジェットに表示される情報です。あなたの評価とAmazonの情報を入力してください。</p>

	<h4>AmazonへのリンクまたはASINコード</h4>
	<p><input class="sa-asin-code" type="text" name="sa-asin" value="<?php echo $asin_value; ?>" /></p>
	<p class="sa-asin-item"><?php if($item_value){ echo $item_value; } else { echo "商品が未登録です。"; } ?></p>
	<p><button class="button sa-asin-code" type="button">初期化</button></p>

	<h4>レート<?php if($rating_value){ echo "(現在のレート: " .$rating_value. ")"; } else { echo "(無し)"; } ?></h4>
	<div id="rating-star-admin">
		<div class="sa-rating-star" data-average="<?php if($rating_value > 0 && $rating_value != NULL){ echo $rating_value; } else { echo "0"; } ?>" data-id="1"></div>
	</div>
	<p><button class="button sa-rating-star-clear" type="button">初期化</button></p>

	<ul class="sa-admin-rating">
		<li class="sa-rating rate0"><input id="rate0.5" type="radio" name="sa-rate" value="0"<?php echo $checked10; ?>><label for="rate0.5">0</label></li>
		<li class="sa-rating rate0.5"><input id="rate0.5" type="radio" name="sa-rate" value="0.5"<?php echo $checked9; ?>><label for="rate0.5">0.5</label></li>
		<li class="sa-rating rate1"><input id="rate1" type="radio" name="sa-rate" value="1"<?php echo $checked8; ?>><label for="rate1">1</label></li>
		<li class="sa-rating rate1.5"><input id="rate1.5" type="radio" name="sa-rate" value="1.5"<?php echo $checked7; ?>><label for="rate1.5">1.5</label></li>
		<li class="sa-rating rate2"><input id="rate2" type="radio" name="sa-rate" value="2"<?php echo $checked6; ?>><label for="rate2">2</label></li>
		<li class="sa-rating rate2.5"><input id="rate2.5" type="radio" name="sa-rate" value="2.5"<?php echo $checked5; ?>><label for="rate2.5">2.5</label></li>
		<li class="sa-rating rate3"><input id="rate3" type="radio" name="sa-rate" value="3"<?php echo $checked4; ?>><label for="rate3">3</label></li>
		<li class="sa-rating rate3.5"><input id="rate3.5" type="radio" name="sa-rate" value="3.5"<?php echo $checked3; ?>><label for="rate3.5">3.5</label></li>
		<li class="sa-rating rate4"><input id="rate4" type="radio" name="sa-rate" value="4"<?php echo $checked2; ?>><label for="rate4">4</label></li>
		<li class="sa-rating rate4.5"><input id="rate4.5" type="radio" name="sa-rate" value="4.5"<?php echo $checked1; ?>><label for="rate4.5">4.5</label></li>
		<li class="sa-rating rate5"><input id="rate5" type="radio" name="sa-rate" value="5"<?php echo $checked0; ?>><label for="rate5">5</label></li>
	</ul>

<?php

} //sa_meta_box_content()

	function post_rating($post_id) {
		$post = get_post($post_id);
		$sa_post_id = $post->ID;
		$sa_rate = $_POST["sa-rate"];
		$sa_asin = $_POST["sa-asin"];
		$sa_item = simple_amazon_item_view($sa_asin, 'name=&layout_type=4&imgsize=');
		$sa_item = htmlspecialchars($sa_item);
		$prev_rate = get_post_meta($sa_post_id, "rating", true);
		$prev_asin = get_post_meta($sa_post_id, "amazon", true);
		$prev_item = get_post_meta($sa_post_id, "item", true);
		update_post_meta($post_id, "rating", $sa_rate, $prev_rate);
		update_post_meta($post_id, "amazon", $sa_asin, $prev_asin);
		update_post_meta($post_id, "item", $sa_item, $prev_item);
	}
	add_action('publish_post', 'post_rating');
 
// メタボックスを追加する関数
function sa_meta_box_output() {
    add_meta_box('sa-meta-box', 'あなたの評価', 'sa_meta_box_content', 'post', 'side', 'high' );
}
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
