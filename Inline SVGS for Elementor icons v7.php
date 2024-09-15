<?php
/**
 * Plugin Name: Inline SVG for Elementor Icon Widget
 * Description: Adds an option to inline SVGs in Elementor's Icon widget with enhanced security, accessibility, styling compatibility, and optimized performance.
 * Version: 1.9.6
 * Author: Your Name
 * Text Domain: inline-svg-elementor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Define the text domain as a constant
define( 'INLINE_SVG_ELEMENTOR_TEXT_DOMAIN', 'inline-svg-elementor' );

// Include the SVG Sanitizer library
if ( ! class_exists( 'enshrined\svgSanitize\Sanitizer' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
}

// Main Plugin Class
class Inline_SVG_Elementor {

    public function __construct() {
        // Add controls to the Icon widget
        add_action( 'elementor/element/icon/section_style_icon/after_section_end', [ $this, 'add_controls' ], 10, 2 );

        // Modify Icon widget rendering
        add_filter( 'elementor/icon/print_template', [ $this, 'inline_svg' ], 10, 3 );
        add_filter( 'elementor/frontend/icon', [ $this, 'inline_svg' ], 10, 3 );

        // Add content controls
        add_action( 'elementor/element/icon/section_icon/before_section_end', [ $this, 'add_content_controls' ], 10, 2 );

        // Cache clearing actions
        $this->setup_cache_clearing();

        // Add admin bar menu for cache clearing
        add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_clear_cache' ], 100 );

        // Handle manual cache clearing
        add_action( 'admin_post_inline_svg_elementor_clear_cache', [ $this, 'manual_clear_cache' ] );
    }

    // Add controls to the Icon widget's Advanced tab
    public function add_controls( $element, $args ) {
        $element->start_controls_section(
            'section_inline_svg',
            [
                'label' => esc_html__( 'Inline SVG', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ),
                'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
            ]
        );

        // Add the toggle control to enable inlining SVG
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

        // Add custom ARIA attributes control
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

        // Add lazy loading control for SVG
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

        $element->end_controls_section();
    }

    // Inline SVG rendering
    public function inline_svg( $icon, $args = [], $instance = null ) {
        if ( empty( $icon['value'] ) ) {
            return '';
        }

        // Get the widget settings
        $settings = $instance->get_settings_for_display();

        // Check if 'enable_inline_svg' is set to 'yes'
        if ( isset( $settings['enable_inline_svg'] ) && 'yes' === $settings['enable_inline_svg'] ) {
            // Proceed to inline the SVG
            if ( is_numeric( $icon['value'] ) ) {
                $attachment_id = $icon['value'];
                $attachment    = get_post( $attachment_id );
                if ( $attachment && 'image/svg+xml' === get_post_mime_type( $attachment_id ) ) {
                    $svg_file_path = get_attached_file( $attachment_id );
                    if ( file_exists( $svg_file_path ) ) {

                        // Generate a unique cache key based on settings that affect the SVG output
                        $settings_hash = md5( wp_json_encode( $this->get_relevant_settings( $settings ) ) );
                        $cache_key     = 'inline_svg_' . md5( "{$attachment_id}_{$settings_hash}" );
                        $cache_group   = 'inline_svg_elementor';
                        $safe_svg      = wp_cache_get( $cache_key, $cache_group );

                        if ( false === $safe_svg ) {
                            $svg_content = file_get_contents( $svg_file_path );

                            // Handle libxml_disable_entity_loader() only for PHP < 8.0
                            if ( PHP_VERSION_ID < 80000 ) {
                                libxml_disable_entity_loader( true );
                            }
                            $internal_errors = libxml_use_internal_errors( true );

                            $dom = new DOMDocument();
                            if ( $dom->loadXML( $svg_content, LIBXML_NONET ) === false ) {
                                // Log error for debugging
                                error_log( 'Error: Failed to load SVG content for attachment ID ' . $attachment_id );

                                libxml_clear_errors();
                                libxml_use_internal_errors( $internal_errors );
                                if ( PHP_VERSION_ID < 80000 ) {
                                    libxml_disable_entity_loader( false );
                                }

                                // Return fallback rendering
                                return \Elementor\Icons_Manager::render_icon( $icon, $args, $instance );
                            }

                            // Sanitize the SVG content
                            $safe_svg = $this->sanitize_svg( $dom->saveXML() );

                            if ( ! $safe_svg ) {
                                return \Elementor\Icons_Manager::render_icon( $icon, $args, $instance );
                            }

                            $dom->loadXML( $safe_svg );
                            $svg_element = $dom->documentElement;

                            // Handle ARIA attributes if provided
                            if ( ! empty( $settings['custom_aria_attributes'] ) ) {
                                $custom_aria = json_decode( $settings['custom_aria_attributes'], true );
                                if ( json_last_error() !== JSON_ERROR_NONE ) {
                                    error_log( 'Error: Invalid JSON for ARIA attributes in widget settings.' );
                                } else if ( is_array( $custom_aria ) ) {
                                    foreach ( $custom_aria as $attr => $value ) {
                                        $attr  = esc_attr( sanitize_text_field( $attr ) );
                                        $value = esc_attr( sanitize_text_field( $value ) );
                                        if ( preg_match( '/^aria-[a-z]+$/', $attr ) ) {
                                            $svg_element->setAttribute( $attr, $value );
                                        }
                                    }
                                }
                            }

                            // Minify the final SVG content and cache it
                            $safe_svg = $dom->saveXML( $svg_element );
                            $safe_svg = preg_replace( '/>\s+</', '><', $safe_svg ); // Minify inline SVG

                            // Restore libxml settings
                            libxml_clear_errors();
                            libxml_use_internal_errors( $internal_errors );
                            if ( PHP_VERSION_ID < 80000 ) {
                                libxml_disable_entity_loader( false );
                            }

                            // Cache the sanitized SVG content
                            wp_cache_set( $cache_key, $safe_svg, $cache_group );
                        }

                        // Add lazy loading attribute if enabled
                        if ( isset( $settings['svg_lazy_load'] ) && 'yes' === $settings['svg_lazy_load'] ) {
                            $safe_svg = str_replace( '<svg', '<svg loading="lazy"', $safe_svg );
                        }

                        return wp_kses_post( $safe_svg ); // Escape the final SVG output for safety

                    } else {
                        return \Elementor\Icons_Manager::render_icon( $icon, $args, $instance );
                    }
                } else {
                    return \Elementor\Icons_Manager::render_icon( $icon, $args, $instance );
                }
            }
        }

        return \Elementor\Icons_Manager::render_icon( $icon, $args, $instance );
    }

    // SVG sanitization function
    private function sanitize_svg( $svg_content ) {
        // Use the enshrined SVG Sanitizer to sanitize SVG content
        $sanitizer = new enshrined\svgSanitize\Sanitizer();
        $sanitizer->minify( true );
        $safe_svg = $sanitizer->sanitize( $svg_content );

        // Re-validate the sanitized SVG with wp_kses_post for additional security
        return wp_kses_post( $safe_svg );
    }

    // Extract relevant settings that affect SVG rendering
    private function get_relevant_settings( $settings ) {
        $relevant_settings = [];
        $keys = [
            'enable_inline_svg',
            'custom_aria_attributes',
            'svg_lazy_load',
        ];

        foreach ( $keys as $key ) {
            if ( isset( $settings[ $key ] ) && '' !== $settings[ $key ] ) {
                $relevant_settings[ $key ] = $settings[ $key ];
            }
        }

        return $relevant_settings;
    }

    // Add content controls for SVG color in the Elementor Icon widget
    public function add_content_controls( $element, $args ) {
        $element->add_control(
            'primary_color',
            [
                'label'     => esc_html__( 'SVG Fill Color', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '',
                'selectors' => [
                    '{{WRAPPER}} .elementor-icon' => 'color: {{VALUE}};',
                ],
            ]
        );
    }

    // Setup cache clearing actions for different plugins and WordPress actions
    private function setup_cache_clearing() {
        add_action( 'rocket_clear_cache', [ $this, 'clear_cache' ] );
        add_action( 'autoptimize_action_cachepurged', [ $this, 'clear_cache' ] );
        add_action( 'w3tc_flush_all', [ $this, 'clear_cache' ] );
        add_action( 'wp_fast_cache_purge_all', [ $this, 'clear_cache' ] );
        add_action( 'ce_clear_all_cache', [ $this, 'clear_cache' ] );
        add_action( 'litespeed_purge_all', [ $this, 'clear_cache' ] );
        add_action( 'swcfpc_purge_cache', [ $this, 'clear_cache' ] );

        add_action( 'switch_theme', [ $this, 'clear_cache' ] );
        add_action( 'customize_save_after', [ $this, 'clear_cache' ] );
        add_action( 'save_post', [ $this, 'clear_cache' ] );
        add_action( 'add_attachment', [ $this, 'clear_cache' ] );
        add_action( 'edit_attachment', [ $this, 'clear_cache' ] );
        add_action( 'delete_attachment', [ $this, 'clear_cache' ] );
    }

    // Clear the SVG cache
    public function clear_cache() {
        $cache_version = get_option( 'inline_svg_elementor_cache_version', '1' );
        $cache_version++;
        update_option( 'inline_svg_elementor_cache_version', $cache_version );
    }

    // Handle manual cache clearing via admin bar action
    public function manual_clear_cache() {
        if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'inline_svg_elementor_clear_cache' ) ) {
            wp_die( esc_html__( 'You are not allowed to clear the cache.', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ) );
        }

        $this->clear_cache();

        wp_redirect( wp_get_referer() );
        exit;
    }

    // Add an option to the admin bar to manually clear the SVG cache
    public function add_admin_bar_clear_cache( $wp_admin_bar ) {
        if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $args = array(
            'id'    => 'inline_svg_elementor_clear_cache',
            'title' => esc_html__( 'Clear Inline SVG Cache', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ),
            'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=inline_svg_elementor_clear_cache' ), 'inline_svg_elementor_clear_cache' ),
            'meta'  => array( 'title' => esc_html__( 'Clear Inline SVG Cache', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ) ),
        );
        $wp_admin_bar->add_node( $args );
    }
}

// Initialize the plugin
new Inline_SVG_Elementor();
