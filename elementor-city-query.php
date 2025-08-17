<?php
if (!defined('ABSPATH')) exit;

class ElementorCityQuery {
    private $user_city_cache = null;
    private $user_province_cache = null;
    private $post_types = array('post', 'product', 'services', 'cars', 'freight', 'auction', 'parking');

    public function __construct() {
        if (!is_admin() && !wp_doing_ajax()) {
            $this->init_hooks();
        }
    }

    private function init_hooks() {
        // Elementor
        add_action('elementor/query/custom_city_filter', array($this, 'modify_query'), 10, 2);
        add_action('elementor/query/posts', array($this, 'modify_query'), 10, 2);
        add_action('elementor/query/archive', array($this, 'modify_query'), 10, 2);

        // JetEngine
        add_filter('jet-engine/listing/grid/posts-query-args', array($this, 'modify_jetengine_query'), 10, 3);
        add_filter('jet-engine/query-builder/query/posts-query-args', array($this, 'modify_jetengine_wp_query'), 10, 2);

        // JetSmartFilters
        add_filter('jet-smart-filters/query/final-query', array($this, 'modify_jetsmartfilters_query'), 10, 2);

		// حالت خاص فقط برای لیستینگ فروشگاه
		//add_filter('jet-smart-filters/query/shop-listing-product-archive', array($this, 'modify_shop_listing_query'), 10, 2);

        // **اضافه کردن هوک جدید برای Custom Query های JetEngine**
        add_filter('jet-engine/query-builder/queries/get-items', array($this, 'modify_jetengine_custom_query'), 10, 2);
        
        // **هوک اضافی برای Query Builder**
        add_filter('jet-engine/query-builder/query/posts-query-args', array($this, 'modify_jetengine_query_builder'), 10, 2);
        
        // **هوک مخصوص Query ID**
        add_filter('jet-engine/query-builder/query/parkingarchive/posts-query-args', array($this, 'modify_parking_archive_query'), 10, 2);

        // Global query (اختیاری اگر میخوای Main Query هم فیلتر بشه)
        add_action('pre_get_posts', array($this, 'modify_global_query'), 15);
        add_filter('jet-engine/query-builder/queries/get-args/query-id/parkingarchive', array($this, 'modify_specific_query'), 10, 2);

    }

    // **تابع جدید برای Custom Query های JetEngine**
    public function modify_jetengine_custom_query($items, $query) {
        $user_city = $this->get_user_city();
        $user_province = $this->get_user_province();

        if (empty($user_city) && empty($user_province)) return $items;

        // چک کردن نوع کوئری
        if (!is_object($query) || !method_exists($query, 'get_query_args')) return $items;
        
        $query_args = $query->get_query_args();
        $post_type = isset($query_args['post_type']) ? $query_args['post_type'] : 'post';
        
        if (!in_array($post_type, $this->post_types)) return $items;

        // اعمال فیلتر شهر
        $meta_query = $this->get_city_meta_query();
        
        if (!empty($query_args['meta_query'])) {
            $query_args['meta_query'] = array(
                'relation' => 'AND',
                $query_args['meta_query'],
                $meta_query
            );
        } else {
            $query_args['meta_query'] = $meta_query;
        }

        // اجرای کوئری جدید
        $filtered_query = new WP_Query($query_args);
        return $filtered_query->posts;
    }

    // **تابع مخصوص Query Builder**
    public function modify_jetengine_query_builder($args, $query) {
        $user_city = $this->get_user_city();
        $user_province = $this->get_user_province();

        if (empty($user_city) && empty($user_province)) return $args;

        $post_type = isset($args['post_type']) ? $args['post_type'] : 'post';
        if (!in_array($post_type, $this->post_types)) return $args;

        // گرفتن Query ID
        $query_id = null;
        if (is_object($query) && method_exists($query, 'get_query_id')) {
            $query_id = $query->get_query_id();
        }

        error_log('[JetEngine Query Builder] Query ID: ' . $query_id);

        $meta_query = $this->get_city_meta_query();

        if (!empty($args['meta_query'])) {
            $args['meta_query'] = array(
                'relation' => 'AND',
                $args['meta_query'],
                $meta_query
            );
        } else {
            $args['meta_query'] = $meta_query;
        }


        return $args;
    }

    // **تابع مخصوص parkingarchive**
    public function modify_parking_archive_query($args, $query) {
        $user_city = $this->get_user_city();
        $user_province = $this->get_user_province();

        if (empty($user_city) && empty($user_province)) return $args;

        error_log('[Parking Archive Query] User City: ' . $user_city . ', Province: ' . $user_province);

        $meta_query = $this->get_city_meta_query();

        if (!empty($args['meta_query'])) {
            $args['meta_query'] = array(
                'relation' => 'AND',
                $args['meta_query'],
                $meta_query
            );
        } else {
            $args['meta_query'] = $meta_query;
        }

        error_log('[Parking Archive Query] Final meta_query: ' . print_r($args['meta_query'], true));

        return $args;
    }

    // باقی توابع بدون تغییر...
    public function modify_query($query, $widget) {
        $user_city = $this->get_user_city();
        $user_province = $this->get_user_province();

        if (empty($user_city) && empty($user_province)) return;

        $post_type = $query->get('post_type') ?: 'post';
        if (!in_array($post_type, $this->post_types)) return;

        $this->apply_city_filter($query);
    }

    public function modify_jetengine_query($args, $render_instance, $settings) {
        $user_city = $this->get_user_city();
        $user_province = $this->get_user_province();

        if (empty($user_city) && empty($user_province)) return $args;

        $post_type = isset($args['post_type']) ? $args['post_type'] : 'post';
        if (!in_array($post_type, $this->post_types)) return $args;

        $meta_query = $this->get_city_meta_query();

        if (!empty($args['meta_query'])) {
            $args['meta_query'] = array(
                'relation' => 'AND',
                $args['meta_query'],
                $meta_query
            );
        } else {
            $args['meta_query'] = $meta_query;
        }

        return $args;
    }

    public function modify_jetengine_wp_query($args, $query) {
        return $this->modify_jetengine_query($args, null, null);
    }

    public function modify_jetsmartfilters_query($query, $provider = null) {
        $user_city = $this->get_user_city();
        $user_province = $this->get_user_province();

        if ((empty($user_city) && empty($user_province)) || !is_object($query)) return $query;

        $post_type = $query->get('post_type') ?: 'post';
        if (!in_array($post_type, $this->post_types)) return $query;

        $this->apply_city_filter($query);
        return $query;
    }

public function modify_global_query($query) {
    if (!$query->is_main_query() || is_admin() || is_feed()) return;

    // *** فقط روی آرشیو ها فیلتر بزن ***
    if ($query->is_archive() || $query->is_home() || $query->is_post_type_archive()) {
        $user_city = $this->get_user_city();
        $user_province = $this->get_user_province();
        $post_type = $query->get('post_type') ?: 'post';

        if ((!empty($user_city) || !empty($user_province)) && in_array($post_type, $this->post_types)) {
            $this->apply_city_filter($query);
        }
    }
    // اگر صفحه تکی/سینگل هست، هیچ فیلتری نزن
}


    private function get_city_meta_query() {
        $user_city = $this->get_user_city();
        $user_province = $this->get_user_province();

        $meta_query = array(
            'relation' => 'OR',
            array(
                'key'     => '_post_city_type',
                'value'   => 'all',
                'compare' => '='
            ),
            array(
                'key'     => '_post_city_type',
                'compare' => 'NOT EXISTS'
            )
        );

        if (!empty($user_city)) {
            $meta_query[] = array(
                'key'     => '_selected_cities',
                'value'   => $user_city,
                'compare' => '='
            );
        }

        if (!empty($user_province)) {
            $meta_query[] = array(
                'key'     => '_selected_provinces',
                'value'   => $user_province,
                'compare' => '='
            );
        }

        return $meta_query;
    }

    private function apply_city_filter(&$query_or_args) {
        $meta_query = $this->get_city_meta_query();

        if ($query_or_args instanceof WP_Query) {
            $existing_meta_query = $query_or_args->get('meta_query');

            if (!empty($existing_meta_query)) {
                $meta_query = array(
                    'relation' => 'AND',
                    $existing_meta_query,
                    $meta_query
                );
            }
            $query_or_args->set('meta_query', $meta_query);

            error_log('[ElementorCityQuery] apply_city_filter to WP_Query: ' . print_r($meta_query, true));
        } elseif (is_array($query_or_args)) {
            if (!empty($query_or_args['meta_query'])) {
                $query_or_args['meta_query'] = array(
                    'relation' => 'AND',
                    $query_or_args['meta_query'],
                    $meta_query
                );
            } else {
                $query_or_args['meta_query'] = $meta_query;
            }
            error_log('[ElementorCityQuery] apply_city_filter to array: ' . print_r($query_or_args['meta_query'], true));
        }
    }


    // و این تابع را اضافه کنید:
    public function modify_specific_query($args, $query) {
        $user_city = $this->get_user_city();
        $user_province = $this->get_user_province();

        if (empty($user_city) && empty($user_province)) return $args;

        $meta_query = $this->get_city_meta_query();

        if (!empty($args['meta_query'])) {
            $args['meta_query'] = array(
                'relation' => 'AND',
                $args['meta_query'],
                $meta_query
            );
        } else {
            $args['meta_query'] = $meta_query;
        }

        return $args;
    }


    private function get_user_city() {
        if ($this->user_city_cache !== null) return $this->user_city_cache;

        $cache_key = 'user_city_' . (is_user_logged_in() ? get_current_user_id() : 'guest');
        $cached_city = wp_cache_get($cache_key, 'elementor_city_filter');

        if ($cached_city !== false) {
            $this->user_city_cache = $cached_city;
            return $cached_city;
        }

        if (is_user_logged_in()) {
            $city = get_user_meta(get_current_user_id(), 'user_city_english', true);
        } else {
            $city = isset($_COOKIE['guest_city']) ? sanitize_text_field($_COOKIE['guest_city']) : '';
        }

        $city = sanitize_text_field($city);
        $this->user_city_cache = $city;
        wp_cache_set($cache_key, $city, 'elementor_city_filter', 600);

        return $city;
    }

    private function get_user_province() {
        if ($this->user_province_cache !== null) return $this->user_province_cache;

        $cache_key = 'user_province_' . (is_user_logged_in() ? get_current_user_id() : 'guest');
        $cached_province = wp_cache_get($cache_key, 'elementor_city_filter');

        if ($cached_province !== false) {
            $this->user_province_cache = $cached_province;
            return $cached_province;
        }

        if (is_user_logged_in()) {
            $province = get_user_meta(get_current_user_id(), 'user_province_english', true);
        } else {
            $province = isset($_COOKIE['guest_province']) ? sanitize_text_field($_COOKIE['guest_province']) : '';
        }

        $province = sanitize_text_field($province);
        $this->user_province_cache = $province;
        wp_cache_set($cache_key, $province, 'elementor_city_filter', 600);

        return $province;
    }
}

new ElementorCityQuery();
