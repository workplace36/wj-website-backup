<?php

/**
 * Plugin Name: WJ Website Backup
 * Description: Secure, memory-safe WordPress database and website file backups by Webjooll.
 * Version: 4.11.19
 * Author: Webjooll
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) exit;

define('WJBA43_VERSION','4.11.19');
define('WJBA43_AUTH_URL','https://backup.webjooll.com/api/auth.php');
define('WJBA43_DB_URL','https://backup.webjooll.com/api/backup.php');
define('WJBA43_MP_URL','https://backup.webjooll.com/api/multipart.php');
define('WJBA43_STATUS_URL','https://backup.webjooll.com/api/status.php');
define('WJBA43_DOWNLOAD_URL','https://backup.webjooll.com/api/download.php');
define('WJBA43_DISCONNECT_URL','https://backup.webjooll.com/api/disconnect.php');
define('WJBA43_PLANS_URL','https://backup.webjooll.com/api/plans.php');
define('WJBA43_CREATE_SUB_URL','https://backup.webjooll.com/api/create-subscription.php');
define('WJBA43_CLAIM_TOKEN_URL','https://backup.webjooll.com/api/claim-token.php');
define('WJBA43_RECONNECT_URL','https://backup.webjooll.com/api/reconnect.php');
define('WJBA43_SUB_STATUS_URL','https://backup.webjooll.com/api/subscription-status.php');
define('WJBA43_CHECKOUT_ORDER_URL','https://backup.webjooll.com/api/create-checkout-order.php');
define('WJBA43_VERIFY_CHECKOUT_URL','https://backup.webjooll.com/api/verify-checkout-payment.php');
define('WJBA43_VERIFY_UPGRADE_URL','https://backup.webjooll.com/api/verify-upgrade-payment.php');
define('WJBA43_OPTION_KEY','wjba_settings');

define('WJBA43_CRON_HOOK','wjba43_daily_backup');
define('WJBA43_CHUNK_SIZE',8*1024*1024);

function wjba43_notice($message,$type='success'){
    $type=in_array($type,['success','error','warning','info'],true)?$type:'success';
    return '<div class="notice notice-'.esc_attr($type).' is-dismissible"><p>'.$message.'</p></div>';
}
function wjba43_get_settings(){ $d=['website_code'=>'','api_token'=>'','plan_name'=>'','max_website_gb'=>0,'storage_limit_gb'=>0,'retention_days'=>0,'subscription_status'=>'','razorpay_subscription_id'=>'','connection_status'=>'','pending_claim_secret'=>'','pending_local_subscription_id'=>0,'pending_razorpay_subscription_id'=>'','manual_disconnect'=>0,'backup_history_cache'=>['backups'=>[]]]; $s=get_option(WJBA43_OPTION_KEY,[]); return wp_parse_args(is_array($s)?$s:[],$d); }
function wjba43_get_country_code() {
    $locale = function_exists('get_locale') ? (string)get_locale() : '';
    $locale = str_replace('-', '_', $locale);
    $parts = explode('_', $locale);
    if (!empty($parts[1]) && preg_match('/^[A-Z]{2}$/', strtoupper($parts[1]))) {
        return strtoupper($parts[1]);
    }

    // WordPress commonly defaults to en_US when no country-specific locale is set.
    // Administrators can override this through the filter when needed.
    $country = apply_filters('wjba_country_code', 'US');
    return preg_match('/^[A-Z]{2}$/', strtoupper((string)$country))
        ? strtoupper((string)$country)
        : '';
}
function wjba43_register_settings(){register_setting('wjba43_settings_group',WJBA43_OPTION_KEY,['type'=>'array','sanitize_callback'=>'wjba43_sanitize_settings','default'=>[]]);}
add_action('admin_init','wjba43_register_settings');
function wjba43_sanitize_settings($in){
 $in=is_array($in)?$in:[];
 $old=wjba43_get_settings();
 return [
  'website_code'=>array_key_exists('website_code',$in)?strtoupper(sanitize_text_field($in['website_code'])):$old['website_code'],
  /* Empty API token is intentional when Stop Connection revokes access. */
  'api_token'=>array_key_exists('api_token',$in)?sanitize_text_field($in['api_token']):$old['api_token'],
  'plan_name'=>array_key_exists('plan_name',$in)?sanitize_text_field($in['plan_name']):$old['plan_name'],
  'max_website_gb'=>array_key_exists('max_website_gb',$in)?(float)$in['max_website_gb']:(float)$old['max_website_gb'],
  'storage_limit_gb'=>array_key_exists('storage_limit_gb',$in)?(float)$in['storage_limit_gb']:(float)$old['storage_limit_gb'],
  'retention_days'=>array_key_exists('retention_days',$in)?(int)$in['retention_days']:(int)$old['retention_days'],
  'subscription_status'=>array_key_exists('subscription_status',$in)?sanitize_text_field($in['subscription_status']):$old['subscription_status'],
  'razorpay_subscription_id'=>array_key_exists('razorpay_subscription_id',$in)?sanitize_text_field($in['razorpay_subscription_id']):$old['razorpay_subscription_id'],
  'connection_status'=>array_key_exists('connection_status',$in)?sanitize_text_field($in['connection_status']):$old['connection_status'],
  'pending_claim_secret'=>array_key_exists('pending_claim_secret',$in)?sanitize_text_field($in['pending_claim_secret']):$old['pending_claim_secret'],
  'pending_local_subscription_id'=>array_key_exists('pending_local_subscription_id',$in)?(int)$in['pending_local_subscription_id']:(int)$old['pending_local_subscription_id'],
  'pending_razorpay_subscription_id'=>array_key_exists('pending_razorpay_subscription_id',$in)?sanitize_text_field($in['pending_razorpay_subscription_id']):$old['pending_razorpay_subscription_id'],
  /* This must accept 1 during manual disconnect and 0 during reconnect. */
  'manual_disconnect'=>array_key_exists('manual_disconnect',$in)?(int)$in['manual_disconnect']:(int)($old['manual_disconnect']??0),
  'backup_history_cache'=>array_key_exists('backup_history_cache',$in)&&is_array($in['backup_history_cache'])?$in['backup_history_cache']:(is_array($old['backup_history_cache']??null)?$old['backup_history_cache']:['backups'=>[]]),
 ];
}
function wjba43_activate(){ wp_clear_scheduled_hook(WJBA43_CRON_HOOK); }
register_activation_hook(__FILE__,'wjba43_activate');
function wjba43_deactivate(){wp_clear_scheduled_hook(WJBA43_CRON_HOOK);}
register_deactivation_hook(__FILE__,'wjba43_deactivate');

/*
 * Full local cleanup on plugin UNINSTALL.
 * Deactivation only stops cron; deleting the plugin removes all plugin
 * connection/subscription/cache state so a fresh reinstall starts clean.
 * The remote backup/Razorpay subscription is intentionally NOT cancelled
 * here because uninstalling a WordPress plugin must not make a billing
 * decision silently.
 */
function wjba43_uninstall(){
    wp_clear_scheduled_hook(WJBA43_CRON_HOOK);
    delete_option(WJBA43_OPTION_KEY);
}
register_uninstall_hook(__FILE__,'wjba43_uninstall');
add_action(WJBA43_CRON_HOOK,'wjba43_daily_backup');
function wjba43_headers(){
    $s=wjba43_get_settings();
    return [
        'X-WJ-Website-Code' => $s['website_code'],
        'X-WJ-API-Token'    => $s['api_token'],
        'Accept'            => 'application/json',
    ];
}

function wjba43_default_plans(){return ['plans'=>[
 ['id'=>4,'name'=>'Small','price'=>149,'currency'=>'INR','max_website_gb'=>2,'storage_limit_gb'=>10,'retention_days'=>7,'backup_frequency'=>'daily','configured'=>false],
 ['id'=>1,'name'=>'Basic','price'=>299,'currency'=>'INR','max_website_gb'=>10,'storage_limit_gb'=>50,'retention_days'=>7,'backup_frequency'=>'daily','configured'=>false],
 ['id'=>2,'name'=>'Business','price'=>499,'currency'=>'INR','max_website_gb'=>25,'storage_limit_gb'=>150,'retention_days'=>15,'backup_frequency'=>'daily','configured'=>false],
 ['id'=>3,'name'=>'Pro','price'=>999,'currency'=>'INR','max_website_gb'=>50,'storage_limit_gb'=>500,'retention_days'=>30,'backup_frequency'=>'daily','configured'=>false],
 ]];}

function wjba43_public_plans($visitor_ip = '') {

    $args = [
        'timeout' => 20,
        'headers' => [
            'Accept' => 'application/json'
        ]
    ];

    // Send IP only when explicitly provided after consent.
    $visitor_ip = trim((string) $visitor_ip);

    if ($visitor_ip !== '' && filter_var($visitor_ip, FILTER_VALIDATE_IP)) {
        $url = add_query_arg(
            'visitor_ip',
            $visitor_ip,
            WJBA43_PLANS_URL
        );
    } else {
        $url = WJBA43_PLANS_URL;
    }

    $r = wp_remote_get($url, $args);

    if (is_wp_error($r)) {
        return [
            'plans' => [],
            'country_code' => '',
            'blocked' => false,
            'setup_message' => 'Unable to load subscription plans. Please try again.'
        ];
    }

    $code = wp_remote_retrieve_response_code($r);
    $d = json_decode(wp_remote_retrieve_body($r), true);

    if (
        $code !== 200 ||
        !is_array($d) ||
        empty($d['success']) ||
        !isset($d['plans']) ||
        !is_array($d['plans'])
    ) {
        return [
            'plans' => [],
            'country_code' => '',
            'blocked' => !empty($d['blocked']),
            'setup_message' => (
                !empty($d['message'])
                    ? sanitize_text_field($d['message'])
                    : 'Unable to load subscription plans. Please try again.'
            )
        ];
    }

    $d['country_code'] = strtoupper(
        trim((string) ($d['country_code'] ?? ''))
    );

    $d['blocked'] = !empty($d['blocked']);

    return $d;
}

function wjba43_create_subscription_remote($plan_id){
 $s=wjba43_get_settings();
 // Never start a second subscription when this plugin already knows an active
 // Razorpay subscription. Reconnect the existing subscription instead.
 if(strtolower((string)($s['subscription_status']??''))==='active' && !empty($s['razorpay_subscription_id']) && empty($s['api_token'])){
   $reconnect=wjba43_reconnect_remote();
   if(!is_wp_error($reconnect)) return ['reconnected'=>true,'reconnect'=>$reconnect];
   return new WP_Error('subscription_active','An active subscription already exists. Reconnect failed: '.$reconnect->get_error_message());
 }
 // Do not require a local website_code here. A fresh installation can be provisioned
 // by create-subscription.php using its domain/email.
 $email=sanitize_email(get_option('admin_email'));$domain=parse_url(home_url('/'),PHP_URL_HOST);$url=home_url('/');
 if(!$email||!$domain)return new WP_Error('site','Could not determine website email/domain.');

$country_code = strtoupper(
    (string) get_transient(
        'wjba43_plan_country_' . get_current_user_id()
    )
);

if (!preg_match('/^[A-Z]{2}$/', $country_code)) {
    return new WP_Error(
        'country_detection_failed',
        'Your country selection has expired. Please check your website size and load the plans again.'
    );
}

if (in_array($country_code, ['PK', 'CN'], true)) {
    return new WP_Error(
        'country_not_supported',
        'Subscriptions are not available in your country.'
    );
}
 try{$claim_secret=bin2hex(random_bytes(32));}catch(Throwable $e){return new WP_Error('security','Could not generate secure provisioning secret.');}
 $r=wp_remote_post(WJBA43_CREATE_SUB_URL,['timeout'=>60,'headers'=>['Content-Type'=>'application/json','Accept'=>'application/json'],'body'=>wp_json_encode(['website_code'=>sanitize_text_field($s['website_code']??''),'email'=>$email,'name'=>wp_get_current_user()->display_name?:$domain,'phone'=>'','domain'=>$domain,'website_domain'=>$domain,'wordpress_url'=>$url,'plan_id'=>(int)$plan_id,'claim_secret'=>$claim_secret,'country_code'=>$country_code])]);
 if(is_wp_error($r))return $r;
 $d=json_decode(wp_remote_retrieve_body($r),true);
 if(wp_remote_retrieve_response_code($r)!==200||empty($d['success'])){
  /*
   * If the backup server says this website already has an active
   * subscription, do not surface that as a purchase error.  This means
   * the site is in the reconnectable state, so use the existing
   * subscription/reconnect endpoint instead of creating another one.
   */
  $remote_message=(string)($d['error']??$d['message']??'');
  if($remote_message!=='' && stripos($remote_message,'active subscription already exists')!==false){
   $reconnect=wjba43_reconnect_remote();
   if(!is_wp_error($reconnect)){
    return ['reconnected'=>true,'reconnect'=>$reconnect];
   }
   return new WP_Error('reconnect',$reconnect->get_error_message());
  }
  return new WP_Error('subscription',$remote_message?:'Could not create subscription.');
 }
 $rs=$d['subscription']??[];
 if(empty($rs['id']))return new WP_Error('subscription','Razorpay subscription ID was not returned.');
 // Save a newly provisioned website code immediately when the provisioning
 // endpoint returns one, so the later claim/activation step has the code.
 $returned_website_code=sanitize_text_field($d['website_code']??($d['website']['website_code']??''));
 if($returned_website_code!=='') $s['website_code']=$returned_website_code;
 $s['pending_claim_secret']=$claim_secret;
 $s['pending_local_subscription_id']=(int)($d['local_subscription_id']??0);
 $s['pending_razorpay_subscription_id']=sanitize_text_field($rs['id']);
 $s['razorpay_subscription_id']=sanitize_text_field($rs['id']);
 update_option(WJBA43_OPTION_KEY,$s,false);
 return $d;
}
function wjba43_claim_subscription_remote(){
 $s=wjba43_get_settings();
 if(empty($s['website_code'])||empty($s['pending_razorpay_subscription_id'])||empty($s['pending_claim_secret']))return new WP_Error('claim','No pending subscription claim is available.');
 $r=wp_remote_post(WJBA43_CLAIM_TOKEN_URL,['timeout'=>30,'headers'=>['Content-Type'=>'application/json','Accept'=>'application/json'],'body'=>wp_json_encode(['website_code'=>$s['website_code'],'subscription_id'=>$s['pending_razorpay_subscription_id'],'claim_secret'=>$s['pending_claim_secret']])]);
 if(is_wp_error($r))return $r;
 $code=wp_remote_retrieve_response_code($r);$d=json_decode(wp_remote_retrieve_body($r),true);
 if($code!==200||empty($d['success']))return new WP_Error('claim',$d['error']??$d['message']??'Subscription activation is still pending.',$code);
 return $d;
}
function wjba43_auto_recover_connection(){
    /*
     * Automatic reconnection is intentionally disabled.
     *
     * A manually disconnected website must remain disconnected until
     * the administrator explicitly clicks Reconnect Subscription.
     */
    return wjba43_get_settings();
}

function wjba43_sync_subscription_remote(){
 $s=wjba43_get_settings();

 if(empty($s['website_code'])) return $s;

 /*
  * A deliberate manual disconnect revokes the API token.
  * Do not call authenticated subscription/status endpoints while
  * disconnected. Keep the locally stored plan/subscription state.
  */
 if((int)($s['manual_disconnect']??0)===1){
   return $s;
 }

 if(strtolower((string)($s['connection_status']??''))==='disconnected' && empty($s['api_token'])){
   return $s;
 }

 $r=wp_remote_post(
   WJBA43_SUB_STATUS_URL,
   [
     'timeout'=>30,
     'headers'=>[
       'Content-Type'=>'application/json',
       'Accept'=>'application/json'
     ],
     'body'=>wp_json_encode([
       'website_code'=>$s['website_code'],
       'domain'=>parse_url(home_url('/'),PHP_URL_HOST)
     ])
   ]
 );

 if(is_wp_error($r)) return $s;

 $d=json_decode(wp_remote_retrieve_body($r),true);

 if(wp_remote_retrieve_response_code($r)!==200||empty($d['success'])) return $s;

 if(!empty($d['subscription'])&&is_array($d['subscription'])){
   $sub=$d['subscription'];

   /* A newly-created Razorpay subscription is only PENDING until the first
    * payment is actually completed and claim_subscription provisions the
    * backup token. Do not let subscription-status.php prematurely turn a
    * pending checkout into a visible active plan. */
   $is_pending_checkout=!empty($s['pending_claim_secret']) || !empty($s['pending_razorpay_subscription_id']);
   $remote_status=strtolower(sanitize_text_field($sub['status']??''));

   if(!$is_pending_checkout){
     if($remote_status!=='') $s['subscription_status']=$remote_status;

     $remote_id=sanitize_text_field(
       $sub['razorpay_subscription_id']??
       ($sub['subscription_id']??($sub['id']??''))
     );
     if($remote_id!=='') $s['razorpay_subscription_id']=$remote_id;

     if(isset($sub['plan'])&&is_array($sub['plan'])){
       $plan_name=sanitize_text_field($sub['plan']['name']??'');
       if($plan_name!=='') $s['plan_name']=$plan_name;
       if(isset($sub['plan']['max_website_gb'])) $s['max_website_gb']=(float)$sub['plan']['max_website_gb'];
       if(isset($sub['plan']['storage_limit_gb'])) $s['storage_limit_gb']=(float)$sub['plan']['storage_limit_gb'];
       if(isset($sub['plan']['retention_days'])) $s['retention_days']=(int)$sub['plan']['retention_days'];
     }

     if(($s['subscription_status']??'')!=='active' && empty($s['api_token'])){
       $s['connection_status']='disconnected';
     }
   }

   update_option(WJBA43_OPTION_KEY,$s,false);
 }

 return $s;
}
function wjba43_repair_connection(){
    $s=wjba43_get_settings();
    if((int)($s['manual_disconnect']??0)===1){
        return $s;
    }

    /*
     * Automatic repair/reconnection is disabled.
     *
     * A disconnected website must stay disconnected until the
     * administrator explicitly clicks "Reconnect Subscription".
     */
    return wjba43_get_settings();
}

function wjba43_reconnect_remote(){
 $s=wjba43_get_settings();
 // Reconnect must also work when the local plugin lost its website_code.
 // The reconnect endpoint receives the current domain/email and can resolve
 // the existing website/subscription on the backup server.
 $email=sanitize_email(get_option('admin_email'));
 $domain=parse_url(home_url('/'),PHP_URL_HOST);

 if(!$email||!$domain) return new WP_Error('reconnect','Could not determine website email/domain.');

 $r=wp_remote_post(
   WJBA43_RECONNECT_URL,
   [
     'timeout'=>60,
     'headers'=>[
       'Content-Type'=>'application/json',
       'Accept'=>'application/json'
     ],
     'body'=>wp_json_encode([
       'website_code'=>$s['website_code'],
       'domain'=>$domain,
       'website_domain'=>$domain,
       'email'=>$email
     ])
   ]
 );

 if(is_wp_error($r)) return $r;

 $d=json_decode(wp_remote_retrieve_body($r),true);
 $code=wp_remote_retrieve_response_code($r);

 if($code!==200||empty($d['success'])){
   return new WP_Error(
     'reconnect',
     $d['message']??$d['error']??'Could not reconnect this subscription.'
   );
 }

 return $d;
}


add_action('wp_ajax_wjba43_reconnect','wjba43_ajax_reconnect');
function wjba43_ajax_reconnect(){
    if(!current_user_can('manage_options')||!check_ajax_referer('wjba43_reconnect','_ajax_nonce'))wp_send_json_error(['message'=>'Unauthorized.'],403);
    $s=wjba43_get_settings();
    $r=wjba43_reconnect_remote();
    if(is_wp_error($r))wp_send_json_error(['message'=>$r->get_error_message()],502);
    $token=sanitize_text_field($r['api_token']??$r['token']??'');
    if($token==='')wp_send_json_error(['message'=>'Reconnect succeeded, but no API token was returned by the backup server.'],502);
    $s['api_token']=$token;
    $s['website_code']=sanitize_text_field($r['website_code']??$s['website_code']);
    $s['subscription_status']=sanitize_text_field($r['subscription_status']??$r['status']??($s['subscription_status']?:'active'));
    $s['razorpay_subscription_id']=sanitize_text_field($r['razorpay_subscription_id']??($r['subscription']['razorpay_subscription_id']??$s['razorpay_subscription_id']));
    $plan=$r['plan']??[];
    if(is_array($plan)){
        $s['plan_name']=sanitize_text_field($plan['name']??$s['plan_name']);
        $s['max_website_gb']=(float)($plan['max_website_gb']??$s['max_website_gb']);
        $s['storage_limit_gb']=(float)($plan['storage_limit_gb']??$s['storage_limit_gb']);
        $s['retention_days']=(int)($plan['retention_days']??$s['retention_days']);
    }
    $s['connection_status']='connected';$s['manual_disconnect']=0;
    update_option(WJBA43_OPTION_KEY,$s,false);
    if(!wp_next_scheduled(WJBA43_CRON_HOOK))wp_schedule_event(time()+300,'daily',WJBA43_CRON_HOOK);
    wp_send_json_success(['website_code'=>$s['website_code'],'subscription_status'=>$s['subscription_status']]);
}


function wjba43_test_connection(){ $s=wjba43_get_settings(); if(!$s['website_code']||!$s['api_token'])return new WP_Error('missing','Save Website Code and API Token first.'); $r=wp_remote_post(WJBA43_AUTH_URL,['timeout'=>20,'headers'=>['Content-Type'=>'application/json'],'body'=>wp_json_encode(['website_code'=>$s['website_code'],'api_token'=>$s['api_token']])]); if(is_wp_error($r))return $r; $d=json_decode(wp_remote_retrieve_body($r),true); if(wp_remote_retrieve_response_code($r)!==200||empty($d['success']))return new WP_Error('auth',$d['message']??'Authentication failed.'); return $d;}

/* Database backup: stream a compressed SQL file to the existing API in memory-bounded chunks. */
function wjba43_sql_escape($v){if($v===null)return 'NULL';return "'".str_replace(["\\","\0","\n","\r","'",'"',"\x1a"],["\\\\","\\0","\\n","\\r","\\'",'\\"',"\\Z"],(string)$v)."'";}
function wjba43_create_database_dump(){
 global $wpdb; if(!function_exists('gzopen'))return new WP_Error('gzip','PHP gzip support is unavailable.');
 $tmp=wp_tempnam('wjba-db');if(!$tmp)return new WP_Error('temp','Could not create temp file.');$path=$tmp.'.sql.gz';@unlink($tmp);$gz=@gzopen($path,'wb6');if(!$gz)return new WP_Error('gzip','Could not open gzip file.');
 gzwrite($gz,"-- Webjooll Database Backup\n-- Site: ".home_url('/')."\n-- Generated: ".gmdate('c')."\nSET FOREIGN_KEY_CHECKS=0;\n\n");
 $tables=$wpdb->get_col('SHOW TABLES');if(!is_array($tables)){gzclose($gz);@unlink($path);return new WP_Error('tables','Could not read tables.');}
 foreach($tables as $t){$safe=str_replace('`','``',$t);$c=$wpdb->get_row("SHOW CREATE TABLE `{$safe}`",ARRAY_N);if(!$c)continue;gzwrite($gz,"DROP TABLE IF EXISTS `{$safe}`;\n".$c[1].";\n\n");$offset=0;$chunk=500;
  while(1){$rows=$wpdb->get_results("SELECT * FROM `{$safe}` LIMIT {$offset},{$chunk}",ARRAY_A);if(!$rows)break;foreach($rows as $row){$cols=[];$vals=[];foreach($row as $col=>$val){$cols[]='`'.str_replace('`','``',$col).'`';$vals[]=wjba43_sql_escape($val);}gzwrite($gz,"INSERT INTO `{$safe}` (".implode(',',$cols).") VALUES (".implode(',',$vals).");\n");}$offset+=count($rows);if(count($rows)<$chunk)break;unset($rows);}
  gzwrite($gz,"\n");
 }
 gzwrite($gz,"SET FOREIGN_KEY_CHECKS=1;\n");gzclose($gz);return $path;
}


function wjba43_upload_db( $path ) {
    if ( ! is_file( $path ) || ! is_readable( $path ) ) {
        return new WP_Error(
            'db_read',
            'Database backup file is missing or unreadable.'
        );
    }

    if ( ! function_exists( 'curl_init' ) ) {
        return new WP_Error(
            'curl_required',
            'Memory-safe database upload requires the PHP cURL extension.'
        );
    }

    $size = filesize( $path );

    if ( false === $size || $size <= 0 ) {
        return new WP_Error(
            'db_size',
            'Database backup file is empty or its size cannot be determined.'
        );
    }

    $fp = fopen( $path, 'rb' );

    if ( false === $fp ) {
        return new WP_Error(
            'db_read',
            'Cannot open database backup.'
        );
    }

    $headers = array_merge(
        wjba43_headers(),
        array(
            'Content-Type'   => 'application/gzip',
            'Content-Length' => (string) $size,
        )
    );

    $streaming_configured = false;

    /*
     * Configure the WordPress HTTP API's cURL transport
     * to stream the gzip file directly from disk.
     */
    $stream_callback = function ( $handle, $args, $url ) use (
        $fp,
        $size,
        &$streaming_configured
    ) {
        if ( WJBA43_DB_URL !== $url ) {
            return;
        }

        $streaming_configured = true;

        curl_setopt_array(
            $handle,
            array(
                CURLOPT_UPLOAD         => true,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => null,
                CURLOPT_INFILESIZE     => $size,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_TIMEOUT        => 3600,

                CURLOPT_READFUNCTION => function (
                    $handle,
                    $stream,
                    $length
                ) use ( $fp ) {
                    $data = fread( $fp, $length );

                    return false === $data ? '' : $data;
                },
            )
        );
    };

    add_action( 'http_api_curl', $stream_callback, 10, 3 );

    try {
        $response = wp_remote_post(
            WJBA43_DB_URL,
            array(
                'timeout'     => 3600,
                'redirection' => 0,
                'httpversion' => '1.1',
                'headers'     => $headers,
                'body'        => '',
                'blocking'    => true,
            )
        );
    } finally {
        remove_action( 'http_api_curl', $stream_callback, 10 );
        fclose( $fp );
    }

    if ( ! $streaming_configured ) {
        return new WP_Error(
            'db_transport',
            'The required streaming HTTP transport was not available.'
        );
    }

    if ( is_wp_error( $response ) ) {
        error_log(
            'WJ Website Backup: Database upload failed: ' .
            $response->get_error_message()
        );

        return new WP_Error(
            'db_upload',
            'Database upload failed. Please check the server logs and try again.'
        );
    }

    $http_code = (int) wp_remote_retrieve_response_code( $response );
    $body      = wp_remote_retrieve_body( $response );
    $data      = json_decode( $body, true );

    if (
        200 !== $http_code ||
        ! is_array( $data ) ||
        empty( $data['success'] )
    ) {
        return new WP_Error(
            'db',
            is_array( $data ) && ! empty( $data['message'] )
                ? sanitize_text_field( $data['message'] )
                : 'Database upload failed.'
        );
    }

    if ( is_file( $path ) ) {
        @unlink( $path );
    }

    return $data;
}


function wjba43_create_database_backup(){$p=wjba43_create_database_dump();if(is_wp_error($p))return $p;$d=wjba43_upload_db($p);if(is_wp_error($d))return $d;return ['backup_id'=>(int)$d['backup_id'],'size_bytes'=>(int)$d['size_bytes']];}

/* Disk-based ZIP creation. Contents are never loaded into RAM. */
function wjba43_zip_add($zip,$root,$path,&$count){
 if(is_link($path))return;$rel=ltrim(str_replace($root,'',$path),'/');if($rel===''||strpos($rel,'wp-content/cache')===0)return;
 if(is_file($path)){if(!$zip->addFile($path,$rel))throw new Exception('Could not add '.$rel);$count++;return;}
 if(is_dir($path)){$items=@scandir($path);if(!$items)return;foreach($items as $x){if($x==='.'||$x==='..')continue;wjba43_zip_add($zip,$root,$path.'/'.$x,$count);}}
}
function wjba43_create_files_archive(){
 if(!class_exists('ZipArchive'))return new WP_Error('zip','PHP ZipArchive extension is required.');
 $root=untrailingslashit(ABSPATH);$tmp=wp_tempnam('wjba-files');if(!$tmp)return new WP_Error('temp','Could not create temp archive.');@unlink($tmp);$path=$tmp.'.zip';$z=new ZipArchive();
 if($z->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)return new WP_Error('zip','Could not create ZIP.');
 $count=0;try{$items=scandir($root);foreach($items as $x){if($x==='.'||$x==='..'||$x==='wp-content/cache')continue;wjba43_zip_add($z,$root,$root.'/'.$x,$count);}$z->close();}catch(Throwable $e){$z->close();@unlink($path);return new WP_Error('zip',$e->getMessage());}
 if(!$count){@unlink($path);return new WP_Error('zip','No files found.');}return $path;
}

/* Multipart upload: each 8MB chunk is read from disk and sent separately. */
function wjba43_mp_request($action,$extra=[],$body=null){
 $s=wjba43_get_settings();
 $headers=wjba43_headers();
 $headers['X-WJ-Action']=$action;
 foreach($extra as $k=>$v)$headers['X-WJ-'.$k]=(string)$v;
 $args=['timeout'=>3600,'headers'=>$headers];
 if($body!==null){
     $args['body']=$body;
     $args['headers']['Content-Type']='application/octet-stream';
 }
 $r=wp_remote_post(WJBA43_MP_URL,$args);if(is_wp_error($r))return $r;$d=json_decode(wp_remote_retrieve_body($r),true);if(wp_remote_retrieve_response_code($r)!==200||empty($d['success']))return new WP_Error('multipart',$d['message']??'Multipart request failed.');return $d;
}
function wjba43_upload_files_multipart($path){
 $size=filesize($path);$name='website-'.gmdate('Ymd-His').'.zip';
 $start=wjba43_mp_request('start',['Filename'=>$name,'Size'=>$size]);if(is_wp_error($start))return $start;
 $upload_id=$start['upload_id'];$r2_key=$start['r2_key'];$part=0;$parts=[];$fp=fopen($path,'rb');if(!$fp){@unlink($path);return new WP_Error('read','Cannot open archive.');}
 while(!feof($fp)){ $buf=fread($fp,WJBA43_CHUNK_SIZE);if($buf===false||$buf==='')break;$part++;
   $r=wjba43_mp_request('part',['Upload-Id'=>$upload_id,'Part-Number'=>$part,'Filename'=>$name,'R2-Key'=>$r2_key],$buf);
   unset($buf);if(is_wp_error($r)){fclose($fp);@unlink($path);return $r;} $parts[]=['part_number'=>$part,'etag'=>$r['etag']];
 }
 fclose($fp);@unlink($path);
 if(!$parts)return new WP_Error('empty','Archive is empty.');
 $r=wjba43_mp_request('complete',['Upload-Id'=>$upload_id,'Filename'=>$name,'R2-Key'=>$r2_key,'Parts'=>base64_encode(wp_json_encode($parts))]);
 if(is_wp_error($r))return $r;return $r;
}
function wjba43_create_files_backup(){$p=wjba43_create_files_archive();if(is_wp_error($p))return $p;return wjba43_upload_files_multipart($p);}

function wjba43_run_backup(){@set_time_limit(0);$db=wjba43_create_database_backup();if(is_wp_error($db))return $db;$files=wjba43_create_files_backup();if(is_wp_error($files))return $files;return ['database'=>$db,'files'=>$files];}
function wjba43_daily_backup() {
    $s = wjba43_get_settings();

    // Do not run backups without valid connection details.
    if (
        empty($s['website_code']) ||
        empty($s['api_token']) ||
        strtolower((string) ($s['subscription_status'] ?? '')) !== 'active' ||
        strtolower((string) ($s['connection_status'] ?? '')) !== 'connected' ||
        (int) ($s['manual_disconnect'] ?? 0) === 1
    ) {
        return;
    }

    // Prevent overlapping scheduled backups.
    if (get_transient('wjba43_daily_lock')) {
        return;
    }

    set_transient('wjba43_daily_lock', 1, 6 * HOUR_IN_SECONDS);

    try {
        $result = wjba43_run_backup();

        if (is_wp_error($result)) {
            error_log(
                'WJ Website Backup: Scheduled backup failed: ' .
                $result->get_error_message()
            );
        }
    } catch (Throwable $e) {
        error_log(
            'WJ Website Backup: Scheduled backup error: ' .
            $e->getMessage()
        );
    } finally {
        delete_transient('wjba43_daily_lock');
    }
}
function wjba43_api_post($url,$extra_headers=[],$body=null){
 $headers=array_merge(wjba43_headers(),$extra_headers);$args=['timeout'=>60,'headers'=>$headers];
 if($body!==null){$args['body']=$body;$args['headers']['Content-Type']='application/json';}
 $r=wp_remote_post($url,$args);if(is_wp_error($r))return $r;$code=wp_remote_retrieve_response_code($r);$d=json_decode(wp_remote_retrieve_body($r),true);
 if($code!==200||empty($d['success']))return new WP_Error('api',$d['message']??'API request failed.');return $d;
}
function wjba43_get_backups(){return wjba43_api_post(WJBA43_STATUS_URL);}
function wjba43_disconnect(){return wjba43_api_post(WJBA43_DISCONNECT_URL);}



function wjba43_download_backup($id){return wjba43_api_post(WJBA43_DOWNLOAD_URL,['X-WJ-Backup-ID'=>(string)(int)$id]);}
function wjba43_backup_rows($items,$type,$allow_download=true){
 $out='';
 if(empty($items)) return '<p class="description">No backups available.</p>';
 foreach($items as $b){
   if(($b['backup_type']??'')!==$type) continue;
   $status=sanitize_text_field($b['status']??'unknown');$date=!empty($b['created_at'])?wp_date('d M Y, h:i A',strtotime($b['created_at'])):'—';
   $size=isset($b['size_bytes'])&&$b['size_bytes']!==null?size_format((int)$b['size_bytes']):'—';
   $action='—';
   if($status==='completed'){$action=$allow_download?'<button type="button" class="button wjba-download" data-id="'.(int)$b['id'].'">Download</button>':'<span class="description">Reconnect to download</span>' ;}
   $out.='<tr><td>'.esc_html($date).'</td><td>'.esc_html($size).'</td><td>'.esc_html(ucfirst($status)).'</td><td>'.$action.'</td></tr>';
 }
 return $out?:'<p class="description">No backups available.</p>';
}
function wjba43_admin_menu(){ add_menu_page('WJ Website Backup','WJ Website Backup','manage_options','wj-website-backup','wjba43_page','dashicons-backup',56); }
add_action('admin_menu','wjba43_admin_menu');

function wjba43_admin_enqueue_scripts($hook_suffix) {
    if (
        $hook_suffix !== 'toplevel_page_wj-website-backup'
        || !current_user_can('manage_options')
    ) {
        return;
    }

    wp_enqueue_script(
        'wjba43-admin',
        plugins_url('assets/admin.js', __FILE__),
        [],
        WJBA43_VERSION,
        true
    );

    wp_enqueue_script(
        'wjba43-admin-download',
        plugins_url('assets/admin-download.js', __FILE__),
        [],
        WJBA43_VERSION,
        true
    );

    $user = wp_get_current_user();
    $settings = wjba43_get_settings();

    wp_localize_script('wjba43-admin', 'WJBA43_DATA', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'email' => sanitize_email(get_option('admin_email')),
        'name' => $user
            ? sanitize_text_field($user->display_name)
            : '',
        'nonces' => [
            'siteSize' => wp_create_nonce('wjba43_site_size'),
            'reconnect' => wp_create_nonce('wjba43_reconnect'),
            'subscription' => wp_create_nonce('wjba43_subscription'),
            'download' => wp_create_nonce('wjba43_download'),
            'loadPlans' => wp_create_nonce('wjba43_load_plans'),
        ],
        'currentPlan' => $settings['plan_name'],
        'subscriptionActive' => (
            strtolower((string) $settings['subscription_status']) === 'active'
        ),
    ]);
}
add_action('admin_enqueue_scripts', 'wjba43_admin_enqueue_scripts');


add_action('wp_ajax_wjba43_load_plans', 'wjba43_ajax_load_plans');

function wjba43_ajax_load_plans() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized.'], 403);
    }

    if (!check_ajax_referer('wjba43_load_plans', '_ajax_nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed. Please refresh the page.'], 403);
    }


if (
    !isset($_POST['ip_consent']) ||
    sanitize_text_field(wp_unslash($_POST['ip_consent'])) !== 'yes'
) {
    wp_send_json_error([
        'message' => 'Please provide consent before loading subscription plans.'
    ], 403);
}


    
$visitor_ip = '';

if (
    isset($_POST['ip_consent']) &&
    sanitize_text_field(wp_unslash($_POST['ip_consent'])) === 'yes'
) {
    $candidate_ip = trim(
        (string) wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')
    );

    if (filter_var($candidate_ip, FILTER_VALIDATE_IP)) {
        $visitor_ip = $candidate_ip;
    }
}

$result = wjba43_public_plans($visitor_ip);


$country_code = strtoupper(
    trim((string) ($result['country_code'] ?? ''))
);

if (
    !empty($result['plans']) &&
    empty($result['blocked']) &&
    preg_match('/^[A-Z]{2}$/', $country_code)
) {
    set_transient(
        'wjba43_plan_country_' . get_current_user_id(),
        $country_code,
        30 * MINUTE_IN_SECONDS
    );
}

    if (!is_array($result)) {
        wp_send_json_error(['message' => 'Invalid response from plans server.'], 502);
    }

    $plans = [];

    foreach (($result['plans'] ?? []) as $plan) {
        if (!is_array($plan)) {
            continue;
        }

        $plans[] = [
            'id' => absint($plan['id'] ?? 0),
            'name' => sanitize_text_field($plan['name'] ?? ''),
            'price' => (float) ($plan['price'] ?? 0),
            'currency' => strtoupper(sanitize_text_field($plan['currency'] ?? '')),
            'max_website_gb' => (float) ($plan['max_website_gb'] ?? $plan['max_website_size_gb'] ?? 0),
            'storage_limit_gb' => (float) ($plan['storage_limit_gb'] ?? 0),
            'retention_days' => absint($plan['retention_days'] ?? 0),
            'backup_frequency' => sanitize_text_field($plan['backup_frequency'] ?? 'daily'),
            'configured' => !empty($plan['configured']),
        ];
    }

    wp_send_json_success([
        'plans' => $plans,
        'country_code' => strtoupper(sanitize_text_field($result['country_code'] ?? '')),
        'blocked' => !empty($result['blocked']),
        'message' => sanitize_text_field($result['setup_message'] ?? ''),
    ]);
}

add_action('wp_ajax_wjba43_create_subscription','wjba43_ajax_create_subscription');
function wjba43_ajax_create_subscription(){
 if(!current_user_can('manage_options')||!check_ajax_referer('wjba43_subscription','_ajax_nonce'))wp_send_json_error(['message'=>'Unauthorized.'],403);
 $plan=isset($_POST['plan_id'])?(int)$_POST['plan_id']:0;
 $r=wjba43_create_subscription_remote($plan);
 if(is_wp_error($r))wp_send_json_error(['message'=>$r->get_error_message()]);
 if(is_array($r)&&!empty($r['reconnected'])){
  $s=wjba43_get_settings();
  $re=$r['reconnect']??[];
  if(is_array($re)){
   $token=sanitize_text_field($re['api_token']??$re['token']??'');
   if($token!=='')$s['api_token']=$token;
   if(!empty($re['website_code']))$s['website_code']=sanitize_text_field($re['website_code']);
   if(!empty($re['subscription_status']))$s['subscription_status']=sanitize_text_field($re['subscription_status']);
   if(!empty($re['razorpay_subscription_id']))$s['razorpay_subscription_id']=sanitize_text_field($re['razorpay_subscription_id']);
   if(isset($re['plan'])&&is_array($re['plan'])){
    if(!empty($re['plan']['name']))$s['plan_name']=sanitize_text_field($re['plan']['name']);
    if(isset($re['plan']['max_website_gb']))$s['max_website_gb']=(float)$re['plan']['max_website_gb'];
    if(isset($re['plan']['storage_limit_gb']))$s['storage_limit_gb']=(float)$re['plan']['storage_limit_gb'];
    if(isset($re['plan']['retention_days']))$s['retention_days']=(int)$re['plan']['retention_days'];
   }
  }
  $s['connection_status']='connected';
  $s['manual_disconnect']=0;
  update_option(WJBA43_OPTION_KEY,$s,false);
  wp_send_json_success(['reconnected'=>true,'website_code'=>$s['website_code'],'subscription_status'=>$s['subscription_status']]);
 }
 wp_send_json_success($r);
}

add_action('wp_ajax_wjba43_create_checkout_order','wjba43_ajax_create_checkout_order');
function wjba43_ajax_create_checkout_order(){
 if(!current_user_can('manage_options')||!check_ajax_referer('wjba43_subscription','_ajax_nonce'))wp_send_json_error(['message'=>'Unauthorized.'],403);
 $plan=isset($_POST['plan_id'])?(int)$_POST['plan_id']:0;
 if($plan<=0)wp_send_json_error(['message'=>'Invalid plan selected.'],400);
 $email=sanitize_email(get_option('admin_email'));
 $user=wp_get_current_user();
 $name=$user&&$user->exists()?sanitize_text_field($user->display_name):'';
 $domain=parse_url(home_url('/'),PHP_URL_HOST);
 $url=home_url('/');
 if(!$email||!filter_var($email,FILTER_VALIDATE_EMAIL))wp_send_json_error(['message'=>'A valid WordPress admin email is required.'],400);
 if(!$domain)wp_send_json_error(['message'=>'Could not determine website domain.'],400);
 $r=wp_remote_post(WJBA43_CHECKOUT_ORDER_URL,[
  'timeout'=>60,
  'headers'=>['Content-Type'=>'application/json','Accept'=>'application/json'],
  'body'=>wp_json_encode([
   'email'=>$email,
   'name'=>$name?:$domain,
   'domain'=>$domain,
   'website_domain'=>$domain,
   'wordpress_url'=>$url,
   'phone'=>'',
   'plan_id'=>$plan
  ])
 ]);
 if(is_wp_error($r))wp_send_json_error(['message'=>$r->get_error_message()],502);
 $code=wp_remote_retrieve_response_code($r);
 $body=json_decode(wp_remote_retrieve_body($r),true);
 if(!is_array($body))wp_send_json_error(['message'=>'Invalid response from backup server.'],502);
 if($code<200||$code>=300||empty($body['success']))wp_send_json_error(['message'=>$body['error']??$body['message']??'Could not create Razorpay payment order.'],$code?:502);
 wp_send_json_success($body);
}

add_action('wp_ajax_wjba43_verify_checkout_payment','wjba43_ajax_verify_checkout_payment');
function wjba43_ajax_verify_checkout_payment(){
 if(!current_user_can('manage_options')||!check_ajax_referer('wjba43_subscription','_ajax_nonce'))wp_send_json_error(['message'=>'Unauthorized.'],403);
 $order_id=sanitize_text_field(wp_unslash($_POST['razorpay_order_id']??''));
 $payment_id=sanitize_text_field(wp_unslash($_POST['razorpay_payment_id']??''));
 $signature=sanitize_text_field(wp_unslash($_POST['razorpay_signature']??''));
 if(!$order_id||!$payment_id||!$signature)wp_send_json_error(['message'=>'Payment verification data is incomplete.'],400);
 $r=wp_remote_post(WJBA43_VERIFY_CHECKOUT_URL,[
  'timeout'=>90,
  'headers'=>['Content-Type'=>'application/json','Accept'=>'application/json'],
  'body'=>wp_json_encode([
   'razorpay_order_id'=>$order_id,
   'razorpay_payment_id'=>$payment_id,
   'razorpay_signature'=>$signature
  ])
 ]);
 if(is_wp_error($r))wp_send_json_error(['message'=>$r->get_error_message()],502);
 $code=wp_remote_retrieve_response_code($r);
 $body=json_decode(wp_remote_retrieve_body($r),true);
 if(!is_array($body))wp_send_json_error(['message'=>'Invalid response from payment verification server.'],502);
 if($code<200||$code>=300||empty($body['success']))wp_send_json_error(['message'=>$body['error']??$body['message']??'Payment verification failed.'],$code?:502);
 $s=wjba43_get_settings();
 $s['website_code']=sanitize_text_field($body['website_code']??$s['website_code']);
 $s['api_token']=sanitize_text_field($body['api_token']??$s['api_token']);
 $s['plan_name']=sanitize_text_field($body['plan']['name']??$s['plan_name']);
 $s['max_website_gb']=(float)($body['plan']['max_website_gb']??$s['max_website_gb']);
 $s['storage_limit_gb']=(float)($body['plan']['storage_limit_gb']??$s['storage_limit_gb']);
 $s['retention_days']=(int)($body['plan']['retention_days']??$s['retention_days']);
 $s['subscription_status']=sanitize_text_field($body['subscription_status']??'active');
 /* Preserve the remote Razorpay subscription ID when the verification API returns it.
  * Older API responses may omit it; upgrade handler will recover it from subscription-status.php. */
 $s['razorpay_subscription_id']=sanitize_text_field(
  $body['razorpay_subscription_id']??
  ($body['subscription']['razorpay_subscription_id']??
  ($body['subscription']['id']??$s['razorpay_subscription_id']))
 );
 $s['pending_claim_secret']='';
 $s['pending_local_subscription_id']=0;
 $s['pending_razorpay_subscription_id']='';
 $s['connection_status']='connected';
 update_option(WJBA43_OPTION_KEY,$s,false);
 wp_send_json_success(['website_code'=>$s['website_code'],'subscription_status'=>$s['subscription_status'],'plan'=>['name'=>$s['plan_name'],'max_website_gb'=>$s['max_website_gb'],'storage_limit_gb'=>$s['storage_limit_gb'],'retention_days'=>$s['retention_days']]]);
}


add_action('wp_ajax_wjba43_subscription_status','wjba43_ajax_subscription_status');
function wjba43_ajax_subscription_status(){
 if(!current_user_can('manage_options')||!check_ajax_referer('wjba43_subscription','_ajax_nonce')){
  wp_send_json_error(['message'=>'Unauthorized.'],403);
 }

 $s=wjba43_get_settings();

 if(empty($s['website_code'])||empty($s['api_token'])){
  wp_send_json_error(['message'=>'Backup subscription is not connected.'],409);
 }

 $r=wp_remote_post(WJBA43_SUB_STATUS_URL,[
  'timeout'=>30,
  'headers'=>[
   'Content-Type'=>'application/json',
   'Accept'=>'application/json',
   'X-WJ-Website-Code'=>$s['website_code'],
   'X-WJ-API-Token'=>$s['api_token']
  ],
  'body'=>wp_json_encode([
   'website_code'=>$s['website_code'],
   'domain'=>parse_url(home_url('/'),PHP_URL_HOST),
   'website_domain'=>parse_url(home_url('/'),PHP_URL_HOST)
  ])
 ]);

 if(is_wp_error($r)){
  wp_send_json_error(['message'=>$r->get_error_message()],502);
 }

 $code=wp_remote_retrieve_response_code($r);
 $body=json_decode(wp_remote_retrieve_body($r),true);

 if(!is_array($body)){
  wp_send_json_error(['message'=>'Invalid response from backup server.'],502);
 }

 if($code<200||$code>=300||empty($body['success'])){
  wp_send_json_error([
   'message'=>$body['message']??'Subscription is not active yet.'
  ],$code?:502);
 }

 $status=strtolower((string)($body['subscription']['status']??$body['status']??''));

 if($status!=='active'){
  wp_send_json_error(['message'=>'Subscription is not active yet.'],409);
 }

 wp_send_json_success($body);
}

add_action('wp_ajax_wjba43_upgrade_subscription','wjba43_ajax_upgrade_subscription');
function wjba43_ajax_upgrade_subscription(){
 if(!current_user_can('manage_options')||!check_ajax_referer('wjba43_subscription','_ajax_nonce')){
  wp_send_json_error(['message'=>'Unauthorized.'],403);
 }

 $plan=isset($_POST['plan_id'])?(int)$_POST['plan_id']:0;

 if($plan<=0){
  wp_send_json_error(['message'=>'Invalid upgrade plan.'],400);
 }

 $s=wjba43_get_settings();

 if(empty($s['website_code'])||empty($s['api_token'])){
  wp_send_json_error(['message'=>'Your backup subscription is not connected.'],409);
 }

 /* The initial checkout flow may activate the subscription without returning
  * razorpay_subscription_id to WordPress. Recover it from the authoritative
  * subscription-status endpoint before attempting an upgrade. */
 if(empty($s['razorpay_subscription_id']) && !empty($s['pending_razorpay_subscription_id'])){
  /* The subscription ID created before Checkout is already authoritative. */
  $s['razorpay_subscription_id']=sanitize_text_field($s['pending_razorpay_subscription_id']);
  update_option(WJBA43_OPTION_KEY,$s,false);
 }

 if(empty($s['razorpay_subscription_id'])){
  $domain=parse_url(home_url('/'),PHP_URL_HOST);
  $status_r=wp_remote_post(WJBA43_SUB_STATUS_URL,[
   'timeout'=>30,
   'headers'=>[
    'Content-Type'=>'application/json',
    'Accept'=>'application/json',
    'X-WJ-Website-Code'=>$s['website_code'],
    'X-WJ-API-Token'=>$s['api_token']
   ],
   'body'=>wp_json_encode([
    'website_code'=>$s['website_code'],
    'domain'=>$domain,
    'website_domain'=>$domain,
    'wordpress_url'=>home_url('/')
   ])
  ]);
  if(!is_wp_error($status_r)){
   $status_body=json_decode(wp_remote_retrieve_body($status_r),true);
   $status_code=wp_remote_retrieve_response_code($status_r);
   $remote_sub=is_array($status_body)?($status_body['subscription']??[]):[];
   $remote_id='';
   if(is_array($remote_sub)){
    $remote_id=$remote_sub['razorpay_subscription_id']??$remote_sub['subscription_id']??$remote_sub['id']??'';
    if($remote_id==='' && isset($remote_sub['razorpay_subscription']) && is_array($remote_sub['razorpay_subscription'])){
      $remote_id=$remote_sub['razorpay_subscription']['id']??$remote_sub['razorpay_subscription']['subscription_id']??'';
    }
   }
   if($remote_id==='') $remote_id=$status_body['razorpay_subscription_id']??$status_body['subscription_id']??$status_body['razorpay_subscription']??'';
   if(is_array($remote_id)) $remote_id=$remote_id['id']??$remote_id['subscription_id']??'';
   if($status_code>=200&&$status_code<300&&is_array($status_body)&&!empty($remote_id)){
    $s['razorpay_subscription_id']=sanitize_text_field($remote_id);
    if(!empty($remote_sub['status'])) $s['subscription_status']=sanitize_text_field($remote_sub['status']);
    update_option(WJBA43_OPTION_KEY,$s,false);
   }
  }
 }

 if(empty($s['razorpay_subscription_id'])){
  /* Last recovery path: a connected site can ask reconnect.php for the
   * existing subscription metadata; this must never create a new payment. */
  $re=wjba43_reconnect_remote();
  if(!is_wp_error($re)){
   $rid=$re['razorpay_subscription_id']??($re['subscription']['razorpay_subscription_id']??($re['subscription']['id']??''));
   if($rid!==''){
    $s['razorpay_subscription_id']=sanitize_text_field($rid);
    update_option(WJBA43_OPTION_KEY,$s,false);
   }
  }
 }

 if(empty($s['razorpay_subscription_id'])){
  wp_send_json_error(['message'=>'Your subscription is connected, but its Razorpay subscription ID could not be retrieved yet. Please try again after the subscription status has synced.'],409);
 }

 $r=wp_remote_post('https://backup.webjooll.com/api/upgrade-subscription.php',[
  'timeout'=>60,
  'headers'=>[
   'Content-Type'=>'application/json',
   'Accept'=>'application/json',
   'X-WJ-Website-Code'=>$s['website_code'],
   'X-WJ-API-Token'=>$s['api_token']
  ],
  'body'=>wp_json_encode([
   'plan_id'=>$plan,
   'website_code'=>$s['website_code'],
   'domain'=>parse_url(home_url('/'),PHP_URL_HOST),
   'website_domain'=>parse_url(home_url('/'),PHP_URL_HOST)
  ])
 ]);

 if(is_wp_error($r)){
  wp_send_json_error(['message'=>$r->get_error_message()],502);
 }

 $code=wp_remote_retrieve_response_code($r);
 $body=json_decode(wp_remote_retrieve_body($r),true);

 if(!is_array($body)){
  wp_send_json_error(['message'=>'Invalid response from backup server.'],502);
 }

 if($code<200||$code>=300||empty($body['success'])){
  wp_send_json_error([
   'message'=>$body['message']??'Could not create upgrade subscription.'
  ],$code?:502);
 }

 wp_send_json_success($body);
}
add_action('wp_ajax_wjba43_verify_upgrade_payment','wjba43_ajax_verify_upgrade_payment');
function wjba43_ajax_verify_upgrade_payment(){
 if(!current_user_can('manage_options')||!check_ajax_referer('wjba43_subscription','_ajax_nonce')){
  wp_send_json_error(['message'=>'Unauthorized.'],403);
 }

 $subscription_id=sanitize_text_field(wp_unslash($_POST['razorpay_subscription_id']??''));
 $payment_id=sanitize_text_field(wp_unslash($_POST['razorpay_payment_id']??''));
 $signature=sanitize_text_field(wp_unslash($_POST['razorpay_signature']??''));

 if(!$subscription_id||!$payment_id||!$signature){
  wp_send_json_error(['message'=>'Subscription verification data is incomplete.'],400);
 }

 $s=wjba43_get_settings();
 if(empty($s['website_code'])||empty($s['api_token'])){
  wp_send_json_error(['message'=>'Backup subscription is not connected.'],409);
 }

 $r=wp_remote_post(WJBA43_VERIFY_UPGRADE_URL,[
  'timeout'=>90,
  'headers'=>[
   'Content-Type'=>'application/json',
   'Accept'=>'application/json',
   'X-WJ-Website-Code'=>$s['website_code'],
   'X-WJ-API-Token'=>$s['api_token']
  ],
  'body'=>wp_json_encode([
   'razorpay_subscription_id'=>$subscription_id,
   'razorpay_payment_id'=>$payment_id,
   'razorpay_signature'=>$signature
  ])
 ]);

 if(is_wp_error($r)){
  wp_send_json_error(['message'=>$r->get_error_message()],502);
 }

 $code=wp_remote_retrieve_response_code($r);
 $body=json_decode(wp_remote_retrieve_body($r),true);

 if(!is_array($body)){
  wp_send_json_error(['message'=>'Invalid response from backup server.'],502);
 }

 if($code<200||$code>=300||empty($body['success'])){
  wp_send_json_error([
   'message'=>$body['message']??$body['error']??'Upgrade verification failed.'
  ],$code?:502);
 }

 if(isset($body['plan'])&&is_array($body['plan'])){
  $s['plan_name']=sanitize_text_field($body['plan']['name']??$s['plan_name']);
  $s['max_website_gb']=(float)($body['plan']['max_website_size_gb']??$s['max_website_gb']);
  $s['storage_limit_gb']=(float)($body['plan']['storage_limit_gb']??$s['storage_limit_gb']);
  $s['retention_days']=(int)($body['plan']['retention_days']??$s['retention_days']);
 }
 $s['razorpay_subscription_id']=$subscription_id;
 $s['subscription_status']='active';
 $s['connection_status']='connected';
 update_option(WJBA43_OPTION_KEY,$s,false);

 wp_send_json_success($body);
}

add_action('wp_ajax_wjba43_claim_subscription','wjba43_ajax_claim_subscription');

function wjba43_ajax_claim_subscription(){

    if(
        !current_user_can('manage_options') ||
        !check_ajax_referer('wjba43_subscription','_ajax_nonce')
    ){
        wp_send_json_error(
            ['message'=>'Unauthorized.'],
            403
        );
    }

    /*
     * First claim the newly paid subscription.
     */
    $r = wjba43_claim_subscription_remote();

    if(is_wp_error($r)){
        wp_send_json_error(
            [
                'message'=>$r->get_error_message(),
                'code'=>$r->get_error_code()
            ]
        );
    }

    $s = wjba43_get_settings();

    /*
     * Save API token and website code returned by claim-token.php.
     */
    $api_token = sanitize_text_field(
        $r['api_token'] ?? ''
    );

    if($api_token !== ''){
        $s['api_token'] = $api_token;
    }

    $website_code = sanitize_text_field(
        $r['website_code'] ?? $s['website_code']
    );

    if($website_code !== ''){
        $s['website_code'] = $website_code;
    }

    /*
     * Subscription status.
     */
    $subscription_status = sanitize_text_field(
        $r['subscription_status'] ??
        ($r['status'] ?? '')
    );

    if($subscription_status === ''){
        $subscription_status = 'active';
    }

    $s['subscription_status'] = $subscription_status;

    /*
     * If claim-token.php already returned the plan,
     * save it immediately.
     */
    if(
        isset($r['plan']) &&
        is_array($r['plan'])
    ){

        $plan_name = sanitize_text_field(
            $r['plan']['name'] ?? ''
        );

        if($plan_name !== ''){
            $s['plan_name'] = $plan_name;
        }

        if(isset($r['plan']['max_website_gb'])){
            $s['max_website_gb'] =
                (float)$r['plan']['max_website_gb'];
        }

        if(isset($r['plan']['max_website_size_gb'])){
            $s['max_website_gb'] =
                (float)$r['plan']['max_website_size_gb'];
        }

        if(isset($r['plan']['storage_limit_gb'])){
            $s['storage_limit_gb'] =
                (float)$r['plan']['storage_limit_gb'];
        }

        if(isset($r['plan']['retention_days'])){
            $s['retention_days'] =
                (int)$r['plan']['retention_days'];
        }
    }

    /*
     * IMPORTANT:
     *
     * claim-token.php may return the API token but not the plan.
     * Therefore, immediately after successful activation,
     * fetch the authoritative subscription status using the
     * newly-created API token.
     */
    if(
        !empty($s['website_code']) &&
        !empty($s['api_token'])
    ){

        $status_response = wp_remote_post(
            WJBA43_SUB_STATUS_URL,
            [
                'timeout' => 30,

                'headers' => [
                    'Content-Type'    => 'application/json',
                    'Accept'          => 'application/json',
                    'X-WJ-Website-Code' => $s['website_code'],
                    'X-WJ-API-Token'    => $s['api_token']
                ],

                'body' => wp_json_encode(
                    [
                        'website_code' =>
                            $s['website_code'],

                        'domain' =>
                            parse_url(
                                home_url('/'),
                                PHP_URL_HOST
                            )
                    ]
                )
            ]
        );

        if(!is_wp_error($status_response)){

            $status_code =
                wp_remote_retrieve_response_code(
                    $status_response
                );

            $status_body =
                json_decode(
                    wp_remote_retrieve_body(
                        $status_response
                    ),
                    true
                );

            /*
             * Read the authoritative plan from
             * subscription-status.php.
             */
            if(
                $status_code >= 200 &&
                $status_code < 300 &&
                is_array($status_body) &&
                !empty($status_body['success'])
            ){

                /*
                 * Subscription information.
                 */
                if(
                    isset($status_body['subscription']) &&
                    is_array($status_body['subscription'])
                ){

                    $remote_sub =
                        $status_body['subscription'];

                    /*
                     * Save remote subscription status.
                     */
                    if(
                        !empty($remote_sub['status'])
                    ){

                        $remote_status =
                            sanitize_text_field(
                                $remote_sub['status']
                            );

                        if($remote_status !== ''){
                            $s['subscription_status'] =
                                $remote_status;
                        }
                    }

                    /*
                     * Save Razorpay subscription ID.
                     */
                    $remote_subscription_id =
                        $remote_sub[
                            'razorpay_subscription_id'
                        ] ??
                        (
                            $remote_sub[
                                'subscription_id'
                            ] ??
                            (
                                $remote_sub['id'] ??
                                ''
                            )
                        );

                    if(
                        is_array(
                            $remote_subscription_id
                        )
                    ){

                        $remote_subscription_id =
                            $remote_subscription_id['id'] ??
                            (
                                $remote_subscription_id[
                                    'subscription_id'
                                ] ?? ''
                            );
                    }

                    $remote_subscription_id =
                        sanitize_text_field(
                            $remote_subscription_id
                        );

                    if(
                        $remote_subscription_id !== ''
                    ){

                        $s['razorpay_subscription_id'] =
                            $remote_subscription_id;
                    }

                    /*
                     * THIS IS THE IMPORTANT PART:
                     * Get the actual paid plan.
                     */
                    if(
                        isset($remote_sub['plan']) &&
                        is_array($remote_sub['plan'])
                    ){

                        $remote_plan =
                            $remote_sub['plan'];

                        $remote_plan_name =
                            sanitize_text_field(
                                $remote_plan['name'] ?? ''
                            );

                        if($remote_plan_name !== ''){

                            $s['plan_name'] =
                                $remote_plan_name;
                        }

                        if(
                            isset(
                                $remote_plan[
                                    'max_website_gb'
                                ]
                            )
                        ){

                            $s['max_website_gb'] =
                                (float)$remote_plan[
                                    'max_website_gb'
                                ];
                        }

                        if(
                            isset(
                                $remote_plan[
                                    'max_website_size_gb'
                                ]
                            )
                        ){

                            $s['max_website_gb'] =
                                (float)$remote_plan[
                                    'max_website_size_gb'
                                ];
                        }

                        if(
                            isset(
                                $remote_plan[
                                    'storage_limit_gb'
                                ]
                            )
                        ){

                            $s['storage_limit_gb'] =
                                (float)$remote_plan[
                                    'storage_limit_gb'
                                ];
                        }

                        if(
                            isset(
                                $remote_plan[
                                    'retention_days'
                                ]
                            )
                        ){

                            $s['retention_days'] =
                                (int)$remote_plan[
                                    'retention_days'
                                ];
                        }
                    }
                }

                /*
                 * Some backend responses may return plan
                 * outside the subscription object.
                 */
                if(
                    isset($status_body['plan']) &&
                    is_array($status_body['plan'])
                ){

                    $remote_plan =
                        $status_body['plan'];

                    $remote_plan_name =
                        sanitize_text_field(
                            $remote_plan['name'] ?? ''
                        );

                    if($remote_plan_name !== ''){

                        $s['plan_name'] =
                            $remote_plan_name;
                    }

                    if(
                        isset(
                            $remote_plan[
                                'max_website_gb'
                            ]
                        )
                    ){

                        $s['max_website_gb'] =
                            (float)$remote_plan[
                                'max_website_gb'
                            ];
                    }

                    if(
                        isset(
                            $remote_plan[
                                'max_website_size_gb'
                            ]
                        )
                    ){

                        $s['max_website_gb'] =
                            (float)$remote_plan[
                                'max_website_size_gb'
                            ];
                    }

                    if(
                        isset(
                            $remote_plan[
                                'storage_limit_gb'
                            ]
                        )
                    ){

                        $s['storage_limit_gb'] =
                            (float)$remote_plan[
                                'storage_limit_gb'
                            ];
                    }

                    if(
                        isset(
                            $remote_plan[
                                'retention_days'
                            ]
                        )
                    ){

                        $s['retention_days'] =
                            (int)$remote_plan[
                                'retention_days'
                            ];
                    }
                }
            }
        }
    }

    /*
     * Final connected state.
     */
    $s['connection_status'] = 'connected';
    $s['manual_disconnect'] = 0;

    /*
     * Clear pending activation data.
     */
    $s['pending_claim_secret'] = '';
    $s['pending_local_subscription_id'] = 0;
    $s['pending_razorpay_subscription_id'] = '';

    /*
     * Save everything BEFORE reloading the page.
     */
    update_option(
        WJBA43_OPTION_KEY,
        $s,
        false
    );

    /*
     * Activation email.
     */
    $email = get_option('admin_email');

    if(
        is_email($email) &&
        !empty($s['api_token'])
    ){

        $subject =
            'WJ Website Backup activated - ' .
            (
                $s['plan_name']
                ?: 'Subscription'
            );

        $body =
            "Your WJ Website Backup subscription is active.\n\n" .
            "Website: " . home_url('/') . "\n" .
            "Plan: " .
            (
                $s['plan_name']
                ?: 'Active'
            ) .
            "\n\n" .
            "Your API token has been securely installed " .
            "in the WJ Website Backup plugin.\n\n" .
            "Webjooll";

        wp_mail(
            $email,
            $subject,
            $body
        );
    }

    /*
     * Return plan information to JavaScript as well.
     */
    wp_send_json_success(
        [
            'website_code' =>
                $s['website_code'],

            'subscription_status' =>
                $s['subscription_status'],

            'plan' => [
                'name' =>
                    $s['plan_name'],

                'max_website_gb' =>
                    (float)$s['max_website_gb'],

                'storage_limit_gb' =>
                    (float)$s['storage_limit_gb'],

                'retention_days' =>
                    (int)$s['retention_days']
            ]
        ]
    );
}


function wjba43_dir_size($dir){
    if(!is_dir($dir)) return 0;

    // Prefer du when available: it is much faster and avoids PHP walking
    // thousands of WordPress files in the AJAX request.
    if(function_exists('shell_exec')){
        $disabled=(string)ini_get('disable_functions');
        if($disabled==='' || strpos(','.$disabled.',', ',shell_exec,')===false){
            $cmd='du -sb --apparent-size -- '.escapeshellarg($dir).' 2>/dev/null';
            $out=@shell_exec($cmd);
            if(is_string($out) && preg_match('/^\s*(\d+)\s+/m',$out,$m)) return (int)$m[1];
        }
    }

    $total=0;
    try{
        $it=new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach($it as $f){
            try{
                if($f->isFile() && !$f->isLink()) $total+=(int)$f->getSize();
            }catch(Throwable $e){}
        }
    }catch(Throwable $e){}
    return $total;
}
function wjba43_website_size(){
    global $wpdb;
    @set_time_limit(120);
    $files=wjba43_dir_size(ABSPATH);
    $db=0;
    $rows=$wpdb->get_results('SHOW TABLE STATUS',ARRAY_A);
    if(is_array($rows)){
        foreach($rows as $r){
            $db+=(int)($r['Data_length']??0)+(int)($r['Index_length']??0);
        }
    }
    $total=$files+$db;
    return ['files_bytes'=>$files,'database_bytes'=>$db,'total_bytes'=>$total,'total'=>size_format($total,2)];
}
add_action('wp_ajax_wjba43_site_size','wjba43_ajax_site_size');
function wjba43_ajax_site_size(){
    if(!current_user_can('manage_options')) wp_send_json_error(['message'=>'Unauthorized.'],403);
    if(!check_ajax_referer('wjba43_site_size','_ajax_nonce',false)) wp_send_json_error(['message'=>'Security check failed. Please refresh the page and try again.'],403);
    try{ wp_send_json_success(wjba43_website_size()); }
    catch(Throwable $e){ wp_send_json_error(['message'=>'Website size check failed: '.$e->getMessage()],500); }
}
/* Disconnect handler: registered globally so admin-post.php can execute it. */
add_action('admin_post_wjba43_disconnect','wjba43_adminpost_disconnect');
function wjba43_adminpost_disconnect(){
    if(!current_user_can('manage_options')){
        wp_die('Unauthorized.');
    }

    check_admin_referer('wjba43_disconnect');

    $s=wjba43_get_settings();
    $old_token=(string)($s['api_token']??'');
    $website_code=(string)($s['website_code']??'');

    /*
     * Do not make the disconnect request depend on the backup API.
     * History is already cached whenever a successful history response is
     * received while connected. Preserve that cache exactly as-is here.
     */
    $s['manual_disconnect']=1;
    $s['api_token']='';
    $s['connection_status']='disconnected';

    update_option(WJBA43_OPTION_KEY,$s,false);
    wp_clear_scheduled_hook(WJBA43_CRON_HOOK);

    /* Best-effort remote revoke. Never block the local disconnect. */
    if($old_token!=='' && $website_code!==''){
        wp_remote_post(
            WJBA43_DISCONNECT_URL,
            [
                'timeout'=>5,
                'blocking'=>false,
                'headers'=>[
                    'Content-Type'=>'application/json',
                    'Accept'=>'application/json',
                    'X-WJ-Website-Code'=>$website_code,
                    'X-WJ-API-Token'=>$old_token
                ],
                'body'=>wp_json_encode(['website_code'=>$website_code])
            ]
        );
    }

    $url=add_query_arg(
        ['page'=>'wj-website-backup','wjba_disconnected'=>'1'],
        admin_url('admin.php')
    );
    wp_safe_redirect($url);
    exit;
}

function wjba43_page(){
 if(!current_user_can('manage_options'))return;


$s=wjba43_get_settings();
$n='';
if(isset($_GET['wjba_disconnected']) && $_GET['wjba_disconnected']==='1'){
    $n=wjba43_notice('Connection stopped successfully. Your subscription and existing backup history are preserved.','success');
}

/*
 * Disconnect is handled exclusively by admin-post.php through
 * wjba43_adminpost_disconnect().
 *
 * Do not process disconnect again inside the page renderer.
 */
 if(isset($_POST['test'])&&check_admin_referer('wjba43_test')){$r=wjba43_test_connection();$n=is_wp_error($r)?wjba43_notice(esc_html($r->get_error_message()),'error'):wjba43_notice('Connection successful. Website: '.esc_html($r['website']['domain']));}
 if(isset($_POST['db'])&&check_admin_referer('wjba43_db')){$r=wjba43_create_database_backup();$n=is_wp_error($r)?wjba43_notice('<strong>Database backup failed:</strong> '.esc_html($r->get_error_message()),'error'):wjba43_notice('<strong>Database backup completed.</strong> ID '.(int)$r['backup_id'].' | '.size_format($r['size_bytes']));}
 if(isset($_POST['files'])&&check_admin_referer('wjba43_files')){$r=wjba43_create_files_backup();$n=is_wp_error($r)?wjba43_notice('<strong>Files backup failed:</strong> '.esc_html($r->get_error_message()),'error'):wjba43_notice('<strong>Files backup completed.</strong> ID '.(int)$r['backup_id'].' | '.size_format($r['size_bytes']));}
 if(isset($_POST['all'])&&check_admin_referer('wjba43_all')){$r=wjba43_run_backup();$n=is_wp_error($r)?wjba43_notice('<strong>Full backup failed:</strong> '.esc_html($r->get_error_message()),'error'):wjba43_notice('<strong>Full backup completed.</strong> DB '.(int)$r['database']['backup_id'].' | Files '.(int)$r['files']['backup_id']);}
 
 /*
  * Do NOT automatically repair/reconnect on page load.
  * A deliberate disconnect must remain disconnected until the
  * administrator explicitly clicks "Reconnect Subscription".
  * Reconnection is handled only by wjba43_ajax_reconnect().
  */
 $connected=!empty($s['api_token']) && (int)($s['manual_disconnect']??0)!==1;

 $history=[];
 $history_error='';
 if($connected){
   $history=wjba43_get_backups();
   if(is_wp_error($history)){
     $history_error=$history->get_error_message();
     $history=is_array($s['backup_history_cache']??null)?$s['backup_history_cache']:[];
   }elseif(is_array($history) && isset($history['backups']) && is_array($history['backups'])){
     $s['backup_history_cache']=$history;
     update_option(WJBA43_OPTION_KEY,$s,false);
   }
 }else{
   $history=is_array($s['backup_history_cache']??null)?$s['backup_history_cache']:[];
 }
 if(is_wp_error($history)){$history_error=$history->get_error_message();$history=[];}
 if(!is_array($history))$history=[];
 if(!isset($history['backups'])||!is_array($history['backups']))$history['backups']=[];
 $plans = [
    'plans' => [],
    'country_code' => '',
    'blocked' => false,
    'setup_message' => ''
];

$plans_list = [];
$country_blocked = false;
$country_code = '';
 $subscription_status=strtolower((string)($s['subscription_status']??''));$subscription_active=($subscription_status==='active');$has_previous_subscription=(!$connected) && (!empty($s['razorpay_subscription_id']) || $subscription_active);$has_plan=!empty($s['plan_name']);if($connected&&$subscription_active&&!wp_next_scheduled(WJBA43_CRON_HOOK))wp_schedule_event(time()+300,'daily',WJBA43_CRON_HOOK);$cron=wp_next_scheduled(WJBA43_CRON_HOOK);
 ?>
 <div class="wrap"><h1>WJ Website Backup <span style="font-size:14px;font-weight:400;color:#666">by Webjooll</span></h1><?php echo $n; ?>
 <?php if($connected && !empty($history['website'])&&is_array($history['website'])): ?><div class="notice notice-info inline"><p><strong>Connected:</strong> <?php echo esc_html($history['website']['domain']); ?> (<?php echo esc_html($history['website']['website_code']); ?>)</p></div><?php endif; ?>
 <p><strong>Memory-safe file backup:</strong> website files are written to disk and uploaded to Cloud in 8 MB multipart chunks.</p>

 <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px;margin:16px 0;max-width:1100px">
  <h2 style="margin-top:0"><?php echo ($connected||$has_plan)?'Website &amp; Plan':'Get Started'; ?></h2>
  <?php if($has_plan): ?><p><strong>Current Plan:</strong> <?php echo esc_html($s['plan_name']); ?> &nbsp; <strong>Subscription:</strong> <?php echo esc_html(ucfirst($subscription_status?:'unknown')); ?> &nbsp; <strong>Connection:</strong> <?php echo $connected?'Connected':'Disconnected'; ?></p><?php endif; ?>
  <div id="wjba-size-result"><p>Check your current website size before starting a backup.</p></div>
  <button type="button" class="button button-primary" id="wjba-check-size">Check Website Size</button>
  
  <p style="margin-top:12px">
    <label>
      <input type="checkbox" id="wjba-ip-consent" />
      I agree to share my public IP address with Webjooll
      to determine my country and show the applicable subscription
      pricing. <a href="https://backup.webjooll.com/privacy-policy.html"
   target="_blank" rel="noopener noreferrer">Privacy Policy</a>
    </label>
  </p>

  <span id="wjba-size-loading" style="margin-left:10px"></span>
 </div>

 <h2><?php echo ($connected||$has_plan)?'Plans &amp; Subscription':'Choose Your Backup Plan'; ?></h2>
 <?php if(!$connected && $subscription_active && $has_previous_subscription): ?><div class="notice notice-info inline" style="max-width:1100px"><p><strong>Subscription found.</strong> Your previous subscription can be reconnected without purchasing again while it remains active. <button type="button" class="button button-primary" id="wjba-reconnect">Reconnect Subscription</button> <span id="wjba-reconnect-status"></span></p></div><?php endif; ?>

<p id="wjba-plans-message" style="color:red;font-size:16px">
    First check your website size to view available plans.
</p>

<div
    id="wjba-plan-grid"
    style="display:none;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;max-width:1100px"
></div>

 <p class="description">Payment is processed securely through Razorpay. Razorpay secret keys and Cloud credentials never enter WordPress.</p>
 <?php if(!empty($plans['setup_message'])): ?><div class="notice notice-warning inline"><p><?php echo esc_html($plans['setup_message']); ?></p></div><?php endif; ?>

 <hr><h2>Backup History</h2>
 <?php if($history_error): ?><div class="notice notice-warning inline"><p><strong>Backup history unavailable:</strong> <?php echo esc_html($history_error); ?></p></div><?php endif; ?>
 <?php if(!$connected): ?><div class="notice notice-info inline"><p>Showing the last saved backup history. Reconnect the subscription to refresh it from the backup server.</p></div><?php endif; ?>
 <h3>Database Backups</h3><table class="widefat striped"><thead><tr><th>Date &amp; Time</th><th>Size</th><th>Status</th><th>Action</th></tr></thead><tbody><?php echo wjba43_backup_rows($history['backups'],'database',$connected); ?></tbody></table>
 <h3 style="margin-top:28px">Website Files Backups</h3><table class="widefat striped"><thead><tr><th>Date &amp; Time</th><th>Size</th><th>Status</th><th>Action</th></tr></thead><tbody><?php echo wjba43_backup_rows($history['backups'],'files',$connected); ?></tbody></table>
 <p class="description">Only backups still retained by your plan are shown. Download links are temporary; Cloud remains private.</p>
 <?php if($connected): ?>
 <hr><h2>Manual Backup</h2>
 <form method="post" style="display:inline-block;margin-right:8px"><?php wp_nonce_field('wjba43_db'); ?><input type="hidden" name="db" value="1"><?php submit_button('Backup Database','secondary',null,false); ?></form>
 <form method="post" style="display:inline-block;margin-right:8px"><?php wp_nonce_field('wjba43_files'); ?><input type="hidden" name="files" value="1"><?php submit_button('Backup Website Files','secondary',null,false); ?></form>
 <form method="post" style="display:inline-block"><?php wp_nonce_field('wjba43_all'); ?><input type="hidden" name="all" value="1"><?php submit_button('Backup Everything','primary',null,false); ?></form>
 <hr><h2>Automatic Backup</h2><p><strong>Frequency:</strong> Daily</p><p><strong>Last completed backup:</strong> <?php $last_auto=''; foreach(($history['backups']??[]) as $hb){if(($hb['status']??'')==='completed'&&!empty($hb['created_at'])){ $dt=strtotime($hb['created_at']); if($dt && (!$last_auto || $dt>$last_auto))$last_auto=$dt; }} echo $last_auto?esc_html(wp_date('d M Y, h:i A',$last_auto)):'No completed backup yet'; ?></p><p><strong>Next run:</strong> <?php echo $cron?esc_html(wp_date('d M Y, h:i A',$cron)):'Not scheduled'; ?></p><p><strong>Status:</strong> <?php echo $cron?'<span style="color:#008a20;font-weight:600">Scheduled</span>':'<span style="color:#b32d2e;font-weight:600">Not scheduled</span>'; ?></p>
 <?php endif; ?>
 <hr><h2>Connection</h2>
 <?php if($connected): ?>
 <p>Stopping the connection revokes this website's API token. Existing backups remain stored.</p><form method="post" id="wjba-disconnect-form">
 <?php wp_nonce_field('wjba43_disconnect'); ?><input type="hidden" name="action" value="wjba43_disconnect">
 <button class="button button-secondary" type="submit">Stop Connection</button>
 </form>
 <?php else: ?>
 <p>The backup connection is currently stopped. Your existing subscription and backup history are preserved.</p>
 <?php if($subscription_active && $has_previous_subscription): ?><button type="button" class="button button-primary" id="wjba-reconnect">Reconnect Subscription</button> <span id="wjba-reconnect-status"></span><?php endif; ?>
 <?php endif; ?>

 </div>
 <?php }
add_action('wp_ajax_wjba43_download','wjba43_ajax_download');
function wjba43_ajax_download(){if(!current_user_can('manage_options')||!check_ajax_referer('wjba43_download','_ajax_nonce'))wp_send_json_error(['message'=>'Unauthorized.'],403);$id=isset($_POST['backup_id'])?(int)$_POST['backup_id']:0;$r=wjba43_download_backup($id);if(is_wp_error($r))wp_send_json_error(['message'=>$r->get_error_message()]);wp_send_json_success(['url'=>$r['url']]);}
