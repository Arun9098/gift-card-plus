<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function display_gift_card_delivery_method()
{
    ?>
    <div class="delivery-method-wrapper">
        <h2 class="delivery-title">Delivery Method
            <span class="edit-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <g clip-path="url(#clip0_1828_37442)">
                        <path
                            d="M3 17.4625V20.5025C3 20.7825 3.22 21.0025 3.5 21.0025H6.54C6.67 21.0025 6.8 20.9525 6.89 20.8525L17.81 9.9425L14.06 6.1925L3.15 17.1025C3.05 17.2025 3 17.3225 3 17.4625ZM20.71 7.0425C21.1 6.6525 21.1 6.0225 20.71 5.6325L18.37 3.2925C17.98 2.9025 17.35 2.9025 16.96 3.2925L15.13 5.1225L18.88 8.8725L20.71 7.0425Z"
                            fill="#505050" />
                    </g>
                    <defs>
                        <clipPath id="clip0_1828_37442">
                            <rect width="24" height="24" fill="white" />
                        </clipPath>
                    </defs>
                </svg>
            </span>
        </h2>

        <div class="delivery-options">
            <!-- Left Section -->
            <div class="delivery-left">
                <div class="delivery-option">
                    <div class="delivery-top-block">
                        <label class="delivery-label">Email</label>
                        <p class="delivery-description">All eGift Cards will be delivered by <a
                                href="mailto:delivery@giftcardsplus.com.au">delivery@giftcardsplus.com.au</a></p>
                    </div>

                    <label class="toggle-switch">
                        <input type="checkbox" id="email-toggle">
                        <span class="slider"></span>
                    </label>

                </div>

                <div class="delivery-option">
                    <div class="delivery-top-block">
                        <label class="delivery-label">SMS</label>
                        <p class="delivery-description">You have added mobiles for 1 of 1 recipients AUD $0.50 delivery fee
                            (exc. GST) per recipient</p>
                    </div>

                    <label class="toggle-switch">
                        <input type="checkbox" id="sms-toggle">
                        <span class="slider"></span>
                    </label>

                </div>
            </div>

            <!-- Right Section -->
            <div class="delivery-right">
                <div class="delivery-option">
                    <div class="delivery-top-block">
                        <label class="delivery-label">Download List</label>
                        <p class="delivery-description">Download List</p>
                    </div>

                    <label class="toggle-switch">
                        <input type="checkbox" id="download-list-toggle">
                        <span class="slider"></span>
                    </label>

                </div>

                <div class="delivery-option">
                    <div class="delivery-top-block">
                        <label class="delivery-label">Trigger Client Send</label>
                        <p class="delivery-description">Download List</p>
                    </div>

                    <label class="toggle-switch">
                        <input type="checkbox" id="trigger-client-toggle">
                        <span class="slider"></span>
                    </label>

                </div>
            </div>
        </div>
    </div>
    <div id="delivery-options-error"></div>

    <div class="schedule-delivery-container">
        <div class="schedule-delivery-header">
            <div class="icon-text">
                <span class="schedule-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path
                            d="M13.2 12.21L15.9 14.91C16.12 15.13 16.23 15.4052 16.23 15.7356C16.23 16.0652 16.12 16.35 15.9 16.59C15.66 16.83 15.3752 16.95 15.0456 16.95C14.7152 16.95 14.43 16.83 14.19 16.59L11.64 14.04C11.36 13.76 11.15 13.45 11.01 13.11C10.87 12.77 10.8 12.4 10.8 12V8.4C10.8 8.06 10.9152 7.7748 11.1456 7.5444C11.3752 7.3148 11.66 7.2 12 7.2C12.34 7.2 12.6252 7.3148 12.8556 7.5444C13.0852 7.7748 13.2 8.06 13.2 8.4V12.21ZM12 2.4C12.34 2.4 12.6252 2.5148 12.8556 2.7444C13.0852 2.9748 13.2 3.26 13.2 3.6C13.2 3.94 13.0852 4.2252 12.8556 4.4556C12.6252 4.6852 12.34 4.8 12 4.8C11.66 4.8 11.3752 4.6852 11.1456 4.4556C10.9152 4.2252 10.8 3.94 10.8 3.6C10.8 3.26 10.9152 2.9748 11.1456 2.7444C11.3752 2.5148 11.66 2.4 12 2.4ZM21.6 12C21.6 12.34 21.4848 12.6248 21.2544 12.8544C21.0248 13.0848 20.74 13.2 20.4 13.2C20.06 13.2 19.7752 13.0848 19.5456 12.8544C19.3152 12.6248 19.2 12.34 19.2 12C19.2 11.66 19.3152 11.3748 19.5456 11.1444C19.7752 10.9148 20.06 10.8 20.4 10.8C20.74 10.8 21.0248 10.9148 21.2544 11.1444C21.4848 11.3748 21.6 11.66 21.6 12ZM12 19.2C12.34 19.2 12.6252 19.3152 12.8556 19.5456C13.0852 19.7752 13.2 20.06 13.2 20.4C13.2 20.74 13.0852 21.0248 12.8556 21.2544C12.6252 21.4848 12.34 21.6 12 21.6C11.66 21.6 11.3752 21.4848 11.1456 21.2544C10.9152 21.0248 10.8 20.74 10.8 20.4C10.8 20.06 10.9152 19.7752 11.1456 19.5456C11.3752 19.3152 11.66 19.2 12 19.2ZM4.8 12C4.8 12.34 4.6852 12.6248 4.4556 12.8544C4.2252 13.0848 3.94 13.2 3.6 13.2C3.26 13.2 2.9748 13.0848 2.7444 12.8544C2.5148 12.6248 2.4 12.34 2.4 12C2.4 11.66 2.5148 11.3748 2.7444 11.1444C2.9748 10.9148 3.26 10.8 3.6 10.8C3.94 10.8 4.2252 10.9148 4.4556 11.1444C4.6852 11.3748 4.8 11.66 4.8 12ZM12 24C10.34 24 8.78 23.6848 7.32 23.0544C5.86 22.4248 4.59 21.57 3.51 20.49C2.43 19.41 1.5752 18.14 0.9456 16.68C0.3152 15.22 0 13.66 0 12C0 10.34 0.3152 8.78 0.9456 7.32C1.5752 5.86 2.43 4.59 3.51 3.51C4.59 2.43 5.86 1.5748 7.32 0.9444C8.78 0.3148 10.34 0 12 0C13.66 0 15.22 0.3148 16.68 0.9444C18.14 1.5748 19.41 2.43 20.49 3.51C21.57 4.59 22.4248 5.86 23.0544 7.32C23.6848 8.78 24 10.34 24 12C24 13.66 23.6848 15.22 23.0544 16.68C22.4248 18.14 21.57 19.41 20.49 20.49C19.41 21.57 18.14 22.4248 16.68 23.0544C15.22 23.6848 13.66 24 12 24ZM12 21.6C14.68 21.6 16.95 20.67 18.81 18.81C20.67 16.95 21.6 14.68 21.6 12C21.6 9.32 20.67 7.05 18.81 5.19C16.95 3.33 14.68 2.4 12 2.4C9.32 2.4 7.05 3.33 5.19 5.19C3.33 7.05 2.4 9.32 2.4 12C2.4 14.68 3.33 16.95 5.19 18.81C7.05 20.67 9.32 21.6 12 21.6Z"
                            fill="#00B67A" />
                    </svg>
                </span>
                <h4>Schedule Delivery</h4>
            </div>
            <span class="accordion-toggle"><i class="fa-solid fa-chevron-up"></i></span>
        </div>
        <div class="apply-schedule-wrapper">
            <input type="checkbox" class="apply-schedule-checkbox" id="apply-schedule-checkbox">
            <label for="apply-schedule-checkbox">Apply Scheduled Date/Time to all selected cards</label>
        </div>
        <div class="schedule-delivery-wrapper open">
            <p class="schedule-note">
                <span class="default-text"><i class="fa-solid fa-circle-info"></i></span> By <strong>default</strong>, all
                orders are delivered immediately upon
                completion.
                If you'd like to schedule a later delivery, please select a preferred time.
            </p>
            <div class="schedule-content">
            </div>
            <div class="schedule-date-container" style="display: none; margin-top: 15px;">
                <label for="schedule-all-datetime">Select Scheduled Date & Time</label>
                <input type="datetime-local" id="schedule-all-datetime" class="form-control" placeholder="Select date and time">
            </div>
            <input type="hidden" id="wp-current-datetime" value="<?php echo esc_attr( current_time('Y-m-d\TH:i') ); ?>">
        </div>
    </div>


    <div class="card-activation-wrapper manual-card-activation-wrapper">
        <h4 class="card-activation-title">Card Activation</h4>
        <div class="activation-expiry-wrapper">
            <div class="form-group flex-row">
                <div class="control-wrapper col col-4">
                    <label for="activation_expiry_type" class="label">Activation Expiry Type<span class="validate">*</span></label>
                    <select id="activation_expiry_type" name="activation_expiry_type">
                        <option value="default" selected>Default</option>
                        <option value="no_activation_expiry">No Activation Expiry</option>
                        <option value="no_activation_needed">No Activation Needed</option>
                        <option value="activation_set_date">Activated by a Set Date</option>
                        <option value="set_period">Activated within a Set Period</option>
                    </select>
                </div>
                <div class="control-wrapper col col-4">
                    <div id="activation_expiry_date_field" style="display: none;">
                        <label for="activation_expiry_date" class="label">Activation Expiry Date(s)<span class="validate">*</span></label>
                        <input type="datetime-local" id="activation-expiry-date" name="activation_expiry_date"
                            class="form-control" value="" min="2025-05-02">
                    </div>
                </div>
                <div class="control-wrapper col col-4">
                    <div id="activation_expiry_period_field" style="display: none;">
                        <label for="activation_expiry_duration" class="label">Activation Expiry Duration(s)<span class="validate">*</span></label>
                        <div class="expiry-input-group">
                            <input type="number" id="activation_expiry_duration" name="activation_expiry_duration" min="1"
                                value="">
                            <select id="activation_expiry_unit" name="activation_expiry_unit">
                                <option value="days">Days</option>
                                <option value="weeks">Weeks</option>
                                <option value="months">Months</option>
                                <option value="years">Years</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="page-bottom-toolbar">
        <div class="right-block right">
            <div class="save-next-button-controls page-bottom-actions">
                <button type="button" class="btn btn-primary btn-black-white btn-primary-black next-btn" id="continue-step">Continue</button>
            </div>
        </div>
    </div>

    <?php
}
?>