<?php

class WPSearchIndexer {

    public function handleSave($postID) {
        global $wpsearch;
        $doc = $this->buildDocument($postID);
        if ($doc) {
            $apiClient = $wpsearch->getWPSearchApiClient();
            $response = $apiClient->index($doc);
        }
    }

    public static function handleStatusChange($postID) {
        global $wpsearch;
        $postInfo = get_post($postID);
        if (($_POST['prev_status'] == 'publish' || $_POST['original_post_status'] == 'publish') && ($postInfo->post_status == 'draft' || $postInfo->post_status == 'private')) {
            $apiClient = $wpsearch->getWPSearchApiClient();
            $response = $apiClient->delete($postID);
        }
    }

    public static function handleDelete($postID) {
        global $wpsearch;
        $apiClient = $wpsearch->getWPSearchApiClient();
       
        $response = $apiClient->delete($postID);
    }

    public function handleTermSave($termId, $ttId, $taxonomy) {
        global $wpsearch;
        $term = get_term($termId, $taxonomy);
        if ($term) {
            $apiClient = $wpsearch->getWPSearchApiClient();
            $response = $apiClient->indexTerm($term);
        }
    }

    public function handleTermTaxonomyEdited($ttIds) {
        global $wpsearch, $wpdb;
        if (!$ttIds) {
            return;
        }
        $terms2 = $wpdb->get_results("select term_id,taxonomy,term_taxonomy_id from wp_term_taxonomy where term_taxonomy_id in (" . implode(",", $ttIds) . ")");
        $terms = array();
        foreach ($terms2 as $term) {
            //$this->handleTermSave($term->term_id, $term->term_taxonomy_id, $term->taxonomy);
            $terms[] = get_term($term->term_id, $term->taxonomy);
        }
        if ($terms) {
            $apiClient = $wpsearch->getWPSearchApiClient();
            $return = $apiClient->bulkIndexTerms($terms);
            if (is_wp_error($return)) {
                $response["error"] = $return->get_error_message();
            }
            return $response;
        }
    }

    public static function handleTermDelete($termId, $ttId, $taxonomy) {
        global $wpsearch;
        $apiClient = $wpsearch->getWPSearchApiClient();
        $response = $apiClient->deleteTerm($termId);
    }

    public static function isIndexable($postInfo) {
        global $wpsearch;
        if (is_numeric($postInfo)) {
            $postInfo = get_post($postInfo);
        }
        $options = WPSearchOptions::getOptions();
        $exclude_post_ids = $options['exclude_post_ids'];
        if ($exclude_post_ids) {
            $exclude_post_ids = explode(",", $exclude_post_ids);
            if (in_array($postInfo->ID, $exclude_post_ids)) {
                return false;
            }
        }

        $index_post_types = $options['index_post_types'];
        if ($index_post_types && (!$index_post_types[$postInfo->post_type] || $index_post_types[$postInfo->post_type] != 1)) {
            return false;
        }
        if ($postInfo->post_type == 'revision' || in_array($postInfo->post_status, array('auto-draft', 'draft', 'inherit','trash'))) {
            return false;
        }
        return true;
    }

    public static function buildDocument($postInfo) {

        global $wpdb, $wp_rewrite;
        $wp_rewrite = new WP_Rewrite(); //fix for Call to a member function get_page_permastruct() on a non-object 
        $doc = NULL;
        if (is_numeric($postInfo)) {
            $postInfo = get_post($postInfo);
        }
        if (!self::isIndexable($postInfo)) {
            return $doc;
        }  

        if ($postInfo) {

            $doc = array();
            $doc['id'] = $postInfo->ID;
            $doc['permalink'] = get_permalink($postInfo->ID);
            $doc['post_title'] = html_entity_decode(strip_tags($postInfo->post_title), ENT_QUOTES, "UTF-8");
            $doc['post_content'] = html_entity_decode(strip_tags(do_shortcode($post->post_content)), ENT_QUOTES, "UTF-8");
            $doc['post_excerpt'] = html_entity_decode(strip_tags($post->post_excerpt), ENT_QUOTES, "UTF-8");
            $doc['author'] = $postInfo->post_author;
            $doc['post_type'] = $postInfo->post_type;
            $doc['post_date'] = $postInfo->post_date;
            $doc['post_modified'] = $postInfo->post_modified;
            $year = (int) date("Y", strtotime($postInfo->post_date));
            if ($year > 0) {
                $doc['post_date_year'] = $year;
            }

            $image = NULL;
            if (current_theme_supports('post-thumbnails') && has_post_thumbnail($postInfo->ID)) {
                $image = wp_get_attachment_url(get_post_thumbnail_id($postInfo->ID));
            }

            if ($image) {
                $doc['image'] = $image;
            }

            $categories = get_the_category($postInfo->ID);
            if (!$categories == NULL) {
                $fieldVals = array();
                foreach ($categories as $term) {
                    $fieldVals[] = $term->cat_ID;
                    $ancestors = get_ancestors($term->cat_ID, 'category');
                    if ($ancestors) {
                        $fieldVals = array_merge($fieldVals, $ancestors);
                    }
                }
                $doc['category'] = $fieldVals;
            }

            $tags = get_the_tags($postInfo->ID);
            if (!$tags == NULL) {
                $fieldVals = array();
                foreach ($tags as $term) {
                    $fieldVals[] = $term->term_id;
                }
                $doc['post_tag'] = $fieldVals;
            }

            // custom taxonomies
            $taxonomies = WPSearchUtils::getTaxonomies();
            foreach ($taxonomies as $taxonomy) {
                $terms = get_the_terms($postInfo->ID, $taxonomy);
                $fieldVals = array();
                if ((array) $terms === $terms) {
                    foreach ($terms as $term) {
                        $fieldVals[] = $term->term_id;
                        $ancestors = get_ancestors($term->term_id, $taxonomy);
                        if ($ancestors) {
                            $fieldVals = array_merge($fieldVals, $ancestors);
                        }
                    }
                }
                if ($fieldVals) {
                    $doc[$taxonomy] = array_unique($fieldVals);
                }
            }

            // custom fields
            $customFields = get_post_custom($postInfo->ID);
            foreach ($customFields as $key => $values) {
                if (substr($key, 0, 1) != "_") {
                    if ($values) {
                        if (!is_array($values)) {
                            $values = array($values);
                        }
                        $valArray = array();
                        foreach ($values as $val) {
                            if ($val) {
                                $valArray[] = $val;
                            }
                        }
                        if ($valArray) {
                            $doc[$key] = $valArray;
                        }
                    }
                }
            }
            if (function_exists('wpml_get_language_information')) {
                $wpmlInfo = wpml_get_language_information($postInfo->ID);
                if ($wpmlInfo['locale']) {
                    $doc['language'] = $wpmlInfo['locale'];
                }
            }
            if (function_exists('pll_get_post_language')) {
                $lang = pll_get_post_language($postInfo->ID, 'locale');
                if ($lang) {
                    $doc['language'] = $lang;
                }
            }

            $doc = apply_filters('wpsearch_post_build_document', $doc);
        }

        return $doc;
    }

    public function indexAll($lastId = 0, $indexAll = false) {
        set_time_limit(30);
        global $wpdb, $wpsearch;
        $documents = array();
        $options = WPSearchOptions::getOptions();
        if ($options['batch_size']) {
            $batchsize = (int) $options['batch_size'];
        }
        if (!$batchsize || $batchsize < 0) {
            $batchsize = 100;
        }

        $lastId = (int) $lastId;

        $response = array();
        $index_post_types = $options['index_post_types'];

        $postTypes = array();
        if ($index_post_types) {
            $postTypes = array_keys($index_post_types);
        }
        $query = "SELECT max(ID) as max FROM $wpdb->posts where post_type!='revision' and post_status not in('auto-draft','draft','inherit') ";
        if ($postTypes) {
            $query .= " and post_type in ('" . implode("','", $postTypes) . "') ";
        }
        $maxPostId = $wpdb->get_results($query);
        $maxPostId = $maxPostId[0]->max;
        $apiClient = $wpsearch->getWPSearchApiClient();

        while (true) {
            $query = "SELECT ID FROM $wpdb->posts WHERE ID >$lastId and post_type!='revision' and post_status not in('auto-draft','draft','inherit') ";

            if ($postTypes) {
                $query .= " and post_type in ('" . implode("','", $postTypes) . "') ";
            }
            $query .= "  ORDER BY ID limit  $batchsize;";


            $posts = $wpdb->get_results($query);
            foreach ($posts as $postId) {
                $postID = $postId->ID;
                $doc = $this->buildDocument($postID);
                if (!$doc) {
                    continue;
                }
                $documents[] = $doc;
                $last = $postID;
            }
            $error = "";
            if ($documents) {
                $return = $apiClient->bulkIndex($documents);
                if (is_wp_error($return)) {
                    if (!$indexAll) {
                        $response["error"] = $return->get_error_message();
                        return $response;
                    } else {
                        return false;
                    }
                }
            }
            if (!$indexAll) {
                $percent = (floatval($last) / floatval($maxPostId)) * 100;

                $response["lastId"] = $last;
                $response["percent"] = $percent;

                if ($last === $maxPostId) {
                    $response["percent"] = 100;
                    $response["end"] = true;
                } else {
                    $response["end"] = false;
                }
                return $response;
            }
            $lastId = $last;
            if ($lastId === $maxPostId) {
                return true;
            }
        }
    }

    public function indexStructure() {
        global $wpdb, $wpsearch;
        $response = array();

        $postTypesOriginal = get_post_types(array(), 'objects');
        $taxonomiesOriginal = (array) get_taxonomies(array(), 'objects');
        $taxonomiesNames = (array) get_taxonomies(array(), 'names');

        $termsOriginal = get_terms($taxonomiesNames, 'orderby=id&hide_empty=0');

        $postTypes = array();
        foreach ($postTypesOriginal as $key => $doc) {
            $document = array();
            $document['label'] = (string) $doc->label;
            $document['name'] = (string) $doc->name;
            $document['public'] = (int) $doc->public;
            $document['hierarchical'] = (int) $doc->hierarchical;
           $postTypes[$key] = $document;
        }
        $taxonomies = array();
        foreach ($taxonomiesOriginal as $key => $doc) {
            $document = array();
            $document['label'] = (string) $doc->label;
            $document['name'] = (string) $doc->name;
            $document['public'] = (int) $doc->public;
            $document['hierarchical'] = (int) $doc->hierarchical;
            $taxonomies[$key] = $document;
        }
        $terms = array();
        foreach ($termsOriginal as $key => $doc) {
            $document = array();
            $document['term_id'] = (int) $doc->term_id;
            $document['name'] = (string) $doc->name;
            $document['slug'] = (string) $doc->slug;
            $document['taxonomy'] = (string) $doc->taxonomy;
            $document['term_taxonomy_id'] = (int) $doc->term_taxonomy_id;
            $document['parent'] = (int) $doc->parent;
            $terms[$key] = $document;
        }

        /* $metaList = $wpdb->get_results("SELECT distinct meta_key FROM $wpdb->postmeta where meta_key not like '\_%' ", ARRAY_A);
          $metaKeys = array();
          if ($metaList) {
          foreach ($metaList as $metaRow) {
          $metaKeys[] = $metaRow['meta_key'];
          }
          } */

        $structure = array('post_types' => $postTypes, 'taxonomies' => $taxonomies, 'terms' => $terms);
        $error = "";
        $apiClient = $wpsearch->getWPSearchApiClient();
        $return = $apiClient->structure($structure);
        if (is_wp_error($return)) {
            $response["error"] = $return->get_error_message();
        }

        return $response;
    }

}
