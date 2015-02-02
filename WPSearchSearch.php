<?php

class WPSearchSearch {

    public function search($params) {
        global $wpsearch;
        if (!$params['size']) {
            $params['size'] = $params['page_size'];
        }
        if (!$params['from']) {
            $current = (isset($params['page'])) ? $params['page'] : 1;
            $params['from'] = ($current - 1) * $params['size'];
        }
        $apiClient = $wpsearch->getWPSearchApiClient();
        $result = $apiClient->search($params);
        if (is_wp_error($result)) {
            return $result;
        }
        if (!$result['response']) {
            return array();
        }
        $result = $result['response'];

        $response = array();
        $response['total'] = $result['total'];
        $response['results'] = $result['results'];
        $response['facets'] = array();

        $options = WPSearchOptions::getOptions();

        if ($result['facets']) {
            $facetOut = array();

            foreach ($result['facets'] as $facetName => $facet) {
                if (!$params['facets'][$facetName]) {
                    continue;
                }
                $facetInfo = array();
                $facetInfo['type'] = $params['facets'][$facetName]['type'];
                if (isset($params['facets'][$facetName]['title'])) {
                    $facetInfo['title'] = $params['facets'][$facetName]['title'];
                } else {
                    $facetInfo['title'] = ucwords($facetName);
                }

                $termsCount = array();
                foreach ($facet['terms'] as $term) {
                    $termsCount[$term['term']] = $term['count'];
                }

                if ($params['facets'][$facetName]['type'] == 'taxonomy') {
                    $taxonomy = get_taxonomy($facetName);

                    $terms = get_terms($facetName, array("hide_empty" => false, "include" => array_keys((array) $termsCount)));
                    $termsNew = array();
                    foreach ($terms as $term) {
                        $termsNew[$term->term_id] = $term;
                    }
                    $terms = $termsNew;

                    $facetItems = array();
                    foreach ($termsCount as $facetTerm => $facetCount) {

                        $termId = $facetTerm;
                        if (!isset($termsNew[$termId])) {
                            continue;
                        }
                        $facetItem = array();
                        $facetItem['count'] = $facetCount;
                        $facetItem['id'] = @$terms[$termId]->term_id;
                        $facetItem['slug'] = @$terms[$termId]->slug;
                        $facetItem['parent'] = @$terms[$termId]->parent;
                        $facetItem['title'] = @$terms[$termId]->name;
                        $facetItem['val'] = $facetItem['id'];
                        if ($params['where'][$facetName] && in_array($facetItem['id'], (array) $params['where'][$facetName]['values'])) {
                            $response['existactivefacet'] = true;
                            $facetItem['active'] = true;
                        }
                        $facetItems[$facetItem['id']] = $facetItem;
                    }
                    $defaultOrdering = $options['facet_ordering_' . $taxonomy->name];
                    $facetInfo['data'] = WPSearchUtils::orderTerms($facetItems, $defaultOrdering, true);
                } elseif ($params['facets'][$facetName]['type'] == 'year') {

                    $facetItems = array();
                    foreach ($termsCount as $facetTerm => $facetCount) {

                        $termId = $facetTerm;
                        $facetItem = array();
                        $facetItem['count'] = $facetCount;
                        $facetItem['id'] = $facetTerm;
                        $facetItem['slug'] = $facetTerm;
                        $facetItem['parent'] = 0;
                        $facetItem['title'] = $facetTerm;
                        $facetItem['val'] = $facetTerm;
                        if ($params['where'][$facetName] && in_array($facetItem['id'], (array) $params['where'][$facetName]['values'])) {
                            $response['existactivefacet'] = true;
                            $facetItem['active'] = true;
                        }
                        $facetItems[$facetItem['id']] = $facetItem;
                    }
                    $defaultOrdering = $options['facet_ordering_post_date_year'];
                    $facetInfo['data'] = WPSearchUtils::orderTerms($facetItems, $defaultOrdering, true);
                } elseif ($params['facets'][$facetName]['type'] == 'post_type') {

                    $terms = WPSearchUtils::getAvailablePostTypes();

                    $facetItems = array();
                    foreach ($termsCount as $facetTerm => $facetCount) {

                        $termId = $facetTerm;
                        if (!isset($terms[$termId])) {
                            continue;
                        }
                        $facetItem = array();
                        $facetItem['count'] = $facetCount;
                        $facetItem['id'] = @$terms[$termId]->name;
                        $facetItem['slug'] = @$terms[$termId]->name;
                        $facetItem['parent'] = 0;
                        $facetItem['title'] = @$terms[$termId]->labels->name;
                        $facetItem['val'] = $facetItem['id'];
                        if ($params['where'][$facetName] && in_array($facetItem['id'], (array) $params['where'][$facetName]['values'])) {
                            $response['existactivefacet'] = true;
                            $facetItem['active'] = true;
                        }
                        $facetItems[$facetItem['id']] = $facetItem;
                    }
                    $defaultOrdering = $options['facet_ordering_post_type'];

                    $facetInfo['data'] = WPSearchUtils::orderTerms($facetItems, $defaultOrdering, true);
                } elseif ($params['facets'][$facetName]['type'] == 'author') {
                    $authorIds = array();

                    $authors = get_users(array("include" => array_keys($termsCount), "fields" => "ids"));
                    $terms = array();
                    foreach ($authors as $authorId) {
                        $author = get_userdata($authorId);
                        if ($author->first_name && $author->last_name) {
                            $name = "$author->first_name $author->last_name";
                        } else {
                            $name = $author->display_name;
                        }
                        $terms[$authorId] = $name;
                    }
                    $facetItems = array();
                    foreach ($termsCount as $facetTerm => $facetCount) {

                        $termId = $facetTerm;
                        if (!isset($terms[$termId])) {
                            continue;
                        }
                        $facetItem = array();
                        $facetItem['count'] = $facetCount;
                        $facetItem['id'] = $termId;
                        $facetItem['slug'] = @$terms[$termId];
                        $facetItem['parent'] = 0;
                        $facetItem['title'] = @$terms[$termId];
                        $facetItem['val'] = $facetItem['id'];
                        if ($params['where'][$facetName] && in_array($facetItem['id'], (array) $params['where'][$facetName]['values'])) {
                            $response['existactivefacet'] = true;
                            $facetItem['active'] = true;
                        }
                        $facetItems[$facetItem['id']] = $facetItem;
                    }
                    $defaultOrdering = $options['facet_ordering_author'];

                    $facetInfo['data'] = WPSearchUtils::orderTerms($facetItems, $defaultOrdering, true);
                }
                $facetInfo['data'] = array_values((array) $facetInfo['data']);
                $facetOut[$facetName] = $facetInfo;
            }
            $response['facets'] = $facetOut;
        }
        $response = apply_filters('wpsearch_post_results_from_api', $response);
        return $response;
    }

}
