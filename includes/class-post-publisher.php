<?php
namespace AICA;

defined( 'ABSPATH' ) || exit;

/**
 * Publiziert generierten Content als WordPress-Post.
 */
class Post_Publisher {

    /**
     * Erstellt oder aktualisiert einen WordPress-Post mit dem generierten Content.
     *
     * @param string $content   Markdown/HTML Content
     * @param array  $meta      Meta-Daten (title, description)
     * @param array  $context   Job-Kontext (topic, keywords, post_status, category_id, etc.)
     * @return int|\WP_Error    Post-ID oder Fehler
     */
    public static function publish( string $content, array $meta, array $context ) {
        $title       = $meta['title']       ?? $context['topic'] ?? 'Generierter Artikel';
        $description = $meta['description'] ?? '';
        $post_status = $context['post_status'] ?? 'draft';
        $category_id = (int) ( $context['category_id'] ?? 0 );

        // Primär-Keyword extrahieren (erstes bei kommagetrennte Liste)
        $keywords_raw    = $context['keywords'] ?? '';
        $primary_keyword = trim( explode( ',', $keywords_raw )[0] );

        // Markdown zu HTML konvertieren (einfacher Konverter)
        $html_content = self::markdown_to_html( $content );

        $post_data = [
            'post_title'   => wp_strip_all_tags( $title ),
            'post_content' => $html_content,
            'post_status'  => $post_status,
            'post_author'  => get_current_user_id() ?: 1,
            'post_type'    => 'post',
            'meta_input'   => [
                '_aica_generated'       => 1,
                '_aica_original_topic'  => $context['topic'] ?? '',
                '_aica_keywords'        => $context['keywords'] ?? '',
                '_aica_voice_id'        => $context['voice_id'] ?? 0,
            ],
        ];

        // Kategorie setzen
        if ( $category_id ) {
            $post_data['post_category'] = [ $category_id ];
        }

        // Yoast SEO Meta-Felder
        if ( $description ) {
            $post_data['meta_input']['_yoast_wpseo_metadesc'] = $description;
        }
        if ( $title ) {
            $post_data['meta_input']['_yoast_wpseo_title'] = wp_strip_all_tags( $title );
        }
        if ( $primary_keyword ) {
            $post_data['meta_input']['_yoast_wpseo_focuskw'] = $primary_keyword;
        }

        // RankMath Meta-Felder
        if ( $description ) {
            $post_data['meta_input']['rank_math_description'] = $description;
        }
        if ( $title ) {
            $post_data['meta_input']['rank_math_seo_title'] = wp_strip_all_tags( $title );
        }
        if ( $primary_keyword ) {
            $post_data['meta_input']['rank_math_focus_keyword'] = $primary_keyword;
        }

        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        return (int) $post_id;
    }

    /**
     * Einfacher Markdown-zu-HTML Konverter (für WordPress-kompatibles HTML).
     */
    public static function markdown_to_html( string $markdown ): string {
        $html = $markdown;

        // Headings
        $html = preg_replace( '/^#### (.+)$/m', '<h4>$1</h4>', $html );
        $html = preg_replace( '/^### (.+)$/m',  '<h3>$1</h3>', $html );
        $html = preg_replace( '/^## (.+)$/m',   '<h2>$1</h2>', $html );
        $html = preg_replace( '/^# (.+)$/m',    '<h1>$1</h1>', $html );

        // Bold & Italic
        $html = preg_replace( '/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $html );
        $html = preg_replace( '/\*\*(.+?)\*\*/',     '<strong>$1</strong>',          $html );
        $html = preg_replace( '/\*(.+?)\*/',          '<em>$1</em>',                  $html );
        $html = preg_replace( '/__(.+?)__/',           '<strong>$1</strong>',          $html );
        $html = preg_replace( '/_(.+?)_/',             '<em>$1</em>',                  $html );

        // Blockquotes
        $html = preg_replace( '/^> (.+)$/m', '<blockquote>$1</blockquote>', $html );

        // Inline Code
        $html = preg_replace( '/`(.+?)`/', '<code>$1</code>', $html );

        // Links
        $html = preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $html );

        // Unordered Lists
        $html = preg_replace_callback(
            '/(?:^[ \t]*[-*+][ \t]+.+(?:\n|$))+/m',
            function ( $matches ) {
                $items = preg_replace( '/^[ \t]*[-*+][ \t]+(.+)$/m', '<li>$1</li>', $matches[0] );
                return '<ul>' . trim( $items ) . '</ul>';
            },
            $html
        );

        // Ordered Lists
        $html = preg_replace_callback(
            '/(?:^[ \t]*\d+\.[ \t]+.+(?:\n|$))+/m',
            function ( $matches ) {
                $items = preg_replace( '/^[ \t]*\d+\.[ \t]+(.+)$/m', '<li>$1</li>', $matches[0] );
                return '<ol>' . trim( $items ) . '</ol>';
            },
            $html
        );

        // Horizontal Rule
        $html = preg_replace( '/^(-{3,}|\*{3,}|_{3,})$/m', '<hr>', $html );

        // Paragraphen (doppelter Zeilenumbruch)
        $html = preg_replace( '/\n{2,}/', '</p><p>', $html );
        $html = '<p>' . $html . '</p>';

        // Aufräumen: leere Paragraphen, doppelte Tags
        $html = preg_replace( '/<p>\s*<\/p>/', '', $html );
        $html = preg_replace( '/<p>(<h[1-6]>)/', '$1', $html );
        $html = preg_replace( '/(<\/h[1-6]>)<\/p>/', '$1', $html );
        $html = preg_replace( '/<p>(<ul>)/', '$1', $html );
        $html = preg_replace( '/(<\/ul>)<\/p>/', '$1', $html );
        $html = preg_replace( '/<p>(<ol>)/', '$1', $html );
        $html = preg_replace( '/(<\/ol>)<\/p>/', '$1', $html );
        $html = preg_replace( '/<p>(<blockquote>)/', '$1', $html );
        $html = preg_replace( '/(<\/blockquote>)<\/p>/', '$1', $html );
        $html = preg_replace( '/<p>(<hr>)<\/p>/', '$1', $html );

        // Einzelne Zeilenumbrüche in Paragraphen
        $html = str_replace( "\n", '<br>', $html );

        // Überschüssige BRs nach Block-Elementen entfernen
        $html = preg_replace( '/(<\/h[1-6]>)<br>/', '$1', $html );
        $html = preg_replace( '/(<\/ul>)<br>/', '$1', $html );
        $html = preg_replace( '/(<\/ol>)<br>/', '$1', $html );
        $html = preg_replace( '/(<\/li>)<br>/', '$1', $html );
        $html = preg_replace( '/<br>(<li>)/', '$1', $html );

        return $html;
    }
}
