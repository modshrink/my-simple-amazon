<?php

/******************************************************************************
 * ウィジェット表示用のクラス
 *****************************************************************************/
 
class MyWidget extends WP_Widget {

	public $rating;

	function __construct() {
		$widget_ops = array('description' => '記事内で指定したAmazonの商品を表示します。');
	//	$control_ops = array('width' => 400, 'height' => 350);
		parent::__construct(
		false,
		'My Simple Amazon',
		$widget_ops
		//$control_ops
		);
	}

	public function form($par) {
	 
		 // タイトルの入力
		$title = (isset($par['title']) && $par['title']) ? $par['title'] : '';
		$id = $this->get_field_id('title');
		$name = $this->get_field_name('title');
		echo "<p>";
		echo '<label>タイトル：</label>';
		echo '<input type="text" id="'.$id.'" name="'.$name.'" value="';
		echo trim(htmlentities($title, ENT_QUOTES, 'UTF-8'));
		echo '" />';
		echo '</p>';
		 
		// テキストの入力
		$text = (isset($par['text']) && $par['text']) ? $par['text'] : '';
		$id = $this->get_field_id('text');
		$name = $this->get_field_name('text');
		echo '<p>投稿のカスタムフィールドで設定した商品が表示されます。</p>';

	}

	public function update($new_instance, $old_instance) {
		return $new_instance;
	}

	public function widget($args, $par) {
		global $post;
		$rating = get_post_meta($post->ID,"rating",true);
		if(is_single() && $rating != 0 && $rating != NULL) {
			echo $args['before_widget'];
			if($par['title']) {
				echo $args['before_title'];
				echo trim(htmlentities($par['title'], ENT_QUOTES, 'UTF-8'));
				echo $args['after_title'];
			}
?>

			<div class="simple-amazon-widget">
				<div itemscope itemtype="http://data-vocabulary.org/Review">
					<?php simple_amazon_custum_view(); ?>
				<h2>このサイトでの評価</h2>
					<p class="rating-star rate<?php echo $rating; ?>"><meta itemprop="rating" content="<?php echo $rating; ?>" /></p>
					<p>評価アイテム » <span itemprop="itemreviewed"><?php echo get_post_meta($post->ID,"item",true); ?></span></p>
					<p>レビュアー » <span itemprop="reviewer"><?php the_author_meta('display_name'); ?></span></p>
					<p>評価日 » <time itemprop="dtreviewed" datetime="<?php echo get_post_time('Y-m-d'); ?>"><?php echo get_post_time('Y-m-d'); ?></time></p>
				</div>
			</div>

<?php

			echo $args['after_widget'];
		}
	}
}

?>