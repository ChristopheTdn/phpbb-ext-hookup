<?php
/**
 *
 * @package hookup
 * @copyright (c) 2015 Martin Beckmann
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 * See https://www.phpbb.com/community/viewtopic.php?f=461&t=2259916
 */


namespace gn36\hookup\notification;

class date_added_rotation extends base
{
	protected $language_key = 'HOOKUP_NOTIFY_DATE_ADDED_ROTATION';
	public static $notification_option = array(
		'lang' 	=> 'HOOKUP_NOTIFY_DATE_ADDED_ROTATION_OPTION',
		'group'	=> 'NOTIFICATION_GROUP_HOOKUP',
	);

	public function get_type()
	{
		return 'gn36.hookup.notification.type.date_added_rotation';
	}

	public function is_available()
	{
		return $this->auth->acl_getf_global('f_hookup');
	}

	// Use parent for this one
	//public function find_users_for_notification($notification_data, $options = array())

	public function get_avatar()
	{
		//return $this->user_loader->get_avatar($this->get_data('user_id'));
		return "<img class='avatar' alt='hookup' src='{$this->phpbb_root_path}ext/gn36/hookup/fixtures/hookup_icon.png' />";
	}

	public function get_title()
	{
		return $this->user->lang($this->language_key, $this->get_data('topic_title'));
	}

	public function get_email_template()
	{
		return '@gn36_hookup/hookup_dates_added_rotation';
	}

	//public function get_reference()

	// Use parent for this one
	//public function get_email_template_variables()

	// Use parent for this one
	//public function create_insert_array($notification_data, $pre_create_data = array())

	/**
	 * Update a notification
	 *
	 * @param array $notification_data Data specific for this type that will be updated
	 */
	public function update_notifications($notification_data)
	{
		$old_notifications = array();
		$read_notifications = array();
		$sql = 'SELECT n.user_id, n.notification_read
			FROM ' . $this->notifications_table . ' n, ' . $this->notification_types_table . ' nt
			WHERE n.notification_type_id = ' . (int) $this->notification_type_id . '
				AND n.item_id = ' . static::get_item_id($notification_data) . '
				AND nt.notification_type_id = n.notification_type_id
				AND nt.notification_type_enabled = 1';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$old_notifications[] = $row['user_id'];
			if ($row['notification_read'])
			{
				$read_notifications[] = $row['user_id'];
			}
		}
		$this->db->sql_freeresult($result);

		// Find the new users to notify
		$notifications = $this->find_users_for_notification($notification_data);

		// Find the notifications we must delete
		$remove_notifications = array_diff($old_notifications, array_keys($notifications));

		// Find the notifications we must add
		$add_notifications = array();
		foreach (array_diff(array_keys($notifications), $old_notifications) as $user_id)
		{
			$add_notifications[$user_id] = $notifications[$user_id];
		}

		foreach (array_diff($read_notifications, $remove_notifications) as $user_id)
		{
			$add_notifications[$user_id] = $notifications[$user_id];
		}

		// Remove the necessary notifications
		$remove_notifications = array_merge($remove_notifications, $read_notifications);
		if (!empty($remove_notifications))
		{
			$sql = 'DELETE FROM ' . $this->notifications_table . '
				WHERE notification_type_id = ' . (int) $this->notification_type_id . '
					AND item_id = ' . static::get_item_id($notification_data) . '
					AND ' . $this->db->sql_in_set('user_id', $remove_notifications);
			$this->db->sql_query($sql);
		}

		// Add the necessary notifications
		$this->notification_manager->add_notifications_for_users($this->get_type(), $notification_data, $add_notifications);

		// return true to continue with the update code in the notifications service (this will update the rest of the notifications)
		return true;
	}
}
