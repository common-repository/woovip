<?php
namespace LWS\WOOVIP\Ui;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage VIP pages, posts and products. */
class Pages
{
	function __construct()
	{
		\add_filter('woocommerce_product_get_price', array($this, 'getAnyPrice'), 9999, 2);
		\add_filter('woocommerce_product_get_sale_price', array($this, 'getSalePrice'), 9999, 2);
		\add_filter('woocommerce_product_is_on_sale', array($this, 'isOnSale'), 9999, 2);
		\add_filter('woocommerce_sale_flash', array($this, 'onSaleText'), 9999, 3);
		// variation special cases 'product_variation'
		\add_filter('woocommerce_product_variation_get_price', array($this, 'getAnyPrice'), 9999, 2);
		\add_filter('woocommerce_product_variation_get_sale_price', array($this, 'getSalePrice'), 9999, 2);
		\add_filter('woocommerce_variation_prices_price', array($this, 'getAnyPrice'), 9999, 2);
		\add_filter('woocommerce_variation_prices_sale_price', array($this, 'getSalePrice'), 9999, 2);


		// ... Filter 'woocommerce_hide_invisible_variations' to optionally hide invisible variations (disabled variations and variations with empty price).

		\add_action('wp_head', array($this, 'head'));
		\add_filter('pre_get_posts', array($this, 'searchFilter'));
		\add_action('template_redirect', array($this, 'redirect'));
		\add_filter('woocommerce_related_products', array($this, 'relatedProducts'), 10, 3);
		\add_action('init', array($this, 'registerUi'));
	}

	protected function isProductScreen()
	{
		if( !isset($this->productScreen) )
		{
			$this->productScreen = false;
			if( function_exists('\get_current_screen') && !empty($screen = \get_current_screen()) )
			{
				$this->productScreen = ($screen->id == 'edit-product');
			}
		}
		return $this->productScreen;
	}

	/** @return true if vip sales should be applied */
	protected function isValidScreen()
	{
		if( is_admin() && !(defined('DOING_AJAX') && DOING_AJAX) )
			return false;
		if( $this->isProductScreen() )
			return false;
		if( defined('DOING_AJAX') && DOING_AJAX && function_exists('\get_current_screen') && \get_current_screen() )
			return false;
		return true;
	}

	function onSaleText($text, $post, $product)
	{
		$rule = \LWS\WOOVIP\Core\Rules::instance()->getProductRule($product);
		if( !$rule->onsale && $product->is_type('variable') )
		{
			foreach( \LWS\WOOVIP\Core\Cache::instance()->getProductAvailableVariations($product) as $variation )
			{
				if( $variation['variation_is_active'] )
				{
					$varProd = \wc_get_product($variation['variation_id']);
					$child = \LWS\WOOVIP\Core\Rules::instance()->getProductRule($varProd);
					if( \LWS\WOOVIP\Core\Rules::instance()->ruleCmp($child, $rule) >= 0 )
						$rule = $child;
				}
			}
		}

		if( $product->is_type('grouped') )
		{
			$master = $rule;
			$first = false;
			foreach( $product->get_children() as $childId )
			{
				$child = \wc_get_product($childId);
				$childRule = \LWS\WOOVIP\Core\Rules::instance()->getProductRule($child);
				if(!isset($childRule->vip))
				{
					$rule = $master;
					break;
				}
				if(!$first)
				{
					$rule= $childRule;
					$first = true;
				}
				if($first)
				{
					if( $rule->vip != $childRule->vip )
					{
						$rule=$master;
					}
				}
			}
		}


		if( $onsale = $rule->onsale )
		{
			if( strlen(trim($original = $product->get_sale_price('edit'))) )
			{
				$onsale = ($rule->price == \LWS\WOOVIP\Core\Rules::instance()->getBestPrice($original, $rule));
			}
		}

		if( $onsale )
		{
			$override = '';
			if( $rule->sticker )
				$override = $rule->sticker;
			else
			{
				$override = __("V.I.P.", 'woovip-lite');
				if( isset($rule->vip) && $rule->vip )
					$override = $rule->vip;
			}
			if( $text )
			{
				/// since wc css classes are not enought for some themes,
				/// hope they try to keep it in a span anyway
				$replaced = \preg_replace(
					"@<span[^>]*class=(?:'|\")([^'\"]*)(?:'|\")[^>]*>(.*)</span>@i",
					'<span class="${1} lws-vip-flash override">'.$override.'</span>',
					$text
				);
				if( !$replaced || ($replaced == $text))
					$text = "<span class='onsale lws-vip-flash'>{$override}</span>";
				else
					$text = $replaced;
			}
			else
				$text = "<span class='onsale lws-vip-flash'>{$override}</span>";
		}
		return $text;
	}

	function isOnSale($onSale, $product)
	{
		if( !$onSale && $this->isValidScreen() )
		{
			$onSale = \LWS\WOOVIP\Core\Rules::instance()->getProductRule($product, $this->getCustomer())->onsale;
		}
		return $onSale;
	}

	function getAnyPrice($price, $product)
	{
		if( $this->isValidScreen() )
		{
			$rule = \LWS\WOOVIP\Core\Rules::instance()->getProductRule($product, $this->getCustomer());
			if( $rule->onsale )
				$price = \LWS\WOOVIP\Core\Rules::instance()->getBestPrice($price, $rule);
		}
		return $price;
	}

	function getSalePrice($price, $product)
	{
		if( $this->isValidScreen() )
		{
			$rule = \LWS\WOOVIP\Core\Rules::instance()->getProductRule($product, $this->getCustomer());
			if( $rule->onsale )
			{
				$price = strlen(trim($price)) ? \LWS\WOOVIP\Core\Rules::instance()->getBestPrice($price, $rule) : $rule->price;
			}
		}
		return $price;
	}

	function getCustomer()
	{
		$user = false;
		if( $order = \wc_get_order(false) )
		{
			// ... this doesn't work in admin screen 'edit order' when product are added via ajax
			$user = $order->get_customer_id('edit');
			if( !$user )
				$user = new \WP_User();
		}
		return $user;
	}

	function registerUi()
	{
		if( \apply_filters('lws_woovip_single_lounge_admin', true, 'pages', $this) )
		{
			\add_filter('woocommerce_product_data_tabs', array($this, 'productSettingTab'));
			\add_action('woocommerce_product_data_panels', array($this, 'productSettingTabContent'));

			\add_action('add_meta_boxes_page', array($this, 'addMetaBoxes'), 10, 1);
			\add_action('add_meta_boxes_post', array($this, 'addMetaBoxes'), 10, 1);

			\add_action('save_post', array($this, 'savePost'));
//			foreach( array('simple') as $productType )
//				\add_action('woocommerce_process_product_meta_' . $productType, array($this, 'savePost'), 10, 1);
		}
	}

	function force403()
	{
		if( $redirectTo = \get_option('lws_woovip_403_redirection') )
		{
			$current = \get_post();
			if( !($current && $current->ID == $redirectTo) )
			{
				if( $url = \get_permalink($redirectTo) )
				{
					\wp_redirect($url);
					exit;
				}
			}
		}

		// 1. Ensure `is_*` functions work
		global $wp_query;
		$wp_query->set_404();

		// 2. Fix HTML title
		add_action( 'wp_title', function () {
			return __("VIP section", 'woovip-lite');
		}, 9999 );

		// 3. Throw 404
		status_header(403);
		nocache_headers();

		// 4. Show 404 template
		require get_404_template();

		// 5. Stop execution
		exit;
	}

	function redirect()
	{
		if( !\is_admin() || (defined('DOING_AJAX') && DOING_AJAX) )
		{
			$postId = \LWS\WOOVIP\Core\Rules::instance()->getPostId();
			if( !$postId && \is_404() && !\is_search() && isset($this->mainQuery) && $this->mainQuery )
			{
				// get post requested before VIP filter if any
				$this->isFilterDisabled = true;
				$posts = $this->mainQuery->get_posts();
				$this->isFilterDisabled = false;
				if( $posts && count($posts) == 1 )
					$postId = reset($posts)->ID;
			}
			if( !\LWS\WOOVIP\Core\Rules::instance()->isVisiblePost($postId) )
			{
				$this->force403();
			}
		}
	}

	function head()
	{
		if( !\is_admin() || (defined('DOING_AJAX') && DOING_AJAX) )
		{
			if( !\LWS\WOOVIP\Core\Rules::instance()->isVisiblePost() )
				echo '<meta name="robots" content="noindex,nofollow"/>';
		}
	}

	function searchFilter($query)
	{
		if( \is_admin() && !(defined('DOING_AJAX') && DOING_AJAX) )
			return $query;
		if( isset($this->isFilterDisabled) && $this->isFilterDisabled )
			return $query;
		if( $query->is_main_query() )
			$this->mainQuery = clone $query;
		// rules is not plugged directly to hook to build instance as late as possible
		return \LWS\WOOVIP\Core\Rules::instance()->filterQuery($query);
	}

	/** filter out related products the user cannot see. */
	function relatedProducts($relatedIds, $productId, $args)
	{
		$rules = \LWS\WOOVIP\Core\Rules::instance();
		for( $index=(count($relatedIds)-1) ; $index>=0 ; --$index )
		{
			$rId = $relatedIds[$index];
			if( !$rules->isVisiblePost($rId) )
						unset($relatedIds[$index]);
		}
		return array_values($relatedIds);
	}

	/** add a custom metabox to manage VIP access. */
	function addMetaBoxes($post)
	{
		\add_meta_box(
			'lws_woovip_lounge',
			__("VIP", 'woovip-lite'),
			array($this, 'echoMetabox'),
			$post->post_type,
			'side',
			'high'
    );
	}

	function echoMetabox($post)
	{
		$checked = '';
		if( !empty(\get_post_meta($post->ID, 'lws_woovip_show_for_vip_only', true)) )
			$checked = ' checked';
		$label = __("Show for VIP only", 'woovip-lite');
		$postType = esc_attr($post->post_type);
		$readonly = \current_user_can('edit_vip_posts') ? '' : ' onclick="return false;"'; // readonly does not work with checkbox

		echo <<<EOT
<div class='lws_woovip_lounge'>
	<label for='lws_woovip_show_for_vip_only'>$label</label>
	<input type='hidden' name='lws_woovip_type' value='{$postType}'>
	<input type='checkbox' name='lws_woovip_show_for_vip_only' id='lws_woovip_show_for_vip_only'$checked$readonly>
</div>
EOT;
	}

	function savePost($postId)
	{
		if( !\apply_filters('lws_woovip_single_lounge_admin', true, 'pages', $this) )
			return;
		if( !\current_user_can('edit_vip_posts') )
			return;

		$postType = isset($_POST['lws_woovip_type']) ? \sanitize_key($_POST['lws_woovip_type']) : '';
		if( in_array($postType, array('page', 'post', 'product')) )
		{
			if( isset($_POST['lws_woovip_show_for_vip_only']) && !empty($_POST['lws_woovip_show_for_vip_only']) )
				\update_post_meta($postId, 'lws_woovip_show_for_vip_only', 'on');
			else
				\delete_post_meta($postId, 'lws_woovip_show_for_vip_only');

			if( $postType == 'product' )
			{
				$price = isset($_POST['lws_woovip_product_vip_on_sale_price']) ? \sanitize_text_field($_POST['lws_woovip_product_vip_on_sale_price']) : '';
				if( empty($price) || is_numeric($price = str_replace(',', '.', $price)) )
					\update_post_meta($postId, 'lws_woovip_product_vip_on_sale_price', $price);
				else
					\lws_admin_add_notice_once('woovip-lite'.'-bad_price', __("Bad VIP price format", 'woovip-lite'), array('level'=>'error'));
			}
		}
	}

	/** Add lateral tabs for product settings. */
	function productSettingTab($tabs)
	{
		$tabs['vip'] = array(
			'label' => __("WooVIP Rules", 'woovip-lite'),
			'target' => 'lws_woovip_product_data',
			'class' => array('lws_woovip_lounge')
		);
		return $tabs;
	}

	function productSettingTabContent()
	{
		global $product_object;
		if( !$product_object ) return;
		$pId = $product_object->get_id();

		echo "<div id='lws_woovip_product_data' class='panel woocommerce_options_panel lws_woovip'><div class='options_group'>";
		echo "<input type='hidden' name='lws_woovip_type' value='product'>";

		$readonly = array();
		if( !\current_user_can('edit_vip_posts') )
			$readonly = array('onclick' => 'return false;');

		$yes = \get_post_meta($pId, 'lws_woovip_show_for_vip_only', true);
		\woocommerce_wp_checkbox(array(
			'id'          => 'lws_woovip_show_for_vip_only',
			'value'       => $yes,
			'cbvalue'			=> 'on',
			'label'       => __("Show for VIP only", 'woovip-lite'),
			'desc_tip'    => true,
			'description' => __("Hidden for all non VIP user.", 'woovip-lite'),
			'custom_attributes' => $readonly
		));

		$price = \get_post_meta($pId, 'lws_woovip_product_vip_on_sale_price', true);
		\woocommerce_wp_text_input( array(
			'id'          => 'lws_woovip_product_vip_on_sale_price',
			'value'       => \esc_attr($price),
			'label'       => __("VIP sale price", 'woovip-lite'),
			'desc_tip'    => true,
			'description' => __("The product gets a discount for VIP users with a special price. If the product already has a discount, the smallest price is kept.", 'woovip-lite'),
			'custom_attributes' => $readonly,
			'wrapper_class'     => 'hide_if_grouped hide_if_variable',
		) );
		$variableProVersionText = __("With PRO version, you could set values on each variation", 'woovip-lite');
		echo "<div class='options_group show_if_variable'>$variableProVersionText</div>";
		echo "</div></div>";
	}
}
