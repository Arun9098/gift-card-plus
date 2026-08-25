jQuery(document).ready(function($) {
    //console.log(ORDER_REPORT_VAL);
    const VAL     = CLIENT_BILLING_BALANCE_REPORT_VAL;

    const table = $('#client_billing_balance_reportsTable').DataTable({
        columns: [
            { title: "Business Name" },
            { title: "Business ID" },
            { title: "Business Float ID" },
            { title: "Approved for Client Billing (Y/N)" },
            { title: "Business ABN" },
            { title: "Business Website" },
            { title: "Business Team Users IDs? " },
            { title: "Busineess Address Line 1" },
            { title: "Business Address Line 2" },
            { title: "Suburb" },
            { title: "State" },
            { title: "Country" },
            { title: "Postcode" },
            { title: "Business Currency" },
            { title: "Balance" },
            { title: "prepaid_limit" },
        ],
        pageLength: 25, // default rows per page
        lengthChange: true,   // Enable page length dropdown (default is true)
        lengthMenu: [ [10, 25, 50, 100], [10, 25, 50, 100] ],  // Dropdown options
        responsive: true,
        data: [],
        order: [[1, 'desc']],
        dom: 'lBfrtip',        // For buttons and length menu positioning with other controls
        buttons: [
            {
                extend: 'copy',
                exportOptions: {
                    columns: ':visible',
                    format: {
                        header: function (data, columnIdx) {
                            return $('#client_billing_balance_reportsTable thead th').eq(columnIdx).text().trim();
                        }
                    }
                }
            },
            {
                extend: 'csv',
                exportOptions: {
                    columns: ':visible',
                    format: {
                        header: function (data, columnIdx) {
                            return $('#client_billing_balance_reportsTable thead th').eq(columnIdx).text().trim();
                        }
                    }
                }
            },
            {
                extend: 'excel',
                exportOptions: {
                    columns: ':visible',
                    format: {
                        header: function (data, columnIdx) {
                            return $('#client_billing_balance_reportsTable thead th').eq(columnIdx).text().trim();
                        }
                    }
                }
            },
            {
                extend: 'pdf',
                exportOptions: {
                    columns: ':visible',
                    format: {
                        header: function (data, columnIdx) {
                            return $('#client_billing_balance_reportsTable thead th').eq(columnIdx).text().trim();
                        }
                    }
                }
            },
            {
                extend: 'print',
                exportOptions: {
                    columns: ':visible',
                    format: {
                        header: function (data, columnIdx) {
                            return $('#client_billing_balance_reportsTable thead th').eq(columnIdx).text().trim();
                        }
                    }
                }
            }
        ]
    });


    /*const response = await fetch(`${ORDER_REPORT_VAL.rest_url}?page=${page}&per_page=${perPage}`, {
        headers: {
            'X-WP-Nonce': ORDER_REPORT_VAL.nonce
        }
    });*/
    async function fetchClientBillingBuisnesses(page = 1, perPage = 50) {
        try {
            
            const response = await fetch(`${VAL.rest_url}?page=${page}&per_page=${perPage}`, {
                headers: { 'X-WP-Nonce': VAL.wp_rest_nonce }
            });

            if (CLIENT_BILLING_BALANCE_REPORT_VAL.permissions != 1) throw new Error('Not allowed');
            if (!response.ok) throw new Error('Failed to fetch Client Billing Businesses');

            const json = await response.json();
            return json;
        } catch (error) {
            console.error('Error fetching Clietn Billing Businesses:', error);
            return { client_billing_businesses: [], total_client_billing_businesses: 0 };
        }
    }

    async function load_client_billing_businessesForTable() {
        let page = 1;
        const perPage = 50;

        const firstPage = await fetchClientBillingBuisnesses(page, perPage);
        table.clear();
        table.rows.add(firstPage.client_billing_businesses.map(cb => [
            cb.business_name,
            cb.business_id,
            cb.business_float_id,
            cb.approved_for_client_billing_y_n,
            cb.business_abn,
            cb.business_website,
            cb.business_team_users_ids,
            cb.busineess_address_line_1,
            cb.business_address_line_2,
            cb.suburb,
            cb.state,
            cb.country,
            cb.postcode,
            cb.business_currency,
            cb.balance,
            cb.prepaid_limit,
        ])).draw();

        const totalPages = Math.ceil(firstPage.total_client_billing_businesses / perPage);

        // Optional: Fetch more pages automatically (or lazy load on user scroll)
        // Example: Fetch all pages sequentially
        for (let p = 2; p <= totalPages; p++) {
            const pageData = await fetchClientBillingBuisnesses(p, perPage);
            table.rows.add(pageData.client_billing_businesses.map(cb => [
                cb.business_name,
                cb.business_id,
                cb.business_float_id,
                cb.approved_for_client_billing_y_n,
                cb.business_abn,
                cb.business_website,
                cb.business_team_users_ids,
                cb.busineess_address_line_1,
                cb.business_address_line_2,
                cb.suburb,
                cb.state,
                cb.country,
                cb.postcode,
                cb.business_currency,
                cb.balance,
                cb.prepaid_limit,
            ])).draw(false);
        }
    }

    load_client_billing_businessesForTable();
});
