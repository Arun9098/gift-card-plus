<?php
/**
 * Template Name: Email Settings
 */
get_header(); ?>


<!-- Frontend Email Template UI -->
<div class="page-spacer-top"></div>
<div class="email-settings-setion">
  <div class="narrow-container">
    <div class="page-title align-left">
      <h1>Email Settings</h1>
    </div>
    <div class="email-settings-wrapper col-6">
      <form id="email-settings-form">
        <div class="email-settings-inner">
          <div class="form-group flex-row">
            <div class="control-wrapper col">
              <label for="select-email" class="label">Select Email</label>
              <select id="select-email" name="email_id" required class="form-control">
                <option value="">Loading...</option>
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
            <div class="control-wrapper col email-input-wrapper">
              <label for="sender-email" class="label">Default Sender Address</label>
              <input type="text" id="sender-email" name="sender_email" class="form-control" placeholder="Enter sender username (e.g., noreply)" maxlength="50">
              <!-- <span class="sender-email-domain">@delivery.giftcardsplus.com.au</span> -->
              <select id="sender-domain" name="sender_domain" class="form-control form-select" style="max-width: 293px;">
                <option value="@delivery.giftcardsplus.com.au">@delivery.giftcardsplus.com.au</option>
                <option value="@giftcardsplus.com.au">@giftcardsplus.com.au</option>
              </select>
            </div>
          </div>
          <!-- <div class="form-group flex-row">
                <div class="control-wrapper col">
                  <label for="email-subject" class="label">Email Subject</label>
                  <input type="text" id="email-subject" name="email_subject" class="form-control">
                </div>
              </div> -->
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
          <div class="form-group">
            <div class="control-wrapper col custom-flex">
              <label>
                Send Test email to:
                <div class="test-email-input-wrap">
                  <input type="email" id="test-email-input" class="link-input"
                    value="noreply@delivery.giftcardsplus.com.au" placeholder="Enter test email address">
                </div>
              </label>
              <button type="button" id="send-test-email" class="btn btn-white btn-black-white btn-primary-black" disabled>Send test
                email</button>
              <div id="test-email-error" style="color: red; margin-top: 5px; display: none;">
                Please enter a valid email address
              </div>
              <div class="success-add-template"></div>
              <div class="error-template"></div>
            </div>
          </div>
        </div>
      </form>
    </div>
    <div class="email-template-code">
      <div class="sub-title">
        <h3>Template Settings</h3>
        <!-- <span id="toggle-view" style="cursor: pointer;"><code>&lt;/&gt;</code></span> -->
      </div>
      <div id="template-editor-container">
        <textarea id="email-template-content" name="email-template-content" class="regular-text" rows="10"></textarea>
      </div>
    </div>
    <div class="page-bottom-toolbar">
      <div class="right-block">
        <button type="submit" id="save-template" class="btn btn-primary btn-black-white btn-primary-black">Save</button>
      </div>
    </div>
  </div>
</div>
</div>
</div>
</div>

<?php get_footer(); ?>