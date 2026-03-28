<?php
/**
 * Plugin Name:       WP AI Content Agent
 * Plugin URI:        https://github.com/soulsites/wp-ai-content-agent
 * Description:       Generiert hochwertigen SEO-Content mit einer Multi-Agenten-Architektur auf Basis der Claude API von Anthropic.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Soulsites
 * License:           GPL v2 or later
 * Text Domain:       wp-aica
 */

defined( 'ABSPATH' ) || exit;

// Plugin-Konstanten
define( 'AICA_VERSION', '1.0.0' );
define( 'AICA_PLUGIN_FILE', __FILE__ );
define( 'AICA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AICA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AICA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader
spl_autoload_register( function ( string $class ) {
    $prefix   = 'AICA\\';
    $base_dir = AICA_PLUGIN_DIR . 'includes/';

    if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, strlen( $prefix ) );
    $parts          = explode( '\\', $relative_class );
    $filename       = 'class-' . strtolower( str_replace( '_', '-', end( $parts ) ) ) . '.php';
    array_pop( $parts );

    $subdir = '';
    if ( ! empty( $parts ) ) {
        $subdir = strtolower( implode( DIRECTORY_SEPARATOR, $parts ) ) . DIRECTORY_SEPARATOR;
    }

    $file = $base_dir . $subdir . $filename;

    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

/**
 * Plugin-Aktivierung
 */
function aica_activate(): void {
    require_once AICA_PLUGIN_DIR . 'includes/class-installer.php';
    AICA\Installer::install();
}
register_activation_hook( __FILE__, 'aica_activate' );

/**
 * Plugin-Deaktivierung
 */
function aica_deactivate(): void {
    require_once AICA_PLUGIN_DIR . 'includes/class-installer.php';
    AICA\Installer::deactivate();
}
register_deactivation_hook( __FILE__, 'aica_deactivate' );

/**
 * Plugin initialisieren
 */
function aica_init(): void {
    require_once AICA_PLUGIN_DIR . 'includes/class-plugin.php';
    AICA\Plugin::get_instance()->init();
}
add_action( 'plugins_loaded', 'aica_init' );
