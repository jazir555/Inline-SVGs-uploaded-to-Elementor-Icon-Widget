<?php
/**
 * Plugin Name: Inline SVG for Elementor Icon Widget
 * Description: Adds an option to inline SVGs in Elementor's Icon widget with enhanced security, accessibility, styling compatibility, and optimized performance.
 * Version: 2.5.0
 * Author: Your Name
 * Text Domain: inline-svg-elementor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'INLINE_SVG_ELEMENTOR_TEXT_DOMAIN', 'inline-svg-elementor' );

if ( ! class_exists( 'enshrined\svgSanitize\Sanitizer' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
}

class Inline_SVG_Elementor {

    private $cache_version;

    public function __construct() {
        $this->cache_version = get_option( 'inline_svg_elementor_cache_version', 1 );

        add_action( 'elementor/element/icon/section_style_icon/after_section_end', [ $this, 'add_controls' ], 10, 2 );
        add_filter( 'elementor/icon/print_template', [ $this, 'inline_svg' ], 10, 3 );
        add_filter( 'elementor/frontend/icon', [ $this, 'inline_svg' ], 10, 3 );
        add_action( 'elementor/element/icon/section_icon/before_section_end', [ $this, 'add_content_controls' ], 10, 2 );
        $this->setup_cache_clearing();
        add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_clear_cache' ], 100 );
        add_action( 'admin_post_inline_svg_elementor_clear_cache', [ $this, 'manual_clear_cache' ] );

        // Add setting for default ARIA attributes
        add_action( 'admin_init', [ $this, 'add_settings' ] );
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
    }

    public function add_controls( $element, $args ) {
        $element->start_controls_section(
            'section_inline_svg',
            [
                'label' => esc_html__( 'Inline SVG', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ),
                'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
            ]
        );

        // Option to enable SVG inlining
        $element->add_control(
            'enable_inline_svg',
            [
                'label'        => esc_html__( 'Enable Inline SVG', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__( 'Yes', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ),
                'label_off'    => esc_html__( 'No', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ),
                'return_value' => 'yes',
                'default'      => 'no',
            ]
        );

        // Add ARIA attributes option with global setting check
        if ( 'no' === get_option( 'inline_svg_elementor_aria_enabled', 'yes' ) ) {
            $element->add_control(
                'custom_aria_attributes',
                [
                    'label'       => esc_html__( 'Custom ARIA Attributes', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ),
                    'type'        => \Elementor\Controls_Manager::TEXTAREA,
                    'description' => esc_html__( 'Add custom ARIA attributes in JSON format, e.g., {"aria-label": "My SVG"}', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ),
                    'default'     => '',
                    'condition'   => [
                        'enable_inline_svg' => 'yes',
                    ],
                ]
            );
        }

        // Option to lazy-load the SVG
        $element->add_control(
            'svg_lazy_load',
            [
                'label'        => esc_html__( 'Lazy Load SVG', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__( 'Yes', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ),
                'label_off'    => esc_html__( 'No', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ),
                'return_value' => 'yes',
                'default'      => 'no',
                'description'  => esc_html__( 'Defer loading of the SVG until it is visible.', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ),
                'condition'    => [
                    'enable_inline_svg' => 'yes',
                ],
            ]
        );

        // Add a file size limit control
        $element->add_control(
            'svg_max_file_size',
            [
                'label'        => esc_html__( 'Max SVG File Size (KB)', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ),
                'type'         => \Elementor\Controls_Manager::NUMBER,
                'min'          => 0,
                'default'      => 512, // Default size limit of 512 KB
                'condition'    => [
                    'enable_inline_svg' => 'yes',
                ],
                'description'  => esc_html__( 'SVGs larger than this size will not be inlined.', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ),
            ]
        );

        $element->end_controls_section();
    }

    public function inline_svg( $icon, $args = [], $instance = null ) {
        if ( empty( $icon['value'] ) || ! $instance instanceof Elementor\Widget_Base ) {
            return '';
        }

        $settings = $instance->get_settings_for_display();
        if ( isset( $settings['enable_inline_svg'] ) && 'yes' === $settings['enable_inline_svg'] ) {
            if ( is_numeric( $icon['value'] ) ) {
                $attachment_id = absint( $icon['value'] );
                $attachment    = get_post( $attachment_id );

                if ( $attachment && 'image/svg+xml' === get_post_mime_type( $attachment_id ) ) {
                    $svg_file_path = get_attached_file( $attachment_id );

                    // Check file size limit
                    $max_file_size = isset( $settings['svg_max_file_size'] ) ? absint( $settings['svg_max_file_size'] ) : 512;
                    if ( filesize( $svg_file_path ) > ( $max_file_size * 1024 ) ) {
                        $this->log_error( 'SVG file too large to inline for attachment ID ' . $attachment_id );
                        return \Elementor\Icons_Manager::render_icon( $icon, $args, $instance );
                    }

                    if ( file_exists( $svg_file_path ) ) {
                        $cache_key   = 'inline_svg_' . md5( $attachment_id . wp_json_encode( $this->get_relevant_settings( $settings ) ) ) . '_' . $this->cache_version;
                        $cache_group = 'inline_svg_elementor';
                        $safe_svg    = wp_cache_get( $cache_key, $cache_group );

                        if ( false === $safe_svg ) {
                            $svg_content = file_get_contents( $svg_file_path );

                            // Handle libxml_disable_entity_loader() only for PHP < 8.0.
                            if ( PHP_VERSION_ID < 80000 ) {
                                $old_libxml = libxml_disable_entity_loader( true );
                            }

                            $internal_errors = libxml_use_internal_errors( true );
                            $dom             = new DOMDocument();

                            if ( ! @$dom->loadXML( $svg_content, LIBXML_NONET ) ) {
                                $this->log_error( 'Failed to load SVG content for attachment ID ' . $attachment_id );
                                libxml_clear_errors();
                                libxml_use_internal_errors( $internal_errors );
                                if ( PHP_VERSION_ID < 80000 ) {
                                    libxml_disable_entity_loader( $old_libxml );
                                }
                                return \Elementor\Icons_Manager::render_icon( $icon, $args, $instance );
                            }

                            $safe_svg = $this->sanitize_svg( $dom->saveXML() );
                            $dom->loadXML( $safe_svg );
                            $svg_element = $dom->documentElement;

                            // Handle ARIA attributes if enabled globally or per widget.
                            if ( ! empty( $settings['custom_aria_attributes'] ) ) {
                                $custom_aria = json_decode( $settings['custom_aria_attributes'], true );
                                $allowed_aria_attributes = [ 'aria-label', 'aria-hidden', 'aria-labelledby' ];
                                foreach ( $custom_aria as $attr => $value ) {
                                    $attr  = sanitize_key( $attr );
                                    $value = esc_attr( sanitize_text_field( $value ) );
                                    if ( in_array( $attr, $allowed_aria_attributes, true ) ) {
                                        $svg_element->setAttribute( $attr, $value );
                                    }
                                }
                            }

                            // Minify the final SVG content and cache it.
                            $safe_svg = preg_replace( '/>\s+</', '><', $dom->saveXML( $svg_element ) );
                            libxml_clear_errors();
                            libxml_use_internal_errors( $internal_errors );

                            if ( PHP_VERSION_ID < 80000 ) {
                                libxml_disable_entity_loader( $old_libxml );
                            }

                            wp_cache_set( $cache_key, $safe_svg, $cache_group );
                        }

                        // Lazy-load the SVG if enabled.
                        if ( isset( $settings['svg_lazy_load'] ) && 'yes' === $settings['svg_lazy_load'] ) {
                            $safe_svg = str_replace( '<svg', '<svg loading="lazy"', $safe_svg );
                        }

                        return wp_kses_post( $safe_svg );
                    }
                }
            }
        }

        return \Elementor\Icons_Manager::render_icon( $icon, $args, $instance );
    }

    // Add settings to disable ARIA by default globally
    public function add_settings() {
        register_setting( 'inline_svg_elementor_settings', 'inline_svg_elementor_aria_enabled' );
    }

    public function add_settings_page() {
        add_options_page(
            esc_html__( 'Inline SVG Settings', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ),
            esc_html__( 'Inline SVG Settings', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ),
            'manage_options',
            'inline-svg-elementor-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Inline SVG Settings', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ); ?></h1>
            <form action="options.php" method="post">
                <?php settings_fields( 'inline_svg_elementor_settings' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="inline_svg_elementor_aria_enabled"><?php esc_html_e( 'Enable ARIA Attributes by Default', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="inline_svg_elementor_aria_enabled" name="inline_svg_elementor_aria_enabled" value="yes" <?php checked( 'yes', get_option( 'inline_svg_elementor_aria_enabled', 'yes' ) ); ?>>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function sanitize_svg( $svg_content ) {
        $sanitizer = new enshrined\svgSanitize\Sanitizer();
        $sanitizer->minify( true );
        return $sanitizer->sanitize( $svg_content );
    }

    private function get_relevant_settings( $settings ) {
        $keys = [ 'enable_inline_svg', 'custom_aria_attributes', 'svg_lazy_load' ];
        return array_filter( $settings, function ( $key ) use ( $keys ) {
            return in_array( $key, $keys, true );
        }, ARRAY_FILTER_USE_KEY );
    }

    // Cache clearing and visual feedback
    public function manual_clear_cache() {
        if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'inline_svg_elementor_clear_cache' ) ) {
            wp_die( esc_html__( 'You are not allowed to clear the cache.', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ) );
        }

        $this->clear_cache();
        set_transient( 'inline_svg_cache_cleared', true, 30 );

        wp_safe_redirect( wp_get_referer() );
        exit;
    }

    public function add_admin_bar_clear_cache( $wp_admin_bar ) {
        if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $cache_cleared_message = '';
        if ( get_transient( 'inline_svg_cache_cleared' ) ) {
            $cache_cleared_message = '<span style="color: green; font-weight: bold;">(' . esc_html__( 'Cache Cleared', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ) . ')</span>';
            delete_transient( 'inline_svg_cache_cleared' );
        }

        $args = [
            'id'    => 'inline_svg_elementor_clear_cache',
            'title' => esc_html__( 'Clear Inline SVG Cache', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ) . $cache_cleared_message,
            'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=inline_svg_elementor_clear_cache' ), 'inline_svg_elementor_clear_cache' ),
            'meta'  => [ 'title' => esc_html__( 'Clear Inline SVG Cache', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ) ],
        ];

        $wp_admin_bar->add_node( $args );
    }

    private function log_error( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Inline SVG Plugin] ' . $message );
        }
    }
}

new Inline_SVG_Elementor();
