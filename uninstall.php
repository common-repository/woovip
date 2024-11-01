<?php
if( !defined( 'WP_UNINSTALL_PLUGIN' ) ) exit();
@include_once dirname(__FILE__) . '/modules/woovip-pro/uninstall.php';

wp_clear_scheduled_hook('lws_woovip_daily_event');

\delete_option('lws_woovip_version');

$roles = array(
	'administrator' => array('edit_vip_members'),
	'shop_manager' => array('edit_vip_members'),
);
foreach( $roles as $slug => $caps )
{
	if( $role = \get_role($slug) )
	{
		foreach( $caps as $cap )
			$role->remove_cap($cap);
	}
}
