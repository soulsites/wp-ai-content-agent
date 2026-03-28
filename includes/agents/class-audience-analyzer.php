<?php
namespace AICA\Agents;

defined( 'ABSPATH' ) || exit;

/**
 * Zielgruppenanalyse Agent.
 * Identifiziert Zielgruppen, Bedürfnisse und Schmerzpunkte.
 */
class Audience_Analyzer extends Agent_Base {

    protected string $agent_key  = 'audience_analyzer';
    protected string $agent_name = 'Zielgruppenanalyst';

    public function run( array $context ) {
        $topic            = $context['topic']           ?? '';
        $keywords         = $context['keywords']        ?? '';
        $analysis_result  = $context['analysis_result'] ?? '';

        if ( empty( $topic ) ) {
            return new \WP_Error( 'no_topic', 'Kein Thema angegeben.' );
        }

        $analysis_excerpt = $analysis_result
            ? "\n\n**Content-Analyse (Zusammenfassung):**\n" . $this->truncate( $analysis_result, 300 )
            : '';

        $prompt = <<<PROMPT
Führe eine detaillierte Zielgruppenanalyse für folgenden Content durch.

**Thema:** {$topic}
**Keywords:** {$keywords}{$analysis_excerpt}

Erstelle eine umfassende Zielgruppenanalyse auf Deutsch:

1. **Primäre Zielgruppe**
   - Demografische Merkmale (Alter, Beruf, Bildung)
   - Psychografische Merkmale (Interessen, Werte, Lebensstil)
   - Digitales Verhalten (welche Plattformen, wann, wie)

2. **Sekundäre Zielgruppe(n)**
   - Kurzbeschreibung weiterer relevanter Zielgruppen

3. **Bedürfnisse & Motivationen**
   - Was treibt die Zielgruppe zur Suche nach diesem Thema?
   - Welches Problem wollen sie lösen?
   - Was erhoffen sie sich vom Artikel?

4. **Schmerzpunkte & Frustration**
   - Was frustriert die Zielgruppe bei diesem Thema?
   - Häufige Missverständnisse und falsche Annahmen

5. **Wichtige Fragen der Zielgruppe**
   - 5-8 konkrete Fragen, die der Artikel beantworten sollte

6. **Sprache & Tonalität**
   - Welche Sprache verwendet die Zielgruppe?
   - Welcher Kommunikationsstil passt?
   - Zu vermeidende Begriffe oder Formulierungen

Antworte detailliert und praxisnah auf Deutsch.
PROMPT;

        return $this->ask( $prompt );
    }
}
