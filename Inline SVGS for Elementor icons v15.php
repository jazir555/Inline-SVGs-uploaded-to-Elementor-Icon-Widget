<?php
/**
 * Plugin Name: Inline SVG for Elementor Icon Widget
 * Description: Adds an option to inline SVGs in Elementor's Icon widget with enhanced security, accessibility, styling compatibility, and optimized performance.
 * Version: 2.6.0
 * Author: Your Name
 * Text Domain: inline-svg-elementor
 */

namespace InlineSVGElementor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'INLINE_SVG_ELEMENTOR_TEXT_DOMAIN', 'inline-svg-elementor' );

// Automatically load SVG sanitizer library.
if ( ! class_exists( 'enshrined\svgSanitize\Sanitizer' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
}

class Inline_SVG_Elementor {

    private $cache_version;

    public function __construct() {
        // Load the cache version from the database, default to 1.
        $this->cache_version = get_option( 'inline_svg_elementor_cache_version', 1 );

        // Add necessary hooks for Elementor widget controls and rendering.
        add_action( 'elementor/element/icon/section_style_icon/after_section_end', [ $this, 'add_controls' ], 10, 2 );
        add_filter( 'elementor/icon/print_template', [ $this, 'inline_svg' ], 10, 3 );
        add_filter( 'elementor/frontend/icon', [ $this, 'inline_svg' ], 10, 3 );
        add_action( 'elementor/element/icon/section_icon/before_section_end', [ $this, 'add_content_controls' ], 10, 2 );

        // Cache clearing hooks.
        $this->setup_cache_clearing();

        // Add admin bar menu for cache clearing.
        add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_clear_cache' ], 100 );
        add_action( 'admin_post_inline_svg_elementor_clear_cache', [ $this, 'manual_clear_cache' ] );

        // Add setting for default ARIA attributes.
        add_action( 'admin_init', [ $this, 'add_settings' ] );
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );

        // Update cache version when plugin settings are updated.
        add_action( 'update_option_inline_svg_elementor_settings', [ $this, 'update_cache_version' ] );

        // Enqueue the script for lazy loading using Intersection Observer.
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_lazy_load_script' ] );
    }

    /**
     * Enqueue JavaScript for lazy loading SVGs using Intersection Observer.
     */
    public function enqueue_lazy_load_script() {
        wp_enqueue_script( 'svg-lazy-load', plugin_dir_url( __FILE__ ) . 'js/svg-lazy-load.js', [], '1.0', true );
    }

    /**
     * Add SVG-related controls to the Elementor Icon widget.
     *
     * @param Elementor\Widget_Base $element The widget instance.
     * @param array                 $args    Additional arguments.
     */
    public function add_controls( $element, $args ) {
        $element->start_controls_section(
            'section_inline_svg',
            [
                'label' => esc_html__( 'Inline SVG', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ),
                'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
            ]
        );

        // Option to enable SVG inlining.
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

        // Add ARIA attributes option based on global settings.
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

        // Lazy load option for SVGs.
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

        // File size limit control.
        $element->add_control(
            'svg_max_file_size',
            [
                'label'        => esc_html__( 'Max SVG File Size (KB)', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ),
                'type'         => \Elementor\Controls_Manager::NUMBER,
                'min'          => 0,
                'default'      => 512, // Default size limit of 512 KB.
                'condition'    => [
                    'enable_inline_svg' => 'yes',
                ],
                'description'  => esc_html__( 'SVGs larger than this size will not be inlined.', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ),
            ]
        );

        $element->end_controls_section();
    }

    /**
     * Handle inline SVG rendering.
     *
     * @param array             $icon      The icon settings.
     * @param array             $args      Additional arguments.
     * @param Elementor\Widget_Base|null $instance The widget instance.
     *
     * @return string Rendered SVG or icon fallback.
     */
    public function inline_svg( $icon, $args = [], $instance = null ) {
        if ( empty( $icon['value'] ) || ! $instance instanceof \Elementor\Widget_Base ) {
            return '';
        }

        $settings = $instance->get_settings_for_display();

        // Check if SVG inlining is enabled.
        if ( isset( $settings['enable_inline_svg'] ) && 'yes' === $settings['enable_inline_svg'] ) {
            if ( is_numeric( $icon['value'] ) ) {
                $attachment_id = absint( $icon['value'] );
                $attachment    = get_post( $attachment_id );

                if ( $attachment && 'image/svg+xml' === get_post_mime_type( $attachment_id ) ) {
                    $svg_file_path = get_attached_file( $attachment_id );

                    // Check file size limit and provide user feedback.
                    $max_file_size = isset( $settings['svg_max_file_size'] ) ? absint( $settings['svg_max_file_size'] ) : 512;
                    if ( filesize( $svg_file_path ) > ( $max_file_size * 1024 ) ) {
                        \Elementor\Plugin::$instance->frontend->add_render_error( esc_html__( 'SVG file too large to inline.', INLINE_SVG_ELEMENTOR_TEXT_DOMAIN ) );
                        return \Elementor\Icons_Manager::render_icon( $icon, $args, $instance );
                    }

                    if ( file_exists( $svg_file_path ) ) {
                        // Cache key generation with versioning.
                        $cache_key   = 'inline_svg_' . md5( $attachment_id . wp_json_encode( $this->get_relevant_settings( $settings ) ) ) . '_' . $this->cache_version;
                        $cache_group = 'inline_svg_elementor';
                        $safe_svg    = wp_cache_get( $cache_key, $cache_group );

                        if ( false === $safe_svg ) {
                            $svg_content = file_get_contents( $svg_file_path );
                            if ( PHP_VERSION_ID < 80000 ) {
                                $old_libxml = libxml_disable_entity_loader( true );
                            }

                            $internal_errors = libxml_use_internal_errors( true );
                            $dom             = new \DOMDocument();

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

                            // Add ARIA attributes if provided.
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

                            // Minify and cache the SVG.
                            $safe_svg = preg_replace( '/>\s+</', '><', $dom->saveXML( $svg_element ) );
                            libxml_clear_errors();
                            libxml_use_internal_errors( $internal_errors );

                            if ( PHP_VERSION_ID < 80000 ) {
                                libxml_disable_entity_loader( $old_libxml );
                            }

                            wp_cache_set( $cache_key, $safe_svg, $cache_group );
                        }

                        // Handle lazy-loading with placeholder and Intersection Observer.
                        if ( isset( $settings['svg_lazy_load'] ) && 'yes' === $settings['svg_lazy_load'] ) {
                            $safe_svg = '<img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" class="svg-placeholder" alt="">' . str_replace( '<svg', '<svg class="lazy-svg" data-lazy="true"', $safe_svg );
                        }

                        return wp_kses_post( $safe_svg );
                    }
                }
            }
        }

        // Fallback to default icon rendering.
        return \Elementor\Icons_Manager::render_icon( $icon, $args, $instance );
    }

    // Additional methods for settings, cache management, and logging follow here.
}

new \InlineSVGElementor\Inline_SVG_Elementor();
