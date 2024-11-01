<?php
namespace LWS\WOOVIP\Ui;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Hide menu entries. */
class Menus
{
	public function __construct()
	{
		if( empty(\get_option('lws_woovip_menu_hidden_through_css', '')) )
		{
			\add_filter('wp_nav_menu_objects', array($this, 'filterItems'), 10, 2);
		}
		else
		{
			\add_action('wp_enqueue_scripts', array($this, 'scripts'));
			\add_filter('nav_menu_css_class', array($this, 'itemClasses'), 10, 4);
		}
	}

	function scripts()
	{
		\wp_enqueue_style('lws-woovip-menu', LWS_WOOVIP_CSS.'/menu.css', array(), LWS_WOOVIP_VERSION);
	}

	/** Filters the sorted list of menu item objects before generating the menu's HTML.
	 * Remove from list the item excluded from VIP process.
	 *
	 * Except if a children is accessible.
	 * That behavior differs from itemClasses()
	 *
	 * @param array    $sorted_menu_items The menu items, sorted by each menu item's menu order.
	 * @param stdClass $args              An object containing wp_nav_menu() arguments. */
	function filterItems($objects, $args)
	{
		$hidden = array();
		$ids = array();
		foreach($objects as $k => &$item)
		{
			if( !$this->isVisible($item) )
			{
				$ids[$item->ID] = $hidden[$k] = $k;
			}
			else if( isset($ids[$item->menu_item_parent]) )
			{
				// restore parent menu up to first visible one
				$showId = $item->menu_item_parent;
				while( isset($ids[$showId]) )
				{
					$nextK = $ids[$showId];
					unset($hidden[$nextK]);
					unset($ids[$showId]);
					$showId = $objects[$nextK]->menu_item_parent;
				}
			}
		}

		return array_values(array_diff_key($objects, $hidden));
	}

	/** Filters the CSS classes applied to a menu item's list item element.
	 * add a display:none class.
	 *
	 * It means all children of a hidden parent will be hide too in any case.
	 * That behavior differs from filterItems()
	 *
	 * @param string[] $classes Array of the CSS classes that are applied to the menu item's `<li>` element.
	 * @param WP_Post  $item    The current menu item.
	 * @param stdClass $args    An object of wp_nav_menu() arguments.
	 * @param int      $depth   Depth of menu item. Used for padding. */
	function itemClasses($classes, $item, $args, $depth)
	{
		if( !$this->isVisible($item) )
			$classes[] = 'lws-woovip-menu-hidden';
		return $classes;
	}

	function isVisible(&$item)
	{
		if( isset($item->type) && isset($item->object) && isset($item->object_id) )
		{
			if( $item->type == 'taxonomy' && in_array($item->object, array('category', 'product_cat')) )
			{
				list($in, $out) = \LWS\WOOVIP\Core\Rules::instance()->sortLoungesByUser(false);

				$terms = array($item->object_id);
				foreach( $terms as $termId )
					$terms = array_merge($terms, \get_ancestors($termId, $item->object));

				return \LWS\WOOVIP\Core\Rules::instance()->isVisibleTaxonomy($terms, $item->object, $in, $out, true);
			}
			else if( $item->type == 'post_type' && in_array($item->object, array('page', 'post', 'product')) )
			{
				return \LWS\WOOVIP\Core\Rules::instance()->isVisiblePost($item->object_id);
			}
		}
		return true;
	}
}
