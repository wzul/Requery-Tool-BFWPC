<?php

/**
 * Plugin Name: Requery for Billplz for WP Charitable
 * Description: WARNING! This plugin is just for education and purpose only and not intended for production use!
 * Author: Wan Zulkarnain
 * 
 **/
 
// offset value must increase by 10!
// https://yourwebsiteurl.com/index.php?mode_validate=production&offset_validate=0

add_action( 'init', 'validate_all_wan' );

function validate_all_wan(){
  if (!isset($_GET['offset_validate'])){
    return;
  }
  
  if (!isset($_GET['mode_validate'])){
    return;
  }
  
  global $wpdb;

  $offset_validate = $_GET['offset_validate'];
  $mode_validate = $_GET['mode_validate'];
  
  $rows_validate = $wpdb->get_results("SELECT * FROM {$wpdb->options} WHERE option_name LIKE 'billplz_charitable_bill_id_%' LIMIT 10 OFFSET  $offset_validate");
  
  foreach($rows_validate as $row){
    $option_name = $row->option_name;
    $option_value = $row->option_value;
    
    if ('charitable-completed' == get_post_status($option_value)) {
        continue;
    }
    $bill_id = str_replace("billplz_charitable_bill_id_", "", $option_name);
  
    $donation = charitable_get_donation((int) $option_value);
    $gateway = new Charitable_Gateway_Billplz();
    $keys = $gateway->get_keys();
    $api_key = $keys['api_key'];
    
    if ($mode_validate == 'production'){
      $process = curl_init("https://www.billplz.com/api/v3/bills/$bill_id");
    } else {
      $process = curl_init("https://www.billplz-sandbox.com/api/v3/bills/$bill_id");
    }

    curl_setopt($process, CURLOPT_HEADER, 0);
    curl_setopt($process, CURLOPT_USERPWD, $api_key . ":");
    curl_setopt($process, CURLOPT_TIMEOUT, 10);
    curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($process, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($process, CURLOPT_SSL_VERIFYPEER, 0);
    $return = curl_exec($process);
    curl_close($process);
    
    $bill = json_decode($return, true);
    
    if ($bill['paid']) {
      if ('charitable-completed' != get_post_status($option_value)) {
        $message = sprintf('%s: %s', __('Billplz Bill ID', 'charitable'), $bill['id']);
        $message .= '<br>Bill URL: ' . $bill['url'];
        $donation->update_donation_log($message);
        $donation->update_status('charitable-completed');
      }
      
      echo "Status update done for bill id $bill_id for donation id $option_value <br />";
    }
  }
  exit;
}

