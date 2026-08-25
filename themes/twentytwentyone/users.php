<?php
/**
 * Template Name: User Listing
 */

get_header(); ?>
<?php 
    $is_create_new_user = isset($_GET['create-new-user']);

    $show = 'style="display:block;"';
    $hide = 'style="display:none;"';

    $user_list_attr   = $is_create_new_user ? $hide : $show;
    $filter_attr      = $is_create_new_user ? $hide : $show;
    $create_form_attr = $is_create_new_user ? $show : $hide;

    $business_attr    = $is_create_new_user ? $hide : $hide;
    $next_btn_attr    = $is_create_new_user ? $hide : $hide;
    $save_next_attr    = $is_create_new_user ? $hide : $hide;
?>
<div class="page-spacer-top"></div>
<div class="user-section">
    <div id="exportOptionsPopup">
        <div class="custom-popup">
            <div class="custom-main-modal">
                <div class="custom-modal-header">
                    <h3>Choose export option:</h3>
                    <button type="button" class="btn-close close-modal" data-bs-dismiss="modal" aria-label="Close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="popup-footer">
                    <button id="exportWithPII" class="btn btn-white">Export with PII Data</button>
                    <button id="exportWithoutPII" class="btn btn-primary">Export without PII Data</button>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="user-list-container" <?= $user_list_attr; ?> >
            <!-- Search & Filter Row -->
            <div class="filter-container " <?= $filter_attr; ?> >
                <div class="top-filter-block">

                    <div class="search-wrapper search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="brandsSearchInput" placeholder="Search by User ID, Name, or Email">
                        <span id="clearSearch" class="clear-btn" style="display: none;">&times;</span>
                    </div>
    
                    <div class="action-buttons">
                        <select id="userRoleFilter" style="height: auto;">
                            <option value="">User Types</option>
                            <?php
                            $roles = wp_roles()->roles;
                            $excluded_roles = ['editor', 'author', 'contributor', 'subscriber', 'customer', 'shop_manager', 'supplier'];
    
                            $available_roles = [];
    
                            // First collect only non-excluded roles
                            foreach ($roles as $key => $role) {
                                if (!in_array($key, $excluded_roles)) {
                                    $available_roles[$key] = $role['name'];
                                }
                            }
    
                            // Sort by role name alphabetically
                            asort($available_roles);
                            // pr($available_roles);
                            // if($available_roles == 'J&C Super admin'){
                            //     $available_roles = 'GCP super admin';
                            // }
                            // Now output sorted options
                            foreach ($available_roles as $key => $name) {
                                if ($name === 'J&C Super admin') {
                                    $name = 'GCP super admin';
                                }
                                echo "<option value='" . esc_html($name) . "'>" . esc_html($name) . "</option>";
                            }
                            ?>
                        </select>
                        <button id="exportTable" class="btn btn-black-white btn-primary-white btn-white size-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <path
                                    d="M6.66797 14.1667L10.0013 17.5M10.0013 17.5L13.3346 14.1667M10.0013 17.5V10M16.668 13.9524C17.6859 13.1117 18.3346 11.8399 18.3346 10.4167C18.3346 7.88536 16.2826 5.83333 13.7513 5.83333C13.5692 5.83333 13.3989 5.73833 13.3064 5.58145C12.2197 3.73736 10.2133 2.5 7.91797 2.5C4.46619 2.5 1.66797 5.29822 1.66797 8.75C1.66797 10.4718 2.36417 12.0309 3.49043 13.1613"
                                    stroke="#344054" stroke-width="1.66667" stroke-linecap="round"
                                    stroke-linejoin="round" />
                            </svg>
                            Export User Table
                        </button>
                        <button id="createUser" class="btn btn-blue">Create New User</button>
                    </div>
                </div>
            </div>
            <div class="select-user-message alert alert-danger"></div>

            <?php
                $userData = array();
                $args = array(
                    'number'     => $limit,
                    'offset'     => $offset,
                    'orderby'    => $orderby,
                    'order'      => $order,
                    'meta_query' => [],
                );

                $user_query = new WP_User_Query($args);
                $users = $user_query->get_results();
                $total_users = $user_query->get_total();

                // Get role names
                $roles = wp_roles()->roles;
                $admin_user_id = get_current_user_id(); 
                $data = array();
                foreach ($users as $user) {
                    $user_meta = get_user_meta($user->ID);
                    $business_user_id = $user->ID;
                    $business_name = ($user_meta['business_name'][0]) ? $user_meta['business_name'][0] : 'N/A';

                    $role_slug = !empty($user->roles) ? $user->roles[0] : '';
                    $role_display_name = '';
                    if ($role_slug === 'administrator') {
                        //$role_display_name = 'J&C Super admin';
                        $role_display_name = 'GCP Super admin';
                    } elseif (isset($roles[$role_slug])) {
                        $role_display_name = $roles[$role_slug]['name'];
                    } else {
                        $role_display_name = ucfirst($role_slug);
                    }

                    if ($role_slug === 'business_user') {
                        // Agar user khud business_user hai to uska business_name
                        $business_name = get_user_meta($user->ID, 'business_name', true) ?: 'N/A';
                    } else {
                        // Agar user ka role business_user nahi hai, assigned business user dekho
                        $assigned_business_user_id = get_user_meta($user->ID, 'assigned_business_user', true);
                        if ($assigned_business_user_id) {
                            $business_name = get_user_meta($assigned_business_user_id, 'business_name', true) ?: 'N/A';
                            $business_user_id = $assigned_business_user_id;
                        }
                    }

                    $userData[] = array(
                        "user_id" => $user->ID,
                        "first_name" => isset($user_meta['first_name'][0]) ? $user_meta['first_name'][0] : '-',
                        "last_name" => isset($user_meta['last_name'][0]) ? $user_meta['last_name'][0] : '-',
                        "email" => $user->user_email,
                        "role" => esc_html($role_display_name),
                        "business_name" => $business_name,
                        "business_user_id" => $business_user_id,
                        "admin_user_id" => $admin_user_id,
                        "details" => '<a href="' . get_edit_user_link($user->ID) . '" target="_blank">View</a>',
                    );
                }
            ?>
            <!-- User Table -->
            <div class="table-container table-responsive">
                <table id="userTable" class="display" style="width:100%">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAllUsers" class="custom-checkbox">
                            </th>
                            <th>
                                User ID
                                <span class="filter-icon" data-col="1" style="cursor:pointer; width:16px; height:16px;"><i class="fa-solid fa-arrow-down"></i><span class="dashicons dashicons-filter filter-icon" data-column="user-id"></span>
                            </th>
                            <th>
                                First Name
                                <span class="filter-icon" data-col="1" style="cursor:pointer; width:16px; height:16px;"><i class="fa-solid fa-arrow-down"></i><span class="dashicons dashicons-filter filter-icon" data-column="user-id"></span>
                            </th>
                            <th>
                                Surname
                                <span class="filter-icon" data-col="1" style="cursor:pointer; width:16px; height:16px;"><i class="fa-solid fa-arrow-down"></i><span class="dashicons dashicons-filter filter-icon" data-column="user-id"></span>
                            </th>
                            <th>
                                Email
                                <span class="filter-icon" data-col="1" style="cursor:pointer; width:16px; height:16px;"><i class="fa-solid fa-arrow-down"></i><span class="dashicons dashicons-filter filter-icon" data-column="user-id"></span>
                            </th>
                            <th>
                                User Type
                                <span class="filter-icon" data-col="1" style="cursor:pointer; width:16px; height:16px;"><i class="fa-solid fa-arrow-down"></i><span class="dashicons dashicons-filter filter-icon" data-column="user-id"></span>
                            </th>
                            <th>
                                Business
                                <span class="filter-icon" data-col="1" style="cursor:pointer; width:16px; height:16px;"><i class="fa-solid fa-arrow-down"></i><span class="dashicons dashicons-filter filter-icon" data-column="user-id"></span>
                            </th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if($userData){
                            foreach ($userData as $key => $value) {
                                echo '<tr>';
                                    echo '<td>';
                                        echo '<input type="checkbox" class="user-checkbox custom-checkbox" value="'.$value['user_id'].'">';
                                    echo '</td>';
                                    echo '<td>';
                                        echo $value['user_id'];
                                    echo '</td>';
                                    echo '<td>';
                                        echo $value['first_name'];
                                    echo '</td>';
                                    echo '<td>';
                                        echo $value['last_name'];
                                    echo '</td>';
                                    echo '<td>';
                                        echo $value['email'];
                                    echo '</td>';
                                    echo '<td>';
                                        echo $value['role'];
                                    echo '</td>';
                                    echo '<td>';
                                        echo $value['business_name'];
                                    echo '</td>';
                                    echo '<td>';
                                        echo '<a href="javascript:void(0)" class="view-user-details edit-product-btn" data-user-id="'.$value['user_id'].'" data-admin-user-id="'.$value['admin_user_id'].'" data-business-user-id="'.$value['business_user_id'].'">View</a>';
                                    echo '</td>';
                                echo '</tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <div class="save-next-buttons page-bottom-toolbar"<?= $save_next_attr; ?> >
                <div class="right-block">
                    <div class="page-bottom-actions">
                        <button class="btn btn-white">Save</button>
                        <button class="btn btn-primary">Next</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="userDetailSection" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="javascript:void(0);" type="button" class="back_to_users_list" id="back_to_users_list">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="21" viewBox="0 0 24 21" fill="none">
                        <path d="M22.4598 8.95444H5.2559L12.772 2.32605C13.3727 1.79632 13.3727 0.927024 12.772 0.397296C12.1713 -0.132432 11.201 -0.132432 10.6004 0.397296L0.450505 9.34834C-0.150168 9.87807 -0.150168 10.7338 0.450505 11.2635L10.6004 20.2146C11.201 20.7443 12.1713 20.7443 12.772 20.2146C13.3727 19.6848 13.3727 18.8291 12.772 18.2994L5.2559 11.671H22.4598C23.3069 11.671 24 11.0598 24 10.3127C24 9.56567 23.3069 8.95444 22.4598 8.95444Z" fill="black"></path>
                    </svg>
                </a>
            </div>
            <div class="tabs-container">
                <div class="tabs">
                    <button class="user-detail-tab tab-btn active-tab" data-target="userProfileContent"
                        id="tabUserProfile">User
                        Profile</button>
                    <button class="user-detail-tab tab-btn" data-target="businessProfileContent"
                        id="tabBusinessProfile">Business
                        Profile</button>
                    <button class="user-detail-tab tab-btn" data-target="orderHistoryContent">Order History</button>
                    <button class="user-detail-tab tab-btn" data-target="trackCardsContent" id="tabTrackCards">Track
                        Cards</button>
                    <button class="user-detail-tab tab-btn" data-target="floatBillingContent">Float & Billing</button>
                    <button class="user-detail-tab tab-btn" data-target="contactListandEventContent">Contact List &
                        Events</button>
                    <button class="user-detail-tab tab-btn" data-target="userWalletContent">User Wallet</button>
                </div>
            </div>

            <div class="user-top-header-block top-filter-block">
                <div class="user-left">
                    <div class="user-business-name-wrapper">
                        <div class="user-business-name"></div>
                    </div>
                    <h2 class="float_balance">
                        <span class="label">Balance:</span>
                        <span class="amount">$0</span>
                    </h2>
                </div>
                <div class="form-actions action-buttons business-profile-buttons">
                    <button type="button" id="sender-profiles-btn" class="btn-save btn btn-black-white btn-primary-white size-sm">Sender
                        Profiles</button>
                    <button type="button" id="branded-cards-btn" class="btn-save btn btn-black-white btn-primary-white size-sm">Branded
                        Cards</button>
                    <button type="button" id="campaigns-btn" class="btn-save btn btn-black-white btn-primary-white size-sm">Campaigns</button>
                </div>
            </div>


            <div id="userProfileContent" class="tab-content" style="display: block;">
                <form id="userProfileForm" class="user-profile-form">
                    <input type="hidden" name="user_id" id="user_id_hidden" value="">

                    <div class="user-header">
                        <h2 class="user-name"></h2>
                    </div>
                    <div class="custom-from-section ">
                        <div class="form-section md-form-section">
                            <div class="form-group flex-row">
                                <div class="control-wrapper col col-6">
                                    <label class="label">User First Name<span class="validate">*</span></label>
                                    <input type="text" name="first_name" class="form-control" required>
                                </div>
                                <div class="control-wrapper col col-6">
                                    <label class="label">User Surname</label>
                                    <input type="text" name="last_name" class="form-control">
                                </div>
                            </div>

                            <div class="form-group flex-row">
                                <div class="control-wrapper col col-6">
                                    <label class="label">Nickname / Team Name</label>
                                    <input type="text" class="nickname_team form-control" name="nickname_team">
                                </div>
                            </div>

                            <div class="form-group flex-row">
                                <div class="control-wrapper col col-6">
                                    <label class="label">User ID<span class="validate">*</span></label>
                                    <input type="text" id="user_id_display" class="readonly form-control" value=""
                                        required readonly>
                                </div>
                                <div class="control-wrapper col col-6">
                                    <label class="label">User Type<span class="validate">*</span></label>
                                    <input type="text" name="user_type" class="readonly form-control" required readonly>
                                </div>
                            </div>

                            <div class="form-group flex-row">
                                <div class="control-wrapper col col-6">
                                    <label class="label">Email<span class="validate">*</span></label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                <div class="control-wrapper col col-6">
                                    <label class="label">Mobile<span class="validate">*</span></label>
                                    <input type="tel" name="mobile" pattern="^(\+61|0)[0-9]{9}$" class="form-control"
                                        placeholder="e.g.+61412345678" required>
                                </div>
                            </div>

                            <div class="form-group flex-row">
                                <div class="control-wrapper col col-6">
                                    <label class="label">Date of Birth</label>
                                    <input type="date" name="dob" class="form-control">
                                </div>
                                <div class="control-wrapper col col-6">
                                    <label class="label">State</label>
                                    <select name="state">
                                        <option value="">Select State</option>
                                        <option value="NSW">NSW</option>
                                        <option value="VIC">VIC</option>
                                        <option value="QLD">QLD</option>
                                        <option value="WA">WA</option>
                                        <option value="SA">SA</option>
                                        <option value="TAS">TAS</option>
                                        <option value="ACT">ACT</option>
                                        <option value="NT">NT</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group flex-row">
                                <div class="control-wrapper col col-6">
                                    <label class="label">Join Date<span class="validate">*</span></label>
                                    <input type="date" name="join_date" class="readonly form-control" required readonly>
                                </div>
                                <div class="control-wrapper col col-6">
                                    <label class="label">Last Login Date<span class="validate">*</span></label>
                                    <input type="date" name="last_login_date" class="readonly form-control" required
                                        readonly>
                                </div>
                            </div>

                            <div class="form-group flex-row">
                                <div class="control-wrapper col col-6">
                                    <label class="label">Work Anniversary</label>
                                    <input type="date" name="work_anniversary" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                <!-- Update the password section to this structure -->
                <div class="custom-from-section">
                    <div class="md-form-section">
                        <div class="com-pass-section">
                            <div class="password-section form-group flex-row">
                                <div class="control-wrapper col">
                                    <label class="label">Set New Password</label>
                                    <input type="text" name="new_password" placeholder="Enter new password"
                                        class="form-control">
                                </div>
                            </div>
                            <button type="button" id="resetPasswordBtn" class="btn btn-blue">Reset
                                Password</button>
                            <a href="#" id="sendResetLink" class="text-link">Reset Password Link</a>
                            <input type="hidden" id="reset_login" value="<?php echo esc_attr($user_login); ?>">
                            <input type="hidden" id="reset_key" value="<?php echo esc_attr($key); ?>">
                            <input type="hidden" id="default_password" value="">
                            <div class="reset-password-success-message user-profile-success"></div>

                            <div class="communications-section">
                                <h3>Communications</h3>
                                <ul class="communications-list">
                                    <li><a href="#" class="comm-link">View email log for user</a></li>
                                    <!-- <li><a href="#" class="comm-link">View customer service logs</a></li> -->
                                </ul>
                            </div>
                            <div id="formStatusMessage" style="display: none; color:green;"></div>
                        </div>
                    </div>
                </div>
                <div class="page-bottom-toolbar">
                    <div class="right-block">
                        <div class="form-actions page-bottom-actions    ">
                            <button type="button" id="saveUserDetailsBtn" class="btn-save btn-black-white btn-primary-white btn btn-white">Save</button>
                            <button type="button" id="nextTabBtn"
                                class="btn-next btn btn-primary btn-black-white btn-primary-black nextTabBtn">Next</button>
                        </div>
                    </div>
                </div>
                <div class="form-message" id="userFormMessage"></div>
            </div>
            <div id="businessProfileContent" class="tab-content" style="display: none;">
                <form id="businessProfileForm" class="business-profile-form" onsubmit="return false;">
                    <input type="hidden" id="businessUserDisplayName" class="form-control" readonly>
                    <input type="hidden" name="user_id" value="">
                    <input type="hidden" name="business_user_id" value="">
                    <input type="hidden" name="profile_business_user" value="">
                    <!-- <div class="business-header">
                        <h2 class="business-name"></h2>
                        <div class="business-balance"></div>
                    </div> -->
                    <div class="custom-from-section">
                        <div class="business-info-section md-form-section">
                            <div class="form-group flex-row">
                                <div class="control-wrapper col">
                                    <label class="label">Business Name<span class="validate">*</span></label>
                                    <input type="text" name="business_name" id="business_name" class="form-control"
                                        required>
                                </div>
                            </div>
                            <div class="form-group flex-row">
                                <div class="control-wrapper col">
                                    <label class="label">Business Website</label>
                                    <input type="url" name="business_website" class="form-control">
                                </div>
                            </div>
                            <!-- Business Team Users Section -->
                            <div class="business-team-section form-group flex-row">
                                <div class="control-wrapper col">
                                    <label class="label">Business Team Users</label>
                                    <div class="team-users-container">
                                        <div class="team-users-scroll user-profile-list">
                                            <!-- Dynamic avatars will be injected here by JS -->
                                            <!-- Add Recipient Button -->
                                            <button class="add-team-user" type="button" id="showRecipientsBtn">
                                                <span>+</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- This is hidden by default -->
                            <div class="recipient-list-container" id="recipientList" style="display: none;">
                                <div class="add-user-bar">
                                    <input type="text" id="recipientSearchInput" placeholder="Add New User" />
                                    <button type="button" type="button"
                                        class="add-user-button btn btn-blue size-sm">Add</button>
                                </div>
                                <div class="transfer-profile-message" style="display:none;"></div>
                                <div class="recipient-list"></div>
                                <div id="removeRecipientModal" class="custom-modal" style="display:none;">
                                    <div class="modal-content">
                                        <h3>Remove Recipient</h3>
                                        <p>Are you sure you want to remove this recipient from the business?</p>
                                        <div class="modal-actions">
                                            <button id="confirmRemoveBtn" class="btn btn-danger">Yes, Remove</button>
                                            <button id="cancelRemoveBtn" class="btn btn-secondary">Cancel</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Popup Modal -->
                            <div id="addRecipientModal" class="custom-modal">
                                <div class="custom-popup">
                                    <div class="custom-modal-content custom-main-modal">
                                        <div class="custom-modal-header">
                                            <span class="close-modal close-recipient-modal">&times;</span>
                                            <h2>Add New Recipient</h2>
                                        </div>
                                        <div class="form-group flex-row">
                                            <div class="control-wrapper col">
                                                <label class="label" for="recipientEmail">Email <span
                                                        class="hint">(Enter email to search user)</span></label>
                                                <div class="email-input-container" style="position: relative;">
                                                    <input type="text" class="form-control" id="recipientEmail"
                                                        placeholder="Search by email..." autocomplete="off" />
                                                    <span id="emailStatus" class="email-status"></span>
                                                    <ul id="emailSuggestions" class="suggestions-list"></ul>
                                                </div>
                                            </div>
                                        </div>
                                        <button id="addNewUserBtn" type="button" style="display: none;"
                                            class="btn primary-btn">Add New User</button>

                                        <div class="user-details-fields">
                                            <div class="form-group flex-row">
                                                <div class="control-wrapper col">
                                                    <label class="label" for="recipientFirstName">First Name</label>
                                                    <input type="text" id="recipientFirstName" placeholder="First name"
                                                        class="form-control">
                                                </div>
                                            </div>
                                            <div class="form-group flex-row">
                                                <div class="control-wrapper col">
                                                    <label class="label" for="recipientLastName">Last Name</label>
                                                    <input type="text" id="recipientLastName" placeholder="Last name"
                                                        class="form-control">
                                                </div>
                                            </div>
                                            <!-- <div class="form-group flex-row">
                                                <div class="control-wrapper col">
                                                    <label class="label" for="recipientUserID">User ID</label>
                                                    <input type="number" id="recipientUserID" class="readonly form-control" readonly>
                                                </div>
                                            </div> -->
                                            <div class="form-group flex-row">
                                                <div class="control-wrapper col">
                                                    <label class="label" for="recipientBusiness">Business Name</label>
                                                    <?php
                                                        $all_business = get_all_business();
                                                        if( $all_business ){ ?>
                                                            <select id="recipientBusiness" name="recipientBusiness">
                                                                <?php foreach ($all_business as $key => $value) { ?>
                                                                    <option value="<?=$key;?>"><?=$value;?></option>
                                                                <?php } ?>
                                                            </select>
                                                        <?php }
                                                    ?>
                                                    <!-- <input type="text" id="recipientBusiness" placeholder="Business name" class="readonly form-control" readonly> -->
                                                </div>
                                            </div>
                                            <div class="form-group flex-row">
                                                <div class="control-wrapper col">
                                                    <label class="label" for="recipientRole">User Role<span
                                                            class="validate">*</span></label>
                                                    <select id="recipientRole" name="recipientRole" required>
                                                        <option value="">Select Role</option>
                                                        <option value="external_business_admin">External Business
                                                            Admin</option>
                                                        <option value="external_business_viewer">External Business
                                                            Viewer</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="popup-footer center">
                                                <button id="submitRecipientEmail" type="button"
                                                    class="btn btn-primary primary-btn">Add</button>
                                            </div>
                                            <div class="add-user-in-business"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="custom-from-section">
                        <div class="billing-section md-form-section">
                            <h3>Billing Details</h3>

                            <div class="form-group flex-row">
                                <div class="control-wrapper col">
                                    <label class="label">Business ID<span class="validate">*</span></label>
                                    <input type="text" name="business_id" class="form-control" required>
                                    <div class="form-group checkbox-group checklist-input">
                                        <input type="checkbox" name="approved_billing" id="approved_billing"
                                            value="yes">
                                        <label for="approved_billing">Approved for payment for client
                                            billing</label>
                                    </div>
                                </div>

                            </div>
                            <div class="form-group flex-row">
                                <div class="control-wrapper col col-6">
                                    <label class="label">Billing Details</label>
                                    <input type="text" name="billing_details" class="form-control">
                                </div>
                                <div class="control-wrapper col col-6">
                                    <label class="label">Billing Details 2</label>
                                    <input type="text" name="billing_details_2" class="form-control">
                                </div>
                            </div>

                            <div class="form-group flex-row">
                                <div class="control-wrapper col col-6">
                                    <label class="label">Business Float ID</label>
                                    <input type="text" name="business_float_id" class="form-control">
                                </div>
                                <div class="control-wrapper col col-6">
                                    <label class="label">Business ABN<span class="validate">*</span></label>
                                    <input type="text" name="business_abn" class="form-control" required>
                                </div>
                            </div>

                            <div class="address-section">
                                <div class="form-group flex-row">
                                    <div class="control-wrapper col">
                                        <label class="label">Business Address Line 1<span
                                                class="validate">*</span></label>
                                        <input type="text" name="address_line1" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-group flex-row">
                                    <div class="control-wrapper col">
                                        <label class="label">Business Address Line 2</label>
                                        <input type="text" name="address_line2" class="form-control">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group flex-row">
                                <div class="control-wrapper col col-6">
                                    <label class="label">Suburb<span class="validate">*</span></label>
                                    <input type="text" name="suburb" class="form-control" required>
                                </div>
                                <div class="control-wrapper col">
                                    <label class="label">State<span class="validate">*</span></label>
                                    <input type="text" name="state" class="form-control" required>
                                </div>
                            </div>


                            <div class="form-group flex-row">
                                <div class="control-wrapper col col-6">
                                    <label class="label">Country</label>
                                    <input type="text" name="country" class="form-control" required>
                                </div>
                                <div class="control-wrapper col col-6">
                                    <label class="label">Postcode</label>
                                    <input type="text" name="postcode" class="form-control" required>
                                </div>
                            </div>

                            <div class="form-group flex-row">
                                <div class="control-wrapper col col-6">
                                    <label class="label">Business Currency</label>
                                    <select name="business_currency">
                                        <option value="AUD" selected>AUD</option>
                                        <option value="EUR">EUR</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="page-bottom-toolbar">
                        <div class="right-block">
                            <div class="form-actions page-bottom-actions">
                                <button type="button" id="saveBusinessDetailsBtn"
                                    class="btn-save btn btn-black-white btn-primary-white btn-white">Save</button>
                                <button type="button" id="nextBusinessTabBtn"
                                    class="btn-next btn btn-primary btn-black-white btn-primary-black nextTabBtn">Next</button>
                            </div>
                        </div>
                    </div>
                </form>
                <div class="form-message form-success-message user-profile-success" id="businessFormMessage"></div>
            </div>
            <div id="orderHistoryContent" class="tab-content" style="display: none;">
                <div class="order-history-controls">
                    <div class="top-filter-block">
                        <div class="search-container">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="order-history-search" placeholder="Search by Order ID or User Name">
                        </div>
                        <div class="action-buttons">
                            <button id="export-order-history" class="btn btn-black-white btn-primary-white btn-white size-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"
                                    fill="none">
                                    <path
                                        d="M6.66797 14.1667L10.0013 17.5M10.0013 17.5L13.3346 14.1667M10.0013 17.5V10M16.668 13.9524C17.6859 13.1117 18.3346 11.8399 18.3346 10.4167C18.3346 7.88536 16.2826 5.83333 13.7513 5.83333C13.5692 5.83333 13.3989 5.73833 13.3064 5.58145C12.2197 3.73736 10.2133 2.5 7.91797 2.5C4.46619 2.5 1.66797 5.29822 1.66797 8.75C1.66797 10.4718 2.36417 12.0309 3.49043 13.1613"
                                        stroke="#344054" stroke-width="1.66667" stroke-linecap="round"
                                        stroke-linejoin="round"></path>
                                </svg>
                                Export List
                            </button>
                        </div>
                    </div>
                </div>
                <div class="export-list-message"></div>
                <div style="overflow-x:auto; width:100%;">
                    <table id="order-history-table" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th data-head_slug="order_number">Order NO</th>
                                <th data-head_slug="order_date">Date</th>
                                <th data-head_slug="order_name">Order Name</th>
                                <th data-head_slug="order_user">User</th>
                                <th data-head_slug="order_status">Status</th>
                                <th data-head_slug="order_invoice">Invoice</th>
                                <th data-head_slug="order_payment">Payment</th>
                                <th data-head_slug="order_total">Total</th>
                                <th data-head_slug="order_campaign">Campaign</th>
                                <th data-head_slug="client_reference">Client Reference</th>
                                <th data-head_slug="order_po">PO</th>
                                <th>Track Cards</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="page-bottom-toolbar">
                    <div class="right-block">
                        <div class="form-actions page-bottom-actions">
                            <button type="button" id="saveorderHistory" class="btn-save btn btn-black-white btn-primary-white">Save</button>
                            <button type="button" id="nextorderHistory" class="btn-next btn btn-black-white btn-primary-black nextTabBtn">Next</button>
                        </div>
                    </div>
                </div>
            </div>
            <div id="trackCardsContent" class="tab-content" style="display: none;">
                <div class="track-cards-controls">
                    <div class="top-filter-block">
                        <div class="search-container">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="track-cards-search" placeholder="Search">
                        </div>
                        <div class="action-buttons">
                            <button id="export-track-cards" class="button button-primary btn btn-black-white btn-primary-white size-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"
                                    fill="none">
                                    <path
                                        d="M6.66797 14.1667L10.0013 17.5M10.0013 17.5L13.3346 14.1667M10.0013 17.5V10M16.668 13.9524C17.6859 13.1117 18.3346 11.8399 18.3346 10.4167C18.3346 7.88536 16.2826 5.83333 13.7513 5.83333C13.5692 5.83333 13.3989 5.73833 13.3064 5.58145C12.2197 3.73736 10.2133 2.5 7.91797 2.5C4.46619 2.5 1.66797 5.29822 1.66797 8.75C1.66797 10.4718 2.36417 12.0309 3.49043 13.1613"
                                        stroke="#344054" stroke-width="1.66667" stroke-linecap="round"
                                        stroke-linejoin="round"></path>
                                </svg>
                                Export List
                            </button>
                        </div>
                    </div>
                </div>
                <table id="trackCardsTable" class="display" style="width:100%">
                    <thead>
                        <tr>
                            <th>Created <span class="filter-icon" data-col="1"
                                    style="cursor:pointer; width:16px; height:16px;"><i
                                        class="fa-solid fa-arrow-down"></i></span><span
                                    class="dashicons dashicons-filter filter-icon" data-column="gift-card"></span></th>
                            <th>Type <span class="filter-icon" data-col="2"
                                    style="cursor:pointer; width:16px; height:16px;"><i
                                        class="fa-solid fa-arrow-down"></i></span><span
                                    class="dashicons dashicons-filter filter-icon" data-column="gift-card"></span></th>
                            <th>Card No <span class="filter-icon" data-col="1"
                                    style="cursor:pointer; width:16px; height:16px;"><i
                                        class="fa-solid fa-arrow-down"></i></span><span
                                    class="dashicons dashicons-filter filter-icon" data-column="gift-card"></span></th>
                            <th>Order No <span class="filter-icon" data-col="3"
                                    style="cursor:pointer; width:16px; height:16px;"><i
                                        class="fa-solid fa-arrow-down"></i></span><span
                                    class="dashicons dashicons-filter filter-icon" data-column="gift-card"></span></th>
                            <th>Email Sent To <span class="filter-icon" data-col="4"
                                    style="cursor:pointer; width:16px; height:16px;"><i
                                        class="fa-solid fa-arrow-down"></i></span><span
                                    class="dashicons dashicons-filter filter-icon" data-column="gift-card"></span></th>
                            <th>SMS Sent To <span class="filter-icon" data-col="5"
                                    style="cursor:pointer; width:16px; height:16px;"><i
                                        class="fa-solid fa-arrow-down"></i></span><span
                                    class="dashicons dashicons-filter filter-icon" data-column="gift-card"></span></th>
                            <th>Status <span class="filter-icon" data-col="6"
                                    style="cursor:pointer; width:16px; height:16px;"><i
                                        class="fa-solid fa-arrow-down"></i></span><span
                                    class="dashicons dashicons-filter filter-icon" data-column="gift-card"></span></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <div class="page-bottom-toolbar">
                    <div class="right-block">
                        <div class="form-actions page-bottom-actions">
                            <button type="button" id="saveTrackCards" class="btn-save btn btn-black-white btn-primary-white">Save</button>
                            <button type="button" id="nextTrackCards" class="btn-next btn btn-black-white btn-primary-black nextTabBtn">Next</button>
                        </div>
                    </div>
                </div>
            </div>
            <div id="floatBillingContent" class="tab-content" style="display: none;">
                <button class="float-billing-extra-btn btn btn-white btn-black-white btn-primary-white size-sm" style="display: none;"> Top Up Float </button>
                <!-- Billing Info Header -->
                <div class="float-billing-header">
                    <div class="billing-header-row">
                        <div class="billing-info-line">
                            <strong>Business Billing Type:</strong>
                            <span class="billing-type-label" id="business-billing-type-display"></span>
                        </div>
                        <div class="float-top-up-wrapper billing-info-line" style="display: none;">
                            <strong>Float Top Up Notification:</strong>
                            <span class="float-top-up-notification-label" id="float-top-up-display">$50</span>
                            <input type="number" id="float-top-up-input" class="editable-input hidden" step="0.01"
                                min="0" />
                            <span class="edit-icon" id="edit-float-top-up">✎</span>
                        </div>
                        <div class="billing-info-line">
                            <strong>Prepaid Limit:</strong>
                            <span id="payment-limit-display">$0.00</span>
                            <input type="number" id="payment-limit-input" class="editable-input hidden" step="0.01"
                                min="0" />
                            <span class="edit-icon" id="edit-payment-limit">✎</span>
                        </div>
                    </div>
                    <!-- Search & Filter -->

                    <div class="float-billing-controls">
                        <div class="top-filter-block">
                            <div class="search-container">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" id="float-billing-search" placeholder="Search">
                            </div>
                            <div class="action-buttons">
                                <button id="export-float-billing-controls" class="btn btn-white btn-black-white btn-primary-white size-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"
                                        fill="none">
                                        <path
                                            d="M6.66797 14.1667L10.0013 17.5M10.0013 17.5L13.3346 14.1667M10.0013 17.5V10M16.668 13.9524C17.6859 13.1117 18.3346 11.8399 18.3346 10.4167C18.3346 7.88536 16.2826 5.83333 13.7513 5.83333C13.5692 5.83333 13.3989 5.73833 13.3064 5.58145C12.2197 3.73736 10.2133 2.5 7.91797 2.5C4.46619 2.5 1.66797 5.29822 1.66797 8.75C1.66797 10.4718 2.36417 12.0309 3.49043 13.1613"
                                            stroke="#344054" stroke-width="1.66667" stroke-linecap="round"
                                            stroke-linejoin="round"></path>
                                    </svg>
                                    Export List
                                </button>
                            </div>
                        </div>
                    </div>


                </div>

                <!-- Transaction Table -->
                <div class="float-billing-table-wrapper">
                    <table id="float-billing-table" class="float-billing-table display nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>Date/Time<span class="dashicons dashicons-filter filter-icon" data-column="date-time"></span><i class="fa-solid fa-arrow-down"></i></th>
                                <th>Balance Type<span class="dashicons dashicons-filter filter-icon" data-column="balance-type"></span><i class="fa-solid fa-arrow-down"></i></th>
                                <th>Action<span class="dashicons dashicons-filter filter-icon" data-column="action"></span><i class="fa-solid fa-arrow-down"></i></th>
                                <th>Order<span class="dashicons dashicons-filter filter-icon" data-column="order"></span><i class="fa-solid fa-arrow-down"></i></th>
                                <th>Invoice<span class="dashicons dashicons-filter filter-icon" data-column="invoice"></span><i class="fa-solid fa-arrow-down"></i></th>
                                <th>Status<span class="dashicons dashicons-filter filter-icon" data-column="status"></span><i class="fa-solid fa-arrow-down"></i></th>
                                <th>Amount<span class="dashicons dashicons-filter filter-icon" data-column="amount"></span><i class="fa-solid fa-arrow-down"></i></th>
                                <th>Reference<span class="dashicons dashicons-filter filter-icon" data-column="reference"></span><i class="fa-solid fa-arrow-down"></i></th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody id="float-billing-body">
                        </tbody>
                    </table>
                </div>

                <!-- Pagination & Save/Next -->
                <div class="float-billing-footer"
                    style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                    <div>
                        Result per page:
                        <select>
                            <option>50</option>
                            <option>100</option>
                        </select>
                    </div>
                </div>
                <div class="page-bottom-toolbar">
                    <div class="right-block">
                        <div class="form-actions page-bottom-actions">
                            <button type="button" id="saveFloatBilling" class="btn-save btn btn-black-white btn-primary-white">Save</button>
                            <button type="button" id="nextFloatBilling" class="btn-next btn btn-black-white btn-primary-black nextTabBtn">Next</button>
                        </div>
                    </div>
                </div>
                <div class="float-billing-tab-message form-message form-success-message user-profile-success"></div>
            </div>
            <div id="contactListandEventContent" class="tab-content" style="display: none;">
                <div id="contact-reminders-container"></div>

                <div class="address-book-section">
                    <div class="address-book-title">
                        <h2 class="user-address-book-name"></h2>
                    </div>

                    <div class="contact-list-and-events-controls">
                        <div class="top-filter-block">
                            <div class="search-container">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" id="contact-user-events-search" placeholder="Search name,ID,email">
                            </div>
                            <div class="action-buttons">
                                <button id="export-contact-user-events" class="button button-primary btn btn-white btn-black-white btn-primary-white size-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"
                                        fill="none">
                                        <path
                                            d="M6.66797 14.1667L10.0013 17.5M10.0013 17.5L13.3346 14.1667M10.0013 17.5V10M16.668 13.9524C17.6859 13.1117 18.3346 11.8399 18.3346 10.4167C18.3346 7.88536 16.2826 5.83333 13.7513 5.83333C13.5692 5.83333 13.3989 5.73833 13.3064 5.58145C12.2197 3.73736 10.2133 2.5 7.91797 2.5C4.46619 2.5 1.66797 5.29822 1.66797 8.75C1.66797 10.4718 2.36417 12.0309 3.49043 13.1613"
                                            stroke="#344054" stroke-width="1.66667" stroke-linecap="round"
                                            stroke-linejoin="round"></path>
                                    </svg>
                                    Export List
                                </button>
                                <button id="bulk-add" class="button button-primary btn btn-white size-sm btn-black-white btn-primary-black">Bulk Add</button>
                                <button id="add-contact" class="button button-primary btn btn-blue">Add
                                    Contact</button>
                            </div>
                        </div>
                    </div>
                    <table id="contact-user-events" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>First Name</th>
                                <th>Surname</th>
                                <th>Nickname</th>
                                <th>Email</th>
                                <th>Mobile</th>
                                <th>Business</th>
                                <th>Date of Birth</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>

                    <div class="page-bottom-toolbar">
                        <div class="right-block">
                            <div class="form-actions page-bottom-actions">
                                <button type="button" id="saveContactListandEventContent"
                                    class="btn-save btn btn-black-white btn-primary-white">Save</button>
                                <button type="button" id="nextSaveContactListandEventContent"
                                    class="btn-next btn btn-black-white btn-primary-black nextTabBtn">Next</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="userWalletContent" class="tab-content" style="display: none;">
                <div class="wallet-section">
                    <div class="wallet-header">
                        <h2 class="wallet-title"></h2>
                        <p class="wallet-card-count"></p>
                    </div>

                    <!-- Cards Section -->
                    <div class="wallet-cards-container" id="walletCardsContainer">
                        <!-- Cards will be loaded here dynamically -->
                    </div>

                    <!-- Offers Section -->
                    <div class="offers-section">
                        <h3 class="offers-title">Offers</h3>
                        <div class="offers-grid" id="offersGrid">
                            <!-- Offers will be loaded here dynamically -->
                        </div>
                    </div>

                    <div class="page-bottom-toolbar">
                        <div class="right-block">
                            <div class="form-actions page-bottom-actions">
                                <button type="button" id="saveUserWallet" class="btn-save btn btn-black-white btn-primary-white">Save</button>
                                <button type="button" id="nextUserWallet" class="btn-next btn btn-black-white btn-primary-black nextTabBtn">Next</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Simple Create User Form (initially hidden) -->
    <div id="createUserFormContainer" <?= $create_form_attr; ?>>
        <div class="md-form-section">
            <div class="new-user-message alert alert-danger"></div>
        </div>
        <div class="simple-user-form">
            <div class="custom-from-section">
                <div class="md-form-section">
                    <form id="createUserForm" class="user-profile-form">
                        <div class="form-section">
                            <div class="form-group flex-row">
                                <div class="control-wrapper col col-6">
                                    <label class="label">User First Name<span class="validate">*</span></label>
                                    <input type="text" name="first_name" class="form-control" required>
                                </div>
                                <div class="control-wrapper col col-6">
                                    <label class="label">User Surname<span class="validate">*</span></label>
                                    <input type="text" name="last_name" class="form-control" required>
                                </div>
                            </div>

                            <div class="form-group flex-row">
                                <div class="control-wrapper col col-6">
                                    <label class="label">Nickname / Team Name</label>
                                    <input type="text" name="nickname_team" class="form-control">
                                </div>
                            </div>

                            <div class="form-group flex-row">
                                <div class="control-wrapper col col-6">
                                    <label class="label">User ID</label>
                                    <input type="text" id="new_user_id_display" value="Auto-generated"
                                        class="readonly form-control" readonly>
                                </div>
                                <div class="control-wrapper col col-6">
                                    <label class="label">User Type<span class="validate">*</span></label>
                                    <select name="user_type" required>
                                        <option value="">Select User Type</option>
                                        <?php
                                        $roles = wp_roles()->roles;
                                        $excluded_roles = ['editor', 'author', 'contributor', 'subscriber', 'customer', 'shop_manager', 'supplier'];

                                        foreach ($roles as $key => $role) {
                                            if (!in_array($key, $excluded_roles)) {
                                                echo '<option value="' . esc_attr($key) . '">' . esc_html($role['name']) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group flex-row">
                                <div class="control-wrapper col col-6">
                                    <label class="label">Email<span class="validate">*</span></label>
                                    <input type="email" name="email" required form-control class="form-control">
                                </div>
                                <div class="control-wrapper col col-6">
                                    <label class="label">Mobile<span class="validate">*</span></label>
                                    <input id="user_create_mobile_num" type="tel" name="mobile" placeholder="e.g. +614XXXXXXXX or 04XXXXXXXX"
                                        required form-control class="form-control">
                                </div>
                            </div>

                            <div class="form-group flex-row">
                                <div class="control-wrapper col col-6">
                                    <label class="label">Date of Birth</label>
                                    <input type="date" name="dob" class="form-control">
                                </div>
                                <div class="control-wrapper col col-6">
                                    <label>State</label>
                                    <select name="state">
                                        <option value="">Select State</option>
                                        <option value="NSW">NSW</option>
                                        <option value="VIC">VIC</option>
                                        <option value="QLD">QLD</option>
                                        <option value="WA">WA</option>
                                        <option value="SA">SA</option>
                                        <option value="TAS">TAS</option>
                                        <option value="ACT">ACT</option>
                                        <option value="NT">NT</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="page-bottom-toolbar">
                            <div class="form-actions">
                                <div class="page-bottom-actions">
                                    <button type="submit" id="saveNewUser" class="btn-black-white btn-primary-white btn-save btn btn-white">Save &
                                        Exit</button>
                                    <button type="button" id="nextToBusinessForm" <?= $next_btn_attr; ?> class="btn-next btn-black-white btn-primary-black btn btn-primary"
                                        style="display: none;">Next</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Business Form (initially hidden) -->
    <div id="businessFormContainer" <?= $business_attr; ?>>
        <div class="business-form container md-container">
            <div class="server-error-messages" style="display:none; color:#d9534f; margin-bottom:10px;"></div>
            <div class="page-title align-left">
                <!-- add name new user -->
                <!-- <h1>Clinton Brilley</h1>  -->
            </div>
            <form id="businessProfileForm1" class="business-profile-form">
                <div class="custom-from-section">
                    <div class="md-form-section">
                        <div class="business-info-section form-group flex-row">
                            <div class="control-wrapper col">
                                <label class="label">Business Name<span class="validate">*</span></label>
                                <input type="text" name="business_name" id="new_business_name" class="form-control"
                                    required>
                            </div>
                        </div>
                        <div class="business-website-row form-group flex-row">
                            <div class="control-wrapper col">
                                <label class="label">Business Website</label>
                                <input type="url" name="new_business_website" class="form-control">
                            </div>
                        </div>
                        <!-- Close .business-info-section -->
                    </div> <!-- Close .billing-section -->
                </div>
                <div class="custom-from-section">
                    <div class="md-form-section">
                        <div class="billing-section">
                            <h3>Billing Details</h3>

                            <div class="form-group flex-row">
                                <div class="control-wrapper col">
                                    <label class="label">Business ID<span class="validate">*</span></label>
                                    <input type="text" name="business_id" id="new_business_id" class="form-control"
                                        required>
                                    <div class="form-group checkbox-group checklist-input">
                                        <input type="checkbox" name="approved_billing" id="approved_billing"
                                            value="yes">
                                        <label for="approved_billing">Approved for payment for client billing</label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group flex-row">
                                <div class="control-wrapper col col-6">
                                    <label class="label">Business Float ID</label>
                                    <input type="text" name="business_float_id" class="form-control">
                                </div>
                                <div class="control-wrapper col col-6">
                                    <label class="label">Business ABN<span class="validate">*</span></label>
                                    <input type="text" name="business_abn" id="new_business_abn" class="form-control"
                                        required>
                                </div>
                            </div>

                            <div class="address-section">
                                <div class="form-group flex-row">
                                    <div class="control-wrapper col col-6">
                                        <label class="label">Business Address Line 1<span
                                                class="validate">*</span></label>
                                        <input type="text" name="address_line1" id="new_address_line1"
                                            class="form-control" required>
                                    </div>
                                    <div class="control-wrapper col col-6">
                                        <label class="label">Business Address Line 2</label>
                                        <input type="text" name="address_line2" id="new_address_line2"
                                            class="form-control">
                                    </div>
                                </div>

                                <div class="form-group flex-row">
                                    <div class="control-wrapper col col-6">
                                        <label class="label">Suburb<span class="validate">*</span></label>
                                        <input type="text" name="suburb" id="new_suburb" class="form-control" required>
                                    </div>
                                    <div class="control-wrapper col col-6">
                                        <label class="label">State<span class="validate">*</span></label>
                                        <input type="text" name="state" id="new_state" class="form-control" required>
                                    </div>
                                </div>

                                <div class="form-group flex-row">
                                    <div class="control-wrapper col col-6">
                                        <label class="label">Country<span class="validate">*</span></label>
                                        <input type="text" name="country" id="new_country" class="form-control"
                                            required>
                                    </div>
                                    <div class="control-wrapper col col-6">
                                        <label class="label">Postcode<span class="validate">*</span></label>
                                        <input type="text" name="postcode" id="new_postcode" class="form-control"
                                            required>
                                    </div>
                                </div>
                            </div> <!-- Close .address-section -->

                        </div> <!-- Close .billing-section -->
                    </div>
                </div>
                <div class="page-bottom-toolbar">
                    <div class="right-block">
                        <div class="page-bottom-actions">
                            <button type="button" id="saveBusinessDetailsBtn1" class="btn-save btn btn-black-white btn-primary-black">Save &
                                Exit</button>
                        </div>
                    </div>
                </div>
            </form>
        </div> <!-- Close .business-form -->
    </div> <!-- Close #businessFormContainer -->
</div>

<?php get_footer(); ?>