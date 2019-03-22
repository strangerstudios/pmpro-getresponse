<?php
/*
Plugin Name: Paid Memberships Pro - GetResponse Add On
Plugin URI: http://www.paidmembershipspro.com/pmpro-getresponse/
Description: Sync your WordPress users and members with GetResponse campaigns.
Version: .4.3
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/
/*
	Copyright 2011	Stranger Studios	(email : jason@strangerstudios.com)
	GPLv2 Full license details in license.txt
*/

/*	
	If PMPro is not installed:
	- New users should be subscribed to these campaigns: [ ]
	- Remove members from campaign when they unsubscribe/delete their account? [ ]
	
	If PMPro is installed:
	* All new users should be subscribed to these campaigns:
	* New users with no membership should be subscribed to these campaigns:
	* New users with membership # should be subscribed to these campaigns: 
	* (Show each level)		
	
	* Provide export for initial import?
*/

//init
function pmprogr_init() {
	//include GetResponse Class if we don't have it already
	if ( ! class_exists( "GetResponse" ) ) {
		require_once( dirname( __FILE__ ) . "/includes/GetResponseAPI.class.php" );
	}

	//get options for below
	$options = get_option( "pmprogr_options" );

	//setup hooks for new users	
	if ( ! empty( $options['users_campaigns'] ) ) {
		add_action( "user_register", "pmprogr_user_register" );
	}

	//setup hooks for PMPro levels
	pmprogr_getPMProLevels();
	global $pmprogr_levels;

	if ( ! empty( $pmprogr_levels ) ) {
		add_action( "pmpro_after_change_membership_level", "pmprogr_pmpro_after_change_membership_level", 15, 2 );
	}

	// load the text domain for plugin.
	load_plugin_textdomain( 'pmpro-getresponse', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

add_action( "init", "pmprogr_init", 0 );

//use a different action if we are on the checkout page
function pmprogr_wp() {
	if ( is_admin() ) {
		return;
	}

	global $post;
	if ( ! empty( $post->post_content ) && strpos( $post->post_content, "[pmpro_checkout]" ) !== false ) {
		remove_action( "pmpro_after_change_membership_level", "pmprogr_pmpro_after_change_membership_level" );
		add_action( "pmpro_after_checkout", "pmprogr_pmpro_after_checkout", 15, 2 );

	}
}

add_action( "wp", "pmprogr_wp", 0 );

//for when checking out
function pmprogr_pmpro_after_checkout( $user_id, $order ) {

	if ( !isset( $order->membership_id )  )  {
		$level_id = isset( $_REQUEST['level'] ) ? intval( $_REQUEST['level'] ) : null;
	} else {
		$level_id = $order->membership_id;
	}

	if ( ! empty( $level_id ) ) {
		pmprogr_pmpro_after_change_membership_level( $level_id, $user_id );
	}
}

//subscribe users when they register
function pmprogr_user_register( $user_id ) {
	clean_user_cache( $user_id );

	$options = get_option( "pmprogr_options" );

	//should we add them to any campaigns?
	if ( ! empty( $options['users_campaigns'] ) && ! empty( $options['api_key'] ) ) {
		//get user info
		$campaign_user = get_userdata( $user_id );

		//subscribe to each campaign
		$api = new GetResponse( $options['api_key'], false );
		foreach ( $options['users_campaigns'] as $campaign ) {
			//subscribe them
			pmprogr_subscribe( $campaign_user, $campaign );
		}
	}
}

//subscribe new members (PMPro) when they register
function pmprogr_pmpro_after_change_membership_level( $level_id, $user_id ) {
	clean_user_cache( $user_id );

	global $pmprogr_levels;
	$options       = get_option( "pmprogr_options" );
	$all_campaigns = get_option( "pmprogr_all_campaigns" );
	$campaign_user = get_userdata( $user_id );
	
	// Make sure we have a user to update.
	if( empty( $campaign_user) ) {
		return false;
	}

	$subscribe_to   = !empty( $options["level_{$level_id}_campaigns"] ) ? $options["level_{$level_id}_campaigns"] : array();
	$user_campaigns = !empty( $options['users_campaigns'] ) ? $options['users_campaigns'] : array();

	//combine level campaigns with all users campaigns
	if ( ! empty( $user_campaigns ) ) {
		$subscribe_to = array_unique( array_merge( $subscribe_to, $user_campaigns ) );
	}

	$api = new GetResponse( $options['api_key'], false );

	//should we add them to any campaigns?
	if ( ! empty( $options["level_{$level_id}_campaigns"] ) && ! empty( $options['api_key'] ) ) {

		// is 'unsubscribe' option false ?
		if ( empty( $options['unsubscribe'] ) ) {
			foreach ( $subscribe_to as $campaign ) {
				pmprogr_subscribe( $campaign_user, $campaign );
			}
		}

		if ( $options['unsubscribe'] == "all" ) {
			//delete contact which will remove their subscription from all campaigns
			$campaign_contacts = $api->getContactsByEmail( $campaign_user->user_email );
			if ( ! empty( $campaign_contacts ) ) {
				foreach ( $campaign_contacts as $contact_id => $campaign_contact ) {
					$response = $api->deleteContact( $contact_id );
				}
			}

			foreach ( $subscribe_to as $campaign ) {
				pmprogr_subscribe( $campaign_user, $campaign );
			}
		} else if ( ! empty( $options['unsubscribe'] ) && 'all' !== $options['unsubscribe'] ) {
			//Get their last level, last entry or second to last if they are changing levels
			global $wpdb;

			if ( ! empty( $level_id ) ) {
				$last_level = $wpdb->get_results(
					$wpdb->prepare( "
						SELECT *
						FROM {$wpdb->pmpro_memberships_users}
						WHERE `user_id` = %d
						ORDER BY `id` DESC
						LIMIT 1,1",
						$user_id
					)
				);
			} else {
				$last_level = $wpdb->get_results(
					$wpdb->prepare( "
						SELECT *
						FROM {$wpdb->pmpro_memberships_users}
						WHERE `user_id` = %d
						ORDER BY `id` DESC LIMIT 1",
						$user_id
					)
				);
			}

			if ( !empty($last_level) ) {
				$last_level_id = $last_level[0]->membership_id;
				if ( ! empty( $options["level_{$last_level_id}_campaigns"] ) ) {
					$unsubscribe_lists = $options["level_{$last_level_id}_campaigns"];
				} else {
					$unsubscribe_lists = array();
				}

				//delete contact only from old campaigns to unsubscribe.
				$campaign_contacts = $api->getContactsByEmail( $campaign_user->user_email, $unsubscribe_lists );
				if ( ! empty( $campaign_contacts ) ) {
					foreach ( $campaign_contacts as $contact_id => $campaign_contact ) {
						$response = $api->deleteContact( $contact_id );
					}
				}
			}

			//subscribe to each campaign
			foreach ( $subscribe_to as $campaign ) {
				pmprogr_subscribe( $campaign_user, $campaign );
			}
		}
	} else if ( ! empty( $options['api_key'] ) && count( $options ) > 3 ) {
		//now they are a normal user should we add them to any campaigns?
		if ( ! empty( $options['users_campaigns'] ) && ! empty( $options['api_key'] ) ) {
			//delete contact to unsubscribe them from all campaigns
			$campaign_contacts = $api->getContactsByEmail( $campaign_user->user_email );
			if ( ! empty( $campaign_contacts ) ) {
				foreach ( $campaign_contacts as $contact_id => $campaign_contact ) {
					$api->deleteContact( $contact_id );
				}
			}

			//subscribe to each campaign
			foreach ( $options['users_campaigns'] as $campaign ) {
				pmprogr_subscribe( $campaign_user, $campaign );
			}
		} else {
			//some memberships are on campaigns. assuming the admin intends this level to be unsubscribed from everything
			if ( is_array( $all_campaigns ) ) {
				//delete contact to unsubscribe them from all campaigns
				$campaign_contacts = $api->getContactsByEmail( $campaign_user->user_email );
				if ( ! empty( $campaign_contacts ) ) {
					foreach ( $campaign_contacts as $contact_id => $campaign_contact ) {
						$api->deleteContact( $contact_id );
					}
				}
			}
		}
	}
}

function pmprogr_subscribe( $campaign_user, $campaign ) {
	//echo "<hr />Trying to subscribe to " . $campaign . "...";
	$options = get_option( "pmprogr_options" );

	$api = new GetResponse( $options['api_key'], false );

	if ( ! empty( $campaign_user->first_name ) && ! empty( $campaign_user->last_name ) ) {
		$name = trim( $campaign_user->first_name . " " . $campaign_user->last_name );
	} else {
		$name = $campaign_user->display_name;
	}

	$response = $api->addContact( $campaign, $name, $campaign_user->user_email, 'standard', 0, apply_filters( "pmpro_getresponse_custom_fields", array(), $campaign_user ) );
}

//admin init. registers settings
function pmprogr_admin_init() {
	//setup settings
	register_setting( 'pmprogr_options', 'pmprogr_options', 'pmprogr_options_validate' );
	add_settings_section( 'pmprogr_section_general', __('General Settings', "pmpro-getresponse"), 'pmprogr_section_general', 'pmprogr_options' );
	add_settings_field( 'pmprogr_option_api_key', __('GetResponse API Key', "pmpro-getresponse"), 'pmprogr_option_api_key', 'pmprogr_options', 'pmprogr_section_general' );
	add_settings_field( 'pmprogr_option_users_campaigns', __('All Users Campaign', "pmpro-getresponse"), 'pmprogr_option_users_campaigns', 'pmprogr_options', 'pmprogr_section_general' );
	//add_settings_field('pmprogr_option_double_opt_in', __('Require Double Opt-in?', "pmpro-getresponse"), 'pmprogr_option_double_opt_in', 'pmprogr_options', 'pmprogr_section_general');
	add_settings_field( 'pmprogr_option_unsubscribe', __('Unsubscribe on Level Change?', "pmpro-getresponse"), 'pmprogr_option_unsubscribe', 'pmprogr_options', 'pmprogr_section_general' );

	//pmpro-related options	
	add_settings_section( 'pmprogr_section_levels', __('Membership Levels and Campaigns', "pmpro-getresponse"), 'pmprogr_section_levels', 'pmprogr_options' );

	//add options for levels
	pmprogr_getPMProLevels();
	global $pmprogr_levels;
	if ( ! empty( $pmprogr_levels ) ) {
		foreach ( $pmprogr_levels as $level ) {
			add_settings_field( 'pmprogr_option_memberships_campaigns_' . $level->id, $level->name, 'pmprogr_option_memberships_campaigns', 'pmprogr_options', 'pmprogr_section_levels', array( $level ) );
		}
	}
}

add_action( "admin_init", "pmprogr_admin_init" );

//set the pmprogr_levels array if PMPro is installed
function pmprogr_getPMProLevels() {
	global $pmprogr_levels, $wpdb;
	
	if ( ! empty( $wpdb->pmpro_membership_levels ) )  {
		$pmprogr_levels = $wpdb->get_results( "SELECT * FROM $wpdb->pmpro_membership_levels ORDER BY id" );
	} else {
		return false;
	}
	
	return $pmprogr_levels;
}

//options sections
function pmprogr_section_general() {
	?>
	<p></p>
	<?php
}

//options sections
function pmprogr_section_levels() {
	global $wpdb, $pmprogr_levels;

	//do we have PMPro installed?
	if ( defined( "PMPRO_VERSION" ) ) {
		?>
		<p><?php _e( 'Paid Memberships Pro is installed.', 'paid-memberships-pro' ); ?></p>
		<?php
		//do we have levels?
		if ( empty( $pmprogr_levels ) ) {
			?>
			<p><?php _e( sprintf( "Once you've %s, you will be able to assign GetResponse campaigns to them here.", "<a href='admin.php?page=pmpro-membershiplevels'>" . __( 'created some levels in Paid Memberships Pro', 'pmpro-getresponse' ) . "</a>" ), 'pmpro-getresponse' ); ?></p>
			<?php
		} else {
			?>
			<p><?php _e("For each level below, choose the campaigns which should be subscribed to when a new user registers.", "pmpro-getresponse");?></p>
			<?php
		}
	} else {
		//just deactivated or needs to be installed?
		if ( file_exists( dirname( __FILE__ ) . "/../paid-memberships-pro/paid-memberships-pro.php" ) ) {
			//just deactivated
			?>
			<p><?php _e( sprintf( "%s to add membership functionality to your site and finer control over your GetResponse campaigns.", "<a href='plugins.php?plugin_status=inactive'>" . __( 'Activate Paid Memberships Pro', 'pmpro-getresponse' ) . "</a>" ), "pmpro-getresponse"); ?></p>
			<?php
		} else {
			//needs to be installed
			?>
			<p>
				<?php _e( sprintf( "%s to add membership functionality to your site and finer control over your GetResponse campaigns.", "<a href='plugin-install.php?tab=search&type=term&s=paid+memberships+pro&plugin-search-input=Search+Plugins'>" . __( "Install Paid Memberships Pro", "pmpro-getresponse") . "</a>" ), "pmpro-getresponse" )?></p>
			<?php
		}
	}
}


//options code
function pmprogr_option_api_key() {
	$options = get_option( 'pmprogr_options' );
	if ( isset( $options['api_key'] ) ) {
		$api_key = $options['api_key'];
	} else {
		$api_key = "";
	} ?>
	<input id="pmprogr_api_key" name="pmprogr_options[api_key]" size="80" type="text"
	       value="<?php echo esc_attr( $api_key ); ?>"/>
	<?php
}

function pmprogr_option_users_campaigns() {
	global $pmprogr_campaigns;
	$options = get_option( 'pmprogr_options' );

	if ( !empty( $options['users_campaigns'] ) && is_array( $options['users_campaigns'] ) ) {
		$selected_campaigns = $options['users_campaigns'];
	} else {
		$selected_campaigns = array();
	}

	if ( ! empty( $pmprogr_campaigns ) ) { ?>
		<select multiple="multiple" name="pmprogr_options[users_campaigns][]">
		<?php

		foreach ( $pmprogr_campaigns as $key => $campaign ) { ?>
			<option
				value="<?php echo esc_attr( $key ) ?>" <?php echo( in_array( $key, $selected_campaigns ) ? 'selected="selected"' : null ) ?>><?php echo esc_attr( $campaign->name ); ?></option>
			<?php
		} ?>
		</select><?php
	} else {
		_e( "No campaigns found.", "pmpro-getresponse" );
	}
}

function pmprogr_option_double_opt_in() {
	$options = get_option( 'pmprogr_options' );
	?>
	<select name="pmprogr_options[double_opt_in]">
		<option value="0" <?php selected( $options['double_opt_in'], 0 ); ?>>No</option>
		<option value="1" <?php selected( $options['double_opt_in'], 1 ); ?>>Yes</option>
	</select>
	<?php
}

function pmprogr_option_unsubscribe() {
	$options = get_option( 'pmprogr_options' );
	?>
	<select name="pmprogr_options[unsubscribe]">
		<option value="0" <?php selected( $options['unsubscribe'], 0 ); ?>>No</option>
		<option value="1" <?php selected( $options['unsubscribe'], 1 ); ?>>Yes (Only old level lists.)</option>
		<option value="all" <?php selected( $options['unsubscribe'], "all" ); ?>>Yes (All other lists.)</option>
	</select>
	<small><?php _e("Recommended: Yes. However, if you manage multiple lists in GetResponse and have users subscribe outside of WordPress, you may want to choose No so contacts aren't unsubscribed from other lists when they register on your site.", "pmpro-getresponse"); ?>
	</small>
	<?php
}

function pmprogr_option_memberships_campaigns( $level ) {
	global $pmprogr_campaigns;
	$options = get_option( 'pmprogr_options' );

	$level              = $level[0];    //WP stores this in the first element of an array
	$selected_campaigns = array();

	if ( isset( $options["level_{$level->id}_campaigns"] ) && is_array( $options["level_{$level->id}_campaigns"] ) ) {
		$selected_campaigns = $options["level_{$level->id}_campaigns"];
	}

	if ( ! empty( $pmprogr_campaigns ) ) { ?>
		<select multiple="multiple" name="pmprogr_options[level_<?php echo $level->id; ?>_campaigns][]"><?php
		foreach ( $pmprogr_campaigns as $key => $campaign ) { ?>
			<option
				value="<?php echo esc_attr( $key ); ?>" <?php echo( in_array( $key, $selected_campaigns ) ? 'selected="selected"' : null ); ?>><?php echo esc_attr( $campaign->name ); ?></option> <?php
		} ?>
		</select><?php
	} else {
		_e( "No campaigns found.", "pmpro-getresponse" );
	}
}

// validate our options
function pmprogr_options_validate( $input ) {
	//api key
	if ( ! empty( $input['api_key'] ) ) {
		$newinput['api_key'] = trim( preg_replace( "[^a-zA-Z0-9\-]", "", $input['api_key'] ) );
	} else {
		$newinput['api_key'] = "";
	}

	if ( ! empty( $input['double_opt_in'] ) ) {
		$newinput['double_opt_in'] = intval( $input['double_opt_in'] );
	} else {
		$newinput['double_opt_in'] = 0;
	}

	if ( ! empty( $input['unsubscribe'] ) ) {
		$newinput['unsubscribe'] = preg_replace( "[^a-zA-Z0-9\-]", "", $input['unsubscribe'] );
	} else {
		$newinput['unsubscribe'] = "";
	}

	//user campaigns
	if ( ! empty( $input['users_campaigns'] ) && is_array( $input['users_campaigns'] ) ) {
		$count = count( $input['users_campaigns'] );
		for ( $i = 0; $i < $count; $i ++ ) {
			$newinput['users_campaigns'][] = trim( preg_replace( "[^a-zA-Z0-9\-]", "", $input['users_campaigns'][ $i ] ) );
		};
	}

	//membership campaigns
	global $pmprogr_levels;
	if ( ! empty( $pmprogr_levels ) ) {
		foreach ( $pmprogr_levels as $level ) {
			if ( ! empty( $input["level_{$level->id}_campaigns"] ) && is_array( $input["level_{$level->id}_campaigns"] ) ) {
				$count = count( $input["level_{$level->id}_campaigns"] );
				for ( $i = 0; $i < $count; $i ++ ) {
					$newinput["level_{$level->id}_campaigns"][] = trim( preg_replace( "[^a-zA-Z0-9\-]", "", $input["level_{$level->id}_campaigns"][ $i ] ) );
				};
			}
		}
	}

	return $newinput;
}

// add the admin options page	
function pmprogr_admin_add_page() {
	add_options_page( 'PMPro GetResponse Options', 'PMPro GetResponse', 'manage_options', 'pmprogr_options', 'pmprogr_options_page' );
}

add_action( 'admin_menu', 'pmprogr_admin_add_page' );

//html for options page
function pmprogr_options_page() {
	global $pmprogr_campaigns;

	//check for a valid API key and get campaigns
	$options = get_option( "pmprogr_options" );
	$api_key = $options['api_key'];

	if ( empty( $options ) ) {
		$options = array( "unsubscribe" => 1 );
		update_option( "pmprogr_options", $options );
	} elseif ( ! isset( $options['unsubscribe'] ) ) {
		$options['unsubscribe'] = 1;
		update_option( "pmprogr_options", $options );
	}

	if ( ! empty( $api_key ) ) {
		/** Ping the GetResponse API to make sure this API Key is valid */
		$api  = new GetResponse( $api_key, false );
		$ping = $api->ping();

		if ( empty( $ping ) ) {
			/** Looks like there was an error */
			$msg  = __( 'Sorry, but GetResponse was unable to verify your API key. Please try entering your API key again.', 'pmpro-getresponse' );
			$msgt = "error";
			add_settings_error( 'pmpro-GetResponse', 'apikey-fail', $msg, $msgt );
		} else {
			//get campaigns
			$pmprogr_campaigns = $api->getCampaigns();
			$all_campaigns     = array();

			//save all campaigns in an option
			$i = 0;
			foreach ( $pmprogr_campaigns as $key => $campaign ) {
				$all_campaigns[ $i ]['id']   = $key;
				$all_campaigns[ $i ]['name'] = $campaign->name;
				$i ++;
			}

			/** Save all of our new data */
			update_option( "pmprogr_all_campaigns", $all_campaigns );
		}
	}
	?>
	<div class="wrap">
		<div id="icon-options-general" class="icon32"><br></div>
		<h2>PMPro GetResponse Integration Options</h2>

		<?php if ( ! empty( $msg ) ) { ?>
			<div class="message <?php echo $msgt; ?>"><p><?php echo $msg; ?></p></div>
		<?php } ?>

		<form action="options.php" method="post">

			<p><?php _e( "This plugin will integrate your site with GetResponse. You can choose one or more GetResponse campaigns to have users subscribed to when they signup for your site.", "pmpro-getresponse" ); ?></p>
			<p><?php _e( sprintf( "If you have %s installed, you can also choose one or more GetResponse campaigns to have members subscribed to for each membership level.", "<a href='http://www.paidmembershipspro.com'>" . __( 'Paid Memberships Pro', 'pmpro-getresponse') . "</a>" ), "pmpro-getresponse" ); ?></p>
			<p><?php _e( sprintf( "Don't have a GetResponse account? %s", "<a href='http://www.getresponse.com/' target='_blank'>" . __( 'Get one here.', 'pmpro-getresponse' ) . "</a>" ), "pmpro-getresponse");?></p>

			<?php settings_fields( 'pmprogr_options' ); ?>
			<?php do_settings_sections( 'pmprogr_options' ); ?>

			<p><br/></p>

			<div class="bottom-buttons">
				<input type="hidden" name="pmprot_options[set]" value="1"/>
				<input type="submit" name="submit" class="button-primary"
				       value="<?php _e('Save Settings', "pmpro-getresponse" ); ?>">
			</div>

		</form>
	</div>
	<?php
}

/*
Function to add links to the plugin action links
*/
function pmprogr_add_action_links( $links ) {

	$new_links = array(
		'<a href="' . get_admin_url( null, 'options-general.php?page=pmprogr_options' ) . '">' . __( "Settings", "pmpro-getresponse" ) . '</a>',
	);

	return array_merge( $new_links, $links );
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'pmprogr_add_action_links' );

/*
Function to add links to the plugin row meta
*/
function pmprogr_plugin_row_meta( $links, $file ) {
	if ( strpos( $file, 'pmpro-getresponse.php' ) !== false ) {
		$new_links = array(
			'<a href="' . esc_url( 'http://www.paidmembershipspro.com/add-ons/third-party-integration/pmpro-getresponse/' ) . '" title="' . esc_attr__( 'View Documentation', 'pmpro-getresponse' ) . '">' . __( 'Docs', 'pmpro-getresponse' ) . '</a>',
			'<a href="' . esc_url( 'http://paidmembershipspro.com/support/' ) . '" title="' . esc_attr__( 'Visit Customer Support Forum', 'pmpro-getresponse' ) . '">' . __( 'Support', 'pmpro-getresponse' ) . '</a>',
		);
		$links     = array_merge( $links, $new_links );
	}

	return $links;
}

add_filter( 'plugin_row_meta', 'pmprogr_plugin_row_meta', 10, 2 );
