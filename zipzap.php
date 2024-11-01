<?php
/*
	Plugin Name: ZipZap
	Plugin URI: http://wordpress.org/extend/plugins/zipzap/
	Description: Adds the ZipZap cash payment option into WP e-Commerce, WooCommerce and eShop.
	Version: 1.0.0
	Author: ZipZap
	Author URI: http://www.zipzapinc.com/
*/

	$nzshpcrt_gateways[$num]['name'] 		= __( 'Pay Cash With ZipZap', 'wpsc' );
	$nzshpcrt_gateways[$num]['internalname']= 'zipzap';
	$nzshpcrt_gateways[$num]['function'] 	= 'gateway_zipzap';
	$nzshpcrt_gateways[$num]['form'] 		= "form_zipzap";
	$nzshpcrt_gateways[$num]['submit_function'] = "submit_zipzap";
	$nzshpcrt_gateways[$num]['payment_type']= "zipzap";
	$nzshpcrt_gateways[$num]['display_name']= __( 'Pay Cash With ZipZap', 'wpsc' );

	function wpeczipzap_accepturl($transaction_id, $session_id)
	{
		$accepturl = get_option('transact_url');
		
		$params = array('zipzap_accept' => '1', 'transaction_id' => $transaction_id, 'sessionid' => $session_id);
		return add_query_arg($params, $accepturl);
	}
	function gateway_zipzap($separator, $sessionid)
	{
		global $wpdb,$wpsc_cart;
		
		$purchase_log_sql = $wpdb->prepare( "SELECT * FROM `".WPSC_TABLE_PURCHASE_LOGS."` WHERE `sessionid`= %s LIMIT 1", $sessionid );
		$purchase_log = $wpdb->get_results($purchase_log_sql,ARRAY_A) ;
		$user_id= $purchase_log[0]['user_ID'];
		 $usersql = "SELECT `" . WPSC_TABLE_SUBMITTED_FORM_DATA . "`.`id`, `" . WPSC_TABLE_SUBMITTED_FORM_DATA . "`.`value`, `" . WPSC_TABLE_CHECKOUT_FORMS . "`.`name`, `" . WPSC_TABLE_CHECKOUT_FORMS . "`.`unique_name` FROM `" . WPSC_TABLE_CHECKOUT_FORMS . "` RIGHT JOIN `" . WPSC_TABLE_SUBMITTED_FORM_DATA . "` ON `" . WPSC_TABLE_CHECKOUT_FORMS . "`.id = `" . WPSC_TABLE_SUBMITTED_FORM_DATA . "`.`form_id` WHERE `" . WPSC_TABLE_SUBMITTED_FORM_DATA . "`.`log_id`=" . $purchase_log[0]['id'] . " ORDER BY `" . WPSC_TABLE_CHECKOUT_FORMS . "`.`checkout_order`";
		  $userinfo = $wpdb->get_results( $usersql, ARRAY_A );
		  
		$cart_sql = "SELECT * FROM `".WPSC_TABLE_CART_CONTENTS."` WHERE `purchaseid`='".$purchase_log[0]['id']."'";
		$cart = $wpdb->get_results($cart_sql,ARRAY_A) ;
		// ZipZap post variables
		
		$zipzap_url	= 'https://www.cashpayment.com/Public/CashOrder';
	
		$continueurl = wpeczipzap_accepturl($transaction_id, $sessionid);
		
		$data['MerchantID'] = get_option('zipzap_merchant_id');
		$data['MerchantCustomerID'] = $user_id;
		$data['MerchantOrderID'] = $purchase_log[0]['id'];
		$data['ItemDescription'] = 'Payment for order No#'.$purchase_log[0]['id'];
		
		
		$data['ReturnURL'] = $continueurl;
		$data['NotifyURL'] = add_query_arg( 'zipzap_callback', 'true', home_url( '/' ) );
		
		
		$data['CustomerEmailAddress'] =  (string)$userinfo[8]['value'];
		$data['CustomerFirstName'] = (string) $userinfo[0]['value'];
		$data['CustomerLastName'] =  (string)$userinfo[1]['value'];
		$data['CustomerAddress'] = (string) $userinfo[3]['value']." ".$userinfo[4]['value']." ".$userinfo[6]['value'];
		$data['CustomerPhoneNumber'] =  (string)$userinfo[7]['value'];
		$data['CustomerPayCountry'] = (string) wpsc_get_customer_meta( 'billing_country' );
		// Get Currency details abd price
		$currency_code = $wpdb->get_results("SELECT `code` FROM `".WPSC_TABLE_CURRENCY_LIST."` WHERE `isocode`='".wpsc_get_customer_meta( 'billing_country' )."' LIMIT 1",ARRAY_A);
		$data['Currency'] = $currency_code[0]['code'];
		
		
	
		$decimal_places = 2;
		$total_price = 0;
		$i = 1;
		$all_donations = true;
		$all_no_shipping = true;
	
		foreach($cart as $item)
		{
			$product_data = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `" . $wpdb->posts . "` WHERE `id`= %d LIMIT 1", $item['prodid'] ), ARRAY_A );
			$product_data = $product_data[0];
	
			$data1['amount_'.$i] = number_format(sprintf("%01.2f",  $item['price']),$decimal_places,'.','');
			$data1['quantity_'.$i] = $item['quantity'];
	
			if($item['donation'] !=1)
			{
				$all_donations = false;
				$data1['shipping_'.$i] = number_format($item['pnp'],$decimal_places,'.','');
				$data1['shipping2_'.$i] = number_format($item['pnp'],$decimal_places,'.','');
			}
			else
			{
				$data1['shipping_'.$i] = number_format(0,$decimal_places,'.','');
				$data1['shipping2_'.$i] = number_format(0,$decimal_places,'.','');
			}
	
			if($product_data['no_shipping'] != 1) {
				$all_no_shipping = false;
			}
	
	
			$total_price = $total_price + ($data1['amount_'.$i] * $data1['quantity_'.$i]);
	
			if( $all_no_shipping != false )
				$total_price = $total_price + $data1['shipping_'.$i] + $data1['shipping2_'.$i];
	
			$i++;
		}
		
		$base_shipping = $purchase_log[0]['base_shipping'];
		if(($base_shipping > 0) && ($all_donations == false) && ($all_no_shipping == false))
		{
			$total_price += number_format($base_shipping,$decimal_places,'.','');
		}
	
		$data['Amount'] = $total_price;
		if($total_price<=get_option('zipzap_order_limit'))	
		{
		$output = "
			<form id=\"zipzap_form\" name=\"zipzap_form\" method=\"post\" action=\"$zipzap_url\">\n";
	
		foreach($data as $n=>$v) {
				$output .= "			<input type=\"hidden\" name=\"$n\" value=\"$v\" />\n";
		}
		$output .= "</form>";
		echo($output);
		echo "<script language=\"javascript\" type=\"text/javascript\">document.getElementById('zipzap_form').submit();</script>";
		exit();
		}
		else
		{
			$output = "Sorry !!! We Can't Processed Your Order as maximum order total limit which can be processed with ZipZap is:".get_option('zipzap_order_limit');
			$output .= "Please <a href='javascript:' onclick='history.go(-1); return false'> Go Back</a> and choose another payment type.";
			echo($output);	
		}
	}

	function nzshpcrt_zipzap_callback()
	{
		global $wpdb;
		
		if(isset($_REQUEST['MerchantID']) && isset($_REQUEST['zipzap_callback']))
		{
			$merchantID = trim($_REQUEST['MerchantID']);
			$merchantCustomerID = trim($_REQUEST['MerchantCustomerID']);
			$orderId 			= trim($_REQUEST['MerchantOrderID']);
			$transactionId 		= trim($_REQUEST['PaymentCode']);
			$amount 			= trim($_REQUEST['Amount']);
			$hash_code			= trim(get_option('zipzap_hash_code'));
			# verify supplied signature:
			$receivedSignature =  trim($_REQUEST['Hash']);
			  
			//  $expectedSignature = hash_hmac('sha1', "{$merchantCustomerID}&{$orderId}&{$amount}&99430bbada6de163d2", MODULE_PAYMENT_ZIPZAP_API_SECRET);
			$expectedSignature = hash("md5","$merchantID$merchantCustomerID$orderId$amount$hash_code");
				//$expectedSignature = md5("$merchantID$merchantCustomerID$orderId$amount$hash_code");
			
			$verified = $receivedSignature == $expectedSignature;
	
			 if ($verified==1) 
			 {
				 $status = $_REQUEST['Status'];
				$transaction_id	=  $_REQUEST['PaymentCode'];
				switch($status)
				{
					case 'PAID':
					
						$wpdb->query("update ".WPSC_TABLE_PURCHASE_LOGS." set transactid='".(string)$transaction_id."',processed='". get_option('zipzap_processing_order_status')."',notes='Zipzap Transaction Submitted Successfully [Transaction ID: " . $transactionId . "]' where id='".$orderId."'" );
						break;
	
					case 'CANCELED': // if it fails, delete it
						$wpdb->query("update ".WPSC_TABLE_PURCHASE_LOGS." set transactid='".(string)$transaction_id."',processed='". get_option('zipzap_failed_order_status')."',notes='Order has been canceled and customer has not paid.' where id='".$orderId."'" );
						break;
	
					case 'ERROR':     
						 $error = $_REQUEST['ErrorDescription'] ;
						$wpdb->query("update ".WPSC_TABLE_PURCHASE_LOGS." set transactid='".(string)$transaction_id."',processed='". get_option('zipzap_pending_order_status')."',notes='Zipzap Checkout Failed: " . $error."' where id='".$orderId."'" );
						break;
	
					default: // if nothing, do nothing, safest course of action here.
						break;
	
				}
			}
		}
	}
/**
 * submit_zipzap function.
 *
 * Use this for now, but it will eventually be replaced with a better form API for gateways
 * @access public
 * @return void
 */	function submit_zipzap()
	{
	
		
		if(isset($_POST['zipzap_merchant_id']))
		{
			update_option('zipzap_merchant_id', $_POST['zipzap_merchant_id']);
		}
	
		if(isset($_POST['zipzap_hash_code']))
		{
			update_option('zipzap_hash_code', $_POST['zipzap_hash_code']);
		}
	
		if(isset($_POST['zipzap_order_limit']))
		{
			update_option('zipzap_order_limit', $_POST['zipzap_order_limit']);
		}
	
		if(isset($_POST['zipzap_pending_order_status']))
		{
			update_option('zipzap_pending_order_status', $_POST['zipzap_pending_order_status']);
		}
	
		if(isset($_POST['zipzap_processing_order_status']))
		{
			update_option('zipzap_processing_order_status', $_POST['zipzap_processing_order_status']);
		}
	
		if(isset($_POST['zipzap_failed_order_status']))
		{
			update_option('zipzap_failed_order_status', $_POST['zipzap_failed_order_status']);
		}
	
		if (!isset($_POST['zipzap_form'])) $_POST['zipzap_form'] = array();
		foreach((array)$_POST['zipzap_form'] as $form => $value)
		{
			update_option(('zipzap_form_'.$form), $value);
		}
		return true;
	}
	
	function form_zipzap()
	{
		global $wpsc_purchlog_statuses;
		$zipzap_server = get_option('zipzap_server');
		$zipzap_server1 = "";
		$zipzap_server2 = "";
		switch($zipzap_server)
		{
			case 'live':
				$zipzap_server2 = "checked ='checked'";
				break;
			case 'test':
				$zipzap_server1 = "checked ='checked'";
				break;
		}
		
			$dropdown_options = '';
			$dropdown_options2 = '';
			$dropdown_options3 = '';
			foreach ( $wpsc_purchlog_statuses as $status ) {
				$selected = $selected2=$selected3='';
				if (  get_option('zipzap_pending_order_status') ==$status['order'] ) {
					$selected = 'selected="selected"';
				}
				if (  get_option('zipzap_processing_order_status') ==$status['order'] ) {
					$selected2 = 'selected="selected"';
				}
				if (  get_option('zipzap_failed_order_status') == $status['order'] ) {
					$selected3 = 'selected="selected"';
				}
				$dropdown_options .= '<option value="' . esc_attr( $status['order'] ) . '" ' . $selected . '>' . esc_html( $status['label'] ) . '</option>';
				$dropdown_options2 .= '<option value="' . esc_attr( $status['order'] ) . '" ' . $selected2 . '>' . esc_html( $status['label'] ) . '</option>';
				$dropdown_options3 .= '<option value="' . esc_attr( $status['order'] ) . '" ' . $selected3 . '>' . esc_html( $status['label'] ) . '</option>';
			}
	
			
		$output = "
			<tr>
				<td>" . __( 'Merchant ID', 'wpsc' ) . "</td>
				<td>
					<input type='text' size='40' value='" . get_option( 'zipzap_merchant_id' ) . "' name='zipzap_merchant_id' />
					<p class='description'>
						" . __( 'This should be set to your Merchant ID that has been set up in the ZipZap interface.', 'wpsc' ) . "
					</p>
				</td>
			</tr>
			<tr>
				<td>" . __( 'Hash Code', 'wpsc' ) . "</td>
				<td>
					<input type='text' size='40' value='" . get_option( 'zipzap_hash_code' ) . "' name='zipzap_hash_code' />
			</tr>
			
			<tr>
				<td>" . __( 'ZipZap Order Limit URL', 'wpsc' ) . "</td>
				<td>
					<input type='text' size='40' value='" . get_option( 'zipzap_order_limit' ) . "' name='zipzap_order_limit' />
			</tr>
			
			<tr>
				<td>" . __( 'ZipZap Pending Order Status', 'wpsc' ) . "</td>
				<td>
					<select name='zipzap_pending_order_status'>" . $dropdown_options . "</select>
			</tr>
			<tr>
				<td>" . __( 'ZipZap Processing Order Status', 'wpsc' ) . "</td>
				<td>
					<select name='zipzap_processing_order_status'>" . $dropdown_options2 . "</select>
			</tr>
			<tr>
				<td>" . __( 'ZipZap Failed Order Status', 'wpsc' ) . "</td>
				<td>
					<select name='zipzap_failed_order_status'>" . $dropdown_options3 . "</select>
			</tr>
			";
		return $output;
	}

add_action('init', 'nzshpcrt_zipzap_callback');

add_filter('wpsc_merchant_v2_gateway_loop_items', 'call_gateway' );

function call_gateway($gateways){
	global $wpdb,$wpsc_cart,$sessionid;
	$total	=  wpsc_cart_total(false);
	if($total>get_option('zipzap_order_limit'))	
	{
		for($i=0;$i<count($gateways);$i++)
		{
			if($gateways[$i]['internalname']=='zipzap')
			{
				unset($gateways[$i]);
				break;
			}
		}
	}
	return $gateways;
}

?>
