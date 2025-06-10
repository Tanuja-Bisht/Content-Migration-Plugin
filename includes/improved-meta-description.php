<?php
/**
 * Improved Meta Description Extraction
 * 
 * This file enhances the WordPress Content Migrator plugin to better extract
 * meta descriptions from source URLs.
 */

if (!defined("ABSPATH")) {
    exit;
}

// Add a button to admin pages to manually fetch meta descriptions
// Disabled: add_action("admin_menu", "cm_add_meta_description_page");

/**
 * Add meta description page to admin menu
 */
function cm_add_meta_description_page() {
    add_submenu_page(
        "content-migrator",
        "Fetch Meta Descriptions",
        "Fetch Meta Descriptions",
        "manage_options",
        "fetch-meta-descriptions",
        "cm_meta_description_page"
    );
}

/**
 * Display the meta description page
 */
function cm_meta_description_page() {
    // Process form submission
    if (isset($_POST["fetch_meta_descriptions"]) && isset($_POST["_wpnonce"]) && wp_verify_nonce($_POST["_wpnonce"], "fetch_meta_descriptions")) {
        $posts = get_posts(array(
            "post_type" => array("post", "page"),
            "posts_per_page" => -1,
            "meta_query" => array(
                array(
                    "key" => "_content_migrator_old_url",
                    "compare" => "EXISTS",
                )
            )
        ));
        
        $processed = 0;
        $successful = 0;
        
        foreach ($posts as $post) {
            $processed++;
            $old_url = get_post_meta($post->ID, "_content_migrator_old_url", true);
            
            if (empty($old_url)) {
                continue;
            }
            
            // Get the HTML content
            $response = wp_remote_get($old_url, array(
                "timeout" => 90,
                "user-agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36",
                "sslverify" => false
            ));
            
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                continue;
            }
            
            $html = wp_remote_retrieve_body($response);
            if (empty($html)) {
                continue;
            }
            
            // Try to extract meta description
            $meta_description = "";
            
            // Try standard description
            if (preg_match("/<meta\\s+[^>]*name\\s*=\\s*[\"|\']description[\"|\'][^>]*content\\s*=\\s*[\"|\']([^>\"\']*)[\"|\'][^>]*>/is", $html, $matches)) {
                $meta_description = $matches[1];
            }
            // Try OG description
            elseif (preg_match("/<meta\\s+[^>]*property\\s*=\\s*[\"|\']og:description[\"|\'][^>]*content\\s*=\\s*[\"|\']([^>\"\']*)[\"|\'][^>]*>/is", $html, $matches)) {
                $meta_description = $matches[1];
            }
            // Try Twitter description
            elseif (preg_match("/<meta\\s+[^>]*name\\s*=\\s*[\"|\']twitter:description[\"|\'][^>]*content\\s*=\\s*[\"|\']([^>\"\']*)[\"|\'][^>]*>/is", $html, $matches)) {
                $meta_description = $matches[1];
            }
            
            if (!empty($meta_description)) {
                $meta_description = html_entity_decode(trim($meta_description), ENT_QUOTES | ENT_HTML5);
                update_post_meta($post->ID, "_yoast_wpseo_metadesc", $meta_description);
                $successful++;
            }
        }
        
        echo "<div class=\"notice notice-success\"><p>Processed " . $processed . " posts, added meta descriptions to " . $successful . " posts.</p></div>";
    }
    
    // Display the form
    ?>
    <div class="wrap">
        <h1>Fetch Meta Descriptions</h1>
        <p>Click the button below to fetch meta descriptions for all posts and pages that were imported using Content Migrator but do not have meta descriptions yet.</p>
        
        <form method="post">
            <?php wp_nonce_field("fetch_meta_descriptions"); ?>
            <p><input type="submit" name="fetch_meta_descriptions" class="button button-primary" value="Fetch Meta Descriptions"></p>
        </form>
    </div>
    <?php
} 