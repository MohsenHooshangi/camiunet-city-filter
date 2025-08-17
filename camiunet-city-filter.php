<?php
/**
 * Plugin Name: فیلتر شهر ایران
 * Description: سیستم فیلتر محتوای سایت بر اساس شهر
 * Version: 2.0.5
 * Author: سعید عربی 
 * Author URI: https://ponisha.ir/profile/saeedarabie
 * Text Domain: camiunet-city-filter
 */

if (!defined('ABSPATH')) exit;




define( 'CAMIUNET_CITY_FILTER_VERSION', '1.5.0' );
define( 'CAMIUNET_CITY_FILTER_URL', plugin_dir_url( __FILE__ ) );
define( 'CAMIUNET_CITY_FILTER_PATH', plugin_dir_path( __FILE__ ) );

class CityFilterPlugin {
    private $post_types = array('post', 'product', 'services', 'cars', 'freight', 'auction', 'parking');
    private $user_city_cache = null;
    private $filter_applied = false;

    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        add_action('init', array($this, 'init'), 1);
        add_action('admin_init', array($this, 'admin_init'));

        add_action('wp_login', array($this, 'transfer_guest_data_to_user'), 10, 2);

        // اضافه کردن هوک برای تنظیم خودکار شهر محصول
        add_action('dokan_new_product_added', array($this, 'auto_set_product_city'), 10, 2);
        add_action('dokan_product_updated', array($this, 'auto_set_product_city'), 10, 2);
        add_action('save_post', array($this, 'auto_set_product_city_on_save'), 10, 2);


    }

    public function activate_plugin() {
        $this->register_taxonomies();
        flush_rewrite_rules();
    }

    public function init() {
        $this->register_taxonomies();

        add_action('wp_ajax_save_user_city', array($this, 'save_user_city_ajax'));
        add_action('wp_ajax_nopriv_save_user_city', array($this, 'save_user_city_ajax'));

        add_action('wp_ajax_save_guest_city_to_user', array($this, 'save_guest_city_to_user_ajax'));
        add_action('wp_ajax_nopriv_save_guest_city_to_user', array($this, 'save_guest_city_to_user_ajax'));



        add_action('show_user_profile', array($this, 'add_user_city_fields'));
        add_action('edit_user_profile', array($this, 'add_user_city_fields'));
        add_action('personal_options_update', array($this, 'save_user_city_fields'));
        add_action('edit_user_profile_update', array($this, 'save_user_city_fields'));

        add_filter('manage_users_columns', array($this, 'add_user_city_column'));
        add_filter('manage_users_custom_column', array($this, 'show_user_city_column'), 10, 3);

        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'show_city_popup'));

 

    }

    public function admin_init() {
        add_action('add_meta_boxes', array($this, 'add_post_city_meta_box'));
        add_action('save_post', array($this, 'camiunet_city_filter_save_meta_box_data'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function register_taxonomies() {
        // شهر
        register_taxonomy('post_cities', $this->post_types, array(
            'labels' => array(
                'name' => 'شهرها',
                'singular_name' => 'شهر'
            ),
            'public' => false,
            'show_ui' => false,
            'show_admin_column' => false,
            'hierarchical' => false,
            'rewrite' => false,
            'query_var' => false,
            'meta_box_cb' => false
        ));

        // استان
        register_taxonomy('post_provinces', $this->post_types, array(
            'labels' => array(
                'name' => 'استان‌ها',
                'singular_name' => 'استان'
            ),
            'public' => false,
            'show_ui' => false,
            'show_admin_column' => false,
            'hierarchical' => false,
            'rewrite' => false,
            'query_var' => false,
            'meta_box_cb' => false
        ));
    }

    public function enqueue_scripts() {
        if (!wp_script_is('city-selection-js', 'enqueued')) {
            wp_enqueue_style('city-selection-css', plugin_dir_url(__FILE__) . 'assets/css/city-selection.css', array(), '2.0.5');
            wp_enqueue_script('city-selection-js', plugin_dir_url(__FILE__) . 'assets/js/city-selection.js', array('jquery'), '2.0.5', true);
            wp_localize_script('city-selection-js', 'citySelector', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('save_city_nonce'),
                'plugin_url' => plugin_dir_url(__FILE__)
            ));
        }
    }

    public function enqueue_admin_scripts($hook) {
        global $post_type;

        $allowed_post_types = get_post_types( [ 'public' => true ], 'names' );
        unset( $allowed_post_types['attachment'] );

        if ( ( 'post.php' === $hook || 'post-new.php' === $hook ) && in_array( $post_type, array_keys( $allowed_post_types ) ) ) {
            wp_enqueue_style( 'city-selection-css', CAMIUNET_CITY_FILTER_URL . 'assets/css/city-selection.css', [], CAMIUNET_CITY_FILTER_VERSION );
            wp_enqueue_script( 'city-meta-box-js', CAMIUNET_CITY_FILTER_URL . 'assets/js/city-meta-box.js', [ 'jquery' ], CAMIUNET_CITY_FILTER_VERSION, true );
            wp_localize_script( 'city-meta-box-js', 'cityMetaBox', [
                'plugin_url' => CAMIUNET_CITY_FILTER_URL,
                'ajax_url'   => admin_url( 'admin-ajax.php' )
            ] );
        }

        if ( ! is_admin() ) {
            wp_enqueue_style( 'city-filter-frontend-css', CAMIUNET_CITY_FILTER_URL . 'assets/css/city-filter-frontend.css', [], CAMIUNET_CITY_FILTER_VERSION );
            wp_enqueue_script( 'city-selection-js', CAMIUNET_CITY_FILTER_URL . 'assets/js/city-selection.js', [ 'jquery' ], CAMIUNET_CITY_FILTER_VERSION, true );
            wp_localize_script( 'city-selection-js', 'citySelector', [
                'ajax_url'   => admin_url( 'admin-ajax.php' ),
                'plugin_url' => CAMIUNET_CITY_FILTER_URL,
                'nonce'      => wp_create_nonce( 'city_selection_nonce' )
            ] );
        }
    }
    public function show_city_popup() {
        $selected_cities = array();
        
        // برای کاربران لاگین شده
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $user_city = get_user_meta($user_id, 'user_city', true);
            if (!empty($user_city)) {
                $selected_cities = array(
                    'province' => get_user_meta($user_id, 'user_province', true),
                    'city' => $user_city,
                    'city_english' => get_user_meta($user_id, 'user_city_english', true)
                );
            }
        }
        
        if (file_exists(plugin_dir_path(__FILE__) . 'includes/popup-template.php')) {
            include plugin_dir_path(__FILE__) . 'includes/popup-template.php';
        }
    }



    // ******************** Admin Meta Box ******************

public function add_post_city_meta_box() {
    $post_types = get_post_types( [ 'public' => true ], 'names' );
    unset( $post_types['attachment'] );

    foreach ( $post_types as $post_type ) {
        add_meta_box(
            'camiunet_city_filter',
            'انتخاب شهر و استان',
            array($this, 'camiunet_city_filter_meta_box_callback'), 
            $post_type,
            'advanced',
            'high'
        );
    }
}




// محتوای متاباکس
function camiunet_city_filter_meta_box_callback( $post ) {
    wp_nonce_field( 'camiunet_city_filter_save_meta_box_data', 'camiunet_city_filter_meta_box_nonce' );

	$post_city_type   = get_post_meta( $post->ID, '_post_city_type', true );
	$selected_cities  = get_post_meta( $post->ID, '_selected_cities', false );
	$selected_provinces = get_post_meta($post->ID, '_selected_provinces', false);


	if ( empty( $post_city_type ) ) {
		$post_city_type = 'all'; // مقدار پیش‌فرض
	}
	?>

    <div class="city-selector-container">
        <div class="city-type-selection">
            <label>
                <input type="radio" name="post_city_type"
                       value="all" <?php checked( $post_city_type, 'all' ); ?>>
                سراسری (نمایش در همه شهرها)
            </label>
            <label>
                <input type="radio" name="post_city_type"
                       value="specific" <?php checked( $post_city_type, 'specific' ); ?>>
                انتخاب شهرهای خاص
            </label>
        </div>

        <!-- NEW STRUCTURE START -->
        <div id="city-selection-area" style="display: <?php echo $post_city_type === 'specific' ? 'block' : 'none'; ?>;">
            <div class="meta-box-wrapper">
                
                <!-- Right Panel: Selection List -->
                <div class="selection-panel">
                    <div class="panel-header">
                        <input type="text" id="city-search" placeholder="جستجو در استان‌ها و شهرها...">
                    </div>
                    <div class="panel-body" id="provinces-container">
                        <!-- Province and city list will be loaded here by JavaScript -->
                        <div class="loading-placeholder">در حال بارگذاری لیست شهرها...</div>
                    </div>
                </div>

                <!-- Left Panel: Summary -->
                <div class="summary-panel">
                    <div class="panel-header">
                        <h3>خلاصه انتخاب</h3>
                    </div>
                    <div class="panel-body">
                        <div class="summary-controls">
                            <div id="selected-count-wrapper">
                                <span id="selected-count">0</span> شهر انتخاب شده
                            </div>
                            <button type="button" id="clear-all-selections" class="button">پاک کردن همه</button>
                        </div>
                        <div id="selected-cities-list">
                            <!-- Selected cities will be listed here -->
                            <div class="no-selection">هیچ شهری انتخاب نشده است.</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <!-- NEW STRUCTURE END -->


        <div id="hidden-fields">
			<?php
			if ( ! empty( $selected_cities ) ) {
				foreach ( $selected_cities as $city ) {
					echo '<input type="hidden" name="selected_cities[]" value="' . esc_attr( $city ) . '">';
				}
			}
            if ( ! empty( $selected_provinces ) ) {
				foreach ( $selected_provinces as $province ) {
					echo '<input type="hidden" name="selected_provinces[]" value="' . esc_attr( $province ) . '">';
				}
			}
			?>
        </div>
    </div>
	<?php
}

// ذخیره داده‌های متاباکس
function camiunet_city_filter_save_meta_box_data( $post_id ) {


	if ( ! isset( $_POST['camiunet_city_filter_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['camiunet_city_filter_meta_box_nonce'], 'camiunet_city_filter_save_meta_box_data' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// ذخیره نوع شهر
	if ( isset( $_POST['post_city_type'] ) ) {
		$city_type = sanitize_text_field( $_POST['post_city_type'] );
		update_post_meta( $post_id, '_post_city_type', $city_type );

		if ( $city_type === 'all' ) {
			delete_post_meta( $post_id, '_selected_cities' );
			delete_post_meta( $post_id, '_selected_provinces' );
		}
	}


	// حذف متای قبلی شهرها و استان‌ها
	delete_post_meta( $post_id, '_selected_cities' );
    delete_post_meta( $post_id, '_selected_provinces' );

	// ذخیره شهرهای انتخاب شده
	if ( isset( $_POST['selected_cities'] ) && is_array( $_POST['selected_cities'] ) ) {
		$cities = array_map( 'sanitize_text_field', $_POST['selected_cities'] );
		foreach ( $cities as $city ) {
			if ( ! empty( $city ) ) {
				add_post_meta( $post_id, '_selected_cities', $city, false );
			}
		}
	}
    // ذخیره استان های انتخاب شده
    if ( isset( $_POST['selected_provinces'] ) && is_array( $_POST['selected_provinces'] ) ) {
		$provinces = array_map( 'sanitize_text_field', $_POST['selected_provinces'] );
		foreach ( $provinces as $province ) {
			if ( ! empty( $province ) ) {
				add_post_meta( $post_id, '_selected_provinces', $province, false );
			}
		}
	}
}






    // ************ User profile ************

    public function add_user_city_fields($user) {
        $user_province = get_user_meta($user->ID, 'user_province', true);
        $user_city = get_user_meta($user->ID, 'user_city', true);
        $user_city_english = get_user_meta($user->ID, 'user_city_english', true);
        ?>
        <h3>اطلاعات مکانی</h3>
        <table class="form-table">
            <tr>
                <th><label for="user_province">استان</label></th>
                <td><input type="text" name="user_province" id="user_province" value="<?php echo esc_attr($user_province); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="user_city">شهر (فارسی)</label></th>
                <td><input type="text" name="user_city" id="user_city" value="<?php echo esc_attr($user_city); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="user_city_english">شهر (انگلیسی)</label></th>
                <td><input type="text" name="user_city_english" id="user_city_english" value="<?php echo esc_attr($user_city_english); ?>" class="regular-text" /></td>
            </tr>
        </table>
        <?php
    }

    public function save_user_city_fields($user_id) {
        if (current_user_can('edit_user', $user_id)) {
            update_user_meta($user_id, 'user_province', sanitize_text_field($_POST['user_province']));
            update_user_meta($user_id, 'user_city', sanitize_text_field($_POST['user_city']));
            update_user_meta($user_id, 'user_city_english', sanitize_text_field($_POST['user_city_english']));
            wp_cache_delete('user_city_' . $user_id, 'city_filter');
        }
    }

    public function add_user_city_column($columns) {
        $columns['city'] = 'شهر';
        return $columns;
    }

    public function show_user_city_column($value, $column_name, $user_id) {
        if ($column_name == 'city') {
            $user_city = get_user_meta($user_id, 'user_city', true);
            $user_province = get_user_meta($user_id, 'user_province', true);
            if (!empty($user_city) && !empty($user_province)) {
                return $user_province . ' - ' . $user_city;
            } else {
                return 'تعیین نشده';
            }
        }
        return $value;
    }

    // ***************** User City AJAX & Cache ***************

    private function get_user_selected_city() {
        if ($this->user_city_cache !== null) return $this->user_city_cache;
        
        $cache_key = 'user_city_' . (is_user_logged_in() ? get_current_user_id() : 'guest_' . md5($_SERVER['REMOTE_ADDR']));
        $cached_city = wp_cache_get($cache_key, 'city_filter');
        
        if ($cached_city !== false) {
            $this->user_city_cache = $cached_city;
            return $cached_city;
        }

        $city = '';
        if (is_user_logged_in()) {
            $city = get_user_meta(get_current_user_id(), 'user_city', true);
        } else {
            $city = isset($_COOKIE['guest_city']) ? sanitize_text_field($_COOKIE['guest_city']) : '';
        }

        $this->user_city_cache = $city;
        wp_cache_set($cache_key, $city, 'city_filter', 600);
        return $city;
    }

    private function user_has_selected_city() {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $city = get_user_meta($user_id, 'user_city', true);
            return !empty($city);
        } else {
            // چک کردن کوکی برای کاربران مهمان
            $guest_city = isset($_COOKIE['guest_city']) ? sanitize_text_field($_COOKIE['guest_city']) : '';
            return !empty($guest_city);
        }
    }



    public function save_user_city_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'save_city_nonce')) {
            wp_die('Security check failed');
        }

        $province = sanitize_text_field($_POST['province']);
        $city = sanitize_text_field($_POST['city']);
        $city_english = sanitize_text_field($_POST['city_english']);

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            update_user_meta($user_id, 'user_province', $province);
            update_user_meta($user_id, 'user_city', $city);
            update_user_meta($user_id, 'user_city_english', $city_english);
            wp_cache_delete('user_city_' . $user_id, 'city_filter');
        } else {
            // ذخیره کامل اطلاعات در کوکی
            $cookie_time = time() + (86400 * 30); // 30 روز
            setcookie('guest_province', $province, $cookie_time, '/');
            setcookie('guest_city', $city, $cookie_time, '/');
            setcookie('guest_city_english', $city_english, $cookie_time, '/');
            
            // ذخیره در سشن هم
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['guest_province'] = $province;
            $_SESSION['guest_city'] = $city;
            $_SESSION['guest_city_english'] = $city_english;
        }

        wp_send_json_success(array(
            'message' => 'شهر با موفقیت ذخیره شد',
            'city' => $city,
            'province' => $province,
            'city_english' => $city_english
        ));
    }



    /**
 * انتقال داده‌های شهر از کوکی به یوزر متا هنگام لاگین
 */
public function transfer_guest_data_to_user($user_login, $user) {
    // استفاده از JavaScript برای خواندن کوکی‌ها
    ?>
    <script type="text/javascript">
    (function() {
        function getCookie(name) {
            var nameEQ = name + "=";
            var ca = document.cookie.split(';');
            for(var i = 0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) == ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) == 0) return decodeURIComponent(c.substring(nameEQ.length, c.length));
            }
            return null;
        }

        var guestProvince = getCookie('guest_province');
        var guestCity = getCookie('guest_city');
        var guestCityEnglish = getCookie('guest_city_english');

        if (guestCity && guestProvince) {
            // ارسال درخواست AJAX برای ذخیره داده‌ها
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            var data = 'action=save_guest_city_to_user' +
                      '&user_id=<?php echo $user->ID; ?>' +
                      '&province=' + encodeURIComponent(guestProvince) +
                      '&city=' + encodeURIComponent(guestCity) +
                      '&city_english=' + encodeURIComponent(guestCityEnglish) +
                      '&nonce=<?php echo wp_create_nonce('transfer_guest_data_nonce'); ?>';
            
            xhr.send(data);
            
            // پاک کردن کوکی‌ها بعد از انتقال
            document.cookie = 'guest_province=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            document.cookie = 'guest_city=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            document.cookie = 'guest_city_english=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
        }
    })();
    </script>
    <?php
}

/**
 * AJAX handler برای ذخیره داده‌های کوکی در یوزر متا
 */
public function save_guest_city_to_user_ajax() {
    // بررسی nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'transfer_guest_data_nonce')) {
        wp_die('Security check failed');
    }

    $user_id = intval($_POST['user_id']);
    $province = sanitize_text_field($_POST['province']);
    $city = sanitize_text_field($_POST['city']);
    $city_english = sanitize_text_field($_POST['city_english']);

    // بررسی اینکه کاربر معتبر است
    if (!$user_id || !get_userdata($user_id)) {
        wp_die('Invalid user');
    }

    // بررسی اینکه کاربر قبلاً شهری انتخاب نکرده باشد
    $existing_city = get_user_meta($user_id, 'user_city', true);
    if (empty($existing_city) && !empty($city)) {
        // ذخیره داده‌ها در یوزر متا
        update_user_meta($user_id, 'user_province', $province);
        update_user_meta($user_id, 'user_city', $city);
        update_user_meta($user_id, 'user_city_english', $city_english);
        
        // پاک کردن کش
        wp_cache_delete('user_city_' . $user_id, 'city_filter');
        
        wp_send_json_success(array(
            'message' => 'داده‌های شهر با موفقیت انتقال یافت',
            'user_id' => $user_id
        ));
    } else {
        wp_send_json_error('کاربر قبلاً شهری انتخاب کرده است');
    }
}





/**
 * تنظیم خودکار شهر محصول بر اساس شهر فروشنده (دکان)
 */
public function auto_set_product_city($product_id, $product_info = null) {
    // بررسی اینکه پست از نوع محصول است
    if (get_post_type($product_id) !== 'product') {
        return;
    }

    // گرفتن فروشنده محصول
    $vendor_id = get_post_field('post_author', $product_id);
    if (!$vendor_id) {
        return;
    }

    // گرفتن شهر فروشنده
    $vendor_city_english = get_user_meta($vendor_id, 'user_city_english', true); // این همان slug است
    $vendor_province = get_user_meta($vendor_id, 'user_province', true);

    if (empty($vendor_city_english) || empty($vendor_province)) {
        return;
    }

    // بررسی اینکه آیا شهر محصول قبلاً تنظیم شده یا نه
    $existing_city_type = get_post_meta($product_id, '_post_city_type', true);
    
    // اگر شهر محصول قبلاً تنظیم نشده یا روی "all" است، آن را تنظیم می‌کنیم
    if (empty($existing_city_type) || $existing_city_type === 'all') {
        
        // تنظیم نوع شهر به specific
        update_post_meta($product_id, '_post_city_type', 'specific');
        
        // پاک کردن شهرهای قبلی
        delete_post_meta($product_id, '_selected_cities');
        delete_post_meta($product_id, '_selected_provinces');
        
        // تنظیم شهر فروشنده (با slug که همان city_english است)
        add_post_meta($product_id, '_selected_cities', $vendor_city_english, false);
        add_post_meta($product_id, '_selected_provinces', $vendor_province, false);
        
        // لاگ برای دیباگ
        error_log('[Auto City Set] Product ID: ' . $product_id . ', Vendor: ' . $vendor_id . ', City slug: ' . $vendor_city_english . ', Province: ' . $vendor_province);
    }
}


/**
 * تنظیم خودکار شهر محصول هنگام ذخیره پست (برای سازگاری بیشتر)
 */
public function auto_set_product_city_on_save($post_id, $post) {
    // بررسی اینکه پست از نوع محصول است
    if ($post->post_type !== 'product') {
        return;
    }

    // بررسی اینکه در حال خودکار ذخیره نیست
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // بررسی اینکه کاربر مجوز ویرایش دارد
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // اگر شهر محصول از طریق فرم تنظیم شده، آن را تغییر ندهیم
    if (isset($_POST['post_city_type']) && $_POST['post_city_type'] === 'specific') {
        return;
    }

    // فراخوانی تابع تنظیم خودکار شهر
    $this->auto_set_product_city($post_id);
}


/**
 * تنظیم خودکار شهر برای تمام محصولات فروشنده هنگام تغییر شهر او
 */
public function update_vendor_products_city($user_id) {
    if (!$user_id) {
        return;
    }

    // گرفتن شهر جدید فروشنده
    $vendor_city = get_user_meta($user_id, 'user_city', true);
    $vendor_province = get_user_meta($user_id, 'user_province', true);

    if (empty($vendor_city) || empty($vendor_province)) {
        return;
    }

    // گرفتن تمام محصولات فروشنده
    $vendor_products = get_posts(array(
        'post_type' => 'product',
        'author' => $user_id,
        'posts_per_page' => -1,
        'post_status' => array('publish', 'draft', 'pending')
    ));

    foreach ($vendor_products as $product) {
        $city_type = get_post_meta($product->ID, '_post_city_type', true);
        
        // فقط محصولاتی که شهر خاص تنظیم شده را به‌روزرسانی می‌کنیم
        if ($city_type === 'specific') {
            // پاک کردن شهرهای قبلی
            delete_post_meta($product->ID, '_selected_cities');
            delete_post_meta($product->ID, '_selected_provinces');
            
            // تنظیم شهر جدید
            add_post_meta($product->ID, '_selected_cities', $vendor_city, false);
            add_post_meta($product->ID, '_selected_provinces', $vendor_province, false);
        }
    }
}





}


new CityFilterPlugin();



add_shortcode('user_city', function () {
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        return get_user_meta($user_id, 'user_city', true);
    } elseif (isset($_COOKIE['guest_city'])) {
        return sanitize_text_field($_COOKIE['guest_city']);
    } else {
        return '';
    }


    return '';
});




if (file_exists(plugin_dir_path(__FILE__) . 'elementor-city-query.php')) {
    require_once plugin_dir_path(__FILE__) . 'elementor-city-query.php';
}


add_shortcode('city_selector_button', function() {
    // گرفتن نام شهر کاربر
    if (is_user_logged_in()) {
        $user_city = get_user_meta(get_current_user_id(), 'user_city', true);
    } elseif (isset($_COOKIE['guest_city'])) {
        $user_city = sanitize_text_field($_COOKIE['guest_city']);
    } else {
        $user_city = 'شهر';
    }
    ob_start(); ?>
    <button
        class="simple-city-selector-btn"
        type="button"
        onclick="openCityPopup()">
        <?php echo esc_html($user_city); ?>
    </button>
    <style>
    .simple-city-selector-btn {
        font-family: "Iran Yekan X family", Sans-serif;
        color: #1E293B !important;
        border: none !important;
        background: #FFFFFF00 !important;
        border-radius: 0 !important;
        padding: 0 !important;
        font-size: 14px;
        font-weight: 400;
        box-shadow: none;
        cursor: pointer;
        transition: background 0.15s, color 0.15s;



    }
    .simple-city-selector-btn:hover {
        background: #340E5B !important;
        color: #fff !important;
        border-color: #340E5B !important;
    }


    @media (max-width: 768px) {
        .simple-city-selector-btn {
            width: -webkit-fill-available;
            width: -moz-available;
            width: fill-available;
            margin-top: 1rem;
            border-radius: 15px !important;
        }
    }

    </style>
    <?php
    return ob_get_clean();
});
