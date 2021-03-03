<?php

/********************************************************************************

 MachForm

  

 Copyright 2007-2016 Appnitro Software. This code cannot be redistributed without

 permission from http://www.appnitro.com/

 

 More info at: http://www.appnitro.com/

 ********************************************************************************/

	require('config.php');

	require('lib/db-session-handler.php');

	require('includes/init.php');



	

	if(empty($_COOKIE['mf_has_cookie'])){

		setcookie('mf_has_cookie','1', time()+3600*24*1, "/");

	}



	header("p3p: CP=\"IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT\"");

	

	require('includes/language.php');

	require('includes/db-core.php');

	require('includes/common-validator.php');

	require('includes/view-functions.php');

	require('includes/post-functions.php');

	require('includes/filter-functions.php');

	require('includes/entry-functions.php');

	require('includes/helper-functions.php');

	require('includes/theme-functions.php');

	require('lib/dompdf/autoload.inc.php');

	require('lib/google-api-client/autoload.php');

	require('lib/libsodium/autoload.php');

	require('lib/swift-mailer/swift_required.php');

	require('lib/HttpClient.class.php');

	require('lib/recaptchalib2.php');

	require('lib/php-captcha/php-captcha.inc.php');

	require('lib/text-captcha.php');

	require('hooks/custom_hooks.php');
	
	
	
	
	function curlPost($url, $data,$showError=1){
		$ch = curl_init();
		$header = [];
	
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$tmpInfo = curl_exec($ch);
		
		$errorno=curl_errno($ch);
		return $tmpInfo;
	}
	function unite_pay($formid,$amount,$xmpch,$key,$orderNo){
		
		
		$url='https://cwcwx.hhu.edu.cn/zhifu/payAccept.aspx';
		
		$data=array();
		$data['orderDate']=date('YmdHis',time()+3600*8);
		$data['orderNo']=$orderNo;
		$data['amount']=$amount;
		$data['notify_url']=$_SERVER["REQUEST_SCHEME"].'://'.$_SERVER["HTTP_HOST"].'/unitepay_notify.php';
		if($_SERVER["HTTP_HOST"]=='jrc.nhri.cn'){
			$data['return_url']=$_SERVER["REQUEST_SCHEME"].'://'.$_SERVER["HTTP_HOST"].'/app/wss/view.php?id='.$formid.'&done=1';
		}else{
			$data['return_url']=$_SERVER["REQUEST_SCHEME"].'://'.$_SERVER["HTTP_HOST"].'/view.php?id='.$formid.'&done=1';
		}
		$data['xmpch']=$xmpch;
		
		$data['sign']=md5('orderDate='.$data['orderDate'].'&orderNo='.$data['orderNo'].'&amount='.$data['amount'].'&xmpch='.$data['xmpch'].'&return_url='.$data['return_url'].'&notify_url='.$data['notify_url'].$key);

		$r=curlPost($url,$data);
		$r=str_replace('http','https',$r);
		$r=str_replace('<head>','<head><base href="https://cwcwx.hhu.edu.cn/zhifu/" />',$r);
		
		return $r;
	}
	
	
	
	

		

	$dbh 		= mf_connect_db();

	$ssl_suffix = mf_get_ssl_suffix();
	
	

	if(mf_is_form_submitted()){ 

		$input_array   = mf_sanitize($_POST);
		
		
		
		
		$form_properties = mf_get_form_properties($dbh,$input_array['form_id'],array('payment_merchant_type'));
				
		$form_id=$input_array['form_id'];	
				
		if($form_properties['payment_merchant_type']=='Unite_pay'){
			
			$_SESSION['mf_entry_hash'][$form_id]='';
		}
		
		
		$session_id = session_id();
		
								
		
		
		
		$money = (double) mf_get_payment_total($dbh,$form_id,$session_id,$input_array['page_number']);
		
		

		$submit_result = mf_process_form($dbh,$input_array);
		
		
		
		$query = "select 
						form_active,
						form_password,
						form_language,
						form_review,
						form_page_total,
						logic_field_enable,
						logic_page_enable,
						payment_price_type,
						logic_success_enable,
						form_encryption_enable,
						form_encryption_public_key,
						form_entry_edit_enable,
						form_entry_edit_resend_notifications,
						form_entry_edit_rerun_logics,
						form_entry_edit_auto_disable,
						form_entry_edit_auto_disable_period,
						form_entry_edit_auto_disable_unit   
					from 
						`".MF_TABLE_PREFIX."forms` where form_id=?";
		$params = array($form_id);
		
		$sth = mf_do_query($query,$params,$dbh);
		$row = mf_do_fetch_result($sth);
		
		
		$form_page_total    = (int) $row['form_page_total'];
		/*
		echo $form_page_total;
		var_dump($submit_result);
		*/
		
		
		
		
		
		
		if((!isset($submit_result['next_page_number']))||($submit_result['next_page_number']==($form_page_total+1))){
			
			if((!isset($submit_result['entry_id']))||(!$submit_result['entry_id'])){
				if(isset($_SESSION['review_id'])&&$_SESSION['review_id']){
					$submit_result['entry_id']=$_SESSION['review_id'];
				}else{
					$query = "SELECT `id` from `".MF_TABLE_PREFIX."form_{$form_id}_review` where session_id=?";
					$params = array($session_id);
					
					$sth = mf_do_query($query,$params,$dbh);
					$row = mf_do_fetch_result($sth);
							
					$submit_result['entry_id']= $row['id'];
					
					
				}

			
			}
		
		}
		
		
		if(!isset($input_array['password'])){ 

		

			if($submit_result['status'] === true){
				
			
				
				
				$form_properties = mf_get_form_properties($dbh,$input_array['form_id'],array('payment_merchant_type'));
				
			
				if((!isset($submit_result['next_page_number']))||($submit_result['next_page_number']==($form_page_total+1))){
					if($form_properties['payment_merchant_type']=='Unite_pay'){
						
						
						
						
							$form_propertiess = mf_get_form_properties($dbh,$input_array['form_id'],array('payment_merchant_type','payment_price_type','payment_price_amount','payment_paypal_rest_live_clientid','payment_paypal_rest_live_secret_key'));
							
							if($form_propertiess['payment_price_type']=='fixed'){
								/*
								var_dump($form_propertiess);die;
								*/
								$money=$form_propertiess['payment_price_amount'];
								
							}else{
							/*
								$query = "select 
											A.element_id,
											A.option_id,
											A.`price`,
											B.`position` 
										from 
											".MF_TABLE_PREFIX."element_prices A left join ".MF_TABLE_PREFIX."element_options B 
										  on 
											(A.form_id=B.form_id and A.element_id=B.element_id and A.option_id=B.option_id) 
									   where 
											A.form_id = ? 
									order by 
													A.element_id,B.position asc";
									$params = array($input_array['form_id']);
									
									$sth = mf_do_query($query,$params,$dbh);
									$current_price_settings = array();
									$money=0;
									while($row = mf_do_fetch_result($sth)){
										
									var_dump($row);
										
										
										
										$element_id = (int) $row['element_id'];
										$option_id = (int) $row['option_id'];
										$current_price_settings[$element_id][$option_id]  = $row['price'];
										
										$ky='element_'.$element_id;
										if(isset($input_array[$ky])){
											if($option_id==$input_array[$ky]){
												$money=$row['price'];
												
											}
											
										}
										if(!$money){
											if(isset($row['price'])){
												$money=$row['price'];
											}
							
										}
									}
								die;
								*/
								
								
								/*
								$total_payment = (double) mf_get_payment_total($dbh,$form_id,$session_id,0);
								echo $total_payment;
								$money = sprintf("%.2f",$total_payment);
													
								echo $money;
								die;
								
								
								echo $session_id;
								echo 'aaaa';
								echo $input_array['page_number'];
								echo 'aaa';
								echo $form_id;
								$money = (double) mf_get_payment_total($dbh,$form_id,$session_id,$input_array['page_number']);
								
								echo $money;
								die;
								
								*/
								
								
								
								
								
								
									
							}
							
				if(!$money){
$query = "select 
											A.element_id,
											A.option_id,
											A.`price`,
											B.`position` 
										from 
											".MF_TABLE_PREFIX."element_prices A left join ".MF_TABLE_PREFIX."element_options B 
										  on 
											(A.form_id=B.form_id and A.element_id=B.element_id and A.option_id=B.option_id) 
									   where 
											A.form_id = ? 
									order by 
													A.element_id,B.position asc";
									$params = array($input_array['form_id']);
									
									$sth = mf_do_query($query,$params,$dbh);
									$current_price_settings = array();
									//$money=0;
									while($row = mf_do_fetch_result($sth)){
										
									//var_dump($row);
										
										
										
										$element_id = (int) $row['element_id'];
										$option_id = (int) $row['option_id'];
										$current_price_settings[$element_id][$option_id]  = $row['price'];
										
										$ky='element_'.$element_id;
										if(isset($input_array[$ky])){
											if($option_id==$input_array[$ky]){
												$money=$row['price'];
												
											}
											
										}
										if(!$money){
											if(isset($row['price'])){
												$money=$row['price'];
											}
							
										}
									}
				}									
							
							
							
							
							
							
							
							
						
			$payment_data['payment_fullname'] = 'Unite_pay';
			$payment_data['form_id'] 		  = $input_array['form_id'];
			$payment_data['record_id'] 		  = $submit_result['entry_id'];
			$payment_data['date_created']	  = date("Y-m-d H:i:s");
			$payment_data['status']			  = 0;
			$payment_data['payment_status']   = 'unpaid'; 

			$query = "INSERT INTO `".MF_TABLE_PREFIX."form_payments`(
									`form_id`, 
									`record_id`, 
									`payment_id`, 
									`date_created`, 
									`payment_date`, 
									`payment_status`, 
									`payment_fullname`, 
									`payment_amount`, 
									`payment_currency`, 
									`payment_test_mode`,
									`payment_merchant_type`, 
									`status`, 
									`billing_street`, 
									`billing_city`, 
									`billing_state`, 
									`billing_zipcode`, 
									`billing_country`, 
									`same_shipping_address`, 
									`shipping_street`, 
									`shipping_city`, 
									`shipping_state`, 
									`shipping_zipcode`, 
									`shipping_country`) 
							VALUES (
									:form_id, 
									:record_id, 
									:payment_id, 
									:date_created, 
									:payment_date, 
									:payment_status, 
									:payment_fullname, 
									:payment_amount, 
									:payment_currency, 
									:payment_test_mode,
									:payment_merchant_type, 
									:status, 
									:billing_street, 
									:billing_city, 
									:billing_state, 
									:billing_zipcode, 
									:billing_country, 
									:same_shipping_address, 
									:shipping_street, 
									:shipping_city, 
									:shipping_state, 
									:shipping_zipcode, 
									:shipping_country)";		
			
			$params = array();
			$params[':form_id'] 		  	= $payment_data['form_id'];
			$params[':record_id'] 			= $payment_data['record_id'];
			$params[':payment_id'] 			= 'Unite_pay';
			$params[':date_created'] 		= $payment_data['date_created'];
			$params[':payment_date'] 		= date("Y-m-d H:i:s");
			$params[':payment_status'] 		= $payment_data['payment_status'];
			$params[':payment_fullname']  	= $payment_data['payment_fullname'];
			$params[':payment_amount'] 	  	= $money;
			$params[':payment_currency']  	= 'CNY';
			$params[':payment_test_mode'] 	= '';
			$params[':payment_merchant_type'] = 'unite_pay';
			$params[':status'] 			  	= $payment_data['status'];
			$params[':billing_street'] 		= '';
			$params[':billing_city']		= '';
			$params[':billing_state'] 		= '';
			$params[':billing_zipcode'] 	= '';
			$params[':billing_country'] 	= '';
			$params[':same_shipping_address'] = '';
			$params[':shipping_street'] 	= '';
			$params[':shipping_city'] 		= '';
			$params[':shipping_state'] 		= '';
			$params[':shipping_zipcode'] 	= '';
			$params[':shipping_country'] 	= '';

			mf_do_query($query,$params,$dbh);
						
						
						
							
						

						$r=unite_pay($payment_data['form_id'],$money,$form_propertiess['payment_paypal_rest_live_clientid'],$form_propertiess['payment_paypal_rest_live_secret_key'],$payment_data['record_id'].'_'.$input_array['form_id']);
						echo $r;die;
			
					}
				}
		

				if(!empty($submit_result['form_resume_url'])){ 

					$_SESSION['mf_form_resume_url'][$input_array['form_id']] = $submit_result['form_resume_url'];

					header("Location: http{$ssl_suffix}://".$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']."?id={$input_array['form_id']}&done=1");
					
					exit;

				}else if($submit_result['logic_page_enable'] === true){ 

					$target_page_id = $submit_result['target_page_id'];



					if(is_numeric($target_page_id)){

						header("Location: http{$ssl_suffix}://".$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']."?id={$input_array['form_id']}&mf_page={$target_page_id}");

						exit;

					}else if($target_page_id == 'payment'){

			

						$form_properties = mf_get_form_properties($dbh,$input_array['form_id'],array('payment_merchant_type'));


						if(!empty($submit_result['bypass_payment_page'])){

							header("Location: http{$ssl_suffix}://".$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']."?id={$input_array['form_id']}&done=1");

							exit;

						}



						if(in_array($form_properties['payment_merchant_type'], array('stripe','authorizenet','paypal_rest','braintree'))){

					

							$_SESSION['mf_form_payment_access'][$input_array['form_id']] = true;

							$_SESSION['mf_payment_record_id'][$input_array['form_id']] = $submit_result['entry_id'];



							header("Location: http{$ssl_suffix}://".$_SERVER['HTTP_HOST'].mf_get_dirname($_SERVER['PHP_SELF'])."/payment.php?id={$input_array['form_id']}");

							exit;

						}else if($form_properties['payment_merchant_type'] == 'paypal_standard'){

							echo "<script type=\"text/javascript\">top.location.replace('{$submit_result['form_redirect']}')</script>";

							exit;

						}

					}else if($target_page_id == 'review'){

						if(!empty($submit_result['origin_page_number'])){

							$page_num_params = '&mf_page_from='.$submit_result['origin_page_number'];

						}



						$_SESSION['review_id'] = $submit_result['review_id'];

						header("Location: http{$ssl_suffix}://".$_SERVER['HTTP_HOST'].mf_get_dirname($_SERVER['PHP_SELF'])."/confirm.php?id={$input_array['form_id']}{$page_num_params}");

						exit;

					}else if($target_page_id == 'success'){


						if(!empty($submit_result['logic_success_enable']) && (($logic_redirect_url = mf_get_logic_success_redirect_url($dbh,$input_array['form_id'],$submit_result['entry_id'])) != '') ){

							echo "<script type=\"text/javascript\">top.location.replace('{$logic_redirect_url}')</script>";

							exit;

						}else if(empty($submit_result['form_redirect'])){		

							header("Location: http{$ssl_suffix}://".$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']."?id={$input_array['form_id']}&done=1");

							exit;

						}else{

							echo "<script type=\"text/javascript\">top.location.replace('{$submit_result['form_redirect']}')</script>";

							exit;

						}

					}



				}else if(!empty($submit_result['review_id'])){ 

					

					if(!empty($submit_result['origin_page_number'])){

						$page_num_params = '&mf_page_from='.$submit_result['origin_page_number'];

					}

					

					$_SESSION['review_id'] = $submit_result['review_id'];

					header("Location: http{$ssl_suffix}://".$_SERVER['HTTP_HOST'].mf_get_dirname($_SERVER['PHP_SELF'])."/confirm.php?id={$input_array['form_id']}{$page_num_params}");

					exit;

				}else{

					if(!empty($submit_result['next_page_number'])){ 

						$_SESSION['mf_form_access'][$input_array['form_id']][$submit_result['next_page_number']] = true;

													

						header("Location: http{$ssl_suffix}://".$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']."?id={$input_array['form_id']}&mf_page={$submit_result['next_page_number']}");

						exit;

					}else{ 

						

						if(mf_is_payment_has_value($dbh,$input_array['form_id'],$submit_result['entry_id']) && empty($submit_result['bypass_payment_page'])){

							
							$_SESSION['mf_form_payment_access'][$input_array['form_id']] = true;

							$_SESSION['mf_payment_record_id'][$input_array['form_id']] = $submit_result['entry_id'];



							header("Location: http{$ssl_suffix}://".$_SERVER['HTTP_HOST'].mf_get_dirname($_SERVER['PHP_SELF'])."/payment.php?id={$input_array['form_id']}");

							exit;

						}else{

							if(empty($submit_result['form_redirect'])){		

								header("Location: http{$ssl_suffix}://".$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']."?id={$input_array['form_id']}&done=1");

								exit;

							}else{

								
								unset($_SESSION['mf_form_completed'][$input_array['form_id']]);

								

								echo "<script type=\"text/javascript\">top.location.replace('{$submit_result['form_redirect']}')</script>";

								exit;

							}

						}

					}

				}

			}else if($submit_result['status'] === false){ 

				$old_values 	= $submit_result['old_values'];

				$custom_error 	= @$submit_result['custom_error'];

				$error_elements = $submit_result['error_elements'];

				

				$form_params = array();

				$form_params['page_number'] = $input_array['page_number'];

				$form_params['populated_values'] = $old_values;

				$form_params['error_elements'] = $error_elements;

				$form_params['custom_error'] = $custom_error;

				

				$markup = mf_display_form($dbh,$input_array['form_id'],$form_params);

			}

		}else{ 

			

			if($submit_result['status'] === true){ 

				$markup = mf_display_form($dbh,$input_array['form_id']);

			}else{

				$custom_error = $submit_result['custom_error'];
				

				$form_params = array();

				$form_params['custom_error'] = $custom_error;

 				$markup = mf_display_form($dbh,$input_array['form_id'],$form_params);

			}

		}

	}else{

		$form_id 		= (int) trim($_GET['id']);

		$page_number	= (int) trim($_GET['mf_page']);

		

		$page_number 	= mf_verify_page_access($form_id,$page_number);

		

		$resume_key		= trim($_GET['mf_resume']);

		if(!empty($resume_key)){

			$_SESSION['mf_form_resume_key'][$form_id] = $resume_key;



			

			$_SESSION['mf_form_resume_url'][$form_id] = array();

			unset($_SESSION['mf_form_resume_url'][$form_id]);



			$_SESSION['mf_entry_hash'][$form_id] = array();

			unset($_SESSION['mf_entry_hash'][$form_id]);

		}



		$edit_key		= trim($_GET['mf_edit']);

		if(!empty($edit_key)){



			$_SESSION['mf_form_edit_key'][$form_id] = $edit_key;



			$_SESSION['mf_entry_hash'][$form_id] = array();

			unset($_SESSION['mf_entry_hash'][$form_id]);

		}

	
		if(!empty($_GET['done']) && (!empty($_SESSION['mf_form_completed'][$form_id]) || !empty($_SESSION['mf_form_resume_url'][$form_id]))){

			$markup = mf_display_success($dbh,$form_id);

		}else{

			$form_params = array();

			$form_params['page_number'] = $page_number;

			$markup = mf_display_form($dbh,$form_id,$form_params);

		}

	}
	
	
	
	
	
	
	

	

	header("Content-Type: text/html; charset=UTF-8");

	echo $markup;

	

?>

