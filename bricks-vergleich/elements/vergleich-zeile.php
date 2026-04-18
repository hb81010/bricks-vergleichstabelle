<?php
/**
 * Bricks Element: Vergleich-Zeile
 *
 * Eine einzelne Merkmal-Zeile. Kann beliebige Bricks-Elemente als Kinder enthalten
 * (Text, Bild, Button, Icon, etc. mit dynamischen Daten).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Element_Vergleich_Zeile extends \Bricks\Element {

    public $category     = 'vergleich';
    public $name         = 'vergleich-zeile';
    public $icon         = 'ti-layout-list-thumb-alt';
    public $css_selector = '';
    public $nestable     = true;

    public function get_label() {
        return esc_html__( 'Vergleich-Zeile', 'bricks-vergleich' );
    }

    /**
     * Default-Kind: ein einfacher Text
     */
    public function get_nestable_children() {
        return [
            [
                'name'     => 'text-basic',
                'settings' => [
                    'text' => esc_html__( 'Wert', 'bricks-vergleich' ),
                    'tag'  => 'p',
                ],
            ],
        ];
    }

    public function set_control_groups() {
        $this->control_groups['content'] = [
            'title' => esc_html__( 'Inhalt & Label', 'bricks-vergleich' ),
            'tab'   => 'content',
        ];

        $this->control_groups['layout'] = [
            'title' => esc_html__( 'Layout', 'bricks-vergleich' ),
            'tab'   => 'content',
        ];

        $this->control_groups['style'] = [
            'title' => esc_html__( 'Style', 'bricks-vergleich' ),
            'tab'   => 'content',
        ];
    }

    public function set_controls() {
        // === INHALT & LABEL ===
        $this->controls['label'] = [
            'tab'            => 'content',
            'group'          => 'content',
            'label'          => esc_html__( 'Label (linke Spalte)', 'bricks-vergleich' ),
            'type'           => 'text',
            'default'        => esc_html__( 'Merkmal', 'bricks-vergleich' ),
            'hasDynamicData' => true,
            'placeholder'    => esc_html__( 'z.B. Preis, Gewicht, Bewertung', 'bricks-vergleich' ),
        ];

        $this->controls['highlight'] = [
            'tab'     => 'content',
            'group'   => 'content',
            'label'   => esc_html__( 'Zeile hervorheben', 'bricks-vergleich' ),
            'type'    => 'checkbox',
            'default' => false,
            'info'    => esc_html__( 'Hebt diese Zeile farblich hervor (z.B. für Testsieger-Merkmale).', 'bricks-vergleich' ),
        ];

        $this->controls['collapsible'] = [
            'tab'     => 'content',
            'group'   => 'content',
            'label'   => esc_html__( 'In Aufklapp-Bereich', 'bricks-vergleich' ),
            'type'    => 'checkbox',
            'default' => false,
            'info'    => esc_html__( 'Versteckt die Zeile, bis der Nutzer unten auf "Alle Kriterien anzeigen" klickt. Funktioniert nur, wenn im Wrapper-Element "Aufklapp-Button aktivieren" an ist.', 'bricks-vergleich' ),
        ];

        $this->controls['info'] = [
            'tab'     => 'content',
            'group'   => 'content',
            'type'    => 'info',
            'content' => esc_html__( 'Ziehe beliebige Bricks-Elemente in diese Zeile (Text, Bild, Button, Icon) – mit dynamischen Daten wie {post_title}, {acf:feldname}, {woo_product_price} usw. Die Anordnung (nebeneinander / untereinander) steuerst du in der Gruppe „Layout".', 'bricks-vergleich' ),
        ];

        // === LAYOUT ===
        $this->controls['flexDirection'] = [
            'tab'     => 'content',
            'group'   => 'layout',
            'label'   => esc_html__( 'Anordnung der Elemente', 'bricks-vergleich' ),
            'type'    => 'select',
            'options' => [
                'row'            => esc_html__( 'Nebeneinander (horizontal)', 'bricks-vergleich' ),
                'column'         => esc_html__( 'Untereinander (vertikal)', 'bricks-vergleich' ),
                'row-reverse'    => esc_html__( 'Nebeneinander umgekehrt', 'bricks-vergleich' ),
                'column-reverse' => esc_html__( 'Untereinander umgekehrt', 'bricks-vergleich' ),
            ],
            'default' => 'row',
            'info'    => esc_html__( 'z.B. "Untereinander" wenn Bild + Name gestapelt werden sollen.', 'bricks-vergleich' ),
            'css'     => [
                [
                    'property' => 'flex-direction',
                    'selector' => '',
                ],
            ],
        ];

        $this->controls['justifyContent'] = [
            'tab'     => 'content',
            'group'   => 'layout',
            'label'   => esc_html__( 'Haupt-Achse ausrichten (justify)', 'bricks-vergleich' ),
            'type'    => 'select',
            'options' => [
                'flex-start'    => esc_html__( 'Start', 'bricks-vergleich' ),
                'center'        => esc_html__( 'Mitte', 'bricks-vergleich' ),
                'flex-end'      => esc_html__( 'Ende', 'bricks-vergleich' ),
                'space-between' => esc_html__( 'Space Between', 'bricks-vergleich' ),
                'space-around'  => esc_html__( 'Space Around', 'bricks-vergleich' ),
                'space-evenly'  => esc_html__( 'Space Evenly', 'bricks-vergleich' ),
            ],
            'default' => 'center',
            'css'     => [
                [
                    'property' => 'justify-content',
                    'selector' => '',
                ],
            ],
        ];

        $this->controls['alignItems'] = [
            'tab'     => 'content',
            'group'   => 'layout',
            'label'   => esc_html__( 'Quer-Achse ausrichten (align)', 'bricks-vergleich' ),
            'type'    => 'select',
            'options' => [
                'flex-start' => esc_html__( 'Start', 'bricks-vergleich' ),
                'center'     => esc_html__( 'Mitte', 'bricks-vergleich' ),
                'flex-end'   => esc_html__( 'Ende', 'bricks-vergleich' ),
                'stretch'    => esc_html__( 'Stretch', 'bricks-vergleich' ),
                'baseline'   => esc_html__( 'Baseline', 'bricks-vergleich' ),
            ],
            'default' => 'center',
            'css'     => [
                [
                    'property' => 'align-items',
                    'selector' => '',
                ],
            ],
        ];

        $this->controls['gap'] = [
            'tab'         => 'content',
            'group'       => 'layout',
            'label'       => esc_html__( 'Abstand zwischen Elementen', 'bricks-vergleich' ),
            'type'        => 'number',
            'units'       => true,
            'default'     => 8,
            'placeholder' => '8px',
            'css'         => [
                [
                    'property' => 'gap',
                    'selector' => '',
                ],
            ],
        ];

        $this->controls['textAlign'] = [
            'tab'     => 'content',
            'group'   => 'layout',
            'label'   => esc_html__( 'Text-Ausrichtung', 'bricks-vergleich' ),
            'type'    => 'select',
            'options' => [
                'left'   => esc_html__( 'Links', 'bricks-vergleich' ),
                'center' => esc_html__( 'Zentriert', 'bricks-vergleich' ),
                'right'  => esc_html__( 'Rechts', 'bricks-vergleich' ),
            ],
            'default' => 'center',
            'css'     => [
                [
                    'property' => 'text-align',
                    'selector' => '',
                ],
            ],
        ];

        $this->controls['padding'] = [
            'tab'     => 'content',
            'group'   => 'layout',
            'label'   => esc_html__( 'Innenabstand (Padding)', 'bricks-vergleich' ),
            'type'    => 'spacing',
            'css'     => [
                [
                    'property' => 'padding',
                    'selector' => '',
                ],
            ],
        ];

        // === STYLE ===
        $this->controls['bgColor'] = [
            'tab'   => 'content',
            'group' => 'style',
            'label' => esc_html__( 'Hintergrundfarbe', 'bricks-vergleich' ),
            'type'  => 'color',
            'css'   => [
                [
                    'property' => 'background-color',
                    'selector' => '',
                ],
            ],
        ];

        $this->controls['textColor'] = [
            'tab'   => 'content',
            'group' => 'style',
            'label' => esc_html__( 'Textfarbe', 'bricks-vergleich' ),
            'type'  => 'color',
            'css'   => [
                [
                    'property' => 'color',
                    'selector' => '',
                ],
            ],
        ];
    }

    public function render() {
        $settings    = $this->settings;
        $highlight   = ! empty( $settings['highlight'] );
        $collapsible = ! empty( $settings['collapsible'] );

        $classes = [ 'vergleich-zelle' ];
        if ( $highlight ) {
            $classes[] = 'is-highlighted';
        }
        if ( $collapsible ) {
            $classes[] = 'is-collapsible';
        }

        $this->set_attribute( '_root', 'class', $classes );

        // Zeilen-ID auf dem Root ausgeben, damit der Wrapper die zugehörigen
        // Label-Zellen beim Render eindeutig dem Aufklapp-Set zuordnen kann.
        if ( $collapsible && ! empty( $this->id ) ) {
            $this->set_attribute( '_root', 'data-vergleich-row-id', $this->id );
        }

        // Layout-Settings als Inline-Style ausgeben — umgeht den Bricks-CSS-Cache und
        // stellt sicher, dass die Controls auch im Frontend sofort wirken
        // (ohne Regenerate-CSS-Cache-Schritt). Werte werden NUR gesetzt wenn der User
        // explizit was konfiguriert hat, damit mein Default-CSS weiterhin greift.
        $inline_css = [];

        if ( ! empty( $settings['flexDirection'] ) ) {
            $inline_css[] = 'flex-direction: ' . $this->sanitize_css_value( $settings['flexDirection'] );
        }
        if ( ! empty( $settings['justifyContent'] ) ) {
            $inline_css[] = 'justify-content: ' . $this->sanitize_css_value( $settings['justifyContent'] );
        }
        if ( ! empty( $settings['alignItems'] ) ) {
            $inline_css[] = 'align-items: ' . $this->sanitize_css_value( $settings['alignItems'] );
        }
        if ( isset( $settings['gap'] ) && $settings['gap'] !== '' ) {
            $gap = $this->format_length( $settings['gap'] );
            if ( $gap !== '' ) {
                $inline_css[] = 'gap: ' . $gap;
            }
        }
        if ( ! empty( $settings['textAlign'] ) ) {
            $inline_css[] = 'text-align: ' . $this->sanitize_css_value( $settings['textAlign'] );
        }
        if ( ! empty( $settings['bgColor'] ) ) {
            $bg = $this->resolve_color( $settings['bgColor'] );
            if ( $bg ) $inline_css[] = 'background-color: ' . $bg;
        }
        if ( ! empty( $settings['textColor'] ) ) {
            $fg = $this->resolve_color( $settings['textColor'] );
            if ( $fg ) $inline_css[] = 'color: ' . $fg;
        }

        // Padding (Spacing-Control liefert Array: top/right/bottom/left)
        if ( isset( $settings['padding'] ) && is_array( $settings['padding'] ) ) {
            $sides = [];
            foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
                $sides[] = isset( $settings['padding'][ $side ] ) && $settings['padding'][ $side ] !== ''
                    ? $this->format_length( $settings['padding'][ $side ] )
                    : '';
            }
            // Nur setzen wenn mindestens eine Seite einen Wert hat
            if ( array_filter( $sides ) ) {
                $sides = array_map( function( $v ){ return $v === '' ? '0' : $v; }, $sides );
                $inline_css[] = 'padding: ' . implode( ' ', $sides );
            }
        }

        if ( ! empty( $inline_css ) ) {
            $this->set_attribute( '_root', 'style', implode( '; ', $inline_css ) );
        }

        echo "<div {$this->render_attributes( '_root' )}>";
        echo \Bricks\Frontend::render_children( $this );
        echo '</div>';
    }

    /**
     * Sichere Werte für CSS (nur Buchstaben, Bindestriche, Zahlen, Leerzeichen)
     */
    private function sanitize_css_value( $value ) {
        if ( ! is_string( $value ) ) {
            return '';
        }
        // Erlaube Buchstaben, Ziffern, -, _, Leerzeichen, Punkt, %, Komma
        return preg_replace( '/[^a-zA-Z0-9\-_\s.%,]/', '', $value );
    }

    /**
     * Bricks speichert number+unit teils als Array (z.B. ['number'=>16,'unit'=>'px']),
     * teils als Zahl, teils als String "16px".
     */
    private function format_length( $value ) {
        if ( is_array( $value ) ) {
            $number = isset( $value['number'] ) ? $value['number'] : ( $value[0] ?? '' );
            $unit   = isset( $value['unit'] )   ? $value['unit']   : ( $value[1] ?? 'px' );
            if ( $number === '' || $number === null ) return '';
            return $this->sanitize_css_value( (string) $number . $unit );
        }
        if ( is_numeric( $value ) ) {
            return $value . 'px';
        }
        if ( is_string( $value ) ) {
            if ( preg_match( '/^[0-9.]+$/', $value ) ) {
                return $value . 'px';
            }
            return $this->sanitize_css_value( $value );
        }
        return '';
    }

    /**
     * Bricks-Color-Control liefert Array (hex, rgb, hsl, oder theme-variable).
     */
    private function resolve_color( $color ) {
        if ( is_string( $color ) ) {
            return $this->sanitize_css_value( $color );
        }
        if ( is_array( $color ) ) {
            if ( ! empty( $color['rgb'] ) )  return $this->sanitize_css_value( $color['rgb'] );
            if ( ! empty( $color['hsl'] ) )  return $this->sanitize_css_value( $color['hsl'] );
            if ( ! empty( $color['hex'] ) )  return $this->sanitize_css_value( $color['hex'] );
            if ( ! empty( $color['raw'] ) )  return $this->sanitize_css_value( $color['raw'] );
        }
        return '';
    }
}
