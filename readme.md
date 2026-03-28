# WP AI Content Agent

**Version:** 1.0.0
**Anforderungen:** WordPress 6.0+, PHP 8.0+
**Lizenz:** GPL v2 or later

Ein leistungsstarkes WordPress-Plugin, das über eine **Multi-Agenten-Architektur** und die **Claude API (Anthropic)** vollautomatisch hochwertigen, SEO-optimierten Content auf Deutsch generiert.

---

## Überblick

WP AI Content Agent verwendet fünf spezialisierte KI-Agenten, die sequenziell zusammenarbeiten, um Artikel zu erstellen, die nicht nur inhaltlich hochwertig, sondern auch SEO-optimiert und auf deine Zielgruppe zugeschnitten sind.

### Die Agenten-Pipeline

```
🔍 Content-Analyst → 👥 Zielgruppenanalyst → 🔑 Keyword-Rechercheur → 📚 Rechercheur → ✍️ Content-Autor
```

| Agent | Aufgabe |
|-------|---------|
| **Content-Analyst** | Analysiert das Thema: Suchintention, Content-Lücken, Unique Angles, Struktur-Empfehlungen |
| **Zielgruppenanalyst** | Identifiziert Zielgruppen, Bedürfnisse, Schmerzpunkte und die wichtigsten Nutzerfragen |
| **Keyword-Rechercheur** | Erstellt vollständige SEO-Keyword-Strategie: Hauptkeyword, Longtails, LSI, W-Fragen, Meta-Daten |
| **Rechercheur** | Sammelt Fakten, Statistiken, Expertenmeinungen, Trends und Praxisbeispiele |
| **Content-Autor** | Schreibt den finalen Artikel mit Voice-Profil, SEO-Optimierung und vollständiger Struktur |

---

## Features

### ✅ Implementiert

#### Kern-Funktionen
- **Multi-Agenten-Architektur** mit 5 spezialisierten Claude-Agenten
- **Vollautomatische Content-Pipeline**: Von Thema → fertigem WordPress-Post
- **Direktes Erstellen von WordPress-Posts** (Entwurf oder veröffentlicht)
- **Markdown-zu-HTML Konverter** für WordPress-kompatibles Ausgabe-Format
- **Echtzeit-Status-Updates** während der Generierung via AJAX-Polling
- **Agent-Logging** mit detailliertem Protokoll jedes Agentenschritts

#### SEO-Optimierung
- **Automatische Meta-Title und Meta-Description** Extraktion
- **SEO-Score Berechnung** (0–100) basierend auf Keyword-Dichte, Überschriftenstruktur, Wortanzahl
- **Yoast SEO & RankMath Kompatibilität** (Meta-Description wird automatisch gesetzt)
- **Keyword-Integration**: Hauptkeyword in H1, erstem Absatz und natürlich im Text
- **Longtail-, LSI- und W-Fragen-Keywords** in der Recherche

#### Voice Profile System
- **Unbegrenzte Voice Profile** erstellen und verwalten
- **Tonalität, Stil, Beispieltexte** pro Profil konfigurierbar
- **Content-Performance Tracking**: Bewerte generierten Content (1–5 Sterne)
- **Feedback-Loop**: Erfolgreiche Beispiele werden zukünftigen Generierungen als Referenz mitgegeben
- **3 Standard-Profile** vorkonfiguriert: Expertenautorität, Freundlicher Ratgeber, Journalistisch neutral

#### Automatisierung & Cronjobs
- **WP-Cron Integration** für automatische Content-Generierung
- **Flexible Schedules**: Manuell, Stündlich, Zweimal täglich, Täglich, Wöchentlich
- **Job-Verwaltung**: Erstellen, Bearbeiten, Pausieren, Löschen, Sofort ausführen
- **Job-Statistiken**: Ausführungsanzahl, letzte/nächste Ausführung
- **Pro Job konfigurierbar**: Thema, Keywords, Voice-Profil, Kategorie, Post-Status, Mindest-Wortanzahl

#### Einstellungen & Konfiguration
- **Claude API-Schlüssel** mit Verbindungstest-Funktion
- **Modell-Auswahl**: Claude Opus 4.6, Sonnet 4.6, Haiku 4.5
- **Globale Parameter**: Max. Tokens, Temperature, Standard-Sprache, Post-Status
- **Pro-Agent Konfiguration**: Eigenes Modell, Max. Tokens, Temperature, System-Prompt
- **Aktivierung/Deaktivierung** einzelner Agenten

#### Admin-Oberfläche
- **Dashboard** mit Statistiken (Artikel, Wörter, Tokens, SEO-Score)
- **Artikel-Generator** Seite mit Echtzeit-Status
- **Content-Verlauf** mit Filterung, Pagination und Details-Modal
- **Automatisierungs-Jobs** Verwaltungsseite
- **Voice Profile** Editor mit Evaluierungs-Tracking
- **Agenten-Konfiguration** mit Tab-Navigation
- **Einstellungen** Seite mit API-Test

#### Datenbankstruktur
- `wp_aica_jobs` – Cronjob-Definitionen
- `wp_aica_content` – Generierter Content mit allen Zwischenergebnissen
- `wp_aica_logs` – Detaillierter Agent-Log pro Generierung

---

## Installation

### Voraussetzungen
- WordPress 6.0 oder höher
- PHP 8.0 oder höher
- Anthropic API-Schlüssel ([console.anthropic.com](https://console.anthropic.com))
- Aktiver WP-Cron (oder Server-Cron als Alternative)

### Schritte

1. **Plugin hochladen**
   ```
   wp-ai-content-agent/ → /wp-content/plugins/
   ```

2. **Plugin aktivieren**
   WordPress Admin → Plugins → WP AI Content Agent → Aktivieren

3. **API-Schlüssel eintragen**
   AI Content Agent → Einstellungen → Claude API-Konfiguration

4. **Verbindung testen**
   Klicke auf „Verbindung testen" um die API-Anbindung zu prüfen

5. **Ersten Artikel generieren**
   AI Content Agent → Artikel generieren → Thema eingeben → Los!

---

## Konfiguration

### API-Einstellungen

| Einstellung | Beschreibung | Standard |
|------------|--------------|---------|
| API-Schlüssel | Anthropic API Key (sk-ant-...) | – |
| Modell | Standard-Claude-Modell | claude-opus-4-6 |
| Max. Tokens | Maximale Ausgabelänge | 8000 |
| Temperature | Kreativität (0=deterministisch, 1=kreativ) | 0.7 |

### Agenten-Konfiguration

Jeder Agent kann individuell konfiguriert werden:

| Agent | Empfohlene Temperature | Empfohlene Tokens |
|-------|----------------------|-------------------|
| Content-Analyst | 0.3 | 2000 |
| Zielgruppenanalyst | 0.4 | 1500 |
| Keyword-Rechercheur | 0.2 | 2000 |
| Rechercheur | 0.5 | 3000 |
| Content-Autor | 0.8 | 8000+ |

### Voice Profile

Voice Profile steuern den Schreibstil des Content-Autors. Konfigurierbar sind:
- **Name** – Bezeichnung des Profils
- **Beschreibung** – Kurze Zusammenfassung für wen/was
- **Tonalität** – Stimmung und Atmosphäre (z.B. „professionell, sachlich")
- **Schreibstil** – Konkrete Stil-Anweisungen
- **Beispieltext** – Referenz-Absatz für die KI (wichtigste Einstellung!)
- **Zu vermeiden** – Explizit auszuschließende Formulierungen
- **Evaluierungen** – Bewertete Content-Beispiele als Feedback

---

## Verwendung

### Artikel manuell generieren

1. **AI Content Agent → Artikel generieren**
2. Thema eingeben (je präziser, desto besser)
3. Optional: Keywords, Voice-Profil, Kategorie, Post-Status
4. „Artikel jetzt generieren" klicken
5. Agenten-Pipeline läuft durch (ca. 2–5 Minuten)
6. Post wird automatisch erstellt

### Cronjob einrichten

1. **AI Content Agent → Automatisierungs-Jobs → Neuen Job erstellen**
2. Name, Thema, Keywords eingeben
3. Schedule wählen (täglich, wöchentlich, etc.)
4. Voice-Profil und Post-Status konfigurieren
5. Speichern – Job wird automatisch geplant
6. Optional: „Jetzt ausführen" für sofortigen Test

### Content-Verlauf

- Alle generierten Artikel mit Status, Wortanzahl, SEO-Score, Token-Verbrauch
- Direktlink zum WordPress-Post-Editor
- Detail-Ansicht mit Agent-Logs
- Filterung nach Status

---

## Technische Architektur

### Plugin-Struktur

```
wp-ai-content-agent/
├── wp-ai-content-agent.php          # Haupt-Plugin-Datei mit Autoloader
├── uninstall.php                     # Aufräumen bei Deinstallation
├── includes/
│   ├── class-installer.php          # DB-Tabellen, Standard-Optionen
│   ├── class-plugin.php             # Singleton, Hooks, AJAX-Handler
│   ├── class-settings.php           # Settings-Verwaltung (Cache + API)
│   ├── class-api-client.php         # Claude API HTTP-Client
│   ├── class-orchestrator.php       # Agent-Koordination & Workflow
│   ├── class-cron-manager.php       # WP-Cron-Integration
│   ├── class-post-publisher.php     # WordPress Post-Erstellung
│   └── agents/
│       ├── class-agent-base.php     # Abstrakte Basis-Klasse
│       ├── class-content-analyzer.php
│       ├── class-audience-analyzer.php
│       ├── class-keyword-researcher.php
│       ├── class-researcher.php
│       └── class-content-writer.php
├── admin/
│   ├── class-admin.php              # Admin-Controller, Menü, Assets
│   ├── templates/
│   │   ├── dashboard.php
│   │   ├── generate.php
│   │   ├── settings.php
│   │   ├── agents.php
│   │   ├── voices.php
│   │   ├── jobs.php
│   │   └── content.php
│   └── assets/
│       ├── css/admin.css
│       └── js/admin.js
└── readme.md
```

### Technologie-Stack

- **KI-Backend**: Anthropic Claude API (Modelle: Opus 4.6, Sonnet 4.6, Haiku 4.5)
- **Kommunikation**: WordPress `wp_remote_post()` (kein cURL direkt)
- **Frontend**: jQuery (WordPress Standard), CSS Custom Properties
- **Datenbank**: WordPress-native `$wpdb` mit Custom Tables
- **Automatisierung**: WordPress WP-Cron mit custom Schedules
- **Sicherheit**: WordPress Nonces, `current_user_can()`, Sanitization

### Datenbankschema

```sql
-- Cronjob-Definitionen
wp_aica_jobs (id, name, topic, keywords, voice_id, post_status,
              category_id, schedule, cron_hook, active, last_run,
              next_run, run_count, settings, ...)

-- Generierter Content (inkl. aller Zwischenergebnisse)
wp_aica_content (id, job_id, topic, keywords, voice_id, status,
                 wp_post_id, analysis_result, audience_result,
                 keyword_result, research_result, final_content,
                 meta_title, meta_description, word_count, seo_score,
                 tokens_used, duration_sec, error_message, ...)

-- Agent-Logs
wp_aica_logs (id, content_id, agent, level, message, data, ...)
```

---

## Geplante Features (Roadmap)

### v1.1 – Qualitäts-Upgrades
- [ ] **Bildgenerierung**: Integration mit DALL-E / Stable Diffusion für Featured Images
- [ ] **Interner Link-Suggester**: Automatische Vorschläge für interne Verlinkungen
- [ ] **Content-Revision**: Bestehende Posts analysieren und überarbeiten lassen
- [ ] **A/B-Testing**: Zwei Versionen eines Artikels generieren und vergleichen

### v1.2 – SEO-Erweiterungen
- [ ] **Yoast/RankMath Deep Integration**: Direkte Score-Anpassung
- [ ] **Schema Markup**: Automatisches FAQ-Schema, Article-Schema
- [ ] **Google Search Console Sync**: Performance-Daten als Feedback-Loop
- [ ] **Keyword-Tracking**: Ranking-Entwicklung für generierte Keywords

### v1.3 – Automatisierung
- [ ] **Themen-Pool**: Vordefinierte Themen-Listen für Job-Rotation
- [ ] **Content-Kalender**: Redaktionsplan-Integration
- [ ] **Automatische Kategorisierung**: KI wählt Kategorie basierend auf Thema
- [ ] **Multi-Site Support**: Artikel für verschiedene WordPress-Sites generieren

### v1.4 – KI-Enhancements
- [ ] **Adaptive Thinking** für komplexe Recherche-Aufgaben (Anthropic Feature)
- [ ] **Prompt-Caching**: Kosten-Optimierung bei wiederholten System-Prompts
- [ ] **Streaming-Anzeige**: Echtzeit-Textausgabe statt Polling
- [ ] **Custom Agent-Workflows**: Eigene Agenten-Pipelines definieren

### v2.0 – Enterprise Features
- [ ] **Team-Rollen**: Redakteur kann generieren, Admin publiziert
- [ ] **Review-Workflow**: Generierter Content erst nach Freigabe publiziert
- [ ] **API-Endpunkt**: REST API für externe Integrationen
- [ ] **Webhooks**: Benachrichtigungen bei fertigem Content

---

## Bekannte Einschränkungen

1. **Keine Echtzeit-Fakten**: Claude hat einen Wissens-Cutoff; aktuelle Ereignisse (< 1 Jahr) sollten manuell überprüft werden
2. **Wartezeit**: Ein vollständiger Durchlauf dauert 2–8 Minuten (abhängig von Modell und Thema)
3. **WP-Cron-Abhängigkeit**: Standard-WP-Cron ist nicht 100% zuverlässig ohne externen Cron-Trigger
4. **Token-Kosten**: Opus 4.6 kostet ca. $0.05–0.20 pro Artikel (je nach Länge)
5. **PHP Timeout**: Sehr lange Generierungen (>120s) können bei manchen Hostern Probleme verursachen – Timeout in `class-api-client.php` anpassen

---

## Kosten-Übersicht (Claude API)

| Modell | Input $/1M | Output $/1M | Ø Kosten/Artikel |
|--------|-----------|------------|-----------------|
| Claude Opus 4.6 | $5.00 | $25.00 | ~$0.10–0.25 |
| Claude Sonnet 4.6 | $3.00 | $15.00 | ~$0.05–0.15 |
| Claude Haiku 4.5 | $1.00 | $5.00 | ~$0.01–0.05 |

*Empfehlung: Opus 4.6 für maximale Qualität, Sonnet 4.6 für Preis-Leistungs-Balance*

---

## Sicherheit

- Alle Eingaben werden mit WordPress-Standard-Funktionen sanitiert (`sanitize_text_field`, `sanitize_textarea_field`, `absint`, etc.)
- AJAX-Anfragen sind mit WordPress Nonces geschützt
- Capability-Checks (`edit_posts`, `manage_options`) für alle sensiblen Operationen
- API-Schlüssel wird in der WordPress Options-Datenbank gespeichert (empfehlung: WordPress Security Keys setzen)
- SQL-Injection-Schutz via `$wpdb->prepare()`
- XSS-Schutz via `esc_html()`, `esc_attr()`, `esc_url()`

---

## Entwicklung

### Lokales Setup

```bash
# WordPress-Entwicklungsumgebung (z.B. Local WP, DDEV)
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/soulsites/wp-ai-content-agent.git
cd wp-ai-content-agent

# Plugin aktivieren
wp plugin activate wp-ai-content-agent

# API-Key setzen
wp option update aica_api_key "sk-ant-api03-..."
```

### Eigene Agenten hinzufügen

```php
// 1. Neue Klasse in includes/agents/ erstellen
class My_Agent extends \AICA\Agents\Agent_Base {
    protected string $agent_key  = 'my_agent';
    protected string $agent_name = 'Mein Agent';

    public function run( array $context ) {
        $prompt = "Analysiere: " . $context['topic'];
        return $this->ask( $prompt );
    }
}

// 2. Agent im Orchestrator einbinden (class-orchestrator.php)
$context = $this->run_agent(
    new Agents\My_Agent(),
    $content_id, $context, 'my_result'
);
```

### Hooks & Filter

```php
// Prompt vor API-Aufruf modifizieren
add_filter( 'aica_agent_prompt', function( $prompt, $agent_key, $context ) {
    // Prompt anpassen
    return $prompt;
}, 10, 3 );

// Nach erfolgter Generierung
add_action( 'aica_content_generated', function( $content_id, $post_id, $context ) {
    // Eigene Aktionen
}, 10, 3 );
```

---

## Support & Beiträge

- **Issues**: [github.com/soulsites/wp-ai-content-agent/issues](https://github.com/soulsites/wp-ai-content-agent/issues)
- **Pull Requests**: Willkommen! Bitte erst ein Issue erstellen.
- **Dokumentation**: Dieses README wird aktiv gepflegt

---

## Lizenz

GPL v2 or later – siehe [WordPress Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/).
