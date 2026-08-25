 <?php
    defined('ABSPATH') || exit;

    $user_id = get_current_user_id();

    // Get current month/year for calendar
    $current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
    $current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

    // Calculate first and last day of the month for calendar
    $first_day_of_month = date('Y-m-01', mktime(0, 0, 0, $current_month, 1, $current_year));
    $last_day_of_month = date('Y-m-t', mktime(0, 0, 0, $current_month, 1, $current_year));

    // Calculate date range for next 30 days (for occasions list)
    $today = date('Y-m-d');
    $next_30_days = date('Y-m-d', strtotime('+30 days'));

    /**
     * Helper function to get events for a user
     */
    function get_user_events($user_id, $date_from = null, $date_to = null, $category_filter = null)
    {

        // Query 1: Events with _gc_user_id matching current user (only this user's events)
        $args1 = [
            'post_type'      => 'tribe_events',
            'posts_per_page' => -1,
            'meta_key'       => '_EventStartDateUTC',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_query' => [
                [
                    'key'   => '_gc_user_id',
                    'value' => $user_id,
                ],
            ]
        ];

        // Query 2: Events created by current user (post_author)
        $args2 = [
            'post_type'      => 'tribe_events',
            'posts_per_page' => -1,
            'author'         => $user_id,
            'meta_key'       => '_EventStartDateUTC',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_query'     => [],
        ];

        // Query 3: Public holidays (category 'public-holidays') – show for all users regardless of author
        $args3 = [
            'post_type'      => 'tribe_events',
            'posts_per_page' => -1,
            'suppress_filters'  => true,
            'meta_key'       => '_EventStartDateUTC',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'tax_query'      => [
                [
                    'taxonomy' => 'tribe_events_cat',
                    'field'    => 'slug',
                    'terms'    => ['public-holidays', 'religious-holiday'],
                ],
            ],
            'meta_query'     => [],
        ];


        // Add date range filter if provided
        // For all-day events, _EventStartDateUTC is stored as 'YYYY-MM-DD 00:00:00'
        // We need to query using DATETIME type to properly match
        if ($date_from && $date_to) {
            $date_query = [
                'key'     => '_EventStartDate',
                'value'   => [$date_from, $date_to],
                'compare' => 'BETWEEN',
                'type'    => 'DATE',
            ];

            $args1['meta_query'][] = $date_query;
            $args2['meta_query'][] = $date_query;
            $args3['meta_query'][] = $date_query;
        }

        // Get events from all three queries
        $events1 = get_posts($args1);
        $events2 = get_posts($args2);
        $events3 = get_posts($args3);

        // Merge and remove duplicates (user's events + public holidays for all users)
        $event_ids = [];
        $all_events = [];
        foreach (array_merge($events1, $events2, $events3) as $event) {
            if (!in_array($event->ID, $event_ids)) {
                $event_ids[] = $event->ID;
                $all_events[] = $event;
            }
        }


        // Apply category filter by detected category (checks both taxonomy and title)
        if ($category_filter && $category_filter !== 'all') {
            $filtered_events = [];
            foreach ($all_events as $event) {
                $matches_filter = false;

                // First, check if event has the category in taxonomy
                $categories = wp_get_post_terms($event->ID, 'tribe_events_cat', ['fields' => 'all']);
                if (!empty($categories)) {
                    foreach ($categories as $category) {
                        if ($category->slug === $category_filter) {
                            $matches_filter = true;
                            break;
                        }
                    }
                }

                // If not found in taxonomy, check title for category keywords
                if (!$matches_filter) {
                    $title = strtolower($event->post_title);
                    if ($category_filter === 'birthdays' && (strpos($title, 'birthday') !== false || strpos($title, 'birth day') !== false)) {
                        $matches_filter = true;
                    } elseif ($category_filter === 'work-anniversaries' && (strpos($title, 'anniversary') !== false || strpos($title, 'work anniversary') !== false)) {
                        $matches_filter = true;
                    } elseif ($category_filter === 'public-holidays' && (strpos($title, 'holiday') !== false || strpos($title, 'public holiday') !== false)) {
                        $matches_filter = true;
                    } elseif ($category_filter === 'my-events') {
                        // For my-events, check if event was created by the current user
                        if ($event->post_author == $user_id) {
                            $matches_filter = true;
                        }
                    }
                }

                if ($matches_filter) {
                    $filtered_events[] = $event;
                }
            }
            $all_events = $filtered_events;
        }

        // Sort by event date
        usort($all_events, function ($a, $b) {
            $date_a = get_post_meta($a->ID, '_EventStartDateUTC', true);
            $date_b = get_post_meta($b->ID, '_EventStartDateUTC', true);
            if (!$date_a) return 1;
            if (!$date_b) return -1;
            return strtotime($date_a) - strtotime($date_b);
        });

        return $all_events;
    }

    // get_days_until_event(), format_event_date(), format_event_date_range(),
    // get_event_type(), and get_event_icon() have been moved to functions.php
    // (guarded by function_exists()) so the admin-side Contact List & Events
    // reminders section can reuse them without duplicating this logic.

    // Get category filter
    $category_filter = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'all';
    $has_type_filter = isset($_GET['type']); // Check if type filter was explicitly set

    // Get events for calendar (current month)
    $calendar_events = get_user_events($user_id, $first_day_of_month, $last_day_of_month, $category_filter);


    // Get events for occasions list: ALL upcoming events (not just 30 days) - no past events
    // Use a far future date to get all upcoming events
    $far_future_date = date('Y-m-d', strtotime('+10 years'));
    $occasions_events = get_user_events($user_id, $today, $far_future_date, $category_filter);

    // Filter to only upcoming events (0–30 days) so "no occasions" shows when all returned events are past
    $occasions_events_display = [];
    foreach ($occasions_events as $event) {
        $event_date_utc = get_post_meta($event->ID, '_EventStartDate', true);
        if (!$event_date_utc) {
            $event_date_utc = get_post_meta($event->ID, '_EventStartDate', true);
        }
        if (!$event_date_utc) continue;
        $days_until = get_days_until_event($event_date_utc);
        // if ($days_until < 0 || $days_until > 30) continue;
        // Only include future events (today and onwards)
        if ($days_until < 0) continue;
        $occasions_events_display[] = $event;
    }


    // Pagination: Show first 20 events initially
    $events_per_page = 4;
    $total_events = count($occasions_events_display);
    $current_page = isset($_GET['events_page']) ? max(1, intval($_GET['events_page'])) : 1;
    $offset = ($current_page - 1) * $events_per_page;
    $events_to_display = array_slice($occasions_events_display, $offset, $events_per_page);
    $has_more_events = ($offset + $events_per_page) < $total_events;



    // Create events array indexed by date (Y-m-d format) for calendar
    // Multi-day events appear on every day from start to end (actual event dates as defined on the event)
    $events_by_date = [];
    foreach ($calendar_events as $event) {
        $event_start = get_post_meta($event->ID, '_EventStartDate', true);
        if (!$event_start) {
            $event_start = get_post_meta($event->ID, '_EventStartDateUTC', true);
        }
        if (!$event_start) continue;

        $event_end = get_post_meta($event->ID, '_EventEndDate', true);
        if (!$event_end) {
            $event_end = get_post_meta($event->ID, '_EventEndDateUTC', true);
        }
        if (!$event_end) {
            $event_end = $event_start;
        }

        $start_date_only = substr($event_start, 0, 10);
        $end_date_only = substr($event_end, 0, 10);
        if ($end_date_only < $start_date_only) {
            $end_date_only = $start_date_only;
        }

        $current = $start_date_only;
        while ($current <= $end_date_only) {
            if (!isset($events_by_date[$current])) {
                $events_by_date[$current] = [];
            }
            $events_by_date[$current][] = $event;
            $current = date('Y-m-d', strtotime($current . ' +1 day'));
        }
    }

    // Calendar generation function
    function generate_calendar($month, $year, $events_by_date)
    {
        $first_day = mktime(0, 0, 0, $month, 1, $year);
        $days_in_month = date('t', $first_day);
        $day_of_week = date('w', $first_day); // 0 = Sunday, 6 = Saturday
        $start_of_week = get_option('start_of_week', 0); // WordPress setting

        // Adjust day of week based on WordPress start of week setting
        $day_of_week = ($day_of_week - $start_of_week + 7) % 7;

        $month_name = date('F Y', $first_day);
        $prev_month = $month - 1;
        $prev_year = $year;
        if ($prev_month < 1) {
            $prev_month = 12;
            $prev_year--;
        }
        $next_month = $month + 1;
        $next_year = $year;
        if ($next_month > 12) {
            $next_month = 1;
            $next_year++;
        }

        $weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        if ($start_of_week == 1) {
            $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        }

        ob_start();
    ?>
     <div class="reminders-calendar-view">
         <div class="calendar-header">
             <h3 class="calendar-month-year"><?php echo esc_html($month_name); ?></h3>
             <div class="next-priv-warpper">

                 <a href="#" class="calendar-nav prev-month" data-month="<?php echo $prev_month; ?>" data-year="<?php echo $prev_year; ?>"><svg xmlns="http://www.w3.org/2000/svg" width="7" height="12" viewBox="0 0 7 12" fill="none">
                         <path d="M0.25 6.25L5 10.9167C5.33333 11.25 5.83333 11.25 6.16667 10.9167C6.5 10.5833 6.5 10.0833 6.16667 9.75L2.08333 5.58333L6.16667 1.41667C6.5 1.08333 6.5 0.583333 6.16667 0.25C6 0.0833331 5.83333 0 5.58333 0C5.33333 0 5.16667 0.0833331 5 0.25L0.25 4.91667C-0.0833333 5.33333 -0.0833333 5.83333 0.25 6.25C0.25 6.16667 0.25 6.16667 0.25 6.25Z" fill="black" />
                     </svg></a>
                 <a href="#" class="calendar-nav next-month" data-month="<?php echo $next_month; ?>" data-year="<?php echo $next_year; ?>"><svg xmlns="http://www.w3.org/2000/svg" width="7" height="12" viewBox="0 0 7 12" fill="none">
                         <path d="M6.14233 4.95483L1.42566 0.246499C1.34819 0.168392 1.25602 0.106397 1.15447 0.0640893C1.05292 0.0217821 0.944004 0 0.833994 0C0.723984 0 0.615062 0.0217821 0.513513 0.0640893C0.411964 0.106397 0.319796 0.168392 0.242327 0.246499C0.0871179 0.402634 0 0.613844 0 0.833999C0 1.05415 0.0871179 1.26536 0.242327 1.4215L4.36733 5.58817L0.242327 9.71317C0.0871179 9.8693 0 10.0805 0 10.3007C0 10.5208 0.0871179 10.732 0.242327 10.8882C0.319506 10.9669 0.411543 11.0295 0.513106 11.0725C0.614669 11.1154 0.723738 11.1377 0.833994 11.1382C0.94425 11.1377 1.05332 11.1154 1.15488 11.0725C1.25644 11.0295 1.34848 10.9669 1.42566 10.8882L6.14233 6.17983C6.22691 6.1018 6.29442 6.00709 6.34059 5.90167C6.38677 5.79625 6.4106 5.68242 6.4106 5.56733C6.4106 5.45225 6.38677 5.33841 6.34059 5.23299C6.29442 5.12758 6.22691 5.03287 6.14233 4.95483Z" fill="black" />
                     </svg></a>
             </div>
         </div>
         <table class="reminders-calendar-table">
             <thead>
                 <tr>
                     <?php foreach ($weekdays as $day) : ?>
                         <th><?php echo esc_html($day); ?></th>
                     <?php endforeach; ?>
                 </tr>
             </thead>
             <tbody>
                 <tr>
                     <?php
                        // Fill in empty cells before the first day
                        for ($i = 0; $i < $day_of_week; $i++) {
                            echo '<td class="calendar-day empty"></td>';
                        }

                        // Fill in days of the month
                        $current_day = 1;
                        $today = date('Y-m-d');
                        while ($current_day <= $days_in_month) {
                            $date_key = sprintf('%04d-%02d-%02d', $year, $month, $current_day);
                            // $is_today = ($date_key == date('Y-m-d'));
                            $is_today = ($date_key == $today);
                            $has_events = isset($events_by_date[$date_key]) && !empty($events_by_date[$date_key]);

                            // Check if any events on this date are in the future (not past)
                            $has_future_events = false;
                            if ($has_events) {
                                foreach ($events_by_date[$date_key] as $event) {
                                    $event_date_utc = get_post_meta($event->ID, '_EventStartDate', true);
                                    if (!$event_date_utc) {
                                        $event_date_utc = get_post_meta($event->ID, '_EventStartDate', true);
                                    }
                                    if ($event_date_utc) {
                                        $event_date_only = substr($event_date_utc, 0, 10);
                                        // Only show pink dot if event date is today or in the future
                                        if ($event_date_only >= $today) {
                                            $has_future_events = true;
                                            break;
                                        }
                                    }
                                }
                            }



                            $day_class = 'calendar-day';
                            if ($is_today) {
                                $day_class .= ' today';
                            }
                            // if ($has_events) {
                            // Only add has-events class if there are future events (not past events)
                            if ($has_future_events) {
                                $day_class .= ' has-events';
                            }

                            echo '<td class="' . esc_attr($day_class) . '" data-date="' . esc_attr($date_key) . '">';
                            echo '<span class="day-number">' . esc_html($current_day) . '</span>';
                            // if ($has_events) {
                            //     $event_count = count($events_by_date[$date_key]);
                            //     echo '<span class="event-indicator" title="' . esc_attr($event_count . ' event(s)') . '">' . esc_html($event_count) . '</span>';
                            // }
                            echo '</td>';

                            $current_day++;
                            $day_of_week++;

                            // Start new row after Saturday (or Sunday if week starts on Monday)
                            if ($day_of_week == 7) {
                                echo '</tr><tr>';
                                $day_of_week = 0;
                            }
                        }

                        // Fill in empty cells after the last day
                        while ($day_of_week < 7) {
                            echo '<td class="calendar-day empty"></td>';
                            $day_of_week++;
                        }
                        ?>
                 </tr>
             </tbody>
         </table>
     </div>
 <?php
        return ob_get_clean();
    }
    ?>

 <div class="my-reminders-wrapper">


     <div class="events-wrap">
         <!-- LEFT SIDEBAR -->
         <aside class="reminders-sidebar">

             <!-- Calendar -->
             <div class="reminders-calendar">
                 <?php echo generate_calendar($current_month, $current_year, $events_by_date); ?>
             </div>

             <!-- Filters -->
             <ul class="event-filters">
                 <?php
                    // Build filter URLs with proper endpoint URL
                    $base_url = wc_get_endpoint_url('my-reminders', '', wc_get_page_permalink('myaccount'));
                    $month_param = !empty($_GET['month']) ? '&month=' . intval($_GET['month']) : '';
                    $year_param = !empty($_GET['year']) ? '&year=' . intval($_GET['year']) : '';
                    ?>
                 <li><a href="<?php echo esc_url($base_url . '?type=all' . $month_param . $year_param); ?>" class="<?php echo ($has_type_filter && $category_filter === 'all') ? 'active' : ''; ?>">All Events</a></li>
                 <?php
                    $event_categories = get_terms([
                        'taxonomy'   => 'tribe_events_cat',
                        'hide_empty' => false,
                        'orderby'    => 'name',
                        'order'      => 'ASC',
                    ]);
                    if (!empty($event_categories) && !is_wp_error($event_categories)) {
                        foreach ($event_categories as $term) {
                    ?>
                         <li><a href="<?php echo esc_url($base_url . '?type=' . esc_attr($term->slug) . $month_param . $year_param); ?>" class="<?php echo $category_filter === $term->slug ? 'active' : ''; ?>"><?php echo esc_html($term->name); ?></a></li>
                 <?php
                        }
                    }
                    ?>
                 <li><a href="<?php echo esc_url($base_url . '?type=my-events' . $month_param . $year_param); ?>" class="<?php echo $category_filter === 'my-events' ? 'active' : ''; ?>">My Events</a></li>
             </ul>

         </aside>

         <!-- RIGHT CONTENT -->
         <section class="reminders-content">

             <!-- Header -->
             <div class="reminders-header">
                 <div class="reminders-title-section">
                     <h2>My Reminders</h2>
                     <!-- <p class="reminders-subtitle">Occasions in the next <strong>30 days</strong>!</p> -->
                     <p class="reminders-subtitle">All upcoming occasions</p>
                 </div>
                 <a href="<?php echo esc_url(site_url('/add-reminder/')); ?>" class="btn-black-p2 btn btn-primary btn-ln">
                     <span class="btn-icon"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="15" viewBox="0 0 12 15" fill="none">
                             <path d="M11.7658 11.3769L10.7455 10.3846V6.53846C10.7455 4.17692 9.44845 2.2 7.18648 1.67692V1.15385C7.18648 0.515385 6.65658 0 6.00014 0C5.34369 0 4.81379 0.515385 4.81379 1.15385V1.67692C2.54391 2.2 1.25474 4.16923 1.25474 6.53846V10.3846L0.234483 11.3769C-0.263783 11.8615 0.0842125 12.6923 0.788112 12.6923H11.2042C11.9161 12.6923 12.2641 11.8615 11.7658 11.3769ZM9.16373 11.1538H6.00013H2.83654V6.53846C2.83654 4.63077 4.0308 3.07692 6.00014 3.07692C7.96947 3.07692 9.16373 4.63077 9.16373 6.53846V11.1538ZM6.00014 15C6.87012 15 7.58193 14.3077 7.58193 13.4615H4.41834C4.41834 14.3077 5.12224 15 6.00014 15Z" fill="white" />
                         </svg></span>
                     Add new reminder
                 </a>
             </div>


             <!-- Occasions Grid -->
             <!-- <div class="occasions-grid"> -->
             <?php //if (!empty($occasions_events_display)) : 
                ?>
             <?php //foreach ($occasions_events_display as $event) : 
                ?>
            <div class="occasions-grid" id="occasions-grid-container">
                 <?php if (!empty($events_to_display)) : ?>
                     <?php foreach ($events_to_display as $event) :
                            // For display, use _EventStartDate and _EventEndDate (local timezone) as defined on the event
                            $event_start = get_post_meta($event->ID, '_EventStartDate', true);
                            if (!$event_start) {
                                $event_start = get_post_meta($event->ID, '_EventStartDateUTC', true);
                            }
                            if (!$event_start) continue;

                            $event_end = get_post_meta($event->ID, '_EventEndDate', true);
                            if (!$event_end) {
                                $event_end = get_post_meta($event->ID, '_EventEndDateUTC', true);
                            }
                            if (!$event_end) {
                                $event_end = $event_start;
                            }

                            // For calculations (days until, filtering), use start date UTC
                            $event_date_utc = get_post_meta($event->ID, '_EventStartDateUTC', true);
                            if (!$event_date_utc) {
                                $event_date_utc = $event_start;
                            }

                            $days_until = get_days_until_event($event_date_utc);

                            // Display actual event date range (start – end) as defined on the event
                            $formatted_date = format_event_date_range($event_start, $event_end);
                            $event_type = get_event_type($event);
                            $icon_class = get_event_icon($event_type['slug']);

                            // Extract event title and type from post title
                            $title = $event->post_title;
                            $type_text = $event_type['name'];

                            // Try to extract person name and event type from title
                            // Format: "Person Name Xth Birthday" or "Person Name Xth Work Anniversary"
                            $title_parts = explode(' ', $title);
                            $person_name = '';
                            $event_type_display = '';

                            // Check if title contains anniversary or birthday
                            if (preg_match('/(\d+)(st|nd|rd|th)\s+(Work\s+)?Anniversary/i', $title, $matches)) {
                                $event_type_display = $matches[1] . $matches[2] . ' Work Anniversary';
                                $person_name = trim(str_replace($matches[0], '', $title));
                            } elseif (preg_match('/(\d+)(st|nd|rd|th)\s+Birthday/i', $title, $matches)) {
                                $event_type_display = $matches[1] . $matches[2] . ' Birthday';
                                $person_name = trim(str_replace($matches[0], '', $title));
                            } else {
                                // Use title as is and type from category
                                $person_name = $title;
                                $event_type_display = $event_type['name'];
                            }
                        ?>
                         <div class="occasion-card" data-event-id="<?php echo esc_attr($event->ID); ?>">
                            <div class="icon-waraper">

                                <div class="occasion-icon <?php echo esc_attr($icon_class); ?>">
                                    <?php if ($icon_class === 'cake-icon') : ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
                                            <path d="M14.0781 14.7902H1.91836C1.51262 14.7902 1.18066 14.497 1.18066 14.1388V10.4875C1.18066 9.82884 1.78721 9.29688 2.52903 9.29688H13.4675C14.2134 9.29688 14.8159 9.83245 14.8159 10.4875V14.1388C14.8159 14.5007 14.4879 14.7902 14.0781 14.7902ZM2.53297 9.73106C2.06166 9.73106 1.67642 10.0712 1.67642 10.4874V14.1387C1.67642 14.2581 1.78708 14.3558 1.92233 14.3558H14.0821C14.2173 14.3558 14.328 14.2581 14.328 14.1387V10.4874C14.328 10.0712 13.9428 9.73106 13.4714 9.73106H2.53297Z" fill="#ED018C" />
                                            <path d="M12.2913 11.9848C12.2257 11.9848 12.1642 11.963 12.1192 11.9196C11.7995 11.6374 11.3773 11.4854 10.9306 11.4854C10.4839 11.4854 10.0577 11.641 9.74209 11.9196C9.69701 11.9594 9.63144 11.9848 9.56996 11.9848C9.50849 11.9848 9.44291 11.963 9.39783 11.9196C9.07817 11.6374 8.65602 11.4854 8.2093 11.4854C7.76258 11.4854 7.33635 11.641 7.02077 11.9196C6.97569 11.9594 6.91012 11.9848 6.84864 11.9848C6.78307 11.9848 6.7216 11.963 6.67651 11.9196C6.02078 11.3406 4.9511 11.3406 4.2953 11.9196C4.25022 11.9594 4.18465 11.9848 4.12317 11.9848C4.0576 11.9848 3.99612 11.963 3.95104 11.9196C3.30351 11.3479 2.2789 11.3334 1.61096 11.887C1.51259 11.9703 1.35687 11.963 1.26259 11.8762C1.16831 11.7893 1.17652 11.6518 1.27488 11.5686C2.07815 10.9027 3.28716 10.8774 4.12319 11.4745C4.91416 10.91 6.05762 10.91 6.84853 11.4745C7.23377 11.1995 7.70919 11.0511 8.21328 11.0511C8.71737 11.0511 9.19279 11.1995 9.57803 11.4745C9.96327 11.1995 10.4387 11.0511 10.9428 11.0511C11.4469 11.0511 11.9223 11.1995 12.3075 11.4745C12.9797 10.9968 13.9346 10.9136 14.7051 11.2791C14.824 11.337 14.869 11.4673 14.8035 11.5722C14.7379 11.6771 14.5904 11.717 14.4715 11.6591C13.824 11.3478 13.0043 11.4564 12.4838 11.916C12.4223 11.963 12.3569 11.9848 12.2913 11.9848Z" fill="#ED018C" />
                                            <path d="M12.9509 9.72644H3.04922C2.64348 9.72644 2.31152 9.43332 2.31152 9.07506V6.29214C2.31152 5.63352 2.91807 5.10156 3.65988 5.10156H12.3362C13.0821 5.10156 13.6845 5.63714 13.6845 6.29214V9.07506C13.6886 9.43332 13.3567 9.72644 12.9509 9.72644ZM3.66005 5.53594C3.18874 5.53594 2.8035 5.87609 2.8035 6.29225V9.07518C2.8035 9.19459 2.91416 9.29231 3.04941 9.29231H12.9511C13.0864 9.29231 13.197 9.1946 13.197 9.07518V6.29225C13.197 5.87609 12.8118 5.53594 12.3405 5.53594H3.66005Z" fill="#ED018C" />
                                            <path d="M11.5533 7.4019C11.4877 7.4019 11.4262 7.38019 11.3812 7.33676C11.1271 7.11241 10.7869 6.98575 10.4262 6.98575C10.0656 6.98575 9.72542 7.10879 9.47131 7.33676C9.42623 7.37657 9.36066 7.4019 9.29918 7.4019C9.23771 7.4019 9.17213 7.38019 9.12705 7.33676C8.87296 7.11241 8.5328 6.98575 8.17213 6.98575C7.81147 6.98575 7.47133 7.10879 7.21722 7.33676C7.17213 7.37657 7.10656 7.4019 7.04508 7.4019C6.98361 7.4019 6.91804 7.38019 6.87295 7.33676C6.34427 6.86995 5.4877 6.86995 4.96312 7.33676C4.91803 7.37657 4.85246 7.4019 4.79099 7.4019C4.72951 7.4019 4.66394 7.38019 4.61886 7.33676C4.09836 6.87717 3.27461 6.86632 2.74181 7.30781C2.64345 7.39105 2.48772 7.38381 2.39345 7.29696C2.29917 7.21011 2.30738 7.0726 2.40574 6.98935C3.07786 6.43206 4.08606 6.40311 4.79514 6.89165C5.45908 6.4393 6.3894 6.4393 7.05339 6.89165C7.37716 6.6709 7.7706 6.55149 8.18453 6.55149C8.59845 6.55149 8.99191 6.67091 9.31566 6.89165C9.63944 6.6709 10.0288 6.55149 10.4468 6.55149C10.8607 6.55149 11.2542 6.67091 11.5779 6.89165C12.1476 6.50445 12.9386 6.4393 13.582 6.7469C13.7009 6.8048 13.746 6.93507 13.6804 7.04001C13.6148 7.14496 13.4673 7.18476 13.3484 7.12686C12.8279 6.87716 12.1722 6.96402 11.7501 7.33313C11.6804 7.38018 11.6189 7.4019 11.5533 7.4019Z" fill="#ED018C" />
                                            <path d="M15.2623 15.9981H0.737698C0.33196 15.9981 0 15.705 0 15.3467V15.0029C0 14.6447 0.33196 14.3516 0.737698 14.3516H15.2623C15.668 14.3516 16 14.6447 16 15.0029V15.3467C16 15.705 15.668 15.9981 15.2623 15.9981ZM0.737698 14.7858C0.602452 14.7858 0.491791 14.8835 0.491791 15.0029V15.3467C0.491791 15.4661 0.602446 15.5639 0.737698 15.5639H15.2623C15.3975 15.5639 15.5082 15.4662 15.5082 15.3467V15.0029C15.5082 14.8835 15.3976 14.7858 15.2623 14.7858H0.737698Z" fill="#ED018C" />
                                            <path d="M3.89337 5.54755C3.75812 5.54755 3.64746 5.44984 3.64746 5.33042V3.06869C3.64746 2.94927 3.75812 2.85156 3.89337 2.85156H5.22123C5.35647 2.85156 5.46713 2.94927 5.46713 3.06869V5.32319C5.46713 5.44261 5.35648 5.54032 5.22123 5.54032C5.08598 5.54032 4.97532 5.44262 4.97532 5.32319V3.28584H4.13925V5.33042C4.13925 5.45346 4.02862 5.54755 3.89337 5.54755Z" fill="#ED018C" />
                                            <path d="M4.94276 3.28221H4.17227C4.07801 3.28221 3.99194 3.23516 3.95095 3.15917C3.42227 2.19658 3.59849 0.900965 4.36899 0.0796135C4.41407 0.0289502 4.48374 0 4.55751 0C4.63128 0 4.70096 0.0289502 4.74604 0.0796135C5.51653 0.901076 5.69275 2.19293 5.16407 3.15917C5.12309 3.23517 5.03703 3.28221 4.94276 3.28221ZM4.3321 2.84795H4.78291C5.10669 2.14229 5.00832 1.24121 4.55751 0.58974C4.10258 1.24111 4.00832 2.14581 4.3321 2.84795Z" fill="#ED018C" />
                                            <path d="M12.107 5.54755C11.9717 5.54755 11.8611 5.44984 11.8611 5.33042V3.28584H11.025V5.32319C11.025 5.44261 10.9144 5.54032 10.7791 5.54032C10.6439 5.54032 10.5332 5.44262 10.5332 5.32319V3.06869C10.5332 2.94927 10.6439 2.85156 10.7791 2.85156H12.107C12.2422 2.85156 12.3529 2.94927 12.3529 3.06869V5.33042C12.3529 5.45346 12.2422 5.54755 12.107 5.54755Z" fill="#ED018C" />
                                            <path d="M11.8275 3.28221H11.057C10.9628 3.28221 10.8767 3.23516 10.8357 3.15917C10.307 2.19658 10.4833 0.900965 11.2538 0.0796135C11.2988 0.0289502 11.3685 0 11.4423 0C11.516 0 11.5857 0.0289502 11.6308 0.0796135C12.4013 0.901076 12.5775 2.19293 12.0488 3.15917C12.0079 3.23517 11.9218 3.28221 11.8275 3.28221ZM11.2169 2.84795H11.6677C11.9915 2.14229 11.8931 1.24121 11.4423 0.58974C10.9873 1.24111 10.8931 2.14581 11.2169 2.84795Z" fill="#ED018C" />
                                            <path d="M8.66461 5.54033C8.52936 5.54033 8.4187 5.44262 8.4187 5.32319V3.28584H7.58263V5.32319C7.58263 5.44261 7.47198 5.54033 7.33673 5.54033C7.20148 5.54033 7.09082 5.44262 7.09082 5.32319V3.06869C7.09082 2.94927 7.20148 2.85156 7.33673 2.85156H8.66459C8.79983 2.85156 8.91049 2.94927 8.91049 3.06869V5.32319C8.91049 5.44623 8.79986 5.54033 8.66461 5.54033Z" fill="#ED018C" />
                                            <path d="M8.38515 3.28221H7.61465C7.52039 3.28221 7.43432 3.23516 7.39334 3.15917C6.86466 2.19658 7.04088 0.900965 7.81137 0.0796135C7.85645 0.0289502 7.92613 0 7.9999 0C8.07367 0 8.14334 0.0289502 8.18842 0.0796135C8.95892 0.901076 9.13513 2.19293 8.60645 3.15917C8.56547 3.23517 8.47941 3.28221 8.38515 3.28221ZM7.77448 2.84795H8.22529C8.54907 2.14229 8.4507 1.24121 7.99989 0.58974C7.54496 1.24111 7.45071 2.14581 7.77448 2.84795Z" fill="#ED018C" />
                                        </svg>
                                    <?php else : ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="15" viewBox="0 0 16 15" fill="none">
                                            <path d="M15.8768 10.0951C15.2168 9.70365 14.4387 9.52435 13.6606 9.58294C12.4425 8.547 10.404 8.86164 9.3314 9.12766C9.04704 8.78253 8.72831 8.43156 8.37771 8.07764C8.64644 7.9751 8.92892 7.87901 9.22579 7.78935L9.22516 7.78994C9.76887 7.62764 10.3251 7.50635 10.8894 7.42607C11.4369 7.34813 11.9918 7.32411 12.5456 7.35399C13.0893 7.38739 13.6268 7.48407 14.1455 7.64228C14.1711 7.6499 14.1968 7.65635 14.223 7.66103C14.4393 7.70322 14.6636 7.66338 14.848 7.5497C15.0317 7.43544 15.1598 7.25732 15.2029 7.05458C15.2461 6.85242 15.2011 6.64148 15.0773 6.47039C14.9542 6.2993 14.763 6.18093 14.5461 6.14285C13.8918 6.0116 13.2218 5.9571 12.5531 5.98055C11.905 6.00691 11.2613 6.09656 10.6332 6.24833C10.0195 6.39657 9.41893 6.58993 8.83765 6.82666C8.43204 6.99308 8.0352 7.17882 7.65022 7.38449C7.22336 6.99836 6.79464 6.64268 6.37529 6.33155C6.99899 4.75008 6.82339 3.85412 6.48278 3.36316C6.48903 3.35261 6.49528 3.34148 6.50153 3.33093C7.26211 1.98384 5.56221 0.156287 5.48972 0.0794857C5.39785 -0.0177817 5.23973 -0.0271568 5.13662 0.0589774C5.03287 0.144525 5.02287 0.292771 5.11474 0.390032C5.13099 0.405266 6.55029 1.934 6.11718 2.99452C5.73346 2.72734 5.24098 2.63651 4.77788 2.74843C4.48728 2.82343 4.29791 2.98105 4.25854 3.17851C4.18417 3.55001 4.46915 3.94141 4.92162 4.08907C5.36284 4.22735 5.84969 4.10606 6.15967 3.78086C6.39592 4.28184 6.33091 5.05997 5.96405 6.03854C5.6997 5.85749 5.44096 5.69049 5.19597 5.55396C4.25977 5.03071 3.61981 4.92055 3.29288 5.227C3.21226 5.30552 3.15789 5.40454 3.13602 5.51177L0.0330012 14.1187C-0.0357451 14.3244 0.00425251 14.5488 0.140496 14.7228C0.276737 14.8969 0.493595 14.9994 0.723581 15C0.799202 15 0.874198 14.9889 0.946064 14.9678L10.133 12.0544C10.1374 12.0533 10.1399 12.0497 10.1443 12.0486V12.048C10.2487 12.0275 10.3455 11.9806 10.4236 11.912C10.7505 11.6056 10.633 11.005 10.0749 10.1273C9.9543 9.93748 9.81493 9.73943 9.6612 9.5361C10.5811 9.33102 12.0391 9.14703 13.0253 9.72068L13.0259 9.7201C12.7072 9.82205 12.4247 10.0037 12.2085 10.2463C11.9522 10.5551 11.9435 10.9014 12.1847 11.1275C12.3729 11.2916 12.6235 11.3795 12.8803 11.3701C13.2484 11.3701 13.6034 11.2389 13.8728 11.0039C14.1571 10.7525 14.2265 10.3529 14.0409 10.0306C14.6021 10.057 15.1465 10.2181 15.622 10.4988C15.7402 10.5598 15.8889 10.5217 15.9577 10.4127C16.0264 10.3031 15.9908 10.1631 15.8764 10.0951L15.8768 10.0951ZM5.08621 3.64678C4.87622 3.57822 4.72248 3.40361 4.74436 3.27939L4.74498 3.2788C4.86435 3.19501 5.01309 3.15576 5.16184 3.16923C5.34183 3.16923 5.51869 3.20966 5.67806 3.28818C5.74118 3.31865 5.80055 3.35556 5.85555 3.39717C5.62806 3.64267 5.35432 3.73408 5.08621 3.64678ZM9.03781 7.25733V7.25674C9.59591 7.02998 10.1715 6.84423 10.7603 6.70185C11.3552 6.55888 11.9652 6.47392 12.5789 6.44989C12.6776 6.44521 12.7776 6.44228 12.8801 6.44228C13.4076 6.44755 13.9325 6.50204 14.4482 6.60341C14.6244 6.63622 14.7394 6.79736 14.7038 6.96259C14.6688 7.12783 14.4975 7.23505 14.3213 7.20225L14.26 7.18643C13.712 7.02118 13.1445 6.92041 12.5696 6.88642C11.9827 6.85537 11.394 6.88115 10.8129 6.96318C10.2236 7.04756 9.64171 7.17471 9.07363 7.34464C8.75365 7.44073 8.44868 7.54444 8.15806 7.65636C8.43305 7.51807 8.7249 7.38565 9.03863 7.25674L9.03781 7.25733ZM3.32695 6.44989C3.7488 7.35166 4.74936 8.52818 5.82616 9.53784C6.0149 9.7148 6.20988 9.88941 6.40675 10.0593C4.58366 9.23198 3.47941 8.24701 2.90504 7.62L3.32695 6.44989ZM0.791421 14.5218C0.712676 14.5447 0.627063 14.5253 0.568314 14.4714C0.509568 14.4175 0.486444 14.3372 0.508944 14.2634L1.17703 12.4101C1.87699 12.8912 2.61445 13.3224 3.38314 13.7004L0.791421 14.5218ZM4.04179 13.4911C3.09309 13.0558 2.19006 12.5384 1.34387 11.9454L1.55198 11.3723C2.5813 12.1082 3.6981 12.7299 4.87995 13.2256L4.04179 13.4911ZM5.58795 13.0007H5.58858C4.2024 12.471 2.90122 11.7649 1.72129 10.9019L2.31876 9.24479C3.40869 10.2432 5.19987 11.4597 7.9597 12.2489L5.58795 13.0007ZM8.79464 11.9841C5.57227 11.1972 3.60439 9.81311 2.49758 8.74914L2.72006 8.13155C3.53876 8.96302 5.10552 10.2152 7.70168 11.0701C8.14166 11.3877 8.61726 11.6596 9.11973 11.881L8.79464 11.9841ZM10.0708 11.5804C10.0571 11.5915 10.0415 11.5997 10.0252 11.6044C10.0077 11.605 9.9902 11.6067 9.97333 11.6103L9.94833 11.6185C9.65648 11.6343 8.95338 11.3536 7.99661 10.6909H7.99598C7.35228 10.2391 6.74543 9.74343 6.18041 9.2073C4.31732 7.46112 3.57688 6.0913 3.60872 5.66995L3.6156 5.65061C3.61935 5.63479 3.62185 5.61897 3.62185 5.60315C3.62747 5.58674 3.63622 5.57209 3.64809 5.5592C3.68809 5.53283 3.73746 5.5217 3.78621 5.52697C3.96495 5.52697 4.32243 5.61135 4.94115 5.95706C5.23238 6.12464 5.51361 6.30569 5.78547 6.49965C5.75797 6.60922 5.81922 6.72173 5.93046 6.76508C5.96046 6.7768 5.99233 6.78324 6.02483 6.78324C6.0642 6.7809 6.10295 6.77035 6.1367 6.7516C6.48855 7.01586 6.84728 7.31586 7.20663 7.63637C7.19476 7.6434 7.18226 7.65043 7.17039 7.65746C6.99977 7.76059 6.9229 7.9563 6.9804 8.13794C7.03789 8.32016 7.21664 8.44497 7.41912 8.44439C7.49474 8.4438 7.56911 8.42681 7.63724 8.39517C7.71786 8.3565 7.8116 8.32193 7.8966 8.28443C8.23095 8.61548 8.54282 8.94655 8.81842 9.26999C8.71092 9.30339 8.64217 9.32682 8.62531 9.33327H8.62468C8.49594 9.37897 8.43095 9.51432 8.47969 9.63561C8.52844 9.7569 8.6728 9.81784 8.80217 9.77214C8.80779 9.7698 8.93653 9.72585 9.14152 9.66726C9.33027 9.90867 9.50462 10.1454 9.64649 10.3681C10.1727 11.1954 10.1308 11.5235 10.0708 11.5804ZM13.5194 10.6722C13.2588 10.9153 12.737 10.9821 12.5389 10.7964C12.4589 10.7214 12.5682 10.5767 12.6039 10.5339C12.8345 10.2966 13.1426 10.1366 13.4794 10.0798C13.6826 10.3077 13.6957 10.5069 13.5194 10.6722ZM9.07154 5.30251C9.16091 5.39685 9.28778 5.45427 9.42214 5.46131C9.43214 5.46131 9.44214 5.46248 9.45214 5.46248V5.46189C9.57651 5.46189 9.69712 5.41912 9.79025 5.34119C10.1277 5.06814 10.4396 4.76872 10.7214 4.44469C11.0071 4.11831 11.262 3.76968 11.4826 3.40171C11.7083 3.03491 11.9032 2.65228 12.0664 2.25735C12.2388 1.84894 12.3726 1.42766 12.4645 0.996991C12.4738 0.953632 12.4788 0.909685 12.4807 0.865153C12.497 0.403431 12.1114 0.0167107 11.6189 0.000885505C11.382 -0.00848963 11.1508 0.072957 10.9789 0.225889C10.8021 0.380571 10.7002 0.596214 10.6958 0.822972C10.6771 1.15989 10.6271 1.49506 10.5471 1.82377C10.4658 2.17007 10.3571 2.50933 10.2215 2.8398C10.0883 3.17321 9.92398 3.49489 9.73025 3.80134C9.53463 4.11364 9.30965 4.40897 9.05779 4.68377C8.89843 4.86482 8.90405 5.1279 9.07154 5.30251ZM10.1627 4.03453C10.3702 3.70699 10.5464 3.36245 10.6889 3.00561C10.8333 2.65403 10.9496 2.29251 11.0352 1.92396C11.1227 1.56242 11.1764 1.19504 11.1952 0.824725C11.2027 0.626093 11.3764 0.469055 11.5876 0.46847H11.6014C11.8183 0.475502 11.9876 0.646013 11.9814 0.848756C11.9801 0.868678 11.9776 0.888599 11.9739 0.907936C11.887 1.31165 11.7614 1.70658 11.5995 2.0892C11.4452 2.46245 11.2608 2.82339 11.0483 3.17027C10.8402 3.51656 10.6002 3.84469 10.3315 4.15115C10.0727 4.45349 9.77901 4.72773 9.45589 4.96912C9.72025 4.67732 9.9571 4.36502 10.1627 4.03453ZM8.15852 2.02765C8.4985 2.02765 8.80474 1.83605 8.93473 1.54132C9.06472 1.24718 8.99285 0.908487 8.75225 0.682898C8.51226 0.457895 8.15103 0.390514 7.83729 0.512391C7.52294 0.634268 7.31857 0.921387 7.31857 1.24014C7.3192 1.67491 7.6948 2.02707 8.15852 2.02765ZM8.15852 0.921387C8.29601 0.921387 8.42038 0.998731 8.47287 1.11826C8.52537 1.23721 8.49599 1.37432 8.39913 1.46573C8.30163 1.55714 8.15539 1.58409 8.0279 1.53487C7.90103 1.48565 7.81854 1.36904 7.81854 1.24015C7.81854 1.06377 7.97103 0.921387 8.15852 0.921387ZM14.2014 8.01724C13.9251 8.01724 13.6764 8.1731 13.5708 8.41216C13.4652 8.65124 13.5239 8.92663 13.7189 9.10943C13.9145 9.29226 14.2076 9.34732 14.4626 9.24772C14.7176 9.14869 14.8838 8.91549 14.8838 8.6565C14.8832 8.30375 14.5782 8.01782 14.2014 8.01724ZM14.2014 8.82818C14.1276 8.82818 14.0607 8.78658 14.0326 8.72271C14.0045 8.65884 14.0201 8.58501 14.072 8.53638C14.1239 8.48716 14.2026 8.47251 14.2707 8.49888C14.3389 8.52524 14.3832 8.58735 14.3832 8.6565C14.3832 8.75084 14.302 8.82759 14.2014 8.82818ZM12.4277 4.04161C12.0652 4.04161 11.739 4.24669 11.6003 4.56017C11.4615 4.87424 11.5384 5.23576 11.7946 5.47601C12.0509 5.71625 12.4358 5.78773 12.7708 5.65823C13.1058 5.52815 13.3239 5.22169 13.3239 4.88185C13.3239 4.41777 12.9227 4.04161 12.4277 4.04161ZM12.4277 5.25159C12.2677 5.25159 12.124 5.16135 12.0627 5.02308C12.0015 4.88421 12.0352 4.72483 12.1484 4.61877C12.2615 4.51331 12.4315 4.48108 12.5796 4.5385C12.7271 4.59592 12.8233 4.73128 12.8233 4.88128C12.8233 5.08578 12.6464 5.25159 12.4277 5.25159ZM13.2583 2.99106L13.417 3.0309V3.03149C13.7601 3.11879 14.0276 3.37016 14.1214 3.69126L14.1638 3.8395V3.84009C14.2045 3.98247 14.3426 4.0815 14.5001 4.0815C14.6569 4.0815 14.7951 3.98247 14.8357 3.84009L14.8794 3.69184C14.9726 3.37075 15.2407 3.11937 15.5831 3.03207L15.7413 2.99223H15.7419C15.8944 2.95414 16 2.82465 16 2.67698C16 2.52932 15.8944 2.39982 15.7419 2.36174L15.5838 2.3219H15.5831C15.2407 2.23459 14.9726 1.98322 14.8794 1.66213L14.8369 1.5133C14.7963 1.37033 14.6582 1.2713 14.5007 1.2713C14.3432 1.2713 14.2051 1.37032 14.1645 1.5133L14.122 1.66213C14.0289 1.98381 13.7608 2.23518 13.417 2.3219L13.2589 2.36174V2.36233C13.107 2.40041 13.0008 2.52932 13.0008 2.67698C13.0008 2.82523 13.107 2.95414 13.2589 2.99223L13.2583 2.99106ZM14.5001 2.04124C14.6463 2.31663 14.8844 2.53988 15.1782 2.67641C14.8844 2.81352 14.6463 3.03676 14.5007 3.31217C14.3545 3.03678 14.1164 2.81353 13.8226 2.67641C14.1163 2.53989 14.3538 2.31665 14.5001 2.04124ZM8.56171 3.31275C8.52984 3.34732 8.51672 3.39361 8.52734 3.43814L8.59296 3.7194H8.59234C8.63609 3.90807 8.58296 4.10436 8.4486 4.25085L8.24736 4.46999C8.21611 4.50456 8.20361 4.55085 8.21361 4.59597L8.27923 4.87956C8.32173 5.06765 8.26861 5.26395 8.13549 5.40985L7.80114 5.77314C7.71115 5.87158 7.55365 5.88271 7.44867 5.79834C7.3443 5.71396 7.33243 5.56572 7.42242 5.46787L7.75677 5.10399V5.10341C7.78802 5.06942 7.80114 5.02313 7.79052 4.9786L7.72489 4.69384C7.68177 4.50517 7.73552 4.30946 7.86864 4.16296L8.0705 3.94383C8.10174 3.90926 8.11424 3.86297 8.10425 3.81843L8.03862 3.53718V3.53777C7.9955 3.34909 8.04862 3.15222 8.18299 3.00572L8.51547 2.64419H8.51484C8.60546 2.54575 8.76295 2.5352 8.86794 2.61958C8.97231 2.70396 8.98418 2.8522 8.89419 2.95063L8.56171 3.31275Z" fill="#ED018C" />
                                        </svg>
                                    <?php endif; ?>
                                </div>
                                <div class="occasion-countdown">
                                    <?php if ($days_until < 0) : ?>
                                        <?php echo esc_html(abs($days_until)); ?> days ago
                                    <?php elseif ($days_until == 0) : ?>
                                        Today
                                    <?php else : ?>
                                        in <?php echo esc_html($days_until); ?> days
                                    <?php endif; ?>
                                </div>
                            </div>
                             <div class="occasion-content">
                                 <div class="occasion-date"><?php echo esc_html($formatted_date); ?></div>
                                 <div class="occasion-title">
                                     <?php if (!empty($person_name) && $person_name !== $title) : ?>
                                         <?php echo esc_html($person_name); ?>
                                         <strong class="occasion-type"><?php echo esc_html($event_type_display); ?></strong>
                                     <?php else : ?>
                                         <strong class="occasion-type"><?php echo esc_html($title); ?></strong>
                                     <?php endif; ?>
                                 </div>
                                 <a href="<?php echo esc_url(site_url('/shop')); ?>" class="shop-now-btn btn btn-white-p2">Shop now</a>
                             </div>
                         </div>
                     <?php endforeach; ?>
                 <?php else : ?>
                     <div class="no-occasions">
                         <!-- <p>No upcoming occasions in the next 30 days.</p> -->
                         <p>No upcoming occasions.</p>
                         <a href="#" class="btn btn-primary btn-ln">Add your first reminder</a>
                     </div>
                 <?php endif; ?>
             </div>

             <!-- Load More Button -->
            <?php if ($has_more_events) : ?>
                 <div class="load-more-events-container" style="text-align: center; margin-top: 30px;">
                     <button type="button" class="load-more-events-btn btn btn-primary btn-ln btn-black-p2"
                         data-page="<?php echo esc_attr($current_page); ?>"
                         data-total="<?php echo esc_attr($total_events); ?>"
                         data-per-page="<?php echo esc_attr($events_per_page); ?>"
                         data-type="<?php echo esc_attr($category_filter); ?>">
                         <?php $remaining_count = $total_events - ($offset + count($events_to_display)); ?>
                         Load More Events (<?php echo esc_html(min($events_per_page, $remaining_count)); ?> of <?php echo esc_html($remaining_count); ?> remaining)
                     </button>
                 </div>
             <?php endif; ?>


         </section>
     </div>

     <div class="address-book-section">
         <h4 class="address-book-title">My Address Book</h4>
         <?php echo do_shortcode(
                '[pdb_list 
        fields="first_name,last_name,middle_name,email,phone,date_of_birth,reminder" 
        template="default-custom" 
        search="true" 
        sort="true" 
        pagination="false" 
        list_limit="-1"]'
            ); ?>
     </div>
     <?php //$templates = get_option('wpb_js_templates'); 
        ?>
     <!-- <div class="page-reminder shop-business-template-2c-need-help-wrap"> -->
     <?php
        // if (!empty($templates)) {
        //     // echo 'Hiiii';
        //     // print_r($templates);
        //     foreach ($templates as $template) {
        //         if (!empty($template['name']) && $template['name'] === 'shop-business-template-2c-need-help-wrap') {
        //             echo do_shortcode($template['template']);
        //             break; // stop once found
        //         }
        //     }
        // } 
        ?>
     <!-- </div> -->

 </div>


 <script>
     jQuery(document).ready(function($) {
         // Prepare events data for JavaScript (for calendar click functionality)
         var eventsData = {};
         <?php
            foreach ($events_by_date as $date_key => $date_events) {
                echo "eventsData['" . esc_js($date_key) . "'] = [];\n";
                foreach ($date_events as $event) {
                    // Use _EventStartDate (local) for JavaScript data
                    $event_date = get_post_meta($event->ID, '_EventStartDate', true);
                    if (!$event_date) {
                        $event_date = get_post_meta($event->ID, '_EventStartDateUTC', true);
                    }
                    echo "eventsData['" . esc_js($date_key) . "'].push({\n";
                    echo "  id: " . intval($event->ID) . ",\n";
                    echo "  title: " . json_encode($event->post_title) . ",\n";
                    echo "  date: " . json_encode($event_date) . "\n";
                    echo "});\n";
                }
            }
            ?>

         // Calendar navigation - update via AJAX without page reload
         $(document).on('click', '.calendar-nav.prev-month, .calendar-nav.next-month', function(e) {
             e.preventDefault();
             var month = $(this).data('month');
             var year = $(this).data('year');
             var currentType = getUrlParameter('type') || 'all';
             var $calendarContainer = $('.reminders-calendar-view');

             // Show loading state
             $calendarContainer.addClass('loading');

             // Make AJAX request to update calendar
             $.ajax({
                 url: '<?php echo admin_url('admin-ajax.php'); ?>',
                 type: 'POST',
                 data: {
                     action: 'update_reminders_calendar',
                     month: month,
                     year: year,
                     type: currentType,
                     nonce: '<?php echo wp_create_nonce('reminders_calendar_nonce'); ?>'
                 },
                 success: function(response) {
                     $calendarContainer.removeClass('loading');
                     if (response.success && response.data && response.data.html) {
                         // Replace calendar HTML
                         $calendarContainer.replaceWith(response.data.html);

                         // // Update URL without reload (optional - for better UX)
                         // var newUrl = window.location.pathname;
                         // var params = new URLSearchParams();
                         // params.set('month', month);
                         // params.set('year', year);
                         // if (currentType && currentType !== 'all') {
                         //     params.set('type', currentType);
                         // }
                         // window.history.pushState({}, '', newUrl + '?' + params.toString());
                     } else {
                         console.error('Calendar update failed:', response);
                         alert('Error updating calendar. Please refresh the page.');
                     }
                 },
                 error: function(xhr, status, error) {
                     $calendarContainer.removeClass('loading');
                     console.error('AJAX error:', status, error, xhr.responseText);
                     alert('Error updating calendar. Please refresh the page.');
                 }
             });
         });

         // Helper function to get URL parameter
         function getUrlParameter(name) {
             name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
             var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
             var results = regex.exec(location.search);
             return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
         }

         // Calendar day click handler (optional - can be used for showing event details)
         $('.reminders-calendar-table tbody td.calendar-day:not(.empty)').on('click', function() {
             var date = $(this).data('date');
             // Remove active class from all days
             $('.reminders-calendar-table tbody td').removeClass('active');
             // Add active class to clicked day
             $(this).addClass('active');

             // Optional: Scroll to events for this date or show details
             if (date && eventsData[date] && eventsData[date].length > 0) {
                 // You can add functionality here to highlight or filter occasions
                 console.log('Events on ' + date + ':', eventsData[date]);
             }
         });

         // Handle reminder bell button click - create or delete event
         $(document).on('click', '#pdb-reminders-table .reminder-bell-button', function(e) {
             e.preventDefault();
             e.stopPropagation();

             var $button = $(this);
             var recordId = $button.data('record-id');
             var isActive = $button.hasClass('reminder-active');

             if (!recordId) {
                 alert('Error: Record ID not found.');
                 return;
             }

             // Disable button and show loading state
             $button.prop('disabled', true);
             var originalHtml = $button.html();
             $button.html('<span style="display:inline-block;width:20px;height:20px;border:2px solid #999;border-top-color:transparent;border-radius:50%;animation:spin 0.6s linear infinite;"></span>');

             // Add spinner animation if not already added
             if ($('#reminder-spinner-style').length === 0) {
                 $('head').append('<style id="reminder-spinner-style">@keyframes spin { to { transform: rotate(360deg); } }</style>');
             }

             // Determine action based on button state
             var action = isActive ? 'delete_event_from_contact' : 'create_event_from_contact';
             var nonce = isActive ? '<?php echo wp_create_nonce('delete_event_from_contact_nonce'); ?>' : '<?php echo wp_create_nonce('create_event_from_contact_nonce'); ?>';

             // Send AJAX request
             $.ajax({
                 url: '<?php echo admin_url('admin-ajax.php'); ?>',
                 type: 'POST',
                 data: {
                     action: action,
                     record_id: recordId,
                     security: nonce
                 },
                 success: function(response) {
                     if (response.success) {
                         if (isActive) {
                             // Delete action - update button to inactive state
                             $button.removeClass('reminder-active').addClass('reminder-inactive');
                             $button.attr('data-reminder-status', 'no');
                             $button.attr('title', 'Reminder disabled');
                             $button.attr('aria-label', 'Reminder disabled');

                             // Update SVG to outline grey bell
                             $button.html('<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" stroke="#999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M13.73 21a2 2 0 0 1-3.46 0" stroke="#999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>');

                             // Show success message
                             if (response.data && response.data.message) {
                                 var $successMsg = $('<div class="event-deleted-message" style="position:fixed;top:20px;right:20px;background:#4caf50;color:white;padding:15px 20px;border-radius:4px;z-index:10000;box-shadow:0 2px 10px rgba(0,0,0,0.2);">' + response.data.message + '</div>');
                                 $('body').append($successMsg);
                                 setTimeout(function() {
                                     $successMsg.fadeOut(300, function() {
                                         $(this).remove();
                                     });
                                 }, 3000);
                             }
                         } else {
                             // Create action - update button to active state
                             $button.removeClass('reminder-inactive').addClass('reminder-active');
                             $button.attr('data-reminder-status', 'yes');
                             $button.attr('title', 'Reminder enabled');
                             $button.attr('aria-label', 'Reminder enabled');

                             // Update SVG to filled pink bell
                             $button.html('<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" fill="#ff69b4" stroke="#ff69b4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M13.73 21a2 2 0 0 1-3.46 0" fill="#ff69b4" stroke="#ff69b4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>');

                             // Show success message then redirect to add-reminder page
                             var $successMsg = $('<div class="event-created-message" style="position:fixed;top:20px;right:20px;background:#4caf50;color:white;padding:15px 20px;border-radius:4px;z-index:10000;box-shadow:0 2px 10px rgba(0,0,0,0.2);">Event created successfully!</div>');
                             $('body').append($successMsg);
                             setTimeout(function() {
                                 window.location.href = '<?php echo esc_url( site_url( '/add-reminder/' ) ); ?>';
                             }, 2000);
                         }
                     } else {
                         // Show error message
                         alert(response.data && response.data.message ? response.data.message : 'Error processing request. Please try again.');
                         $button.prop('disabled', false);
                         $button.html(originalHtml);
                     }
                 },
                 error: function(xhr, status, error) {
                     console.error('AJAX error:', status, error, xhr.responseText);
                     alert('Error processing request. Please try again.');
                     $button.prop('disabled', false);
                     $button.html(originalHtml);
                 }
             });
         });

         // Load More Events AJAX handler
         $(document).on('click', '.load-more-events-btn', function(e) {
             e.preventDefault();
             var $button = $(this);
             var currentPage = parseInt($button.data('page')) || 1;
             var nextPage = currentPage + 1;
             var totalEvents = parseInt($button.data('total')) || 0;
             var perPage = parseInt($button.data('per-page')) || 4;
             var eventType = $button.data('type') || 'all';
             var month = getUrlParameter('month') || '<?php echo $current_month; ?>';
             var year = getUrlParameter('year') || '<?php echo $current_year; ?>';

             // Disable button and show loading
             $button.prop('disabled', true);
             var originalText = $button.html();
             $button.html('Loading...');

             $.ajax({
                 url: '<?php echo admin_url('admin-ajax.php'); ?>',
                 type: 'POST',
                 data: {
                     action: 'load_more_reminders_events',
                     page: nextPage,
                     per_page: perPage,
                     type: eventType,
                     month: month,
                     year: year,
                     nonce: '<?php echo wp_create_nonce('load_more_reminders_nonce'); ?>'
                 },
                 success: function(response) {
                     if (response.success && response.data) {
                         // Append new events to grid
                         if (response.data.html) {
                             $('#occasions-grid-container').append(response.data.html);
                         }

                         // Update button or remove if no more events
                         if (response.data.has_more) {
                             var remaining  = response.data.total - response.data.loaded;
                             var nextBatch  = Math.min(perPage, remaining);
                             $button.data('page', nextPage);
                             $button.html('Load More Events (' + nextBatch + ' of ' + remaining + ' remaining)');
                             $button.prop('disabled', false);
                         } else {
                             $button.closest('.load-more-events-container').fadeOut(300, function() {
                                 $(this).remove();
                             });
                         }
                     } else {
                         alert('Error loading more events. Please try again.');
                         $button.prop('disabled', false);
                         $button.html(originalText);
                     }
                 },
                 error: function(xhr, status, error) {
                     console.error('AJAX error:', status, error);
                     console.error('Response:', xhr.responseText);
                     console.error('Status Code:', xhr.status);

                     var errorMessage = 'Error loading more events. Please try again.';
                     if (xhr.responseText) {
                         try {
                             var response = JSON.parse(xhr.responseText);
                             if (response.data && response.data.message) {
                                 errorMessage = response.data.message;
                             }
                         } catch (e) {
                             // Not JSON, use default message
                         }
                     }

                     alert(errorMessage);
                     $button.prop('disabled', false);
                     $button.html(originalText);
                 }
             });
         });
     });
 </script>