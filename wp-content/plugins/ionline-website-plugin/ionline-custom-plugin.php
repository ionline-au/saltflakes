<?php
/**
 * Plugin Name: iOnline Custom Plugin
 * Plugin URI: https://ionline.com.au/
 * Description: Salt Flakes Order Management
 * Version: 1.0.1
 * Author: iOnline Pty Ltd
 * Author URI: https://ionline.com.au/
 **/

date_default_timezone_set("Australia/Brisbane");

// simple dd function
if (!function_exists('dd')) {
    function dd($data, $exit) {
        if ($_SERVER['REMOTE_ADDR'] == '117.120.9.42'  || $_SERVER['REMOTE_ADDR'] == '179.61.228.141' ) {
            echo '<pre>';
            print_r($data);
            echo '</pre>';
            if ($exit) {
                exit();
            }
        }
    }
}

// add manage orders under the WooCommerce menu
add_action('admin_menu', 'add_woocommerce_menu');
function add_woocommerce_menu()
{
    add_submenu_page('woocommerce', 'Delivery &', 'Delivery & Orders', 'manage_options', 'order-custom-plugin', 'manage_order_dashboard');
}

// add these items under Orders in the WooCoomerce Menu in the admin
add_filter('woocommerce_account_menu_items', 'add_woocommerce_account_menu_items', 10, 1);
function add_woocommerce_account_menu_items($items)
{
    $items['orders'] = __('Orders', 'woocommerce');
    $items['delivery'] = __('Delivery', 'woocommerce');
    return $items;
}

// Orders Export Dashboard
function manage_order_dashboard()
{

    define('BASE_PATH', plugin_dir_path(__FILE__));
    $plugin_url = plugin_dir_url(__FILE__);

    if (!current_user_can('manage_options')) {
        return;
    }

    // Get the active tab from the $_GET param
    $default_tab = null;
    $tab = isset($_GET['tab']) ? $_GET['tab'] : $default_tab;

    ?>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.22/pdfmake.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/0.4.1/html2canvas.min.js"></script>
    <script src="//cdn.rawgit.com/rainabba/jquery-table2excel/1.1.0/dist/jquery.table2excel.min.js"></script>
    <script src="<?php echo $plugin_url ?>/js/fancyTable.min.js"></script>
    <script src="<?php echo $plugin_url ?>/js/tableHTMLExport.js"></script><!-- jQuery Modal -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-modal/0.9.1/jquery.modal.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-modal/0.9.1/jquery.modal.min.css"/>
    <link rel="stylesheet" href="/wp-content/plugins/woocommerce/assets/css/admin.css"/>
    <link rel="stylesheet" href="<?php echo $plugin_url ?>/css/main.css"/>
    <div class="wrap">

        <!-- Print the page title -->
        <h1><?php echo esc_html(get_admin_page_title()); ?> Orders </h1>

        <!-- Here are our tabs -->
        <nav class="nav-tab-wrapper">
            <a href="?page=order-custom-plugin&tab=all" class="nav-tab <?php if ($tab === "all" || !isset($tab)): ?>nav-tab-active<?php endif; ?>">All</a>

            <?php
            // Loop through each tab and display its name
            $date = new DateTime();
            $limit = 6;
            for ($day = 0; $day <= $limit; $day++) {
                $date->add(new DateInterval('P' . $day . 'D'));

                // exclude the days she doesn't deliver
                if (!in_array(date('l', strtotime(date('l', strtotime("+ $day day")))), array('Saturday', 'Sunday'))) {

                    // get a looping day name and the current day name
                    $date_range = date('Y-m-d', strtotime("+ $day day")) . '--' . date('Y-m-d', strtotime("+ $day day"));
                    $looping_day_name = date('l', strtotime('now + ' . $day . ' days'));
                    $now_day_name = date('l', strtotime('now'));
                    ?>
                    <a href="?page=order-custom-plugin&tab=<?php echo $day ?>&date-range=<?php echo $date_range; ?>" class="nav-tab <?php if ($tab == "$day" && $tab != 'all'): ?>nav-tab-active<?php endif; ?>">
                        <?php
                        if ($looping_day_name == $now_day_name) {
                            echo 'Today (' . $looping_day_name . ')';
                        } else {
                            echo $looping_day_name;
                        }
                        ?>
                    </a>
                    <?php
                }
            }
            ?>
        </nav>

        <div class="tab-content">
            <div class="wrap">
                <?php

                $args = array(
                    'limit' => 500,
                );

                if (isset($_GET['date-range']) && isset($_GET['tab']) && $_GET['tab'] == 'all') {

                    $range = explode('--', $_GET['date-range']);
                    $start = date('Y-m-d 00:00:00', strtotime($range[0]));
                    $end = date('Y-m-d 23:59:59', strtotime($range[1]));
                    $args = array(
                        'limit' => 100,
                        'meta_key' => 'delivery_date',
                        'meta_value' => array($start, $end),
                        'meta_compare' => 'BETWEEN'
                    );
                }

                if (isset($_GET['date-range']) && isset($_GET['tab']) && $_GET['tab'] != 'all') {

                    $range = explode('--', $_GET['date-range']);

                    $start_raw = date('Y-m-d', strtotime($range[0]));
                    $end_raw = date('Y-m-d', strtotime($range[1]));

                    $date = new DateTime();
                    $date->add(new DateInterval('P' . $day . 'D'));

                    $check_day = date('l', strtotime(date('l', strtotime($start_raw))));

                    if (in_array($check_day, array('Monday'))) {
                        // $start = date( 'Y-m-d 13:00:00', strtotime( '-3 day', strtotime( $start_raw ) ) );
                        // $end = date( 'Y-m-d 12:59:59', strtotime( $end_raw ) );

                        $start = date('Y-m-d 13:00:01', strtotime('-3 day', strtotime($start_raw)));
                        $end = date('Y-m-d 23:59:59', strtotime($end_raw));

                        $args = array(
                            'limit' => -1,
                            'meta_key' => 'delivery_date',
                            'meta_value' => array($start, $end),
                            'meta_compare' => 'BETWEEN'
                        );
                        //echo "monday start: " . $start . " | ".$end;
                    }


                    if (in_array($check_day, array('Tuesday'))) {
                        // $start = date( 'Y-m-d 13:00:00', strtotime( '-1 day', strtotime( $start_raw ) ) );
                        // $end   = date( 'Y-m-d 12:59:59', strtotime( $end_raw ) );
                        $start = date('Y-m-d 00:00:00', strtotime($start_raw));
                        $end = date('Y-m-d 23:59:59', strtotime($end_raw));
                        $args = array(
                            'limit' => -1,
                            'meta_key' => 'delivery_date',
                            'meta_value' => array($start, $end),
                            'meta_compare' => 'BETWEEN'
                        );
                    }
                    if (in_array($check_day, array('Wednesday'))) {
                        //$start = date( 'Y-m-d 00:00:00', strtotime( '-2 day', strtotime( $start_raw ) ) );
                        //$end   = date( 'Y-m-d 12:59:59', strtotime( $end_raw ) );
                        $start = date('Y-m-d 00:00:00', strtotime($start_raw));
                        $end = date('Y-m-d 23:59:59', strtotime($end_raw));
                        $args = array(
                            'limit' => -1,
                            'meta_key' => 'delivery_date',
                            'meta_value' => array($start, $end),
                            'meta_compare' => 'BETWEEN'
                        );
                    }

                    if (in_array($check_day, array('Thursday'))) {
                        //$start = date( 'Y-m-d 00:00:00', strtotime( '-2 day', strtotime( $start_raw ) ) );
                        //$end   = date( 'Y-m-d 12:59:59', strtotime( $end_raw ) );
                        $start = date('Y-m-d 00:00:00', strtotime($start_raw));
                        $end = date('Y-m-d 23:59:59', strtotime($end_raw));
                        $args = array(
                            'limit' => -1,
                            'meta_key' => 'delivery_date',
                            'meta_value' => array($start, $end),
                            'meta_compare' => 'BETWEEN'
                        );
                    }

                    if (in_array($check_day, array('Friday'))) {
                        // $start = date( 'Y-m-d 00:00:00', strtotime( '-1 day', strtotime( $start_raw ) ) );
                        // $end   = date( 'Y-m-d 12:59:59', strtotime( $end_raw ) );
                        $start = date('Y-m-d 00:00:00', strtotime($start_raw));
                        $end = date('Y-m-d 23:59:59', strtotime($end_raw));
                        $args = array(
                            'limit' => -1,
                            'meta_key' => 'delivery_date',
                            'meta_value' => array($start, $end),
                            'meta_compare' => 'BETWEEN'
                        );
                    }

                }

                $orders = wc_get_orders($args);

                if (isset($_GET['tab']) && $_GET['tab'] != 'all') {

                    $range = explode('--', $_GET['date-range']);
                    echo '<h3>Delivery  : ' . date('F j, Y', strtotime($range[0])) . '</h3>';
                }

                ?>

                <?php /*if (isset($start)) { ?>
					<h4>Cut-off : <?php echo date('F j, Y @ h:iA (l)', strtotime($start)); ?>- <?php echo date('F j, Y @ h:iA (l)', strtotime($end)); ?></h4>
                <?php }*/ ?>

                <p style="display: block;float: left;margin 20px 0 20px 0px;width:100%">Total Selected Orders: <span id="total_orders">0</span></p>
                <form id="export_form" method="post" style="margin-bottom: -45px !important;">
                    <input type="hidden" name="action" id="action" value="">
                    <input type="hidden" name="option" id="option" value="">
                    <label class="dropdown" id="export_csv_dd">
                        <div class="dd-button">
                            Export
                        </div>
                        <input type="hidden" id="order_ids" name="order_ids">
                        <input type="checkbox" class="dd-input" name="export_csv" id="export_csv" value="">
                        <ul class="dd-menu">
                            <li>Order Summary CSV</li>
                            <li>Order Line Items CSV</li>
                            <li>Product Tally CSV</li>
                            <li>Product Tally (With Properties) CSV</li>
                        </ul>
                    </label>
                    <label class="dropdown" id="export_pdf_dd">
                        <div class="dd-button2">
                            Print
                        </div>
                        <input type="checkbox" class="dd-input2" name="export_pdf" id="export_pdf" value="">
                        <ul class="dd-menu2">
                            <li>Pick Slip</li>
                            <li>Delivery Slip</li>
                            <li>Invoice</li>
                            <li>Delivery Manifest</li>
                            <li>Delivery Summary</li>
                            <li>Product Tally</li>
                        </ul>
                    </label>
                </form>
                <form id="date_search" class=" actions " method="get" autocomplete="off" action="/wp-admin/admin.php?page=drive-programs/drive-programs.php" style="margin-right:0;padding-right:0;">
                    <div class="tablenav top alignright " style="margin-bottom: 20px">
                        <!--<h3 style="display: inline-block" class="alignleft">Total Products: <?php /*echo count($orders)*/ ?></h3>-->
                        <input type="hidden" name="page" value="order-custom-plugin"/>
                        <?php /*if ($tab == "all" || !isset($tab)) { */?><!--
							<div id="reportrange" class="search-box" style="overflow: hidden;position: relative; padding: 5px 10px;padding-right:0px;">
								<input placeholder="Select start and end date" type="text" id="date-range" value="<?php /*echo isset($_GET['date-range']) ? $_GET['date-range'] : "" */?>" role="presentation" autocomplete="off" name="date-range" autoComplete='none' style="width: 250px;">
								<i class="fa fa-calendar" style="font-size: 22px;"></i>
							</div>
                        <?php /*} else { */?>
							<div id="" class="search-box" style="overflow: hidden;position: relative; padding: 5px 10px;padding-right:0px;display:none">
								<input type="text" value="<?php /*echo explode("-", $_GET['date-range'])[0] */?>" role="presentation" style="width: 150px;text-align: center">
								<i class="fa fa-calendar" style="font-size: 22px;"></i>
							</div>
                        --><?php /*}*/ ?>
                    </div>
                </form>
                <table id="order_table" class="wp-list-table widefat fixed striped table-view-list product" style="position:relative;">
                    <thead>
                    <tr>
                        <th style="width: 1%;text-align: center">#</th>
                        <th style="width: 2%;text-align: center"><input type="checkbox" id="select_all" style="margin-left:-5px !important;margin-top:1px;">
                        </th>

                        <th style="width: 4%;text-align: center">Order #</th>
                        <th style="width: 5%;text-align: left">Order Status</th>
                        <th style="width: 20%">Customer Details</th>
                        <th style="width: 6%">Items</th>
                        <th style="width: 6%">Created</th>
                        <th style="width: 6%">Delivery Date</th>
                        <th style="width: 5%">Total</th>
                        <th style="width: 5%">Payment Status</th>
                    </tr>
                    </thead>
                    <?php $i = 1; // +($promobrand->page_size == 200?100:0); ?>
                    <tbody id="" data-wp-lists="list:comment" data-test="<?php ?>">
                    <?php
                    $ctr_item = 1;
                    if ($orders):
                        foreach ($orders as $order):

                            $order_id = $order->get_id();

                            $user_id = get_post_meta($order_id, '_customer_user', true);
                            $deliver_date = get_post_meta($order_id, 'delivery_date', true);

                            $customer = new WC_Customer($user_id);
                            if ($_SERVER["REMOTE_ADDR"] == "154.6.147.71") {
                                /*echo "<pre>";
                                //print_r($order_id);
                                echo "</pre>";*/
                            }
                            $status = $order->get_status();
                            if ($customer->get_billing_company() !== null) {
                                $billing_company = $customer->get_billing_company();
                            }

                            $billing_address_1 = $customer->get_billing_address_1();
                            $billing_address_2 = $customer->get_billing_address_2();
                            $billing_city = $customer->get_billing_city();
                            $billing_state = $customer->get_billing_state();
                            $billing_postcode = $customer->get_billing_postcode();
                            $billing_country = $customer->get_billing_country();
                            $billing_email = $customer->get_billing_email();

                            $full_name = $customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name();
                            $billing_phone = $customer->get_billing_phone();

                            $contact_address = '
                            <p>
                                <strong>Name: </strong>' . $full_name . '</strong><br>
                                <strong>Company: </strong>' . $billing_company . '</strong><br>
                                <strong>Address: </strong>' . $billing_address_1 . ' ' . $billing_city . ' ' . $billing_state . ' ' . $billing_postcode . ' ' . $billing_country . '<br>
                                <strong>Email: </strong>' . $billing_email . '<br>
                                <strong>Phone: </strong>' . $billing_phone . '
                            </p>
                            ';
                            $products = "";
                            $item_quantity = 0;
                            $item_total = 0.0;
                            $payment_status = $order->get_date_paid();
                            $sku = "";
                            foreach ($order->get_items() as $item_id => $item) {

                                // Get an instance of corresponding the WC_Product object
                                $product = $item->get_product();

                                $product_name = $item->get_name(); // Get the item name (product name)
                                $item_quantity += $item->get_quantity(); // Get the item quantity
                                $item_total = $item->get_total(); // Get the item line total discounted
                                $product = $item->get_product();
                                if ($product) {
                                    $sku = $product->get_sku();
                                }

                                // Displaying this data (to check)
                                $products .= '
									<p><strong>Product Name: </strong>' . $product_name . '<br/>
                                     <strong>Quantity: </strong> ' . $item_quantity . ' |
                                     <strong>Item total: </strong>$' . number_format($item_total, 2) . '</p>
                                 ';

                            }

                            ?>
                            <tr id="<?php echo $order_id; ?>">
                                <td><?php echo $ctr_item?></td>
                                <td style="text-align: center">
                                    <input type="checkbox" name="selected-orders[]" id="selected-orders" value="<?php echo $order_id; ?>">
                                </td>
                                <td>
                                    <a href="/wp-admin/post.php?post=<?php echo $order_id ?>&action=edit"><?php echo $order_id ?></a>
                                </td>
                                <td>
                                    <mark class="order-status status-<?php echo $status; ?> tips">
                                        <span><?php echo ucwords($status) ?></span></mark>
                                </td>
                                <td>
                                    <?php echo $contact_address ?>
                                </td>
                                <td>
                                    <p><?php echo $item_quantity . " item(s)" ?></p>
                                </td>
                                <td>
                                    <p><?php echo $order->get_date_created()->format('M j, Y h:i A'); ?></p>
                                </td>
                                <td>
                                    <p><?php echo date("M j, Y ", strtotime($deliver_date)) ?></p>
                                </td>
                                <td>
                                    <p style="font-weight: bold;font-size: 14px">$<?php echo number_format($item_total, 2) ?><p>
                                </td>
                                <td>
                                    <mark class="order-status status-<?php echo $payment_status ? "completed " : "on-hold " ?>">
                                        <span><?php echo $payment_status ? "Paid" : "Unpaid" ?></span>
                                    </mark>
                                </td>
                            </tr>
                            <?php
                            $i++;
                            $ctr_item++;
                        endforeach;
                    endif;
                    ?>
                    </tbody>
                </table>

                <script type="text/javascript">
                    jQuery(document).ready(function ($) {

                        $('#export_csv_dd .dd-menu li').click(function () {
                            $("#action").val("csv");
                            $("#export_csv").val($(this).html());

                            var ids = "";
                            jQuery('input[name="selected-orders[]"]:checked').each(function () {
                                ids += jQuery(this).val() + ",";
                            });

                            $("#order_ids").val(ids);

                            //console.log($("#action").val());
                            $("#export_form").trigger("submit");

                        });

                        $('#export_pdf_dd .dd-menu2 li').click(function () {

                            $("#export_pdf").val($(this).html());
                            $("#action").val("pdf");
                            var ids = "";
                            jQuery('input[name="selected-orders[]"]:checked').each(function () {
                                ids += jQuery(this).val() + ",";
                            });
                            $("#order_ids").val(ids);
                            /* console.log($("#action").val());
                             console.log($(this).html());
                             console.log($("#export_pdf").val());*/
                            $("#export_form").trigger("submit");

                        });

                        $('#reportrange').daterangepicker({
                            "linkedCalendars": false,
                            "autoUpdateInput": false,
                            "autoApply": true,
                            "opens": "right"
                        }, function (start, end) {
                            console.log(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));
                            $('#date-range').val(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));
                            $("#date_search").trigger("submit");
                        });

                        $("#export_orders_csv").click(function (e) {

                            var now = moment();

                            var date_name = moment().format('MMMM-D-YYYY');
                            $("#tbl_export").tableHTMLExport({
                                type: 'csv',
                                filename: date_name + '-orders-list.csv',
                                // for csv
                                separator: ',',
                                newline: '\r\n',
                                trimContent: false,
                                quoteFields: true,
                                ignoreColumns: '5',
                                ignoreRows: '',
                                // your html table has html content?
                                htmlContent: true,
                                consoleLog: false,

                            });

                        })

                        $("#export_orders_pdf").click(function (e) {

                            var now = moment();

                            var date_name = moment().format('MMMM-D-YYYY');

                            html2canvas($('#pdf')[0], {

                                letterRendering: 1,
                                allowTaint: true,
                                onrendered: function (canvas) {
                                    var data = canvas.toDataURL();
                                    var docDefinition = {
                                        content: [{
                                            image: data,
                                            width: 500
                                        }]
                                    };
                                    pdfMake.createPdf(docDefinition).download(date_name + "-orders-details.pdf");
                                }
                            });

                        })

                        jQuery('#import_data').click(function (e) {
                            e.preventDefault();
                            $('.mask').addClass('ajax');
                            var selected_prod = [];
                            jQuery('input[name="selected_prod[]"]:checked').each(function () {
                                jQuery(this).attr("data-cat", jQuery(this).parent().parent().find('select').val())
                                selected_prod.push(jQuery(this).data());

                            });


                            jQuery.ajax({

                                type: 'POST',
                                dataType: "json",
                                url: "/wp-admin/admin-ajax.php",
                                data: {
                                    action: 'insert_product_promobrand',
                                    selected_prod: selected_prod
                                },
                                success: function (response) {
                                    // Handle the response from the server

                                    /* console.log(response.message);
                                     console.log(response);*/
                                    jQuery("#message").addClass("notice notice-success is-dismissible");
                                    jQuery('#message').html("<h3>" + response.message + "</h3>");
                                    $('.mask').removeClass('ajax');

                                    $('html, body').animate({
                                        scrollTop: $("#message").offset().top - 100
                                    }, 2000);
                                    setTimeout(function () {
                                        window.location.reload();
                                    }, 3000);

                                },
                                error: function (jqXHR, textStatus, errorThrown) {
                                    // Handle the error
                                    console.log(response);
                                    jQuery("#message").addClass("notice notice-warning is-dismissible");
                                    JQuery('#message').html(response.message);
                                    setTimeout(function () {
                                        window.location.reload(1);
                                    }, 5000);
                                }
                            });
                        });

                        $("#select_all").change(function () {
                            if (this.checked) {
                                $('#order_table input:checkbox').each(function () {
                                    $(this).prop('checked', true);
                                });
                            } else {
                                $('#order_table input:checkbox').each(function () {
                                    $(this).prop('checked', false);
                                });
                            }
                            if (this.checked) {
                                var i = 0;
                                jQuery('input[name="selected-orders[]"]:checked').each(function () {
                                    i++;
                                });
                                jQuery("#total_orders").html(i);
                            } else {
                                jQuery("#total_orders").html(0);
                            }
                        });

                        jQuery("#select_all").on("click", function (event) {
                            jQuery(this).parent().parent().parent().closest('tbody').nextUntil('tr').find('input[type="checkbox"]').prop('checked', this.checked)
                        })

                        $('input[name="selected-orders[]"]').on('change', function () {

                            var i = 0;
                            jQuery('input[name="selected-orders[]"]:checked').each(function () {
                                i++;
                            });
                            jQuery("#total_orders").html(i);
                        });

                    });
                </script>
            </div>
        </div>
    </div>
    <?php

    function get_product_first_category_name($product)
    {
        $categories = get_the_terms($product->get_id(), 'product_cat');
        if (!empty($categories)) {
            // Return the name of the first category
            return $categories[0]->name;
        }
        return '';
    }

    if (isset($_POST['export_pdf']) && $_POST['action'] == 'pdf') {

        $print_option = $_POST['export_pdf'];
        $order_ids = [];
        foreach (explode(",", $_POST['order_ids']) as $id => $val) {
            if ($val != "") {
                $order_ids[] = $val;
            }
        }

        if ( $print_option == "Pick Slip" ) {

            $pdf_list = [];
            if(count($order_ids) > 1){
                foreach ($order_ids as $order_id) {


                    $order = wc_get_order($order_id);
                    $status = $order->get_status();
                    $order_date = $order->get_date_created();
                    $full_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                    $billing_company = $order->get_billing_company();
                    $billing_address_1 = $order->get_billing_address_1();
                    $billing_address_2 = $order->get_billing_address_2();
                    $billing_city = $order->get_billing_city();
                    $billing_state = $order->get_billing_state();
                    $billing_postcode = $order->get_billing_postcode();
                    $billing_country = $order->get_billing_country();
                    $billing_email = $order->get_billing_email();
                    $billing_phone = $order->get_billing_phone();
                    $customer_details = '<p style="color: #333">Company: </>' . $billing_company . '</p>
                                    <p style="color: #333">Name: ' . $full_name . '</p>
                                    <p style="color: #333">Address:' . $billing_address_1 . ' ' . $billing_city . ' ' . $billing_state . ' ' . $billing_postcode . ' ' . $billing_country . '</p>
                                    <p style="color: #333">Email: ' . $billing_email . '</p>
                                    <p style="color: #333">Phone:' . $billing_phone . '</p>';

                    $payment_status = $order->get_date_paid();
                    $order_items = "";
                    $item_count = 0;
                    $item_total_qty = 0;
                    foreach ($order->get_items() as $item) {


                        $product_name = $item->get_name(); // Get the item name (product name)
                        $item_quantity = $item->get_quantity(); // Get the item quantity
                        //$item_total_amount    += $item->get_total();
                        $item_total_qty += $item->get_quantity();


                        $product = $item->get_product();
                        $sku = $product->get_sku();
                        $order_items .= "<tr>
                                        <td>" . $sku . "</td>
                                        <td>" . $product_name . "</td>
                                        <td style='text-align: center'>" . $item_quantity . "</td>
                                        <td style='text-align: center'><div style='margin:0 auto;text-align:center; padding:10px 5px;border: solid 1px;width: 15px'></div></td></td>
                                        <td></td>
                                    </tr>";
                        $item_count++;

                    }


                    // Fill the template variable


                    $template_file = file_get_contents('/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/templates/pick_slip.html');
                    $template_file = str_replace('%%invoice_no%%', $order_id, $template_file);
                    $template_file = str_replace('%%order_date%%', date('d M Y', strtotime($order_date)), $template_file);
                    $template_file = str_replace('%%order_no%%', $order_id, $template_file);

                    $template_file = str_replace('%%customer_details%%', $customer_details, $template_file);
                    $template_file = str_replace('%%item_details%%', $order_items, $template_file);
                    $template_file = str_replace('%%item_count%%', $item_count, $template_file);
                    $template_file = str_replace('%%item_total_qty%%', $item_total_qty, $template_file);

                    $file_name = "pickslip-" . $order_id . '-pick-slip-' . date('d-m-Y');
                    $save = file_put_contents('/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html', $template_file);
                    //echo $save;
                    //echo '<br/> saved : '.'/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html', $template_file;
                    $cmd = '/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/wkhtmltopdf /home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html /home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.pdf';
                    //echo "<br/> cmd : ".$cmd;
                    $run = shell_exec($cmd);
                    //echo "<br/> run : ".$run;




                    $pdf_list[] = '/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.pdf';
                }
                $file_name = "pickslip-";
                $x = 1;
                foreach ($order_ids as $order_id) {

                    if (count($order_ids) == $x) {
                        $file_name .= "$order_id";
                    } else {
                        $file_name .= "$order_id-";
                    }
                    $x++;
                }

                $final_file_name = "/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/$file_name";

                $cmd = "gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=$final_file_name.pdf ";
                foreach ($pdf_list as $pdf) {

                    $cmd .= $pdf . " ";
                }

                $result = shell_exec($cmd);

                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $file_name . '.pdf"');


                ob_clean();
                flush();
                readfile('/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.pdf');
            }else{
                foreach ($order_ids as $order_id) {

                    $order = wc_get_order($order_id);
                    $status = $order->get_status();
                    $order_date = $order->get_date_created()->format('d M Y');
                    $full_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                    $billing_company = $order->get_billing_company();
                    $billing_address_1 = $order->get_billing_address_1();
                    $billing_address_2 = $order->get_billing_address_2();
                    $billing_city = $order->get_billing_city();
                    $billing_state = $order->get_billing_state();
                    $billing_postcode = $order->get_billing_postcode();
                    $billing_country = $order->get_billing_country();
                    $billing_email = $order->get_billing_email();
                    $billing_phone = $order->get_billing_phone();
                    $customer_details = '
						<p style="color: #333">Company: </>' . $billing_company . '</p>
                        <p style="color: #333">Name: ' . $full_name . '</p>
                        <p style="color: #333">Address:' . $billing_address_1 . ' ' . $billing_city . ' ' . $billing_state . ' ' . $billing_postcode . ' ' . $billing_country . '</p>
                        <p style="color: #333">Email: ' . $billing_email . '</p>
                        <p style="color: #333">Phone:' . $billing_phone . '</p>
                    ';

                    $payment_status = $order->get_date_paid();
                    $order_items = "";
                    $item_count = 0;
                    $item_total_qty = 0;
                    foreach ($order->get_items() as $item) {


                        $product_name = $item->get_name(); // Get the item name (product name)
                        $item_quantity = $item->get_quantity(); // Get the item quantity
                        //$item_total_amount    += $item->get_total();
                        $item_total_qty += $item->get_quantity();


                        $product = $item->get_product();
                        $sku = $product->get_sku();
                        $order_items .= "<tr>
                                        <td>" . $sku . "</td>
                                        <td>" . $product_name . "</td>
                                        <td style='text-align: center'>" . $item_quantity . "</td>
                                        <td style='text-align: center'><div style='margin:0 auto;text-align:center; padding:10px 5px;border: solid 1px;width: 15px'></div></td></td>
                                        <td></td>
                                    </tr>";
                        $item_count++;

                    }

                    // Fill the template variable
                    $template_file = file_get_contents('/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/templates/pick_slip.html');
                    $template_file = str_replace('%%invoice_no%%', $order_id, $template_file);
                    $template_file = str_replace('%%order_date%%', $order_date, $template_file);
                    $template_file = str_replace('%%order_no%%', $order_id, $template_file);
                    $template_file = str_replace('%%customer_details%%', $customer_details, $template_file);
                    $template_file = str_replace('%%item_details%%', $order_items, $template_file);
                    $template_file = str_replace('%%item_count%%', $item_count, $template_file);
                    $template_file = str_replace('%%item_total_qty%%', $item_total_qty, $template_file);

                    $file_name = "Order-" . $order_id . '-pick-slip-' . date('d-m-Y');
                    $save = file_put_contents('/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html', $template_file);
                    //echo $save;
                    //echo '<br/> saved : '.'/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html', $template_file;
                    $cmd = '/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/wkhtmltopdf /home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html /home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.pdf';
                    //echo "<br/> cmd : ".$cmd;
                    $run = shell_exec($cmd);
                    //echo "<br/> run : ".$run;


                    for ($i = 0; $i < 5; $i++) {
                        echo '<iframe src="test_multiple_downfile.php?text=' . $i . '">/iframe>';
                    }

                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="' . $file_name . '.pdf"');


                    ob_clean();
                    flush();

                    readfile('/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.pdf');

                }
            }
        }

        if ( $print_option == "Delivery Slip" ) {
            $pdf_list = [];
            if(count($order_ids) > 1){
                foreach ( $order_ids as $order_id ) {

                    $order             = wc_get_order( $order_id );
                    $status            = $order->get_status();
                    $order_date        = $order->get_date_created();
                    $full_name         = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                    $billing_company   = $order->get_billing_company();
                    $billing_address_1 = $order->get_billing_address_1();
                    $billing_address_2 = $order->get_billing_address_2();
                    $billing_city      = $order->get_billing_city();
                    $billing_state     = $order->get_billing_state();
                    $billing_postcode  = $order->get_billing_postcode();
                    $billing_country   = $order->get_billing_country();
                    $billing_email     = $order->get_billing_email();
                    $billing_phone     = $order->get_billing_phone();
                    $customer_details  = '<p style="color: #333">Company: </>' . $billing_company . '</p>
                                    <p style="color: #333">Name: ' . $full_name . '</p>
                                    <p style="color: #333">Address:' . $billing_address_1 . ' ' . $billing_city . ' ' . $billing_state . ' ' . $billing_postcode . ' ' . $billing_country . '</p>
                                    <p style="color: #333">Email: ' . $billing_email . '</p>
                                    <p style="color: #333">Phone:' . $billing_phone . '</p>';

                    $payment_status    = $order->get_date_paid();
                    $order_items       = "";
                    $item_count        = 0;
                    $item_total_qty    = 0;
                    $item_total_amount = 0;
                    foreach ( $order->get_items() as $item ) {


                        $product_name      = $item->get_name(); // Get the item name (product name)
                        $item_quantity     = $item->get_quantity(); // Get the item quantity
                        $item_total_amount += $item->get_total(); // Get the item line total discounted
                        $item_total_qty    += $item->get_quantity();


                        $product     = $item->get_product();
                        $sku         = $product->get_sku();
                        $order_items .= "<tr>
                                        <td style='text-align: center'>" . $item_quantity . "</td>
                                        <td>" . $product_name . "</td>
                                       
                                    </tr>";

                        $item_count ++;
                    }

                    // Fill the template variable


                    $template_file = file_get_contents( '/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/templates/delivery_slip.html' );
                    $template_file = str_replace( '%%invoice_no%%', $order_id, $template_file );
                    $template_file = str_replace( '%%order_date%%', date( 'd M Y', strtotime( $order_date ) ), $template_file );
                    $template_file = str_replace( '%%order_no%%', $order_id, $template_file );

                    $template_file = str_replace( '%%customer_details%%', $customer_details, $template_file );
                    $template_file = str_replace( '%%item_details%%', $order_items, $template_file );
                    $template_file = str_replace( '%%item_count%%', $item_count, $template_file );
                    $template_file = str_replace( '%%item_total_qty%%', $item_total_qty, $template_file );
                    $template_file = str_replace( '%%item_total_amount%%', $item_total_amount, $template_file );


                    $file_name = "Order-" . $order_id . '-delivery-slip-' . date( 'd-m-Y' );
                    $save      = file_put_contents( '/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html', $template_file );
                    //echo $save;
                    //echo '<br/> saved : '.'/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html', $template_file;
                    $cmd = '/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/wkhtmltopdf /home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html /home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.pdf';
                    //echo "<br/> cmd : ".$cmd;
                    $run = shell_exec( $cmd );
                    //echo "<br/> run : ".$run;
                    $pdf_list[] = '/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.pdf';
                }
                $file_name = "delivery-slip-";
                $x = 1;
                foreach ($order_ids as $order_id) {

                    if (count($order_ids) == $x) {
                        $file_name .= "$order_id";
                    } else {
                        $file_name .= "$order_id-";
                    }
                    $x++;
                }

                $final_file_name = "/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/$file_name";

                $cmd = "gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=$final_file_name.pdf ";
                foreach ($pdf_list as $pdf) {

                    $cmd .= $pdf . " ";
                }

                $result = shell_exec($cmd);

                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $file_name . '.pdf"');


                ob_clean();
                flush();

                readfile('/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.pdf');

            }else{
                foreach ( $order_ids as $order_id ) {

                    $order             = wc_get_order( $order_id );
                    $status            = $order->get_status();
                    $order_date        = $order->get_date_created();
                    $full_name         = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                    $billing_company   = $order->get_billing_company();
                    $billing_address_1 = $order->get_billing_address_1();
                    $billing_address_2 = $order->get_billing_address_2();
                    $billing_city      = $order->get_billing_city();
                    $billing_state     = $order->get_billing_state();
                    $billing_postcode  = $order->get_billing_postcode();
                    $billing_country   = $order->get_billing_country();
                    $billing_email     = $order->get_billing_email();
                    $billing_phone     = $order->get_billing_phone();
                    $customer_details  = '<p style="color: #333">Company: </>' . $billing_company . '</p>
                                    <p style="color: #333">Name: ' . $full_name . '</p>
                                    <p style="color: #333">Address:' . $billing_address_1 . ' ' . $billing_city . ' ' . $billing_state . ' ' . $billing_postcode . ' ' . $billing_country . '</p>
                                    <p style="color: #333">Email: ' . $billing_email . '</p>
                                    <p style="color: #333">Phone:' . $billing_phone . '</p>';

                    $payment_status    = $order->get_date_paid();
                    $order_items       = "";
                    $item_count        = 0;
                    $item_total_qty    = 0;
                    $item_total_amount = 0;
                    foreach ( $order->get_items() as $item ) {


                        $product_name      = $item->get_name(); // Get the item name (product name)
                        $item_quantity     = $item->get_quantity(); // Get the item quantity
                        $item_total_amount += $item->get_total(); // Get the item line total discounted
                        $item_total_qty    += $item->get_quantity();


                        $product     = $item->get_product();
                        $sku         = $product->get_sku();
                        $order_items .= "<tr>
                                        <td style='text-align: center'>" . $item_quantity . "</td>
                                        <td>" . $product_name . "</td>
                                       
                                    </tr>";

                        $item_count ++;
                    }

                    // Fill the template variable


                    $template_file = file_get_contents( '/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/templates/delivery_slip.html' );
                    $template_file = str_replace( '%%invoice_no%%', $order_id, $template_file );
                    $template_file = str_replace( '%%order_date%%', date( 'd M Y', strtotime( $order_date ) ), $template_file );
                    $template_file = str_replace( '%%order_no%%', $order_id, $template_file );

                    $template_file = str_replace( '%%customer_details%%', $customer_details, $template_file );
                    $template_file = str_replace( '%%item_details%%', $order_items, $template_file );
                    $template_file = str_replace( '%%item_count%%', $item_count, $template_file );
                    $template_file = str_replace( '%%item_total_qty%%', $item_total_qty, $template_file );
                    $template_file = str_replace( '%%item_total_amount%%', $item_total_amount, $template_file );


                    $file_name = "Order-" . $order_id . '-delivery-slip-' . date( 'd-m-Y' );
                    $save      = file_put_contents( '/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html', $template_file );
                    //echo $save;
                    //echo '<br/> saved : '.'/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html', $template_file;
                    $cmd = '/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/wkhtmltopdf /home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html /home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.pdf';
                    //echo "<br/> cmd : ".$cmd;
                    $run = shell_exec( $cmd );
                    //echo "<br/> run : ".$run;
                    header( 'Content-Type: application/pdf' );
                    header( 'Content-Disposition: attachment; filename="' . $file_name . '.pdf"' );


                    ob_clean();
                    flush();

                    readfile( '/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.pdf' );
                }
            }
        }

        if ($print_option == "Invoice") {
            $pdf_list = [];
            if (count($order_ids) > 1) {
                foreach ($order_ids as $order_id) {

                    $order = wc_get_order($order_id);


                    if ($order) {
                        $items = $order->get_items();

                        // Function to get the first category name of a product


                        // Sorting the items by category name
                        usort($items, function ($a, $b) {
                            $product_a = $a->get_product();
                            $product_b = $b->get_product();
                            $category_a = get_product_first_category_name($product_a);
                            $category_b = get_product_first_category_name($product_b);
                            return strcmp($category_a, $category_b);
                        });

                        // $items now contains the sorted products
                        // You can process $items as needed
                    }

                    $status = $order->get_status();

                    $order_date = $order->get_date_created();
                    $full_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                    $billing_company = $order->get_billing_company();
                    $billing_address_1 = $order->get_billing_address_1();
                    $billing_address_2 = $order->get_billing_address_2();
                    $billing_city = $order->get_billing_city();
                    $billing_state = $order->get_billing_state();
                    $billing_postcode = $order->get_billing_postcode();
                    $billing_country = $order->get_billing_country();
                    $billing_email = $order->get_billing_email();
                    $billing_phone = $order->get_billing_phone();
                    $customer_details = '<p style="color: #333;margin: 0 0 5px 0!important;line-height: 20px !important;padding: 0 !important">' . $billing_company . '</p>
                                    <p style="color: #333;margin: 0 0 5px 0!important;line-height: 20px !important;padding: 0 !important">' . $full_name . '</p>
                                    <p style="color: #333;margin: 0 0 5px 0!important;line-height: 16px !important;padding: 0 !important">' . $billing_address_1 . ' ' . $billing_city . ' ' . $billing_state . ' ' . $billing_postcode . ' ' . $billing_country . '</p>
                                    <p style="color: #333;margin: 0 0 5px 0!important;line-height: 16px !important;padding: 0 !important">' . $billing_email . '</p>
                                    <p style="color: #333;margin: 0 0 5px 0!important;line-height: 16px !important;padding: 0 !important">' . $billing_phone . '</p>';


                    $order_notes = $order->get_customer_note();
                    $payment_status = $order->get_date_paid();

                    $order_items = "";
                    $item_count = 0;
                    $item_total_qty = 0;
                    $item_total_amount = 0;

                    /* ORDER */
                    $subtotal = $order->get_subtotal();
                    $shipping = $order->get_shipping_total();
                    $tax = $order->get_total_tax();
                    $discount = $order->get_discount_total();
                    $total = $order->get_total();


                    foreach ($order->get_items() as $item) {


                        $product_name = $item->get_name(); // Get the item name (product name)
                        $item_quantity = $item->get_quantity(); // Get the item quantity
                        $item_total_amount += $item->get_total(); // Get the item line total discounted
                        $item_total_qty += $item->get_quantity();
                        $item_subtotal = $item->get_subtotal();
                        $product = $item->get_product();
                        $item_product_price = $product->get_price();

                        $terms = wp_get_post_terms($item->get_product_id(), 'product_cat', array('fields' => 'names'));
                        $terms_list = "";
                        foreach ($terms as $term) {


                            if ($term == count($terms)) {
                                $terms_list .= $term;
                            } else {
                                $terms_list .= $term . ",";

                            }

                        }


                        $sku = $product->get_sku();
                        $order_items .= "<tr>
                                        <td>*" . $product_name . "</td>
                                        <td style='text-align: center'>" . $item_quantity . "</td>
                                        <td style='text-align: center'>$" . $item_product_price . "</td>
                                        <td style='text-align: center'>$" . $item_subtotal . "</td>
                                    </tr>";

                        $item_count++;

                    }

                    // Fill the template variable

                    $delivery_date = date("F j, Y", strtotime(get_post_meta($order_id, 'delivery_date', true)));
                    $template_file = file_get_contents('/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/templates/invoice.html');
                    $template_file = str_replace('%%invoice_no%%', $order_id, $template_file);
                    $template_file = str_replace('%%order_date%%', date('d M Y', strtotime($order_date)), $template_file);
                    $template_file = str_replace('%%order_no%%', $order_id, $template_file);

                    $template_file = str_replace('%%customer_details%%', $customer_details, $template_file);
                    $template_file = str_replace('%%item_details%%', $order_items, $template_file);
                    $template_file = str_replace('%%item_count%%', $item_count, $template_file);
                    $template_file = str_replace('%%item_total_qty%%', $item_total_qty, $template_file);
                    $template_file = str_replace('%%item_total_amount%%', $item_total_amount, $template_file);

                    $template_file = str_replace('%%sub_total%%', $subtotal, $template_file);
                    $template_file = str_replace('%%shipping%%', $shipping, $template_file);
                    $template_file = str_replace('%%tax%%', $tax, $template_file);
                    $template_file = str_replace('%%total%%', $total, $template_file);
                    $template_file = str_replace('%%discount%%', $discount, $template_file);
                    $template_file = str_replace('%%over_all_total%%', $total, $template_file);
                    $template_file = str_replace('%%delivery_date%%', $delivery_date, $template_file);
                    $template_file = str_replace('%%payment_status%%', ($payment_status ? "Paid" : "Unpaid"), $template_file);
                    $template_file = str_replace('%%customer_notes%%', $order_notes, $template_file);

                    $file_name = "invoice-" . $order_id;
                    $save = file_put_contents('/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html', $template_file);
                    //echo $save;
                    //echo '<br/> saved : '.'/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html', $template_file;
                    $cmd = '/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/wkhtmltopdf /home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html /home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.pdf';
                    //echo "<br/> cmd : ".$cmd;
                    $run = shell_exec($cmd);
                    //echo "<br/> run : ".$run;
                    // header( 'Content-Type: application/pdf' );
                    // header( 'Content-Disposition: attachment; filename="' . $file_name . '.pdf"' );
                    //  ob_clean();
                    //  flush();

                    $pdf_list[] = '/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.pdf';


                }

                $file_name = "invoice-";
                $x = 1;
                foreach ($order_ids as $order_id) {

                    if (count($order_ids) == $x) {
                        $file_name .= "$order_id";
                    } else {
                        $file_name .= "$order_id-";
                    }
                    $x++;
                }

                $final_file_name = "/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/$file_name";

                $cmd = "gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=$final_file_name.pdf ";
                foreach ($pdf_list as $pdf) {

                    $cmd .= $pdf . " ";
                }

                $result = shell_exec($cmd);

                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $file_name . '.pdf"');


                ob_clean();
                flush();

                readfile('/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.pdf');
            } else {


                foreach ($order_ids as $order_id) {

                    $order = wc_get_order($order_id);


                    if ($order) {
                        $items = $order->get_items();

                        // Function to get the first category name of a product


                        // Sorting the items by category name
                        usort($items, function ($a, $b) {
                            $product_a = $a->get_product();
                            $product_b = $b->get_product();
                            $category_a = get_product_first_category_name($product_a);
                            $category_b = get_product_first_category_name($product_b);
                            return strcmp($category_a, $category_b);
                        });

                        // $items now contains the sorted products
                        // You can process $items as needed
                    }

                    $status = $order->get_status();

                    $order_date = $order->get_date_created();
                    $full_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                    $billing_company = $order->get_billing_company();
                    $billing_address_1 = $order->get_billing_address_1();
                    $billing_address_2 = $order->get_billing_address_2();
                    $billing_city = $order->get_billing_city();
                    $billing_state = $order->get_billing_state();
                    $billing_postcode = $order->get_billing_postcode();
                    $billing_country = $order->get_billing_country();
                    $billing_email = $order->get_billing_email();
                    $billing_phone = $order->get_billing_phone();
                    $customer_details = '<p style="color: #333;margin: 0 0 5px 0!important;line-height: 20px !important;padding: 0 !important">' . $billing_company . '</p>
                                    <p style="color: #333;margin: 0 0 5px 0!important;line-height: 20px !important;padding: 0 !important">' . $full_name . '</p>
                                    <p style="color: #333;margin: 0 0 5px 0!important;line-height: 16px !important;padding: 0 !important">' . $billing_address_1 . ' ' . $billing_city . ' ' . $billing_state . ' ' . $billing_postcode . ' ' . $billing_country . '</p>
                                    <p style="color: #333;margin: 0 0 5px 0!important;line-height: 16px !important;padding: 0 !important">' . $billing_email . '</p>
                                    <p style="color: #333;margin: 0 0 5px 0!important;line-height: 16px !important;padding: 0 !important">' . $billing_phone . '</p>';


                    $order_notes = $order->get_customer_note();
                    $payment_status = $order->get_date_paid();

                    $order_items = "";
                    $item_count = 0;
                    $item_total_qty = 0;
                    $item_total_amount = 0;

                    /* ORDER */
                    $subtotal = $order->get_subtotal();
                    $shipping = $order->get_shipping_total();
                    $tax = $order->get_total_tax();
                    $discount = $order->get_discount_total();
                    $total = $order->get_total();


                    foreach ($order->get_items() as $item) {


                        $product_name = $item->get_name(); // Get the item name (product name)
                        $item_quantity = $item->get_quantity(); // Get the item quantity
                        $item_total_amount += $item->get_total(); // Get the item line total discounted
                        $item_total_qty += $item->get_quantity();
                        $item_subtotal = $item->get_subtotal();
                        $product = $item->get_product();
                        $item_product_price = $product->get_price();

                        $terms = wp_get_post_terms($item->get_product_id(), 'product_cat', array('fields' => 'names'));
                        $terms_list = "";
                        foreach ($terms as $term) {


                            if ($term == count($terms)) {
                                $terms_list .= $term;
                            } else {
                                $terms_list .= $term . ",";

                            }

                        }


                        $sku = $product->get_sku();
                        $order_items .= "<tr>
                                        <td>*" . $product_name . "</td>
                                        <td style='text-align: center'>" . $item_quantity . "</td>
                                        <td style='text-align: center'>$" . $item_product_price . "</td>
                                        <td style='text-align: center'>$" . $item_subtotal . "</td>
                                        
                                       
                                    </tr>";

                        $item_count++;

                    }

                    // Fill the template variable

                    $delivery_date = date("F j, Y", strtotime(get_post_meta($order_id, 'delivery_date', true)));
                    $template_file = file_get_contents('/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/templates/invoice.html');
                    $template_file = str_replace('%%invoice_no%%', $order_id, $template_file);
                    $template_file = str_replace('%%order_date%%', date('d M Y', strtotime($order_date)), $template_file);
                    $template_file = str_replace('%%order_no%%', $order_id, $template_file);

                    $template_file = str_replace('%%customer_details%%', $customer_details, $template_file);
                    $template_file = str_replace('%%item_details%%', $order_items, $template_file);
                    $template_file = str_replace('%%item_count%%', $item_count, $template_file);
                    $template_file = str_replace('%%item_total_qty%%', $item_total_qty, $template_file);
                    $template_file = str_replace('%%item_total_amount%%', $item_total_amount, $template_file);

                    $template_file = str_replace('%%sub_total%%', $subtotal, $template_file);
                    $template_file = str_replace('%%shipping%%', $shipping, $template_file);
                    $template_file = str_replace('%%tax%%', $tax, $template_file);
                    $template_file = str_replace('%%total%%', $total, $template_file);
                    $template_file = str_replace('%%discount%%', $discount, $template_file);
                    $template_file = str_replace('%%over_all_total%%', $total, $template_file);
                    $template_file = str_replace('%%delivery_date%%', $delivery_date, $template_file);
                    $template_file = str_replace('%%payment_status%%', ($payment_status ? "Paid" : "Unpaid"), $template_file);
                    $template_file = str_replace('%%customer_notes%%', $order_notes, $template_file);

                    $file_name = "invoice-" . $order_id;
                    $save = file_put_contents('/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html', $template_file);
                    //echo $save;
                    //echo '<br/> saved : '.'/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html', $template_file;
                    $cmd = '/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/wkhtmltopdf /home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html /home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.pdf';
                    //echo "<br/> cmd : ".$cmd;
                    $run = shell_exec($cmd);
                    //echo "<br/> run : ".$run;
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="' . $file_name . '.pdf"');
                    ob_clean();
                    flush();


                    readfile('/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.pdf');

                }

            }

        }

        if ($print_option == "Delivery Manifest") {
            echo "Delivery Manifest";

            $table_list = ' <table class="item-table" style="width: 100%;border: solid 1px" cellpadding="0" cellspacing="0" >
                    <thead>
                    <th  style="text-align: left;width:50%"><strong>Customer </strong></th>
                    <th  style="text-align: left;width:15%"><strong>QTY </strong></th>
                    <th  style="text-align: left;width:35%"><strong>ITEM</strong> </th>


                    </thead>
                    <tbody>';

            $order_items = "";
            $no_orders = count($order_ids);
            $x = 0;
            $orders_array = [];
            foreach ($order_ids as $order_id) {


                $order = wc_get_order($order_id);
                $status = $order->get_status();
                $order_date = $order->get_date_created();
                $full_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                $billing_company = $order->get_billing_company();
                $billing_address_1 = $order->get_billing_address_1();
                $billing_address_2 = $order->get_billing_address_2();
                $billing_city = $order->get_billing_city();
                $billing_state = $order->get_billing_state();
                $billing_postcode = $order->get_billing_postcode();
                $billing_country = $order->get_billing_country();
                $billing_email = $order->get_billing_email();
                $billing_phone = $order->get_billing_phone();

                $orders_array['customer']['name'][$full_name] = $full_name;
                $customer_details = '<p style="color: #333">Company: </>' . $billing_company . '</p>
                                    <p style="color: #333">Name: ' . $full_name . '</p>
                                    <p style="color: #333">Address:' . $billing_address_1 . ' ' . $billing_city . ' ' . $billing_state . ' ' . $billing_postcode . ' ' . $billing_country . '</p>
                                    <p style="color: #333">Order : ' . ($x + 1) . ' of ' . $no_orders . '</p>
                                    <p style="color: #333">Order Number:' . $order_id . '</p>';

                $payment_status = $order->get_date_paid();

                $item_count = 0;
                $item_total_qty = 0;
                $item_total_amount = 0;

                /* ORDER
                $subtotal = $order->get_subtotal();
                $shipping = $order->get_shipping_total();
                $tax = $order->get_total_tax();
                $discount = $order->get_discount_total();
                $total     = $order->get_total();*/


                $order_items .= "<tr>
                                    <td style='padding-left: 20px'>" . $customer_details . "</td>
                                    <td colspan='2' style='vertical-align: top' valign='top'>
                                        <table  class='striped' style='width:100%;vertical-align: top'>";
                $item_count = count($order->get_items());
                $item_qty = 0;
                foreach ($order->get_items() as $item) {

                    $orders_array['customer']['products'][$item->get_name()] = $item->get_name();

                    $product_name = $item->get_name(); // Get the item name (product name)
                    $item_quantity = $item->get_quantity(); // Get the item quantity

                    $order_items .= "<tr>
                                         <td style='text-align: center;width: 30%;text-align: left;border-bottom:0px'>" . $item_quantity . "</td>
                                         <td style='text-align: center;width: 70%;text-align: left;border-bottom:0px'>" . $product_name . "</td>   
                                      </tr>";
                    $item_qty += $item->get_quantity();


                }
                $order_items .= "    </table>
                                  </td>
                                </tr>
                                ";


                $if_last = ($no_orders == $x ? "" : "border-bottom: 2px #333333 solid");
                $order_items .= '
                                        <tr style="' . $if_last . '">
                                                <td  colspan="3" style="padding-left: 20px">
                                                <strong>ITEM TOTAL: ' . $item_count . '</strong>
                                                <strong style="margin-left: 20px">ITEM QUANTITY: ' . $item_qty . '</strong>
                                                </td>
                                        </tr>
                                  ';
                $x++;
            }

            $table_list .= $order_items . '</table>';

            $order_summary = "<h4>$no_orders Orders | " . count($orders_array['customer']['name']) . " Customers | " . count($orders_array['customer']['products']) . " Products</h4>";

            $template_file = file_get_contents('/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/templates/delivery_manifest.html');


            $template_file = str_replace('%%table_list%%', $table_list, $template_file);
            $template_file = str_replace('%%order_summary%%', $order_summary, $template_file);

            $file_name = "orders_delivery_manifest_" . date('d_m_Y_H_i_s');
            $save = file_put_contents('/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html', $template_file);
            //echo $save;
            //echo '<br/> saved : '.'/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html', $template_file;
            $cmd = '/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/wkhtmltopdf /home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html /home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.pdf';
            //echo "<br/> cmd : ".$cmd;
            $run = shell_exec($cmd);
            //echo "<br/> run : ".$run;
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $file_name . '.pdf"');


            ob_clean();
            flush();

            readfile('/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.pdf');


        }

        if ($print_option == "Delivery Summary") {

            $table_list = ' <table class="item-table" style="width: 100%;border: solid 1px" cellpadding="0" cellspacing="0" >
                    <thead>
                    <th  style="text-align: center;width:5%"><strong>#</strong></th>
                    <th  style="text-align: center;width:10%"><strong>Order No </strong></th>
                    <th  style="text-align: left;width:15%"><strong>Customer Name </strong></th>
                    <th  style="text-align: left;width:15%"><strong>Items </strong></th>
                    <th  style="text-align: left;width:12%"><strong>Value</strong> </th>
                    <th  style="text-align: left;width:12%"><strong>Delivery Time</strong> </th>
                    <th  style="text-align: left;width:12%"><strong>Temperature</strong> </th>
                    <th  style="text-align: left;width:12%"><strong>Received By</strong> </th>
                    <th  style="text-align: left;width:12%"><strong>Signature</strong> </th>
                    </thead>
                    <tbody>';

            $order_items = "";
            $no_orders = count($order_ids);
            $x = 0;
            $orders_array = [];
            $deliver_date = "";
            //$deliver_time = "";
            foreach ($order_ids as $order_id) {


                $order = wc_get_order($order_id);
                $status = $order->get_status();
                $order_date = $order->get_date_created();
                $full_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                $billing_company = $order->get_billing_company();
                $billing_address_1 = $order->get_billing_address_1();
                $billing_address_2 = $order->get_billing_address_2();
                $billing_city = $order->get_billing_city();
                $billing_state = $order->get_billing_state();
                $billing_postcode = $order->get_billing_postcode();
                $billing_country = $order->get_billing_country();
                $billing_email = $order->get_billing_email();
                $billing_phone = $order->get_billing_phone();

                $orders_array['customer']['name'][$full_name] = $full_name;

                $deliver_date = get_post_meta($order_id, 'delivery_date', true);

                //$deliver_time = get_post_meta( $order_id, 'delivery_time', true );

                $order_items .= "<tr>
                                    <td style='text-align: center' >" . ($x + 1) . "</td>
                                    <td style='text-align: center' >" . $order_id . "</td>
                                    <td >" . $billing_company . '-' . $full_name . "</td>
                                ";

                $item_count = count($order->get_items());
                $item_qty = 0;
                $item_product_price = 0.0;
                $total = $order->get_total();
                foreach ($order->get_items() as $item) {

                    $orders_array['customer']['products'][$item->get_name()] = $item->get_name();

                    $product_name = $item->get_name(); // Get the item name (product name)
                    $item_qty += $item->get_quantity(); // Get the item quantity

                    $product = $item->get_product();
                    $item_product_price = $product->get_price();
                }
                $order_items .= "<td style=''>" . $item_qty . "</td>
                                 <td style=''>$" . $total . "</td>
                                 <td>&nbsp;</td>   
                                 <td>&nbsp;</td>   
                                 <td>&nbsp;</td>   
                                 <td>&nbsp;</td>   
                                      ";
                $order_items .= "</tr>";


                $x++;
            }


            $if_last = ($no_orders == $x ? "" : "border-bottom: 2px #333333 solid");

            $table_list .= $order_items . '</table>';


            $order_summary = "<h4 >$no_orders Orders | " . count($orders_array['customer']['name']) . " Customers | " . count($orders_array['customer']['products']) . " Products </h4>";
            $delivery = "<span style='text-align: right;width: 100%'>Delivery Date: " . date("F j, Y", strtotime($deliver_date)) . "</span>";


            $template_file = file_get_contents('/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/templates/delivery_summary.html');


            $template_file = str_replace('%%table_list%%', $table_list, $template_file);
            $template_file = str_replace('%%order_summary%%', $order_summary, $template_file);
            $template_file = str_replace('%%delivery%%', $delivery, $template_file);


            $file_name = "orders_delivery_summary_" . date('d_m_Y_H_i_s');
            $save = file_put_contents('/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html', $template_file);
            //echo $save;
            //echo '<br/> saved : '.'/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html', $template_file;
            $cmd = '/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/wkhtmltopdf -O landscape /home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html /home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.pdf';
            //echo "<br/> cmd : ".$cmd;
            $run = shell_exec($cmd);
            //echo "<br/> run : ".$run;
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $file_name . '.pdf"');


            ob_clean();
            flush();

            readfile('/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.pdf');


        }

        if ($print_option == "Product Tally") {

            $table_list = ' <table class="item-table" style="width: 100%;border: solid 1px" cellpadding="0" cellspacing="0" >
                    <thead>
                    <th  style="text-align: left;width:20%"><strong>Category </strong></th>
                    <th  style="text-align: left;width:40%"><strong>ITEM</strong> </th>
                    <th  style="text-align: left;width:15%"><strong>SKU</strong></th>
                    <th  style="text-align: left;width:15%"><strong>QTY</th>

                    </thead>
                    <tbody>';

            $order_items = "";
            $no_orders = count($order_ids);
            $x = 1;
            $orders_array = [];
            $category_array = [];

            foreach ($order_ids as $order_id) {


                $order = wc_get_order($order_id);
                $full_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                $orders_array['customer']['name'][$full_name] = $full_name;
                $product_name = $item->get_name();
                foreach ($order->get_items() as $item_id => $item) {

                    $orders_array['customer']['products'][$product_name] = $product_name;
                    $terms = wp_get_post_terms($item->get_product_id(), 'product_cat', array('fields' => 'names'));
                    $product_name = $item->get_name();
                    $orders_array['products'][$product_name]['quantity'] += $item->get_quantity();
                    $orders_array['products'][$product_name]['sku'] = $sku;
                    $ctr = 0;
                    foreach ($terms as $term) {
                        $product = $item->get_product();
                        $sku = $product->get_sku();

                        if ($term == count($terms)) {
                            $orders_array['products'][$product_name]['cat'] .= $term;
                        } else {
                            $orders_array['products'][$product_name]['cat'] .= $term . ",";

                        }
                        $selected_term = get_term_by('name', $term, 'product_cat');

                        if ($selected_term->parent) {
                            $category_array['products'][$selected_term->name][$product_name]['quantity'] += $item->get_quantity();
                            $category_array['products'][$selected_term->name][$product_name]['sku'] = $sku;
                        }

                        $ctr++;
                    }
                }


                $x++;
            }


            $no_items = count($orders_array['products']);


            $qty = 0;
            foreach ($category_array['products'] as $category => $products) {
                if ($category) {
                    foreach ($products as $product_name => $item) {
                        $table_list .= "<tr '>
                                 <td style=''>" . $category . "</td>
                                 <td style=''>" . $product_name . "</td>
                                 <td style=''>" . $item['sku'] . "</td>
                                 <td style=''>" . $item['quantity'] . "</td>";
                        $table_list .= "</tr>";
                        $qty += $item['quantity'];
                    }

                }

            }
            $table_list .= "<tr style='border:solid 1px #333333'>
                                 <td></td>
                                 <td ></td>
                                 <td ><strong>TOTAL</strong></td>
                                 <td ><strong>" . $qty . "</strong></td>";
            $table_list .= "</tr>";


            $order_summary = "<h4>$no_orders Orders | " . count($orders_array['customer']['name']) . " Customers | " . count($orders_array['customer']['products']) . " Products</h4>";

            $template_file = file_get_contents('/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/templates/product_tally.html');


            $template_file = str_replace('%%table_list%%', $table_list, $template_file);
            $template_file = str_replace('%%order_summary%%', $order_summary, $template_file);

            echo $template_file;


            $file_name = "product_tally_" . date('d_m_Y_H_i_s');
            $save = file_put_contents('/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html', $template_file);
            //  echo $save;
            //echo '<br/> saved : '.'/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html', $template_file;
            $cmd = '/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/wkhtmltopdf /home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.html /home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.pdf';
            // echo "<br/> cmd : ".$cmd;
            $run = shell_exec($cmd);
            //echo "<br/> run : ".$run;


            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $file_name . '.pdf"');


            ob_clean();
            flush();

            readfile('/home/saltflakes/webapps/saltflakes/wp-content/plugins/ionline-website-plugin/files/' . $file_name . '.pdf');


        }

    }

    if (isset($_POST['export_csv']) && $_POST['action'] == 'csv') {

        $print_option = $_POST['export_csv'];
        $order_ids = [];
        foreach (explode(",", $_POST['order_ids']) as $id => $val) {
            if ($val != "") {
                $order_ids[] = $val;
            }
        }

        if ($print_option == "Order Summary CSV") {
            $order_ids = [];

            foreach (explode(",", $_POST['order_ids']) as $id => $val) {
                if ($val != "") {
                    $order_ids[] = $val;
                }
            }

            ob_clean(); //empty the output buffer
            ob_start();
            ob_end_clean();
            // Prepare the CSV file
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="orders-summary-' . date("F-y-j-h-i-s") . '.csv"');

            // Open the file

            $file = fopen('php://output', 'w');

            // Add the headers
            fputcsv($file,
                array(
                    "State"
                ,
                    "Customer",
                    "CustomerId",
                    "CustomerAddress1",
                    "CustomerAddress2",
                    "CustomerSuburb",
                    "CustomerState",
                    "CustomerPostCode",
                    "CustomerCountry",
                    "CustomerDefaultPaymentMethod",
                    "OrderNumber",
                    "OrderDate",
                    "DeliveryDate",
                    "PaidDate",
                    "PaidDateTime",
                    "InvoicePaymentMethod",
                    "Notes",
                    "TotalCost",
                    "TotalGST",
                    "TotalPaid",
                    "TotalDue",
                    "TotalFreight",
                    "ProductSKUs",
                    "ProductNames"
                )
            );


            // Loop through the order IDs and add the order data
            foreach ($order_ids as $order_id) {
                if ($order_id) {
                    // Get the order
                    $order = wc_get_order($order_id);

                    $order = wc_get_order($order_id);
                    $status = $order->get_status();
                    $order_date = $order->get_date_created();
                    $user_id = $order->get_user_id();
                    $full_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                    $billing_company = $order->get_billing_company();
                    $billing_address_1 = $order->get_billing_address_1();
                    $billing_address_2 = $order->get_billing_address_2();
                    $billing_city = $order->get_billing_city();
                    $billing_state = $order->get_billing_state();
                    $billing_postcode = $order->get_billing_postcode();
                    $billing_country = $order->get_billing_country();
                    $billing_email = $order->get_billing_email();
                    $billing_phone = $order->get_billing_phone();

                    $payment_method_title = $order->get_payment_method_title();
                    $payment_date = $order->get_date_paid();
                    $payment_method = $order->get_payment_method();

                    $notes = $order->get_customer_note();

                    /* ORDER */
                    $subtotal = $order->get_subtotal();
                    $shipping = $order->get_shipping_total();
                    $tax = $order->get_total_tax();
                    $discount = $order->get_discount_total();
                    $total = $order->get_total();

                    // Check if the order exists
                    $sku = "";
                    $x = 1;
                    $item_product_name = "";
                    foreach ($order->get_items() as $item) {

                        $product = $item->get_product();

                        if ($x == count($order->get_items())) {
                            $sku .= $product->get_sku();
                            $item_product_name .= $product->get_name();
                        } else {
                            $sku .= $product->get_sku() . ",";
                            $item_product_name .= $product->get_name() . ",";
                        }
                        $x++;

                    }

                    //$deliver_date = $order->get_meta( 'delivery_date' );
                    //$deliver_time = date( "H:i:s a", strtotime( $order->get_meta( 'delivery_date' ) ) );
                    $delivery_date = date("Y-m-d", strtotime(get_post_meta($order_id, 'delivery_date', true)));
                    if ($order) {
                        fputcsv($file,
                            array(
                                $status,
                                $full_name,
                                $user_id,
                                $billing_address_1,
                                $billing_address_2,
                                $billing_city,
                                $billing_state,
                                $billing_postcode,
                                $billing_country,
                                $payment_method_title,
                                $order_id,
                                $order_date,
                                $delivery_date,
                                $payment_date,
                                date("H:i:s a", strtotime($payment_date)),
                                $payment_method,
                                $notes,
                                $subtotal,
                                $shipping,
                                $tax,
                                $discount,
                                $total,
                                $sku,
                                $product_name,

                            )
                        );
                    }
                }
            }

            // Close the file
            fclose($file);
            exit();

            //send the whole buffer

            // End execution
            // exit;
        }
        if ($print_option == "Order Line Items CSV") {
            $order_ids = [];

            foreach (explode(",", $_POST['order_ids']) as $id => $val) {
                if ($val != "") {
                    $order_ids[] = $val;
                }
            }

            ob_clean(); //empty the output buffer
            ob_start();
            ob_end_clean();
            // Prepare the CSV file
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="orders-line-items-' . date("F-y-j-h-i-s") . '.csv"');

            // Open the file

            $file = fopen('php://output', 'w');

            // Add the headers
            fputcsv($file,
                array(
                    'Name',
                    'SKU',

                    'State',
                    'Customer',
                    'CustomerAddress1',
                    'CustomerAddress2',
                    'CustomerSuburb',
                    'CustomerState',
                    'CustomerPriceGroup',
                    'CustomerVisibilityGroup',
                    'UnitPrice',
                    'Quantity',
                    'Subtotal',
                    'GST',
                    'OrderNumber',
                    'OrderDate',
                    'DueDate',
                    'DeliveryDate',
                    'Notes',
                    'PaidAt'
                ));


            // Loop through the order IDs and add the order data
            $item_list = [];
            foreach ($order_ids as $order_id) {
                if ($order_id) {
                    // Get the order
                    $order = wc_get_order($order_id);

                    $order = wc_get_order($order_id);
                    $status = $order->get_status();
                    $order_date = $order->get_date_created();
                    $user_id = $order->get_user_id();
                    $full_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                    $billing_company = $order->get_billing_company();
                    $billing_address_1 = $order->get_billing_address_1();
                    $billing_address_2 = $order->get_billing_address_2();
                    $billing_city = $order->get_billing_city();
                    $billing_state = $order->get_billing_state();
                    $billing_postcode = $order->get_billing_postcode();
                    $billing_country = $order->get_billing_country();
                    $billing_email = $order->get_billing_email();
                    $billing_phone = $order->get_billing_phone();

                    $payment_method_title = $order->get_payment_method_title();
                    $payment_date = $order->get_date_paid();
                    $payment_method = $order->get_payment_method();

                    $notes = $order->get_customer_note();

                    /* ORDER Charges*/
                    $subtotal = $order->get_subtotal();
                    $shipping = $order->get_shipping_total();
                    $tax = $order->get_total_tax();
                    $discount = $order->get_discount_total();
                    $total = $order->get_total();


                    // Check if the order exists
                    $sku = "";
                    $x = 1;
                    $item_product_name = "";
                    $delivery_date = date("Y-m-d", strtotime(get_post_meta($order_id, 'delivery_date', true)));
                    foreach ($order->get_items() as $item) {

                        $product = $item->get_product();


                        $sku = $product->get_sku();


                        fputcsv($file,
                            array(
                                $product->get_name(),
                                $sku,
                                $status,
                                $billing_company . "-" . $full_name,
                                $billing_address_1,
                                $billing_address_2,
                                $billing_city,
                                $billing_state,
                                $billing_postcode,
                                $billing_country,
                                $product->get_price(),
                                $item->get_quantity(),
                                $item->get_quantity() * $product->get_price(),
                                $tax,
                                $order_id,
                                $order_date,
                                $delivery_date,
                                $notes,
                                $payment_date
                            )
                        );


                        $x++;

                    }


                    //$deliver_time = date( "H:i:s a", strtotime( $order->get_meta( 'delivery_date' ) ) );
                    /*foreach ($item_list as $order) {
                        foreach ($order as $item) {
                            fputcsv($file,
                                array(
                                    $item['Name'],
                                    $item['SKU'],
                                    $item['Quantity'],
                                    $item['State/Status'],
                                    $item['Customer'],
                                    $item['Address1'],
                                    $item['Address2'],
                                    $item['Suburb'],
                                    $item['State'],
                                    $item['PostCode'],
                                    $item['Country'],
                                    $item['unit_price'],
                                    $item['GST'],
                                    $item['OrderNo'],
                                    $item['OrderDate'],
                                    $item['DueDate'],
                                    $item['DeliveryDate'],
                                    $item['Notes'],
                                    $item['PaidAt']
                                )
                            );
                        }
                    }
                    echo "<pre>";
                    print_r($item_list);
                    echo "</pre>";*/

                }
            }

            // Close the file
            fclose($file);
            exit();

            //send the whole buffer

            // End execution
            // exit;
        }

        if ($print_option == "Product Tally CSV") {
            $order_ids = [];

            foreach (explode(",", $_POST['order_ids']) as $id => $val) {
                if ($val != "") {
                    $order_ids[] = $val;
                }
            }

            ob_clean(); //empty the output buffer
            ob_start();
            ob_end_clean();
            // Prepare the CSV file
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="product-tally-' . date("F-y-j-h-i-s") . '.csv"');

            // Open the file

            $file = fopen('php://output', 'w');

            // Add the headers
            fputcsv($file,
                array(
                    'ProductCategory',
                    'ProductName',
                    'ProductSKU',
                    'Quantity',
                    'Weight',

                ));
            // Loop through the order IDs and add the order data

            $x = 0;
            $item_list = [];
            foreach ($order_ids as $order_id) {
                if ($order_id) {
                    // Get the order
                    $order = wc_get_order($order_id);

                    $order = wc_get_order($order_id);

                    foreach ($order->get_items() as $item) {

                        $product = $item->get_product();
                        $sku = $product->get_sku();
                        $item_product_name = $product->get_name();


                        $terms = wp_get_post_terms($item->get_product_id(), 'product_cat', array('fields' => 'names'));
                        $category = "";
                        $ctr = 1;
                        foreach ($terms as $term) {
                            $product = $item->get_product();
                            $sku = $product->get_sku();

                            if ($ctr == count($terms)) {
                                $category .= $term;
                            } else {
                                $category .= $term . ",";

                            }
                            $ctr++;
                        }

                        $item_list[$product->get_id()]['ProductCategory'] = $category;
                        $item_list[$product->get_id()]['ProductName'] = $product->get_name();
                        $item_list[$product->get_id()]['SKU'] = $sku;
                        $item_list[$product->get_id()]['Quantity'] += $item->get_quantity();
                        $item_list[$product->get_id()]['Weight'] += $product->get_weight();


                    }
                    /*echo "<pre>";
                    print_r($item_list);
                    echo "</pre>";
                    return;
                    exit();*/

                    $x++;


                }
            }
            foreach ($item_list as $key => $item) {
                fputcsv($file,
                    array(
                        $item['ProductCategory'],
                        $item['ProductName'],
                        $item['SKU'],
                        $item['Quantity'],
                        $item['Weight'],

                    )
                );
            }

            // Close the file
            fclose($file);
            exit();

            //send the whole buffer

            // End execution
            // exit;
        }

        if ($print_option == "Product Tally (With Properties) CSV") {
            $order_ids = [];

            foreach (explode(",", $_POST['order_ids']) as $id => $val) {
                if ($val != "") {
                    $order_ids[] = $val;
                }
            }

            ob_clean(); //empty the output buffer
            ob_start();
            ob_end_clean();
            // Prepare the CSV file
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="product-tally-' . date("F-y-j-h-i-s") . '.csv"');

            // Open the file

            $file = fopen('php://output', 'w');


            // Add the headers
            fputcsv($file,
                array(
                    'ProductCategory',
                    'ProductName',
                    'ProductSKU',
                    'Quantity',
                    'Weight',
                    'NightPack',
                    'PackingTag',
                    'Sync',

                ));
            // Loop through the order IDs and add the order data

            $x = 0;
            $item_list = [];
            foreach ($order_ids as $order_id) {
                if ($order_id) {
                    // Get the order
                    $order = wc_get_order($order_id);

                    $order = wc_get_order($order_id);

                    foreach ($order->get_items() as $item) {

                        $product = $item->get_product();
                        $sku = $product->get_sku();
                        $item_product_name = $product->get_name();


                        $terms = wp_get_post_terms($item->get_product_id(), 'product_cat', array('fields' => 'names'));
                        $category = "";
                        $ctr = 1;
                        foreach ($terms as $term) {
                            $product = $item->get_product();
                            $sku = $product->get_sku();

                            if ($ctr == count($terms)) {
                                $category .= $term;
                            } else {
                                $category .= $term . ",";

                            }
                            $ctr++;
                        }

                        $item_list[$product->get_id()]['ProductCategory'] = $category;

                        $item_list[$product->get_id()]['ProductName'] = $product->get_name();
                        $item_list[$product->get_id()]['SKU'] = $sku;
                        $item_list[$product->get_id()]['Quantity'] += $item->get_quantity();
                        $item_list[$product->get_id()]['Weight'] += $product->get_weight();


                    }
                    /*echo "<pre>";
                    print_r($item_list);
                    echo "</pre>";
                    return;
                    exit();*/

                    $x++;


                }
            }
            foreach ($item_list as $key => $item) {
                fputcsv($file,
                    array(
                        $item['ProductCategory'],
                        $item['ProductName'],
                        $item['SKU'],
                        $item['Quantity'],
                        $item['Weight'],
                        count($item_list),
                    )
                );
            }

            // Close the file
            fclose($file);
            exit();

            //send the whole buffer

            // End execution
            // exit;
        }
    }

}

// Add Date Delivery Fields
add_action('woocommerce_before_order_notes', 'custom_fields_woo');
function custom_fields_woo($checkout)
{
    echo '<div id="custom_fields" >';
    $days_escape = date('D') == 'Fri' ? 3 : 1;
    woocommerce_form_field(
        'delivery_date',
        array(
            'type' => 'text',
            'required' => true,
            'class' => array('form-row-wide', 'input-time', 'half-width'),
            'label' => 'Delivery Date',
            'id' => 'delivery_date',
            'label_class' => 'date-label',
            'placeholder' => 'dd/mm/yyyy',
            'custom_attributes' => array(
                'min' => date('Y-m-d', strtotime($days_escape.' day', strtotime(date('Y-m-d')))),
                'required' => 'required'
            ),
        ));
    $checkout->get_value('delivery_date');
    echo '</div>';
    return $checkout;
}

add_action('woocommerce_checkout_update_order_meta', 'save_custom_fields_woo');
function save_custom_fields_woo($order_id)
{

    if (!empty($_POST['delivery_date'])) {

        $var = $_POST['delivery_date'];
        $delivery_date = str_replace('/', '-', $var);
        update_post_meta($order_id, 'delivery_date', date("Y-m-d 00:00:00", strtotime($delivery_date)));
    }


}

// Check that the delivery date is not empty when it's selected
if ($_SERVER['REMOTE_ADDR'] == '144.6.113.133') {

    add_action('woocommerce_checkout_process', 'check_datetimepicker_field_matt');
    function check_datetimepicker_field_matt()
    {
        date_default_timezone_set('Australia/Brisbane');

        $_POST['delivery_date'] = '17-10-2025';
        // $hourNow = date('H');
        $hourNow = 14;

        $delivery_date = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['delivery_date'])));
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $today = date('Y-m-d');

        $deliveryDateOfWeekAsN = date('N', strtotime($delivery_date)); // monday is 1, tuesday is 2,  wednesday is 3,  thursday is 4,  friday is 5,  saturday is 6, sunday is 7

        // there has to be a delivery date
        if (empty($_POST['delivery_date'])) {
            wc_add_notice(__('You must enter a delivery/pickup date'), 'error');
        }

        // Validate date format (this example uses d/m/Y format, adjust as needed)
        if (strtotime($delivery_date) === false || strtotime($delivery_date) === 0) {
            wc_add_notice(__('Invalid date - please use this format DD/MM/YYYY example ' . date('01/01/Y')), 'error');
        }

        echo '<pre>';
        print_r('today is ' . date('l \t\h\e jS \o\f F') . ' which is day ' . date('N') . ' of the week');
        echo '<br>';
        print_r('$delivery_date: ' . $delivery_date);
        echo '<br>';
        print_r('$yesterday: ' . $yesterday);
        echo '<br>';
        print_r('$today: ' . $today);
        echo '<br>';
        print_r('$tomorrow: ' . $tomorrow);
        echo '<br>';
        print_r('$deliveryDateOfWeekAsN: ' . $deliveryDateOfWeekAsN);
        echo '<br>';
        print_r('$hourNow: ' . $hourNow);
        echo '<br>';
        print_r('delivery_date_POST: ' . $_POST['delivery_date']);
        echo '<br>';
        print_r('str_$delivery_date: ' . strtotime($delivery_date));
        echo '<br>';
        print_r('str_$yesterday: ' . strtotime($yesterday));
        echo '<br>';
        print_r('full_date: ' . date('d/m/Y H:i:s'));
        echo '<br>';
        print_r('date_date(): ' . date('Y-m-d'));
        echo '<br>';

        // check past delivery dates
        if (strtotime($delivery_date) < strtotime($yesterday)) {
            wc_add_notice(__('You cannot select a date in the past.<br/>Please set the delivery date to a future date '), 'error');
        }

        // disallow saturdays and sundays
        if ($deliveryDateOfWeekAsN == 6 || $deliveryDateOfWeekAsN == 7) {
            echo '<br>';
            print_r('test hit for weekends');
            echo '<br>';
            wc_add_notice(__('You select a ' . date('l', strtotime($delivery_date)) . ' delivery - weekends are not available for delivery.', ''), 'error');
        }

        // if the delivery date is today and it past 1pm


        exit();

    }

} else {

    add_action('woocommerce_checkout_process', 'check_datetimepicker_field');
    function check_datetimepicker_field()
    {
        date_default_timezone_set('Australia/Brisbane');

        // Must have a delivery date
        if (!isset($_POST['delivery_date']) || empty($_POST['delivery_date'])) {
            wc_add_notice(__('You must enter a delivery/pickup date'), 'error');
            return;
        }

        // Parse the delivery date
        $delivery_date_raw = str_replace('/', '-', $_POST['delivery_date']);
        $delivery_date = date('Y-m-d', strtotime($delivery_date_raw));

        // Validate date format
        if (strtotime($delivery_date_raw) === false || strtotime($delivery_date_raw) === 0) {
            wc_add_notice(__('Invalid date - please use this format dd/mm/yyyy example ' . date('01/01/Y')), 'error');
            return;
        }

        // Get current time info
        $hour_now = (int) date('H');
        $today_day_of_week = (int) date('w'); // 0=Sun, 1=Mon, ..., 5=Fri, 6=Sat

        // Get delivery date day of week (using 'w' for consistency: 0=Sun, 6=Sat)
        $delivery_day_of_week = (int) date('w', strtotime($delivery_date));

        // Validate: delivery date cannot be Saturday (6) or Sunday (0)
        if ($delivery_day_of_week == 0 || $delivery_day_of_week == 6) {
            wc_add_notice(__('Weekends are not available for delivery. Please select a weekday (Monday-Friday).'), 'error');
            return;
        }

        /**
         * Calculate minimum allowed delivery date based on:
         * - Friday after 1pm, Saturday, Sunday → Monday is minimum
         * - Weekday after 1pm → +2 days minimum (skip tomorrow)
         * - Before 1pm → tomorrow is minimum
         */
        $min_delivery_date = new DateTime('today', new DateTimeZone('Australia/Brisbane'));

        // Friday after 1pm, Saturday, or Sunday → Monday is minimum
        if (($today_day_of_week == 5 && $hour_now >= 13) || $today_day_of_week == 6 || $today_day_of_week == 0) {
            // Calculate days until next Monday
            if ($today_day_of_week == 5) { // Friday after 1pm
                $min_delivery_date->modify('+3 days');
            } elseif ($today_day_of_week == 6) { // Saturday
                $min_delivery_date->modify('+2 days');
            } else { // Sunday
                $min_delivery_date->modify('+1 day');
            }
        }
        // Weekday (Mon-Thu) after 1pm → +2 days minimum
        elseif ($hour_now >= 13) {
            $min_delivery_date->modify('+2 days');
            // If +2 days lands on Saturday, push to Monday
            if ($min_delivery_date->format('w') == 6) {
                $min_delivery_date->modify('+2 days');
            }
            // If +2 days lands on Sunday, push to Monday
            elseif ($min_delivery_date->format('w') == 0) {
                $min_delivery_date->modify('+1 day');
            }
        }
        // Before 1pm → tomorrow is minimum
        else {
            $min_delivery_date->modify('+1 day');
            // If tomorrow is Saturday, push to Monday
            if ($min_delivery_date->format('w') == 6) {
                $min_delivery_date->modify('+2 days');
            }
            // If tomorrow is Sunday, push to Monday
            elseif ($min_delivery_date->format('w') == 0) {
                $min_delivery_date->modify('+1 day');
            }
        }

        // Validate: delivery date must be on or after minimum allowed date
        $delivery_date_obj = new DateTime($delivery_date, new DateTimeZone('Australia/Brisbane'));
        if ($delivery_date_obj < $min_delivery_date) {
            $min_date_formatted = $min_delivery_date->format('l, F j, Y');
            wc_add_notice(__("Delivery cutoff is 1PM. The earliest available delivery date is {$min_date_formatted}."), 'error');
            return;
        }

        // Validate: check if the selected day is enabled for this customer
        $user_id = get_current_user_id();
        if ($user_id) {
            $day_meta_map = array(
                1 => 'monday',
                2 => 'tuesday',
                3 => 'wednesday',
                4 => 'thursday',
                5 => 'friday'
            );

            if (isset($day_meta_map[$delivery_day_of_week])) {
                $day_meta_key = $day_meta_map[$delivery_day_of_week];
                $day_enabled = get_user_meta($user_id, $day_meta_key, true);

                if ($day_enabled != '1') {
                    $day_name = date('l', strtotime($delivery_date));
                    wc_add_notice(__("{$day_name} is not available for delivery on your account. Please select one of your assigned delivery days."), 'error');
                    return;
                }
            }
        }
    }

}

// Display field value on the order edit page
add_action('woocommerce_admin_order_data_after_billing_address', 'my_custom_checkout_field_display_admin_order_meta', 10, 1);
function my_custom_checkout_field_display_admin_order_meta($order)
{

    // get all the meta data values we need
    $delivery_date = $order->get_meta('delivery_date');
    ?>
    <div class="address">
        <div class="edit_delivery">
            <?php
            woocommerce_wp_text_input(array(
                'id' => 'delivery_date',
                'label' => 'Delivery Date:',
                'value' => date("Y-m-d", strtotime($delivery_date ? $delivery_date : date("Y-m-d", strtotime("+1 day")))),
                'type' => 'text',
                'class' => '',
                'custom_attributes' => array('required' => 'required'),
                'wrapper_class' => 'form-field-wide' // always add this class
            ));
            /*
            woocommerce_wp_text_input( array(
                'id'            => 'delivery_time',
                'label'         => 'Delivery Time:',
                'value'         => date( "H:i", strtotime( $delivery_date ? $delivery_date : "" ) ),
                'type'          => 'hidden',
                'wrapper_class' => 'form-field-wide'
            ));
            */
            ?>
        </div>

        <?php
        if ($user = $order->get_user()) {
            $user_ID = $user->ID;
            $available_del_date = [];
            if ($user_ID) {
                $available_del_date['0'] = '0';
                $available_del_date['1'] = get_the_author_meta('monday', $user_ID) ? get_the_author_meta('monday', $user_ID) : '0';
                $available_del_date['2'] = get_the_author_meta('tuesday', $user_ID) ? get_the_author_meta('tuesday', $user_ID) : '0';
                $available_del_date['3'] = get_the_author_meta('wednesday', $user_ID) ? get_the_author_meta('wednesday', $user_ID) : '0';
                $available_del_date['4'] = get_the_author_meta('thursday', $user_ID) ? get_the_author_meta('thursday', $user_ID) : '0';
                $available_del_date['5'] = get_the_author_meta('friday', $user_ID) ? get_the_author_meta('friday', $user_ID) : '0';
                //$available_del_date['5'] = get_the_author_meta('friday', $user_ID);
                $available_del_date['6'] = '0';
            }
        }
        ?>
        <script>
            jQuery(document).ready(function ($) {

                var available_days = <?php echo json_encode($available_del_date)?>;

                $("#delivery_date").datepicker({
                    dateFormat: 'yy-mm-dd',
                    // maxDate: '+10Y',
                    // minDate: '+0M',
                    changeMonth: true,
                    changeYear: true,
                    // yearRange: "-0:+10",
                    // minDate: '1',
                    beforeShowDay: function (date) {
                        var day = date.getDay();
                        var day_now = date.getDate();
                        var month = date.getMonth() + 1;
                        var year = date.getFullYear();
                        var day_to_check = day_now + "/" + month + "/" + year;

                        <?php
                        $hr = date("H");
                        $tomorrow_num = date('w', strtotime('+ 1 day'));
                        $tomorrow_date = date('j/n/Y', strtotime('+ 1 day'));

                        //get if date is friday
                        $friday = 0;
                        if (date('D') == 'Fri') {
                            $friday = 1;
                        }
                        ?>

                        var tomorrow_date = "<?php echo $tomorrow_date;?>";

                        if (day_to_check.trim() == tomorrow_date.trim()) {
                            // console.log("same date : " + day_to_check);
                            if (<?php echo $hr?> >= 13) {
                                //console.log("time >= 13 : " +<?php echo $hr?>);
                                return [false, ""];
                            }
                        }

                        <?php
                        // Loop all days and set if disable or not
                        //                        foreach ($available_del_date as $key => $value) {
                        //                            if ($value == 1) {
                        //                                echo "if (day == $key) {";
                        //                                echo "return [true, ''];";
                        //                                echo "}";
                        //                            }
                        //                            if ($hr >= 13) {
                        //
                        //                            }
                        //                        }
                        ?>
                        return [true, ""];
                    }
                });

                var d = new Date();
                var hr = d.getHours(); // => 9

                $('#delivery_date').change(function () {
                    var date = new Date($(this).val());
                    const day = date.getDay();
                    var disable_days = "\n";
                    $.each(available_days, function (days, status) {
                        if (status == 0) {
                            switch (days) {
                                case 0:
                                    disable_days += "Sunday\n";
                                    break;
                                case 1:
                                    disable_days += "Monday\n";
                                    break;
                                case 2:
                                    disable_days += "Tuesday\n";
                                    break;
                                case 3:
                                    disable_days += "Wednesday\n";
                                    break;
                                case 4:
                                    disable_days += "Thursday\n";
                                    break;
                                case 5:
                                    disable_days += "Friday\n";
                                    break;
                                case 6:
                                    disable_days += "Saturday\n";
                                    break;

                            }
                        }
                    });
                    if (disable_days.length > 1) {
                        // var disable_days = disable_days.substring(0, disable_days.length - 1);
                    }
                    //console.log(disable_days);

                    $.each(available_days, function (days, status) {
                        if (days == day && status == 0) {
                            // console.log("days = day")
                            // console.log(days +" = "+ day);
                            // console.log("status ="+ status);
                            /* alert("The following days are not available for this customer: " + disable_days);
                             //  console.log(("Delivery is not available on: "+disable_days));
                             $('#delivery_date').val("");*/
                        }
                    });

                });
            });
        </script>
        <?php
        ?>
    </div>
    <?php
}

add_action('woocommerce_process_shop_order_meta', 'save_datetime_delivery');
function save_datetime_delivery($order_id)
{
    update_post_meta($order_id, 'delivery_date', date("Y-m-d H:i:s", strtotime($_POST['delivery_date']/* . " " . $_POST['delivery_time' */)));
}

/* Add custom fields check out CSS*/
add_action('wp_footer', 'custom_function_checkout');
function custom_function_checkout()
{

    $user_ID = get_current_user_id();

    if (is_checkout()) {
        $available_del_date = [];

        if ($user_ID) {
            $available_del_date['0'] = '0';
            $available_del_date['1'] = get_the_author_meta('monday', $user_ID) ? get_the_author_meta('monday', $user_ID) : '0';
            $available_del_date['2'] = get_the_author_meta('tuesday', $user_ID) ? get_the_author_meta('tuesday', $user_ID) : '0';
            // $available_del_date['3'] = '0';
            $available_del_date['3'] = get_the_author_meta('wednesday', $user_ID) ? get_the_author_meta('wednesday', $user_ID) : '0';
            $available_del_date['4'] = get_the_author_meta('thursday', $user_ID) ? get_the_author_meta('thursday', $user_ID) : '0';
            $available_del_date['5'] = get_the_author_meta('friday', $user_ID) ? get_the_author_meta('friday', $user_ID) : '0';
            // $available_del_date['5'] = get_the_author_meta('friday', $user_ID);
            $available_del_date['6'] = '0';

        }


        ?>
        <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css">
        <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.3/jquery.validate.min.js"></script>
        <style type="text/css">
            #custom_fields {
                display: flex;
                flex-direction: row;
                flex-wrap: nowrap;
                align-content: center;
                justify-content: flex-start;
                align-items: center;
            }

            #delivery_time_field span input {
                border: 1px solid #666;
                border-radius: 3px;
                padding: 0.5rem 1rem;
            }
        </style>
        <script>
            jQuery(document).ready(function ($) {

                var available_days = <?php echo json_encode($available_del_date)?>;

                /**
                 * Calculate minimum delivery date dynamically based on:
                 * - Friday after 1pm, Saturday, Sunday → Monday is minimum
                 * - Weekday after 1pm → +2 days (skip tomorrow)
                 * - Before 1pm → tomorrow is minimum
                 * All dates must also skip weekends
                 */
                function getMinDeliveryDate() {
                    var now = new Date();
                    var dayOfWeek = now.getDay(); // 0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat
                    var hour = now.getHours();
                    var minDate = new Date(now);

                    // Reset time to start of day for comparison
                    minDate.setHours(0, 0, 0, 0);

                    // Friday after 1pm, Saturday, or Sunday → Monday is minimum
                    if ((dayOfWeek === 5 && hour >= 13) || dayOfWeek === 6 || dayOfWeek === 0) {
                        var daysUntilMonday;
                        if (dayOfWeek === 5) { // Friday after 1pm
                            daysUntilMonday = 3;
                        } else if (dayOfWeek === 6) { // Saturday
                            daysUntilMonday = 2;
                        } else { // Sunday (dayOfWeek === 0)
                            daysUntilMonday = 1;
                        }
                        minDate.setDate(minDate.getDate() + daysUntilMonday);
                    }
                    // Weekday (Mon-Thu) after 1pm → +2 days minimum (skip tomorrow)
                    else if (hour >= 13) {
                        minDate.setDate(minDate.getDate() + 2);
                        // If +2 days lands on Saturday, push to Monday
                        if (minDate.getDay() === 6) {
                            minDate.setDate(minDate.getDate() + 2);
                        }
                        // If +2 days lands on Sunday, push to Monday
                        else if (minDate.getDay() === 0) {
                            minDate.setDate(minDate.getDate() + 1);
                        }
                    }
                    // Before 1pm → tomorrow is minimum
                    else {
                        minDate.setDate(minDate.getDate() + 1);
                        // If tomorrow is Saturday, push to Monday
                        if (minDate.getDay() === 6) {
                            minDate.setDate(minDate.getDate() + 2);
                        }
                        // If tomorrow is Sunday, push to Monday
                        else if (minDate.getDay() === 0) {
                            minDate.setDate(minDate.getDate() + 1);
                        }
                    }

                    return minDate;
                }

                var minDeliveryDate = getMinDeliveryDate();

                $("#delivery_date").datepicker({
                    dateFormat: 'dd/mm/yy',
                    minDate: minDeliveryDate,
                    beforeShowDay: function (date) {
                        var day = date.getDay(); // 0=Sun, 1=Mon, ..., 6=Sat

                        // Check if this day is enabled for this customer
                        // available_days uses: 0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat
                        if (available_days[day] === '1' || available_days[day] === 1) {
                            return [true, ''];
                        }

                        return [false, ''];
                    }
                });


                $.validator.addMethod("dateITA", function (value, element) {
                    return this.optional(element) || /^(0?[1-9]|[12][0-9]|3[01])\/(0?[1-9]|1[0-2])\/(19|20)\d\d$/.test(value);
                }, "Please enter a valid date in the format dd/mm/yyyy");




                $('#delivery_date').change(function () {
                    var date = new Date($(this).val());
                    const day = date.getDay();

                    var disable_days = "\n";
                    $.each(available_days, function (days, status) {
                        if (status == 0) {
                            switch (days) {
                                case 0:
                                    disable_days += "Sunday\n";
                                    break;
                                case 1:
                                    disable_days += "Monday\n";
                                    break;
                                case 2:
                                    disable_days += "Tuesday\n";
                                    break;
                                case 3:
                                    disable_days += "Wednesday\n";
                                    break;
                                case 4:
                                    disable_days += "Thursday\n";
                                    break;
                                case 5:
                                    disable_days += "Friday\n";
                                    break;
                                case 6:
                                    disable_days += "Saturday\n";
                                    break;

                            }
                        }
                    });
                    if (disable_days.length > 1) {
                        var disable_days = disable_days.substring(0, disable_days.length - 1);
                    }
                    //console.log(disable_days);

                    $.each(available_days, function (days, status) {

                        if (days == day && status == 0) {
                            // console.log("days = day")
                            // console.log(days +" = "+ day);
                            // console.log("status ="+ status);
                            alert("Delivery is not available on: " + disable_days);
                            //  console.log(("Delivery is not available on: "+disable_days));
                            $('#delivery_date').val("");
                        }
                    });

                });
            });

            function formatDate(datestr) {
                var date = new Date(datestr);
                var day = date.getDate();
                day = day > 9 ? day : "0" + day;
                var month = date.getMonth() + 1;
                month = month > 9 ? month : "0" + month;
                return month + "/" + day + "/" + date.getFullYear();
            }
        </script>
        <?php
    }
}

add_action('admin_enqueue_scripts', 'enqueue_modal_window_assets');
function enqueue_modal_window_assets()
{
    // Check that we are on the right screen
    if (get_current_screen()->id == 'order-custom-plugin') {
        // Enqueue the assets
        wp_enqueue_style('thickbox');
        wp_enqueue_script('plugin-install');
    }
}

/* add shortcode for displaying recent products*/
add_shortcode('recently_ordered_products', 'wc_recently_ordered_products');
function wc_recently_ordered_products()
{

    if (is_user_logged_in()) {
        global $post;
        global $woocommerce;

        $post_slug = $post->post_name;
        $user_id = get_current_user_id();
        ?>
        <div class="woo-loop">
            <?php

            if ($post_slug == 'wholesale-ordering' || $post_slug == 'shop' || is_shop()) {
                if (is_user_logged_in()) {

                    $args = array(
                        'numberposts' => - 1,
                        'meta_key'    => '_customer_user',
                        'meta_value'  => $user_id,
                        'post_type'   => wc_get_order_types(),
                        'post_status' => array_keys( wc_get_order_statuses() )
                    );

                    $customer_orders = get_posts( $args );
                    $product_ids     = array();

                    foreach ( $customer_orders as $customer_order ) {
                        $order = wc_get_order( $customer_order->ID );
                        $items = $order->get_items();

                        foreach ( $items as $item ) {
                            $product_ids[] = $item->get_product_id();
                        }
                    }

                    $product_ids = array_unique( $product_ids );



                    if ( ! is_user_logged_in() ) {
                        return '<p>Please log in to view your most ordered products.</p>';
                    }

                    $user_id = get_current_user_id();

                    // Get all orders for the current user
                    $orders = wc_get_orders( array(
                        'customer_id' => $user_id,
                        'status'      => array( 'completed', 'processing', 'on-hold' ),
                        'limit'       => -1,
                    ) );

                    $product_counts = array();

                    // Loop through all orders and count each product
                    foreach ( $orders as $order ) {
                        foreach ( $order->get_items() as $item ) {
                            $product_id = $item->get_product_id();
                            $qty = $item->get_quantity();

                            if ( isset( $product_counts[$product_id] ) ) {
                                $product_counts[$product_id] += $qty;
                            } else {
                                $product_counts[$product_id] = $qty;
                            }
                        }
                    }

                    if (  $product_counts  ==0 ) {
                        echo '<p>No products found in your order history.</p>';
                        return;
                    }

                    // Sort products by quantity (descending)
                    arsort( $product_counts );

                    // Get top 4 product IDs
                    $top_products = array_slice( array_keys( $product_counts ), (count($product_counts) - 4), 4 );


                    $output = '';
                    $x = 1;
                    echo "<p class='recent-title'>Recently Ordered Products</p>";

                    if (count($top_products) >= 1) {

                        echo "<ul class='products elementor-grid columns-3'>";
                        foreach ($top_products as $product_id) {

                            if ($x <= 12) {
                                $product = wc_get_product($product_id);
                                $image = wp_get_attachment_image_src(get_post_thumbnail_id($product_id), 'single-post-thumbnail');
                                ?>
                                <li class="product">
                                    <a href="<?php echo get_permalink($product_id) ?>" class="woocommerce-LoopProduct-link woocommerce-loop-product__link">
                                        <img style="width: 100%" src="<?php echo get_the_post_thumbnail_url($product_id, 'post-thumbnail') ? get_the_post_thumbnail_url($product_id, 'post-thumbnail') : '/wp-content/uploads/woocommerce-placeholder-300x300.png'; ?>" data-id="<?php echo $product_id ?>" />
                                        <h2 class="woocommerce-loop-product__title"><?php echo $product->get_name(); ?></h2>
                                        <?php echo $product->get_price_html(); ?>
                                    </a>

                                    <?php
                                    if (!$product->is_type('variable')) {
                                        echo woocommerce_quantity_input(array(), $product, false);
                                    }
                                    ?>

                                    <div class="woocommerce-loop-product__buttons">
                                        <a href="<?php echo $product->is_type('variable') ? get_permalink($product_id) : do_shortcode('[add_to_cart_url id=' . $product_id . ']') ?>"
                                            <?php if (!$product->is_type('variable')) { ?>
                                                data-product_id="<?php echo $product_id ?>" data-product_sku="<?php echo $product->get_sku(); ?>"aria-label="Add “<?php echo $product->get_name(); ?>” to your cart"aria-describedby="" rel="nofollow"
                                            <?php } ?>
                                           class="button <?php echo $product->is_type('variable') ? 'product_type_variable' : 'product_type_simple' ?> add_to_cart_button ajax_add_to_cart">
                                            <?php
                                            if ($product->is_type('variable')) {
                                                echo 'Select Options';
                                            } else {
                                                echo 'Add to cart';
                                            }
                                            ?>
                                        </a>
                                    </div>  <?php /*?>
                                    <?php */ ?>
                                </li>
                                <?php
                            }

                            $x++;
                        }
                        echo '</ul>';

                    } else {
                        echo "<p class='recent-no-products'>No recent order products found</p>";
                    }

                }

            } ?>
        </div>
        <?php

    }
    else {
        echo "<p>Please log in to see your recently ordered products.</p>";
    }

}

add_action('wp_head', 'custom_style_shop');
function custom_style_shop()
{
    global $post;
    $post_slug = $post->post_name;
    if (is_shop() || $post_slug == "wholesale-ordering" || $post_slug == "shop") {
        ?>
        <script>
            jQuery(document).ready(function ($) {
                console.log("post log :<?php echo $post_slug ?>");
                $(document).on("change", "li.product .quantity input.qty", function (e) {
                    e.preventDefault();
                    console.log($(this).val());
                    var add_to_cart_button = $(this).closest("li.product").find("a.add_to_cart_button");
                    // For AJAX add-to-cart actions.
                    add_to_cart_button.attr("data-quantity", $(this).val());
                    // For non-AJAX add-to-cart actions.
                    add_to_cart_button.attr("href", "?add-to-cart=" + add_to_cart_button.attr("data-product_id") + "&quantity=" + $(this).val());
                });
            });
        </script>
        <style type="text/css">
            <?php

            if($post_slug == "wholesale-ordering"){
            ?>


            <?php } ?>

            .qty.text {
                width: 3.631em;
                text-align: center;
                margin-bottom: 10px;
            }

            .woo-loop ul.products {
                /*display: flex !important;
                flex-wrap: wrap;*/
                grid-column-gap: 20px !important;
                grid-row-gap: 20px !important;
                grid-template-columns: repeat(4, 2fr) !important;
                justify-self: start;
                float: left;
                padding-left: 10px !important;
                overflow: hidden;
            }

            .woo-loop ul.products:before {
                display: none !important;

            }

            .woo-loop ul.products li {
                display: flex !important;
                flex-direction: column;
                margin: 0 3% 1.5% 0 !important;
                width: 100% !important;
                justify-content: space-between;
                gap: 5px;

            }


            .woo-loop ul.products li.product a h2 {
                font-family: "Mulish", Sans-serif !important;
                font-size: 16px !important;
                font-weight: 600;
                line-height: 1em;
                color: #333333;
                padding: 0px 0px;
                margin: 0 0 10px 0;
                text-align: start;
            }

            .recent-title {
                font-size: 28px !important;
                font-family: "Cormorant" !important;
                font-weight: 600 !important;
                color: #333333 !important;
            }

            .woocommerce-Price-amount {
                color: black !important;
            }

            .recent-no-products {
                font-size: 14px !important;
                padding: 20px 0px;
            }

            .added_to_cart.wc-forward {
                font-family: "Mulish", Sans-serif;
                font-size: 14px;
                font-weight: 400;
                text-transform: uppercase;
                letter-spacing: 2.5px;
                padding: 4px 10px;
            }

            .woo-loop ul.products li.product a img {
                width: 100%;
                height: 220px;

            }

            .woo-loop ul.products li.product .woocommerce-loop-product__buttons a {
                color: #ffffff;
                background-color: #A57355;
                font-family: "Mulish", Sans-serif;
                font-size: 13px;
                text-align: center;
                font-weight: 400;
                text-transform: uppercase;
                letter-spacing: 1.2px;
                padding: 10px 10px 10px 10px;
                width: 100%;
                display: inline-block;
                border-radius: 3px !important;
                margin: 0;
                line-height: 1;
            }

            .woo-loop ul.products li.product .woocommerce-loop-product__buttons a.wc-forward {
                color: #ffffff;
                background-color: #A57355;
                font-family: "Mulish", Sans-serif;
                font-size: 14px;
                font-weight: 400;
                text-transform: uppercase;
                letter-spacing: 2.5px;
                padding: 5px;
                display: inline-block;
                border-radius: 3px;
                margin: 0px;
            }

            @media only screen and (max-width: 767px) {
                .woo-loop ul.products {
                    /*display: flex !important;
                    flex-wrap: wrap;*/
                    grid-column-gap: 10px;
                    grid-row-gap: 20px;
                    grid-template-columns: repeat(2, 6fr) !important;
                }
            }

            @media only screen and (min-width: 768px) and  (max-width: 1024px) {
                .woo-loop ul.products {
                    /*display: flex !important;
                    flex-wrap: wrap;*/
                    grid-column-gap: 10px;
                    grid-row-gap: 20px;
                    grid-column-gap: 5px;
                    grid-row-gap: 10px;
                    grid-template-columns: repeat(3, 4fr);
                }
            }

            .ant-btn {
                font-family: "Mulish", Sans-serif;
                font-weight: 400;
                text-transform: uppercase;
                letter-spacing: 2.5px;
                padding: 4px 10px !important;
                font-size: 12px !important;
            }

            .ant-btn:hover, .ant-btn:focus {
                background-color: #6c3300 !important;
                border-color: #6c3300 !important;
            }
        </style>
        <?php
    }
}

// add some css to the header
add_action('wp_head', function () {
    ?>
    <style>
        #delivery_date_field, #delivery_time_field {
            width: 49% !important;
        }

        .body > div.elementor.elementor-417.elementor-location-single.post-35.page.type-page.status-publish.hentry > div > div > div > div > div > div > div > div > div > div > div > nav > ul > li.woocommerce-MyAccount-navigation-link.woocommerce-MyAccount-navigation-link--downloads {
            display: none;
        }

        body > div.elementor.elementor-417.elementor-location-single.post-35.page.type-page.status-publish.hentry > div > div > div > div > div > div > div > div > div > div > div > nav > ul > li.woocommerce-MyAccount-navigation-link.woocommerce-MyAccount-navigation-link--delivery {
            display: none;
        }

        .elementor-kit-5 button, .elementor-kit-5 input[type='button'], .elementor-kit-5 input[type='submit'], .elementor-kit-5 .elementor-button {
            border-radius: 10px;
            padding: 5px !important;
            padding-left: 15px !important;
            padding-right: 15px !important;
            border: none !important;
        }

        input.ant-input.css-eq3tly {
            height: 31px !important;
        }
    </style>
    <?php
});

// add some jquery to the footer
add_action('wp_footer', function () {
    ?>
    <script>
        jQuery(document).ready(function ($) {
            jQuery('#customer_details > div.col-2 > div.woocommerce-additional-fields').prepend("<p style='color:#A57355;font-weight: bold'>Note: The cut-off time is 1 p.m. for next-day delivery</p>");
            jQuery('#customer_details > div.col-2 > div.woocommerce-additional-fields').prepend('<h3>Delivery Date & Time</h3>');

            $(document.body).on('checkout_error', function (a, b) {
                $('.woocommerce-error li').each(function (i) {

                    var id = $(this).attr('data-id');

                    var label = $("label[for='" + id + "'] ").text().replace(/\*/g, '');
                    $("label[for='" + id + "'] ").css("color", "red ");
                    $("label[for='" + id + "'] ").next('span').append('<abbr class="required" style="font-weight: normal;margin: 2px 0" title="required">' + label + 'is required field</abbr>');
                    $("#" + id).css("border-color", "red");
                    $("label[for='" + id + "']").focus();


                });
            });
        });
    </script>
    <?php
});

add_action('init', function () {
    if (is_admin()) {
        if (str_contains($_SERVER['REQUEST_URI'], 'user-edit.php')) {
            ?>
            <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
            <script>
                jQuery(document).ready(function ($) {
                    jQuery('#your-profile > h2:nth-child(5)').hide();
                    jQuery('#your-profile > table:nth-child(6)').hide();
                    jQuery('.yoast-settings').hide();
                    jQuery('#your-profile > h3:nth-child(22)').hide();
                    jQuery('#your-profile > table:nth-child(23)').hide();
                    jQuery('#e-notes').hide();
                    jQuery('#your-profile > h2:nth-child(31)').hide();
                    jQuery('#your-profile > table:nth-child(32)').hide();
                    jQuery('#your-profile > table:nth-child(26)').hide();
                    jQuery('#your-profile > h2:nth-child(11)').hide();
                    jQuery('#your-profile > table:nth-child(12)').hide();
                    jQuery('#your-profile > table:nth-child(10) > tbody > tr.user-url-wrap').hide();
                    jQuery('#your-profile > table:nth-child(10) > tbody > tr.user-facebook-wrap').hide();
                    jQuery('#your-profile > table:nth-child(10) > tbody > tr.user-instagram-wrap').hide();
                    jQuery('#your-profile > table:nth-child(10) > tbody > tr.user-linkedin-wrap').hide();
                    jQuery('#your-profile > table:nth-child(10) > tbody > tr.user-myspace-wrap').hide();
                    jQuery('#your-profile > table:nth-child(10) > tbody > tr.user-pinterest-wrap').hide();
                    jQuery('#your-profile > table:nth-child(10) > tbody > tr.user-soundcloud-wrap').hide();
                    jQuery('#your-profile > table:nth-child(10) > tbody > tr.user-tumblr-wrap').hide();
                    jQuery('#your-profile > table:nth-child(10) > tbody > tr.user-twitter-wrap').hide();
                    jQuery('#your-profile > table:nth-child(10) > tbody > tr.user-youtube-wrap').hide();
                    jQuery('#your-profile > table:nth-child(10) > tbody > tr.user-wikipedia-wrap').hide();
                });
            </script>
            <?php
        }
    }
});

add_filter('manage_edit-shop_order_columns', 'so61375632_add_new_order_admin_column');
function so61375632_add_new_order_admin_column($columns)
{

    unset($columns['order_date']); // remove the default column

    $edit_columns = array_splice($columns, 0, 2);

    $edit_columns['order_custom_date'] = 'Date';

    return array_merge($edit_columns, $columns);

}

add_action('manage_shop_order_posts_custom_column', 'so61375632_add_new_order_admin_column_content', 20, 2);
function so61375632_add_new_order_admin_column_content($column, $order_id)
{

    if ($column == 'order_custom_date') {

        $order = wc_get_order($order_id);

        if (!is_wp_error($order)) {

            echo $order->get_date_created()->format('M j, Y h:i a'); // pass any PHP date format

        }

    }

}