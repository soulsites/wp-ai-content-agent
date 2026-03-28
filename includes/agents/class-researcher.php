<?php
namespace AICA\Agents;

defined( 'ABSPATH' ) || exit;

/**
 * Recherche Agent.
 * Sammelt tiefgründige Fakten, Statistiken und Perspektiven zum Thema.
 */
class Researcher extends Agent_Base {

    protected string $agent_key  = 'researcher';
    protected string $agent_name = 'Rechercheur';

    public function run( array $context ) {
        $topic           = $context['topic']           ?? '';
        $keywords        = $context['keywords']        ?? '';
        $analysis_result = $context['analysis_result'] ?? '';
        $audience_result = $context['audience_result'] ?? '';

        if ( empty( $topic ) ) {
            return new \WP_Error( 'no_topic', 'Kein Thema angegeben.' );
        }

        // Kontext komprimieren
        $context_parts = [];
        if ( $analysis_result ) {
            $context_parts[] = "**Content-Analyse (Auszug):**\n" . $this->truncate( $analysis_result, 400 );
        }
        if ( $audience_result ) {
            $context_parts[] = "**Zielgruppe (Auszug):**\n" . $this->truncate( $audience_result, 300 );
        }
        $context_section = $context_parts ? "\n\n" . implode( "\n\n", $context_parts ) : '';

        $prompt = <<<PROMPT
Erstelle einen umfassenden Recherche-Report für folgenden Artikel.

**Thema:** {$topic}
**Keywords:** {$keywords}{$context_section}

Recherchiere tiefgründig und liefere folgende Informationen auf Deutsch:

1. **Kernfakten & Hintergrund**
   - Die wichtigsten Fakten zum Thema
   - Historischer Kontext (wenn relevant)
   - Aktuelle Entwicklungen und Trends (Stand 2024/2025)

2. **Statistiken & Daten**
   - Relevante Zahlen, Studien und Umfrageergebnisse
   - Quellenangaben (Forschungsinstitute, Studien, offizielle Stellen)
   - Hinweis: Nutze bekannte, seriöse Quellen

3. **Expertenmeinungen & Best Practices**
   - Anerkannte Expertenpositionen zum Thema
   - Branchenkonsens vs. kontroverse Meinungen
   - Bewährte Methoden und Empfehlungen

4. **Häufige Missverständnisse & Mythen**
   - Weit verbreitete falsche Annahmen
   - Richtigstellung mit Begründung

5. **Aktuelle Trends & Zukunftsperspektiven**
   - Aktuelle Entwicklungen im Bereich
   - Prognosen und Ausblicke

6. **Praxisbeispiele & Case Studies**
   - Konkrete Beispiele aus der Praxis
   - Erfolgsgeschichten oder Fallstudien (allgemein)

7. **Kontroverses & Nuancen**
   - Unterschiedliche Sichtweisen
   - Einschränkungen und Grenzen des Themas

8. **Weiterführende Themen**
   - Verwandte Bereiche für interne Verlinkung
   - Tiefergehende Ressourcen-Empfehlungen

Antworte detailliert, faktenreich und präzise auf Deutsch.
PROMPT;

        return $this->ask( $prompt );
    }
}
