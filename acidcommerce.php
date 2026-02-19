<?php
/**
 * Plugin Name: AcidCommerce
 * Plugin URI: https://github.com/deveguru
 * Description: مدیریت پیشرفته محصولات و سفارشات WooCommerce
 * Version: 2.0.0
 * Author: Alireza Fatemi
 * Author URI: https://alirezafatemi.ir
 * Text Domain: acidcommerce
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

class AcidCommerce {
    
    private $batch_size = 100;
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_acid_get_products', [$this, 'get_products_data']);
        add_action('wp_ajax_acid_delete_products', [$this, 'delete_products']);
        add_action('wp_ajax_acid_cleanup_orders', [$this, 'start_cleanup']);
        add_action('wp_ajax_acid_process_order_batch', [$this, 'process_order_batch']);
        add_action('wp_ajax_acid_get_stats', [$this, 'get_stats']);
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'AcidCommerce',
            'AcidCommerce',
            'manage_woocommerce',
            'acidcommerce',
            [$this, 'render_admin_page'],
            'dashicons-chart-area',
            56
        );
    }
    
    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_acidcommerce') return;
        
        wp_add_inline_style('wp-admin', $this->get_css());
        
        wp_enqueue_script('acidcommerce-admin', plugins_url('', __FILE__), ['jquery'], '2.0.0', true);
        wp_add_inline_script('acidcommerce-admin', $this->get_js());
        
        wp_localize_script('acidcommerce-admin', 'acidCommerce', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('acid_nonce')
        ]);
    }
    
    private function get_css() {
        return "
        .acid-container {
            max-width: 100%;
            margin: 20px 20px 20px 0;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .acid-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        .acid-header h1 {
            margin: 0 0 10px 0;
            color: #1e1e1e;
            font-size: 28px;
            font-weight: 700;
        }
        .acid-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e5e7eb;
        }
        .acid-tab {
            padding: 12px 24px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            color: #6b7280;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        .acid-tab:hover {
            color: #374151;
        }
        .acid-tab.active {
            color: #7c3aed;
            border-bottom-color: #7c3aed;
        }
        .acid-tab-content {
            display: none;
        }
        .acid-tab-content.active {
            display: block;
        }
        .acid-form-group {
            margin-bottom: 20px;
        }
        .acid-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3338;
            font-size: 14px;
        }
        .acid-form-group input,
        .acid-form-group select {
            width: 100%;
            max-width: 500px;
            padding: 12px 15px;
            border: 1px solid #dcdcdc;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .acid-form-group input:focus,
        .acid-form-group select:focus {
            border-color: #7c3aed;
            outline: none;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }
        .acid-btn-group {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .acid-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .acid-btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        .acid-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .acid-btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }
        .acid-btn-secondary:hover {
            background: #e5e7eb;
        }
        .acid-btn-danger {
            background: #ef4444;
            color: #fff;
        }
        .acid-btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }
        .acid-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        .acid-alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }
        .acid-alert-success {
            background: #d1fae5;
            border: 1px solid #6ee7b7;
            color: #065f46;
        }
        .acid-alert-error {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }
        .acid-alert-info {
            background: #dbeafe;
            border: 1px solid #93c5fd;
            color: #1e40af;
        }
        .acid-filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .acid-filter-item {
            flex: 1;
            min-width: 200px;
        }
        .acid-filter-item label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 13px;
            color: #374151;
        }
        .acid-filter-item input,
        .acid-filter-item select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
        .acid-table-container {
            overflow-x: auto;
            margin-top: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }
        .acid-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .acid-table thead {
            background: #f9fafb;
        }
        .acid-table th {
            padding: 12px 15px;
            text-align: right;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }
        .acid-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f3f4f6;
        }
        .acid-table tbody tr:hover {
            background: #f9fafb;
        }
        .acid-table tbody tr:last-child td {
            border-bottom: none;
        }
        .acid-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .acid-product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            background: #f3f4f6;
        }
        .acid-product-name {
            font-weight: 600;
            color: #1f2937;
        }
        .acid-sales-count {
            display: inline-block;
            padding: 4px 12px;
            background: #dbeafe;
            color: #1e40af;
            border-radius: 12px;
            font-weight: 600;
            font-size: 13px;
        }
        .acid-sales-count.zero {
            background: #fee2e2;
            color: #991b1b;
        }
        .acid-pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            align-items: center;
        }
        .acid-pagination button {
            padding: 8px 16px;
            border: 1px solid #d1d5db;
            background: #fff;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        .acid-pagination button:hover:not(:disabled) {
            background: #f3f4f6;
        }
        .acid-pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .acid-pagination span {
            font-size: 14px;
            color: #6b7280;
        }
        .acid-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .acid-loading {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }
        .acid-no-data {
            text-align: center;
            padding: 40px;
            color: #9ca3af;
            font-size: 15px;
        }
        .acid-progress {
            margin-top: 25px;
            display: none;
        }
        .acid-progress-bar {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .acid-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s;
            border-radius: 10px;
        }
        .acid-progress-text {
            text-align: center;
            font-size: 13px;
            color: #6b7280;
            font-weight: 500;
        }
        .acid-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .acid-stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            border-radius: 8px;
            color: #fff;
        }
        .acid-stat-label {
            font-size: 13px;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        .acid-stat-value {
            font-size: 28px;
            font-weight: 700;
        }
        .acid-bulk-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 15px;
        }
        .acid-info-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 20px;
            margin-top: 30px;
        }
        .acid-info-box h3 {
            margin: 0 0 12px 0;
            color: #1f2937;
            font-size: 16px;
        }
        .acid-info-box ul {
            margin: 0;
            padding-right: 20px;
            color: #6b7280;
            font-size: 13px;
            line-height: 1.8;
        }
        .acid-search-box {
            margin-bottom: 20px;
        }
        .acid-search-box input {
            width: 100%;
            max-width: 400px;
            padding: 12px 15px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }";
    }
    
    private function get_js() {
        return "
        (function($) {
            let processing = false;
            let currentPage = 1;
            let totalPages = 1;
            let allProducts = [];
            let filteredProducts = [];
            
            $('.acid-tab').on('click', function() {
                const target = $(this).data('tab');
                $('.acid-tab').removeClass('active');
                $(this).addClass('active');
                $('.acid-tab-content').removeClass('active');
                $('#' + target).addClass('active');
            });
            
            $('#load-products-btn').on('click', function() {
                loadProducts();
            });
            
            $('#apply-filters-btn').on('click', function() {
                applyFilters();
            });
            
            $('#search-products').on('input', function() {
                applyFilters();
            });
            
            $('#select-all').on('change', function() {
                $('.product-checkbox').prop('checked', $(this).prop('checked'));
            });
            
            $('#delete-selected-btn').on('click', function() {
                const selected = $('.product-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();
                
                if (selected.length === 0) {
                    showAlert('لطفا حداقل یک محصول انتخاب کنید', 'error');
                    return;
                }
                
                if (!confirm('آیا از حذف ' + selected.length + ' محصول اطمینان دارید؟ این عملیات غیرقابل بازگشت است!')) {
                    return;
                }
                
                deleteProducts(selected);
            });
            
            $(document).on('click', '.delete-single-product', function() {
                const productId = $(this).data('id');
                const productName = $(this).data('name');
                
                if (!confirm('آیا از حذف محصول \"' + productName + '\" اطمینان دارید؟')) {
                    return;
                }
                
                deleteProducts([productId]);
            });
            
            $(document).on('click', '.acid-pagination button', function() {
                const action = $(this).data('action');
                if (action === 'prev' && currentPage > 1) {
                    currentPage--;
                    displayCurrentPage();
                } else if (action === 'next' && currentPage < totalPages) {
                    currentPage++;
                    displayCurrentPage();
                }
            });
            
            function loadProducts() {
                $('#load-products-btn').prop('disabled', true).html('<span class=\"acid-spinner\"></span> در حال بارگذاری...');
                $('#products-table-body').html('<tr><td colspan=\"7\" class=\"acid-loading\">در حال بارگذاری محصولات از دیتابیس...</td></tr>');
                
                $.ajax({
                    url: acidCommerce.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'acid_get_products',
                        nonce: acidCommerce.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            allProducts = response.data.products;
                            filteredProducts = allProducts;
                            updateStats(response.data.stats);
                            applyFilters();
                            showAlert('محصولات با موفقیت بارگذاری شد', 'success');
                        } else {
                            $('#products-table-body').html('<tr><td colspan=\"7\" class=\"acid-no-data\">' + response.data.message + '</td></tr>');
                            showAlert(response.data.message, 'error');
                        }
                    },
                    error: function() {
                        $('#products-table-body').html('<tr><td colspan=\"7\" class=\"acid-no-data\">خطا در بارگذاری محصولات</td></tr>');
                        showAlert('خطا در بارگذاری محصولات', 'error');
                    },
                    complete: function() {
                        $('#load-products-btn').prop('disabled', false).html('بارگذاری مجدد محصولات');
                    }
                });
            }
            
            function applyFilters() {
                if (allProducts.length === 0) {
                    return;
                }
                
                const sortBy = $('#sort_by').val();
                const dateFrom = $('#date_from').val();
                const dateTo = $('#date_to').val();
                const searchTerm = $('#search-products').val().toLowerCase();
                
                filteredProducts = allProducts.filter(function(product) {
                    let match = true;
                    
                    if (searchTerm) {
                        match = match && (
                            product.name.toLowerCase().includes(searchTerm) ||
                            product.sku.toLowerCase().includes(searchTerm)
                        );
                    }
                    
                    if (dateFrom) {
                        const productDate = new Date(product.date_created);
                        const fromDate = new Date(dateFrom);
                        match = match && productDate >= fromDate;
                    }
                    
                    if (dateTo) {
                        const productDate = new Date(product.date_created);
                        const toDate = new Date(dateTo);
                        match = match && productDate <= toDate;
                    }
                    
                    return match;
                });
                
                filteredProducts.sort(function(a, b) {
                    switch(sortBy) {
                        case 'sales_asc':
                            return a.total_sales - b.total_sales;
                        case 'sales_desc':
                            return b.total_sales - a.total_sales;
                        case 'name_asc':
                            return a.name.localeCompare(b.name, 'fa');
                        case 'name_desc':
                            return b.name.localeCompare(a.name, 'fa');
                        case 'price_asc':
                            return a.price_raw - b.price_raw;
                        case 'price_desc':
                            return b.price_raw - a.price_raw;
                        case 'date_asc':
                            return new Date(a.date_created) - new Date(b.date_created);
                        case 'date_desc':
                            return new Date(b.date_created) - new Date(a.date_created);
                        default:
                            return 0;
                    }
                });
                
                currentPage = 1;
                totalPages = Math.ceil(filteredProducts.length / 20);
                displayCurrentPage();
            }
            
            function displayCurrentPage() {
                const perPage = 20;
                const start = (currentPage - 1) * perPage;
                const end = start + perPage;
                const pageProducts = filteredProducts.slice(start, end);
                
                displayProducts(pageProducts);
                updatePagination();
            }
            
            function displayProducts(products) {
                if (products.length === 0) {
                    $('#products-table-body').html('<tr><td colspan=\"7\" class=\"acid-no-data\">محصولی یافت نشد</td></tr>');
                    return;
                }
                
                let html = '';
                products.forEach(function(product) {
                    const salesClass = product.total_sales === 0 ? 'zero' : '';
                    html += '<tr>';
                    html += '<td><input type=\"checkbox\" class=\"product-checkbox acid-checkbox\" value=\"' + product.id + '\"></td>';
                    html += '<td><img src=\"' + product.image + '\" class=\"acid-product-image\" alt=\"' + product.name + '\" onerror=\"this.src=\\'data:image/svg+xml,%3Csvg xmlns=\\'http://www.w3.org/2000/svg\\' width=\\'50\\' height=\\'50\\'%3E%3Crect fill=\\'%23f3f4f6\\' width=\\'50\\' height=\\'50\\'/%3E%3C/svg%3E\\'\"></td>';
                    html += '<td><span class=\"acid-product-name\">' + product.name + '</span></td>';
                    html += '<td>' + product.sku + '</td>';
                    html += '<td><span class=\"acid-sales-count ' + salesClass + '\">' + product.total_sales + '</span></td>';
                    html += '<td>' + product.price + '</td>';
                    html += '<td><button class=\"acid-btn acid-btn-danger delete-single-product\" data-id=\"' + product.id + '\" data-name=\"' + product.name + '\">حذف</button></td>';
                    html += '</tr>';
                });
                $('#products-table-body').html(html);
            }
            
            function updateStats(stats) {
                $('#total-products').text(stats.total_products.toLocaleString('fa'));
                $('#total-sales').text(stats.total_sales.toLocaleString('fa'));
                $('#total-revenue').text(stats.total_revenue.toLocaleString('fa'));
                $('#zero-sales').text(stats.zero_sales.toLocaleString('fa'));
            }
            
            function updatePagination() {
                if (totalPages <= 1) {
                    $('.acid-pagination').html('');
                    return;
                }
                
                const html = '<button data-action=\"prev\" ' + (currentPage === 1 ? 'disabled' : '') + '>قبلی</button>' +
                            '<span>صفحه ' + currentPage.toLocaleString('fa') + ' از ' + totalPages.toLocaleString('fa') + '</span>' +
                            '<button data-action=\"next\" ' + (currentPage === totalPages ? 'disabled' : '') + '>بعدی</button>';
                $('.acid-pagination').html(html);
            }
            
            function deleteProducts(productIds) {
                $('#delete-selected-btn').prop('disabled', true).html('<span class=\"acid-spinner\"></span> در حال حذف...');
                
                $.ajax({
                    url: acidCommerce.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'acid_delete_products',
                        nonce: acidCommerce.nonce,
                        product_ids: productIds
                    },
                    success: function(response) {
                        if (response.success) {
                            showAlert(response.data.message, 'success');
                            loadProducts();
                        } else {
                            showAlert('خطا: ' + response.data.message, 'error');
                        }
                    },
                    error: function() {
                        showAlert('خطا در حذف محصولات', 'error');
                    },
                    complete: function() {
                        $('#delete-selected-btn').prop('disabled', false).html('حذف محصولات انتخاب شده');
                    }
                });
            }
            
            $('#cleanup-form').on('submit', function(e) {
                e.preventDefault();
                
                if (processing) return false;
                
                const timeRange = $('#time_range').val();
                const orderStatus = $('#order_status').val();
                
                if (!timeRange) {
                    showAlert('لطفا بازه زمانی را انتخاب کنید', 'error');
                    return false;
                }
                
                if (!confirm('آیا از پاکسازی سفارشات اطمینان دارید؟ این عملیات غیرقابل بازگشت است!')) {
                    return false;
                }
                
                processing = true;
                const btn = $('#start-cleanup-btn');
                btn.prop('disabled', true).html('<span class=\"acid-spinner\"></span> در حال شروع...');
                $('.acid-alert').remove();
                
                $.ajax({
                    url: acidCommerce.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'acid_cleanup_orders',
                        nonce: acidCommerce.nonce,
                        time_range: timeRange,
                        order_status: orderStatus
                    },
                    success: function(response) {
                        if (response.success) {
                            $('.acid-progress').show();
                            $('.acid-progress-fill').css('width', '0%');
                            $('.acid-progress-text').text('0 از ' + response.data.total + ' سفارش پردازش شد');
                            processBatch(response.data.total, 0, timeRange, orderStatus);
                        } else {
                            showAlert('خطا: ' + response.data.message, 'error');
                            resetCleanupButton();
                        }
                    },
                    error: function() {
                        showAlert('خطا در شروع پاکسازی', 'error');
                        resetCleanupButton();
                    }
                });
                
                return false;
            });
            
            function processBatch(total, processed, timeRange, orderStatus) {
                if (processed >= total) {
                    showAlert('پاکسازی با موفقیت انجام شد! ' + total + ' سفارش حذف شد.', 'success');
                    $('.acid-progress').hide();
                    resetCleanupButton();
                    return;
                }
                
                $.ajax({
                    url: acidCommerce.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'acid_process_order_batch',
                        nonce: acidCommerce.nonce,
                        time_range: timeRange,
                        order_status: orderStatus,
                        offset: processed
                    },
                    success: function(response) {
                        if (response.success) {
                            const newProcessed = processed + response.data.processed;
                            const percentage = Math.round((newProcessed / total) * 100);
                            
                            $('.acid-progress-fill').css('width', percentage + '%');
                            $('.acid-progress-text').text(newProcessed + ' از ' + total + ' سفارش پردازش شد (' + percentage + '%)');
                            
                            if (newProcessed < total) {
                                setTimeout(function() {
                                    processBatch(total, newProcessed, timeRange, orderStatus);
                                }, 300);
                            } else {
                                showAlert('پاکسازی با موفقیت انجام شد! ' + total + ' سفارش حذف شد.', 'success');
                                $('.acid-progress').hide();
                                resetCleanupButton();
                            }
                        } else {
                            showAlert('خطا در پردازش: ' + response.data.message, 'error');
                            resetCleanupButton();
                        }
                    },
                    error: function() {
                        showAlert('خطا در پردازش دسته', 'error');
                        resetCleanupButton();
                    }
                });
            }
            
            function resetCleanupButton() {
                processing = false;
                $('#start-cleanup-btn').prop('disabled', false).html('شروع پاکسازی');
            }
            
            function showAlert(message, type) {
                $('.acid-alert').remove();
                const alertClass = 'acid-alert-' + type;
                const alert = $('<div class=\"acid-alert ' + alertClass + '\">' + message + '</div>');
                $('.acid-header').after(alert);
                
                setTimeout(function() {
                    alert.fadeOut(function() {
                        $(this).remove();
                    });
                }, 8000);
            }
        })(jQuery);";
    }
    
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <div class="acid-container">
                <div class="acid-header">
                    <h1>🔥 AcidCommerce</h1>
                    <p>مدیریت پیشرفته محصولات و سفارشات WooCommerce با اتصال مستقیم به دیتابیس</p>
                </div>
                
                <div class="acid-tabs">
                    <button class="acid-tab active" data-tab="products-tab">مدیریت محصولات</button>
                    <button class="acid-tab" data-tab="orders-tab">پاکسازی سفارشات</button>
                </div>
                
                <div id="products-tab" class="acid-tab-content active">
                    <div class="acid-stats">
                        <div class="acid-stat-card">
                            <div class="acid-stat-label">تعداد کل محصولات</div>
                            <div class="acid-stat-value" id="total-products">0</div>
                        </div>
                        <div class="acid-stat-card">
                            <div class="acid-stat-label">مجموع فروش</div>
                            <div class="acid-stat-value" id="total-sales">0</div>
                        </div>
                        <div class="acid-stat-card">
                            <div class="acid-stat-label">درآمد کل (تومان)</div>
                            <div class="acid-stat-value" id="total-revenue">0</div>
                        </div>
                        <div class="acid-stat-card">
                            <div class="acid-stat-label">محصولات بدون فروش</div>
                            <div class="acid-stat-value" id="zero-sales">0</div>
                        </div>
                    </div>
                    
                    <div class="acid-search-box">
                        <input type="text" id="search-products" placeholder="جستجو در نام یا کد محصول...">
                    </div>
                    
                    <div class="acid-filters">
                        <div class="acid-filter-item">
                            <label>مرتب سازی</label>
                            <select id="sort_by">
                                <option value="sales_desc">فروش: زیاد به کم</option>
                                <option value="sales_asc">فروش: کم به زیاد</option>
                                <option value="name_asc">نام: الف تا ی</option>
                                <option value="name_desc">نام: ی تا الف</option>
                                <option value="price_asc">قیمت: کم به زیاد</option>
                                <option value="price_desc">قیمت: زیاد به کم</option>
                                <option value="date_desc">تاریخ: جدید به قدیم</option>
                                <option value="date_asc">تاریخ: قدیم به جدید</option>
                            </select>
                        </div>
                        <div class="acid-filter-item">
                            <label>از تاریخ</label>
                            <input type="date" id="date_from">
                        </div>
                        <div class="acid-filter-item">
                            <label>تا تاریخ</label>
                            <input type="date" id="date_to">
                        </div>
                        <div class="acid-filter-item">
                            <label>&nbsp;</label>
                            <button id="apply-filters-btn" class="acid-btn acid-btn-primary">اعمال فیلتر</button>
                        </div>
                    </div>
                    
                    <div class="acid-bulk-actions">
                        <button id="load-products-btn" class="acid-btn acid-btn-secondary">بارگذاری محصولات از دیتابیس</button>
                        <button id="delete-selected-btn" class="acid-btn acid-btn-danger">حذف محصولات انتخاب شده</button>
                    </div>
                    
                    <div class="acid-table-container">
                        <table class="acid-table">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="select-all" class="acid-checkbox"></th>
                                    <th>تصویر</th>
                                    <th>نام محصول</th>
                                    <th>کد محصول</th>
                                    <th>تعداد فروش</th>
                                    <th>قیمت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody id="products-table-body">
                                <tr>
                                    <td colspan="7" class="acid-no-data">برای مشاهده محصولات، دکمه "بارگذاری محصولات از دیتابیس" را کلیک کنید</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="acid-pagination"></div>
                </div>
                
                <div id="orders-tab" class="acid-tab-content">
                    <form id="cleanup-form">
                        <div class="acid-form-group">
                            <label for="time_range">بازه زمانی پاکسازی</label>
                            <select id="time_range" name="time_range" required>
                                <option value="">انتخاب کنید...</option>
                                <option value="1">1 ماه قبل</option>
                                <option value="3">3 ماه قبل</option>
                                <option value="6">6 ماه قبل</option>
                                <option value="12">12 ماه قبل (1 سال)</option>
                                <option value="24">24 ماه قبل (2 سال)</option>
                                <option value="36">36 ماه قبل (3 سال)</option>
                            </select>
                        </div>
                        
                        <div class="acid-form-group">
                            <label for="order_status">وضعیت سفارشات</label>
                            <select id="order_status" name="order_status">
                                <option value="all">همه وضعیت ها</option>
                                <option value="wc-completed">تکمیل شده</option>
                                <option value="wc-cancelled">لغو شده</option>
                                <option value="wc-failed">ناموفق</option>
                                <option value="wc-pending">در انتظار پرداخت</option>
                                <option value="wc-processing">در حال پردازش</option>
                                <option value="wc-on-hold">نگه داشته شده</option>
                                <option value="wc-refunded">بازپرداخت شده</option>
                            </select>
                        </div>
                        
                        <div class="acid-btn-group">
                            <button type="submit" id="start-cleanup-btn" class="acid-btn acid-btn-primary">
                                شروع پاکسازی
                            </button>
                        </div>
                        
                        <div class="acid-progress">
                            <div class="acid-progress-bar">
                                <div class="acid-progress-fill" style="width: 0%"></div>
                            </div>
                            <div class="acid-progress-text">0 از 0 سفارش پردازش شد</div>
                        </div>
                    </form>
                    
                    <div class="acid-info-box">
                        <h3>⚠️ هشدار مهم</h3>
                        <ul>
                            <li>این افزونه مستقیماً به دیتابیس متصل می شود و عملکرد بسیار سریع تری دارد</li>
                            <li>بازه زمانی مورد نظر را انتخاب کنید (سفارشات قدیمی تر از این بازه حذف می شوند)</li>
                            <li>می توانید وضعیت خاصی از سفارشات را برای حذف انتخاب کنید</li>
                            <li>پاکسازی به صورت دسته ای انجام می شود تا فشار به سرور وارد نشود</li>
                            <li><strong>توجه: این عملیات غیرقابل بازگشت است، حتماً پشتیبان کامل از دیتابیس تهیه کنید</strong></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function get_products_data() {
        check_ajax_referer('acid_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
            return;
        }
        
        global $wpdb;
        
        $query = "
            SELECT 
                p.ID as id,
                p.post_title as name,
                p.post_date as date_created,
                pm_sku.meta_value as sku,
                COALESCE(pm_sales.meta_value, 0) as total_sales,
                COALESCE(pm_price.meta_value, 0) as price,
                pm_thumb.meta_value as thumbnail_id
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            LEFT JOIN {$wpdb->postmeta} pm_sales ON p.ID = pm_sales.post_id AND pm_sales.meta_key = 'total_sales'
            LEFT JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
            LEFT JOIN {$wpdb->postmeta} pm_thumb ON p.ID = pm_thumb.post_id AND pm_thumb.meta_key = '_thumbnail_id'
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
        ";
        
        $results = $wpdb->get_results($query);
        
        if (empty($results)) {
            wp_send_json_error(['message' => 'محصولی یافت نشد']);
            return;
        }
        
        $products = [];
        $total_sales = 0;
        $total_revenue = 0;
        $zero_sales = 0;
        
        foreach ($results as $product) {
            $sales = intval($product->total_sales);
            $price = floatval($product->price);
            
            if ($sales === 0) {
                $zero_sales++;
            }
            
            $image_url = '';
            if ($product->thumbnail_id) {
                $image_url = wp_get_attachment_url($product->thumbnail_id);
            }
            
            $products[] = [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku ?: '-',
                'total_sales' => $sales,
                'price' => number_format($price) . ' تومان',
                'price_raw' => $price,
                'image' => $image_url ?: 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'50\' height=\'50\'%3E%3Crect fill=\'%23f3f4f6\' width=\'50\' height=\'50\'/%3E%3C/svg%3E',
                'date_created' => $product->date_created
            ];
            
            $total_sales += $sales;
            $total_revenue += ($sales * $price);
        }
        
        wp_send_json_success([
            'products' => $products,
            'stats' => [
                'total_products' => count($products),
                'total_sales' => $total_sales,
                'total_revenue' => $total_revenue,
                'zero_sales' => $zero_sales
            ]
        ]);
    }
    
    public function delete_products() {
        check_ajax_referer('acid_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
            return;
        }
        
        $product_ids = array_map('intval', $_POST['product_ids']);
        
        if (empty($product_ids)) {
            wp_send_json_error(['message' => 'محصولی انتخاب نشده است']);
            return;
        }
        
        $deleted = 0;
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                if ($product->delete(true)) {
                    $deleted++;
                }
            }
        }
        
        wp_send_json_success([
            'message' => $deleted . ' محصول با موفقیت حذف شد'
        ]);
    }
    
    public function start_cleanup() {
        check_ajax_referer('acid_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
            return;
        }
        
        $time_range = intval($_POST['time_range']);
        $order_status = sanitize_text_field($_POST['order_status']);
        
        if ($time_range < 1) {
            wp_send_json_error(['message' => 'بازه زمانی نامعتبر است']);
            return;
        }
        
        global $wpdb;
        
        $date_before = date('Y-m-d H:i:s', strtotime("-{$time_range} months"));
        
        $query = "
            SELECT COUNT(*) as total
            FROM {$wpdb->posts}
            WHERE post_type = 'shop_order'
            AND post_date < %s
        ";
        
        if ($order_status !== 'all') {
            $query .= $wpdb->prepare(" AND post_status = %s", $order_status);
        }
        
        $total = $wpdb->get_var($wpdb->prepare($query, $date_before));
        
        if ($total === 0) {
            wp_send_json_error(['message' => 'سفارشی برای حذف یافت نشد']);
            return;
        }
        
        wp_send_json_success([
            'total' => intval($total),
            'message' => $total . ' سفارش برای حذف یافت شد'
        ]);
    }
    
    public function process_order_batch() {
        check_ajax_referer('acid_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
            return;
        }
        
        $time_range = intval($_POST['time_range']);
        $order_status = sanitize_text_field($_POST['order_status']);
        
        global $wpdb;
        
        $date_before = date('Y-m-d H:i:s', strtotime("-{$time_range} months"));
        
        $query = "
            SELECT ID
            FROM {$wpdb->posts}
            WHERE post_type = 'shop_order'
            AND post_date < %s
        ";
        
        if ($order_status !== 'all') {
            $query .= $wpdb->prepare(" AND post_status = %s", $order_status);
        }
        
        $query .= " LIMIT {$this->batch_size}";
        
        $order_ids = $wpdb->get_col($wpdb->prepare($query, $date_before));
        
        if (empty($order_ids)) {
            wp_send_json_success([
                'processed' => 0,
                'message' => 'پاکسازی کامل شد'
            ]);
            return;
        }
        
        $deleted = 0;
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                if ($order->delete(true)) {
                    $deleted++;
                }
            }
        }
        
        wp_send_json_success([
            'processed' => $deleted,
            'message' => $deleted . ' سفارش حذف شد'
        ]);
    }
}

new AcidCommerce();
