<?php
	class bips
	{
		var $code, $title, $description, $enabled, $payment;

		function bips()
		{
			global $order;
			$this->code = 'bips';
			$this->title = MODULE_PAYMENT_BIPS_TEXT_TITLE;
			$this->description = MODULE_PAYMENT_BIPS_TEXT_DESCRIPTION;
			$this->sort_order = MODULE_PAYMENT_BIPS_SORT_ORDER;
			$this->enabled = ((MODULE_PAYMENT_BIPS_STATUS == 'True') ? true : false);

			if ((int)MODULE_PAYMENT_BIPS_ORDER_STATUS_ID > 0)
			{
				$this->order_status = MODULE_PAYMENT_BIPS_ORDER_STATUS_ID;
				$payment = 'bips';
			}
			else if ($payment == 'bips')
			{
				$payment = '';
			}

			if (is_object($order)) $this->update_status();

			$this->email_footer = MODULE_PAYMENT_BIPS_TEXT_EMAIL_FOOTER;
		}

		function update_status()
		{
			global $db;
			global $order;

			if (($this->enabled == true) && ((int)MODULE_PAYMENT_BIPS_ZONE > 0))
			{
				$check_flag = false;
				$check = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_BIPS_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
				
				while (!$check->EOF)
				{
					if ($check->fields['zone_id'] < 1)
					{
						$check_flag = true;
						break;
					}
					else if ($check->fields['zone_id'] == $order->billing['zone_id'])
					{
						$check_flag = true;
						break;
					}

					$check->MoveNext();
				}

				if ($check_flag == false)
				{
					$this->enabled = false;
				}
			}

			if (!MODULE_PAYMENT_BIPS_API OR !strlen(MODULE_PAYMENT_BIPS_API))
			{
				print 'No Invoice API key';
				$this->enabled = false;
			}

			if (!MODULE_PAYMENT_BIPS_SECRET OR !strlen(MODULE_PAYMENT_BIPS_SECRET))
			{
				print 'No BIPS Merchant Secret';
				$this->enabled = false;
			}
		}

		function selection()
		{
			return array('id' => $this->code, 'module' => $this->title);
		}

		function javascript_validation()
		{
			return false;
		}

		function confirmation()
		{
			return false;
		}

		function process_button()
		{
			return false;
		}

		function pre_confirmation_check()
		{
			return false;
		}

		function before_process()
		{
			return false; 
		}

		// called upon clicking confirm (after before_process and after the order is created)
		function after_process()
		{
			global $insert_id, $order, $db;
					
			// change order status to value selected by merchant
			$db->Execute("update ". TABLE_ORDERS. " set orders_status = " . MODULE_PAYMENT_BIPS_UNPAID_STATUS_ID . " where orders_id = ". $insert_id);

			$ch = curl_init();
			curl_setopt_array($ch, array(
			CURLOPT_URL => 'https://bips.me/api/v1/invoice',
			CURLOPT_USERPWD => MODULE_PAYMENT_BIPS_API,
			CURLOPT_POSTFIELDS => 'price=' . number_format($order->info['total'], 2, '.', '') . '&currency=' . $order->info['currency'] . '&item=' . $item_name . '&custom=' . json_encode(array('order_id' => $insert_id, 'physical' => ($order->content_type == 'physical' ? 'true' : 'false'), 'returnurl' => rawurlencode(zen_href_link('account')), 'cancelurl' => rawurlencode(zen_href_link('account')))),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPAUTH => CURLAUTH_BASIC));
			$url = curl_exec($ch);
			curl_close($ch);
			
			$_SESSION['cart']->reset(true);
			zen_redirect($url);

			return false;
		}

		function get_error()
		{
			return false;
		}

		function check()
		{
			global $db;
			if (!isset($this->_check))
			{
				$check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_BIPS_STATUS'");
				$this->_check = $check_query->RecordCount();
			}
			
			return $this->_check;
		}

		function install()
		{
			global $db, $messageStack;
			if (defined('MODULE_PAYMENT_BIPS_STATUS'))
			{
				$messageStack->add_session('BIPS module already installed.', 'error');
				zen_redirect(zen_href_link(FILENAME_MODULES, 'set=payment&module=bips', 'NONSSL'));
				return 'failed';
			}

			$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) "
			."values ('Enable BIPS Module', 'MODULE_PAYMENT_BIPS_STATUS', 'True', 'Do you want to accept bitcoin payments via BIPS?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now());");

			$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) "
			."values ('BIPS API key', 'MODULE_PAYMENT_BIPS_API', '', 'Enter your BIPS Invoice API key', '6', '0', now());");

			$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) "
			."values ('BIPS Merchant Secret', 'MODULE_PAYMENT_BIPS_SECRET', '', 'Enter your Merchant Secret from BIPS', '6', '0', now());");

			$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) "
			."values ('Unpaid Order Status', 'MODULE_PAYMENT_BIPS_UNPAID_STATUS_ID', '" . DEFAULT_ORDERS_STATUS_ID .  "', 'Automatically set the status of unpaid orders to this value.', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

			$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) "
			."values ('Paid Order Status', 'MODULE_PAYMENT_BIPS_PAID_STATUS_ID', '2', 'Automatically set the status of paid orders to this value.', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
				
			$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) "
			."values ('Payment Zone', 'MODULE_PAYMENT_BIPS_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
			
			$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) "
			."values ('Sort order of display.', 'MODULE_PAYMENT_BIPS_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '2', now())");
		}

		function remove()
		{
			global $db;
			$db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
		}

		function keys()
		{
			return array(
				'MODULE_PAYMENT_BIPS_STATUS', 
				'MODULE_PAYMENT_BIPS_API',
				'MODULE_PAYMENT_BIPS_SECRET',
				'MODULE_PAYMENT_BIPS_UNPAID_STATUS_ID',
				'MODULE_PAYMENT_BIPS_PAID_STATUS_ID',
				'MODULE_PAYMENT_BIPS_SORT_ORDER',
				'MODULE_PAYMENT_BIPS_ZONE'
			);
		}
	}
?>