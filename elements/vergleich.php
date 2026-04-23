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

    /** ID-Präfix für aria-labelledby (verknüpft Zellen mit ihrem Row-Label). */
    public $_aria_id_prefix = '';

    /** Runtime-State für Schema.org JSON-LD (von render_inner befüllt, von render_card_inner pro Produkt ergänzt). */
    public $_schema_runtime = null;
    public $_schema_items = [];

    /** Index der ersten aufklappbaren Zeile (fuer Fade-Peek). -1 = keine. */
    public $_first_collapsible_idx = -1;

    /** Zähler für eindeutige Lightbox-Dialog-IDs im aktuellen Render-Durchlauf. */
    public $_lightbox_counter = 0;

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
        $this->control_groups['a11y']    = [ 'title' => esc_html__( 'Zugänglichkeit & SEO', 'bricks-vergleich' ), 'tab' => 'content' ];
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

        $this->controls['_sepStickyRows'] = [
            'tab'   => 'content', 'group' => 'rows',
            'type'  => 'separator',
            'label' => esc_html__( 'Sticky-Zeilen (global)', 'bricks-vergleich' ),
        ];

        $this->controls['stickyRowTop'] = [
            'tab'   => 'content', 'group' => 'rows',
            'label' => esc_html__( 'Sticky-Abstand (top)', 'bricks-vergleich' ),
            'type'  => 'number',
            'units' => true,
            'description' => esc_html__( 'Abstand zum Viewport-Rand für alle als sticky markierten Zeilen (z.B. Ausgleich für fixe Topbar). Leer = 0.', 'bricks-vergleich' ),
            'css'   => [ [ 'property' => '--vgl-sticky-row-top', 'selector' => '' ] ],
        ];

        $this->controls['stickyRowLabelBg'] = [
            'tab'   => 'content', 'group' => 'rows',
            'label' => esc_html__( 'Hintergrund Sticky-Label', 'bricks-vergleich' ),
            'type'  => 'color',
            'description' => esc_html__( 'Sticky-Zellen brauchen eine deckende Farbe, damit scrollender Inhalt dahinter nicht durchscheint. Default: Label-Spalten-Grau.', 'bricks-vergleich' ),
            'css'   => [ [ 'property' => 'background-color', 'selector' => '.vergleich-label.is-sticky-row' ] ],
        ];

        $this->controls['stickyRowCellBg'] = [
            'tab'   => 'content', 'group' => 'rows',
            'label' => esc_html__( 'Hintergrund Sticky-Zellen', 'bricks-vergleich' ),
            'type'  => 'color',
            'description' => esc_html__( 'Default: Weiß (Card-Hintergrund).', 'bricks-vergleich' ),
            'css'   => [ [ 'property' => 'background-color', 'selector' => '.vergleich-zelle.is-sticky-row' ] ],
        ];

        $this->controls['stickyRowTypography'] = [
            'tab'   => 'content', 'group' => 'rows',
            'label' => esc_html__( 'Typografie Sticky-Zeilen', 'bricks-vergleich' ),
            'type'  => 'typography',
            'description' => esc_html__( 'Gilt für Label und Zellen aller als sticky markierten Zeilen.', 'bricks-vergleich' ),
            'css'   => [ [ 'property' => 'typography', 'selector' => '.is-sticky-row' ] ],
        ];

        $this->controls['stickyRowBorder'] = [
            'tab'   => 'content', 'group' => 'rows',
            'label' => esc_html__( 'Rahmen Sticky-Zeilen', 'bricks-vergleich' ),
            'type'  => 'border',
            'description' => esc_html__( 'Optional: eigene Border/Radius für Sticky-Zeilen, z.B. Schatten-Linie unten.', 'bricks-vergleich' ),
            'css'   => [ [ 'property' => 'border', 'selector' => '.is-sticky-row' ] ],
        ];

        $this->controls['stickyRowShadow'] = [
            'tab'   => 'content', 'group' => 'rows',
            'label' => esc_html__( 'Schatten Sticky-Zeilen', 'bricks-vergleich' ),
            'type'  => 'box-shadow',
            'description' => esc_html__( 'Für den Finanzfluss-Look: subtiler Schatten unterhalb der Zeile.', 'bricks-vergleich' ),
            'css'   => [ [ 'property' => 'box-shadow', 'selector' => '.is-sticky-row' ] ],
        ];

        // ======================================================================
        // ZUGÄNGLICHKEIT & SEO
        // ======================================================================
        $this->controls['a11yInfo'] = [
            'tab'     => 'content', 'group' => 'a11y',
            'type'    => 'info',
            'content' => esc_html__( 'Die Tabelle wird mit ARIA-Rollen ausgestattet (role="table", role="rowheader" für Labels, role="cell" für Produktspalten) und jede Zelle ist per aria-labelledby mit ihrem Zeilen-Label verknüpft. Screenreader und KI-Modelle können dadurch die Struktur als Vergleichstabelle erkennen.', 'bricks-vergleich' ),
        ];

        $this->controls['tableAriaLabel'] = [
            'tab'         => 'content', 'group' => 'a11y',
            'label'       => esc_html__( 'ARIA-Label der Tabelle', 'bricks-vergleich' ),
            'type'        => 'text',
            'hasDynamicData' => 'text',
            'default'     => esc_html__( 'Produkt-Vergleichstabelle', 'bricks-vergleich' ),
            'description' => esc_html__( 'Beschreibt die Tabelle für Screenreader und Suchmaschinen. Z.B. "Vergleich Rudergeräte 2025".', 'bricks-vergleich' ),
        ];

        $this->controls['tableCaption'] = [
            'tab'         => 'content', 'group' => 'a11y',
            'label'       => esc_html__( 'Sichtbare Überschrift (optional)', 'bricks-vergleich' ),
            'type'        => 'text',
            'hasDynamicData' => 'text',
            'description' => esc_html__( 'Rendert als <h3> direkt über der Tabelle. Hilft Google dabei zu verstehen, was verglichen wird. Leer = keine Überschrift.', 'bricks-vergleich' ),
        ];

        $this->controls['tableCaptionTag'] = [
            'tab'       => 'content', 'group' => 'a11y',
            'label'     => esc_html__( 'Überschrift-Tag', 'bricks-vergleich' ),
            'type'      => 'select',
            'options'   => [ 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'div' => 'DIV (keine Heading)' ],
            'default'   => 'h3',
            'required'  => [ 'tableCaption', '!=', '' ],
        ];

        $this->controls['tableCaptionVisible'] = [
            'tab'       => 'content', 'group' => 'a11y',
            'label'     => esc_html__( 'Überschrift visuell anzeigen', 'bricks-vergleich' ),
            'type'      => 'checkbox',
            'default'   => true,
            'description' => esc_html__( 'Aus = Überschrift bleibt für Screenreader/SEO im DOM, wird aber visuell versteckt (sr-only-Pattern).', 'bricks-vergleich' ),
            'required'  => [ 'tableCaption', '!=', '' ],
        ];

        $this->controls['_sepSchema'] = [
            'tab'   => 'content', 'group' => 'a11y',
            'type'  => 'separator',
            'label' => esc_html__( 'Schema.org / JSON-LD', 'bricks-vergleich' ),
        ];

        $this->controls['schemaEnabled'] = [
            'tab'         => 'content', 'group' => 'a11y',
            'label'       => esc_html__( 'JSON-LD Schema.org ausgeben', 'bricks-vergleich' ),
            'type'        => 'checkbox',
            'description' => esc_html__( 'Emittiert eine ItemList mit Produkten (Name, Bild, Preis, Rating, URL). Google kann die Tabelle dann als Produktvergleich erkennen. Zeilen pro Feld über "Schema.org-Rolle" im jeweiligen Repeater-Eintrag verknüpfen.', 'bricks-vergleich' ),
        ];

        $this->controls['schemaListName'] = [
            'tab'         => 'content', 'group' => 'a11y',
            'label'       => esc_html__( 'Name der Liste', 'bricks-vergleich' ),
            'type'        => 'text',
            'hasDynamicData' => 'text',
            'placeholder' => esc_html__( 'z.B. Die besten Rudergeräte 2025', 'bricks-vergleich' ),
            'description' => esc_html__( 'Erscheint als "name" auf der ItemList. Standardmäßig wird der Tabellen-ARIA-Label verwendet.', 'bricks-vergleich' ),
            'required'    => [ 'schemaEnabled', '=', true ],
        ];

        $this->controls['schemaCurrency'] = [
            'tab'         => 'content', 'group' => 'a11y',
            'label'       => esc_html__( 'Währung (ISO 4217)', 'bricks-vergleich' ),
            'type'        => 'text',
            'default'     => 'EUR',
            'placeholder' => 'EUR',
            'description' => esc_html__( 'z.B. EUR, USD, GBP. Wird auf Offer → priceCurrency gesetzt.', 'bricks-vergleich' ),
            'required'    => [ 'schemaEnabled', '=', true ],
        ];

        $this->controls['schemaRatingBest'] = [
            'tab'         => 'content', 'group' => 'a11y',
            'label'       => esc_html__( 'Max. Rating-Wert', 'bricks-vergleich' ),
            'type'        => 'number',
            'default'     => 5,
            'description' => esc_html__( 'Wird als bestRating auf AggregateRating gesetzt. 5 für Sterne-Bewertung, 100 für Prozent-Scores etc.', 'bricks-vergleich' ),
            'required'    => [ 'schemaEnabled', '=', true ],
        ];

        $this->controls['schemaRatingCount'] = [
            'tab'         => 'content', 'group' => 'a11y',
            'label'       => esc_html__( 'Anzahl Bewertungen (optional)', 'bricks-vergleich' ),
            'type'        => 'number',
            'description' => esc_html__( 'Google Rich-Snippets zeigen AggregateRating nur mit ratingCount. Eigene Redaktions-Note = 1. Leer lassen, wenn du keine Rating-Snippets erzwingen willst.', 'bricks-vergleich' ),
            'required'    => [ 'schemaEnabled', '=', true ],
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
            'type' => 'number', 'units' => true,
            'css'   => [ [ 'property' => '--vgl-label-width', 'selector' => '' ] ],
        ];

        $this->controls['columnWidth'] = [
            'tab' => 'content', 'group' => 'layout',
            'label' => esc_html__( 'Breite Produkt-Spalte', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true,
            'css'   => [ [ 'property' => '--vgl-column-width', 'selector' => '' ] ],
        ];

        $this->controls['rowMinHeight'] = [
            'tab' => 'content', 'group' => 'layout',
            'label' => esc_html__( 'Min-Höhe pro Zeile', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true,
            'css'   => [ [ 'property' => '--vgl-row-min', 'selector' => '' ] ],
        ];

        $this->controls['cellPadding'] = [
            'tab' => 'content', 'group' => 'layout',
            'label' => esc_html__( 'Innenabstand Zellen & Labels', 'bricks-vergleich' ),
            'type' => 'spacing',
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
            'type' => 'number', 'units' => true,
            'required' => [ 'imageEnforce', '=', true ],
            'css'   => [ [ 'property' => '--vgl-img-width', 'selector' => '' ] ],
        ];

        $this->controls['imageHeight'] = [
            'tab' => 'content', 'group' => 'images',
            'label' => esc_html__( 'Bildhöhe', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true,
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
            'type'        => 'number', 'units' => true,
            'description' => esc_html__( 'Wieviel von der ersten verborgenen Zeile sichtbar sein soll (bevor der Fade einsetzt).', 'bricks-vergleich' ),
            'required'    => [ [ 'expandEnabled', '=', true ], [ 'expandFadeEnabled', '=', true ] ],
            'css'         => [ [ 'property' => '--vgl-fade-peek', 'selector' => '' ] ],
        ];

        $this->controls['expandFadeHeight'] = [
            'tab'         => 'content', 'group' => 'expand',
            'label'       => esc_html__( 'Fade-Höhe (Gradient)', 'bricks-vergleich' ),
            'type'        => 'number', 'units' => true,
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
            'type'        => 'number', 'units' => true,
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
            'type' => 'number', 'min' => 0,
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
            'type' => 'number', 'units' => true,
            'required' => [ 'rankingEnabled', '=', true ],
            'css'   => [ [ 'property' => '--vgl-rank-offset-y', 'selector' => '' ] ],
        ];

        $this->controls['rankingOffsetX'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Abstand links/rechts', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true,
            'required' => [ 'rankingEnabled', '=', true ],
            'css'   => [ [ 'property' => '--vgl-rank-offset-x', 'selector' => '' ] ],
        ];

        $this->controls['rankingSize'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Mindestgröße', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true,
            'required' => [ 'rankingEnabled', '=', true ],
            'css'   => [ [ 'property' => '--vgl-rank-size', 'selector' => '' ] ],
        ];

        $this->controls['rankingPadding'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Innenabstand', 'bricks-vergleich' ),
            'type' => 'spacing',
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
            'type' => 'number', 'min' => 0, 'max' => 4,
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
            'type' => 'number', 'units' => true,
            'required' => [ 'scoreEnabled', '=', true ],
            'css'   => [ [ 'property' => '--vgl-score-offset-y', 'selector' => '' ] ],
        ];

        $this->controls['scoreOffsetX'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Abstand links/rechts', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true,
            'required' => [ 'scoreEnabled', '=', true ],
            'css'   => [ [ 'property' => '--vgl-score-offset-x', 'selector' => '' ] ],
        ];

        $this->controls['scoreMinSize'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Mindestgröße', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true,
            'required' => [ 'scoreEnabled', '=', true ],
            'css'   => [ [ 'property' => '--vgl-score-size', 'selector' => '' ] ],
        ];

        $this->controls['scorePadding'] = [
            'tab' => 'content', 'group' => 'badges',
            'label' => esc_html__( 'Innenabstand', 'bricks-vergleich' ),
            'type' => 'spacing',
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
            'type'  => 'number', 'units' => true,
            'required' => [ 'productLabelsEnabled', '=', true ],
            'css'   => [ [ 'property' => '--vgl-product-label-height', 'selector' => '' ] ],
        ];

        $this->controls['productLabelsGap'] = [
            'tab'   => 'content', 'group' => 'productLabels',
            'label' => esc_html__( 'Abstand zur Tabelle', 'bricks-vergleich' ),
            'type'  => 'number', 'units' => true,
            'required' => [ 'productLabelsEnabled', '=', true ],
            'css'   => [ [ 'property' => '--vgl-product-label-gap', 'selector' => '' ] ],
        ];

        $this->controls['productLabelsPadding'] = [
            'tab'   => 'content', 'group' => 'productLabels',
            'label' => esc_html__( 'Innenabstand', 'bricks-vergleich' ),
            'type'  => 'spacing',
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

        $this->controls['productLabelsSticky'] = [
            'tab'   => 'content', 'group' => 'productLabels',
            'label' => esc_html__( 'Sticky beim Scrollen', 'bricks-vergleich' ),
            'type'  => 'checkbox',
            'description' => esc_html__( 'Die Produkt-Label-Zeile bleibt beim vertikalen Scrollen am oberen Viewport-Rand hängen (wie z.B. bei Finanzfluss).', 'bricks-vergleich' ),
            'required' => [ 'productLabelsEnabled', '=', true ],
        ];

        $this->controls['productLabelsStickyOffset'] = [
            'tab'   => 'content', 'group' => 'productLabels',
            'label' => esc_html__( 'Sticky-Abstand (top)', 'bricks-vergleich' ),
            'type'  => 'number',
            'units' => true,
            'description' => esc_html__( 'Abstand zur Viewport-Oberkante, z.B. um eine fixe Kopfleiste auszugleichen. Leer = 0.', 'bricks-vergleich' ),
            'required' => [ [ 'productLabelsEnabled', '=', true ], [ 'productLabelsSticky', '=', true ] ],
            'css'   => [ [ 'property' => '--vgl-product-labels-sticky-top', 'selector' => '' ] ],
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
            'type' => 'number', 'units' => true,
            'required' => [ 'navEnabled', '=', true ],
            'css' => [ [ 'property' => '--vgl-nav-size', 'selector' => '' ] ],
        ];

        $this->controls['navIconSize'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Icon-Größe', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true,
            'required' => [ 'navEnabled', '=', true ],
            'css' => [ [ 'property' => '--vgl-nav-icon-size', 'selector' => '' ] ],
        ];

        $this->controls['navOffset'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Abstand zum Rand', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true,
            'required' => [ 'navEnabled', '=', true ],
            'css' => [ [ 'property' => '--vgl-nav-offset', 'selector' => '' ] ],
        ];

        $this->controls['navOffsetX'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Versatz horizontal', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true,
            'description' => esc_html__( 'Feinjustierung zusätzlich zum Rand-Abstand. Positive Werte schieben beide Pfeile nach rechts, negative nach links.', 'bricks-vergleich' ),
            'required' => [ 'navEnabled', '=', true ],
            'css' => [ [ 'property' => '--vgl-nav-offset-x', 'selector' => '' ] ],
        ];

        $this->controls['navOffsetY'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Versatz vertikal', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true,
            'description' => esc_html__( 'Feinjustierung der Auto-Position. Positive Werte schieben den Pfeil nach unten, negative nach oben. Sticky-Verhalten beim Scrollen bleibt erhalten.', 'bricks-vergleich' ),
            'required' => [ 'navEnabled', '=', true ],
            'css' => [ [ 'property' => '--vgl-nav-offset-y', 'selector' => '' ] ],
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
                'above'    => esc_html__( 'Über der Tabelle', 'bricks-vergleich' ),
                'below'    => esc_html__( 'Unter der Tabelle', 'bricks-vergleich' ),
                'labelrow' => esc_html__( 'In der Produkt-Label-Zeile (über Label-Spalte)', 'bricks-vergleich' ),
            ],
            'placeholder' => esc_html__( 'Über der Tabelle', 'bricks-vergleich' ),
            'description' => esc_html__( '„In der Produkt-Label-Zeile" setzt den Zähler auf die gleiche Linie wie die Testsieger-/Platz-Badges, direkt im linken Spacer. Voraussetzung: Produkt-Labels sind aktiviert.', 'bricks-vergleich' ),
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
                'left'       => esc_html__( 'Links (volle Breite)', 'bricks-vergleich' ),
                'center'     => esc_html__( 'Zentriert', 'bricks-vergleich' ),
                'right'      => esc_html__( 'Rechts', 'bricks-vergleich' ),
                'labelcol'   => esc_html__( 'Über Label-Spalte (links, schmal)', 'bricks-vergleich' ),
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
            'required' => [ 'navCounterEnabled', '=', true ],
            'css' => [ [ 'property' => 'padding', 'selector' => '.vergleich-counter' ] ],
        ];

        // ─── SPALTE ANPINNEN ───────────────────────────────────────────────
        $this->controls['_sepPin'] = [
            'tab' => 'content', 'group' => 'scroll',
            'type' => 'separator',
            'label' => esc_html__( 'Spalte anpinnen', 'bricks-vergleich' ),
        ];

        $this->controls['pinEnabled'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Anpin-Funktion aktivieren', 'bricks-vergleich' ),
            'type' => 'checkbox',
            'description' => esc_html__( 'Kleiner Pin-Button oben rechts an jeder Spalte. Klick fixiert die Spalte links beim horizontalen Scrollen, damit man andere Spalten bequem damit vergleichen kann. Pin erscheint erst, wenn mindestens zwei Spalten gleichzeitig sichtbar sind.', 'bricks-vergleich' ),
        ];

        $this->controls['pinColor'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Pin-Farbe', 'bricks-vergleich' ),
            'type' => 'color',
            'required' => [ 'pinEnabled', '=', true ],
            'css' => [ [ 'property' => '--vgl-pin-color', 'selector' => '' ] ],
        ];

        $this->controls['pinColorActive'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Pin-Farbe aktiv', 'bricks-vergleich' ),
            'type' => 'color',
            'description' => esc_html__( 'Farbe des Pin-Icons, wenn die Spalte aktuell angepinnt ist.', 'bricks-vergleich' ),
            'required' => [ 'pinEnabled', '=', true ],
            'css' => [ [ 'property' => '--vgl-pin-color-active', 'selector' => '' ] ],
        ];

        $this->controls['pinOffsetX'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Abstand rechts', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true,
            'required' => [ 'pinEnabled', '=', true ],
            'css' => [ [ 'property' => '--vgl-pin-offset-x', 'selector' => '' ] ],
        ];

        $this->controls['pinOffsetY'] = [
            'tab' => 'content', 'group' => 'scroll',
            'label' => esc_html__( 'Abstand oben', 'bricks-vergleich' ),
            'type' => 'number', 'units' => true,
            'required' => [ 'pinEnabled', '=', true ],
            'css' => [ [ 'property' => '--vgl-pin-offset-y', 'selector' => '' ] ],
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

        $this->controls['labelShadow'] = [
            'tab' => 'content', 'group' => 'style',
            'label' => esc_html__( 'Schatten Label-Spalte', 'bricks-vergleich' ),
            'type' => 'box-shadow',
            'description' => esc_html__( 'Box-Schatten auf der Label-Spalte — z.B. X=8 Y=0 Unschärfe=12 für einen weichen Schatten rechts zur Abgrenzung vom Scroll-Bereich.', 'bricks-vergleich' ),
            'css' => [ [ 'property' => 'box-shadow', 'selector' => '.vergleich-labels' ] ],
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
                    'lightbox'=> esc_html__( 'Lightbox / Popover (Mehr Infos)', 'bricks-vergleich' ),
                    'coupon'  => esc_html__( 'Gutscheincode (mit Kopieren-Button)', 'bricks-vergleich' ),
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
            'textTypography' => [
                'label'    => esc_html__( 'Typografie', 'bricks-vergleich' ),
                'type'     => 'typography',
                'required' => [ 'type', '=', 'text' ],
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

            // ─── Eigenes Styling (überschreibt Preset) ───
            '_sepBtnCustom' => [
                'type'     => 'separator',
                'label'    => esc_html__( 'Eigenes Styling (überschreibt Preset)', 'bricks-vergleich' ),
                'required' => [ 'type', '=', 'button' ],
            ],
            'btnBgColor' => [
                'label'    => esc_html__( 'Hintergrundfarbe', 'bricks-vergleich' ),
                'type'     => 'color',
                'required' => [ 'type', '=', 'button' ],
            ],
            'btnTypography' => [
                'label'    => esc_html__( 'Typografie', 'bricks-vergleich' ),
                'type'     => 'typography',
                'required' => [ 'type', '=', 'button' ],
            ],
            'btnBorder' => [
                'label'    => esc_html__( 'Rahmen', 'bricks-vergleich' ),
                'type'     => 'border',
                'required' => [ 'type', '=', 'button' ],
            ],
            'btnPadding' => [
                'label'    => esc_html__( 'Innenabstand', 'bricks-vergleich' ),
                'type'     => 'spacing',
                'required' => [ 'type', '=', 'button' ],
            ],
            'btnMinWidth' => [
                'label'       => esc_html__( 'Mindestbreite', 'bricks-vergleich' ),
                'type'        => 'number',
                'units'       => true,
                'description' => esc_html__( 'z.B. 85% oder 140px. Leer = kein min-width. Nützlich, damit Buttons in allen Spalten gleich breit sind, auch wenn der Text unterschiedlich lang ist.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'button' ],
            ],
            'btnShadow' => [
                'label'    => esc_html__( 'Schatten', 'bricks-vergleich' ),
                'type'     => 'box-shadow',
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
                'required' => [ 'type', '=', 'rating' ],
            ],
            'ratingShowNumber' => [
                'label'    => esc_html__( 'Zahl zusätzlich anzeigen', 'bricks-vergleich' ),
                'type'     => 'checkbox',
                'required' => [ 'type', '=', 'rating' ],
            ],
            'ratingNumberTypography' => [
                'label'    => esc_html__( 'Typografie (Zahl)', 'bricks-vergleich' ),
                'type'     => 'typography',
                'required' => [ [ 'type', '=', 'rating' ], [ 'ratingShowNumber', '=', true ] ],
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
            'boolTrueIcon' => [
                'label'       => esc_html__( 'Icon "Ja" (optional)', 'bricks-vergleich' ),
                'type'        => 'icon',
                'description' => esc_html__( 'Ohne Auswahl wird ein Default-Häkchen verwendet.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'bool' ],
            ],
            'boolFalseIcon' => [
                'label'       => esc_html__( 'Icon "Nein" (optional)', 'bricks-vergleich' ),
                'type'        => 'icon',
                'description' => esc_html__( 'Ohne Auswahl wird ein Default-X verwendet.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'bool' ],
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
            'boolTypography' => [
                'label'       => esc_html__( 'Typografie (Text)', 'bricks-vergleich' ),
                'type'        => 'typography',
                'description' => esc_html__( 'Gilt für den optionalen Ja/Nein-Text neben dem Icon. Die Icon-Farbe bleibt unabhängig über Farbe "Ja" / "Nein".', 'bricks-vergleich' ),
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
            'scoreDisplay' => [
                'label'    => esc_html__( 'Darstellung', 'bricks-vergleich' ),
                'type'     => 'select',
                'options'  => [
                    'plain' => esc_html__( 'Nur Zahl (minimal)', 'bricks-vergleich' ),
                    'badge' => esc_html__( 'Badge / Kapsel (Pill)', 'bricks-vergleich' ),
                    'card'  => esc_html__( 'Karte mit Verdikt darunter', 'bricks-vergleich' ),
                ],
                'default'  => 'plain',
                'required' => [ 'type', '=', 'score' ],
            ],
            'scoreBadge' => [
                'label'       => esc_html__( 'Als Kapsel / Badge rendern (Legacy)', 'bricks-vergleich' ),
                'type'        => 'checkbox',
                'description' => esc_html__( 'Ersetzt durch "Darstellung" oben. Bleibt aus Rückwärtskompatibilität.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'score' ],
            ],
            'scoreBgColor' => [
                'label'    => esc_html__( 'Hintergrundfarbe', 'bricks-vergleich' ),
                'type'     => 'color',
                'required' => [ 'type', '=', 'score' ],
            ],
            'scoreTypography' => [
                'label'       => esc_html__( 'Typografie (Gesamtbereich)', 'bricks-vergleich' ),
                'type'        => 'typography',
                'description' => esc_html__( 'Fallback-Typografie für Zahl + Suffix. Im Karten-Modus gibt es eigene Typo-Controls weiter unten, die das überschreiben.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'score' ],
            ],
            'scorePadding' => [
                'label'       => esc_html__( 'Innenabstand', 'bricks-vergleich' ),
                'type'        => 'spacing',
                'required'    => [ 'type', '=', 'score' ],
            ],
            'scoreBorder' => [
                'label'       => esc_html__( 'Rahmen', 'bricks-vergleich' ),
                'type'        => 'border',
                'required'    => [ 'type', '=', 'score' ],
            ],
            'scoreShadow' => [
                'label'       => esc_html__( 'Schatten', 'bricks-vergleich' ),
                'type'        => 'box-shadow',
                'required'    => [ 'type', '=', 'score' ],
            ],

            // ─── Karten-Modus ───
            '_sepScoreCard' => [
                'type'     => 'separator',
                'label'    => esc_html__( 'Karten-Modus: Zahl groß, Verdikt unten', 'bricks-vergleich' ),
                'required' => [ 'scoreDisplay', '=', 'card' ],
            ],
            'scoreCardWidth' => [
                'label'       => esc_html__( 'Karten-Breite', 'bricks-vergleich' ),
                'type'        => 'text',
                'placeholder' => '140px',
                'description' => esc_html__( 'CSS-Wert — z.B. 140px, 8rem, 80%. Leer = Content-Breite.', 'bricks-vergleich' ),
                'required'    => [ 'scoreDisplay', '=', 'card' ],
            ],
            'scoreValueTypography' => [
                'label'       => esc_html__( 'Typografie: Zahl (groß)', 'bricks-vergleich' ),
                'type'        => 'typography',
                'description' => esc_html__( 'Default: fett, groß.', 'bricks-vergleich' ),
                'required'    => [ 'scoreDisplay', '=', 'card' ],
            ],
            'scoreSuffixTypography' => [
                'label'       => esc_html__( 'Typografie: Suffix (z.B. %)', 'bricks-vergleich' ),
                'type'        => 'typography',
                'description' => esc_html__( 'Default: deutlich kleiner als die Zahl, hochgestellt rechts daneben.', 'bricks-vergleich' ),
                'required'    => [ 'scoreDisplay', '=', 'card' ],
            ],
            'scoreValuePadding' => [
                'label'    => esc_html__( 'Innenabstand: Zahl-Bereich', 'bricks-vergleich' ),
                'type'     => 'spacing',
                // Default ausgegraut im UI anzeigen, wird auch als effektiver
                // Wert genutzt, wenn User nichts eingibt.
                'default'  => [ 'top' => '18px', 'right' => '16px', 'bottom' => '18px', 'left' => '16px' ],
                'required' => [ 'scoreDisplay', '=', 'card' ],
            ],
            'scoreVerdictSource' => [
                'label'   => esc_html__( 'Verdikt-Quelle', 'bricks-vergleich' ),
                'type'    => 'select',
                'options' => [
                    'text'  => esc_html__( 'Fester Text / Dynamic Data', 'bricks-vergleich' ),
                    'bands' => esc_html__( 'Automatisch nach Wertebereich', 'bricks-vergleich' ),
                    'none'  => esc_html__( 'Kein Verdikt (nur Zahl)', 'bricks-vergleich' ),
                ],
                'default'  => 'text',
                'required' => [ 'scoreDisplay', '=', 'card' ],
            ],
            'scoreVerdictText' => [
                'label'          => esc_html__( 'Verdikt-Text', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => 'text',
                'placeholder'    => esc_html__( 'z.B. Sehr Gut oder {cf:verdict}', 'bricks-vergleich' ),
                'required'       => [ [ 'scoreDisplay', '=', 'card' ], [ 'scoreVerdictSource', '=', 'text' ] ],
            ],
            'scoreVerdictBands' => [
                'label'         => esc_html__( 'Wertebereiche', 'bricks-vergleich' ),
                'type'          => 'repeater',
                'titleProperty' => 'label',
                'description'   => esc_html__( 'Ab-Schwelle abwärts zuordnen. Der erste Treffer (Wert ≥ Schwelle) gewinnt — also Bänder von hoch nach niedrig anlegen (90 → „Sehr Gut", 75 → „Gut", 50 → „OK").', 'bricks-vergleich' ),
                'required'      => [ [ 'scoreDisplay', '=', 'card' ], [ 'scoreVerdictSource', '=', 'bands' ] ],
                'fields'        => [
                    'min' => [
                        'label'       => esc_html__( 'Ab-Schwelle', 'bricks-vergleich' ),
                        'type'        => 'number',
                        'placeholder' => '90',
                    ],
                    'label' => [
                        'label'          => esc_html__( 'Label', 'bricks-vergleich' ),
                        'type'           => 'text',
                        'hasDynamicData' => 'text',
                        'placeholder'    => esc_html__( 'z.B. Sehr Gut', 'bricks-vergleich' ),
                    ],
                ],
            ],
            'scoreVerdictBg' => [
                'label'    => esc_html__( 'Hintergrundfarbe: Verdikt-Streifen', 'bricks-vergleich' ),
                'type'     => 'color',
                'required' => [ [ 'scoreDisplay', '=', 'card' ], [ 'scoreVerdictSource', '!=', 'none' ] ],
            ],
            'scoreVerdictTypography' => [
                'label'    => esc_html__( 'Typografie: Verdikt', 'bricks-vergleich' ),
                'type'     => 'typography',
                'required' => [ [ 'scoreDisplay', '=', 'card' ], [ 'scoreVerdictSource', '!=', 'none' ] ],
            ],
            'scoreVerdictPadding' => [
                'label'    => esc_html__( 'Innenabstand: Verdikt', 'bricks-vergleich' ),
                'type'     => 'spacing',
                'default'  => [ 'top' => '10px', 'right' => '12px', 'bottom' => '10px', 'left' => '12px' ],
                'required' => [ [ 'scoreDisplay', '=', 'card' ], [ 'scoreVerdictSource', '!=', 'none' ] ],
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
                'label'       => esc_html__( 'Einträge pro Spalte', 'bricks-vergleich' ),
                'type'        => 'repeater',
                'placeholder' => esc_html__( 'Spalte', 'bricks-vergleich' ),
                'description' => esc_html__( 'Pro Produkt-Spalte einen Eintrag. Rich-Text-Liste (Aufzählung) verwenden — jedes <li> wird ein Listenpunkt. Reihenfolge matched den Query-Loop.', 'bricks-vergleich' ),
                'required'    => [ [ 'type', '=', 'list' ], [ 'listSource', '=', 'manualColumns' ] ],
                'fields'      => [
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
                'required'    => [ 'type', '=', 'list' ],
            ],
            'listIconGap' => [
                'label'       => esc_html__( 'Abstand Icon → Text', 'bricks-vergleich' ),
                'type'        => 'number', 'units' => true,
                'required'    => [ 'type', '=', 'list' ],
            ],
            'listItemGap' => [
                'label'       => esc_html__( 'Abstand zwischen Einträgen', 'bricks-vergleich' ),
                'type'        => 'number', 'units' => true,
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
            'listTypography' => [
                'label'       => esc_html__( 'Typografie (Text)', 'bricks-vergleich' ),
                'type'        => 'typography',
                'description' => esc_html__( 'Gilt für den Text der Listeneinträge. Die Icon-Farbe bleibt unabhängig.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'list' ],
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

            // ───── LIGHTBOX ─────
            'lightboxTriggerText' => [
                'label'          => esc_html__( 'Button-Text', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => 'text',
                'default'        => esc_html__( 'Mehr Infos', 'bricks-vergleich' ),
                'placeholder'    => esc_html__( 'z.B. Details ansehen', 'bricks-vergleich' ),
                'required'       => [ 'type', '=', 'lightbox' ],
            ],
            'lightboxTriggerIcon' => [
                'label'       => esc_html__( 'Icon (optional)', 'bricks-vergleich' ),
                'type'        => 'icon',
                'description' => esc_html__( 'Wähle ein Icon aus der Bricks-Library oder füge ein eigenes SVG ein.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'lightbox' ],
            ],
            'lightboxTriggerIconPosition' => [
                'label'    => esc_html__( 'Icon-Position', 'bricks-vergleich' ),
                'type'     => 'select',
                'options'  => [
                    'left'  => esc_html__( 'Links vom Text', 'bricks-vergleich' ),
                    'right' => esc_html__( 'Rechts vom Text', 'bricks-vergleich' ),
                ],
                'default'  => 'left',
                'required' => [ 'type', '=', 'lightbox' ],
                // Optische Sichtbarkeit nur, wenn Icon gesetzt ist.
                // Bricks required unterstützt nur flache Bedingungen; hier
                // akzeptieren wir, dass das Feld immer sichtbar ist, aber
                // ohne Icon hat es schlicht keine Wirkung.
            ],
            'lightboxTriggerIconSize' => [
                'label'       => esc_html__( 'Icon-Größe', 'bricks-vergleich' ),
                'type'        => 'number',
                'units'       => true,
                'placeholder' => '1em',
                'description' => esc_html__( 'Leer = 1em (scaled mit der Button-Schriftgröße). Eigener Wert z.B. 18px.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'lightbox' ],
            ],
            'lightboxTriggerIconGap' => [
                'label'       => esc_html__( 'Abstand Icon ↔ Text', 'bricks-vergleich' ),
                'type'        => 'number',
                'units'       => true,
                'placeholder' => '6px',
                'required'    => [ 'type', '=', 'lightbox' ],
            ],
            'lightboxTriggerIconColor' => [
                'label'       => esc_html__( 'Icon-Farbe', 'bricks-vergleich' ),
                'type'        => 'color',
                'description' => esc_html__( 'Färbt das Icon zuverlässig, unabhängig davon, ob das SVG fill="currentColor" oder hartcodierte Farbwerte enthält. Die Bricks-internen Fill- und Stroke-Felder am Icon oben greifen bei manchen SVG-Uploads nicht — dieses Feld schon.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'lightbox' ],
            ],
            'lightboxTitle' => [
                'label'          => esc_html__( 'Dialog-Titel (optional)', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => 'text',
                'description'    => esc_html__( 'Erscheint als Überschrift im Popover. Leer = keine Überschrift.', 'bricks-vergleich' ),
                'required'       => [ 'type', '=', 'lightbox' ],
            ],
            'lightboxContent' => [
                'label'       => esc_html__( 'Inhalt (HTML / Shortcode)', 'bricks-vergleich' ),
                'type'        => 'code',
                'mode'        => 'htmlmixed',
                'description' => esc_html__( 'Dynamic-Data-Tags werden aufgelöst. Shortcodes werden ausgeführt. Mehrere Absätze/Blöcke stapeln sich automatisch untereinander — anders als bei normalen Zellen gibt es hier kein Flex-Layout, das den Inhalt horizontal quetscht.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'lightbox' ],
            ],
            'lightboxTriggerStyle' => [
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
                'required' => [ 'type', '=', 'lightbox' ],
            ],
            'lightboxTriggerCircle' => [
                'label'    => esc_html__( 'Kreis (Pill-Form)', 'bricks-vergleich' ),
                'type'     => 'checkbox',
                'required' => [ 'type', '=', 'lightbox' ],
            ],
            'lightboxTriggerOutline' => [
                'label'    => esc_html__( 'Umriss', 'bricks-vergleich' ),
                'type'     => 'checkbox',
                'required' => [ 'type', '=', 'lightbox' ],
            ],

            // ─── Eigenes Button-Styling (überschreibt Preset) ───
            '_sepLightboxTriggerCustom' => [
                'type'     => 'separator',
                'label'    => esc_html__( 'Eigenes Button-Styling (überschreibt Preset)', 'bricks-vergleich' ),
                'required' => [ 'type', '=', 'lightbox' ],
            ],
            'lightboxTriggerBgColor' => [
                'label'       => esc_html__( 'Hintergrundfarbe', 'bricks-vergleich' ),
                'type'        => 'color',
                'description' => esc_html__( 'Theme-Farben + globale Bricks-Farb-Variablen (z.B. {color-primary}) werden aufgelöst.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'lightbox' ],
            ],
            'lightboxTriggerTypography' => [
                'label'       => esc_html__( 'Typografie', 'bricks-vergleich' ),
                'type'        => 'typography',
                'description' => esc_html__( 'Font-Size, Weight, Color, Letter-Spacing … Variablen-Picker verfügbar.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'lightbox' ],
            ],
            'lightboxTriggerBorder' => [
                'label'    => esc_html__( 'Rahmen', 'bricks-vergleich' ),
                'type'     => 'border',
                'required' => [ 'type', '=', 'lightbox' ],
            ],
            'lightboxTriggerPadding' => [
                'label'       => esc_html__( 'Innenabstand', 'bricks-vergleich' ),
                'type'        => 'spacing',
                'description' => esc_html__( 'Top / Right / Bottom / Left. CSS-Variablen erlaubt (z.B. var(--space-sm), 10px, 0.75rem).', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'lightbox' ],
            ],
            'lightboxTriggerMinWidth' => [
                'label'       => esc_html__( 'Mindestbreite', 'bricks-vergleich' ),
                'type'        => 'number',
                'units'       => true,
                'placeholder' => '140',
                'description' => esc_html__( 'z.B. 140px oder 85%. Leer = auto.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'lightbox' ],
            ],
            'lightboxTriggerShadow' => [
                'label'    => esc_html__( 'Schatten', 'bricks-vergleich' ),
                'type'     => 'box-shadow',
                'required' => [ 'type', '=', 'lightbox' ],
            ],

            '_sepLightboxLayout' => [
                'type'     => 'separator',
                'label'    => esc_html__( 'Dialog-Layout', 'bricks-vergleich' ),
                'required' => [ 'type', '=', 'lightbox' ],
            ],
            'lightboxPosition' => [
                'label'   => esc_html__( 'Position', 'bricks-vergleich' ),
                'type'    => 'select',
                'options' => [
                    'center' => esc_html__( 'Mitte (Standard)', 'bricks-vergleich' ),
                    'top'    => esc_html__( 'Oben', 'bricks-vergleich' ),
                    'bottom' => esc_html__( 'Unten (Bottom-Sheet)', 'bricks-vergleich' ),
                ],
                'default'     => 'center',
                'description' => esc_html__( 'Mobile-Tipp: "Unten" fühlt sich auf dem Smartphone wie ein nativer Bottom-Sheet an.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'lightbox' ],
            ],
            'lightboxMaxWidth' => [
                'label'       => esc_html__( 'Max. Breite', 'bricks-vergleich' ),
                'type'        => 'text',
                'placeholder' => '640px',
                'description' => esc_html__( 'Einheiten erlaubt: px, %, vw, rem. Auf kleinen Viewports wird zusätzlich auf "100vw − 32px" gekappt. Leer = 640px.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'lightbox' ],
            ],
            'lightboxMaxHeight' => [
                'label'       => esc_html__( 'Max. Höhe', 'bricks-vergleich' ),
                'type'        => 'text',
                'placeholder' => 'calc(100vh - 32px)',
                'description' => esc_html__( 'Einheiten erlaubt: px, %, vh. Leer = automatisch (Viewport-Höhe minus Abstand).', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'lightbox' ],
            ],

            // ───── COUPON ─────
            'couponMode' => [
                'label'       => esc_html__( 'Modus', 'bricks-vergleich' ),
                'type'        => 'select',
                'options'     => [
                    'single' => esc_html__( 'Gleicher Code für alle Spalten', 'bricks-vergleich' ),
                    'manual' => esc_html__( 'Pro Spalte manuell', 'bricks-vergleich' ),
                ],
                'default'     => 'single',
                'description' => esc_html__( 'Manuell pro Spalte = jedes Produkt bekommt individuell Code, Shop-Link oder kann ausgeblendet werden. Ideal für gemischte Vergleiche, wo nicht jedes Produkt einen Gutscheincode hat.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'coupon' ],
            ],
            'couponCode' => [
                'label'          => esc_html__( 'Gutscheincode', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => 'text',
                'placeholder'    => 'SOMMER20',
                'description'    => esc_html__( 'Der Code, der beim Klick in die Zwischenablage kopiert wird. Dynamic-Data-Tags wie {cf:coupon_code} werden aufgelöst. Im Manuell-Modus ist das der Fallback für Spalten ohne eigenen Code.', 'bricks-vergleich' ),
                'required'       => [ 'type', '=', 'coupon' ],
            ],
            'couponColumns' => [
                'label'         => esc_html__( 'Werte pro Spalte', 'bricks-vergleich' ),
                'type'          => 'repeater',
                'titleProperty' => 'code',
                'placeholder'   => esc_html__( 'Spalte', 'bricks-vergleich' ),
                'description'   => esc_html__( 'Pro Produkt-Spalte einen Eintrag. Reihenfolge matched den Query-Loop. Felder leer lassen, um den globalen Wert zu übernehmen. "Ausblenden" = Zelle bleibt leer.', 'bricks-vergleich' ),
                'required'      => [ 'couponMode', '=', 'manual' ],
                'fields'        => [
                    'code' => [
                        'label'          => esc_html__( 'Gutscheincode', 'bricks-vergleich' ),
                        'type'           => 'text',
                        'hasDynamicData' => 'text',
                        'placeholder'    => esc_html__( 'leer = globaler Code', 'bricks-vergleich' ),
                    ],
                    'shopText' => [
                        'label'          => esc_html__( 'Shop-Button-Text', 'bricks-vergleich' ),
                        'type'           => 'text',
                        'hasDynamicData' => 'text',
                        'placeholder'    => esc_html__( 'leer = globaler Text', 'bricks-vergleich' ),
                    ],
                    'shopLink' => [
                        'label' => esc_html__( 'Shop-Link', 'bricks-vergleich' ),
                        'type'  => 'link',
                    ],
                    'hide' => [
                        'label'       => esc_html__( 'Diese Spalte ausblenden', 'bricks-vergleich' ),
                        'type'        => 'checkbox',
                        'description' => esc_html__( 'Für Produkte ohne Gutscheincode — Zelle bleibt komplett leer.', 'bricks-vergleich' ),
                    ],
                ],
            ],
            'couponManualFallback' => [
                'label'          => esc_html__( 'Fallback für fehlende Spalten-Einträge', 'bricks-vergleich' ),
                'type'           => 'select',
                'options'        => [
                    'global' => esc_html__( 'Globalen Code verwenden', 'bricks-vergleich' ),
                    'hide'   => esc_html__( 'Zelle ausblenden', 'bricks-vergleich' ),
                ],
                'default'        => 'global',
                'description'    => esc_html__( 'Was passiert, wenn es mehr Produkte im Loop gibt als Einträge im Repeater.', 'bricks-vergleich' ),
                'required'       => [ 'couponMode', '=', 'manual' ],
            ],
            'couponLabel' => [
                'label'          => esc_html__( 'Vortext (optional)', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => 'text',
                'placeholder'    => esc_html__( 'z.B. Gutschein:', 'bricks-vergleich' ),
                'description'    => esc_html__( 'Erscheint über dem Code-Feld. Leer = kein Vortext.', 'bricks-vergleich' ),
                'required'       => [ 'type', '=', 'coupon' ],
            ],
            'couponCopyTooltip' => [
                'label'          => esc_html__( 'Button-Tooltip', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => 'text',
                'default'        => esc_html__( 'Code kopieren', 'bricks-vergleich' ),
                'required'       => [ 'type', '=', 'coupon' ],
            ],
            'couponCopiedMessage' => [
                'label'          => esc_html__( 'Toast-Meldung (nach Klick)', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => 'text',
                'default'        => esc_html__( 'Code kopiert!', 'bricks-vergleich' ),
                'description'    => esc_html__( 'Kurze Bestätigung, die rechts unten eingeblendet wird. %code% wird durch den kopierten Code ersetzt.', 'bricks-vergleich' ),
                'required'       => [ 'type', '=', 'coupon' ],
            ],

            // ─── Code-Box-Styling ───
            '_sepCouponCode' => [
                'type'     => 'separator',
                'label'    => esc_html__( 'Code-Box-Styling', 'bricks-vergleich' ),
                'required' => [ 'type', '=', 'coupon' ],
            ],
            'couponCodeBg' => [
                'label'    => esc_html__( 'Hintergrundfarbe', 'bricks-vergleich' ),
                'type'     => 'color',
                'required' => [ 'type', '=', 'coupon' ],
            ],
            'couponCodeTypography' => [
                'label'    => esc_html__( 'Typografie', 'bricks-vergleich' ),
                'type'     => 'typography',
                'required' => [ 'type', '=', 'coupon' ],
            ],
            'couponCodeBorder' => [
                'label'       => esc_html__( 'Rahmen', 'bricks-vergleich' ),
                'type'        => 'border',
                'description' => esc_html__( 'Tipp: gestrichelter Rahmen (dashed) sieht bei Gutscheincodes klassisch gut aus.', 'bricks-vergleich' ),
                'required'    => [ 'type', '=', 'coupon' ],
            ],
            'couponCodePadding' => [
                'label'    => esc_html__( 'Innenabstand', 'bricks-vergleich' ),
                'type'     => 'spacing',
                'required' => [ 'type', '=', 'coupon' ],
            ],

            // ─── Shop-Button (optional) ───
            '_sepCouponShop' => [
                'type'     => 'separator',
                'label'    => esc_html__( 'Shop-Button (optional, unter dem Code)', 'bricks-vergleich' ),
                'required' => [ 'type', '=', 'coupon' ],
            ],
            'couponShopEnabled' => [
                'label'    => esc_html__( 'Shop-Button anzeigen', 'bricks-vergleich' ),
                'type'     => 'checkbox',
                'required' => [ 'type', '=', 'coupon' ],
            ],
            'couponShopText' => [
                'label'          => esc_html__( 'Button-Text', 'bricks-vergleich' ),
                'type'           => 'text',
                'hasDynamicData' => 'text',
                'default'        => esc_html__( 'Zum Shop', 'bricks-vergleich' ),
                'required'       => [ 'couponShopEnabled', '=', true ],
            ],
            'couponShopLink' => [
                'label'    => esc_html__( 'Shop-Link', 'bricks-vergleich' ),
                'type'     => 'link',
                'required' => [ 'couponShopEnabled', '=', true ],
            ],
            'couponShopStyle' => [
                'label'   => esc_html__( 'Button-Stil', 'bricks-vergleich' ),
                'type'    => 'select',
                'options' => [
                    'primary'   => esc_html__( 'Primär', 'bricks-vergleich' ),
                    'secondary' => esc_html__( 'Sekundär', 'bricks-vergleich' ),
                    'light'     => esc_html__( 'Hell', 'bricks-vergleich' ),
                    'dark'      => esc_html__( 'Dunkel', 'bricks-vergleich' ),
                    'success'   => esc_html__( 'Erfolg', 'bricks-vergleich' ),
                ],
                'default'  => 'primary',
                'required' => [ 'couponShopEnabled', '=', true ],
            ],
            'couponShopOutline' => [
                'label'    => esc_html__( 'Umriss', 'bricks-vergleich' ),
                'type'     => 'checkbox',
                'required' => [ 'couponShopEnabled', '=', true ],
            ],
            'couponShopBgColor' => [
                'label'    => esc_html__( 'Hintergrundfarbe (Override)', 'bricks-vergleich' ),
                'type'     => 'color',
                'required' => [ 'couponShopEnabled', '=', true ],
            ],
            'couponShopTypography' => [
                'label'    => esc_html__( 'Typografie', 'bricks-vergleich' ),
                'type'     => 'typography',
                'required' => [ 'couponShopEnabled', '=', true ],
            ],
            'couponShopPadding' => [
                'label'    => esc_html__( 'Innenabstand', 'bricks-vergleich' ),
                'type'     => 'spacing',
                'required' => [ 'couponShopEnabled', '=', true ],
            ],
            'couponShopBorder' => [
                'label'    => esc_html__( 'Rahmen', 'bricks-vergleich' ),
                'type'     => 'border',
                'required' => [ 'couponShopEnabled', '=', true ],
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
            'stickyRow' => [
                'label'       => esc_html__( 'Sticky beim Scrollen', 'bricks-vergleich' ),
                'type'        => 'checkbox',
                'description' => esc_html__( 'Diese Zeile (Label + alle Produktspalten) bleibt beim vertikalen Scrollen am oberen Viewport-Rand hängen. Bei mehreren sticky Zeilen stapeln sie sich — bei Bedarf Sticky-Abstand auf Element-Ebene anpassen.', 'bricks-vergleich' ),
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
            'schemaRole' => [
                'label'   => esc_html__( 'Schema.org-Rolle (SEO)', 'bricks-vergleich' ),
                'type'    => 'select',
                'options' => [
                    ''             => esc_html__( '— Keine —', 'bricks-vergleich' ),
                    'name'         => esc_html__( 'Produkt-Name', 'bricks-vergleich' ),
                    'image'        => esc_html__( 'Produkt-Bild', 'bricks-vergleich' ),
                    'url'          => esc_html__( 'Produkt-URL (Offer)', 'bricks-vergleich' ),
                    'price'        => esc_html__( 'Preis', 'bricks-vergleich' ),
                    'ratingValue'  => esc_html__( 'Bewertung (0–max)', 'bricks-vergleich' ),
                    'brand'        => esc_html__( 'Marke', 'bricks-vergleich' ),
                    'description'  => esc_html__( 'Kurzbeschreibung', 'bricks-vergleich' ),
                ],
                'description' => esc_html__( 'Verknüpft diese Zeile mit einem Schema.org-Feld. Nur aktiv, wenn "JSON-LD Schema.org ausgeben" auf Element-Ebene aktiviert ist.', 'bricks-vergleich' ),
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

        // Zugänglichkeit & SEO
        $table_aria_label   = $this->dd_string( (string) ( $settings['tableAriaLabel'] ?? '' ) );
        if ( $table_aria_label === '' ) $table_aria_label = esc_html__( 'Produkt-Vergleichstabelle', 'bricks-vergleich' );
        $table_caption      = $this->dd_string( (string) ( $settings['tableCaption'] ?? '' ) );
        $table_caption_tag  = $settings['tableCaptionTag'] ?? 'h3';
        if ( ! in_array( $table_caption_tag, [ 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ], true ) ) $table_caption_tag = 'h3';
        $table_caption_vis  = ! isset( $settings['tableCaptionVisible'] ) ? true : ! empty( $settings['tableCaptionVisible'] );

        // Eindeutiges ID-Präfix für aria-labelledby. Nutzt Bricks-Element-ID,
        // damit mehrere Tabellen auf einer Seite nicht kollidieren.
        $aria_id_prefix = 'vgl-' . ( isset( $this->id ) && $this->id !== '' ? preg_replace( '/[^a-z0-9_-]/i', '', (string) $this->id ) : uniqid() ) . '-lbl';
        $this->_aria_id_prefix = $aria_id_prefix;

        // Schema.org Runtime — pro Produkt wird später ein Item gesammelt.
        $this->_schema_items = [];

        // Lightbox-Counter auf 0, damit jede gerenderte Tabelle ihre eigenen
        // ID-Sequenzen kriegt (sonst würden mehrere Tabellen auf einer Seite
        // kollidieren, falls beide zufällig bei Counter 1 starten — der
        // Base-Präfix enthält die Bricks-Element-ID, das sollte reichen,
        // aber sauberer ist reset pro Render).
        $this->_lightbox_counter = 0;
        $schema_enabled = ! empty( $settings['schemaEnabled'] );
        if ( $schema_enabled ) {
            $this->_schema_runtime = [
                'enabled'      => true,
                'currency'     => strtoupper( preg_replace( '/[^A-Z]/i', '', (string) ( $settings['schemaCurrency'] ?? 'EUR' ) ) ) ?: 'EUR',
                'rating_best'  => max( 1, (int) ( $settings['schemaRatingBest'] ?? 5 ) ),
                'rating_count' => isset( $settings['schemaRatingCount'] ) && $settings['schemaRatingCount'] !== ''
                    ? max( 0, (int) $settings['schemaRatingCount'] )
                    : 0,
                'list_name'    => $this->dd_string( (string) ( $settings['schemaListName'] ?? '' ) ) ?: $table_aria_label,
            ];
        } else {
            $this->_schema_runtime = null;
        }

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
        $nav_counter_position = $settings['navCounterPosition'] ?? 'above';
        if ( ! in_array( $nav_counter_position, [ 'above', 'below', 'labelrow' ], true ) ) $nav_counter_position = 'above';
        $nav_counter_format   = isset( $settings['navCounterFormat'] ) && $settings['navCounterFormat'] !== ''
            ? (string) $settings['navCounterFormat']
            : '{start}–{end} von {total}';
        $nav_counter_align = $settings['navCounterAlign'] ?? 'right';
        if ( ! in_array( $nav_counter_align, [ 'left', 'center', 'right', 'labelcol' ], true ) ) $nav_counter_align = 'right';

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
        // Fade-Collapse: Grid-Zeilen = sichtbare + 1 Peek-Zeile (falls überhaupt
        // eine einklappbare Zeile existiert). Ohne diese Begrenzung würden die
        // mit display:none versteckten Zeilen weiterhin min-height-Spuren im
        // Grid belegen und unten einen leeren Block erzeugen.
        $fade_row_count = $visible_row_count + ( $first_collapsible_idx >= 0 ? 1 : 0 );

        $inline_style = sprintf(
            '--vgl-row-count:%d; --vgl-row-count-collapsed:%d; --vgl-row-count-fade:%d; --vgl-text-align:%s;',
            $row_count, $visible_row_count, $fade_row_count, esc_attr( $text_align )
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
        if ( $product_labels_enabled && ! empty( $settings['productLabelsSticky'] ) ) {
            $root_classes[] = 'has-sticky-product-labels';
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
        if ( ! empty( $settings['pinEnabled'] ) ) {
            $wrapper_classes[] = 'has-pin';
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
        // Bei "labelrow" wird der Zaehler weiter unten im Spacer der
        // Produkt-Label-Zeile inline gerendert — nicht oberhalb/unterhalb.

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
        // an .vergleich-scroll gekoppelt (siehe assets/frontend.js → bindLabelRowSync).
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

            // Linker Spacer — gleiche Breite wie die Label-Spalte; optionaler
            // Text; optional der Nav-Zaehler, wenn Position == 'labelrow'.
            echo '<div class="vergleich-product-label-row__spacer" style="' . esc_attr( $spacer_style ) . '">';
            if ( $pl_left !== '' ) {
                echo '<span class="vergleich-product-label-row__spacer-text">' . wp_kses_post( $pl_left ) . '</span>';
            }
            if ( $nav_counter_enabled && $nav_counter_position === 'labelrow' ) {
                // Inline-Zaehler im Spacer. Eigene Modifier-Klasse, damit das
                // Styling (kein padding-top/bottom, auf Spacer-Hoehe zentriert)
                // nicht mit den regulaeren Above/Below-Varianten kollidiert.
                echo $counter_html;
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

        // Optionale Überschrift (a11y + SEO). Sichtbar oder sr-only — entweder
        // als semantisches Heading-Tag (h2-h6) für Google / Reader, oder als
        // neutraler <div> falls der User nur eine optische Beschriftung will.
        if ( $table_caption !== '' ) {
            $cap_cls = 'vergleich-caption';
            if ( ! $table_caption_vis ) $cap_cls .= ' is-sr-only';
            echo '<' . esc_attr( $table_caption_tag ) . ' class="' . esc_attr( $cap_cls ) . '">'
                . wp_kses_post( $table_caption )
                . '</' . esc_attr( $table_caption_tag ) . '>';
        }

        // Innerer Table-Wrapper (Bordered, enthält Labels + Cards).
        // role="table" + aria-label macht die Struktur für Screenreader und KI-
        // Parser als Vergleichstabelle erkennbar. aria-rowcount = Gesamtzahl
        // der Zeilen (inkl. eingeklappter).
        $wrapper_data_attrs = 'data-row-count="' . (int) $row_count . '"';
        if ( $nav_counter_enabled ) {
            $wrapper_data_attrs .= ' data-counter="' . esc_attr( $counter_id ) . '"';
        }
        $wrapper_a11y_attrs = 'role="table"'
            . ' aria-label="' . esc_attr( $table_aria_label ) . '"'
            . ' aria-rowcount="' . (int) $row_count . '"';
        echo '<div class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '" '
            . $wrapper_data_attrs . ' ' . $wrapper_a11y_attrs . '>';

        // ─── LABEL COLUMN ──────────────────────────────────────────────────
        // role="presentation" auf den strukturellen Wrappern (Labels/Scroll/
        // Track/Card), damit sie das ARIA-Table-Modell nicht unterbrechen —
        // Rollen role="rowheader" (Labels) und role="cell" (Zellen) zählen
        // dann direkt unter role="table".
        echo '<div class="vergleich-labels" role="presentation">';

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
            if ( ! empty( $row['stickyRow'] ) ) {
                $cls .= ' is-sticky-row';
            }

            $extra = ' data-row-index="' . (int) $idx . '"';
            if ( $collapsible ) {
                $extra .= ' data-vergleich-row-id="' . esc_attr( $row_key ) . '"';
            }
            if ( $label_inline !== '' ) {
                $extra .= ' style="' . esc_attr( $label_inline ) . '"';
            }
            // ARIA: Label ist der Row-Header. Eindeutige ID, damit die Zellen in
            // den Produkt-Spalten per aria-labelledby darauf zeigen können
            // („Preis: 1.299 €" statt nur „1.299 €" für Screenreader/KI).
            $label_id = $aria_id_prefix . '-' . (int) $idx;
            $extra .= ' role="rowheader"'
                   . ' id="' . esc_attr( $label_id ) . '"'
                   . ' aria-rowindex="' . ( (int) $idx + 1 ) . '"';

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
        echo '<div class="vergleich-scroll" role="presentation"><div class="vergleich-track" role="presentation">';

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

        echo '</div>'; // .vergleich-wrapper schließen — Expand-Button liegt außerhalb

        // Navigations-Pfeile ALS GESCHWISTER des Wrappers — nicht darin.
        // Grund: Wrapper hat overflow:hidden, würde die Pfeile beim sticky-
        // Scrollen clippen. Im Root können sie frei positioniert werden und
        // das JS verankert sie per position:absolute + Viewport-Sticky-Top.
        if ( $nav_enabled ) {
            $arrow_l = '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>';
            $arrow_r = '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>';
            echo '<button type="button" class="vergleich-nav vergleich-nav--prev" aria-label="' . esc_attr__( 'Zurück', 'bricks-vergleich' ) . '" data-vgl-nav="prev" hidden>' . $arrow_l . '</button>';
            echo '<button type="button" class="vergleich-nav vergleich-nav--next" aria-label="' . esc_attr__( 'Weiter', 'bricks-vergleich' ) . '" data-vgl-nav="next" hidden>' . $arrow_r . '</button>';
        }

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

        // JSON-LD Schema.org Output: nachdem alle Produkte gerendert wurden,
        // emittieren wir eine ItemList mit allen gesammelten Produkt-Items.
        // Pro Produkt wurde `_schema_items[]` in render_card_inner befüllt.
        if ( ! empty( $this->_schema_runtime ) && ! empty( $this->_schema_items ) ) {
            echo $this->render_schema_jsonld();
        }

        echo '</div>'; // .vergleich-root
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

        // Schema.org: pro Produkt ein Item sammeln. Läuft im Loop-Kontext, damit
        // DD-Tags gegen das richtige Produkt aufgelöst werden.
        if ( ! empty( $this->_schema_runtime ) ) {
            $this->collect_schema_item( $rows, $loop_post_id );
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

                // ARIA: aria-label enthaelt den tatsaechlichen Wert, sonst
                // maskiert "Bewertung" den <span>-Textinhalt fuer Screenreader.
                // Reader hoert dann "Bewertung 80 %" statt nur "Bewertung".
                $score_aria = trim(
                    sprintf(
                        /* translators: 1: prefix, 2: wert, 3: suffix */
                        esc_html__( 'Bewertung: %1$s%2$s%3$s', 'bricks-vergleich' ),
                        $s_prefix !== '' ? $s_prefix . ' ' : '',
                        $display,
                        $s_suffix !== '' ? ' ' . $s_suffix : ''
                    )
                );

                $score_html  = '<div class="vergleich-score" role="img" aria-label="' . esc_attr( $score_aria ) . '">';
                if ( $s_prefix !== '' ) $score_html .= '<span class="vergleich-score__prefix" aria-hidden="true">' . esc_html( $s_prefix ) . '</span>';
                $score_html .= '<span class="vergleich-score__value" aria-hidden="true">' . esc_html( $display ) . '</span>';
                if ( $s_suffix !== '' ) $score_html .= '<span class="vergleich-score__suffix" aria-hidden="true">' . esc_html( $s_suffix ) . '</span>';
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

        // Pin-Button: wird per JS ein-/ausgeblendet (braucht mindestens 2
        // gleichzeitig sichtbare Spalten, sonst ergibt Pinning keinen Sinn).
        $pin_html = '';
        if ( ! empty( $settings['pinEnabled'] ) ) {
            $pin_html = '<button type="button" class="vergleich-pin" data-vgl-pin'
                . ' aria-label="' . esc_attr__( 'Spalte anpinnen', 'bricks-vergleich' ) . '"'
                . ' aria-pressed="false" tabindex="0">'
                . '<svg class="vergleich-pin__icon vergleich-pin__icon--off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
                . '<line x1="12" y1="17" x2="12" y2="22"/>'
                . '<path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a1 1 0 0 0 0-2H8a1 1 0 0 0 0 2h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24Z"/>'
                . '</svg>'
                . '<svg class="vergleich-pin__icon vergleich-pin__icon--on" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">'
                . '<path d="M16 9V4h1a1 1 0 0 0 0-2H7a1 1 0 0 0 0 2h1v5c0 1.66-1.34 3-3 3v2h5.97v7l1 1 1-1v-7H19v-2c-1.66 0-3-1.34-3-3z"/>'
                . '</svg>'
                . '</button>';
        }

        ob_start();
        echo '<div class="' . esc_attr( $card_class ) . '"' . $card_data . ' role="presentation">';
        echo $rank_html;
        echo $pin_html;

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
        if ( ! empty( $row['stickyRow'] ) ) $classes[] = 'is-sticky-row';
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

        // ARIA: Zelle ist eine Daten-Zelle der Tabelle, gehört zum Row-Header
        // mit gleicher row-index. aria-labelledby verknüpft beide → Reader
        // liest „Preis: 1.299 €" statt nur „1.299 €".
        $extra .= ' role="cell"';
        $extra .= ' aria-rowindex="' . ( (int) $idx + 1 ) . '"';
        if ( $this->_aria_id_prefix !== '' ) {
            $extra .= ' aria-labelledby="' . esc_attr( $this->_aria_id_prefix . '-' . (int) $idx ) . '"';
        }

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
                case 'lightbox':$content = $this->render_cell_lightbox( $row );break;
                case 'coupon':  $content = $this->render_cell_coupon( $row );  break;
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

        $typo_css = $this->format_typography( $row['textTypography'] ?? null );
        $style_attr = $typo_css !== '' ? ' style="' . esc_attr( $typo_css ) . '"' : '';
        return '<' . $tag . ' class="vergleich-text"' . $style_attr . '>' . $content . '</' . $tag . '>';
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

        // Optionale Inline-Overrides — nur gesetzt, wenn der User im Repeater
        // entsprechende Controls befüllt hat. Preset-Styles bleiben die Basis.
        $inline = '';
        $bg_override = $this->resolve_color( $row['btnBgColor'] ?? null );
        if ( $bg_override !== '' ) $inline .= 'background:' . esc_attr( $bg_override ) . ';';
        $inline .= $this->format_typography( $row['btnTypography'] ?? null );
        $inline .= $this->format_border( $row['btnBorder'] ?? null );
        if ( ! empty( $row['btnPadding'] ) && is_array( $row['btnPadding'] ) ) {
            $padding_any = array_filter( $row['btnPadding'], function ( $v ) { return $v !== '' && $v !== null; } );
            if ( ! empty( $padding_any ) ) {
                $inline .= 'padding:' . esc_attr( $this->format_spacing( $row['btnPadding'], '' ) ) . ';';
            }
        }
        $min_width = $this->get_css_value( $row['btnMinWidth'] ?? null, '' );
        if ( $min_width !== '' ) {
            $inline .= 'min-width:' . esc_attr( $min_width ) . ';';
        }
        $inline .= $this->format_box_shadow( $row['btnShadow'] ?? null );

        $attrs  = 'class="' . esc_attr( implode( ' ', $class_list ) ) . '"';
        if ( $inline !== '' )     $attrs .= ' style="' . esc_attr( $inline ) . '"';
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
            $num_style = $this->format_typography( $row['ratingNumberTypography'] ?? null, [ 'font-weight' => '600' ] );
            $number_html = '<span class="vergleich-rating__number" style="' . esc_attr( $num_style ) . '">'
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

            // ARIA: komplette Rating-Angabe in einem aria-label, damit
            // Screenreader "4,5 von 5 Sternen" hoeren statt 5 Einzel-Icons.
            // Nummer und Icons sind aria-hidden, weil der Label das schon
            // enthaelt (sonst doppelte Ansage).
            $rating_decimals = ( $value_clamped == (int) $value_clamped ) ? 0 : 1;
            $rating_aria = sprintf(
                /* translators: 1: wert, 2: max */
                esc_html__( '%1$s von %2$s', 'bricks-vergleich' ),
                number_format_i18n( $value_clamped, $rating_decimals ),
                (string) $max
            );

            return '<div class="vergleich-rating has-custom-icons" role="img" aria-label="' . esc_attr( $rating_aria ) . '" style="display:inline-flex;align-items:center;gap:6px;">'
                . '<span class="vergleich-rating__icons" aria-hidden="true" style="display:inline-flex;align-items:center;gap:' . esc_attr( $gap ) . ';">'
                . $parts
                . '</span>'
                . ( $number_html !== '' ? '<span aria-hidden="true">' . $number_html . '</span>' : '' )
                . '</div>';
        }

        // ─── Fallback: ★-Zeichen mit CSS-Overlay für Teilfüllung ─────────
        $pct = ( $value_clamped / $max ) * 100;
        $rating_decimals = ( $value_clamped == (int) $value_clamped ) ? 0 : 1;
        $rating_aria = sprintf(
            /* translators: 1: wert, 2: max */
            esc_html__( '%1$s von %2$s Sternen', 'bricks-vergleich' ),
            number_format_i18n( $value_clamped, $rating_decimals ),
            (string) $max
        );
        $html  = '<div class="vergleich-rating" role="img" aria-label="' . esc_attr( $rating_aria ) . '" style="display:inline-flex;align-items:center;gap:6px;font-size:' . esc_attr( $size ) . ';">';
        $html .= '<span class="vergleich-rating__stars" aria-hidden="true" style="position:relative;color:' . esc_attr( $empty_color ) . ';letter-spacing:' . esc_attr( $gap ) . ';font-family:Arial,sans-serif;">';
        $html .= str_repeat( '★', $max );
        $html .= '<span class="vergleich-rating__fill" style="position:absolute;inset:0;width:' . esc_attr( $pct ) . '%;overflow:hidden;color:' . esc_attr( $fill_color ) . ';white-space:nowrap;">';
        $html .= str_repeat( '★', $max );
        $html .= '</span></span>';
        if ( $number_html !== '' ) $html .= '<span aria-hidden="true">' . $number_html . '</span>';
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

        // User-Icon (Bricks Icon-Control) hat Vorrang. Fallback: inline SVG in
        // currentColor, damit der umgebende color-Style (über boolTrueColor /
        // boolFalseColor) greift. Gleiches Verhalten wie beim rating-Zelltyp.
        $custom_icon = $is_true ? ( $row['boolTrueIcon'] ?? null ) : ( $row['boolFalseIcon'] ?? null );
        if ( is_array( $custom_icon ) && ( ! empty( $custom_icon['icon'] ) || ! empty( $custom_icon['svg'] ) ) ) {
            $svg = \Bricks\Element::render_icon( $custom_icon );
        } elseif ( $is_true ) {
            $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="4 12 10 18 20 6"/></svg>';
        } else {
            $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>';
        }

        $wrap_style = sprintf(
            'display:inline-flex;align-items:center;justify-content:center;gap:6px;color:%s;font-weight:600;',
            esc_attr( $color )
        );
        // font-size mitgeben, damit Bricks-gerenderte Font-Icons (<i class="...">)
        // auf die gleiche Größe skalieren wie inline-SVG (width/height).
        $icon_style = sprintf(
            'display:inline-flex;align-items:center;justify-content:center;width:%s;height:%s;font-size:%s;flex:0 0 auto;line-height:1;',
            esc_attr( $size ), esc_attr( $size ), esc_attr( $size )
        );

        $out  = '<span class="vergleich-bool is-' . ( $is_true ? 'true' : 'false' ) . '"'
              . ' style="' . esc_attr( $wrap_style ) . '"'
              . ' aria-label="' . esc_attr( $aria ) . '">';
        $out .= '<span class="vergleich-bool__icon" style="' . esc_attr( $icon_style ) . '">' . $svg . '</span>';
        if ( $label !== '' ) {
            $text_style = $this->format_typography( $row['boolTypography'] ?? null );
            $out .= '<span class="vergleich-bool__text"' . ( $text_style !== '' ? ' style="' . esc_attr( $text_style ) . '"' : '' ) . '>' . esc_html( $label ) . '</span>';
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

        // Darstellungs-Modus: neues scoreDisplay (plain/badge/card) hat
        // Vorrang; Legacy-Checkbox scoreBadge bleibt als Fallback.
        $display_mode = isset( $row['scoreDisplay'] ) ? (string) $row['scoreDisplay'] : '';
        if ( ! in_array( $display_mode, [ 'plain', 'badge', 'card' ], true ) ) {
            $display_mode = ! empty( $row['scoreBadge'] ) ? 'badge' : 'plain';
        }
        $as_badge = ( $display_mode === 'badge' );
        $as_card  = ( $display_mode === 'card' );

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

        // ═══════════════════════════════════════════════════════════════════
        // CARD-Modus: eigene Struktur — Zahl groß oben, Verdikt unten
        // ═══════════════════════════════════════════════════════════════════
        if ( $as_card ) {
            return $this->render_score_card( $row, $display, $prefix, $suffix );
        }

        // ═══════════════════════════════════════════════════════════════════
        // Plain / Badge — bisheriges Rendering
        // ═══════════════════════════════════════════════════════════════════
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
            $shadow_css = $this->format_box_shadow( $row['scoreShadow'] ?? null, '0 1px 2px rgba(0,0,0,.12)' );
            $style .= 'background:' . esc_attr( $bg ) . ';'
                   .  'padding:' . esc_attr( $padding ) . ';' . $border_css . $shadow_css
                   .  'white-space:nowrap;';
        }

        $html  = '<span class="vergleich-score-cell' . ( $as_badge ? ' is-badge' : '' ) . '" style="' . esc_attr( $style ) . '">';
        if ( $prefix !== '' ) $html .= '<span class="vergleich-score-cell__prefix" style="opacity:.85;">' . esc_html( $prefix ) . '</span>';
        $html .= '<span class="vergleich-score-cell__value">' . esc_html( $display ) . '</span>';
        if ( $suffix !== '' ) $html .= '<span class="vergleich-score-cell__suffix" style="opacity:.85;">' . esc_html( $suffix ) . '</span>';
        $html .= '</span>';
        return $html;
    }

    /**
     * Score-Karten-Modus: Outer-Box mit zwei Regionen.
     * Oben: groß gesetzte Zahl + hochgestelltes Suffix.
     * Unten: Verdikt-Streifen (z.B. "Sehr Gut"), optional, mit eigener
     * Hintergrundfarbe und Typografie.
     */
    private function render_score_card( $row, $display, $prefix, $suffix ) {
        // Verdikt ermitteln — je nach Quelle
        $verdict_source = isset( $row['scoreVerdictSource'] ) ? (string) $row['scoreVerdictSource'] : 'text';
        if ( ! in_array( $verdict_source, [ 'text', 'bands', 'none' ], true ) ) $verdict_source = 'text';
        $verdict = '';

        if ( $verdict_source === 'text' ) {
            $v_raw = (string) ( $row['scoreVerdictText'] ?? '' );
            $verdict = trim( $this->dd_string( $v_raw ) );
        } elseif ( $verdict_source === 'bands' ) {
            $bands = isset( $row['scoreVerdictBands'] ) && is_array( $row['scoreVerdictBands'] )
                ? array_values( array_filter( $row['scoreVerdictBands'], 'is_array' ) )
                : [];
            // Bänder nach min desc sortieren: erster Treffer mit wert >= min gewinnt.
            usort( $bands, function ( $a, $b ) {
                $ma = isset( $a['min'] ) && $a['min'] !== '' ? (float) $a['min'] : -INF;
                $mb = isset( $b['min'] ) && $b['min'] !== '' ? (float) $b['min'] : -INF;
                return ( $ma < $mb ) ? 1 : ( ( $ma > $mb ) ? -1 : 0 );
            } );
            // Numerischen Wert aus $display zurückparsen (Komma → Punkt).
            $num = null;
            $norm = str_replace( ',', '.', $display );
            if ( is_numeric( $norm ) ) $num = (float) $norm;
            if ( $num !== null ) {
                foreach ( $bands as $b ) {
                    $min = isset( $b['min'] ) && $b['min'] !== '' ? (float) $b['min'] : null;
                    if ( $min === null ) continue;
                    if ( $num >= $min ) {
                        $verdict = trim( $this->dd_string( (string) ( $b['label'] ?? '' ) ) );
                        break;
                    }
                }
            }
        }

        // Outer-Box-Styling: nutzt die vorhandenen Controls (bg / border /
        // padding / shadow) — die wirken im Card-Modus auf den Wrapper.
        $card_bg     = $this->resolve_color( $row['scoreBgColor'] ?? null ) ?: '#e5e7eb';
        $card_border = $this->format_border( $row['scoreBorder'] ?? null, [ 'radius' => '14px' ] );
        $card_shadow = $this->format_box_shadow( $row['scoreShadow'] ?? null );
        $card_width  = $this->get_css_value( $row['scoreCardWidth'] ?? null, '' );

        $card_style = 'display:inline-flex;flex-direction:column;overflow:hidden;'
                    . 'background:' . esc_attr( $card_bg ) . ';'
                    . $card_border . $card_shadow;
        if ( $card_width !== '' ) $card_style .= 'width:' . esc_attr( $card_width ) . ';';

        // Zahl-Region: Typografie mit sinnvollem Default (groß + fett).
        $value_typo = $this->format_typography( $row['scoreValueTypography'] ?? null, [
            'font-size'   => '2.25rem',
            'font-weight' => '800',
            'line-height' => '1',
        ] );
        $suffix_typo = $this->format_typography( $row['scoreSuffixTypography'] ?? null, [
            'font-size'   => '0.9rem',
            'font-weight' => '600',
            'line-height' => '1',
        ] );
        $value_pad = '';
        if ( ! empty( $row['scoreValuePadding'] ) && is_array( $row['scoreValuePadding'] ) ) {
            $vp_any = array_filter( $row['scoreValuePadding'], function ( $v ) { return $v !== '' && $v !== null; } );
            if ( ! empty( $vp_any ) ) {
                $value_pad = 'padding:' . esc_attr( $this->format_spacing( $row['scoreValuePadding'], '' ) ) . ';';
            }
        }
        if ( $value_pad === '' ) $value_pad = 'padding:18px 16px;';

        $value_region_style = 'display:flex;align-items:baseline;justify-content:center;gap:4px;'
                            . $value_pad;

        $html  = '<div class="vergleich-score-cell is-card" style="' . esc_attr( $card_style ) . '">';
        $html .= '<div class="vergleich-score-cell__value-region" style="' . esc_attr( $value_region_style ) . '">';
        if ( $prefix !== '' ) {
            $html .= '<span class="vergleich-score-cell__prefix" style="' . esc_attr( $suffix_typo . 'opacity:.8;' ) . '">' . esc_html( $prefix ) . '</span>';
        }
        $html .= '<span class="vergleich-score-cell__value" style="' . esc_attr( $value_typo ) . '">' . esc_html( $display ) . '</span>';
        if ( $suffix !== '' ) {
            // Kein align-self:flex-start — Suffix soll auf der Baseline sitzen,
            // nicht als Superscript (Container hat align-items:baseline).
            $html .= '<span class="vergleich-score-cell__suffix" style="' . esc_attr( $suffix_typo ) . '">' . esc_html( $suffix ) . '</span>';
        }
        $html .= '</div>';

        if ( $verdict_source !== 'none' && $verdict !== '' ) {
            $verdict_bg   = $this->resolve_color( $row['scoreVerdictBg'] ?? null ) ?: 'rgba(0,0,0,0.08)';
            $verdict_typo = $this->format_typography( $row['scoreVerdictTypography'] ?? null, [
                'font-size'   => '0.875rem',
                'font-weight' => '600',
                'line-height' => '1.3',
            ] );
            $verdict_pad = '10px 12px';
            if ( ! empty( $row['scoreVerdictPadding'] ) && is_array( $row['scoreVerdictPadding'] ) ) {
                $vrp_any = array_filter( $row['scoreVerdictPadding'], function ( $v ) { return $v !== '' && $v !== null; } );
                if ( ! empty( $vrp_any ) ) {
                    $verdict_pad = $this->format_spacing( $row['scoreVerdictPadding'], $verdict_pad );
                }
            }
            $verdict_style = 'background:' . esc_attr( $verdict_bg ) . ';'
                           . 'padding:' . esc_attr( $verdict_pad ) . ';'
                           . 'text-align:center;'
                           . $verdict_typo;
            $html .= '<div class="vergleich-score-cell__verdict" style="' . esc_attr( $verdict_style ) . '">'
                   . esc_html( $verdict )
                   . '</div>';
        }

        $html .= '</div>';
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
        $typography_css = $this->format_typography( $row['listTypography'] ?? null );

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
                            return $this->render_list_html( $items, $icon, $icon_color, $icon_size, $icon_gap, $item_gap, $align, $fallback_raw, $typography_css );
                        }
                        $raw = is_scalar( $meta ) ? (string) $meta : '';
                    }
                }
            }
        }

        $items = $this->parse_list_items( $raw );
        return $this->render_list_html( $items, $icon, $icon_color, $icon_size, $icon_gap, $item_gap, $align, $fallback_raw, $typography_css );
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
    private function render_list_html( $items, $icon, $icon_color, $icon_size, $icon_gap, $item_gap, $align, $fallback_raw, $typography_css = '' ) {
        if ( empty( $items ) ) {
            $fb = $fallback_raw !== '' ? (string) $this->dd_string( $fallback_raw ) : '';
            if ( trim( strip_tags( $fb ) ) === '' ) return '';
            $fb_style = $typography_css !== '' ? ' style="' . esc_attr( $typography_css ) . '"' : '';
            return '<span class="vergleich-list__fallback"' . $fb_style . '>' . wp_kses_post( $fb ) . '</span>';
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
            $html .= '<span class="vergleich-list__text" style="min-width:0;flex:1 1 auto;' . $typography_css . '">' . wp_kses_post( $item ) . '</span>';
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

    /**
     * Lightbox-Zelle: Trigger-Button in der Zelle + zugehöriges <dialog>
     * direkt daneben. Nutzt natives <dialog> + showModal() — das rendert im
     * Browser-Top-Layer, ist damit immun gegen overflow:clip der Umgebung
     * und erbt kein flex-Layout von .vergleich-zelle, sodass mehrzeiliger
     * Inhalt (Shortcodes, Absätze) naturgemäß untereinander stapelt.
     *
     * Der Dialog kriegt eine pro-Render eindeutige ID, damit mehrere
     * Lightbox-Zellen (z.B. pro Produkt-Card) sich nicht gegenseitig
     * triggern. Eindeutigkeit: Wrapper-Counter (siehe $_lightbox_counter).
     */
    private function render_cell_lightbox( $row ) {
        $trigger_raw = isset( $row['lightboxTriggerText'] ) && $row['lightboxTriggerText'] !== ''
            ? (string) $row['lightboxTriggerText']
            : esc_html__( 'Mehr Infos', 'bricks-vergleich' );
        $trigger_text = $this->dd_string( $trigger_raw );
        if ( $trigger_text === '' ) $trigger_text = esc_html__( 'Mehr Infos', 'bricks-vergleich' );

        $title_raw = isset( $row['lightboxTitle'] ) ? (string) $row['lightboxTitle'] : '';
        $title     = $title_raw !== '' ? $this->dd_string( $title_raw ) : '';

        $body_raw = isset( $row['lightboxContent'] ) ? (string) $row['lightboxContent'] : '';
        // Dynamic-Data auflösen, dann Shortcodes ausführen — gleicher Pfad
        // wie beim HTML-Zelltyp, damit Autoren einheitliche Erwartung haben.
        $body = $body_raw !== '' ? do_shortcode( $this->dd_string( $body_raw ) ) : '';

        // Eindeutige ID pro Dialog-Instanz.
        if ( ! isset( $this->_lightbox_counter ) ) $this->_lightbox_counter = 0;
        $this->_lightbox_counter++;
        $base = isset( $this->id ) && $this->id !== ''
            ? preg_replace( '/[^a-z0-9_-]/i', '', (string) $this->id )
            : 'vgl';
        $dlg_id = 'vgl-lb-' . $base . '-' . $this->_lightbox_counter;

        // Trigger-Styling — gleiches Muster wie render_cell_button:
        // Bricks-Preset-Klassen (bricks-background-*/bricks-color-*) als Basis,
        // plus optionale Inline-Overrides aus Typography/Border/Padding/Shadow.
        $style   = isset( $row['lightboxTriggerStyle'] ) ? preg_replace( '/[^a-z]/i', '', strtolower( (string) $row['lightboxTriggerStyle'] ) ) : 'primary';
        if ( $style === '' ) $style = 'primary';
        $circle  = ! empty( $row['lightboxTriggerCircle'] );
        $outline = ! empty( $row['lightboxTriggerOutline'] );

        $btn_classes = [ 'vergleich-lightbox-trigger', 'bricks-button' ];
        if ( $outline ) {
            $btn_classes[] = 'outline';
            $btn_classes[] = 'bricks-color-' . $style;
        } else {
            $btn_classes[] = 'bricks-background-' . $style;
        }
        if ( $circle ) $btn_classes[] = 'circle';

        // Inline-Overrides (nur wenn User Werte gesetzt hat).
        $btn_inline = '';
        $bg_override = $this->resolve_color( $row['lightboxTriggerBgColor'] ?? null );
        if ( $bg_override !== '' ) $btn_inline .= 'background:' . esc_attr( $bg_override ) . ';';
        $btn_inline .= $this->format_typography( $row['lightboxTriggerTypography'] ?? null );
        $btn_inline .= $this->format_border( $row['lightboxTriggerBorder'] ?? null );
        if ( ! empty( $row['lightboxTriggerPadding'] ) && is_array( $row['lightboxTriggerPadding'] ) ) {
            $pad_any = array_filter( $row['lightboxTriggerPadding'], function ( $v ) { return $v !== '' && $v !== null; } );
            if ( ! empty( $pad_any ) ) {
                $btn_inline .= 'padding:' . esc_attr( $this->format_spacing( $row['lightboxTriggerPadding'], '' ) ) . ';';
            }
        }
        $min_w = $this->get_css_value( $row['lightboxTriggerMinWidth'] ?? null, '' );
        if ( $min_w !== '' ) $btn_inline .= 'min-width:' . esc_attr( $min_w ) . ';';
        $btn_inline .= $this->format_box_shadow( $row['lightboxTriggerShadow'] ?? null );

        // Position & Größe
        $position = isset( $row['lightboxPosition'] ) ? (string) $row['lightboxPosition'] : 'center';
        if ( ! in_array( $position, [ 'center', 'top', 'bottom' ], true ) ) $position = 'center';
        $dlg_class = 'vergleich-lightbox-dialog is-pos-' . $position;

        // Größen-Inlines: nur sanitize, was wir einbauen. Als CSS-Custom-
        // Properties auf dem <dialog> — so kann die CSS-Regel eine Kombination
        // aus User-Wert UND „100vw − 32px" als Obergrenze bilden.
        $max_w = trim( $this->sanitize_css_value( isset( $row['lightboxMaxWidth'] ) ? (string) $row['lightboxMaxWidth'] : '' ) );
        $max_h = trim( $this->sanitize_css_value( isset( $row['lightboxMaxHeight'] ) ? (string) $row['lightboxMaxHeight'] : '' ) );
        // Einheitenlose Zahlen als px interpretieren — sonst ergibt z.B. „640"
        // CSS `max-width: 640`, was ungültig ist, die gesamte max-width-Regel
        // unwirksam macht und den Dialog auf UA-Default-Breite (≈ 100vw) läuft.
        if ( $max_w !== '' && preg_match( '/^[0-9]+(\.[0-9]+)?$/', $max_w ) ) $max_w .= 'px';
        if ( $max_h !== '' && preg_match( '/^[0-9]+(\.[0-9]+)?$/', $max_h ) ) $max_h .= 'px';
        $dlg_style = '';
        if ( $max_w !== '' ) $dlg_style .= '--vgl-lb-max-w:' . $max_w . ';';
        if ( $max_h !== '' ) $dlg_style .= '--vgl-lb-max-h:' . $max_h . ';';

        // Icon rendern — gleicher Pfad wie render_cell_icon: nutzt
        // Bricks\Element::render_icon(), damit Icon-Library und custom-SVGs
        // beide automatisch unterstützt werden.
        //
        // Größen-Handling: Wrapper kriegt IMMER eine explizite width/height
        // (Default 1em), das innere SVG wird per CSS-Regel darunter auf
        // 100% gezwungen. Grund: Custom-SVG-Uploads haben oft native
        // width/height-Attribute (meist 24×24 oder 512×512), die ohne
        // !important die CSS-Größe ignorieren — das war bei allen Icon-
        // Zellen das gleiche Problem.
        $icon_html = '';
        $icon      = $row['lightboxTriggerIcon'] ?? null;
        $icon_pos  = isset( $row['lightboxTriggerIconPosition'] ) ? (string) $row['lightboxTriggerIconPosition'] : 'left';
        if ( $icon_pos !== 'right' ) $icon_pos = 'left';
        if ( is_array( $icon ) && ( ! empty( $icon['icon'] ) || ! empty( $icon['svg'] ) ) ) {
            $icon_size = $this->get_css_value( $row['lightboxTriggerIconSize'] ?? null, '1em' );
            $icon_color = $this->resolve_color( $row['lightboxTriggerIconColor'] ?? null );
            // Bricks rendert je nach Icon-Typ anders: bei SVG-Uploads wird
            // die class+style direkt auf das <svg>-Element gesetzt (KEIN
            // Wrapper), bei Font-Icons auf ein <span> mit einem <i>-Child.
            // Deshalb immer BEIDES setzen: color (Font-Icons) + fill (SVG).
            $icon_style = sprintf(
                'width:%s;height:%s;font-size:%s;line-height:1;flex:0 0 auto;',
                esc_attr( $icon_size ), esc_attr( $icon_size ), esc_attr( $icon_size )
            );
            if ( $icon_color !== '' ) {
                $icon_style .= 'color:' . esc_attr( $icon_color ) . ';';
                $icon_style .= 'fill:' . esc_attr( $icon_color ) . ';';
            }
            $icon_attrs = [
                'class' => [ 'vergleich-lightbox-trigger__icon' ],
                'style' => $icon_style,
            ];
            $icon_html = \Bricks\Element::render_icon( $icon, $icon_attrs );
        }

        // Gap zwischen Icon und Text: Button-CSS hat Default 6px; nur
        // überschreiben, wenn der User einen eigenen Wert gesetzt hat.
        $icon_gap = $this->get_css_value( $row['lightboxTriggerIconGap'] ?? null, '' );
        if ( $icon_html !== '' && $icon_gap !== '' ) {
            $btn_inline .= 'gap:' . esc_attr( $icon_gap ) . ';';
        }

        $btn_attrs = 'class="' . esc_attr( implode( ' ', $btn_classes ) ) . '"';
        if ( $btn_inline !== '' ) $btn_attrs .= ' style="' . esc_attr( $btn_inline ) . '"';
        $text_html = '<span class="vergleich-lightbox-trigger__text">' . esc_html( $trigger_text ) . '</span>';
        $inner = ( $icon_pos === 'right' )
            ? $text_html . $icon_html
            : $icon_html . $text_html;

        $html = '<button type="button" ' . $btn_attrs
              . ' data-vgl-lightbox-open="' . esc_attr( $dlg_id ) . '"'
              . ' aria-haspopup="dialog"'
              . ' aria-expanded="false"'
              . ' aria-controls="' . esc_attr( $dlg_id ) . '">'
              . $inner
              . '</button>';

        $html .= '<dialog class="' . esc_attr( $dlg_class ) . '" id="' . esc_attr( $dlg_id ) . '"';
        if ( $dlg_style !== '' ) {
            $html .= ' style="' . esc_attr( $dlg_style ) . '"';
        }
        $html .= '>';
        $html .= '<div class="vergleich-lightbox-dialog__inner">';
        // Close-Button als erstes Child für Tab-Order.
        $html .= '<button type="button" class="vergleich-lightbox-close" data-vgl-lightbox-close aria-label="'
               . esc_attr__( 'Schließen', 'bricks-vergleich' ) . '">&times;</button>';
        if ( $title !== '' ) {
            $html .= '<h3 class="vergleich-lightbox-dialog__title">' . wp_kses_post( $title ) . '</h3>';
        }
        $html .= '<div class="vergleich-lightbox-dialog__body">' . $body . '</div>';
        $html .= '</div>';
        $html .= '</dialog>';

        return $html;
    }

    /**
     * Gutschein-Zelle: Code-Box + Kopieren-Button, optional Shop-Button darunter.
     * Der eigentliche Copy-Vorgang + Toast läuft in assets/frontend.js.
     *
     * Barrierefreiheit:
     *  - Copy-Button: aria-label + title (Tooltip)
     *  - Code: <code>-Tag (semantisch korrekt) mit aria-live nicht noetig, weil
     *    der Toast die Bestaetigung gibt
     *  - Shop-Button: <a> mit rel=nofollow sponsored, wenn Affiliate — aus
     *    Link-Control-Daten abgeleitet
     */
    private function render_cell_coupon( $row ) {
        $mode = isset( $row['couponMode'] ) ? (string) $row['couponMode'] : 'single';
        if ( ! in_array( $mode, [ 'single', 'manual' ], true ) ) $mode = 'single';

        // Globale Defaults — dienen im manuellen Modus als Fallback.
        $global_code      = trim( $this->dd_string( (string) ( $row['couponCode'] ?? '' ) ) );
        $global_shop_text = $this->dd_string( (string) ( $row['couponShopText'] ?? '' ) );

        // Per-Spalte-Overrides im Manuell-Modus.
        $col_override = null;
        if ( $mode === 'manual' ) {
            $columns = isset( $row['couponColumns'] ) && is_array( $row['couponColumns'] )
                ? array_values( array_filter( $row['couponColumns'], 'is_array' ) )
                : [];
            $loop_idx = 0;
            if ( class_exists( '\Bricks\Query' ) && method_exists( '\Bricks\Query', 'get_loop_index' ) ) {
                $li = \Bricks\Query::get_loop_index();
                if ( $li !== '' && $li !== null ) $loop_idx = (int) $li;
            }
            if ( isset( $columns[ $loop_idx ] ) ) {
                $col_override = $columns[ $loop_idx ];
                // Spalte explizit ausgeblendet → leere Zelle rendern.
                if ( ! empty( $col_override['hide'] ) ) return '';
            } else {
                // Kein Eintrag fuer diese Spalte. Je nach Fallback-Setting:
                // "hide" → gar nichts rendern, "global" → mit globalen Werten weiter.
                $fb = isset( $row['couponManualFallback'] ) ? (string) $row['couponManualFallback'] : 'global';
                if ( $fb === 'hide' ) return '';
            }
        }

        // Code bestimmen: Spalten-Override, sonst global.
        $code = $global_code;
        if ( is_array( $col_override ) ) {
            $col_code_raw = isset( $col_override['code'] ) ? (string) $col_override['code'] : '';
            if ( trim( $col_code_raw ) !== '' ) {
                $code = trim( $this->dd_string( $col_code_raw ) );
            }
        }
        if ( $code === '' ) return '';

        $label_text = $this->dd_string( (string) ( $row['couponLabel'] ?? '' ) );
        $tooltip    = $this->dd_string( (string) ( $row['couponCopyTooltip'] ?? '' ) );
        if ( $tooltip === '' ) $tooltip = esc_html__( 'Code kopieren', 'bricks-vergleich' );
        $copied_msg = $this->dd_string( (string) ( $row['couponCopiedMessage'] ?? '' ) );
        if ( $copied_msg === '' ) $copied_msg = esc_html__( 'Code kopiert!', 'bricks-vergleich' );

        // Code-Box Inline-Styling aus Bricks-Controls.
        $code_inline = '';
        $bg = $this->resolve_color( $row['couponCodeBg'] ?? null );
        if ( $bg !== '' ) $code_inline .= 'background-color:' . esc_attr( $bg ) . ';';
        $code_inline .= $this->format_typography( $row['couponCodeTypography'] ?? null );
        $code_inline .= $this->format_border( $row['couponCodeBorder'] ?? null );
        if ( ! empty( $row['couponCodePadding'] ) && is_array( $row['couponCodePadding'] ) ) {
            $pad_any = array_filter( $row['couponCodePadding'], function ( $v ) { return $v !== '' && $v !== null; } );
            if ( ! empty( $pad_any ) ) {
                $code_inline .= 'padding:' . esc_attr( $this->format_spacing( $row['couponCodePadding'], '' ) ) . ';';
            }
        }

        $html  = '<div class="vergleich-coupon">';

        if ( $label_text !== '' ) {
            $html .= '<div class="vergleich-coupon__label">' . esc_html( $label_text ) . '</div>';
        }

        $html .= '<div class="vergleich-coupon__row">';
        // <span> statt <code>: Bricks-Canvas (und manche Themes) injizieren
        // UA-/Editor-Styles fuer <code>, die das Flex-/Grid-Layout aufbrechen.
        // Semantik via data-Attribut; der user-select:all verhaelt sich gleich.
        $html .= '<span class="vergleich-coupon__code" data-vgl-coupon-code'
               . ( $code_inline !== '' ? ' style="' . esc_attr( $code_inline ) . '"' : '' )
               . '>' . esc_html( $code ) . '</span>';
        // Copy-Button: SVG-Icon (zwei Rechtecke = Clipboard-Metapher), Success-
        // Variante (Häkchen) wird per JS bei Bedarf eingeblendet.
        $html .= '<button type="button" class="vergleich-coupon__copy"'
               . ' data-vgl-copy-code="' . esc_attr( $code ) . '"'
               . ' data-vgl-copy-toast="' . esc_attr( str_replace( '%code%', $code, $copied_msg ) ) . '"'
               . ' aria-label="' . esc_attr( $tooltip ) . '"'
               . ' title="' . esc_attr( $tooltip ) . '">'
               . '<span class="vergleich-coupon__copy-icons" aria-hidden="true">'
               . '<svg class="vergleich-coupon__copy-icon is-default" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>'
               . '<svg class="vergleich-coupon__copy-icon is-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>'
               . '</span>'
               . '</button>';
        $html .= '</div>';

        // Optional Shop-Button
        if ( ! empty( $row['couponShopEnabled'] ) ) {
            // Spalten-Override fuer Text + Link, sonst globale Einstellung.
            $shop_text_raw = '';
            if ( is_array( $col_override ) && isset( $col_override['shopText'] ) && trim( (string) $col_override['shopText'] ) !== '' ) {
                $shop_text_raw = (string) $col_override['shopText'];
            } else {
                $shop_text_raw = (string) ( $row['couponShopText'] ?? '' );
            }
            $shop_text = $this->dd_string( $shop_text_raw );
            if ( $shop_text === '' ) $shop_text = esc_html__( 'Zum Shop', 'bricks-vergleich' );

            // Link: Spalten-Override nur, wenn dort ein gueltiger Link gesetzt ist,
            // sonst globaler Link.
            $col_link = ( is_array( $col_override ) && ! empty( $col_override['shopLink'] ) )
                ? $col_override['shopLink']
                : null;
            $col_link_resolved = $col_link !== null ? $this->resolve_link( $col_link ) : [ 'url' => '' ];
            $link = ! empty( $col_link_resolved['url'] )
                ? $col_link_resolved
                : $this->resolve_link( $row['couponShopLink'] ?? null );

            $style   = isset( $row['couponShopStyle'] ) ? preg_replace( '/[^a-z]/i', '', strtolower( (string) $row['couponShopStyle'] ) ) : 'primary';
            if ( $style === '' ) $style = 'primary';
            $outline = ! empty( $row['couponShopOutline'] );

            $cls = [ 'vergleich-coupon__shop', 'bricks-button' ];
            if ( $outline ) {
                $cls[] = 'outline';
                $cls[] = 'bricks-color-' . $style;
            } else {
                $cls[] = 'bricks-background-' . $style;
            }

            $shop_inline = '';
            $shop_bg = $this->resolve_color( $row['couponShopBgColor'] ?? null );
            if ( $shop_bg !== '' ) $shop_inline .= 'background:' . esc_attr( $shop_bg ) . ';';
            $shop_inline .= $this->format_typography( $row['couponShopTypography'] ?? null );
            $shop_inline .= $this->format_border( $row['couponShopBorder'] ?? null );
            if ( ! empty( $row['couponShopPadding'] ) && is_array( $row['couponShopPadding'] ) ) {
                $sp_any = array_filter( $row['couponShopPadding'], function ( $v ) { return $v !== '' && $v !== null; } );
                if ( ! empty( $sp_any ) ) {
                    $shop_inline .= 'padding:' . esc_attr( $this->format_spacing( $row['couponShopPadding'], '' ) ) . ';';
                }
            }

            $shop_attrs = 'class="' . esc_attr( implode( ' ', $cls ) ) . '"';
            if ( $shop_inline !== '' )     $shop_attrs .= ' style="' . esc_attr( $shop_inline ) . '"';
            if ( ! empty( $link['aria'] ) ) $shop_attrs .= ' aria-label="' . esc_attr( $link['aria'] ) . '"';
            if ( ! empty( $link['title'] ) )$shop_attrs .= ' title="' . esc_attr( $link['title'] ) . '"';

            if ( ! empty( $link['url'] ) ) {
                $shop_attrs .= ' href="' . esc_url( $link['url'] ) . '"';
                if ( ! empty( $link['target'] ) ) $shop_attrs .= ' target="' . esc_attr( $link['target'] ) . '"';
                if ( ! empty( $link['rel'] ) )    $shop_attrs .= ' rel="' . esc_attr( trim( $link['rel'] ) ) . '"';
                $html .= '<a ' . $shop_attrs . '>' . esc_html( $shop_text ) . '</a>';
            } else {
                $html .= '<button type="button" ' . $shop_attrs . '>' . esc_html( $shop_text ) . '</button>';
            }
        }

        $html .= '</div>';
        return $html;
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

    // ==========================================================================
    // SCHEMA.ORG JSON-LD
    // ==========================================================================

    /**
     * Pro Produkt (im Loop-Kontext) die Zeilen durchgehen und Schema.org-Felder
     * aus den zugeordneten Rollen extrahieren. Das Item wird an _schema_items
     * angehängt und am Ende von render_inner als ItemList ausgegeben.
     */
    private function collect_schema_item( $rows, $loop_post_id ) {
        $item = [];
        foreach ( $rows as $row ) {
            $role = isset( $row['schemaRole'] ) ? (string) $row['schemaRole'] : '';
            if ( $role === '' ) continue;
            $value = $this->extract_row_value_for_schema( $row, $role, $loop_post_id );
            if ( $value === '' || $value === null ) continue;

            switch ( $role ) {
                case 'name':
                    $item['name'] = $value;
                    break;
                case 'image':
                    $item['image'] = $value;
                    break;
                case 'url':
                    // URL bleibt auch als Top-Level Product.url für Reichweite.
                    $item['url'] = $value;
                    break;
                case 'price':
                    if ( ! isset( $item['offers'] ) ) $item['offers'] = [ '@type' => 'Offer' ];
                    $item['offers']['price']         = $value;
                    $item['offers']['priceCurrency'] = $this->_schema_runtime['currency'];
                    break;
                case 'ratingValue':
                    $item['aggregateRating'] = [
                        '@type'       => 'AggregateRating',
                        'ratingValue' => (string) $value,
                        'bestRating'  => (string) $this->_schema_runtime['rating_best'],
                    ];
                    if ( (int) $this->_schema_runtime['rating_count'] > 0 ) {
                        $item['aggregateRating']['ratingCount'] = (string) $this->_schema_runtime['rating_count'];
                    }
                    break;
                case 'brand':
                    $item['brand'] = [ '@type' => 'Brand', 'name' => $value ];
                    break;
                case 'description':
                    $item['description'] = $value;
                    break;
            }
        }

        // Offer.url = Product.url (falls beides vorhanden), hilft Google die Verknüpfung sauber zu erkennen.
        if ( isset( $item['offers'] ) && isset( $item['url'] ) && ! isset( $item['offers']['url'] ) ) {
            $item['offers']['url'] = $item['url'];
        }

        if ( ! empty( $item ) ) {
            $this->_schema_items[] = $item;
        }
    }

    /**
     * Reinen Wert aus einer Zeile für das Schema-Feld extrahieren. Je nach
     * Zelltyp wird unterschiedlich aufgelöst — Text/Price als plain string,
     * Image als URL, Button als Offer-URL, Rating/Score als Zahl.
     */
    private function extract_row_value_for_schema( $row, $role, $loop_post_id ) {
        $type = $row['type'] ?? 'text';

        // URL-Rollen kommen typischerweise aus button-Zellen.
        if ( $role === 'url' && $type === 'button' ) {
            return $this->resolve_button_url( $row );
        }

        // Image-Rollen aus image-Zellen (Meta-Key o.ä.).
        if ( $role === 'image' && $type === 'image' ) {
            return $this->resolve_image_url( $row, $loop_post_id );
        }

        // Rating aus rating- oder score-Zellen.
        if ( $role === 'ratingValue' ) {
            if ( $type === 'rating' ) {
                $raw = $this->dd_string( (string) ( $row['ratingValue'] ?? '' ) );
                return $this->parse_number( $raw );
            }
            if ( $type === 'score' ) {
                return $this->resolve_score_value( $row, $loop_post_id );
            }
        }

        // Preis: text- oder score-Zelle (Zahl extrahieren).
        if ( $role === 'price' ) {
            if ( $type === 'text' ) {
                $raw = $this->dd_string( (string) ( $row['text'] ?? '' ) );
                return $this->parse_number( $raw );
            }
            if ( $type === 'score' ) {
                return $this->resolve_score_value( $row, $loop_post_id );
            }
        }

        // Fallback für name/brand/description → text-Zellen.
        if ( $type === 'text' ) {
            $raw = $this->dd_string( (string) ( $row['text'] ?? '' ) );
            return $this->clean_schema_string( $raw );
        }

        // Dynamic-Zelle: DD auflösen
        if ( $type === 'dynamic' ) {
            $dd = (string) ( $row['dynamic'] ?? '' );
            if ( $dd !== '' ) {
                $resolved = $this->dd_string( $dd );
                return $this->clean_schema_string( $resolved );
            }
        }

        return '';
    }

    private function resolve_button_url( $row ) {
        $link = $row['btnLink'] ?? [];
        if ( ! is_array( $link ) ) return is_string( $link ) ? $this->dd_string( $link ) : '';
        $type = $link['type'] ?? '';
        if ( $type === 'internal' && ! empty( $link['postId'] ) ) {
            return (string) ( get_permalink( (int) $link['postId'] ) ?: '' );
        }
        if ( $type === 'external' && ! empty( $link['url'] ) ) {
            return $this->dd_string( (string) $link['url'] );
        }
        if ( $type === 'meta' && ! empty( $link['useDynamicData'] ) ) {
            $dd = is_array( $link['useDynamicData'] )
                ? (string) ( $link['useDynamicData']['name'] ?? '' )
                : (string) $link['useDynamicData'];
            return $this->dd_string( $dd );
        }
        if ( ! empty( $link['url'] ) ) return $this->dd_string( (string) $link['url'] );
        return '';
    }

    private function resolve_image_url( $row, $loop_post_id ) {
        $image = $row['image'] ?? null;
        if ( is_array( $image ) ) {
            // Direkt-Upload: id oder url
            if ( ! empty( $image['id'] ) ) {
                $url = wp_get_attachment_image_url( (int) $image['id'], 'full' );
                if ( $url ) return $url;
            }
            if ( ! empty( $image['url'] ) && is_string( $image['url'] ) ) {
                return (string) $image['url'];
            }
            // Dynamic Data (z.B. {featured_image})
            $dyn = is_string( $image['useDynamicData'] ?? null ) ? trim( $image['useDynamicData'] ) : '';
            if ( $dyn !== '' ) {
                if ( preg_match( '/^\{\s*featured_image\s*\}$/', $dyn ) && $loop_post_id > 0 ) {
                    $thumb = get_the_post_thumbnail_url( $loop_post_id, 'full' );
                    if ( $thumb ) return $thumb;
                }
                if ( function_exists( 'bricks_render_dynamic_data' ) ) {
                    $resolved = bricks_render_dynamic_data( $dyn, $loop_post_id ?: null );
                    if ( is_string( $resolved ) && $resolved !== '' && $resolved !== $dyn ) {
                        return $resolved;
                    }
                }
            }
        }
        // Fallback: Post-Thumbnail
        if ( $loop_post_id > 0 ) {
            $thumb = get_the_post_thumbnail_url( $loop_post_id, 'full' );
            if ( $thumb ) return $thumb;
        }
        return '';
    }

    private function resolve_score_value( $row, $loop_post_id ) {
        $key = isset( $row['scoreKey'] ) ? trim( (string) $row['scoreKey'] ) : '';
        if ( $key === '' ) return '';
        $raw = '';
        if ( strpos( $key, '{' ) !== false && function_exists( 'bricks_render_dynamic_data' ) ) {
            $resolved = bricks_render_dynamic_data( $key, $loop_post_id ?: null );
            $raw = is_string( $resolved ) ? $resolved : '';
        } elseif ( $loop_post_id > 0 ) {
            $meta = get_post_meta( $loop_post_id, $key, true );
            $raw = is_scalar( $meta ) ? (string) $meta : '';
        }
        return $this->parse_number( $raw );
    }

    /**
     * Zahl aus einem String extrahieren — "1.299,00 €" → "1299.00", "4,5" → "4.5".
     * Google akzeptiert Preise als String im Punkt-Format.
     */
    private function parse_number( $str ) {
        $str = (string) $str;
        if ( $str === '' ) return '';
        // Nur Zahlen, Komma, Punkt, Minus behalten.
        $clean = preg_replace( '/[^0-9.,\-]/', '', $str );
        if ( $clean === '' ) return '';
        // Wenn sowohl , als auch . vorhanden: Tausender-Trenner entfernen (der
        // Trenner, der weiter links steht, ist Tausender).
        if ( strpos( $clean, ',' ) !== false && strpos( $clean, '.' ) !== false ) {
            if ( strrpos( $clean, ',' ) > strrpos( $clean, '.' ) ) {
                // Deutsches Format: "1.299,00" → "1299.00"
                $clean = str_replace( '.', '', $clean );
                $clean = str_replace( ',', '.', $clean );
            } else {
                // US-Format: "1,299.00" → "1299.00"
                $clean = str_replace( ',', '', $clean );
            }
        } elseif ( strpos( $clean, ',' ) !== false ) {
            // Nur Komma: Dezimaltrenner.
            $clean = str_replace( ',', '.', $clean );
        }
        return $clean;
    }

    private function clean_schema_string( $str ) {
        $str = wp_strip_all_tags( (string) $str );
        $str = trim( preg_replace( '/\s+/', ' ', $str ) );
        return $str;
    }

    /**
     * Die gesammelten Items als Schema.org ItemList emittieren.
     */
    private function render_schema_jsonld() {
        $list_name = $this->_schema_runtime['list_name'] ?? 'Produktvergleich';
        $elements  = [];
        foreach ( $this->_schema_items as $i => $item ) {
            $product = array_merge( [ '@type' => 'Product' ], $item );
            $elements[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'item'     => $product,
            ];
        }
        $data = [
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'name'            => $list_name,
            'numberOfItems'   => count( $elements ),
            'itemListElement' => $elements,
        ];
        // JSON_UNESCAPED_UNICODE für Umlaute; Slashes bewusst NICHT unescaped,
        // damit in JSON-String-Werten vorkommende "</script>"-Sequenzen als
        // "<\/script>" escaped bleiben (Sicherheit gegen HTML-Parse-Breakout).
        $json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE );
        if ( $json === false ) return '';
        return '<script type="application/ld+json" class="vergleich-jsonld">' . $json . '</script>';
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

    // Bricks-Typography-Struktur → Inline-CSS-Fragment. Richtet sich nach
    // Bricks\Assets::typography-Case: kebab-case Keys, Strings, Font-Size/
    // Letter-Spacing mit Einheit, Font-Family mit Quotes + Fallback, Text-
    // Shadow als Struct. Überspringt Arrays für simple String-Props wie
    // Bricks selbst. Defaults greifen nur wenn der Wert fehlt.
    private function format_typography( $typo, $defaults = [] ) {
        $out = '';

        if ( is_array( $typo ) ) {
            // Font-Family: Quotes + optionale Fallback-Familie
            if ( ! empty( $typo['font-family'] ) && is_string( $typo['font-family'] ) ) {
                $family   = $this->sanitize_css_value( $typo['font-family'] );
                $fallback = ! empty( $typo['fallback'] ) && is_string( $typo['fallback'] ) ? ', ' . $this->sanitize_css_value( $typo['fallback'] ) : '';
                if ( $family !== '' ) $out .= 'font-family:"' . esc_attr( $family ) . '"' . esc_attr( $fallback ) . ';';
            }

            // Color
            if ( ! empty( $typo['color'] ) ) {
                $resolved = $this->resolve_color( $typo['color'] );
                if ( $resolved !== '' ) $out .= 'color:' . esc_attr( $resolved ) . ';';
            }

            // Text-Shadow: Struct { values: { offsetX, offsetY, blur }, color }
            if ( ! empty( $typo['text-shadow'] ) && is_array( $typo['text-shadow'] ) ) {
                $ts   = $typo['text-shadow'];
                $vals = is_array( $ts['values'] ?? null ) ? $ts['values'] : [];
                $parts = [];
                foreach ( [ 'offsetX', 'offsetY', 'blur' ] as $k ) {
                    $v = $vals[ $k ] ?? '';
                    $parts[] = ( $v === '' || $v === null ) ? '0' : ( is_numeric( $v ) ? $v . 'px' : $this->sanitize_css_value( (string) $v ) );
                }
                $c = ! empty( $ts['color'] ) ? $this->resolve_color( $ts['color'] ) : '';
                if ( $c !== '' ) $parts[] = $c; else $parts[] = 'transparent';
                $out .= 'text-shadow:' . esc_attr( implode( ' ', $parts ) ) . ';';
            }

            // Alle weiteren Props: direkter CSS-Property-Name. Arrays werden
            // übersprungen (wie Bricks-Core). Font-Size/Letter-Spacing bekommen
            // bei reinen Zahlen 'px' angehängt.
            $skip = [ 'font-family', 'fallback', 'color', 'text-shadow', 'font-variants' ];
            foreach ( $typo as $prop => $val ) {
                if ( in_array( $prop, $skip, true ) ) continue;
                if ( $val === '' || $val === null ) continue;
                if ( is_array( $val ) ) continue;
                $str = (string) $val;
                if ( $prop === 'font-size' || $prop === 'letter-spacing' ) {
                    if ( is_numeric( $str ) ) $str .= 'px';
                }
                $out .= esc_attr( (string) $prop ) . ':' . esc_attr( $this->sanitize_css_value( $str ) ) . ';';
            }
        }

        foreach ( [ 'color', 'font-size', 'font-weight', 'line-height' ] as $p ) {
            if ( ! empty( $defaults[ $p ] ) && strpos( $out, $p . ':' ) === false ) {
                $out .= $p . ':' . esc_attr( (string) $defaults[ $p ] ) . ';';
            }
        }
        return $out;
    }

    // Bricks-Box-Shadow-Struktur → Inline-CSS-Fragment. Bricks kann den Wert
    // entweder als { values: "x y blur spread color", inset: bool } oder als
    // { offsetX, offsetY, blur, spread, color, inset } liefern.
    private function format_box_shadow( $shadow, $default = '' ) {
        if ( ! is_array( $shadow ) ) {
            return $default !== '' ? 'box-shadow:' . esc_attr( $default ) . ';' : '';
        }
        if ( isset( $shadow['values'] ) && is_string( $shadow['values'] ) && trim( $shadow['values'] ) !== '' ) {
            $val = $this->sanitize_css_value( $shadow['values'] );
            if ( ! empty( $shadow['inset'] ) ) $val .= ' inset';
            return 'box-shadow:' . esc_attr( $val ) . ';';
        }
        $ox = isset( $shadow['offsetX'] ) && $shadow['offsetX'] !== '' ? $this->format_length( $shadow['offsetX'] ) : '';
        $oy = isset( $shadow['offsetY'] ) && $shadow['offsetY'] !== '' ? $this->format_length( $shadow['offsetY'] ) : '';
        $bl = isset( $shadow['blur'] )    && $shadow['blur']    !== '' ? $this->format_length( $shadow['blur'] )    : '';
        $sp = isset( $shadow['spread'] )  && $shadow['spread']  !== '' ? $this->format_length( $shadow['spread'] )  : '';
        $co = ! empty( $shadow['color'] ) ? $this->resolve_color( $shadow['color'] ) : '';
        if ( $ox === '' && $oy === '' && $bl === '' && $sp === '' && $co === '' ) {
            return $default !== '' ? 'box-shadow:' . esc_attr( $default ) . ';' : '';
        }
        $parts = [ $ox !== '' ? $ox : '0', $oy !== '' ? $oy : '0' ];
        if ( $bl !== '' ) $parts[] = $bl;
        if ( $sp !== '' ) $parts[] = $sp;
        if ( $co !== '' ) $parts[] = $co;
        $val = implode( ' ', $parts );
        if ( ! empty( $shadow['inset'] ) ) $val .= ' inset';
        return 'box-shadow:' . esc_attr( $val ) . ';';
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
            /* overflow: clip (statt hidden) erhält das visuelle Clipping für den
               Border-Radius, erzeugt aber KEINEN Scroll-Container — so bleibt
               position: sticky auf inneren Zellen relativ zum Seiten-Scroll
               funktionsfähig. (Chrome 90+, FF 81+, Safari 15.5+.) */
            overflow: clip;
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
        /* Info-Icon-Button + Tooltip via ::after (content: attr()). Tooltip
           erscheint unter dem Icon, damit er nicht in die Nachbar-Card clipt. */
        .vergleich-tooltip {
            position: relative;
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
            /* Position: nach UNTEN statt rechts. Grund: rechts von der Label-
               Spalte beginnt direkt die erste Card, deren overflow:clip einen
               eigenen Stacking-Context erzeugt und den Tooltip visuell clippt.
               Nach unten bleibt der Tooltip vertikal in der Label-Spalten-Höhe
               und umgeht das Problem. */
            position: absolute;
            left: 0;
            top: calc(100% + 6px);
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

        /* === Lightbox-Zelle ===
           Die .vergleich-zelle selbst bleibt flex (Plugin-default) — darin
           liegt nur ein kompakter Trigger-Button. Der eigentliche Inhalt
           wohnt im <dialog>, das via showModal() in den Browser-Top-Layer
           gerendert wird: immun gegen overflow:clip/scale/transform der
           Umgebung, eigener Stacking-Context, und die Kinder im Dialog-Body
           stapeln natürlich als normaler Block-Flow — genau das, was User
           erwarten, wenn sie Shortcodes oder mehrzeilige HTML-Blöcke
           reinstecken. */
        /* Trigger-Button: nur Layout-Reset + Cursor. Farben / Typografie /
           Border / Padding / Shadow kommen entweder aus den Bricks-Preset-
           Klassen (bricks-background-primary etc.) oder aus Inline-Overrides
           — exakt derselbe Pattern wie render_cell_button. */
        .vergleich-lightbox-trigger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin: 0;
            cursor: pointer;
            line-height: 1.3;
            transition: background-color .15s ease, color .15s ease, border-color .15s ease, box-shadow .15s ease, transform .05s ease;
        }
        .vergleich-lightbox-trigger.circle {
            border-radius: 999px;
        }
        .vergleich-lightbox-trigger:hover {
            filter: brightness(0.95);
        }
        .vergleich-lightbox-trigger:active {
            transform: translateY(1px);
        }
        .vergleich-lightbox-trigger:focus-visible {
            outline: 2px solid currentColor;
            outline-offset: 2px;
        }
        /* Icon-Wrapper-Styling: Bricks-render_icon setzt die Klasse je nach
           Icon-Typ an unterschiedlichen Stellen ins DOM — bei Font-Icons auf
           einen Container-<span> mit einem <i>-Child, bei SVG-Uploads direkt
           auf das <svg>-Element selbst (ohne Wrapper). Deshalb müssen unsere
           Selektoren BEIDE Varianten treffen.
           Gemeinsame Wrapper-Eigenschaften: Flex-Box, Breite/Höhe/Line-Height
           kommen aus dem Inline-Style (siehe PHP). */
        .vergleich-lightbox-trigger__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            line-height: 1;
        }
        /* SVG-Uploads kommen oft mit nativen width/height-Attributen (z.B.
           512×512). Das SVG ist selbst das Icon-Element → inline width/height
           vom Plugin greift direkt darauf. Wenn das SVG nested in einem
           Wrapper sitzt (manche Icon-Renderings), bleibt der Wrapper
           maßgebend und das SVG-Child wird auf 100% gezogen. */
        .vergleich-lightbox-trigger__icon > svg {
            width: 100% !important;
            height: 100% !important;
            display: block;
        }
        /* Farb-Inheritance in SVG-Kindern:
           Wenn die Icon-Farbe per Plugin-Feld gesetzt ist, bekommt das SVG
           bzw. der Wrapper inline "fill: <color>". Kinder mit hartcodiertem
           fill="#000" oder style="fill:..." würden das normalerweise
           ignorieren — deshalb erzwingen wir mit !important, dass alle
           SVG-Descendant-Elemente fill vom Root erben.
           Gilt sowohl, wenn das SVG SELBST der Wrapper ist
           (svg.vergleich-lightbox-trigger__icon *) als auch, wenn das SVG
           ein Child des Wrappers ist (.vergleich-lightbox-trigger__icon > svg *). */
        svg.vergleich-lightbox-trigger__icon *,
        .vergleich-lightbox-trigger__icon > svg * {
            fill: inherit !important;
        }
        /* Stroke bleibt weicher (kein !important), damit Outline-Icons, die
           explizit eine Strichfarbe am Path gesetzt haben, sie behalten. */
        svg.vergleich-lightbox-trigger__icon *,
        .vergleich-lightbox-trigger__icon > svg * {
            stroke: inherit;
        }
        /* Font-Icons (Font Awesome, Ionicons, …) — <i>/.bricks-icon Child im
           Wrapper. color vom Wrapper wird geerbt, font-size ebenfalls. */
        .vergleich-lightbox-trigger__icon > i,
        .vergleich-lightbox-trigger__icon > .bricks-icon {
            font-size: inherit !important;
            line-height: 1 !important;
        }

        .vergleich-lightbox-dialog {
            /* Deterministisches Positionieren: Browser-UA-Defaults für
               <dialog> variieren (Chrome/Firefox/Safari setzen unterschiedlich
               `inset` und `margin`). Wir legen es explizit fest, damit die
               Positionsklassen vorhersagbar greifen.
               max-width/max-height-Logik:
                 - User-Wert via --vgl-lb-max-w / --vgl-lb-max-h (leer ⇒ Default)
                 - Zusätzlich hart auf Viewport − 32px kappen, damit der Dialog
                   auf Mobile nie über den Rand hinausragt. */
            position: fixed;
            padding: 0;
            border: none;
            background: transparent;
            /* Dialog rendert IMMER auf der konfigurierten Breite — nur auf
               schmalen Viewports wird zusätzlich auf "100vw − 32px" gekappt,
               damit er nie über den Bildschirmrand hinausläuft. Vorher nur
               max-width → bei wenig Content schrumpfte der Dialog auf
               Content-Breite, obwohl der User eine feste Breite eingestellt
               hatte. Das war UX-mäßig verwirrend. */
            width: min(var(--vgl-lb-max-w, 640px), calc(100vw - 32px));
            max-width: calc(100vw - 32px);
            max-height: min(var(--vgl-lb-max-h, calc(100vh - 32px)), calc(100vh - 32px));
            color: inherit;
            /* Textstyle aus der Umgebung erben. */
            font: inherit;
            line-height: 1.55;
        }
        /* Positionsklassen. transform-basiertes Centering: bulletproof,
           weil es keinen UA-Default-Zusammenspielkram mit inset/margin gibt.
           !important auf top/left/transform, weil das UA-Stylesheet für
           :modal sehr aggressiv ist (setzt inset-block-start/end + margin:auto,
           und manche Browser respektieren unsere Overrides je nach Reihenfolge
           des Cascade nicht zuverlässig). */
        .vergleich-lightbox-dialog.is-pos-center {
            top: 50% !important;
            left: 50% !important;
            right: auto !important;
            bottom: auto !important;
            transform: translate(-50%, -50%) !important;
            margin: 0 !important;
        }
        .vergleich-lightbox-dialog.is-pos-top {
            top: var(--vgl-lb-offset, 24px) !important;
            left: 50% !important;
            right: auto !important;
            bottom: auto !important;
            transform: translateX(-50%) !important;
            margin: 0 !important;
        }
        .vergleich-lightbox-dialog.is-pos-bottom {
            top: auto !important;
            left: 50% !important;
            right: auto !important;
            bottom: var(--vgl-lb-offset, 24px) !important;
            transform: translateX(-50%) !important;
            margin: 0 !important;
        }
        /* Bottom-Sheet auf Mobile: volle Breite, oben abgerundet, unten am Rand. */
        @media (max-width: 560px) {
            .vergleich-lightbox-dialog.is-pos-bottom {
                bottom: 0 !important;
                left: 0 !important;
                right: 0 !important;
                transform: none !important;
                margin: 0 !important;
                width: 100vw !important;
                max-width: 100vw !important;
            }
            .vergleich-lightbox-dialog.is-pos-bottom .vergleich-lightbox-dialog__inner {
                border-radius: 14px 14px 0 0;
            }
        }
        .vergleich-lightbox-dialog::backdrop {
            background: rgba(15, 23, 42, 0.55);
            /* Nur backdrop-filter wenn unterstützt — sonst bleibt die rgba
               oben als Fallback. */
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
        }
        .vergleich-lightbox-dialog__inner {
            position: relative;
            background: #fff;
            border-radius: 10px;
            padding: 24px 28px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
            max-height: inherit;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        .vergleich-lightbox-dialog__title {
            margin: 0 0 12px;
            padding-right: 32px;
            font-size: 1.25rem;
            line-height: 1.3;
            font-weight: 600;
        }
        .vergleich-lightbox-dialog__body {
            /* KEIN flex, KEIN grid mit !important — damit Shortcodes, Listen
               und HTML-Blöcke im normalen Block-Flow untereinander stapeln.
               Das war der Grund für diesen Zelltyp. */
            display: block;
        }
        /* Inhaltsblöcke im Body dürfen atmen: Standard-Abstände wie in
           einem normalen Post-Content-Bereich. */
        .vergleich-lightbox-dialog__body > * + * {
            margin-top: 12px;
        }
        .vergleich-lightbox-dialog__body img {
            max-width: 100%;
            height: auto;
        }
        .vergleich-lightbox-close {
            position: absolute;
            top: 8px;
            right: 10px;
            width: 32px;
            height: 32px;
            padding: 0;
            margin: 0;
            border: none;
            background: transparent;
            font-size: 28px;
            line-height: 1;
            cursor: pointer;
            color: #64748b;
            border-radius: 4px;
            transition: background-color .15s ease, color .15s ease;
        }
        .vergleich-lightbox-close:hover {
            background-color: #f1f5f9;
            color: #0f172a;
        }
        .vergleich-lightbox-close:focus-visible {
            outline: 2px solid var(--vgl-lb-trigger-color, #2563eb);
            outline-offset: 1px;
        }
        @media (max-width: 480px) {
            .vergleich-lightbox-dialog__inner {
                padding: 20px 18px;
                border-radius: 8px;
            }
            .vergleich-lightbox-dialog__title {
                font-size: 1.125rem;
            }
        }

        /* === Gutscheincode-Zelle ===
           .vergleich-zelle bleibt flex-centered (Plugin-Default), der innere
           .vergleich-coupon-Container gibt einen eigenen vertikalen Stack —
           Code-Box + Kopier-Button oben, Shop-Button darunter.

           !important auf die Layout-Eigenschaften (display/flex-direction),
           weil der Bricks-Canvas-Iframe in seltenen Faellen Reset-/Theme-
           Regeln mit gleicher Specificity aber spaeterer Kaskadenposition
           einschleust. Frontend sieht ok aus, Canvas kollabiert das Layout —
           mit !important greift die Regel in beiden Kontexten deterministisch. */
        .vergleich-coupon {
            display: flex !important;
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 8px;
            width: 100%;
            max-width: 100%;
        }
        .vergleich-coupon__label {
            display: block;
            font-size: 0.85em;
            opacity: 0.75;
            text-align: center;
        }
        .vergleich-coupon__row {
            display: flex !important;
            flex-direction: row !important;
            align-items: stretch !important;
            gap: 6px;
            width: 100%;
            min-width: 0;
        }
        .vergleich-coupon__code {
            flex: 1 1 auto !important;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            min-width: 0;
            padding: 8px 12px;
            border: 2px dashed #94a3b8;
            border-radius: 6px;
            background: #f8fafc;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-weight: 700;
            font-size: 0.95em;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            user-select: all;
            -webkit-user-select: all;
        }
        .vergleich-coupon__copy {
            flex: 0 0 auto !important;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: auto;
            padding: 0;
            margin: 0;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #fff;
            color: #334155;
            cursor: pointer;
            transition: background-color .15s ease, color .15s ease, border-color .15s ease, transform .05s ease;
        }
        .vergleich-coupon__copy:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
            color: #0f172a;
        }
        .vergleich-coupon__copy:active {
            transform: translateY(1px);
        }
        .vergleich-coupon__copy:focus-visible {
            outline: 2px solid #2563eb;
            outline-offset: 2px;
        }
        .vergleich-coupon__copy-icons {
            position: relative;
            display: inline-flex !important;
            width: 18px !important;
            height: 18px !important;
            flex: 0 0 auto !important;
        }
        .vergleich-coupon__copy-icon {
            position: absolute;
            inset: 0;
            width: 100% !important;
            height: 100% !important;
            display: block;
            transition: opacity .15s ease, transform .15s ease;
        }
        .vergleich-coupon__copy-icon.is-success {
            opacity: 0;
            transform: scale(0.6);
            color: #16a34a;
        }
        /* Success-State: default-Icon ausblenden, Haekchen einblenden, Code
           kurz hellgruen hinterlegen. JS setzt .is-copied auf den Button. */
        .vergleich-coupon__copy.is-copied {
            background: #dcfce7;
            border-color: #86efac;
            color: #15803d;
        }
        .vergleich-coupon__copy.is-copied .vergleich-coupon__copy-icon.is-default {
            opacity: 0;
            transform: scale(0.6);
        }
        .vergleich-coupon__copy.is-copied .vergleich-coupon__copy-icon.is-success {
            opacity: 1;
            transform: scale(1);
        }
        .vergleich-coupon__row:has(.vergleich-coupon__copy.is-copied) .vergleich-coupon__code {
            border-color: #16a34a;
            background: #f0fdf4;
            transition: border-color .2s ease, background-color .2s ease;
        }
        /* Shop-Button: Default-Layout, Bricks-Preset-Klassen uebernehmen den
           Rest. User-Overrides per Inline-Style. */
        .vergleich-coupon__shop {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            cursor: pointer;
            transition: background-color .15s ease, color .15s ease, border-color .15s ease, transform .05s ease;
        }
        .vergleich-coupon__shop:hover { filter: brightness(0.95); }
        .vergleich-coupon__shop:active { transform: translateY(1px); }
        .vergleich-coupon__shop:focus-visible {
            outline: 2px solid currentColor;
            outline-offset: 2px;
        }

        /* Toast: Seiten-weites Singleton (JS legt es einmalig im <body> an).
           Fixed unten-rechts, eingeblendet per .is-visible Klasse. */
        .vergleich-toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 100000;
            max-width: calc(100vw - 48px);
            padding: 12px 16px 12px 14px;
            background: #0f172a;
            color: #fff;
            font-size: 0.925rem;
            line-height: 1.4;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            opacity: 0;
            transform: translateY(12px);
            transition: opacity .2s ease, transform .2s ease;
            pointer-events: none;
        }
        .vergleich-toast.is-visible {
            opacity: 1;
            transform: translateY(0);
        }
        .vergleich-toast__icon {
            flex: 0 0 auto;
            width: 18px;
            height: 18px;
            color: #4ade80;
        }
        .vergleich-toast__icon svg {
            width: 100%;
            height: 100%;
            display: block;
        }
        .vergleich-toast__text {
            flex: 1 1 auto;
            min-width: 0;
        }
        @media (max-width: 480px) {
            .vergleich-toast {
                left: 12px;
                right: 12px;
                bottom: 12px;
                max-width: none;
                justify-content: flex-start;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .vergleich-toast,
            .vergleich-coupon__copy-icon,
            .vergleich-coupon__row:has(.vergleich-coupon__copy.is-copied) .vergleich-coupon__code {
                transition: none;
            }
        }

        /* === Sticky-Zeilen === JS-gesteuert (siehe assets/frontend.js →
           bindStickyRows), weil position:sticky auf Grid-Items mit Subgrid in
           Chrome unzuverlässig ist. JS misst beim Scrollen die natürliche
           Position und verschiebt die Zellen per transform:translateY, sodass
           sie visuell am Viewport-Rand hängen bleiben. Die Zellen behalten
           ihre Grid-Slots — kein Layout-Sprung. */
        .vergleich-label.is-sticky-row,
        .vergleich-zelle.is-sticky-row {
            /* Während Sticky aktiv ist: z-index + Hintergrund, damit die Zelle
               über dem Rest schwebt und nicht transparent wird. Das Transform
               selbst kommt aus JS. */
            z-index: 4;
            /* will-change: transform → Browser hebt die Zelle auf einen eigenen
               Compositor-Layer (GPU). Ohne das würden die Sticky-Zellen auf
               Mobile (insbesondere iOS Safari) bei jedem Scroll-Frame neu
               gepaintet, was im Momentum-Scroll sichtbar ruckelt. Mit Layer:
               Scroll läuft auf dem Compositor-Thread, Transform-Writes sind
               reines Compositor-Update. */
            will-change: transform;
        }
        /* .vergleich-root-Prefix hebt Specificity auf 0,3,0, damit eigene
           Row-Highlights (0,2,0) geschlagen werden. Bricks-User-Controls setzen
           unter #brxe-xxx (1,2,0) und gewinnen dann normal. */
        .vergleich-root .vergleich-label.is-sticky-row {
            background-color: #f3f4f6;
        }
        .vergleich-root .vergleich-zelle.is-sticky-row {
            background-color: #fff;
        }
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
            overflow-y: clip; /* clip statt hidden, damit position:sticky auf Zellen durchgereicht wird. */
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
            /* clip statt hidden, damit position:sticky auf Zellen durchgereicht wird. */
            overflow: clip;
            box-sizing: border-box;
            position: relative !important;
            z-index: 1;
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
            background: var(--vgl-score-bg, #111827);
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0,0,0,.18);
            pointer-events: none;
            white-space: nowrap;
            margin: 0 !important;
            flex: 0 0 auto !important;
        }
        /* Typografie-Defaults in eigener, weniger-spezifischer Regel (0,1,0),
           damit Bricks-Typography-Control (Scope-Prefix → 0,2,0) sie problemlos
           überschreiben kann. In der obigen 0,3,0-Regel würden sie gewinnen. */
        .vergleich-score {
            font-size: 14px;
            font-weight: 700;
            line-height: 1;
            color: #fff;
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

        /* === Navigations-Pfeile ===
           position: absolute, Geschwister von .vergleich-wrapper inside
           .vergleich-root. JS setzt per Frame das top, damit die Pfeile beim
           vertikalen Seiten-Scroll im Viewport bleiben (Sticky-Anker an der
           Ranking-Zeile). left/right beziehen sich auf den Root, der die volle
           Tabellenbreite hat. */
        .vergleich-nav {
            position: absolute;
            top: 80px;
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
            left: calc(var(--vgl-label-width, 200px) + var(--vgl-nav-offset, 12px) + var(--vgl-nav-offset-x, 0px));
        }
        .vergleich-nav--next {
            right: calc(var(--vgl-nav-offset, 12px) - var(--vgl-nav-offset-x, 0px));
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

        /* Table-Caption (a11y + SEO). Sichtbar oder sr-only. */
        .vergleich-caption {
            margin: 0 0 12px;
        }
        .vergleich-caption.is-sr-only {
            position: absolute !important;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
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
        /* labelcol-Variante: nur so breit wie die Label-Spalte, links
           ausgerichtet — sitzt optisch direkt ueber der Label-Spalte. */
        .vergleich-counter.is-align-labelcol {
            width: var(--vgl-label-width, 200px);
            max-width: 100%;
            text-align: left;
        }
        /* Inline-Variante im Spacer der Produkt-Label-Zeile: kein eigenes
           Padding/Min-Height, stattdessen vom Flex-Spacer geregelt. Wird
           automatisch vertikal zentriert, weil der Spacer align-items:
           center vererbt. */
        .vergleich-product-label-row__spacer .vergleich-counter {
            padding: 0;
            min-height: 0;
            width: 100%;
        }

        /* Wenn Nav-Pfeile aktiv sind, Scrollbar ausblenden — Scrollen bleibt
           per Pfeil-Click, Wheel und Touch-Swipe moeglich. */
        .vergleich-wrapper.has-nav .vergleich-scroll {
            scrollbar-width: none;
            -ms-overflow-style: none;
            /* Card-by-card Snapping auch bei Touch-Swipe und Wheel. Gleiche
               Schrittgroesse wie die Nav-Pfeile: eine Card pro Gesture.
               Nur im Card-Step-Modus aktiv — View-Step-Modus (per Button
               eine Viewport-Breite) behaelt freies Scrollen zwischen den
               Cards, damit mehrere Cards auf einmal sichtbar bleiben. */
            scroll-behavior: smooth;
            /* overscroll-behavior-x: contain verhindert, dass ein schneller
               horizontaler Touch-Swipe auf iOS den Browser-Back-Gesture
               oder das Page-Scroll triggert. */
            overscroll-behavior-x: contain;
        }
        .vergleich-wrapper.has-nav.vgl-nav-step-card .vergleich-scroll {
            scroll-snap-type: x mandatory;
        }
        .vergleich-wrapper.has-nav.vgl-nav-step-card .vergleich-card {
            scroll-snap-align: start;
            /* snap-stop: always → auch bei schnellen Swipes stoppt der
               Browser an der naechsten Card, statt mehrere auf einmal zu
               ueberspringen. Das ist der „eine Swipe = eine Card"-Effekt
               den die User erwarten. */
            scroll-snap-stop: always;
        }
        .vergleich-wrapper.has-nav .vergleich-scroll::-webkit-scrollbar {
            display: none;
            width: 0;
            height: 0;
        }

        /* Root-Container: nimmt Wrapper + Expand-Button auf */
        .vergleich-root {
            display: block;
            position: relative;
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
            /* Grid nur so hoch wie visible-rows + 1 Peek-Zeile machen. Alle
               weiteren collapsiblen Zeilen sind display:none und brauchen
               keine Grid-Spur, würden aber sonst durch --vgl-row-min einen
               großen leeren Block unterhalb der letzten Zeile erzeugen. */
            grid-template-rows: repeat(var(--vgl-row-count-fade, 1), minmax(var(--vgl-row-min, 20px), auto)) !important;
            position: relative;
            overflow: clip;
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
        /* Sticky-Header (Finanzfluss-Pattern): die Produkt-Label-Zeile bleibt
           beim vertikalen Scrollen oben am Viewport-Rand hängen. Funktioniert
           hier sauber, weil .vergleich-product-label-row ein direkter Child von
           .vergleich-root ist — nicht im overflow:hidden-Wrapper. Gap wird auf
           den margin-bottom verschoben, damit keine Lücke zwischen Sticky-Header
           und dem Rest entsteht. */
        .vergleich-root.has-sticky-product-labels .vergleich-product-label-row {
            position: sticky;
            top: var(--vgl-product-labels-sticky-top, 0px);
            z-index: 6;
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

        /* === PIN-BUTTON (Spalte anpinnen) === */
        .vergleich-pin {
            position: absolute;
            top: var(--vgl-pin-offset-y, 8px);
            right: var(--vgl-pin-offset-x, 8px);
            z-index: 6;
            width: var(--vgl-pin-size, 28px);
            height: var(--vgl-pin-size, 28px);
            padding: 0;
            margin: 0;
            border: 0;
            background: transparent;
            color: var(--vgl-pin-color, #9ca3af);
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            line-height: 1;
            border-radius: 6px;
            transition: color .15s ease, background-color .15s ease;
        }
        .vergleich-wrapper.has-pin.can-pin .vergleich-pin { display: inline-flex; }
        .vergleich-pin:hover {
            color: var(--vgl-pin-color-active, #111827);
            background: rgba(0, 0, 0, .05);
        }
        .vergleich-pin:focus-visible {
            outline: 2px solid var(--vgl-pin-color-active, #111827);
            outline-offset: 2px;
        }
        .vergleich-pin__icon { width: 16px; height: 16px; display: block; }
        .vergleich-pin__icon--on { display: none; }
        .vergleich-card.is-pinned {
            z-index: 10 !important;
            background: #fff;
            box-shadow: 6px 0 8px -4px rgba(0, 0, 0, .12);
        }
        @supports (animation-timeline: scroll()) {
            .vergleich-scroll {
                scroll-timeline-name: --vgl-cards;
                scroll-timeline-axis: inline;
            }
            .vergleich-card.is-pinned {
                animation-name: vgl-pin-sticky;
                animation-duration: 1ms;
                animation-timing-function: linear;
                animation-fill-mode: both;
                animation-timeline: --vgl-cards;
            }
            @keyframes vgl-pin-sticky {
                to { transform: translateX(var(--vgl-scroll-max, 0px)); }
            }
        }
        .vergleich-product-label-item.is-pinned-label {
            z-index: 2;
            position: relative;
            will-change: transform;
        }
        /* Sicherheit: Pin-Button bleibt interaktiv, auch wenn die Card Sticky
           ist und in einem neuen Stacking-Context liegt. */
        .vergleich-pin { pointer-events: auto; }
        .vergleich-card.is-pinned .vergleich-pin { z-index: 7; }
        .vergleich-card.is-pinned .vergleich-pin { color: var(--vgl-pin-color-active, #111827); }
        .vergleich-card.is-pinned .vergleich-pin__icon--off { display: none; }
        .vergleich-card.is-pinned .vergleich-pin__icon--on  { display: block; }
        </style>';
    }

}
