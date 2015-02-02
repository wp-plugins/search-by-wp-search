<?php

class WPSearchUtils {

    public static function isActive() {
        $options = WPSearchOptions::getOptions();
        if ($options['api_key']) {
            return true;
        } else {
            return false;
        }
    }

    public static function isSearchPage() {
        if (is_search()) {
            return true;
        }
        global $post;
        if (has_shortcode($post->post_content, 'wpsearch')) {
            return true;
        }
        return false;
    }

    public static function getDefaultFacets() {
        $availableFacets = self::getAvailableFacets();
        $facets = array();
        $options = WPSearchOptions::getOptions();
        if ($options['search_page_facets']['enabled']) {
            $facetsOrdered = $options['search_page_facets']['enabled'];
            unset($facetsOrdered['placebo']);

            foreach ($facetsOrdered as $facet => $title) {
                if ($availableFacets[$facet]) {
                    $facets[$facet] = $availableFacets[$facet];
                }
            }
            return $facets;
        }
        return $availableFacets;
    }

    public static function getAvailableFacets() {
        global $wpdb;
        $taxonomies = (array) get_taxonomies(array('public' => true), 'objects');
        $options = WPSearchOptions::getOptions();

        $availableFacets = array();
        $availableFacets['post_date'] = array("title" => __('Date range', 'wpsearch'), 'type' => 'daterange', 'facet' => 'post_date', 'order' => $options['facet_ordering_post_type']);
        $availableFacets['author'] = array("title" => __('Author', 'wpsearch'), 'type' => 'author', 'facet' => 'author');
        $availableFacets['post_type'] = array("title" => __('Post type', 'wpsearch'), 'type' => 'post_type', 'facet' => 'post_type');
        foreach ($taxonomies as $key => $taxonomy) {
            $availableFacets[$taxonomy->name] = array("title" => $taxonomy->labels->name,
                'type' => 'taxonomy',
                'facet' => $taxonomy->name,
                'hierarchical' => $taxonomy->hierarchical,
                'order' => $options['facet_ordering_' . $taxonomy->name]);
        }

        $availableFacets['post_date_year'] = array("title" => __('Year', 'wpsearch'), 'type' => 'year', 'facet' => 'post_date_year');
        return $availableFacets;
    }

    public static function getAvailableFacetsTitles() {
        $availableFacets = self::getAvailableFacets();
        $facetTitles = array();
        foreach ($availableFacets as $facetField => $facet) {
            $facetTitles[$facet['facet']] = $facet['title'];
        }
        return $facetTitles;
    }

    public static function getAvailablePostTypes() {
        $postTypes = get_post_types(array('public' => true), 'objects', 'and');
        return $postTypes;
    }

    public static function getAvailablePostTypesNames() {
        $postTypes = get_post_types(array('public' => true), 'names', 'and');
        return array_values($postTypes);
    }

    public static function getDefaultPostTypes() {
        return self::getAvailablePostTypesNames();
    }

    public static function getTaxonomies() {
        $taxonomies = (array) get_taxonomies(array('public' => true), 'names');

        return $taxonomies;
    }

    public static function getLevel(&$results, $id) {
        $result = $results[$id];
        if (!isset($results[$id]['level'])) {
            $parentLevel = self::getLevel($results, $result['parent']);
            $results[$id]['level'] = $parentLevel + 1;
        }
        return $results[$id]['level'];
    }

    public static function getTermsLevel($taxonomy) {
        $terms = get_terms($taxonomy, 'orderby=id&hide_empty=0');
        $results = array();
        //
        foreach ($terms as $term) {
            $results[$term->term_id] = array("parent" => $term->parent);
            if ($term->parent == 0) {
                $results[$term->term_id]['level'] = 0;
            }
        }
        foreach ($results as $id => &$result) {
            if (!isset($result['level'])) {
                self::getLevel($results, $id);
            }
        }
        return $results;
    }

    public static function orderTerms($terms, $ordering = "count", $fillLevels = false) {
        
        if (!$terms) {
            return array();
        }
        $terms = apply_filters('wpsearch_pre_order_terms', $terms,$ordering);
        if ($ordering == "alphabetical") {
            usort($terms, create_function('$a, $b', 'return strcmp(strtolower($a["title"]),strtolower($b["title"]));'));
        } elseif ($ordering == "alphabeticalreverse") {
            usort($terms, create_function('$a, $b', 'return strcmp(strtolower($b["title"]),strtolower($a["title"]));'));
        } elseif ($ordering == "hierarchical") {
            $list = array();
            foreach ($terms as $term) {
                $list[$term['parent']][] = $term;
            }
            $tree = self::buildTree($list, $list[0], $fillLevels);
            $terms = self::normalaiseTree($tree);
        } else {
            usort($terms, create_function('$a, $b', ' return intval($b["count"])-intval($a["count"]);'));
        }
        $terms = apply_filters('wpsearch_post_order_terms', $terms);
        return $terms;
    }

    public static function buildTree(&$list, $parent, $fillLevels, $level = 0) {
        $tree = array();
        foreach ($parent as $item) {
            if (isset($list[$item['id']])) {
                $item['children'] = self::buildTree($list, $list[$item['id']], $fillLevels, $level + 1);
            }
            if ($fillLevels) {
                $item['level'] = $level;
            }
            $tree[] = $item;
        }
        // $tree = self::formatTree($tree);
        usort($tree, create_function('$a, $b', ' return intval($b["count"])-intval($a["count"]);'));
        return $tree;
    }

    public static function normalaiseTree($tree) {
        $normalisedTree = array();
        foreach ($tree as $item) {
            if ($item['children']) {
                $children = $item['children'];
                unset($item['children']);
                $normalisedTree[] = $item;
                $normalisedTree = array_merge($normalisedTree, self::normalaiseTree($children));
            } else {
                $normalisedTree[] = $item;
            }
        }
        return $normalisedTree;
    }

    public static function formatTree($array) {
        usort($array, create_function('$a, $b', ' if($a["type"] == "num" && $b["type"] == "num"){
				$bval = filter_var($b["title"], FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION);
				$aval = filter_var($a["title"], FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION);
				return intval(10000*((float)$bval-(float)$aval));}elseif($a["type"] == "numdesc" && $b["type"] == "numdesc"){
				$bval = filter_var($b["title"], FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION);
				$aval = filter_var($a["title"], FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION);
				return intval(10000*((float)$aval-(float)$bval));}else{return strcmp(strtolower($a["title"]),strtolower($b["title"]));}'));
        return $array;
    }

    public static function markersUniqLat($markers) {
        $cordinateDelta = 0.00001;
        $markersArray = array();
        $rands = array();
        foreach ($markers as $key => $marker) {
            $ok = false;
            $latitude = $marker["latitude"];
            $longitude = $marker["longitude"];
            if (!isset($markersArray[(string) $latitude]) || !in_array((string) $longitude, $markersArray[(string) $latitude])) {
                $markersArray[(string) $latitude][] = $longitude;
                continue;
            }

            foreach ($rands as $rand) {
                $latitude2 = $latitude + ($rand[0] * $cordinateDelta);
                $longitude2 = $longitude + ($rand[1] * $cordinateDelta);

                if (!isset($markersArray[(string) $latitude2]) || !in_array((string) $longitude2, $markersArray[(string) $latitude2])) {
                    $markers[$key]["latitude"] = $latitude2;
                    $markers[$key]["longitude"] = $longitude2;
                    $markersArray[(string) $latitude2][] = (string) $longitude2;

                    $ok = true;
                    break;
                }
            }
            if ($ok) {
                continue;
            }
            while (true) {
                $rand = rand(-100, 100);
                $rand2 = rand(-100, 100);
                $rands[] = array($rand, $rand2);
                $latitude2 = $latitude + ($rand * $cordinateDelta);
                $longitude2 = $longitude + ($rand2 * $cordinateDelta);
                if (!isset($markersArray[(string) $latitude2]) || !in_array((string) $longitude2, $markersArray[(string) $latitude2])) {
                    $markers[$key]["latitude"] = $latitude2;
                    $markers[$key]["longitude"] = $longitude2;
                    $markersArray[(string) $latitude2][] = (string) $longitude2;
                    break;
                }
                if (count($rand) > 100) {
                    exit;
                }
            }
        }

        return $markers;
    }

    public static function getUniqLat($latitude, $longitude, $arr) {
        $latitudeor = $latitude;
        $longitudeor = $longitude;

        while (true) {

            if (isset($arr[(string) $latitude]) && in_array((string) $longitude, $arr[(string) $latitude])) {
                $rand = rand(-100, 100);
                $rand2 = rand(-100, 100);
                $latitude = $latitudeor + ($rand * 0.00001);
                $longitude = $longitudeor + ($rand2 * 0.00001);
            } else {
                return array("latitude" => $latitude, "longitude" => $longitude);
            }
        }
    }

    public static function formatDate($thedate) {
        $datere = '/(\d{4}-\d{2}-\d{2})\s(\d{2}:\d{2}:\d{2})/';
        $replstr = '${1}T${2}Z';
        return preg_replace($datere, $replstr, $thedate);
    }

    public static function getBaseUrl() {
        $baseUrl = 'http';
        if ($_SERVER["HTTPS"] == "on") {
            $pageURL .= "s";
        }
        $baseUrl .= "://";
        if ($_SERVER["SERVER_PORT"] != "80") {
            $baseUrl .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
        } else {
            $baseUrl .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
        }
        $pos = strpos($baseUrl, "?");
        $sep = "?";
        if ($pos !== false) {
            $sep = "&";
        }
        $baseUrl .= $sep;
        $baseUrl = self::fixUrl($baseUrl);
        return $baseUrl;
    }

    public static function fixUrl($url) {
        while (strpos($url, "&&") !== false) {
            $url = str_replace("&&", "&", $url);
        }
        while (strpos($url, "?&") !== false) {
            $url = str_replace("?&", "?", $url);
        }
        if (substr($url, -1) == "&") {
            $url = substr($url, 0, -1);
        }
        $url = str_replace("%5B0%5D", "[]", $url);

        return $url;
    }

    public static function getFirstImage($postID = 0, $width = 60, $height = 60, $img_script = '') {
        global $wpdb;
        if ($postID > 0) {

            // select the post content from the db

            $sql = 'SELECT post_content FROM ' . $wpdb->posts . ' WHERE id = ' . $wpdb->escape($postID);
            $row = $wpdb->get_row($sql);
            $the_content = $row->post_content;
            if (strlen($the_content)) {

                // use regex to find the src of the image

                preg_match("/<img src\=('|\")(.*)('|\") .*( |)\/>/", $the_content, $matches);
                if (!$matches) {
                    preg_match("/<img class\=\".*\" src\=('|\")(.*)('|\") .*( |)\/>/U", $the_content, $matches);
                }
                if (!$matches) {
                    preg_match("/<img class\=\".*\" title\=\".*\" src\=('|\")(.*)('|\") .*( |)\/>/U", $the_content, $matches);
                }

                $the_image = '';
                $the_image_src = $matches[2];
                $frags = preg_split("/(\"|')/", $the_image_src);
                if (count($frags)) {
                    $the_image_src = $frags[0];
                }

                // if an image isn't found yet
                if (!strlen($the_image_src)) {
                    $attachments = get_children(array('post_parent' => $postID, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => 'ASC', 'orderby' => 'menu_order ID'));

                    if (count($attachments) > 0) {
                        $q = 0;
                        foreach ($attachments as $id => $attachment) {
                            $q++;
                            if ($q == 1) {
                                $thumbURL = wp_get_attachment_image_src($id, $args['size']);
                                $the_image_src = $thumbURL[0];
                                break;
                            } // if first image
                        } // foreach
                    } // if there are attachments
                } // if no image found yet
                // if src found, then create a new img tag

                if (strlen($the_image_src)) {
                    if (strlen($img_script)) {

                        // if the src starts with http/https, then strip out server name

                        if (preg_match("/^(http(|s):\/\/)/", $the_image_src)) {
                            $the_image_src = preg_replace("/^(http(|s):\/\/)/", '', $the_image_src);
                            $frags = split("\/", $the_image_src);
                            array_shift($frags);
                            $the_image_src = '/' . join("/", $frags);
                        }
                        $the_image = '<img alt="" src="' . $img_script . $the_image_src . '" />';
                    } else {
                        $the_image = '<img alt="" src="' . $the_image_src . '" width="' . $width . '" height="' . $height . '" />';
                    }
                }
                return $the_image_src;
            }
        }
    }

  

    public static function pluginUrl() {
        return plugin_dir_url("") . 'search-by-wp-search/';
    }

    public static function pluginDir() {
        return plugin_dir_path(__FILE__);
    }

    public static function getResetUrl() {
        $baseUrl = WPSearchUtils::getBaseUrl();
        $baseUrl = WPSearchUtils::fixUrl($baseUrl);

        $parts = parse_url($baseUrl);
        $query = "";
        $data = array();
        if ($parts['query']) {
            parse_str($parts['query'], $output);
            $allowedParams = array('s', 'page_id', 'p');
            foreach ($allowedParams as $field) {
                if (isset($output[$field])) {
                    $data[$field] = $output[$field];
                }
            }
            $query = http_build_query($data);
        }

        $url = $parts['scheme'] . "://" . $parts['host'] . $parts['path'];
        if ($query) {
            $url .= "?" . $query;
        }
        return $url;
    }

    public static function array_map($function, $array) {
        $new = array();
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $new[$key] = self::array_map($function, $val);
            } else {
                $new[$key] = call_user_func($function, $val);
            }
        }
        return $new;
    }

    public static function saveErrorLog($data) {
        global $wpdb;
        $defaults = array(
            //'log_date' => current_time('mysql'),
            'error_message' => '',
            'params' => '',
            'url' => ''
        );

        $data = array_merge($defaults, $data);
        $log_errors = ini_get('log_errors');
        ini_set('log_errors', 1);
        ini_set('error_log', WP_CONTENT_DIR . '/debug.log');

        error_log(print_r($data, 1));
        ini_set('log_errors', $log_errors);

        /*
         * $options = WPSearchOptions::getOptions();
          if ($options['error_logging']) {
          $wpdb->insert($wpdb->prefix . 'wpsearch_log', $data);
          }
         */
    }

}
