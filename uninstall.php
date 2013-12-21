<?php
if( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) 
	exit();
	
// limit user data returned to just the id
$users = get_users( array( 'fields' => 'ID' ) );

// loop through each user
foreach( $users as $user )
{
	// delete the custom user meta in the wp_usermeta table
	delete_user_meta( $user, 'uae_user_activation_code' );
}