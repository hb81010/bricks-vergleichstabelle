# BricksTable

Custom Bricks Element für Produktvergleichstabellen, bei denen **Produkte als Spalten** statt als Zeilen dargestellt werden – wie z.B. bei Finanzfluss.

## Installation

1. Die ZIP-Datei `bricks-vergleich.zip` im WordPress-Admin unter **Plugins → Neues Plugin → Hochladen** installieren
2. Plugin aktivieren
3. Im Bricks-Editor erscheint eine neue Element-Kategorie „Vergleich" mit zwei Elementen:
   - **Vergleich (Spalten)** – der Wrapper
   - **Vergleich-Zeile** – eine Merkmal-Zeile

## Schnellstart

### 1. Vergleich-Element einfügen

Im Bricks-Editor in der Element-Liste nach **„Vergleich (Spalten)"** suchen und auf die Seite ziehen.

Das Element kommt mit 3 Start-Zeilen (Bild, Name, Preis) vorkonfiguriert.

### 2. Query Loop aktivieren

Wrapper anklicken → rechtes Panel → **Query Loop (∞)** einschalten.
- Post Type: dein Produkt-CPT (z.B. WooCommerce Products oder eigener CPT)
- Posts per page: beliebig

Pro Produkt wird jetzt eine Spalte erzeugt.

### 3. Zeilen anpassen

Im Struktur-Panel siehst du die **Vergleich-Zeile**-Elemente. Jede Zeile hat:
- **Label** – erscheint links in der Merkmal-Spalte
- **Inhalt** – beliebige Bricks-Elemente (Text, Bild, Button, Icon...) mit dynamischen Daten

Beispiel-Inhalt pro Zeile:
- Bild-Zeile → Image-Element mit `{featured_image}`
- Preis-Zeile → Text mit `{woo_product_price}`
- ACF-Feld → Text mit `{acf:dein_feld}`
- Button → mit dynamischem Link

### 4. Weitere Zeilen hinzufügen

Im Struktur-Panel: Rechtsklick auf Vergleich-Element → **Kind hinzufügen → Vergleich-Zeile**.
Dann das Label setzen und Inhalt reinziehen. Fertig.

## Element-Einstellungen

### Wrapper (Vergleich)

**Layout-Tab:**
- Breite Label-Spalte (default: 220px)
- Breite Produkt-Spalten (default: 220px)
- Min-Höhe pro Zeile (default: 80px)
- Label-Spalte sticky (default: an)
- Trennlinien (default: an)
- Textausrichtung in Cards

**Styling-Tab:**
- Hintergrundfarben Labels/Cards
- Textfarbe Labels
- Rahmenfarbe
- Eckenradius

### Zeile (Vergleich-Zeile)

- Label (mit Dynamic Data Support)
- Zeile hervorheben (für Testsieger-Merkmale)
- Hintergrundfarbe
- Inhaltsausrichtung

## Wiederverwendbarkeit

**Als Bricks-Komponente speichern** (Bricks 1.12+):
Rechtsklick auf Vergleich-Element → **Als Komponente speichern**.

So kannst du die gesamte Vergleichstabelle mit einem Klick auf beliebigen Seiten einfügen. Änderungen am Komponenten-Master pflegen sich zentral.

## Häufige Anpassungen

### Individuelle Zeilenhöhen

Falls eine Zeile höher sein soll (z.B. Bild-Zeile), im Custom CSS des Wrappers:

```css
%root% .vergleich-labels > *:nth-child(1),
%root% .vergleich-card > *:nth-child(1) {
  min-height: 140px;
}
```

(nth-child(1) = erste Zeile anpassen)

### Testsieger-Spalte hervorheben

Mit einer Condition am Vergleich-Wrapper oder per Custom CSS, wenn ein bestimmtes ACF-Feld gesetzt ist.

## Dateistruktur

```
bricks-vergleich/
├── bricks-vergleich.php       # Plugin-Hauptdatei
├── elements/
│   ├── vergleich.php          # Wrapper-Element
│   └── vergleich-zeile.php    # Zeilen-Element
├── assets/
│   └── vergleich.css          # Frontend-Styles
└── README.md                  # Diese Datei
```

## Voraussetzungen

- WordPress 6.0+
- PHP 7.4+
- Bricks Theme (aktiv)

## Versionshinweise

**1.0.0** – Erste Version

## Lizenz

Für eigene Projekte frei nutzbar.
