<?php
/**
 * Template Name: SMS Settings
 */
get_header(); ?>

<!-- Frontend SMS Template UI -->
<div class="page-spacer-top"></div>
<div class="sms-settings-setion">
  <div class="narrow-container">
      <div class="page-title align-left">
          <h1>SMS Settings</h1>
        </div>
    <form id="sms-settings-form">
      <div class="sms-settings-inner">
        <div class="col-12 col-md-6">
          <div class="form-group flex-row">
            <div class="control-wrapper col">
                <label for="select-sms" class="label">Select SMS</label>
                <select id="select-sms" name="sms_id" required class="form-control" >
                  <option value="">Select SMS</option>
                </select>
            </div>
         </div>
         <div class="form-group flex-row">
          <div class="control-wrapper col">
              <label for="sender-name" class="label">Default Sender Name</label>
              <input type="text" id="sender-name" name="sender_name" class="form-control">
            </div>
         </div>
         <div class="form-group flex-row">
          <div class="control-wrapper col">
              <label for="sender-number" class="label">Default Number</label>
              <input type="number" id="sender-number" name="sender_number" class="form-control">

            <!-- <label for="sms-subject" style="margin-top: 1rem;">SMS Subject</label>
            <input type="text" id="sms-subject" name="sms_subject" style="width: 100%; padding: 0.5rem;"> -->
            </div>
          </div>
          <div class="form-group flex-row">
              <div class="control-wrapper col">
                <label for="send-trigger" class="label">Send Trigger</label>
                <select id="send-trigger" name="trigger" class="form-control">
                  <option value="">Select Trigger</option>
                  <option value="customer_new_account">User Registration</option>
                  <option value="customer_processing_order">Order Received</option>
                  <option value="customer_completed_order">Order Completed</option>
                </select>
              </div>
          </div>

          <div class="col custom-flex">
            <label>
              Send Test SMS to:
              <div class="test-sms-input-wrap">
                <input type="number" id="test-sms-input" placeholder="Enter test phone number" class="link-input">
              </div>
            </label>
            <button type="button" id="send-test-sms" class="btn btn-white" disabled>Send test SMS</button>
            <div id="test-sms-error" style="color: red; margin-top: 5px; display: none;">
              Please enter a valid phone number
            </div>
          </div>
        </div>

        <div class="col-12 col-md-6">
          <div class="sub-title">
            <h3>Template Settings</h3>
            <span class="icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="19" height="14" viewBox="0 0 19 14" fill="none">
                <path d="M8.45248 13.9907C8.6929 14.041 8.92853 13.8821 8.97789 13.6372L11.5796 0.687188C11.6289 0.440677 11.4729 0.202282 11.2325 0.152004L10.5462 0.00928742C10.3042 -0.0409884 10.0702 0.117947 10.0208 0.362831L7.41916 13.3128C7.36981 13.5593 7.52584 13.7977 7.76625 13.848L8.45248 13.9907Z" fill="black"/>
                <path d="M2.42939 6.99848L5.51651 10.8243C5.67254 11.0173 5.64547 11.3044 5.45601 11.4633L4.91468 11.9158C4.72521 12.0747 4.4434 12.0472 4.28737 11.8525L0.6015 7.2856C0.466167 7.11855 0.466167 6.8769 0.6015 6.70825L4.28737 2.14131C4.4434 1.94832 4.72521 1.91913 4.91468 2.07806L5.45601 2.53054C5.64547 2.68948 5.67413 2.97652 5.5181 3.16952L2.43098 6.99537L2.42939 6.99848Z" fill="black"/>
                <path d="M13.4835 3.17263L16.5706 6.99848L13.4835 10.8243C13.3275 11.0173 13.3545 11.3044 13.544 11.4633L14.0853 11.9158C14.2748 12.0747 14.5566 12.0472 14.7126 11.8525L18.3985 7.2856C18.5338 7.11855 18.5338 6.8769 18.3985 6.70825L14.7126 2.14131C14.5566 1.94832 14.2748 1.91913 14.0853 2.07806L13.544 2.53054C13.3545 2.68948 13.3259 2.97652 13.4819 3.16952L13.4835 3.17263Z" fill="black"/>
                </svg>
            </span>
          </div>
          <div id="template-editor-container">
            <textarea id="sms-template-content" name="sms-template-content" class="regular-text" rows="10"></textarea>
          </div>
        </div>
      </div>
      <div class="page-bottom-toolbar">
            <div class="right-block">
               <button type="submit" class="Save btn btn-primary">Save</button>
            </div>
          </div>
    </form>
  </div>
</div>


<?php get_footer(); ?>
