<?php

namespace ChocChop\Core;

class URLScraper {
    /** @var string|null Selector that produced content (for recipe learn-back). */
    private $last_used_selector = null;

    /**
     *
     * @param string $url
     * @return array{success: bool, article?: array, error?: string}
     */
    public function scrape_url($url) {
        // Validate URL (no esc_url_raw — already sanitized by caller)
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'error' => __('URL inválida.', 'sp-chop'),
            ];
        }

        // Structural SSRF check (hostname/TLD blocklist, raw IP check)
        if (!Security::is_safe_url($url)) {
            Security::log_security_event('blocked_ssrf_attempt_scraper', ['url' => $url]);
            return [
                'success' => false,
                'error' => __('URL no permitida (protección SSRF).', 'sp-chop'),
            ];
        }

        // Primary: wp_safe_remote_get (handles SSRF at transport layer)
        $response = wp_safe_remote_get($url, [
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (compatible; ChocChop/1.1; +' . get_site_url() . ')',
        ]);

        // If primary fails, try Cloudflare fallback
        if (is_wp_error($response)) {
            $cf_result = $this->scrape_via_cloudflare($url);
            if ($cf_result['success']) {
                return $cf_result;
            }
            $cf_error = $cf_result['error'] ?? 'error desconocido';
            ErrorHandler::log('URLScraper', sprintf('Descarga fallida para %s: directo=%s · Cloudflare=%s', $url, $response->get_error_message(), $cf_error));
            return [
                'success' => false,
                'error' => sprintf('Directo: %s · Cloudflare: %s', $response->get_error_message(), $cf_error),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            // Non-200 (e.g. 403/503 WAF block) — try Cloudflare before giving up.
            $cf_result = $this->scrape_via_cloudflare($url);
            if ($cf_result['success']) {
                return $cf_result;
            }
            $cf_error = $cf_result['error'] ?? 'error desconocido';
            ErrorHandler::log('URLScraper', sprintf('HTTP %d para %s · Cloudflare: %s', $status_code, $url, $cf_error));
            return [
                'success' => false,
                'error' => sprintf('Directo: HTTP %d · Cloudflare: %s', $status_code, $cf_error),
            ];
        }

        $html = wp_remote_retrieve_body($response);

        if (empty($html)) {
            return [
                'success' => false,
                'error' => __('No se obtuvo contenido de la URL.', 'sp-chop'),
            ];
        }

        // Parse HTML and extract article
        $article = $this->extract_article($html, $url);

        if (!$article['success']) {
            return $article;
        }

        return [
            'success' => true,
            'article' => $article['data'],
        ];
    }

    /**
     * Extract article content from HTML
     *
     * @param string $html
     * @param string $url
     * @return array
     */
    private function extract_article($html, $url) {
        // Try JSON-LD first — works reliably on Next.js/SPA sites
        $ld = $this->extract_json_ld($html);

        // Load HTML into DOMDocument
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $xpath = new \DOMXPath($dom);

        $domain = RecipeManager::url_to_domain($url);
        $recipe = RecipeManager::get_recipe($domain);
        $content = '';
        $this->last_used_selector = null;

        // Recipe-first extraction: try the learned/manual content selector.
        if ($recipe && !empty($recipe['content_selector'])) {
            $recipe_xpath = RecipeManager::css_to_xpath($recipe['content_selector']);
            $recipe_nodes = $this->safe_query($xpath, $recipe_xpath);
            if ($recipe_nodes->length > 0) {
                $content_node = $recipe_nodes->item(0);

                // Strip junk WITHIN the content node only.
                if (!empty($recipe['strip_selectors'])) {
                    foreach ($recipe['strip_selectors'] as $strip_css) {
                        $strip_xp = RecipeManager::css_to_xpath($strip_css);
                        $junk = $this->safe_query($xpath, $strip_xp);
                        foreach ($junk as $junk_node) {
                            // Only remove if it's a descendant of the content node.
                            if ($junk_node->parentNode && $this->is_descendant_of($junk_node, $content_node)) {
                                $junk_node->parentNode->removeChild($junk_node);
                            }
                        }
                    }
                }

                $content = $this->extract_text_from_node($content_node);
                $content = $this->clean_content($content);

                // Apply recipe text patterns (validate regex to prevent ReDoS).
                if (!empty($recipe['strip_text'])) {
                    foreach ($recipe['strip_text'] as $pattern) {
                        if ( @preg_match( '/' . $pattern . '/iu', '' ) !== false ) {
                            $content = @preg_replace( '/' . $pattern . '/iu', '', $content );
                            if ( preg_last_error() !== PREG_NO_ERROR ) {
                                break;
                            }
                        }
                    }
                    $content = $this->clean_content($content);
                }

                if (mb_strlen($content) > 100) {
                    $this->last_used_selector = $recipe['content_selector'];
                } else {
                    $content = ''; // Not enough content — fall through to generic
                }
            }
        }

        // Extract components — JSON-LD values take priority where available
        $title   = !empty($ld['headline']) ? $ld['headline'] : $this->extract_title($xpath, $dom);
        $author  = !empty($ld['author'])   ? $ld['author']   : $this->extract_author($xpath);
        $date    = !empty($ld['date'])     ? $ld['date']     : $this->extract_date($xpath);
        if (empty($content)) {
            $content = !empty($ld['content']) ? $ld['content'] : $this->extract_content($xpath, $dom);
        }
        $quotes  = $this->extract_quotes($content);
        $key_data = $this->extract_key_data($content);

        // Last resort: use og:description so the AI has *something* to work with.
        if (empty($content)) {
            $og_desc = $this->safe_query($xpath, '//meta[@property="og:description"]/@content');
            if ($og_desc->length > 0) {
                $content = trim($og_desc->item(0)->nodeValue ?? '');
            }
        }

        if (empty($content)) {
            ErrorHandler::log('URLScraper', sprintf('Sin contenido extraíble de %s (JSON-LD, DOM y OG vacíos)', $url));
            return [
                'success' => false,
                'error' => __('No se pudo extraer contenido — la página podría requerir JavaScript para renderizar.', 'sp-chop'),
            ];
        }

        return [
            'success' => true,
            'data' => [
                'title' => $title,
                'author' => $author,
                'date' => $date,
                'content' => $content,
                'link' => $url,
                'quotes' => $quotes,
                'key_data' => $key_data,
                'guid' => 'scraped-' . md5($url . time()),
                'categories' => [],
            ],
        ];
    }

    /**
     * Safe XPath query — returns empty DOMNodeList-like on failure
     *
     * @param \DOMXPath $xpath
     * @param string    $expression
     * @return \DOMNodeList
     */
    private function safe_query(\DOMXPath $xpath, string $expression) {
        $result = @$xpath->query($expression);
        if ($result === false) {
            // Return an empty node list via a query that matches nothing
            return $xpath->query('/nothing');
        }
        return $result;
    }

    /**
     * Extract structured data from JSON-LD script tags
     *
     * @param string $html Raw HTML.
     * @return array{headline?: string, author?: string, date?: int, content?: string}
     */
    private function extract_json_ld(string $html): array {
        $result = [];

        if (!preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches)) {
            return $result;
        }

        foreach ($matches[1] as $json_str) {
            $data = json_decode(trim($json_str), true);
            if (!is_array($data)) {
                continue;
            }

            // Handle @graph wrapper
            if (isset($data['@graph']) && is_array($data['@graph'])) {
                foreach ($data['@graph'] as $item) {
                    $result = $this->parse_ld_item($item, $result);
                }
            } else {
                $result = $this->parse_ld_item($data, $result);
            }

            // Stop once we have the essentials
            if (!empty($result['headline']) && !empty($result['content'])) {
                break;
            }
        }

        return $result;
    }

    /**
     * Parse a single JSON-LD item for article data
     *
     * @param array $item  JSON-LD object.
     * @param array $carry Previously extracted data.
     * @return array Merged data.
     */
    private function parse_ld_item(array $item, array $carry): array {
        $types = ['NewsArticle', 'Article', 'BlogPosting', 'WebPage', 'ReportageNewsArticle'];
        $type  = $item['@type'] ?? '';

        if (!in_array($type, $types, true)) {
            return $carry;
        }

        if (empty($carry['headline']) && !empty($item['headline'])) {
            $carry['headline'] = wp_strip_all_tags($item['headline']);
        }

        if (empty($carry['author'])) {
            $author = $item['author'] ?? null;
            if (is_array($author)) {
                $author = $author[0] ?? $author;
            }
            if (is_array($author) && !empty($author['name'])) {
                $carry['author'] = wp_strip_all_tags($author['name']);
            } elseif (is_string($author)) {
                $carry['author'] = wp_strip_all_tags($author);
            }
        }

        if (empty($carry['date'])) {
            $date_str = $item['datePublished'] ?? $item['dateCreated'] ?? '';
            if ($date_str) {
                $ts = strtotime($date_str);
                if ($ts !== false) {
                    $carry['date'] = $ts;
                }
            }
        }

        if (empty($carry['content']) && !empty($item['articleBody'])) {
            $carry['content'] = wp_strip_all_tags($item['articleBody']);
        }

        return $carry;
    }

    /**
     * Extract title from HTML
     *
     * @param \DOMXPath $xpath
     * @param \DOMDocument $dom
     * @return string
     */
    private function extract_title($xpath, $dom) {
        $selectors = [
            '//meta[@property="og:title"]/@content',
            '//meta[@name="twitter:title"]/@content',
            '//h1[contains(@class,"title")]',
            '//h1[contains(@class,"headline")]',
            '//h1[1]',
            '//title',
        ];

        foreach ($selectors as $selector) {
            $nodes = $this->safe_query($xpath, $selector);
            if ($nodes->length > 0) {
                $title = $nodes->item(0)->nodeValue ?? $nodes->item(0)->textContent;
                $title = trim(wp_strip_all_tags($title));
                if (!empty($title) && strlen($title) > 10) {
                    return $title;
                }
            }
        }

        return __('Artículo sin título', 'sp-chop');
    }

    /**
     * Extract author from HTML
     *
     * @param \DOMXPath $xpath
     * @return string
     */
    private function extract_author($xpath) {
        $selectors = [
            '//meta[@property="article:author"]/@content',
            '//meta[@name="author"]/@content',
            '//span[contains(@class,"author")]',
            '//div[contains(@class,"author")]',
            '//a[@rel="author"]',
        ];

        foreach ($selectors as $selector) {
            $nodes = $this->safe_query($xpath, $selector);
            if ($nodes->length > 0) {
                $author = $nodes->item(0)->nodeValue ?? $nodes->item(0)->textContent;
                $author = trim(wp_strip_all_tags($author));
                if (!empty($author) && strlen($author) < 100) {
                    return $author;
                }
            }
        }

        return '';
    }

    /**
     * Extract publication date
     *
     * @param \DOMXPath $xpath
     * @return int
     */
    private function extract_date($xpath) {
        $selectors = [
            '//meta[@property="article:published_time"]/@content',
            '//meta[@name="pubdate"]/@content',
            '//time/@datetime',
            '//span[contains(@class,"date")]',
        ];

        foreach ($selectors as $selector) {
            $nodes = $this->safe_query($xpath, $selector);
            if ($nodes->length > 0) {
                $date_str = $nodes->item(0)->nodeValue ?? $nodes->item(0)->textContent;
                $timestamp = strtotime($date_str);
                if ($timestamp !== false) {
                    return $timestamp;
                }
            }
        }

        return time();
    }

    /**
     * Extract main content from HTML
     *
     * @param \DOMXPath $xpath
     * @param \DOMDocument $dom
     * @return string
     */
    private function extract_content($xpath, $dom) {
        // Remove junk nodes from the DOM *before* extraction.
        $this->strip_junk_nodes($xpath);

        $selectors = [
            '//article//div[contains(@class,"article-body") or contains(@class,"article-text") or contains(@class,"story-body")]',
            '//div[contains(@class,"article-content")]',
            '//div[contains(@class,"post-content")]',
            '//div[contains(@class,"entry-content")]',
            '//div[contains(@class,"content-body")]',
            '//article',
            '//div[@id="content"]',
            '//main',
        ];

        $content_node = null;

        foreach ($selectors as $selector) {
            $nodes = $this->safe_query($xpath, $selector);
            if ($nodes->length > 0) {
                $content_node = $nodes->item(0);
                $this->last_used_selector = $selector;
                break;
            }
        }

        if (!$content_node) {
            // Fallback: get all paragraphs with meaningful content.
            $paragraphs = $this->safe_query($xpath, '//p');
            $content = '';
            foreach ($paragraphs as $p) {
                $text = trim($p->textContent);
                if (strlen($text) > 50) {
                    $content .= $text . "\n\n";
                }
            }
            return $this->clean_content(trim($content));
        }

        // Extract text from content node.
        $content = $this->extract_text_from_node($content_node);

        // Clean up.
        $content = $this->clean_content($content);

        return $content;
    }

    /**
     * Remove junk nodes from DOM before content extraction.
     *
     * Strips social widgets, share bars, related articles, ads, navigation,
     * and other non-article elements that pollute extracted text.
     *
     * @param \DOMXPath $xpath
     */
    private function strip_junk_nodes(\DOMXPath $xpath) {
        // Classes/IDs that signal non-article content.
        $junk_patterns = [
            'share', 'social', 'related', 'sidebar', 'comment', 'newsletter',
            'subscribe', 'popup', 'modal', 'ad-', 'ads-', 'advertisement',
            'promo', 'widget', 'breadcrumb', 'pagination', 'nav-', 'menu',
            'footer', 'header', 'toolbar', 'cookie', 'banner',
        ];

        foreach ($junk_patterns as $pattern) {
            // Match class or id containing the pattern.
            $nodes = $this->safe_query($xpath,
                '//*[contains(@class,"' . $pattern . '") or contains(@id,"' . $pattern . '")]'
            );
            foreach ($nodes as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        // Remove specific tags that are always junk in article context.
        $junk_tags = ['button', 'form', 'svg', 'noscript', 'select', 'input', 'textarea'];
        foreach ($junk_tags as $tag) {
            $nodes = $this->safe_query($xpath, '//' . $tag);
            foreach ($nodes as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }
    }

    /**
     * Check if a node is a descendant of another node.
     *
     * @param \DOMNode $node     The potential descendant.
     * @param \DOMNode $ancestor The potential ancestor.
     * @return bool
     */
    private function is_descendant_of(\DOMNode $node, \DOMNode $ancestor): bool {
        $parent = $node->parentNode;
        while ($parent) {
            if ($parent->isSameNode($ancestor)) {
                return true;
            }
            $parent = $parent->parentNode;
        }
        return false;
    }

    /**
     * Extract text from DOM node recursively
     *
     * @param \DOMNode $node
     * @return string
     */
    private function extract_text_from_node($node) {
        $text = '';

        if ($node->nodeType === XML_TEXT_NODE) {
            return $node->nodeValue;
        }

        // Skip non-content elements.
        $skip_tags = [
            'script', 'style', 'nav', 'footer', 'header', 'aside', 'iframe',
            'button', 'form', 'svg', 'figure', 'figcaption', 'noscript',
            'select', 'input', 'textarea', 'label',
        ];
        if (in_array($node->nodeName, $skip_tags, true)) {
            return '';
        }

        // Skip elements with share/social/ad classes.
        if ($node instanceof \DOMElement) {
            $cls = $node->getAttribute('class') . ' ' . $node->getAttribute('id');
            if (preg_match('/\b(share|social|related|sidebar|comment|ad-slot|newsletter|subscribe|widget|promo)\b/i', $cls)) {
                return '';
            }
        }

        foreach ($node->childNodes as $child) {
            if ($child->nodeName === 'p' || $child->nodeName === 'div' || $child->nodeName === 'blockquote') {
                $text .= $this->extract_text_from_node($child) . "\n\n";
            } elseif ($child->nodeName === 'br') {
                $text .= "\n";
            } elseif ($child->nodeName === 'h1' || $child->nodeName === 'h2' || $child->nodeName === 'h3') {
                $text .= "\n\n" . $this->extract_text_from_node($child) . "\n\n";
            } else {
                $text .= $this->extract_text_from_node($child);
            }
        }

        return $text;
    }

    /**
     * Clean extracted content
     *
     * @param string $content
     * @return string
     */
    private function clean_content($content) {
        // Strip social/share concatenated junk (e.g. "FacebookXWhatsAppTelegramE-MailCopiar link").
        $content = preg_replace(
            '/(?:Facebook|Twitter|WhatsApp|Telegram|LinkedIn|Instagram|TikTok|E-?Mail|Copiar\s*link|Artículo\s*impreso|Compartir|Síguenos\s*en:?|Share|Print|Copy\s*link)[\s]*/iu',
            '',
            $content
        );

        // Remove "Leer más", "Te puede interesar", etc.
        $content = preg_replace(
            '/^(?:Leer más|Lee también|Te puede interesar|Nota relacionada|Ver también|Más noticias|Noticias relacionadas)[:\s]*$/imu',
            '',
            $content
        );

        // Remove lines that are just a URL.
        $content = preg_replace('/^\s*https?:\/\/\S+\s*$/m', '', $content);

        // Remove multiple newlines.
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        // Remove extra spaces.
        $content = preg_replace("/[ \t]+/", " ", $content);

        // Trim each line.
        $lines = explode("\n", $content);
        $lines = array_map('trim', $lines);

        // Remove very short lines (< 15 chars) that are likely UI fragments.
        $lines = array_filter($lines, function($line) {
            return $line === '' || mb_strlen($line) >= 15;
        });

        $content = implode("\n", $lines);

        return trim($content);
    }

    /**
     * Extract quotes from content
     *
     * @param string $content
     * @return array
     */
    private function extract_quotes($content) {
        $quotes = [];

        // Find text in quotes (using various quote styles)
        $patterns = [
            '/"([^"]{20,200})"/u',  // English quotes
            '/«([^»]{20,200})»/u',  // French quotes
            '/"([^"]{20,200})"/u',  // Smart quotes
            '/\'([^\']{20,200})\'/u', // Single quotes
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $quote) {
                    $quote = trim($quote);
                    if (strlen($quote) > 20 && strlen($quote) < 200) {
                        $quotes[] = $quote;
                    }
                }
            }
        }

        // Remove duplicates
        $quotes = array_unique($quotes);

        // Limit to 5 quotes
        return array_slice($quotes, 0, 5);
    }

    /**
     * Extract key data points from content
     *
     * @param string $content
     * @return array
     */
    private function extract_key_data($content) {
        $key_data = [];

        // Find sentences with numbers (often key facts)
        $sentences = preg_split('/[.!?]\s+/', $content);

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);

            // Look for sentences with numbers/percentages/dates
            if (preg_match('/\d+%?/', $sentence) && strlen($sentence) > 20 && strlen($sentence) < 200) {
                $key_data[] = $sentence;
            }

            // Limit to 5 key data points
            if (count($key_data) >= 5) {
                break;
            }
        }

        return $key_data;
    }

    /**
     * Fallback: scrape URL via Cloudflare Browser Rendering /markdown API.
     *
     * Returns clean markdown content, bypassing local DNS/SSRF restrictions.
     *
     * @param string $url URL to render.
     * @return array{success: bool, article?: array, error?: string}
     */
    private function scrape_via_cloudflare(string $url): array {
        $account_id = Config::get('cloudflare_account_id', '');
        $api_token  = Security::decrypt(Config::get('cloudflare_api_token', ''));

        if (empty($account_id) || empty($api_token)) {
            return ['success' => false, 'error' => __('Cloudflare no configurado.', 'sp-chop')];
        }

        $api_url = sprintf(
            'https://api.cloudflare.com/client/v4/accounts/%s/browser-rendering/markdown',
            $account_id
        );

        $response = wp_remote_post($api_url, [
            'timeout' => 45,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'url'         => $url,
                'gotoOptions' => ['waitUntil' => 'networkidle0'],
            ]),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['success']) || empty($body['result'])) {
            return ['success' => false, 'error' => __('Cloudflare no devolvió contenido.', 'sp-chop')];
        }

        $markdown = $body['result'];

        // Extract title from first markdown heading
        $title = __('Artículo sin título', 'sp-chop');
        if (preg_match('/^#\s+(.+)$/m', $markdown, $m)) {
            $title = trim($m[1]);
        }

        return [
            'success' => true,
            'article' => [
                'title'      => $title,
                'author'     => '',
                'date'       => time(),
                'content'    => $markdown,
                'link'       => $url,
                'quotes'     => [],
                'key_data'   => [],
                'guid'       => 'cf-' . md5($url . time()),
                'categories' => [],
            ],
        ];
    }

    /**
     * Queue a URL for pipeline processing
     *
     * Scrapes the URL and adds to queue for the standard 2-pass pipeline.
     *
     * @param string $url URL to scrape and queue.
     * @return array {success: bool, queue_id?: int, title?: string, error?: string}
     */
    public function queue_url( string $url ): array {
        // Basic URL validation — SSRF check is handled by scrape_url() internally.
        $scrape_result = $this->scrape_url( $url );
        if ( ! $scrape_result['success'] ) {
            return array(
                'success' => false,
                'error'   => $scrape_result['error'] ?? __( 'No se pudo scrapear la URL.', 'sp-chop' ),
            );
        }

        $article = $scrape_result['article'];

        // Queue via QueueManager.
        $queue_id = QueueManager::add_to_queue( array(
            'email_uid'     => 'url_' . md5( $url ),
            'email_subject' => $article['title'] ?? __( 'Untitled Article', 'sp-chop' ),
            'content'       => $article['content'] ?? '',
            'email_from'    => wp_parse_url( $url, PHP_URL_HOST ) ?? '',
            'email_date'    => current_time( 'mysql' ),
            'content_source' => 'body',
            'source_type'   => 'url',
            'source_url'    => $url,
            'triage_score'  => 80,
        ) );

        if ( ! $queue_id ) {
            return array(
                'success' => false,
                'error'   => __( 'No se pudo agregar a la cola (posible duplicado).', 'sp-chop' ),
            );
        }

        // Store scrape context for recipe learn-back after pipeline completes.
        set_transient( 'sp_chop_scrape_ctx_' . $queue_id, [
            'content_selector' => $this->last_used_selector,
            'domain'           => RecipeManager::url_to_domain( $url ),
        ], DAY_IN_SECONDS );

        return array(
            'success'  => true,
            'queue_id' => $queue_id,
            'title'    => $article['title'] ?? '',
        );
    }
}
