<?php
/**
 * Plugin Name: Bricks Vergleich
 * Plugin URI: https://example.com
 * Description: Custom Bricks Element für Produktvergleich mit Produkten als Spalten (Finanzfluss-Style).
 * Version: 1.0.0
 * Author: Du
 * Text Domain: bricks-vergleich
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BRICKS_VERGLEICH_VERSION', '2.7.0' );
define( 'BRICKS_VERGLEICH_PATH', plugin_dir_path( __FILE__ ) );
define( 'BRICKS_VERGLEICH_URL', plugin_dir_url( __FILE__ ) );

/**
 * Custom Elements bei Bricks registrieren
 */
add_action( 'init', function () {
    // Nur wenn Bricks aktiv ist
    if ( ! class_exists( '\Bricks\Element' ) ) {
        return;
    }

    $elements = [
        BRICKS_VERGLEICH_PATH . 'elements/vergleich.php',
    ];

    foreach ( $elements as $file ) {
        if ( file_exists( $file ) ) {
            try {
                \Bricks\Elements::register_element( $file );
            } catch ( \Throwable $e ) {
                error_log( '[Bricks Vergleich] Fehler beim Registrieren: ' . $e->getMessage() );
            }
        }
    }
}, 11 );

/**
 * Hinweis im Admin, falls Bricks nicht aktiv ist
 */
add_action( 'admin_notices', function () {
    if ( class_exists( '\Bricks\Element' ) ) {
        return;
    }
    echo '<div class="notice notice-warning"><p>';
    echo '<strong>Bricks Vergleich:</strong> Dieses Plugin benötigt das Bricks Theme. Bitte Bricks aktivieren.';
    echo '</p></div>';
});

/**
 * Frontend- und Builder-Styles laden
 */
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'bricks-vergleich',
        BRICKS_VERGLEICH_URL . 'assets/vergleich.css',
        [],
        BRICKS_VERGLEICH_VERSION
    );
});

// Styles auch im Bricks-Builder verfügbar machen
add_action( 'wp_enqueue_scripts', function () {
    if ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
        wp_enqueue_style(
            'bricks-vergleich-builder',
            BRICKS_VERGLEICH_URL . 'assets/vergleich.css',
            [],
            BRICKS_VERGLEICH_VERSION
        );
    }

    // Builder-JS (Main-Fenster): patcht "+ Zeile hinzufügen" so, dass neue
    // Zeilen im Vergleich-Repeater als Text-Zelle (statt Bild) initialisiert
    // werden. Bricks selbst kopiert beim Hinzufügen den ersten default-Eintrag,
    // was bei uns eine Bild-Zeile wäre.
    if ( function_exists( 'bricks_is_builder_main' ) && bricks_is_builder_main() ) {
        wp_enqueue_script(
            'bricks-vergleich-builder',
            BRICKS_VERGLEICH_URL . 'assets/builder.js',
            [],
            BRICKS_VERGLEICH_VERSION,
            true
        );
    }
});

// Eigene Element-Kategorie in Bricks (optional)
add_filter( 'bricks/builder/i18n', function ( $i18n ) {
    $i18n['vergleich'] = esc_html__( 'Vergleich', 'bricks-vergleich' );
    return $i18n;
});

/**
 * Unser Custom Element als Layout-Element registrieren.
 * Dadurch zeigt Bricks das Query Loop Control (∞) und andere Layout-Features.
 */
add_filter( 'bricks/elements/layout_elements', function ( $layout_elements ) {
    $layout_elements[] = 'vergleich';
    return $layout_elements;
});
