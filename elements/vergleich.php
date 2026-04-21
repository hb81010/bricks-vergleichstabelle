<?php
/**
 * Bricks Element: Vergleich (v2 - Single Element, Repeater-based Rows)
 *
 * Zeigt Produkte als Spalten statt als Zeilen.
 * Zeilen-Schema wird über einen Repeater konfiguriert (kein Nestable mehr).
 * Query Loop auf diesem Element loopt die Produkte (= Spalten).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Element_Vergleich extends \Bricks\Element {

    public $category     = 'vergleich';
    public $name         = 'vergleich';
    public $icon         = 'ti-layout-column3-alt';
    public $css_selector = '.vergleich-wrapper';
    public $scripts      = [];
    public $nestable     = false;

    /** Runtime-State für Ranking-Badge (von render() befüllt, von render_card() gelesen). */
    public $_ranking_runtime = null;

    /** Runtime-State für Score-Badge (Bewertung aus Meta-Feld). */
    public $_score_runtime = null;

    /** Runtime-State für Produkt-Labels (manueller Top-Balken pro Spalte). */
    public $_product_label_runtime = null;

    /** Index der ersten aufklappbaren Zeile (fuer Fade-Peek). -1 = keine. */
    public $_first_collapsible_idx = -1;

    public function get_label() {
        return esc_html__( 'Produkt-Vergleichstabelle', 'bricks-vergleich' );
    }

    public function set_control_groups() {
        $this->control_groups['query']   = [ 'title' => esc_html__( 'Query Loop', 'bricks-vergleich' ),        'tab' => 'content' ];
        $this->control_groups['rows']    = [ 'title' => esc_html__( 'Zeilen', 'bricks-vergleich' ),            'tab' => 'content' ];
        $this->control_groups['layout']  = [ 'title' => esc_html__( 'Layout', 'bricks-vergleich' ),            'tab' => 'content' ];
        $this->control_groups['images']  = [ 'title' => esc_html__( 'Bilder', 'bricks-vergleich' ),            'tab' => 'content' ];
        $this->control_groups['expand']  = [ 'title' => esc_html__( 'Aufklappen', 'bricks-vergleich' ),        'tab' => 'content' ];
        $this->control_groups['badges']  = [ 'title' => esc_html__( 'Badges', 'bricks-vergleich' ),            'tab' => 'content' ];
        $this->control_groups['productLabels'] = [ 'title' => esc_html__( 'Produkt-Labels', 'bricks-vergleich' ), 'tab' => 'content' ];
        $this->control_groups['scroll']  = [ 'title' => esc_html__( 'Scroll & Navigation', 'bricks-vergleich' ), 'tab' => 'content' ];
        $this->control_groups['effects'] = [ 'title' => esc_html__( 'Zeilen-Effekte', 'bricks-vergleich' ),    'tab' => 'content' ];
        $this->control_groups['style']   = [ 'title' => esc_html__( 'Farben & Rahmen', 'bricks-vergleich' ),   'tab' => 'content' ];
    }

    public function set_controls() {

        // ======================================================================
        // QUERY
        // ======================================================================
        $this->controls = array_replace_recursive(
            $this->controls,
            $this->get_loop_builder_controls( 'query' )
        );

        $this->controls['queryInfo'] = [
            'tab'     => 'content',
            'group'   => 'query',
            'type'    => 'info',
            'content' => esc_html__( 'Aktiviere den Query-Loop, damit pro Iteration eine neue Produkt-Spalte erzeugt wird. Ohne Loop: eine einzelne Demo-Spalte mit aktuellem Post-Kontext.', 'bricks-vergleich' ),
        ];

        // ======================================================================
        // ZEILEN (Repeater)
        // ======================================================================
        $this->controls['rows'] = [
            'tab'           => 'content',
            'group'         => 'rows',
            'type'          => 'repeater',
            'titleProperty' => 'label',
            'placeholder'   => esc_html__( 'Zeile', 'bricks-vergleich' ),
            'default'       => $this->default_rows(),
            'fields'        => $this->get_row_fields(),
        ];

        $this->controls['rowsInfo'] = [
            'tab'     => 'content',
            'group'   => 'rows',
            'type'    => 'info',
            'content' => esc_html__( 'Jede Zeile hat links ein Label und pro Produkt-Spalte eine Zelle. Der Zellentyp bestimmt den Inhalt (Text, Bild, Icon, Button, Rating, HTML, Dynamisch).', 'bricks-vergleich' ),
        ];

        // ======================================================================
        // LAYOUT
        // ======================================================================
        $this->controls['_sepSizes'] = [
            'tab' => 'content', 'group' => 'layout',
            'type' => 'separator',
            'label' => esc_html__( 'Maße', 'bricks-vergleich' ),
        ];

        $this->controls['labelWidth'] = [
            'tab' => 'content', 'group' => 'layout',
            'label' => esc_html__( 'Breite Label-Spalte', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true, 'placeholder' => '200px',
            'css'   => [ [ 'property' => '--vgl-label-width', 'selector' => '' ] ],
        ];

        $this->controls['columnWidth'] = [
            'tab' => 'content', 'group' => 'layout',
            'label' => esc_html__( 'Breite Produkt-Spalte', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true, 'placeholder' => '200px',
            'css'   => [ [ 'property' => '--vgl-column-width', 'selector' => '' ] ],
        ];

        $this->controls['rowMinHeight'] = [
            'tab' => 'content', 'group' => 'layout',
            'label' => esc_html__( 'Min-Höhe pro Zeile', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true, 'placeholder' => '20px',
            'css'   => [ [ 'property' => '--vgl-row-min', 'selector' => '' ] ],
        ];

        $this->controls['cellPadding'] = [
            'tab' => 'content', 'group' => 'layout',
            'label' => esc_html__( 'Innenabstand Zellen & Labels', 'bricks-vergleich' ),
            'type' => 'spacing',
            'placeholder' => [ 'top' => '8px', 'right' => '12px', 'bottom' => '8px', 'left' => '12px' ],
            'css'   => [
                [ 'property' => 'padding', 'selector' => '.vergleich-zelle' ],
                [ 'property' => 'padding', 'selector' => '.vergleich-label' ],
            ],
        ];

        $this->controls['_sepBehavior'] = [
            'tab' => 'content', 'group' => 'layout',
            'type' => 'separator',
            'label' => esc_html__( 'Verhalten', 'bricks-vergleich' ),
        ];

        $this->controls['stickyLabels'] = [
            'tab' => 'content', 'group' => 'layout',
            'label' => esc_html__( 'Label-Spalte sticky', 'bricks-vergleich' ),
            'type' => 'checkbox', 'default' => true,
        ];

        $this->controls['showDivider'] = [
            'tab' => 'content', 'group' => 'layout',
            'label' => esc_html__( 'Trennlinien zwischen Zeilen', 'bricks-vergleich' ),
            'type' => 'checkbox', 'default' => true,
        ];

        $this->controls['textAlign'] = [
            'tab' => 'content', 'group' => 'layout',
            'label' => esc_html__( 'Textausrichtung Cards (Default)', 'bricks-vergleich' ),
            'type' => 'select',
            'options' => [
                'left' => esc_html__( 'Links', 'bricks-vergleich' ),
                'center' => esc_html__( 'Zentriert', 'bricks-vergleich' ),
                'right' => esc_html__( 'Rechts', 'bricks-vergleich' ),
            ],
            'placeholder' => esc_html__( 'Zentriert', 'bricks-vergleich' ),
        ];

        // ======================================================================
        // IMAGES
        // ======================================================================
        $this->controls['imageEnforce'] = [
            'tab' => 'content', 'group' => 'images',
            'label' => esc_html__( 'Einheitliche Bildgröße erzwingen', 'bricks-vergleich' ),
            'type' => 'checkbox', 'default' => true,
        ];

        $this->controls['imageWidth'] = [
            'tab' => 'content', 'group' => 'images',
            'label' => esc_html__( 'Bildbreite', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true, 'placeholder' => '100px',
            'required' => [ 'imageEnforce', '=', true ],
            'css'   => [ [ 'property' => '--vgl-img-width', 'selector' => '' ] ],
        ];

        $this->controls['imageHeight'] = [
            'tab' => 'content', 'group' => 'images',
            'label' => esc_html__( 'Bildhöhe', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true, 'placeholder' => '100px',
            'required' => [ 'imageEnforce', '=', true ],
            'css'   => [ [ 'property' => '--vgl-img-height', 'selector' => '' ] ],
        ];

        $this->controls['imageObjectFit'] = [
            'tab' => 'content', 'group' => 'images',
            'label' => esc_html__( 'Bildanpassung (object-fit)', 'bricks-vergleich' ),
            'type' => 'select',
            'options' => [
                'cover' => 'cover', 'contain' => 'contain', 'fill' => 'fill',
                'scale-down' => 'scale-down', 'none' => 'none',
            ],
            'default' => 'cover',
            'required' => [ 'imageEnforce', '=', true ],
            'css'   => [ [ 'property' => '--vgl-img-fit', 'selector' => '' ] ],
        ];

        $this->controls['imageBorder'] = [
            'tab' => 'content', 'group' => 'images',
            'label' => esc_html__( 'Rahmen', 'bricks-vergleich' ),
            'type' => 'border',
            'required' => [ 'imageEnforce', '=', true ],
            'css'   => [ [ 'property' => 'border', 'selector' => '.vergleich-zelle--image .vergleich-image, .vergleich-zelle--image .vergleich-image-placeholder' ] ],
        ];

        // ======================================================================
        // EXPAND / COLLAPSE
        // ======================================================================
        $this->controls['expandEnabled'] = [
            'tab' => 'content', 'group' => 'expand',
            'label' => esc_html__( 'Aufklapp-Button aktivieren', 'bricks-vergleich' ),
            'type' => 'checkbox', 'default' => false,
            'description' => esc_html__( 'Zeilen mit aktivierter Option "Aufklappbar" werden bis zum Klick versteckt.', 'bricks-vergleich' ),
        ];

        $this->controls['expandLabel'] = [
            'tab' => 'content', 'group' => 'expand',
            'label' => esc_html__( 'Button-Text (eingeklappt)', 'bricks-vergleich' ),
            'type' => 'text', 'default' => esc_html__( 'Alle Kriterien anzeigen', 'bricks-vergleich' ),
            'hasDynamicData' => 'text',
            'required' => [ 'expandEnabled', '=', true ],
        ];

        $this->controls['collapseLabel'] = [
            'tab' => 'content', 'group' => 'expand',
            'label' => esc_html__( 'Button-Text (ausgeklappt)', 'bricks-vergleich' ),
            'type' => 'text', 'default' => esc_html__( 'Weniger anzeigen', 'bricks-vergleich' ),
            'hasDynamicData' => 'text',
            'required' => [ 'expandEnabled', '=', true ],
        ];

        $this->controls['expandBtnSize'] = [
            'tab' => 'content', 'group' => 'expand',
            'label' => esc_html__( 'Button-Größe', 'bricks-vergleich' ),
            'type' => 'select',
            'options' => [
                ''   => esc_html__( 'Standard', 'bricks-vergleich' ),
                'sm' => esc_html__( 'Klein', 'bricks-vergleich' ),
                'md' => esc_html__( 'Mittel', 'bricks-vergleich' ),
                'lg' => esc_html__( 'Groß', 'bricks-vergleich' ),
                'xl' => esc_html__( 'Extra groß', 'bricks-vergleich' ),
            ],
            'placeholder' => esc_html__( 'Standard', 'bricks-vergleich' ),
            'required' => [ 'expandEnabled', '=', true ],
        ];

        $this->controls['expandButtonStyle'] = [
            'tab' => 'content', 'group' => 'expand',
            'label' => esc_html__( 'Button-Stil', 'bricks-vergleich' ),
            'type' => 'select',
            'options' => [
                'primary'   => esc_html__( 'Primär', 'bricks-vergleich' ),
                'secondary' => esc_html__( 'Sekundär', 'bricks-vergleich' ),
                'light'     => esc_html__( 'Hell', 'bricks-vergleich' ),
                'dark'      => esc_html__( 'Dunkel', 'bricks-vergleich' ),
                'muted'     => esc_html__( 'Gedämpft', 'bricks-vergleich' ),
                'info'      => esc_html__( 'Info', 'bricks-vergleich' ),
                'success'   => esc_html__( 'Erfolg', 'bricks-vergleich' ),
                'warning'   => esc_html__( 'Warnung', 'bricks-vergleich' ),
                'danger'    => esc_html__( 'Gefahr', 'bricks-vergleich' ),
            ],
            'default' => 'primary',
            'required' => [ 'expandEnabled', '=', true ],
        ];

        $this->controls['expandBtnCircle'] = [
            'tab' => 'content', 'group' => 'expand',
            'label' => esc_html__( 'Kreis (Pill-Form)', 'bricks-vergleich' ),
            'type' => 'checkbox',
            'required' => [ 'expandEnabled', '=', true ],
        ];

        $this->controls['expandBtnOutline'] = [
            'tab' => 'content', 'group' => 'expand',
            'label' => esc_html__( 'Umriss', 'bricks-vergleich' ),
            'type' => 'checkbox',
            'required' => [ 'expandEnabled', '=', true ],
        ];

        // ─── Eigenes Styling (überschreibt das Preset oben) ───
        $this->controls['_sepExpandBtnCustom'] = [
            'tab' => 'content', 'group' => 'expand',
            'type' => 'separator',
            'label' => esc_html__( 'Eigenes Styling (überschreibt Preset)', 'bricks-vergleich' ),
            'required' => [ 'expandEnabled', '=', true ],
        ];

        $this->controls['expandBtnBgColor'] = [
            'tab' => 'content', 'group' => 'expand',
            'label' => esc_html__( 'Hintergrundfarbe', 'bricks-vergleich' ),
            'type' => 'color',
            'required' => [ 'expandEnabled', '=', true ],
            'css' => [ [ 'property' => 'background-color', 'selector' => '.vergleich-expand-btn' ] ],
        ];

        $this->controls['expandBtnTypography'] = [
            'tab' => 'content', 'group' => 'expand',
            'label' => esc_html__( 'Typografie', 'bricks-vergleich' ),
            'type' => 'typography',
            'required' => [ 'expandEnabled', '=', true ],
            'css' => [ [ 'property' => 'typography', 'selector' => '.vergleich-expand-btn' ] ],
        ];

        $this->controls['expandBtnBorder'] = [
            'tab' => 'content', 'group' => 'expand',
            'label' => esc_html__( 'Rahmen', 'bricks-vergleich' ),
            'type' => 'border',
            'required' => [ 'expandEnabled', '=', true ],
            'css' => [ [ 'property' => 'border', 'selector' => '.vergleich-expand-btn' ] ],
        ];

        $this->controls['expandBtnPadding'] = [
            'tab' => 'content', 'group' => 'expand',
            'label' => esc_html__( 'Innenabstand', 'bricks-vergleich' ),
            'type' => 'spacing',
            'required' => [ 'expandEnabled', '=', true ],
            'css' => [ [ 'property' => 'padding', 'selector' => '.vergleich-expand-btn' ] ],
        ];

        $this->controls['_sepExpandLayout'] = [
            'tab' => 'content', 'group' => 'expand',
            'type' => 'separator',
            'label' => esc_html__( 'Layout', 'bricks-vergleich' ),
            'required' => [ 'expandEnabled', '=', true ],
        ];

        $this->controls['expandAlign'] = [
            'tab' => 'content', 'group' => 'expand',
            'label' => esc_html__( 'Button-Ausrichtung', 'bricks-vergleich' ),
            'type' => 'select',
            'options' => [
                'left' => esc_html__( 'Links', 'bricks-vergleich' ),
                'center' => esc_html__( 'Zentriert', 'bricks-vergleich' ),
                'right' => esc_html__( 'Rechts', 'bricks-vergleich' ),
            ],
            'default' => 'center',
            'required' => [ 'expandEnabled', '=', true ],
        ];

        $this->controls['expandShowIcon'] = [
            'tab' => 'content', 'group' => 'expand',
            'label' => esc_html__( 'Pfeil-Icon zeigen', 'bricks-vergleich' ),
            'type' => 'checkbox', 'default' => true,
            'required' => [ 'expandEnabled', '=', true ],
        ];

        // ─── Fade-Effekt (optischer Teaser der nächsten Zeile) ───
        $this->controls['_sepExpandFade'] = [
            'tab' => 'content', 'group' => 'expand',
            'type' => 'separator',
            'label' => esc_html__( 'Fade-Effekt (optional)', 'bricks-vergleich' ),
            'required' => [ 'expandEnabled', '=', true ],
        ];

        $this->controls['expandFadeEnabled'] = [
            'tab'         => 'content', 'group' => 'expand',
            'label'       => esc_html__( 'Fade-Effekt aktivieren', 'bricks-vergleich' ),
            'type'        => 'checkbox',
            'description' => esc_html__( 'Statt harter Abschneidung: die erste verborgene Zeile wird leicht angedeutet, ein Farbverlauf blendet sie nach unten aus und der "Aufklappen"-Button überlagert den Fade-Bereich.', 'bricks-vergleich' ),
            'required'    => [ 'expandEnabled', '=', true ],
        ];

        $this->controls['expandFadePeek'] = [
            'tab'         => 'content', 'group' => 'expand',
            'label'       => esc_html__( 'Sichtbare Peek-Höhe', 'bricks-vergleich' ),
            'type'        => 'number', 'units' => true, 'placeholder' => '48px',
            'description' => esc_html__( 'Wieviel von der ersten verborgenen Zeile sichtbar sein soll (bevor der Fade einsetzt).', 'bricks-vergleich' ),
            'required'    => [ [ 'expandEnabled', '=', true ], [ 'expandFadeEnabled', '=', true ] ],
            'css'         => [ [ 'property' => '--vgl-fade-peek', 'selector' => '' ] ],
        ];

        $this->controls['expandFadeHeight'] = [
            'tab'         => 'content', 'group' => 'expand',
            'label'       => esc_html__( 'Fade-Höhe (Gradient)', 'bricks-vergleich' ),
            'type'        => 'number', 'units' => true, 'placeholder' => '140px',
            'description' => esc_html__( 'Höhe des Farbverlaufs am unteren Rand.', 'bricks-vergleich' ),
            'required'    => [ [ 'expandEnabled', '=', true ], [ 'expandFadeEnabled', '=', true ] ],
            'css'         => [ [ 'property' => '--vgl-fade-height', 'selector' => '' ] ],
        ];

        $this->controls['expandFadeColor'] = [
            'tab'         => 'content', 'group' => 'expand',
            'label'       => esc_html__( 'Fade-Farbe', 'bricks-vergleich' ),
            'type'        => 'color',
            'description' => esc_html__( 'Endfarbe des Gradients. Sollte der Tabellenhintergrund sein (standardmäßig Weiß).', 'bricks-vergleich' ),
            'required'    => [ [ 'expandEnabled', '=', true ], [ 'expandFadeEnabled', '=', true ] ],
            'css'         => [ [ 'property' => '--vgl-fade-color', 'selector' => '' ] ],
        ];

        $this->controls['expandFadeButtonOverlap'] = [
            'tab'         => 'content', 'group' => 'expand',
            'label'       => esc_html__( 'Button-Überlappung', 'bricks-vergleich' ),
            'type'        => 'number', 'units' => true, 'placeholder' => '40px',
            'description' => esc_html__( 'Wie weit der Aufklappen-Button in den Fade-Bereich hinein ragen soll.', 'bricks-vergleich' ),
            'required'    => [ [ 'expandEnabled', '=', true ], [ 'expandFadeEnabled', '=', true ] ],
            'css'         => [ [ 'property' => '--vgl-fade-btn-overlap', 'selector' => '' ] ],
        ];

        // ======================================================================
        // BADGES — Ranking + Bewertung zusammengefasst.
        // ======================================================================
        $this->controls['_sepRanking'] = [
            'tab' => 'content', 'group' => 'badges',
            'type' => 'separator',
            'label' => esc_html__( 'Ranking-Badge', 'bricks-vergleich' ),
        ];

        $this->controls['rankingEnabled'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Ranking-Badge anzeigen', 'bricks-vergleich' ),
            'type' => 'checkbox',
            'description' => esc_html__( 'Platzierungs-Plakette (1, 2, 3, …) auf jeder Produkt-Spalte.', 'bricks-vergleich' ),
        ];

        $this->controls['rankingStart'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Start-Nummer', 'bricks-vergleich' ),
            'type' => 'number', 'placeholder' => '1', 'min' => 0,
            'required' => [ 'rankingEnabled', '=', true ],
        ];

        $this->controls['rankingReverse'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Reihenfolge umkehren', 'bricks-vergleich' ),
            'type' => 'checkbox',
            'description' => esc_html__( 'Letzte Spalte bekommt Platz 1.', 'bricks-vergleich' ),
            'required' => [ 'rankingEnabled', '=', true ],
        ];

        $this->controls['rankingPrefix'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Präfix', 'bricks-vergleich' ),
            'type' => 'text', 'placeholder' => '#',
            'hasDynamicData' => 'text',
            'required' => [ 'rankingEnabled', '=', true ],
        ];

        $this->controls['rankingSuffix'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Suffix', 'bricks-vergleich' ),
            'type' => 'text',
            'hasDynamicData' => 'text',
            'required' => [ 'rankingEnabled', '=', true ],
        ];

        $this->controls['rankingPosition'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Position', 'bricks-vergleich' ),
            'type' => 'select',
            'options' => [
                'top-left' => esc_html__( 'Oben links', 'bricks-vergleich' ),
                'top-center' => esc_html__( 'Oben Mitte', 'bricks-vergleich' ),
                'top-right' => esc_html__( 'Oben rechts', 'bricks-vergleich' ),
                'bottom-left' => esc_html__( 'Unten links', 'bricks-vergleich' ),
                'bottom-right' => esc_html__( 'Unten rechts', 'bricks-vergleich' ),
            ],
            'placeholder' => esc_html__( 'Oben links', 'bricks-vergleich' ),
            'required' => [ 'rankingEnabled', '=', true ],
        ];

        $this->controls['rankingOffsetY'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Abstand oben/unten', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true, 'placeholder' => '8px',
            'required' => [ 'rankingEnabled', '=', true ],
            'css'   => [ [ 'property' => '--vgl-rank-offset-y', 'selector' => '' ] ],
        ];

        $this->controls['rankingOffsetX'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Abstand links/rechts', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true, 'placeholder' => '8px',
            'required' => [ 'rankingEnabled', '=', true ],
            'css'   => [ [ 'property' => '--vgl-rank-offset-x', 'selector' => '' ] ],
        ];

        $this->controls['rankingSize'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Mindestgröße', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true, 'placeholder' => '36px',
            'required' => [ 'rankingEnabled', '=', true ],
            'css'   => [ [ 'property' => '--vgl-rank-size', 'selector' => '' ] ],
        ];

        $this->controls['rankingPadding'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Innenabstand', 'bricks-vergleich' ),
            'type' => 'spacing',
            'placeholder' => [ 'top' => '4px', 'right' => '10px', 'bottom' => '4px', 'left' => '10px' ],
            'required' => [ 'rankingEnabled', '=', true ],
            'css'   => [ [ 'property' => 'padding', 'selector' => '.vergleich-rank' ] ],
        ];

        $this->controls['rankingBgColor'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Hintergrundfarbe', 'bricks-vergleich' ),
            'type' => 'color',
            'required' => [ 'rankingEnabled', '=', true ],
            'css'   => [ [ 'property' => '--vgl-rank-bg', 'selector' => '' ] ],
        ];

        $this->controls['rankingTypography'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Typografie', 'bricks-vergleich' ),
            'type' => 'typography',
            'required' => [ 'rankingEnabled', '=', true ],
            'css'   => [ [ 'property' => 'typography', 'selector' => '.vergleich-rank' ] ],
        ];

        $this->controls['rankingBorder'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Rahmen', 'bricks-vergleich' ),
            'type' => 'border',
            'required' => [ 'rankingEnabled', '=', true ],
            'css'   => [ [ 'property' => 'border', 'selector' => '.vergleich-rank' ] ],
        ];

        $this->controls['rankingShadow'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Schatten', 'bricks-vergleich' ),
            'type' => 'box-shadow',
            'required' => [ 'rankingEnabled', '=', true ],
            'css' => [ [ 'property' => 'box-shadow', 'selector' => '.vergleich-rank' ] ],
        ];

        $this->controls['rankingHighlightTop'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Top-1 besonders hervorheben', 'bricks-vergleich' ),
            'type' => 'checkbox',
            'required' => [ 'rankingEnabled', '=', true ],
        ];

        // ======================================================================
        // SCORE / BEWERTUNGS-BADGE (gleiche Gruppe wie Ranking).
        // ======================================================================
        $this->controls['_sepScore'] = [
            'tab' => 'content', 'group' => 'badges',
            'type' => 'separator',
            'label' => esc_html__( 'Bewertungs-Badge', 'bricks-vergleich' ),
        ];

        $this->controls['scoreEnabled'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Bewertungs-Badge anzeigen', 'bricks-vergleich' ),
            'type' => 'checkbox',
            'description' => esc_html__( 'Zeigt einen Meta-Feld-Wert (z. B. Bewertungsnote) als Badge auf jeder Spalte.', 'bricks-vergleich' ),
        ];

        $this->controls['scoreMetaKey'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Meta-Key oder Dynamic Data', 'bricks-vergleich' ),
            'type' => 'text',
            'placeholder' => 'bewertung',
            'hasDynamicData' => 'text',
            'description' => esc_html__( 'Entweder reiner Meta-Key (z. B. bewertung) oder ein Dynamic-Data-Tag wie {acf:bewertung} / {je_product_bewertung}.', 'bricks-vergleich' ),
            'required' => [ 'scoreEnabled', '=', true ],
        ];

        $this->controls['scoreDecimals'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Dezimalstellen', 'bricks-vergleich' ),
            'type' => 'number', 'placeholder' => '1', 'min' => 0, 'max' => 4,
            'required' => [ 'scoreEnabled', '=', true ],
        ];

        $this->controls['scoreDecimalSeparator'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Dezimal-Trennzeichen', 'bricks-vergleich' ),
            'type' => 'select',
            'options' => [
                ','  => esc_html__( 'Komma (1,5)', 'bricks-vergleich' ),
                '.'  => esc_html__( 'Punkt (1.5)', 'bricks-vergleich' ),
            ],
            'placeholder' => esc_html__( 'Komma (1,5)', 'bricks-vergleich' ),
            'required' => [ 'scoreEnabled', '=', true ],
        ];

        $this->controls['scorePrefix'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Präfix', 'bricks-vergleich' ),
            'type' => 'text',
            'hasDynamicData' => 'text',
            'placeholder' => esc_html__( 'z.B. Note', 'bricks-vergleich' ),
            'required' => [ 'scoreEnabled', '=', true ],
        ];

        $this->controls['scoreSuffix'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Suffix', 'bricks-vergleich' ),
            'type' => 'text',
            'hasDynamicData' => 'text',
            'placeholder' => esc_html__( 'z.B. /5', 'bricks-vergleich' ),
            'required' => [ 'scoreEnabled', '=', true ],
        ];

        $this->controls['scorePosition'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Position', 'bricks-vergleich' ),
            'type' => 'select',
            'options' => [
                'top-left'      => esc_html__( 'Oben links', 'bricks-vergleich' ),
                'top-center'    => esc_html__( 'Oben Mitte', 'bricks-vergleich' ),
                'top-right'     => esc_html__( 'Oben rechts', 'bricks-vergleich' ),
                'bottom-left'   => esc_html__( 'Unten links', 'bricks-vergleich' ),
                'bottom-center' => esc_html__( 'Unten Mitte', 'bricks-vergleich' ),
                'bottom-right'  => esc_html__( 'Unten rechts', 'bricks-vergleich' ),
            ],
            'placeholder' => esc_html__( 'Unten links', 'bricks-vergleich' ),
            'required' => [ 'scoreEnabled', '=', true ],
        ];

        $this->controls['scoreOffsetY'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Abstand oben/unten', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true, 'placeholder' => '8px',
            'required' => [ 'scoreEnabled', '=', true ],
            'css'   => [ [ 'property' => '--vgl-score-offset-y', 'selector' => '' ] ],
        ];

        $this->controls['scoreOffsetX'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Abstand links/rechts', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true, 'placeholder' => '8px',
            'required' => [ 'scoreEnabled', '=', true ],
            'css'   => [ [ 'property' => '--vgl-score-offset-x', 'selector' => '' ] ],
        ];

        $this->controls['scoreMinSize'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Mindestgröße', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true, 'placeholder' => '36px',
            'required' => [ 'scoreEnabled', '=', true ],
            'css'   => [ [ 'property' => '--vgl-score-size', 'selector' => '' ] ],
        ];

        $this->controls['scorePadding'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Innenabstand', 'bricks-vergleich' ),
            'type' => 'spacing',
            'placeholder' => [ 'top' => '6px', 'right' => '10px', 'bottom' => '6px', 'left' => '10px' ],
            'required' => [ 'scoreEnabled', '=', true ],
            'css'   => [ [ 'property' => 'padding', 'selector' => '.vergleich-score' ] ],
        ];

        $this->controls['scoreBgColor'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Hintergrundfarbe', 'bricks-vergleich' ),
            'type' => 'color',
            'required' => [ 'scoreEnabled', '=', true ],
            'css'   => [ [ 'property' => '--vgl-score-bg', 'selector' => '' ] ],
        ];

        $this->controls['scoreTypography'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Typografie', 'bricks-vergleich' ),
            'type' => 'typography',
            'required' => [ 'scoreEnabled', '=', true ],
            'css'   => [ [ 'property' => 'typography', 'selector' => '.vergleich-score' ] ],
        ];

        $this->controls['scoreBorder'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Rahmen', 'bricks-vergleich' ),
            'type' => 'border',
            'required' => [ 'scoreEnabled', '=', true ],
            'css'   => [ [ 'property' => 'border', 'selector' => '.vergleich-score' ] ],
        ];

        $this->controls['scoreShadow'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Schatten', 'bricks-vergleich' ),
            'type' => 'box-shadow',
            'required' => [ 'scoreEnabled', '=', true ],
            'css' => [ [ 'property' => 'box-shadow', 'selector' => '.vergleich-score' ] ],
        ];

        $this->controls['scoreHideEmpty'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Badge verbergen, wenn Wert leer', 'bricks-vergleich' ),
            'type' => 'checkbox',
            'description' => esc_html__( 'Empfohlen: an. Badge wird ausgeblendet, wenn das Feld leer ist.', 'bricks-vergleich' ),
            'required' => [ 'scoreEnabled', '=', true ],
        ];

        // ======================================================================
        // PRODUKT-LABELS — Manueller Balken oberhalb jeder Spalte (z. B. "TESTSIEGER").
        // Pro Produkt individuell konfigurierbar, Position oberhalb der ersten Zeile.
        // ======================================================================
        $this->controls['productLabelsEnabled'] = [
            'tab'   => 'content', 'group' => 'productLabels',
            'label' => esc_html__( 'Produkt-Labels anzeigen', 'bricks-vergleich' ),
            'type'  => 'checkbox',
            'description' => esc_html__( 'Fügt einen manuell konfigurierbaren Balken oberhalb jeder Produkt-Spalte ein (z. B. "TESTSIEGER", "PREIS-TIPP"). Leere Einträge erzeugen einen leeren Platzhalter, damit die Zeilenhöhen synchron bleiben.', 'bricks-vergleich' ),
        ];

        $this->controls['productLabelsInfo'] = [
            'tab'     => 'content', 'group' => 'productLabels',
            'type'    => 'info',
            'content' => esc_html__( 'Reihenfolge der Einträge = Reihenfolge der Produkt-Spalten. Fehlt ein Eintrag für eine Spalte, wird der Fallback-Text verwendet; ist beides leer, bleibt der Platz leer (Höhe bleibt erhalten).', 'bricks-vergleich' ),
            'required' => [ 'productLabelsEnabled', '=', true ],
        ];

        $this->controls['productLabelsItems'] = [
            'tab'           => 'content', 'group' => 'productLabels',
            'label'         => esc_html__( 'Labels pro Spalte', 'bricks-vergleich' ),
            'type'          => 'repeater',
            'titleProperty' => 'text',
            'placeholder'   => esc_html__( 'Label', 'bricks-vergleich' ),
            'fields'        => [
                'text' => [
                    'label' => esc_html__( 'Text', 'bricks-vergleich' ),
                    'type' => 'text',
                    'hasDynamicData' => 'text',
                    'placeholder' => esc_html__( 'z. B. TESTSIEGER', 'bricks-vergleich' ),
                ],
                'bgColor' => [
                    'label' => esc_html__( 'Hintergrundfarbe', 'bricks-vergleich' ),
                    'type' => 'color',
                ],
                'textColor' => [
                    'label' => esc_html__( 'Textfarbe', 'bricks-vergleich' ),
                    'type' => 'color',
                ],
            ],
            'required' => [ 'productLabelsEnabled', '=', true ],
        ];

        $this->controls['productLabelsFallback'] = [
            'tab'   => 'content', 'group' => 'productLabels',
            'label' => esc_html__( 'Fallback-Text', 'bricks-vergleich' ),
            'type'  => 'text',
            'hasDynamicData' => 'text',
            'description' => esc_html__( 'Wird verwendet, wenn für eine Spalte kein Eintrag vorhanden ist. Leer lassen = Platzhalter ohne Text.', 'bricks-vergleich' ),
            'required' => [ 'productLabelsEnabled', '=', true ],
        ];

        $this->controls['productLabelsLeftLabel'] = [
            'tab'   => 'content', 'group' => 'productLabels',
            'label' => esc_html__( 'Text in linker Spalte', 'bricks-vergleich' ),
            'type'  => 'text',
            'hasDynamicData' => 'text',
            'description' => esc_html__( 'Optionaler Text, der links in der Label-Spalte für diese Zeile erscheint. Leer = leerer Platzhalter.', 'bricks-vergleich' ),
            'required' => [ 'productLabelsEnabled', '=', true ],
        ];

        $this->controls['_sepProdLabelsStyle'] = [
            'tab'   => 'content', 'group' => 'productLabels',
            'type'  => 'separator',
            'label' => esc_html__( 'Darstellung', 'bricks-vergleich' ),
            'required' => [ 'productLabelsEnabled', '=', true ],
        ];

        $this->controls['productLabelsHeight'] = [
            'tab'   => 'content', 'group' => 'productLabels',
            'label' => esc_html__( 'Balkenhöhe', 'bricks-vergleich' ),
            'type'  => 'number', 'units' => true, 'placeholder' => '40px',
            'required' => [ 'productLabelsEnabled', '=', true ],
            'css'   => [ [ 'property' => '--vgl-product-label-height', 'selector' => '' ] ],
        ];

        $this->controls['productLabelsGap'] = [
            'tab'   => 'content', 'group' => 'productLabels',
            'label' => esc_html__( 'Abstand zur Tabelle', 'bricks-vergleich' ),
            'type'  => 'number', 'units' => true, 'placeholder' => '6px',
            'required' => [ 'productLabelsEnabled', '=', true ],
            'css'   => [ [ 'property' => '--vgl-product-label-gap', 'selector' => '' ] ],
        ];

        $this->controls['productLabelsPadding'] = [
            'tab'   => 'content', 'group' => 'productLabels',
            'label' => esc_html__( 'Innenabstand', 'bricks-vergleich' ),
            'type'  => 'spacing',
            'placeholder' => [ 'top' => '8px', 'right' => '12px', 'bottom' => '8px', 'left' => '12px' ],
            'required' => [ 'productLabelsEnabled', '=', true ],
            'css'   => [ [ 'property' => 'padding', 'selector' => '.vergleich-product-label-item' ] ],
        ];

        $this->controls['productLabelsTypography'] = [
            'tab'   => 'content', 'group' => 'productLabels',
            'label' => esc_html__( 'Typografie', 'bricks-vergleich' ),
            'type'  => 'typography',
            'required' => [ 'productLabelsEnabled', '=', true ],
            'css'   => [ [ 'property' => 'typography', 'selector' => '.vergleich-product-label-item:not(.is-empty)' ] ],
        ];

        $this->controls['productLabelsDefaultBg'] = [
            'tab'   => 'content', 'group' => 'productLabels',
            'label' => esc_html__( 'Standard-Hintergrund', 'bricks-vergleich' ),
            'type'  => 'color',
            'description' => esc_html__( 'Wird verwendet, wenn im Repeater-Eintrag keine Farbe gesetzt ist.', 'bricks-vergleich' ),
            'required' => [ 'productLabelsEnabled', '=', true ],
            'css'   => [ [ 'property' => '--vgl-product-label-bg', 'selector' => '' ] ],
        ];

        $this->controls['productLabelsBorder'] = [
            'tab'   => 'content', 'group' => 'productLabels',
            'label' => esc_html__( 'Rahmen', 'bricks-vergleich' ),
            'type'  => 'border',
            'description' => esc_html__( 'Gilt für alle nicht-leeren Labels (leere Platzhalter bleiben ohne Rahmen).', 'bricks-vergleich' ),
            'required' => [ 'productLabelsEnabled', '=', true ],
            'css'   => [ [ 'property' => 'border', 'selector' => '.vergleich-product-label-item:not(.is-empty)' ] ],
        ];

        // ======================================================================
        // SCROLL & NAVIGATION — Pfeile und Zähler in einer Gruppe.
        // ======================================================================
        $this->controls['_sepArrows'] = [
            'tab' => 'content', 'group' => 'scroll',
            'type' => 'separator',
            'label' => esc_html__( 'Navigations-Pfeile', 'bricks-vergleich' ),
        ];

        $this->controls['navEnabled'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Navigations-Pfeile anzeigen', 'bricks-vergleich' ),
            'type' => 'checkbox',
            'description' => esc_html__( 'Kreisrunde Pfeile links/rechts am Scroll-Bereich — nur sichtbar, wenn die Spalten tatsächlich überlaufen.', 'bricks-vergleich' ),
        ];

        $this->controls['navSize'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Button-Größe', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true, 'placeholder' => '44px',
            'required' => [ 'navEnabled', '=', true ],
            'css' => [ [ 'property' => '--vgl-nav-size', 'selector' => '' ] ],
        ];

        $this->controls['navIconSize'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Icon-Größe', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true, 'placeholder' => '18px',
            'required' => [ 'navEnabled', '=', true ],
            'css' => [ [ 'property' => '--vgl-nav-icon-size', 'selector' => '' ] ],
        ];

        $this->controls['navOffset'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Abstand zum Rand', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true, 'placeholder' => '12px',
            'required' => [ 'navEnabled', '=', true ],
            'css' => [ [ 'property' => '--vgl-nav-offset', 'selector' => '' ] ],
        ];

        $this->controls['navBgColor'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Hintergrundfarbe', 'bricks-vergleich' ),
            'type' => 'color',
            'required' => [ 'navEnabled', '=', true ],
            'css' => [ [ 'property' => '--vgl-nav-bg', 'selector' => '' ] ],
        ];

        $this->controls['navIconColor'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Icon-Farbe', 'bricks-vergleich' ),
            'type' => 'color',
            'required' => [ 'navEnabled', '=', true ],
            'css' => [ [ 'property' => '--vgl-nav-color', 'selector' => '' ] ],
        ];

        $this->controls['navBorder'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Rahmen', 'bricks-vergleich' ),
            'type' => 'border',
            'required' => [ 'navEnabled', '=', true ],
            'css' => [ [ 'property' => 'border', 'selector' => '.vergleich-nav' ] ],
        ];

        $this->controls['navShadow'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Schatten', 'bricks-vergleich' ),
            'type' => 'box-shadow',
            'required' => [ 'navEnabled', '=', true ],
            'css' => [ [ 'property' => 'box-shadow', 'selector' => '.vergleich-nav' ] ],
        ];

        $this->controls['navScrollStep'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Scroll-Schrittweite', 'bricks-vergleich' ),
            'type' => 'select',
            'options' => [
                'card' => esc_html__( 'Eine Spalte', 'bricks-vergleich' ),
                'view' => esc_html__( 'Eine Bildschirmbreite', 'bricks-vergleich' ),
            ],
            'placeholder' => esc_html__( 'Eine Spalte', 'bricks-vergleich' ),
            'required' => [ 'navEnabled', '=', true ],
        ];

        $this->controls['_sepCounter'] = [
            'tab' => 'content', 'group' => 'scroll',
            'type' => 'separator',
            'label' => esc_html__( 'Positions-Zähler', 'bricks-vergleich' ),
        ];

        $this->controls['navCounterEnabled'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Zähler anzeigen (z.B. „1–4 von 80")', 'bricks-vergleich' ),
            'type' => 'checkbox',
            'description' => esc_html__( 'Kleine Anzeige, die zeigt, welche Spalten gerade sichtbar sind. Besonders hilfreich auf Mobil.', 'bricks-vergleich' ),
        ];

        $this->controls['navCounterPosition'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Zähler-Position', 'bricks-vergleich' ),
            'type' => 'select',
            'options' => [
                'above' => esc_html__( 'Über der Tabelle', 'bricks-vergleich' ),
                'below' => esc_html__( 'Unter der Tabelle', 'bricks-vergleich' ),
            ],
            'placeholder' => esc_html__( 'Über der Tabelle', 'bricks-vergleich' ),
            'required' => [ 'navCounterEnabled', '=', true ],
        ];

        $this->controls['navCounterFormat'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Zähler-Format', 'bricks-vergleich' ),
            'type' => 'text',
            'placeholder' => '{start}–{end} von {total}',
            'description' => esc_html__( 'Platzhalter: {start}, {end}, {total}.', 'bricks-vergleich' ),
            'required' => [ 'navCounterEnabled', '=', true ],
        ];

        $this->controls['navCounterAlign'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Zähler-Ausrichtung', 'bricks-vergleich' ),
            'type' => 'select',
            'options' => [
                'left'   => esc_html__( 'Links', 'bricks-vergleich' ),
                'center' => esc_html__( 'Zentriert', 'bricks-vergleich' ),
                'right'  => esc_html__( 'Rechts', 'bricks-vergleich' ),
            ],
            'placeholder' => esc_html__( 'Rechts', 'bricks-vergleich' ),
            'required' => [ 'navCounterEnabled', '=', true ],
        ];

        $this->controls['navCounterTypography'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Typografie', 'bricks-vergleich' ),
            'type' => 'typography',
            'required' => [ 'navCounterEnabled', '=', true ],
            'css' => [ [ 'property' => 'typography', 'selector' => '.vergleich-counter' ] ],
        ];

        $this->controls['navCounterPadding'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Innenabstand', 'bricks-vergleich' ),
            'type' => 'spacing',
            'placeholder' => [ 'top' => '8px', 'right' => '4px', 'bottom' => '8px', 'left' => '4px' ],
            'required' => [ 'navCounterEnabled', '=', true ],
            'css' => [ [ 'property' => 'padding', 'selector' => '.vergleich-counter' ] ],
        ];

        // ======================================================================
        // STYLE
        // ======================================================================
        $this->controls['labelBgColor'] = [
            'tab' => 'content', 'group' => 'style',
            'label' => esc_html__( 'Hintergrund Label-Spalte', 'bricks-vergleich' ),
            'type' => 'color',
            'css' => [ [ 'property' => 'background-color', 'selector' => '.vergleich-labels' ] ],
        ];

        // ─── ZEILEN-EFFEKTE (Zebra, Hover, Highlight-Schatten) ─────────────
        $this->controls['_sepZebra'] = [
            'tab' => 'content', 'group' => 'effects',
            'type' => 'separator',
            'label' => esc_html__( 'Zebra-Streifen', 'bricks-vergleich' ),
        ];

        $this->controls['rowBgOdd'] = [
            'tab' => 'content', 'group' => 'effects',
            'label' => esc_html__( 'Hintergrund ungerade Zeilen', 'bricks-vergleich' ),
            'type' => 'color',
            'description' => esc_html__( 'Leer = kein Zebra. Gilt für 1., 3., 5. Zeile.', 'bricks-vergleich' ),
            'css' => [ [ 'property' => '--vgl-row-bg-odd', 'selector' => '' ] ],
        ];

        $this->controls['rowBgEven'] = [
            'tab' => 'content', 'group' => 'effects',
            'label' => esc_html__( 'Hintergrund gerade Zeilen', 'bricks-vergleich' ),
            'type' => 'color',
            'description' => esc_html__( 'Leer = kein Zebra. Gilt für 2., 4., 6. Zeile.', 'bricks-vergleich' ),
            'css' => [ [ 'property' => '--vgl-row-bg-even', 'selector' => '' ] ],
        ];

        $this->controls['rowColorOdd'] = [
            'tab' => 'content', 'group' => 'effects',
            'label' => esc_html__( 'Textfarbe ungerade Zeilen', 'bricks-vergleich' ),
            'type' => 'color',
            'css' => [ [ 'property' => '--vgl-row-color-odd', 'selector' => '' ] ],
        ];

        $this->controls['rowColorEven'] = [
            'tab' => 'content', 'group' => 'effects',
            'label' => esc_html__( 'Textfarbe gerade Zeilen', 'bricks-vergleich' ),
            'type' => 'color',
            'css' => [ [ 'property' => '--vgl-row-color-even', 'selector' => '' ] ],
        ];

        $this->controls['_sepHover'] = [
            'tab' => 'content', 'group' => 'effects',
            'type' => 'separator',
            'label' => esc_html__( 'Hover-Effekt', 'bricks-vergleich' ),
        ];

        $this->controls['rowHoverEnabled'] = [
            'tab' => 'content', 'group' => 'effects',
            'label' => esc_html__( 'Zeilen-Hover-Effekt', 'bricks-vergleich' ),
            'type' => 'checkbox',
            'description' => esc_html__( 'Färbt die gesamte Zeile leicht ein, wenn die Maus drüber fährt.', 'bricks-vergleich' ),
        ];

        $this->controls['rowHoverBg'] = [
            'tab' => 'content', 'group' => 'effects',
            'label' => esc_html__( 'Hover-Hintergrundfarbe', 'bricks-vergleich' ),
            'type' => 'color',
            'required' => [ 'rowHoverEnabled', '=', true ],
            'css' => [ [ 'property' => '--vgl-row-hover-bg', 'selector' => '' ] ],
        ];

        $this->controls['_sepHighlight'] = [
            'tab' => 'content', 'group' => 'effects',
            'type' => 'separator',
            'label' => esc_html__( 'Zeilen-Hervorhebung', 'bricks-vergleich' ),
        ];

        $this->controls['highlightShadow'] = [
            'tab' => 'content', 'group' => 'effects',
            'label' => esc_html__( 'Schatten', 'bricks-vergleich' ),
            'type' => 'box-shadow',
            'description' => esc_html__( 'Wird verwendet, wenn eine Zeile mit Stil „Schatten" hervorgehoben wird. Ohne Wert bleibt der Default (zweischichtiger weicher Glow).', 'bricks-vergleich' ),
            'css' => [
                [ 'property' => 'box-shadow', 'selector' => '.vergleich-label.is-highlight-shadow' ],
                [ 'property' => 'box-shadow', 'selector' => '.vergleich-zelle.is-highlight-shadow' ],
            ],
        ];

        $this->controls['labelColor'] = [
            'tab' => 'content', 'group' => 'style',
            'label' => esc_html__( 'Textfarbe Labels', 'bricks-vergleich' ),
            'type' => 'color',
            'css' => [ [ 'property' => 'color', 'selector' => '.vergleich-label' ] ],
        ];

        $this->controls['cardBgColor'] = [
            'tab' => 'content', 'group' => 'style',
            'label' => esc_html__( 'Hintergrund Produkt-Spalten', 'bricks-vergleich' ),
            'type' => 'color',
            'css' => [ [ 'property' => 'background-color', 'selector' => '.vergleich-card' ] ],
        ];

        $this->controls['border'] = [
            'tab' => 'content', 'group' => 'style',
            'label' => esc_html__( 'Rahmen', 'bricks-vergleich' ),
            'type' => 'border',
            'css' => [
                [ 'property' => 'border', 'selector' => '' ],
                [ 'property' => 'border-color', 'selector' => '.vergleich-card' ],
                [ 'property' => 'border-color', 'selector' => '.vergleich-label' ],
                [ 'property' => 'border-color', 'selector' => '.vergleich-zelle' ],
            ],
        ];
    }

    // ==========================================================================
    // ROW-FIELD-DEFINITIONS (Repeater-Fields)
    // ==========================================================================

    private function get_row_fields() {
        return [
            'label' => [
                'label'          => esc_html__( 'Label (linke Spalte)', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => true,
                'placeholder'    => esc_html__( 'z.B. Preis, Gewicht', 'bricks-vergleich' ),
            ],
            'type' => [
                'label'   => esc_html__( 'Zellentyp', 'bricks-vergleich' ),
                'type'    => 'select',
                'options' => [
                    'text'    => esc_html__( 'Text', 'bricks-vergleich' ),
                    'image'   => esc_html__( 'Bild', 'bricks-vergleich' ),
                    'icon'    => esc_html__( 'Icon', 'bricks-vergleich' ),
                    'button'  => esc_html__( 'Button', 'bricks-vergleich' ),
                    'rating'  => esc_html__( 'Sterne-Rating', 'bricks-vergleich' ),
                    'bool'    => esc_html__( 'Ja/Nein (Check / Cross)', 'bricks-vergleich' ),
                    'score'   => esc_html__( 'Bewertung (Note / Punkte)', 'bricks-vergleich' ),
                    'list'    => esc_html__( 'Liste mit Icon (Vor-/Nachteile)', 'bricks-vergleich' ),
                    'manual'  => esc_html__( 'Manuell pro Spalte', 'bricks-vergleich' ),
                    'html'    => esc_html__( 'HTML / Shortcode', 'bricks-vergleich' ),
                    'dynamic' => esc_html__( 'Dynamische Daten (Tag)', 'bricks-vergleich' ),
                ],
                'default' => 'text',
            ],

            // ───── TEXT ─────
            'text' => [
                'label'          => esc_html__( 'Text', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => 'text',
                'required'       => [ 'type', '=', 'text' ],
                'placeholder'    => 'z.B. {post_title}',
            ],
            'textTag' => [
                'label'    => esc_html__( 'HTML-Tag', 'bricks-vergleich' ),
                'type'     => 'select',
                'options'  => [ 'p' => 'p', 'span' => 'span', 'h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4', 'h5' => 'h5', 'h6' => 'h6', 'strong' => 'strong', 'em' => 'em' ],
                'default'  => 'p',
                'required' => [ 'type', '=', 'text' ],
            ],
            'textLink' => [
                'label'       => esc_html__( 'Verlinken (optional)', 'bricks-vergleich' ),
                'type'        => 'link',
                'description' => esc_html__( 'Leer = kein Link. Z.B. Dynamic Data "{post_url}" um zum Produkt zu verlinken.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'text' ],
            ],
            'textFallback' => [
                'label'          => esc_html__( 'Fallback wenn leer', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => 'text',
                'default'        => '-',
                'placeholder'    => '-',
                'description'    => esc_html__( 'Wird angezeigt, wenn Dynamic Data leer ist.', 'bricks-vergleich' ),
                'required'       => [ 'type', '=', 'text' ],
            ],

            // ───── IMAGE ─────
            'image' => [
                'label'    => esc_html__( 'Bild', 'bricks-vergleich' ),
                'type'     => 'image',
                'required' => [ 'type', '=', 'image' ],
            ],
            'imageLink' => [
                'label'       => esc_html__( 'Verlinken (optional)', 'bricks-vergleich' ),
                'type'        => 'link',
                'description' => esc_html__( 'Leer = kein Link. Z.B. Dynamic Data "{post_url}" um zum Produkt zu verlinken.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'image' ],
            ],
            'imageBlendMode' => [
                'label'       => esc_html__( 'Mischmodus (Blend Mode)', 'bricks-vergleich' ),
                'type'        => 'select',
                'options'     => [
                    ''           => esc_html__( 'Normal', 'bricks-vergleich' ),
                    'multiply'   => esc_html__( 'Multiply', 'bricks-vergleich' ),
                    'darken'     => esc_html__( 'Darken', 'bricks-vergleich' ),
                    'screen'     => esc_html__( 'Screen', 'bricks-vergleich' ),
                    'overlay'    => esc_html__( 'Overlay', 'bricks-vergleich' ),
                    'luminosity' => esc_html__( 'Luminosity', 'bricks-vergleich' ),
                ],
                'default'     => '',
                'description' => esc_html__( 'Multiply entfernt weiße Hintergründe bei Produktbildern visuell.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'image' ],
            ],

            // ───── ICON ─────
            'icon' => [
                'label'    => esc_html__( 'Icon', 'bricks-vergleich' ),
                'type'     => 'icon',
                'required' => [ 'type', '=', 'icon' ],
            ],
            'iconColor' => [
                'label'    => esc_html__( 'Icon-Farbe', 'bricks-vergleich' ),
                'type'     => 'color',
                'required' => [ 'type', '=', 'icon' ],
            ],
            'iconSize' => [
                'label'    => esc_html__( 'Icon-Größe', 'bricks-vergleich' ),
                'type'     => 'number',
                'units'    => true,
                'placeholder' => '24px',
                'required' => [ 'type', '=', 'icon' ],
            ],

            // ───── BUTTON ─────
            'btnText' => [
                'label'          => esc_html__( 'Button-Text', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => 'text',
                'default'        => esc_html__( 'Mehr erfahren', 'bricks-vergleich' ),
                'required'       => [ 'type', '=', 'button' ],
            ],
            'btnLink' => [
                'label'    => esc_html__( 'Button-Link', 'bricks-vergleich' ),
                'type'     => 'link',
                'required' => [ 'type', '=', 'button' ],
            ],
            'btnSize' => [
                'label'       => esc_html__( 'Button-Größe', 'bricks-vergleich' ),
                'type'        => 'select',
                'options'     => [
                    ''   => esc_html__( 'Standard', 'bricks-vergleich' ),
                    'sm' => esc_html__( 'Klein', 'bricks-vergleich' ),
                    'md' => esc_html__( 'Mittel', 'bricks-vergleich' ),
                    'lg' => esc_html__( 'Groß', 'bricks-vergleich' ),
                    'xl' => esc_html__( 'Extra groß', 'bricks-vergleich' ),
                ],
                'placeholder' => esc_html__( 'Standard', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'button' ],
            ],
            'btnStyle' => [
                'label'    => esc_html__( 'Button-Stil', 'bricks-vergleich' ),
                'type'     => 'select',
                'options'  => [
                    'primary'   => esc_html__( 'Primär', 'bricks-vergleich' ),
                    'secondary' => esc_html__( 'Sekundär', 'bricks-vergleich' ),
                    'light'     => esc_html__( 'Hell', 'bricks-vergleich' ),
                    'dark'      => esc_html__( 'Dunkel', 'bricks-vergleich' ),
                    'muted'     => esc_html__( 'Gedämpft', 'bricks-vergleich' ),
                    'info'      => esc_html__( 'Info', 'bricks-vergleich' ),
                    'success'   => esc_html__( 'Erfolg', 'bricks-vergleich' ),
                    'warning'   => esc_html__( 'Warnung', 'bricks-vergleich' ),
                    'danger'    => esc_html__( 'Gefahr', 'bricks-vergleich' ),
                ],
                'default'  => 'primary',
                'required' => [ 'type', '=', 'button' ],
            ],
            'btnCircle' => [
                'label'    => esc_html__( 'Kreis (Pill-Form)', 'bricks-vergleich' ),
                'type'     => 'checkbox',
                'required' => [ 'type', '=', 'button' ],
            ],
            'btnOutline' => [
                'label'    => esc_html__( 'Umriss', 'bricks-vergleich' ),
                'type'     => 'checkbox',
                'required' => [ 'type', '=', 'button' ],
            ],

            // ───── RATING ─────
            'ratingValue' => [
                'label'          => esc_html__( 'Rating-Wert (0–5)', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => 'text',
                'default'        => '4.5',
                'placeholder'    => '4.5 oder {acf:rating}',
                'required'       => [ 'type', '=', 'rating' ],
            ],
            'ratingMax' => [
                'label'    => esc_html__( 'Max-Wert', 'bricks-vergleich' ),
                'type'     => 'number',
                'default'  => 5,
                'required' => [ 'type', '=', 'rating' ],
            ],
            'ratingColor' => [
                'label'    => esc_html__( 'Farbe', 'bricks-vergleich' ),
                'type'     => 'color',
                'default'  => [ 'hex' => '#f59e0b' ],
                'required' => [ 'type', '=', 'rating' ],
            ],
            'ratingSize' => [
                'label'    => esc_html__( 'Sterngröße', 'bricks-vergleich' ),
                'type'     => 'number', 'units' => true,
                'default'  => 18,
                'placeholder' => '18px',
                'required' => [ 'type', '=', 'rating' ],
            ],
            'ratingShowNumber' => [
                'label'    => esc_html__( 'Zahl zusätzlich anzeigen', 'bricks-vergleich' ),
                'type'     => 'checkbox',
                'required' => [ 'type', '=', 'rating' ],
            ],
            'ratingIconFull' => [
                'label'       => esc_html__( 'Icon "voll" (optional)', 'bricks-vergleich' ),
                'type'        => 'icon',
                'description' => esc_html__( 'Ohne Auswahl werden Default-Sterne (★) verwendet.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'rating' ],
            ],
            'ratingIconEmpty' => [
                'label'       => esc_html__( 'Icon "leer" (optional)', 'bricks-vergleich' ),
                'type'        => 'icon',
                'description' => esc_html__( 'Ohne Auswahl werden Default-Sterne verwendet.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'rating' ],
            ],
            'ratingIconHalf' => [
                'label'       => esc_html__( 'Icon "halb" (optional)', 'bricks-vergleich' ),
                'type'        => 'icon',
                'description' => esc_html__( 'Wird bei Nachkommastellen (z.B. 4,5) genutzt. Ohne: gerundete Ganzzahl.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'rating' ],
            ],
            'ratingIconGap' => [
                'label'       => esc_html__( 'Abstand zwischen Icons', 'bricks-vergleich' ),
                'type'        => 'number',
                'units'       => true,
                'default'     => 2,
                'placeholder' => '2px',
                'required'    => [ 'type', '=', 'rating' ],
            ],
            'ratingEmptyColor' => [
                'label'    => esc_html__( 'Farbe "leer"', 'bricks-vergleich' ),
                'type'     => 'color',
                'default'  => [ 'hex' => '#d1d5db' ],
                'required' => [ 'type', '=', 'rating' ],
            ],

            // ───── BOOL (Ja/Nein) ─────
            'boolValue' => [
                'label'          => esc_html__( 'Wert (Dynamic Data oder fester Wert)', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => 'text',
                'default'        => '',
                'placeholder'    => '{jet_cf_app_support} oder true',
                'description'    => esc_html__( 'Akzeptiert true/false, 1/0, yes/no, on/off — alles andere (außer leer) wird als TRUE gewertet.', 'bricks-vergleich' ),
                'required'       => [ 'type', '=', 'bool' ],
            ],
            'boolTrueColor' => [
                'label'    => esc_html__( 'Farbe "Ja"', 'bricks-vergleich' ),
                'type'     => 'color',
                'default'  => [ 'hex' => '#16a34a' ],
                'required' => [ 'type', '=', 'bool' ],
            ],
            'boolFalseColor' => [
                'label'    => esc_html__( 'Farbe "Nein"', 'bricks-vergleich' ),
                'type'     => 'color',
                'default'  => [ 'hex' => '#dc2626' ],
                'required' => [ 'type', '=', 'bool' ],
            ],
            'boolSize' => [
                'label'    => esc_html__( 'Icon-Größe', 'bricks-vergleich' ),
                'type'     => 'number',
                'units'    => true,
                'default'  => 20,
                'placeholder' => '20px',
                'required' => [ 'type', '=', 'bool' ],
            ],
            'boolTrueText' => [
                'label'       => esc_html__( 'Text "Ja" (optional)', 'bricks-vergleich' ),
                'type'        => 'text',
                'default'     => '',
                'placeholder' => esc_html__( 'z.B. Ja — leer = nur Icon', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'bool' ],
            ],
            'boolFalseText' => [
                'label'       => esc_html__( 'Text "Nein" (optional)', 'bricks-vergleich' ),
                'type'        => 'text',
                'default'     => '',
                'placeholder' => esc_html__( 'z.B. Nein — leer = nur Icon', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'bool' ],
            ],

            // ───── SCORE (Bewertung als Zelle, z.B. Note 1,5 oder 88 Punkte) ─────
            'scoreKey' => [
                'label'          => esc_html__( 'Meta-Key oder Dynamic Data', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => 'text',
                'placeholder'    => esc_html__( 'z.B. bewertung oder {acf:bewertung}', 'bricks-vergleich' ),
                'description'    => esc_html__( 'Entweder reiner Meta-Key oder ein DD-Tag. Nicht-numerische Werte werden unverändert ausgegeben.', 'bricks-vergleich' ),
                'required'       => [ 'type', '=', 'score' ],
            ],
            'scoreDecimals' => [
                'label'       => esc_html__( 'Dezimalstellen', 'bricks-vergleich' ),
                'type'        => 'number',
                'min'         => 0, 'max' => 4,
                'placeholder' => '1',
                'required'    => [ 'type', '=', 'score' ],
            ],
            'scoreDecSep' => [
                'label'    => esc_html__( 'Dezimal-Trennzeichen', 'bricks-vergleich' ),
                'type'     => 'select',
                'options'  => [
                    ',' => esc_html__( 'Komma (1,5)', 'bricks-vergleich' ),
                    '.' => esc_html__( 'Punkt (1.5)', 'bricks-vergleich' ),
                ],
                'default'  => ',',
                'required' => [ 'type', '=', 'score' ],
            ],
            'scorePrefix' => [
                'label'          => esc_html__( 'Präfix', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => 'text',
                'placeholder'    => esc_html__( 'z.B. Note', 'bricks-vergleich' ),
                'required'       => [ 'type', '=', 'score' ],
            ],
            'scoreSuffix' => [
                'label'          => esc_html__( 'Suffix', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => 'text',
                'placeholder'    => esc_html__( 'z.B. /5', 'bricks-vergleich' ),
                'required'       => [ 'type', '=', 'score' ],
            ],
            'scoreFallback' => [
                'label'          => esc_html__( 'Fallback wenn leer', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => 'text',
                'placeholder'    => '-',
                'description'    => esc_html__( 'Wird angezeigt, wenn der Wert leer ist. Leer lassen um die Zelle komplett leer zu lassen.', 'bricks-vergleich' ),
                'required'       => [ 'type', '=', 'score' ],
            ],
            'scoreHideEmpty' => [
                'label'       => esc_html__( 'Zelle verbergen, wenn leer', 'bricks-vergleich' ),
                'type'        => 'checkbox',
                'description' => esc_html__( 'Kein Fallback anzeigen — Zelle bleibt leer, keine Ausgabe.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'score' ],
            ],
            '_sepScoreStyle' => [
                'type'     => 'separator',
                'label'    => esc_html__( 'Optisches Styling', 'bricks-vergleich' ),
                'required' => [ 'type', '=', 'score' ],
            ],
            'scoreBadge' => [
                'label'       => esc_html__( 'Als Kapsel / Badge rendern', 'bricks-vergleich' ),
                'type'        => 'checkbox',
                'description' => esc_html__( 'Zahl in einer farbigen Kapsel (Pill) — wie der Bewertungs-Badge auf dem Bild.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'score' ],
            ],
            'scoreBgColor' => [
                'label'    => esc_html__( 'Hintergrundfarbe', 'bricks-vergleich' ),
                'type'     => 'color',
                'required' => [ [ 'type', '=', 'score' ], [ 'scoreBadge', '=', true ] ],
            ],
            'scoreTypography' => [
                'label'    => esc_html__( 'Typografie', 'bricks-vergleich' ),
                'type'     => 'typography',
                'required' => [ 'type', '=', 'score' ],
            ],
            'scorePadding' => [
                'label'       => esc_html__( 'Innenabstand', 'bricks-vergleich' ),
                'type'        => 'spacing',
                'placeholder' => [ 'top' => '6px', 'right' => '12px', 'bottom' => '6px', 'left' => '12px' ],
                'required'    => [ [ 'type', '=', 'score' ], [ 'scoreBadge', '=', true ] ],
            ],
            'scoreBorder' => [
                'label'       => esc_html__( 'Rahmen', 'bricks-vergleich' ),
                'type'        => 'border',
                'required'    => [ [ 'type', '=', 'score' ], [ 'scoreBadge', '=', true ] ],
            ],

            // ───── LIST (Icon-Liste pro Spalte, z.B. Vorteile / Nachteile) ─────
            'listSource' => [
                'label'   => esc_html__( 'Datenquelle', 'bricks-vergleich' ),
                'type'    => 'select',
                'options' => [
                    'dynamic'       => esc_html__( 'Dynamisch (Meta / DD)', 'bricks-vergleich' ),
                    'manualColumns' => esc_html__( 'Manuell pro Spalte', 'bricks-vergleich' ),
                ],
                'default'  => 'dynamic',
                'required' => [ 'type', '=', 'list' ],
            ],
            'listDynamic' => [
                'label'          => esc_html__( 'Dynamic Data / Meta-Key', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => 'text',
                'placeholder'    => 'z. B. {acf:pros} oder pros',
                'description'    => esc_html__( 'Akzeptiert: Meta-Key (Wert zeilen-getrennt), Dynamic-Data-Tag, oder HTML mit <ul><li>…</li></ul>.', 'bricks-vergleich' ),
                'required'       => [ [ 'type', '=', 'list' ], [ 'listSource', '!=', 'manualColumns' ] ],
            ],
            'listManualColumns' => [
                'label'         => esc_html__( 'Einträge pro Spalte', 'bricks-vergleich' ),
                'type'          => 'repeater',
                'titleProperty' => 'content',
                'placeholder'   => esc_html__( 'Spalte', 'bricks-vergleich' ),
                'description'   => esc_html__( 'Pro Produkt-Spalte einen Eintrag. Rich-Text-Liste (Aufzählung) verwenden — jedes <li> wird ein Listenpunkt. Reihenfolge matched den Query-Loop.', 'bricks-vergleich' ),
                'required'      => [ [ 'type', '=', 'list' ], [ 'listSource', '=', 'manualColumns' ] ],
                'fields'        => [
                    'content' => [
                        'label'          => esc_html__( 'Inhalt', 'bricks-vergleich' ),
                        'type'           => 'editor',
                        'hasDynamicData' => 'text',
                        'description'    => esc_html__( 'Liste über das Toolbar-Icon (Aufzählung) einfügen — jeder <li>-Eintrag wird mit dem konfigurierten Icon gerendert.', 'bricks-vergleich' ),
                    ],
                ],
            ],
            'listIcon' => [
                'label'    => esc_html__( 'Icon', 'bricks-vergleich' ),
                'type'     => 'icon',
                'required' => [ 'type', '=', 'list' ],
            ],
            'listIconColor' => [
                'label'    => esc_html__( 'Icon-Farbe', 'bricks-vergleich' ),
                'type'     => 'color',
                'required' => [ 'type', '=', 'list' ],
            ],
            'listIconSize' => [
                'label'       => esc_html__( 'Icon-Größe', 'bricks-vergleich' ),
                'type'        => 'number', 'units' => true,
                'placeholder' => '16px',
                'required'    => [ 'type', '=', 'list' ],
            ],
            'listIconGap' => [
                'label'       => esc_html__( 'Abstand Icon → Text', 'bricks-vergleich' ),
                'type'        => 'number', 'units' => true,
                'placeholder' => '8px',
                'required'    => [ 'type', '=', 'list' ],
            ],
            'listItemGap' => [
                'label'       => esc_html__( 'Abstand zwischen Einträgen', 'bricks-vergleich' ),
                'type'        => 'number', 'units' => true,
                'placeholder' => '6px',
                'required'    => [ 'type', '=', 'list' ],
            ],
            'listAlign' => [
                'label'    => esc_html__( 'Ausrichtung', 'bricks-vergleich' ),
                'type'     => 'select',
                'options'  => [
                    'left'   => esc_html__( 'Links', 'bricks-vergleich' ),
                    'center' => esc_html__( 'Mitte', 'bricks-vergleich' ),
                    'right'  => esc_html__( 'Rechts', 'bricks-vergleich' ),
                ],
                'default'  => 'left',
                'required' => [ 'type', '=', 'list' ],
            ],
            'listFallback' => [
                'label'          => esc_html__( 'Fallback-Text wenn leer', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => 'text',
                'placeholder'    => '-',
                'description'    => esc_html__( 'Angezeigt, wenn für die Spalte keine Einträge vorhanden sind. Leer = gar nichts anzeigen.', 'bricks-vergleich' ),
                'required'       => [ 'type', '=', 'list' ],
            ],

            // ───── MANUAL (pro Spalte) ─────
            'manualColumns' => [
                'label'         => esc_html__( 'Werte pro Spalte', 'bricks-vergleich' ),
                'type'          => 'repeater',
                'titleProperty' => 'text',
                'placeholder'   => esc_html__( 'Spalte', 'bricks-vergleich' ),
                'description'   => esc_html__( 'Pro Produkt-Spalte einen Eintrag anlegen. Reihenfolge matched den Query-Loop. Fehlende Einträge zeigen den Fallback.', 'bricks-vergleich' ),
                'required'      => [ 'type', '=', 'manual' ],
                'fields'        => [
                    'text' => [
                        'label'          => esc_html__( 'Text / Inhalt', 'bricks-vergleich' ),
                        'type'           => 'text',
                        'hasDynamicData' => 'text',
                        'placeholder'    => esc_html__( 'z.B. Unser Tipp', 'bricks-vergleich' ),
                    ],
                    'tag' => [
                        'label'   => esc_html__( 'HTML-Tag', 'bricks-vergleich' ),
                        'type'    => 'select',
                        'options' => [ 'p' => 'p', 'span' => 'span', 'strong' => 'strong', 'em' => 'em', 'h3' => 'h3', 'h4' => 'h4', 'h5' => 'h5', 'h6' => 'h6' ],
                        'default' => 'p',
                    ],
                ],
            ],
            'manualFallback' => [
                'label'          => esc_html__( 'Fallback wenn Spalte fehlt', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => 'text',
                'default'        => '-',
                'placeholder'    => '-',
                'description'    => esc_html__( 'Greift, wenn mehr Produkte im Loop sind als Sub-Einträge definiert.', 'bricks-vergleich' ),
                'required'       => [ 'type', '=', 'manual' ],
            ],

            // ───── HTML ─────
            'html' => [
                'label'       => esc_html__( 'HTML / Shortcode', 'bricks-vergleich' ),
                'type'        => 'code',
                'mode'        => 'htmlmixed',
                'description' => esc_html__( 'Dynamic-Data-Tags werden aufgelöst. Shortcodes werden ausgeführt.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'html' ],
            ],

            // ───── DYNAMIC ─────
            'dynamic' => [
                'label'          => esc_html__( 'Dynamic-Data-Tag(s)', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => 'text',
                'placeholder'    => '{woo_product_price} oder {cf:feature}',
                'required'       => [ 'type', '=', 'dynamic' ],
            ],
            'dynamicFallback' => [
                'label'          => esc_html__( 'Fallback wenn leer', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => 'text',
                'default'        => '-',
                'placeholder'    => '-',
                'description'    => esc_html__( 'Wird angezeigt, wenn das Tag leer auflöst.', 'bricks-vergleich' ),
                'required'       => [ 'type', '=', 'dynamic' ],
            ],

            // ───── COMMON ─────
            'labelTooltip' => [
                'label'          => esc_html__( 'Label-Tooltip', 'bricks-vergleich' ),
                'type'           => 'textarea',
                'hasDynamicData' => 'text',
                'placeholder'    => esc_html__( 'Optionaler Erklärtext, erscheint als Tooltip beim Hover auf das Info-Icon.', 'bricks-vergleich' ),
            ],
            'highlight' => [
                'label' => esc_html__( 'Zeile hervorheben', 'bricks-vergleich' ),
                'type'  => 'checkbox',
            ],
            'highlightStyle' => [
                'label'   => esc_html__( 'Hervorhebungs-Stil', 'bricks-vergleich' ),
                'type'    => 'select',
                'options' => [
                    'background' => esc_html__( 'Hintergrundfarbe', 'bricks-vergleich' ),
                    'shadow'     => esc_html__( 'Schatten', 'bricks-vergleich' ),
                    'both'       => esc_html__( 'Beides', 'bricks-vergleich' ),
                ],
                'default'  => 'background',
                'required' => [ 'highlight', '=', true ],
            ],
            'highlightBg' => [
                'label'    => esc_html__( 'Hintergrundfarbe (Hervorhebung)', 'bricks-vergleich' ),
                'type'     => 'color',
                'required' => [ 'highlight', '=', true ],
            ],
            'highlightTextColor' => [
                'label'    => esc_html__( 'Textfarbe Label (Hervorhebung)', 'bricks-vergleich' ),
                'type'     => 'color',
                'required' => [ 'highlight', '=', true ],
            ],
            'collapsible' => [
                'label' => esc_html__( 'Zur Aufklapp-Zone', 'bricks-vergleich' ),
                'type'  => 'checkbox',
            ],
            // Marker für Builder-Badge (siehe assets/builder.js)
            '_markerCollapsible' => [
                'type'     => 'info',
                'content'  => '',
                'required' => [ 'collapsible', '=', true ],
            ],
            'cellAlign' => [
                'label'   => esc_html__( 'Inhalt ausrichten', 'bricks-vergleich' ),
                'type'    => 'select',
                'options' => [
                    ''       => esc_html__( '— Vererben —', 'bricks-vergleich' ),
                    'left'   => esc_html__( 'Links', 'bricks-vergleich' ),
                    'center' => esc_html__( 'Zentriert', 'bricks-vergleich' ),
                    'right'  => esc_html__( 'Rechts', 'bricks-vergleich' ),
                ],
            ],
        ];
    }

    // ==========================================================================
    // RENDER
    // ==========================================================================

    public function render() {
        try {
            $this->render_inner();
        } catch ( \Throwable $e ) {
            error_log( '[Bricks Vergleich] Render-Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . ' | trace: ' . $e->getTraceAsString() );
            echo '<div style="padding:1rem;border:1px solid #fca5a5;background:#fef2f2;color:#991b1b;font-family:monospace;font-size:12px;">'
                . 'Vergleich-Render-Fehler: ' . esc_html( $e->getMessage() ) . '<br>'
                . esc_html( $e->getFile() ) . ':' . (int) $e->getLine()
                . '</div>';
        }
    }

    private function render_inner() {
        $settings = isset( $this->settings ) && is_array( $this->settings ) ? $this->settings : [];

        $label_width    = $this->get_css_value( $settings['labelWidth']    ?? null, '200px' );
        $column_width   = $this->get_css_value( $settings['columnWidth']   ?? null, '200px' );
        $row_min_height = $this->get_css_value( $settings['rowMinHeight']  ?? null, '20px' );
        $sticky         = ! empty( $settings['stickyLabels'] );
        $divider        = ! empty( $settings['showDivider'] );
        $text_align     = $settings['textAlign'] ?? 'center';

        // Image
        $enforce_img = ! empty( $settings['imageEnforce'] ) || ! isset( $settings['imageEnforce'] );
        $img_width   = $this->get_css_value( $settings['imageWidth']  ?? null, '100px' );
        $img_height  = $this->get_css_value( $settings['imageHeight'] ?? null, '100px' );
        $img_fit     = $settings['imageObjectFit'] ?? 'cover';

        // Expand/Collapse
        $expand_enabled   = ! empty( $settings['expandEnabled'] );
        $expand_label     = isset( $settings['expandLabel'] ) && $settings['expandLabel'] !== '' ? (string) $settings['expandLabel'] : esc_html__( 'Alle Kriterien anzeigen', 'bricks-vergleich' );
        $collapse_label   = isset( $settings['collapseLabel'] ) && $settings['collapseLabel'] !== '' ? (string) $settings['collapseLabel'] : esc_html__( 'Weniger anzeigen', 'bricks-vergleich' );
        $expand_label     = $this->dd_string( $expand_label );
        $collapse_label   = $this->dd_string( $collapse_label );
        $expand_btn_style   = isset( $settings['expandButtonStyle'] ) ? preg_replace( '/[^a-z]/i', '', strtolower( (string) $settings['expandButtonStyle'] ) ) : 'primary';
        if ( $expand_btn_style === '' ) $expand_btn_style = 'primary';
        $expand_btn_size    = isset( $settings['expandBtnSize'] ) ? preg_replace( '/[^a-z]/i', '', strtolower( (string) $settings['expandBtnSize'] ) ) : '';
        $expand_btn_circle  = ! empty( $settings['expandBtnCircle'] );
        $expand_btn_outline = ! empty( $settings['expandBtnOutline'] );
        $expand_align       = $settings['expandAlign'] ?? 'center';
        $expand_show_icon   = ! isset( $settings['expandShowIcon'] ) || ! empty( $settings['expandShowIcon'] );
        $expand_fade_enabled = ! empty( $settings['expandFadeEnabled'] );

        // Ranking
        $ranking_enabled   = ! empty( $settings['rankingEnabled'] );
        $ranking_start     = isset( $settings['rankingStart'] ) && $settings['rankingStart'] !== '' ? (int) $settings['rankingStart'] : 1;
        $ranking_reverse   = ! empty( $settings['rankingReverse'] );
        $ranking_prefix    = isset( $settings['rankingPrefix'] ) ? (string) $settings['rankingPrefix'] : '#';
        $ranking_suffix    = isset( $settings['rankingSuffix'] ) ? (string) $settings['rankingSuffix'] : '';
        $ranking_prefix    = $this->dd_string( $ranking_prefix );
        $ranking_suffix    = $this->dd_string( $ranking_suffix );
        $ranking_position      = $settings['rankingPosition'] ?? 'top-left';
        $ranking_offset_y      = $this->get_css_value( $settings['rankingOffsetY'] ?? null, '8px' );
        $ranking_offset_x      = $this->get_css_value( $settings['rankingOffsetX'] ?? null, '8px' );
        $ranking_size          = $this->get_css_value( $settings['rankingSize']    ?? null, '36px' );
        $ranking_padding       = $this->format_spacing( $settings['rankingPadding'] ?? null, '4px 10px' );
        $ranking_highlight_top = ! empty( $settings['rankingHighlightTop'] );

        $this->_ranking_runtime = [
            'enabled'       => $ranking_enabled,
            'start'         => $ranking_start,
            'reverse'       => $ranking_reverse,
            'prefix'        => $ranking_prefix,
            'suffix'        => $ranking_suffix,
            'highlight_top' => $ranking_highlight_top,
            'total'         => 0,
        ];

        // Score / Bewertung
        $score_enabled     = ! empty( $settings['scoreEnabled'] );
        $score_meta_key    = isset( $settings['scoreMetaKey'] ) ? trim( (string) $settings['scoreMetaKey'] ) : '';
        $score_decimals    = isset( $settings['scoreDecimals'] ) && $settings['scoreDecimals'] !== '' ? max( 0, min( 4, (int) $settings['scoreDecimals'] ) ) : 1;
        $score_dec_sep     = isset( $settings['scoreDecimalSeparator'] ) && $settings['scoreDecimalSeparator'] === '.' ? '.' : ',';
        $score_prefix      = $this->dd_string( (string) ( $settings['scorePrefix'] ?? '' ) );
        $score_suffix      = $this->dd_string( (string) ( $settings['scoreSuffix'] ?? '' ) );
        $score_position    = $settings['scorePosition'] ?? 'bottom-left';
        $score_offset_y    = $this->get_css_value( $settings['scoreOffsetY'] ?? null, '8px' );
        $score_offset_x    = $this->get_css_value( $settings['scoreOffsetX'] ?? null, '8px' );
        $score_min_size    = $this->get_css_value( $settings['scoreMinSize'] ?? null, '36px' );
        $score_padding     = $this->format_spacing( $settings['scorePadding'] ?? null, '6px 10px' );
        $score_hide_empty = ! isset( $settings['scoreHideEmpty'] ) || ! empty( $settings['scoreHideEmpty'] );

        $this->_score_runtime = [
            'enabled'     => $score_enabled,
            'meta_key'    => $score_meta_key,
            'decimals'    => $score_decimals,
            'dec_sep'     => $score_dec_sep,
            'prefix'      => $score_prefix,
            'suffix'      => $score_suffix,
            'hide_empty'  => $score_hide_empty,
        ];

        // Produkt-Labels (manueller Balken oberhalb jeder Spalte)
        $product_labels_enabled   = ! empty( $settings['productLabelsEnabled'] );
        $product_labels_items_raw = isset( $settings['productLabelsItems'] ) && is_array( $settings['productLabelsItems'] ) ? $settings['productLabelsItems'] : [];
        $product_labels_fallback  = $this->dd_string( (string) ( $settings['productLabelsFallback'] ?? '' ) );
        $product_labels_left_lbl  = $this->dd_string( (string) ( $settings['productLabelsLeftLabel'] ?? '' ) );

        // Items normalisieren: pro Index text/bg/color auflösen.
        $product_labels_items = [];
        foreach ( $product_labels_items_raw as $item ) {
            if ( ! is_array( $item ) ) $item = [];
            $product_labels_items[] = [
                'text'      => $this->dd_string( (string) ( $item['text'] ?? '' ) ),
                'bgColor'   => $this->resolve_color( $item['bgColor'] ?? null ),
                'textColor' => $this->resolve_color( $item['textColor'] ?? null ),
            ];
        }

        $this->_product_label_runtime = [
            'enabled'  => $product_labels_enabled,
            'items'    => $product_labels_items,
            'fallback' => $product_labels_fallback,
            'left'     => $product_labels_left_lbl,
        ];

        // Navigation
        $nav_enabled      = ! empty( $settings['navEnabled'] );
        $nav_size         = $this->get_css_value( $settings['navSize']     ?? null, '44px' );
        $nav_icon_size    = $this->get_css_value( $settings['navIconSize'] ?? null, '18px' );
        $nav_offset       = $this->get_css_value( $settings['navOffset']   ?? null, '12px' );
        $nav_bg_color     = $this->resolve_color( $settings['navBgColor']     ?? null ) ?: '#ffffff';
        $nav_icon_color   = $this->resolve_color( $settings['navIconColor']   ?? null ) ?: '#111827';
        $nav_scroll_step = $settings['navScrollStep'] ?? 'card';
        if ( $nav_scroll_step !== 'view' ) $nav_scroll_step = 'card';

        $nav_counter_enabled  = ! empty( $settings['navCounterEnabled'] );
        $nav_counter_position = ( $settings['navCounterPosition'] ?? 'above' ) === 'below' ? 'below' : 'above';
        $nav_counter_format   = isset( $settings['navCounterFormat'] ) && $settings['navCounterFormat'] !== ''
            ? (string) $settings['navCounterFormat']
            : '{start}–{end} von {total}';
        $nav_counter_align = $settings['navCounterAlign'] ?? 'right';
        if ( ! in_array( $nav_counter_align, [ 'left', 'center', 'right' ], true ) ) $nav_counter_align = 'right';

        // Rows
        $rows = $this->get_rows();
        $row_count = max( 1, count( $rows ) );
        $visible_row_count = 0;
        $first_collapsible_idx = -1;
        foreach ( $rows as $ri => $r ) {
            if ( empty( $r['collapsible'] ) ) {
                $visible_row_count++;
            } elseif ( $first_collapsible_idx === -1 ) {
                $first_collapsible_idx = $ri;
            }
        }
        $visible_row_count = max( 1, $visible_row_count );

        // Index der ersten aufklappbaren Zeile — wird bei aktivem Fade-Effekt
        // im kollabierten Zustand als "Peek" angezeigt (statt display:none).
        $this->_first_collapsible_idx = $first_collapsible_idx;

        // Inline-Style auf _root: NUR Runtime-berechnete Werte, die nicht
        // ueber Bricks' css-Array-Pipeline gesetzt werden koennen.
        // Keine Fallback-Werte fuer User-Controls: inline-style hat Spezifi-
        // taet 1000 und wuerde jede Klassen-/Global-Style-Regel ueberschreiben.
        // Fehlende Controls werden durch var(--name, default) im CSS abgefangen.
        $inline_style = sprintf(
            '--vgl-row-count:%d; --vgl-row-count-collapsed:%d; --vgl-text-align:%s;',
            $row_count, $visible_row_count, esc_attr( $text_align )
        );

        // Struktur: _root = neutraler Container mit CSS-Variablen,
        //           .vergleich-wrapper = bordertes Table-Grid (innen),
        //           .vergleich-expand  = Aufklapp-Button (außen, Geschwister der Wrapper).
        // So liegt der Button optisch UNTER dem Table statt innerhalb des Border-Frames.
        $root_classes = [ 'vergleich-root' ];
        if ( $expand_enabled && $visible_row_count < $row_count ) {
            $root_classes[] = 'has-expand';
            if ( $expand_fade_enabled ) $root_classes[] = 'has-expand-fade';
        }

        $wrapper_classes = [ 'vergleich-wrapper' ];
        if ( $sticky )         $wrapper_classes[] = 'has-sticky-labels';
        if ( $divider )        $wrapper_classes[] = 'has-dividers';
        if ( $enforce_img )    $wrapper_classes[] = 'has-enforced-images';
        if ( $ranking_enabled ) {
            $wrapper_classes[] = 'has-ranking';
            $wrapper_classes[] = 'has-ranking-pos-' . preg_replace( '/[^a-z\-]/', '', strtolower( (string) $ranking_position ) );
        }
        if ( $score_enabled ) {
            $wrapper_classes[] = 'has-score';
            $wrapper_classes[] = 'has-score-pos-' . preg_replace( '/[^a-z\-]/', '', strtolower( (string) $score_position ) );
        }
        if ( $product_labels_enabled ) {
            $wrapper_classes[] = 'has-product-labels';
        }
        if ( $nav_enabled ) {
            $wrapper_classes[] = 'has-nav';
            $wrapper_classes[] = 'vgl-nav-step-' . $nav_scroll_step;
        }
        if ( ! empty( $settings['rowHoverEnabled'] ) ) {
            $wrapper_classes[] = 'has-row-hover';
        }
        if ( $expand_enabled && $visible_row_count < $row_count ) {
            $wrapper_classes[] = 'has-expand';
            $wrapper_classes[] = 'is-collapsed';
            if ( $expand_fade_enabled ) $wrapper_classes[] = 'has-expand-fade';
        }

        $this->set_attribute( '_root', 'class', $root_classes );
        $this->set_attribute( '_root', 'style', $inline_style );
        $this->set_attribute( '_root', 'data-row-count', (string) $row_count );

        // Inline CSS nur einmal pro Request, bei Builder-/AJAX-Requests immer
        static $css_printed = false;
        $is_builder_request = ( defined( 'DOING_AJAX' ) && DOING_AJAX )
            || ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() )
            || ( function_exists( 'bricks_is_builder_call' ) && bricks_is_builder_call() );
        if ( ! $css_printed || $is_builder_request ) {
            echo $this->get_inline_css();
            $css_printed = true;
        }

        echo "<div {$this->render_attributes( '_root' )}>";

        // Counter-Element: zeigt "X–Y von Z" an. Wird per JS aktualisiert, der
        // Initialtext ist ein Fallback falls kein JS. Position: über oder unter
        // dem Table-Wrapper. data-format steuert die Darstellung via JS.
        $counter_id   = 'vgl-counter-' . (string) $this->id;
        $counter_html = '';
        if ( $nav_counter_enabled ) {
            // Initialtext: zeigt "…" als Placeholder bis JS echte Werte
            // einfuegt. So bleibt das Element auch im Canvas sichtbar, wo
            // das Sync-Script u.U. erst verzoegert laeuft.
            $counter_html = sprintf(
                '<div id="%s" class="vergleich-counter is-align-%s" data-vgl-counter data-format="%s" aria-live="polite">&hellip;</div>',
                esc_attr( $counter_id ),
                esc_attr( $nav_counter_align ),
                esc_attr( $nav_counter_format )
            );
        }
        if ( $nav_counter_enabled && $nav_counter_position === 'above' ) {
            echo $counter_html;
        }

        // Query bereits hier erzeugen (statt erst unten), damit die Anzahl
        // Produkte fuer die Produkt-Label-Leiste verfuegbar ist. Dasselbe
        // Query-Objekt wird weiter unten fuer das Rendern der Cards benutzt.
        $has_loop       = ! empty( $settings['hasLoop'] );
        $prepared_query = null;
        $prepared_count = 0;
        if ( $has_loop && class_exists( '\Bricks\Query' ) ) {
            try {
                $element_for_query = [
                    'id'       => $this->id,
                    'name'     => $this->name,
                    'settings' => $this->settings,
                ];
                $prepared_query = new \Bricks\Query( $element_for_query );
                $prepared_count = (int) ( $prepared_query->count ?? 0 );
                if ( is_array( $this->_ranking_runtime ) ) {
                    $this->_ranking_runtime['total'] = $prepared_count;
                }
            } catch ( \Throwable $e ) {
                $prepared_query = null;
            }
        }

        // ─── PRODUKT-LABELS (oberhalb des Wrappers) ────────────────────────
        // Wird als eigene Leiste vor dem Wrapper gerendert — sieht damit aus
        // wie ein freischwebender Balken über der Tabelle, nicht wie eine
        // weitere Zeile im Tabellenrahmen. Horizontaler Scroll wird per JS
        // an .vergleich-scroll gekoppelt (siehe print_sync_script).
        if ( ! empty( $this->_product_label_runtime['enabled'] ) ) {
            $pl_cfg    = $this->_product_label_runtime;
            $pl_items  = is_array( $pl_cfg['items'] ?? null ) ? $pl_cfg['items'] : [];
            $pl_fb     = (string) ( $pl_cfg['fallback'] ?? '' );
            $pl_left   = (string) ( $pl_cfg['left'] ?? '' );

            // Anzahl der Spalten: Query-Count bevorzugt, sonst Anzahl Items,
            // sonst 1.
            $pl_total = $prepared_count > 0 ? $prepared_count : max( 1, count( $pl_items ) );

            // Layout-kritische Styles inline setzen — Bricks-Canvas ueberschreibt
            // sonst gelegentlich unsere Klassen-Regeln (display:flex/grid greift
            // im Canvas nicht zuverlaessig). Inline gewinnt immer.
            $row_style    = 'display:flex;margin:0 1px var(--vgl-product-label-gap,6px);max-width:100%;';
            $spacer_style = 'flex:0 0 var(--vgl-label-width,200px);width:var(--vgl-label-width,200px);max-width:var(--vgl-label-width,200px);display:flex;align-items:center;padding:0 var(--vgl-cell-padding,16px);min-width:0;background:transparent;';
            $scroll_style = 'flex:1 1 auto;min-width:0;overflow:hidden;';
            $track_style  = 'display:flex;flex-direction:row;flex-wrap:nowrap;min-width:0;will-change:transform;';
            // Layout-kritische Styles inline; Typografie kommt über das
            // typography-Control (selektorbasierte CSS-Regel).
            $item_base    = 'flex:0 0 var(--vgl-column-width,200px);width:var(--vgl-column-width,200px);max-width:var(--vgl-column-width,200px);'
                . 'min-height:var(--vgl-product-label-height,40px);padding:var(--vgl-product-label-padding,8px 12px);'
                . 'line-height:1.2;display:flex;align-items:center;'
                . 'justify-content:center;overflow:hidden;box-sizing:border-box;';

            echo '<div class="vergleich-product-label-row" aria-hidden="true" style="' . esc_attr( $row_style ) . '">';

            // Linker Spacer — gleiche Breite wie die Label-Spalte; optionaler Text.
            echo '<div class="vergleich-product-label-row__spacer" style="' . esc_attr( $spacer_style ) . '">';
            if ( $pl_left !== '' ) {
                echo '<span class="vergleich-product-label-row__spacer-text">' . wp_kses_post( $pl_left ) . '</span>';
            }
            echo '</div>';

            // Rechts: Track mit einem Item pro Spalte — Breite pro Item matcht
            // die Card-Breite der Tabelle.
            echo '<div class="vergleich-product-label-row__scroll" style="' . esc_attr( $scroll_style ) . '">';
            echo '<div class="vergleich-product-label-row__track" style="' . esc_attr( $track_style ) . '">';

            for ( $i = 0; $i < $pl_total; $i++ ) {
                $item     = isset( $pl_items[ $i ] ) && is_array( $pl_items[ $i ] ) ? $pl_items[ $i ] : null;
                $pl_text  = is_array( $item ) ? (string) ( $item['text'] ?? '' ) : '';
                if ( $pl_text === '' ) $pl_text = $pl_fb;
                $pl_bg    = is_array( $item ) ? (string) ( $item['bgColor']   ?? '' ) : '';
                $pl_color = is_array( $item ) ? (string) ( $item['textColor'] ?? '' ) : '';

                $cls = 'vergleich-product-label-item';
                if ( $pl_text === '' ) $cls .= ' is-empty';

                // Pro-Item-Overrides: wenn im Repeater-Eintrag gesetzt, hart
                // inline. Sonst greifen die Typografie- und BG-Controls.
                $item_style = $item_base;
                if ( $pl_text === '' ) {
                    $item_style .= 'background:transparent;pointer-events:none;';
                } else {
                    if ( $pl_bg    !== '' ) $item_style .= 'background:' . $pl_bg . ';';
                    if ( $pl_color !== '' ) $item_style .= 'color:' . $pl_color . ';';
                }

                echo '<div class="' . esc_attr( $cls ) . '" style="' . esc_attr( $item_style ) . '">';
                if ( $pl_text !== '' ) {
                    echo '<span class="vergleich-product-label-item__text">' . wp_kses_post( $pl_text ) . '</span>';
                }
                echo '</div>';
            }

            echo '</div></div>'; // /__track /__scroll
            echo '</div>'; // /.vergleich-product-label-row
        }

        // Innerer Table-Wrapper (Bordered, enthält Labels + Cards)
        $wrapper_data_attrs = 'data-row-count="' . (int) $row_count . '"';
        if ( $nav_counter_enabled ) {
            $wrapper_data_attrs .= ' data-counter="' . esc_attr( $counter_id ) . '"';
        }
        echo '<div class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '" ' . $wrapper_data_attrs . '>';

        // ─── LABEL COLUMN ──────────────────────────────────────────────────
        echo '<div class="vergleich-labels">';

        if ( empty( $rows ) ) {
            echo '<div class="vergleich-label" style="color:#9ca3af;font-style:italic;">'
                . esc_html__( '(Zeilen im Repeater hinzufügen)', 'bricks-vergleich' )
                . '</div>';
        }
        foreach ( $rows as $idx => $row ) {
            $label       = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
            $highlight   = ! empty( $row['highlight'] );
            $collapsible = ! empty( $row['collapsible'] );
            $row_key     = $this->row_key( $row, $idx );

            if ( $label === '' ) {
                $label = sprintf( esc_html__( 'Zeile %d', 'bricks-vergleich' ), $idx + 1 );
            }

            $cls = 'vergleich-label';
            $cls .= ' is-row-' . ( $idx % 2 === 0 ? 'odd' : 'even' );
            $label_inline = '';
            if ( $highlight ) {
                $hl_style = $row['highlightStyle'] ?? 'background';
                if ( $hl_style === 'shadow' || $hl_style === 'both' ) $cls .= ' is-highlight-shadow';
                if ( $hl_style !== 'shadow' ) {
                    $cls .= ' is-highlighted';
                    $hl_bg   = $this->resolve_color( $row['highlightBg'] ?? null );
                    $hl_text = $this->resolve_color( $row['highlightTextColor'] ?? null );
                    if ( $hl_bg )   $label_inline .= 'background:' . $hl_bg . ';';
                    if ( $hl_text ) $label_inline .= 'color:' . $hl_text . ';';
                }
            }
            if ( $collapsible ) {
                $cls .= ' is-collapsible';
                if ( $idx === $first_collapsible_idx ) $cls .= ' is-peek';
            }

            $extra = ' data-row-index="' . (int) $idx . '"';
            if ( $collapsible ) {
                $extra .= ' data-vergleich-row-id="' . esc_attr( $row_key ) . '"';
            }
            if ( $label_inline !== '' ) {
                $extra .= ' style="' . esc_attr( $label_inline ) . '"';
            }

            echo '<div class="' . esc_attr( $cls ) . '"' . $extra . '>';
            echo '<span class="vergleich-label__text">' . wp_kses_post( $this->dd_string( $label ) ) . '</span>';

            // Optionaler Tooltip-Text: rendert ein kleines Info-Icon mit
            // data-tooltip-Attribut. Inhalt per DD aufgeloest, als text (keine
            // HTML-Tags, damit CSS-Tooltip mit content: attr() arbeitet).
            $tip_raw = isset( $row['labelTooltip'] ) ? trim( (string) $row['labelTooltip'] ) : '';
            if ( $tip_raw !== '' ) {
                $tip_text = $this->dd_string( $tip_raw );
                $tip_text = wp_strip_all_tags( (string) $tip_text );
                if ( $tip_text !== '' ) {
                    echo '<button type="button" class="vergleich-tooltip" data-tooltip="' . esc_attr( $tip_text ) . '" aria-label="' . esc_attr( $tip_text ) . '" title="' . esc_attr( $tip_text ) . '">';
                    echo '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
                    echo '</button>';
                }
            }

            echo '</div>';
        }
        echo '</div>';

        // ─── SCROLL / TRACK (Cards) ────────────────────────────────────────
        echo '<div class="vergleich-scroll"><div class="vergleich-track">';

        if ( $prepared_query instanceof \Bricks\Query ) {
            try {
                $query = $prepared_query;
                $loop_output = $query->render( [ $this, 'render_card' ], [] );
                if ( trim( (string) $loop_output ) === '' ) {
                    echo '<div class="vergleich-card"><div class="vergleich-zelle" style="padding:2rem;color:#6b7280;">'
                        . esc_html__( 'Keine Einträge gefunden. Query anpassen.', 'bricks-vergleich' )
                        . '</div></div>';
                } else {
                    echo $loop_output;
                }
                $query->destroy();
                unset( $query );
            } catch ( \Throwable $e ) {
                echo '<div class="vergleich-card"><div class="vergleich-zelle" style="padding:2rem;color:#dc2626;">Query-Fehler: '
                    . esc_html( $e->getMessage() )
                    . '</div></div>';
            }
        } else {
            // Ohne Loop: eine Spalte mit aktuellem Post-Kontext
            echo $this->render_card();
        }

        echo '</div></div>';

        // Navigations-Pfeile (nur wenn aktiviert). Sitzen absolut innerhalb
        // des Wrappers — JS aktiviert sie, wenn die Cards horizontal überlaufen.
        if ( $nav_enabled ) {
            $arrow_l = '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>';
            $arrow_r = '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>';
            echo '<button type="button" class="vergleich-nav vergleich-nav--prev" aria-label="' . esc_attr__( 'Zurück', 'bricks-vergleich' ) . '" data-vgl-nav="prev" hidden>' . $arrow_l . '</button>';
            echo '<button type="button" class="vergleich-nav vergleich-nav--next" aria-label="' . esc_attr__( 'Weiter', 'bricks-vergleich' ) . '" data-vgl-nav="next" hidden>' . $arrow_r . '</button>';
        }

        echo '</div>'; // .vergleich-wrapper schließen — Expand-Button liegt außerhalb

        if ( $nav_counter_enabled && $nav_counter_position === 'below' ) {
            echo $counter_html;
        }

        // ─── EXPAND BUTTON (außerhalb des Table-Wrappers, native Bricks-Klassen) ──
        if ( $expand_enabled && $visible_row_count < $row_count ) {
            $btn_align_cls = 'is-align-' . preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $expand_align ) );

            $btn_classes = [ 'bricks-button', 'vergleich-expand-btn' ];
            if ( in_array( $expand_btn_size, [ 'sm', 'md', 'lg', 'xl' ], true ) ) {
                $btn_classes[] = $expand_btn_size;
            }
            if ( $expand_btn_outline ) {
                $btn_classes[] = 'outline';
                $btn_classes[] = 'bricks-color-' . $expand_btn_style;
            } else {
                $btn_classes[] = 'bricks-background-' . $expand_btn_style;
            }
            if ( $expand_btn_circle ) $btn_classes[] = 'circle';

            echo '<div class="vergleich-expand ' . esc_attr( $btn_align_cls ) . '">';
            echo '<button type="button" class="' . esc_attr( implode( ' ', $btn_classes ) ) . '"'
                . ' data-label-expand="' . esc_attr( $expand_label ) . '"'
                . ' data-label-collapse="' . esc_attr( $collapse_label ) . '"'
                . ' aria-expanded="false">';
            echo '<span class="vergleich-expand-text">' . esc_html( $expand_label ) . '</span>';
            if ( $expand_show_icon ) {
                echo '<span class="vergleich-expand-icon" aria-hidden="true" style="display:inline-flex;margin-left:6px;"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><polyline points="5 8 10 13 15 8"></polyline></svg></span>';
            }
            echo '</button></div>';
        }

        echo '</div>'; // .vergleich-root

        $this->print_sync_script();
    }

    // ==========================================================================
    // RENDER CARD (eine Produkt-Spalte)
    // ==========================================================================

    public function render_card() {
        try {
            return $this->render_card_inner();
        } catch ( \Throwable $e ) {
            error_log( '[Bricks Vergleich] Card-Render-Fehler: ' . $e->getMessage() );
            return '<div class="vergleich-card"><div class="vergleich-zelle" style="color:#9ca3af;font-size:12px;">'
                . esc_html__( '(Render-Fehler – siehe PHP-Log)', 'bricks-vergleich' )
                . '</div></div>';
        }
    }

    private function render_card_inner() {
        $settings = isset( $this->settings ) && is_array( $this->settings ) ? $this->settings : [];
        $rows     = $this->get_rows();
        $default_align = $settings['textAlign'] ?? 'center';

        // Post/Product-Context auf Loop-Object setzen
        $loop_post    = null;
        $loop_post_id = 0;
        if ( class_exists( '\Bricks\Query' ) && method_exists( '\Bricks\Query', 'get_loop_object' ) ) {
            $loop_obj = \Bricks\Query::get_loop_object();
            if ( $loop_obj instanceof \WP_Post ) {
                $loop_post    = $loop_obj;
                $loop_post_id = (int) $loop_obj->ID;
            } elseif ( is_object( $loop_obj ) && method_exists( $loop_obj, 'get_id' ) ) {
                $maybe_id = (int) $loop_obj->get_id();
                if ( $maybe_id > 0 ) {
                    $loop_post_id = $maybe_id;
                    $loop_post    = get_post( $maybe_id );
                }
            }
        }
        if ( ! $loop_post_id ) {
            $maybe = (int) get_the_ID();
            if ( $maybe > 0 ) {
                $loop_post_id = $maybe;
                $loop_post    = get_post( $maybe );
            }
        }

        $prev_post    = $GLOBALS['post']    ?? null;
        $prev_product = $GLOBALS['product'] ?? null;

        if ( $loop_post instanceof \WP_Post ) {
            $GLOBALS['post'] = $loop_post;
            setup_postdata( $loop_post );
            if ( function_exists( 'wc_get_product' ) && $loop_post->post_type === 'product' ) {
                $wc_product = wc_get_product( $loop_post_id );
                if ( $wc_product ) {
                    $GLOBALS['product'] = $wc_product;
                }
            }
        }

        // Ranking-Badge
        $rank_html  = '';
        $card_class = 'vergleich-card';
        $card_data  = '';
        $rank_cfg   = is_array( $this->_ranking_runtime ) ? $this->_ranking_runtime : null;
        if ( $rank_cfg && ! empty( $rank_cfg['enabled'] ) ) {
            $loop_idx = 0;
            if ( class_exists( '\Bricks\Query' ) && method_exists( '\Bricks\Query', 'get_loop_index' ) ) {
                $li = \Bricks\Query::get_loop_index();
                if ( $li !== '' && $li !== null ) $loop_idx = (int) $li;
            }
            $start = (int) $rank_cfg['start'];
            $total = (int) $rank_cfg['total'];
            $rank_number = ( ! empty( $rank_cfg['reverse'] ) && $total > 0 )
                ? $start + ( $total - 1 - $loop_idx )
                : $start + $loop_idx;
            if ( $rank_number < 0 ) $rank_number = 0;

            $card_class = 'vergleich-card has-rank vergleich-card--rank-' . $rank_number;
            if ( ! empty( $rank_cfg['highlight_top'] ) && $rank_number === 1 ) {
                $card_class .= ' is-rank-top';
            }
            $card_data = ' data-rank="' . esc_attr( (string) $rank_number ) . '"';

            $prefix = (string) ( $rank_cfg['prefix'] ?? '' );
            $suffix = (string) ( $rank_cfg['suffix'] ?? '' );
            $rank_html  = '<div class="vergleich-rank" aria-label="' . esc_attr( sprintf( 'Platz %d', $rank_number ) ) . '">';
            if ( $prefix !== '' ) $rank_html .= '<span class="vergleich-rank__prefix">' . esc_html( $prefix ) . '</span>';
            $rank_html .= '<span class="vergleich-rank__number">' . esc_html( (string) $rank_number ) . '</span>';
            if ( $suffix !== '' ) $rank_html .= '<span class="vergleich-rank__suffix">' . esc_html( $suffix ) . '</span>';
            $rank_html .= '</div>';
        }

        // Score-Badge (Bewertung aus Meta-Feld)
        $score_html = '';
        $score_cfg  = is_array( $this->_score_runtime ) ? $this->_score_runtime : null;
        if ( $score_cfg && ! empty( $score_cfg['enabled'] ) && ! empty( $score_cfg['meta_key'] ) && $loop_post_id > 0 ) {
            $key = (string) $score_cfg['meta_key'];
            $raw_str = '';
            // Enthält DD-Tag? → über Bricks auflösen (respektiert Loop-Kontext).
            if ( strpos( $key, '{' ) !== false && function_exists( 'bricks_render_dynamic_data' ) ) {
                $resolved = bricks_render_dynamic_data( $key, $loop_post_id );
                $raw_str  = is_string( $resolved ) ? trim( $resolved ) : '';
                // Wenn Bricks das Tag nicht auflösen konnte, bleibt der Roh-Tag
                // stehen — dann werten wir als "leer".
                if ( $raw_str === $key ) {
                    $raw_str = '';
                }
            } else {
                $raw = get_post_meta( $loop_post_id, $key, true );
                if ( is_array( $raw ) ) {
                    $raw = reset( $raw );
                }
                $raw_str = is_scalar( $raw ) ? trim( (string) $raw ) : '';
            }

            $is_empty = ( $raw_str === '' );
            if ( ! ( $is_empty && ! empty( $score_cfg['hide_empty'] ) ) ) {
                // Wert formatieren: als Zahl parsen (Komma→Punkt), dann mit
                // Dezimalstellen ausgeben. Nicht-numerische Werte durchreichen.
                $display = $raw_str;
                $normalized = str_replace( ',', '.', $raw_str );
                if ( $normalized !== '' && is_numeric( $normalized ) ) {
                    $num     = (float) $normalized;
                    $dec     = (int) $score_cfg['decimals'];
                    $sep     = (string) $score_cfg['dec_sep'];
                    $display = number_format( $num, $dec, $sep, '' );
                }

                $s_prefix = (string) $score_cfg['prefix'];
                $s_suffix = (string) $score_cfg['suffix'];

                $score_html  = '<div class="vergleich-score" aria-label="' . esc_attr__( 'Bewertung', 'bricks-vergleich' ) . '">';
                if ( $s_prefix !== '' ) $score_html .= '<span class="vergleich-score__prefix">' . esc_html( $s_prefix ) . '</span>';
                $score_html .= '<span class="vergleich-score__value">' . esc_html( $display ) . '</span>';
                if ( $s_suffix !== '' ) $score_html .= '<span class="vergleich-score__suffix">' . esc_html( $s_suffix ) . '</span>';
                $score_html .= '</div>';
            }
        }

        // Anker-Index bestimmen: erste Bild-Zeile, sonst 0. Das Badge wird
        // als Kind der entsprechenden Zelle gerendert, damit seine absolute
        // Positionierung an der Zellen-Box klebt — nicht an der ganzen Card.
        $score_anchor_idx = -1;
        if ( $score_html !== '' ) {
            $score_anchor_idx = 0;
            foreach ( $rows as $ri => $rr ) {
                if ( ( $rr['type'] ?? 'text' ) === 'image' ) {
                    $score_anchor_idx = $ri;
                    break;
                }
            }
        }

        ob_start();
        echo '<div class="' . esc_attr( $card_class ) . '"' . $card_data . '>';
        echo $rank_html;

        foreach ( $rows as $idx => $row ) {
            $inject = ( $idx === $score_anchor_idx ) ? $score_html : '';
            echo $this->render_cell( $row, $idx, $default_align, $inject );
        }

        echo '</div>';
        $html = ob_get_clean();

        // Context reset
        if ( $prev_post !== null ) {
            $GLOBALS['post'] = $prev_post;
            setup_postdata( $prev_post );
        } else {
            wp_reset_postdata();
        }
        if ( $prev_product !== null ) {
            $GLOBALS['product'] = $prev_product;
        } else {
            unset( $GLOBALS['product'] );
        }

        return $html;
    }

    // ==========================================================================
    // RENDER CELL (eine Zelle innerhalb einer Card)
    // ==========================================================================

    private function render_cell( $row, $idx, $default_align, $append_html = '' ) {
        $type        = $row['type']       ?? 'text';
        $highlight   = ! empty( $row['highlight'] );
        $collapsible = ! empty( $row['collapsible'] );
        $align       = ! empty( $row['cellAlign'] ) ? $row['cellAlign'] : $default_align;
        $row_key     = $this->row_key( $row, $idx );

        $classes = [ 'vergleich-zelle', 'vergleich-zelle--' . preg_replace( '/[^a-z0-9_-]/i', '', $type ) ];
        $classes[] = 'is-row-' . ( $idx % 2 === 0 ? 'odd' : 'even' );
        $styles = [];
        if ( $highlight ) {
            $hl_style = $row['highlightStyle'] ?? 'background';
            if ( $hl_style === 'shadow' || $hl_style === 'both' ) $classes[] = 'is-highlight-shadow';
            if ( $hl_style !== 'shadow' ) {
                $classes[] = 'is-highlighted';
                $hl_bg = $this->resolve_color( $row['highlightBg'] ?? null );
                if ( $hl_bg ) $styles[] = 'background:' . $hl_bg;
            }
        }
        if ( $collapsible ) {
            $classes[] = 'is-collapsible';
            if ( $idx === (int) $this->_first_collapsible_idx ) $classes[] = 'is-peek';
        }
        if ( $append_html !== '' ) $classes[] = 'has-score-anchor';
        if ( $align ) {
            $map = [
                'left'   => [ 'justify-content' => 'flex-start', 'text-align' => 'left' ],
                'center' => [ 'justify-content' => 'center',     'text-align' => 'center' ],
                'right'  => [ 'justify-content' => 'flex-end',   'text-align' => 'right' ],
            ];
            if ( isset( $map[ $align ] ) ) {
                foreach ( $map[ $align ] as $k => $v ) {
                    $styles[] = $k . ':' . $v;
                }
            }
        }

        $extra = ' data-row-index="' . (int) $idx . '"';
        if ( $collapsible ) $extra .= ' data-vergleich-row-id="' . esc_attr( $row_key ) . '"';
        if ( ! empty( $styles ) ) $extra .= ' style="' . esc_attr( implode( ';', $styles ) ) . '"';

        // Zelleninhalt defensiv rendern — Fehler eines einzelnen Renderers
        // dürfen nicht den ganzen Card-Render abschießen.
        $content = '';
        try {
            switch ( $type ) {
                case 'image':   $content = $this->render_cell_image( $row );   break;
                case 'icon':    $content = $this->render_cell_icon( $row );    break;
                case 'button':  $content = $this->render_cell_button( $row, $idx );  break;
                case 'rating':  $content = $this->render_cell_rating( $row );  break;
                case 'bool':    $content = $this->render_cell_bool( $row );    break;
                case 'score':   $content = $this->render_cell_score( $row );   break;
                case 'list':    $content = $this->render_cell_list( $row );    break;
                case 'manual':  $content = $this->render_cell_manual( $row );  break;
                case 'html':    $content = $this->render_cell_html( $row );    break;
                case 'dynamic': $content = $this->render_cell_dynamic( $row ); break;
                case 'text':
                default:        $content = $this->render_cell_text( $row );    break;
            }
        } catch ( \Throwable $e ) {
            error_log( '[Bricks Vergleich] Cell-Render-Fehler (' . $type . '): ' . $e->getMessage() );
            $content = '';
        }

        return '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"' . $extra . '>' . $content . $append_html . '</div>';
    }

    /**
     * Dynamic Data sicher auflösen: gibt IMMER einen String zurück.
     * Bricks' `bricks_render_dynamic_data()` kann je nach Tag ein Array liefern
     * (z.B. Bild-IDs, Multi-Select-ACF). Für Text-Rendering müssen wir das zu
     * einem String reduzieren, sonst crasht `strpos`/`wp_kses_post`/`strip_tags`.
     */
    private function dd_string( $value ) {
        if ( ! is_string( $value ) ) {
            return '';
        }
        if ( $value === '' || ! function_exists( 'bricks_render_dynamic_data' ) ) {
            return $value;
        }
        try {
            $resolved = bricks_render_dynamic_data( $value );
        } catch ( \Throwable $e ) {
            error_log( '[Bricks Vergleich] DD-Fehler: ' . $e->getMessage() );
            return $value;
        }
        if ( is_array( $resolved ) ) {
            // Flache Arrays (Multi-Values) mit Komma joinen.
            $resolved = implode( ', ', array_map( function ( $v ) {
                return is_scalar( $v ) ? (string) $v : '';
            }, $resolved ) );
        }
        return is_scalar( $resolved ) ? (string) $resolved : '';
    }

    private function render_cell_text( $row ) {
        $raw = isset( $row['text'] ) ? (string) $row['text'] : '';
        if ( $raw === '' ) return '';
        $tag = preg_replace( '/[^a-z0-9]/i', '', (string) ( $row['textTag'] ?? 'p' ) ) ?: 'p';
        $resolved = $this->dd_string( $raw );

        // Fallback wenn der aufgelöste String leer oder reines Whitespace ist.
        if ( trim( strip_tags( $resolved ) ) === '' ) {
            $fb_raw = isset( $row['textFallback'] ) ? (string) $row['textFallback'] : '';
            if ( $fb_raw === '' ) return '';
            $resolved = $this->dd_string( $fb_raw );
            if ( trim( strip_tags( $resolved ) ) === '' ) return '';
        }

        $link = $this->resolve_link( $row['textLink'] ?? null );
        $inner = wp_kses_post( $resolved );
        // Link-Wrap INNERHALB des Tags, damit die semantische Struktur (z.B. <h3>)
        // erhalten bleibt und der Link nur den Text umschließt.
        $content = $this->maybe_wrap_link( $inner, $link, 'vergleich-text-link' );
        return '<' . $tag . ' class="vergleich-text">' . $content . '</' . $tag . '>';
    }

    private function render_cell_image( $row ) {
        $image = $row['image'] ?? null;
        if ( ! is_array( $image ) ) return '';

        $url = '';
        $alt = '';
        $size = is_string( $image['size'] ?? null ) && $image['size'] !== '' ? $image['size'] : 'medium';

        // Dynamic-Data-Tag (z.B. {featured_image}) — Bricks nutzt hier render_tag()
        // mit 'image'-Context; Rückgabe ist typischerweise [attachment_id] oder [url].
        $dyn = is_string( $image['useDynamicData'] ?? null ) ? trim( $image['useDynamicData'] ) : '';
        if ( $dyn !== '' ) {
            // Shortcut für {featured_image}: direkt WP-API, kein Umweg durch
            // Bricks' DD-Filter-Chain (die bei manchen Providern strpos auf
            // Arrays auslöst und das Errorlog flutet).
            if ( preg_match( '/^\{\s*featured_image\s*\}$/', $dyn ) ) {
                $pid = 0;
                if ( class_exists( '\Bricks\Query' ) && method_exists( '\Bricks\Query', 'get_loop_object_id' ) && \Bricks\Query::is_looping() ) {
                    $pid = (int) \Bricks\Query::get_loop_object_id();
                }
                if ( ! $pid ) $pid = (int) get_the_ID();
                if ( $pid ) {
                    $thumb_id = (int) get_post_thumbnail_id( $pid );
                    if ( $thumb_id ) {
                        $src = wp_get_attachment_image_src( $thumb_id, $size );
                        if ( $src ) {
                            $url = (string) $src[0];
                            $alt = (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
                        }
                    }
                }
            } elseif ( method_exists( $this, 'render_dynamic_data_tag' ) ) {
                // Andere DD-Tags: Bricks' regulärer Pfad
                try {
                    $resolved = $this->render_dynamic_data_tag( $dyn, 'image', [ 'size' => $size ] );
                    if ( is_array( $resolved ) && ! empty( $resolved[0] ) ) {
                        $first = $resolved[0];
                        if ( is_numeric( $first ) ) {
                            $attach_id = (int) $first;
                            $src = wp_get_attachment_image_src( $attach_id, $size );
                            if ( $src ) {
                                $url = (string) $src[0];
                                $alt = (string) get_post_meta( $attach_id, '_wp_attachment_image_alt', true );
                            }
                        } elseif ( is_string( $first ) && $first !== '' ) {
                            $url = $first;
                        }
                    } elseif ( is_string( $resolved ) && $resolved !== '' ) {
                        $url = $resolved;
                    }
                } catch ( \Throwable $e ) {
                    // Still unterdrücken — nur beim ERSTEN Vorkommen loggen.
                    static $logged_once = false;
                    if ( ! $logged_once ) {
                        $logged_once = true;
                        error_log( '[Bricks Vergleich] Image DD-Fehler (einmalig, weitere unterdrückt): ' . $e->getMessage() );
                    }
                }
            }
        }
        if ( $url === '' && ! empty( $image['id'] ) ) {
            $src = wp_get_attachment_image_src( (int) $image['id'], $size );
            if ( $src ) {
                $url = (string) $src[0];
                $alt = (string) get_post_meta( (int) $image['id'], '_wp_attachment_image_alt', true );
            }
        }
        if ( $url === '' && ! empty( $image['url'] ) && is_string( $image['url'] ) ) {
            $url = $image['url'];
        }
        $link = $this->resolve_link( $row['imageLink'] ?? null );

        if ( $url === '' ) {
            $placeholder = '<div class="vergleich-image-placeholder" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="32" height="32"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>';
            return $this->maybe_wrap_link( $placeholder, $link, 'vergleich-image-link' );
        }

        $blend = isset( $row['imageBlendMode'] ) ? (string) $row['imageBlendMode'] : '';
        $allowed_blend = [ 'multiply', 'darken', 'screen', 'overlay', 'luminosity' ];
        $style_attr = '';
        if ( in_array( $blend, $allowed_blend, true ) ) {
            $style_attr = ' style="mix-blend-mode:' . esc_attr( $blend ) . ';"';
        }

        $img = sprintf(
            '<img class="vergleich-image" src="%s" alt="%s" loading="lazy"%s />',
            esc_url( $url ),
            esc_attr( $alt ),
            $style_attr
        );
        return $this->maybe_wrap_link( $img, $link, 'vergleich-image-link' );
    }

    private function render_cell_icon( $row ) {
        $icon       = $row['icon'] ?? null;
        $color      = $this->resolve_color( $row['iconColor'] ?? null );
        $size       = $this->get_css_value( $row['iconSize'] ?? null, '24px' );
        if ( ! is_array( $icon ) || ( empty( $icon['icon'] ) && empty( $icon['svg'] ) ) ) {
            return '';
        }
        $style = 'font-size:' . esc_attr( $size ) . ';';
        if ( $color ) $style .= 'color:' . esc_attr( $color ) . ';';
        $attrs = [ 'class' => [ 'vergleich-icon' ], 'style' => $style ];
        return \Bricks\Element::render_icon( $icon, $attrs );
    }

    private function render_cell_button( $row, $idx = 0 ) {
        $text    = $this->dd_string( isset( $row['btnText'] ) ? (string) $row['btnText'] : '' );
        $link    = $row['btnLink']   ?? [];
        $style   = isset( $row['btnStyle'] ) ? preg_replace( '/[^a-z]/i', '', strtolower( (string) $row['btnStyle'] ) ) : 'primary';
        if ( $style === '' ) $style = 'primary';
        $size    = isset( $row['btnSize'] ) ? preg_replace( '/[^a-z]/i', '', strtolower( (string) $row['btnSize'] ) ) : '';
        $circle  = ! empty( $row['btnCircle'] );
        $outline = ! empty( $row['btnOutline'] );

        $url        = '';
        $target     = '';
        $rel        = '';
        $aria_label = '';
        $title_attr = '';

        if ( is_array( $link ) ) {
            $link_type = $link['type'] ?? '';

            // Internal: WP-Post auswählen
            if ( $link_type === 'internal' && ! empty( $link['postId'] ) ) {
                $url = (string) ( get_permalink( (int) $link['postId'] ) ?: '' );
                if ( $url !== '' && ! empty( $link['urlParams'] ) ) {
                    $url .= $this->dd_string( (string) $link['urlParams'] );
                }
            }
            // External: URL (kann DD-Tags wie "{post_url}?x=y" enthalten)
            elseif ( $link_type === 'external' && ! empty( $link['url'] ) ) {
                $raw = (string) $link['url'];
                $url = $this->dd_string( $raw );
            }
            // Dynamic Data (Bricks-Link-Typ "meta"): nur ein DD-Tag wie {post_url}
            elseif ( $link_type === 'meta' && ! empty( $link['useDynamicData'] ) ) {
                $dd_tag = is_array( $link['useDynamicData'] )
                    ? (string) ( $link['useDynamicData']['name'] ?? '' )
                    : (string) $link['useDynamicData'];
                $url = $this->dd_string( $dd_tag );
            }
            // Taxonomy
            elseif ( $link_type === 'taxonomy' && ! empty( $link['term'] ) ) {
                $parts = explode( '::', (string) $link['term'] );
                if ( count( $parts ) === 2 ) {
                    $term = get_term( (int) $parts[1], $parts[0] );
                    if ( $term && ! is_wp_error( $term ) ) {
                        $term_link = get_term_link( $term );
                        if ( ! is_wp_error( $term_link ) ) $url = (string) $term_link;
                    }
                }
            }
            // Media
            elseif ( $link_type === 'media' && ! empty( $link['mediaData']['id'] ) ) {
                $url = (string) ( wp_get_attachment_url( (int) $link['mediaData']['id'] ) ?: '' );
            }
            // Fallback für ältere Datenstrukturen
            elseif ( ! empty( $link['url'] ) ) {
                $url = $this->dd_string( (string) $link['url'] );
            }

            if ( ! empty( $link['newTab'] ) )   $target = '_blank';
            if ( ! empty( $link['rel'] ) )      $rel = $this->dd_string( (string) $link['rel'] );
            if ( ! empty( $link['nofollow'] ) ) $rel = trim( $rel . ' nofollow' );
            if ( ! empty( $link['ariaLabel'] ) ) $aria_label = $this->dd_string( (string) $link['ariaLabel'] );
            if ( ! empty( $link['title'] ) )    $title_attr = $this->dd_string( (string) $link['title'] );
        } elseif ( is_string( $link ) ) {
            $url = $this->dd_string( $link );
        }

        // Native Bricks-Button-Klassen → optisch 1:1 wie ein echtes Button-Element.
        // Styling kommt komplett aus Bricks' Theme-CSS (bricks-button, bricks-background-*,
        // bricks-color-*, sm/md/lg/xl, outline, circle).
        $class_list = [ 'bricks-button', 'vergleich-btn' ];
        if ( in_array( $size, [ 'sm', 'md', 'lg', 'xl' ], true ) ) {
            $class_list[] = $size;
        }
        if ( $outline ) {
            $class_list[] = 'outline';
            $class_list[] = 'bricks-color-' . $style;
        } else {
            $class_list[] = 'bricks-background-' . $style;
        }
        if ( $circle ) $class_list[] = 'circle';

        $attrs  = 'class="' . esc_attr( implode( ' ', $class_list ) ) . '"';
        if ( $aria_label !== '' ) $attrs .= ' aria-label="' . esc_attr( $aria_label ) . '"';
        if ( $title_attr !== '' ) $attrs .= ' title="' . esc_attr( $title_attr ) . '"';
        if ( $url !== '' ) {
            $attrs .= ' href="' . esc_url( $url ) . '"';
            if ( $target !== '' ) $attrs .= ' target="' . esc_attr( $target ) . '"';
            if ( $rel !== '' )    $attrs .= ' rel="' . esc_attr( trim( $rel ) ) . '"';
            return '<a ' . $attrs . '>' . esc_html( $text ) . '</a>';
        }
        return '<button type="button" ' . $attrs . '>' . esc_html( $text ) . '</button>';
    }

    private function render_cell_rating( $row ) {
        $raw   = $this->dd_string( isset( $row['ratingValue'] ) ? (string) $row['ratingValue'] : '0' );
        $value = (float) str_replace( ',', '.', trim( strip_tags( $raw ) ) );
        $max   = isset( $row['ratingMax'] ) && $row['ratingMax'] !== '' ? (int) $row['ratingMax'] : 5;
        if ( $max < 1 ) $max = 5;
        $fill_color  = $this->resolve_color( $row['ratingColor']      ?? null ) ?: '#f59e0b';
        $empty_color = $this->resolve_color( $row['ratingEmptyColor'] ?? null ) ?: '#d1d5db';
        $size  = $this->get_css_value( $row['ratingSize']    ?? null, '18px' );
        $gap   = $this->get_css_value( $row['ratingIconGap'] ?? null, '2px' );
        $show_number = ! empty( $row['ratingShowNumber'] );

        $icon_full  = $row['ratingIconFull']  ?? null;
        $icon_empty = $row['ratingIconEmpty'] ?? null;
        $icon_half  = $row['ratingIconHalf']  ?? null;
        $has_custom = is_array( $icon_full ) && ( ! empty( $icon_full['icon'] ) || ! empty( $icon_full['svg'] ) );

        $value_clamped = max( 0, min( (float) $max, $value ) );

        $number_html = '';
        if ( $show_number ) {
            $decimals = ( $value_clamped == (int) $value_clamped ) ? 0 : 1;
            $number_html = '<span class="vergleich-rating__number" style="font-weight:600;">'
                . esc_html( number_format_i18n( $value_clamped, $decimals ) )
                . '</span>';
        }

        // ─── Custom-Icon-Rendering ────────────────────────────────────────
        if ( $has_custom ) {
            $has_half = is_array( $icon_half ) && ( ! empty( $icon_half['icon'] ) || ! empty( $icon_half['svg'] ) );

            // Entscheidung pro Position: full / half / empty
            $full_count  = (int) floor( $value_clamped );
            $frac        = $value_clamped - $full_count;
            $use_half    = $has_half && $frac >= 0.25 && $frac < 0.75;
            $extra_full  = ( ! $use_half && $frac >= 0.5 ) ? 1 : 0;
            $full_count += $extra_full;

            // Wrap-Span zwingt Icon auf fixe Größe (SVG-Icons bringen oft eigene
            // width/height HTML-Attribute von 512px mit, die font-size ignorieren).
            // Die CSS-Regel .vergleich-rating__icon > * setzt SVG/Icon auf 100%.
            $wrap_style = sprintf(
                'display:inline-flex;align-items:center;justify-content:center;width:%s;height:%s;line-height:1;font-size:%s;flex:0 0 auto;',
                esc_attr( $size ), esc_attr( $size ), esc_attr( $size )
            );

            $parts = '';
            for ( $i = 1; $i <= $max; $i++ ) {
                if ( $i <= $full_count ) {
                    $state_class = 'is-full';
                    $color       = $fill_color;
                    $icon        = $icon_full;
                } elseif ( $use_half && $i === $full_count + 1 ) {
                    $state_class = 'is-half';
                    $color       = $fill_color;
                    $icon        = $icon_half;
                } else {
                    $state_class = 'is-empty';
                    $color       = $empty_color;
                    $icon        = ( is_array( $icon_empty ) && ( ! empty( $icon_empty['icon'] ) || ! empty( $icon_empty['svg'] ) ) )
                        ? $icon_empty : $icon_full;
                }
                $inner_icon = \Bricks\Element::render_icon( $icon );
                $parts .= '<span class="vergleich-rating__icon ' . esc_attr( $state_class ) . '"'
                    . ' style="' . esc_attr( $wrap_style . 'color:' . $color . ';' ) . '">'
                    . $inner_icon
                    . '</span>';
            }

            return '<div class="vergleich-rating has-custom-icons" style="display:inline-flex;align-items:center;gap:6px;">'
                . '<span class="vergleich-rating__icons" style="display:inline-flex;align-items:center;gap:' . esc_attr( $gap ) . ';">'
                . $parts
                . '</span>'
                . $number_html
                . '</div>';
        }

        // ─── Fallback: ★-Zeichen mit CSS-Overlay für Teilfüllung ─────────
        $pct = ( $value_clamped / $max ) * 100;
        $html  = '<div class="vergleich-rating" style="display:inline-flex;align-items:center;gap:6px;font-size:' . esc_attr( $size ) . ';">';
        $html .= '<span class="vergleich-rating__stars" style="position:relative;color:' . esc_attr( $empty_color ) . ';letter-spacing:' . esc_attr( $gap ) . ';font-family:Arial,sans-serif;">';
        $html .= str_repeat( '★', $max );
        $html .= '<span class="vergleich-rating__fill" style="position:absolute;inset:0;width:' . esc_attr( $pct ) . '%;overflow:hidden;color:' . esc_attr( $fill_color ) . ';white-space:nowrap;">';
        $html .= str_repeat( '★', $max );
        $html .= '</span></span>';
        $html .= $number_html;
        $html .= '</div>';
        return $html;
    }

    /**
     * Evaluiert einen Wert als Boolean. Umgang mit allen gängigen Formaten:
     * true/false, 1/0, "true"/"false", "yes"/"no", "on"/"off", 1/0, "", null.
     * Leer/null/0/false → false, alles andere → true.
     */
    private function to_bool( $value ) {
        if ( is_bool( $value ) ) return $value;
        if ( is_null( $value ) ) return false;
        if ( is_numeric( $value ) ) return (float) $value != 0;
        if ( is_string( $value ) ) {
            $v = strtolower( trim( $value ) );
            if ( $v === '' )                                        return false;
            if ( in_array( $v, [ 'false', '0', 'no', 'off', 'nein' ], true ) ) return false;
            return true;
        }
        if ( is_array( $value ) )  return ! empty( $value );
        return (bool) $value;
    }

    private function render_cell_bool( $row ) {
        $raw_input = isset( $row['boolValue'] ) ? (string) $row['boolValue'] : '';
        $resolved  = $raw_input === '' ? '' : $this->dd_string( $raw_input );
        $is_true   = $this->to_bool( $resolved );

        $true_color  = $this->resolve_color( $row['boolTrueColor']  ?? null ) ?: '#16a34a';
        $false_color = $this->resolve_color( $row['boolFalseColor'] ?? null ) ?: '#dc2626';
        $size        = $this->get_css_value( $row['boolSize'] ?? null, '20px' );
        $true_text   = isset( $row['boolTrueText'] )  ? (string) $row['boolTrueText']  : '';
        $false_text  = isset( $row['boolFalseText'] ) ? (string) $row['boolFalseText'] : '';

        $color = $is_true ? $true_color : $false_color;
        $label = $is_true ? $true_text  : $false_text;
        $aria  = $is_true ? esc_html__( 'Ja', 'bricks-vergleich' ) : esc_html__( 'Nein', 'bricks-vergleich' );

        // Inline SVG — immer in currentColor, damit der umgebende color-Style greift.
        if ( $is_true ) {
            $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="4 12 10 18 20 6"/></svg>';
        } else {
            $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>';
        }

        $wrap_style = sprintf(
            'display:inline-flex;align-items:center;justify-content:center;gap:6px;color:%s;font-weight:600;',
            esc_attr( $color )
        );
        $icon_style = sprintf(
            'display:inline-block;width:%s;height:%s;flex:0 0 auto;',
            esc_attr( $size ), esc_attr( $size )
        );

        $out  = '<span class="vergleich-bool is-' . ( $is_true ? 'true' : 'false' ) . '"'
              . ' style="' . esc_attr( $wrap_style ) . '"'
              . ' aria-label="' . esc_attr( $aria ) . '">';
        $out .= '<span class="vergleich-bool__icon" style="' . esc_attr( $icon_style ) . '">' . $svg . '</span>';
        if ( $label !== '' ) {
            $out .= '<span class="vergleich-bool__text">' . esc_html( $label ) . '</span>';
        }
        $out .= '</span>';
        return $out;
    }

    /**
     * Manuelle Werte pro Produkt-Spalte. Greift via Bricks-Query-Loop-Index
     * auf das i-te Sub-Repeater-Item zu. Ohne Loop (einzelne Demo-Card) → Index 0.
     */
    private function render_cell_score( $row ) {
        $key         = isset( $row['scoreKey'] ) ? trim( (string) $row['scoreKey'] ) : '';
        $decimals    = isset( $row['scoreDecimals'] ) && $row['scoreDecimals'] !== '' ? max( 0, min( 4, (int) $row['scoreDecimals'] ) ) : 1;
        $dec_sep     = isset( $row['scoreDecSep'] ) && $row['scoreDecSep'] === '.' ? '.' : ',';
        $prefix      = $this->dd_string( (string) ( $row['scorePrefix'] ?? '' ) );
        $suffix      = $this->dd_string( (string) ( $row['scoreSuffix'] ?? '' ) );
        $hide_empty  = ! empty( $row['scoreHideEmpty'] );
        $as_badge    = ! empty( $row['scoreBadge'] );

        // Rohwert auflösen — DD-Tag oder reiner Meta-Key.
        $raw_str = '';
        if ( $key !== '' ) {
            if ( strpos( $key, '{' ) !== false ) {
                $resolved = $this->dd_string( $key );
                $raw_str  = is_string( $resolved ) ? trim( $resolved ) : '';
                // Wenn Bricks das Tag nicht auflösen konnte, bleibt der Roh-Tag
                // stehen — dann werten wir als "leer".
                if ( $raw_str === $key ) $raw_str = '';
            } else {
                $post_id = 0;
                if ( class_exists( '\Bricks\Query' ) && method_exists( '\Bricks\Query', 'get_loop_object_id' ) && \Bricks\Query::is_looping() ) {
                    $post_id = (int) \Bricks\Query::get_loop_object_id();
                }
                if ( ! $post_id ) $post_id = (int) get_the_ID();
                if ( $post_id ) {
                    $meta = get_post_meta( $post_id, $key, true );
                    if ( is_array( $meta ) ) $meta = reset( $meta );
                    $raw_str = is_scalar( $meta ) ? trim( (string) $meta ) : '';
                }
            }
        }

        $is_empty = ( $raw_str === '' );
        if ( $is_empty ) {
            if ( $hide_empty ) return '';
            $fb = isset( $row['scoreFallback'] ) ? (string) $row['scoreFallback'] : '';
            if ( $fb === '' ) return '';
            $raw_str = (string) $this->dd_string( $fb );
            if ( trim( $raw_str ) === '' ) return '';
        }

        // Numerisch? → mit Dezimalstellen formatieren (Komma→Punkt vor parseFloat,
        // dann Ausgabe mit gewähltem Trennzeichen). Nicht-numerisches durchreichen.
        $display    = $raw_str;
        $normalized = str_replace( ',', '.', $raw_str );
        if ( $normalized !== '' && is_numeric( $normalized ) ) {
            $display = number_format( (float) $normalized, $decimals, $dec_sep, '' );
        }

        // Inline-Styling (Canvas-robust). Typografie aus dem Bricks-Control
        // extrahieren, Defaults greifen nur, wenn nichts gesetzt.
        $typo_defaults = [
            'font-size'   => $as_badge ? '14px' : '16px',
            'font-weight' => '700',
            'line-height' => '1.2',
        ];
        if ( $as_badge ) $typo_defaults['color'] = '#ffffff';

        $typo_css = $this->format_typography( $row['scoreTypography'] ?? null, $typo_defaults );
        $style = $typo_css . 'display:inline-flex;align-items:center;gap:4px;';

        if ( $as_badge ) {
            $bg      = $this->resolve_color( $row['scoreBgColor'] ?? null ) ?: '#111827';
            $padding = $this->format_spacing( $row['scorePadding'] ?? null, '6px 12px' );
            $border_css = $this->format_border( $row['scoreBorder'] ?? null, [ 'radius' => '9999px' ] );
            $style .= 'background:' . esc_attr( $bg ) . ';'
                   .  'padding:' . esc_attr( $padding ) . ';' . $border_css
                   .  'box-shadow:0 1px 2px rgba(0,0,0,.12);white-space:nowrap;';
        }

        $html  = '<span class="vergleich-score-cell' . ( $as_badge ? ' is-badge' : '' ) . '" style="' . esc_attr( $style ) . '">';
        if ( $prefix !== '' ) $html .= '<span class="vergleich-score-cell__prefix" style="opacity:.85;">' . esc_html( $prefix ) . '</span>';
        $html .= '<span class="vergleich-score-cell__value">' . esc_html( $display ) . '</span>';
        if ( $suffix !== '' ) $html .= '<span class="vergleich-score-cell__suffix" style="opacity:.85;">' . esc_html( $suffix ) . '</span>';
        $html .= '</span>';
        return $html;
    }

    private function render_cell_list( $row ) {
        $source       = isset( $row['listSource'] ) ? (string) $row['listSource'] : 'dynamic';
        $icon         = $row['listIcon'] ?? null;
        $icon_color   = $this->resolve_color( $row['listIconColor'] ?? null );
        $icon_size    = $this->get_css_value( $row['listIconSize'] ?? null, '16px' );
        $icon_gap     = $this->get_css_value( $row['listIconGap'] ?? null, '8px' );
        $item_gap     = $this->get_css_value( $row['listItemGap'] ?? null, '6px' );
        $align        = $row['listAlign'] ?? 'left';
        $fallback_raw = isset( $row['listFallback'] ) ? (string) $row['listFallback'] : '';

        // Datenquelle auflösen → Array von Strings (HTML pro Listeneintrag erlaubt).
        $raw = '';
        if ( $source === 'manualColumns' ) {
            $cols = isset( $row['listManualColumns'] ) && is_array( $row['listManualColumns'] )
                ? array_values( array_filter( $row['listManualColumns'], 'is_array' ) )
                : [];

            // Loop-Index (passende Spalte auswählen).
            $loop_idx = 0;
            if ( class_exists( '\Bricks\Query' ) && method_exists( '\Bricks\Query', 'get_loop_index' ) ) {
                $li = \Bricks\Query::get_loop_index();
                if ( $li !== '' && $li !== null ) $loop_idx = (int) $li;
            }
            $entry = $cols[ $loop_idx ] ?? null;
            $raw   = is_array( $entry ) ? (string) ( $entry['content'] ?? '' ) : '';
            if ( $raw !== '' ) $raw = $this->dd_string( $raw );
        } else {
            $key = isset( $row['listDynamic'] ) ? trim( (string) $row['listDynamic'] ) : '';
            if ( $key !== '' ) {
                // DD-Tag? → über Bricks auflösen (respektiert Loop-Kontext).
                if ( strpos( $key, '{' ) !== false ) {
                    $raw = (string) $this->dd_string( $key );
                } else {
                    // Reiner Meta-Key: Wert des aktuellen Loop-Posts holen.
                    $post_id = 0;
                    if ( class_exists( '\Bricks\Query' ) && method_exists( '\Bricks\Query', 'get_loop_object_id' ) && \Bricks\Query::is_looping() ) {
                        $post_id = (int) \Bricks\Query::get_loop_object_id();
                    }
                    if ( ! $post_id ) $post_id = (int) get_the_ID();
                    if ( $post_id ) {
                        $meta = get_post_meta( $post_id, $key, true );
                        if ( is_array( $meta ) ) {
                            // Array-Meta (ACF Checkbox/Multi-Select): direkt als Items nehmen.
                            $items = array_filter( array_map( 'strval', $meta ), function( $v ) { return trim( $v ) !== ''; } );
                            $items = array_values( $items );
                            return $this->render_list_html( $items, $icon, $icon_color, $icon_size, $icon_gap, $item_gap, $align, $fallback_raw );
                        }
                        $raw = is_scalar( $meta ) ? (string) $meta : '';
                    }
                }
            }
        }

        $items = $this->parse_list_items( $raw );
        return $this->render_list_html( $items, $icon, $icon_color, $icon_size, $icon_gap, $item_gap, $align, $fallback_raw );
    }

    /**
     * Zerlegt Rohdaten (HTML / Text) in einzelne Listeneinträge.
     * Bevorzugt <li>-Inhalte; sonst Zeilenumbrüche (\n oder <br>); entfernt
     * führende Bullet-Zeichen (•, -, *). Behält Inline-HTML pro Eintrag.
     */
    private function parse_list_items( $raw ) {
        $raw = trim( (string) $raw );
        if ( $raw === '' ) return [];

        // HTML-Liste? → <li>-Inhalte extrahieren.
        if ( stripos( $raw, '<li' ) !== false ) {
            $items = [];
            if ( preg_match_all( '/<li\b[^>]*>(.*?)<\/li>/is', $raw, $m ) ) {
                foreach ( $m[1] as $item ) {
                    $cleaned = trim( $item );
                    if ( $cleaned !== '' ) $items[] = $cleaned;
                }
            }
            if ( ! empty( $items ) ) return $items;
        }

        // <br> als Zeilenumbruch behandeln.
        $normalized = preg_replace( '/<br\s*\/?>/i', "\n", $raw );
        // Absätze (<p>) als Zeilentrenner.
        $normalized = preg_replace( '/<\/p>\s*<p[^>]*>/i', "\n", $normalized );
        $normalized = preg_replace( '/<\/?p[^>]*>/i', '', $normalized );

        $lines = preg_split( '/\r?\n/', (string) $normalized );
        $items = [];
        foreach ( $lines as $line ) {
            $line = trim( (string) $line );
            // Führende Bullet-Zeichen entfernen (•, ·, -, *).
            $line = preg_replace( '/^[\s\-\*\x{2022}\x{00B7}]+/u', '', (string) $line );
            if ( $line !== '' ) $items[] = $line;
        }
        return $items;
    }

    /**
     * Rendert die aufgelöste Item-Liste als <ul> mit Icon pro Eintrag.
     * Layout-kritische Styles inline — Bricks-Canvas kann Klassen-Regeln
     * zeitweise schlucken.
     */
    private function render_list_html( $items, $icon, $icon_color, $icon_size, $icon_gap, $item_gap, $align, $fallback_raw ) {
        if ( empty( $items ) ) {
            $fb = $fallback_raw !== '' ? (string) $this->dd_string( $fallback_raw ) : '';
            if ( trim( strip_tags( $fb ) ) === '' ) return '';
            return '<span class="vergleich-list__fallback">' . wp_kses_post( $fb ) . '</span>';
        }

        // Icon-Block einmalig bauen (wird pro Eintrag per HTML-Kopie eingesetzt).
        // Wichtig: SVG-Icons ignorieren font-size und bringen ihre native
        // Größe mit (z.B. 512px). Deshalb die Box mit festen width/height
        // zwingen; das innere <svg> wird per CSS auf 100% gezogen (s. Inline-CSS).
        $icon_html = '';
        if ( is_array( $icon ) && ( ! empty( $icon['icon'] ) || ! empty( $icon['svg'] ) ) ) {
            $sz    = esc_attr( $icon_size );
            $style = 'width:' . $sz . ';height:' . $sz . ';min-width:' . $sz . ';'
                   . 'flex:0 0 ' . $sz . ';'
                   . 'font-size:' . $sz . ';line-height:1;'
                   . 'display:inline-flex;align-items:center;justify-content:center;'
                   . 'overflow:hidden;box-sizing:content-box;';
            if ( $icon_color ) $style .= 'color:' . esc_attr( $icon_color ) . ';';
            try {
                $icon_html = \Bricks\Element::render_icon( $icon, [
                    'class' => [ 'vergleich-list__icon' ],
                    'style' => $style,
                ] );
            } catch ( \Throwable $e ) {
                $icon_html = '';
            }
        }

        $justify_map = [ 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' ];
        $justify     = $justify_map[ $align ] ?? 'flex-start';

        $ul_style = 'display:flex;flex-direction:column;gap:' . esc_attr( $item_gap ) . ';list-style:none;padding:0;margin:0;align-items:stretch;width:100%;';
        $li_style = 'display:flex;align-items:flex-start;gap:' . esc_attr( $icon_gap ) . ';justify-content:' . esc_attr( $justify ) . ';text-align:left;';

        $html = '<ul class="vergleich-list" style="' . esc_attr( $ul_style ) . '">';
        foreach ( $items as $item ) {
            $html .= '<li class="vergleich-list__item" style="' . esc_attr( $li_style ) . '">';
            if ( $icon_html !== '' ) $html .= $icon_html;
            $html .= '<span class="vergleich-list__text" style="min-width:0;flex:1 1 auto;">' . wp_kses_post( $item ) . '</span>';
            $html .= '</li>';
        }
        $html .= '</ul>';
        return $html;
    }

    private function render_cell_manual( $row ) {
        $columns = isset( $row['manualColumns'] ) && is_array( $row['manualColumns'] )
            ? array_values( array_filter( $row['manualColumns'], 'is_array' ) )
            : [];

        // Loop-Index ermitteln
        $loop_idx = 0;
        if ( class_exists( '\Bricks\Query' ) && method_exists( '\Bricks\Query', 'get_loop_index' ) ) {
            $li = \Bricks\Query::get_loop_index();
            if ( $li !== '' && $li !== null ) $loop_idx = (int) $li;
        }

        $entry = $columns[ $loop_idx ] ?? null;
        $text  = ( is_array( $entry ) && isset( $entry['text'] ) ) ? (string) $entry['text'] : '';
        $tag   = 'p';
        if ( is_array( $entry ) && ! empty( $entry['tag'] ) ) {
            $tag = preg_replace( '/[^a-z0-9]/i', '', (string) $entry['tag'] ) ?: 'p';
        }

        $resolved = $text === '' ? '' : $this->dd_string( $text );

        // Fallback wenn leer/kein Eintrag
        if ( trim( strip_tags( $resolved ) ) === '' ) {
            $fb_raw = isset( $row['manualFallback'] ) ? (string) $row['manualFallback'] : '';
            if ( $fb_raw === '' ) return '';
            $resolved = $this->dd_string( $fb_raw );
            if ( trim( strip_tags( $resolved ) ) === '' ) return '';
        }

        return '<' . $tag . ' class="vergleich-manual">' . wp_kses_post( $resolved ) . '</' . $tag . '>';
    }

    private function render_cell_html( $row ) {
        $raw = isset( $row['html'] ) ? (string) $row['html'] : '';
        if ( $raw === '' ) return '';
        $resolved = $this->dd_string( $raw );
        return do_shortcode( $resolved );
    }

    private function render_cell_dynamic( $row ) {
        $raw = isset( $row['dynamic'] ) ? (string) $row['dynamic'] : '';
        if ( $raw === '' ) return '';
        $resolved = $this->dd_string( $raw );

        if ( trim( strip_tags( $resolved ) ) === '' ) {
            $fb_raw = isset( $row['dynamicFallback'] ) ? (string) $row['dynamicFallback'] : '';
            if ( $fb_raw === '' ) return '';
            $resolved = $this->dd_string( $fb_raw );
            if ( trim( strip_tags( $resolved ) ) === '' ) return '';
        }

        return '<span class="vergleich-dynamic">' . wp_kses_post( $resolved ) . '</span>';
    }

    // ==========================================================================
    // HELPERS
    // ==========================================================================

    /**
     * Löst ein Bricks-Link-Control (alle Typen: internal/external/meta/taxonomy/media)
     * in eine URL + Attribute auf. Gibt ['url','target','rel','aria','title'] zurück;
     * url ist leer, wenn kein gültiger Link konfiguriert ist.
     */
    private function resolve_link( $link ) {
        $out = [ 'url' => '', 'target' => '', 'rel' => '', 'aria' => '', 'title' => '' ];
        if ( empty( $link ) ) return $out;

        if ( is_string( $link ) ) {
            $out['url'] = $this->dd_string( $link );
            return $out;
        }
        if ( ! is_array( $link ) ) return $out;

        $type = $link['type'] ?? '';
        if ( $type === 'internal' && ! empty( $link['postId'] ) ) {
            $out['url'] = (string) ( get_permalink( (int) $link['postId'] ) ?: '' );
            if ( $out['url'] !== '' && ! empty( $link['urlParams'] ) ) {
                $out['url'] .= $this->dd_string( (string) $link['urlParams'] );
            }
        } elseif ( $type === 'external' && ! empty( $link['url'] ) ) {
            $out['url'] = $this->dd_string( (string) $link['url'] );
        } elseif ( $type === 'meta' && ! empty( $link['useDynamicData'] ) ) {
            $dd_tag = is_array( $link['useDynamicData'] )
                ? (string) ( $link['useDynamicData']['name'] ?? '' )
                : (string) $link['useDynamicData'];
            $out['url'] = $this->dd_string( $dd_tag );
        } elseif ( $type === 'taxonomy' && ! empty( $link['term'] ) ) {
            $parts = explode( '::', (string) $link['term'] );
            if ( count( $parts ) === 2 ) {
                $term = get_term( (int) $parts[1], $parts[0] );
                if ( $term && ! is_wp_error( $term ) ) {
                    $term_link = get_term_link( $term );
                    if ( ! is_wp_error( $term_link ) ) $out['url'] = (string) $term_link;
                }
            }
        } elseif ( $type === 'media' && ! empty( $link['mediaData']['id'] ) ) {
            $out['url'] = (string) ( wp_get_attachment_url( (int) $link['mediaData']['id'] ) ?: '' );
        } elseif ( ! empty( $link['url'] ) ) {
            // Fallback für ältere Datenstrukturen
            $out['url'] = $this->dd_string( (string) $link['url'] );
        }

        if ( ! empty( $link['newTab'] ) )    $out['target'] = '_blank';
        if ( ! empty( $link['rel'] ) )       $out['rel'] = $this->dd_string( (string) $link['rel'] );
        if ( ! empty( $link['nofollow'] ) )  $out['rel'] = trim( $out['rel'] . ' nofollow' );
        if ( ! empty( $link['ariaLabel'] ) ) $out['aria'] = $this->dd_string( (string) $link['ariaLabel'] );
        if ( ! empty( $link['title'] ) )     $out['title'] = $this->dd_string( (string) $link['title'] );

        return $out;
    }

    /**
     * Wickelt gerenderten Inhalt in einen <a>-Tag, wenn der Link gültig ist.
     * Sonst wird der Inhalt unverändert zurückgegeben.
     */
    private function maybe_wrap_link( $content, $link_data, $extra_class = '' ) {
        if ( empty( $link_data['url'] ) ) return $content;
        $attrs = 'href="' . esc_url( $link_data['url'] ) . '"';
        if ( $extra_class !== '' )            $attrs .= ' class="' . esc_attr( $extra_class ) . '"';
        if ( ! empty( $link_data['target'] ) ) $attrs .= ' target="' . esc_attr( $link_data['target'] ) . '"';
        if ( ! empty( $link_data['rel'] ) )    $attrs .= ' rel="' . esc_attr( trim( $link_data['rel'] ) ) . '"';
        if ( ! empty( $link_data['aria'] ) )   $attrs .= ' aria-label="' . esc_attr( $link_data['aria'] ) . '"';
        if ( ! empty( $link_data['title'] ) )  $attrs .= ' title="' . esc_attr( $link_data['title'] ) . '"';
        return '<a ' . $attrs . '>' . $content . '</a>';
    }

    private function get_rows() {
        // Wenn der Repeater gar nicht in den Settings existiert (= Alt-Element
        // ohne Migration oder Frisch-Drop vor erstem Save), auf unsere Defaults
        // zurückfallen, damit das Element nicht komplett leer im Canvas steht.
        $has_explicit = is_array( $this->settings ?? null ) && array_key_exists( 'rows', $this->settings );
        $rows = $has_explicit ? ( $this->settings['rows'] ?? [] ) : $this->default_rows();
        if ( ! is_array( $rows ) ) $rows = [];
        return array_values( array_filter( $rows, 'is_array' ) );
    }

    private function default_rows() {
        return [
            [
                'label' => esc_html__( 'Bild', 'bricks-vergleich' ),
                'type'  => 'image',
                'image' => [ 'useDynamicData' => '{featured_image}', 'size' => 'medium' ],
            ],
            [
                'label'   => esc_html__( 'Name', 'bricks-vergleich' ),
                'type'    => 'text',
                'text'    => '{post_title}',
                'textTag' => 'p',
            ],
            [
                'label'   => esc_html__( 'Preis', 'bricks-vergleich' ),
                'type'    => 'text',
                'text'    => '{woo_product_price}',
                'textTag' => 'p',
            ],
        ];
    }

    /** Eindeutiger Key pro Zeile (zur Verknüpfung Label ↔ Zelle im Collapse-Feature). */
    private function row_key( $row, $idx ) {
        if ( ! empty( $row['_id'] ) ) return (string) $row['_id'];
        if ( ! empty( $row['id'] ) )  return (string) $row['id'];
        return 'row-' . (int) $idx;
    }

    private function resolve_color( $color ) {
        if ( is_string( $color ) ) return $this->sanitize_css_value( $color );
        if ( is_array( $color ) ) {
            foreach ( [ 'rgb', 'hsl', 'hex', 'raw' ] as $k ) {
                if ( ! empty( $color[ $k ] ) ) return $this->sanitize_css_value( $color[ $k ] );
            }
        }
        return '';
    }

    private function sanitize_css_value( $value ) {
        if ( ! is_string( $value ) ) return '';
        return preg_replace( '/[^a-zA-Z0-9\-_\s.%,#()]/', '', $value );
    }

    private function format_spacing( $value, $default ) {
        if ( ! is_array( $value ) || empty( array_filter( $value, function ( $v ) { return $v !== '' && $v !== null; } ) ) ) {
            return $default;
        }
        $sides = [];
        foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
            $raw = $value[ $side ] ?? '';
            $sides[] = ( $raw === '' || $raw === null ) ? '0' : $this->format_length( $raw );
        }
        return implode( ' ', $sides );
    }

    // Bricks-Typography-Struktur → Inline-CSS-Fragment. Für Stellen, an denen
    // Typografie inline gebraucht wird (z.B. Repeater-Badges). Für Element-
    // Level-Controls reicht 'css' => [[ 'property' => 'typography', ... ]] —
    // Bricks erzeugt das CSS dann selbst.
    private function format_typography( $typo, $defaults = [] ) {
        $props = [
            'color', 'font-family', 'font-weight', 'font-style',
            'font-variation-settings', 'line-height', 'text-align',
            'text-transform', 'text-decoration', 'white-space',
        ];
        $length_props = [ 'font-size', 'letter-spacing' ];

        $out = '';
        if ( is_array( $typo ) ) {
            foreach ( $props as $p ) {
                if ( ! isset( $typo[ $p ] ) || $typo[ $p ] === '' || $typo[ $p ] === null ) continue;
                $val = $typo[ $p ];
                if ( $p === 'color' ) {
                    $resolved = $this->resolve_color( $val );
                    if ( $resolved !== '' ) $out .= 'color:' . esc_attr( $resolved ) . ';';
                    continue;
                }
                if ( is_array( $val ) ) continue; // unerwartet — skip
                $out .= $p . ':' . esc_attr( $this->sanitize_css_value( (string) $val ) ) . ';';
            }
            foreach ( $length_props as $p ) {
                if ( ! isset( $typo[ $p ] ) ) continue;
                $v = $typo[ $p ];
                if ( $v === '' || $v === null ) continue;
                $formatted = $this->format_length( $v );
                if ( $formatted !== '' && $formatted !== '0' ) {
                    $out .= $p . ':' . esc_attr( $formatted ) . ';';
                }
            }
        }
        foreach ( [ 'color', 'font-size', 'font-weight', 'line-height' ] as $p ) {
            if ( ! empty( $defaults[ $p ] ) && strpos( $out, $p . ':' ) === false ) {
                $out .= $p . ':' . esc_attr( (string) $defaults[ $p ] ) . ';';
            }
        }
        return $out;
    }

    // Bricks-Border-Struktur → Inline-CSS-Fragment. Defaults greifen nur, wenn
    // der User KEINEN Wert gesetzt hat (z.B. Badge-Default radius: 9999px).
    private function format_border( $border, $defaults = [] ) {
        $width_css = $style_css = $color_css = $radius_css = '';
        if ( is_array( $border ) ) {
            if ( ! empty( $border['width'] ) && is_array( $border['width'] ) ) {
                $sides = [];
                $any = false;
                foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
                    $v = $border['width'][ $side ] ?? '';
                    if ( $v === '' || $v === null ) { $sides[] = '0'; continue; }
                    $sides[] = $this->format_length( $v );
                    $any = true;
                }
                if ( $any ) $width_css = implode( ' ', $sides );
            }
            if ( ! empty( $border['style'] ) && is_string( $border['style'] ) ) {
                $style_css = $this->sanitize_css_value( $border['style'] );
            }
            if ( ! empty( $border['color'] ) ) {
                $color_css = $this->resolve_color( $border['color'] );
            }
            if ( ! empty( $border['radius'] ) && is_array( $border['radius'] ) ) {
                $keys = isset( $border['radius']['top-left'] ) || isset( $border['radius']['top-right'] )
                    ? [ 'top-left', 'top-right', 'bottom-right', 'bottom-left' ]
                    : [ 'top', 'right', 'bottom', 'left' ];
                $corners = [];
                $any = false;
                foreach ( $keys as $k ) {
                    $v = $border['radius'][ $k ] ?? '';
                    if ( $v === '' || $v === null ) { $corners[] = '0'; continue; }
                    $corners[] = $this->format_length( $v );
                    $any = true;
                }
                if ( $any ) $radius_css = implode( ' ', $corners );
            }
        }
        if ( $radius_css === '' && ! empty( $defaults['radius'] ) ) $radius_css = (string) $defaults['radius'];

        $out = '';
        if ( $width_css !== '' ) $out .= 'border-width:' . esc_attr( $width_css ) . ';';
        if ( $style_css !== '' ) $out .= 'border-style:' . esc_attr( $style_css ) . ';';
        if ( $color_css !== '' ) $out .= 'border-color:' . esc_attr( $color_css ) . ';';
        if ( $radius_css !== '' ) $out .= 'border-radius:' . esc_attr( $radius_css ) . ';';
        return $out;
    }

    private function format_length( $value ) {
        if ( is_array( $value ) ) {
            $n = $value['number'] ?? ( $value[0] ?? '' );
            $u = $value['unit']   ?? ( $value[1] ?? 'px' );
            if ( $n === '' || $n === null ) return '0';
            return $this->sanitize_css_value( (string) $n . $u );
        }
        if ( is_numeric( $value ) ) return $value . 'px';
        if ( is_string( $value ) ) {
            if ( preg_match( '/^[0-9.]+$/', $value ) ) return $value . 'px';
            return $this->sanitize_css_value( $value );
        }
        return '0';
    }

    private function get_css_value( $value, $default ) {
        if ( is_array( $value ) ) {
            $n = array_key_exists( 'number', $value ) ? $value['number'] : ( $value[0] ?? null );
            $u = array_key_exists( 'unit',   $value ) ? $value['unit']   : ( $value[1] ?? 'px' );
            if ( $n === '' || $n === null ) return $default;
            if ( $u === '' || $u === null ) $u = 'px';
            return $n . $u;
        }
        if ( empty( $value ) && $value !== 0 && $value !== '0' ) return $default;
        if ( is_numeric( $value ) ) return $value . 'px';
        if ( is_string( $value ) ) {
            if ( preg_match( '/^[0-9.]+$/', $value ) ) return $value . 'px';
            return $value;
        }
        return $default;
    }

    // ==========================================================================
    // INLINE CSS + SYNC-SCRIPT
    // ==========================================================================

    private function get_inline_css() {
        return '<style id="bricks-vergleich-inline-css">
        .vergleich-wrapper {
            display: grid !important;
            /* Scroll-Spalte: minmax(0, auto) = darf auf 0 schrumpfen (für
               horizontales Scrolling bei Überlauf), wächst aber nur bis zur
               Track-Breite — kein Leerraum rechts neben den Cards. */
            grid-template-columns: var(--vgl-label-width, 200px) minmax(0, auto) !important;
            grid-template-rows: repeat(var(--vgl-row-count, 3), minmax(var(--vgl-row-min, 20px), auto)) !important;
            align-content: start !important;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
            position: relative;
            max-width: 100%;
        }
        .vergleich-labels {
            display: grid !important;
            grid-column: 1 !important;
            grid-row: 1 / -1 !important;
            grid-template-rows: subgrid !important;
            background: #f3f4f6;
            z-index: 3;
        }
        .vergleich-wrapper.has-sticky-labels .vergleich-labels {
            position: sticky;
            left: 0;
        }
        .vergleich-label {
            padding: var(--vgl-cell-padding, 16px);
            font-weight: 600;
            color: #111;
            display: flex;
            align-items: center;
            gap: 6px;
            line-height: 1.3;
            min-width: 0;
            min-height: var(--vgl-row-min, 20px);
            box-sizing: border-box;
        }
        .vergleich-label__text { min-width: 0; }
        /* Info-Icon-Button + Tooltip via ::after (content: attr()). Erscheint
           rechts vom Icon, schmal, mit Umbruch. Uebersteht auch laengere Texte. */
        .vergleich-tooltip {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.15em;
            height: 1.15em;
            padding: 0;
            margin: 0;
            border: 0;
            background: transparent;
            color: currentColor;
            opacity: .55;
            cursor: help;
            font-size: .95em;
            line-height: 1;
            position: relative;
            transition: opacity .12s ease;
        }
        .vergleich-tooltip:hover,
        .vergleich-tooltip:focus-visible { opacity: 1; outline: none; }
        .vergleich-tooltip svg { width: 1em; height: 1em; display: block; }
        .vergleich-tooltip::after {
            content: attr(data-tooltip);
            position: absolute;
            left: calc(100% + 10px);
            top: 50%;
            transform: translateY(-50%);
            min-width: 180px;
            max-width: 280px;
            padding: 8px 12px;
            background: #111827;
            color: #fff;
            font-size: 13px;
            font-weight: 400;
            line-height: 1.4;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,.18);
            white-space: normal;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            z-index: 20;
            transition: opacity .12s ease;
        }
        .vergleich-tooltip:hover::after,
        .vergleich-tooltip:focus-visible::after {
            opacity: 1;
            visibility: visible;
        }
        .vergleich-wrapper.has-dividers .vergleich-label { border-bottom: 1px solid #e5e7eb; }
        .vergleich-wrapper.has-dividers .vergleich-label:last-child { border-bottom: none; }
        .vergleich-label.is-highlighted { background: #fef3c7; color: #92400e; }
        /* Schatten-Hervorhebung: bandartiger Schatten an Ober- und Unterkante
           jeder Zelle der Reihe. Liegt per z-index ueber angrenzenden Zeilen
           und ueberdeckt die Divider visuell an dieser Stelle. */
        .vergleich-label.is-highlight-shadow,
        .vergleich-zelle.is-highlight-shadow {
            position: relative;
            z-index: 2;
            box-shadow: 0 3px 10px -2px rgba(0,0,0,.09), 0 -3px 10px -2px rgba(0,0,0,.09);
        }
        .vergleich-wrapper.has-dividers .vergleich-label.is-highlight-shadow,
        .vergleich-wrapper.has-dividers .vergleich-zelle.is-highlight-shadow {
            border-top: 0;
            border-bottom: 0;
        }

        .vergleich-scroll {
            grid-column: 2 !important;
            grid-row: 1 / -1 !important;
            overflow-x: auto;
            overflow-y: hidden; /* Nur horizontal scrollen — verhindert das vertikale Drift im Canvas */
            min-width: 0;
            display: grid !important;
            grid-template-rows: subgrid !important;
            grid-template-columns: max-content !important;
        }
        .vergleich-track {
            display: grid !important;
            grid-auto-flow: column !important;
            grid-auto-columns: var(--vgl-column-width, 200px) !important;
            grid-template-rows: subgrid !important;
            grid-row: 1 / -1 !important;
            min-width: 0;
        }
        .vergleich-card {
            display: grid !important;
            grid-template-rows: subgrid !important;
            grid-row: 1 / -1 !important;
            border-left: 1px solid #e5e7eb;
            background: #fff;
            min-width: 0;
            max-width: var(--vgl-column-width, 200px);
            width: var(--vgl-column-width, 200px);
            overflow: hidden;
            box-sizing: border-box;
            position: relative !important;
        }
        .vergleich-card:first-child { border-left: none; }

        .vergleich-zelle {
            padding: var(--vgl-cell-padding, 16px);
            display: flex !important;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 8px;
            line-height: 1.4;
            min-height: var(--vgl-row-min, 20px);
            min-width: 0;
            max-width: 100%;
            box-sizing: border-box;
            overflow: hidden;
        }
        .vergleich-wrapper.has-dividers .vergleich-zelle { border-bottom: 1px solid #e5e7eb; }
        .vergleich-wrapper.has-dividers .vergleich-zelle:last-child { border-bottom: none; }
        .vergleich-zelle.is-highlighted { background: #fef3c7; }
        .vergleich-zelle > * { margin: 0 !important; max-width: 100%; min-width: 0; }

        /* Bilder */
        .vergleich-zelle img {
            max-width: 100% !important;
            height: auto !important;
            object-fit: contain;
        }
        .vergleich-wrapper.has-enforced-images .vergleich-zelle--image .vergleich-image,
        .vergleich-wrapper.has-enforced-images .vergleich-zelle--image .vergleich-image-placeholder {
            width: var(--vgl-img-width, 100px) !important;
            height: var(--vgl-img-height, 100px) !important;
            max-width: 100% !important;
            object-fit: var(--vgl-img-fit, cover) !important;
            display: block;
        }
        .vergleich-image-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            background: #f3f4f6;
            border-radius: 8px;
        }

        /* Rating: Custom-Icons → Wrapper bestimmt die Größe, SVG/Icon füllt 100%.
           SVG-Icons bringen oft native width/height-Attribute mit (z.B. 512×512),
           die sonst den font-size-Wrap ignorieren würden. Farben bleiben bewusst
           unangetastet, damit mehrfarbige SVGs (z.B. halb-gefüllte Sterne) ihre
           Original-Farbverläufe behalten. */
        .vergleich-rating__icon {
            overflow: hidden;
        }
        .vergleich-rating__icon > svg {
            width: 100% !important;
            height: 100% !important;
            display: block;
        }
        .vergleich-rating__icon > i,
        .vergleich-rating__icon > .bricks-icon {
            font-size: inherit !important;
            line-height: 1 !important;
        }

        /* Listen-Icon: SVG-Icons (Custom Upload) haben native Groessen und
           ignorieren font-size. Wrapper hat feste width/height (inline), das
           innere <svg> wird hier auf 100% gezogen. Fuer Font-Icons (FA etc.)
           greift stattdessen font-size; Groesse kommt per inherit. */
        .vergleich-list__icon {
            overflow: hidden;
        }
        .vergleich-list__icon > svg {
            width: 100% !important;
            height: 100% !important;
            display: block !important;
            max-width: 100% !important;
            max-height: 100% !important;
        }
        .vergleich-list__icon > i,
        .vergleich-list__icon > .bricks-icon {
            font-size: inherit !important;
            line-height: 1 !important;
        }

        /* Ranking-Badge */
        .vergleich-card > .vergleich-rank,
        .vergleich-rank {
            position: absolute !important;
            top: var(--vgl-rank-offset-y, 8px);
            left: var(--vgl-rank-offset-x, 8px);
            z-index: 5;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            gap: 2px;
            box-sizing: border-box;
            min-width: var(--vgl-rank-size, 36px);
            min-height: var(--vgl-rank-size, 36px);
            padding: var(--vgl-rank-padding, 4px 10px);
            font-size: 14px;
            font-weight: 700;
            line-height: 1;
            color: #fff;
            background: var(--vgl-rank-bg, #f59e0b);
            border-radius: 9999px;
            box-shadow: 0 2px 6px rgba(0,0,0,.18);
            pointer-events: none;
            white-space: nowrap;
            grid-row: auto !important;
            grid-column: auto !important;
            margin: 0 !important;
        }
        .vergleich-wrapper.has-ranking-pos-top-left .vergleich-card > .vergleich-rank {
            top: var(--vgl-rank-offset-y, 8px) !important;
            right: auto !important;
            left: var(--vgl-rank-offset-x, 8px) !important;
            bottom: auto !important;
            transform: none !important;
        }
        .vergleich-wrapper.has-ranking-pos-top-right .vergleich-card > .vergleich-rank {
            top: var(--vgl-rank-offset-y, 8px) !important;
            left: auto !important;
            right: var(--vgl-rank-offset-x, 8px) !important;
            bottom: auto !important;
            transform: none !important;
        }
        .vergleich-wrapper.has-ranking-pos-top-center .vergleich-card > .vergleich-rank {
            top: var(--vgl-rank-offset-y, 8px) !important;
            left: 50% !important;
            right: auto !important;
            bottom: auto !important;
            transform: translateX(-50%) !important;
        }
        .vergleich-wrapper.has-ranking-pos-bottom-left .vergleich-card > .vergleich-rank {
            top: auto !important;
            right: auto !important;
            left: var(--vgl-rank-offset-x, 8px) !important;
            bottom: var(--vgl-rank-offset-y, 8px) !important;
            transform: none !important;
        }
        .vergleich-wrapper.has-ranking-pos-bottom-right .vergleich-card > .vergleich-rank {
            top: auto !important;
            left: auto !important;
            right: var(--vgl-rank-offset-x, 8px) !important;
            bottom: var(--vgl-rank-offset-y, 8px) !important;
            transform: none !important;
        }
        .vergleich-card.is-rank-top > .vergleich-rank {
            background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%) !important;
            color: #fff !important;
        }

        /* Score / Bewertungs-Badge
           Das Badge wird als Kind der Bild-Zelle gerendert (siehe render_cell).
           Die Zelle bekommt dann die Klasse .has-score-anchor und position:
           relative — so klebt das absolute Badge an DIESER Zellen-Box statt
           an der ganzen Card. */
        .vergleich-zelle.has-score-anchor {
            position: relative !important;
        }
        .vergleich-zelle.has-score-anchor > .vergleich-score,
        .vergleich-score {
            position: absolute !important;
            bottom: var(--vgl-score-offset-y, 8px);
            left: var(--vgl-score-offset-x, 8px);
            z-index: 5;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            gap: 2px;
            box-sizing: border-box;
            min-width: var(--vgl-score-size, 36px);
            min-height: var(--vgl-score-size, 36px);
            padding: var(--vgl-score-padding, 6px 10px);
            font-size: 14px;
            font-weight: 700;
            line-height: 1;
            color: #fff;
            background: var(--vgl-score-bg, #111827);
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0,0,0,.18);
            pointer-events: none;
            white-space: nowrap;
            margin: 0 !important;
            flex: 0 0 auto !important;
        }
        .vergleich-wrapper.has-score-pos-top-left .vergleich-zelle.has-score-anchor > .vergleich-score {
            top: var(--vgl-score-offset-y, 8px) !important;
            right: auto !important;
            left: var(--vgl-score-offset-x, 8px) !important;
            bottom: auto !important;
            transform: none !important;
        }
        .vergleich-wrapper.has-score-pos-top-right .vergleich-zelle.has-score-anchor > .vergleich-score {
            top: var(--vgl-score-offset-y, 8px) !important;
            left: auto !important;
            right: var(--vgl-score-offset-x, 8px) !important;
            bottom: auto !important;
            transform: none !important;
        }
        .vergleich-wrapper.has-score-pos-top-center .vergleich-zelle.has-score-anchor > .vergleich-score {
            top: var(--vgl-score-offset-y, 8px) !important;
            left: 50% !important;
            right: auto !important;
            bottom: auto !important;
            transform: translateX(-50%) !important;
        }
        .vergleich-wrapper.has-score-pos-bottom-left .vergleich-zelle.has-score-anchor > .vergleich-score {
            top: auto !important;
            right: auto !important;
            left: var(--vgl-score-offset-x, 8px) !important;
            bottom: var(--vgl-score-offset-y, 8px) !important;
            transform: none !important;
        }
        .vergleich-wrapper.has-score-pos-bottom-center .vergleich-zelle.has-score-anchor > .vergleich-score {
            top: auto !important;
            left: 50% !important;
            right: auto !important;
            bottom: var(--vgl-score-offset-y, 8px) !important;
            transform: translateX(-50%) !important;
        }
        .vergleich-wrapper.has-score-pos-bottom-right .vergleich-zelle.has-score-anchor > .vergleich-score {
            top: auto !important;
            left: auto !important;
            right: var(--vgl-score-offset-x, 8px) !important;
            bottom: var(--vgl-score-offset-y, 8px) !important;
            transform: none !important;
        }
        .vergleich-score__prefix,
        .vergleich-score__suffix {
            opacity: .85;
        }

        /* === Navigations-Pfeile === */
        .vergleich-nav {
            position: absolute !important;
            top: 50% !important;
            transform: translateY(-50%);
            z-index: 8;
            width: var(--vgl-nav-size, 44px);
            height: var(--vgl-nav-size, 44px);
            padding: 0;
            margin: 0;
            border: 1px solid #e5e7eb;
            background: var(--vgl-nav-bg, #ffffff);
            color: var(--vgl-nav-color, #111827);
            border-radius: 9999px;
            box-shadow: 0 4px 12px rgba(0,0,0,.15);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: var(--vgl-nav-icon-size, 18px);
            line-height: 1;
            transition: opacity .15s ease, transform .15s ease, background-color .15s ease;
            opacity: 1;
        }
        .vergleich-nav:hover {
            transform: translateY(-50%) scale(1.05);
            background: var(--vgl-nav-bg, #ffffff);
        }
        .vergleich-nav:focus-visible {
            outline: 2px solid var(--vgl-nav-color, #111827);
            outline-offset: 2px;
        }
        .vergleich-nav[hidden] {
            display: none !important;
        }
        .vergleich-nav svg {
            width: var(--vgl-nav-icon-size, 18px);
            height: var(--vgl-nav-icon-size, 18px);
            display: block;
        }
        .vergleich-nav--prev {
            left: calc(var(--vgl-label-width, 200px) + var(--vgl-nav-offset, 12px));
        }
        .vergleich-nav--next {
            right: var(--vgl-nav-offset, 12px);
        }
        /* Zebra-Streifen: nur auf den Card-Zellen, NICHT auf der Label-
           Spalte — die behaelt ihre eigene Hintergrundfarbe. */
        .vergleich-zelle.is-row-odd  { background-color: var(--vgl-row-bg-odd,  transparent); }
        .vergleich-zelle.is-row-even { background-color: var(--vgl-row-bg-even, transparent); }
        .vergleich-zelle.is-row-odd  { color: var(--vgl-row-color-odd,  inherit); }
        .vergleich-zelle.is-row-even { color: var(--vgl-row-color-even, inherit); }

        /* Zeilen-Hover: bei is-row-hover auf Label + allen Zellen derselben
           Zeile (per JS geflaggt) leicht einfaerben. Respektiert bestehende
           Hintergruende nicht vollstaendig — ueberschreibt nur ohne explizite
           Hervorhebungs-Farbe. */
        .vergleich-wrapper.has-row-hover .vergleich-label.is-row-hover,
        .vergleich-wrapper.has-row-hover .vergleich-zelle.is-row-hover {
            background-color: var(--vgl-row-hover-bg, rgba(0,0,0,.04));
            transition: background-color .12s ease;
        }

        /* Counter-Element (z.B. "1–4 von 80") */
        .vergleich-counter {
            font-size: 0.875rem;
            color: #6b7280;
            padding: 8px 4px;
            min-height: 1.75em;
        }
        .vergleich-counter.is-align-left   { text-align: left; }
        .vergleich-counter.is-align-center { text-align: center; }
        .vergleich-counter.is-align-right  { text-align: right; }

        /* Wenn Nav-Pfeile aktiv sind, Scrollbar ausblenden — Scrollen bleibt
           per Pfeil-Click, Wheel und Touch-Swipe moeglich. */
        .vergleich-wrapper.has-nav .vergleich-scroll {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .vergleich-wrapper.has-nav .vergleich-scroll::-webkit-scrollbar {
            display: none;
            width: 0;
            height: 0;
        }

        /* Root-Container: nimmt Wrapper + Expand-Button auf */
        .vergleich-root {
            display: block;
        }

        /* Expand / Collapse: einfacher display:none-Toggle ohne Animation. */
        .vergleich-wrapper.has-expand.is-collapsed {
            /* Im eingeklappten Zustand das Grid auf die Anzahl SICHTBARER Rows
               reduzieren, sonst bleiben die display:none-Items als leere Slots
               sichtbar. --vgl-row-count-collapsed wird in render() berechnet. */
            grid-template-rows: repeat(var(--vgl-row-count-collapsed, 1), minmax(var(--vgl-row-min, 20px), auto)) !important;
        }
        .vergleich-wrapper.has-expand.is-collapsed .vergleich-label.is-collapsible,
        .vergleich-wrapper.has-expand.is-collapsed .vergleich-zelle.is-collapsible {
            display: none !important;
        }
        /* Expand-Button sitzt außerhalb des Wrappers (unter dem Table, nicht drin). */
        .vergleich-root > .vergleich-expand,
        .vergleich-expand {
            display: flex !important;
            align-items: center !important;
            box-sizing: border-box !important;
            width: 100% !important;
            padding: 16px 0 0 0 !important;
            background: transparent !important;
            border: 0 !important;
            margin: 0 !important;
        }
        .vergleich-expand.is-align-left   { justify-content: flex-start !important; }
        .vergleich-expand.is-align-center { justify-content: center !important; }
        .vergleich-expand.is-align-right  { justify-content: flex-end !important; }

        /* === Fade-Collapse-Modus ================================================
           Alternative zum harten display:none-Toggle. Die erste aufklappbare
           Zeile (is-peek) wird teilweise sichtbar gelassen und per Gradient
           nach unten ausgeblendet. Der Aufklappen-Button rutscht durch
           negative Margin in den Fade-Bereich hinein und wirkt so als Teil
           des Teasers. Expandiert: alles normal, kein Gradient, kein Peek.
        */
        .vergleich-wrapper.has-expand-fade.is-collapsed {
            /* Volle Zeilenanzahl beibehalten, nicht auf --vgl-row-count-collapsed
               kuerzen — die Peek-Zeile bleibt Teil des Subgrids. */
            grid-template-rows: repeat(var(--vgl-row-count, 3), minmax(var(--vgl-row-min, 20px), auto)) !important;
            position: relative;
            overflow: hidden;
        }
        /* Alle Nicht-Peek-Collapsibles bleiben versteckt. */
        .vergleich-wrapper.has-expand-fade.is-collapsed .vergleich-label.is-collapsible:not(.is-peek),
        .vergleich-wrapper.has-expand-fade.is-collapsed .vergleich-zelle.is-collapsible:not(.is-peek) {
            display: none !important;
        }
        /* Die Peek-Zeile wird angezeigt, aber in der Hoehe beschnitten. */
        .vergleich-wrapper.has-expand-fade.is-collapsed .vergleich-label.is-collapsible.is-peek,
        .vergleich-wrapper.has-expand-fade.is-collapsed .vergleich-zelle.is-collapsible.is-peek {
            display: flex !important;
            max-height: var(--vgl-fade-peek, 48px) !important;
            min-height: 0 !important;
            overflow: hidden !important;
            /* Inhalt nach oben clippen, damit nur der Kopf der Zeile sichtbar ist. */
            align-items: flex-start !important;
        }
        /* Die Grid-Zeile selbst soll sich ebenfalls zusammenziehen: Subgrid
           passt sich der tatsaechlichen Zellenhoehe an, weil wir in der
           JS-Sync keine min-height auf Peek-Rows zwingen (s. sync_script). */
        .vergleich-wrapper.has-expand-fade.is-collapsed .is-peek {
            grid-row-start: auto;
        }
        /* Gradient am unteren Rand. Sitzt ueber Labels + Scroll und verdeckt
           Peek-Zeile + den Uebergang zum Button. Keine Interaktion (pointer-events:none). */
        .vergleich-wrapper.has-expand-fade.is-collapsed::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: var(--vgl-fade-height, 140px);
            background: linear-gradient(
                to bottom,
                rgba(255, 255, 255, 0) 0%,
                var(--vgl-fade-color, #ffffff) 85%
            );
            pointer-events: none;
            z-index: 6;
        }
        /* Button ueberlappt den Fade-Bereich: negatives margin-top zieht ihn
           in den Wrapper hinein. Nur im kollabierten Fade-Modus. */
        .vergleich-root.has-expand-fade.has-expand .vergleich-expand {
            margin-top: calc(-1 * var(--vgl-fade-btn-overlap, 40px)) !important;
            position: relative;
            z-index: 10;
        }
        /* Wenn ausgeklappt, verschwindet der Overlap automatisch, weil die
           Wrapper-Klasse has-expand-fade bleibt, aber is-collapsed wegfaellt:
           Gradient + Peek werden durch das CSS oben nur im is-collapsed-Zustand
           aktiviert. Der Button-Margin bleibt bestehen, ist dann aber visuell
           minimal (Wrapper ragt nicht mehr in den Buttonbereich). Falls ge-
           wuenscht, hier den Overlap aufheben: */
        .vergleich-root.has-expand-fade.has-expand .vergleich-wrapper:not(.is-collapsed) ~ .vergleich-expand {
            margin-top: 0 !important;
        }

        /* === Produkt-Labels (freie Leiste oberhalb des Tabellen-Wrappers) ===
           Eigenes 2-Spalten-Grid ueber dem Wrapper: links ein Spacer in Breite
           der Label-Spalte, rechts ein horizontal scrollbarer Track dessen
           scrollLeft per JS an .vergleich-scroll gekoppelt ist. Dadurch wirkt
           die Leiste wie ein freischwebender Balken — keine Tabellenrahmen,
           keine Divider. Leere Items sind transparent und dienen nur als
           Platzhalter fuer die Spaltenbreite. */
        .vergleich-product-label-row {
            display: grid !important;
            grid-template-columns: var(--vgl-label-width, 200px) minmax(0, auto) !important;
            /* 1px seitlich: gleicht den 1px-Border des Wrappers aus, damit die
               Label-Spalten pixelgenau ueber den Card-Spalten sitzen. */
            margin: 0 1px var(--vgl-product-label-gap, 6px);
            max-width: 100%;
        }
        .vergleich-product-label-row__spacer {
            background: transparent;
            padding: 0 var(--vgl-cell-padding, 16px);
            display: flex;
            align-items: center;
            font-size: 14px;
            color: #6b7280;
            min-width: 0;
        }
        .vergleich-product-label-row__spacer-text {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .vergleich-product-label-row__scroll {
            overflow-x: hidden;
            overflow-y: hidden;
            min-width: 0;
        }
        .vergleich-product-label-row__track {
            display: grid !important;
            grid-auto-flow: column !important;
            grid-auto-columns: var(--vgl-column-width, 200px) !important;
            min-width: 0;
        }
        .vergleich-product-label-item {
            min-height: var(--vgl-product-label-height, 40px);
            padding: var(--vgl-product-label-padding, 8px 12px);
            font-size: 14px;
            font-weight: 700;
            line-height: 1.2;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            overflow: hidden;
            box-sizing: border-box;
            background: transparent;
        }
        /* Standard-Hintergrund (aus den Controls) nur auf gefuellte Items
           anwenden. Inline-Styles pro Item ueberschreiben den Default. */
        .vergleich-product-label-item:not(.is-empty) {
            background: var(--vgl-product-label-bg, transparent);
        }
        .vergleich-product-label-item.is-empty {
            background: transparent !important;
            pointer-events: none;
        }
        .vergleich-product-label-item__text {
            display: inline-block;
            max-width: 100%;
            white-space: normal;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Breakpoint-Werte kommen ausschliesslich aus den User-Controls
           labelWidth und columnWidth via Bricks reaktiver CSS-Pipeline. */
        </style>';
    }

    private function print_sync_script() {
        static $printed = false;
        if ( $printed ) return;
        $printed = true;
        ?>
<script id="bricks-vergleich-sync">
(function(){
    function syncRows(wrapper){
        if (!wrapper || !wrapper.isConnected) return;
        var labels = wrapper.querySelectorAll(".vergleich-labels .vergleich-label");
        var cards  = wrapper.querySelectorAll(".vergleich-card");
        if (!labels.length || !cards.length) return;

        labels.forEach(function(el){ el.style.minHeight = ""; });
        var cardCells = [];
        cards.forEach(function(card){
            var cells = [];
            card.querySelectorAll(".vergleich-zelle").forEach(function(el){
                if (el.closest(".vergleich-card") === card) cells.push(el);
            });
            cardCells.push(cells);
            cells.forEach(function(el){ el.style.minHeight = ""; });
        });

        for (var i = 0; i < labels.length; i++) {
            var max = labels[i].offsetHeight;
            for (var c = 0; c < cardCells.length; c++) {
                var cell = cardCells[c][i];
                if (cell) max = Math.max(max, cell.offsetHeight);
            }
            labels[i].style.minHeight = max + "px";
            for (var c2 = 0; c2 < cardCells.length; c2++) {
                var cell2 = cardCells[c2][i];
                if (cell2) cell2.style.minHeight = max + "px";
            }
        }
    }

    function bindExpand(wrapper){
        if (!wrapper || wrapper._vglBound) return;
        wrapper._vglBound = true;
        // Expand-Button sitzt als Geschwister der Wrapper innerhalb des Roots.
        var root = wrapper.closest(".vergleich-root") || wrapper.parentNode;
        var btn = root ? root.querySelector(".vergleich-expand-btn") : null;
        if (!btn) return;
        btn.addEventListener("click", function(){
            var expanded = wrapper.classList.toggle("is-collapsed") ? false : true;
            // NB: toggle returns the NEW state; is-collapsed true = collapsed
            var isCollapsed = wrapper.classList.contains("is-collapsed");
            btn.setAttribute("aria-expanded", isCollapsed ? "false" : "true");
            var txt = btn.querySelector(".vergleich-expand-text");
            if (txt) {
                txt.textContent = isCollapsed
                    ? btn.getAttribute("data-label-expand")
                    : btn.getAttribute("data-label-collapse");
            }
            // Pfeil drehen (direkt, keine Transition)
            var iconWrap = btn.querySelector(".vergleich-expand-icon");
            if (iconWrap) iconWrap.style.transform = isCollapsed ? "rotate(0deg)" : "rotate(180deg)";
            // Row-Sync nach dem Toggle
            requestAnimationFrame(function(){ syncRows(wrapper); });
        });
    }

    // Re-queries child nodes bei jedem Call — robust gegen Canvas-Re-Renders,
    // bei denen Bricks die Wrapper-Kinder austauscht und unsere Referenzen
    // verwaist lassen wuerde.
    function findCounter(wrapper){
        // Primaer ueber Wrapper-Attribut data-counter (ID-Ref, sicher auch
        // wenn Bricks die DOM-Struktur im Canvas anders verschachtelt).
        var id = wrapper.getAttribute("data-counter");
        if (id) {
            var byId = document.getElementById(id);
            if (byId) return byId;
        }
        // Fallback: naechster Root-Container
        var root = wrapper.closest(".vergleich-root") || wrapper.parentNode;
        return root ? root.querySelector("[data-vgl-counter]") : null;
    }

    function updateNav(wrapper){
        var scroll = wrapper.querySelector(".vergleich-scroll");
        if (!scroll) return;

        var counter = findCounter(wrapper);
        var prev    = wrapper.querySelector(".vergleich-nav--prev");
        var next    = wrapper.querySelector(".vergleich-nav--next");

        var overflows = scroll.scrollWidth - scroll.clientWidth > 1;
        var atStart   = scroll.scrollLeft <= 1;
        var atEnd     = scroll.scrollLeft >= scroll.scrollWidth - scroll.clientWidth - 1;

        if (prev && next) {
            prev.hidden = !overflows || atStart;
            next.hidden = !overflows || atEnd;
        }

        if (counter) {
            var cards = scroll.querySelectorAll(".vergleich-card");
            var total = cards.length;
            if (total === 0) {
                counter.textContent = "";
            } else {
                var firstCard = cards[0];
                var cardW = firstCard ? firstCard.getBoundingClientRect().width : 0;
                if (!cardW || cardW < 1) cardW = 1;
                var cw = scroll.clientWidth || cardW;
                var visible = Math.max(1, Math.round(cw / cardW));
                var start = Math.floor(scroll.scrollLeft / cardW) + 1;
                if (start < 1) start = 1;
                if (start > total) start = total;
                var end = Math.min(start + visible - 1, total);
                var fmt = counter.getAttribute("data-format") || "{start}–{end} von {total}";
                counter.textContent = fmt
                    .replace("{start}", start)
                    .replace("{end}",   end)
                    .replace("{total}", total);
            }
        }
    }

    function bindNav(wrapper){
        if (wrapper._vglNavBound) { updateNav(wrapper); return; }
        wrapper._vglNavBound = true;

        // Click-Events per Delegation am Wrapper — uebersteht, wenn Bricks
        // die Nav-Buttons im Canvas gegen neue Nodes ersetzt.
        wrapper.addEventListener("click", function(e){
            var btn = e.target && e.target.closest ? e.target.closest("[data-vgl-nav]") : null;
            if (!btn || !wrapper.contains(btn)) return;
            var scroll = wrapper.querySelector(".vergleich-scroll");
            if (!scroll) return;
            var dir = btn.getAttribute("data-vgl-nav");
            var card = scroll.querySelector(".vergleich-card");
            var cardW = card ? card.getBoundingClientRect().width : 200;
            var stepMode = wrapper.classList.contains("vgl-nav-step-view") ? "view" : "card";
            var step = stepMode === "view" ? Math.max(scroll.clientWidth - 20, 100) : cardW;
            scroll.scrollBy({ left: (dir === "prev" ? -step : step), behavior: "smooth" });
        });

        var handler = function(){ updateNav(wrapper); };
        var scroll = wrapper.querySelector(".vergleich-scroll");
        if (scroll) scroll.addEventListener("scroll", handler, { passive: true });
        window.addEventListener("resize", handler);
        if (typeof ResizeObserver !== "undefined") {
            var ro = new ResizeObserver(handler);
            if (scroll) ro.observe(scroll);
            ro.observe(wrapper);
        }
        requestAnimationFrame(handler);
        setTimeout(handler, 250);
        setTimeout(handler, 800);
    }

    // Horizontaler Scroll-Sync: die Produkt-Label-Leiste sitzt ausserhalb
    // des Wrappers. Statt der Leiste selbst Scroll-Verhalten zu geben (was
    // bei overflow:hidden unzuverlaessig ist), verschieben wir den Track per
    // transform: translateX(-scrollLeft) synchron zur Haupt-Scroll-Position.
    function bindLabelRowSync(wrapper){
        if (!wrapper || wrapper._vglLabelRowBound) return;
        var root = wrapper.closest(".vergleich-root") || wrapper.parentNode;
        if (!root) return;
        var track = root.querySelector(".vergleich-product-label-row__track");
        if (!track) return;
        var scroll = wrapper.querySelector(".vergleich-scroll");
        if (!scroll) return;
        wrapper._vglLabelRowBound = true;

        var ticking = false;
        function apply(){
            track.style.transform = "translateX(" + (-scroll.scrollLeft) + "px)";
        }
        scroll.addEventListener("scroll", function(){
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(function(){
                apply();
                ticking = false;
            });
        }, { passive: true });
        apply(); // initial
    }

    function bindRowHover(wrapper){
        if (wrapper._vglRowHoverBound) return;
        wrapper._vglRowHoverBound = true;

        function rowEls(idx){
            return wrapper.querySelectorAll(
                '[data-row-index="' + idx + '"]'
            );
        }
        function clearActive(){
            var active = wrapper.querySelectorAll(".is-row-hover");
            for (var i = 0; i < active.length; i++) active[i].classList.remove("is-row-hover");
        }
        wrapper.addEventListener("mouseover", function(e){
            if (!wrapper.classList.contains("has-row-hover")) return;
            var el = e.target && e.target.closest ? e.target.closest("[data-row-index]") : null;
            if (!el || !wrapper.contains(el)) return;
            var idx = el.getAttribute("data-row-index");
            if (idx === null) return;
            clearActive();
            var els = rowEls(idx);
            for (var i = 0; i < els.length; i++) els[i].classList.add("is-row-hover");
        });
        wrapper.addEventListener("mouseleave", clearActive);
    }

    function init(wrapper){
        syncRows(wrapper);
        bindExpand(wrapper);
        bindNav(wrapper);
        bindRowHover(wrapper);
        bindLabelRowSync(wrapper);
        var burst = 0;
        (function tick(){
            if (!wrapper.isConnected) return;
            syncRows(wrapper);
            if (++burst < 20) requestAnimationFrame(tick);
        })();

        if (typeof ResizeObserver !== "undefined") {
            var ro = new ResizeObserver(function(){ syncRows(wrapper); });
            ro.observe(wrapper);
            wrapper.querySelectorAll("img").forEach(function(img){
                if (!img.complete) img.addEventListener("load", function(){ syncRows(wrapper); }, { once: true });
            });
        }
    }

    function boot(){
        document.querySelectorAll(".vergleich-wrapper").forEach(init);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }

    // Bricks-Builder re-renders: MutationObserver — reagiert NUR wenn neue
    // .vergleich-wrapper-Elemente ins DOM kommen. Text- oder Attribut-Aenderungen
    // innerhalb vorhandener Wrapper (z.B. vom Counter selbst) duerfen nicht
    // erneut boot() triggern, sonst Endlosschleife.
    if (typeof MutationObserver !== "undefined") {
        var mo = new MutationObserver(function(mutations){
            for (var i = 0; i < mutations.length; i++) {
                var added = mutations[i].addedNodes;
                for (var j = 0; j < added.length; j++) {
                    var n = added[j];
                    if (!n || n.nodeType !== 1) continue;
                    if ((n.classList && n.classList.contains("vergleich-wrapper")) ||
                        (n.querySelector && n.querySelector(".vergleich-wrapper"))) {
                        boot();
                        return;
                    }
                }
            }
        });
        mo.observe(document.body || document.documentElement, { childList: true, subtree: true });
    }
})();
</script>
        <?php
    }
}
