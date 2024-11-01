<?php
namespace LWS\WOOVIP\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Read VIP rules, sort by user. */
class cache
{
	protected $variations = array();
	protected $visibilities = array();

	/** cannot be instaciated directly. @see instance() */
	protected function __construct()
	{
	}

	static function instance()
	{
		static $_inst = false;
		if( !$_inst )
		{
			$className = \apply_filters('lws_woovip_core_cache_classname', '\\'.\get_class());
			$_inst = new $className();
		}
		return $_inst;
	}

	function getProductAvailableVariations(\WC_Product &$product)
	{
		$pid = $product->get_id();
		if( !isset($this->variations[$pid]) )
		{
			$this->variations[$pid] = $product->get_available_variations();
		}
		return $this->variations[$pid];
	}

	/** Never call directly, lets Rules::isVisiblePost do that */
	function getVisibility($userId, $pid)
	{
		return isset($this->visibilities[$userId][$pid]) ? $this->visibilities[$userId][$pid] : null;
	}

	/** Never call directly, lets Rules::isVisiblePost do that */
	function setVisibility($userId, $pid, $visible)
	{
		$this->visibilities[$userId][$pid] = $visible;
	}
}
