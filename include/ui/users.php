<?php
namespace LWS\WOOVIP\Ui;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage VIP users. */
class Users
{
	function __construct()
	{
		\add_action('set_user_role', array(&$this, 'roleChanged'), 9999, 3);
		\add_action('setup_theme', array(&$this, 'renameRole'));
		\add_action('init', array(&$this, 'registerUi'));
		\add_action('lws_woovip_ensure_user_role_order', array(&$this, 'ensureUserRoleOrder'), 10, 1);
	}

	function registerUi()
	{
		if( \apply_filters('lws_woovip_single_lounge_admin', true, 'users', $this) )
		{
			\add_filter('manage_users_columns', array($this, 'isVIPColumn'), 20);
			\add_filter('manage_users_custom_column', array($this, 'isVIPRow'), 10, 3);

			\add_filter('bulk_actions-users', array($this, 'registerBulkAction'));
			\add_filter('handle_bulk_actions-users', array($this, 'handleBulkAction'), 10, 3);
		}
	}

	/** override read values, that's enough */
	function renameRole()
	{
		\LWS\WOOVIP\Core\Rules::instance()->renameRoles();
	}

	/** If role changed from elsewhere, restore the VIP roles. */
	function roleChanged($userId, $role, $old_roles)
	{
		if( $user = \LWS\WOOVIP\Core\Rules::instance()->setMembershipRetention(false, $userId) )
		{
			foreach( array_intersect(\LWS\WOOVIP\Core\Rules::instance()->getRoles(), $old_roles) as $role )
			{
				$user->add_role($role);
			}
			$this->ensureUserRoleOrder($user);
			\LWS\WOOVIP\Core\Rules::instance()->setMembershipRetention(true, $user);
		}
	}

	/** the following code is here to prevent some poor coded plugin failure,
	 * 	like using wpml with smart-manager-for-wp-e-commerce or woo-confirmation-email.
	 *	Totally useless for us, but we think about the best for our customers.
	 *	So, ensure roles and capabilities order. */
	function ensureUserRoleOrder($user)
	{
		$caps = get_user_meta($user->ID, $user->cap_key, true);
		if( is_array($caps) )
		{
			\uksort($caps, array($this, 'capSort'));
			\update_user_meta($user->ID, $user->cap_key, $caps);
		}
	}

	/** Some plugins requires 'adminstrator' at index zero to detect them,
	 * or cause error if the first index is not a role.
	 * some other plugins doesn't care when they add capabilities to a user.
	 * Using both together could result in unexpected behavior.
	 * That sort should resolve that kind of laziness. */
	function capSort($a, $b)
	{
		if( $a == $b ) return 0;
		static $dftroles = array('administrator','editor','author','contributor','subscriber');
		$ia = array_search($a, $dftroles);
		$ib = array_search($b, $dftroles);
		if( false !== $ia || false !== $ib )
		{
			if( false === $ia ) return 1;
			if( false === $ib ) return -1;
			return $ia - $ib;
		}
		$wp_roles = \wp_roles();
		$ra = $wp_roles->is_role($a);
		$rb = $wp_roles->is_role($b);
		if( $ra == $rb ) return 0;
		return $ra ? -1 : 1;
	}

	function registerBulkAction($actions)
	{
		if( \current_user_can('edit_vip_members') )
		{
			$actions['lws_woovip_add'] = __("Set as VIP", 'woovip-lite');
			$actions['lws_woovip_del'] = __("Remove from VIP", 'woovip-lite');
		}
		return $actions;
	}

	function handleBulkAction($redirectTo, $doaction, $userIds)
	{
		if( !\current_user_can('edit_vip_members') )
			return $redirectTo;

		$roles = \LWS\WOOVIP\Core\Rules::instance()->getRoles();

		if( $doaction == 'lws_woovip_add' )
		{
			$count = 0;
			foreach( $userIds as $userId )
			{
				if( $user = \get_user_by('ID', $userId) )
				{
					foreach( $roles as $role )
						$user->add_role($role);
					$this->ensureUserRoleOrder($user);
					++$count;
				}
			}
			$redirectTo = add_query_arg( 'bulk_vip_users', $count, $redirectTo);
		}
		else if( $doaction == 'lws_woovip_del' )
		{
			$count = 0;
			foreach( $userIds as $userId )
			{
				if( $user = \get_user_by('ID', $userId) )
				{
					foreach( $roles as $role )
						$user->remove_role($role);
					$this->ensureUserRoleOrder($user);
					++$count;
				}
			}
			$redirectTo = add_query_arg( 'bulk_no_vip_users', $count, $redirectTo);
		}
		return $redirectTo;
	}

	function isVIPColumn($column)
	{
		$column['lws_woovip_member'] = __("Is VIP", 'woovip-lite');
		return $column;
	}

	function isVIPRow($val, $column_name, $userId)
	{
		switch($column_name)
		{
			case 'lws_woovip_member' :
				$yes = false;
				if( $userData = \get_userdata($userId) )
					$yes = !empty(array_intersect(\LWS\WOOVIP\Core\Rules::instance()->getRoles(), $userData->roles));
				return ($yes ? __("Yes", 'woovip-lite') : __("No", 'woovip-lite'));
			break;
		default:
		}
		return $val;
	}
}
