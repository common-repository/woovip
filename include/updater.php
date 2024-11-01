<?php
namespace LWS\WOOVIP;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** satic class to manage activation and version updates. */
class Updater
{
	/** @return array[ version => changelog ] */
	function getNotices()
	{
		$notes = array();

		$notes['1.0'] = <<<EOT
<b>WooVIP 1.0</b><br/>
<p>Initial release.</p>
<ul>
	<li>Set user as VIP</li>
	<li>Define page, post or product as VIP section</li>
	<li>Define VIP product price</li>
</ul>
EOT;

		return $notes;
	}

	static function checkUpdate()
	{
		$wpInstalling = \wp_installing();
		\wp_installing(true); // should force no cache

		if( version_compare(($from = get_option('lws_woovip_version', '0')), ($to = LWS_WOOVIP_VERSION), '<') )
		{
			\wp_suspend_cache_invalidation(false);
			$me = new self();
			$me->update($from, $to);
			$me->notice($from, $to);
		}

		\wp_installing($wpInstalling);
	}

	function notice($fromVersion, $toVersion)
	{
		if( version_compare($fromVersion, '1.0', '>=') )
		{
			$notices = $this->getNotices();
			$text = '';
			foreach($notices as $version => $changelog)
			{
				if( version_compare($fromVersion, $version, '<') && version_compare($version, $toVersion, '<=') ) // from < v <= new
					$text .= "<p>{$changelog}</p>";
			}
			if( !empty($text) )
				\lws_admin_add_notice('woovip-lite'.'-changelog-'.$toVersion, $text, array('level'=>'info', 'forgettable'=>true, 'dismissible'=>true));
		}
	}

	/** Update
	 * @param $fromVersion previously registered version.
	 * @param $toVersion actual version. */
	function update($fromVersion, $toVersion)
	{
		$this->from = $fromVersion;
		$this->to = $toVersion;

		if( empty($fromVersion) || \version_compare($fromVersion, '1.0.0', '<') )
		{
			$this->addCapacity();

			// add default role
			$name = _x("V.I.P.", "Default vip role name", 'woovip-lite');
			\add_role('lws_woovip_member', $name);
			\update_option('lws_woovip_single_lounge_name', $name);

			\update_option('lws_woovip_redirect_to_licence', 1);
		}

		update_option('lws_woovip_version', LWS_WOOVIP_VERSION);
	}

	/** Add 'edit_vip_members' and 'edit_vip_posts' capacity to 'administrator' and 'shop_manager'. */
	private function addCapacity()
	{
		$roles = array(
			'administrator' => array('edit_vip_members', 'edit_vip_posts'),
			'shop_manager' => array('edit_vip_members', 'edit_vip_posts'),
		);
		foreach( $roles as $slug => $caps )
		{
			if( $role = \get_role($slug) )
			{
				foreach( $caps as $cap )
				{
					if( !$role->has_cap($cap) )
						$role->add_cap($cap);
				}
			}
		}
	}

	/// dbDelta could write on standard output @see releaseLog()
	protected function grabLog()
	{
		ob_start(function($msg){
			if( !empty($msg) )
				error_log($msg);
		});
	}

	/// @see grabLog()
	protected function releaseLog()
	{
		ob_end_flush();
	}

}
