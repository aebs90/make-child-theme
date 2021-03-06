<?php
/**
 * @package Make Child
 */


/**
 * The theme version.
 */
define('TTFMAKE_CHILD_VERSION', '1.1.0');

/**
 * Turn off the parent theme styles.
 *
 * If you would like to use this child theme to style Make from scratch, rather
 * than simply overriding specific style rules, simply remove the '//' from the
 * 'add_filter' line below. This will tell the theme not to enqueue the parent
 * stylesheet along with the child one.
 */
//add_filter( 'make_enqueue_parent_stylesheet', '__return_false' );

/**
 * Add your custom theme functions here.
 */


/* redirect users to blog page after login */
function redirect_to_front_page()
{
    global $redirect_to;
    if (!isset($_GET['redirect_to'])) {
        //$redirect_to = get_option('siteurl');

        $redirect_to = '/blog';
    }
}
add_action('login_form', 'redirect_to_front_page');

add_action('wp_logout', 'auto_redirect_after_logout');
function auto_redirect_after_logout()
{
    wp_redirect(home_url());
    exit();
}

add_action('woocommerce_before_my_account', 'show_signed_forms');

function show_signed_forms()
{
    echo signed_forms_html();
}

$membership_forms = array('amt-membership' => array('membership_agreement_date','liability_waiver_date','emergency_contact_date'),
                          'monster-corps-membership' => array('membership_agreement_date','liability_waiver_date','emergency_contact_date'),
                          'bot-bot-program-membership' => array('liability_waiver_date'),
                          'ops-membership' => array('membership_agreement_date','liability_waiver_date','emergency_contact_date')
);
// other membership types: ops-membership

// this function doesn't emit anything; instead it returns a string of html
// that's because this is used as a *filter* (not a hook) for woocommerce_memberships_thank_you_message
function signed_forms_html()
{
    $user_id = get_current_user_id();
    $umeta = get_user_meta($user_id);

    $user_memberships = array();
    $pretty_plan_names = array();

    $html = "";

    foreach (wc_memberships_get_membership_plans() as $plan) {
        $slug = $plan->slug;
        $name = $plan->name;
        $pretty_plan_names[$slug] = $name;

        if (wc_memberships_is_user_active_member($user_id, $slug)) {
            array_push($user_memberships, $slug);
        }
    }

    global $membership_forms;

    $first_name = $umeta['first_name'][0];
    $last_name = $umeta['last_name'][0];
    $full_name = $first_name . " " . $last_name;

    $packet_url_data = array('amt-membership' => array('PowerFormId' => '7fa50c17-c7e1-4578-aefd-ac5261e64b33',
                                                       'Member â€“ Regular_UserName' => $full_name,
                                                       'EnvelopeField_user_id' => $user_id,
                                                       'EnvelopeField_packet_name' => 'amt-membership',
                                                       'EnvelopeField_server_hostname' => $_SERVER['HTTP_HOST']),
                             'bot-bot-program-membership' => array('PowerFormId' => '5e80fcde-bdb3-452e-a464-3286cb485530',
                                                                   'Day Member_UserName' => $full_name,
                                                                   'EnvelopeField_user_id' => $user_id,
                                                                   'EnvelopeField_packet_name' => 'bot-bot-program-membership',
                                                                   'EnvelopeField_server_hostname' => $_SERVER['HTTP_HOST']),
                             'monster-corps-membership' => array('PowerFormId' => '7fa50c17-c7e1-4578-aefd-ac5261e64b33',
                                                                 'Member â€“ Regular_UserName' => $full_name,
                                                                 'EnvelopeField_user_id' => $user_id,
                                                                 'EnvelopeField_packet_name' => 'monster-corps-membership',
                                                                 'EnvelopeField_server_hostname' => $_SERVER['HTTP_HOST']),
                             'ops-membership' => array('PowerFormId' => '7fa50c17-c7e1-4578-aefd-ac5261e64b33',
                                                       'Member â€“ Regular_UserName' => $full_name,
                                                       'EnvelopeField_user_id' => $user_id,
                                                       'EnvelopeField_packet_name' => 'ops-membership',
                                                       'EnvelopeField_server_hostname' => $_SERVER['HTTP_HOST'])

    );

    function make_packet_url($data)
    {
        $docusign_prefix = 'https://na3.docusign.net/Member/PowerFormSigning.aspx?';
        $url = $docusign_prefix . http_build_query($data);
        return $url;
    }


    $html .= ('<div class="signed-forms">');
    $html .= ('<h2>Signed forms</h2>');
	
		if ( is_user_logged_in() ) {
			$picture = get_user_meta( get_current_user_id(), 'amt_directory_picture', true );
			
			if ( ! $picture ) {
		?>
				<div class="notice">
					You don't have a profile picture.
					Please <a href="<?php echo site_url(); ?>/wp-admin/profile.php">set your profile picture</a>
				</div>
		<?php
			}
		}


    $all_packets_signed = true;
    $pretty_field_names = array('membership_agreement_date' => 'Membership Agreement',
                                'liability_waiver_date' => 'Liability Waiver',
                                'emergency_contact_date' => 'Emergency Contact'
    );


    //$html .= "<pre>" . $user_memberships . "</pre>";

    $table_str  = '<table><tr><th>Forms for</th><th>Date signed</th></tr>';

    foreach ($user_memberships as $plan_name) {
        $field_names = $membership_forms[$plan_name];
        $packet_url = make_packet_url($packet_url_data[$plan_name]);

        $dates_signed = array();
        $this_packet_signed = true;
        foreach ($field_names as $field_name) {
            $form_date = get_user_meta($user_id, $field_name, true);
            if ($form_date) {
                $dates_signed[$field_name] = $form_date;
            } else {
                $dates_signed[$field_name] = null;
                $this_packet_signed = false;
                $all_packets_signed = false;
            }
        }

        $date_display = "";
        $smiley_emoji = ":D";
        $sad_emoji = ":(";
        foreach ($dates_signed as $field_name => $date) {
            $pretty_field_name = $pretty_field_names[$field_name];
            if (is_null($date)) {
                $date_display .= $sad_emoji . ' ' . $pretty_field_name . ":  not signed<br />";
            } else {
                $date_display .= $smiley_emoji . ' ' . $pretty_field_name . ": " . $date . "<br />";
            }
        }
        if (!$this_packet_signed) {
            $date_display .= "<a target='_blank' href='" . $packet_url . "'>Sign forms here</a>";
        }

        $table_str .= '<tr><td>' . $pretty_plan_names[$plan_name] . '</td><td>' . $date_display . '</td></tr>';
    }
    $table_str .= '</table>';

    if (!$all_packets_signed) {
        $html .= "<span style='font-weight: bold'>To keep your membership active, you must sign the liability/information forms below</span>";
    }

    $html .= $table_str;

    $html .= '</div>';

    if (!$all_packets_signed) {
        $html = "<div style='border: 20px solid indianred; padding: 0.5em; background-color: indianred; color: white'>" . $html . "</div>";
    }

    return $html;
}

add_action('woocommerce_before_my_account', 'my_laser_usage');

function my_laser_usage()
{
    print("<hr><h2>Laser Cutter Usage</h2>\n");

    $userid = get_current_user_id();
    $auth = md5($userid."3e532d5d228f9594c55d72978e3d3663ef32ed64dad4d078236ebd24c6a1a2d4fb872d4ef9a84eca78ffeb449afd98a6b4080cecb4124a9cb0dbd3188bf80415");
    $data = json_decode(file_get_contents("https://ssl.acemonstertoys.org/member/mylaserfees.php?userid=$userid&auth=$auth"), true);

    if (!isset($data['ok'])) {
        print("Error loading laser cutter usage, sorry!<br>");
        return;
    }

    if (!isset($data['rows']) || count($data['rows']) == 0) {
        print("No laser cutter usage found under this account. Updates hourly.<br>");
        return;
    }


    print("<table border=1>");
    print("<thead>");
    print("<tr>");
    print("<th>date</th>");
    print("<th>usage minutes</th>");
    print("<th>fee</th>");
    print("<th>billed</th>");
    print("<th>note</th>");
    print("</tr>");
    print("</thead>");

    foreach ($data['rows'] as $row) {
        print("<tr>");
        print("<td>");
        print(implode("</td><td>", $row));
        print("</td>");
        print("</tr>");
    }

    print("</table>");
}

// after completing purchase of a new membership, show signed forms status
function filter_prepend_signed_forms($content)
{
    if (strpos($content, 'purchasing a membership') !== false) {
        return $content . signed_forms_html();
    } else {
        return $content;
    }
}

add_filter('woocommerce_memberships_thank_you_message', 'filter_prepend_signed_forms');

// return true if a given RFID is a member
function is_member($rfid, $opts = array())
{
    $args = array( 'meta_key' => 'member_rfid', 'meta_value' => $rfid );
    $user_query = new WP_User_Query(array_merge($args, $opts));
    return !empty($user_query->results);
}

//////////////// API ///////////////////////

// Find user ID associated with rfid
function find_member_ids_from_rfid($rfid)
{ #API2
  $args = array( 'meta_key' => 'member_rfid', 'meta_value' => $rfid );
    $user_query = new WP_User_Query($args);
    $ids = array();

    //
    //$test_query = new WP_User_Query( array( 'number' => 200) );
    //echo var_export($test_query->results, true);
    //
    //echo var_export(get_field('member_rfid', 'user_149'), true);


    //echo var_export($user_query->results,true);
    foreach ($user_query->results as $user) {
        array_push($ids, $user->ID);
    }
    return $ids;
}

// Get all memberships as slugs
function membership_plan_slugs()
{ #API3
  $plan_slugs = array();
    foreach (wc_memberships_get_membership_plans() as $plan) {
        if ($plan->slug == "bot-bot-program-membership") {
            continue;
        } // ignore box bot plan
        array_push($plan_slugs, $plan->slug);
    }
    return $plan_slugs;
}

// Check if a given user ID is active or not
function is_member_active($user_id, $slugs=null)
{ #API2
    $active = false;
    if(!$slugs)
        $slugs = membership_plan_slugs();

    foreach ($slugs as $slug) {
        if (wc_memberships_is_user_active_member($user_id, $slug)) {
            $active = true;
        }
    }
    return $active;
}

// Check if a given RFID is good or not
function rfid_status($request)
{ #API
  if (!validate_request($request)) {
      return invalid_request_response();
  }

    $success = false;
    $rfid = $request->get_params('id');
    //still handle one rfid assigned to multiple users
    $user_ids = find_member_ids_from_rfid($rfid);
    foreach ($user_ids as $user_id) {
        if (is_member_active($user_id)) {
            $success = true;
        }
    }

    return $success;
};
function rfid_status_int($rfid)
{ #API
    $success = false;
    //still handle one rfid assigned to multiple users
    $user_ids = find_member_ids_from_rfid($rfid);

    foreach ($user_ids as $user_id) {
        if (is_member_active($user_id)) {
            $success = true;
        }
    }

    return $success;
};
// Get the RFID for a specific user given their UserID
function rfid_for_user($user_id)
{ #API2
  // $args = array( 'meta_key' => 'member_rfid', 'user_id' => $user_id );
  // $user_query = new WP_User_Query( $args );
  // $rfid = $user_query->results;
  $rfid = get_user_meta($user_id, 'member_rfid', true);
    return $rfid;
}

function checkcerts($request)
{
  if (!validate_request($request)) {
      return invalid_request_response();
  }
    $requestobj = get_user_for_rfid($request);
    $returnData->active =  rfid_status_int($request[id]);
    if (strstr($requestobj, 'Could not find')) {
        $returnData->name = 'not_found';
        $returnData->certs = 'not_found';
        // $returnData->to_vend = 'not_found';
    } else {
        $returnData->name = get_user_by('id', $requestobj['ID'])->first_name." ".get_user_by('id', $requestobj['ID'])->last_name;
        $returnData->certs = get_field('has_taken_laser_class', 'user_'.$requestobj['ID']);
        $newQuery = wc_get_orders(array(
      'customer' => $requestobj['ID'],
      'status' => 'to-vend'
    ));
        // $orders = array();
        // if ($newQuery) {
        //     foreach ($newQuery as $key => $value) {
        //         array_push($orders, $value->id);
        //     }
        //     $returnData->to_vend = $orders;
        // } else {
        //     $returnData->to_vend = 'no-vend-order';
        // }
    }

    return $returnData;
}

function get_vendable_orders($request)
{
  if (!validate_request($request)) {
      return invalid_request_response();
  }
    $requestobj = get_user_for_rfid($request);
    if (strstr($requestobj, 'Could not find')) {
        $returnData->name = 'not_found';
        $returnData->to_vend = 'no-orders';
        $returnData->disbursed = 'no-orders';
    } else {
        $returnData->name = get_user_by('id', $requestobj['ID'])->first_name." ".get_user_by('id', $requestobj['ID'])->last_name;
        $vendQuery = wc_get_orders(array(
      'customer' => $requestobj['ID'],
      'status' => 'to-vend'
    ));
        $vendOrders = array();
        if ($vendQuery) {
            foreach ($vendQuery as $key => $value) {
                array_push($vendOrders, $value->id);
            }
            $returnData->to_vend = $vendOrders;
        } else {
            $returnData->to_vend = 'no-orders';
        }
        $disbursedQuery = wc_get_orders(array(
      'customer' => $requestobj['ID'],
      'status' => 'disbursed'
    ));
        $disbOrders = array();
        if ($disbursedQuery) {
            foreach ($disbursedQuery as $key => $value) {
                array_push($disbOrders, $value->id);
            }
            $returnData->disbursed = $disbOrders;
        } else {
            $returnData->disbursed = 'no-orders';
        }

    }

    return $returnData;
}

function view_vend_order($request)
{
  if (!validate_request($request)) {
      return invalid_request_response();
  }
    $order = wc_get_order($request[order_id]);
    if ( $order==false ) {
      $returnData->items = 'could_not_find_order';
      $returnData->status = 'could_not_find_order';
      return $returnData;
    } elseif ($order->get_status()) {
      $returnData->items = array();
        foreach ($order->get_items() as $item_id => $item_data) {
            array_push($returnData->items, $item_data['name']);
        }
        $returnData->status = $order->get_status();

      return $returnData;
    }
}

function disburse_vendable_order($request)
{
  if (!validate_request($request)) {
      return invalid_request_response();
  }
    $order = wc_get_order($request[order_id]);
    if ( $order==false ) {
      $returnData->status = 'could_not_find_order';
      return $returnData;
    } elseif ($order->get_status()=='to-vend') {
      $returnData->items = array();

        foreach ($order->get_items() as $item_id => $item_data) {
            $next_item = array();
            array_push($next_item, $item_data['name']);
            $product = wc_get_product($item_data['product_id']);
            array_push($next_item, $product->get_attribute('vend_bay'));
            array_push($returnData->items, $next_item);

        $order->update_status('disbursed');
        $returnData->status = 'marking_as_disbursed';
      }
      return $returnData;
    } elseif ($order->get_status()=='disbursed') {
      $returnData->status = 'already_disbursed';
      return $returnData;
    } else {
      $returnData->status = 'could_not_find_order';
      return $returnData;
    }
}

function get_product_info($request)
{
  if (!validate_request($request)) {
      return invalid_request_response();
  }
    //
    // Built current list of vending items
    //
    $vend_items = array();
    $the_query = new WP_Query(array( 'product_cat' => 'vend-item' ));
    while ($the_query->have_posts()) {
        $the_query->the_post();
        $product = wc_get_product(get_the_ID());
        $vend_bay = $product->get_attribute('vend_bay');
        if($vend_bay == $request['vend_bay']) {
          $returnData->vend_bay = $vend_bay;
          $returnData->title = $product->get_title();
          $returnData->image_url = $product->get_image();
          $returnData->desc =  $product->get_description();
          return $returnData;
        }
    }
    return "Couldn't find that bay";

}

function get_expiration_schedule()
{
    $time = time();
    echo $time . "\n";
    //$next_scheduled = wp_next_scheduled( 'amt_expire_user_role' );
    //if (! $next_scheduled || $next_scheduled < $time) {
    //  wp_schedule_event($time, 'hourly', 'amt_expire_user_role');
    //}
    return wp_next_scheduled('amt_expire_user_role');
    //return expire_user_role();
}

// Loop over all users, find someone whose membership has expired,
// then if they are 'author' or 'customer', set their role to 'subscriber' instead
function expire_user_role()
{
    if (! wp_next_scheduled('amt_expire_user_role')) {
        wp_schedule_event(time(), 'hourly', 'amt_expire_user_role');
    }

    // Get the list of valid membership plans
    $plan_slugs = membership_plan_slugs();

    $out = array();

    @file_get_contents("http://acemonstertoys.org/tmp-logger/logger.php?called=1");

    foreach (get_users() as $user) {
        $activePlan = false;

        // loop over all the plans, see if the member has one
        foreach ($plan_slugs as $slug) {
            if (wc_memberships_is_user_active_member($user->ID, $slug)) {
                $activePlan = true;
                $sslug = $slug;
                break;
            }
        }

        $activeRole = false;
        $adminRole = false;
        $rrole = $user->roles[0];
        $arole = "--";

        // loop over all the user's roles, look for a role that is not author or customer
        foreach ($user->roles as $role) {
            if ($role == 'author' || $role == 'editor') {
                $activeRole = true;
                $rrole = $role;
            }
            if ($role == 'shop_manager' || $role == 'administrator') {
                $adminRole = true;
                $arole = $role;
            }
        }

        // Big logic tree of all possible combinations of active/inactive/etc
        if ($activePlan) {
            if ($adminRole) { // active membership & is admin -> skip
                $out[] = array('id'=>$user->user_email, 'r'=>"active $sslug admin $arole");
                continue;
            }
            if (!$activeRole) { // active membership, no active role -> upgrade to active role
                $out[] = array('id'=>$user->user_email, 'r'=>'UPGRADE');
                $user->set_role('author');
                // log it on the admin site
                //@file_get_contents("http://acemonstertoys.org/member/changerole.php?up=1&msg=".urlencode($user->user_email));
                @file_get_contents("http://acemonstertoys.org/tmp-logger/logger.php?up=1&msg=".urlencode($user->user_email));
                continue;
            }
            // active membership, active role -> skip
            $out[] = array('id'=>$user->user_email, 'r'=>"active $sslug role $rrole");
            continue;
        } else {
            if ($adminRole) { // no active membership & is admin -> skip
                $out[] = array('id'=>$user->user_email, 'r'=>"inactive admin $arole");
                continue;
            }
            if (!$activeRole) { // no active membership & no active role -> skip
                $out[] = array('id'=>$user->user_email, 'r'=>"inactive role $rrole");
                continue;
            }

            // no active membership but active role -> downgrade role
            $out[] = array('id'=>$user->user_email, 'r'=>'DOWNGRADE');
            $user->set_role('subscriber');

            // log it on the admin site
            @file_get_contents("http://acemonstertoys.org/tmp-logger/logger.php?down=1&msg=".urlencode($user->user_email));
        }
    }

    return $out;
}



// Slurp all users and their info
function get_all_users_info($request)
{ #API
  if (!validate_request($request)) {
      return invalid_request_response();
  }

    $out = array();

    // Get all the plans (so we can put their names & slugs into the results without looking up each time)
    $plans_in = wc_memberships_get_membership_plans();
    $plans = array();
    foreach ($plans_in as $plan) {
        $plans[$plan->id] = $plan;
    }

    foreach (get_users() as $user) {
        $umeta = get_user_meta($user->ID);
        $meta = array();

        // List of fields from user metadata that we'd like to pull
        $fields = array('nickname','description','wp_capabilities','wp_user_level','ing_phone','ing_email','member_rfid','member_door_code','amt_membership_legal','amt_status_handle','membership_date','membership_agreement_date','liability_waiver_date','has_taken_laser_class','twitter_handle','billing_first_name','billing_last_name','billing_company','billing_address_1','billing_address_2','billing_city','billing_postcode','billing_country','billing_state','billing_phone','billing_email','shipping_first_name','shipping_last_name','shipping_company','shipping_address_1','shipping_address_2','shipping_city','shipping_postcode','shipping_country','shipping_state','account_status','role','_stripe_customer_id','_order_count','_money_spent','paying_customer');
        foreach ($fields as $field) {
            if (isset($umeta[$field])) {
                if (is_array($umeta[$field])) {
                    $meta[$field] = $umeta[$field][0];
                } else {
                    $meta[$field] = $umeta[$field];
                }
            }
        }

        // List of fields from the user object we'd like to pull
        $fields = array('ID','user_login','user_nicename','user_email','user_url','display_name','first_name','last_name');
        foreach ($fields as $field) {
            if (isset($user, $field)) {
                $meta[$field] = $user->{$field};
            }
        }

        // Get this guy's WC memberships
        $uplans_in = wc_memberships_get_user_memberships($user->ID);
        $uplans = array();

        // Pull out the data for each plan that we are interested in
        foreach ($uplans_in as $uplan_in) {
            $uplan = array();
            $uplan['id'] = $uplan_in->plan_id;
            $uplan['status'] = $uplan_in->get_status();
            $uplan['name'] = $plans[$uplan_in->plan_id]->name;
            $uplan['slug'] = $plans[$uplan_in->plan_id]->slug;
            $uplan['end'] = $uplan_in->get_end_date('timestamp');
            $uplans[] = $uplan;
        }

        $meta['plans'] = $uplans;
        $out[] = $meta;
    }

    return $out;
}

// Return list of all active RFIDs
function active_rfids($request)
{ #API
  if (!validate_request($request)) {
      return invalid_request_response();
  }

    $rfids = array();

    foreach (get_users() as $user) {
        $user_id = $user->ID;
        if (is_member_active($user_id)) {
            $rfid = rfid_for_user($user_id);
            if (!empty($rfid)) {
                array_push($rfids, $rfid);
            }
        }
    }

    $out = array("OK",$rfids);

    return $out;
}

// Returns list of all rfids that are active and have a given certification
// Requires auth
// input: cert=certification id
// output: [ "OK", ["0123ABC", "0123ABC" ], "cert" ]
function get_cert_rfids($request) {
  if (!validate_request($request)) {
      return invalid_request_response();
  }

    $slugs = membership_plan_slugs();
    $rfids = [];
    $cert = $request['cert'];
    foreach (get_users() as $user) {
        $user_id = $user->ID;
        if (is_member_active($user_id, $slugs)) {
            $rfid = rfid_for_user($user_id);
            if (!empty($rfid)) {
                $certs = get_field('has_taken_laser_class', 'user_'.$user_id);
                if(in_array($cert, $certs))
                    array_push($rfids, $rfid);
            }
        }
    }

    return ["OK", $rfids, $cert];
}

// Counts the number of active, inactive, and operator users
function count_active_users($request)
{ #API
  $countA = $countI = $countO = 0;

    foreach (get_users() as $user) {
        if (wc_memberships_is_user_active_member($user->ID, 'ops-membership')) {
            $countO++;
        } elseif (is_member_active($user->ID)) {
            $countA++;
        } else {
            $countI++;
        }
    }

    return array($countA, $countI, $countO);
}

// Authenticates the request (just check if they have the right password)
function validate_request($request)
{ #API2
  $header = $request->get_header('X-Amt-Auth');
    /* FIXME: move to someplace not here */
    return $header == "3e532d5d228f9594c55d72978e3d3663ef32ed64dad4d078236ebd24c6a1a2d4fb872d4ef9a84eca78ffeb449afd98a6b4080cecb4124a9cb0dbd3188bf80415";
    // return true;
}

// Standard error for wrong pass
function invalid_request_response()
{ #API2
  return "['ERROR: Request is invalid']";
}

// Find the user associated with a given RFID
function get_user_for_rfid($request)
{ #API
  if (!validate_request($request)) {
      return invalid_request_response();
  }

    $rfid = trim($request->get_param('id'));
    $member_id = find_member_ids_from_rfid($rfid);

    if (empty($member_id)) {
        return "Could not find user with ".var_export($rfid, true);
    } else {
        $member_id = $member_id[0];
    } // hopefully there aren't multiple, but maybe there are, pick first

    $amtStatusHandle = get_field('amt_status_handle', 'user_'.$member_id);
    $userData = get_user_by('ID', $member_id)->to_array();
    $userData["amt_status_handle"] = $amtStatusHandle;
    return $userData;
}


// Set the end date for a user's membership
// Pass in:
// 'email' (can be email address or userID)
// 'time' unix timestamp to set end date. Must set it in the future for
function set_user_end_date($request)
{ #API
  if (!validate_request($request)) {
      return invalid_request_response();
  }

    if (!isset($request['email'])) {
        return "ERROR: Missing email";
    }

    if (!isset($request['time'])) {
        return "ERROR: Missing end time";
    }

    $userid = trim($request['email']);
    $date = trim($request['time']);

    if (isset($request['slug'])) {
        $searchslug = $request['slug'];
    } else {
        $searchslug = 'legacy';
    }

    $user = get_user_by('ID', $userid);
    if (!$user) {
        $user = get_user_by('email', $userid);
    }
    if (!$user) {
        return "ERROR: Cannot find user '$userid'";
    }

    // Get all plans up front so we can look at info about them later
    $plans_in = wc_memberships_get_membership_plans();
    $plans = array();
    foreach ($plans_in as $plan) {
        $plans[$plan->id] = $plan;
    }

    $slugs = array();
    foreach ($plans as $plan) {
        $slugs[$plan->id] = $plan->slug;
    }

    // Get all the memberships belonging to the user
    $uplans = wc_memberships_get_user_memberships($user->ID);

    $out = array();

    // Look for plans that are active and match the slug we are searching for, then cancel them
    foreach ($uplans as &$plan) {
        $status = $plan->get_status();
        $slug = $plans[$plan->plan_id]->slug;
        if ($status == 'active' && $slug == $searchslug) {
            $out[] = array('id'=>$plan->plan_id, 'slug'=>$plans[$plan->plan_id]->slug, 'end'=>$date);
            $plan->set_end_date($date);
            $plan->cancel_membership("Expired on old site");
        }
    }

    return array('id'=>$user->id, 'email'=>$user->user_email, 'plans_updated'=>$out, 'searchslug'=>$searchslug);
}

// Send out customer invoice email for an order
// Pass in:
// 'orderid'
function send_customer_invoice($request)
{ #API
  if (!validate_request($request)) {
      return invalid_request_response();
  }

    if (!isset($request['orderid'])) {
        return "ERROR: Missing email";
    }

    $order = wc_get_order(absint($request['orderid']));
    if (!$order) {
        return "ERROR: Couldn't find order '".$request['orderid']."'";
    }

    $wc = WC();
    if (!$wc) {
        return "ERROR: Couldn't get WC?";
    }
    $mailer = $wc->mailer();
    if (!$mailer) {
        return "ERROR: Couldn't get mailer?";
    }
    $mailer->customer_invoice($order);

    return "Probably OK";
}

// Check if current user has any past due orders
function check_unpaid_orders()
{
    $orders = wc_get_orders(array(
        'status'=>'wc-pending',
        'customer'=>get_current_user_id()
    ));
    if (count($orders) > 0) {
        return true;
    } else {
        return false;
    }
}


// Logs an rfid action
// input:
// rfid         10-digit RFID tag
// timestamp        date/time of the log record
// device           the device from the <certs> list
// action           list of actions taken by the fobbox
// value            value associated with that action
//
function log_rfid($request) {
    global $wpdb;
    global $amt_db_version;

  if (!validate_request($request)) {
      return invalid_request_response();
  }

    $installed_ver = get_option('amt_db_version');
    $installed = amt_db_install();

    $userid = find_member_ids_from_rfid($request['rfid']);

    $table_name = $wpdb->prefix . 'amt_rfid_log';

$wpdb->query( $wpdb->prepare( 
    "
        INSERT INTO $table_name
        ( timestamp, rfid, userid, device, action, value )
        VALUES ( FROM_UNIXTIME(%d), %s, %d, %s, %s, %s )
    ", 
        $request['timestamp'],
        $request['rfid'],
        $userid,
        $request['device'],
        urldecode($request['action']),
        urldecode($request['value'])
) );

    $id = $wpdb->insert_id;

    $out = [
        $id,
        $request['rfid'],
        $request['timestamp'],
        $request['device'],
        urldecode($request['action']),
        urldecode($request['value']),
        $userid,
        $installed,
        $amt_db_version,
        $installed_ver,
        $table_name

        ];
    return $out;
}


global $amt_db_version;
$amt_db_version = '1.0';

function amt_db_install() {
    global $wpdb;
    global $amt_db_version;

    $installed_ver = get_option('amt_db_version');

    if($installed_ver != $amt_db_version) {

        $table_name = $wpdb->prefix . 'amt_rfid_log';
        
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id int(9) NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL,
            rfid varchar(20),
            userid int(9),
            device varchar(100),
            action varchar(1000),
            value varchar(1000),
            PRIMARY KEY  (id),
            KEY timestamp_i (timestamp),
            KEY rfid_i (rfid),
            KEY userid_i (userid)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $res = dbDelta( $sql );

        update_option( 'amt_db_version', $amt_db_version );

        return $res;
    }

    return false;
}


// Map API URLs to PHP methods
add_action('rest_api_init', function () {

    register_rest_route('amt/v1', '/rfid/(?P<id>[a-zA-Z0-9]+)/certs', array(
    'methods' => 'GET',
    'callback' => 'checkcerts'
  ));

    register_rest_route('amt/v1', '/rfid/(?P<id>[a-zA-Z0-9]+)', array(
    'methods' => 'GET',
    'callback' => 'rfid_status'
  ));

    register_rest_route('amt/v1', '/rfids/active', array(
    'methods' => 'GET',
    'callback' => 'active_rfids'
  ));

    register_rest_route('amt/v1', '/rfids/cert/(?P<cert>[a-zA-Z0-9]+)', array(
    'methods' => 'GET',
    'callback' => 'get_cert_rfids'
  ));

    register_rest_route('amt/v1', '/rfid/log/(?P<rfid>[a-zA-Z0-9]+)/(?P<timestamp>[0-9]+)/(?P<device>[a-zA-Z0-9_-]+)/(?<action>.+)/(?<value>.+)', array(
    'methods' => 'GET',
    'callback' => 'log_rfid'
  ));

    register_rest_route('amt/v1', '/rfid/(?P<id>[a-zA-Z0-9]+)/user', array(
    'methods' => 'GET',
    'callback' => 'get_user_for_rfid'
  ));

    register_rest_route('amt/v1', '/vend/(?P<id>[a-zA-Z0-9]+)', array(
    'methods' => 'GET',
    'callback' => 'get_vendable_orders'
  ));

    register_rest_route('amt/v1', '/vend/view/(?P<order_id>[0-9]+)', array(
    'methods' => 'GET',
    'callback' => 'view_vend_order'
  ));

    register_rest_route('amt/v1', '/vend/disburse/(?P<order_id>[0-9]+)', array(
    'methods' => 'GET',
    'callback' => 'disburse_vendable_order'
  ));

    register_rest_route('amt/v1', '/vend/get_info/(?P<vend_bay>[a-zA-Z0-9]+)', array(
    'methods' => 'GET',
    'callback' => 'get_product_info'
  ));

    register_rest_route('amt/v1', '/user/count', array(
    'methods' => 'GET',
    'callback' => 'count_active_users'
  ));

    register_rest_route('amt/v1', '/user/info', array(
    'methods' => 'GET',
    'callback' => 'get_all_users_info'
  ));

    register_rest_route('amt/v1', '/user/expire', array(
    'methods' => 'GET',
    'callback' => 'set_user_end_date'
  ));

    register_rest_route('amt/v1', '/order/invoice', array(
    'methods' => 'GET',
    'callback' => 'send_customer_invoice'
  ));

    register_rest_route('amt/v1', '/expiration_schedule', array(
    'methods' => 'GET',
    'callback' => 'get_expiration_schedule'
  ));

    // uncomment to test the expire-all-users logic remotely and see results
    register_rest_route('amt/v1', '/user/expireALL', array(
     'methods' => 'GET',
     'callback' => 'expire_user_role'
 ));

    register_rest_route('amt/v1', '/docusign/complete', array(
    'methods' => 'POST',
    'callback' => 'docusign_complete'
  ));
});

// test this part by issuing a Publish command from the docusign connect interface
// note: we can get the POST data that is sent to this endpoint by looking at the logs in docusign
function docusign_complete($request)
{
    $request_body = $request->get_body();

    $xml = simplexml_load_string($request_body);
    $env = $xml->EnvelopeStatus;
    $status = (string) $env->Status;

    // check that user actually completed the form
    if ($status != 'Completed') {
        //print('Bad: ' . $status);
        return 'Ignoring request. Envelope not completed';
    }

    $custom_fields = $env->CustomFields->children();

    // figure out which power form they signed

    $user_id = null;
    $packet_name = null;
    $server_hostname = null;

    foreach ($custom_fields as $custom_field) {
        if ($custom_field->Name == 'user_id') {
            $user_id = intval($custom_field->Value);
        }
        if ($custom_field->Name == 'packet_name') {
            $packet_name = (string) $custom_field->Value;
        }
        if ($custom_field->Name == 'server_hostname') {
            $server_hostname = (string) $custom_field->Value;
        }
    }
    if ($_SERVER['HTTP_HOST'] != $server_hostname) {
        return 'Ignoring request. Envelope sent from ' . $server_hostname . ' but this server is ' . $_SERVER['HTTP_HOST'];
    }

    $completed_time = date_parse((string) $env->Completed);
    $completed_time_str = sprintf("%04d%02d%02d", $completed_time["year"], $completed_time["month"], $completed_time["day"]);

    global $membership_forms;
    //print_r($membership_forms);
    $forms_signed = $membership_forms[$packet_name];
    //print_r($forms_signed);
    // echo $packet_name . "\n";
    // echo "blah";

    foreach ($forms_signed as $form) {
        echo $form . "\n";
        update_user_meta($user_id, $form, $completed_time_str);
    }

    return true;
}

// Link action name to our function
add_action('amt_expire_user_role', 'expire_user_role', 10, 0);

// Schedule our action when theme is activated
function custom_amt_activation($oldname, $oldtheme=false)
{
    if (! wp_next_scheduled('amt_expire_user_role')) {
        wp_schedule_event(time(), 'hourly', 'amt_expire_user_role');
    }
}

// Link theme activation to our custom activation function
add_action("after_switch_theme", "custom_amt_activation", 10, 2);

// Remove scheduled action when theme deactivated
function custom_amt_deactivation($newname, $newtheme)
{
    wp_clear_scheduled_hook('amt_expire_user_role');
}

// Link theme deactivation to our custom deactivation function
add_action("switch_theme", "custom_amt_deactivation", 10, 2);

// Send toybot notification on new orders
function notify_new_order($order_id)
{
    $order = wc_get_order($order_id);
    $total = $order->get_formatted_order_total();
    @file_get_contents("http://acemonstertoys.org/member/neworder.php?amt=".urlencode($total));
}
add_action('woocommerce_order_status_completed', 'notify_new_order');


//Member Directory

if (! function_exists('skills_taxonomy')) {

// Register Custom Taxonomy
    function skills_taxonomy()
    {
        $labels = array(
    'name'                       => _x('Skills', 'Taxonomy General Name', 'text_domain'),
    'singular_name'              => _x('Skill', 'Taxonomy Singular Name', 'text_domain'),
    'menu_name'                  => __('Skills', 'text_domain'),
    'all_items'                  => __('All Items', 'text_domain'),
    'parent_item'                => __('Parent Item', 'text_domain'),
    'parent_item_colon'          => __('Parent Item:', 'text_domain'),
    'new_item_name'              => __('New Item Name', 'text_domain'),
    'add_new_item'               => __('Add New Item', 'text_domain'),
    'edit_item'                  => __('Edit Item', 'text_domain'),
    'update_item'                => __('Update Item', 'text_domain'),
    'view_item'                  => __('View Item', 'text_domain'),
    'separate_items_with_commas' => __('Separate items with commas', 'text_domain'),
    'add_or_remove_items'        => __('Add or remove items', 'text_domain'),
    'choose_from_most_used'      => __('Choose from the most used', 'text_domain'),
    'popular_items'              => __('Popular Items', 'text_domain'),
    'search_items'               => __('Search Items', 'text_domain'),
    'not_found'                  => __('Not Found', 'text_domain'),
    'no_terms'                   => __('No items', 'text_domain'),
    'items_list'                 => __('Items list', 'text_domain'),
    'items_list_navigation'      => __('Items list navigation', 'text_domain'),
  );
        $args = array(
    'labels'                     => $labels,
    'hierarchical'               => false,
    'public'                     => true,
    'show_ui'                    => true,
    'show_admin_column'          => true,
    'show_in_nav_menus'          => true,
    'show_tagcloud'              => true,
  );
        register_taxonomy('skills', array( 'amt_profile' ), $args);
    }
    add_action('init', 'skills_taxonomy', 0);
}

// Adding a custom post type

if (! function_exists('amt_profile_post_type')) {

// Register Custom Post Type
    function amt_profile_post_type()
    {
        $labels = array(
    'name'                  => _x('AMT Profiles', 'Post Type General Name', 'text_domain'),
    'singular_name'         => _x('AMT Profile', 'Post Type Singular Name', 'text_domain'),
    'menu_name'             => __('AMT Profiles', 'text_domain'),
    'name_admin_bar'        => __('AMT Profile', 'text_domain'),
    'archives'              => __('AMT Profile Archives', 'text_domain'),
    'parent_item_colon'     => __('Parent Item:', 'text_domain'),
    'all_items'             => __('All AMT Profiles', 'text_domain'),
    'add_new_item'          => __('Add New AMT Profiles', 'text_domain'),
    'add_new'               => __('Add New', 'text_domain'),
    'new_item'              => __('New Item', 'text_domain'),
    'edit_item'             => __('Edit Item', 'text_domain'),
    'update_item'           => __('Update Item', 'text_domain'),
    'view_item'             => __('View Item', 'text_domain'),
    'search_items'          => __('Search Item', 'text_domain'),
    'not_found'             => __('Not found', 'text_domain'),
    'not_found_in_trash'    => __('Not found in Trash', 'text_domain'),
    'featured_image'        => __('Featured Image', 'text_domain'),
    'set_featured_image'    => __('Set featured image', 'text_domain'),
    'remove_featured_image' => __('Remove featured image', 'text_domain'),
    'use_featured_image'    => __('Use as featured image', 'text_domain'),
    'insert_into_item'      => __('Insert into item', 'text_domain'),
    'uploaded_to_this_item' => __('Uploaded to this item', 'text_domain'),
    'items_list'            => __('Items list', 'text_domain'),
    'items_list_navigation' => __('Items list navigation', 'text_domain'),
    'filter_items_list'     => __('Filter items list', 'text_domain'),
  );
        $args = array(
    'label'                 => __('AMT Profile', 'text_domain'),
    'description'           => __('AMT Profiles for members', 'text_domain'),
    'labels'                => $labels,
    'supports'              => array( 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'comments', 'revisions', 'page-attributes', ),
    'taxonomies'            => array( 'skills' ),
    'hierarchical'          => false,
    'public'                => true,
    'show_ui'               => true,
    'show_in_menu'          => true,
    'menu_position'         => 5,
    'menu_icon'             => 'dashicons-palmtree',
    'show_in_admin_bar'     => true,
    'show_in_nav_menus'     => true,
    'can_export'            => true,
    'has_archive'           => true,
    'exclude_from_search'   => true,
    'publicly_queryable'    => true,
    'capability_type'       => 'post',
  );
        register_post_type('amt_profile', $args);
    }
    add_action('init', 'amt_profile_post_type', 0);
}


function get_amt_profile_page_link($user)
{
    $args=array(
  'post_type' => 'amt_profile',
  'post_status' => 'any',
  'author' => $user->ID,
  'posts_per_page' => -1
);


    $amt_profile_link = 'profile_not_found';
    $my_query = null;
    $my_query = new WP_Query($args);
    if ($my_query->have_posts()) {
        $amt_profile_posts = $my_query->get_posts();

        foreach ($amt_profile_posts as $post) {
            $amt_profile_link = get_permalink($post);
            break;
        }
    }
    wp_reset_query();

    return $amt_profile_link;
}

// extending author abilities for skills tags


function add_theme_caps()
{
    // gets the author role
    $role = get_role('author');

    // This only works, because it accesses the class instance.
    // would allow the author to edit others' posts for current theme only
    $role->add_cap('manage_categories');
}
add_action('admin_init', 'add_theme_caps');


/* -------------------------------------------------------------
 * - Create AMT Profile When Adding A New User -
 * ------------------------------------------------------------- */

// Execute theme_create_amt_profile function when a new user is registered
add_action('user_register', 'theme_create_amt_profile');

function theme_create_amt_profile($user_id)
{
    // Get the info of the new user
    $user_info = get_userdata($user_id);

    // Get the First Name + Last Name of the New User
    // If this info is not already on get_userdata, get it from the _POST info
    $name = ($user_info->first_name)
		? $user_info->first_name . ' ' . substr( $user_info->last_name, 0, 1 )
		: $_POST['billing_first_name'] . ' ' . substr( $_POST['billing_last_name'], 0, 1 );

    // Insert new AMT Profile entry
    wp_insert_post(array(
    'post_author' => $user_id,
    'post_title' => $name,
    'post_status' => 'publish',
    'post_type' => 'amt_profile',
  ));
}

/* -------------------------------------------------------------
 * - Hide profile fields for everybody but adims -
 * ------------------------------------------------------------- */

add_action('admin_enqueue_scripts', 'theme_hide_profile_fields');

function theme_hide_profile_fields()
{
    if (! current_user_can('administrator')) {
        ?>
    <style type="text/css">
    .user-rich-editing-wrap,
    .user-admin-color-wrap,
    .user-comment-shortcuts-wrap,
    .show-admin-bar.user-admin-bar-front-wrap,
    .user-url-wrap,
    .user-profile-picture{
      display: none;
    }
    </style>
<?php
    }
}

/* -------------------------------------------------------------
 * - Abandoned Orders -
 * ------------------------------------------------------------- */
function your_function($user_login, $user)
{
    delete_user_meta($user->ID, '_woocommerce_abandoned_orders_last_reminder');
}
add_action('wp_login', 'your_function', 10, 2);

add_action('init', 'theme_abandoned_orders_init');

function theme_abandoned_orders_init()
{
    if (is_user_logged_in()) {
        $pending_orders = get_posts(array(
       'posts_per_page' => -1,
       'meta_key'    => '_customer_user',
       'meta_value'  => get_current_user_id(),
       'post_type'   => 'shop_order',
       'post_status' => 'wc-pending',
    ));

        if (count($pending_orders) > 0) {
            $last_reminder = get_user_meta(get_current_user_id(), '_woocommerce_abandoned_orders_last_reminder', true);
            if (! $last_reminder) {
                $last_reminder = 0;
            }

            $last_reminder = $last_reminder + (60 * 60 * 24 * 1);

            if (time() > $last_reminder) {
                theme_abandoned_cart_init();
            }
        }
    }
}

/* -------------------------------------------------------------
 * - Vending Product Check - Sets item as ready 'to-vend'
 * ------------------------------------------------------------- */
add_action('woocommerce_order_status_completed', 'vend_filter');

function vend_filter($order_id)
{
    //
    // Built current list of vending items
    //
    $vend_items = array();
    $the_query = new WP_Query(array( 'product_cat' => 'vend-item' ));
    while ($the_query->have_posts()) {
        $the_query->the_post();
        array_push($vend_items, get_the_ID());
    }
    //
    // Check items on order
    //
    $order = wc_get_order($order_id);


    foreach ($order->get_items() as $item_id => $item_data) {
        // error_log($item_data['product_id']);
        if (in_array($item_data['product_id'], $vend_items)) {
            $order->update_status('to-vend');
            break;
        } else {
            // error_log("passed");
        }
    }
}




function theme_abandoned_cart_init()
{
    add_action('wp_enqueue_scripts', 'theme_abandoned_cart_enqueue_scripts');
    add_action('wp_footer', 'theme_abandoned_cart_print_html');
}

function theme_abandoned_cart_enqueue_scripts()
{
    wp_enqueue_script('lity', get_stylesheet_directory_uri() . '/libraries/lity/lity.min.js', array( 'jquery' ), null, true);
    wp_enqueue_style('lity', get_stylesheet_directory_uri() . '/libraries/lity/lity.min.css', array(), null);
}

function theme_abandoned_cart_print_html()
{
    $current_user = wp_get_current_user(); ?>
  <div id="abandoned-cart-reminder" style="background:#fff" class="lity-hide">
    <div id="cart-reminder">
      <h2 id="cart-reminder-title">Hello <span><?php echo $current_user->display_name; ?></span>!</h2>
      <p>We've notice you have an unpaid bill.</p>
      <p>Please <a href="/my-account/orders/">go to your account</a> to complete your order(s).</p>
    </div>
  </div>
  <script type="text/javascript">
  jQuery(document).ready(function($){
    var lightbox = lity( '#abandoned-cart-reminder' );
  });
  </script>
<?php
  update_user_meta(get_current_user_id(), '_woocommerce_abandoned_orders_last_reminder', time());
}

add_action( 'wp_enqueue_scripts', function(){
		wp_deregister_style( 'font-awesome' );
		wp_register_style(
			'font-awesome',
			get_stylesheet_directory_uri() . '/css/libs/font-awesome/css/font-awesome.min.css',
			array(),
			'5.0.13'
		);
}, 20 );

function filter_wc_stripe_payment_metadata( $metadata, $order, $source ) {		
    $count = 1;
	foreach( $order->get_items() as $item_id => $line_item ){
		$item_data = $line_item->get_data();
		$product = $line_item->get_product();
		$product_name = $product->get_name();
		$item_quantity = $line_item->get_quantity();
		$item_total = $line_item->get_total();
		$metadata['Line Item '.$count] = 'Product name: '.$product_name.' | Quantity: '.$item_quantity.' | Item total: '. number_format( $item_total, 2 );
		$count += 1;
	}

	return $metadata;
}

add_filter( 'wc_stripe_payment_metadata', 'filter_wc_stripe_payment_metadata', 10, 3 );
