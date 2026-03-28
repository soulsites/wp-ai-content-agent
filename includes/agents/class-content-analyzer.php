<?php
namespace AICA\Agents;

defined( 'ABSPATH' ) || exit;

/**
 * Content-Analyse Agent.
 * Analysiert das Thema: Content-Lücken, Unique Angles, Suchintention.
 */
class Content_Analyzer extends Agent_Base {

    protected string $agent_key  = 'content_analyzer';
    protected string $agent_name = 'Content-Analyst';

    public function run( array $context ) {
        $topic    = $context['topic']    ?? '';
        $keywords = $context['keywords'] ?? '';

        if ( empty( $topic ) ) {
            return new \WP_Error( 'no_topic', 'Kein Thema angegeben.' );
        }

        $prompt = <<<PROMPT
Analysiere das folgende Thema für die Content-Erstellung.

**Thema:** {$topic}
**Ziel-Keywords:** {$keywords}

Erstelle eine umfassende Content-Analyse auf Deutsch mit folgenden Abschnitten:

1. **Suchintention**: Was suchen Nutzer bei diesem Thema? (informational/transactional/navigational/commercial)
2. **Content-Lücken**: Welche wichtigen Aspekte werden von existierendem Content oft übersehen?
3. **Unique Angles**: 3-5 einzigartige Blickwinkel/Perspektiven, die diesen Artikel herausstechen lassen
4. **Struktur-Empfehlung**: Optimale H1-H3 Überschriftenstruktur für SEO und Lesbarkeit
5. **Content-Tiefe**: Empfohlene Wortanzahl und Detailtiefe
6. **Wettbewerbsanalyse**: Was macht erfolgreicher Content zu diesem Thema aus?

Antworte strukturiert und präzise auf Deutsch.
PROMPT;

        return $this->ask( $prompt );
    }
}
