<?php
namespace AICA\Agents;

defined( 'ABSPATH' ) || exit;

/**
 * Keyword-Recherche Agent.
 * Erstellt eine umfassende SEO-Keyword-Strategie.
 */
class Keyword_Researcher extends Agent_Base {

    protected string $agent_key  = 'keyword_researcher';
    protected string $agent_name = 'Keyword-Rechercheur';

    public function run( array $context ) {
        $topic    = $context['topic']    ?? '';
        $keywords = $context['keywords'] ?? '';

        if ( empty( $topic ) ) {
            return new \WP_Error( 'no_topic', 'Kein Thema angegeben.' );
        }

        $seed_keywords = $keywords ? "\n**Seed-Keywords vom Nutzer:** {$keywords}" : '';

        $prompt = <<<PROMPT
Erstelle eine umfassende Keyword-Strategie für folgenden Artikel.

**Thema:** {$topic}{$seed_keywords}

Entwickle eine vollständige SEO-Keyword-Strategie auf Deutsch:

1. **Primäres Hauptkeyword**
   - Das wichtigste Keyword mit höchstem Suchvolumen und Relevanz
   - Suchintention dieses Keywords
   - Empfohlene Platzierung (H1, erster Absatz, Meta-Title)

2. **Sekundäre Keywords (5-8)**
   - Wichtige unterstützende Keywords
   - Je eine kurze Erklärung der Relevanz

3. **Longtail-Keywords (8-12)**
   - Spezifische, weniger umkämpfte Keywords
   - Besonders geeignet für Featured Snippets

4. **LSI-Keywords & Synonyme (10-15)**
   - Semantisch verwandte Begriffe
   - Natürliche Variationen und Synonyme

5. **Fragewörter & W-Fragen (6-10)**
   - Typische Suchanfragen in Frageform
   - Ideal für FAQ-Abschnitte und Voice Search

6. **Keyword-Cluster**
   - Wie Keywords thematisch gruppiert werden sollten
   - Empfehlung für interne Verlinkung

7. **Wettbewerbsintensität**
   - Einschätzung des Wettbewerbs für die Hauptkeywords
   - Empfehlungen für schnelle Rankings

8. **Meta-Daten Empfehlung**
   - Vorschlag für Meta-Title (max. 60 Zeichen)
   - Vorschlag für Meta-Description (max. 155 Zeichen)

Antworte strukturiert und SEO-optimiert auf Deutsch.
PROMPT;

        return $this->ask( $prompt );
    }
}
