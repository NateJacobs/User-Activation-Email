<?php

/**
 *	Plugin Name: User Activation Email
 *	Plugin URI: https://github.com/NateJacobs/User-Activation-Email
 *	Description: Add an activation code to the new user email sent once a user registers. The user must enter this activation code in addition to a username and password to log in successfully the first time.
 *	Version: 1.3.0
 *	License: GPL V2
 *	Author: Nate Jacobs <nate@natejacobs.org>
 *	Author URI: http://natejacobs.org
 */

class UserActivationEmail
{
	protected $user_meta = 'uae_user_activation_code';
	
	// hook into actions and filters
	public function __construct()
	{
		// since 0.1
		add_filter( 'authenticate', array( $this, 'check_user_activation_code' ), 11, 3 );
		add_action( 'login_form', array( $this, 'add_login_field' ) );
		add_action( 'user_register', array( $this, 'add_activation_code' ) );
		add_action( 'wp_login', array( $this, 'update_activation_code' ) );
		
		// since 0.2
		add_action( 'show_user_profile', array( $this, 'add_user_profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'add_user_profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_user_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_profile_fields' ) );
		
		// since 0.3
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		add_action( 'plugins_loaded', array( $this, 'add_textdomain' ) );
		
		// since 1.0
		add_filter( 'manage_users_columns', array( $this, 'add_active_column' ) ); 
		add_action( 'manage_users_custom_column', array( $this, 'show_active_column_content' ), 10, 3 );
		add_filter( 'manage_users_sortable_columns', array( $this, 'sortable_active_column' ) );
		add_action( 'pre_user_query', array( $this, 'sortable_active_query' ) );
	}
	
	/** 
	 *	Localization 
	 *
	 *	@author		Nate Jacobs
	 *	@since		0.3
	 */
	public function add_textdomain() 
	{
  		load_plugin_textdomain( 'user-activation-email', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' ); 
	}
	
	/** 
	 *	Activation
	 *
	 *	Upon plugin activation create a custom user meta key of uae_user_activation_code
	 *	for all users and set the value to active (user is already active). 
	 *
	 *	@author		Nate Jacobs
	 *	@since		0.3
	 */
	public function activate()
	{
		global $wpdb;

		set_time_limit(120); // added because it takes forever to activate over .27 Million users.			
		$sql = "SELECT ID FROM {$wpdb->users} WHERE ID NOT IN
				(SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'uae_user_activation_code')";

		$users = $wpdb->get_col($sql);

		foreach($users as $user) {
			set_time_limit(10); // reset the clock every iteration
			
			// add the custom user meta to the wp_usermeta table
			add_user_meta( $user, $this->user_meta, 'active', true );
		}

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
	 *
	 *	@param	string	$user
	 *	@param	string	$user_login
	 *	@param	string	$password
	 */
	public function check_user_activation_code( $user, $user_login, $password )
	{
		// get the error class ready
		$error = new \WP_Error();
		
		$activation_code = '';
		
		// first check if either of the two fields are empty
		if ( empty( $user_login ) || empty( $password ) )
		{
			// figure out which one
			if ( empty( $user_login ) )
				$error->add( 'empty_username', __( 'The username field is empty.', 'user-activation-email' ) );
	
			if ( empty( $password ) )
				$error->add( 'empty_password', __( 'The password field is empty.', 'user-activation-email' ) );
				
			// remove the ability to authenticate	
			remove_action( 'authenticate', 'wp_authenticate_username_password', 20 );
			
			// return appropriate error
			return $error;
		}
		
		$user_info = get_user_by( 'login', $user_login );
		
		// if the object is empty, meaning an invalid username
		if( empty( $user_info ) )
		{
			// add the error message for invalid username
			$error->add( 'incorrect user', __( 'Username does not exist', 'user-activation-email' ) );
			
			// remove the ability to authenticate
			remove_action( 'authenticate', 'wp_authenticate_username_password', 20 );
			
			// return appropriate error
			return $error;
		}
		else
		{
			// get the custom user meta defined during registration
			$activation_code = get_user_meta( $user_info->ID, $this->user_meta, true );
		}
		
		if( $activation_code == 'active' )
		{
			return $user;
			exit;
		}
		else
		{
			if( $_POST['activation-code'] !== $activation_code )
			{
				// register a new error with the error message set above
				$user = new WP_Error( 'access_denied', __( 'Sorry, that activation code does not match. Please try again. You can find the activation code in your welcome email.', 'user-activation-email' ) );
				// deny access to login and send back to login page
				remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );
				
				return $user;
			}
		}		
	}
	
	/** 
	 *	Update Activation Code
	 *
	 *	Once a user successfully logs in, updates the activation code meta to read 'active'.
	 *	This allows the check_user_activation_code to bypass code matching if set to 'active'.
	 *
	 *	@author		Nate Jacobs
	 *	@since		0.1
	 *	
	 *	@param	string	$user_login
	 */
	public function update_activation_code( $user_login )
	{
		// get user data by login
		$user = get_user_by( 'login', $user_login );
		// change the custom user meta to show the user has already activated
		update_user_meta( $user->ID, $this->user_meta, 'active' );
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
			<label for="activation-code"><?php echo __( 'Activation Code (New User Only)', 'user-activation-email' ); ?><br>
				<input type="text" id="activation-code" class="input" name="activation-code" tabindex="20" value="<?php if( isset( $_GET['uae-key'] ) ) echo urldecode( $_GET['uae-key'] ); ?>">
			</label>
		</p>
		<?php
	}
	
	/** 
	 *	Add Activation Code
	 *
	 *	Generates the random activation code and adds it to the user_meta during user registration.
	 *
	 *	@author		Nate Jacobs
	 *	@since		0.1
	 *
	 *	@param		int	$user_id
	 */
	public function add_activation_code( $user_id )
	{
		// generate activation code
		$activation_code = wp_generate_password( 10 );
		
		// add to the user meta
		add_user_meta( $user_id, $this->user_meta, $activation_code );
	}
	
	/** 
	 *	Add a User Profile Field
	 *
	 *	Adds a field that shows the users activation code on their user profile page.
	 *	This field is only shown to admins.
	 *
	 *	@author		Nate Jacobs
	 *	@since		0.2
	 *	@updated	1.0
	 *
	 *	@param		string	$user
	 */
	public function add_user_profile_fields( $user )
	{
		if( current_user_can( 'manage_options', $user->ID ) )
		{
		?>
		<h3><?php _e( 'User Activation Email', 'user-activation-email' ); ?></h3>
		<p><?php _e( 'You may reset this user\'s activation code. If it reads \'active\', the user has activated their account.', 'user-activation-email' ); ?></p>
		<table class="form-table">
		<tr>
			<th><label for="activation-code"><?php _e( 'Activation Code (10 characters)', 'user-activation-email' ); ?></label></th>
			<td>
				<input type="text" id="activation-code" name="activation-code" value="<?php echo esc_attr( get_the_author_meta( $this->user_meta, $user->ID ) ); ?>" class="regular-text">
			</td>
		</tr>
		</table>
		<?php
		}	
	}
	
	/** 
	 *	Save Any Changes to User Profile Field
	 *
	 *	This method saves any changes made to the activation code.
	 *
	 *	@author		Nate Jacobs
	 *	@since		0.2
	 *
	 *	@param	int	$user_id
	 */
	public function save_user_profile_fields( $user_id )
	{
		if( !current_user_can( 'manage_options', $user_id ) ) {
			return false;
		}
		
		update_user_meta( $user_id, $this->user_meta, $_POST['activation-code'] );
	}
	
	/** 
	*	Add Active Custom Column
	*
	*	Add a new custom column to the user list table of 'Active'.
	*
	*	@author		Nate Jacobs
	*	@date		1/13/13
	*	@since		1.0
	*
	*	@param		array	$columns
	*	@return		array	$columns (with the new column appended)
	*/
	public function add_active_column( $columns ) 
	{
    		$columns['active_user'] = __( 'Active', 'user-activation-email' );
		return $columns;
    }

    /** 
    *	Display Active Custom Column
    *
	*	If the user account has been activated the column will display 'Yes', if not then 'No'.	
    *
    *	@author		Nate Jacobs
    *	@date		1/13/13
    *	@since		1.0
    *
    *	@param		string	$value
    *	@param		array	$column_name
    *	@param		string	$user_id
    */
    public function show_active_column_content( $empty, $column_name, $user_id ) 
    {
	    	$user = get_userdata( $user_id );
	    	
	    	if ( 'active_user' == $column_name )
		{
			if( 'active' == $user->uae_user_activation_code )
		    	{
			    	$active = __( 'Yes', 'user-activation-email' );
		    	}
		    	else
		    	{
			    	$active = __( 'No', 'user-activation-email' );
		    	}
	    	
			return $active;
		}
		else
		{
			return $empty;
		}
	}
	
	/** 
	*	Sortable Active Custom Column
	*
	*	Allow the new 'Active' custom column to be sortable.
	*
	*	@author		Nate Jacobs
	*	@date		1/13/13
	*	@since		1.0
	*
	*	@param		array	$columns	
	*/
	public function sortable_active_column( $columns ) 
	{  
	    $columns['active_user'] = 'active';  
	    return $columns;  
	}
	
	/** 
	*	Sortable Active Custom Column Query
	*
	*	Rewrite the user query to allow sorting by the new 'Active' custom column.
	*
	*	@author		Nate Jacobs
	*	@date		1/13/13
	*	@since		1.0
	*	@updated		1.1
	*
	*	@param		object	$query
	*/
	function sortable_active_query( $query )
	{
		global $wpdb,$current_screen;
		
		if( !is_admin() ) {
			return;
		}
		
	    if( 'active' == $query->query_vars['orderby'] && 'users' == $current_screen->id )
	    {
			$query->query_where = "WHERE 1=1 AND ( $wpdb->usermeta.meta_key = 'uae_user_activation_code' AND $wpdb->usermeta.user_id = $wpdb->users.ID ) ";
			$query->query_orderby = str_replace( 'user_login', "$wpdb->usermeta.meta_value", $query->query_orderby );
	    }
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
	 *	@updated	1.0
	 *
	 *	@param	int	$user_id
	 *	@param	string	$plaintext_pass
	 */
	function wp_new_user_notification( $user_id, $plaintext_pass = '' )
	{
		// pre-notification setup
		do_action('uae_start_new_user_notifcation');

		$user = new WP_User($user_id);
		$activation_code = get_user_meta( $user->ID, 'uae_user_activation_code', true );

		$user_login = stripslashes($user->user_login); 
		$user_email = stripslashes($user->user_email); 

		$message  = sprintf(__('New user registration on your blog %s:', 'user-activation-email'), get_option('blogname')) . "\r\n\r\n"; 
		$message .= sprintf(__('Username: %s', 'user-activation-email'), $user_login) . "\r\n\r\n"; 
		$message .= sprintf(__('E-mail: %s', 'user-activation-email'), $user_email) . "\r\n"; 

		// you can skip the admin email if the uae_admin_email filter return false
		$admin_email = apply_filters('uae_admin_email', get_option('admin_email'));
		
		$message = apply_filters('uae_admin_message', $message, $admin_email, $user_login, $user_email, $plaintext_pass, $activation_code);
		
		$subject = apply_filters('uae_admin_message_subject', sprintf(__('[%s] New User Registration', 'user-activation-email'), get_option('blogname')), $admin_email, $user_login, $user_email, $plaintext_pass, $activation_code);

		if($admin_email) {
			@wp_mail($admin_email, $subject, $message); 
		}
 
		if(empty($plaintext_pass)) {
			return;
		}
        
     	$message  = sprintf(__('Username: %s', 'user-activation-email'), $user_login) . "\r\n"; 
     	$message .= sprintf(__('Password: %s', 'user-activation-email'), $plaintext_pass) . "\r\n\n";
     	$message .= sprintf(__('Activation Code: %s', 'user-activation-email'), $activation_code) . "\r\n\n"; 
     	$message .= wp_login_url().'?uae-key='.urlencode( $activation_code )."\r\n"; 

		$message = apply_filters('uae_user_message', $message, $user_login, $user_email, $plaintext_pass, $activation_code);
		
		$subject = apply_filters('uae_user_message_subject', sprintf(__('[%s] Your username and password', 'user-activation-email'), get_option('blogname')), $user_login, $user_email, $plaintext_pass, $activation_code);

		wp_mail($user_email, $subject, $message);	

		// post-notification teardown
		do_action('uae_end_new_user_notifcation');

	}
endif;




