<?php
	add_shortcode('user_report', 'func_user_report');
	function func_user_report(){ 
		ob_start(); ?>
		<table class="user_reportsTable" id="user_reportsTable">
	        <thead>
	            <tr>
	                <th data-head_slug="user_id">User ID</th>
					<th data-head_slug="status">Status</th>
					<th data-head_slug="user_type">User Type</th>
					<th data-head_slug="first_name">First Name</th>
					<th data-head_slug="surname">Surname</th>
					<th data-head_slug="nickname_team_name">Nickname/ Team Name</th>
					<th data-head_slug="email">Email</th>
					<th data-head_slug="mobile">Mobile</th>
					<th data-head_slug="date_of_birth">Date of Birth</th>
					<th data-head_slug="state">State</th>
					<th data-head_slug="business_consumer">Business/ Consumer</th>
					<th data-head_slug="business_name">Business Name</th>
					<th data-head_slug="business_id">Business ID</th>
					<th data-head_slug="business_billing_type">Business Billing Type</th>
					<th data-head_slug="approved_for_client_billing">Approved for Client Billing</th>
					<th data-head_slug="business_float_id">Business Float ID</th>
					<th data-head_slug="business_website">Business Website</th>
					<th data-head_slug="business_abn">Business ABN</th>
					<th data-head_slug="business_address_line_1">Business Address Line 1</th>
					<th data-head_slug="business_address_line_2">Business Address Line 2</th>
					<th data-head_slug="suburb">Suburb</th>
					<th data-head_slug="state">State</th>
					<th data-head_slug="country">Country</th>
					<th data-head_slug="post_code">Post Code</th>
					<th data-head_slug="business_currency">Business Currency</th>
					<th data-head_slug="user_created_date">User created date </th>
					<th data-head_slug="float_balance">Float Balance</th>
					<th data-head_slug="account_creation_type">Account Creation Type (Register/ Created by Admin)</th>
					<th data-head_slug="time_of_last_login">Time of Last Login</th>
					<th data-head_slug="next_reminder_date">Next Reminder Date</th>
					<th data-head_slug="next_reminder">Next Reminder</th>
					<th data-head_slug="wishlist_items">Wishlist Items</th>
					<th data-head_slug="wishlist_updated_date">Wishlist updated date</th>
					<th data-head_slug="email_preferences_y_n">Email Preferences (Y/N)</th>
					<th data-head_slug="sms_preferences_y_n">SMS Preferences (Y/N)</th>
					<th data-head_slug="personalised_offers_y_n">Personalised Offers (Y/N)</th>
					<th data-head_slug="events_celebrated">Events Celebrated </th>
					<th data-head_slug="hobbies_interests">Hobbies & Interests</th>
					<th data-head_slug="cards_in_wallet">Cards in Wallet</th>
					<th data-head_slug="card_name_1">Card Name 1</th>
					<th data-head_slug="card_number_1">Card Number 1</th>
					<th data-head_slug="card_denomination_1">Card Denomination 1</th>
					<th data-head_slug="card_status_1">Card Status 1</th>
					<th data-head_slug="card_name_2">Card Name 2</th>
					<th data-head_slug="card_number_2">Card Number 2</th>
					<th data-head_slug="card_denomination_2">Card Denomination 2</th>
					<th data-head_slug="card_status_2">Card Status 2</th>
	            </tr>
	        </thead>
	        <tbody></tbody>
	    </table>
		<?php return ob_get_clean();
	}

?>