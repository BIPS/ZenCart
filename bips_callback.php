<?php
	require 'includes/application_top.php';

	global $db;

	$BIPS = $_POST;
	$hash = hash('sha512', $BIPS['transaction']['hash'] . MODULE_PAYMENT_BIPS_SECRET);

	header('HTTP/1.1 200 OK');
	print '1';

	if ($BIPS['hash'] == $hash && $BIPS['status'] == 1)
	{
		@$db->Execute("update " . TABLE_ORDERS . " set orders_status = " . MODULE_PAYMENT_BIPS_PAID_STATUS_ID . " where orders_id = " . intval($BIPS["custom"]["order_id"]));
	}
?>