<?php
/**
 * Plugin Name: VizzEx Signal Architect
 * Plugin URI:  https://vizzex.com
 * Description: Full-spectrum AI signal architecture: wraps content in &lt;article&gt;, sections headings with aria-labels, injects freshness &lt;time&gt; signals, auto-wraps images in &lt;figure&gt;/&lt;figcaption&gt;, provides E-E-A-T author footers, and offers an [aside] shortcode — all to maximize AI search citation confidence.
 * Version:     1.1.0
 * Author:      VizzEx
 * Author URI:  https://vizzex.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vizzex-signal-architect
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Prevent direct access.
}

/**
 * ============================================================
 * 1. CONSTANTS
 * ============================================================
 */
define( 'VIZZEX_SA_VERSION', '1.1.0' );
define( 'VIZZEX_SA_OPTION_POST_TYPES', 'vizzex_sa_enabled_post_types' );
define( 'VIZZEX_SA_OPTION_FEATURES', 'vizzex_sa_feature_toggles' );
define( 'VIZZEX_SA_META_KEY', '_vizzex_sa_enabled' );

/**
 * ============================================================
 * 2. ACTIVATION — Set sensible defaults
 * ============================================================
 */
function vizzex_sa_activate() {
    // Default: only "post" is enabled out of the box.
    if ( false === get_option( VIZZEX_SA_OPTION_POST_TYPES ) ) {
        update_option( VIZZEX_SA_OPTION_POST_TYPES, array( 'post' ) );
    }
    // Default feature toggles — all on by default.
    if ( false === get_option( VIZZEX_SA_OPTION_FEATURES ) ) {
        update_option( VIZZEX_SA_OPTION_FEATURES, array(
            'header_time'   => '1', // <header> with <time> freshness signals
            'footer_eeat'   => '1', // <footer> with author E-E-A-T
            'figure_wrap'   => '1', // Auto-wrap bare <img> in <figure>/<figcaption>
            'aside_shortcode' => '1', // [aside] shortcode
        ) );
    }
}
register_activation_hook( __FILE__, 'vizzex_sa_activate' );

/**
 * ============================================================
 * 3. GLOBAL SETTINGS PAGE — Choose which post types get the
 *    AI Signals metabox.
 * ============================================================
 */

/** 3a. Register the settings page under the Settings menu. */
function vizzex_sa_add_settings_page() {
    add_options_page(
        __( 'VizzEx Signal Architect', 'vizzex-signal-architect' ),
        __( 'Signal Architect', 'vizzex-signal-architect' ),
        'manage_options',
        'vizzex-signal-architect',
        'vizzex_sa_render_settings_page'
    );
}
add_action( 'admin_menu', 'vizzex_sa_add_settings_page' );

/** 3b. Register the setting so WordPress handles sanitisation. */
function vizzex_sa_register_settings() {
    register_setting(
        'vizzex_sa_settings_group',
        VIZZEX_SA_OPTION_POST_TYPES,
        array(
            'type'              => 'array',
            'sanitize_callback' => 'vizzex_sa_sanitize_post_types',
            'default'           => array( 'post' ),
        )
    );
    register_setting(
        'vizzex_sa_settings_group',
        VIZZEX_SA_OPTION_FEATURES,
        array(
            'type'              => 'array',
            'sanitize_callback' => 'vizzex_sa_sanitize_features',
            'default'           => array(
                'header_time'     => '1',
                'footer_eeat'     => '1',
                'figure_wrap'     => '1',
                'aside_shortcode' => '1',
            ),
        )
    );
}
add_action( 'admin_init', 'vizzex_sa_register_settings' );

/** 3c. Sanitise: only keep valid, public post type slugs. */
function vizzex_sa_sanitize_post_types( $input ) {
    if ( ! is_array( $input ) ) {
        return array();
    }
    $valid = get_post_types( array( 'public' => true ), 'names' );
    return array_values( array_intersect( $input, $valid ) );
}

/** 3d. Sanitise feature toggles. */
function vizzex_sa_sanitize_features( $input ) {
    if ( ! is_array( $input ) ) {
        return array();
    }
    $valid_keys = array( 'header_time', 'footer_eeat', 'figure_wrap', 'aside_shortcode' );
    $clean      = array();
    foreach ( $valid_keys as $key ) {
        $clean[ $key ] = ! empty( $input[ $key ] ) ? '1' : '';
    }
    return $clean;
}

/** 3e. Render the settings page. */
function vizzex_sa_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $enabled   = (array) get_option( VIZZEX_SA_OPTION_POST_TYPES, array( 'post' ) );
    $all_types = get_post_types( array( 'public' => true ), 'objects' );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'VizzEx Signal Architect', 'vizzex-signal-architect' ); ?></h1>
        <p style="max-width:720px;">
            <?php esc_html_e(
                'Select the post types where the "AI Signals" metabox should appear. '
                . 'When AI Signals is turned on for an individual post, all H2–H6 headings '
                . 'and their content will be automatically wrapped in semantic <section> tags '
                . 'with aria-labels — creating structured signal architecture for AI search.',
                'vizzex-signal-architect'
            ); ?>
        </p>

        <form method="post" action="options.php">
            <?php settings_fields( 'vizzex_sa_settings_group' ); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable for Post Types', 'vizzex-signal-architect' ); ?></th>
                    <td>
                        <fieldset>
                            <?php foreach ( $all_types as $pt ) : ?>
                                <label style="display:block; margin-bottom:6px;">
                                    <input
                                        type="checkbox"
                                        name="<?php echo esc_attr( VIZZEX_SA_OPTION_POST_TYPES ); ?>[]"
                                        value="<?php echo esc_attr( $pt->name ); ?>"
                                        <?php checked( in_array( $pt->name, $enabled, true ) ); ?>
                                    />
                                    <?php echo esc_html( $pt->labels->singular_name ); ?>
                                    <code style="color:#666;">(<?php echo esc_html( $pt->name ); ?>)</code>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <p class="description">
                            <?php esc_html_e(
                                'The AI Signals metabox will appear on the edit screen for each selected post type.',
                                'vizzex-signal-architect'
                            ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h2 style="margin-top:2em;"><?php esc_html_e( 'Signal Booster Features', 'vizzex-signal-architect' ); ?></h2>
            <p style="max-width:720px;">
                <?php esc_html_e(
                    'Enable or disable individual signal booster features. These enhance the core '
                    . '<article> and <section> architecture with additional semantic HTML5 signals.',
                    'vizzex-signal-architect'
                ); ?>
            </p>

            <?php
            $features = (array) get_option( VIZZEX_SA_OPTION_FEATURES, array(
                'header_time'     => '1',
                'footer_eeat'     => '1',
                'figure_wrap'     => '1',
                'aside_shortcode' => '1',
            ) );
            $feature_labels = array(
                'header_time'     => array(
                    'label' => __( 'Freshness Signals (<header> + <time>)', 'vizzex-signal-architect' ),
                    'desc'  => __( 'Injects a <header> block with <time> tags for the published and last-modified dates inside the <article> envelope.', 'vizzex-signal-architect' ),
                ),
                'footer_eeat'     => array(
                    'label' => __( 'Author E-E-A-T Footer (<footer>)', 'vizzex-signal-architect' ),
                    'desc'  => __( 'Appends a <footer> inside the <article> with author name, credentials, and bio. Pulls from VizzEx Pro author entities when available, falls back to WordPress user data.', 'vizzex-signal-architect' ),
                ),
                'figure_wrap'     => array(
                    'label' => __( 'Auto Figure Wrapping (<figure> + <figcaption>)', 'vizzex-signal-architect' ),
                    'desc'  => __( 'Automatically wraps bare <img> tags (not already inside a <figure>) in <figure> with a <figcaption> derived from the alt text.', 'vizzex-signal-architect' ),
                ),
                'aside_shortcode' => array(
                    'label' => __( '[aside] Shortcode', 'vizzex-signal-architect' ),
                    'desc'  => __( 'Registers a [aside] shortcode that wraps content in a semantic <aside> tag for secondary context that won\'t dilute the main section vector.', 'vizzex-signal-architect' ),
                ),
            );
            ?>
            <table class="form-table" role="presentation">
                <?php foreach ( $feature_labels as $key => $info ) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html( $info['label'] ); ?></th>
                        <td>
                            <label>
                                <input
                                    type="checkbox"
                                    name="<?php echo esc_attr( VIZZEX_SA_OPTION_FEATURES ); ?>[<?php echo esc_attr( $key ); ?>]"
                                    value="1"
                                    <?php checked( ! empty( $features[ $key ] ) ); ?>
                                />
                                <?php esc_html_e( 'Enabled', 'vizzex-signal-architect' ); ?>
                            </label>
                            <p class="description"><?php echo esc_html( $info['desc'] ); ?></p>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <?php submit_button( __( 'Save Changes', 'vizzex-signal-architect' ) ); ?>
        </form>
    </div>
    <?php
}

/**
 * ============================================================
 * 4. PER-POST METABOX — Toggle "AI Signals" on or off
 * ============================================================
 */

/** 4a. Register the metabox on all enabled post types. */
function vizzex_sa_add_meta_box() {
    $enabled_types = (array) get_option( VIZZEX_SA_OPTION_POST_TYPES, array( 'post' ) );

    foreach ( $enabled_types as $post_type ) {
        add_meta_box(
            'vizzex_sa_toggle',
            __( 'AI Signals — VizzEx Signal Architect', 'vizzex-signal-architect' ),
            'vizzex_sa_render_meta_box',
            $post_type,
            'side',
            'high'
        );
    }
}
add_action( 'add_meta_boxes', 'vizzex_sa_add_meta_box' );

/** 4b. Render the metabox contents. */
function vizzex_sa_render_meta_box( $post ) {
    wp_nonce_field( 'vizzex_sa_save_meta', 'vizzex_sa_nonce' );

    // Default is "off" (empty string).
    $enabled = get_post_meta( $post->ID, VIZZEX_SA_META_KEY, true );
    ?>
    <style>
        .vizzex-sa-toggle-wrap { padding: 8px 0; }
        .vizzex-sa-toggle-wrap label { font-weight: 600; cursor: pointer; }
        .vizzex-sa-toggle-wrap .description { margin-top: 8px; color: #666; font-size: 12px; }
        .vizzex-sa-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            margin-left: 8px;
        }
        .vizzex-sa-status.on  { background: #d4edda; color: #155724; }
        .vizzex-sa-status.off { background: #f8d7da; color: #721c24; }
    </style>
    <div class="vizzex-sa-toggle-wrap">
        <label for="vizzex_sa_enabled">
            <input
                type="checkbox"
                id="vizzex_sa_enabled"
                name="vizzex_sa_enabled"
                value="1"
                <?php checked( $enabled, '1' ); ?>
            />
            <?php esc_html_e( 'Enable AI Signals for this post', 'vizzex-signal-architect' ); ?>
            <span class="vizzex-sa-status <?php echo $enabled === '1' ? 'on' : 'off'; ?>">
                <?php echo $enabled === '1'
                    ? esc_html__( 'On', 'vizzex-signal-architect' )
                    : esc_html__( 'Off', 'vizzex-signal-architect' ); ?>
            </span>
        </label>
        <p class="description">
            <?php esc_html_e(
                'When enabled, all H2–H6 headings will be wrapped in semantic <section> tags with aria-labels on the front end.',
                'vizzex-signal-architect'
            ); ?>
        </p>
    </div>
    <script>
    (function(){
        var cb = document.getElementById('vizzex_sa_enabled');
        if (!cb) return;
        cb.addEventListener('change', function(){
            var badge = cb.parentNode.querySelector('.vizzex-sa-status');
            if (cb.checked) {
                badge.textContent = 'On';
                badge.className = 'vizzex-sa-status on';
            } else {
                badge.textContent = 'Off';
                badge.className = 'vizzex-sa-status off';
            }
        });
    })();
    </script>
    <?php
}

/** 4c. Save the metabox value. */
function vizzex_sa_save_meta( $post_id ) {
    // Security checks.
    if ( ! isset( $_POST['vizzex_sa_nonce'] ) || ! wp_verify_nonce( $_POST['vizzex_sa_nonce'], 'vizzex_sa_save_meta' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $value = isset( $_POST['vizzex_sa_enabled'] ) ? '1' : '';
    update_post_meta( $post_id, VIZZEX_SA_META_KEY, $value );
}
add_action( 'save_post', 'vizzex_sa_save_meta' );

/**
 * ============================================================
 * 5. SIGNAL BOOSTER HELPERS
 * ============================================================
 */

/** Helper: Check if a signal booster feature is enabled globally. */
function vizzex_sa_feature_enabled( $feature_key ) {
    $features = (array) get_option( VIZZEX_SA_OPTION_FEATURES, array(
        'header_time'     => '1',
        'footer_eeat'     => '1',
        'figure_wrap'     => '1',
        'aside_shortcode' => '1',
    ) );
    return ! empty( $features[ $feature_key ] );
}

/**
 * 5a. FRESHNESS SIGNALS — <header> with <time> tags.
 *
 * Builds a <header> block containing the post's published and
 * last-modified dates as <time datetime="..."> elements.
 * Placed at the top of the <article> envelope.
 */
function vizzex_sa_build_header_time() {
    $pub_iso      = get_the_date( 'c' );
    $pub_display  = get_the_date();
    $mod_iso      = get_the_modified_date( 'c' );
    $mod_display  = get_the_modified_date();

    $header  = '<header class="vizzex-freshness-signals">' . "\n";
    $header .= '  <time datetime="' . esc_attr( $pub_iso ) . '" itemprop="datePublished">Published: ' . esc_html( $pub_display ) . '</time>' . "\n";

    // Only show modified date if it differs from published date.
    if ( get_the_date( 'Y-m-d' ) !== get_the_modified_date( 'Y-m-d' ) ) {
        $header .= '  <time datetime="' . esc_attr( $mod_iso ) . '" itemprop="dateModified">Updated: ' . esc_html( $mod_display ) . '</time>' . "\n";
    }

    $header .= '</header>' . "\n";

    return $header;
}

/**
 * 5b. AUTHOR E-E-A-T FOOTER — <footer> with author credentials.
 *
 * Pulls author data from VizzEx Pro's author entities (vzx_schema_settings)
 * when available, otherwise falls back to WordPress native author meta.
 * Placed at the bottom of the <article> envelope, after the last <section>.
 */
function vizzex_sa_build_footer_eeat() {
    $post_author_id    = get_the_author_meta( 'ID' );
    $post_author_login = get_the_author_meta( 'user_login' );

    // Defaults from WordPress native.
    $author_name  = get_the_author_meta( 'display_name' );
    $author_title = '';
    $author_bio   = get_the_author_meta( 'description' );
    $author_url   = get_author_posts_url( $post_author_id );

    // Attempt to pull richer data from VizzEx Pro author entities.
    $vzx_settings = get_option( 'vzx_schema_settings', array() );
    if ( ! empty( $vzx_settings['authors'] ) && is_array( $vzx_settings['authors'] ) ) {
        foreach ( $vzx_settings['authors'] as $entity ) {
            $wp_ids = isset( $entity['wp_user_ids'] ) ? (array) $entity['wp_user_ids'] : array();
            // Match on user_login (string) or user ID (int).
            if ( in_array( $post_author_login, $wp_ids, true ) || in_array( $post_author_id, $wp_ids ) || in_array( (string) $post_author_id, $wp_ids, true ) ) {
                $author_name  = ! empty( $entity['name'] )        ? $entity['name']        : $author_name;
                $author_title = ! empty( $entity['job_title'] )   ? $entity['job_title']   : '';
                $author_bio   = ! empty( $entity['description'] ) ? $entity['description'] : $author_bio;
                $author_url   = ! empty( $entity['url'] )         ? $entity['url']         : $author_url;
                break;
            }
        }
    }

    $footer  = '<footer class="vizzex-author-authority">' . "\n";
    $footer .= '  <p class="vizzex-author-name">';
    $footer .= 'Written by: <a href="' . esc_url( $author_url ) . '" rel="author">' . esc_html( $author_name ) . '</a>';
    if ( $author_title ) {
        $footer .= ' — ' . esc_html( $author_title );
    }
    $footer .= '</p>' . "\n";

    if ( $author_bio ) {
        $footer .= '  <p class="vizzex-author-bio">' . esc_html( $author_bio ) . '</p>' . "\n";
    }

    $footer .= '</footer>' . "\n";

    return $footer;
}

/**
 * 5c. AUTO FIGURE WRAPPING — Wrap bare <img> tags in <figure>/<figcaption>.
 *
 * Finds <img> tags that are NOT already inside a <figure> element and wraps
 * them in <figure> with a <figcaption> derived from the image's alt text.
 * Skips images that already have a caption or are inside a figure.
 */
function vizzex_sa_auto_figure_wrap( $content ) {
    // Match <img> tags that are NOT preceded by <figure (with possible attributes).
    // We use a callback to check context and wrap appropriately.
    return preg_replace_callback(
        '/<img\s[^>]*>/is',
        function ( $match ) use ( $content ) {
            $img_tag = $match[0];

            // Get the position of this img in the content.
            $pos = strpos( $content, $img_tag );

            // Check if this <img> is already inside a <figure> tag.
            // Look backwards from the img position for an unclosed <figure>.
            $before = substr( $content, 0, $pos );
            $figure_open  = substr_count( strtolower( $before ), '<figure' );
            $figure_close = substr_count( strtolower( $before ), '</figure' );
            if ( $figure_open > $figure_close ) {
                // Already inside a <figure> — leave it alone.
                return $img_tag;
            }

            // Extract alt text for the figcaption.
            $alt = '';
            if ( preg_match( '/alt=["\']([^"\']*)["\']/', $img_tag, $alt_match ) ) {
                $alt = trim( $alt_match[1] );
            }

            $output  = '<figure class="vizzex-figure">' . "\n";
            $output .= '  ' . $img_tag . "\n";
            if ( $alt ) {
                $output .= '  <figcaption>' . esc_html( $alt ) . '</figcaption>' . "\n";
            }
            $output .= '</figure>';

            return $output;
        },
        $content
    );
}

/**
 * 5d. [aside] SHORTCODE — Semantic secondary context.
 *
 * Usage: [aside]Your bonus tip or definition here.[/aside]
 * Outputs: <aside class="vizzex-secondary-context">...</aside>
 */
function vizzex_sa_aside_shortcode( $atts, $content = null ) {
    if ( is_null( $content ) ) {
        return '';
    }
    return '<aside class="vizzex-secondary-context">' . "\n"
        . do_shortcode( $content ) . "\n"
        . '</aside>';
}
// Register the shortcode if the feature is enabled.
// Note: Shortcode registration runs early, so we check on init.
function vizzex_sa_register_shortcodes() {
    if ( vizzex_sa_feature_enabled( 'aside_shortcode' ) ) {
        add_shortcode( 'aside', 'vizzex_sa_aside_shortcode' );
    }
}
add_action( 'init', 'vizzex_sa_register_shortcodes' );

/**
 * ============================================================
 * 6. THE AUTO-SECTIONER — The core content filter
 * ============================================================
 *
 * Strategy: "Full-Spectrum" AI Signal Architecture.
 *
 * 1. The entire post is wrapped in an <article class="vizzex-knowledge-object">
 *    tag — the hard boundary that tells AI crawlers this is a complete,
 *    self-contained Knowledge Object.
 *
 * 2. A <header> with <time> tags provides freshness signals (published + modified).
 *
 * 3. Bare <img> tags are auto-wrapped in <figure>/<figcaption> for multimodal context.
 *
 * 4. Every H2–H6 starts a new <section> at the same nesting level.
 *    This keeps the markup flat and prevents a "closing tag waterfall."
 *    Each section gets:
 *      - aria-label  = the heading text (stripped of inner HTML)
 *      - class       = "semantic-unit unit-h{level}"
 *
 * 5. A <footer> with author E-E-A-T data anchors authorship authority.
 */
function vizzex_sa_auto_section_wrapper( $content ) {
    // 1. Don't run in the admin area.
    if ( is_admin() ) {
        return $content;
    }

    // 2. Only run on singular views of enabled post types.
    if ( ! is_singular() ) {
        return $content;
    }

    $enabled_types = (array) get_option( VIZZEX_SA_OPTION_POST_TYPES, array( 'post' ) );
    if ( ! in_array( get_post_type(), $enabled_types, true ) ) {
        return $content;
    }

    // 3. Check the per-post toggle — must be explicitly "on."
    $post_id = get_the_ID();
    if ( get_post_meta( $post_id, VIZZEX_SA_META_KEY, true ) !== '1' ) {
        return $content;
    }

    // 4. Auto-wrap bare <img> tags in <figure>/<figcaption> (if enabled).
    if ( vizzex_sa_feature_enabled( 'figure_wrap' ) ) {
        $content = vizzex_sa_auto_figure_wrap( $content );
    }

    // 5. Split the content at every H2–H6 tag.
    $parts = preg_split(
        '/(<h[2-6][^>]*>.*?<\/h[2-6]>)/is',
        $content,
        -1,
        PREG_SPLIT_DELIM_CAPTURE
    );

    // If no headings were found, return content untouched.
    if ( count( $parts ) <= 1 ) {
        return $content;
    }

    $new_content  = '';
    $section_open = false;

    foreach ( $parts as $part ) {
        // Is this part a heading tag?
        if ( preg_match( '/<h([2-6])[^>]*>(.*?)<\/h[2-6]>/is', $part, $matches ) ) {
            $h_level    = $matches[1];
            $h_text     = wp_strip_all_tags( $matches[2] );
            $aria_label = esc_attr( $h_text );

            // Close any previously open section.
            if ( $section_open ) {
                $new_content .= "\n</section>\n";
            }

            // Open a new section.
            $new_content .= '<section aria-label="' . $aria_label . '" class="semantic-unit unit-h' . $h_level . '">' . "\n";
            $new_content .= $part;
            $section_open = true;
        } else {
            // Regular content (paragraphs, images, lists, etc.).
            $new_content .= $part;
        }
    }

    // Close the final section.
    if ( $section_open ) {
        $new_content .= "\n</section>";
    }

    // 6. Build the "Global Envelope" — <article> with optional header and footer.
    $article  = '<article class="vizzex-knowledge-object">' . "\n";

    // 6a. Freshness signals — <header> with <time> tags.
    if ( vizzex_sa_feature_enabled( 'header_time' ) ) {
        $article .= vizzex_sa_build_header_time();
    }

    // 6b. The sectioned content.
    $article .= $new_content . "\n";

    // 6c. Author E-E-A-T footer.
    if ( vizzex_sa_feature_enabled( 'footer_eeat' ) ) {
        $article .= vizzex_sa_build_footer_eeat();
    }

    $article .= '</article>';

    $new_content = $article;

    return $new_content;
}
// Priority 99 — run late so shortcodes and blocks are already rendered.
add_filter( 'the_content', 'vizzex_sa_auto_section_wrapper', 99 );

/**
 * ============================================================
 * 7. SETTINGS LINK on the Plugins page
 * ============================================================
 */
function vizzex_sa_plugin_action_links( $links ) {
    $settings_link = '<a href="' . admin_url( 'options-general.php?page=vizzex-signal-architect' ) . '">'
        . __( 'Settings', 'vizzex-signal-architect' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'vizzex_sa_plugin_action_links' );

/**
 * ============================================================
 * 8. GUTENBERG / BLOCK EDITOR COMPATIBILITY
 *    Show the metabox in the block editor sidebar via a
 *    simple plugin sidebar isn't needed — WordPress renders
 *    PHP metaboxes in the block editor automatically.
 *    But we ensure compatibility by declaring support.
 * ============================================================
 */
function vizzex_sa_add_editor_styles() {
    // Adds a tiny admin notice in the editor when AI Signals is active.
    global $post;
    if ( ! $post ) {
        return;
    }

    $enabled_types = (array) get_option( VIZZEX_SA_OPTION_POST_TYPES, array( 'post' ) );
    if ( ! in_array( get_post_type( $post ), $enabled_types, true ) ) {
        return;
    }

    if ( get_post_meta( $post->ID, VIZZEX_SA_META_KEY, true ) === '1' ) {
        echo '<style>#vizzex_sa_toggle { border-left: 4px solid #28a745; }</style>';
    }
}
add_action( 'admin_head', 'vizzex_sa_add_editor_styles' );
