<?php
/*
 * Template Name: Reports Export
 * Description: Single page template for exporting various reports as CSV
 */

get_header();

$report_types_arr = get_order_report_types();

$report_type = 'order_report';
if( isset($_GET['report_type']) && !empty($_GET['report_type']) && array_key_exists($_GET['report_type'], $report_types_arr) ){
    $report_type = $_GET['report_type'];
}
?>

<?php if( $report_types_arr ){ ?>
    <div class="reports-wrapper">
        <div class="reports-container">
            <h1>Reports</h1>
            <div class="reportsmessage"></div>

            <label for="report-type">Select Report Type</label>
            <select id="report-type" name="report_type">
                <option value="">Select Report Type</option>
                <?php foreach ($report_types_arr as $key => $value) {
                    $selected = '';
                    if( $report_type == $key ){
                        $selected = ' SELECTED';
                    }
                ?>
                    <option value="<?php echo esc_html($key); ?>"<?php echo $selected; ?>><?php echo esc_html($value); ?></option>
                <?php } ?>
            </select>

            <!-- Form posts to admin-ajax.php and triggers the CSV download -->
            <!-- <form id="report-export-form" method="post" action="<?php //echo esc_url(admin_url('admin-ajax.php')); ?>">
                <input type="hidden" name="action" value="generate_report">
                <?php //wp_nonce_field('reports_export', 'reports_export_nonce'); ?>
                <input type="hidden" name="report_type" id="report_type_input" value="">


                <button type="submit" id="export-report-btn" class="button" style="display:none;">Export</button>
            </form> -->

        </div>
    </div>
    <div class="reports-table-container container">
        <?php if( !empty($report_type) ){
            echo do_shortcode('['.$report_type.']');
        } ?>
    </div>
    <div id="page-loading-overlay">
        <div class="loading-text">Loading report, please wait...</div>
    </div>
<?php } ?>

<?php get_footer(); ?>