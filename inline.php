<?php
/**
 * Plugin Name: Inline SVG for Elementor Icon Widget
 * Description: Adds an option to inline SVGs in Elementor's Icon widget with enhanced security, accessibility, styling compatibility, and optimized performance.
 * Version: 2.0.1
 * Author: Your Name
 * Text Domain: inline-svg-elementor
 */

use Elementor\Controls_Manager;
use Elementor\Icons_Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Define the text domain as a constant

// Define the text domain as a constant

// Always define the text domain constant
define( 'INLINE_SVG_ELEMENTOR_TEXT_DOMAIN', 'inline-svg-elementor' );



// Include the SVG Sanitizer library
if ( ! class_exists( 'enshrined\svgSanitize\Sanitizer' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
}

// Main Plugin Class
class Inline_SVG_Elementor {

    public function __construct() {
        // Add controls to the Icon widget

        // Add controls to the Icon Box widget (style section for the icon box icon)
        
        // Add controls to the Icon Box widget (style section for the icon box icon)
        add_action( 'elementor/element/icon-box/section_icon/before_section_end', [ $this, 'add_controls' ], 10, 2 );

        // Add controls to the Social Icons widget (style section for individual social icons)
        add_action( 'elementor/element/social-icons/section_icon_s
        // Add controls to the Social Icons widget (style section for individual social icons)
        add_action( 'elementor/element/social-icons/section_icon_style/before_section_end', [ $this, 'add_controls' ], 10, 2 );

        // Add controls to the Icon List widget (style section for icon list items)
        add_action( 'elementor/element/icon-list/section_style_icon/before_section_end', [ $this, 'add_controls' ], 10, 2 );

        // Add controls to the Share Buttons widget (style section for the button icons)
        add_action( 'elementor/element/share-buttons/section_share_buttons_icons/before_section_end', [ $this, 'add_controls' ], 10, 2 );


        // Add controls to the Icon List widget (style section for icon list items)
        add_action( 'elementor/element/icon-list/section_style_icon/before_section_end', [ $this, 'add_controls' ], 10, 2 );

        // Add controls to the Share Buttons widget (style section for the button icons)
        add_action( 'elementor/element/share-buttons/section_share_buttons_icons/before_section_end', [ $this, 'add_controls' ], 10, 2 );

        // Ensure all actions and filters are properly structured
        add_filter( 'elementor/icon-box/print_template', [ $this, 'inline_svg' ], 10, 3 );
        add_filter( 'elementor/frontend/icon-box', [ $this, 'inline_svg' ], 10, 3 );

        add_filter( 'elementor/social-icons/print_template', [ $this, 'inline_svg' ], 10, 3 );
        add_filter( 'elementor/frontend/social-icons', [ $this, 'inline_svg' ], 10, 3 );

        add_filter( 'elementor/icon-list/print_template', [ $this, 'inline_svg' ], 10, 3 );
        add_filter( 'elementor/frontend/icon-list', [ $this, 'inline_svg' ], 10, 3 );

        add_filter( 'elementor/share-buttons/print_template', [ $this, 'inline_svg' ], 10, 3 );
        add_filter( 'elementor/frontend/share-buttons', [ $this, 'inline_svg' ], 10, 3 );

        
        // Add controls to the Social Icons widget (style section for individual social icons)
        add_action( 'elementor/element/social-icons/section_icon_s
        // Add controls to the Social Icons widget (style section for individual social icons)
        add_action( 'elementor/element/social-icons/section_icon_style/before_section_end', [ $this, 'add_controls' ], 10, 2 );

        // Add controls to the Icon List widget (style section for icon list items)
        add_action( 'elementor/element/icon-list/section_style_icon/before_section_end', [ $this, 'add_controls' ], 10, 2 );

        // Add controls to the Share Buttons widget (style section for the button icons)
        add_action( 'elementor/element/share-buttons/section_share_buttons_icons/before_section_end', [ $this, 'add_controls' ], 10, 2 );

        
        // Add controls to the Icon List widget (style section for icon list items)
        add_action( 'elementor/element/icon-list/section_style_icon/before_section_end', [ $this, 'add_controls' ], 10, 2 );
        
        // Add controls to the Share Buttons widget (style section for the button icons)
        add_action( 'elementor/element/share-buttons/section_share_buttons_icons/before_section_end', [ $this, 'add_controls' ], 10, 2 );

        // Modify Icon Box, Social Icons, Icon List, and Share Buttons widget rendering
        add_filter( 'elementor/icon-box/print_template', [ $this, 'inline_svg' ], 10, 3 );
        add_filter( 'elementor/frontend/icon-box', [ $this, 'inline_svg' ], 10, 3 );

        add_filter( 'elementor/social-icons/print_template', [ $this, 'inline_svg' ], 10, 3 );
        add_filter( 'elementor/frontend/social-icons', [ $this, 'inline_svg' ], 10, 3 );

        add_filter( 'elementor/icon-list/print_template', [ $this, 'inline_svg' ], 10, 3 );
        add_filter( 'elementor/frontend/icon-list', [ $this, 'inline_svg' ], 10, 3 );

        add_filter( 'elementor/share-buttons/print_template', [ $this, 'inline_svg' ], 10, 3 );
        add_filter( 'elementor/frontend/share-buttons', [ $this, 'inline_svg' ], 10, 3 );
        add_action( 'elementor/element/icon/section_style_icon/after_section_end', [ $this, 'add_controls' ], 10, 2 );

        // Modify Icon widget rendering
        add_filter( 'elementor/icon/print_template', [ $this, 'inline_svg' ], 10, 3 );
        add_filter( 'elementor/frontend/icon', [ $this, 'inline_svg' ], 10, 3 );

        // Add content controls
        add_action( 'elementor/element/icon/section_icon/before_section_end', [ $this, 'add_content_controls' ], 10, 2 );

        // Setup universal cache clearing actions
        $this->setup_cache_clearing();

        // Add admin bar menu for cache clearing
        add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_clear_cache' ], 100 );

        // Handle manual cache clearing
        add_action( 'admin_post_inline_svg_elementor_clear_cache', [ $this, 'manual_clear_cache' ] );
    }

    // Add controls to the Icon widget's Advanced tab
    
public function add_controls($element) {
    $element->start_controls_section(
        'section_inline_svg',
        [
            'label' => esc_html__( 'Inline SVG', 'inline-svg-elementor' ),
            'tab'   => Controls_Manager::TAB_ADVANCED,
        ]
    );

    // Add the toggle control to enable inlining SVG
    $element->add_control(
        'enable_inline_svg',
        [
            'label'        => esc_html__( 'Enable Inline SVG', 'inline-svg-elementor' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => esc_html__( 'Yes', 'inline-svg-elementor' ),
            'label_off'    => esc_html__( 'No', 'inline-svg-elementor' ),
            'return_value' => 'yes',
            'default'      => 'no',
        ]
    );

    // Add custom ARIA attributes control
    $element->add_control(
        'custom_aria_attributes',
        [
            'label'       => esc_html__( 'Custom ARIA Attributes', 'inline-svg-elementor' ),
            'type'        => Controls_Manager::TEXTAREA,
            'description' => esc_html__( 'Add custom ARIA attributes in JSON format, e.g., {"aria-label": "My SVG"}' ),
        ]
    );

    $element->end_controls_section();  // Properly closing the controls section
}
', 'inline-svg-elementor' ),
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
                'label'        => esc_html__( 'Lazy Load SVG', 'inline-svg-elementor' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => esc_html__( 'Yes', 'inline-svg-elementor' ),
                'label_off'    => esc_html__( 'No', 'inline-svg-elementor' ),
                'return_value' => 'yes',
                'default'      => 'no',
                'description'  => esc_html__( 'Defer loading of the SVG until it is visible.', 'inline-svg-elementor' ),
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

                        // Optimize cache key generation
                        $cache_key = 'inline_svg_' . md5( $attachment_id . wp_json_encode( $this->get_relevant_settings( $settings ) ) );
                        $cache_group   = 'inline_svg_elementor';
                        $safe_svg      = wp_cache_get( $cache_key, $cache_group );

                        if ( false === $safe_svg ) {
                            $svg_content = file_get_contents( $svg_file_path );

                            // Handle libxml_disable_entity_loader() only for PHP < 8.0
                            if ( PHP_VERSION_ID < 80000 ) {
                                libxml_disable_entity_loader();
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
                                return Icons_Manager::render_icon( $icon, $args, $instance );
                            }

                            // Sanitize the SVG content
                            $safe_svg = $this->sanitize_svg( $dom->saveXML() );

                            if ( ! $safe_svg ) {
                                return Icons_Manager::render_icon( $icon, $args, $instance );
                            }

                            $dom->loadXML( $safe_svg );
                            $svg_element = $dom->documentElement;

                            // Handle ARIA attributes if provided
                            if ( ! empty( $settings['custom_aria_attributes'] ) ) {
                                $custom_aria = json_decode( $settings['custom_aria_attributes'], true );
                                if ( json_last_error() !== JSON_ERROR_NONE ) {
                                    error_log( 'Error: Invalid JSON for ARIA attributes in widget settings.' );
                                    $custom_aria = [];
                                }

                                // Allow only whitelisted ARIA attributes
                                $allowed_aria_attributes = ['aria-label', 'aria-hidden', 'aria-labelledby'];
                                foreach ( $custom_aria as $attr => $value ) {
                                    $attr  = sanitize_key($attr);
                                    $value = esc_attr(sanitize_text_field($value));
                                    if ( in_array( $attr, $allowed_aria_attributes, true ) ) {
                                        $svg_element->setAttribute( $attr, $value );
                                    } else {
                                        error_log("Unsupported ARIA attribute: $attr.");
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
                        return Icons_Manager::render_icon( $icon, $args, $instance );
                    }
                } else {
                    return Icons_Manager::render_icon( $icon, $args, $instance );
                }
            }
        }

        return Icons_Manager::render_icon( $icon, $args, $instance );
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
    public function add_content_controls($element) {
        $element->add_control(
            'primary_color',
            [
                'label'     => esc_html__( 'SVG Fill Color', 'inline-svg-elementor' ),
                'type'      => Controls_Manager::COLOR,
                'default'   => '',
                'selectors' => [
                    '{{WRAPPER}} .elementor-icon' => 'color: {{VALUE}};',
                ],
            ]
        );
    }

    // Setup cache clearing for various events
    private function setup_cache_clearing() {
        $cache_clear_actions = [
            'rocket_clear_cache', 'autoptimize_action_cachepurged', 'w3tc_flush_all', 'wp_fast_cache_purge_all',
            'ce_clear_all_cache', 'litespeed_purge_all', 'swcfpc_purge_cache', 'switch_theme', 'customize_save_after',
            'save_post', 'add_attachment', 'edit_attachment', 'delete_attachment'
        ];

        foreach ($cache_clear_actions as $action) {
            add_action($action, [$this, 'clear_cache']);
        }
    }

    // Universal cache clearing function
    public function clear_cache() {
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        // Trigger cache clearing for page caching plugins through core methods if supported
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
    }

    // Handle manual cache clearing via admin bar action
    public function manual_clear_cache() {
        if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'inline_svg_elementor_clear_cache' ) ) {
            wp_die( esc_html__( 'You are not allowed to clear the cache.', 'inline-svg-elementor' ) );
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
            'title' => esc_html__( 'Clear Inline SVG Cache', 'inline-svg-elementor' ),
            'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=inline_svg_elementor_clear_cache' ), 'inline_svg_elementor_clear_cache' ),
            'meta'  => array( 'title' => esc_html__( 'Clear Inline SVG Cache', 'inline-svg-elementor' ) ),
        );
        $wp_admin_bar->add_node( $args );
    }
}

// Initialize the plugin
new Inline_SVG_Elementor();
