<?php
/**
 * Plugin Name: VizzEx Signal Architect
 * Plugin URI:  https://vizzex.com
 * Description: Automatically wraps H2–H6 headings and their content in semantic &lt;section&gt; tags with aria-labels, creating structured "signal architecture" for AI search engines and citation systems.
 * Version:     1.0.0
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
define( 'VIZZEX_SA_VERSION', '1.0.0' );
define( 'VIZZEX_SA_OPTION_POST_TYPES', 'vizzex_sa_enabled_post_types' );
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

/** 3d. Render the settings page. */
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
 * 5. THE AUTO-SECTIONER — The core content filter
 * ============================================================
 *
 * Strategy: "Flat Sibling" approach with a "Global Envelope."
 *
 * 1. The entire post is wrapped in an <article class="vizzex-knowledge-object">
 *    tag — the hard boundary that tells AI crawlers this is a complete,
 *    self-contained Knowledge Object.
 *
 * 2. Every H2–H6 starts a new <section> at the same nesting level.
 *    This keeps the markup flat and prevents a "closing tag waterfall."
 *    Each section gets:
 *      - aria-label  = the heading text (stripped of inner HTML)
 *      - class       = "semantic-unit unit-h{level}"
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

    // 4. Split the content at every H2–H6 tag.
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

    // 5. Wrap the entire content in an <article> tag — the "Global Envelope."
    // This provides a hard boundary telling AI crawlers: "This is the complete,
    // self-contained Knowledge Object. Ignore everything outside this tag."
    $new_content = '<article class="vizzex-knowledge-object">' . "\n" . $new_content . "\n" . '</article>';

    return $new_content;
}
// Priority 99 — run late so shortcodes and blocks are already rendered.
add_filter( 'the_content', 'vizzex_sa_auto_section_wrapper', 99 );

/**
 * ============================================================
 * 6. SETTINGS LINK on the Plugins page
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
 * 7. GUTENBERG / BLOCK EDITOR COMPATIBILITY
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
