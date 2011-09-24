<?php

/**
 *	Plugin Name: User Activation Email
 *	Plugin URI: https://github.com/NateJacobs/User-Activation-Email
 *	Description: Add an activation code to the new user email sent once a user registers. The user must enter this activation code in addition to a username and password to log in successfully the first time.
 *	Version: 0.1
 *	License: GPL V2
 *	Author: Nate Jacobs <nate@natejacobs.org>
 *	Author URI: http://natejacobs.org
 */

class UserActivationEmail
{
	CONST user_meta = "uae_user_activation_code";
	CONST error_message = "<strong>ERROR</strong>: Sorry, that activation code does not match. Please try again. You can find the activation code in your welcome email.";
	
	// hook into actions and filters
	public function __construct()
	{
		add_filter( 'authenticate', array( __CLASS__, 'check_user_activation_code' ), 10, 3 );
		add_action( 'login_form', array( __CLASS__, 'add_login_field' ) );
		add_action( 'user_register', array( __CLASS__, 'add_activation_code' ) );
		add_action( 'wp_login', array( __CLASS__, 'update_activation_code' ) );
	}
	
	/** 
	 *	Check Activation Code
	 *
	 *	Compares the user entered activation code with the code created upon registration.
	 *	If the activation code is the same allow access. If the user is already activated,
	 *	open the gates.
	 *
	 *	@author		Nate Jacobs
	 *	@since		0.1
	 */
	public function check_user_activation_code( $user, $user_login, $password )
	{
		// get user data by login
		$user = get_userdatabylogin( $user_login );
		
		// if the user has entered something in the user name box
		if ( $user_login )
		{
			// get the custom user meta defined during registration
			$activation_code = get_user_meta( $user->ID, self::user_meta, true );
		}
		if ( empty( $user_login ) || empty($password) )
		{
			if ( empty($username) )
				$user = new WP_Error( 'empty_username', __( '<strong>ERROR</strong>: The username field is empty.' ) );
	
			if ( empty($password) )
				$user = new WP_Error( 'empty_password', __( '<strong>ERROR</strong>: The password field is empty.' ) );
		}
		else
		{
			if ( $activation_code == 'active' )
			{
				return $user;
				exit;
			}
			// if the activation code entered by the user is not identical to the activation code
			// stored in the *_usermeta table then deny access
			if ( $_POST['activation-code'] !== $activation_code )
			{
				// register a new error with the error message set above
				$user = new WP_Error( 'access_denied', __( self::error_message ) );
				// deny access to login and send back to login page
				remove_action( 'authenticate', 'wp_authenticate_username_password', 20 );
			}
		}	
		return $user;
	}
	
	/** 
	 *	Update Activation Code
	 *
	 *	Once a user successfully logs in, updates the activation code meta to read 'active'.
	 *	This allows the check_user_activation_code to bypass code matching if set to 'active'.
	 *
	 *	@author		Nate Jacobs
	 *	@since		0.1
	 */
	public function update_activation_code( $user_login )
	{
		// get user data by login
		$user = get_userdatabylogin( $user_login );
		// change the custom user meta to show the user has already activated
		update_user_meta( $user->ID, self::user_meta, 'active' );
	}
	
	/** 
	 *	Add Login Field
	 *
	 *	Adds a login field to the login form.
	 *
	 *	@author		Nate Jacobs
	 *	@since		0.1
	 */
	public function add_login_field()
	{
		?>
		<p>
			<label for="activation-code"><?php echo __( 'Activation Code (New User Only)' ); ?><br>
				<input type="text" id="activation-code" class="input" name="activation-code" tabindex="20" value="<?php if( isset( $_POST['activation-code'] ) ) echo $_POST['activation-code']; ?>">
			</label>
		</p>
		<?php
	}
	
	/** 
	 *	Generate the Activation Code
	 *
	 *	Helper function that creates a random activation code.
	 *	http://paulmason.name/blog/item/unique-random-alphanumeric-string-generator-in-php
	 *
	 *	@author		Nate Jacobs
	 *	@since		0.1
	 */
	private function createRandomString( $string_length, $character_set ) 
	{
	  $random_string = array();
	  for ( $i = 1; $i <= $string_length; $i++ ) 
	  {
	    $rand_character = $character_set[rand(0, strlen( $character_set ) - 1)];
	    $random_string[] = $rand_character;
	  }
	  shuffle( $random_string );
	  return implode( '', $random_string );
	}
	
	/** 
	 *	Add Activation Code
	 *
	 *	Generates the random activation code and adds it to the user_meta during user registration.
	 *
	 *	@author		Nate Jacobs
	 *	@since		0.1
	 */
	public function add_activation_code( $user_id )
	{
		$character_set = 'abcdefghjkmnpqrstuvwxyz23456789#';
		$string_length = 10;
		$activation_code = self::createRandomString( $string_length, $character_set );
		add_user_meta( $user_id, self::user_meta, $activation_code );
	}
	
	
}
new UserActivationEmail();

if ( !function_exists('wp_new_user_notification') ) :
	
	/** 
	 *	WP New User Notification
	 *
	 *	Overrides the function with the same name in wp-includes/pluggable.php.
	 *	Adds the activation code into the new user welcome email.
	 *
	 *	@author		Nate Jacobs
	 *	@since		0.1
	 */
	function wp_new_user_notification( $user_id, $plaintext_pass = '' )
	{
		$user = new WP_User($user_id);
		$activation_code = get_user_meta( $user->ID, UserActivationEmail::user_meta, true ); 

		$user_login = stripslashes($user->user_login); 
		$user_email = stripslashes($user->user_email); 

		$message  = sprintf(__('New user registration on your blog %s:'), get_option('blogname')) . "\r\n\r\n"; 
		$message .= sprintf(__('Username: %s'), $user_login) . "\r\n\r\n"; 
		$message .= sprintf(__('E-mail: %s'), $user_email) . "\r\n"; 

		@wp_mail(get_option('admin_email'), sprintf(__('[%s] New User Registration'), get_option('blogname')), $message); 
 
		if ( empty($plaintext_pass) ) 
        	return; 
 
     	$message  = sprintf(__('Username: %s'), $user_login) . "\r\n"; 
     	$message .= sprintf(__('Password: %s'), $plaintext_pass) . "\r\n\n";
     	$message .= sprintf(__('Activation Code: %s'), $activation_code) . "\r\n\n"; 
     	$message .= wp_login_url() . "\r\n"; 

		wp_mail($user_email, sprintf(__('[%s] Your username and password'), get_option('blogname')), $message);	
	}
endif;