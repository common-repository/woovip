<?php
namespace LWS\WOOVIP\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Read VIP rules, sort by user. */
class Rules
{
	/** cannot be instaciated directly. @see instance() */
	protected function __construct()
	{
	}

	static function instance()
	{
		static $_inst = false;
		if( !$_inst )
		{
			$className = \apply_filters('lws_woovip_core_rules_classname', '\\'.\get_class());
			$_inst = new $className();
		}
		return $_inst;
	}

	/** @return array of vip role */
	function getRoles()
	{
		if( !isset($this->roles) )
		{
			$this->roles = array('lws_woovip_member'=>'lws_woovip_member');
			$this->ensureRoleExists($this->roles);
		}
		return $this->roles;
	}

	/** @return array of user defined vip role name as (key=>name)
	 * Since that returns only custom names, some VIP roles can be missing here @see getRoles(). */
	function getRolesNames()
	{
		$names = array();
		if( !empty($name = trim(\get_option('lws_woovip_single_lounge_name', ''))) )
			$names['lws_woovip_member'] = $name;
		return $names;
	}

	function ensureRoleExists($roles)
	{
		$names = false;
		if( !is_array($roles) )
			$roles = array($roles);
		foreach( $roles as $role )
		{
			if( !\get_role($role) )
			{
				if( $names === false )
					$names = $this->getRolesNames();

				$name = (isset($names[$role]) ? $names[$role] : false);
				\add_role($role, empty($name) ? $role : $name);
			}
		}
		return $roles;
	}

	/** @return (object){onSale: bool; price: float;}
	 * @param $product (WP_Product)
	 * @param $user (false|int|WP_User) if false, we look for current user. */
	function getProductRule($product, $user=false)
	{
		if( !isset($this->products) )
			$this->products = array();
		$productId = $product->get_id();
		if( isset($this->products[$productId]) )
			return $this->products[$productId];

		$this->products[$productId] = (object)['onsale' => false, 'price' => 0.0, 'sticker' => false, 'worst' => false];

		$price = \get_post_meta($productId, 'lws_woovip_product_vip_on_sale_price', true);
		if( strlen($price) > 0 && is_numeric($price) )
		{
			$user = $this->getUser($user);
			if( $user && $user->ID )
			{
				if( !empty(array_intersect($this->getRoles(), $user->roles)) )
				{
					if( $price < $product->get_price('edit') )
					{
						$this->products[$productId]->onsale = true;
						$this->products[$productId]->price = $price;
						$names = $this->getRolesNames();
						if( $names && isset($names['lws_woovip_member']) )
							$this->products[$productId]->vip = $names['lws_woovip_member'];
					}
				}
			}
		}

		return $this->products[$productId];
	}

	/** @return (bool) should be visible
	 * @param $postId (false|int) any WP_Post ID: post, page or product. If false we look for current page.
	 * @param $user (false|int|WP_User) if false, we look for current user. */
	function isVisiblePost($postId=false, $user=false)
	{
		$vip = false;
		if( $postId = $this->getPostId($postId) )
		{
			$vip = \LWS\WOOVIP\Core\Cache::instance()->getVisibility(0, $postId);
			if( null === $vip )
			{
				// is vip posts anyway?
				$vip = !empty(\get_post_meta($postId, 'lws_woovip_show_for_vip_only', true));
				\LWS\WOOVIP\Core\Cache::instance()->getVisibility(0, $postId, $vip);
			}
		}
		if( !$vip )
		{
			return true;
		}

		$allowed = false;
		$user = $this->getUser($user);
		if( $user && $user->ID )
		{
			$allowed = \LWS\WOOVIP\Core\Cache::instance()->getVisibility($user->ID, $postId);
			if( null === $allowed )
			{
				// vip posts, is vip user?
				$allowed = !empty(array_intersect($this->getRoles(), $user->roles));
				\LWS\WOOVIP\Core\Cache::instance()->getVisibility($user->ID, $postId, $allowed);
			}
		}
		if( $allowed )
		{
			return true;
		}

		return false;
	}

	/** @return (WP_Query)
	 * alter query to hide some results depending on rules.
	 * @param $user (false|int|WP_User) if false, we look for current user. */
	function filterQuery(\WP_Query &$query, $user=false)
	{
		if( $query->is_search || (
			isset($query->query) && is_array($query->query) && (
				!isset($query->query['post_type']) || in_array($query->query['post_type'], array('product', 'page', 'post'))
			)
		))
		{
			$allowed = false;
			$user = $this->getUser($user);
			if( $user && $user->ID )
			{
				// is user vip anyway
				$allowed = !empty(array_intersect($this->getRoles(), $user->roles));
			}

			if( !$allowed )
			{
				// not vip user, hide vip posts
				$meta = $query->get('meta_query');
				if( empty($meta) )
					$meta = array();
				$meta[] = array(
					'relation' => 'OR',
					array(
						'key' => 'lws_woovip_show_for_vip_only',
						'value' => 'bug #23268', // This is ignored, but is necessary...
						'compare' => 'NOT EXISTS'
					),
					array(
						'key' => 'lws_woovip_show_for_vip_only',
						'value' => '',
						'compare' => '='
					),
				);

				$query->set('meta_query', $meta);
			}
		}
		return $query;
	}

	function getPostId($post=false)
	{
		if( $post === false )
		{
			if( \is_singular() )
			{
				global $post;
				if( isset($post) && $post && isset($post->ID) )
					return $post->ID;
			}
		}
		else if( is_a($post, '\WP_Post') )
			return $post->ID;
		else if( is_a($post, '\WC_Product') )
			return $post->get_id();
		else if( is_numeric($post) )
			return intval($post);
		return false;
	}

	/** @return WP_User or false if not log in.
	 * @param $user (false|int|WP_User) if false, we look for current user. */
	function getUser($user=false)
	{
		if( $user === false )
		{
			$user = \wp_get_current_user();
			return ($user->ID ? $user : false);
		}
		else if( is_a($user, '\WP_User') )
			return $user;
		else
			return \get_user_by('ID', $user);
		return false;
	}

	/** @return int or false if not log in.
	 * @param $user (false|int|WP_User) if false, we look for current user. */
	function getUserId($user=false)
	{
		if( $user === false )
			return \get_current_user_id();
		else if( is_a($user, '\WP_User') )
			return $user->ID;
		else if( is_numeric($user) )
			return intval($user);
		return false;
	}

	/** When user role is changed from anyway, we try to restore VIP roles immediatly.
	 * That function allows disabling that control (works as a mutex).
	 * To remove a VIP role, call that function frist with $enable = false, make your changes,
	 * then call that function again with $yes = true.
	 * @return false if user cannot be found or is already free to lose VIP role;
	 * or a WP_User instance. */
	function setMembershipRetention($enable, $user=false)
	{
		$u = $this->getUser($user);
		if( $u && $u->ID )
		{
			if( !isset($this->retention) )
				$this->retention = array();

			if( $enable )
			{
				if( isset($this->retention[$u->ID]) )
					unset($this->retention[$u->ID]);
			}
			else
			{
				if( isset($this->retention[$u->ID]) )
					$u = false;
				else
					$this->retention[$u->ID] = false;
			}
		}
		return $u;
	}

	/** @return $wpRoles
	 * @param $wpRoles (false|WP_Roles) if false, use the global $wp_roles */
	function renameRoles($wpRoles=false)
	{
		if( !empty($names = \LWS\WOOVIP\Core\Rules::instance()->getRolesNames()) )
		{
			if( !$wpRoles )
			{
				global $wp_roles;
				if( !isset($wp_roles) || !$wp_roles )
					$wp_roles = new WP_Roles();
				$wpRoles = &$wp_roles;
			}

			foreach( $names as $role => $name )
			{
				if( isset($wpRoles->role_names[$role]) )
				{
					$wpRoles->role_names[$role] = $name;
					$wpRoles->roles[$role]['name'] = $name;
				}
			}
		}
		return $wpRoles;
	}

	/** Get the Memberships the user is, or is not, a member.
	 * @param $user (false|int|WP_User) @see getUser()
	 * @return array of array, first entry is member, second is membership the user does not belong to. */
	function sortLoungesByUser($user=false)
	{
		return array(array(),array());
	}

	/** @param $terms (array) term ids @see getTaxonomies
	 * @param $taxonomy (string) in ['product_cat', 'category'] rules option key
	 * @param $in (array) memberships the user belong to @see sortLoungesByUser
	 * @param $out (array) memberships the user cannot see @see sortLoungesByUser
	 * @return true|false|$default return if explicitely grant or reject, else return $default */
	function isVisibleTaxonomy($terms, $taxonomy, $in, $out, $default=null)
	{
		return $default;
	}

	function isRisingEnabled()
	{
		return false;
	}

	function isRisingPrior()
	{
		return false;
	}

	/** @return 1 if r1 is better than r2, 0 means equal, -1 if r2 is better than r1 */
	function ruleCmp($r1, $r2)
	{
		if( !$r2->onsale )
			return $r1->onsale ? 1 : 0;
		if( !$r1->onsale )
			return $r2->onsale ? -1 : 0;

		if( $r1->worst && $r2->worst )
		{
			if( $this->isRisingPrior() )
				return -$this->priceCmp($r1->price, $r2->price);
			else
				return $this->priceCmp($r1->price, $r2->price);
		}
		else if( $r1->worst || $r2->worst )
		{
			if( $this->isRisingPrior() )
				return $r1->worst ? 1 : -1;
			else
				return $r2->worst ? 1 : -1;
		}
		else
		{
			return $this->priceCmp($r1->price, $r2->price);
		}
	}

	/** @return 1 if p1 is less than p2, 0 means equal, -1 if p2 is less than p1 */
	function priceCmp($p1, $p2)
	{
		if( $p1 < $p2 )
			return 1;
		if( $p2 < $p1 )
			return -1;
		return 0;
	}

	/** depending on rule and settings
	 * usually return the min. */
	function getBestPrice($price, $rule)
	{
		if( $rule->worst && $this->isRisingEnabled() )
			return max($price, $rule->price);
		else
			return min($price, $rule->price);
	}
}
