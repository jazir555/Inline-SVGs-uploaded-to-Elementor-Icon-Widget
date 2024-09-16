<?php
/**
 * Plugin Name: Inline SVG for Elementor Icon Widget
 * Description: Adds an option to inline SVGs in Elementor's Icon widget with enhanced security, accessibility, styling compatibility, and optimized performance.
 * Version: 2.1.0
 * Author: Your Name
 * Text Domain: inline-svg-elementor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'INLINE_SVG_ELEMENTOR_TEXT_DOMAIN', 'inline-svg-elementor' );

// Include the SVG Sanitizer library.
if ( ! class_exists( 'enshrined\svgSanitize\Sanitizer' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
}

class Inline_SVG_Elementor {

    /**
     * Constructor for the plugin.
     */
    public function __construct() {
        add_action( 'elementor/element/icon/section_style_icon/after_section_end', [ $this, 'add_controls' ], 10, 2 );
        add_filter( 'elementor/icon/print_template', [ $this, 'inline_svg' ], 10, 3 );
        add_filter( 'elementor/frontend/icon', [ $this, 'inline_svg' ], 10, 3 );
        add_action( 'elementor/element/icon/section_icon/before_section_end', [ $this, 'add_content_controls' ], 10, 2 );

        $this->setup_cache_clearing();
        add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_clear_cache' ], 100 );
        add_action( 'admin_post_inline_svg_elementor_clear_cache', [ $this, 'manual_clear_cache' ] );
    }

    /**
     * Add controls to the Icon widget's Advanced tab.
     */
    public function add_controls( $element, $args ) {
        $element->start_controls_section(
            'section_inline_svg',
            [
                'label' => esc_html__( 'Inline SVG', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ),
                'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
            ]
        );

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

    /**
     * Inline SVG rendering.
     */
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

                    if ( file_exists( $svg_file_path ) ) {
                        $cache_key   = 'inline_svg_' . md5( $attachment_id . wp_json_encode( $this->get_relevant_settings( $settings ) ) );
                        $cache_group = 'inline_svg_elementor';
                        $safe_svg    = wp_cache_get( $cache_key, $cache_group );

                        if ( false === $safe_svg ) {
                            $svg_content = file_get_contents( $svg_file_path );

                            if ( PHP_VERSION_ID < 80000 ) {
                                $old_libxml = libxml_disable_entity_loader( true );
                            }

                            $internal_errors = libxml_use_internal_errors( true );
                            $dom             = new DOMDocument();

                            if ( ! $dom->loadXML( $svg_content, LIBXML_NONET ) ) {
                                error_log( 'Error: Failed to load SVG content for attachment ID ' . $attachment_id );
                                libxml_clear_errors();
                                libxml_use_internal_errors( $internal_errors );

                                if ( PHP_VERSION_ID < 80000 ) {
                                    libxml_disable_entity_loader( $old_libxml );
                                }

                                return \Elementor\Icons_Manager::render_icon( $icon, $args, $instance );
                            }

                            $safe_svg = $this->sanitize_svg( $dom->saveXML() );

                            if ( ! $safe_svg ) {
                                return \Elementor\Icons_Manager::render_icon( $icon, $args, $instance );
                            }

                            $dom->loadXML( $safe_svg );
                            $svg_element = $dom->documentElement;

                            // Handle ARIA attributes.
                            if ( ! empty( $settings['custom_aria_attributes'] ) ) {
                                $custom_aria = json_decode( $settings['custom_aria_attributes'], true );

                                if ( json_last_error() !== JSON_ERROR_NONE ) {
                                    error_log( 'Error: Invalid JSON for ARIA attributes in widget settings.' );
                                    $custom_aria = [];
                                }

                                $allowed_aria_attributes = [ 'aria-label', 'aria-hidden', 'aria-labelledby', 'aria-describedby', 'aria-controls', 'aria-expanded', 'aria-pressed' ];

                                foreach ( $custom_aria as $attr => $value ) {
                                    $attr  = sanitize_key( $attr );
                                    $value = esc_attr( sanitize_text_field( $value ) );
                                    if ( in_array( $attr, $allowed_aria_attributes, true ) ) {
                                        $svg_element->setAttribute( $attr, $value );
                                    } else {
                                        error_log( "Unsupported ARIA attribute: {$attr}." );
                                    }
                                }
                            }

                            $safe_svg = $dom->saveXML( $svg_element );
                            $safe_svg = preg_replace( '/>\s+</', '><', $safe_svg );
                            libxml_clear_errors();
                            libxml_use_internal_errors( $internal_errors );

                            if ( PHP_VERSION_ID < 80000 ) {
                                libxml_disable_entity_loader( $old_libxml );
                            }

                            wp_cache_set( $cache_key, $safe_svg, $cache_group );
                        }

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

    private function sanitize_svg( $svg_content ) {
        $sanitizer = new enshrined\svgSanitize\Sanitizer();
        $sanitizer->minify( true );
        return $sanitizer->sanitize( $svg_content );
    }

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

    private function setup_cache_clearing() {
        $cache_clear_actions = [
            'rocket_clear_cache', 'autoptimize_action_cachepurged', 'w3tc_flush_all', 'wp_fast_cache_purge_all',
            'ce_clear_all_cache', 'litespeed_purge_all', 'swcfpc_purge_cache', 'switch_theme', 'customize_save_after',
            'save_post', 'add_attachment', 'edit_attachment', 'delete_attachment'
        ];

        foreach ( $cache_clear_actions as $action ) {
            add_action( $action, [ $this, 'clear_cache' ] );
        }
    }

    public function clear_cache() {
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }

        if ( function_exists( 'wp_cache_clear_cache' ) ) {
            wp_cache_clear_cache();
        }
    }

    public function manual_clear_cache() {
        if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'inline_svg_elementor_clear_cache' ) ) {
            wp_die( esc_html__( 'You are not allowed to clear the cache.', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ) );
        }

        $this->clear_cache();

        wp_safe_redirect( wp_get_referer() );
        exit;
    }

    public function add_admin_bar_clear_cache( $wp_admin_bar ) {
        if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $args = [
            'id'    => 'inline_svg_elementor_clear_cache',
            'title' => esc_html__( 'Clear Inline SVG Cache', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ),
            'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=inline_svg_elementor_clear_cache' ), 'inline_svg_elementor_clear_cache' ),
            'meta'  => [ 'title' => esc_html__( 'Clear Inline SVG Cache', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ) ],
        ];

        $wp_admin_bar->add_node( $args );
    }
}

new Inline_SVG_Elementor();
