<?php
namespace AICA\Agents;

defined( 'ABSPATH' ) || exit;

/**
 * Content-Writing Agent.
 * Schreibt den finalen SEO-optimierten Artikel basierend auf allen Agenten-Ergebnissen.
 * Verwendet Voice-Profile für konsistente Tonalität.
 */
class Content_Writer extends Agent_Base {

    protected string $agent_key  = 'content_writer';
    protected string $agent_name = 'Content-Autor';

    public function run( array $context ) {
        $topic           = $context['topic']           ?? '';
        $keywords        = $context['keywords']        ?? '';
        $voice_id        = (int) ( $context['voice_id'] ?? 0 );
        $analysis_result = $context['analysis_result'] ?? '';
        $audience_result = $context['audience_result'] ?? '';
        $keyword_result  = $context['keyword_result']  ?? '';
        $research_result = $context['research_result'] ?? '';
        $job_settings    = $context['job_settings']    ?? [];

        if ( empty( $topic ) ) {
            return new \WP_Error( 'no_topic', 'Kein Thema angegeben.' );
        }

        // Voice-Profil laden
        $voice_section = $this->build_voice_section( $voice_id );

        // Mindest-Wortanzahl aus Job-Einstellungen
        $min_words = max( 600, (int) ( $job_settings['min_word_count'] ?? 800 ) );

        // Kontext aufbauen
        $analysis_excerpt = $analysis_result
            ? $this->truncate( $analysis_result, 600 )
            : 'Keine Analyse vorhanden.';

        $audience_excerpt = $audience_result
            ? $this->truncate( $audience_result, 400 )
            : 'Keine Zielgruppenanalyse vorhanden.';

        $keyword_excerpt = $keyword_result
            ? $this->truncate( $keyword_result, 500 )
            : 'Keine Keyword-Recherche vorhanden.';

        $research_excerpt = $research_result
            ? $this->truncate( $research_result, 1200 )
            : 'Keine Recherche vorhanden.';

        $prompt = <<<PROMPT
Du sollst einen hochwertigen, SEO-optimierten Artikel auf Deutsch verfassen.

## THEMA
{$topic}

## KEYWORDS
{$keywords}

{$voice_section}

## CONTENT-ANALYSE (Struktur & Unique Angles)
{$analysis_excerpt}

## ZIELGRUPPENANALYSE
{$audience_excerpt}

## KEYWORD-STRATEGIE
{$keyword_excerpt}

## RECHERCHE & FAKTEN
{$research_excerpt}

---

## DEINE AUFGABE

Schreibe jetzt einen vollständigen, professionellen Artikel auf Deutsch mit folgenden Anforderungen:

### Inhalt & Qualität:
- **Mindestlänge:** {$min_words} Wörter (gerne mehr für Tiefe)
- Integriere alle wichtigen Fakten und Statistiken aus der Recherche
- Beantworte alle identifizierten Nutzerfragen
- Biete einzigartigen Mehrwert gegenüber typischen Artikeln
- Verwende konkrete Beispiele und praktische Tipps

### SEO-Optimierung:
- Das Primär-Keyword im ersten Absatz, in Überschriften und natürlich im Text
- Semantische Keywords und LSI-Keywords natürlich einbauen
- Optimale Überschriftenstruktur (H2, H3) für Lesbarkeit und SEO
- Interne Verlinkungshinweise als [VERLINKUNG: Thema] markieren
- Am Ende: Vorschlag für Meta-Title und Meta-Description

### Struktur:
- Fesselnde Einleitung (Hook + Versprechen)
- Klar gegliederte Hauptabschnitte mit H2/H3
- Listenelemente und Tabellen wo sinnvoll
- Starkes Fazit mit Handlungsaufforderung (CTA)
- FAQ-Abschnitt am Ende (5-6 Fragen)

### Format:
Verwende Markdown für die Formatierung:
- # für H1 (nur einmal, als Titel)
- ## für H2 Abschnitte
- ### für H3 Unterabschnitte
- **fett** für wichtige Begriffe
- - oder * für Listen
- > für Zitate/Expertenmeinungen

Beginne direkt mit dem Artikel (kein Präambel wie "Hier ist der Artikel:").
PROMPT;

        // Mehr Tokens für den Writer
        $params = [
            'max_tokens'  => $this->settings['max_tokens'] ?? 8000,
            'temperature' => $this->settings['temperature'] ?? 0.8,
        ];

        return $this->ask( $prompt, '', $params );
    }

    /**
     * Baut den Voice-Profil-Abschnitt für den Prompt auf.
     */
    private function build_voice_section( int $voice_id ): string {
        if ( ! $voice_id ) {
            return "## SCHREIBSTIL\nProfessionell, informativ und lesbar. Direkter Stil auf Deutsch.";
        }

        $profile = \AICA\Settings::get_voice_profile( $voice_id );
        if ( ! $profile ) {
            return "## SCHREIBSTIL\nProfessionell, informativ und lesbar. Direkter Stil auf Deutsch.";
        }

        $name        = $profile['name']        ?? '';
        $description = $profile['description'] ?? '';
        $tone        = $profile['tone']        ?? '';
        $style       = $profile['style']       ?? '';
        $example     = $profile['example']     ?? '';
        $avoid       = $profile['avoid']       ?? '';

        $section = "## SCHREIBSTIL & VOICE PROFIL: {$name}\n";
        $section .= "**Beschreibung:** {$description}\n";
        $section .= "**Tonalität:** {$tone}\n";
        $section .= "**Stil:** {$style}\n";

        if ( $example ) {
            $section .= "\n**Beispieltext (orientiere deinen Stil daran):**\n> {$example}\n";
        }

        if ( $avoid ) {
            $section .= "\n**Unbedingt vermeiden:** {$avoid}\n";
        }

        // Erfolgreiche Content-Beispiele aus Evaluierungen
        if ( ! empty( $profile['evaluations'] ) ) {
            $good_examples = array_filter(
                $profile['evaluations'],
                fn( $e ) => ( $e['rating'] ?? 0 ) >= 4
            );

            if ( ! empty( $good_examples ) ) {
                $section .= "\n**Erfolgreiche Content-Beispiele (Stil-Referenz):**\n";
                $count = 0;
                foreach ( $good_examples as $eval ) {
                    if ( $count >= 2 ) {
                        break;
                    }
                    $excerpt = $this->truncate( $eval['excerpt'] ?? '', 200 );
                    if ( $excerpt ) {
                        $section .= "> {$excerpt}\n";
                        $count++;
                    }
                }
            }
        }

        return $section;
    }

    /**
     * Extrahiert Meta-Daten aus dem generierten Artikel.
     */
    public function extract_meta( string $content ): array {
        $meta = [
            'title'       => '',
            'description' => '',
        ];

        // Meta-Title extrahieren (bevorzugt expliziter Meta-Title im Content)
        if ( preg_match( '/(?:^|\n)(?:##\s+)?Meta.?Title[:\s=]+(.+?)(?:\n|$)/im', $content, $m ) ) {
            $meta['title'] = trim( $m[1], ' -:' );
        } elseif ( preg_match( '/(?:^|\n)Meta.?Title[:\s=]+(.+?)(?:\n|$)/im', $content, $m ) ) {
            $meta['title'] = trim( $m[1], ' -:' );
        }
        // Fallback: Erste H1 Überschrift
        elseif ( preg_match( '/^#\s+(.+)$/m', $content, $m ) ) {
            $meta['title'] = trim( $m[1] );
        }
        // Letzter Fallback: Erste Zeile oder erstes Wort-Cluster
        else {
            $lines = array_filter( array_map( 'trim', explode( "\n", $content ) ) );
            if ( ! empty( $lines ) ) {
                $first_line = $lines[0];
                // Entferne Markdown-Formatierungen
                $first_line = preg_replace( '/^#+\s+/', '', $first_line );
                $first_line = trim( $first_line, '# *_-' );
                if ( strlen( $first_line ) > 0 && strlen( $first_line ) < 200 ) {
                    $meta['title'] = $first_line;
                }
            }
        }

        // Meta-Description extrahieren
        if ( preg_match( '/(?:^|\n)(?:##\s+)?Meta.?Description[:\s=]+(.+?)(?:\n|$)/im', $content, $m ) ) {
            $meta['description'] = trim( $m[1], ' -:' );
        } elseif ( preg_match( '/(?:^|\n)Meta.?Description[:\s=]+(.+?)(?:\n|$)/im', $content, $m ) ) {
            $meta['description'] = trim( $m[1], ' -:' );
        }
        // Fallback: Erste 160 Zeichen des Textes ohne Markdown
        else {
            $text_only = preg_replace( '/[#*_`\[\]()]+/', '', $content );
            $text_only = preg_replace( '/\n+/', ' ', $text_only );
            $first_paragraph = trim( substr( $text_only, 0, 160 ) );
            if ( strlen( $first_paragraph ) > 20 ) {
                $meta['description'] = rtrim( $first_paragraph, '.,!?' ) . '...';
            }
        }

        return $meta;
    }

    /**
     * Berechnet einen einfachen SEO-Score (0-100) für den Artikel.
     */
    public function calculate_seo_score( string $content, string $keyword ): int {
        $score = 50; // Basis-Score

        if ( empty( $keyword ) || empty( $content ) ) {
            return $score;
        }

        // Bei mehreren Keywords (kommagetrennt) nur das erste (Primär-Keyword) verwenden
        $primary_keyword = trim( explode( ',', $keyword )[0] );

        $lower_content = mb_strtolower( $content );
        $lower_keyword = mb_strtolower( $primary_keyword );
        $word_count    = str_word_count( $content );

        // Keyword in H1
        if ( preg_match( '/^#\s+.*' . preg_quote( $lower_keyword, '/' ) . '/im', $lower_content ) ) {
            $score += 10;
        }

        // Keyword im ersten Absatz (erste 300 Zeichen)
        $first_300 = mb_substr( $lower_content, 0, 300 );
        if ( str_contains( $first_300, $lower_keyword ) ) {
            $score += 10;
        }

        // Keyword-Dichte (1-3% optimal)
        $keyword_count = substr_count( $lower_content, $lower_keyword );
        $keyword_words = str_word_count( $lower_keyword );
        if ( $word_count > 0 ) {
            $density = ( $keyword_count * $keyword_words / $word_count ) * 100;
            if ( $density >= 1.0 && $density <= 3.0 ) {
                $score += 10;
            }
        }

        // H2-Überschriften vorhanden
        if ( preg_match_all( '/^##\s+/m', $content ) >= 3 ) {
            $score += 5;
        }

        // Wortanzahl
        if ( $word_count >= 1200 ) {
            $score += 10;
        } elseif ( $word_count >= 800 ) {
            $score += 5;
        }

        // FAQ-Abschnitt
        if ( preg_match( '/##.*faq|##.*häufig|##.*fragen/i', $content ) ) {
            $score += 5;
        }

        return min( 100, $score );
    }
}
