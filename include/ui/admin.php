<?php
namespace LWS\WOOVIP\Ui;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Create the backend menu and settings pages. */
class Admin
{
	public function __construct()
	{
		$pages = array(
			'lounges' => array(
				'id' => LWS_WOOVIP_PAGE,
				'dashicons' => '',
				'index'     => 70,
				'rights'    => 'edit_vip_members',
				'title'     => __("WooVIP", 'woovip-lite'),
				'subtitle'  => __("VIP Membership", 'woovip-lite'),
				'tabs'      => array()
			),
			'system' => array(
				'id'       		=> LWS_WOOVIP_PAGE . '-system',
				'title'    		=> __("System", 'woovip-lite'),
				'subtitle' 		=> __("System", 'woovip-lite'),
				'rights'    => 'edit_vip_members',
				'tabs' => array()
			),
		);

		$current = $this->getCurrentPage();
		if( LWS_WOOVIP_PAGE == $current )
		{
			$pages['lounges']['tabs'] = $this->getLoungesTabs();
		}
		else if( LWS_WOOVIP_PAGE . '-system' == $current )
		{
			$pages['system']['tabs'] = $this->getSystemTabs();
		}

		\lws_register_pages($pages);
		\add_action('admin_enqueue_scripts', array($this , 'scripts'));
	}

	private function getLoungesTabs()
	{
		$tabs = array(
			'single' => array(
				'id'     => 'main',
				'title'  => __("VIP Membership", 'woovip-lite'),
				'icon'	=> 'lws-icon-crown',
				'groups' => array(
					'main'	=> array(
						'id'	=> 'main',
						'title'	=> __("Membership settings", 'woovip-lite'),
						'icon'	=> 'lws-icon-crown',
						'color'	=> '#c79648',
						'text'	=> __("Set the rules of your Membership.", 'woovip-lite'),
						'fields' => array(
							'name' => array(
								'id'    => 'lws_woovip_single_lounge_name',
								'title' => __("Name your VIP membership", 'woovip-lite'),
								'type'  => 'text'
							),
							'redirection' => array(
								'id'    => 'lws_woovip_403_redirection',
								'title' => __("Forbidden pages redirects to", 'woovip-lite'),
								'type'  => 'lacselect',
								'extra' => array(
									'maxwidth'	=> "300px",
									'mode'		=> 'select',
									'help'		=> __("When a page or product is for Members, visitor is redirected to another page. Default is the 404 page.", 'woovip-lite'),
									'predefined' => 'page',
								)
							),
						)
					),
					'users'	=> array(
						'id'	=> 'users',
						'title'	=> __("Manage Members", 'woovip-lite'),
						'icon'	=> 'lws-icon-a-star',
						'color'	=> '#00768b',
						'class'	=> 'half',
						'text'	=> __("Manage the memberships of your users.", 'woovip-lite'),
						'fields' => array(
							'users_link' => array(
								'id'    => 'lws_woovip_users_link',
								'title' => __("Assign users membership", 'woovip-lite'),
								'type'  => 'custom',
									'extra' => array(
									'content' => sprintf("<a href='%s' target='_blank'>" . __("Assign user memberships in the Users page." . "</a>", 'woovip-lite'), \esc_attr(\admin_url('users.php'))),
								)
							)
						)
					),
					'rules'	=> array(
						'id'	=> 'users',
						'title'	=> __("Membership rules", 'woovip-lite'),
						'icon'	=> 'lws-icon-b-check',
						'color'	=> '#336666',
						'class'	=> 'half',
						'text'	=> __("Set the products, pages and post rules for your membership. In The free version, the rules are directly set on the wordpress/woocommerce pages", 'woovip-lite'),
						'fields' => array(
							'pages_link' => array(
								'id'    => 'lws_woovip_pages_link',
								'title' => __("Pages visibility", 'woovip-lite'),
								'type'  => 'custom',
									'extra' => array(
									'content' => sprintf("<a href='%s' target='_blank'>" . __("Edit the Wordpress Pages visibility.", 'woovip-lite') . "</a>", \esc_attr(\admin_url('edit.php?post_type=page'))),
								),
							),
							'posts_link' => array(
								'id'    => 'lws_woovip_posts_link',
								'title' => __("Posts visibility", 'woovip-lite'),
								'type'  => 'custom',
									'extra' => array(
									'content' => sprintf("<a href='%s' target='_blank'>" . __("Edit the Wordpress Posts visibility.", 'woovip-lite') . "</a>", \esc_attr(\admin_url('edit.php'))),
								),
							),
							'porducts_link' => array(
								'id'    => 'lws_woovip_products_link',
								'title' => __("Set the products rules", 'woovip-lite'),
								'type'  => 'custom',
									'extra' => array(
									'content' => sprintf("<a href='%s' target='_blank'>" . __("Edit the products prices and visibility.", 'woovip-lite') . "</a>", \esc_attr(\admin_url('edit.php?post_type=product'))),
								),
							),
						)
					),
				)
			),
		);
		return $tabs;
	}

	private function getSystemTabs()
	{
		$tabs = array(
			'data_management' => array(
				'id'     => 'data_management',
				'title'  => __("Data Management", 'woovip-lite'),
				'icon'   => 'lws-icon-components',
				'groups' => array(
					'delete' => array(
						'id'    => 'delete',
						'title' => __("Delete all data", 'woovip-lite'),
						'icon'  => 'lws-icon-delete-forever',
						'text'  => __("Remove all VIP memberships and user's memberships.", 'woovip-lite')
						. '<br/>' . __("Use it with care since this action is <b>not undoable</b>.", 'woovip-lite'),
						'fields' => array(
							'trigger_delete' => array(
								'id' => 'trigger_delete_all_woovip',
								'title' => __("Delete All Data", 'woovip-lite'),
								'type' => 'button',
								'extra' => array(
									'callback' => array($this, 'deleteAllData')
								),
							),
						)
					),
				)
			)
		);
		return $tabs;
	}

	protected function getCurrentPage()
	{
		if (isset($_REQUEST['page']) && ($current = \sanitize_text_field($_REQUEST['page'])))
			return $current;
		if (isset($_REQUEST['option_page']) && ($current = \sanitize_text_field($_REQUEST['option_page'])))
			return $current;
		return false;
	}

	public function scripts($hook)
	{
		\wp_enqueue_style('wv-menu-icon', LWS_WOOVIP_CSS . '/menu-icon.css', array(), LWS_WOOVIP_VERSION);
	}

	function deleteAllData($btnId, $data=array())
	{
		if( $btnId != 'trigger_delete_all_woovip' ) return false;

		if( !(isset($data['del_conf']) && \wp_verify_nonce($data['del_conf'], 'deleteAllData')) )
		{
			$label = __("If you really want to reset all WooVIP data, check this box and click on <i>'%s'</i> again.", 'woovip-lite');
			$label = sprintf($label, __("Delete All Data", 'woovip-lite'));
			$warn = __("This operation is not undoable!", 'woovip-lite');
			$tips = __("Consider making a backup of your database before continue.", 'woovip-lite');

			$nonce = \esc_attr(\wp_create_nonce('deleteAllData'));
			$str = <<<EOT
<p>
	<input type='checkbox' class='lws-ignore-confirm' id='del_conf' name='del_conf' value='{$nonce}' autocomplete='off'>
	<label for='del_conf'>{$label} <b style='color: red;'>{$warn}</b><br/>{$tips}</label>
</p>
EOT;
			return $str;
		}

		$wpInstalling = \wp_installing();
		\wp_installing(true); // should force no cache
		\do_action('lws_woovip_before_delete_all', $data);
		error_log("[WooVIP] Delete everything");

		global $wpdb;
		// clean options
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'lws_woovip_%'");
		// user meta
		$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'lws_woovip_%'");
		// post meta
		$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'lws_woovip_%'");

		$role = \get_role('lws_woovip_member');
		if( $role && empty($role->capabilities) )
		{
			foreach( \get_users(array('role'=>'lws_woovip_member')) as $user )
				$user->remove_role('lws_woovip_member');
			\remove_role('lws_woovip_member');
		}

		\do_action('lws_woovip_after_delete_all', $data);
		\wp_installing($wpInstalling);
		return __("WooVIP install has been cleaned up.", 'woovip-lite');
	}
}
