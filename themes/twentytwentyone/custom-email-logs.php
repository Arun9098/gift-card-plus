<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// Only enqueue DataTables on the email logs admin page
if ( is_admin() && isset( $_GET['page'] ) && $_GET['page'] === 'email-logs' ) {
    wp_enqueue_style(
        'datatables-css',
        'https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css',
        array(),
        '1.11.5'
    );
    wp_add_inline_style( 'datatables-css', '' ); // placeholder — SRI enforced via filter below
    wp_enqueue_script( 'jquery' );
    wp_enqueue_script(
        'datatables-js',
        'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js',
        array( 'jquery' ),
        '1.11.5',
        true
    );
    // Add SRI integrity attributes via script_loader_tag / style_loader_tag filters
    add_filter( 'style_loader_tag', function ( $tag, $handle ) {
        if ( $handle === 'datatables-css' ) {
            return str_replace(
                ' href=',
                ' integrity="sha256-XmvvdZqyBwEhXxVetnuRd6P824S8MwqWY98eqSRLzCY=" crossorigin="anonymous" href=',
                $tag
            );
        }
        return $tag;
    }, 10, 2 );
    add_filter( 'script_loader_tag', function ( $tag, $handle ) {
        if ( $handle === 'datatables-js' ) {
            return str_replace(
                ' src=',
                ' integrity="sha256-lpQbyCsrPqrv7IZbdk1u4zJ3Ft/DUAIfZElc0Zl25Kw=" crossorigin="anonymous" src=',
                $tag
            );
        }
        return $tag;
    }, 10, 2 );
}

// Get email logs from database with enhanced username fetching
// Replace the get_email_logs() function with this:
function get_email_logs($args = array())
{
    global $wpdb;

    $defaults = array(
        'search' => '',
        'date_from' => '',
        'date_to' => '',
        'status' => '',
        'user_id' => '',
        'per_page' => -1
    );

    $args = wp_parse_args($args, $defaults);

    $table_name = $wpdb->prefix . 'email_log';
    $where = array();
    $prepare_args = array();

    // Search condition - works for ID, username, and email
    if (!empty($args['search'])) {
        $search_term = $args['search'];
        $where_clauses = array();

        // Check if search is numeric (ID search)
        if (is_numeric($search_term)) {
            $where_clauses[] = "id = %d";
            $prepare_args[] = intval($search_term);
        }

        // Search in email, subject, headers, and username
        $where_clauses[] = "(to_email LIKE %s OR subject LIKE %s OR headers LIKE %s)";
        $prepare_args = array_merge($prepare_args, array_fill(0, 3, '%' . $wpdb->esc_like($search_term) . '%'));

        $where[] = '(' . implode(' OR ', $where_clauses) . ')';
    }

    // Date conditions
    if (!empty($args['date_from'])) {
        $where[] = "sent_date >= %s";
        $prepare_args[] = $args['date_from'];
    }

    if (!empty($args['date_to'])) {
        $where[] = "sent_date <= %s";
        $prepare_args[] = $args['date_to'] . ' 23:59:59';
    }

    // Status condition
    if (!empty($args['status'])) {
        $where[] = "status = %s";
        $prepare_args[] = $args['status'];
    }

    // ✅ User ID condition (added here)
    if (!empty($args['user_id'])) {
        $user = get_user_by('ID', intval($args['user_id']));
        if ($user) {
            $where[] = "to_email = %s";
            $prepare_args[] = $user->user_email;
        }
    }

    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $query = "SELECT * FROM {$table_name} {$where_clause} ORDER BY sent_date DESC";
    if ( ! empty( $prepare_args ) ) {
        $query = $wpdb->prepare( $query, $prepare_args );
    } else {
        $query = $wpdb->prepare( "SELECT * FROM {$table_name} ORDER BY sent_date DESC" );
    }

    $logs = $wpdb->get_results( $query );

    // Enhance logs with usernames and statuses
    foreach ($logs as &$log) {
        $log->username = get_username_from_email($log->to_email);
        $log->status = determine_email_status($log);
    }

    return $logs;
}


// Function to get username from email
function get_username_from_email($email)
{
    // First try to find user by email
    $user = get_user_by('email', $email);

    if ($user) {
        return $user->user_login;
    }

    // If not found, try to extract from email (common patterns)
    $email_parts = explode('@', $email);
    $possible_username = $email_parts[0];

    // Remove common suffixes
    $possible_username = str_replace('.', ' ', $possible_username);
    $possible_username = preg_replace('/\d+$/', '', $possible_username);
    $possible_username = trim($possible_username);

    return $possible_username ?: 'N/A';
}
// Function to determine email status
// Make sure this matches the filter options exactly
function determine_email_status($log)
{
    // First check if status is already set in the log
    if (!empty($log->status)) {
        return strtolower($log->status); // Ensure consistent case
    }

    // Determine status based on subject and content
    $subject = strtolower($log->subject);
    $message = strtolower($log->message);

    if (
        strpos($subject, 'bounce') !== false || strpos($subject, 'failed') !== false ||
        strpos($message, 'bounce') !== false || strpos($message, 'failed') !== false
    ) {
        return 'bounced';
    }

    if (strpos($subject, 'pending') !== false || strpos($message, 'pending') !== false) {
        return 'pending';
    }

    // Default to delivered
    return 'delivered';
}

// Display the email logs table
function display_email_logs_table()
{

    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : '';

    $logs = get_email_logs(array(
        'user_id' => $user_id
    )); ?>
    <div class="page-spacer-top"></div>
    <div class="email-logs-container">
        <div class="container">
            <div class="page-title align-left">
                <h1>Email Logs</h1>
            </div>
            <div class="email-logs-filters">
                <div class="top-filter-block">
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="search-input" placeholder="Search user name, ID and email">
                    </div>
                    <div class="action-buttons">
                        <button id="filter-btn" class="btn btn-rounded-white">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
                                <path
                                    d="M7 8.4C6.77963 8.4 6.59504 8.3328 6.44622 8.1984C6.29689 8.06447 6.22222 7.89833 6.22222 7.7C6.22222 7.50167 6.29689 7.3353 6.44622 7.2009C6.59504 7.06697 6.77963 7 7 7C7.22037 7 7.40522 7.06697 7.55456 7.2009C7.70337 7.3353 7.77778 7.50167 7.77778 7.7C7.77778 7.89833 7.70337 8.06447 7.55456 8.1984C7.40522 8.3328 7.22037 8.4 7 8.4ZM3.88889 8.4C3.66852 8.4 3.48367 8.3328 3.33433 8.1984C3.18552 8.06447 3.11111 7.89833 3.11111 7.7C3.11111 7.50167 3.18552 7.3353 3.33433 7.2009C3.48367 7.06697 3.66852 7 3.88889 7C4.10926 7 4.29411 7.06697 4.44344 7.2009C4.59226 7.3353 4.66667 7.50167 4.66667 7.7C4.66667 7.89833 4.59226 8.06447 4.44344 8.1984C4.29411 8.3328 4.10926 8.4 3.88889 8.4ZM10.1111 8.4C9.89074 8.4 9.70615 8.3328 9.55733 8.1984C9.408 8.06447 9.33333 7.89833 9.33333 7.7C9.33333 7.50167 9.408 7.3353 9.55733 7.2009C9.70615 7.06697 9.89074 7 10.1111 7C10.3315 7 10.5161 7.06697 10.6649 7.2009C10.8142 7.3353 10.8889 7.50167 10.8889 7.7C10.8889 7.89833 10.8142 8.06447 10.6649 8.1984C10.5161 8.3328 10.3315 8.4 10.1111 8.4ZM7 11.2C6.77963 11.2 6.59504 11.1328 6.44622 10.9984C6.29689 10.8645 6.22222 10.6983 6.22222 10.5C6.22222 10.3017 6.29689 10.1355 6.44622 10.0016C6.59504 9.8672 6.77963 9.8 7 9.8C7.22037 9.8 7.40522 9.8672 7.55456 10.0016C7.70337 10.1355 7.77778 10.3017 7.77778 10.5C7.77778 10.6983 7.70337 10.8645 7.55456 10.9984C7.40522 11.1328 7.22037 11.2 7 11.2ZM3.88889 11.2C3.66852 11.2 3.48367 11.1328 3.33433 10.9984C3.18552 10.8645 3.11111 10.6983 3.11111 10.5C3.11111 10.3017 3.18552 10.1355 3.33433 10.0016C3.48367 9.8672 3.66852 9.8 3.88889 9.8C4.10926 9.8 4.29411 9.8672 4.44344 10.0016C4.59226 10.1355 4.66667 10.3017 4.66667 10.5C4.66667 10.6983 4.59226 10.8645 4.44344 10.9984C4.29411 11.1328 4.10926 11.2 3.88889 11.2ZM10.1111 11.2C9.89074 11.2 9.70615 11.1328 9.55733 10.9984C9.408 10.8645 9.33333 10.6983 9.33333 10.5C9.33333 10.3017 9.408 10.1355 9.55733 10.0016C9.70615 9.8672 9.89074 9.8 10.1111 9.8C10.3315 9.8 10.5161 9.8672 10.6649 10.0016C10.8142 10.1355 10.8889 10.3017 10.8889 10.5C10.8889 10.6983 10.8142 10.8645 10.6649 10.9984C10.5161 11.1328 10.3315 11.2 10.1111 11.2ZM1.55556 14C1.12778 14 0.761444 13.863 0.456555 13.5891C0.152185 13.3147 0 12.985 0 12.6V2.8C0 2.415 0.152185 2.08553 0.456555 1.8116C0.761444 1.5372 1.12778 1.4 1.55556 1.4H2.33333V0.7C2.33333 0.501667 2.40774 0.3353 2.55656 0.2009C2.70589 0.0669666 2.89074 0 3.11111 0C3.33148 0 3.51633 0.0669666 3.66567 0.2009C3.81448 0.3353 3.88889 0.501667 3.88889 0.7V1.4H10.1111V0.7C10.1111 0.501667 10.1858 0.3353 10.3351 0.2009C10.4839 0.0669666 10.6685 0 10.8889 0C11.1093 0 11.2939 0.0669666 11.4427 0.2009C11.592 0.3353 11.6667 0.501667 11.6667 0.7V1.4H12.4444C12.8722 1.4 13.2386 1.5372 13.5434 1.8116C13.8478 2.08553 14 2.415 14 2.8V12.6C14 12.985 13.8478 13.3147 13.5434 13.5891C13.2386 13.863 12.8722 14 12.4444 14H1.55556ZM1.55556 12.6H12.4444V5.6H1.55556V12.6ZM1.55556 4.2H12.4444V2.8H1.55556V4.2Z"
                                    fill="#505050" />
                            </svg>
                            <span class="text-sm font-medium">Filter by: <strong class="font-semibold">Date</strong></span>
                        </button>

                        <div class="date-filter-wrapper">
                            <div class="filter-field">
                                <div class="date-range-fields">
                                    <input type="date" id="date-from" class="date-filter">
                                    <span>to</span>
                                    <input type="date" id="date-to" class="date-filter">
                                </div>
                            </div>

                            <div class="filter-field">
                                <select id="status-filter" class="status-filter">
                                    <option value="">All</option>
                                    <option value="delivered">Delivered</option>
                                    <option value="bounced">Bounced</option>
                                    <option value="pending">Pending</option>
                                </select>
                            </div>

                            <button id="reset-filters" class="button btn btn-blue">Reset Filter</button>
                        </div>
                    </div>
                </div>

            </div>

            <div class="email-logs-table">
                <table id="email-logs-datatable" class="display" style="width:100%">
                    <thead>
                        <tr>        
                            <th><input type="checkbox" id="email-logs-select-all"></th>
                            <th>Sent at <i class="fa-solid fa-arrow-down"></i></th>
                            <th>ID <i class="fa-solid fa-arrow-down"></i><span class="dashicons dashicons-filter filter-icon" data-column="id"></span></th>
                            <th>Username <i class="fa-solid fa-arrow-down"></i><span class="dashicons dashicons-filter filter-icon" data-column="username"></span></th>
                            <th>Status <i class="fa-solid fa-arrow-down"></i></th>
                            <th>To <i class="fa-solid fa-arrow-down"></i><span class="dashicons dashicons-filter filter-icon" data-column="to"></span></th>
                            <th>Subject <i class="fa-solid fa-arrow-down"></i><span class="dashicons dashicons-filter filter-icon" data-column="subject"></span></th>
                            <th>Details <i class="fa-solid fa-arrow-down"></i><span class="dashicons dashicons-filter filter-icon" data-column="details"></span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><input type="checkbox" class="row-checkbox" value="<?php echo esc_attr( $log->id ); ?>"></td>
                                <td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', strtotime( $log->sent_date ) ) ); ?></td>
                                <td><?php echo esc_html( $log->id ); ?></td>
                                <td><?php echo esc_html( $log->username ); ?></td>
                                <td class="status <?php echo esc_attr( strtolower( $log->status ) ); ?>" data-status="<?php echo esc_attr( strtolower( $log->status ) ); ?>">
                                    <span class="status-badge status-<?php echo esc_attr( strtolower( $log->status ) ); ?>">
                                        <?php echo esc_html( ucfirst( $log->status ) ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( $log->to_email ); ?></td>
                                <td><?php echo esc_html( $log->subject ); ?></td>
                                <td><a href="#" class="view-email edit-product-btn"
                                        data-logid="<?php echo esc_attr( $log->id ); ?>">View/Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal for viewing email content -->
    <!-- Modal for viewing email content -->
    <div class="container">
        <div id="email-content-modal" class="modal narrow-container">
            <div class="modal-content">
                <div class="email-content-header">
                    <h2>Email Content</h2>
                    <span class="zoome-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <g clip-path="url(#clip0_2961_53344)">
                                <path
                                    d="M12.46 8.335L14.875 5.92V7.9375C14.875 8.08668 14.9343 8.22976 15.0397 8.33525C15.1452 8.44073 15.2883 8.5 15.4375 8.5C15.5867 8.5 15.7298 8.44073 15.8352 8.33525C15.9407 8.22976 16 8.08668 16 7.9375V4.5625C16 4.41332 15.9407 4.27024 15.8352 4.16475C15.7298 4.05926 15.5867 4 15.4375 4H12.0625C11.9133 4 11.7702 4.05926 11.6647 4.16475C11.5593 4.27024 11.5 4.41332 11.5 4.5625C11.5 4.71168 11.5593 4.85476 11.6647 4.96025C11.7702 5.06574 11.9133 5.125 12.0625 5.125H14.08L11.665 7.54L12.0666 8.48588L12.46 8.335ZM4 15.4375V12.0625C4 11.9133 4.05926 11.7702 4.16475 11.6647C4.27024 11.5593 4.41332 11.5 4.5625 11.5C4.71168 11.5 4.85476 11.5593 4.96025 11.6647C5.06574 11.7702 5.125 11.9133 5.125 12.0625V14.08L11.665 7.54L12.0666 8.48588L12.46 8.335L5.92 14.875H7.9375C8.08668 14.875 8.22976 14.9343 8.33525 15.0397C8.44073 15.1452 8.5 15.2883 8.5 15.4375C8.5 15.5867 8.44073 15.7298 8.33525 15.8352C8.22976 15.9407 8.08668 16 7.9375 16H4.5625C4.48855 16.0003 4.41527 15.9859 4.34689 15.9578C4.27851 15.9296 4.21638 15.8882 4.16409 15.8359C4.1118 15.7836 4.07037 15.7215 4.04221 15.6531C4.01405 15.5847 3.9997 15.5114 4 15.4375Z"
                                    fill="black" />
                            </g>
                            <defs>
                                <clipPath id="clip0_2961_53344">
                                    <rect width="20" height="20" fill="white" />
                                </clipPath>
                            </defs>
                        </svg>
                    </span>
                </div>
                <div class="email-content-body">
                    <div class="email-meta">
                        <div class="email-top-content">
                            <div class="email-title-wrapper"><strong>Sent at:</strong></div> <span
                                id="email-sent-at"></span>
                        </div>
                        <div class="email-top-content">
                            <div class="email-title-wrapper"><strong>To:</strong> </div><span id="email-to"></span>
                        </div>
                        <div class="email-top-content">
                            <div class="email-title-wrapper"><strong>Subject:</strong> </div><span
                                id="email-subject"></span>
                        </div>
                    </div>

                    <div class="email-content-tabs">
                        <button class="tab-button active btn" data-tab="raw-content">Raw Email Content</button>
                        <button class="tab-button btn" data-tab="html-preview">Preview as HTML</button>
                    </div>

                    <div class="email-content-wrapper">
                        <div id="email-content-loading">Loading...</div>

                        <div id="raw-content" class="tab-content active">
                            <!-- <pre id="email-raw-content"></pre> -->
                            <code id="email-raw-content"></code>
                        </div>

                        <div id="html-preview" class="tab-content">
                            <div id="email-html-content"></div>
                        </div>
                    </div>
                </div>

                <div class="email-content-footer center">
                    <button id="close-modal" class="button btn">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// Display the table
display_email_logs_table();
?>