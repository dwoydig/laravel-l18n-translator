# CLAUDE.md

Laravel-Package (`dwoydig/l18n-translator`) zur Verwaltung von JSON-Sprachdateien über eine Web-UI mit optionaler DeepL-Übersetzung.
PHP ^8.1, Laravel 10–12. Frontend: Blade + Tailwind CSS (CDN) + Alpine.js.

## Struktur

- `src/TranslationServiceProvider.php` – Registrierung von Config, Services, Views, Routes
- `src/TranslationManager.php` – Lesen/Schreiben der Sprachdateien
- `src/Services/` – Geschäftslogik und externe APIs (z. B. `DeeplService`)
- `src/Http/Controllers/` – dünne Controller, delegieren an Services
- `src/Facades/` – Facades für Services
- `resources/views/` – Blade-Views, wiederverwendbare Teile in `partials/`
- `config/l18n-translator.php` – Package-Konfiguration (Werte via `.env`)

## Architektur: SOLID

- **Single Responsibility**: Jede Klasse hat genau eine Aufgabe. Controller validieren Input und geben Responses zurück – keine Datei-, API- oder Geschäftslogik im Controller. Logik gehört in Services bzw. den `TranslationManager`.
- **Open/Closed**: Neues Verhalten durch neue Klassen/Implementierungen ergänzen, nicht durch wachsende `if`/`switch`-Ketten in bestehenden Klassen.
- **Liskov Substitution**: Implementierungen eines Interfaces müssen austauschbar sein – gleiche Signaturen, gleiche Rückgabetypen, keine zusätzlichen Vorbedingungen.
- **Interface Segregation**: Kleine, fokussierte Interfaces statt großer „Alles-Interfaces“ (z. B. `Translator` getrennt von `UsageProvider`).
- **Dependency Inversion**: Abhängigkeiten per Constructor Injection beziehen und gegen Interfaces typisieren. Bindings im `TranslationServiceProvider` registrieren. Kein `new` für Services und kein `app()`/`resolve()` innerhalb von Klassen, wenn Injection möglich ist.
- Konfigurationswerte nur über `config('l18n-translator.*')` lesen, nie direkt `env()` außerhalb von `config/`.

## Keine Code-Duplizierung (DRY)

- Vor dem Schreiben neuer Logik prüfen, ob es bereits eine Methode, einen Service oder ein Partial dafür gibt – und diese wiederverwenden.
- Wiederkehrende PHP-Logik in private Methoden, Services oder Traits auslagern.
- Wiederkehrendes Markup in `resources/views/partials/` (bzw. Blade-Komponenten) auslagern und per `@include` einbinden – kein Copy-Paste von HTML-Blöcken zwischen Views.
- Wiederkehrende Alpine-Logik in `Alpine.store()` bzw. `Alpine.data()` zentralisieren statt in mehreren `x-data`-Blöcken zu duplizieren.
- Stößt du beim Arbeiten auf bestehende Duplikate im betroffenen Code, fasse sie zusammen.

## Styling: nur Tailwind CSS

- Ausschließlich Tailwind-Utility-Klassen verwenden.
- **Keine eigenen Styles**: keine `<style>`-Blöcke, keine eigenen CSS-Dateien, keine CSS-Klassen-Definitionen.
- Kein statisches `style="..."`. Einzige Ausnahme: zur Laufzeit berechnete Werte, die Tailwind nicht abbilden kann (z. B. `width` eines Fortschrittsbalkens in Prozent).
- Benötigte Erweiterungen (z. B. eigene Animationen oder Farben) über `tailwind.config` im Layout definieren, nicht über eigenes CSS.
- Wiederkehrende Klassen-Kombinationen (Buttons, Karten, Badges) als Partial/Komponente kapseln statt die Klassenliste zu kopieren.

## Type Hints

- Jede Datei beginnt mit `declare(strict_types=1);` (bei neuen Dateien).
- Parameter-, Rückgabe- und Property-Typen immer angeben, wo PHP es erlaubt (inkl. `void`, `?Type`, Union Types).
- Constructor Property Promotion und `readonly` nutzen, wo sinnvoll.
- Wo native Typen nicht ausreichen (z. B. Array-Strukturen), PHPDoc ergänzen: `@param array<string, string> $translations`, `@return list<string>`.
- Keine `mixed`-Typen, wenn ein konkreterer Typ möglich ist.

## Git-Workflow

- **Niemals direkt auf `master` committen.**
- Jedes Feature und jeder Bugfix bekommt einen eigenen Branch, abgezweigt vom aktuellen `master`:
  - Features: `feature/<kurze-beschreibung>` (z. B. `feature/show-deepl-token-usage`)
  - Bugfixes: `bugfix/<kurze-beschreibung>` (z. B. `bugfix/missing-keys-count`)
- Branch-Namen in kebab-case, kurz und sprechend.
- Änderungen gelangen ausschließlich per Pull Request nach `master`.

## Konventionen

- Namespace: `Dwoydig\L18nTranslator\`, PSR-4 unter `src/`.
- Routen-Namen mit Prefix `l18n.`, Views mit Namespace `l18n-translator::`.
- Code-Stil an den umgebenden Code anpassen (PSR-12, kurze PHPDoc-Blöcke über öffentlichen Methoden).
